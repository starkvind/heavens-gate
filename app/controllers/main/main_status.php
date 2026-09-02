<?php setMetaFromPage("Estado | Heaven's Gate", "Estado público del archivo de Heaven's Gate.", null, 'website'); ?>
<?php
include_once(__DIR__ . '/../../helpers/public_response.php');
include_once(__DIR__ . '/../../helpers/public_status.php');

header('Content-Type: text/html; charset=utf-8');

if (!isset($link) || !($link instanceof mysqli)) {
    hg_public_log_error('main_status', 'missing DB connection');
    hg_public_render_error('Estado no disponible', 'No se pudo cargar el estado general en este momento.');
    return;
}

if (method_exists($link, 'set_charset')) {
    $link->set_charset('utf8mb4');
}

include("app/partials/main_nav_bar.php");

if (!function_exists('hg_status_h')) {
    function hg_status_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$metrics = hg_public_status_metrics($link);

echo "<h2>Estado</h2>";
echo "<p>Resumen público del archivo. Esta página muestra únicamente magnitudes editoriales y no expone información de infraestructura o mantenimiento.</p>";
echo "<fieldset class='renglonPaginaDon'>";
echo "<legend>Archivo de Heaven's Gate</legend>";
foreach ($metrics as $label => $value) {
    if ($value === null) {
        continue;
    }
    echo "<div class='renglonStatusIz'>" . hg_status_h($label) . ":</div>";
    echo "<div class='renglonStatusDe'>" . number_format((int)$value, 0, ',', '.') . "</div>";
}
echo "</fieldset><br/>";
