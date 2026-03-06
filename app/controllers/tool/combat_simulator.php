<?php
setMetaFromPage(
    "Simulador de combate | Heaven's Gate",
    "Simulador de combate de personajes integrado en la arquitectura actual.",
    null,
    'website'
);

require_once __DIR__ . '/../../helpers/sim_legacy_bootstrap.php';

$simRouteMap = [
    'combat_simulator' => 'simulator_page.php',
    'combat_simulator_result' => 'battle_result_page.php',
    'combat_simulator_logs' => 'battle_log_list_page.php',
    'combat_simulator_log' => 'battle_log_detail_page.php',
    'combat_simulator_scores' => 'scoreboard_page.php',
    'combat_simulator_weapons' => 'weapon_usage_page.php',

    // Legacy aliases kept for backward compatibility
    'simulador' => 'simulator_page.php',
    'simulador2' => 'battle_result_page.php',
    'combtodo' => 'battle_log_list_page.php',
    'vercombat' => 'battle_log_detail_page.php',
    'punts' => 'scoreboard_page.php',
    'arms' => 'weapon_usage_page.php',
];

$simTarget = $simRouteMap[$routeKey] ?? 'simulator_page.php';
$simFile = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'tool' . DIRECTORY_SEPARATOR . 'combat_simulator' . DIRECTORY_SEPARATOR . $simTarget;

if (!is_file($simFile)) {
    http_response_code(500);
    echo "<p>No se encuentra el archivo del simulador de combate: " . htmlspecialchars($simTarget, ENT_QUOTES, 'UTF-8') . ".</p>";
    return;
}

echo '<link rel="stylesheet" href="/assets/css/combat-simulator.css">';
$simPageClass = preg_replace('/[^a-z0-9\-]+/i', '-', (string)$routeKey);
if ($simPageClass === '' || $simPageClass === '-') {
    $simPageClass = 'combat-simulator';
}
echo '<section class="hg-sim-shell sim-' . htmlspecialchars($simPageClass, ENT_QUOTES, 'UTF-8') . '">';
include $simFile;
echo '</section>';
