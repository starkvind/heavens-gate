<?php

$metaTitle = "Lector del foro | Heaven's Gate";
$metaDescription = 'Lector móvil de temas guardados del foro.';
$pageSect = 'Herramientas';

require_once __DIR__ . '/../../helpers/forum_item_embed.php';

if (!defined('HG_FORUM_TOPIC_VIEWER_EMBED')) {
    define('HG_FORUM_TOPIC_VIEWER_EMBED', true);
}

$hgForumViewerRoot = dirname(__DIR__, 3);
$hgForumViewerCss = '/assets/css/hg-forum-item-embed.css';
$hgForumViewerJs = '/assets/js/forum-item-viewer-embed.js';
$hgForumViewerCssVersion = @filemtime($hgForumViewerRoot . $hgForumViewerCss) ?: 1;
$hgForumViewerJsVersion = @filemtime($hgForumViewerRoot . $hgForumViewerJs) ?: 1;
?>
<link rel="stylesheet" href="<?= htmlspecialchars($hgForumViewerCss, ENT_QUOTES, 'UTF-8') ?>?v=<?= htmlspecialchars((string)$hgForumViewerCssVersion, ENT_QUOTES, 'UTF-8') ?>">
<script src="<?= htmlspecialchars($hgForumViewerJs, ENT_QUOTES, 'UTF-8') ?>?v=<?= htmlspecialchars((string)$hgForumViewerJsVersion, ENT_QUOTES, 'UTF-8') ?>" defer></script>
<section class="hg-mobile-section hg-mobile-tool-heading">
    <h1>Lector del foro</h1>
</section>

<section class="hg-mobile-section hg-mobile-tool hg-mobile-forum-viewer-tool">
<?php
ob_start();
include(__DIR__ . '/../../tools/forum_topic_viewer_tool.php');
$forumViewerHtml = (string)ob_get_clean();
echo hg_forum_expand_item_bbcode($forumViewerHtml);
?>
</section>

