<?php
use ZBlogPHP\AMP\AMP;
use ZBlogPHP\AMP\Cache\Cache;

RegisterPlugin("amp", "ActivePlugin_amp");
DefinePluginFilter('Filter_Plugin_AMP_Template');
DefinePluginFilter('Filter_Plugin_AMP_ViewIndex_Begin');

define('RUNNING_IN_ZBLOGPHP_AMP', true);

/**
 * 是否允许内嵌的amp主题运行
 * 当检测到目前的主题启用了amp_active函数，则自动关闭此功能
 */
$amp_allow_self_theme_start = true;
/**
 * 当前运行模式是否为内嵌的amp主题
 */
$amp_in_self_theme_mode = false;
/**
 * 当前是否运行于amp模式下
 */
$amp_start = false;
/**
 * AMP Cache parameters
 */
$amp_cache_parameter = array();

/**
 * Call stack
 *
 * Navigate to index.php(Filter_Plugin_Index_Begin):
 *  Check QueryString to enter AMP mode: ``amp_Index_Begin_For_Switch_To_amp``
 *  Add <meta> for all pages: ``amp_Index_Begin_For_Header``
 *  If in AMP mode: ``amp_ViewIndex_Begin``
 *    Check is cache available for ViewList: ``amp_viewlist_get_cache``
 *      If available, output cache and exit
 *  Then, Z-BlogPHP will initialize page.
 *
 * If in AMP mode(Filter_Plugin_Index_Begin), or someone called ``amp_active``:
 *  Format all contents if in ViewList: ``amp_ViewList_Template``
 *  Check is cache available for ViewPost: ``amp_ViewPost_Template``
 *     If available, output cache and exit.
 *     If not, format all contents
 *
 * When page finished ():
 *  If in AMP mode, caching the whole page (amp_save_cache)
 */


function amp_fake_autoload () {
    require_once dirname(__FILE__) . '/AMP/AMP.php';
    require_once dirname(__FILE__) . '/Cache/Cache.php';
    require_once dirname(__FILE__) . '/Cache/Drivers/CacheDriver.php';
    require_once dirname(__FILE__) . '/Cache/Drivers/File.php';
}

function ActivePlugin_amp() {
  global $zbp;
  // Useless in AMP
  // Add_Filter_Plugin('Filter_Plugin_Index_Begin', 'amp_Index_Begin_For_API');
  Add_Filter_Plugin('Filter_Plugin_Index_Begin', 'amp_Index_Begin_For_Switch_To_amp');
  Add_Filter_Plugin('Filter_Plugin_Index_Begin', 'amp_Index_Begin_For_Header');
  Add_Filter_Plugin('Filter_Plugin_Post_Call', 'amp_Call_Get_amp_URL');
  Add_Filter_Plugin('Filter_Plugin_Post_Save', 'amp_remove_post_cache');
  Add_Filter_Plugin('Filter_Plugin_Post_Del', 'amp_remove_post_cache');
  Add_Filter_Plugin('Filter_Plugin_Post_Save', 'amp_update_list_cache');
}

function InstallPlugin_amp() {
  global $zbp;
  $zbp->Config('amp')->enable_header_canonical = 1;
  $zbp->Config('amp')->remove_all_plugin_headers = 1;
  $zbp->SaveConfig('amp');
  amp_initialize_amp_page();
  $zbp->template->BuildTemplate();

}
function UninstallPlugin_amp() {}

/**
 * amp功能激活函数
 * 进入此函数，即代表目前启用了amp主题
 * @param bool $allow_other_amp_template
 */
function amp_active ($allow_other_amp_template = false) {
  global $zbp, $amp_start;
  amp_fake_autoload();
  $amp_start = true;
  Add_Filter_Plugin('Filter_Plugin_Zbp_BuildTemplate', 'amp_Zbp_LoadTemplate');
  Add_Filter_Plugin('Filter_Plugin_ViewList_Template', 'amp_ViewList_Template');
  Add_Filter_Plugin('Filter_Plugin_ViewPost_Template', 'amp_ViewPost_Template');
  $GLOBALS['amp_allow_self_theme_start'] = $allow_other_amp_template;
}


function amp_theme_get_original_url ($url) {
  return str_replace('amp/', '', $url);
}

function amp_theme_get_amp_url ($url) {
  global $zbp;
  return str_replace($zbp->host, $zbp->host . 'amp/', $url);
}

