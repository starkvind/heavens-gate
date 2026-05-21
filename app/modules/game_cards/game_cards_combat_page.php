<?php
include("app/partials/main_nav_bar.php");

$isAdmin = !empty($hgCardsIsAdmin);
?>

<div class="hg-cards hg-cards--combat" data-view="combat" data-catalog-url="/api/game_cards.php" data-is-admin="<?php echo $isAdmin ? '1' : '0'; ?>">
    <nav class="hg-game-tabs" aria-label="Secciones del juego de cartas">
        <a href="/games/card-game">Sobres</a>
        <a href="/games/card-game#shop">Tienda</a>
        <a href="/games/card-game/collection">Colección</a>
        <a href="/games/card-game/collection#memory">Recuerdos</a>
        <a class="is-active" href="/games/card-game/combat">Combate</a>
        <a href="/games/card-game/explanation">Información</a>
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
                    <button type="button" data-combat-mode="daily-boss" disabled>Jefe diario</button>
                    <button type="button" data-combat-mode="dungeon" disabled>Mazmorra</button>
                </div>
                <label class="hg-collection-select">
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
                        <button type="button" data-combat-action="attack" disabled>Atacar</button>
                        <button type="button" data-combat-action="defend" disabled>Defender</button>
                        <button type="button" data-combat-action="switch" disabled>Cambiar</button>
                        <button type="button" data-combat-action="flee" disabled>Huir</button>
                    </div>
                    <div class="hg-combat-bench" data-combat-bench hidden></div>
                </div>
            </div>
        </div>

        <div class="hg-combat-log" data-combat-log aria-live="polite"></div>
    </section>

    <section class="hg-combat-loadout hg-combat-screen-panel" data-combat-screen="loadout" hidden aria-label="Equipos de combate">
        <div class="hg-section-head hg-section-head--split">
            <div>
                <h3>Preparar equipo</h3>
                <p>Guarda hasta 5 equipos. Cada equipo usa 5 copias concretas de tu colección.</p>
            </div>
            <label class="hg-collection-select">
                <span>Equipo activo</span>
                <select data-combat-team-select-mirror></select>
            </label>
        </div>

        <section class="hg-combat-profile" aria-label="Perfil de combate">
            <div>
                <h3>Perfil de combate</h3>
                <p>Guarda tu nombre y una carta favorita para futuros registros de combate.</p>
            </div>
            <label class="hg-collection-select">
                <span>Nombre</span>
                <input type="text" maxlength="32" placeholder="Jugador" data-combat-profile-name>
            </label>
            <label class="hg-collection-select">
                <span>Carta favorita</span>
                <select data-combat-profile-favorite></select>
            </label>
        </section>

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

<script src="/assets/js/game-cards.js" defer></script>
