<?php
include("app/partials/main_nav_bar.php");

$isAdmin = !empty($hgCardsIsAdmin);
?>

<div class="hg-cards hg-cards--collection" data-view="collection" data-catalog-url="/api/game_cards.php" data-is-admin="<?php echo $isAdmin ? '1' : '0'; ?>">
    <nav class="hg-game-tabs" aria-label="Secciones del juego de cartas">
        <a href="/games/card-game">Sobres</a>
        <a href="/games/card-game#shop">Tienda</a>
        <a class="is-active" href="/games/card-game/collection">Colección</a>
        <a href="/games/card-game/collection#memory">Recuerdos</a>
        <a href="/games/card-game/combat">Combate</a>
        <a href="/games/card-game/explanation">Información</a>
    </nav>

    <header class="hg-cards__table-head">
        <div>
            <h2>Colección de mnemógeno</h2>
        </div>
    </header>

    <section class="hg-cards__controls" aria-label="Resumen de colección">
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
        <p id="hgStatusText" class="hg-cards__status">Cargando catálogo...</p>
    </section>

    <section class="hg-collection-browser" aria-label="Colección de cartas">
        <div class="hg-section-head hg-section-head--split">
            <div>
                <h3>Colección</h3>
                <p>Vista de álbum o tabla, filtrable por cartas obtenidas, rareza y colección.</p>
            </div>
        </div>

        <div class="hg-collection-toolbar" aria-label="Opciones de vista de colección">
            <div class="hg-collection-toolbar__main">
                <div class="hg-view-toggle" role="group" aria-label="Modo de vista">
                    <button type="button" class="is-active" data-collection-mode="album">Álbum</button>
                    <button type="button" data-collection-mode="table">Tabla</button>
                </div>
                <details class="hg-collection-advanced">
                    <summary>Filtros avanzados</summary>
                    <div class="hg-collection-filters" aria-label="Filtros de colección">
                        <label class="hg-filter-check">
                            <input type="checkbox" data-collection-owned-filter>
                            <span>Sólo obtenidas</span>
                        </label>
                        <label class="hg-filter-check">
                            <input type="checkbox" data-collection-has-moves-filter>
                            <span>Con habilidades</span>
                        </label>
                        <label class="hg-filter-check">
                            <input type="checkbox" data-collection-in-team-filter>
                            <span>En equipo</span>
                        </label>
                        <label class="hg-filter-check">
                            <input type="checkbox" data-collection-working-filter>
                            <span>Rememorando</span>
                        </label>
                        <label class="hg-collection-select hg-collection-search">
                            <span>Nombre</span>
                            <input type="search" data-collection-name-filter placeholder="Buscar carta...">
                        </label>
                        <label class="hg-collection-select">
                            <span>Rareza</span>
                            <select data-collection-rarity-filter>
                                <option value="all">Todas</option>
                                <option value="common">Común</option>
                                <option value="unusual">Inusual</option>
                                <option value="rare">Raro</option>
                                <option value="epic">Épico</option>
                                <option value="legendary">Legendario</option>
                                <option value="mythic">Mítico</option>
                                <option value="stigmatic">Estigmático</option>
                            </select>
                        </label>
                        <label class="hg-collection-select">
                            <span>Colección</span>
                            <select data-collection-type-filter></select>
                        </label>
                    </div>
                </details>
                <label class="hg-page-size">
                    <span>Por página</span>
                    <select data-collection-page-size>
                        <option value="12">12</option>
                        <option value="24" selected>24</option>
                        <option value="48">48</option>
                        <option value="96">96</option>
                    </select>
                </label>
            </div>
        </div>

        <div class="hg-album-tabs" data-album-tabs role="tablist" aria-label="Categorías del álbum"></div>
        <div class="hg-pagination hg-pagination--top" data-collection-pager aria-live="polite"></div>

        <div class="hg-collection-view is-active" data-collection-view="album">
            <div class="hg-album__grid" data-album-grid aria-live="polite"></div>
        </div>

        <div class="hg-collection-view" data-collection-view="table" hidden>
            <div class="hg-collection-table-wrap">
                <table id="hgCollectionTable" class="display hg-collection-table" style="width:100%">
                    <thead>
                        <tr>
                            <th>Rareza</th>
                            <th>Calidad</th>
                            <th>Carta</th>
                            <th>Categoría</th>
                            <th>ID</th>
                            <th>PS</th>
                            <th>ATQ</th>
                            <th>DEF</th>
                            <th>Total</th>
                            <th>Copias</th>
                            <th>Obtención</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

        <div class="hg-pagination" data-collection-pager aria-live="polite"></div>
    </section>

    <section id="memory" class="hg-workbench" aria-label="Recordar cartas">
        <div class="hg-section-head hg-section-head--split">
            <div>
                <h3>Recordar</h3>
                <p>Las cartas que están rememorando generan Mnemones, fragmentos de Mnemógeno. Puedes tener hasta 5 y cada una debe permanecer 24 horas antes de volver.</p>
            </div>
            <button type="button" class="hg-primary-btn" data-work-claim>Reclamar</button>
        </div>
        <div class="hg-workbench__summary" data-work-summary aria-live="polite"></div>
        <div class="hg-workbench__list" data-work-list aria-live="polite"></div>
    </section>

    <section class="hg-collection-tools" aria-label="Gestión de colección">
        <div class="hg-section-head">
            <h3>Gestión</h3>
            <p>Herramientas de respaldo, borrado local y venta de copias.</p>
        </div>

        <div class="hg-import-export">
            <button type="button" id="hgExportCollection">Exportar JSON</button>
            <label class="hg-file-btn" for="hgImportFile">Importar JSON</label>
            <input type="file" id="hgImportFile" accept="application/json,.json">
            <button type="button" class="hg-danger-btn" id="hgResetCollection">Borrar colección local</button>
        </div>

        <section class="hg-player-profile" aria-label="Perfil de combate">
            <div>
                <h3>Perfil de combate</h3>
                <p>Guarda el nombre que aparece en los combates y en los registros.</p>
            </div>
            <label class="hg-collection-select">
                <span>Nombre del jugador</span>
                <input type="text" maxlength="32" placeholder="Jugador" data-combat-profile-name>
            </label>
        </section>

        <section class="hg-bulk-sell" aria-label="Venta de cartas por rareza">
            <div>
                <h3>Vender cartas en lote</h3>
                <p>Elige una rareza y convierte sus copias en Remorias.</p>
            </div>
            <label for="hgBulkSellRarity">Rareza</label>
            <select id="hgBulkSellRarity">
                <option value="common">Común</option>
                <option value="unusual">Inusual</option>
                <option value="rare">Raro</option>
                <option value="epic">Épico</option>
                <option value="legendary">Legendario</option>
                <option value="mythic">Mítico</option>
                <option value="stigmatic">Estigmático</option>
            </select>
            <label class="hg-bulk-sell__keep">
                <input type="checkbox" id="hgBulkSellKeepBest" checked>
                <span>Conservar la mejor copia de cada carta</span>
            </label>
            <button type="button" class="hg-danger-btn" id="hgBulkSellButton">Vender cartas...</button>
            <strong id="hgBulkSellPreview" class="hg-bulk-sell__preview" aria-live="polite"></strong>
        </section>
    </section>
</div>

<script src="/assets/js/game-cards.js?v=20260529-evo-fix-1" defer></script>
