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

if (!function_exists('hg_characters_table_exists')) {
    function hg_characters_table_exists(mysqli $link, string $table): bool
    {
        static $cache = [];
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        if ($table === '') {
            return false;
        }
        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }

        $stmt = mysqli_prepare(
            $link,
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        if (!$stmt) {
            return $cache[$table] = false;
        }
        mysqli_stmt_bind_param($stmt, 's', $table);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $count);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);

        return $cache[$table] = ((int)$count > 0);
    }
}

if (!function_exists('hg_characters_fetch_resources')) {
    function hg_characters_fetch_resources(mysqli $link, int $characterId, int $systemId = 0): array
    {
        $out = ['renombre' => [], 'estado' => [], 'exp' => []];
        if ($characterId <= 0 || !hg_characters_table_exists($link, 'dim_systems_resources')) {
            return $out;
        }

        $bridgeTable = '';
        foreach (['bridge_characters_system_resources', 'bridge_characters_resources'] as $candidate) {
            if (hg_characters_table_exists($link, $candidate)) {
                $bridgeTable = $candidate;
                break;
            }
        }
        if ($bridgeTable === '') {
            return $out;
        }

        $hasSystemBridge = hg_characters_table_exists($link, 'bridge_systems_resources_to_system');
        $hasSystemSort = $hasSystemBridge
            && hg_characters_has_column($link, 'bridge_systems_resources_to_system', 'sort_order');
        $useSystemSort = $hasSystemSort && $systemId > 0;

        $sortSelect = $useSystemSort
            ? 'COALESCE(bs.sort_order, r.sort_order, 9999) AS sort_order_eff'
            : 'COALESCE(r.sort_order, 9999) AS sort_order_eff';
        $systemJoin = $useSystemSort
            ? 'LEFT JOIN bridge_systems_resources_to_system bs ON bs.resource_id = r.id AND bs.system_id = ?'
            : '';

        $sql = "
            SELECT r.id, r.name, r.kind, r.sort_order, b.value_permanent, b.value_temporary,
                   {$sortSelect}
            FROM `{$bridgeTable}` b
            INNER JOIN dim_systems_resources r ON r.id = b.resource_id
            {$systemJoin}
            WHERE b.character_id = ?
            ORDER BY r.kind, sort_order_eff, r.name
        ";
        $stmt = mysqli_prepare($link, $sql);
        if (!$stmt) {
            return $out;
        }
        if ($useSystemSort) {
            mysqli_stmt_bind_param($stmt, 'ii', $systemId, $characterId);
        } else {
            mysqli_stmt_bind_param($stmt, 'i', $characterId);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $kind = strtolower(trim((string)($row['kind'] ?? '')));
                if (!isset($out[$kind])) {
                    $out[$kind] = [];
                }
                $out[$kind][] = [
                    'id' => (int)($row['id'] ?? 0),
                    'name' => (string)($row['name'] ?? ''),
                    'perm' => (int)($row['value_permanent'] ?? 0),
                    'temp' => (int)($row['value_temporary'] ?? 0),
                ];
            }
            mysqli_free_result($result);
        }
        mysqli_stmt_close($stmt);

        return $out;
    }
}

if (!function_exists('hg_characters_fetch_merits_flaws')) {
    function hg_characters_fetch_merits_flaws(mysqli $link, int $characterId): array
    {
        if ($characterId <= 0) {
            return [];
        }
        $stmt = mysqli_prepare(
            $link,
            "SELECT nmd.id, nmd.name, nmd.kind, nmd.cost, b.level
             FROM bridge_characters_merits_flaws b
             JOIN dim_merits_flaws nmd ON nmd.id = b.merit_flaw_id
             WHERE b.character_id = ?
             ORDER BY nmd.kind DESC, nmd.cost, nmd.name"
        );
        if (!$stmt) {
            return [];
        }
        mysqli_stmt_bind_param($stmt, 'i', $characterId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rows = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
            mysqli_free_result($result);
        }
        mysqli_stmt_close($stmt);
        return $rows;
    }
}

