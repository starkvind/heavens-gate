<?php

/**
 * Database access for public relationship-map views.
 */

function hg_relationships_normalize_ids(array $ids): array
{
    $out = [];
    foreach ($ids as $id) {
        $id = (int)$id;
        if ($id > 0) {
            $out[$id] = true;
        }
    }
    return array_keys($out);
}

function hg_relationships_append_not_in_ids(string &$sql, string &$types, array &$params, string $column, array $ids): void
{
    $ids = hg_relationships_normalize_ids($ids);
    if (!$ids) {
        return;
    }

    $sql .= ' AND ' . $column . ' NOT IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
    $types .= str_repeat('i', count($ids));
    foreach ($ids as $id) {
        $params[] = $id;
    }
}

function hg_relationships_bind(mysqli_stmt $stmt, string $types, array &$params): void
{
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
}

function hg_relationships_fetch_clan_map_characters(mysqli $link, array $excludedChronicleIds = []): ?array
{
    $sql = "
        SELECT
            p.id,
            p.name,
            p.image_url,
            cbc.organization_id,
            gbc.group_id AS manada_id
        FROM fact_characters p
            LEFT JOIN (
                SELECT character_id, MIN(organization_id) AS organization_id
                FROM bridge_characters_organizations
                WHERE (is_active = 1 OR is_active IS NULL)
                GROUP BY character_id
            ) cbc ON cbc.character_id = p.id
            LEFT JOIN (
                SELECT character_id, MIN(group_id) AS group_id
                FROM bridge_characters_groups
                WHERE (is_active = 1 OR is_active IS NULL)
                GROUP BY character_id
            ) gbc ON gbc.character_id = p.id
        WHERE 1=1";
    $types = '';
    $params = [];
    hg_relationships_append_not_in_ids($sql, $types, $params, 'p.chronicle_id', $excludedChronicleIds);

    $stmt = $link->prepare($sql);
    if (!($stmt instanceof mysqli_stmt)) {
        return null;
    }
    hg_relationships_bind($stmt, $types, $params);
    if (!$stmt->execute()) {
        $stmt->close();
        return null;
    }
    $result = $stmt->get_result();
    if (!($result instanceof mysqli_result)) {
        $stmt->close();
        return null;
    }

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $row['organization_id'] = isset($row['organization_id']) ? (int)$row['organization_id'] : 0;
        $row['manada_id'] = isset($row['manada_id']) ? (int)$row['manada_id'] : 0;
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

function hg_relationships_fetch_names_by_ids(mysqli $link, string $table, array $ids): ?array
{
    $allowed = ['dim_organizations', 'dim_groups'];
    if (!in_array($table, $allowed, true)) {
        return null;
    }

    $ids = hg_relationships_normalize_ids($ids);
    if (!$ids) {
        return [];
    }

    $sql = "SELECT id, name FROM {$table} WHERE id IN (" . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY name ASC';
    $types = str_repeat('i', count($ids));
    $params = $ids;
    $stmt = $link->prepare($sql);
    if (!($stmt instanceof mysqli_stmt)) {
        return null;
    }
    hg_relationships_bind($stmt, $types, $params);
    if (!$stmt->execute()) {
        $stmt->close();
        return null;
    }
    $result = $stmt->get_result();
    if (!($result instanceof mysqli_result)) {
        $stmt->close();
        return null;
    }

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[(int)$row['id']] = (string)$row['name'];
    }
    $stmt->close();
    return $rows;
}

function hg_relationships_fetch_org_group_relations(mysqli $link, array $organizationIds, array $groupIds): ?array
{
    $organizationIds = hg_relationships_normalize_ids($organizationIds);
    $groupIds = hg_relationships_normalize_ids($groupIds);
    if (!$organizationIds || !$groupIds) {
        return [];
    }

    $orgPlaceholders = implode(',', array_fill(0, count($organizationIds), '?'));
    $groupPlaceholders = implode(',', array_fill(0, count($groupIds), '?'));
    $sql = "
        SELECT organization_id, group_id
        FROM bridge_organizations_groups
        WHERE organization_id IN ({$orgPlaceholders})
          AND group_id IN ({$groupPlaceholders})
          AND (is_active = 1 OR is_active IS NULL)
    ";
    $params = array_merge($organizationIds, $groupIds);
    $types = str_repeat('i', count($params));
    $stmt = $link->prepare($sql);
    if (!($stmt instanceof mysqli_stmt)) {
        return null;
    }
    hg_relationships_bind($stmt, $types, $params);
    if (!$stmt->execute()) {
        $stmt->close();
        return null;
    }
    $result = $stmt->get_result();
    if (!($result instanceof mysqli_result)) {
        $stmt->close();
        return null;
    }

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $clanId = (int)$row['organization_id'];
        $groupId = (int)$row['group_id'];
        $rows[$clanId . '-' . $groupId] = ['clan' => $clanId, 'manada' => $groupId];
    }
    $stmt->close();
    return $rows;
}

function hg_relationships_fetch_character_nodes(mysqli $link, array $excludedChronicleIds = []): ?array
{
    $sql = "
        SELECT
            p.id,
            p.name,
            p.image_url,
            COALESCE(dcs.label, '') AS status,
            p.status_id,
            COALESCE(nc.name, '') AS clan_name
        FROM fact_characters p
            LEFT JOIN dim_character_status dcs
                ON dcs.id = p.status_id
            LEFT JOIN bridge_characters_organizations hccb
                ON hccb.character_id = p.id
               AND (hccb.is_active = 1 OR hccb.is_active IS NULL)
            LEFT JOIN dim_organizations nc
                ON nc.id = hccb.organization_id
        WHERE 1=1";
    $types = '';
    $params = [];
    hg_relationships_append_not_in_ids($sql, $types, $params, 'p.chronicle_id', $excludedChronicleIds);
    $sql .= ' ORDER BY p.name ASC';

    $stmt = $link->prepare($sql);
    if (!($stmt instanceof mysqli_stmt)) {
        return null;
    }
    hg_relationships_bind($stmt, $types, $params);
    if (!$stmt->execute()) {
        $stmt->close();
        return null;
    }
    $result = $stmt->get_result();
    if (!($result instanceof mysqli_result)) {
        $stmt->close();
        return null;
    }
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function hg_relationships_fetch_group_map_characters(mysqli $link, array $excludedChronicleIds = []): ?array
{
    $sql = "
        SELECT
            p.id,
            p.name,
            p.image_url,
            COALESCE(dcs.label, '') AS status,
            p.status_id,
            gbc.group_id
        FROM fact_characters p
            LEFT JOIN dim_character_status dcs
                ON dcs.id = p.status_id
            LEFT JOIN bridge_characters_groups gbc
                ON gbc.character_id = p.id
               AND (gbc.is_active = 1 OR gbc.is_active IS NULL)
        WHERE 1=1";
    $types = '';
    $params = [];
    hg_relationships_append_not_in_ids($sql, $types, $params, 'p.chronicle_id', $excludedChronicleIds);

    $stmt = $link->prepare($sql);
    if (!($stmt instanceof mysqli_stmt)) {
        return null;
    }
    hg_relationships_bind($stmt, $types, $params);
    if (!$stmt->execute()) {
        $stmt->close();
        return null;
    }
    $result = $stmt->get_result();
    if (!($result instanceof mysqli_result)) {
        $stmt->close();
        return null;
    }
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function hg_relationships_fetch_character_relations(mysqli $link): ?array
{
    $result = $link->query('SELECT * FROM bridge_characters_relations');
    if (!($result instanceof mysqli_result)) {
        return null;
    }
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();
    return $rows;
}
