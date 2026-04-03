<?php
setMetaFromPage("Tabla de episodios | Heaven's Gate", "Listado completo de episodios y capítulos de Heaven's Gate.", null, 'website');
include("app/partials/main_nav_bar.php");
header('Content-Type: text/html; charset=utf-8');
if ($link) { mysqli_set_charset($link, "utf8mb4"); }
if (!function_exists('hg_ct_h')) {
    function hg_ct_h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('hg_ct_col_exists')) {
    function hg_ct_col_exists(mysqli $link, string $table, string $column): bool {
        static $cache = [];
        $key = $table . ':' . $column;
        if (isset($cache[$key])) return $cache[$key];
        $ok = false;
        if ($st = $link->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?")) {
            $st->bind_param('ss', $table, $column);
            $st->execute();
            $st->bind_result($count);
            $st->fetch();
            $st->close();
            $ok = ((int)$count > 0);
        }
        $cache[$key] = $ok;
        return $ok;
    }
}
if (!function_exists('hg_ct_season_label')) {
    function hg_ct_season_label(string $kind, int $number, string $name): string {
        $kind = trim($kind);
        $name = trim($name);
        if ($kind === 'historia_personal') return $name !== '' ? ('Historia personal - ' . $name) : 'Historia personal';
        if ($kind === 'especial') return $name !== '' ? ('Especial - ' . $name) : 'Especial';
        if ($kind === 'inciso') {
            $incisoNum = $number;
            if ($incisoNum >= 100 && $incisoNum < 200) $incisoNum -= 100;
            $prefix = 'Inciso ' . ($incisoNum > 0 ? $incisoNum : '?');
            return $name !== '' ? ($prefix . ' - ' . $name) : $prefix;
        }
        $prefix = 'T' . ($number > 0 ? $number : '?');
        return $name !== '' ? ($prefix . ' - ' . $name) : $prefix;
    }
}

$hasSeasonChronicle = hg_ct_col_exists($link, 'dim_seasons', 'chronicle_id');
$rows = [];
$selectChronicle = $hasSeasonChronicle
    ? ",
        ch.id AS chronicle_id,
        ch.pretty_id AS chronicle_pretty_id,
        ch.name AS chronicle_name"
    : ",
        NULL AS chronicle_id,
        NULL AS chronicle_pretty_id,
        NULL AS chronicle_name";
$joinChronicle = $hasSeasonChronicle ? " LEFT JOIN dim_chronicles ch ON ch.id = s.chronicle_id" : "";
$sql = "
    SELECT
        c.id AS chapter_id,
        c.pretty_id AS chapter_pretty_id,
        c.name AS chapter_name,
        c.chapter_number,
        c.played_date,
        s.id AS season_id,
        s.pretty_id AS season_pretty_id,
        s.name AS season_name,
        s.season_number,
        COALESCE(s.season_kind, 'temporada') AS season_kind,
        COALESCE(s.sort_order, 999999) AS season_sort_order
        {$selectChronicle}
    FROM dim_chapters c
    LEFT JOIN dim_seasons s ON s.id = c.season_id
    {$joinChronicle}
    ORDER BY
        COALESCE(s.sort_order, 999999) ASC,
        s.season_number ASC,
        c.chapter_number ASC,
        c.name ASC
";

$result = mysqli_query($link, $sql);
if ($result instanceof mysqli_result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    mysqli_free_result($result);
}

$pageSect = "Capítulos";
?>
<link rel="stylesheet" href="/assets/css/hg-docs.css">
<?php include_once("app/partials/datatable_assets.php"); ?>

<h2 class="docs-table-title">Tabla de episodios</h2>

