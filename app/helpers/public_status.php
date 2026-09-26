<?php
// Public-facing status metrics. Keep this deliberately editorial: no schema,
// bridge, configuration, log, debug or administrative-health counters.

if (!function_exists('hg_public_status_count')) {
    function hg_public_status_count(mysqli $link, string $table, string $where = '1=1'): ?int
    {
        static $allowed = [
            'dim_chronicles' => true,
            'dim_seasons' => true,
            'dim_chapters' => true,
            'fact_characters' => true,
            'dim_organizations' => true,
            'dim_groups' => true,
            'fact_timeline_events' => true,
            'fact_docs' => true,
            'fact_items' => true,
            'fact_gifts' => true,
            'fact_rites' => true,
            'fact_discipline_powers' => true,
            'dim_systems' => true,
            'dim_maps' => true,
            'fact_map_pois' => true,
        ];
        if (!isset($allowed[$table])) {
            return null;
        }

        $sql = "SELECT COUNT(*) AS total FROM `{$table}` WHERE {$where}";
        $rs = @$link->query($sql);
        if (!$rs) {
            return null;
        }
        $row = $rs->fetch_assoc();
        $rs->close();
        return isset($row['total']) ? (int)$row['total'] : 0;
    }
}

if (!function_exists('hg_public_status_metrics')) {
    function hg_public_status_metrics(mysqli $link): array
    {
        $gifts = hg_public_status_count($link, 'fact_gifts');
        $rites = hg_public_status_count($link, 'fact_rites');
        $disciplines = hg_public_status_count($link, 'fact_discipline_powers');
        $powers = null;
        if ($gifts !== null || $rites !== null || $disciplines !== null) {
            $powers = (int)($gifts ?? 0) + (int)($rites ?? 0) + (int)($disciplines ?? 0);
        }

        return [
            'Crónicas' => hg_public_status_count($link, 'dim_chronicles'),
            'Temporadas' => hg_public_status_count($link, 'dim_seasons'),
            'Capítulos' => hg_public_status_count($link, 'dim_chapters'),
            'Personajes' => hg_public_status_count($link, 'fact_characters'),
            'Organizaciones' => hg_public_status_count($link, 'dim_organizations'),
            'Grupos y manadas' => hg_public_status_count($link, 'dim_groups'),
            'Eventos' => hg_public_status_count($link, 'fact_timeline_events', 'is_active = 1'),
            'Documentos' => hg_public_status_count($link, 'fact_docs'),
            'Objetos' => hg_public_status_count($link, 'fact_items'),
            'Poderes' => $powers,
            'Sistemas de juego' => hg_public_status_count($link, 'dim_systems'),
            'Mapas' => hg_public_status_count($link, 'dim_maps'),
            'Puntos de interés' => hg_public_status_count($link, 'fact_map_pois'),
        ];
    }
}
