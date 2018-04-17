{template:header}
<body class="single">
<header>
<h2><a href="{$host}">{$name}</a> <sup>{$subname}</sup></h2>
</header>
<section>
  <section id="main">
    <section>
{if $article.Type==ZC_POST_TYPE_ARTICLE}
{template:post-single}
{else}
{template:post-page}
{/if}
    </section>
  </section>
</section>
{template:footer}
