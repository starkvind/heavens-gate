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
            "SELECT b.override_value, b.modifier, t.name AS trait_name,
                    f.strength_bonus, f.dexterity_bonus, f.stamina_bonus
             FROM dim_forms f
             JOIN fact_characters c ON c.id = ?
             JOIN dim_breeds br ON br.id = c.breed_id AND br.form_system_id = f.system_id
             JOIN dim_traits t ON t.id = ?
             LEFT JOIN bridge_forms_traits b ON b.form_id = f.id AND b.trait_id = t.id
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

        // Legacy fallback while the remaining physical modifiers are moved
        // from dim_forms to bridge_forms_traits.
        $modifier = 0;
        switch ((string)($row['trait_name'] ?? '')) {
            case 'Fuerza':
                $modifier = (int)($row['strength_bonus'] ?? 0);
                break;
            case 'Destreza':
                $modifier = (int)($row['dexterity_bonus'] ?? 0);
                break;
            case 'Resistencia':
                $modifier = (int)($row['stamina_bonus'] ?? 0);
                break;
        }

        return max(0, $baseValue + $modifier);
    }
}
