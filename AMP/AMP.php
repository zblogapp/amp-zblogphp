<?php
namespace ZBlogPHP\AMP;

use Lullabot\AMP\AMP as AMPFormatter;
use ZBlogPHP\AMP\Cache\Cache;

require_once dirname(__FILE__) . '/../simple_html_dom.php';
require_once dirname(__FILE__) . '/../vendor/autoload.php';

class AMP {

    /**
     * @var string[]
     */
    public $styles;
    /**
     * @var string
     */
    public $warnings;
    /**
     * @var Cache
     */
    private $cache;

    public function __construct($styles = [])
    {
        $this->styles = $styles;
        $this->cache = new Cache();
    }

    /**
     * Format only by AMPFormatter
     * @param $content
     * @return string[]
     * @throws \Exception
     */
    public static function formatByAMPFormatter ($content) {
        $amp = new AMPFormatter();
        $amp->loadHtml($content);
        return [
            'html' => $amp->convertToAmpHtml(),
            'warnings' => $amp->warningsHumanHtml()
        ];
    }

    /**
     * @param $content
     * @return string
     * @throws \Exception
     */
    public function format ($content) {
        $dom = new \simple_html_dom();
        $dom->load($content);
        $this->formatTags($dom);
        $this->formatHtmlStyles($dom);

        if ($GLOBALS['amp_allow_self_theme_start']) {
            $this->fixIncorrentUrl($dom);
        }

        $this->formatImgSize($dom);
        $html = $dom->innertext;
        $ret = self::formatByAMPFormatter($html);
        $this->warnings = $ret['warnings'];
        return $ret['html'];
    }

    /**
     * Get CSS of proceed HTML
     * @return string
     */
    public function css () {
        $ret = array('');
        foreach ($this->styles as $key => $value) {
            $ret[] = '.' . $key . '{' . $value . '}';
        }
        return implode('', $ret);
    }

    /**
     * Fix incorrect urls
     * @param \simple_html_dom $dom
     */
    protected function fixIncorrentUrl ($dom) {
        $urlAttrs = array('src', 'href');
        foreach ($urlAttrs as $attr) {
            $items = $dom->find("[$attr]");
            foreach ($items as $item) {
                $item->$attr = amp_theme_get_original_url($item->$attr);
            }
        }
    }


    /**
     * Get hash of a style
     * @param $text
     * @return string
     */
    protected function styleHash ($text) {
        $text = md5($text);
        return 's' . substr($text, 0, 6);
    }

    /**
     * Extract all styles to one style
     * @param \simple_html_dom $dom
     * @return \simple_html_dom
     */
    protected function formatHtmlStyles ($dom) {
        $elements = $dom->find('[style]');
        foreach ($elements as $element) {
            $styleName = $this->styleHash($element->style);
            $style = preg_replace('/(width|height):.*?[;|$]/', '', $element->style);
            $this->styles[$styleName] = $style;
            $element->style = null;
            if ($element->class) {
                $element->class .= ' ' . $styleName;
            } else {
                $element->class = $styleName;
            }
        }
        return $dom;
    }

    /**
     * Convert some HTML tags to <span> with style
     * @param \simple_html_dom $dom
     */
    protected function formatTags ($dom) {
        $styles = array(
            'em' => 'font-style:italic;',
            'strong' => 'font-weight:bold;',
            'b' => 'font-weight:bold;',
            'sup' => 'vertical-align:super;font-size:smaller;',
            'sub' => 'vertical-align:sub;font-size: smaller;'
        );
        foreach ($styles as $tag => $style) {
            $items = $dom->find($tag);
            foreach ($items as $item) {
                $item->tag = 'span';
                if ($item->style) {
                    $item->style .= ';' . $style;
                } else {
                    $item->style = $style;
                }
            }
        }
    }

    /**
     * Get all images size
     * @param \simple_html_dom $dom
     */
    function formatImgSize($dom) {
        $client = new \FasterImage\FasterImage();
        $elements = $dom->find('img');
        $queue = array();
        foreach ($elements as $element) {
            $src = $element->src;
            $cache = $this->cache->get('image', $src);
            if ($element->width && $element->height) {
                continue;
            }
            if (!is_null($cache)) {
                $element->width = $cache->content->width;
                $element->height = $cache->content->height;
                continue;
            }
            $queue[$src] = $element;
        }

        try {
            $images = $client->batch(array_keys($queue));
            foreach ($images as $key => $image) {
                var_dump($image);
                if ($image['size'] === 'failed') {
                    $queue[$key]->width = 500;
                    $queue[$key]->height = 500;
                } else {
                    list($width, $height) = $image['size'];
                    $this->cache->set('image', $key, array(
                        'width' => $width,
                        'height' => $height
                    ));
                    $queue[$key]->width = $width;
                    $queue[$key]->height = $height;
                }

            }
        } catch (\Exception $e) {
            foreach ($queue as $src => $value) {
                $value->width = 500;
                $value->height = 500;
            }
        }

    }
}



