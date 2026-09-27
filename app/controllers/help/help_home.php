<?php
require_once __DIR__ . '/../../domains/help/queries.php';

setMetaFromPage(
    "Ayuda | Heaven's Gate",
    "Guías de uso y consulta para navegar por el archivo de Heaven's Gate.",
    null,
    'website'
);

$helpPages = hg_help_fetch_published_pages($link);

include("app/partials/main_nav_bar.php");
if (function_exists('hg_page_register_stylesheet')) {
    hg_page_register_stylesheet('/assets/css/hg-help.css');
}
?>

<main class="hg-help-shell">
    <header class="hg-help-hero">
        <p class="hg-help-kicker">Heaven's Gate</p>
        <h1>Ayuda</h1>
        <p class="hg-help-lead">Guías breves para orientarte por el archivo, encontrar información y utilizar las herramientas de la web.</p>
    </header>

    <section class="hg-help-section" aria-labelledby="help-guides-title">
        <div class="hg-help-section-head">
            <h2 id="help-guides-title">Manuales disponibles</h2>
            <p>La ayuda explica cómo usar la web. El trasfondo, los documentos de campaña y el material de juego siguen viviendo en sus secciones habituales.</p>
        </div>

        <div class="hg-help-card-grid">
            <?php if (!$helpPages): ?>
                <p>No hay manuales publicados todavía.</p>
            <?php else: ?>
                <?php foreach ($helpPages as $page): ?>
                    <a class="hg-help-card" href="/help/<?= rawurlencode((string)$page['slug']) ?>">
                        <span class="hg-help-card-label"><?= htmlspecialchars((string)$page['nav_label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                        <strong><?= htmlspecialchars((string)$page['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong>
                        <span><?= htmlspecialchars((string)$page['summary'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                        <span class="hg-help-card-link">Abrir guía &rarr;</span>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <aside class="hg-help-callout">
        <strong>Ayuda y documentación son cosas distintas.</strong>
        <p>Si buscas información del universo o textos de campaña, entra en <a href="/documents">Documentos</a>. Si necesitas saber cómo funciona la web, estás en el sitio correcto.</p>
    </aside>
</main>