if (!function_exists('hg_characters_fetch_conditions')) {
    function hg_characters_fetch_conditions(mysqli $link, int $characterId): array
    {
        if ($characterId <= 0
            || !hg_characters_table_exists($link, 'bridge_characters_conditions')
            || !hg_characters_table_exists($link, 'dim_character_conditions')) {
            return [];
        }

        $instanceSelect = hg_characters_has_column($link, 'bridge_characters_conditions', 'instance_no')
            ? 'bcc.instance_no'
            : '1';
        $locationSelect = hg_characters_has_column($link, 'bridge_characters_conditions', 'location')
            ? 'bcc.location'
            : 'NULL';
        $activeWhere = hg_characters_has_column($link, 'bridge_characters_conditions', 'is_active')
            ? 'AND (bcc.is_active = 1 OR bcc.is_active IS NULL)'
            : '';

        $sql = "
            SELECT c.id, c.pretty_id, c.name, c.category,
                   {$instanceSelect} AS instance_no,
                   {$locationSelect} AS condition_location
            FROM bridge_characters_conditions bcc
            JOIN dim_character_conditions c ON c.id = bcc.condition_id
            WHERE bcc.character_id = ?
              {$activeWhere}
            ORDER BY
                CASE
                    WHEN c.category = 'Deformidad Metis' THEN 0
                    WHEN c.category = 'Herida de Guerra' THEN 1
                    WHEN c.category LIKE '%Cicatrices%' THEN 1
                    WHEN c.category = 'Trastorno Mental' THEN 2
                    ELSE 9999
                END ASC,
                c.name ASC,
                instance_no ASC
        ";
        $stmt = mysqli_prepare($link, $sql);
        if (!$stmt) {
            return [];
        }
        mysqli_stmt_bind_param($stmt, 'i', $characterId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rows = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
            mysqli_free_result($result);
        }
        mysqli_stmt_close($stmt);
        return $rows;
    }
}

if (!function_exists('hg_characters_fetch_powers')) {
    function hg_characters_fetch_powers(mysqli $link, int $characterId): array
    {
        $out = [];
        if ($characterId <= 0) {
            return $out;
        }

        $stmt = mysqli_prepare(
            $link,
            'SELECT power_kind, power_id, power_level FROM bridge_characters_powers WHERE character_id = ? ORDER BY power_kind ASC'
        );
        if (!$stmt) {
            return $out;
        }
        mysqli_stmt_bind_param($stmt, 'i', $characterId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $bridgeRows = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $kind = (string)($row['power_kind'] ?? '');
                $bridgeRows[$kind][] = [
                    'id' => (int)($row['power_id'] ?? 0),
                    'bridge_level' => $row['power_level'] !== null ? (int)$row['power_level'] : null,
                ];
            }
            mysqli_free_result($result);
        }
        mysqli_stmt_close($stmt);

        foreach ($bridgeRows as $kind => $rows) {
            $ids = [];
            foreach ($rows as $row) {
                if (($row['id'] ?? 0) > 0) {
                    $ids[(int)$row['id']] = true;
                }
            }
            if (empty($ids)) {
                continue;
            }

            $idList = implode(',', array_keys($ids));
            if ($kind === 'dones') {
                $sql = "SELECT id, name, rank AS source_level FROM fact_gifts WHERE id IN ({$idList})";
            } elseif ($kind === 'disciplinas') {
                $sql = "SELECT id, name, NULL AS source_level FROM dim_discipline_types WHERE id IN ({$idList})";
            } elseif ($kind === 'rituales') {
                $sql = "SELECT id, name, level AS source_level FROM fact_rites WHERE id IN ({$idList})";
            } else {
                continue;
            }

            $meta = [];
            $metaResult = mysqli_query($link, $sql);
            if ($metaResult) {
                while ($row = mysqli_fetch_assoc($metaResult)) {
                    $meta[(int)$row['id']] = $row;
                }
                mysqli_free_result($metaResult);
            }

            foreach ($rows as $row) {
                $id = (int)($row['id'] ?? 0);
                $data = $meta[$id] ?? null;
                if (!$data) {
                    continue;
                }
                $sourceLevel = $data['source_level'] !== null ? (int)$data['source_level'] : null;
                $displayLevel = $kind === 'disciplinas' ? $row['bridge_level'] : $sourceLevel;
                $out[$kind][] = [
                    'id' => $id,
                    'name' => (string)($data['name'] ?? ''),
                    'level' => $displayLevel,
                    'sort_level' => $sourceLevel ?? 999,
                ];
            }

            usort($out[$kind], static function (array $a, array $b): int {
                $levelCmp = ((int)($a['sort_level'] ?? 999)) <=> ((int)($b['sort_level'] ?? 999));
                if ($levelCmp !== 0) {
                    return $levelCmp;
                }
                $nameCmp = strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
                if ($nameCmp !== 0) {
                    return $nameCmp;
                }
                return ((int)($a['id'] ?? 0)) <=> ((int)($b['id'] ?? 0));
            });
        }

        return $out;
    }
}

