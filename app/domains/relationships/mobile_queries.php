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

if (!function_exists('hg_relationship_mobile_rows')) {
    function hg_relationship_mobile_rows(mysqli $link, string $sql, string $types = '', array $params = []): ?array
    {
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
        return hg_relationship_mobile_rows($link, $sql, $allTypes, $allParams);
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
        $rows = hg_relationship_mobile_rows($link, $sql, $types, $params);
        if ($rows === null) return null;
        $counts = [];
        foreach ($rows as $row) $counts[(int)$row['organization_id']] = (int)$row['member_count'];
        return $counts;
    }
}

if (!function_exists('hg_relationship_mobile_fetch_organization')) {
    function hg_relationship_mobile_fetch_organization(mysqli $link, int $organizationId): ?array
    {
        $rows = hg_relationship_mobile_rows(
            $link,
            "SELECT o.*, COALESCE(t.name, '') AS totem_name
             FROM dim_organizations o
             LEFT JOIN dim_totems t ON t.id = o.totem_id
             WHERE o.id = ? LIMIT 1",
            'i',
            [$organizationId]
        );
        return $rows ? $rows[0] : null;
    }
}

if (!function_exists('hg_relationship_mobile_fetch_organization_groups')) {
    function hg_relationship_mobile_fetch_organization_groups(mysqli $link, int $organizationId, bool $active): ?array
    {
        return hg_relationship_mobile_rows(
            $link,
            "SELECT g.id, g.name, g.is_active, COALESCE(t.name, '') AS totem_name,
                    COUNT(DISTINCT bcg.character_id) AS member_count
             FROM bridge_organizations_groups bog
             INNER JOIN dim_groups g ON g.id = bog.group_id
             LEFT JOIN dim_totems t ON t.id = g.totem_id
             LEFT JOIN bridge_characters_groups bcg
               ON bcg.group_id = g.id
              AND (bcg.is_active = 1 OR bcg.is_active IS NULL)
             WHERE bog.organization_id = ?
               AND (bog.is_active = 1 OR bog.is_active IS NULL)
               AND g.is_active = ?
             GROUP BY g.id, g.name, g.is_active, t.name
             ORDER BY g.name ASC",
            'ii',
            [$organizationId, $active ? 1 : 0]
        );
    }
}

if (!function_exists('hg_relationship_mobile_fetch_direct_members')) {
    function hg_relationship_mobile_fetch_direct_members(mysqli $link, int $organizationId, $excludedChronicles = '2,7'): ?array
    {
        $ids = hg_relationship_mobile_normalize_ids($excludedChronicles);
        $types = 'i';
        $params = [$organizationId];
        $condition = hg_relationship_mobile_not_in('p.chronicle_id', $ids, $types, $params);
        return hg_relationship_mobile_rows(
            $link,
            "SELECT p.id, p.name, p.alias, p.image_url, p.gender,
                    COALESCE(dcs.label, '') AS status_label, bco.role
             FROM bridge_characters_organizations bco
             INNER JOIN fact_characters p ON p.id = bco.character_id
             LEFT JOIN dim_character_status dcs ON dcs.id = p.status_id
             LEFT JOIN bridge_characters_groups bcg
               ON bcg.character_id = p.id
              AND (bcg.is_active = 1 OR bcg.is_active IS NULL)
             WHERE bco.organization_id = ?
               AND (bco.is_active = 1 OR bco.is_active IS NULL)
               AND bcg.character_id IS NULL
               AND {$condition}
             ORDER BY p.name ASC",
            $types,
            $params
        );
    }
}

if (!function_exists('hg_relationship_mobile_fetch_markdown_data')) {
    function hg_relationship_mobile_fetch_markdown_data(mysqli $link, int $organizationId, $excludedChronicles = '2,7'): ?array
    {
        $ids = hg_relationship_mobile_normalize_ids($excludedChronicles);
        $memberTypes = 'i';
        $memberParams = [$organizationId];
        $memberCondition = hg_relationship_mobile_not_in('p.chronicle_id', $ids, $memberTypes, $memberParams);
        $characterFields = "p.name, COALESCE(p.alias, '') AS alias, COALESCE(p.garou_name, '') AS garou_name,
                            COALESCE(p.info_text, '') AS description, COALESCE(dcs.label, '') AS status";
        $members = hg_relationship_mobile_rows(
            $link,
            "SELECT DISTINCT {$characterFields}
             FROM bridge_characters_organizations bco
             INNER JOIN fact_characters p ON p.id = bco.character_id
             LEFT JOIN dim_character_status dcs ON dcs.id = p.status_id
             LEFT JOIN bridge_characters_groups bcg
               ON bcg.character_id = p.id
              AND (bcg.is_active = 1 OR bcg.is_active IS NULL)
             WHERE bco.organization_id = ?
               AND (bco.is_active = 1 OR bco.is_active IS NULL)
               AND bcg.character_id IS NULL
               AND {$memberCondition}
             ORDER BY p.name",
            $memberTypes,
            $memberParams
        );
        if ($members === null) return null;

        $groupTypes = 'i';
        $groupParams = [$organizationId];
        $groupCondition = hg_relationship_mobile_not_in('g.chronicle_id', $ids, $groupTypes, $groupParams);
        $groups = hg_relationship_mobile_rows(
            $link,
            "SELECT g.id, g.name, COALESCE(g.description, '') AS description
             FROM bridge_organizations_groups bog
             INNER JOIN dim_groups g ON g.id = bog.group_id
             WHERE bog.organization_id = ?
               AND (bog.is_active = 1 OR bog.is_active IS NULL)
               AND {$groupCondition}
             ORDER BY g.name",
            $groupTypes,
            $groupParams
        );
        if ($groups === null) return null;

        $data = ['members' => $members, 'groups' => []];
        foreach ($groups as $group) {
            $groupId = (int)($group['id'] ?? 0);
            $types = 'i';
            $params = [$groupId];
            $condition = hg_relationship_mobile_not_in('p.chronicle_id', $ids, $types, $params);
            $groupMembers = hg_relationship_mobile_rows(
                $link,
                "SELECT DISTINCT {$characterFields}
                 FROM bridge_characters_groups bcg
                 INNER JOIN fact_characters p ON p.id = bcg.character_id
                 LEFT JOIN dim_character_status dcs ON dcs.id = p.status_id
                 WHERE bcg.group_id = ?
                   AND (bcg.is_active = 1 OR bcg.is_active IS NULL)
                   AND {$condition}
                 ORDER BY p.name",
                $types,
                $params
            );
            if ($groupMembers === null) return null;
            $data['groups'][] = [
                'name' => (string)($group['name'] ?? ''),
                'description' => (string)($group['description'] ?? ''),
                'members' => $groupMembers,
            ];
        }
        return $data;
    }
}

