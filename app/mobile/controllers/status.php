<?php
include_once(__DIR__ . '/../../helpers/public_response.php');
include_once(__DIR__ . '/../../helpers/public_status.php');

$metaTitle = "Estado | Heaven's Gate";
$metaDescription = 'Estado público del archivo.';
$pageSect = 'Estado';

if (!function_exists('hg_mobile_status_h')) {
    function hg_mobile_status_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!isset($link) || !($link instanceof mysqli)) {
    echo '<section class="hg-mobile-section"><h1>Estado</h1><p>No se pudo cargar el estado del archivo.</p></section>';
    return;
}

if (method_exists($link, 'set_charset')) {
    $link->set_charset('utf8mb4');
}

$metrics = hg_public_status_metrics($link);
?>
<section class="hg-mobile-section hg-mobile-status-head">
    <h1>Estado</h1>
    <p>Resumen público del archivo de Heaven's Gate.</p>
    <div class="hg-mobile-stat-grid">
        <?php foreach ($metrics as $label => $value): ?>
            <?php if ($value === null) continue; ?>
            <div class="hg-mobile-stat">
                <strong><?= number_format((int)$value, 0, ',', '.') ?></strong>
                <span><?= hg_mobile_status_h($label) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</section>
