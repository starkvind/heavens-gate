<?php
/**
 * Maneuver bridge helpers.
 */

function hg_maneuver_bridge_table_exists(mysqli $link, string $table): bool {
    $safe = $link->real_escape_string($table);
    $result = $link->query("SHOW TABLES LIKE '{$safe}'");
    $exists = $result && $result->num_rows > 0;
    if ($result) $result->free();
    return $exists;
}

function hg_assign_default_maneuvers_to_system(mysqli $link, int $systemId): int {
    if ($systemId <= 0 || !hg_maneuver_bridge_table_exists($link, 'bridge_maneuvers_systems')) return 0;

    $defaults = ['patada', 'punetazo', 'placaje', 'presa', 'barrido'];
    $placeholders = implode(',', array_fill(0, count($defaults), '?'));
    $sql = "SELECT id FROM fact_combat_maneuvers WHERE pretty_id IN ({$placeholders})";
    $stmt = $link->prepare($sql);
    if (!$stmt) return 0;

    $types = str_repeat('s', count($defaults));
    $params = [];
    $params[] = $types;
    foreach ($defaults as $index => $prettyId) $params[] = &$defaults[$index];
    call_user_func_array([$stmt, 'bind_param'], $params);
    $stmt->execute();
    $result = $stmt->get_result();

    $assigned = 0;
    if ($result && $insert = $link->prepare('INSERT IGNORE INTO bridge_maneuvers_systems (maneuver_id, system_id) VALUES (?, ?)')) {
        while ($row = $result->fetch_assoc()) {
            $maneuverId = (int)($row['id'] ?? 0);
            if ($maneuverId <= 0) continue;
            $insert->bind_param('ii', $maneuverId, $systemId);
            if ($insert->execute()) $assigned += $insert->affected_rows;
        }
        $insert->close();
    }
    if ($result) $result->free();
    $stmt->close();
    return $assigned;
}