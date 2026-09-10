<?php

if (!function_exists('hg_characters_normalize_int_csv')) {
    function hg_characters_normalize_int_csv($csv): string
    {
        $csv = trim((string)$csv);
        if ($csv === '' || strtoupper($csv) === 'FALSE') {
            return '';
        }

        $ids = [];
        foreach (preg_split('/\s*,\s*/', $csv) as $part) {
            if (preg_match('/^\d+$/', (string)$part)) {
                $ids[] = (string)(int)$part;
            }
        }

        return implode(',', array_values(array_unique($ids)));
    }
}

if (!function_exists('hg_characters_has_column')) {
    function hg_characters_has_column(mysqli $link, string $table, string $column): bool
    {
        static $cache = [];
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        if ($table === '' || $column === '') {
            return false;
        }

        $key = $table . ':' . $column;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $result = mysqli_query($link, "SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
        if (!$result) {
            return $cache[$key] = false;
        }

        $exists = mysqli_num_rows($result) > 0;
        mysqli_free_result($result);
        return $cache[$key] = $exists;
    }
}

if (!function_exists('hg_characters_fetch_types')) {
    function hg_characters_fetch_types(mysqli $link)
    {
        $imageSelect = hg_characters_has_column($link, 'dim_character_types', 'image_url')
            ? ", COALESCE(image_url, '') AS image_url"
            : ", '' AS image_url";
        $descriptionSelect = hg_characters_has_column($link, 'dim_character_types', 'description')
            ? ", COALESCE(description, '') AS description"
            : ", '' AS description";

        $result = mysqli_query(
            $link,
            "SELECT id, kind {$imageSelect} {$descriptionSelect} FROM dim_character_types ORDER BY sort_order, kind"
        );
        if (!$result) {
            return false;
        }

        $types = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $types[] = [
                'id' => (int)($row['id'] ?? 0),
                'name' => (string)($row['kind'] ?? ''),
                'kind' => (string)($row['kind'] ?? ''),
                'image_url' => (string)($row['image_url'] ?? ''),
                'description' => (string)($row['description'] ?? ''),
            ];
        }
        mysqli_free_result($result);

        return $types;
    }
}

if (!function_exists('hg_characters_collect_type_cards')) {
    function hg_characters_collect_type_cards(
        mysqli $link,
        array $types,
        string $typeColumn,
        string $excludedChronicles
    ): ?array {
        $typeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $typeColumn);
        if ($typeColumn === '') {
            return null;
        }

        $excluded = hg_characters_normalize_int_csv($excludedChronicles);
        $chronicleWhere = $excluded !== '' ? " AND p.chronicle_id NOT IN ({$excluded})" : '';
        $stmt = mysqli_prepare(
            $link,
            "SELECT COUNT(DISTINCT p.id) AS total, MIN(NULLIF(p.image_url, '')) AS representative_image_url
             FROM fact_characters p
             WHERE p.`{$typeColumn}` = ? {$chronicleWhere}"
        );
        if (!$stmt) {
            return null;
        }

        $cards = [];
        foreach ($types as $type) {
            $typeId = (int)($type['id'] ?? 0);
            if ($typeId <= 0) {
                continue;
            }

            mysqli_stmt_bind_param($stmt, 'i', $typeId);
            if (!mysqli_stmt_execute($stmt)) {
                continue;
            }
            $result = mysqli_stmt_get_result($stmt);
            $row = $result ? mysqli_fetch_assoc($result) : null;
            if ($result) {
                mysqli_free_result($result);
            }

            $total = (int)($row['total'] ?? 0);
            if ($total <= 0) {
                continue;
            }

            $image = trim((string)($type['image_url'] ?? ''));
            if ($image === '') {
                $image = trim((string)($row['representative_image_url'] ?? ''));
            }
            if (strpos($image, '/public/') === 0) {
                $image = substr($image, 7);
            }
            if ($image === '') {
                $image = '/img/og/og_image_bio.webp';
            }

            $cards[] = [
                'id' => $typeId,
                'name' => (string)($type['name'] ?? $type['kind'] ?? ''),
                'total' => $total,
                'image_url' => $image,
                'description' => trim((string)($type['description'] ?? '')),
            ];
        }
        mysqli_stmt_close($stmt);

        return $cards;
    }
}

if (!function_exists('hg_characters_fetch_type_cards')) {
    function hg_characters_fetch_type_cards(mysqli $link, $excludedChronicles = '')
    {
        $types = hg_characters_fetch_types($link);
        if ($types === false) {
            return false;
        }

        foreach (['character_type_id', 'kind', 'tipo'] as $typeColumn) {
            $cards = hg_characters_collect_type_cards($link, $types, $typeColumn, (string)$excludedChronicles);
            if ($cards === null) {
                continue;
            }
            if (!empty($cards)) {
                return $cards;
            }
        }

        return [];
    }
}

