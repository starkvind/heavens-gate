<?php

if (!function_exists('hg_cbe_table_exists')) {
    function hg_cbe_table_exists(mysqli $db, string $table): bool
    {
        static $cache = [];
        $key = strtolower($table);
        if (array_key_exists($key, $cache)) {
            return (bool)$cache[$key];
        }

        $ok = false;
        if ($st = $db->prepare("
            SELECT COUNT(*)
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
        ")) {
            $count = 0;
            $st->bind_param('s', $table);
            $st->execute();
            $st->bind_result($count);
            $st->fetch();
            $st->close();
            $ok = ((int)$count > 0);
        }

        $cache[$key] = $ok;
        return $ok;
    }
}

if (!function_exists('hg_cbe_column_exists')) {
    function hg_cbe_column_exists(mysqli $db, string $table, string $column): bool
    {
        static $cache = [];
        $key = strtolower($table . '.' . $column);
        if (array_key_exists($key, $cache)) {
            return (bool)$cache[$key];
        }

        $ok = false;
        if ($st = $db->prepare("
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
        ")) {
            $count = 0;
            $st->bind_param('ss', $table, $column);
            $st->execute();
            $st->bind_result($count);
            $st->fetch();
            $st->close();
            $ok = ((int)$count > 0);
        }

        $cache[$key] = $ok;
        return $ok;
    }
}

if (!function_exists('hg_cbe_month_map')) {
    function hg_cbe_month_map(): array
    {
        return [
            'enero' => 1,
            'febrero' => 2,
            'marzo' => 3,
            'abril' => 4,
            'mayo' => 5,
            'junio' => 6,
            'julio' => 7,
            'agosto' => 8,
            'septiembre' => 9,
            'setiembre' => 9,
            'octubre' => 10,
            'noviembre' => 11,
            'diciembre' => 12,
        ];
    }
}

if (!function_exists('hg_cbe_birthtext_expr')) {
    function hg_cbe_birthtext_expr(mysqli $db, string $characterAlias = 'fc'): string
    {
        if (hg_cbe_column_exists($db, 'fact_characters', 'birthdate_text')) {
            return $characterAlias . '.birthdate_text';
        }

        if (
            !hg_cbe_table_exists($db, 'fact_timeline_events')
            || !hg_cbe_table_exists($db, 'bridge_timeline_events_characters')
            || !hg_cbe_table_exists($db, 'dim_timeline_events_types')
        ) {
            return "''";
        }

        return "(
            SELECT CASE
                WHEN COALESCE(TRIM(e.date_note), '') <> '' AND e.date_precision <> 'day' THEN e.date_note
                WHEN e.date_precision = 'day' THEN DATE_FORMAT(e.event_date, '%Y-%m-%d')
                WHEN e.date_precision = 'month' THEN COALESCE(NULLIF(TRIM(e.date_note), ''), DATE_FORMAT(e.event_date, '%m/%Y'))
                WHEN e.date_precision = 'year' THEN COALESCE(NULLIF(TRIM(e.date_note), ''), DATE_FORMAT(e.event_date, '%Y'))
                ELSE COALESCE(e.date_note, '')
            END
            FROM bridge_timeline_events_characters btec
            INNER JOIN fact_timeline_events e ON e.id = btec.event_id
            INNER JOIN dim_timeline_events_types tet ON tet.id = e.event_type_id
            WHERE btec.character_id = {$characterAlias}.id
              AND tet.pretty_id = 'nacimiento'
            ORDER BY e.id ASC
            LIMIT 1
        )";
    }
}

