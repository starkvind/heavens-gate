<?php
$isAdmin = !empty($hgCardsIsAdmin);
require_once __DIR__ . '/game_cards_info_content.php';
?>

<div class="hg-cards hg-cards--mobile" data-view="gacha" data-mobile="1" data-catalog-url="/api/game_cards.php" data-is-admin="<?php echo $isAdmin ? '1' : '0'; ?>">
    <header class="hg-mobile-topbar">
        <div>
            <h2>Archivo de Mnemógeno</h2>
        </div>
        <div class="hg-counter hg-counter--currency" aria-live="polite">
            <span>Mnemones</span>
            <strong data-mnemones-counter>0</strong>
        </div>
        <div class="hg-counter hg-counter--currency" aria-live="polite">
            <span>Remorias</span>
            <strong data-remorias-counter>0</strong>
        </div>
    </header>

    <nav class="hg-mobile-tabs" aria-label="Secciones móviles del juego">
        <button type="button" class="is-active" data-mobile-panel-tab="packs">Sobres</button>
        <button type="button" data-mobile-panel-tab="shop">Tienda</button>
        <button type="button" data-mobile-panel-tab="collection">Colección</button>
        <button type="button" data-mobile-panel-tab="memory">Recordar</button>
        <button type="button" data-mobile-panel-tab="combat">Combate</button>
        <button type="button" data-mobile-panel-tab="info">Información</button>
    </nav>

    <main class="hg-mobile-panels">
        <section class="hg-mobile-panel is-active" data-mobile-panel="packs" aria-label="Sobres disponibles">
            <div class="hg-cards__controls hg-mobile-stats" aria-label="Estado de sobres">
                <div class="hg-counter" aria-live="polite">
                    <span>Colección</span>
                    <strong id="hgUniqueCounter">0 / 0</strong>
                </div>
                <div class="hg-counter" aria-live="polite">
                    <span>Copias</span>
                    <strong id="hgTotalCopiesCounter">0</strong>
                </div>
            </div>

            <p id="hgStatusText" class="hg-cards__status">Cargando catálogo...</p>

            <div class="hg-pack-grid hg-pack-grid--mobile" data-pack-grid>
                <button type="button" class="hg-pack hg-pack--standard" data-pack-kind="standard">
                    <span class="hg-pack__seal">HG</span>
                    <span class="hg-pack__title">Sobre mnemónico</span>
                    <span class="hg-pack__count">5 cartas</span>
                    <span class="hg-pack__stock" data-pack-stock="standard">x0</span>
                </button>
                <button type="button" class="hg-pack hg-pack--echoes" data-pack-kind="echoes">
                    <span class="hg-pack__seal">EC</span>
                    <span class="hg-pack__title">Sobre de ecos</span>
                    <span class="hg-pack__count">Comunes e Inusuales</span>
                    <span class="hg-pack__stock" data-pack-stock="echoes">x0</span>
                </button>
                <button type="button" class="hg-pack hg-pack--magic" data-pack-kind="magic">
                    <span class="hg-pack__seal">*</span>
                    <span class="hg-pack__title">Sobre mágico</span>
                    <span class="hg-pack__count">Mejor rareza</span>
                    <span class="hg-pack__stock" data-pack-stock="magic">x0</span>
                </button>
                <button type="button" class="hg-pack hg-pack--characters" data-pack-kind="characters">
                    <span class="hg-pack__seal">PJ</span>
                    <span class="hg-pack__title">Sobre de personajes</span>
                    <span class="hg-pack__count">Solo biografías</span>
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
                    <span class="hg-pack__seal">PW</span>
                    <span class="hg-pack__title">Sobre arcano</span>
                    <span class="hg-pack__count">Poderes y Ritos</span>
                    <span class="hg-pack__stock" data-pack-stock="powers">x0</span>
                </button>
                <button type="button" class="hg-pack hg-pack--chronicles" data-pack-kind="chronicles">
                    <span class="hg-pack__seal">CR</span>
                    <span class="hg-pack__title">Sobre de crónica</span>
                    <span class="hg-pack__count">Historias</span>
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
                <button type="button" class="hg-pack hg-pack--gaian" data-pack-kind="gaian">
                    <span class="hg-pack__seal">GA</span>
                    <span class="hg-pack__title">Sobre gaiano</span>
                    <span class="hg-pack__count">Epico o superior</span>
                    <span class="hg-pack__stock" data-pack-stock="gaian">x0</span>
                </button>
                <button type="button" class="hg-pack-open-all" data-pack-open-all>Abrir todos</button>
            </div>

            <section class="hg-pack-results" aria-live="polite" aria-label="Resultado del sobre">
                <div class="hg-section-head">
                    <h3>Último sobre</h3>
                    <p>Desliza o usa las flechas para revisar las cartas obtenidas.</p>
                </div>
                <div class="hg-pack-results__grid" id="hgPackResults"></div>
            </section>
        </section>

        <section class="hg-mobile-panel" data-mobile-panel="shop" aria-label="Tienda de sobres">
            <div class="hg-section-head">
                <h3>Intercambio de mnemógeno</h3>
                <p>Usa Mnemones para reclamar sobres, comprar objetos rituales y cambiar por Remorias.</p>
            </div>
            <div class="hg-shop-grid hg-shop-grid--mobile">
                <article class="hg-shop-item" data-shop-pack="standard" data-shop-free="1"><span>Mnemónico gratis</span><strong>Gratis - quedan 3</strong></article>
                <article class="hg-shop-item" data-shop-pack="standard"><span>Sobre mnemónico</span><strong>50 Mnemones</strong></article>
                <article class="hg-shop-item" data-shop-pack="echoes"><span>Sobre de ecos</span><strong>90 Mnemones</strong></article>
                <article hidden class="hg-shop-item" data-shop-pack="magic"><span>Sobre mágico</span><strong>220 Mnemones</strong></article>
                <article class="hg-shop-item" data-shop-pack="characters"><span>Sobre de personajes</span><strong>240 Mnemones</strong></article>
                <article class="hg-shop-item" data-shop-pack="lineage"><span>Sobre de linaje</span><strong>420 Mnemones</strong></article>
                <article class="hg-shop-item" data-shop-pack="essence"><span>Sobre de esencia</span><strong>300 Mnemones</strong></article>
                <article class="hg-shop-item" data-shop-pack="powers"><span>Sobre arcano</span><strong>240 Mnemones</strong></article>
                <article class="hg-shop-item" data-shop-pack="chronicles"><span>Sobre de crónica</span><strong>140 Mnemones</strong></article>
                <article class="hg-shop-item" data-shop-pack="relics"><span>Sobre de reliquias</span><strong>160 Mnemones</strong></article>
                <article hidden class="hg-shop-item" data-shop-pack="omens"><span>Sobre de presagios</span><strong>650 Mnemones</strong></article>
                <article hidden class="hg-shop-item" data-shop-pack="gaian"><span>Sobre gaiano</span><strong>2000 Mnemones</strong></article>
                <article class="hg-shop-item" data-shop-material="icarus_vial"><span>Vial de Ícaro</span><strong>10000 Mnemones</strong></article>
                <article class="hg-shop-item" data-shop-material="stigma_orb"><span>Orbe de Estigma</span><strong>50000 Mnemones</strong></article>
                <article class="hg-shop-item" data-shop-material="babylon_shred"><span>Retal de Babilonia</span><strong>125000 Mnemones</strong></article>
                <article class="hg-shop-item" data-shop-material="mnemo_glyph"><span>Glifo nemonico</span><strong>500 Mnemones</strong></article>
                <article class="hg-shop-item" data-shop-exchange-remorias="10"><span>Cambio por 10 Remorias</span><strong>1000 Mnemones</strong></article>
                <article class="hg-shop-item" data-shop-exchange-remorias="100"><span>Cambio por 100 Remorias</span><strong>10000 Mnemones</strong></article>
                <article class="hg-shop-item" data-shop-exchange-remorias="1000"><span>Cambio por 1000 Remorias</span><strong>100000 Mnemones</strong></article>
            </div>
        </section>

        <section class="hg-mobile-panel" data-mobile-panel="collection" aria-label="Colección local">
            <section class="hg-collection-browser hg-collection-browser--mobile" aria-label="Colección de cartas">
                <div class="hg-section-head">
                    <h3>Colección</h3>
                    <p>Vista de álbum o tabla, filtrable por obtenidas, rareza y colección.</p>
                </div>

                <div class="hg-collection-toolbar" aria-label="Opciones de vista de colección">
                    <div class="hg-collection-toolbar__main">
                        <div class="hg-view-toggle" role="group" aria-label="Modo de vista">
                            <button type="button" class="is-active" data-collection-mode="album">Álbum</button>
                            <button type="button" data-collection-mode="table">Tabla</button>
                        </div>
                        <label class="hg-page-size">
                            <span>Por página</span>
                            <select data-collection-page-size>
                                <option value="10">10</option>
                                <option value="20" selected>20</option>
                                <option value="50">50</option>
                            </select>
                        </label>
                    </div>
                    <div class="hg-collection-filters" aria-label="Filtros de colección">
                        <label class="hg-filter-check">
                            <input type="checkbox" data-collection-owned-filter>
                            <span>S&oacute;lo obtenidas</span>
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
                                <option value="stigmatic">Estigm&aacute;tico</option>
                            </select>
                        </label>
                        <label class="hg-collection-select">
                            <span>Colección</span>
                            <select data-collection-type-filter></select>
                        </label>
                    </div>
                </div>

                <div class="hg-album-tabs" data-album-tabs role="tablist" aria-label="Categorías del álbum"></div>
                <div class="hg-pagination hg-pagination--top" data-collection-pager aria-live="polite"></div>

                <div class="hg-collection-view is-active" data-collection-view="album">
                    <div class="hg-album__grid" data-album-grid aria-live="polite"></div>
                </div>

                <div class="hg-collection-view" data-collection-view="table" hidden>
                    <div class="hg-collection-table-wrap hg-collection-table-wrap--mobile">
                        <table id="hgCollectionTable" class="hg-collection-table" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Cal.</th>
                                    <th>Carta</th>
                                    <th>Cat.</th>
                                    <th>ID</th>
                                    <th>Tot.</th>
                                    <th>Cop.</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>

                <div class="hg-pagination" data-collection-pager aria-live="polite"></div>
            </section>

            <section class="hg-collection-tools hg-collection-tools--mobile" aria-label="Gestión de colección">
                <div class="hg-section-head">
                    <h3>Gestión</h3>
                    <p>Respaldo, borrado local y venta de copias.</p>
                </div>

                <div class="hg-import-export hg-import-export--mobile">
                    <button type="button" id="hgExportCollection">Exportar JSON</button>
                    <label class="hg-file-btn" for="hgImportFile">Importar JSON</label>
                    <input type="file" id="hgImportFile" accept="application/json,.json">
                    <button type="button" class="hg-danger-btn" id="hgResetCollection">Borrar colección</button>
                </div>

                <section class="hg-player-profile" aria-label="Perfil de combate">
                    <div>
                        <h3>Perfil de combate</h3>
                        <p>Nombre visible en combates y registros.</p>
                    </div>
                    <label class="hg-collection-select">
                        <span>Nombre del jugador</span>
                        <input type="text" maxlength="32" placeholder="Jugador" data-combat-profile-name>
                    </label>
                </section>

                <section class="hg-bulk-sell" aria-label="Venta de cartas por rareza">
                    <div>
                        <h3>Vender cartas</h3>
                        <p>Convierte una rareza en Remorias.</p>
                    </div>
                    <label for="hgBulkSellRarity">Rareza</label>
                    <select id="hgBulkSellRarity">
                        <option value="common">Común</option>
                        <option value="unusual">Inusual</option>
                        <option value="rare">Raro</option>
                        <option value="epic">Épico</option>
                        <option value="legendary">Legendario</option>
                        <option value="mythic">Mítico</option>
                        <option value="stigmatic">Estigm&aacute;tico</option>
                    </select>
                    <label class="hg-bulk-sell__keep">
                        <input type="checkbox" id="hgBulkSellKeepBest" checked>
                        <span>Conservar la mejor copia de cada carta</span>
                    </label>
                    <button type="button" class="hg-danger-btn" id="hgBulkSellButton">Vender cartas...</button>
                    <strong id="hgBulkSellPreview" class="hg-bulk-sell__preview" aria-live="polite"></strong>
                </section>
            </section>
        </section>

        <section class="hg-mobile-panel" data-mobile-panel="memory" aria-label="Rememoración de cartas">
            <section class="hg-workbench hg-workbench--mobile" aria-label="Recordar cartas">
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
        </section>

        <section class="hg-mobile-panel" data-mobile-panel="combat" aria-label="Combate de entrenamiento">
            <div class="hg-combat-screen-tabs" role="tablist" aria-label="Pantallas de combate">
                <button type="button" class="is-active" data-combat-screen-tab="battle">Combate</button>
                <button type="button" data-combat-screen-tab="loadout">Equipo</button>
            </div>

            <section class="hg-combat-loadout hg-combat-screen-panel" data-combat-screen="loadout" hidden aria-label="Equipos de combate">
                <div class="hg-section-head">
                    <h3>Preparar equipo</h3>
                    <p>Guarda hasta 5 equipos de 5 cartas.</p>
                </div>
                <label class="hg-collection-select">
                    <span>Equipo activo</span>
                    <select data-combat-team-select-mirror></select>
                </label>
                <label class="hg-collection-select">
                    <span>Nombre del equipo</span>
                    <input type="text" maxlength="40" placeholder="Equipo 1" data-combat-team-name>
                </label>
                <div class="hg-combat-team__slots" data-combat-team-slots aria-live="polite"></div>
                <div class="hg-combat-team__actions">
                    <button type="button" data-combat-auto-team>Auto</button>
                    <button type="button" class="hg-primary-btn" data-combat-save-team>Guardar</button>
                    <button type="button" data-combat-clear-team>Vaciar</button>
                </div>
                <div class="hg-combat-picker">
                    <div class="hg-combat-picker__head">
                        <strong>Cartas disponibles</strong>
                        <label class="hg-filter-check">
                            <input type="checkbox" data-combat-only-ready checked>
                            <span>Sólo no elegidas</span>
                        </label>
                        <label class="hg-collection-select hg-combat-sort">
                            <span>Orden</span>
                            <select data-combat-sort>
                                <option value="quality">Calidad %</option>
                                <option value="total">Total</option>
                                <option value="rarity">Rareza</option>
                                <option value="recent">Recientes</option>
                                <option value="name">Nombre</option>
                            </select>
                        </label>
                    </div>
                    <div class="hg-combat-picker__filters" aria-label="Filtros de cartas para equipo">
                        <label class="hg-collection-select">
                            <span>Rareza</span>
                            <select data-combat-rarity-filter>
                                <option value="all">Todas</option>
                                <option value="common">Común</option>
                                <option value="unusual">Inusual</option>
                                <option value="rare">Raro</option>
                                <option value="epic">Épico</option>
                                <option value="legendary">Legendario</option>
                                <option value="mythic">Mítico</option>
                                <option value="stigmatic">Estigm&aacute;tico</option>
                            </select>
                        </label>
                        <label class="hg-collection-select">
                            <span>Colección</span>
                            <select data-combat-type-filter></select>
                        </label>
                    </div>
                    <div class="hg-combat-card-list" data-combat-card-list aria-live="polite"></div>
                </div>
            </section>

            <section class="hg-combat-arena-shell hg-combat-screen-panel is-active" data-combat-screen="battle" aria-label="Modo entrenamiento">
                <div class="hg-combat-setup">
                    <div class="hg-combat-mode-tabs" aria-label="Tipo de combate">
                        <button type="button" class="is-active" data-combat-mode="training">Entrenamiento</button>
                        <button type="button" data-combat-mode="daily-boss">Jefe diario</button>
                        <button type="button" data-combat-mode="dungeon" disabled>Mazmorra</button>
                    </div>
                    <div class="hg-daily-boss-summary" data-daily-boss-summary hidden></div>
                    <label class="hg-collection-select" data-combat-difficulty-wrap>
                        <span>Rival</span>
                        <select data-combat-difficulty>
                            <option value="apprentice">Aprendiz</option>
                            <option value="hobbyist">Aficionado</option>
                            <option value="expert">Experto</option>
                            <option value="master">Maestro</option>
                            <option value="nemesis">N&eacute;mesis</option>
                        </select>
                    </label>
                    <div class="hg-combat-team-picker">
                        <label class="hg-collection-select">
                            <span>Equipo activo</span>
                            <select data-combat-team-select></select>
                        </label>
                        <div class="hg-combat-team-preview" data-combat-team-preview aria-live="polite"></div>
                    </div>
                    <button type="button" class="hg-primary-btn" data-combat-start>Iniciar combate</button>
                </div>

                <div class="hg-combat-stage" data-combat-stage>
                    <div class="hg-combat-screen">
                        <div class="hg-combat-field">
                            <div class="hg-combat-hud hg-combat-hud--enemy">
                                <strong data-combat-enemy-name>Enemigo</strong>
                                <div class="hg-combat-shields" data-combat-enemy-shields aria-label="Escudos"></div>
                                <div class="hg-combat-hp"><span data-combat-enemy-hp-bar></span></div>
                                <small data-combat-enemy-hp>PS 0 / 0</small>
                                <div class="hg-combat-stats">
                                    <span>ATQ <b data-combat-enemy-atk>0</b></span>
                                    <span>DEF <b data-combat-enemy-def>0</b></span>
                                </div>
                            </div>
                            <div class="hg-combat-card-stand hg-combat-card-stand--enemy" data-combat-enemy-card></div>
                            <div class="hg-combat-card-stand hg-combat-card-stand--player" data-combat-player-card></div>
                            <div class="hg-combat-hud hg-combat-hud--player">
                                <strong data-combat-player-name>Jugador</strong>
                                <div class="hg-combat-shields" data-combat-player-shields aria-label="Escudos"></div>
                                <div class="hg-combat-hp"><span data-combat-player-hp-bar></span></div>
                                <small data-combat-player-hp>PS 0 / 0</small>
                                <div class="hg-combat-stats">
                                    <span>ATQ <b data-combat-player-atk>0</b></span>
                                    <span>DEF <b data-combat-player-def>0</b></span>
                                </div>
                            </div>
                        </div>
                        <div class="hg-combat-command-panel">
                            <div class="hg-combat-message" data-combat-message>Elige 5 cartas y empieza un entrenamiento.</div>
                            <div class="hg-combat-actions" data-combat-actions>
                                <div class="hg-combat-command-view" data-combat-command-view="root">
                                    <button type="button" data-combat-command="actions" disabled>Acciones</button>
                                    <button type="button" data-combat-command="inventory" disabled>Inventario</button>
                                    <button type="button" data-combat-action="switch" disabled>Cambiar</button>
                                    <button type="button" data-combat-action="flee" disabled>Huir</button>
                                </div>
                                <div class="hg-combat-command-view hg-combat-command-view--submenu" data-combat-command-view="actions" hidden>
                                    <button type="button" disabled data-combat-extra-action-slot="1">Acción 1</button>
                                    <button type="button" data-combat-action="attack" disabled><span aria-hidden="true">✊</span> Atacar</button>
                                    <button type="button" data-combat-action="defend" disabled><span aria-hidden="true">🛡</span> Defender</button>
                                    <button type="button" disabled data-combat-extra-action-slot="2">Acción 2</button>
                                    <button type="button" disabled data-combat-extra-action-slot="3">Acción 3</button>
                                    <button type="button" data-combat-command-back>&lt; Volver</button>
                                </div>
                                <div class="hg-combat-command-view hg-combat-command-view--submenu" data-combat-command-view="inventory" hidden>
                                    <button type="button" disabled data-combat-inventory-slot="1">Item 1</button>
                                    <button type="button" disabled data-combat-inventory-slot="2">Item 2</button>
                                    <button type="button" disabled data-combat-inventory-slot="3">Item 3</button>
                                    <button type="button" disabled data-combat-inventory-slot="4">Item 4</button>
                                    <button type="button" disabled data-combat-inventory-slot="5">Item 5</button>
                                    <button type="button" data-combat-command-back>&lt; Volver</button>
                                </div>
                            </div>
                            <div class="hg-combat-bench" data-combat-bench hidden></div>
                        </div>
                    </div>
                </div>
                <div class="hg-combat-log" data-combat-log aria-live="polite"></div>
            </section>
        </section>

        <section class="hg-mobile-panel" data-mobile-panel="info" aria-label="Explicación del juego">
            <?php hg_gc_render_info_content('mobile'); ?>
        </section>
    </main>
</div>

<script src="/assets/js/game-cards.js?v=20260529-evo-fix-1" defer></script>
