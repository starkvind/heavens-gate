<?php

if (!function_exists('hg_relationship_mobile_normalize_ids')) {
    function hg_relationship_mobile_normalize_ids($value): array
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

if (!function_exists('hg_relationship_mobile_not_in')) {
    function hg_relationship_mobile_not_in(string $column, array $ids, string &$types, array &$params): string
    {
        if (!$ids) return '1=1';
        $types .= str_repeat('i', count($ids));
        foreach ($ids as $id) $params[] = $id;
        return $column . ' NOT IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
    }
}

if (!function_exists('hg_relationship_mobile_fetch_organizations')) {
    function hg_relationship_mobile_fetch_organizations(mysqli $link): ?array
    {
        $sql = "SELECT o.id, o.name, '' AS system_name, COALESCE(t.name, '') AS totem_name
                FROM dim_organizations o
                LEFT JOIN dim_totems t ON t.id = o.totem_id
                ORDER BY o.sort_order ASC, o.name ASC";
        $result = mysqli_query($link, $sql);
        if (!$result) {
            $result = mysqli_query($link, 'SELECT id, name FROM dim_organizations ORDER BY name ASC');
            if (!$result) return null;
            $rows = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $row['system_name'] = '';
                $row['totem_name'] = '';
                $rows[] = $row;
            }
            mysqli_free_result($result);
            return $rows;
        }
        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
        mysqli_free_result($result);
        return $rows;
    }
}

if (!function_exists('hg_relationship_mobile_fetch_groups')) {
    function hg_relationship_mobile_fetch_groups(mysqli $link, $excludedChronicles = '2,7'): ?array
    {
        $ids = hg_relationship_mobile_normalize_ids($excludedChronicles);
        $types = '';
        $params = [];
        $groupCond = hg_relationship_mobile_not_in('g.chronicle_id', $ids, $types, $params);
        $characterTypes = '';
        $characterParams = [];
        $characterCond = hg_relationship_mobile_not_in('p.chronicle_id', $ids, $characterTypes, $characterParams);

        $sql = "SELECT
                    g.id,
                    g.name,
                    g.is_active,
                    COALESCE(ch.name, '') AS chronicle_name,
                    COALESCE(t.name, '') AS totem_name,
                    COALESCE(o.id, 0) AS organization_id,
                    COALESCE(o.name, 'Sin organizacion') AS organization_name,
                    COALESCE(o.sort_order, 999999) AS organization_sort_order,
                    (
                        SELECT COUNT(DISTINCT bcg.character_id)
                        FROM bridge_characters_groups bcg
                        INNER JOIN fact_characters p ON p.id = bcg.character_id
                        WHERE bcg.group_id = g.id
                          AND (bcg.is_active = 1 OR bcg.is_active IS NULL)
                          AND {$characterCond}
                    ) AS member_count
                FROM dim_groups g
                LEFT JOIN dim_chronicles ch ON ch.id = g.chronicle_id
                LEFT JOIN dim_totems t ON t.id = g.totem_id
                LEFT JOIN bridge_organizations_groups bog
                    ON bog.group_id = g.id
                   AND (bog.is_active = 1 OR bog.is_active IS NULL)
                LEFT JOIN dim_organizations o ON o.id = bog.organization_id
                WHERE {$groupCond}
                ORDER BY COALESCE(o.sort_order, 999999), o.name, g.is_active DESC, g.name ASC";

        $allTypes = $characterTypes . $types;
        $allParams = array_merge($characterParams, $params);
        $stmt = mysqli_prepare($link, $sql);
        if (!$stmt) return null;
        if ($allTypes !== '') mysqli_stmt_bind_param($stmt, $allTypes, ...$allParams);
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

if (!function_exists('hg_relationship_mobile_fetch_member_counts_by_organization')) {
    function hg_relationship_mobile_fetch_member_counts_by_organization(mysqli $link, $excludedChronicles = '2,7'): ?array
    {
        $ids = hg_relationship_mobile_normalize_ids($excludedChronicles);
        $typesA = '';
        $paramsA = [];
        $condA = hg_relationship_mobile_not_in('p.chronicle_id', $ids, $typesA, $paramsA);
        $typesB = '';
        $paramsB = [];
        $condB = hg_relationship_mobile_not_in('p.chronicle_id', $ids, $typesB, $paramsB);
        $sql = "SELECT organization_id, COUNT(DISTINCT character_id) AS member_count
                FROM (
                    SELECT bco.organization_id, bco.character_id
                    FROM bridge_characters_organizations bco
                    INNER JOIN fact_characters p ON p.id = bco.character_id
                    WHERE (bco.is_active = 1 OR bco.is_active IS NULL)
                      AND {$condA}
                    UNION ALL
                    SELECT bog.organization_id, bcg.character_id
                    FROM bridge_characters_groups bcg
                    INNER JOIN bridge_organizations_groups bog ON bog.group_id = bcg.group_id
                    INNER JOIN fact_characters p ON p.id = bcg.character_id
                    WHERE (bcg.is_active = 1 OR bcg.is_active IS NULL)
                      AND (bog.is_active = 1 OR bog.is_active IS NULL)
                      AND {$condB}
                ) x
                GROUP BY organization_id";
        $types = $typesA . $typesB;
        $params = array_merge($paramsA, $paramsB);
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
        $counts = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $counts[(int)$row['organization_id']] = (int)$row['member_count'];
        }
        mysqli_free_result($result);
        mysqli_stmt_close($stmt);
        return $counts;
    }
}
