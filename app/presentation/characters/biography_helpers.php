<?php

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('bio_page_is_admin_flag_enabled')) {
    function bio_page_is_admin_flag_enabled(): bool
    {
        if (function_exists('hg_admin_is_authenticated')) return hg_admin_is_authenticated();
        return (!empty($_SESSION) && is_array($_SESSION) && !empty($_SESSION['is_admin']));
    }
}

if (!function_exists('createSkillCircle')) {
    function createSkillCircle(array $array, string $prefix): array
    {
        $result = [];
        foreach ($array as $value) {
            $baseDir = ($prefix === 'gem-pwr') ? 'img/ui/gems/pwr' : 'img/ui/gems/attr';
            $result[] = "<img class='bioAttCircle' src='{$baseDir}/{$prefix}-0{$value}.webp'/>";
        }
        return $result;
    }
}

if (!function_exists('bio_order_trait_list')) {
    function bio_order_trait_list(array $list, int $baseCount = 0): array
    {
        if ($baseCount <= 0) return $list;
        $base = array_slice($list, 0, $baseCount);
        $extras = array_slice($list, $baseCount);
        usort($extras, static function (array $left, array $right): int {
            return strtolower((string)($left['name'] ?? '')) <=> strtolower((string)($right['name'] ?? ''));
        });
        return array_merge($base, $extras);
    }
}
