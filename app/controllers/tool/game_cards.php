<?php
require_once __DIR__ . '/../../helpers/admin_auth.php';

$gameCardsRoute = (string)($routeKey ?? ($_GET['p'] ?? 'game_cards'));
$gameCardsView = 'gacha';
if ($gameCardsRoute === 'game_cards_collection') {
    $gameCardsView = 'collection';
} elseif ($gameCardsRoute === 'game_cards_combat') {
    $gameCardsView = 'combat';
} elseif ($gameCardsRoute === 'game_cards_explanation') {
    $gameCardsView = 'explanation';
}

$gameCardsTitles = [
    'gacha' => 'Archivo de mnemogeno',
    'collection' => 'Coleccion de mnemogeno',
    'combat' => 'Combate del Archivo de Mnemogeno',
    'explanation' => 'Explicacion del Archivo de Mnemogeno',
];

setMetaFromPage(
    ($gameCardsTitles[$gameCardsView] ?? $gameCardsTitles['gacha']) . " | Heaven's Gate",
    $gameCardsView === 'explanation'
        ? "Reglas, rarezas, atributos, sobres y Mnemones del juego de cartas de Heaven's Gate."
        : "Abre sobres y conserva en este navegador una coleccion local de cartas de Heaven's Gate.",
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
