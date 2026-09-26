<?php

if (!function_exists('hg_character_types_normalize_excluded_ids')) {
    function hg_character_types_normalize_excluded_ids($excludedChronicles): array
    {
        $ids = [];
        foreach (preg_split('/\s*,\s*/', trim((string)$excludedChronicles)) as $part) {
            if ($part !== '' && preg_match('/^\d+$/', (string)$part)) {
                $id = (int)$part;
                if ($id > 0) $ids[$id] = $id;
            }
        }
        return array_values($ids);
    }
}

if (!function_exists('hg_character_types_fetch_one')) {
    function hg_character_types_fetch_one(mysqli $link, int $typeId): ?array
    {
        if ($typeId <= 0) return null;
        $stmt = mysqli_prepare(
            $link,
            "SELECT id, kind, COALESCE(image_url, '') AS image_url, COALESCE(description, '') AS description
             FROM dim_character_types
             WHERE id = ?
             LIMIT 1"
        );
        if (!$stmt) return null;
        mysqli_stmt_bind_param($stmt, 'i', $typeId);
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

if (!function_exists('hg_character_types_fetch_characters_for_column')) {
    function hg_character_types_fetch_characters_for_column(
        mysqli $link,
        int $typeId,
        string $typeColumn,
        array $excludedIds
    ): ?array {
        $typeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $typeColumn);
        if ($typeId <= 0 || $typeColumn === '') return [];

        $activePackIdExpr = "
            SELECT bcg.group_id
            FROM bridge_characters_groups bcg
            WHERE bcg.character_id = p.id
              AND (bcg.is_active = 1 OR bcg.is_active IS NULL)
            ORDER BY bcg.updated_at DESC, bcg.created_at DESC, bcg.group_id DESC
            LIMIT 1
        ";
        $activePackOrgIdExpr = "
            SELECT bog.organization_id
            FROM bridge_organizations_groups bog
            WHERE bog.group_id = (($activePackIdExpr))
              AND (bog.is_active = 1 OR bog.is_active IS NULL)
            ORDER BY bog.updated_at DESC, bog.created_at DESC, bog.organization_id DESC
            LIMIT 1
        ";
        $activeDirectOrgIdExpr = "
            SELECT bco.organization_id
            FROM bridge_characters_organizations bco
            WHERE bco.character_id = p.id
              AND (bco.is_active = 1 OR bco.is_active IS NULL)
            ORDER BY bco.updated_at DESC, bco.created_at DESC, bco.organization_id DESC
            LIMIT 1
        ";
        $activePackNameExpr = "
            SELECT g.name
            FROM bridge_characters_groups bcg
            INNER JOIN dim_groups g ON g.id = bcg.group_id
            WHERE bcg.character_id = p.id
              AND (bcg.is_active = 1 OR bcg.is_active IS NULL)
            ORDER BY bcg.updated_at DESC, bcg.created_at DESC, bcg.group_id DESC
            LIMIT 1
        ";

        $sql = "SELECT
                    p.id,
                    p.pretty_id,
                    p.name,
                    p.alias,
                    p.concept,
                    p.image_url,
                    p.gender,
                    p.character_kind,
                    p.character_type_id,
                    p.status_id,
                    COALESCE(dcs.label, '') AS status,
                    COALESCE(dcs.label, '') AS status_label,
                    COALESCE(({$activePackNameExpr}), '') AS pack_name,
                    COALESCE(({$activePackOrgIdExpr}), ({$activeDirectOrgIdExpr}), 0) AS organization_id,
                    COALESCE(
                        (SELECT o.pretty_id FROM dim_organizations o WHERE o.id = ({$activePackOrgIdExpr}) LIMIT 1),
                        (SELECT o.pretty_id FROM dim_organizations o WHERE o.id = ({$activeDirectOrgIdExpr}) LIMIT 1),
                        ''
                    ) AS clan_pretty_id,
                    COALESCE(
                        (SELECT o.name FROM dim_organizations o WHERE o.id = ({$activePackOrgIdExpr}) LIMIT 1),
                        (SELECT o.name FROM dim_organizations o WHERE o.id = ({$activeDirectOrgIdExpr}) LIMIT 1),
                        'Sin clan'
                    ) AS clan_name,
                    COALESCE(
                        (SELECT o.name FROM dim_organizations o WHERE o.id = ({$activePackOrgIdExpr}) LIMIT 1),
                        (SELECT o.name FROM dim_organizations o WHERE o.id = ({$activeDirectOrgIdExpr}) LIMIT 1),
                        'Sin clan'
                    ) AS organization_name,
                    IFNULL(COALESCE(
                        (SELECT o.sort_order FROM dim_organizations o WHERE o.id = ({$activePackOrgIdExpr}) LIMIT 1),
                        (SELECT o.sort_order FROM dim_organizations o WHERE o.id = ({$activeDirectOrgIdExpr}) LIMIT 1)
                    ), 999999) AS organization_sort_order
                FROM fact_characters p
                LEFT JOIN dim_character_status dcs ON dcs.id = p.status_id
                WHERE p.`{$typeColumn}` = ?";
        $types = 'i';
        $params = [$typeId];
        if ($excludedIds) {
            $sql .= ' AND p.chronicle_id NOT IN (' . implode(',', array_fill(0, count($excludedIds), '?')) . ')';
            $types .= str_repeat('i', count($excludedIds));
            foreach ($excludedIds as $id) $params[] = $id;
        }
        $sql .= ' ORDER BY organization_sort_order ASC, organization_name ASC, p.name ASC';

        $stmt = mysqli_prepare($link, $sql);
        if (!$stmt) return null;
        mysqli_stmt_bind_param($stmt, $types, ...$params);
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

if (!function_exists('hg_character_types_fetch_characters')) {
    function hg_character_types_fetch_characters(mysqli $link, int $typeId, $excludedChronicles = '2,7'): ?array
    {
        if ($typeId <= 0) return [];
        $excludedIds = hg_character_types_normalize_excluded_ids($excludedChronicles);
        return hg_character_types_fetch_characters_for_column($link, $typeId, 'character_type_id', $excludedIds);
    }
}
