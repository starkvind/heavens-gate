<?php

if (!function_exists('hg_gc_info_pack_rows')) {
    function hg_gc_info_pack_rows(): array
    {
        return [
            ['Mnemónico', '50', 'Cualquier carta activa.', '64 / 22 / 9 / 3.5 / 1.2 / 0.3'],
            ['Ecos', '90', 'Sólo comunes e inusuales.', '82 común, 18 inusual.'],
            ['Mágico', 'No tienda', 'Cualquier carta, con mejores probabilidades.', '20 / 38 / 24 / 11 / 5 / 2'],
            ['Personajes', '240', 'Sólo personajes.', 'Distribución normal.'],
            ['Linaje', '420', 'Personajes, tribus y auspicios.', '46 / 30 / 16 / 6 / 1.6 / 0.4'],
            ['Esencia', '300', 'Sistemas, tribus, auspicios y formas.', '52 / 29 / 13 / 4.2 / 1.4 / 0.4'],
            ['Arcano', '240', 'Poderes, dones, ritos, tótems y disciplinas.', 'Distribución normal.'],
            ['Crónica', '140', 'Crónicas, temporadas y episodios.', '58 / 26 / 11 / 3.8 / 1 / 0.2'],
            ['Reliquias', '160', 'Objetos, documentos y tótems.', '55 / 28 / 12 / 3.8 / 1 / 0.2'],
            ['Presagios', 'No tienda', 'Sólo raro o superior.', '70 raro, 21 épico, 7 legendario, 2 mítico.'],
            ['Gaiano', 'No tienda', 'Sólo épico, legendario o mítico.', '55 épico, 30 legendario, 15 mítico.'],
        ];
    }
}

if (!function_exists('hg_gc_info_rarity_rows')) {
    function hg_gc_info_rarity_rows(): array
    {
        return [
            ['common', 'Común', 'Blanco', '10-40', '10 Remoria'],
            ['unusual', 'Inusual', 'Verde', '30-60', '30 Remorias'],
            ['rare', 'Raro', 'Azul', '50-85', '80 Remorias'],
            ['epic', 'Épico', 'Rosa', '70-105', '250 Remorias'],
            ['legendary', 'Legendario', 'Naranja', '90-125', '750 Remorias'],
            ['mythic', 'Mítico', 'Morado', '115-160', '2000 Remorias'],
            ['stigmatic', 'Estigmático', 'Rojo sangre', '180-260', '0 Remorias'],
        ];
    }
}

if (!function_exists('hg_gc_info_mobile_cards')) {
    function hg_gc_info_mobile_cards(): array
    {
        return [
            ['Rarezas', 'Común, Inusual, Raro, Épico, Legendario, Mítico y Estigmático. Estigmático queda reservado para fuentes especiales como el Jefe diario; no sale en sobres ni por evolución normal.'],
            ['Atributos', 'Cada copia obtiene PS, ATQ y DEF aleatorios dentro de los límites de su rareza. Dos copias de la misma carta pueden despertar con valores distintos.'],
            ['Sobres', 'Cada sobre contiene 5 cartas. Hay sobres generales y sobres centrados en personajes, linajes, esencia, poderes, crónicas, reliquias o rarezas altas.'],
            ['Tienda', 'Compra sobres con Mnemones y objetos rituales con Remorias. El sobre mnemónico gratis tiene 3 usos diarios; los sobres no básicos comprables tienen cupo diario de 10.'],
            ['Mnemones', 'Son la moneda de sobres. Una colección nueva empieza con 500. Se obtienen en entrenamientos y rememorando cartas.'],
            ['Remorias', 'Son la moneda de progreso. Se obtienen desintegrando copias y se gastan en objetos rituales, evolución de rareza y mejora de atributos.'],
            ['Recordar', 'Puedes tener hasta 5 cartas rememorando. Generan Mnemones de forma pasiva y deben permanecer al menos 24 horas antes de volver.'],
            ['Evolución', 'Una copia con calidad mínima del 50% puede subir de rareza sacrificando copias compatibles, pagando Remorias y, desde Épico, usando objetos rituales.'],
            ['Mejora', 'La mejora de atributos consume hasta 5 copias compatibles para subir la calidad de una copia concreta.'],
            ['Colección', 'El álbum muestra todas las cartas disponibles en el Archivo, incluso las no obtenidas. Las cartas conseguidas pueden abrirse, revisarse y compararse por sus mejores copias.'],
            ['Combate', 'Prepara un equipo de 5 copias concretas. Los entrenamientos dan Mnemones y no destruyen cartas; el Jefe diario conserva PS, no permite huir y puede consumir el equipo derrotado.'],
            ['Progreso', 'La colección, los recursos, los equipos y el avance del Jefe diario se mantienen entre sesiones.'],
        ];
    }
}

if (!function_exists('hg_gc_render_info_content')) {
    function hg_gc_render_info_content(string $variant = 'desktop'): void
    {
        if ($variant === 'mobile') {
            hg_gc_render_mobile_info_content();
            return;
        }
        hg_gc_render_desktop_info_content();
    }
}

