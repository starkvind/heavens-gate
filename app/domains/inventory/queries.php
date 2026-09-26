<?php

if (!function_exists('hg_inventory_normalize_int_csv')) {
    function hg_inventory_normalize_int_csv($csv): string
    {
        $csv = trim((string)$csv);
        if ($csv === '') return '';
        $ids = [];
        foreach (preg_split('/\s*,\s*/', $csv) as $part) {
            if (preg_match('/^\d+$/', (string)$part)) {
                $ids[] = (string)(int)$part;
            }
        }
        return implode(',', array_values(array_unique($ids)));
    }
}

if (!function_exists('hg_inventory_has_column')) {
    function hg_inventory_has_column(mysqli $link, string $table, string $column): bool
    {
        return $table === 'fact_characters' && $column === 'character_kind';
    }
}

if (!function_exists('hg_inventory_fetch_catalog')) {
    function hg_inventory_fetch_catalog(mysqli $link): ?array
    {
        $result = mysqli_query(
            $link,
            "SELECT i.id AS item_id,
                    i.pretty_id AS item_pretty_id,
                    i.name AS item_name,
                    i.image_url AS item_img,
                    t.name AS item_category,
                    t.pretty_id AS item_type_pretty,
                    t.id AS item_type_id,
                    COALESCE(b.name, '') AS item_origin
             FROM fact_items i
             LEFT JOIN dim_item_types t ON i.item_type_id = t.id
             LEFT JOIN dim_bibliographies b ON i.bibliography_id = b.id
             ORDER BY t.name ASC, i.name ASC"
        );
        if (!$result) return null;
        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
        mysqli_free_result($result);
        return $rows;
    }
}

