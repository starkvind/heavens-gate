<?php
include("app/partials/main_nav_bar.php");
require_once __DIR__ . '/game_cards_info_content.php';
?>

<div class="hg-cards hg-cards--explanation" data-view="explanation">
    <nav class="hg-game-tabs" aria-label="Secciones del juego de cartas">
        <a href="/games/card-game">Sobres</a>
        <a href="/games/card-game#shop">Tienda</a>
        <a href="/games/card-game/collection">Colección</a>
        <a href="/games/card-game/collection#memory">Recuerdos</a>
        <a href="/games/card-game/combat">Combate</a>
        <a class="is-active" href="/games/card-game/explanation">Información</a>
    </nav>

    <header class="hg-cards__titlebar">
        <p class="hg-cards__kicker">Reglas del minijuego</p>
        <h2>Archivo de Mnemógeno</h2>
        <p class="hg-cards__intro">Guía de rarezas, sobres, atributos, Mnemones, Remorias, progreso y combate.</p>
    </header>

    <?php hg_gc_render_info_content('desktop'); ?>
</div>
