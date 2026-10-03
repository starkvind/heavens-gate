<?php

if (!function_exists('hg_characters_fetch_form_presentation_by_ids')) {
    function hg_characters_fetch_form_presentation_by_ids(mysqli $link, array $formIds): array
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

        $rows = [];
        while ($row = $rs->fetch_assoc()) {
            $row['regeneration'] = ((int)($row['hpregen'] ?? 0) > 0) ? 1 : 0;
            $rows[(int)$row['id']] = $row;
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
