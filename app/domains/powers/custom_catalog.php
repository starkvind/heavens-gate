<?php

require_once __DIR__ . '/queries.php';

if (!function_exists('hg_power_custom_fetch_rows')) {
    function hg_power_custom_fetch_rows(mysqli $link, string $query): array
    {
        if (strpos($query, 'fact_gifts') !== false) {
            $rows = hg_powers_fetch_catalog($link, 'gifts');
        } elseif (strpos($query, 'fact_rites') !== false) {
            $rows = hg_powers_fetch_catalog($link, 'rites');
        } elseif (strpos($query, 'dim_totems') !== false) {
            $rows = hg_powers_fetch_catalog($link, 'totems');
        } elseif (strpos($query, 'fact_discipline_powers') !== false) {
            $rows = hg_powers_fetch_catalog($link, 'disciplines');
        } else {
            $rows = [];
        }

        return is_array($rows) ? $rows : [];
    }
}

if (!function_exists('hg_power_custom_gift_rules_col')) {
    function hg_power_custom_gift_rules_col(mysqli $link): string
    {
        return hg_powers_gift_rules_column($link);
    }
}
