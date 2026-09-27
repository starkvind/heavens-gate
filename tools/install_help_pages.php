<?php
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este instalador solo se ejecuta por CLI.\n");
    exit(1);
}

require_once __DIR__ . '/../app/helpers/db_connection.php';
mysqli_set_charset($link, 'utf8mb4');

$ddl = <<<'SQL'
CREATE TABLE IF NOT EXISTS fact_help_pages (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(160) NOT NULL,
    nav_label VARCHAR(80) NOT NULL DEFAULT 'Guía',
    title VARCHAR(255) NOT NULL,
    summary VARCHAR(500) NOT NULL DEFAULT '',
    lead TEXT NOT NULL,
    meta_description VARCHAR(255) NOT NULL DEFAULT '',
    content_html LONGTEXT NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fact_help_pages_slug (slug),
    KEY idx_fact_help_pages_public (is_published, sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

if (!$link->query($ddl)) {
    fwrite(STDERR, "Error creando fact_help_pages: " . $link->error . "\n");
    exit(1);
}

$pages = [
    [
        'slug' => 'getting-started',
        'nav_label' => 'Manual básico',
        'title' => 'Manual básico de uso',
        'summary' => 'Portada, navegación, búsqueda, personajes, temporadas, cronología, reglas, poderes y herramientas.',
        'lead' => 'La forma más rápida de entender dónde está cada cosa y cómo moverte por un archivo que lleva creciendo desde 2006.',
        'meta_description' => "Manual básico para navegar, buscar y consultar el archivo de Heaven's Gate.",
        'sort_order' => 10,
        'content_html' => <<<'HTML'
<nav class="hg-help-toc" aria-label="Contenido de la guía">
        <a href="/help/getting-started#inicio">Inicio</a>
        <a href="/help/getting-started#menu">Menú</a>
        <a href="/help/getting-started#buscar">Buscar</a>
        <a href="/help/getting-started#archivo">Consultar el archivo</a>
        <a href="/help/getting-started#reglas">Reglas y poderes</a>
        <a href="/help/getting-started#herramientas">Herramientas</a>
        <a href="/help/getting-started#movil">Móvil</a>
        <a href="/help/getting-started#preguntas">Preguntas rápidas</a>
    </nav>

    <section class="hg-help-section" id="inicio">
        <span class="hg-help-section-number">1</span>
        <div class="hg-help-section-head">
            <h2>Empieza por la portada</h2>
            <p>La portada funciona como punto de entrada al archivo. El buscador principal consulta todas las secciones y los bloques de Explora llevan directamente a los grandes dominios de contenido.</p>
        </div>
        <figure class="hg-help-figure">
            <img src="/img/help/help-basic-01-home.webp" alt="Portada de Heaven's Gate con buscador y tarjetas de exploración" loading="lazy" decoding="async">
            <figcaption>La portada reúne accesos rápidos a los grandes bloques del archivo.</figcaption>
        </figure>
        <div class="hg-help-note">
            <strong>Atajo útil.</strong>
            <p>Si ya sabes qué buscas, escribe el nombre de un personaje, capítulo, lugar, poder o documento en el buscador de portada. Para búsquedas más controladas utiliza la página <a href="/search">Buscar</a>.</p>
        </div>
    </section>

    <section class="hg-help-section" id="menu">
        <span class="hg-help-section-number">2</span>
        <div class="hg-help-section-head">
            <h2>Usa el menú como mapa de la web</h2>
            <p>El menú agrupa el contenido por función. No necesitas memorizar la estructura: abre la categoría que se parezca más a lo que estás intentando localizar.</p>
        </div>
        <figure class="hg-help-figure hg-help-figure--narrow">
            <img src="/img/help/help-basic-02-menu.webp" alt="Menú lateral de Heaven's Gate desplegado" loading="lazy" decoding="async">
            <figcaption>El menú lateral agrupa las secciones principales de Heaven's Gate.</figcaption>
        </figure>
        <div class="hg-help-definition-grid">
            <div><strong>Inicio</strong><span>Portada, noticias, búsqueda, estado de la web, ayuda y acceso al foro.</span></div>
            <div><strong>Biografías</strong><span>Personajes, tipos, grupos, sociedades y relaciones.</span></div>
            <div><strong>Archivo</strong><span>Tramas activas, temporadas, incisos, especiales y capítulos.</span></div>
            <div><strong>Trasfondo</strong><span>Documentos, línea temporal, mapas, banda sonora y galería.</span></div>
            <div><strong>Mecánicas</strong><span>Sistemas, rasgos, acciones, méritos, inventario, personalidades y maniobras.</span></div>
            <div><strong>Poderes</strong><span>Dones, rituales, tótems y disciplinas.</span></div>
            <div><strong>Herramientas</strong><span>Tiradados, utilidades del foro, generadores y otras herramientas.</span></div>
        </div>
    </section>

    <section class="hg-help-section" id="buscar">
        <span class="hg-help-section-number">3</span>
        <div class="hg-help-section-head">
            <h2>Buscar contenido</h2>
            <p>La búsqueda acepta un mínimo de tres caracteres. Puedes consultar todo el archivo o limitar los resultados a una sección concreta. La web conserva en tu navegador las búsquedas recientes para que puedas repetirlas rápidamente.</p>
        </div>
        <figure class="hg-help-figure">
            <img src="/img/help/help-basic-03-search.webp" alt="Buscador de Heaven's Gate con selector de sección" loading="lazy" decoding="async">
            <figcaption>La búsqueda permite limitar los resultados a una sección concreta y recuperar consultas recientes.</figcaption>
        </figure>
        <div class="hg-help-steps">
            <div class="hg-help-step"><span>1</span><div><strong>Escribe el término.</strong><p>Prueba primero con el nombre propio o concepto más específico.</p></div></div>
            <div class="hg-help-step"><span>2</span><div><strong>Elige una sección.</strong><p>Usa la búsqueda global si no sabes dónde vive la información.</p></div></div>
            <div class="hg-help-step"><span>3</span><div><strong>Abre el resultado.</strong><p>Las páginas relacionadas te permitirán seguir navegando desde esa entrada.</p></div></div>
        </div>
    </section>

    <section class="hg-help-section" id="archivo">
        <span class="hg-help-section-number">4</span>
        <div class="hg-help-section-head">
            <h2>Consultar la historia y sus personajes</h2>
            <p>Heaven's Gate relaciona personajes, capítulos, eventos, organizaciones y crónicas. Puedes entrar por cualquiera de esas puertas y continuar desde los enlaces internos.</p>
        </div>
        <div class="hg-help-figure-grid">
            <figure class="hg-help-figure">
                <img src="/img/help/help-basic-04-character.webp" alt="Biografía de Brisa del Sur" loading="lazy" decoding="async">
                <figcaption>Las biografías concentran identidad, trasfondo y enlaces relacionados del personaje.</figcaption>
            </figure>
            <figure class="hg-help-figure">
                <img src="/img/help/help-basic-05-chapter.webp" alt="Capítulo Armas de fuego con participantes, eventos y resumen" loading="lazy" decoding="async">
                <figcaption>Los capítulos reúnen participantes, eventos relacionados y el resumen de la sesión.</figcaption>
            </figure>
        </div>
        <div class="hg-help-definition-grid">
            <div><strong>Personajes</strong><span>La ficha reúne biografía, datos, afiliaciones y conexiones disponibles para cada figura.</span></div>
            <div><strong>Temporadas y capítulos</strong><span>Las temporadas ordenan los arcos. Los capítulos muestran participantes, eventos relacionados, resumen y navegación entre episodios.</span></div>
            <div><strong>Línea temporal</strong><span>Permite recorrer los acontecimientos del mundo y abrir cada evento como entrada independiente.</span></div>
            <div><strong>Organizaciones</strong><span>Clanes, manadas, sociedades y otros grupos se pueden consultar como entidades propias.</span></div>
            <div><strong>Documentos</strong><span>Textos de campaña, trasfondo y material narrativo que no pertenece a la ayuda de uso.</span></div>
            <div><strong>Mapas y multimedia</strong><span>Mapas, galería y banda sonora sirven como capas de consulta complementarias.</span></div>
        </div>
    </section>

    <section class="hg-help-section" id="reglas">
        <span class="hg-help-section-number">5</span>
        <div class="hg-help-section-head">
            <h2>Reglas, sistemas y poderes</h2>
            <p>La parte mecánica está separada del archivo narrativo para que pueda utilizarse como referencia rápida durante una partida.</p>
        </div>
        <div class="hg-help-actions">
            <a href="/systems"><strong>Sistemas</strong><span>Razas, tribus, auspicios, formas y otros detalles de cada sistema.</span></a>
            <a href="/rules"><strong>Reglas</strong><span>Rasgos, condiciones, acciones, maniobras, personalidades, méritos y defectos.</span></a>
            <a href="/powers"><strong>Poderes</strong><span>Dones, rituales, tótems y disciplinas con sus fichas de consulta.</span></a>
            <a href="/inventory"><strong>Inventario</strong><span>Objetos y elementos materiales registrados en el archivo.</span></a>
        </div>
    </section>

    <section class="hg-help-section" id="herramientas">
        <span class="hg-help-section-number">6</span>
        <div class="hg-help-section-head">
            <h2>Herramientas</h2>
            <p>Además del archivo, la web incorpora utilidades pensadas para jugar y preparar partidas.</p>
        </div>
        <div class="hg-help-actions">
            <a href="/tools/dice"><strong>Tiradados</strong><span>Realiza y conserva tiradas desde la web.</span></a>
            <a href="/tools/forum-avatar"><strong>Mensajes para foro</strong><span>Construye bloques <code>hg_avatar</code>, elige personaje y variante, previsualiza el resultado y copia el BBCode.</span></a>
            <a href="/tools/forum-topic-viewer"><strong>Visor del foro</strong><span>Lee temas de partida con navegación optimizada para hilos largos.</span></a>
            <a href="/tools/garou-name-generator"><strong>Generador Garou</strong><span>Genera nombres Garou para personajes y PNJ.</span></a>
        </div>
        <p class="hg-help-inline-link"><a href="/help/forum-viewer">Ver el manual completo del visor de partidas por foro &rarr;</a></p>
    </section>

    <section class="hg-help-section" id="movil">
        <span class="hg-help-section-number">7</span>
        <div class="hg-help-section-head">
            <h2>Usar Heaven's Gate en móvil</h2>
            <p>La vista móvil utiliza las mismas rutas y el mismo contenido. El botón Menú concentra la navegación y permite cambiar la apariencia. Cuando el navegador lo permite, Heaven's Gate también puede instalarse como aplicación web.</p>
        </div>
        <div class="hg-help-figure-grid">
            <figure class="hg-help-figure">
                <img src="/img/help/help-basic-06-mobile-menu.webp" alt="Menú de Heaven's Gate en móvil" loading="lazy" decoding="async">
                <figcaption>En móvil, las mismas áreas quedan agrupadas en un menú compacto.</figcaption>
            </figure>
            <figure class="hg-help-figure">
                <img src="/img/help/help-basic-07-mobile-pwa.webp" alt="Diálogo para añadir Heaven's Gate a la pantalla de inicio" loading="lazy" decoding="async">
                <figcaption>En navegadores compatibles, Heaven's Gate puede añadirse a la pantalla de inicio.</figcaption>
            </figure>
        </div>
        <div class="hg-help-note">
            <strong>iPhone y iPad.</strong>
            <p>Si no aparece un diálogo de instalación, utiliza Compartir y después «Añadir a pantalla de inicio».</p>
        </div>
    </section>

    <section class="hg-help-section" id="preguntas">
        <span class="hg-help-section-number">8</span>
        <div class="hg-help-section-head">
            <h2>Preguntas rápidas</h2>
        </div>
        <div class="hg-help-faq">
            <details>
                <summary>No sé dónde está algo.</summary>
                <p>Empieza por <a href="/search">Buscar</a> y utiliza la opción global. Si conoces el tipo de contenido, limita después la sección.</p>
            </details>
            <details>
                <summary>¿Documentos y Ayuda son lo mismo?</summary>
                <p>No. <a href="/documents">Documentos</a> contiene material del universo y de campaña. <a href="/help">Ayuda</a> explica cómo utilizar la web y sus herramientas.</p>
            </details>
            <details>
                <summary>¿La web tiene una versión distinta para móvil?</summary>
                <p>Existe una vista móvil de compatibilidad, pero utiliza las mismas URLs y el mismo contenido. Puedes cambiar de vista desde el propio interfaz móvil.</p>
            </details>
            <details>
                <summary>¿Puedo instalar Heaven's Gate como una app?</summary>
                <p>Sí, cuando tu navegador ofrece instalación de aplicaciones web. En iOS se utiliza la opción «Añadir a pantalla de inicio».</p>
            </details>
        </div>
    </section>

    <footer class="hg-help-article-footer">
        <a href="/help">&larr; Volver a Ayuda</a>
    </footer>
HTML
    ],
    [
        'slug' => 'forum-viewer',
        'nav_label' => 'Partidas por foro',
        'title' => 'Visor de partidas por foro',
        'summary' => 'Elegir capítulo, leer hilos largos, saltar entre mensajes, usar la tabla de contenidos y volver al foro.',
        'lead' => 'Lectura rápida, navegación por mensajes y acceso directo al foro.',
        'meta_description' => "Guía de uso del visor de partidas por foro de Heaven's Gate.",
        'sort_order' => 20,
        'content_html' => <<<'HTML'
<aside class="hg-help-callout">
        <strong>Importante</strong>
        <p>El visor no sustituye al foro. El contenido se consulta directamente desde el foro y se presenta en Heaven's Gate con una interfaz de lectura más cómoda. Para responder o editar mensajes se sigue utilizando el foro original.</p>
    </aside>

    <nav class="hg-help-toc" aria-label="Contenido de la guía">
        <a href="/help/forum-viewer#antes">Antes de empezar</a>
        <a href="/help/forum-viewer#capitulo">Elegir capítulo</a>
        <a href="/help/forum-viewer#leer">Leer y moverse</a>
        <a href="/help/forum-viewer#toc">Tabla de contenidos</a>
        <a href="/help/forum-viewer#flujo">Flujo recomendado</a>
        <a href="/help/forum-viewer#faq">Preguntas rápidas</a>
    </nav>

    <section class="hg-help-section" id="antes">
        <span class="hg-help-section-number">1</span>
        <div class="hg-help-section-head">
            <h2>Antes de empezar</h2>
            <p>Un capítulo solo aparece en el visor cuando Dirección lo ha dado de alta y lo ha asociado a su tema del foro. Si el tema existe pero no aparece aquí, debe registrarse primero.</p>
        </div>
        <div class="hg-help-definition-grid">
            <div><strong>Contenido autorizado</strong><span>El visor muestra únicamente capítulos y temas registrados para esta herramienta.</span></div>
            <div><strong>Lectura directa</strong><span>Los mensajes se leen desde el foro al cargar la página; no hace falta copiarlos a Heaven's Gate.</span></div>
            <div><strong>Orden cronológico</strong><span>El hilo se presenta desde el mensaje más antiguo al más reciente.</span></div>
            <div><strong>Navegación rápida</strong><span>Puedes saltar entre mensajes sin recorrer manualmente páginas completas mediante scroll.</span></div>
        </div>
    </section>

    <section class="hg-help-section" id="capitulo">
        <span class="hg-help-section-number">2</span>
        <div class="hg-help-section-head">
            <h2>Elegir un capítulo</h2>
        </div>
        <figure class="hg-help-figure">
            <img src="/img/help/help-forum-01-selector.webp" alt="Selector de capítulos del visor de partidas por foro" loading="lazy" decoding="async">
            <figcaption>«Cambiar capítulo» abre el catálogo de temas registrados para el visor.</figcaption>
        </figure>
        <div class="hg-help-steps">
            <div class="hg-help-step"><span>1</span><div><strong>Pulsa «Cambiar capítulo».</strong><p>El catálogo permanece plegado mientras lees para no robar espacio al hilo.</p></div></div>
            <div class="hg-help-step"><span>2</span><div><strong>Busca el capítulo.</strong><p>Puedes localizarlo por título, episodio o agrupación. Solo aparecen los capítulos registrados por Dirección.</p></div></div>
            <div class="hg-help-step"><span>3</span><div><strong>Abre el tema.</strong><p>La cabecera mostrará episodio, temporada y agrupación cuando esos datos estén vinculados al capítulo.</p></div></div>
        </div>
        <div class="hg-help-note">
            <strong>Si no encuentras un capítulo</strong>
            <p>No significa necesariamente que el tema no exista. Comprueba primero que el DJ lo haya dado de alta y vinculado correctamente.</p>
        </div>
    </section>

    <section class="hg-help-section" id="leer">
        <span class="hg-help-section-number">3</span>
        <div class="hg-help-section-head">
            <h2>Leer y moverse por un hilo</h2>
            <p>El control flotante permanece visible mientras lees y actúa como centro de navegación.</p>
        </div>
        <figure class="hg-help-figure">
            <img src="/img/help/help-forum-02-reader.webp" alt="Mensaje del visor de partidas con controles de navegación" loading="lazy" decoding="async">
            <figcaption>La lectura mantiene visibles las acciones del mensaje y el navegador inferior.</figcaption>
        </figure>
        <div class="hg-help-definition-grid">
            <div><strong>Flecha izquierda</strong><span>Salta al mensaje anterior.</span></div>
            <div><strong>Indicador central</strong><span>Muestra tu posición actual y el autor del mensaje visible. Al pulsarlo abre la tabla de contenidos.</span></div>
            <div><strong>Flecha derecha</strong><span>Salta al mensaje siguiente.</span></div>
            <div><strong>Ir al último mensaje</strong><span>Desde la cabecera puedes saltar directamente a la intervención más reciente.</span></div>
        </div>
        <div class="hg-help-note">
            <strong>Acciones de cada mensaje</strong>
            <p>«Abrir en el foro» lleva a la intervención original para responder, citar o editar. «Copiar mensaje» copia su contenido; la cabecera ofrece además una acción para copiar el hilo completo.</p>
        </div>
    </section>

    <section class="hg-help-section" id="toc">
        <span class="hg-help-section-number">4</span>
        <div class="hg-help-section-head">
            <h2>Tabla de contenidos flotante</h2>
            <p>Pulsa el indicador central del navegador para abrir la tabla sin reducir el ancho del texto.</p>
        </div>
        <figure class="hg-help-figure">
            <img src="/img/help/help-forum-03-toc.webp" alt="Tabla de contenidos flotante del visor de partidas por foro" loading="lazy" decoding="async">
            <figcaption>La tabla de contenidos permite saltar a una intervención concreta sin perder el hilo.</figcaption>
        </figure>
        <div class="hg-help-steps">
            <div class="hg-help-step"><span>1</span><div><strong>Se abre sobre la lectura.</strong><p>El texto permanece debajo y conserva todo su ancho.</p></div></div>
            <div class="hg-help-step"><span>2</span><div><strong>Empieza por lo reciente.</strong><p>En hilos largos, la vista compacta muestra primero los últimos mensajes.</p></div></div>
            <div class="hg-help-step"><span>3</span><div><strong>Pulsa «Ver todos».</strong><p>Expande la lista completa cuando necesitas localizar una intervención antigua.</p></div></div>
            <div class="hg-help-step"><span>4</span><div><strong>Elige un mensaje.</strong><p>El visor salta a esa intervención y cierra automáticamente la tabla.</p></div></div>
            <div class="hg-help-step"><span>5</span><div><strong>Cierra sin saltar.</strong><p>Pulsa de nuevo el control central, haz clic fuera de la ventana o utiliza Esc.</p></div></div>
        </div>
    </section>

    <section class="hg-help-section" id="flujo">
        <span class="hg-help-section-number">5</span>
        <div class="hg-help-section-head">
            <h2>Flujo recomendado</h2>
        </div>
        <div class="hg-help-flow-grid">
            <div>
                <strong>Para jugadores</strong>
                <p>Abre el visor, elige capítulo y usa «Ir al último mensaje» si estás al día. Navega con las flechas o abre la tabla central cuando necesites localizar una intervención concreta. Para continuar la partida, abre el mensaje original en el foro.</p>
            </div>
            <div>
                <strong>Para Dirección</strong>
                <p>Da de alta el capítulo y vincúlalo al tema correcto antes de compartir el visor. Después no hace falta copiar las respuestas: el contenido se consulta directamente desde el foro.</p>
            </div>
        </div>
    </section>

    <section class="hg-help-section" id="faq">
        <span class="hg-help-section-number">6</span>
        <div class="hg-help-section-head">
            <h2>Preguntas rápidas</h2>
        </div>
        <div class="hg-help-faq">
            <details>
                <summary>¿Por qué no aparece mi capítulo?</summary>
                <p>Porque todavía no está registrado, está desactivado o no se ha vinculado al tema correcto. Debe revisarlo el DJ.</p>
            </details>
            <details>
                <summary>¿Tengo que publicar también en Heaven's Gate?</summary>
                <p>No. La partida sigue escribiéndose en el foro. El visor es una capa de lectura y navegación.</p>
            </details>
            <details>
                <summary>¿Cuándo aparecen las respuestas nuevas?</summary>
                <p>El visor consulta el foro cuando se carga la página. Si sabes que hay una respuesta nueva y no la ves, recarga el capítulo.</p>
            </details>
            <details>
                <summary>¿Puedo responder desde el visor?</summary>
                <p>No. Utiliza la acción «Abrir en el foro» del mensaje para continuar desde la intervención original.</p>
            </details>
            <details>
                <summary>¿Por qué los mensajes están de antiguo a reciente?</summary>
                <p>Porque así se conserva la lectura natural. Para retomar rápidamente una partida puedes saltar al último mensaje o utilizar la tabla de contenidos.</p>
            </details>
        </div>
    </section>

    <footer class="hg-help-article-footer">
        <a href="/tools/forum-topic-viewer">Abrir el visor</a>
        <a href="/help">&larr; Volver a Ayuda</a>
    </footer>
HTML
    ],
];

$check = $link->prepare("SELECT id FROM fact_help_pages WHERE slug = ? LIMIT 1");
$insert = $link->prepare(
    "INSERT INTO fact_help_pages
     (slug, nav_label, title, summary, lead, meta_description, content_html, sort_order, is_published)
     VALUES (?,?,?,?,?,?,?,?,1)"
);
if (!$check || !$insert) {
    fwrite(STDERR, "No se pudieron preparar las consultas de seed.\n");
    exit(1);
}

$created = 0;
$kept = 0;
foreach ($pages as $page) {
    $slug = $page['slug'];
    $check->bind_param('s', $slug);
    $check->execute();
    $existing = $check->get_result()->fetch_assoc();
    if ($existing) {
        $kept++;
        continue;
    }

    $insert->bind_param(
        'sssssssi',
        $page['slug'],
        $page['nav_label'],
        $page['title'],
        $page['summary'],
        $page['lead'],
        $page['meta_description'],
        $page['content_html'],
        $page['sort_order']
    );
    if (!$insert->execute()) {
        fwrite(STDERR, "Error creando {$page['slug']}: " . $insert->error . "\n");
        exit(1);
    }
    $created++;
}
$check->close();
$insert->close();

echo "fact_help_pages lista. Creadas: {$created}. Ya existentes: {$kept}.\n";
echo "/help/getting-started\n/help/forum-viewer\n";
