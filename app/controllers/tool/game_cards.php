<?php
require_once __DIR__ . '/../../helpers/admin_auth.php';

$gameCardsRoute = (string)($routeKey ?? ($_GET['p'] ?? 'game_cards'));
$gameCardsLabMode = strpos($gameCardsRoute, 'game_cards_lab') === 0;
$gameCardsBasePath = $gameCardsLabMode ? '/games/hg-cardgame-dev-lab' : '/games/card-game';
$gameCardsCatalogUrl = '/api/game_cards.php';
$gameCardsScriptSrc = '/assets/js/game-cards-v2.js?v=20260614-upgraded-guard' . ($gameCardsLabMode ? '-lab' : '');
$gameCardsStorageScope = $gameCardsLabMode ? 'dev-lab' : 'prod';
$gameCardsBootScripts = $gameCardsLabMode
    ? [
        '/assets/js/card-game/bootstrap/game-card-features.js?v=20260614-dev-lab',
        '/assets/js/card-game/bootstrap/game-card-loader.js?v=20260614-dev-lab',
        '/assets/js/card-game/bootstrap/game-card-app.js?v=20260614-dev-lab',
    ]
    : [];

$gameCardsView = 'gacha';
if ($gameCardsRoute === 'game_cards_collection' || $gameCardsRoute === 'game_cards_lab_collection') {
    $gameCardsView = 'collection';
} elseif ($gameCardsRoute === 'game_cards_combat' || $gameCardsRoute === 'game_cards_lab_combat') {
    $gameCardsView = 'combat';
} elseif ($gameCardsRoute === 'game_cards_explanation' || $gameCardsRoute === 'game_cards_lab_explanation') {
    $gameCardsView = 'explanation';
}

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

echo '<link rel="stylesheet" href="/assets/css/game-cards.css?v=20260530-ui-texts-final-db">';
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
