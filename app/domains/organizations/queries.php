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
