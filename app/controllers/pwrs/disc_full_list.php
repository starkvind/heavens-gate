<?php
require_once __DIR__ . '/../../helpers/power_custom_pages.php';

$config = hg_power_custom_catalog_disciplines($link);
$config['meta_description'] = 'Listado completo de disciplinas en formato extendido.';
$config['intro'] = 'Listado completo de disciplinas, con ficha extendida y preparado para impresión.';
hg_power_custom_render_full_catalog($link, $config);
