<?php
include("app/partials/main_nav_bar.php");
require_once __DIR__ . '/game_cards_info_content.php';

$gameCardsBasePath = $gameCardsBasePath ?? '/games/card-game';
?>

<div class="hg-cards hg-cards--explanation" data-view="explanation">
    <nav class="hg-game-tabs" aria-label="Secciones del juego de cartas">
        <a data-game-tab="packs" href="<?php echo htmlspecialchars((string)$gameCardsBasePath, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">Sobres</a>
        <a data-game-tab="shop" href="<?php echo htmlspecialchars((string)($gameCardsBasePath . '#shop'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">Tienda</a>
        <a data-game-tab="collection" href="<?php echo htmlspecialchars((string)($gameCardsBasePath . '/collection'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">Colección</a>
        <a data-game-tab="memory" href="<?php echo htmlspecialchars((string)($gameCardsBasePath . '/collection#memory'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">Recuerdos</a>
        <a data-game-tab="combat" href="<?php echo htmlspecialchars((string)($gameCardsBasePath . '/combat'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">Combate</a>
        <a class="is-active" data-game-tab="info" href="<?php echo htmlspecialchars((string)($gameCardsBasePath . '/explanation'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">Información</a>
    </nav>

    <header class="hg-cards__titlebar">
        <p class="hg-cards__kicker">Reglas del minijuego</p>
        <h2>Archivo de Mnemógeno</h2>
        <p class="hg-cards__intro">Guía de rarezas, sobres, atributos, Mnemones, Remorias, progreso y combate.</p>
    </header>

    <?php hg_gc_render_info_content('desktop'); ?>
</div>
