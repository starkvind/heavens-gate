<?php

if (!function_exists('hg_relationship_archive_normalize_ids')) {
    function hg_relationship_archive_normalize_ids($value): array
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

if (!function_exists('hg_relationship_archive_table_exists')) {
    function hg_relationship_archive_table_exists(mysqli $link, string $table): bool
    {
        return in_array($table, ['dim_organization_departments', 'bridge_characters_org'], true);
    }
}

if (!function_exists('hg_relationship_archive_fetch_organizations')) {
    function hg_relationship_archive_fetch_organizations(mysqli $link): ?array
    {
        $result = mysqli_query($link, 'SELECT id, name FROM dim_organizations ORDER BY sort_order');
        if (!$result) return null;
        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = ['id' => (int)$row['id'], 'name' => (string)$row['name']];
        }
        mysqli_free_result($result);
        return $rows;
    }
}

if (!function_exists('hg_relationship_archive_fetch_groups')) {
    function hg_relationship_archive_fetch_groups(mysqli $link, int $organizationId, $excludedChronicles = ''): ?array
    {
        if ($organizationId <= 0) return [];
        $excludedIds = hg_relationship_archive_normalize_ids($excludedChronicles);
        $sql = "SELECT m.id, m.name, m.is_active
                FROM bridge_organizations_groups b
                INNER JOIN dim_groups m ON m.id = b.group_id
                WHERE b.organization_id = ?
                  AND (b.is_active = 1 OR b.is_active IS NULL)";
        $types = 'i';
        $params = [$organizationId];
        if ($excludedIds) {
            $sql .= ' AND m.chronicle_id NOT IN (' . implode(',', array_fill(0, count($excludedIds), '?')) . ')';
            $types .= str_repeat('i', count($excludedIds));
            foreach ($excludedIds as $id) $params[] = $id;
        }
        $sql .= ' ORDER BY m.name';
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
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = [
                'id' => (int)$row['id'],
                'name' => (string)$row['name'],
                'is_active' => (int)$row['is_active'],
            ];
        }
        mysqli_free_result($result);
        mysqli_stmt_close($stmt);
        return $rows;
    }
}

if (!function_exists('hg_relationship_archive_org_chart_available')) {
    function hg_relationship_archive_org_chart_available(mysqli $link, int $organizationId): bool
    {
        if ($organizationId <= 0) return false;
        if (!hg_relationship_archive_table_exists($link, 'dim_organization_departments')
            || !hg_relationship_archive_table_exists($link, 'bridge_characters_org')) {
            return false;
        }
        $stmt = mysqli_prepare(
            $link,
            "SELECT
                (SELECT COUNT(*) FROM dim_organization_departments WHERE organization_id = ? AND is_active = 1)
              + (SELECT COUNT(*) FROM bridge_characters_org WHERE organization_id = ? AND is_active = 1) AS total"
        );
        if (!$stmt) return false;
        mysqli_stmt_bind_param($stmt, 'ii', $organizationId, $organizationId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        if ($result) mysqli_free_result($result);
        mysqli_stmt_close($stmt);
        return (int)($row['total'] ?? 0) > 0;
    }
}