if (!function_exists('hg_relationship_mobile_fetch_group')) {
    function hg_relationship_mobile_fetch_group(mysqli $link, int $groupId): ?array
    {
        $rows = hg_relationship_mobile_rows(
            $link,
            "SELECT g.*, COALESCE(ch.name, '') AS chronicle_name, COALESCE(t.name, '') AS totem_name
             FROM dim_groups g
             LEFT JOIN dim_chronicles ch ON ch.id = g.chronicle_id
             LEFT JOIN dim_totems t ON t.id = g.totem_id
             WHERE g.id = ? LIMIT 1",
            'i',
            [$groupId]
        );
        return $rows ? $rows[0] : null;
    }
}

if (!function_exists('hg_relationship_mobile_fetch_group_organization')) {
    function hg_relationship_mobile_fetch_group_organization(mysqli $link, int $groupId, int $preferredOrganizationId = 0): ?array
    {
        if ($preferredOrganizationId > 0) {
            $rows = hg_relationship_mobile_rows(
                $link,
                "SELECT o.id, o.name
                 FROM bridge_organizations_groups bog
                 INNER JOIN dim_organizations o ON o.id = bog.organization_id
                 WHERE bog.group_id = ?
                   AND bog.organization_id = ?
                   AND (bog.is_active = 1 OR bog.is_active IS NULL)
                 LIMIT 1",
                'ii',
                [$groupId, $preferredOrganizationId]
            );
            if ($rows === null) return null;
            if ($rows) return $rows[0];
        }
        $rows = hg_relationship_mobile_rows(
            $link,
            "SELECT o.id, o.name
             FROM bridge_organizations_groups bog
             INNER JOIN dim_organizations o ON o.id = bog.organization_id
             WHERE bog.group_id = ?
               AND (bog.is_active = 1 OR bog.is_active IS NULL)
             ORDER BY bog.updated_at DESC, bog.created_at DESC, bog.organization_id DESC
             LIMIT 1",
            'i',
            [$groupId]
        );
        if ($rows === null) return null;
        return $rows ? $rows[0] : ['id' => 0, 'name' => ''];
    }
}

if (!function_exists('hg_relationship_mobile_fetch_group_members')) {
    function hg_relationship_mobile_fetch_group_members(mysqli $link, int $groupId, bool $oldMembers, $excludedChronicles = '2,7'): ?array
    {
        $ids = hg_relationship_mobile_normalize_ids($excludedChronicles);
        $types = 'i';
        $params = [$groupId];
        $chronicleCondition = hg_relationship_mobile_not_in('p.chronicle_id', $ids, $types, $params);
        $membershipCondition = $oldMembers
            ? "(bcg.is_active = 0 OR LOWER(TRIM(COALESCE(dcs.label, ''))) COLLATE utf8mb4_unicode_ci = 'cadaver')"
            : "(bcg.is_active = 1 OR bcg.is_active IS NULL) AND LOWER(TRIM(COALESCE(dcs.label, ''))) COLLATE utf8mb4_unicode_ci <> 'cadaver'";
        return hg_relationship_mobile_rows(
            $link,
            "SELECT p.id, p.name, p.alias, p.image_url, p.gender,
                    COALESCE(dcs.label, '') AS status_label, bcg.position
             FROM bridge_characters_groups bcg
             INNER JOIN fact_characters p ON p.id = bcg.character_id
             LEFT JOIN dim_character_status dcs ON dcs.id = p.status_id
             WHERE bcg.group_id = ?
               AND {$membershipCondition}
               AND {$chronicleCondition}
             ORDER BY p.name ASC",
            $types,
            $params
        );
    }
}
