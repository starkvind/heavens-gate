<?php

if (!function_exists('hg_rules_normalize_int_csv')) {
    function hg_rules_normalize_int_csv($csv): string
    {
        $parts = preg_split('/\s*,\s*/', trim((string)$csv));
        $ints = [];
        foreach ($parts ?: [] as $part) {
            if ($part !== '' && preg_match('/^\d+$/', $part)) {
                $ints[] = (string)(int)$part;
            }
        }
        return implode(',', array_values(array_unique($ints)));
    }
}

if (!function_exists('hg_rules_column_exists')) {
    function hg_rules_column_exists(mysqli $db, string $table, string $column): bool
    {
        static $schema = [
            'bridge_characters_conditions' => ['is_active'],
            'dim_merits_flaws' => ['affiliation', 'cost', 'description', 'kind', 'system_name'],
            'dim_traits' => ['kind'],
        ];
        return isset($schema[$table]) && in_array($column, $schema[$table], true);
    }
}

if (!function_exists('hg_rules_fetch_all')) {
    function hg_rules_fetch_all(mysqli $db, string $sql): ?array
    {
        $result = $db->query($sql);
        if (!$result) {
            return null;
        }
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $result->free();
        return $rows;
    }
}

if (!function_exists('hg_rules_fetch_one_int')) {
    function hg_rules_fetch_one_int(mysqli $db, string $sql, int $id): ?array
    {
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();
        return $row ?: null;
    }
}

if (!function_exists('hg_rules_character_kind_sql')) {
    function hg_rules_character_kind_sql(mysqli $db, string $alias): string
    {
        return function_exists('hg_character_kind_select')
            ? hg_character_kind_select($db, $alias)
            : "''";
    }
}

if (!function_exists('hg_rules_fetch_home_counts')) {
    function hg_rules_fetch_home_counts(mysqli $db): array
    {
        $tables = [
            'traits' => 'dim_traits',
            'merits' => 'dim_merits_flaws',
            'conditions' => 'dim_character_conditions',
            'actions' => 'fact_actions',
            'archetypes' => 'dim_archetypes',
            'maneuvers' => 'fact_combat_maneuvers',
        ];
        $counts = [];
        foreach ($tables as $key => $table) {
            $result = $db->query("SELECT COUNT(*) AS total FROM `{$table}`");
            if (!$result) {
                $counts[$key] = null;
                continue;
            }
            $row = $result->fetch_assoc();
            $result->free();
            $counts[$key] = isset($row['total']) ? (int)$row['total'] : 0;
        }
        return $counts;
    }
}

