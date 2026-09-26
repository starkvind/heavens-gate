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

if (!function_exists('hg_power_catalog_label')) {
    function hg_power_catalog_label(string $kind): string
    {
        static $labels = [
            'gifts' => 'Dones',
            'rites' => 'Rituales',
            'totems' => 'Tótems',
            'disciplines' => 'Disciplinas',
        ];

        return $labels[$kind] ?? 'Poderes';
    }
}

if (!function_exists('hg_power_catalog_export_assets')) {
    function hg_power_catalog_export_assets(bool $selectionActions = false): void
    {
        if (function_exists('hg_page_register_stylesheet')) {
            hg_page_register_stylesheet('/assets/css/hg-power-exports.css');
        } else {
            echo '<link rel="stylesheet" href="/assets/css/hg-power-exports.css">';
        }

        if (!$selectionActions || defined('HG_POWER_EXPORT_ACTIONS_LOADED')) {
            return;
        }
        define('HG_POWER_EXPORT_ACTIONS_LOADED', true);

        $src = '/assets/js/hg-power-export-actions.js';
        if (function_exists('hg_page_asset_versioned_href')) {
            $src = hg_page_asset_versioned_href($src);
        }
        echo '<script src="' . htmlspecialchars($src, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" defer></script>';
    }
}

if (!function_exists('hg_render_power_catalog_exports')) {
    function hg_render_power_catalog_exports(string $kind, string $active): void
    {
        if ($active !== 'full') {
            return;
        }

        $tabs = hg_power_catalog_tabs_map($kind);
        $fullHref = (string)($tabs['full'] ?? '');
        if ($fullHref === '') {
            return;
        }

        $label = hg_power_catalog_label($kind);
        $printHref = $fullHref . '?print=1';
        $markdownHref = $fullHref . '?export=md';

        echo "<div class='hg-power-export-bar' aria-label='Exportar catálogo de " . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "'>";
        echo "<span class='hg-power-export-bar__label'>Catálogo completo</span>";
        echo "<a class='hg-power-export-bar__action' href='" . htmlspecialchars($printHref, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "'>Imprimir todo</a>";
        echo "<a class='hg-power-export-bar__action hg-power-export-bar__action--primary' href='" . htmlspecialchars($markdownHref, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "'>Descargar todo (.md)</a>";
        echo "</div>";
    }
}

if (!function_exists('hg_render_power_catalog_tabs')) {
    function hg_render_power_catalog_tabs(string $kind, string $active): void
    {
        $tabs = hg_power_catalog_tabs_map($kind);
        if (!$tabs) {
            return;
        }

        if ($active === 'full' || $active === 'custom') {
            hg_power_catalog_export_assets($active === 'custom');
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

        hg_render_power_catalog_exports($kind, $active);
    }
}
