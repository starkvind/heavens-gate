<?php
include("app/partials/main_nav_bar.php");

$isAdmin = !empty($hgCardsIsAdmin);
$gameCardsBasePath = $gameCardsBasePath ?? '/games/card-game';
$gameCardsCatalogUrl = $gameCardsCatalogUrl ?? '/api/game_cards.php';
$gameCardsScriptSrc = $gameCardsScriptSrc ?? '/assets/js/card-game/bootstrap/game-card-runtime.js?v=20260712-runtime-hotfix6';
$gameCardsStorageScope = $gameCardsStorageScope ?? 'prod';
$gameCardsBootScripts = $gameCardsBootScripts ?? [];
?>

<div class="hg-cards hg-cards--combat" data-view="combat" data-runtime-mode="hybrid" data-catalog-url="<?php echo htmlspecialchars((string)$gameCardsCatalogUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" data-is-admin="<?php echo $isAdmin ? '1' : '0'; ?>" data-base-path="<?php echo htmlspecialchars((string)$gameCardsBasePath, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" data-mobile-url="<?php echo htmlspecialchars((string)($gameCardsBasePath . '/mobile'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" data-storage-scope="<?php echo htmlspecialchars((string)$gameCardsStorageScope, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
    <nav class="hg-game-tabs" aria-label="Secciones del juego de cartas">
        <a data-game-tab="packs" href="<?php echo htmlspecialchars((string)$gameCardsBasePath, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">Sobres</a>
        <a data-game-tab="shop" href="<?php echo htmlspecialchars((string)($gameCardsBasePath . '#shop'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">Tienda</a>
        <a data-game-tab="collection" href="<?php echo htmlspecialchars((string)($gameCardsBasePath . '/collection'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">Colección</a>
        <a data-game-tab="memory" href="<?php echo htmlspecialchars((string)($gameCardsBasePath . '/collection#memory'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">Recuerdos</a>
        <a class="is-active" data-game-tab="combat" href="<?php echo htmlspecialchars((string)($gameCardsBasePath . '/combat'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">Combate</a>
        <a data-game-tab="info" href="<?php echo htmlspecialchars((string)($gameCardsBasePath . '/explanation'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">Información</a>
    </nav>

    <header class="hg-cards__titlebar">
        <h2>Combate de mnemógeno</h2>
    </header>

    <div class="hg-combat-screen-tabs" role="tablist" aria-label="Pantallas de combate">
        <button type="button" class="is-active" data-combat-screen-tab="battle">Combate</button>
        <button type="button" data-combat-screen-tab="loadout">Preparar equipo</button>
    </div>

    <section class="hg-combat-arena-shell hg-combat-screen-panel is-active" data-combat-screen="battle" aria-label="Modo entrenamiento">
        <div class="hg-section-head hg-combat-head">
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
        </div>

        <div class="hg-combat-stage" data-combat-stage>
            <div class="hg-combat-screen">
                <div class="hg-combat-field">
                    <div class="hg-combat-hud hg-combat-hud--enemy">
                        <strong data-combat-enemy-name>Enemigo</strong>
                        <div class="hg-combat-rival" data-combat-enemy-rival hidden>
                            <img src="" alt="" data-combat-enemy-rival-avatar>
                            <span>
                                <b data-combat-enemy-rival-name>Rival</b>
                                <small data-combat-enemy-rival-title>Rival de entrenamiento</small>
                            </span>
                        </div>
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

    <section class="hg-combat-loadout hg-combat-screen-panel" data-combat-screen="loadout" hidden aria-label="Equipos de combate">
        <div class="hg-section-head">
            <div>
                <h3>Preparar equipo</h3>
                <p>Guarda hasta 5 equipos. Cada equipo usa 5 copias concretas de tu colección.</p>
            </div>
        </div>

        <div class="hg-combat-loadout-controls">
            <div class="hg-combat-team-settings">
                <label class="hg-collection-select">
                    <span>Equipo activo</span>
                    <select data-combat-team-select-mirror></select>
                </label>
                <label class="hg-collection-select">
                    <span>Nombre del equipo</span>
                    <input type="text" maxlength="40" placeholder="Equipo 1" data-combat-team-name>
                </label>
            </div>
        </div>

        <div class="hg-combat-loadout__grid">
            <div class="hg-combat-team">
                <div class="hg-combat-team__slots" data-combat-team-slots aria-live="polite"></div>
                <div class="hg-combat-team__actions">
                    <button type="button" data-combat-auto-team>Autoequipo</button>
                    <button type="button" class="hg-primary-btn" data-combat-save-team>Guardar equipo</button>
                    <button type="button" data-combat-clear-team>Vaciar equipo</button>
                </div>
            </div>

            <div class="hg-combat-picker">
                <div class="hg-combat-picker__head">
                    <strong>Cartas disponibles</strong>
                    <label class="hg-filter-check">
                        <input type="checkbox" data-combat-only-ready checked>
                        <span>Sólo no elegidas</span>
                    </label>
                    <label class="hg-collection-select">
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
        </div>
    </section>
</div>

<?php foreach ($gameCardsBootScripts as $gameCardsBootScript): ?>
<script src="<?php echo htmlspecialchars((string)$gameCardsBootScript, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" defer></script>
<?php endforeach; ?>
<script src="<?php echo htmlspecialchars((string)$gameCardsScriptSrc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" defer></script>
