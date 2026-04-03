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

function hg_maps_schema_info(mysqli $link): array
{
    static $cache = [];

    $cacheKey = spl_object_id($link);
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $columnExists = static function (string $table, string $column) use ($link): bool {
        $sql = sprintf(
            "SHOW COLUMNS FROM `%s` LIKE '%s'",
            $link->real_escape_string($table),
            $link->real_escape_string($column)
        );
        $result = $link->query($sql);
        return $result instanceof mysqli_result && $result->num_rows > 0;
    };

    $indexExists = static function (string $table, string $indexName) use ($link): bool {
        $sql = sprintf(
            "SHOW INDEX FROM `%s` WHERE Key_name='%s'",
            $link->real_escape_string($table),
            $link->real_escape_string($indexName)
        );
        $result = $link->query($sql);
        return $result instanceof mysqli_result && $result->num_rows > 0;
    };

    $cache[$cacheKey] = [
        'has_map_id' => $columnExists('fact_map_pois', 'map_id'),
        'has_cat_id' => $columnExists('fact_map_pois', 'category_id'),
        'has_pretty_id' => $columnExists('fact_map_pois', 'pretty_id'),
        'has_fulltext' => $indexExists('fact_map_pois', 'ft_pois'),
    ];

    return $cache[$cacheKey];
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
            'url' => 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png',
            'attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/basemaps" target="_blank" rel="noopener">CARTO</a>',
            'subdomains' => ['a', 'b', 'c', 'd'],
            'maxZoom' => 19,
        ],
        'osm-standard' => [
            'name' => 'OpenStreetMap',
            'url' => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
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

function hg_maps_fetch_maps(mysqli $link): array
{
    $maps = [];
    $sql = "SELECT id, name, slug, center_lat, center_lng, default_zoom, default_tile,
                   bounds_sw_lat, bounds_sw_lng, bounds_ne_lat, bounds_ne_lng,
                   min_zoom, max_zoom
            FROM dim_maps
            ORDER BY name";
    $result = $link->query($sql);

    if (!($result instanceof mysqli_result)) {
        return $maps;
    }

    while ($row = $result->fetch_assoc()) {
        $maps[] = [
            'id' => (int)$row['id'],
            'name' => (string)$row['name'],
            'slug' => (string)($row['slug'] ?: hg_maps_slugify((string)$row['name'])),
            'center_lat' => (float)$row['center_lat'],
            'center_lng' => (float)$row['center_lng'],
            'default_zoom' => (int)$row['default_zoom'],
            'default_tile' => (string)($row['default_tile'] ?? 'carto-dark'),
            'bounds_sw_lat' => $row['bounds_sw_lat'],
            'bounds_sw_lng' => $row['bounds_sw_lng'],
            'bounds_ne_lat' => $row['bounds_ne_lat'],
            'bounds_ne_lng' => $row['bounds_ne_lng'],
            'min_zoom' => isset($row['min_zoom']) ? (int)$row['min_zoom'] : 3,
            'max_zoom' => isset($row['max_zoom']) ? (int)$row['max_zoom'] : 19,
        ];
    }

    return $maps;
}

function hg_maps_find_map(array $maps, string $mapParam): ?array
{
    $mapParam = trim($mapParam);
    if ($mapParam === '') {
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
    $slug = strtolower((string)($map['slug'] ?? ''));
    $name = strtolower(hg_maps_slugify((string)($map['name'] ?? '')));

    return $slug === 'gaia2' || $name === 'gaia2';
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

function hg_maps_fetch_categories(mysqli $link, array $schema, array $filters, array $mapNamesById = []): array
{
    $categories = [];
    $includeAllMaps = !empty($filters['include_all_maps']);
    $selectedMapId = isset($filters['selected_map_id']) ? (int)$filters['selected_map_id'] : 0;
    $selectedMapName = (string)($filters['selected_map_name'] ?? '');
    $sourceMapId = isset($filters['source_map_id']) ? (int)$filters['source_map_id'] : 0;
    $sourceMapName = hg_maps_source_map_name($filters, $mapNamesById);

    if ($schema['has_map_id'] && $schema['has_cat_id']) {
        $sql = "SELECT DISTINCT c.id, c.name, c.color_hex, c.sort_order
                FROM dim_map_categories c
                JOIN fact_map_pois p ON p.category_id = c.id
                WHERE 1=1";
        $types = '';
        $params = [];

        if ($includeAllMaps) {
            if ($sourceMapId > 0) {
                $sql .= " AND p.map_id = ?";
                $types .= 'i';
                $params[] = $sourceMapId;
            }
        } elseif ($selectedMapId > 0) {
            $sql .= " AND p.map_id = ?";
            $types .= 'i';
            $params[] = $selectedMapId;
        }

        $sql .= " ORDER BY c.sort_order, c.name";
        $stmt = $link->prepare($sql);
        if ($stmt instanceof mysqli_stmt) {
            if ($types !== '') {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $categories[] = [
                    'id' => (int)$row['id'],
                    'name' => (string)$row['name'],
                    'color_hex' => hg_maps_safe_color((string)$row['color_hex']),
                ];
            }
            $stmt->close();
        }
    } else {
        $sql = "SELECT DISTINCT category AS name FROM fact_map_pois WHERE 1=1";
        $types = '';
        $params = [];

        if ($includeAllMaps) {
            if ($sourceMapName !== '') {
                $sql .= " AND map = ?";
                $types .= 's';
                $params[] = $sourceMapName;
            }
        } elseif ($selectedMapName !== '') {
            $sql .= " AND map = ?";
            $types .= 's';
            $params[] = $selectedMapName;
        }

        $sql .= " ORDER BY category";
        $stmt = $link->prepare($sql);
        if ($stmt instanceof mysqli_stmt) {
            if ($types !== '') {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $categories[] = [
                    'id' => 0,
                    'name' => (string)$row['name'],
                    'color_hex' => '#95A5A6',
                ];
            }
            $stmt->close();
        }
    }

    if ($selectedMapId > 0) {
        $sqlAreas = "SELECT DISTINCT c.id, c.name, c.color_hex
                     FROM fact_map_areas a
                     JOIN dim_map_categories c ON c.id = a.category_id
                     WHERE a.map_id = ? AND a.category_id IS NOT NULL
                     ORDER BY c.name";
        $stmtAreas = $link->prepare($sqlAreas);
        if ($stmtAreas instanceof mysqli_stmt) {
            $stmtAreas->bind_param('i', $selectedMapId);
            $stmtAreas->execute();
            $resultAreas = $stmtAreas->get_result();
            while ($row = $resultAreas->fetch_assoc()) {
                $categories[] = [
                    'id' => (int)$row['id'],
                    'name' => (string)$row['name'],
                    'color_hex' => hg_maps_safe_color((string)$row['color_hex']),
                ];
            }
            $stmtAreas->close();
        }
    }

    $unique = [];
    foreach ($categories as $category) {
        $key = $category['id'] > 0 ? 'id:' . $category['id'] : 'name:' . strtolower($category['name']);
        $unique[$key] = $category;
    }

    return array_values($unique);
}

function hg_maps_fetch_pois(mysqli $link, array $schema, array $filters, array $mapNamesById = []): array
{
    $includeAllMaps = !empty($filters['include_all_maps']);
    $selectedMapId = isset($filters['selected_map_id']) ? (int)$filters['selected_map_id'] : 0;
    $selectedMapName = (string)($filters['selected_map_name'] ?? '');
    $sourceMapId = isset($filters['source_map_id']) ? (int)$filters['source_map_id'] : 0;
    $sourceMapName = hg_maps_source_map_name($filters, $mapNamesById);
    $categoryId = isset($filters['category_id']) ? (int)$filters['category_id'] : 0;
    $categoryName = trim((string)($filters['category_name'] ?? ''));
    $query = trim((string)($filters['q'] ?? ''));
    $limit = isset($filters['limit']) ? max(1, min(1000, (int)$filters['limit'])) : 250;
    $offset = isset($filters['offset']) ? max(0, (int)$filters['offset']) : 0;
    $fromMapSlug = (string)($filters['from_map_slug'] ?? '');

    $items = [];

    if ($schema['has_map_id']) {
        $sql = "SELECT p.id";
        if ($schema['has_pretty_id']) {
            $sql .= ", p.pretty_id";
        }
        $sql .= ",
                       p.name, p.description, p.thumbnail, p.latitude, p.longitude,
                       p.map_id, m.name AS map_name, m.slug AS map_slug";

        if ($schema['has_cat_id']) {
            $sql .= ",
                       p.category_id, c.name AS category_name, c.color_hex";
        } else {
            $sql .= ",
                       0 AS category_id, '' AS category_name, '#95a5a6' AS color_hex";
        }

        $sql .= "
                FROM fact_map_pois p
                JOIN dim_maps m ON m.id = p.map_id";

        if ($schema['has_cat_id']) {
            $sql .= "
                LEFT JOIN dim_map_categories c ON c.id = p.category_id";
        }

        $sql .= "
                WHERE 1=1";

        $types = '';
        $params = [];

        if ($includeAllMaps) {
            if ($sourceMapId > 0) {
                $sql .= " AND p.map_id = ?";
                $types .= 'i';
                $params[] = $sourceMapId;
            }
        } elseif ($selectedMapId > 0) {
            $sql .= " AND p.map_id = ?";
            $types .= 'i';
            $params[] = $selectedMapId;
        }

        if ($schema['has_cat_id'] && $categoryId > 0) {
            $sql .= " AND p.category_id = ?";
            $types .= 'i';
            $params[] = $categoryId;
        } elseif ($categoryName !== '' && $schema['has_cat_id']) {
            $sql .= " AND c.name = ?";
            $types .= 's';
            $params[] = $categoryName;
        }

        if ($query !== '') {
            if (!empty($schema['has_fulltext'])) {
                $sql .= " AND MATCH(p.name, p.description) AGAINST (? IN NATURAL LANGUAGE MODE)";
                $types .= 's';
                $params[] = $query;
            } else {
                $sql .= " AND (p.name LIKE CONCAT('%', ?, '%') OR p.description LIKE CONCAT('%', ?, '%'))";
                $types .= 'ss';
                $params[] = $query;
                $params[] = $query;
            }
        }

        $sql .= " ORDER BY p.name ASC LIMIT ? OFFSET ?";
        $types .= 'ii';
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $link->prepare($sql);
        if ($stmt instanceof mysqli_stmt) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $items[] = hg_maps_prepare_poi_row($row, $fromMapSlug);
            }
            $stmt->close();
        }

        return $items;
    }

    $sql = "SELECT p.id";
    if ($schema['has_pretty_id']) {
        $sql .= ", p.pretty_id";
    }
    $sql .= ",
                   p.name, p.description, p.thumbnail, p.latitude, p.longitude,
                   0 AS map_id, p.map AS map_name, '' AS map_slug,
                   0 AS category_id, p.category AS category_name, '#95a5a6' AS color_hex
            FROM fact_map_pois p
            WHERE 1=1";
    $types = '';
    $params = [];

    if ($includeAllMaps) {
        if ($sourceMapName !== '') {
            $sql .= " AND p.map = ?";
            $types .= 's';
            $params[] = $sourceMapName;
        }
    } elseif ($selectedMapName !== '') {
        $sql .= " AND p.map = ?";
        $types .= 's';
        $params[] = $selectedMapName;
    }

    if ($categoryName !== '') {
        $sql .= " AND p.category = ?";
        $types .= 's';
        $params[] = $categoryName;
    }

    if ($query !== '') {
        $sql .= " AND (p.name LIKE CONCAT('%', ?, '%') OR p.description LIKE CONCAT('%', ?, '%'))";
        $types .= 'ss';
        $params[] = $query;
        $params[] = $query;
    }

    $sql .= " ORDER BY p.name ASC LIMIT ? OFFSET ?";
    $types .= 'ii';
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $link->prepare($sql);
    if ($stmt instanceof mysqli_stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $row['map_slug'] = hg_maps_slugify((string)($row['map_name'] ?? ''));
            $items[] = hg_maps_prepare_poi_row($row, $fromMapSlug);
        }
        $stmt->close();
    }

    return $items;
}

