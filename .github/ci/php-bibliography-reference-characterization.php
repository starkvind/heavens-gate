<?php
// No DB required: verify bibliography detail links match the real public router.
require_once __DIR__ . '/../../app/domains/bibliography/admin.php';
require_once __DIR__ . '/../../app/routing/path_matcher.php';

$expected = [
    'fact_gifts' => 'muestradon',
    'fact_rites' => 'seerite',
    'dim_totems' => 'muestratotem',
    'fact_discipline_powers' => 'muestradisc',
    'fact_docs' => 'verdoc',
    'fact_items' => 'verobj',
    'dim_systems' => 'sistemas',
    'dim_breeds' => 'versistdetalle',
    'dim_auspices' => 'versistdetalle',
    'dim_tribes' => 'versistdetalle',
    'fact_misc_systems' => 'versistdetalle',
    'dim_forms' => 'verforma',
    'dim_traits' => 'verrasgo',
    'dim_merits_flaws' => 'vermyd',
    'fact_actions' => 'veraction',
    'fact_combat_maneuvers' => 'vermaneu',
    'fact_timeline_events' => 'timeline_event',
    'fact_map_pois' => 'maps_detail',
    'bridge_gifts_availability' => 'muestradon',
];
$routes = hg_bib_admin_public_routes();
if (array_keys($routes) !== array_keys($expected)) {
    fwrite(STDERR, "Bibliography route allowlist changed; review targets.\n");
    exit(1);
}
foreach ($expected as $table => $action) {
    $prefix = $routes[$table];
    $matched = hg_request_path_matcher_match($prefix . '123');
    $actual = (string)($matched['params']['p'] ?? '');
    if ($actual !== $action) {
        fwrite(STDERR, "{$table} resolved to {$actual}, expected {$action}\n");
        exit(1);
    }
}
echo "Bibliography reference links: verified " . count($expected) . " public route mappings.\n";
