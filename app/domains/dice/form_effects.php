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
            "SELECT b.override_value, b.modifier
             FROM dim_forms f
             JOIN fact_characters c ON c.id = ?
             JOIN dim_breeds br ON br.id = c.breed_id AND br.form_system_id = f.system_id
             LEFT JOIN bridge_forms_traits b ON b.form_id = f.id AND b.trait_id = ?
             WHERE f.id = ?
             LIMIT 1"
        );
        if (!$stmt) {
            return $baseValue;
        }

        mysqli_stmt_bind_param($stmt, 'iii', $characterId, $traitId, $formId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        if ($result) {
            mysqli_free_result($result);
        }
        mysqli_stmt_close($stmt);

        // No row means the selected form does not belong to the character's
        // breed form family (or the character has no form family at all).
        if (!$row) {
            return $baseValue;
        }

        if ($row['override_value'] !== null) {
            return max(0, (int)$row['override_value']);
        }

        if ($row['modifier'] !== null) {
            return max(0, $baseValue + (int)$row['modifier']);
        }

        // bridge_forms_traits is now the only source of attribute changes.
        // Missing rows mean that this Form does not modify the requested trait.
        return $baseValue;
    }
}
