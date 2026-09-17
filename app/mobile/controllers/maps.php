<?php

include_once(__DIR__ . '/../../helpers/pretty.php');
include_once(__DIR__ . '/../../helpers/maps.php');
require_once(__DIR__ . '/../../domains/maps/queries.php');

$metaTitle = "Mapas | Heaven's Gate";
$metaDescription = 'Mapas interactivos móviles.';
$pageSect = 'Mapas';

$routeKey = hg_request_route($hgRequest);

if ($routeKey === 'maps_detail') {
    $rawPoiId = hg_request_param($hgRequest, 'poi');
    $resolvedPoiId = 0;

    if ($rawPoiId !== '' && isset($link) && ($link instanceof mysqli)) {
        $resolvedPoiId = hg_maps_query_resolve_poi_id($link, $rawPoiId);
    }

    $hgRequest['params']['poi'] = (string)$resolvedPoiId;
    include(__DIR__ . '/../../controllers/maps/maps_detail.php');
    return;
}

include(__DIR__ . '/../../controllers/maps/maps_main.php');
