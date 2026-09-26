<?php
require_once __DIR__ . '/../../domains/powers/custom_catalog.php';
require_once __DIR__ . '/../../helpers/power_custom_pages.php';

$config = hg_power_custom_catalog_disciplines($link);
$config['meta_description'] = 'Listado completo de disciplinas en formato extendido.';
$config['intro'] = 'Listado completo de disciplinas, con ficha extendida y preparado para impresión.';

if (hg_request_query_param($hgRequest, 'export') === 'md') {
    $items = hg_power_custom_build_items($link, $config);
    hg_power_custom_markdown_download($config, $items);
}

hg_power_custom_render_full_catalog($link, $config, $hgRequest);
