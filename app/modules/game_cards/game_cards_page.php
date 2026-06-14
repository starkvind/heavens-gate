<?php
include("app/partials/main_nav_bar.php");

if (!function_exists('hg_cards_h')) {
    function hg_cards_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$isAdmin = !empty($hgCardsIsAdmin);
$gameCardsBasePath = $gameCardsBasePath ?? '/games/card-game';
$gameCardsCatalogUrl = $gameCardsCatalogUrl ?? '/api/game_cards.php';
$gameCardsScriptSrc = $gameCardsScriptSrc ?? '/assets/js/game-cards-v2.js?v=20260614-upgraded-guard';
$gameCardsStorageScope = $gameCardsStorageScope ?? 'prod';
$gameCardsBootScripts = $gameCardsBootScripts ?? [];
?>

<div class="hg-cards" data-view="gacha" data-catalog-url="<?php echo hg_cards_h($gameCardsCatalogUrl); ?>" data-is-admin="<?php echo $isAdmin ? '1' : '0'; ?>" data-base-path="<?php echo hg_cards_h($gameCardsBasePath); ?>" data-mobile-url="<?php echo hg_cards_h($gameCardsBasePath . '/mobile'); ?>" data-storage-scope="<?php echo hg_cards_h($gameCardsStorageScope); ?>">
    <nav class="hg-game-tabs" aria-label="Secciones del juego de cartas">
        <a class="is-active" href="<?php echo hg_cards_h($gameCardsBasePath); ?>">Sobres</a>
        <a href="<?php echo hg_cards_h($gameCardsBasePath . '#shop'); ?>">Tienda</a>
        <a href="<?php echo hg_cards_h($gameCardsBasePath . '/collection'); ?>">Colección</a>
        <a href="<?php echo hg_cards_h($gameCardsBasePath . '/collection#memory'); ?>">Recuerdos</a>
        <a href="<?php echo hg_cards_h($gameCardsBasePath . '/combat'); ?>">Combate</a>
        <a href="<?php echo hg_cards_h($gameCardsBasePath . '/explanation'); ?>">Información</a>
    </nav>

    <header class="hg-cards__titlebar">
        <h2>Archivo de mnemógeno</h2>
    </header>

    <section class="hg-pack-section" aria-label="Sobres disponibles">
        <div class="hg-section-head">
            <h3>Sobres</h3>
            <p>Todos los sobres contienen 5 cartas.</p>
        </div>

        <div class="hg-pack-grid" data-pack-grid></div>
    </section>

    <section class="hg-cards__controls" aria-label="Estado de sobres">
        <div class="hg-counter hg-counter--currency" aria-live="polite">
            <span>Mnemones</span>
            <strong data-mnemones-counter>0</strong>
        </div>
        <div class="hg-counter hg-counter--currency" aria-live="polite">
            <span>Remorias</span>
            <strong data-remorias-counter>0</strong>
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

    <section id="shop" class="hg-shop-section" aria-label="Tienda de sobres">
        <div class="hg-section-head">
            <h3>Intercambio de mnemógeno</h3>
            <p>Usa Mnemones para reclamar sobres, comprar objetos rituales y cambiar por Remorias.</p>
        </div>
        <div class="hg-shop-grid"></div>
    </section>

    <section class="hg-pack-results" aria-live="polite" aria-label="Resultado del sobre">
        <div class="hg-section-head">
            <h3>Sobre abierto</h3>
            <p id="hgStatusText">Cargando catálogo...</p>
        </div>
        <div class="hg-pack-results__grid" id="hgPackResults"></div>
    </section>
</div>

<?php foreach ($gameCardsBootScripts as $gameCardsBootScript): ?>
<script src="<?php echo hg_cards_h($gameCardsBootScript); ?>" defer></script>
<?php endforeach; ?>
<script src="<?php echo hg_cards_h($gameCardsScriptSrc); ?>" defer></script>
