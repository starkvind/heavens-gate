<?php
include_once(__DIR__ . '/../../helpers/pretty.php');

function hg_aocs_table_exists(mysqli $link, string $table): bool
{
    $stmt = $link->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return (int)$count > 0;
}

function hg_aocs_count_rows(mysqli $link, string $table): int
{
    if (!hg_aocs_table_exists($link, $table)) {
        return 0;
    }
    $safe = str_replace('`', '``', $table);
    $rs = $link->query("SELECT COUNT(*) AS c FROM `$safe`");
    if (!$rs) {
        return 0;
    }
    $row = $rs->fetch_assoc();
    return (int)($row['c'] ?? 0);
}

function hg_aocs_fetch_id_prepared(mysqli $link, string $sql, string $types, array $params): int
{
    $stmt = $link->prepare($sql);
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rs = $stmt->get_result();
    $row = $rs ? $rs->fetch_assoc() : null;
    $stmt->close();
    return (int)($row['id'] ?? 0);
}

function hg_aocs_upsert_department(mysqli $link, int $orgId, ?int $parentId, string $pretty, string $name, string $type, int $level, string $color, string $description, int $sort): int
{
    $existing = hg_aocs_fetch_id_prepared(
        $link,
        'SELECT id FROM dim_organization_departments WHERE organization_id = ? AND pretty_id = ? LIMIT 1',
        'is',
        [$orgId, $pretty]
    );

    if ($existing > 0) {
        $stmt = $link->prepare("
            UPDATE dim_organization_departments
            SET parent_department_id = ?, name = ?, department_type = ?, hierarchy_level = ?, color = ?, description = ?, sort_order = ?, is_active = 1
            WHERE id = ?
        ");
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('ississii', $parentId, $name, $type, $level, $color, $description, $sort, $existing);
        $stmt->execute();
        $stmt->close();
        return $existing;
    }

    $stmt = $link->prepare("
        INSERT INTO dim_organization_departments
            (organization_id, parent_department_id, pretty_id, name, department_type, hierarchy_level, color, description, sort_order, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
    ");
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('iisssissi', $orgId, $parentId, $pretty, $name, $type, $level, $color, $description, $sort);
    $stmt->execute();
    $id = (int)$stmt->insert_id;
    $stmt->close();
    return $id;
}

function hg_aocs_fetch_organizations(mysqli $link): array
{
    $rows = [];
    $rs = $link->query("SELECT id, pretty_id, name FROM dim_organizations ORDER BY sort_order ASC, name ASC");
    if (!$rs) {
        return $rows;
    }
    while ($row = $rs->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $rows[] = $row;
    }
    return $rows;
}

function hg_aocs_fetch_departments(mysqli $link, int $orgId): array
{
    $rows = [];
    if ($orgId <= 0 || !hg_aocs_table_exists($link, 'dim_organization_departments')) {
        return $rows;
    }
    $stmt = $link->prepare("
        SELECT d.id, d.parent_department_id, d.pretty_id, d.name, d.department_type, d.hierarchy_level, d.color, d.sort_order,
               COALESCE(p.name, '') AS parent_name
        FROM dim_organization_departments d
            LEFT JOIN dim_organization_departments p ON p.id = d.parent_department_id
        WHERE d.organization_id = ?
          AND d.is_active = 1
        ORDER BY d.hierarchy_level ASC, d.sort_order ASC, d.name ASC
    ");
    if (!$stmt) {
        return $rows;
    }
    $stmt->bind_param('i', $orgId);
    $stmt->execute();
    $rs = $stmt->get_result();
    while ($row = $rs->fetch_assoc()) {
        $row['id'] = (int)($row['id'] ?? 0);
        $row['parent_department_id'] = (int)($row['parent_department_id'] ?? 0);
        $row['hierarchy_level'] = (int)($row['hierarchy_level'] ?? 0);
        $row['sort_order'] = (int)($row['sort_order'] ?? 0);
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

function hg_aocs_department_belongs_to_org(mysqli $link, int $departmentId, int $orgId): bool
{
    if ($departmentId <= 0) {
        return true;
    }
    $stmt = $link->prepare("SELECT id FROM dim_organization_departments WHERE id = ? AND organization_id = ? LIMIT 1");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ii', $departmentId, $orgId);
    $stmt->execute();
    $rs = $stmt->get_result();
    $ok = $rs && $rs->fetch_assoc();
    $stmt->close();
    return (bool)$ok;
}

function hg_aocs_next_department_sort(mysqli $link, int $orgId, ?int $parentId): int
{
    if ($parentId === null) {
        $stmt = $link->prepare("SELECT COALESCE(MAX(sort_order), 0) + 10 AS next_sort FROM dim_organization_departments WHERE organization_id = ? AND parent_department_id IS NULL");
        if (!$stmt) {
            return 10;
        }
        $stmt->bind_param('i', $orgId);
    } else {
        $stmt = $link->prepare("SELECT COALESCE(MAX(sort_order), 0) + 10 AS next_sort FROM dim_organization_departments WHERE organization_id = ? AND parent_department_id = ?");
        if (!$stmt) {
            return 10;
        }
        $stmt->bind_param('ii', $orgId, $parentId);
    }
    $stmt->execute();
    $rs = $stmt->get_result();
    $row = $rs ? $rs->fetch_assoc() : null;
    $stmt->close();
    return max(10, (int)($row['next_sort'] ?? 10));
}

function hg_aocs_unique_department_pretty(mysqli $link, int $orgId, string $basePretty): string
{
    $basePretty = slugify_pretty_id($basePretty);
    if ($basePretty === '') {
        $basePretty = 'categoria';
    }
    $pretty = $basePretty;
    $suffix = 2;
    while (hg_aocs_fetch_id_prepared(
        $link,
        'SELECT id FROM dim_organization_departments WHERE organization_id = ? AND pretty_id = ? LIMIT 1',
        'is',
        [$orgId, $pretty]
    ) > 0) {
        $pretty = $basePretty . '-' . $suffix;
        $suffix++;
    }
    return $pretty;
}

