<?php

include_once(__DIR__ . '/runtime_response.php');

function hg_maps_require_connection($link, bool $asJson = false): void
{
    if (!($link instanceof mysqli)) {
        hg_runtime_log_error('maps.db', mysqli_connect_error());

        if ($asJson) {
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: application/json; charset=UTF-8');
            }
            echo json_encode([
                'ok' => false,
                'error' => 'No se pudo conectar a la base de datos.',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        hg_runtime_public_error(
            'Mapas no disponibles',
            'No se pudo conectar a la base de datos.',
            500,
            false
        );
        exit;
    }
}

function hg_maps_slugify(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (function_exists('mb_strtolower')) {
        $value = mb_strtolower($value, 'UTF-8');
    } else {
        $value = strtolower($value);
    }

    if (function_exists('iconv')) {
        $translit = @iconv('UTF-8', 'ASCII//TRANSLIT', $value);
        if (is_string($translit) && $translit !== '') {
            $value = $translit;
        }
    }

    $value = preg_replace('/[^a-z0-9]+/i', '-', $value);
    $value = trim((string)$value, '-');

    return $value;
}

function hg_maps_safe_color(?string $value, string $fallback = '#95a5a6'): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return $fallback;
    }

    if ($value[0] !== '#') {
        $value = '#' . $value;
    }

    return preg_match('/^#[0-9a-f]{6}$/i', $value) ? strtoupper($value) : $fallback;
}

function hg_maps_safe_url(?string $url): string
{
    $url = trim((string)$url);
    if ($url === '') {
        return '';
    }

    if (preg_match('#^(https?:)?//#i', $url)) {
        return $url;
    }

    if ($url[0] === '/') {
        return $url;
    }

    return '';
}

function hg_maps_json($value): string
{
    $json = json_encode(
        $value,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
    );

    return is_string($json) ? $json : 'null';
}

function hg_maps_tile_presets(): array
{
    static $tiles = null;

    if ($tiles !== null) {
        return $tiles;
    }

    $tiles = [
        'carto-dark' => [
            'name' => 'CARTO Dark',
            'url' => 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.webp',
            'attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/basemaps" target="_blank" rel="noopener">CARTO</a>',
            'subdomains' => ['a', 'b', 'c', 'd'],
            'maxZoom' => 19,
        ],
        'osm-standard' => [
            'name' => 'OpenStreetMap',
            'url' => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.webp',
            'attribution' => '&copy; OpenStreetMap contributors',
            'subdomains' => ['a', 'b', 'c'],
            'maxZoom' => 19,
        ],
        'esri-gray' => [
            'name' => 'Esri Gray',
            'url' => 'https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Light_Gray_Base/MapServer/tile/{z}/{y}/{x}',
            'attribution' => 'Tiles &copy; Esri - Esri, DeLorme, NAVTEQ',
            'subdomains' => [],
            'maxZoom' => 19,
        ],
    ];

    return $tiles;
}

function hg_maps_tile_for_map(array $map): array
{
    $tiles = hg_maps_tile_presets();
    $tileKey = (string)($map['default_tile'] ?? '');

    return $tiles[$tileKey] ?? $tiles['carto-dark'];
}

function hg_maps_map_bounds(array $map): ?array
{
    $keys = ['bounds_sw_lat', 'bounds_sw_lng', 'bounds_ne_lat', 'bounds_ne_lng'];
    foreach ($keys as $key) {
        if (!array_key_exists($key, $map) || $map[$key] === null || $map[$key] === '') {
            return null;
        }
    }

    return [
        [(float)$map['bounds_sw_lat'], (float)$map['bounds_sw_lng']],
        [(float)$map['bounds_ne_lat'], (float)$map['bounds_ne_lng']],
    ];
}

function hg_maps_global_scope_key(array $map): string
{
    $slug = strtolower(trim((string)($map['slug'] ?? '')));
    $name = strtolower(hg_maps_slugify((string)($map['name'] ?? '')));
    $candidates = array_values(array_unique(array_filter([$slug, $name], static function ($value) {
        return $value !== '';
    })));

    foreach ($candidates as $candidate) {
        if ($candidate === 'gaia2' || $candidate === 'gaia-2') {
            return 'gaia2';
        }
        if ($candidate === 'gaia1' || $candidate === 'gaia-1') {
            return 'gaia1';
        }
    }

    return '';
}

function hg_maps_find_map(array $maps, string $mapParam): ?array
{
    $mapParam = trim($mapParam);
    if ($mapParam === '') {
        foreach ($maps as $map) {
            if (hg_maps_global_scope_key($map) === 'gaia2') {
                return $map;
            }
        }
        foreach ($maps as $map) {
            if (hg_maps_is_global_map($map)) {
                return $map;
            }
        }
        return $maps[0] ?? null;
    }

    foreach ($maps as $map) {
        if ((string)$map['slug'] === $mapParam) {
            return $map;
        }
    }

    foreach ($maps as $map) {
        if (strcasecmp((string)$map['name'], $mapParam) === 0) {
            return $map;
        }
    }

    foreach ($maps as $map) {
        if ((string)$map['id'] === $mapParam) {
            return $map;
        }
    }

    return $maps[0] ?? null;
}