if (!function_exists('hg_characters_chronicle_condition')) {
    function hg_characters_chronicle_condition(string $alias, $excludedChronicles = '2,7'): string
    {
        $excluded = hg_characters_normalize_int_csv($excludedChronicles);
        if ($excluded === '') {
            return '1=1';
        }

        $alias = preg_replace('/[^a-zA-Z0-9_]/', '', $alias);
        $column = $alias !== '' ? "{$alias}.chronicle_id" : 'chronicle_id';
        return "{$column} NOT IN ({$excluded})";
    }
}

if (!function_exists('hg_characters_mobile_filter_options')) {
    function hg_characters_mobile_filter_options(mysqli $link, $excludedChronicles = '2,7'): array
    {
        $conditionP = hg_characters_chronicle_condition('p', $excludedChronicles);
        $conditionBare = hg_characters_chronicle_condition('', $excludedChronicles);
        $filters = [
            'types' => [],
            'groups' => [],
            'organizations' => [],
            'systems' => [],
            'statuses' => [],
        ];

        if ($result = mysqli_query($link, "SELECT id, kind FROM dim_character_types ORDER BY sort_order, kind")) {
            while ($row = mysqli_fetch_assoc($result)) {
                $filters['types'][] = $row;
            }
            mysqli_free_result($result);
        }

        $groupSql = "
            SELECT g.id, g.name
            FROM dim_groups g
            WHERE EXISTS (
                SELECT 1
                FROM bridge_characters_groups bcg
                INNER JOIN fact_characters p ON p.id = bcg.character_id
                WHERE bcg.group_id = g.id
                  AND {$conditionP}
                  AND (bcg.is_active = 1 OR bcg.is_active IS NULL)
            )
            ORDER BY g.name
        ";
        if ($result = mysqli_query($link, $groupSql)) {
            while ($row = mysqli_fetch_assoc($result)) {
                $filters['groups'][] = $row;
            }
            mysqli_free_result($result);
        }

        $organizationSql = "
            SELECT o.id, o.name
            FROM dim_organizations o
            WHERE EXISTS (
                SELECT 1
                FROM bridge_characters_organizations bco
                INNER JOIN fact_characters p ON p.id = bco.character_id
                WHERE bco.organization_id = o.id
                  AND {$conditionP}
                  AND (bco.is_active = 1 OR bco.is_active IS NULL)
            )
            OR EXISTS (
                SELECT 1
                FROM bridge_organizations_groups bog
                INNER JOIN bridge_characters_groups bcg ON bcg.group_id = bog.group_id
                INNER JOIN fact_characters p ON p.id = bcg.character_id
                WHERE bog.organization_id = o.id
                  AND {$conditionP}
                  AND (bog.is_active = 1 OR bog.is_active IS NULL)
                  AND (bcg.is_active = 1 OR bcg.is_active IS NULL)
            )
            ORDER BY o.name
        ";
        if ($result = mysqli_query($link, $organizationSql)) {
            while ($row = mysqli_fetch_assoc($result)) {
                $filters['organizations'][] = $row;
            }
            mysqli_free_result($result);
        }

        $systemSql = "SELECT id, name FROM dim_systems
                      WHERE id IN (
                          SELECT DISTINCT system_id FROM fact_characters
                          WHERE system_id IS NOT NULL AND {$conditionBare}
                      )
                      ORDER BY name";
        if ($result = mysqli_query($link, $systemSql)) {
            while ($row = mysqli_fetch_assoc($result)) {
                $filters['systems'][] = $row;
            }
            mysqli_free_result($result);
        }

        $statusSql = "SELECT id, label FROM dim_character_status
                      WHERE id IN (
                          SELECT DISTINCT status_id FROM fact_characters
                          WHERE status_id IS NOT NULL AND {$conditionBare}
                      )
                      ORDER BY label";
        if ($result = mysqli_query($link, $statusSql)) {
            while ($row = mysqli_fetch_assoc($result)) {
                $filters['statuses'][] = $row;
            }
            mysqli_free_result($result);
        }

        return $filters;
    }
}

