<?php

/*
 * Canonical maneuver/Form admin state.
 *
 * This replaces the active use of the legacy hg_rules_admin_maneuver_state()
 * implementation kept in app/domains/rules/admin.php. Form identity now comes
 * from dim_forms.system_id and applicability from bridge_forms_applicability;
 * dim_forms.race is intentionally not read.
 */
if (!function_exists('hg_rules_admin_maneuver_state_normalized')) {
    function hg_rules_admin_maneuver_state_normalized(mysqli $link, int $selectedId, bool $bridgesReady): array
    {
        $maneuvers = hg_rules_admin_fetch_all($link, 'SELECT id, name, system_name, user FROM fact_combat_maneuvers ORDER BY system_name, name');
        if ($selectedId <= 0 && !empty($maneuvers)) $selectedId = (int)$maneuvers[0]['id'];

        $systems = hg_rules_admin_fetch_all($link, 'SELECT id, name FROM dim_systems ORDER BY sort_order, name');
        $forms = hg_rules_admin_fetch_all(
            $link,
            "SELECT
                f.id,
                f.form,
                s.name AS system_name,
                COALESCE(
                    GROUP_CONCAT(
                        DISTINCT COALESCE(NULLIF(db.name, ''), NULLIF(dt.name, ''))
                        ORDER BY COALESCE(NULLIF(db.name, ''), NULLIF(dt.name, ''))
                        SEPARATOR ', '
                    ),
                    ''
                ) AS applicability_name
             FROM dim_forms f
             JOIN dim_systems s ON s.id = f.system_id
             LEFT JOIN bridge_forms_applicability bfa
               ON bfa.form_id = f.id AND bfa.is_active = 1
             LEFT JOIN dim_breeds db ON db.id = bfa.breed_id
             LEFT JOIN dim_tribes dt ON dt.id = bfa.tribe_id
             GROUP BY f.id, f.form, f.sort_order, s.name, s.sort_order
             ORDER BY s.sort_order, s.name, applicability_name, f.sort_order, f.form"
        );

        $selectedSystems = [];
        $selectedForms = [];
        $maneuverLinkMap = [];

        if ($bridgesReady) {
            foreach (hg_rules_admin_fetch_all($link, 'SELECT maneuver_id, system_id FROM bridge_maneuvers_systems') as $row) {
                $maneuverLinkMap[(int)$row['maneuver_id']]['systems'][] = (int)$row['system_id'];
            }
            foreach (hg_rules_admin_fetch_all($link, 'SELECT maneuver_id, form_id FROM bridge_maneuvers_forms') as $row) {
                $maneuverLinkMap[(int)$row['maneuver_id']]['forms'][] = (int)$row['form_id'];
            }

            if ($selectedId > 0) {
                $st = $link->prepare('SELECT system_id FROM bridge_maneuvers_systems WHERE maneuver_id = ?');
                if ($st) {
                    $st->bind_param('i', $selectedId);
                    $st->execute();
                    $rs = $st->get_result();
                    while ($rs && ($row = $rs->fetch_assoc())) $selectedSystems[(int)$row['system_id']] = true;
                    $st->close();
                }

                $st = $link->prepare('SELECT form_id FROM bridge_maneuvers_forms WHERE maneuver_id = ?');
                if ($st) {
                    $st->bind_param('i', $selectedId);
                    $st->execute();
                    $rs = $st->get_result();
                    while ($rs && ($row = $rs->fetch_assoc())) $selectedForms[(int)$row['form_id']] = true;
                    $st->close();
                }
            }
        }

        return compact('maneuvers', 'selectedId', 'systems', 'forms', 'selectedSystems', 'selectedForms', 'maneuverLinkMap');
    }
}