<div class="docs-table-wrap">
    <div class="docs-table-inner">
        <div class="dt-toolbar">
            <div class="left">
                <div class="ms-wrap ms-wrap--wide" id="filter-season">
                    <div class="ms-btn" id="ms-toggle-season" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false">
                        <span class="ms-label">Temporada</span>
                        <span class="ms-summary" id="ms-summary-season">Todas</span>
                    </div>
                    <div class="ms-panel" id="ms-panel-season" aria-hidden="true">
                        <div id="ms-options-season"></div>
                        <div class="ms-actions">
                            <button type="button" id="ms-select-all-season">Todo</button>
                            <button type="button" id="ms-clear-season">Limpiar</button>
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
                    <th>T&iacute;tulo episodio</th>
                    <th>N&ordm;</th>
                    <th>Temporada</th>
                    <th>Fecha de juego</th>
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

    function sortValues(values) {
        return values.sort((a, b) => {
            if (a === '-' && b !== '-') return 1;
            if (b === '-' && a !== '-') return -1;
            return a.localeCompare(b, 'es');
        });
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

    rows.forEach(r => {
        const chapterSlug = r.chapter_pretty_id || r.chapter_id;
        const chapterName = `<a href="/chapters/${escapeHtml(chapterSlug)}">${escapeHtml(r.chapter_name || 'Sin titulo')}</a>`;
        const chapterNumber = Number(r.chapter_number || 0);
        const seasonSlug = r.season_pretty_id || r.season_id || '';
        const seasonText = seasonLabel(r);
        const seasonSort = `${String(r.season_sort_order || 999999).padStart(6, '0')}-${String(r.season_number || 0).padStart(4, '0')}-${escapeHtml(seasonText)}`;
        const seasonCell = seasonSlug
            ? `<a href="/seasons/${escapeHtml(seasonSlug)}">${escapeHtml(seasonText)}</a>`
            : escapeHtml(seasonText);
        let played = '-';
        let playedSort = '9999-99-99';
        if (r.played_date && r.played_date !== '0000-00-00') {
            const parts = String(r.played_date).split('-');
            if (parts.length === 3) played = `${parts[2]}-${parts[1]}-${parts[0]}`;
            else played = escapeHtml(r.played_date);
            playedSort = escapeHtml(r.played_date);
        }

        tbody.append(`<tr>
            <td>${chapterName}</td>
            <td data-order="${chapterNumber || 0}">${chapterNumber || '-'}</td>
            <td data-order="${seasonSort}">${seasonCell}</td>
            <td data-order="${playedSort}">${played}</td>
        </tr>`);
    });

    const dt = $('#tabla-capitulos').DataTable({
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        order: [[2, "asc"], [1, "asc"], [0, "asc"]],
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
            const $inp = $('#tabla-capitulos_filter input');
            if ($inp.length) {
                const cs = window.getComputedStyle($inp[0]);
                ['#ms-toggle-season', '#ms-toggle-chronicle'].forEach(sel => {
                    const $btn = $(sel);
                    if (!$btn.length) return;
                    $btn.css({
                        'font-family': cs.fontFamily,
                        'font-size': cs.fontSize,
                        'font-weight': cs.fontWeight,
                        'line-height': cs.lineHeight,
                        'padding': cs.padding,
                        'border': cs.border,
                        'border-radius': cs.borderRadius,
                        'background-color': cs.backgroundColor,
                        'color': cs.color,
                        'box-sizing': cs.boxSizing,
                        'height': cs.height,
                        'min-height': cs.height
                    });

                    $btn.on('focus', function(){
                        $(this).css({
                            'outline':'none',
                            'border-color':'#3b82f6',
                            'box-shadow':'0 0 0 3px rgba(59,130,246,.18)'
                        });
                    }).on('blur', function(){
                        $(this).css({
                            'border': cs.border,
                            'box-shadow': 'none'
                        });
                    });
                });
            }
        }
    });

    const seasonSet = new Set();
    const chronicleSet = new Set();
    rows.forEach(r => {
        seasonSet.add(seasonLabel(r) || '-');
        chronicleSet.add(chronicleLabel(r));
    });

    const filterConfigs = [
        {
            key: 'season',
            allLabel: 'Todas',
            noneLabel: 'Ninguna',
            values: sortValues(Array.from(seasonSet)),
            getValue: seasonLabel
        }
    ];
    <?php if ($hasSeasonChronicle): ?>
    filterConfigs.push({
        key: 'chronicle',
        allLabel: 'Todas',
        noneLabel: 'Ninguna',
        values: sortValues(Array.from(chronicleSet)),
        getValue: chronicleLabel
    });
    <?php endif; ?>

    function openPanel(key){ $('#ms-panel-' + key).show().attr('aria-hidden','false'); $('#ms-toggle-' + key).attr('aria-expanded','true'); }
    function closePanel(key){ $('#ms-panel-' + key).hide().attr('aria-hidden','true'); $('#ms-toggle-' + key).attr('aria-expanded','false'); }
    function togglePanel(key){ $('#ms-panel-' + key).is(':visible') ? closePanel(key) : openPanel(key); }
    function getSelected(cfg){
        const selected = $('#ms-options-' + cfg.key + ' input:checked').map(function(){ return $(this).val(); }).get();
        if (selected.length === cfg.values.length) return null;
        return selected;
    }
    function updateSummary(cfg, selected){
        const $summary = $('#ms-summary-' + cfg.key);
        if (selected === null) { $summary.text(cfg.allLabel); return; }
        if (selected.length === 0) { $summary.text(cfg.noneLabel); return; }
        if (selected.length === 1) $summary.text(selected[0]);
        else $summary.text(selected.length + ' selecc.');
    }
    function applyFilters(){
        filterConfigs.forEach(cfg => updateSummary(cfg, getSelected(cfg)));
        dt.draw();
    }

    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
        if (settings.nTable.id !== 'tabla-capitulos') return true;
        let ok = true;
        filterConfigs.forEach(cfg => {
            const selected = getSelected(cfg);
            if (selected === null) return;
            const row = rows[dataIndex] || {};
            if (selected.length === 0) {
                ok = false;
                return;
            }
            const value = cfg.getValue(row);
            if (!selected.includes(value)) ok = false;
        });
        return ok;
    });

    filterConfigs.forEach(cfg => {
        const $opts = $('#ms-options-' + cfg.key);
        cfg.values.forEach(v => {
            const safe = escapeHtml(v);
            $opts.append(`
                <label class="ms-row">
                    <input type="checkbox" value="${safe}" checked>
                    <span>${safe}</span>
                </label>
            `);
        });

        $('#ms-toggle-' + cfg.key).on('click', () => togglePanel(cfg.key));
        $('#ms-toggle-' + cfg.key).on('keydown', (e)=>{ if(e.key==='Enter' || e.key===' ') { e.preventDefault(); togglePanel(cfg.key); } });
        $opts.on('change', 'input', applyFilters);
        $('#ms-select-all-' + cfg.key).on('click', function(){
            $opts.find('input').prop('checked', true);
            applyFilters();
        });
        $('#ms-clear-' + cfg.key).on('click', function(){
            $opts.find('input').prop('checked', false);
            applyFilters();
        });
    });

    $(document).on('click', function(e){
        filterConfigs.forEach(cfg => {
            if (!$(e.target).closest('#filter-' + cfg.key).length) closePanel(cfg.key);
        });
    });

    applyFilters();
});
</script>