if (!function_exists('hg_characters_mobile_where')) {
    function hg_characters_mobile_where(mysqli $link, array $filters, $excludedChronicles = '2,7'): string
    {
        $where = [hg_characters_chronicle_condition('p', $excludedChronicles)];

        $queryText = trim((string)($filters['query'] ?? ''));
        if ($queryText !== '') {
            $like = mysqli_real_escape_string($link, '%' . $queryText . '%');
            $where[] = "(p.name LIKE '{$like}' OR p.alias LIKE '{$like}' OR p.concept LIKE '{$like}')";
        }

        $typeId = max(0, (int)($filters['type'] ?? 0));
        if ($typeId > 0) {
            $where[] = "p.character_type_id = {$typeId}";
        }

        $groupId = max(0, (int)($filters['group'] ?? 0));
        if ($groupId > 0) {
            $where[] = "EXISTS (
                SELECT 1 FROM bridge_characters_groups bcg
                WHERE bcg.character_id = p.id
                  AND bcg.group_id = {$groupId}
                  AND (bcg.is_active = 1 OR bcg.is_active IS NULL)
            )";
        }

        $organizationId = max(0, (int)($filters['organization'] ?? 0));
        if ($organizationId > 0) {
            $where[] = "(
                EXISTS (
                    SELECT 1 FROM bridge_characters_organizations bco
                    WHERE bco.character_id = p.id
                      AND bco.organization_id = {$organizationId}
                      AND (bco.is_active = 1 OR bco.is_active IS NULL)
                )
                OR EXISTS (
                    SELECT 1
                    FROM bridge_characters_groups bcg
                    INNER JOIN bridge_organizations_groups bog ON bog.group_id = bcg.group_id
                    WHERE bcg.character_id = p.id
                      AND bog.organization_id = {$organizationId}
                      AND (bcg.is_active = 1 OR bcg.is_active IS NULL)
                      AND (bog.is_active = 1 OR bog.is_active IS NULL)
                )
            )";
        }

        $systemId = max(0, (int)($filters['system'] ?? 0));
        if ($systemId > 0) {
            $where[] = "p.system_id = {$systemId}";
        }

        $statusId = max(0, (int)($filters['status'] ?? 0));
        if ($statusId > 0) {
            $where[] = "p.status_id = {$statusId}";
        }

        return implode(' AND ', $where);
    }
}

if (!function_exists('hg_characters_fetch_mobile_page')) {
    function hg_characters_fetch_mobile_page(
        mysqli $link,
        array $filters,
        $excludedChronicles,
        int $page,
        int $pageSize
    ): array {
        $page = max(1, $page);
        $pageSize = max(1, $pageSize);
        $whereSql = hg_characters_mobile_where($link, $filters, $excludedChronicles);

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

if (!function_exists('hg_characters_fetch_table_rows')) {
    function hg_characters_fetch_table_rows(mysqli $link, $excludedChronicles = '2,7')
    {
        $typeColumn = '';
        foreach (['character_type_id', 'kind', 'tipo'] as $candidate) {
            if (hg_characters_has_column($link, 'fact_characters', $candidate)) {
                $typeColumn = $candidate;
                break;
            }
        }
        if ($typeColumn === '') {
            return false;
        }

        $whereChronicle = hg_characters_chronicle_condition('p', $excludedChronicles);
        $sql = "
            SELECT
                p.id,
                p.pretty_id AS character_pretty_id,
                p.name AS character_name,
                p.alias,
                p.concept,
                p.image_url,
                p.gender,
                nm2.id AS pack_id,
                nm2.pretty_id AS pack_pretty_id,
                nm2.name AS pack_name,
                nc2.id AS organization_id,
                nc2.pretty_id AS clan_pretty_id,
                nc2.name AS clan_name,
                nc_from_pack.id AS clan_from_pack_id,
                nc_from_pack.pretty_id AS clan_from_pack_pretty_id,
                nc_from_pack.name AS clan_from_pack_name,
                a.id AS type_id,
                a.pretty_id AS type_pretty_id,
                a.kind AS type_name,
                s.name AS system_name,
                COALESCE(dcs.label, '') AS status,
                p.status_id
            FROM fact_characters p
            LEFT JOIN dim_character_status dcs ON dcs.id = p.status_id
            LEFT JOIN bridge_characters_groups hcg
                ON hcg.character_id = p.id
               AND (hcg.is_active = 1 OR hcg.is_active IS NULL)
            LEFT JOIN dim_groups nm2 ON nm2.id = hcg.group_id
            LEFT JOIN bridge_characters_organizations hcc
                ON hcc.character_id = p.id
               AND (hcc.is_active = 1 OR hcc.is_active IS NULL)
            LEFT JOIN dim_organizations nc2 ON nc2.id = hcc.organization_id
            LEFT JOIN bridge_organizations_groups hcg2
                ON hcg2.group_id = nm2.id
               AND (hcg2.is_active = 1 OR hcg2.is_active IS NULL)
            LEFT JOIN dim_organizations nc_from_pack ON nc_from_pack.id = hcg2.organization_id
            LEFT JOIN dim_character_types a ON a.id = p.`{$typeColumn}`
            LEFT JOIN dim_systems s ON s.id = p.system_id
            WHERE {$whereChronicle}
            ORDER BY p.name ASC
        ";

        $result = mysqli_query($link, $sql);
        if (!$result) {
            return false;
        }

        $characters = [];
        while ($row = mysqli_fetch_assoc($result)) {
            if (empty($row['organization_id']) && !empty($row['clan_from_pack_id'])) {
                $row['organization_id'] = $row['clan_from_pack_id'];
                $row['clan_name'] = $row['clan_from_pack_name'];
                $row['clan_pretty_id'] = $row['clan_from_pack_pretty_id'];
            }
            unset($row['clan_from_pack_id'], $row['clan_from_pack_name'], $row['clan_from_pack_pretty_id']);
            $characters[] = $row;
        }
        mysqli_free_result($result);

        return $characters;
    }
}
