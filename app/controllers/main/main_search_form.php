<?php
setMetaFromPage('Busqueda | Heaven\'s Gate', 'Buscador de contenido de la campana.', null, 'website');
include_once(__DIR__ . '/../../helpers/search_catalog.php');
include('app/partials/main_nav_bar.php');

if (function_exists('hg_page_register_stylesheet')) {
    hg_page_register_stylesheet('/assets/css/hg-main.css');
    hg_page_register_stylesheet('/assets/css/pages/search.css');
}

$searchCatalog = hg_search_catalog($link);
?>

<div class="search-page">
    <h2>B&uacute;squeda</h2>
    <section class="search-panel">
        <p>Busca contenido del archivo por nombre, descripci&oacute;n o texto relacionado seg&uacute;n la secci&oacute;n elegida.</p>
        <form action="/search/results" method="get">
            <div class="search-form-grid">
                <div class="search-field">
                    <label for="search-q">Texto a buscar</label>
                    <input id="search-q" type="text" name="q" maxlength="80" minlength="3" />
                </div>
                <div class="search-field">
                    <label for="search-section">Secci&oacute;n</label>
                    <select id="search-section" name="section">
                        <?php foreach ($searchCatalog as $value => $config): ?>
                            <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"><?= $config['label_html'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="search-actions">
                <input type="submit" value="Buscar" class="boton1" />
            </div>
        </form>
        <div class="search-help">Incluye una opci&oacute;n global para consultar todas las secciones. M&iacute;nimo 3 letras.</div>
        <div class="search-recent" id="search-recent">
            <span class="search-recent-label">Recientes</span>
            <div class="search-recent-items" id="search-recent-items"></div>
        </div>
    </section>
</div>
<script>
(function () {
    const STORAGE_KEY = 'hg-search-recent';
    const root = document.getElementById('search-recent');
    const items = document.getElementById('search-recent-items');
    if (!root || !items) return;

    let recent = [];
    try {
        recent = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
    } catch (err) {
        recent = [];
    }

    if (!Array.isArray(recent) || recent.length === 0) return;

    recent.slice(0, 5).forEach(function (entry) {
        if (!entry || !entry.q || !entry.section) return;
        const a = document.createElement('a');
        a.className = 'search-recent-chip';
        a.href = '/search/results?q=' + encodeURIComponent(entry.q) + '&section=' + encodeURIComponent(entry.section);
        a.textContent = entry.q + ' · ' + entry.label;
        items.appendChild(a);
    });

    if (items.children.length > 0) {
        root.classList.add('is-ready');
    }
})();
</script>
