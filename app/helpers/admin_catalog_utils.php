<?php
// Shared helpers for narrative/admin catalog CRUD.

include_once(__DIR__ . '/pretty.php');

if (!function_exists('hg_admin_catalog_count_by_id')) {
    function hg_admin_catalog_count_by_id(mysqli $link, string $table, string $column, int $id): int
    {
        if ($id <= 0) {
            return 0;
        }

        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        if ($table === '' || $column === '') {
            return 0;
        }

        if (!hg_table_has_column($link, $table, $column)) {
            return 0;
        }

        $sql = "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = ?";
        $st = $link->prepare($sql);
        if (!$st) {
            return 0;
        }

        $count = 0;
        $st->bind_param('i', $id);
        if ($st->execute()) {
            $st->bind_result($count);
            $st->fetch();
        }
        $st->close();

        return (int)$count;
    }
}

if (!function_exists('hg_admin_catalog_name_exists')) {
    function hg_admin_catalog_name_exists(mysqli $link, string $table, string $name, int $excludeId = 0): bool
    {
        $name = trim($name);
        if ($name === '') {
            return false;
        }

        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        if ($table === '' || !hg_table_has_column($link, $table, 'name')) {
            return false;
        }

        $sql = "SELECT id FROM `{$table}` WHERE name = ? AND id <> ? LIMIT 1";
        $st = $link->prepare($sql);
        if (!$st) {
            return false;
        }

        $foundId = 0;
        $st->bind_param('si', $name, $excludeId);
        $ok = false;
        if ($st->execute()) {
            $st->bind_result($foundId);
            $ok = $st->fetch();
        }
        $st->close();

        return (bool)$ok && $foundId > 0;
    }
}

if (!function_exists('hg_admin_catalog_pretty_exists')) {
    function hg_admin_catalog_pretty_exists(mysqli $link, string $table, string $prettyId, int $excludeId = 0): bool
    {
        $prettyId = trim($prettyId);
        if ($prettyId === '') {
            return false;
        }

        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        if ($table === '' || !hg_table_has_column($link, $table, 'pretty_id')) {
            return false;
        }

        $sql = "SELECT id FROM `{$table}` WHERE pretty_id = ? AND id <> ? LIMIT 1";
        $st = $link->prepare($sql);
        if (!$st) {
            return false;
        }

        $foundId = 0;
        $st->bind_param('si', $prettyId, $excludeId);
        $ok = false;
        if ($st->execute()) {
            $st->bind_result($foundId);
            $ok = $st->fetch();
        }
        $st->close();

        return (bool)$ok && $foundId > 0;
    }
}

if (!function_exists('hg_admin_catalog_update_pretty_id')) {
    function hg_admin_catalog_update_pretty_id(mysqli $link, string $table, int $id, string $prettyId): bool
    {
        if ($id <= 0) {
            return false;
        }

        $prettyId = trim($prettyId);
        if ($prettyId === '') {
            return false;
        }

        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        if ($table === '' || !hg_table_has_column($link, $table, 'pretty_id')) {
            return true;
        }

        $sql = "UPDATE `{$table}` SET pretty_id = ? WHERE id = ?";
        $st = $link->prepare($sql);
        if (!$st) {
            return false;
        }

        $st->bind_param('si', $prettyId, $id);
        $ok = $st->execute();
        $st->close();

        return (bool)$ok;
    }
}

if (!function_exists('hg_admin_catalog_persist_pretty_id')) {
    function hg_admin_catalog_persist_pretty_id(mysqli $link, string $table, int $id, string $source): bool
    {
        if ($id <= 0) {
            return false;
        }

        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        if ($table === '' || !hg_table_has_column($link, $table, 'pretty_id')) {
            return true;
        }

        $baseSlug = hg_pretty_expected_slug($table, $source, $id);

        $slug = $baseSlug;
        if (hg_admin_catalog_pretty_exists($link, $table, $slug, $id)) {
            $slug = $baseSlug . '-' . $id;
        }
        if (hg_admin_catalog_pretty_exists($link, $table, $slug, $id)) {
            $suffix = 2;
            while (hg_admin_catalog_pretty_exists($link, $table, $slug . '-' . $suffix, $id)) {
                $suffix++;
            }
            $slug = $slug . '-' . $suffix;
        }

        $sql = "UPDATE `{$table}` SET pretty_id = ? WHERE id = ?";
        $st = $link->prepare($sql);
        if (!$st) {
            return false;
        }

        $st->bind_param('si', $slug, $id);
        $ok = $st->execute();
        $st->close();

        return (bool)$ok;
    }
}

