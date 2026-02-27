<?php
setMetaFromPage("Arquetipos | Heaven's Gate", "Listado de arquetipos de personalidad.", null, 'website');
include("app/partials/main_nav_bar.php");
header('Content-Type: text/html; charset=utf-8');
if ($link) { mysqli_set_charset($link, "utf8mb4"); }

$query = "
    SELECT
        a.id AS arche_id,
        a.pretty_id AS arche_pretty_id,
        a.name AS arche_name,
        COALESCE(b.name, '') AS arche_origin,
        COUNT(DISTINCT c.id) AS arche_holders
    FROM dim_archetypes a
    LEFT JOIN dim_bibliographies b ON a.bibliography_id = b.id
    LEFT JOIN fact_characters c
        ON c.nature_id = a.id OR c.demeanor_id = a.id
    GROUP BY a.id, a.pretty_id, a.name, b.name
    ORDER BY a.name ASC
";
$result = mysqli_query($link, $query);

$archetypes = [];
while ($row = mysqli_fetch_assoc($result)) {
    $archetypes[] = $row;
}
mysqli_free_result($result);

function ensure_utf8($value) {
    if (is_string($value)) {
        if (function_exists('mb_check_encoding') && !mb_check_encoding($value, 'UTF-8')) {
            if (function_exists('mb_convert_encoding')) {
                return mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
            }
            if (function_exists('utf8_encode')) {
                return utf8_encode($value);
            }
        }
        return $value;
    }
    if (is_array($value)) {
        foreach ($value as $k => $v) {
            $value[$k] = ensure_utf8($v);
        }
        return $value;
    }
    return $value;
}

$archetypes = ensure_utf8($archetypes);
$pageSect = "Arquetipos de personalidad";
?>
<link rel="stylesheet" href="/assets/css/hg-docs.css">
<?php include_once("app/partials/datatable_assets.php"); ?>

<h2 class="docs-table-title">Arquetipos de personalidad</h2>

<div class="docs-table-wrap">
    <div class="docs-table-inner">
        <div class="dt-toolbar">
            <div class="left">
                <div class="ms-wrap ms-wrap--wide" id="filter-origin">
                    <div class="ms-btn" id="ms-toggle-origin" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false">
                        <span class="ms-label">Origen</span>
                        <span class="ms-summary" id="ms-summary-origin">Todos</span>
                    </div>
                    <div class="ms-panel" id="ms-panel-origin" aria-hidden="true">
                        <div id="ms-options-origin"></div>
                        <div class="ms-actions">
                            <button type="button" id="ms-select-all-origin">Todo</button>
                            <button type="button" id="ms-clear-origin">Limpiar</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="right" id="dt-search-slot"></div>
        </div>

        <table id="tabla-arquetipos" class="display docs-table">
            <thead>
                <tr>
                    <th>Arquetipo</th>
                    <th>Personajes</th>
                    <th>Origen</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<script>
$(document).ready(function () {
    const archetypes = <?= json_encode($archetypes, JSON_UNESCAPED_UNICODE) ?>;
    const tbody = $('#tabla-arquetipos tbody');

    archetypes.forEach(a => {
        const archeSlug = a.arche_pretty_id || a.arche_id;
        const nombre = `<a href="/rules/archetypes/${escapeHtml(archeSlug)}">${escapeHtml(a.arche_name)}</a>`;
        const origen = a.arche_origin ? escapeHtml(a.arche_origin) : '-';
        const holders = Number(a.arche_holders || 0);

        tbody.append(`<tr><td>${nombre}</td><td>${holders}</td><td>${origen}</td></tr>`);
    });

    const dt = $('#tabla-arquetipos').DataTable({
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        order: [[0, "asc"]],
        language: {
            search: "&#128269; Buscar: ",
            lengthMenu: "Mostrar _MENU_ arquetipos",
            info: "Mostrando _START_ a _END_ de _TOTAL_ arquetipos",
            infoEmpty: "No hay arquetipos disponibles",
            emptyTable: "No hay datos en la tabla",
            paginate: {
				first: "Primero",
				last: "&Uacute;ltimo",
				next: "&#9654;",
				previous: "&#9664;"
            }
        },
        initComplete: function(){
            $('#dt-search-slot').append($('#tabla-arquetipos_filter'));
            const $inp = $('#tabla-arquetipos_filter input');
            if ($inp.length) {
                const cs = window.getComputedStyle($inp[0]);
                const $btn = $('#ms-toggle-origin');
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
                    $(this).css({'outline':'none','border-color':'#3b82f6','box-shadow':'0 0 0 3px rgba(59,130,246,.18)'});
                }).on('blur', function(){
                    $(this).css({'border': cs.border,'box-shadow': 'none'});
                });
            }
        }
    });

    const originSet = new Set();
    archetypes.forEach(a => {
        originSet.add((a.arche_origin !== null && a.arche_origin !== undefined && String(a.arche_origin).trim() !== '') ? String(a.arche_origin).trim() : '-');
    });
    const originValues = sortValues(Array.from(originSet));

    function openPanel(){ $('#ms-panel-origin').show().attr('aria-hidden','false'); $('#ms-toggle-origin').attr('aria-expanded','true'); }
    function closePanel(){ $('#ms-panel-origin').hide().attr('aria-hidden','true'); $('#ms-toggle-origin').attr('aria-expanded','false'); }
    function togglePanel(){ $('#ms-panel-origin').is(':visible') ? closePanel() : openPanel(); }

    function getSelected(){
        const selected = $('#ms-options-origin input:checked').map(function(){ return $(this).val(); }).get();
        return selected.length ? selected : null;
    }

    function updateSummary(selected){
        const $summary = $('#ms-summary-origin');
        if (selected === null) { $summary.text('Todos'); return; }
        if (selected.length === 1) $summary.text(selected[0]);
        else $summary.text(selected.length + ' selecc.');
    }

    function applyFilters(){
        const selected = getSelected();
        updateSummary(selected);
        if (selected === null) {
            dt.column(2).search('', true, false);
        } else {
            const pat = '^(?:' + selected.map(s => escapeRegex(s)).join('|') + ')$';
            dt.column(2).search(pat, true, false);
        }
        dt.draw();
    }

    const $opts = $('#ms-options-origin');
    originValues.forEach(v => {
        const safe = escapeHtml(v);
        $opts.append(`
            <label class="ms-row">
                <input type="checkbox" value="${safe}" checked>
                <span>${safe}</span>
            </label>
        `);
    });

    $('#ms-toggle-origin').on('click', togglePanel);
    $('#ms-toggle-origin').on('keydown', (e)=>{ if(e.key==='Enter' || e.key===' ') { e.preventDefault(); togglePanel(); } });
    $opts.on('change', 'input', applyFilters);

    $('#ms-select-all-origin').on('click', function(){
        $opts.find('input').prop('checked', true);
        applyFilters();
    });
    $('#ms-clear-origin').on('click', function(){
        $opts.find('input').prop('checked', false);
        applyFilters();
    });

    $(document).on('click', function(e){
        if (!$(e.target).closest('#filter-origin').length) closePanel();
    });

    applyFilters();
});

function escapeHtml(text) {
    if (!text) return '';
    return text.replace(/[&<>"']/g, function (m) {
        return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m];
    });
}

function escapeRegex(text){
    return String(text).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function sortValues(values){
    return values.sort((a,b)=>{
        if (a === '-' && b !== '-') return 1;
        if (b === '-' && a !== '-') return -1;
        return a.localeCompare(b, 'es');
    });
}
</script>

