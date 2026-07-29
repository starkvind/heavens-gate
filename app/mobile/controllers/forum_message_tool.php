<?php

$metaTitle = "Mensajes para foro | Heaven's Gate";
$metaDescription = 'Editor móvil para crear mensajes de foro con avatar, color y formato.';
$pageSect = 'Herramientas';

if (!defined('HG_MOBILE_TOOL_EMBED')) {
    define('HG_MOBILE_TOOL_EMBED', true);
}
?>
<section class="hg-mobile-section hg-mobile-tool-heading">
    <h1>Mensajes para foro</h1>
</section>

<section class="hg-mobile-section hg-mobile-tool hg-mobile-forum-message-tool">
<?php include(__DIR__ . '/../../controllers/tool/forum_avatar_builder.php'); ?>
</section>