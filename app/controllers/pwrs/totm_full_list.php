<?php
require_once __DIR__ . '/../../helpers/power_custom_pages.php';

$config = hg_power_custom_catalog_totems($link);
$config['meta_description'] = 'Listado completo de tótems en formato extendido.';
$config['intro'] = 'Listado completo de tótems, con acceso rápido y ficha completa.';
hg_power_custom_render_full_catalog($link, $config);