if (!function_exists('hg_admin_catalog_assign_pretty_id')) {
    function hg_admin_catalog_assign_pretty_id(mysqli $link, string $table, int $id, string $preferredPrettyId, string $fallbackSource): bool
    {
        if ($id <= 0) {
            return false;
        }

        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        if ($table === '' || !hg_table_has_column($link, $table, 'pretty_id')) {
            return true;
        }

        $preferredPrettyId = trim($preferredPrettyId);
        $hasManualPrettyId = ($preferredPrettyId !== '');
        $slug = $hasManualPrettyId
            ? slugify_pretty_id($preferredPrettyId)
            : hg_pretty_expected_slug($table, $fallbackSource, $id);

        if ($slug === '') {
            $slug = (string)$id;
        }

        if (hg_admin_catalog_pretty_exists($link, $table, $slug, $id)) {
            if ($hasManualPrettyId) {
                return false;
            }
            $baseSlug = $slug;
            $suffix = 2;
            while (hg_admin_catalog_pretty_exists($link, $table, $baseSlug . '-' . $suffix, $id)) {
                $suffix++;
            }
            $slug = $baseSlug . '-' . $suffix;
        }

        $sql = "UPDATE `{$table}` SET pretty_id = ? WHERE id = ?";
        $st = $link->prepare($sql);
        if (!$st) {
            return false;
        }

        $st->bind_param('si', $slug, $id);
        $ok = $st->execute();
        $st->close();

        return (bool)$ok;
    }
}

if (!function_exists('hg_admin_catalog_dependencies_total')) {
    function hg_admin_catalog_dependencies_total(array $deps): int
    {
        $total = 0;
        foreach ($deps as $dep) {
            $total += (int)($dep['count'] ?? 0);
        }
        return $total;
    }
}

if (!function_exists('hg_admin_catalog_dependencies_summary')) {
    function hg_admin_catalog_dependencies_summary(array $deps): string
    {
        $parts = [];
        foreach ($deps as $dep) {
            $count = (int)($dep['count'] ?? 0);
            if ($count <= 0) {
                continue;
            }
            $label = trim((string)($dep['label'] ?? 'Dependencias'));
            $parts[] = $label . ' (' . $count . ')';
        }
        return implode(', ', $parts);
    }
}

if (!function_exists('hg_admin_catalog_get_chronicle_dependencies')) {
    function hg_admin_catalog_get_chronicle_dependencies(mysqli $link, int $chronicleId): array
    {
        $deps = [
            [
                'key' => 'characters',
                'label' => 'Personajes',
                'count' => hg_admin_catalog_count_by_id($link, 'fact_characters', 'chronicle_id', $chronicleId),
            ],
            [
                'key' => 'timeline',
                'label' => 'Vinculos de linea temporal',
                'count' => hg_admin_catalog_count_by_id($link, 'bridge_timeline_events_chronicles', 'chronicle_id', $chronicleId),
            ],
        ];

        if (hg_table_has_column($link, 'dim_seasons', 'chronicle_id')) {
            $deps[] = [
                'key' => 'seasons',
                'label' => 'Temporadas',
                'count' => hg_admin_catalog_count_by_id($link, 'dim_seasons', 'chronicle_id', $chronicleId),
            ];
        }

        return $deps;
    }
}

if (!function_exists('hg_admin_catalog_get_reality_dependencies')) {
    function hg_admin_catalog_get_reality_dependencies(mysqli $link, int $realityId): array
    {
        return [
            [
                'key' => 'characters',
                'label' => 'Personajes',
                'count' => hg_admin_catalog_count_by_id($link, 'fact_characters', 'reality_id', $realityId),
            ],
            [
                'key' => 'timeline',
                'label' => 'Vinculos de linea temporal',
                'count' => hg_admin_catalog_count_by_id($link, 'bridge_timeline_events_realities', 'reality_id', $realityId),
            ],
        ];
    }
}

if (!function_exists('hg_admin_catalog_get_player_dependencies')) {
    function hg_admin_catalog_get_player_dependencies(mysqli $link, int $playerId): array
    {
        return [
            [
                'key' => 'characters',
                'label' => 'Personajes',
                'count' => hg_admin_catalog_count_by_id($link, 'fact_characters', 'player_id', $playerId),
            ],
        ];
    }
}
