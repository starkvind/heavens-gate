<?php
require_once(__DIR__ . '/../../domains/chapters/queries.php');

setMetaFromPage("Tabla de episodios | Heaven's Gate", "Listado completo de episodios y capítulos de Heaven's Gate.", null, 'website');
include("app/partials/main_nav_bar.php");
header('Content-Type: text/html; charset=utf-8');
if ($link) { mysqli_set_charset($link, "utf8mb4"); }

$hasSeasonChronicle = hg_chapters_column_exists($link, 'dim_seasons', 'chronicle_id');
$rows = hg_chapters_fetch_table_rows($link) ?? [];
$pageSect = "Capítulos";
?>
<?php if (function_exists('hg_page_register_stylesheet')) { hg_page_register_stylesheet('/assets/css/hg-docs.css'); } else { ?><link rel="stylesheet" href="/assets/css/hg-docs.css"><?php } ?>
<?php include_once("app/partials/datatable_assets.php"); ?>
<?php if (function_exists('hg_page_register_stylesheet')) { hg_page_register_stylesheet('/assets/css/pages/legacy/controllers-chapters-chapter_table.css'); } else { ?><link rel="stylesheet" href="/assets/css/pages/legacy/controllers-chapters-chapter_table.css"><?php } ?>

<h2 class="docs-table-title">Tabla de episodios</h2>

<div class="docs-table-wrap">
    <div class="docs-table-inner">
        <div class="dt-toolbar">
            <div class="left">
                <div class="ms-wrap" id="filter-kind">
                    <div class="ms-btn" id="ms-toggle-kind" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false">
                        <span class="ms-label">Tipo de temporada</span>
                        <span class="ms-summary" id="ms-summary-kind">Todos</span>
                    </div>
                    <div class="ms-panel" id="ms-panel-kind" aria-hidden="true">
                        <div id="ms-options-kind"></div>
                        <div class="ms-actions">
                            <button type="button" id="ms-select-all-kind">Todo</button>
                            <button type="button" id="ms-clear-kind">Limpiar</button>
                        </div>
                    </div>
                </div>
                <?php if ($hasSeasonChronicle): ?>
                <div class="ms-wrap ms-wrap--wide" id="filter-chronicle">
                    <div class="ms-btn" id="ms-toggle-chronicle" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false">
                        <span class="ms-label">Cr&oacute;nica</span>
                        <span class="ms-summary" id="ms-summary-chronicle">Todas</span>
                    </div>
                    <div class="ms-panel" id="ms-panel-chronicle" aria-hidden="true">
                        <div id="ms-options-chronicle"></div>
                        <div class="ms-actions">
                            <button type="button" id="ms-select-all-chronicle">Todo</button>
                            <button type="button" id="ms-clear-chronicle">Limpiar</button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <div class="right" id="dt-search-slot"></div>
        </div>
        <table id="tabla-capitulos" class="display docs-table">
            <thead>
                <tr>
                    <th>Episodio</th>
                    <th>N&ordm;</th>
                    <th>Temporada</th>
                    <th>Cr&oacute;nica</th>
                    <th>Descripci&oacute;n</th>
                    <th>N&ordm; personajes</th>
                    <th>Tipo de temporada</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<script>
