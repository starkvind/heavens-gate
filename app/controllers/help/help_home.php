<?php
setMetaFromPage(
    "Ayuda | Heaven's Gate",
    "Guías de uso y consulta para navegar por el archivo de Heaven's Gate.",
    null,
    'website'
);
include("app/partials/main_nav_bar.php");
if (function_exists('hg_page_register_stylesheet')) {
    hg_page_register_stylesheet('/assets/css/hg-help.css');
}
?>

<main class="help-shell">
    <header class="help-hero">
        <p class="help-kicker">Heaven's Gate</p>
        <h1>Ayuda</h1>
        <p class="help-lead">Guías breves para orientarte por el archivo, encontrar información y utilizar las herramientas de la web.</p>
    </header>

    <section class="help-section" aria-labelledby="help-guides-title">
        <div class="help-section-head">
            <h2 id="help-guides-title">Manuales disponibles</h2>
            <p>La ayuda explica cómo usar la web. El trasfondo, los documentos de campaña y el material de juego siguen viviendo en sus secciones habituales.</p>
        </div>

        <div class="help-card-grid">
            <a class="help-card" href="/help/getting-started">
                <span class="help-card-label">Manual básico</span>
                <strong>Empezar a usar Heaven's Gate</strong>
                <span>Portada, navegación, búsqueda, personajes, temporadas, cronología, reglas, poderes y herramientas.</span>
                <span class="help-card-link">Abrir guía &rarr;</span>
            </a>

            <a class="help-card" href="/help/forum-viewer">
                <span class="help-card-label">Partidas por foro</span>
                <strong>Visor de partidas por foro</strong>
                <span>Elegir capítulo, leer hilos largos, saltar entre mensajes, usar la tabla de contenidos y volver al foro.</span>
                <span class="help-card-link">Abrir guía &rarr;</span>
            </a>
        </div>
    </section>

    <section class="help-section" aria-labelledby="help-shortcuts-title">
        <div class="help-section-head">
            <h2 id="help-shortcuts-title">Accesos rápidos</h2>
        </div>
        <div class="help-shortcuts">
            <a href="/search">Buscar</a>
            <a href="/characters">Personajes</a>
            <a href="/seasons">Temporadas</a>
            <a href="/timeline">Línea temporal</a>
            <a href="/rules">Reglas</a>
            <a href="/powers">Poderes</a>
            <a href="/tools/forum-topic-viewer">Visor del foro</a>
        </div>
    </section>

    <aside class="help-callout">
        <strong>Ayuda y documentación son cosas distintas.</strong>
        <p>Si buscas información del universo o textos de campaña, entra en <a href="/documents">Documentos</a>. Si necesitas saber cómo funciona la web, estás en el sitio correcto.</p>
    </aside>
</main>
