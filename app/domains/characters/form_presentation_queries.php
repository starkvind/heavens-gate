<?php

if (!function_exists('hg_characters_fetch_form_presentation_by_ids')) {
    function hg_characters_fetch_form_presentation_by_ids(mysqli $link, array $formIds, int $breedId = 0): array
    {
        $ids = [];
        foreach ($formIds as $formId) {
            $formId = (int)$formId;
            if ($formId > 0) {
                $ids[$formId] = $formId;
            }
        }

        if (!$ids) {
            return [];
        }

        // IDs are explicitly cast to int above, so the IN list is safe.
        $sql = 'SELECT id, description, silhouette_image_url, weapons, firearms, hpregen '
            . 'FROM dim_forms WHERE id IN (' . implode(',', array_values($ids)) . ')';
        $rs = $link->query($sql);
        if (!$rs) {
            return [];
        }

        $regenProfile = null;
        if ($breedId > 0) {
            $stmt = $link->prepare(
                'SELECT native_form_id, regen_normal_per_turn, regen_stress_difficulty, '
                . 'regen_in_native_form, regen_aggravated_auto '
                . 'FROM dim_breeds WHERE id = ? LIMIT 1'
            );
            if ($stmt) {
                $stmt->bind_param('i', $breedId);
                $stmt->execute();
                $profileResult = $stmt->get_result();
                $regenProfile = $profileResult ? $profileResult->fetch_assoc() : null;
                if ($profileResult) $profileResult->free();
                $stmt->close();
            }
        }

        $rows = [];
        while ($row = $rs->fetch_assoc()) {
            $formId = (int)($row['id'] ?? 0);
            $baseHpregen = max(0, (int)($row['hpregen'] ?? 0));
            $effectiveHpregen = $baseHpregen;
            $row['regeneration_contextual'] = 0;
            $row['regen_stress_difficulty'] = 0;
            $row['regen_aggravated_auto'] = 0;

            if (is_array($regenProfile) && (int)($regenProfile['regen_normal_per_turn'] ?? 0) > 0) {
                $nativeFormId = (int)($regenProfile['native_form_id'] ?? 0);
                $regenInNativeForm = (int)($regenProfile['regen_in_native_form'] ?? 0) === 1;
                $isNativeForm = $nativeFormId > 0 && $formId === $nativeFormId;
                $row['regeneration_contextual'] = 1;
                $row['regen_stress_difficulty'] = max(0, (int)($regenProfile['regen_stress_difficulty'] ?? 0));
                $row['regen_aggravated_auto'] = (int)($regenProfile['regen_aggravated_auto'] ?? 0) === 1 ? 1 : 0;

                if (!$isNativeForm || $regenInNativeForm) {
                    $effectiveHpregen = max($effectiveHpregen, (int)$regenProfile['regen_normal_per_turn']);
                }
            }

            $row['hpregen'] = $effectiveHpregen;
            $row['regeneration'] = $effectiveHpregen > 0 ? 1 : 0;
            $row['regeneration_label'] = $effectiveHpregen > 0
                ? $effectiveHpregen . ' / turno'
                : ($row['regeneration_contextual'] ? 'No (Forma natal)' : 'No');
            $rows[$formId] = $row;
        }
        $rs->free();

        return $rows;
    }
}

if (!function_exists('hg_characters_fetch_form_trait_overrides')) {
    function hg_characters_fetch_form_trait_overrides(mysqli $link, array $formIds): array
    {
        $ids = [];
        foreach ($formIds as $formId) {
            $formId = (int)$formId;
            if ($formId > 0) {
                $ids[$formId] = $formId;
            }
        }

        if (!$ids) {
            return [];
        }

        // The production schema is deployed before this branch. Returning an
        // empty map on query failure keeps the presentation layer backwards
        // compatible while a deployment is still between code and schema.
        $sql = 'SELECT form_id, trait_id, override_value '
            . 'FROM bridge_forms_traits '
            . 'WHERE form_id IN (' . implode(',', array_values($ids)) . ') '
            . 'AND override_value IS NOT NULL';
        $rs = $link->query($sql);
        if (!$rs) {
            return [];
        }

        $rows = [];
        while ($row = $rs->fetch_assoc()) {
            $formId = (int)($row['form_id'] ?? 0);
            $traitId = (int)($row['trait_id'] ?? 0);
            if ($formId <= 0 || $traitId <= 0) {
                continue;
            }
            $rows[$formId][$traitId] = (int)$row['override_value'];
        }
        $rs->free();

        return $rows;
    }
}