function hg_maps_fetch_areas(mysqli $link, int $mapId, int $categoryId = 0): array
{
    if ($mapId <= 0) {
        return [];
    }

    $sql = "SELECT a.id, a.map_id, a.category_id,
                   a.name, a.description, a.geometry,
                   c.name AS category_name,
                   COALESCE(a.color_hex, c.color_hex, '#2ecc71') AS color_hex
            FROM fact_map_areas a
            LEFT JOIN dim_map_categories c ON c.id = a.category_id
            WHERE a.map_id = ?";
    $types = 'i';
    $params = [$mapId];

    if ($categoryId > 0) {
        $sql .= " AND a.category_id = ?";
        $types .= 'i';
        $params[] = $categoryId;
    }

    $sql .= " ORDER BY a.id DESC LIMIT 1000";

    $areas = [];
    $stmt = $link->prepare($sql);
    if ($stmt instanceof mysqli_stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $area = hg_maps_prepare_area_row($row);
            if ($area !== null) {
                $areas[] = $area;
            }
        }
        $stmt->close();
    }

    return $areas;
}

function hg_maps_fetch_poi_detail(mysqli $link, array $schema, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }

    if ($schema['has_map_id']) {
        $sql = "SELECT p.id";
        if ($schema['has_pretty_id']) {
            $sql .= ", p.pretty_id";
        }
        $sql .= ",
                       p.name, p.description, p.thumbnail, p.latitude, p.longitude,
                       p.map_id,
                       m.name AS map_name, m.slug AS map_slug, m.default_tile, m.default_zoom,
                       m.min_zoom, m.max_zoom, m.center_lat, m.center_lng,
                       m.bounds_sw_lat, m.bounds_sw_lng, m.bounds_ne_lat, m.bounds_ne_lng";

        if ($schema['has_cat_id']) {
            $sql .= ",
                       p.category_id, c.name AS category_name, c.color_hex";
        } else {
            $sql .= ",
                       0 AS category_id, '' AS category_name, '#95a5a6' AS color_hex";
        }

        $sql .= "
                FROM fact_map_pois p
                JOIN dim_maps m ON m.id = p.map_id";

        if ($schema['has_cat_id']) {
            $sql .= "
                LEFT JOIN dim_map_categories c ON c.id = p.category_id";
        }

        $sql .= "
                WHERE p.id = ?
                LIMIT 1";

        $stmt = $link->prepare($sql);
        if ($stmt instanceof mysqli_stmt) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (is_array($row)) {
                $poi = hg_maps_prepare_poi_row($row);
                $poi['default_tile'] = (string)($row['default_tile'] ?? 'carto-dark');
                $poi['default_zoom'] = isset($row['default_zoom']) ? (int)$row['default_zoom'] : 8;
                $poi['min_zoom'] = isset($row['min_zoom']) ? (int)$row['min_zoom'] : 3;
                $poi['max_zoom'] = isset($row['max_zoom']) ? (int)$row['max_zoom'] : 19;
                $poi['center_lat'] = isset($row['center_lat']) ? (float)$row['center_lat'] : $poi['latitude'];
                $poi['center_lng'] = isset($row['center_lng']) ? (float)$row['center_lng'] : $poi['longitude'];
                $poi['bounds'] = hg_maps_map_bounds($row);
                return $poi;
            }
        }

        return null;
    }

    $sql = "SELECT id";
    if ($schema['has_pretty_id']) {
        $sql .= ", pretty_id";
    }
    $sql .= ",
                   name, description, thumbnail, latitude, longitude,
                   map AS map_name, category AS category_name
            FROM fact_map_pois
            WHERE id = ?
            LIMIT 1";

    $stmt = $link->prepare($sql);
    if ($stmt instanceof mysqli_stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (is_array($row)) {
            $row['map_id'] = 0;
            $row['map_slug'] = hg_maps_slugify((string)($row['map_name'] ?? ''));
            $row['category_id'] = 0;
            $row['color_hex'] = '#95a5a6';
            $poi = hg_maps_prepare_poi_row($row);
            $poi['default_tile'] = 'carto-dark';
            $poi['default_zoom'] = 8;
            $poi['min_zoom'] = 3;
            $poi['max_zoom'] = 19;
            $poi['center_lat'] = $poi['latitude'];
            $poi['center_lng'] = $poi['longitude'];
            $poi['bounds'] = null;
            return $poi;
        }
    }

    return null;
}

