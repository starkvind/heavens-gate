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
        static $cache = [];
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        if ($table === '' || $column === '') return false;
        $key = $table . ':' . $column;
        if (array_key_exists($key, $cache)) return $cache[$key];

        $stmt = mysqli_prepare(
            $link,
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        if (!$stmt) return $cache[$key] = false;
        mysqli_stmt_bind_param($stmt, 'ss', $table, $column);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $count);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);
        return $cache[$key] = ((int)$count > 0);
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
