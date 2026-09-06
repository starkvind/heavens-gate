<?php

if (!function_exists('hg_power_catalog_tabs_map')) {
    function hg_power_catalog_tabs_map(string $kind): array
    {
        static $map = [
            'gifts' => [
                'table' => '/powers/gifts',
                'full' => '/powers/gifts/full',
                'custom' => '/powers/gifts/custom',
            ],
            'rites' => [
                'table' => '/powers/rites',
                'full' => '/powers/rites/full',
                'custom' => '/powers/rites/custom',
            ],
            'totems' => [
                'table' => '/powers/totems',
                'full' => '/powers/totems/full',
                'custom' => '/powers/totems/custom',
            ],
            'disciplines' => [
                'table' => '/powers/disciplines',
                'full' => '/powers/disciplines/full',
                'custom' => '/powers/disciplines/custom',
            ],
        ];

        return $map[$kind] ?? [];
    }
}

if (!function_exists('hg_render_power_catalog_tabs')) {
    function hg_render_power_catalog_tabs(string $kind, string $active): void
    {
        $tabs = hg_power_catalog_tabs_map($kind);
        if (!$tabs) {
            return;
        }

        $defs = [
            'table' => ['icon' => '▦', 'label' => 'Tabla'],
            'full' => ['icon' => '≣', 'label' => 'Completa'],
            'custom' => ['icon' => '✦', 'label' => 'Personalizada'],
        ];

        echo "<div class='hg-tabs hg-power-catalog-tabs'>";
        foreach ($tabs as $key => $href) {
            $def = $defs[$key] ?? ['icon' => '•', 'label' => ucfirst($key)];
            $activeClass = ($active === $key) ? ' active' : '';
            echo "<a class='hgTabBtn{$activeClass}' href='" . htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "'>";
            echo "<span class='hgTabEmoji'>" . htmlspecialchars($def['icon'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</span>";
            echo "<span class='hgTabLabel'>" . htmlspecialchars($def['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</span>";
            echo "</a>";
        }
        echo "</div>";
    }
}
