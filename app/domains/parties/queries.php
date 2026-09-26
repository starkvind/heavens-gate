<?php

if (!function_exists('hg_parties_normalize_chronicle_ids')) {
    function hg_parties_normalize_chronicle_ids($value): array
    {
        $value = trim((string)$value);
        if ($value === '') return [];
        $ids = [];
        foreach (preg_split('/\s*,\s*/', $value) as $part) {
            if (preg_match('/^\d+$/', (string)$part)) {
                $id = (int)$part;
                if ($id > 0) $ids[$id] = $id;
            }
        }
        return array_values($ids);
    }
}

if (!function_exists('hg_parties_fetch_active')) {
    function hg_parties_fetch_active(mysqli $link): ?array
    {
        $result = mysqli_query(
            $link,
            'SELECT hp.id, hp.name, hp.description FROM dim_parties hp WHERE hp.active = 1 ORDER BY hp.sort_order ASC'
        );
        if (!$result) return null;

        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[(int)$row['id']] = $row;
            $rows[(int)$row['id']]['characters'] = [];
        }
        mysqli_free_result($result);
        return $rows;
    }
}

if (!function_exists('hg_parties_fetch_active_members')) {
    function hg_parties_fetch_active_members(mysqli $link, array $excludedChronicleIds = []): ?array
    {
        $sql = "SELECT c.id, c.party_id, c.base_char_id,
                       c.m_hp, c.m_rage, c.m_gnosis, c.m_glamour, c.m_mana, c.m_blood, c.m_wp,
                       c.alias AS nombre, p.image_url AS avatar
                FROM fact_party_members c
                JOIN fact_characters p ON p.id = c.base_char_id
                LEFT JOIN dim_parties hp ON c.party_id = hp.id
                WHERE c.active = 1 AND hp.active = 1";
        $types = '';
        $params = [];
        if ($excludedChronicleIds) {
            $ids = array_values(array_filter(array_map('intval', $excludedChronicleIds), static fn($id) => $id > 0));
            if ($ids) {
                $sql .= ' AND p.chronicle_id NOT IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
                $types = str_repeat('i', count($ids));
                $params = $ids;
            }
        }

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

if (!function_exists('hg_parties_fetch_resource_changes')) {
    function hg_parties_fetch_resource_changes(mysqli $link): ?array
    {
        $result = mysqli_query(
            $link,
            'SELECT party_member_id, resource, SUM(value) AS total FROM fact_party_members_changes GROUP BY party_member_id, resource'
        );
        if (!$result) return null;

        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
        mysqli_free_result($result);
        return $rows;
    }
}
