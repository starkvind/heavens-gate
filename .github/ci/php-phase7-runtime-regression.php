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
        'function hg_systems_fetch_resources',
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

fwrite(STDOUT, "Phase 7 runtime regression characterization: OK\n");