if (!function_exists('hg_gc_render_desktop_info_content')) {
    function hg_gc_render_desktop_info_content(): void
    {
        ?>
        <section class="hg-doc-section">
            <h3>Resumen</h3>
            <p>El juego permite abrir sobres, obtener cartas del Archivo de Mnemógeno, mejorar copias concretas, preparar equipos y combatir.</p>
            <p>La rareza pertenece a la carta. Los atributos <strong>PS</strong>, <strong>ATQ</strong> y <strong>DEF</strong> pertenecen a cada copia obtenida, por eso dos copias de la misma carta pueden tener valores distintos.</p>
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
                        <?php foreach (hg_gc_info_rarity_rows() as $row): ?>
                            <tr>
                                <td><span class="hg-rarity-label hg-rarity-label--<?= htmlspecialchars($row[0], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($row[1], ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td><code><?= htmlspecialchars($row[0], ENT_QUOTES, 'UTF-8') ?></code></td>
                                <td><?= htmlspecialchars($row[2], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($row[3], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($row[4], ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p>La rareza estigmática queda reservada para fuentes especiales como el Jefe diario y no entra en sobres ni evolución de rareza.</p>
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
                    <p>Ataque. Aumenta el daño que puede causar la copia en combate.</p>
                </article>
                <article class="hg-doc-panel">
                    <h4>DEF</h4>
                    <p>Defensa. Representa la capacidad defensiva de la copia obtenida.</p>
                </article>
            </div>
            <p>Cada copia tira PS, ATQ y DEF de forma independiente dentro del rango de su rareza. Una carta rara, por ejemplo, genera cada atributo entre 50 y 85.</p>
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
                        <?php foreach (hg_gc_info_pack_rows() as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row[0], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($row[1], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($row[2], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($row[3], ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p>Las distribuciones abreviadas siguen el orden: común, inusual, raro, épico, legendario y mítico. Los sobres marcados como “No tienda” existen en el sistema, pero no aparecen como compra directa en la tienda actual.</p>
        </section>

        <section class="hg-doc-section">
            <h3>Monedas y tienda</h3>
            <p>Los Mnemones son la moneda de sobres. Una colección nueva empieza con <strong>500 Mnemones</strong>. Se obtienen ganando entrenamientos y asignando copias a recordar.</p>
            <p>Las Remorias son la moneda de progreso. Se obtienen desintegrando cartas y se gastan en objetos rituales, evoluciones de rareza y mejoras de atributos.</p>
            <p>La tienda permite reclamar hasta <strong>3 sobres mnemónicos gratis al día</strong>. El sobre mnemónico básico comprado no tiene límite diario; los demás sobres comprables tienen un límite de <strong>10 unidades por tipo y día</strong>. Los objetos rituales disponibles son Vial de Ícaro, Orbe de Estigma y Retal de Babilonia.</p>
        </section>

        <section class="hg-doc-section">
            <h3>Progreso de cartas</h3>
            <p>Las copias pueden desintegrarse para obtener Remorias. Las copias marcadas como favoritas o asignadas a recordar no se pueden vender, evolucionar ni mejorar hasta retirarlas.</p>
            <p>La evolución de rareza requiere una copia con al menos <strong>50% de calidad</strong>, sacrificios suficientes, Remorias y, a partir de Épico, un objeto ritual. La rareza estigmática no forma parte de la evolución normal.</p>
            <p>La mejora de atributos consume hasta <strong>5 copias compatibles</strong> para aumentar la calidad de una copia concreta. La calidad máxima es 100%.</p>
        </section>

        <section class="hg-doc-section">
            <h3>Recordar</h3>
            <p>Puedes asignar hasta <strong>5 cartas</strong> a rememoración. Generan Mnemones de forma pasiva según su rareza y deben permanecer al menos <strong>24 horas</strong> antes de volver.</p>
        </section>

        <section class="hg-doc-section">
            <h3>Combate</h3>
            <p>El combate usa equipos de <strong>5 copias concretas</strong>. En entrenamiento, el rival depende de la dificultad elegida; ganar concede Mnemones y perder no destruye cartas.</p>
            <p>El Jefe diario usa una carta de personaje como enemigo estigmático, conserva sus PS entre intentos, no permite huir y destruye las cartas que derrota. Si cae todo el equipo, se pierden las 5 cartas del intento. Al derrotarlo se puede obtener la carta estigmática diaria y botín adicional.</p>
            <p>Las acciones disponibles actualmente son atacar, defender, cambiar, usar inventario cuando esté disponible y huir en entrenamientos. Los movimientos propios de cartas están planificados, pero todavía no forman parte del flujo activo.</p>
        </section>

        <section class="hg-doc-section">
            <h3>Progreso</h3>
            <p>La colección, los recursos, los equipos preparados y el avance del Jefe diario se mantienen entre sesiones.</p>
        </section>
        <?php
    }
}

if (!function_exists('hg_gc_render_mobile_info_content')) {
    function hg_gc_render_mobile_info_content(): void
    {
        ?>
        <div class="hg-doc-section">
            <h3>Cómo funciona</h3>
            <p>Abre sobres, consigue cartas del Archivo de Mnemógeno, usa Mnemones para reclamar sobres, Remorias para progresar cartas y equipos de 5 copias para combatir.</p>
        </div>

        <div class="hg-doc-grid">
            <?php foreach (hg_gc_info_mobile_cards() as $card): ?>
                <article>
                    <h4><?= htmlspecialchars($card[0], ENT_QUOTES, 'UTF-8') ?></h4>
                    <p><?= htmlspecialchars($card[1], ENT_QUOTES, 'UTF-8') ?></p>
                </article>
            <?php endforeach; ?>
        </div>
        <?php
    }
}
