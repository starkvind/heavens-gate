<?php
http_response_code(410);
$pageSect = 'Herramientas';
$pageTitle2 = 'Archivo de mnemógeno retirado';
if (function_exists('setMetaFromPage')) {
    setMetaFromPage('Archivo de mnemógeno retirado | Heaven\'s Gate', 'El juego de cartas ya no forma parte del sitio activo.', null, 'website');
}
include('app/partials/main_nav_bar.php');
?>
<div class="bioBody">
    <fieldset class="bioSeccion">
        <legend>Archivo de mnemógeno retirado</legend>
        <p>Esta herramienta ha sido retirada del sitio activo.</p>
    </fieldset>
</div>