if (!function_exists('hg_characters_fetch_items')) {
    function hg_characters_fetch_items(mysqli $link, int $characterId): array
    {
        if ($characterId <= 0) {
            return [];
        }
        $stmt = mysqli_prepare(
            $link,
            "SELECT o.id, o.pretty_id AS item_pretty, o.name, o.item_type_id,
                    t.pretty_id AS type_pretty, COALESCE(t.name, '') AS item_type_name
             FROM bridge_characters_items b
             JOIN fact_items o ON o.id = b.item_id
             LEFT JOIN dim_item_types t ON t.id = o.item_type_id
             WHERE b.character_id = ?
             ORDER BY o.item_type_id, o.name"
        );
        if (!$stmt) {
            return [];
        }
        mysqli_stmt_bind_param($stmt, 'i', $characterId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rows = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
            mysqli_free_result($result);
        }
        mysqli_stmt_close($stmt);
        return $rows;
    }
}

if (!function_exists('hg_characters_fetch_lookup')) {
    function hg_characters_fetch_lookup(mysqli $link, string $table, int $id, array $fields = ['name']): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        if ($table === '') {
            return null;
        }

        $safeFields = [];
        foreach ($fields as $field) {
            $field = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$field);
            if ($field !== '') {
                $safeFields[] = "`{$field}`";
            }
        }
        if (empty($safeFields)) {
            return null;
        }

        $stmt = mysqli_prepare(
            $link,
            'SELECT ' . implode(', ', $safeFields) . " FROM `{$table}` WHERE id = ? LIMIT 1"
        );
        if (!$stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        if ($result) {
            mysqli_free_result($result);
        }
        mysqli_stmt_close($stmt);

        return $row ?: null;
    }
}

if (!function_exists('hg_characters_fetch_misc_systems')) {
    function hg_characters_fetch_misc_systems(mysqli $link, int $characterId): array
    {
        if ($characterId <= 0
            || !hg_characters_table_exists($link, 'bridge_characters_misc_systems')
            || !hg_characters_table_exists($link, 'fact_misc_systems')) {
            return [];
        }

        $activeWhere = hg_characters_has_column($link, 'bridge_characters_misc_systems', 'is_active')
            ? 'AND (b.is_active = 1 OR b.is_active IS NULL)'
            : '';
        $sortOrder = hg_characters_has_column($link, 'bridge_characters_misc_systems', 'sort_order')
            ? 'b.sort_order ASC, '
            : '';

        $stmt = mysqli_prepare(
            $link,
            "SELECT b.misc_system_id, m.name, COALESCE(m.kind, '') AS kind
             FROM bridge_characters_misc_systems b
             INNER JOIN fact_misc_systems m ON m.id = b.misc_system_id
             WHERE b.character_id = ?
               {$activeWhere}
             ORDER BY {$sortOrder}m.kind ASC, m.name ASC, b.id ASC"
        );
        if (!$stmt) {
            return [];
        }
        mysqli_stmt_bind_param($stmt, 'i', $characterId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rows = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
            mysqli_free_result($result);
        }
        mysqli_stmt_close($stmt);

        return $rows;
    }
}


