<?php

$metaTitle = "Simulador de Combate | Heaven's Gate";
$metaDescription = 'Simulador de combate en vista móvil.';
$pageSect = 'Juegos';

if (!defined('HG_MOBILE_TOOL_EMBED')) define('HG_MOBILE_TOOL_EMBED', true);
if (!defined('HG_MOBILE_DESKTOP_EMBED')) define('HG_MOBILE_DESKTOP_EMBED', true);
if (!defined('HG_MOBILE_COMBAT_SIMULATOR')) define('HG_MOBILE_COMBAT_SIMULATOR', true);

$currentSimRoute = trim((string)($_GET['p'] ?? 'combat_simulator'));
$simAliases = [
    'simulador' => 'combat_simulator',
    'simulador2' => 'combat_simulator_result',
    'combtodo' => 'combat_simulator_logs',
    'vercombat' => 'combat_simulator_log',
    'punts' => 'combat_simulator_scores',
    'arms' => 'combat_simulator_weapons',
    'sim_tournament' => 'combat_simulator_tournament',
];
$currentSimRoute = $simAliases[$currentSimRoute] ?? $currentSimRoute;

$simTabs = [
    'combat_simulator' => ['href' => '/games/combat-simulator', 'label' => 'Simular'],
    'combat_simulator_logs' => ['href' => '/games/combat-simulator/log', 'label' => 'Registro'],
    'combat_simulator_scores' => ['href' => '/games/combat-simulator/scores', 'label' => 'Ranking'],
    'combat_simulator_weapons' => ['href' => '/games/combat-simulator/weapons', 'label' => 'Armas'],
    'combat_simulator_tournament' => ['href' => '/games/combat-simulator/tournament', 'label' => 'Torneo'],
];

$activeTab = $currentSimRoute;
if ($currentSimRoute === 'combat_simulator_result') {
    $activeTab = 'combat_simulator';
} elseif ($currentSimRoute === 'combat_simulator_log') {
    $activeTab = 'combat_simulator_logs';
}
?>
<section class="hg-mobile-section hg-mobile-tool hg-mobile-combat-native">
    <header class="hg-mobile-combat-head">
        <h1>Simulador de Combate</h1>
        <nav class="hg-mobile-combat-tabs" aria-label="Secciónes del simulador">
            <?php foreach ($simTabs as $tabKey => $tab): ?>
                <a class="<?= $activeTab === $tabKey ? 'is-active' : '' ?>" href="<?= htmlspecialchars($tab['href'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                    <?= htmlspecialchars($tab['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </header>
    <?php include(__DIR__ . '/../../controllers/tool/combat_simulator.php'); ?>
</section>