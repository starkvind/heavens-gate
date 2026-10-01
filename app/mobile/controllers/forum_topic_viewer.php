<?php

$metaTitle = "Lector del foro | Heaven's Gate";
$metaDescription = 'Lector móvil de temas guardados del foro.';
$pageSect = 'Herramientas';

require_once __DIR__ . '/../../helpers/forum_item_embed.php';

if (!defined('HG_FORUM_TOPIC_VIEWER_EMBED')) {
    define('HG_FORUM_TOPIC_VIEWER_EMBED', true);
}
?>
<link rel="stylesheet" href="/assets/css/hg-forum-item-embed.css">
<section class="hg-mobile-section hg-mobile-tool-heading">
    <h1>Lector del foro</h1>
</section>

<section class="hg-mobile-section hg-mobile-tool hg-mobile-forum-viewer-tool">
<?php
ob_start();
include(__DIR__ . '/../../tools/forum_topic_viewer_tool.php');
$forumViewerHtml = (string)ob_get_clean();
echo hg_forum_expand_item_bbcode($link, $forumViewerHtml);
?>
</section>