function hg_maps_fetch_related_pois(mysqli $link, array $schema, array $poi, int $limit = 30): array
{
    $limit = max(1, min(100, $limit));
    $items = [];

    if ($schema['has_map_id'] && !empty($poi['map_id'])) {
        $sql = "SELECT id";
        if ($schema['has_pretty_id']) {
            $sql .= ", pretty_id";
        }
        $sql .= ",
                       name
                FROM fact_map_pois
                WHERE map_id = ? AND id <> ?
                ORDER BY name
                LIMIT ?";
        $stmt = $link->prepare($sql);
        if ($stmt instanceof mysqli_stmt) {
            $stmt->bind_param('iii', $poi['map_id'], $poi['id'], $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $row['map_slug'] = (string)$poi['map_slug'];
                $items[] = [
                    'id' => (int)$row['id'],
                    'pretty_id' => (string)($row['pretty_id'] ?? ''),
                    'name' => (string)$row['name'],
                    'detail_url' => hg_maps_build_detail_url($row, (string)$poi['map_slug']),
                ];
            }
            $stmt->close();
        }

        return $items;
    }

    $sql = "SELECT id";
    if ($schema['has_pretty_id']) {
        $sql .= ", pretty_id";
    }
    $sql .= ",
                   name
            FROM fact_map_pois
            WHERE map = ? AND id <> ?
            ORDER BY name
            LIMIT ?";
    $stmt = $link->prepare($sql);
    if ($stmt instanceof mysqli_stmt) {
        $mapName = (string)($poi['map_name'] ?? '');
        $poiId = (int)($poi['id'] ?? 0);
        $stmt->bind_param('sii', $mapName, $poiId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $row['map_slug'] = (string)($poi['map_slug'] ?? '');
            $items[] = [
                'id' => (int)$row['id'],
                'pretty_id' => (string)($row['pretty_id'] ?? ''),
                'name' => (string)$row['name'],
                'detail_url' => hg_maps_build_detail_url($row, (string)($poi['map_slug'] ?? '')),
            ];
        }
        $stmt->close();
    }

    return $items;
}
