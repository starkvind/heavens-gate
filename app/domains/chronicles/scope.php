<?php

if (!function_exists('hg_chronicle_scope_sanitize_int_csv')) {
    function hg_chronicle_scope_sanitize_int_csv($csv): string
    {
        $csv = trim((string)$csv);
        if ($csv === '' || strtoupper($csv) === 'FALSE') {
            return '';
        }

        $ids = [];
        foreach (preg_split('/\\s*,\\s*/', $csv) ?: [] as $part) {
            if (preg_match('/^\\d+$/', (string)$part)) {
                $ids[] = (string)(int)$part;
            }
        }
        return implode(',', array_values(array_unique($ids)));
    }
}

if (!function_exists('hg_chronicle_scope_excluded_csv')) {
    function hg_chronicle_scope_excluded_csv(?string $configured = null, string $fallback = '2,7'): string
    {
        if ($configured === null) {
            global $excludeChronicles;
            $configured = isset($excludeChronicles) ? (string)$excludeChronicles : '';
        }

        $normalized = hg_chronicle_scope_sanitize_int_csv($configured);
        if ($normalized !== '') {
            return $normalized;
        }
        return hg_chronicle_scope_sanitize_int_csv($fallback);
    }
}