function hg_maps_is_global_map(array $map): bool
{
    return hg_maps_global_scope_key($map) !== '';
}

function hg_maps_global_scope_excluded_ids(array $maps, array $selectedMap): array
{
    $scopeKey = hg_maps_global_scope_key($selectedMap);
    if ($scopeKey === '') {
        return [];
    }

    $excludedKey = $scopeKey === 'gaia2' ? 'gaia1' : 'gaia2';
    $ids = [];

    foreach ($maps as $map) {
        if (hg_maps_global_scope_key($map) === $excludedKey) {
            $ids[] = (int)($map['id'] ?? 0);
        }
    }

    return array_values(array_unique(array_filter($ids, static function ($value) {
        return $value > 0;
    })));
}

function hg_maps_global_scope_excluded_name(array $maps, array $selectedMap): string
{
    $excludedIds = hg_maps_global_scope_excluded_ids($maps, $selectedMap);
    if (empty($excludedIds)) {
        return '';
    }

    foreach ($maps as $map) {
        $mapId = (int)($map['id'] ?? 0);
        if (in_array($mapId, $excludedIds, true)) {
            return (string)($map['name'] ?? '');
        }
    }

    return '';
}

function hg_maps_filter_excluded_map_ids(array $filters): array
{
    $raw = $filters['excluded_map_ids'] ?? [];
    if (!is_array($raw)) {
        return [];
    }

    $ids = [];
    foreach ($raw as $value) {
        $id = (int)$value;
        if ($id > 0) {
            $ids[] = $id;
        }
    }

    return array_values(array_unique($ids));
}

function hg_maps_filter_excluded_map_names(array $filters, array $mapNamesById): array
{
    $ids = hg_maps_filter_excluded_map_ids($filters);
    $names = [];

    foreach ($ids as $id) {
        $name = trim((string)($mapNamesById[$id] ?? ''));
        if ($name !== '') {
            $names[] = $name;
        }
    }

    return array_values(array_unique($names));
}

function hg_maps_build_detail_url(array $poi, string $fromMapSlug = ''): string
{
    $detailKey = trim((string)($poi['pretty_id'] ?? ''));
    if ($detailKey === '') {
        $detailKey = (string)((int)($poi['id'] ?? 0));
    }

    $url = '/maps/poi/' . rawurlencode($detailKey);
    if ($fromMapSlug !== '') {
        $url .= '?from_map=' . rawurlencode($fromMapSlug);
    }

    return $url;
}

function hg_maps_prepare_poi_row(array $row, string $fromMapSlug = ''): array
{
    return [
        'id' => isset($row['id']) ? (int)$row['id'] : 0,
        'pretty_id' => (string)($row['pretty_id'] ?? ''),
        'name' => (string)($row['name'] ?? ''),
        'description' => (string)($row['description'] ?? ''),
        'thumbnail' => hg_maps_safe_url((string)($row['thumbnail'] ?? '')),
        'latitude' => isset($row['latitude']) ? (float)$row['latitude'] : 0.0,
        'longitude' => isset($row['longitude']) ? (float)$row['longitude'] : 0.0,
        'map_id' => isset($row['map_id']) ? (int)$row['map_id'] : 0,
        'map_name' => (string)($row['map_name'] ?? ''),
        'map_slug' => (string)($row['map_slug'] ?? ''),
        'category_id' => isset($row['category_id']) ? (int)$row['category_id'] : 0,
        'category_name' => (string)($row['category_name'] ?? ''),
        'color_hex' => hg_maps_safe_color((string)($row['color_hex'] ?? '')),
        'detail_url' => hg_maps_build_detail_url($row, $fromMapSlug),
    ];
}

function hg_maps_prepare_area_row(array $row): ?array
{
    $geometry = $row['geometry'] ?? null;
    if (is_string($geometry)) {
        $geometry = json_decode($geometry, true);
    }

    if (!is_array($geometry) || empty($geometry['type'])) {
        return null;
    }

    return [
        'id' => isset($row['id']) ? (int)$row['id'] : 0,
        'map_id' => isset($row['map_id']) ? (int)$row['map_id'] : 0,
        'category_id' => isset($row['category_id']) ? (int)$row['category_id'] : 0,
        'name' => (string)($row['name'] ?? ''),
        'description' => (string)($row['description'] ?? ''),
        'category_name' => (string)($row['category_name'] ?? ''),
        'color_hex' => hg_maps_safe_color((string)($row['color_hex'] ?? '#2ecc71'), '#2ecc71'),
        'geometry' => $geometry,
    ];
}

function hg_maps_source_map_name(array $filters, array $mapNamesById): string
{
    $sourceMapId = isset($filters['source_map_id']) ? (int)$filters['source_map_id'] : 0;
    return $sourceMapId > 0 ? (string)($mapNamesById[$sourceMapId] ?? '') : '';
}