/**
 * @param $clazz
 * @param $method
 * @param $args
 * @return mixed
 */
function amp_Call_Get_amp_URL(&$clazz, $method, $args) {
  if ($method === 'ampUrl') {
    $GLOBALS['hooks']['Filter_Plugin_Post_Call']['amp_Call_Get_amp_URL'] = PLUGIN_EXITSIGNAL_RETURN;
    return amp_theme_get_amp_url($clazz->Url);
  } else if ($method === 'OrigUrl') {
    $GLOBALS['hooks']['Filter_Plugin_Post_Call']['amp_Call_Get_amp_URL'] = PLUGIN_EXITSIGNAL_RETURN;
    return amp_theme_get_original_url($clazz->Url);
  }
  return null;
}

function amp_get_canonical_html ($url) {
  return '<link rel="canonical" href="' . htmlspecialchars($url) . '" />';
}

function amp_get_amphtml_html ($url) {
  return '<link rel="amphtml" href="' . htmlspecialchars($url) . '" />';
}

function amp_Index_Begin_For_Header () {
  global $zbp, $amp_start, $amp_in_self_theme_mode;
  if ($amp_start) {
    if ($zbp->Config('amp')->remove_all_plugin_headers == '1') {
      $zbp->header = ''; // 此处必须强制清空，以避免其他插件造成的影响
      $zbp->footer = '';
    }
    if ($amp_in_self_theme_mode) {
      $zbp->header .= amp_get_canonical_html(amp_theme_get_original_url($zbp->fullcurrenturl));
    } else {
      if ($zbp->Config('amp')->enable_header_canonical == '1') {
        $zbp->header .= amp_get_canonical_html($zbp->fullcurrenturl);
      }
    }
  } else {
    if ($zbp->Config('amp')->enable_header_canonical == '1') {
      $zbp->header .= amp_get_amphtml_html(amp_theme_get_amp_url($zbp->fullcurrenturl));
    }
  }
}

function amp_Index_Begin_For_Switch_To_amp() {
  global $zbp, $amp_allow_self_theme_start, $amp_in_self_theme_mode;
  if (!$amp_allow_self_theme_start) return;
  $amp_in_self_theme_mode = true;
  $uri = GetVars('REQUEST_URI', 'SERVER');
  $host = parse_url($zbp->host);
  $checkUri = str_replace($host['path'], '', $uri);
  if (preg_match("/^(index.php\/amp|amp)/", $checkUri)) {
    amp_initialize_amp_page();
  }
}

function amp_initialize_amp_page() {
  global $zbp, $bloghost;
  amp_active(true);
  $bloghost .= 'amp/';
  $zbp->theme = 'amp';
  $zbp->template = $zbp->PrepareTemplate();

  $files = GetFilesInDir($zbp->path . 'zb_system/defend/default/', 'php');
  foreach ($files as $sortname => $fullname) {
    $zbp->template->templates[$sortname] = file_get_contents($fullname);
  }

  $files = GetFilesInDir(dirname(__FILE__) . '/template', 'php');
  foreach ($files as $sortname => $fullname) {
      $zbp->template->templates[$sortname] = file_get_contents($fullname);
  }

  foreach ($GLOBALS['hooks']['Filter_Plugin_AMP_Template'] as $fpname => &$fpsignal) {
    $fpname($zbp->template);
  }

  if (isset($zbp->option['ZC_DEBUG_MODE']) && $zbp->option['ZC_DEBUG_MODE']) {
    $zbp->template->BuildTemplate();
  }

  Add_Filter_Plugin('Filter_Plugin_ViewIndex_Begin', 'amp_ViewIndex_Begin');
}

/**
 * @param \Template $template
 */
function amp_viewlist_template_force_set_template(&$template) {
  $template->SetTemplate('index');
}

/**
 * @param \Template $template
 */
function amp_viewpost_template_force_set_template(&$template) {
  $template->SetTemplate('single');
}

function amp_viewlist_get_cache() {
    global $amp_cache_parameter;
    $args = func_get_args();
    $amp_cache_parameter = array('list', $args);
    amp_check_and_output_cache();
}

