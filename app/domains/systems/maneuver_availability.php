<?php

/*
 * Canonical Maneuver availability for System/Form public surfaces.
 *
 * fact_combat_maneuvers.system_id identifies editorial origin only.
 * Runtime availability is exclusively relational:
 *   - bridge_maneuvers_systems = all Forms in a System;
 *   - bridge_maneuvers_forms   = one explicit Form.
 */

if (!function_exists('hg_systems_fetch_system_maneuvers_normalized')) {
    function hg_systems_fetch_system_maneuvers_normalized(mysqli $link, int $systemId): array
    {
        if ($systemId <= 0) return [];

        $stmt = $link->prepare(
            "SELECT DISTINCT
                m.id,
                m.pretty_id,
                m.name,
                m.image_url,
                m.roll,
                m.difficulty,
                m.damage,
                m.actions,
                m.system_id AS origin_system_id,
                COALESCE(origin_system.name, '') AS origin_system_name
             FROM bridge_maneuvers_systems availability
             INNER JOIN fact_combat_maneuvers m
               ON m.id = availability.maneuver_id
             LEFT JOIN dim_systems origin_system
               ON origin_system.id = m.system_id
             WHERE availability.system_id = ?
             ORDER BY m.name ASC"
        );
        if (!$stmt) return [];

        $stmt->bind_param('i', $systemId);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        while ($result && ($row = $result->fetch_assoc())) $rows[] = $row;
        if ($result) $result->free();
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('hg_systems_fetch_form_maneuvers_normalized')) {
    function hg_systems_fetch_form_maneuvers_normalized(mysqli $link, int $systemId, int $formId): array
    {
        if ($systemId <= 0 || $formId <= 0) return [];

        $stmt = $link->prepare(
            "SELECT DISTINCT
                m.id,
                m.pretty_id,
                m.name,
                m.image_url,
                m.roll,
                m.difficulty,
                m.damage,
                m.actions,
                m.system_id AS origin_system_id,
                COALESCE(origin_system.name, '') AS origin_system_name,
                CASE
                    WHEN system_availability.maneuver_id IS NOT NULL THEN 'system'
                    ELSE 'form'
                END AS availability_scope
             FROM fact_combat_maneuvers m
             LEFT JOIN bridge_maneuvers_systems system_availability
               ON system_availability.maneuver_id = m.id
              AND system_availability.system_id = ?
             LEFT JOIN bridge_maneuvers_forms form_availability
               ON form_availability.maneuver_id = m.id
              AND form_availability.form_id = ?
             LEFT JOIN dim_systems origin_system
               ON origin_system.id = m.system_id
             WHERE system_availability.maneuver_id IS NOT NULL
                OR form_availability.maneuver_id IS NOT NULL
             ORDER BY m.name ASC"
        );
        if (!$stmt) return [];

        $stmt->bind_param('ii', $systemId, $formId);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        while ($result && ($row = $result->fetch_assoc())) $rows[] = $row;
        if ($result) $result->free();
        $stmt->close();
        return $rows;
    }
}