if (!function_exists('hg_characters_fetch_active_affiliations')) {
    function hg_characters_fetch_active_affiliations(mysqli $link, int $characterId): array
    {
        $out = ['groups' => [], 'organizations' => []];
        if ($characterId <= 0) {
            return $out;
        }

        if (hg_characters_table_exists($link, 'bridge_characters_groups')
            && hg_characters_table_exists($link, 'dim_groups')) {
            $stmt = mysqli_prepare(
                $link,
                "SELECT g.id, g.name
                 FROM bridge_characters_groups bcg
                 INNER JOIN dim_groups g ON g.id = bcg.group_id
                 WHERE bcg.character_id = ?
                   AND (bcg.is_active = 1 OR bcg.is_active IS NULL)
                 ORDER BY bcg.updated_at DESC, bcg.created_at DESC, bcg.group_id DESC"
            );
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'i', $characterId);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                if ($result) {
                    while ($row = mysqli_fetch_assoc($result)) {
                        $out['groups'][] = $row;
                    }
                    mysqli_free_result($result);
                }
                mysqli_stmt_close($stmt);
            }
        }

        if (hg_characters_table_exists($link, 'bridge_characters_organizations')
            && hg_characters_table_exists($link, 'dim_organizations')) {
            $stmt = mysqli_prepare(
                $link,
                "SELECT o.id, o.name
                 FROM bridge_characters_organizations bco
                 INNER JOIN dim_organizations o ON o.id = bco.organization_id
                 WHERE bco.character_id = ?
                   AND (bco.is_active = 1 OR bco.is_active IS NULL)
                 ORDER BY bco.updated_at DESC, bco.created_at DESC, bco.organization_id DESC"
            );
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'i', $characterId);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                if ($result) {
                    while ($row = mysqli_fetch_assoc($result)) {
                        $out['organizations'][] = $row;
                    }
                    mysqli_free_result($result);
                }
                mysqli_stmt_close($stmt);
            }
        }

        if (empty($out['organizations'])
            && !empty($out['groups'])
            && hg_characters_table_exists($link, 'bridge_organizations_groups')
            && hg_characters_table_exists($link, 'dim_organizations')) {
            $stmt = mysqli_prepare(
                $link,
                "SELECT DISTINCT o.id, o.name
                 FROM bridge_characters_groups bcg
                 INNER JOIN bridge_organizations_groups bog ON bog.group_id = bcg.group_id
                 INNER JOIN dim_organizations o ON o.id = bog.organization_id
                 WHERE bcg.character_id = ?
                   AND (bcg.is_active = 1 OR bcg.is_active IS NULL)
                   AND (bog.is_active = 1 OR bog.is_active IS NULL)
                 ORDER BY bog.updated_at DESC, bog.created_at DESC, bog.organization_id DESC"
            );
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'i', $characterId);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                if ($result) {
                    while ($row = mysqli_fetch_assoc($result)) {
                        $out['organizations'][] = $row;
                    }
                    mysqli_free_result($result);
                }
                mysqli_stmt_close($stmt);
            }
        }

        return $out;
    }
}

if (!function_exists('hg_characters_fetch_primary_affiliations')) {
    function hg_characters_fetch_primary_affiliations(mysqli $link, int $characterId): array
    {
        $groupId = 0;
        $organizationId = 0;
        if ($characterId <= 0) {
            return ['group_id' => 0, 'organization_id' => 0];
        }

        $stmt = mysqli_prepare(
            $link,
            "SELECT group_id
             FROM bridge_characters_groups
             WHERE character_id = ? AND is_active = 1
             ORDER BY updated_at DESC, created_at DESC, group_id DESC
             LIMIT 1"
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $characterId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_bind_result($stmt, $value);
            if (mysqli_stmt_fetch($stmt)) {
                $groupId = (int)$value;
            }
            mysqli_stmt_close($stmt);
        }

        if ($groupId > 0) {
            $stmt = mysqli_prepare(
                $link,
                "SELECT organization_id
                 FROM bridge_organizations_groups
                 WHERE group_id = ? AND is_active = 1
                 ORDER BY updated_at DESC, created_at DESC, organization_id DESC
                 LIMIT 1"
            );
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'i', $groupId);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_bind_result($stmt, $value);
                if (mysqli_stmt_fetch($stmt)) {
                    $organizationId = (int)$value;
                }
                mysqli_stmt_close($stmt);
            }
        }

        if ($organizationId === 0) {
            $stmt = mysqli_prepare(
                $link,
                "SELECT organization_id
                 FROM bridge_characters_organizations
                 WHERE character_id = ? AND is_active = 1
                 ORDER BY updated_at DESC, created_at DESC, organization_id DESC
                 LIMIT 1"
            );
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'i', $characterId);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_bind_result($stmt, $value);
                if (mysqli_stmt_fetch($stmt)) {
                    $organizationId = (int)$value;
                }
                mysqli_stmt_close($stmt);
            }
        }

        if ($organizationId === 0 && $groupId > 0 && hg_characters_has_column($link, 'dim_groups', 'clan')) {
            $stmt = mysqli_prepare(
                $link,
                "SELECT c.id
                 FROM dim_organizations c
                 JOIN dim_groups m ON m.clan = c.name
                 WHERE m.id = ? LIMIT 1"
            );
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'i', $groupId);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_bind_result($stmt, $value);
                if (mysqli_stmt_fetch($stmt)) {
                    $organizationId = (int)$value;
                }
                mysqli_stmt_close($stmt);
            }
        }

        if ($organizationId === 0 && hg_characters_has_column($link, 'fact_characters', 'clan')) {
            $stmt = mysqli_prepare($link, 'SELECT clan FROM fact_characters WHERE id = ? LIMIT 1');
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'i', $characterId);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_bind_result($stmt, $value);
                if (mysqli_stmt_fetch($stmt)) {
                    $organizationId = (int)$value;
                }
                mysqli_stmt_close($stmt);
            }
        }

        return ['group_id' => $groupId, 'organization_id' => $organizationId];
    }
}

