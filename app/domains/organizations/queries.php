<?php

/** Database access for public organization/group structures and org charts. */

if (!function_exists('hg_organizations_table_exists')) {
    function hg_organizations_table_exists(mysqli $link, string $table): bool
    {
        static $cache = [];
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        if ($table === '') return false;
        if (array_key_exists($table, $cache)) return $cache[$table];
        $stmt = $link->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        if (!$stmt) return $cache[$table] = false;
        $stmt->bind_param('s', $table);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
        return $cache[$table] = ((int)$count > 0);
    }
}

if (!function_exists('hg_organizations_fetch_one')) {
    function hg_organizations_fetch_one(mysqli $link, string $raw): ?array
    {
        $raw = trim($raw);
        if ($raw === '') $raw = 'justicia-metalica';
        if (preg_match('/^\d+$/', $raw)) {
            $stmt = $link->prepare('SELECT id, pretty_id, name, color, description FROM dim_organizations WHERE id = ? LIMIT 1');
            if (!$stmt) return null;
            $id = (int)$raw;
            $stmt->bind_param('i', $id);
        } else {
            $stmt = $link->prepare('SELECT id, pretty_id, name, color, description FROM dim_organizations WHERE pretty_id = ? OR name = ? LIMIT 1');
            if (!$stmt) return null;
            $stmt->bind_param('ss', $raw, $raw);
        }
        if (!$stmt->execute()) { $stmt->close(); return null; }
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();
        return is_array($row) ? $row : null;
    }
}

if (!function_exists('hg_organizations_fetch_chart_options')) {
    function hg_organizations_fetch_chart_options(mysqli $link): array
    {
        if (!hg_organizations_table_exists($link, 'dim_organization_departments') || !hg_organizations_table_exists($link, 'bridge_characters_org')) return [];
        $sql = "SELECT o.id, o.pretty_id, o.name,
                       COUNT(DISTINCT d.id) AS department_count,
                       COUNT(DISTINCT b.id) AS role_count
                FROM dim_organizations o
                LEFT JOIN dim_organization_departments d ON d.organization_id = o.id AND d.is_active = 1
                LEFT JOIN bridge_characters_org b ON b.organization_id = o.id AND b.is_active = 1
                GROUP BY o.id, o.pretty_id, o.name, o.sort_order
                HAVING department_count > 0 OR role_count > 0
                ORDER BY o.sort_order ASC, o.name ASC";
        $result = $link->query($sql);
        if (!($result instanceof mysqli_result)) return [];
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $row['id'] = (int)$row['id'];
            $row['department_count'] = (int)$row['department_count'];
            $row['role_count'] = (int)$row['role_count'];
            $rows[] = $row;
        }
        $result->free();
        return $rows;
    }
}

