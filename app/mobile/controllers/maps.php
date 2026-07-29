<?php

include_once(__DIR__ . '/../../helpers/pretty.php');

$metaTitle = "Mapas | Heaven's Gate";
$metaDescription = 'Mapas interactivos móviles.';
$pageSect = 'Mapas';

$routeKey = trim((string)($_GET['p'] ?? ''));

if ($routeKey === 'maps_detail') {
    $rawPoiId = trim((string)($_GET['id'] ?? ''));
    $resolvedPoiId = 0;

    if ($rawPoiId !== '' && isset($link) && ($link instanceof mysqli)) {
        if (preg_match('/^\d+$/', $rawPoiId)) {
            $resolvedPoiId = (int)$rawPoiId;
        } elseif (function_exists('resolve_pretty_id')) {
            $resolvedPoiId = (int)(resolve_pretty_id($link, 'fact_map_pois', $rawPoiId) ?? 0);
        }
    }

    $_GET['id'] = (string)$resolvedPoiId;
    include(__DIR__ . '/../../controllers/maps/maps_detail.php');
    return;
}

include(__DIR__ . '/../../controllers/maps/maps_main.php');