<?php

require_once __DIR__ . '/queries.php';

if (!function_exists('hg_power_custom_build_items')) {
    function hg_power_custom_build_items(mysqli $link, array $config): array
    {
        $kind = (string)($config['kind'] ?? '');
        $rows = hg_powers_fetch_catalog($link, $kind);
        if (!is_array($rows)) return [];

        $mapper = $config['map_row'] ?? null;
        $items = [];
        foreach ($rows as $row) {
            $item = is_callable($mapper) ? $mapper($row, $link) : $row;
            if (!is_array($item)) continue;

            $item = hg_power_custom_ensure_utf8($item);
            $item['fields'] = is_array($item['fields'] ?? null) ? $item['fields'] : [];
            $item['chips'] = is_array($item['chips'] ?? null) ? $item['chips'] : [];
            $item['sections'] = is_array($item['sections'] ?? null) ? $item['sections'] : [];
            $items[] = $item;
        }

        return $items;
    }
}

if (!function_exists('hg_power_custom_gift_rules_col')) {
    function hg_power_custom_gift_rules_col(mysqli $link): string
    {
        return hg_powers_gift_rules_column($link);
    }
}
