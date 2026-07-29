<?php

$metaTitle = "Lector del foro | Heaven's Gate";
$metaDescription = 'Lector móvil de temas guardados del foro.';
$pageSect = 'Herramientas';

if (!defined('HG_FORUM_TOPIC_VIEWER_EMBED')) {
    define('HG_FORUM_TOPIC_VIEWER_EMBED', true);
}
?>
<section class="hg-mobile-section hg-mobile-tool-heading">
    <h1>Lector del foro</h1>
</section>

<section class="hg-mobile-section hg-mobile-tool hg-mobile-forum-viewer-tool">
<?php include(__DIR__ . '/../../tools/forum_topic_viewer_tool.php'); ?>
</section>

