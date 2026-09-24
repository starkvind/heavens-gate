<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

function hg_phase7_runtime_fail(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$contracts = [
    'app/domains/systems/queries.php' => [
        'function hg_systems_fetch_resource',
        'function hg_systems_fetch_resources',
        'function hg_systems_fetch_mobile_resources',
        'function hg_systems_fetch_detail',
        'function hg_systems_fetch_gifts',
        'function hg_systems_fetch_members',
        'function hg_systems_fetch_form',
        'function hg_systems_fetch_form_maneuvers',
        'bridge_systems_resources_to_system',
        'dim_systems_resources',
    ],
    'app/domains/characters/admin_service.php' => [
        'function pjs_table_exists',
        'function pjs_table_has_column',
        'bridge_characters_system_resources',
        'dim_character_status',
    ],
    'app/helpers/maps.php' => [
        'function hg_maps_json',
        'JSON_HEX_TAG',
    ],
    'app/controllers/maps/maps_main.php' => [
        'hg_maps_json($mainConfig)',
    ],
];

foreach ($contracts as $relative => $needles) {
    $source = file_get_contents($root . '/' . $relative);
    if ($source === false) {
        hg_phase7_runtime_fail("Cannot read {$relative}");
    }
    foreach ($needles as $needle) {
        if (strpos($source, $needle) === false) {
            hg_phase7_runtime_fail("Phase 7 runtime contract missing in {$relative}: {$needle}");
        }
    }
}

$consumerAudit = file_get_contents($root . '/.github/ci/php-consumer-graph-audit.py');
if ($consumerAudit === false || strpos($consumerAudit, "(?:php|=)") === false) {
    hg_phase7_runtime_fail('Consumer graph no longer parses short echo PHP blocks');
}

$removedPhase72Symbols = [
    'ensure_trailing_query',
    'hg_cbe_format_event_birth_text',
    'hg_cbe_upsert_birth_event_from_text',
    'hg_content_touch_at',
    'hg_maps_fetch_areas',
    'hg_maps_fetch_categories',
    'hg_maps_fetch_maps',
    'hg_maps_fetch_poi_detail',
    'hg_maps_fetch_pois',
    'hg_maps_fetch_related_pois',
    'hg_maps_schema_info',
    'hg_power_custom_asset_image',
    'hg_ser_constraint_exists',
    'hg_ser_ensure_energy_schema',
    'hg_ser_index_exists',
    'hg_ser_retire_legacy_schema',
    'pjs_delete_character_avatar_variant',
    'pjs_upsert_character_avatar_variant',
    'hg_cbe_find_birth_type_id',
    'hg_cbe_parse_birth_text',
    'hg_maps_sql_append_not_in_ids',
    'hg_maps_sql_append_not_in_strings',
    'hg_power_custom_fetch_rows',
    'hg_cbe_month_map',
];

foreach (['app', 'api'] as $runtimeRoot) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root . '/' . $runtimeRoot, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
            continue;
        }
        $source = file_get_contents($file->getPathname());
        if ($source === false) continue;
        foreach ($removedPhase72Symbols as $symbol) {
            if (preg_match('/\\b' . preg_quote($symbol, '/') . '\\s*\\(/', $source)) {
                $relative = str_replace($root . '/', '', $file->getPathname());
                hg_phase7_runtime_fail("Removed Phase 7.2 symbol still called in {$relative}: {$symbol}");
            }
        }
    }
}

fwrite(STDOUT, "Phase 7 runtime regression characterization: OK\n");
