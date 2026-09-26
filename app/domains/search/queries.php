<?php

if (!function_exists('hg_search_query_item_slug_row')) {
    function hg_search_query_item_slug_row(mysqli $link, int $itemId): ?array
    {
        if ($itemId <= 0) return null;
        $stmt = mysqli_prepare(
            $link,
            "SELECT i.pretty_id AS item_pretty, t.pretty_id AS type_pretty, t.id AS type_id
             FROM fact_items i
             LEFT JOIN dim_item_types t ON t.id = i.item_type_id
             WHERE i.id = ? LIMIT 1"
        );
        if (!$stmt) return null;
        mysqli_stmt_bind_param($stmt, 'i', $itemId);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return null;
        }
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? (mysqli_fetch_assoc($result) ?: null) : null;
        if ($result) mysqli_free_result($result);
        mysqli_stmt_close($stmt);
        return $row;
    }
}

if (!function_exists('hg_search_query_build_where')) {
    function hg_search_query_build_where(array $fields, array $terms, array &$params, string &$types): string
    {
        $whereParts = [];
        foreach ($terms as $term) {
            $like = '%' . $term . '%';
            $sub = [];
            foreach ($fields as $field) {
                $sub[] = $field . ' LIKE ?';
                $params[] = $like;
                $types .= 's';
            }
            if ($sub) $whereParts[] = '(' . implode(' OR ', $sub) . ')';
        }
        return implode(' AND ', $whereParts);
    }
}

if (!function_exists('hg_search_query_fetch_section')) {
    function hg_search_query_fetch_section(
        mysqli $link,
        array $config,
        array $terms,
        int $limit,
        string $baseWhere = ''
    ): ?array {
        $params = [];
        $types = '';
        $termWhere = hg_search_query_build_where($config['search_fields'] ?? [], $terms, $params, $types);
        $parts = array_values(array_filter([$baseWhere, $termWhere], static fn($part) => trim((string)$part) !== ''));
        if (!$parts) return [];

        $sql = "SELECT
                    {$config['id_expr']} AS result_id,
                    {$config['title_expr']} AS result_title,
                    {$config['excerpt_expr']} AS result_excerpt,
                    {$config['secondary_expr']} AS result_secondary
                FROM {$config['from_sql']}
                WHERE " . implode(' AND ', $parts);
        if (!empty($config['group_sql'])) {
            $sql .= " GROUP BY {$config['group_sql']}";
        }
        $sql .= " ORDER BY {$config['order_sql']} LIMIT " . max(1, (int)$limit);

        $stmt = mysqli_prepare($link, $sql);
        if (!$stmt) return null;
        if ($types !== '') mysqli_stmt_bind_param($stmt, $types, ...$params);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return null;
        }
        $result = mysqli_stmt_get_result($stmt);
        if (!$result) {
            mysqli_stmt_close($stmt);
            return null;
        }

        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
        mysqli_free_result($result);
        mysqli_stmt_close($stmt);
        return $rows;
    }
}
