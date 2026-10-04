<?php

require_once __DIR__ . '/queries.php';

if (!function_exists('hg_characters_multifilter_ids')) {
    function hg_characters_multifilter_ids($value): array
    {
        $parts = is_array($value)
            ? $value
            : preg_split('/\s*,\s*/', trim((string)$value), -1, PREG_SPLIT_NO_EMPTY);
        $ids = [];
        foreach ($parts ?: [] as $part) {
            if (!preg_match('/^\d+$/', trim((string)$part))) {
                continue;
            }
            $id = (int)$part;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        return array_values($ids);
    }
}

if (!function_exists('hg_characters_mobile_multiselect_where')) {
    function hg_characters_mobile_multiselect_where(mysqli $link, array $filters, $excludedChronicles = '2,7'): string
    {
        $where = [hg_characters_chronicle_condition('p', $excludedChronicles)];

        $queryText = trim((string)($filters['query'] ?? ''));
        if ($queryText !== '') {
            $like = mysqli_real_escape_string($link, '%' . $queryText . '%');
            $where[] = "(p.name LIKE '{$like}' OR p.alias LIKE '{$like}' OR p.concept LIKE '{$like}')";
        }

        $typeIds = hg_characters_multifilter_ids($filters['type'] ?? []);
        if ($typeIds) {
            $where[] = 'p.character_type_id IN (' . implode(',', $typeIds) . ')';
        }

        $groupIds = hg_characters_multifilter_ids($filters['group'] ?? []);
        if ($groupIds) {
            $where[] = "EXISTS (
                SELECT 1 FROM bridge_characters_groups bcg
                WHERE bcg.character_id = p.id
                  AND bcg.group_id IN (" . implode(',', $groupIds) . ")
                  AND (bcg.is_active = 1 OR bcg.is_active IS NULL)
            )";
        }

        $organizationIds = hg_characters_multifilter_ids($filters['organization'] ?? []);
        if ($organizationIds) {
            $organizationCsv = implode(',', $organizationIds);
            $where[] = "(
                EXISTS (
                    SELECT 1 FROM bridge_characters_organizations bco
                    WHERE bco.character_id = p.id
                      AND bco.organization_id IN ({$organizationCsv})
                      AND (bco.is_active = 1 OR bco.is_active IS NULL)
                )
                OR EXISTS (
                    SELECT 1
                    FROM bridge_characters_groups bcg
                    INNER JOIN bridge_organizations_groups bog ON bog.group_id = bcg.group_id
                    WHERE bcg.character_id = p.id
                      AND bog.organization_id IN ({$organizationCsv})
                      AND (bcg.is_active = 1 OR bcg.is_active IS NULL)
                      AND (bog.is_active = 1 OR bog.is_active IS NULL)
                )
            )";
        }

        $systemIds = hg_characters_multifilter_ids($filters['system'] ?? []);
        if ($systemIds) {
            $where[] = 'p.system_id IN (' . implode(',', $systemIds) . ')';
        }

        $statusIds = hg_characters_multifilter_ids($filters['status'] ?? []);
        if ($statusIds) {
            $where[] = 'p.status_id IN (' . implode(',', $statusIds) . ')';
        }

        return implode(' AND ', $where);
    }
}

if (!function_exists('hg_characters_fetch_mobile_multiselect_page')) {
    function hg_characters_fetch_mobile_multiselect_page(
        mysqli $link,
        array $filters,
        $excludedChronicles,
        int $page,
        int $pageSize
    ): array {
        $page = max(1, $page);
        $pageSize = max(1, $pageSize);
        $whereSql = hg_characters_mobile_multiselect_where($link, $filters, $excludedChronicles);

        $total = 0;
        $countError = '';
        $countResult = mysqli_query(
            $link,
            "SELECT COUNT(DISTINCT p.id) AS total FROM fact_characters p WHERE {$whereSql}"
        );
        if ($countResult) {
            $row = mysqli_fetch_assoc($countResult);
            $total = (int)($row['total'] ?? 0);
            mysqli_free_result($countResult);
        } else {
            $countError = mysqli_error($link);
        }

        $totalPages = max(1, (int)ceil($total / $pageSize));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $pageSize;

        $sql = "
            SELECT
                p.id,
                p.pretty_id,
                p.name,
                p.alias,
                p.concept,
                p.image_url,
                p.character_type_id,
                p.system_id,
                COALESCE(ct.kind, '') AS type_name,
                COALESCE(sys.name, '') AS system_name,
                COALESCE(dcs.label, '') AS status_label,
                (
                    SELECT g.name
                    FROM bridge_characters_groups bcg
                    INNER JOIN dim_groups g ON g.id = bcg.group_id
                    WHERE bcg.character_id = p.id
                      AND (bcg.is_active = 1 OR bcg.is_active IS NULL)
                    ORDER BY bcg.group_id
                    LIMIT 1
                ) AS pack_name,
                COALESCE(
                    (
                        SELECT o.name
                        FROM bridge_characters_organizations bco
                        INNER JOIN dim_organizations o ON o.id = bco.organization_id
                        WHERE bco.character_id = p.id
                          AND (bco.is_active = 1 OR bco.is_active IS NULL)
                        ORDER BY bco.organization_id
                        LIMIT 1
                    ),
                    (
                        SELECT o2.name
                        FROM bridge_characters_groups bcg2
                        INNER JOIN bridge_organizations_groups bog ON bog.group_id = bcg2.group_id
                        INNER JOIN dim_organizations o2 ON o2.id = bog.organization_id
                        WHERE bcg2.character_id = p.id
                          AND (bcg2.is_active = 1 OR bcg2.is_active IS NULL)
                          AND (bog.is_active = 1 OR bog.is_active IS NULL)
                        ORDER BY bog.organization_id
                        LIMIT 1
                    ),
                    ''
                ) AS organization_name
            FROM fact_characters p
            LEFT JOIN dim_character_types ct ON ct.id = p.character_type_id
            LEFT JOIN dim_systems sys ON sys.id = p.system_id
            LEFT JOIN dim_character_status dcs ON dcs.id = p.status_id
            WHERE {$whereSql}
            ORDER BY p.name ASC
            LIMIT {$pageSize} OFFSET {$offset}
        ";

        $characters = [];
        $listError = '';
        $result = mysqli_query($link, $sql);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $characters[] = $row;
            }
            mysqli_free_result($result);
        } else {
            $listError = mysqli_error($link);
        }

        return [
            'total' => $total,
            'total_pages' => $totalPages,
            'page' => $page,
            'characters' => $characters,
            'count_error' => $countError,
            'list_error' => $listError,
        ];
    }
}
