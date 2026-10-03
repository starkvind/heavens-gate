<?php
/**
 * Maneuver bridge helpers.
 *
 * Availability policy belongs to the Maneuver editor and normalized bridge
 * tables. New Systems do not receive Maneuvers automatically.
 */

function hg_maneuver_bridge_table_exists(mysqli $link, string $table): bool {
    return in_array($table, ['bridge_maneuvers_systems', 'bridge_maneuvers_forms'], true);
}
