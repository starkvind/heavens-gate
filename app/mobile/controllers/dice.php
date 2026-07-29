<?php

$metaTitle = "Tiradados | Heaven's Gate";
$metaDescription = 'Tiradados móvil reutilizando la herramienta publica.';
$pageSect = 'Herramientas';

if (!defined('HG_MOBILE_TOOL_EMBED')) define('HG_MOBILE_TOOL_EMBED', true);
if (!defined('HG_MOBILE_DESKTOP_EMBED')) define('HG_MOBILE_DESKTOP_EMBED', true);
?>
<section class="hg-mobile-section hg-mobile-tool hg-mobile-dice-native">
<?php include(__DIR__ . '/../../controllers/tool/dice_roller.php'); ?>
</section>