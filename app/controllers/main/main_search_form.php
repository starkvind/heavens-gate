<?php
setMetaFromPage('Busqueda | Heaven\'s Gate', 'Buscador de contenido de la campana.', null, 'website');
include_once(__DIR__ . '/../../helpers/search_catalog.php');
include('app/partials/main_nav_bar.php');

$searchCatalog = hg_search_catalog($link);
?>
<link rel="stylesheet" href="/assets/css/hg-main.css">
<style>
.search-page {
    max-width: 760px;
    margin: 0 auto;
    text-align: left;
}

.search-panel {
    border: 1px solid #000088;
    background: #05014e;
    padding: 18px;
}

.search-panel p {
    margin: 0 0 14px;
    color: #d5defe;
}

.search-form-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.8fr) minmax(220px, 1fr);
    gap: 12px;
    align-items: end;
}

.search-field label {
    display: block;
    margin-bottom: 5px;
    color: #99ffff;
    font-weight: bold;
}

.search-field input,
.search-field select {
    width: 100%;
    box-sizing: border-box;
    padding: 7px 8px;
    font-size: 11px;
}

.search-actions {
    margin-top: 14px;
    display: flex;
    justify-content: flex-end;
}

.search-actions .boton1 {
    width: auto;
    min-width: 110px;
    padding: 7px 14px;
}

.search-help {
    margin-top: 12px;
    color: #a9bff7;
    font-size: 10px;
}

.search-recent {
    margin-top: 16px;
    display: none;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
}

.search-recent.is-ready {
    display: flex;
}

.search-recent-label {
    color: #99ffff;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}

.search-recent-items {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.search-recent-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 8px;
    border: 1px solid rgba(0, 120, 220, 0.55);
    background: rgba(0, 30, 80, 0.22);
    color: #cfe0ff;
    text-decoration: none;
    font-size: 10px;
}

.search-recent-chip:hover {
    background: rgba(0, 40, 95, 0.38);
    border-color: rgba(0, 150, 255, 0.75);
}

@media (max-width: 720px) {
    .search-form-grid {
        grid-template-columns: 1fr;
    }

    .search-actions {
        justify-content: stretch;
    }

    .search-actions .boton1 {
        width: 100%;
    }
}
</style>

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
