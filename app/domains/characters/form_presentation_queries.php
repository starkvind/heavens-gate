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
        $sql = 'SELECT id, description, silhouette_image_url, weapons, firearms, regeneration, hpregen '
            . 'FROM dim_forms WHERE id IN (' . implode(',', array_values($ids)) . ')';
        $rs = $link->query($sql);
        if (!$rs) {
            return [];
        }

        $rows = [];
        while ($row = $rs->fetch_assoc()) {
            $rows[(int)$row['id']] = $row;
        }
        $rs->free();

        return $rows;
    }
}
