<?php
require_once __DIR__ . '/../../helpers/admin_auth.php';
require_once __DIR__ . '/../../helpers/game_cards_runtime.php';

$gameCardsRoute = (string)($routeKey ?? ($_GET['p'] ?? 'game_cards'));
$gameCardsLabMode = strpos($gameCardsRoute, 'game_cards_lab') === 0;
$gameCardsBasePath = $gameCardsLabMode ? '/games/hg-cardgame-dev-lab' : '/games/card-game';
$gameCardsCatalogUrl = '/api/game_cards.php';
$gameCardsScriptSrc = hg_gc_runtime_entry_script();
$gameCardsStorageScope = $gameCardsLabMode ? 'dev-lab' : 'prod';

$gameCardsView = 'gacha';
if ($gameCardsRoute === 'game_cards_collection' || $gameCardsRoute === 'game_cards_lab_collection') {
    $gameCardsView = 'collection';
} elseif ($gameCardsRoute === 'game_cards_combat' || $gameCardsRoute === 'game_cards_lab_combat') {
    $gameCardsView = 'combat';
} elseif ($gameCardsRoute === 'game_cards_explanation' || $gameCardsRoute === 'game_cards_lab_explanation') {
    $gameCardsView = 'explanation';
}

$gameCardsBootScripts = hg_gc_runtime_boot_scripts($gameCardsView, false);

$titleSuffix = $gameCardsLabMode ? ' [Dev Lab]' : '';
$gameCardsTitles = [
    'gacha' => 'Archivo de mnemogeno' . $titleSuffix,
    'collection' => 'Coleccion de mnemogeno' . $titleSuffix,
    'combat' => 'Combate del Archivo de Mnemogeno' . $titleSuffix,
    'explanation' => 'Explicacion del Archivo de Mnemogeno' . $titleSuffix,
];

setMetaFromPage(
    ($gameCardsTitles[$gameCardsView] ?? $gameCardsTitles['gacha']) . " | Heaven's Gate",
    $gameCardsView === 'explanation'
        ? "Reglas, rarezas, atributos, sobres y Mnemones del juego de cartas de Heaven's Gate."
        : ($gameCardsLabMode
            ? "Entorno Dev Lab del juego de cartas. Usa almacenamiento local aislado para probar cambios sin tocar la coleccion de produccion."
            : "Abre sobres y conserva en este navegador una coleccion local de cartas de Heaven's Gate."),
    null,
    'website'
);

$hgCardsIsAdmin = hg_admin_is_authenticated();

echo '<link rel="stylesheet" href="' . htmlspecialchars(hg_gc_runtime_script('/assets/css/game-cards.css', 'css'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
echo '<section class="hg-card-game-shell">';
if ($gameCardsView === 'collection') {
    include dirname(__DIR__, 2) . '/partials/tool/game_cards/game_cards_collection_page.php';
} elseif ($gameCardsView === 'combat') {
    include dirname(__DIR__, 2) . '/partials/tool/game_cards/game_cards_combat_page.php';
} elseif ($gameCardsView === 'explanation') {
    include dirname(__DIR__, 2) . '/partials/tool/game_cards/game_cards_explanation_page.php';
} else {
    include dirname(__DIR__, 2) . '/partials/tool/game_cards/game_cards_page.php';
}
echo '</section>';