$(document).ready(function () {
    const rows = <?= json_encode($rows, JSON_UNESCAPED_UNICODE) ?>;
    const tbody = $('#tabla-capitulos tbody');

    function escapeHtml(value) {
        return String(value || '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#39;');
    }

    function seasonLabel(row) {
        const kind = String(row.season_kind || 'temporada').trim();
        const number = Number(row.season_number || 0);
        const name = String(row.season_name || '').trim();
        if (kind === 'historia_personal') return name ? `Historia personal - ${name}` : 'Historia personal';
        if (kind === 'especial') return name ? `Especial - ${name}` : 'Especial';
        if (kind === 'inciso') {
            let incisoNum = number;
            if (incisoNum >= 100 && incisoNum < 200) incisoNum -= 100;
            const prefix = `Inciso ${incisoNum > 0 ? incisoNum : '?'}`;
            return name ? `${prefix} - ${name}` : prefix;
        }
        const prefix = `T${number > 0 ? number : '?'}`;
        return name ? `${prefix} - ${name}` : prefix;
    }

    function chronicleLabel(row) {
        return String(row.chronicle_name || '').trim() || '-';
    }

    function seasonKindLabel(row) {
        const kind = String(row.season_kind || 'temporada').trim();
        if (kind === 'historia_personal') return 'Historia personal';
        if (kind === 'especial') return 'Especial';
        if (kind === 'inciso') return 'Inciso';
        return 'Temporada';
    }

    function descriptionLength(row) {
        const source = String(row.chapter_synopsis || '');
        const plain = source
            .replace(/<[^>]*>/g, ' ')
            .replace(/&nbsp;/gi, ' ')
            .replace(/\s+/g, ' ')
            .trim();
        return plain.length;
    }

    rows.forEach(r => {
        const chapterSlug = r.chapter_pretty_id || r.chapter_id;
        const chapterName = `<a href="/chapters/${escapeHtml(chapterSlug)}">${escapeHtml(r.chapter_name || 'Sin titulo')}</a>`;
        const chapterNumber = Number(r.chapter_number || 0);
        const seasonNumber = Number(r.season_number || 0);
        const seasonSlug = r.season_pretty_id || r.season_id || '';
        const seasonText = seasonLabel(r);
        const seasonKind = String(r.season_kind || 'temporada').trim();
        const seasonKindText = seasonKindLabel(r);
        const seasonSort = `${String(r.season_sort_order || 999999).padStart(6, '0')}-${String(r.season_number || 0).padStart(4, '0')}-${escapeHtml(seasonText)}`;
        const seasonCell = seasonSlug
            ? `<a href="/seasons/${escapeHtml(seasonSlug)}">${escapeHtml(seasonText)}</a>`
            : escapeHtml(seasonText);
        const episodeCode = seasonKind === 'temporada'
            ? `${seasonNumber > 0 ? seasonNumber : '?'}x${String(chapterNumber > 0 ? chapterNumber : 0).padStart(2, '0')}`
            : `${chapterNumber > 0 ? chapterNumber : 0}`;
        const episodeSort = `${String(seasonNumber > 0 ? seasonNumber : 0).padStart(4, '0')}-${String(chapterNumber > 0 ? chapterNumber : 0).padStart(4, '0')}`;
        const chronicleText = chronicleLabel(r);
        const descLen = descriptionLength(r);
        const characterCount = Number(r.character_count || 0);

        tbody.append(`<tr>
            <td>${chapterName}</td>
            <td data-order="${episodeSort}">${escapeHtml(episodeCode)}</td>
            <td data-order="${seasonSort}">${seasonCell}</td>
            <td>${escapeHtml(chronicleText)}</td>
            <td data-order="${descLen}">${descLen}</td>
            <td data-order="${characterCount}">${characterCount}</td>
            <td>${escapeHtml(seasonKindText)}</td>
        </tr>`);
    });

    const dt = $('#tabla-capitulos').DataTable({
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        order: [[1, "asc"], [0, "asc"]],
        columnDefs: [
            { targets: 6, visible: false, searchable: true }
        ],
        language: {
            search: "&#128269; Buscar:&nbsp;",
            lengthMenu: "Mostrar _MENU_ episodios",
            info: "Mostrando _START_ a _END_ de _TOTAL_ episodios",
            infoEmpty: "No hay episodios disponibles",
            emptyTable: "No hay datos en la tabla",
            paginate: {
                first: "Primero",
                last: "&Uacute;ltimo",
                next: "&#9654;",
                previous: "&#9664;"
            }
        },
        initComplete: function () {
            $('#dt-search-slot').append($('#tabla-capitulos_filter'));
        }
    });

    const filters = [
        { key: 'kind', value: seasonKindLabel, column: 6, allLabel: 'Todos', dependsOn: [] }
    ];
    <?php if ($hasSeasonChronicle): ?>
    filters.push({ key: 'chronicle', value: chronicleLabel, column: 3, allLabel: 'Todas', dependsOn: ['kind'] });
    <?php endif; ?>

    HGDataTableFilters.create({
        table: dt,
        rows: rows,
        filters: filters
    });
});
</script>