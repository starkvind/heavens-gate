<?php
http_response_code(410);
$pageSect = 'Herramientas';
$pageTitle2 = 'Simulador de combate retirado';
if (function_exists('setMetaFromPage')) {
    setMetaFromPage('Simulador de combate retirado | Heaven\'s Gate', 'El simulador de combate ya no forma parte del sitio activo.', null, 'website');
}
include('app/partials/main_nav_bar.php');
?>
<div class="bioBody">
    <fieldset class="bioSeccion">
        <legend>Simulador de combate retirado</legend>
        <p>Esta herramienta ha sido retirada del sitio activo.</p>
    </fieldset>
</div>
