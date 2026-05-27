<?php
include("app/partials/main_nav_bar.php");
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
        <p class="hg-cards__intro">Guía de rarezas, sobres, atributos, Mnemones, Remorias y criterios del seed de cartas.</p>
    </header>

    <section class="hg-doc-section">
        <h3>Resumen</h3>
        <p>El juego permite abrir sobres, obtener cartas basadas en entidades existentes de la web y conservar la colección en el navegador mediante <code>localStorage</code>. No hay cuenta de usuario y no se guarda progreso del jugador en servidor.</p>
        <p>La rareza pertenece al catálogo maestro de la carta. Los atributos <strong>PS</strong>, <strong>ATQ</strong> y <strong>DEF</strong> pertenecen a cada copia obtenida, por eso dos copias de la misma carta pueden tener valores distintos.</p>
    </section>

    <section class="hg-doc-section">
        <h3>Rarezas</h3>
        <div class="hg-doc-table-wrap">
            <table class="hg-doc-table">
                <thead>
                    <tr>
                        <th>Rareza</th>
                        <th>Clave</th>
                        <th>Color</th>
                        <th>Rango PS / ATQ / DEF</th>
                        <th>Desintegrar</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td><span class="hg-rarity-label hg-rarity-label--common">Común</span></td><td><code>common</code></td><td>Blanco</td><td>10-40</td><td>1 Remoria</td></tr>
                    <tr><td><span class="hg-rarity-label hg-rarity-label--unusual">Inusual</span></td><td><code>unusual</code></td><td>Verde</td><td>30-60</td><td>3 Remorias</td></tr>
                    <tr><td><span class="hg-rarity-label hg-rarity-label--rare">Raro</span></td><td><code>rare</code></td><td>Azul</td><td>50-85</td><td>8 Remorias</td></tr>
                    <tr><td><span class="hg-rarity-label hg-rarity-label--epic">Épico</span></td><td><code>epic</code></td><td>Rosa</td><td>70-105</td><td>25 Remorias</td></tr>
                    <tr><td><span class="hg-rarity-label hg-rarity-label--legendary">Legendario</span></td><td><code>legendary</code></td><td>Naranja</td><td>90-125</td><td>75 Remorias</td></tr>
                    <tr><td><span class="hg-rarity-label hg-rarity-label--mythic">Mítico</span></td><td><code>mythic</code></td><td>Morado</td><td>115-155</td><td>200 Remorias</td></tr>
                    <tr><td><span class="hg-rarity-label hg-rarity-label--stigmatic">Estigm&aacute;tico</span></td><td><code>stigmatic</code></td><td>Rojo sangre</td><td>180-260</td><td>0 Remorias</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="hg-doc-section">
        <h3>Atributos</h3>
        <div class="hg-doc-grid">
            <article class="hg-doc-panel">
                <h4>PS</h4>
                <p>Puntos de salud. Representan la resistencia general de esa copia concreta.</p>
            </article>
            <article class="hg-doc-panel">
                <h4>ATQ</h4>
                <p>Ataque. En la base de datos se mantiene como <code>atk</code>, pero se muestra como ATQ.</p>
            </article>
            <article class="hg-doc-panel">
                <h4>DEF</h4>
                <p>Defensa. Representa la capacidad defensiva de la copia obtenida.</p>
            </article>
        </div>
        <p>Cada copia tira PS, ATQ y DEF de forma independiente dentro del rango de la rareza. Una carta rara, por ejemplo, genera cada atributo entre 50 y 85.</p>
    </section>

    <section class="hg-doc-section">
        <h3>Sobres y precios</h3>
        <div class="hg-doc-table-wrap">
            <table class="hg-doc-table">
                <thead>
                    <tr>
                        <th>Sobre</th>
                        <th>Precio</th>
                        <th>Contenido</th>
                        <th>Distribución</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td>Mnemónico gratis</td><td>0</td><td>Cualquier carta activa. Se reclama en tienda hasta 3 al dia.</td><td>64 / 22 / 9 / 3.5 / 1.2 / 0.3</td></tr>
                    <tr><td>Mnemónico</td><td>50</td><td>Cualquier carta activa.</td><td>64 / 22 / 9 / 3.5 / 1.2 / 0.3</td></tr>
                    <tr><td>Ecos</td><td>90</td><td>Sólo comunes e inusuales.</td><td>82 común, 18 inusual.</td></tr>
                    <tr><td>Mágico</td><td>No tienda</td><td>Cualquier carta, con mejores probabilidades.</td><td>20 / 38 / 24 / 11 / 5 / 2</td></tr>
                    <tr><td>Personajes</td><td>240</td><td>Sólo personajes.</td><td>Distribución normal.</td></tr>
                    <tr><td>Linaje</td><td>420</td><td>Personajes con mejores probabilidades.</td><td>46 / 30 / 16 / 6 / 1.6 / 0.4</td></tr>
                    <tr><td>Arcano</td><td>240</td><td>Poderes, dones, ritos, tótems y disciplinas.</td><td>Distribución normal.</td></tr>
                    <tr><td>Crónica</td><td>140</td><td>Crónicas, temporadas y episodios.</td><td>58 / 26 / 11 / 3.8 / 1 / 0.2</td></tr>
                    <tr><td>Reliquias</td><td>160</td><td>Objetos, documentos y tótems.</td><td>55 / 28 / 12 / 3.8 / 1 / 0.2</td></tr>
                    <tr><td>Presagios</td><td>No tienda</td><td>Sólo raro o superior.</td><td>70 raro, 21 épico, 7 legendario, 2 mítico.</td></tr>
                    <tr><td>Gaiano</td><td>No tienda</td><td>Sólo épico, legendario o mítico.</td><td>55 épico, 30 legendario, 15 mítico.</td></tr>
                </tbody>
            </table>
        </div>
        <p>Las distribuciones abreviadas siguen el orden: común, inusual, raro, épico, legendario y mítico. La rareza estigmática queda reservada para fuentes especiales como el Jefe diario y no entra en sobres ni evolución de rareza.</p>
    </section>

    <section class="hg-doc-section">
        <h3>Mnemones</h3>
        <p>Los Mnemones son la moneda de sobres. Una colección nueva empieza con <strong>500 Mnemones</strong>. Se obtienen combatiendo y asignando copias a recordar.</p>
        <p>Las Remorias son la moneda de progreso. Se obtienen desintegrando cartas y se gastan en objetos rituales, evoluciones y mejoras de atributos.</p>
        <p>El sobre mnemónico gratis se reclama desde la tienda con un cupo diario de <strong>3 unidades</strong>. Los sobres no básicos comprables tienen un límite diario de <strong>10 unidades por tipo</strong>.</p>
    </section>

    <section class="hg-doc-section">
        <h3>Criterio del seed</h3>
        <p>El script convierte entidades existentes en cartas. Si la carta ya existe, actualiza sus datos y recalcula la rareza.</p>
        <div class="hg-doc-table-wrap">
            <table class="hg-doc-table">
                <thead>
                    <tr>
                        <th>Origen</th>
                        <th>Criterio de rareza</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td>Personajes</td><td>Suman puntos por rango, por <code>character_kind = pj</code> y por número de relaciones entre personajes. 0-1 común, 2-3 inusual, 4-5 raro, 6-7 épico, 8-9 legendario, 10+ mítico.</td></tr>
                    <tr><td>Objetos, dones, ritos y disciplinas</td><td>Usan nivel/rango: 0-1 común, 2 inusual, 3 raro, 4 épico, 5 legendario, 6+ mítico.</td></tr>
                    <tr><td>Tótems</td><td>Usan coste: 0-2 común, 3-4 inusual, 5 raro, 6-7 épico, 8-9 legendario, 10+ mítico.</td></tr>
                    <tr><td>Episodios</td><td>Comunes por defecto. Los múltiplos de 10 son inusuales.</td></tr>
                    <tr><td>Temporadas</td><td><code>season_kind = temporada</code> es raro; el resto, inusual.</td></tr>
                    <tr><td>Crónicas y documentos</td><td>Crónicas raras. Documentos comunes.</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="hg-doc-section">
        <h3>Persistencia</h3>
        <p>El progreso se guarda en <code>localStorage</code> con la clave <code>hg_card_collection_v2</code>. Ahí viven la colección, Mnemones, Remorias e inventarios. El estado diario de la tienda se guarda en <code>hg_card_shop_state_v2</code>. El catálogo maestro se carga desde <code>/api/game_cards.php</code>.</p>
    </section>
</div>
