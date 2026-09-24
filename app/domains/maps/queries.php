<?php

/**
 * Database access for the public Maps domain.
 * Presentation/normalization helpers remain in app/helpers/maps.php.
 */

function hg_maps_query_schema_info(mysqli $link): array
{
    static $cache = [];

    $cacheKey = spl_object_id($link);
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $cache[$cacheKey] = [
        'has_map_id' => true,
        'has_cat_id' => true,
        'has_pretty_id' => true,
        'has_fulltext' => true,
    ];

    return $cache[$cacheKey];
}

function hg_maps_query_fetch_maps(mysqli $link): array
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
    $result->free();

    return $maps;
}

function hg_maps_query_append_not_in_ids(string &$sql, string &$types, array &$params, string $column, array $ids): void
{
    if (empty($ids)) {
        return;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql .= " AND {$column} NOT IN ({$placeholders})";
    $types .= str_repeat('i', count($ids));
    foreach ($ids as $id) {
        $params[] = (int)$id;
    }
}

function hg_maps_query_append_not_in_strings(string &$sql, string &$types, array &$params, string $column, array $values): void
{
    if (empty($values)) {
        return;
    }

    $placeholders = implode(',', array_fill(0, count($values), '?'));
    $sql .= " AND {$column} NOT IN ({$placeholders})";
    $types .= str_repeat('s', count($values));
    foreach ($values as $value) {
        $params[] = (string)$value;
    }
}

function hg_maps_query_fetch_categories(mysqli $link, array $schema, array $filters, array $mapNamesById = []): array
{
    $categories = [];
    $includeAllMaps = !empty($filters['include_all_maps']);
    $selectedMapId = isset($filters['selected_map_id']) ? (int)$filters['selected_map_id'] : 0;
    $selectedMapName = (string)($filters['selected_map_name'] ?? '');
    $sourceMapId = isset($filters['source_map_id']) ? (int)$filters['source_map_id'] : 0;
    $sourceMapName = hg_maps_source_map_name($filters, $mapNamesById);
    $excludedMapIds = hg_maps_filter_excluded_map_ids($filters);
    $excludedMapNames = hg_maps_filter_excluded_map_names($filters, $mapNamesById);

    if (!empty($schema['has_map_id']) && !empty($schema['has_cat_id'])) {
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

        hg_maps_query_append_not_in_ids($sql, $types, $params, 'p.map_id', $excludedMapIds);
        $sql .= " ORDER BY c.sort_order, c.name";

        $stmt = $link->prepare($sql);
        if ($stmt instanceof mysqli_stmt) {
            if ($types !== '') {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            while ($result && ($row = $result->fetch_assoc())) {
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

        hg_maps_query_append_not_in_strings($sql, $types, $params, 'map', $excludedMapNames);
        $sql .= " ORDER BY category";

        $stmt = $link->prepare($sql);
        if ($stmt instanceof mysqli_stmt) {
            if ($types !== '') {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            while ($result && ($row = $result->fetch_assoc())) {
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
        $stmtAreas = $link->prepare("SELECT DISTINCT c.id, c.name, c.color_hex
                                     FROM fact_map_areas a
                                     JOIN dim_map_categories c ON c.id = a.category_id
                                     WHERE a.map_id = ? AND a.category_id IS NOT NULL
                                     ORDER BY c.name");
        if ($stmtAreas instanceof mysqli_stmt) {
            $stmtAreas->bind_param('i', $selectedMapId);
            $stmtAreas->execute();
            $resultAreas = $stmtAreas->get_result();
            while ($resultAreas && ($row = $resultAreas->fetch_assoc())) {
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

function hg_maps_query_fetch_pois(mysqli $link, array $schema, array $filters, array $mapNamesById = []): array
{
    $includeAllMaps = !empty($filters['include_all_maps']);
    $selectedMapId = isset($filters['selected_map_id']) ? (int)$filters['selected_map_id'] : 0;
    $selectedMapName = (string)($filters['selected_map_name'] ?? '');
    $sourceMapId = isset($filters['source_map_id']) ? (int)$filters['source_map_id'] : 0;
    $sourceMapName = hg_maps_source_map_name($filters, $mapNamesById);
    $excludedMapIds = hg_maps_filter_excluded_map_ids($filters);
    $excludedMapNames = hg_maps_filter_excluded_map_names($filters, $mapNamesById);
    $categoryId = isset($filters['category_id']) ? (int)$filters['category_id'] : 0;
    $categoryName = trim((string)($filters['category_name'] ?? ''));
    $query = trim((string)($filters['q'] ?? ''));
    $limit = isset($filters['limit']) ? max(1, min(1000, (int)$filters['limit'])) : 250;
    $offset = isset($filters['offset']) ? max(0, (int)$filters['offset']) : 0;
    $fromMapSlug = (string)($filters['from_map_slug'] ?? '');
    $items = [];

    if (!empty($schema['has_map_id'])) {
        $sql = "SELECT p.id";
        if (!empty($schema['has_pretty_id'])) {
            $sql .= ", p.pretty_id";
        }
        $sql .= ", p.name, p.description, p.thumbnail, p.latitude, p.longitude,
                       p.map_id, m.name AS map_name, m.slug AS map_slug";

        if (!empty($schema['has_cat_id'])) {
            $sql .= ", p.category_id, c.name AS category_name, c.color_hex";
        } else {
            $sql .= ", 0 AS category_id, '' AS category_name, '#95a5a6' AS color_hex";
        }

        $sql .= " FROM fact_map_pois p JOIN dim_maps m ON m.id = p.map_id";
        if (!empty($schema['has_cat_id'])) {
            $sql .= " LEFT JOIN dim_map_categories c ON c.id = p.category_id";
        }
        $sql .= " WHERE 1=1";

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

        hg_maps_query_append_not_in_ids($sql, $types, $params, 'p.map_id', $excludedMapIds);

        if (!empty($schema['has_cat_id']) && $categoryId > 0) {
            $sql .= " AND p.category_id = ?";
            $types .= 'i';
            $params[] = $categoryId;
        } elseif ($categoryName !== '' && !empty($schema['has_cat_id'])) {
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
            while ($result && ($row = $result->fetch_assoc())) {
                $items[] = hg_maps_prepare_poi_row($row, $fromMapSlug);
            }
            $stmt->close();
        }
        return $items;
    }

    $sql = "SELECT p.id";
    if (!empty($schema['has_pretty_id'])) {
        $sql .= ", p.pretty_id";
    }
    $sql .= ", p.name, p.description, p.thumbnail, p.latitude, p.longitude,
                   0 AS map_id, p.map AS map_name, '' AS map_slug,
                   0 AS category_id, p.category AS category_name, '#95a5a6' AS color_hex
            FROM fact_map_pois p WHERE 1=1";
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

    hg_maps_query_append_not_in_strings($sql, $types, $params, 'p.map', $excludedMapNames);

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
        while ($result && ($row = $result->fetch_assoc())) {
            $row['map_slug'] = hg_maps_slugify((string)($row['map_name'] ?? ''));
            $items[] = hg_maps_prepare_poi_row($row, $fromMapSlug);
        }
        $stmt->close();
    }

    return $items;
}

function hg_maps_query_fetch_areas(mysqli $link, int $mapId, int $categoryId = 0): array
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
        while ($result && ($row = $result->fetch_assoc())) {
            $area = hg_maps_prepare_area_row($row);
            if ($area !== null) {
                $areas[] = $area;
            }
        }
        $stmt->close();
    }
    return $areas;
}

function hg_maps_query_resolve_poi_id(mysqli $link, string $raw, ?array $schema = null): int
{
    $raw = trim($raw);
    if ($raw === '') {
        return 0;
    }
    if (preg_match('/^\d+$/', $raw)) {
        return (int)$raw;
    }

    $schema = $schema ?? hg_maps_query_schema_info($link);
    if (empty($schema['has_pretty_id'])) {
        return 0;
    }

    $stmt = $link->prepare('SELECT id FROM fact_map_pois WHERE pretty_id = ? LIMIT 1');
    if (!($stmt instanceof mysqli_stmt)) {
        return 0;
    }
    $stmt->bind_param('s', $raw);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    return is_array($row) ? (int)($row['id'] ?? 0) : 0;
}

function hg_maps_query_fetch_poi_detail(mysqli $link, array $schema, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }

    if (!empty($schema['has_map_id'])) {
        $sql = "SELECT p.id";
        if (!empty($schema['has_pretty_id'])) {
            $sql .= ", p.pretty_id";
        }
        $sql .= ", p.name, p.description, p.thumbnail, p.latitude, p.longitude,
                       p.map_id,
                       m.name AS map_name, m.slug AS map_slug, m.default_tile, m.default_zoom,
                       m.min_zoom, m.max_zoom, m.center_lat, m.center_lng,
                       m.bounds_sw_lat, m.bounds_sw_lng, m.bounds_ne_lat, m.bounds_ne_lng";
        if (!empty($schema['has_cat_id'])) {
            $sql .= ", p.category_id, c.name AS category_name, c.color_hex";
        } else {
            $sql .= ", 0 AS category_id, '' AS category_name, '#95a5a6' AS color_hex";
        }
        $sql .= " FROM fact_map_pois p JOIN dim_maps m ON m.id = p.map_id";
        if (!empty($schema['has_cat_id'])) {
            $sql .= " LEFT JOIN dim_map_categories c ON c.id = p.category_id";
        }
        $sql .= " WHERE p.id = ? LIMIT 1";

        $stmt = $link->prepare($sql);
        if ($stmt instanceof mysqli_stmt) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result ? $result->fetch_assoc() : null;
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
    if (!empty($schema['has_pretty_id'])) {
        $sql .= ", pretty_id";
    }
    $sql .= ", name, description, thumbnail, latitude, longitude,
                   map AS map_name, category AS category_name
            FROM fact_map_pois WHERE id = ? LIMIT 1";

    $stmt = $link->prepare($sql);
    if ($stmt instanceof mysqli_stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
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

function hg_maps_query_fetch_related_pois(mysqli $link, array $schema, array $poi, int $limit = 30): array
{
    $limit = max(1, min(100, $limit));
    $items = [];

    if (!empty($schema['has_map_id']) && !empty($poi['map_id'])) {
        $sql = "SELECT id";
        if (!empty($schema['has_pretty_id'])) {
            $sql .= ", pretty_id";
        }
        $sql .= ", name FROM fact_map_pois WHERE map_id = ? AND id <> ? ORDER BY name LIMIT ?";
        $stmt = $link->prepare($sql);
        if ($stmt instanceof mysqli_stmt) {
            $mapId = (int)$poi['map_id'];
            $poiId = (int)$poi['id'];
            $stmt->bind_param('iii', $mapId, $poiId, $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($result && ($row = $result->fetch_assoc())) {
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
    if (!empty($schema['has_pretty_id'])) {
        $sql .= ", pretty_id";
    }
    $sql .= ", name FROM fact_map_pois WHERE map = ? AND id <> ? ORDER BY name LIMIT ?";
    $stmt = $link->prepare($sql);
    if ($stmt instanceof mysqli_stmt) {
        $mapName = (string)($poi['map_name'] ?? '');
        $poiId = (int)($poi['id'] ?? 0);
        $stmt->bind_param('sii', $mapName, $poiId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($result && ($row = $result->fetch_assoc())) {
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
