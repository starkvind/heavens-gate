<?php
setMetaFromPage(
    "Manual básico de uso | Ayuda | Heaven's Gate",
    "Manual básico para navegar, buscar y consultar el archivo de Heaven's Gate.",
    null,
    'article'
);
include("app/partials/main_nav_bar.php");
if (function_exists('hg_page_register_stylesheet')) {
    hg_page_register_stylesheet('/assets/css/hg-help.css');
}
?>

<main class="hg-help-shell help-article">
    <header class="hg-help-hero">
        <p class="hg-help-kicker"><a href="/help">Ayuda</a></p>
        <h1>Manual básico de uso</h1>
        <p class="hg-help-lead">La forma más rápida de entender dónde está cada cosa y cómo moverte por un archivo que lleva creciendo desde 2006.</p>
    </header>

    <nav class="hg-help-toc" aria-label="Contenido de la guía">
        <a href="#inicio">Inicio</a>
        <a href="#menu">Menú</a>
        <a href="#buscar">Buscar</a>
        <a href="#archivo">Consultar el archivo</a>
        <a href="#reglas">Reglas y poderes</a>
        <a href="#herramientas">Herramientas</a>
        <a href="#movil">Móvil</a>
        <a href="#preguntas">Preguntas rápidas</a>
    </nav>

    <section class="hg-help-section" id="inicio">
        <span class="hg-help-section-number">1</span>
        <div class="hg-help-section-head">
            <h2>Empieza por la portada</h2>
            <p>La portada funciona como punto de entrada al archivo. El buscador principal consulta todas las secciones y los bloques de Explora llevan directamente a los grandes dominios de contenido.</p>
        </div>
        <!-- Captura futura: portada completa con buscador y bloque Explora. -->
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
        <!-- Captura futura: menú lateral con varias categorías desplegadas. -->
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
        <!-- Captura futura: buscador avanzado con selector de sección y búsquedas recientes. -->
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
        <!-- Captura futura: una biografía completa y un capítulo con participantes/eventos. -->
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
        <!-- Captura futura: menú móvil y bloque de instalación PWA. -->
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
</main>
