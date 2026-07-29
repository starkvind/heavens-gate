<?php

if (!function_exists('hg_mobile_sanitize_int_csv')) {
    function hg_mobile_sanitize_int_csv($csv): string
    {
        $csv = trim((string)$csv);
        if ($csv === '' || strtoupper($csv) === 'FALSE') {
            return '';
        }
        $parts = preg_split('/\s*,\s*/', $csv);
        $ids = [];
        foreach ($parts as $part) {
            if (preg_match('/^\d+$/', (string)$part)) {
                $ids[] = (string)(int)$part;
            }
        }
        return implode(',', array_values(array_unique($ids)));
    }
}

if (!function_exists('hg_mobile_excluded_chronicles_csv')) {
    function hg_mobile_excluded_chronicles_csv(string $fallback = '2,7'): string
    {
        global $excludeChronicles;
        $configured = isset($excludeChronicles) ? hg_mobile_sanitize_int_csv($excludeChronicles) : '';
        if ($configured !== '') {
            return $configured;
        }
        return hg_mobile_sanitize_int_csv($fallback);
    }
}

if (!function_exists('hg_mobile_chronicle_exclusion_condition')) {
    function hg_mobile_chronicle_exclusion_condition(string $alias = 'p'): string
    {
        $csv = hg_mobile_excluded_chronicles_csv();
        if ($csv === '') {
            return '1=1';
        }
        $column = trim($alias) !== '' ? trim($alias) . '.chronicle_id' : 'chronicle_id';
        return $column . ' NOT IN (' . $csv . ')';
    }
}

if (!function_exists('hg_mobile_chronicle_exclusion_and')) {
    function hg_mobile_chronicle_exclusion_and(string $alias = 'p'): string
    {
        $condition = hg_mobile_chronicle_exclusion_condition($alias);
        return $condition === '1=1' ? '' : ' AND ' . $condition . ' ';
    }
}