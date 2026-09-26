<?php
?>
<section class="hg-mobile-section hg-mobile-search">
    <h1><?= $isResults ? 'Resultados' : 'Buscar' ?></h1>
    <form action="/search/results" method="get" class="hg-mobile-search-form">
        <label for="mobile-search-q">Texto</label>
        <input id="mobile-search-q" type="search" name="q" maxlength="80" minlength="3" value="<?= hg_mobile_search_h($query) ?>" placeholder="Nombre, descripción, texto">

        <label for="mobile-search-section">Sección</label>
        <select id="mobile-search-section" name="section">
            <?php foreach ($catalog as $value => $config): ?>
                <option value="<?= hg_mobile_search_h($value) ?>"<?= $value === $sectionKey ? ' selected' : '' ?>><?= hg_mobile_search_html_label($config) ?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit">Buscar</button>
    </form>
    <?php if (!$isResults): ?>
        <p class="hg-mobile-muted">Busca en personajes, crónicas, temporadas, capítulos, documentos, reglas, poderes y sistemas. Mínimo 3 letras.</p>
        <div class="hg-mobile-search-recent"
             id="search-recent"
             data-mobile-search-recent
             data-current-q="<?= hg_mobile_search_h($query) ?>"
             data-current-section="<?= hg_mobile_search_h($sectionKey) ?>"
             data-current-label="<?= hg_mobile_search_h(hg_mobile_search_text_label($sectionConfig)) ?>"
             data-store-current="<?= ($isResults && $query !== '' && $queryLength > 2) ? '1' : '0' ?>"
             data-skip-current="<?= $isResults ? '1' : '0' ?>">
            <span>Recientes</span>
            <div data-mobile-search-recent-items></div>
        </div>
    <?php endif; ?>
</section>

<?php if ($isResults): ?>
<section class="hg-mobile-section hg-mobile-search-results">
    <?php if ($query !== '' && $queryLength > 2): ?>
        <div class="hg-mobile-search-meta"><span><?= count($rows) ?> resultados</span><span><?= hg_mobile_search_html_label($sectionConfig) ?></span></div>
        <?php if ($sectionKey !== 'all'): ?><a class="hg-mobile-pill-link" href="/search/results?q=<?= rawurlencode($query) ?>&section=all">Ver mezcla global</a><?php endif; ?>
        <?php if ($sectionKey === 'all' && !empty($sectionBreakdown)): ?>
            <div class="hg-mobile-search-sections">
                <?php foreach ($sectionBreakdown as $breakKey => $breakMeta): ?>
                    <a href="/search/results?q=<?= rawurlencode($query) ?>&section=<?= rawurlencode($breakKey) ?>"><strong><?= $breakMeta['label_html'] ?></strong><span><?= (int)$breakMeta['count'] ?></span></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="hg-mobile-search-recent"
             id="search-recent"
             data-mobile-search-recent
             data-current-q="<?= hg_mobile_search_h($query) ?>"
             data-current-section="<?= hg_mobile_search_h($sectionKey) ?>"
             data-current-label="<?= hg_mobile_search_h(hg_mobile_search_text_label($sectionConfig)) ?>"
             data-store-current="<?= ($isResults && $query !== '' && $queryLength > 2) ? '1' : '0' ?>"
             data-skip-current="<?= $isResults ? '1' : '0' ?>">
            <span>Recientes</span>
            <div data-mobile-search-recent-items></div>
        </div>
        <?php if (!empty($rows)): ?>
            <div class="hg-mobile-card-list">
                <?php foreach ($rows as $row): ?>
                    <?php $href = hg_mobile_search_result_url($link, (string)$row['route'], (int)$row['id']); ?>
                    <a class="hg-mobile-card hg-mobile-search-card" href="<?= hg_mobile_search_h($href) ?>">
                        <span class="hg-mobile-tag"><?= $row['section_label_html'] ?></span>
                        <strong><?= hg_mobile_search_highlight($row['title'] !== '' ? $row['title'] : ('Elemento #' . $row['id']), $terms) ?></strong>
                        <p><?= hg_mobile_search_highlight($row['excerpt'] !== '' ? $row['excerpt'] : 'Sin descripción breve disponible.', $terms) ?></p>
                        <?php if (($row['secondary'] ?? '') !== ''): ?><small><?= hg_mobile_search_highlight($row['secondary'], $terms) ?></small><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="hg-mobile-empty">Sin coincidencias para &quot;<?= hg_mobile_search_h($query) ?>&quot;.</div>
        <?php endif; ?>
    <?php elseif ($query === ''): ?>
        <div class="hg-mobile-empty">Introduce un criterio de busqueda.</div>
    <?php else: ?>
        <div class="hg-mobile-empty">La busqueda debe tener al menos 3 letras.</div>
    <?php endif; ?>
</section>
<?php endif; ?>

