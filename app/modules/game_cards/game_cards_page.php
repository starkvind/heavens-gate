<?php
include("app/partials/main_nav_bar.php");

if (!function_exists('hg_cards_h')) {
    function hg_cards_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$isAdmin = !empty($hgCardsIsAdmin);
?>

<div class="hg-cards" data-view="gacha" data-catalog-url="/api/game_cards.php" data-is-admin="<?php echo $isAdmin ? '1' : '0'; ?>">
    <nav class="hg-game-tabs" aria-label="Secciones del juego de cartas">
        <a class="is-active" href="/games/card-game">Sobres</a>
        <a href="/games/card-game/collection">Colección</a>
        <a href="/games/card-game/explanation">Información</a>
    </nav>

    <header class="hg-cards__titlebar">
        <h2>Archivo de mnemógeno</h2>
    </header>

    <section class="hg-pack-section" aria-label="Sobres disponibles">
        <div class="hg-section-head">
            <h3>Sobres</h3>
            <p>Todos los sobres contienen 5 cartas.</p>
        </div>

        <div class="hg-pack-grid">
            <button type="button" class="hg-pack hg-pack--standard" data-pack-kind="standard">
                <span class="hg-pack__seal">HG</span>
                <span class="hg-pack__title">Sobre mnemónico</span>
                <span class="hg-pack__count">5 cartas</span>
                <span class="hg-pack__stock" data-pack-stock="standard">0 hoy</span>
            </button>
            <button type="button" class="hg-pack hg-pack--echoes" data-pack-kind="echoes">
                <span class="hg-pack__seal">EC</span>
                <span class="hg-pack__title">Sobre de ecos</span>
                <span class="hg-pack__count">Común e inusual</span>
                <span class="hg-pack__stock" data-pack-stock="echoes">x0</span>
            </button>
            <button type="button" class="hg-pack hg-pack--magic" data-pack-kind="magic">
                <span class="hg-pack__seal">✦</span>
                <span class="hg-pack__title">Sobre mágico</span>
                <span class="hg-pack__count">Mejor rareza</span>
                <span class="hg-pack__stock" data-pack-stock="magic">x0</span>
            </button>
            <button type="button" class="hg-pack hg-pack--characters" data-pack-kind="characters">
                <span class="hg-pack__seal">PJ</span>
                <span class="hg-pack__title">Sobre de personajes</span>
                <span class="hg-pack__count">Sólo personajes</span>
                <span class="hg-pack__stock" data-pack-stock="characters">x0</span>
            </button>
            <button type="button" class="hg-pack hg-pack--lineage" data-pack-kind="lineage">
                <span class="hg-pack__seal">LN</span>
                <span class="hg-pack__title">Sobre de linaje</span>
                <span class="hg-pack__count">Personajes y linajes</span>
                <span class="hg-pack__stock" data-pack-stock="lineage">x0</span>
            </button>
            <button type="button" class="hg-pack hg-pack--essence" data-pack-kind="essence">
                <span class="hg-pack__seal">ES</span>
                <span class="hg-pack__title">Sobre de esencia</span>
                <span class="hg-pack__count">Sistemas y formas</span>
                <span class="hg-pack__stock" data-pack-stock="essence">x0</span>
            </button>
            <button type="button" class="hg-pack hg-pack--powers" data-pack-kind="powers">
                <span class="hg-pack__seal">Ω</span>
                <span class="hg-pack__title">Sobre arcano</span>
                <span class="hg-pack__count">Poderes y ritos</span>
                <span class="hg-pack__stock" data-pack-stock="powers">x0</span>
            </button>
            <button type="button" class="hg-pack hg-pack--chronicles" data-pack-kind="chronicles">
                <span class="hg-pack__seal">CR</span>
                <span class="hg-pack__title">Sobre de crónica</span>
                <span class="hg-pack__count">Historias y temporadas</span>
                <span class="hg-pack__stock" data-pack-stock="chronicles">x0</span>
            </button>
            <button type="button" class="hg-pack hg-pack--relics" data-pack-kind="relics">
                <span class="hg-pack__seal">RX</span>
                <span class="hg-pack__title">Sobre de reliquias</span>
                <span class="hg-pack__count">Objetos y documentos</span>
                <span class="hg-pack__stock" data-pack-stock="relics">x0</span>
            </button>
            <button type="button" class="hg-pack hg-pack--omens" data-pack-kind="omens">
                <span class="hg-pack__seal">PR</span>
                <span class="hg-pack__title">Sobre de presagios</span>
                <span class="hg-pack__count">Raro o superior</span>
                <span class="hg-pack__stock" data-pack-stock="omens">x0</span>
            </button>
        </div>
    </section>

    <section class="hg-cards__controls" aria-label="Estado de sobres">
        <div class="hg-counter hg-counter--currency" aria-live="polite">
            <span>Mnemones</span>
            <strong data-mnemones-counter>0</strong>
        </div>
        <div class="hg-counter" aria-live="polite">
            <span>Sobres gratis</span>
            <strong id="hgDailyPacksCounter"><?php echo $isAdmin ? 'Admin' : '0 / 10'; ?></strong>
        </div>
        <div class="hg-counter" aria-live="polite">
            <span>Colección</span>
            <strong id="hgUniqueCounter">0 / 0</strong>
        </div>
        <div class="hg-counter" aria-live="polite">
            <span>Copias</span>
            <strong id="hgTotalCopiesCounter">0</strong>
        </div>
    </section>

    <section class="hg-shop-section" aria-label="Tienda de sobres">
        <div class="hg-section-head">
            <h3>Intercambio de mnemógeno</h3>
            <p>Usa Mnemones para reclamar sobres adicionales.</p>
        </div>
        <div class="hg-shop-grid">
            <button type="button" class="hg-shop-item" data-buy-pack="standard">
                <span>Sobre mnemónico</span>
                <strong>50 Mnemones</strong>
            </button>
            <button type="button" class="hg-shop-item" data-buy-pack="echoes">
                <span>Sobre de ecos</span>
                <strong>90 Mnemones</strong>
            </button>
            <button type="button" class="hg-shop-item" data-buy-pack="magic">
                <span>Sobre mágico</span>
                <strong>220 Mnemones</strong>
            </button>
            <button type="button" class="hg-shop-item" data-buy-pack="characters">
                <span>Sobre de personajes</span>
                <strong>240 Mnemones</strong>
            </button>
            <button type="button" class="hg-shop-item" data-buy-pack="lineage">
                <span>Sobre de linaje</span>
                <strong>420 Mnemones</strong>
            </button>
            <button type="button" class="hg-shop-item" data-buy-pack="essence">
                <span>Sobre de esencia</span>
                <strong>300 Mnemones</strong>
            </button>
            <button type="button" class="hg-shop-item" data-buy-pack="powers">
                <span>Sobre arcano</span>
                <strong>240 Mnemones</strong>
            </button>
            <button type="button" class="hg-shop-item" data-buy-pack="chronicles">
                <span>Sobre de crónica</span>
                <strong>140 Mnemones</strong>
            </button>
            <button type="button" class="hg-shop-item" data-buy-pack="relics">
                <span>Sobre de reliquias</span>
                <strong>160 Mnemones</strong>
            </button>
            <button type="button" class="hg-shop-item" data-buy-pack="omens">
                <span>Sobre de presagios</span>
                <strong>650 Mnemones</strong>
            </button>
        </div>
    </section>

    <section class="hg-pack-results" aria-live="polite" aria-label="Resultado del sobre">
        <div class="hg-section-head">
            <h3>Sobre abierto</h3>
            <p id="hgStatusText">Cargando catálogo...</p>
        </div>
        <div class="hg-pack-results__grid" id="hgPackResults"></div>
    </section>
</div>

<script src="/assets/js/game-cards.js" defer></script>
