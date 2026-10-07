<?php

require_once __DIR__ . '/../characters/sheet_queries.php';
require_once __DIR__ . '/../characters/form_presentation_queries.php';

if (!function_exists('hg_dice_resolve_form_context')) {
    function hg_dice_resolve_form_context(mysqli $db, int $characterId, int $formId): ?array
    {
        if ($characterId <= 0 || $formId <= 0) {
            return null;
        }

        $stmt = $db->prepare(
            'SELECT c.breed_id, c.tribe_id, br.form_system_id '
            . 'FROM fact_characters c '
            . 'JOIN dim_breeds br ON br.id = c.breed_id '
            . 'WHERE c.id = ? LIMIT 1'
        );
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('i', $characterId);
        $stmt->execute();
        $result = $stmt->get_result();
        $character = $result ? $result->fetch_assoc() : null;
        if ($result) {
            $result->free();
        }
        $stmt->close();

        $breedId = (int)($character['breed_id'] ?? 0);
        $tribeId = (int)($character['tribe_id'] ?? 0);
        $formSystemId = (int)($character['form_system_id'] ?? 0);
        if ($breedId <= 0 || $formSystemId <= 0) {
            return null;
        }

        $selectedForm = null;
        foreach (hg_characters_fetch_forms_for_system($db, $formSystemId, $breedId, $tribeId) as $form) {
            if ((int)($form['id'] ?? 0) === $formId) {
                $selectedForm = $form;
                break;
            }
        }
        if (!$selectedForm) {
            return null;
        }

        $presentationById = hg_characters_fetch_form_presentation_by_ids($db, [$formId], $breedId);
        $presentation = $presentationById[$formId] ?? null;
        if (!is_array($presentation)) {
            return null;
        }

        return [
            'id' => $formId,
            'name' => trim((string)($selectedForm['form'] ?? '')),
            'weapons' => (int)($presentation['weapons'] ?? 0),
            'firearms' => (int)($presentation['firearms'] ?? 0),
            'hpregen' => (int)($presentation['hpregen'] ?? 0),
        ];
    }
}

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

        // Validate the supplied form through the same canonical family and
        // applicability path used by character sheets. A same-system form that
        // does not apply to this character must not affect a roll.
        if (hg_dice_resolve_form_context($db, $characterId, $formId) === null) {
            return $baseValue;
        }

        $stmt = $db->prepare(
            'SELECT override_value, modifier '
            . 'FROM bridge_forms_traits '
            . 'WHERE form_id = ? AND trait_id = ? '
            . 'LIMIT 1'
        );
        if (!$stmt) {
            return $baseValue;
        }

        $stmt->bind_param('ii', $formId, $traitId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        if ($result) {
            $result->free();
        }
        $stmt->close();

        if (!$row) {
            return $baseValue;
        }

        if ($row['override_value'] !== null) {
            return max(0, (int)$row['override_value']);
        }

        if ($row['modifier'] !== null) {
            return max(0, $baseValue + (int)$row['modifier']);
        }

        // bridge_forms_traits is the only source of Form trait changes.
        // Missing/empty effects mean that the selected Form leaves the trait
        // unchanged.
        return $baseValue;
    }
}
