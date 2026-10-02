<?php

if (!function_exists('hg_dice_resolve_form_attribute_value')) {
    function hg_dice_resolve_form_attribute_value(
        mysqli $db,
        int $characterId,
        int $formId,
        int $traitId,
        int $baseValue
    ): int {
        $baseValue = max(0, $baseValue);
        if ($characterId <= 0 || $formId <= 0 || $traitId <= 0) {
            return $baseValue;
        }

        $stmt = mysqli_prepare(
            $db,
            "SELECT b.override_value
             FROM dim_forms f
             JOIN fact_characters c ON c.id = ?
             JOIN dim_breeds br ON br.id = c.breed_id AND br.form_system_id = f.system_id
             LEFT JOIN bridge_forms_traits b ON b.form_id = f.id AND b.trait_id = ?
             WHERE f.id = ?
             LIMIT 1"
        );

        // During a rolling deployment the column may not exist yet. Preserve
        // the legacy additive behavior until the schema change has landed.
        if (!$stmt) {
            return max(0, $baseValue + hg_dice_form_attribute_modifier($db, $characterId, $formId, $traitId));
        }

        mysqli_stmt_bind_param($stmt, 'iii', $characterId, $traitId, $formId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        if ($result) {
            mysqli_free_result($result);
        }
        mysqli_stmt_close($stmt);

        if (is_array($row) && array_key_exists('override_value', $row) && $row['override_value'] !== null) {
            return max(0, (int)$row['override_value']);
        }

        return max(0, $baseValue + hg_dice_form_attribute_modifier($db, $characterId, $formId, $traitId));
    }
}