if (!function_exists('hg_characters_fetch_birth_event')) {
    function hg_characters_fetch_birth_event(mysqli $link, int $characterId): array
    {
        $fallback = [
            'event_date' => '',
            'date_precision' => 'unknown',
            'date_note' => '',
        ];
        if ($characterId <= 0
            || !hg_characters_table_exists($link, 'fact_timeline_events')
            || !hg_characters_table_exists($link, 'bridge_timeline_events_characters')) {
            return $fallback;
        }

        $hasTypeTable = hg_characters_table_exists($link, 'dim_timeline_events_types');
        $hasEventTypeId = hg_characters_has_column($link, 'fact_timeline_events', 'event_type_id');
        $hasKind = hg_characters_has_column($link, 'fact_timeline_events', 'kind');
        $hasPretty = hg_characters_has_column($link, 'fact_timeline_events', 'pretty_id');
        $hasPrecision = hg_characters_has_column($link, 'fact_timeline_events', 'date_precision');
        $hasNote = hg_characters_has_column($link, 'fact_timeline_events', 'date_note');
        $hasSortDate = hg_characters_has_column($link, 'fact_timeline_events', 'sort_date');
        $hasActive = hg_characters_has_column($link, 'fact_timeline_events', 'is_active');

        $precisionExpr = $hasPrecision ? 'e.date_precision' : "'day'";
        $noteExpr = $hasNote ? 'e.date_note' : 'NULL';
        $sortDateExpr = $hasSortDate ? 'COALESCE(e.sort_date, e.event_date)' : 'e.event_date';
        $joinTypes = ($hasTypeTable && $hasEventTypeId)
            ? 'LEFT JOIN dim_timeline_events_types tet ON tet.id = e.event_type_id'
            : '';
        $activeCond = $hasActive ? 'AND e.is_active = 1' : '';
        $prettyIdSql = "'birthday-char-{$characterId}'";

        $whereParts = [];
        if ($hasPretty) {
            $whereParts[] = 'e.pretty_id = ' . $prettyIdSql;
        }
        if ($hasTypeTable && $hasEventTypeId) {
            $whereParts[] = "tet.pretty_id = 'nacimiento'";
        }
        if ($hasKind) {
            $whereParts[] = "e.kind = 'nacimiento'";
        }
        if (empty($whereParts)) {
            return $fallback;
        }

        $rankExpr = '9';
        if ($hasPretty && $hasTypeTable && $hasEventTypeId && $hasKind) {
            $rankExpr = "CASE WHEN e.pretty_id = {$prettyIdSql} THEN 0 WHEN tet.pretty_id = 'nacimiento' THEN 1 WHEN e.kind = 'nacimiento' THEN 2 ELSE 9 END";
        } elseif ($hasPretty && $hasTypeTable && $hasEventTypeId) {
            $rankExpr = "CASE WHEN e.pretty_id = {$prettyIdSql} THEN 0 WHEN tet.pretty_id = 'nacimiento' THEN 1 ELSE 9 END";
        } elseif ($hasPretty && $hasKind) {
            $rankExpr = "CASE WHEN e.pretty_id = {$prettyIdSql} THEN 0 WHEN e.kind = 'nacimiento' THEN 1 ELSE 9 END";
        } elseif ($hasPretty) {
            $rankExpr = "CASE WHEN e.pretty_id = {$prettyIdSql} THEN 0 ELSE 9 END";
        } elseif ($hasTypeTable && $hasEventTypeId && $hasKind) {
            $rankExpr = "CASE WHEN tet.pretty_id = 'nacimiento' THEN 0 WHEN e.kind = 'nacimiento' THEN 1 ELSE 9 END";
        } elseif ($hasTypeTable && $hasEventTypeId) {
            $rankExpr = "CASE WHEN tet.pretty_id = 'nacimiento' THEN 0 ELSE 9 END";
        } elseif ($hasKind) {
            $rankExpr = "CASE WHEN e.kind = 'nacimiento' THEN 0 ELSE 9 END";
        }

        $sql = "
            SELECT e.event_date, {$precisionExpr} AS date_precision, {$noteExpr} AS date_note
            FROM fact_timeline_events e
            LEFT JOIN bridge_timeline_events_characters bec ON bec.event_id = e.id
            {$joinTypes}
            WHERE (bec.character_id = ?" . ($hasPretty ? ' OR e.pretty_id = ' . $prettyIdSql : '') . ")
              {$activeCond}
              AND (" . implode(' OR ', $whereParts) . ")
            ORDER BY {$rankExpr} ASC, {$sortDateExpr} ASC, e.id ASC
            LIMIT 1
        ";

        $stmt = mysqli_prepare($link, $sql);
        if (!$stmt) {
            return $fallback;
        }
        mysqli_stmt_bind_param($stmt, 'i', $characterId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        if ($result) {
            mysqli_free_result($result);
        }
        mysqli_stmt_close($stmt);

        if (!$row) {
            return $fallback;
        }
        return [
            'event_date' => (string)($row['event_date'] ?? ''),
            'date_precision' => (string)($row['date_precision'] ?? 'unknown'),
            'date_note' => (string)($row['date_note'] ?? ''),
        ];
    }
}

if (!function_exists('hg_characters_fetch_sheet_skills')) {
    function hg_characters_fetch_sheet_skills(mysqli $link, int $characterId, int $systemId): array
    {
        $data = ['primary' => [], 'secondary' => []];
        if ($characterId <= 0 || $systemId <= 0) {
            return $data;
        }

        $stmt = mysqli_prepare(
            $link,
            "SELECT t.id, t.name, t.kind, t.classification, s.sort_order, COALESCE(b.value, 0) AS value
             FROM fact_trait_sets s
             INNER JOIN dim_traits t ON t.id = s.trait_id
             LEFT JOIN bridge_characters_traits b ON b.trait_id = t.id AND b.character_id = ?
             WHERE s.system_id = ? AND s.is_active = 1
             ORDER BY s.sort_order ASC, t.name ASC"
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ii', $characterId, $systemId);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $data['primary'][] = $row;
                }
                mysqli_free_result($result);
            }
            mysqli_stmt_close($stmt);
        }

        $stmt = mysqli_prepare(
            $link,
            "SELECT t.id, t.name, t.kind, t.classification, COALESCE(b.value, 0) AS value
             FROM bridge_characters_traits b
             INNER JOIN dim_traits t ON t.id = b.trait_id
             WHERE b.character_id = ?
               AND b.value > 0
               AND NOT EXISTS (
                   SELECT 1
                   FROM fact_trait_sets s
                   WHERE s.system_id = ?
                     AND s.trait_id = t.id
                     AND s.is_active = 1
               )
             ORDER BY t.name ASC"
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ii', $characterId, $systemId);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $data['secondary'][] = $row;
                }
                mysqli_free_result($result);
            }
            mysqli_stmt_close($stmt);
        }

        return $data;
    }
}