if (!function_exists('hg_rules_fetch_traits_table')) {
    function hg_rules_fetch_traits_table(mysqli $db, string $excludeChronicles = '2,7'): ?array
    {
        $exclude = hg_rules_normalize_int_csv($excludeChronicles);
        $whereChron = $exclude !== '' ? "p.chronicle_id NOT IN ({$exclude})" : '1=1';
        $kindColumn = hg_rules_column_exists($db, 'dim_traits', 'kind') ? 'kind' : 'tipo';

        return hg_rules_fetch_all($db, "
            SELECT
                nh.id AS trait_id,
                nh.pretty_id AS trait_pretty_id,
                nh.name AS trait_name,
                nh.`{$kindColumn}` AS trait_category,
                SUBSTRING(nh.classification, 5) AS trait_subcategory,
                COALESCE(nb.name, '') AS trait_origin,
                COUNT(DISTINCT p.id) AS trait_holders
            FROM dim_traits nh
                LEFT JOIN dim_bibliographies nb ON nh.bibliography_id = nb.id
                LEFT JOIN bridge_characters_traits bt ON bt.trait_id = nh.id AND bt.value >= 1
                LEFT JOIN fact_characters p ON p.id = bt.character_id AND {$whereChron}
            GROUP BY nh.id, nh.pretty_id, nh.name, nh.`{$kindColumn}`, nh.classification, nb.name
            ORDER BY
                CASE
                    WHEN nh.`{$kindColumn}` = 'Atributos' THEN 0
                    WHEN nh.`{$kindColumn}` = 'Talentos' THEN 1
                    WHEN nh.`{$kindColumn}` IN ('Técnicas', 'Tecnicas') THEN 2
                    WHEN nh.`{$kindColumn}` = 'Conocimientos' THEN 3
                    WHEN nh.`{$kindColumn}` = 'Trasfondos' THEN 4
                    ELSE 9999
                END,
                nh.classification,
                nh.id
        ");
    }
}

if (!function_exists('hg_rules_fetch_trait')) {
    function hg_rules_fetch_trait(mysqli $db, int $id): ?array
    {
        $kindColumn = hg_rules_column_exists($db, 'dim_traits', 'kind') ? 'kind' : 'tipo';
        return hg_rules_fetch_one_int($db, "
            SELECT t.*, t.`{$kindColumn}` AS rule_kind, COALESCE(b.name, '') AS origin_name
            FROM dim_traits t
            LEFT JOIN dim_bibliographies b ON b.id = t.bibliography_id
            WHERE t.id = ?
            LIMIT 1
        ", $id);
    }
}

if (!function_exists('hg_rules_fetch_trait_owners')) {
    function hg_rules_fetch_trait_owners(mysqli $db, int $id, string $excludeChronicles = ''): array
    {
        $exclude = hg_rules_normalize_int_csv($excludeChronicles);
        $chronicleSql = $exclude !== '' ? " AND c.chronicle_id NOT IN ({$exclude})" : '';
        $kindSql = hg_rules_character_kind_sql($db, 'c');
        $stmt = $db->prepare("
            SELECT c.id, c.name, c.alias, c.image_url, c.gender,
                   COALESCE(s.label, '') AS status, c.status_id,
                   {$kindSql} AS character_kind, b.value
            FROM bridge_characters_traits b
            JOIN fact_characters c ON c.id = b.character_id
            LEFT JOIN dim_character_status s ON s.id = c.status_id
            WHERE b.trait_id = ? AND b.value >= 1 {$chronicleSql}
            ORDER BY b.value, c.name
        ");
        if (!$stmt) return [];
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        while ($result && ($row = $result->fetch_assoc())) $rows[] = $row;
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('hg_rules_fetch_conditions_table')) {
    function hg_rules_fetch_conditions_table(mysqli $db, string $excludeChronicles = '2,7'): ?array
    {
        $exclude = hg_rules_normalize_int_csv($excludeChronicles);
        $whereChron = $exclude !== '' ? "p.chronicle_id NOT IN ({$exclude})" : '1=1';
        $activeSql = hg_rules_column_exists($db, 'bridge_characters_conditions', 'is_active')
            ? '(bcc.is_active = 1 OR bcc.is_active IS NULL)'
            : '1=1';
        return hg_rules_fetch_all($db, "
            SELECT c.id AS condition_id, c.pretty_id AS condition_pretty_id,
                   c.name AS condition_name, c.category AS condition_category,
                   COALESCE(b.name, '') AS condition_origin,
                   COUNT(DISTINCT CASE WHEN p.id IS NOT NULL AND {$activeSql} THEN p.id END) AS affected_characters
            FROM dim_character_conditions c
            LEFT JOIN dim_bibliographies b ON b.id = c.bibliography_id
            LEFT JOIN bridge_characters_conditions bcc ON bcc.condition_id = c.id
            LEFT JOIN fact_characters p ON p.id = bcc.character_id AND {$whereChron}
            GROUP BY c.id, c.pretty_id, c.name, c.category, b.name
            ORDER BY CASE
                WHEN c.category = 'Deformidad Metis' THEN 0
                WHEN c.category = 'Herida de Guerra' THEN 1
                WHEN c.category = 'Trastorno Mental' THEN 2
                ELSE 9999 END,
                c.name
        ");
    }
}

if (!function_exists('hg_rules_fetch_condition')) {
    function hg_rules_fetch_condition(mysqli $db, int $id): ?array
    {
        return hg_rules_fetch_one_int($db, "
            SELECT c.*, COALESCE(b.name, '') AS origin_name
            FROM dim_character_conditions c
            LEFT JOIN dim_bibliographies b ON b.id = c.bibliography_id
            WHERE c.id = ? LIMIT 1
        ", $id);
    }
}

if (!function_exists('hg_rules_fetch_condition_effects')) {
    function hg_rules_fetch_condition_effects(mysqli $db, int $id): array
    {
        $stmt = $db->prepare("
            SELECT bcct.id, bcct.modifier_value, COALESCE(bcct.description, '') AS effect_description,
                   t.id AS trait_id, t.pretty_id AS trait_pretty_id, t.name AS trait_name
            FROM bridge_character_conditions_traits bcct
            JOIN dim_traits t ON t.id = bcct.trait_id
            WHERE bcct.condition_id = ?
            ORDER BY t.name, bcct.id
        ");
        if (!$stmt) return [];
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        while ($result && ($row = $result->fetch_assoc())) $rows[] = $row;
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('hg_rules_fetch_condition_owners')) {
    function hg_rules_fetch_condition_owners(mysqli $db, int $id, string $excludeChronicles = ''): array
    {
        $exclude = hg_rules_normalize_int_csv($excludeChronicles);
        $chronicleSql = $exclude !== '' ? " AND c.chronicle_id NOT IN ({$exclude})" : '';
        $activeSql = hg_rules_column_exists($db, 'bridge_characters_conditions', 'is_active')
            ? ' AND (bcc.is_active = 1 OR bcc.is_active IS NULL)'
            : '';
        $kindSql = hg_rules_character_kind_sql($db, 'c');
        $stmt = $db->prepare("
            SELECT DISTINCT c.id, c.name, c.alias, c.image_url, c.gender,
                   COALESCE(s.label, '') AS status, c.status_id, {$kindSql} AS character_kind
            FROM bridge_characters_conditions bcc
            JOIN fact_characters c ON c.id = bcc.character_id
            LEFT JOIN dim_character_status s ON s.id = c.status_id
            WHERE bcc.condition_id = ? {$activeSql} {$chronicleSql}
            ORDER BY c.name
        ");
        if (!$stmt) return [];
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        while ($result && ($row = $result->fetch_assoc())) $rows[] = $row;
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('hg_rules_fetch_actions')) {
    function hg_rules_fetch_actions(mysqli $db): ?array
    {
        return hg_rules_fetch_all($db, "
            SELECT a.id, a.pretty_id, a.name, a.category, a.difficulty_mode, a.fixed_difficulty,
                   a.suggested_difficulty, a.min_difficulty, a.max_difficulty,
                   attr.name AS attribute_name, skill.name AS skill_name,
                   COALESCE(b.name, '') AS origin_name
            FROM fact_actions a
            JOIN dim_traits attr ON attr.id = a.attribute_trait_id
            JOIN dim_traits skill ON skill.id = a.skill_trait_id
            LEFT JOIN dim_bibliographies b ON b.id = a.bibliography_id
            ORDER BY a.category, a.name
        ");
    }
}

if (!function_exists('hg_rules_fetch_action')) {
    function hg_rules_fetch_action(mysqli $db, int $id): ?array
    {
        return hg_rules_fetch_one_int($db, "
            SELECT a.*, attr.name AS attribute_name, skill.name AS skill_name,
                   COALESCE(b.name, '') AS origin_name
            FROM fact_actions a
            JOIN dim_traits attr ON attr.id = a.attribute_trait_id
            JOIN dim_traits skill ON skill.id = a.skill_trait_id
            LEFT JOIN dim_bibliographies b ON b.id = a.bibliography_id
            WHERE a.id = ? LIMIT 1
        ", $id);
    }
}

if (!function_exists('hg_rules_fetch_maneuvers')) {
    function hg_rules_fetch_maneuvers(mysqli $db): ?array
    {
        return hg_rules_fetch_all($db, "
            SELECT m.*, COALESCE(b.name, '') AS origin_name
            FROM fact_combat_maneuvers m
            LEFT JOIN dim_bibliographies b ON b.id = m.bibliography_id
            ORDER BY m.system_name, m.roll DESC, m.name
        ");
    }
}

if (!function_exists('hg_rules_fetch_maneuver')) {
    function hg_rules_fetch_maneuver(mysqli $db, int $id): ?array
    {
        return hg_rules_fetch_one_int($db, "
            SELECT m.*, COALESCE(b.name, '') AS origin_name
            FROM fact_combat_maneuvers m
            LEFT JOIN dim_bibliographies b ON b.id = m.bibliography_id
            WHERE m.id = ? LIMIT 1
        ", $id);
    }
}

if (!function_exists('hg_rules_fetch_archetypes')) {
    function hg_rules_fetch_archetypes(mysqli $db): ?array
    {
        return hg_rules_fetch_all($db, "
            SELECT a.id AS arche_id, a.pretty_id AS arche_pretty_id, a.name AS arche_name,
                   COALESCE(b.name, '') AS arche_origin, COUNT(DISTINCT c.id) AS arche_holders
            FROM dim_archetypes a
            LEFT JOIN dim_bibliographies b ON a.bibliography_id = b.id
            LEFT JOIN fact_characters c ON c.nature_id = a.id OR c.demeanor_id = a.id
            GROUP BY a.id, a.pretty_id, a.name, b.name
            ORDER BY a.name
        ");
    }
}

if (!function_exists('hg_rules_fetch_archetype')) {
    function hg_rules_fetch_archetype(mysqli $db, int $id): ?array
    {
        return hg_rules_fetch_one_int($db, "
            SELECT a.*, COALESCE(b.name, '') AS origin_name
            FROM dim_archetypes a
            LEFT JOIN dim_bibliographies b ON b.id = a.bibliography_id
            WHERE a.id = ? LIMIT 1
        ", $id);
    }
}

if (!function_exists('hg_rules_fetch_archetype_owners')) {
    function hg_rules_fetch_archetype_owners(mysqli $db, int $id, string $role, string $excludeChronicles = ''): array
    {
        $column = $role === 'demeanor' ? 'demeanor_id' : 'nature_id';
        $exclude = hg_rules_normalize_int_csv($excludeChronicles);
        $chronicleSql = $exclude !== '' ? " AND c.chronicle_id NOT IN ({$exclude})" : '';
        $kindSql = hg_rules_character_kind_sql($db, 'c');
        $stmt = $db->prepare("
            SELECT c.id, c.name, c.alias, c.image_url, c.gender,
                   COALESCE(s.label, '') AS status, c.status_id, {$kindSql} AS character_kind
            FROM fact_characters c
            LEFT JOIN dim_character_status s ON s.id = c.status_id
            WHERE c.`{$column}` = ? {$chronicleSql}
            ORDER BY c.name
        ");
        if (!$stmt) return [];
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        while ($result && ($row = $result->fetch_assoc())) $rows[] = $row;
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('hg_rules_fetch_merits')) {
    function hg_rules_fetch_merits(mysqli $db): ?array
    {
        $kind = hg_rules_column_exists($db, 'dim_merits_flaws', 'kind') ? 'kind' : 'tipo';
        $system = hg_rules_column_exists($db, 'dim_merits_flaws', 'system_name') ? 'system_name' : 'sistema';
        $affiliation = hg_rules_column_exists($db, 'dim_merits_flaws', 'affiliation') ? 'affiliation' : 'afiliacion';
        $cost = hg_rules_column_exists($db, 'dim_merits_flaws', 'cost') ? 'cost' : 'coste';
        return hg_rules_fetch_all($db, "
            SELECT m.id AS merit_id, m.pretty_id AS merit_pretty_id, m.name AS merit_name,
                   m.`{$system}` AS merit_system, m.`{$kind}` AS merit_type,
                   m.`{$affiliation}` AS merit_category, m.`{$cost}` AS merit_cost,
                   COALESCE(b.name, '') AS merit_origin
            FROM dim_merits_flaws m
            LEFT JOIN dim_bibliographies b ON m.bibliography_id = b.id
            ORDER BY CASE WHEN m.`{$kind}` IN ('Méritos', 'Meritos') THEN 0 ELSE 9999 END,
                     m.`{$system}`, m.name
        ");
    }
}

if (!function_exists('hg_rules_fetch_merit')) {
    function hg_rules_fetch_merit(mysqli $db, int $id): ?array
    {
        $kind = hg_rules_column_exists($db, 'dim_merits_flaws', 'kind') ? 'kind' : 'tipo';
        $system = hg_rules_column_exists($db, 'dim_merits_flaws', 'system_name') ? 'system_name' : 'sistema';
        $affiliation = hg_rules_column_exists($db, 'dim_merits_flaws', 'affiliation') ? 'affiliation' : 'afiliacion';
        $cost = hg_rules_column_exists($db, 'dim_merits_flaws', 'cost') ? 'cost' : 'coste';
        $description = hg_rules_column_exists($db, 'dim_merits_flaws', 'description') ? 'description' : 'descripcion';
        return hg_rules_fetch_one_int($db, "
            SELECT m.*, m.`{$kind}` AS tipo, m.`{$system}` AS sistema,
                   m.`{$affiliation}` AS afiliacion, m.`{$cost}` AS coste,
                   m.`{$description}` AS descripcion, COALESCE(b.name, '') AS origin_name
            FROM dim_merits_flaws m
            LEFT JOIN dim_bibliographies b ON b.id = m.bibliography_id
            WHERE m.id = ? LIMIT 1
        ", $id);
    }
}

if (!function_exists('hg_rules_fetch_merit_affiliations')) {
    function hg_rules_fetch_merit_affiliations(mysqli $db): array
    {
        $affiliation = hg_rules_column_exists($db, 'dim_merits_flaws', 'affiliation') ? 'affiliation' : 'afiliacion';
        $rows = hg_rules_fetch_all($db, "SELECT DISTINCT `{$affiliation}` AS afiliacion FROM dim_merits_flaws ORDER BY `{$affiliation}`") ?: [];
        return array_values(array_filter(array_map(static fn(array $row): string => (string)($row['afiliacion'] ?? ''), $rows), static fn(string $v): bool => $v !== ''));
    }
}

if (!function_exists('hg_rules_fetch_merit_owners')) {
    function hg_rules_fetch_merit_owners(mysqli $db, int $id, string $excludeChronicles = ''): array
    {
        $exclude = hg_rules_normalize_int_csv($excludeChronicles);
        $chronicleSql = $exclude !== '' ? " AND c.chronicle_id NOT IN ({$exclude})" : '';
        $kindSql = hg_rules_character_kind_sql($db, 'c');
        $stmt = $db->prepare("
            SELECT DISTINCT c.id, c.name AS nombre, c.alias, c.image_url, c.gender,
                   COALESCE(s.label, '') AS status, c.status_id, {$kindSql} AS character_kind
            FROM bridge_characters_merits_flaws b
            JOIN fact_characters c ON c.id = b.character_id
            LEFT JOIN dim_character_status s ON s.id = c.status_id
            WHERE b.merit_flaw_id = ? {$chronicleSql}
            ORDER BY c.name
        ");
        if (!$stmt) return [];
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        while ($result && ($row = $result->fetch_assoc())) $rows[] = $row;
        $stmt->close();
        return $rows;
    }
}