if (!function_exists('hg_organizations_fetch_departments')) {
    function hg_organizations_fetch_departments(mysqli $link, int $organizationId): array
    {
        $stmt = $link->prepare("SELECT id, organization_id, parent_department_id, pretty_id, name, department_type, hierarchy_level, color, description, sort_order
                                FROM dim_organization_departments
                                WHERE organization_id = ? AND is_active = 1
                                ORDER BY hierarchy_level ASC, sort_order ASC, name ASC");
        if (!$stmt) return [];
        $stmt->bind_param('i', $organizationId);
        if (!$stmt->execute()) { $stmt->close(); return []; }
        $result = $stmt->get_result();
        $rows = [];
        while ($result && ($row = $result->fetch_assoc())) {
            $row['id'] = (int)$row['id'];
            $row['organization_id'] = (int)$row['organization_id'];
            $row['parent_department_id'] = (int)($row['parent_department_id'] ?? 0);
            $row['hierarchy_level'] = (int)($row['hierarchy_level'] ?? 1);
            $row['sort_order'] = (int)($row['sort_order'] ?? 0);
            $rows[] = $row;
        }
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('hg_organizations_fetch_roles')) {
    function hg_organizations_fetch_roles(mysqli $link, int $organizationId): array
    {
        $stmt = $link->prepare("SELECT
                b.id, b.character_id, b.organization_id, b.department_id, b.parent_bridge_id,
                b.hierarchy_level, b.position_name, b.position_code, b.scope_label, b.responsibility,
                b.is_head, b.is_primary, b.sort_order, b.created_at, b.updated_at,
                p.pretty_id AS character_pretty_id, p.name AS character_name, p.alias, p.garou_name,
                p.gender, p.image_url, p.rank,
                COALESCE(db.name, '') AS breed_name,
                COALESCE(da.name, '') AS auspice_name,
                COALESCE(dt.name, '') AS tribe_name,
                d.name AS department_name, d.department_type, d.color AS department_color,
                d.parent_department_id, pd.department_type AS parent_department_type,
                pd.name AS parent_department_name
            FROM bridge_characters_org b
            INNER JOIN fact_characters p ON p.id = b.character_id
            LEFT JOIN dim_breeds db ON db.id = p.breed_id
            LEFT JOIN dim_auspices da ON da.id = p.auspice_id
            LEFT JOIN dim_tribes dt ON dt.id = p.tribe_id
            LEFT JOIN dim_organization_departments d ON d.id = b.department_id
            LEFT JOIN dim_organization_departments pd ON pd.id = d.parent_department_id
            WHERE b.organization_id = ? AND b.is_active = 1
            ORDER BY b.hierarchy_level ASC, b.sort_order ASC, p.name ASC");
        if (!$stmt) return [];
        $stmt->bind_param('i', $organizationId);
        if (!$stmt->execute()) { $stmt->close(); return []; }
        $result = $stmt->get_result();
        $rows = [];
        while ($result && ($row = $result->fetch_assoc())) {
            $row['id'] = (int)$row['id'];
            $row['character_id'] = (int)$row['character_id'];
            $row['organization_id'] = (int)$row['organization_id'];
            $row['department_id'] = (int)($row['department_id'] ?? 0);
            $row['parent_bridge_id'] = (int)($row['parent_bridge_id'] ?? 0);
            $row['hierarchy_level'] = (int)($row['hierarchy_level'] ?? 1);
            $row['parent_department_id'] = (int)($row['parent_department_id'] ?? 0);
            $row['is_head'] = (int)($row['is_head'] ?? 0);
            $row['is_primary'] = (int)($row['is_primary'] ?? 0);
            $row['sort_order'] = (int)($row['sort_order'] ?? 0);
            $rows[] = $row;
        }
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('hg_organizations_has_column')) {
    function hg_organizations_has_column(mysqli $link, string $table, string $column): bool
    {
        static $cache = [];
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        if ($table === '' || $column === '') return false;
        $key = $table . ':' . $column;
        if (array_key_exists($key, $cache)) return $cache[$key];
        $stmt = $link->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        if (!$stmt) return $cache[$key] = false;
        $stmt->bind_param('ss', $table, $column);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
        return $cache[$key] = ((int)$count > 0);
    }
}

if (!function_exists('hg_organizations_normalize_int_csv')) {
    function hg_organizations_normalize_int_csv($csv): string
    {
        $ids = [];
        foreach (preg_split('/\\s*,\\s*/', trim((string)$csv)) as $part) {
            if ($part !== '' && preg_match('/^\\d+$/', $part)) $ids[(int)$part] = true;
        }
        return implode(',', array_keys($ids));
    }
}

if (!function_exists('hg_organizations_resolve_id')) {
    function hg_organizations_resolve_id(mysqli $link, string $table, $value): int
    {
        if (!in_array($table, ['dim_organizations', 'dim_groups'], true)) return 0;
        $value = trim((string)$value);
        if ($value === '') return 0;
        if (preg_match('/^\\d+$/', $value)) return (int)$value;
        if (!function_exists('resolve_pretty_id')) return 0;
        return (int)(resolve_pretty_id($link, $table, $value) ?? 0);
    }
}

if (!function_exists('hg_organizations_fetch_entity')) {
    function hg_organizations_fetch_entity(mysqli $link, int $type, int $id): ?array
    {
        if ($id <= 0) return null;
        $table = $type === 1 ? 'dim_groups' : ($type === 2 ? 'dim_organizations' : '');
        if ($table === '') return null;
        $stmt = $link->prepare("SELECT * FROM `{$table}` WHERE id = ? LIMIT 1");
        if (!$stmt) return null;
        $stmt->bind_param('i', $id);
        if (!$stmt->execute()) { $stmt->close(); return null; }
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        if ($result) $result->free();
        $stmt->close();
        return is_array($row) ? $row : null;
    }
}

if (!function_exists('hg_organizations_fetch_group_organization')) {
    function hg_organizations_fetch_group_organization(mysqli $link, int $groupId, int $preferredOrganizationId = 0): array
    {
        if ($groupId <= 0) return ['id' => 0, 'name' => ''];

        if ($preferredOrganizationId > 0) {
            $stmt = $link->prepare("SELECT c.id AS organization_id, c.name AS organization_name
                                    FROM bridge_organizations_groups b
                                    INNER JOIN dim_groups m ON m.id = b.group_id
                                    INNER JOIN dim_organizations c ON c.id = b.organization_id
                                    WHERE b.organization_id = ? AND m.id = ?
                                      AND (b.is_active = 1 OR b.is_active IS NULL)
                                    LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('ii', $preferredOrganizationId, $groupId);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result ? $result->fetch_assoc() : null;
                if ($result) $result->free();
                $stmt->close();
                if ($row) return ['id' => (int)$row['organization_id'], 'name' => (string)$row['organization_name']];
            }
        }

        $stmt = $link->prepare("SELECT c.id AS organization_id, c.name AS organization_name
                                FROM bridge_organizations_groups b
                                INNER JOIN dim_organizations c ON c.id = b.organization_id
                                WHERE b.group_id = ? AND (b.is_active = 1 OR b.is_active IS NULL)
                                ORDER BY b.updated_at DESC, b.created_at DESC, b.organization_id DESC
                                LIMIT 1");
        if (!$stmt) return ['id' => 0, 'name' => ''];
        $stmt->bind_param('i', $groupId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        if ($result) $result->free();
        $stmt->close();
        return $row ? ['id' => (int)$row['organization_id'], 'name' => (string)$row['organization_name']] : ['id' => 0, 'name' => ''];
    }
}

if (!function_exists('hg_organizations_org_chart_available')) {
    function hg_organizations_org_chart_available(mysqli $link, int $organizationId): bool
    {
        if ($organizationId <= 0
            || !hg_organizations_table_exists($link, 'dim_organization_departments')
            || !hg_organizations_table_exists($link, 'bridge_characters_org')) return false;
        $stmt = $link->prepare("SELECT
                (SELECT COUNT(*) FROM dim_organization_departments WHERE organization_id = ? AND is_active = 1)
              + (SELECT COUNT(*) FROM bridge_characters_org WHERE organization_id = ? AND is_active = 1) AS total");
        if (!$stmt) return false;
        $stmt->bind_param('ii', $organizationId, $organizationId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        if ($result) $result->free();
        $stmt->close();
        return (int)($row['total'] ?? 0) > 0;
    }
}

if (!function_exists('hg_organizations_fetch_totem')) {
    function hg_organizations_fetch_totem(mysqli $link, int $totemId): ?array
    {
        if ($totemId <= 0) return null;
        $stmt = $link->prepare('SELECT id, name FROM dim_totems WHERE id = ? LIMIT 1');
        if (!$stmt) return null;
        $stmt->bind_param('i', $totemId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        if ($result) $result->free();
        $stmt->close();
        return $row ?: null;
    }
}

if (!function_exists('hg_organizations_character_kind_column')) {
    function hg_organizations_character_kind_column(mysqli $link): string
    {
        return hg_organizations_has_column($link, 'fact_characters', 'kind') ? 'kind' : 'character_kind';
    }
}

if (!function_exists('hg_organizations_fetch_group_members')) {
    function hg_organizations_fetch_group_members(mysqli $link, int $groupId, $excludedChronicles = '', bool $former = false): array
    {
        if ($groupId <= 0) return [];
        $excluded = hg_organizations_normalize_int_csv($excludedChronicles);
        $chronicle = $excluded !== '' ? " AND p.chronicle_id NOT IN ({$excluded})" : '';
        $kind = hg_organizations_character_kind_column($link);
        $membership = $former
            ? "(bg.is_active = 0 OR LOWER(TRIM(COALESCE(dcs.label, ''))) COLLATE utf8mb4_unicode_ci = 'cadaver')"
            : "(bg.is_active = 1 OR bg.is_active IS NULL) AND LOWER(TRIM(COALESCE(dcs.label, ''))) COLLATE utf8mb4_unicode_ci <> 'cadaver'";
        $stmt = $link->prepare("SELECT p.id, p.name, p.alias, p.image_url, p.gender,
                                      COALESCE(dcs.label, '') AS status, p.status_id,
                                      p.`{$kind}` AS character_kind
                               FROM bridge_characters_groups bg
                               INNER JOIN fact_characters p ON p.id = bg.character_id
                               LEFT JOIN dim_character_status dcs ON dcs.id = p.status_id
                               WHERE bg.group_id = ? AND {$membership} {$chronicle}
                               ORDER BY
                                   CASE LOWER(TRIM(COALESCE(dcs.label, '')))
                                       WHEN 'paradero desconocido' THEN 1
                                       WHEN 'cadaver' THEN 2
                                       WHEN 'aun por aparecer' THEN 9999
                                       ELSE 0
                                   END,
                                   p.name");
        if (!$stmt) return [];
        $stmt->bind_param('i', $groupId);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        while ($result && ($row = $result->fetch_assoc())) $rows[] = $row;
        if ($result) $result->free();
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('hg_organizations_fetch_groups_for_organization')) {
    function hg_organizations_fetch_groups_for_organization(mysqli $link, int $organizationId, $excludedChronicles = '', bool $active = true): array
    {
        if ($organizationId <= 0) return [];
        $excluded = hg_organizations_normalize_int_csv($excludedChronicles);
        $chronicle = $excluded !== '' ? " AND m.chronicle_id NOT IN ({$excluded})" : '';
        $activeValue = $active ? 1 : 0;
        $stmt = $link->prepare("SELECT m.id, m.name
                               FROM bridge_organizations_groups b
                               INNER JOIN dim_groups m ON m.id = b.group_id
                               WHERE b.organization_id = ?
                                 AND (b.is_active = 1 OR b.is_active IS NULL)
                                 AND m.is_active = {$activeValue}
                                 {$chronicle}
                               ORDER BY m.name");
        if (!$stmt) return [];
        $stmt->bind_param('i', $organizationId);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        while ($result && ($row = $result->fetch_assoc())) $rows[] = $row;
        if ($result) $result->free();
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('hg_organizations_fetch_ungrouped_members')) {
    function hg_organizations_fetch_ungrouped_members(mysqli $link, int $organizationId, $excludedChronicles = ''): array
    {
        if ($organizationId <= 0) return [];
        $excluded = hg_organizations_normalize_int_csv($excludedChronicles);
        $chronicle = $excluded !== '' ? " AND p.chronicle_id NOT IN ({$excluded})" : '';
        $kind = hg_organizations_character_kind_column($link);
        $stmt = $link->prepare("SELECT p.id, p.name, p.alias, p.image_url, p.gender,
                                      COALESCE(dcs.label, '') AS status, p.status_id,
                                      p.`{$kind}` AS character_kind
                               FROM bridge_characters_organizations bc
                               INNER JOIN fact_characters p ON p.id = bc.character_id
                               LEFT JOIN dim_character_status dcs ON dcs.id = p.status_id
                               LEFT JOIN bridge_characters_groups bg
                                 ON bg.character_id = p.id
                                AND (bg.is_active = 1 OR bg.is_active IS NULL)
                               WHERE bc.organization_id = ?
                                 AND (bc.is_active = 1 OR bc.is_active IS NULL)
                                 AND bg.character_id IS NULL
                                 {$chronicle}
                               ORDER BY
                                   CASE LOWER(TRIM(COALESCE(dcs.label, '')))
                                       WHEN 'paradero desconocido' THEN 1
                                       WHEN 'cadaver' THEN 2
                                       WHEN 'aun por aparecer' THEN 9999
                                       ELSE 0
                                   END,
                                   p.name");
        if (!$stmt) return [];
        $stmt->bind_param('i', $organizationId);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        while ($result && ($row = $result->fetch_assoc())) $rows[] = $row;
        if ($result) $result->free();
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('hg_organizations_fetch_markdown_data')) {
    function hg_organizations_fetch_markdown_data(mysqli $link, int $organizationId, $excludedChronicles = ''): array
    {
        $data = ['members' => [], 'groups' => []];
        if ($organizationId <= 0) return $data;

        $excluded = hg_organizations_normalize_int_csv($excludedChronicles);
        $chronicle = $excluded !== '' ? " AND p.chronicle_id NOT IN ({$excluded})" : '';
        $groupChronicle = $excluded !== '' ? " AND m.chronicle_id NOT IN ({$excluded})" : '';
        $fields = "p.name, COALESCE(p.alias, '') AS alias, COALESCE(p.garou_name, '') AS garou_name,
                   COALESCE(p.info_text, '') AS description, COALESCE(dcs.label, '') AS status";

        $stmt = $link->prepare("SELECT DISTINCT {$fields}
                               FROM bridge_characters_organizations bc
                               INNER JOIN fact_characters p ON p.id = bc.character_id
                               LEFT JOIN dim_character_status dcs ON dcs.id = p.status_id
                               LEFT JOIN bridge_characters_groups bg ON bg.character_id = p.id AND (bg.is_active = 1 OR bg.is_active IS NULL)
                               WHERE bc.organization_id = ? AND (bc.is_active = 1 OR bc.is_active IS NULL)
                                 AND bg.character_id IS NULL {$chronicle}
                               ORDER BY p.name");
        if ($stmt) {
            $stmt->bind_param('i', $organizationId);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($result && ($row = $result->fetch_assoc())) $data['members'][] = $row;
            if ($result) $result->free();
            $stmt->close();
        }

        $stmt = $link->prepare("SELECT m.id, m.name, COALESCE(m.description, '') AS description
                               FROM bridge_organizations_groups bog
                               INNER JOIN dim_groups m ON m.id = bog.group_id
                               WHERE bog.organization_id = ? AND (bog.is_active = 1 OR bog.is_active IS NULL)
                                 {$groupChronicle}
                               ORDER BY m.name");
        if (!$stmt) return $data;
        $stmt->bind_param('i', $organizationId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($result && ($group = $result->fetch_assoc())) {
            $groupId = (int)$group['id'];
            $members = [];
            $memberStmt = $link->prepare("SELECT DISTINCT {$fields}
                                         FROM bridge_characters_groups bg
                                         INNER JOIN fact_characters p ON p.id = bg.character_id
                                         LEFT JOIN dim_character_status dcs ON dcs.id = p.status_id
                                         WHERE bg.group_id = ? AND (bg.is_active = 1 OR bg.is_active IS NULL)
                                           {$chronicle}
                                         ORDER BY p.name");
            if ($memberStmt) {
                $memberStmt->bind_param('i', $groupId);
                $memberStmt->execute();
                $memberResult = $memberStmt->get_result();
                while ($memberResult && ($member = $memberResult->fetch_assoc())) $members[] = $member;
                if ($memberResult) $memberResult->free();
                $memberStmt->close();
            }
            $data['groups'][] = [
                'name' => (string)($group['name'] ?? ''),
                'description' => (string)($group['description'] ?? ''),
                'members' => $members,
            ];
        }
        if ($result) $result->free();
        $stmt->close();
        return $data;
    }
}
