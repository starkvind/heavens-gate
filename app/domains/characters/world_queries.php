<?php

if (!function_exists('hg_character_worlds_schema_ready')) {
    function hg_character_worlds_schema_ready(mysqli $link): bool
    {
        $stmtTable = mysqli_prepare(
            $link,
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        if (!$stmtTable) return false;
        $table = 'dim_realities';
        mysqli_stmt_bind_param($stmtTable, 's', $table);
        mysqli_stmt_execute($stmtTable);
        mysqli_stmt_bind_result($stmtTable, $tableCount);
        mysqli_stmt_fetch($stmtTable);
        mysqli_stmt_close($stmtTable);
        if ((int)$tableCount <= 0) return false;

        $stmtColumn = mysqli_prepare(
            $link,
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        if (!$stmtColumn) return false;
        $characterTable = 'fact_characters';
        $column = 'reality_id';
        mysqli_stmt_bind_param($stmtColumn, 'ss', $characterTable, $column);
        mysqli_stmt_execute($stmtColumn);
        mysqli_stmt_bind_result($stmtColumn, $columnCount);
        mysqli_stmt_fetch($stmtColumn);
        mysqli_stmt_close($stmtColumn);
        return (int)$columnCount > 0;
    }
}

if (!function_exists('hg_character_worlds_normalize_ids')) {
    function hg_character_worlds_normalize_ids($value): array
    {
        $ids = [];
        foreach (preg_split('/\s*,\s*/', trim((string)$value)) as $part) {
            if ($part !== '' && preg_match('/^\d+$/', (string)$part)) {
                $id = (int)$part;
                if ($id > 0) $ids[$id] = $id;
            }
        }
        return array_values($ids);
    }
}

if (!function_exists('hg_character_worlds_fetch')) {
    function hg_character_worlds_fetch(
        mysqli $link,
        int $realityId = 0,
        $excludedChronicles = ''
    ): ?array {
        $kindSql = function_exists('hg_character_kind_select') ? hg_character_kind_select($link, 'p') : "''";
        $sql = "SELECT p.id, p.name, p.alias, COALESCE(dcs.label, '') AS status,
                       p.status_id, p.image_url, p.gender, {$kindSql} AS character_kind,
                       p.reality_id, COALESCE(r.name, 'Sin realidad') AS reality_name
                FROM fact_characters p
                LEFT JOIN dim_character_status dcs ON dcs.id = p.status_id
                LEFT JOIN dim_realities r ON r.id = p.reality_id
                WHERE 1=1";
        $types = '';
        $params = [];

        $excludedIds = hg_character_worlds_normalize_ids($excludedChronicles);
        if ($excludedIds) {
            $sql .= ' AND p.chronicle_id NOT IN (' . implode(',', array_fill(0, count($excludedIds), '?')) . ')';
            $types .= str_repeat('i', count($excludedIds));
            foreach ($excludedIds as $id) $params[] = $id;
        }
        if ($realityId > 0) {
            $sql .= ' AND p.reality_id = ?';
            $types .= 'i';
            $params[] = $realityId;
        }
        $sql .= ' ORDER BY r.name ASC, p.name ASC';

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
