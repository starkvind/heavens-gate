<?php
require_once __DIR__ . '/../../domains/powers/custom_catalog.php';
require_once __DIR__ . '/../../helpers/power_custom_pages.php';

$config = hg_power_custom_catalog_totems($link);
$config['meta_description'] = 'Listado completo de tótems en formato extendido.';
$config['intro'] = 'Listado completo de tótems, con acceso rápido y ficha completa.';

if (hg_request_query_param($hgRequest, 'export') === 'md') {
    $items = hg_power_custom_build_items($link, $config);
    hg_power_custom_markdown_download($config, $items);
}

hg_power_custom_render_full_catalog($link, $config, $hgRequest);
