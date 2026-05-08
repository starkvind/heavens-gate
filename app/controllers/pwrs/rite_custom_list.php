<?php
require_once __DIR__ . '/../../helpers/power_custom_pages.php';

$config = hg_power_custom_catalog_rites($link);
hg_power_custom_render($link, $config);
