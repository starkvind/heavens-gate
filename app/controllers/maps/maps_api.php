<?php

include_once(__DIR__ . '/../../helpers/maps.php');

hg_maps_require_connection($link, true);

if (function_exists('header_remove')) {
    @header_remove();
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$jsonResponse = static function (array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
};

$action = hg_request_query_param($hgRequest, 'ajax');
if ($action === '') {
    $jsonResponse(['ok' => false, 'error' => 'Accion no indicada.'], 400);
}

$schema = hg_maps_schema_info($link);
$maps = hg_maps_fetch_maps($link);

if (!$maps) {
    $jsonResponse(['ok' => false, 'error' => 'No hay mapas configurados.'], 404);
}

$mapsById = [];
$mapNamesById = [];
foreach ($maps as $map) {
    $mapsById[(int)$map['id']] = $map;
    $mapNamesById[(int)$map['id']] = (string)$map['name'];
}

$selectedMapId = (int)hg_request_query_param($hgRequest, 'map_id');
$selectedMap = $mapsById[$selectedMapId] ?? null;

if (!$selectedMap) {
    $jsonResponse(['ok' => false, 'error' => 'Mapa no valido.'], 400);
}

$allowGlobalPoiScope = hg_maps_is_global_map($selectedMap);
$includeAllMaps = $allowGlobalPoiScope
    && in_array(strtolower(hg_request_query_param($hgRequest, 'include_all_maps', '0')), ['1', 'true', 'yes'], true);
$excludedGlobalMapIds = hg_maps_global_scope_excluded_ids($maps, $selectedMap);

$sourceMapId = (int)hg_request_query_param($hgRequest, 'source_map_id');
if ($sourceMapId > 0 && !isset($mapsById[$sourceMapId])) {
    $sourceMapId = 0;
}

$filters = [
    'selected_map_id' => (int)$selectedMap['id'],
    'selected_map_name' => (string)$selectedMap['name'],
    'include_all_maps' => $includeAllMaps,
    'source_map_id' => $sourceMapId,
    'excluded_map_ids' => $excludedGlobalMapIds,
    'category_id' => (int)hg_request_query_param($hgRequest, 'category_id'),
    'category_name' => hg_request_query_param($hgRequest, 'category_name'),
    'q' => hg_request_query_param($hgRequest, 'q'),
    'limit' => (int)hg_request_query_param($hgRequest, 'limit', '250'),
    'offset' => (int)hg_request_query_param($hgRequest, 'offset', '0'),
    'from_map_slug' => (string)$selectedMap['slug'],
];

switch ($action) {
    case 'search':
        $items = hg_maps_fetch_pois($link, $schema, $filters, $mapNamesById);
        $jsonResponse([
            'ok' => true,
            'count' => count($items),
            'items' => $items,
            'meta' => [
                'selected_map_id' => (int)$selectedMap['id'],
                'selected_map_slug' => (string)$selectedMap['slug'],
                'allow_global_scope' => $allowGlobalPoiScope,
                'include_all_maps' => $includeAllMaps,
                'source_map_id' => $sourceMapId,
            ],
        ]);
        break;

    case 'areas':
        $categoryId = (int)hg_request_query_param($hgRequest, 'category_id');
        $items = hg_maps_fetch_areas($link, (int)$selectedMap['id'], $categoryId);
        $jsonResponse([
            'ok' => true,
            'count' => count($items),
            'items' => $items,
            'meta' => [
                'selected_map_id' => (int)$selectedMap['id'],
            ],
        ]);
        break;

    default:
        $jsonResponse(['ok' => false, 'error' => 'Accion no soportada.'], 400);
}
