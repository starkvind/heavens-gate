<?php

if (!function_exists('hg_character_worlds_schema_ready')) {
    function hg_character_worlds_schema_ready(mysqli $link): bool
    {
        return true;
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
        $kindSql = hg_character_kind_select($link, 'p');
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