if (!function_exists('hg_inventory_fetch_type')) {
    function hg_inventory_fetch_type(mysqli $link, int $typeId): ?array
    {
        if ($typeId <= 0) return null;
        $stmt = mysqli_prepare($link, 'SELECT id, name, pretty_id FROM dim_item_types WHERE id = ? LIMIT 1');
        if (!$stmt) return null;
        mysqli_stmt_bind_param($stmt, 'i', $typeId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        if ($result) mysqli_free_result($result);
        mysqli_stmt_close($stmt);
        return $row ?: null;
    }
}

if (!function_exists('hg_inventory_fetch_items_by_type')) {
    function hg_inventory_fetch_items_by_type(mysqli $link, int $typeId): array
    {
        if ($typeId <= 0) return [];
        $stmt = mysqli_prepare(
            $link,
            "SELECT i.id AS item_id,
                    i.pretty_id AS item_pretty_id,
                    i.name AS item_name,
                    i.image_url AS item_img,
                    COALESCE(b.name, '') AS item_origin
             FROM fact_items i
             LEFT JOIN dim_bibliographies b ON i.bibliography_id = b.id
             WHERE i.item_type_id = ?
             ORDER BY b.name ASC, i.name ASC"
        );
        if (!$stmt) return [];
        mysqli_stmt_bind_param($stmt, 'i', $typeId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rows = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
            mysqli_free_result($result);
        }
        mysqli_stmt_close($stmt);
        return $rows;
    }
}

if (!function_exists('hg_inventory_fetch_item')) {
    function hg_inventory_fetch_item(mysqli $link, int $itemId): ?array
    {
        if ($itemId <= 0) return null;
        $stmt = mysqli_prepare(
            $link,
            "SELECT i.*,
                    COALESCE(b.name, '') AS bibliography_name,
                    COALESCE(t.name, '') AS item_type_name
             FROM fact_items i
             LEFT JOIN dim_bibliographies b ON b.id = i.bibliography_id
             LEFT JOIN dim_item_types t ON t.id = i.item_type_id
             WHERE i.id = ? LIMIT 1"
        );
        if (!$stmt) return null;
        mysqli_stmt_bind_param($stmt, 'i', $itemId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        if ($result) mysqli_free_result($result);
        mysqli_stmt_close($stmt);
        return $row ?: null;
    }
}

if (!function_exists('hg_inventory_fetch_owners')) {
    function hg_inventory_fetch_owners(mysqli $link, int $itemId, $excludedChronicles = ''): array
    {
        if ($itemId <= 0) return [];

        $excluded = hg_inventory_normalize_int_csv($excludedChronicles);
        $chronicleWhere = $excluded !== '' ? "AND p.chronicle_id NOT IN ({$excluded})" : '';
        $kindExpr = "''";
        foreach (['character_kind', 'kind'] as $column) {
            if (hg_inventory_has_column($link, 'fact_characters', $column)) {
                $kindExpr = "p.`{$column}`";
                break;
            }
        }

        $stmt = mysqli_prepare(
            $link,
            "SELECT p.id,
                    p.name,
                    p.alias,
                    p.image_url,
                    p.gender,
                    COALESCE(dcs.label, '') AS status,
                    p.status_id,
                    {$kindExpr} AS character_kind
             FROM bridge_characters_items b
             JOIN fact_characters p ON p.id = b.character_id
             LEFT JOIN dim_character_status dcs ON dcs.id = p.status_id
             WHERE b.item_id = ? {$chronicleWhere}
             ORDER BY p.name"
        );
        if (!$stmt) return [];
        mysqli_stmt_bind_param($stmt, 'i', $itemId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rows = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
            mysqli_free_result($result);
        }
        mysqli_stmt_close($stmt);
        return $rows;
    }
}

if (!function_exists('hg_inventory_resolve_id')) {
    function hg_inventory_resolve_id(mysqli $link, string $table, string $raw): int
    {
        if (!in_array($table, ['fact_items', 'dim_item_types'], true)) return 0;
        $raw = trim(rawurldecode($raw));
        if ($raw === '') return 0;
        if (preg_match('/^\d+$/', $raw)) return (int)$raw;
        if (function_exists('resolve_pretty_id')) {
            $resolved = resolve_pretty_id($link, $table, $raw);
            if ((int)$resolved > 0) return (int)$resolved;
        }
        return 0;
    }
}

if (!function_exists('hg_inventory_fetch_mobile_item')) {
    function hg_inventory_fetch_mobile_item(mysqli $link, int $itemId): ?array
    {
        if ($itemId <= 0) return null;
        $stmt = mysqli_prepare(
            $link,
            "SELECT i.*, t.name AS type_name, t.pretty_id AS type_pretty, COALESCE(b.name, '') AS origin
             FROM fact_items i
             LEFT JOIN dim_item_types t ON t.id = i.item_type_id
             LEFT JOIN dim_bibliographies b ON b.id = i.bibliography_id
             WHERE i.id = ? LIMIT 1"
        );
        if (!$stmt) return null;
        mysqli_stmt_bind_param($stmt, 'i', $itemId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? (mysqli_fetch_assoc($result) ?: null) : null;
        if ($result) mysqli_free_result($result);
        mysqli_stmt_close($stmt);
        return $row;
    }
}

if (!function_exists('hg_inventory_fetch_mobile_owners')) {
    function hg_inventory_fetch_mobile_owners(mysqli $link, int $itemId, $excludedChronicles = '2,7'): array
    {
        if ($itemId <= 0) return [];
        $excluded = hg_inventory_normalize_int_csv($excludedChronicles);
        $ids = $excluded !== '' ? array_map('intval', explode(',', $excluded)) : [];
        $kindExpr = "''";
        foreach (['character_kind', 'kind'] as $column) {
            if (hg_inventory_has_column($link, 'fact_characters', $column)) {
                $kindExpr = "p.`{$column}`";
                break;
            }
        }

        $sql = "SELECT p.id, p.name, p.alias, p.image_url, p.gender,
                       COALESCE(dcs.label, '') AS status, p.status_id,
                       {$kindExpr} AS character_kind
                FROM bridge_characters_items b
                JOIN fact_characters p ON p.id = b.character_id
                LEFT JOIN dim_character_status dcs ON dcs.id = p.status_id
                WHERE b.item_id = ?";
        $types = 'i';
        $params = [$itemId];
        if ($ids) {
            $sql .= ' AND p.chronicle_id NOT IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            $types .= str_repeat('i', count($ids));
            foreach ($ids as $id) $params[] = $id;
        }
        $sql .= ' ORDER BY p.name ASC, p.id ASC';

        $stmt = mysqli_prepare($link, $sql);
        if (!$stmt) return [];
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rows = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
            mysqli_free_result($result);
        }
        mysqli_stmt_close($stmt);
        return $rows;
    }
}

if (!function_exists('hg_inventory_fetch_mobile_items_by_type')) {
    function hg_inventory_fetch_mobile_items_by_type(mysqli $link, int $typeId): array
    {
        if ($typeId <= 0) return [];
        $stmt = mysqli_prepare(
            $link,
            "SELECT i.id AS item_id, i.pretty_id AS item_pretty_id, i.name AS item_name,
                    i.image_url AS item_img, i.description, i.item_type_id,
                    t.name AS item_category, t.pretty_id AS item_type_pretty,
                    COALESCE(b.name, '') AS item_origin
             FROM fact_items i
             LEFT JOIN dim_item_types t ON t.id = i.item_type_id
             LEFT JOIN dim_bibliographies b ON b.id = i.bibliography_id
             WHERE i.item_type_id = ?
             ORDER BY b.name ASC, i.name ASC, i.id ASC"
        );
        if (!$stmt) return [];
        mysqli_stmt_bind_param($stmt, 'i', $typeId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rows = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
            mysqli_free_result($result);
        }
        mysqli_stmt_close($stmt);
        return $rows;
    }
}

if (!function_exists('hg_inventory_fetch_mobile_types')) {
    function hg_inventory_fetch_mobile_types(mysqli $link): array
    {
        $result = mysqli_query(
            $link,
            "SELECT t.id, t.name, t.pretty_id, COUNT(i.id) AS item_count,
                    MIN(NULLIF(i.image_url, '')) AS cover_image
             FROM dim_item_types t
             LEFT JOIN fact_items i ON i.item_type_id = t.id
             GROUP BY t.id, t.name, t.pretty_id
             ORDER BY t.name ASC, t.id ASC"
        );
        if (!$result) return [];
        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
        mysqli_free_result($result);
        return $rows;
    }
}

if (!function_exists('hg_inventory_fetch_mobile_catalog')) {
    function hg_inventory_fetch_mobile_catalog(mysqli $link): ?array
    {
        $result = mysqli_query(
            $link,
            "SELECT i.id AS item_id, i.pretty_id AS item_pretty_id, i.name AS item_name,
                    i.image_url AS item_img, i.description, i.item_type_id,
                    t.name AS item_category, t.pretty_id AS item_type_pretty,
                    COALESCE(b.name, '') AS item_origin
             FROM fact_items i
             LEFT JOIN dim_item_types t ON t.id = i.item_type_id
             LEFT JOIN dim_bibliographies b ON b.id = i.bibliography_id
             ORDER BY t.name ASC, i.name ASC, i.id ASC"
        );
        if (!$result) return null;
        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
        mysqli_free_result($result);
        return $rows;
    }
}
