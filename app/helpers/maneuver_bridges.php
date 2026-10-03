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

/**
 * Transitional compatibility shim for the System admin controller.
 *
 * Historical code called this after creating every System. That policy was
 * incorrect: Maneuver availability is now explicit and relational. Keep the
 * symbol harmless until the controller-side call is physically removed.
 */
function hg_assign_default_maneuvers_to_system(mysqli $link, int $systemId): int {
    return 0;
}