function amp_ViewIndex_Begin (&$url) {
  global $zbp;
  Add_Filter_Plugin('Filter_Plugin_ViewList_Template', 'amp_viewlist_template_force_set_template');
  Add_Filter_Plugin('Filter_Plugin_ViewPost_Template', 'amp_viewpost_template_force_set_template');

  // Initialize caches
  Add_Filter_Plugin('Filter_Plugin_ViewList_Begin', 'amp_viewlist_get_cache');

  // Use ViewPost_Template for Caching Post
  // We don't know article id in Begin
  //Add_Filter_Plugin('Filter_Plugin_ViewPost_Begin', 'amp_viewpost_get_cache');

  // not yet implemented
  // Add_Filter_Plugin('Filter_Plugin_ViewSearch_Begin', 'amp_viewpost_template_force_set_template');

  Add_Filter_Plugin('Filter_Plugin_Index_End', 'amp_save_cache');

  $url = amp_theme_get_original_url($url);
  // Register all static template here
  foreach ($GLOBALS['hooks']['Filter_Plugin_AMP_ViewIndex_Begin'] as $fpname => &$fpsignal) {
    $fpname($url);
  }
}

/**
 * @deprecated
 */
function amp_Index_Begin_For_API() {
  $components = array('comment', 'article_viewnum');
  if (!isset($_GET['amp'])) return;
  $component = GetVars('component', 'GET');
  if (!in_array($component, $components)) return;
  return;
}

/**
 * @param string[] $templates
 */
function amp_Zbp_LoadTemplate(&$templates) {
  $templateList = array(
    'amp-comment' => '/components/comment/amp-comment.php',
    'amp-comment-footer' => '/components/comment/amp-comment-footer.php'
  );
  foreach ($templateList as $key => $template) {
    if (!isset($templates[$key])) {
      $templates[$key] = file_get_contents(dirname(__FILE__) . $template);
    }
  }
}

function amp_check_and_output_cache() {
    global $zbp, $amp_cache_parameter;
    if (isset($zbp->option['ZC_DEBUG_MODE']) && $zbp->option['ZC_DEBUG_MODE']) {
        ob_start();
        return;
    }

    $cache = new Cache();
    $item = $cache->get($amp_cache_parameter[0], $amp_cache_parameter[1]);
    if (!is_null($item)) {
        echo $item->content;
        echo '<!-- Cached by Z-BlogPHP AMP Plugin -->';
        RunTime();
        exit;
    }
    ob_start();
}

function amp_save_cache() {
    global $amp_cache_parameter;
    $cache = new Cache();
    $cache->set($amp_cache_parameter[0], $amp_cache_parameter[1], ob_get_contents());
}

/**
 * @param \Template $template
 * @throws \Exception
 */
function amp_ViewList_Template  (&$template) {
  global $zbp;
  $amp = new AMP();
  $articles = $template->GetTags('articles');
  foreach ($articles as $article) {
      $article->Intro = $amp->format($article->Intro);
      $article->Content = $amp->format($article->Content);
  }
  $copyright = $amp->format($template->GetTags('copyright'));
  amp_format_sidebars($amp, $template);
  $template->SetTags('ampstyle', $amp->css());
  $template->SetTags('copyright', $copyright);
}


/**
 * @param \Template $template
 * @throws \Exception
 */
function amp_ViewPost_Template (&$template) {
  global $zbp, $amp_cache_parameter;
  $article = $template->GetTags('article');
  $amp_cache_parameter = array('post', $article->ID);
  amp_check_and_output_cache();
  $amp = new AMP();

  $article->Intro = $amp->format($article->Intro);
  $article->Content = $amp->format($article->Content);
  $copyright = $amp->format($template->GetTags('copyright'));
  amp_format_sidebars($amp, $template);
  $template->SetTags('ampstyle', $amp->css());
  $template->SetTags('copyright', $copyright);
}

/**
 * @param AMP $amp
 * @param \Template $template
 * @throws Exception
 */
function amp_format_sidebars (&$amp, $template) {
  $sidebarNames = array('sidebar', 'sidebar2', 'sidebar3', 'sidebar4', 'sidebar5');
  foreach ($sidebarNames as $sidebarName) {
    $sidebars = $template->GetTags($sidebarName);
    foreach ($sidebars as $sidebar) {
      $sidebar->Content = $amp->format($sidebar->Content);
    }
  }
}

/**
 * @param \Post $post
 */
function amp_remove_post_cache(&$post) {
    amp_fake_autoload();
    $cache = new Cache();
    $cache->remove('post', $post->ID);
    amp_update_list_cache();
}

function amp_update_list_cache() {
    amp_fake_autoload();
    $cache = new Cache();
    $cache->updateListTime();
}