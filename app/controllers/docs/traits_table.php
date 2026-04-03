<?php
setMetaFromPage("Rasgos | Heaven's Gate", "Listado de rasgos y habilidades.", null, 'website');
include_once(__DIR__ . '/../../helpers/public_response.php');
header('Content-Type: text/html; charset=utf-8');

if (!$link) {
    hg_public_log_error('traits_table', 'missing DB connection');
    hg_public_render_error(
        'Rasgos no disponibles',
        'No se pudo cargar este listado en este momento.',
        500,
        true
    );
    return;
}

mysqli_set_charset($link, "utf8mb4");
include("app/partials/main_nav_bar.php");

if (!function_exists('h')) {
    function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('sanitize_int_csv')) {
    function sanitize_int_csv($csv){
        $csv = (string)$csv;
        if (trim($csv) === '') return '';
        $parts = preg_split('/\s*,\s*/', trim($csv));
        $ints = [];
        foreach ($parts as $p) {
            if ($p === '') continue;
            if (preg_match('/^\d+$/', $p)) $ints[] = (string)(int)$p;
        }
        $ints = array_values(array_unique($ints));
        return implode(',', $ints);
    }
}

// Mismo criterio que bio_table.php: si no viene variable global, excluir 2,7
$excludeChronicles = isset($excludeChronicles) ? sanitize_int_csv($excludeChronicles) : '2,7';
$whereChron = ($excludeChronicles !== '') ? "p.chronicle_id NOT IN ($excludeChronicles)" : "1=1";

// Cargar rasgos con la query indicada
$query = "
    select
        nh.id as trait_id,
        nh.pretty_id as trait_pretty_id,
        nh.name as trait_name,
        nh.kind as trait_category,
        SUBSTRING(nh.classification, 5) as trait_subcategory,
        COALESCE(nb.name, '') as trait_origin,
        COUNT(DISTINCT p.id) as trait_holders
    from dim_traits nh
        left join dim_bibliographies nb on nh.bibliography_id = nb.id
        left join bridge_characters_traits bt on bt.trait_id = nh.id and bt.value >= 1
        left join fact_characters p on p.id = bt.character_id and $whereChron
    group by nh.id, nh.pretty_id, nh.name, nh.kind, nh.classification, nb.name
    order by
        CASE
            when nh.kind = 'Atributos' then 0
            when nh.kind = 'Talentos' then 1
            when nh.kind = 'Técnicas' or nh.kind = 'Tecnicas' then 2
            when nh.kind = 'Conocimientos' then 3
            when nh.kind = 'Trasfondos' then 4
            else 9999
        END ASC,
        nh.classification ASC,
        nh.id ASC
";
$result = mysqli_query($link, $query);
if (!$result) {
    $err = mysqli_error($link);
    if (stripos($err, "Unknown column 'nh.kind'") !== false || stripos($err, "Unknown column `nh`.`kind`") !== false) {
        $query = str_replace("nh.kind", "nh.tipo", $query);
        $result = mysqli_query($link, $query);
    }
}

$rasgos = [];
$isResult = ($result instanceof mysqli_result);
if ($result && $isResult) {
    while ($row = mysqli_fetch_assoc($result)) {
        $rasgos[] = $row;
    }
    mysqli_free_result($result);
} else {
    $err = mysqli_error($link);
    hg_public_log_error('traits_table', 'query failed: ' . $err);
    hg_public_render_error('Rasgos no disponibles', 'No se pudo cargar este listado en este momento.');
    return;
}

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

$rasgos = ensure_utf8($rasgos);

$pageSect = "Rasgos";
?>
<link rel="stylesheet" href="/assets/css/hg-docs.css">
<?php include_once("app/partials/datatable_assets.php"); ?>

<h2 class="docs-table-title">Rasgos</h2>

<div class="docs-table-wrap">
    <div class="docs-table-inner">
        <div class="dt-toolbar">
            <div class="left">
                <div class="ms-wrap" id="filter-type">
                    <div class="ms-btn" id="ms-toggle-type" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false">
                        <span class="ms-label">Tipo</span>
                        <span class="ms-summary" id="ms-summary-type">Todos</span>
                    </div>
                    <div class="ms-panel" id="ms-panel-type" aria-hidden="true">
                        <div id="ms-options-type"></div>
                        <div class="ms-actions">
                            <button type="button" id="ms-select-all-type">Todo</button>
                            <button type="button" id="ms-clear-type">Limpiar</button>
                        </div>
                    </div>
                </div>
                <div class="ms-wrap" id="filter-class">
                    <div class="ms-btn" id="ms-toggle-class" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false">
                        <span class="ms-label">Clasificación</span>
                        <span class="ms-summary" id="ms-summary-class">Todas</span>
                    </div>
                    <div class="ms-panel" id="ms-panel-class" aria-hidden="true">
                        <div id="ms-options-class"></div>
                        <div class="ms-actions">
                            <button type="button" id="ms-select-all-class">Todo</button>
                            <button type="button" id="ms-clear-class">Limpiar</button>
                        </div>
                    </div>
                </div>
                <div class="ms-wrap" id="filter-origin">
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
        <table id="tabla-rasgos" class="display docs-table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Personajes</th>
                    <th>Tipo</th>
                    <th>Clasificación</th>
                    <th>Origen</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<script>
$(document).ready(function () {
    const rasgos = <?= json_encode($rasgos, JSON_UNESCAPED_UNICODE) ?>;
    const tbody = $('#tabla-rasgos tbody');

    rasgos.forEach(r => {
        const traitSlug = r.trait_pretty_id || r.trait_id;
        const nombre = `<a href="/rules/traits/${escapeHtml(traitSlug)}">${escapeHtml(r.trait_name)}</a>`;
        const holders = Number(r.trait_holders || 0);
        const tipo = r.trait_category ? escapeHtml(r.trait_category) : '-';
        const clasificacion = r.trait_subcategory ? escapeHtml(r.trait_subcategory) : '-';
        const origen = r.trait_origin ? escapeHtml(r.trait_origin) : '-';

        const row = `<tr>
            <td>${nombre}</td>
            <td>${holders}</td>
            <td>${tipo}</td>
            <td>${clasificacion}</td>
            <td>${origen}</td>
        </tr>`;
        tbody.append(row);
    });

    const dt = $('#tabla-rasgos').DataTable({
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        order: [[0, "asc"]],
        language: {
            search: "Buscar:&nbsp;",
            lengthMenu: "Mostrar _MENU_ rasgos",
            info: "Mostrando _START_ a _END_ de _TOTAL_ rasgos",
            infoEmpty: "No hay rasgos disponibles",
            emptyTable: "No hay datos en la tabla",
            paginate: {
                first: "Primero",
                last: "Último",
                next: "Siguiente",
                previous: "Anterior"
            }
        },
        initComplete: function(){
            $('#dt-search-slot').append($('#tabla-rasgos_filter'));
            const $inp = $('#tabla-rasgos_filter input');
            if ($inp.length) {
                const cs = window.getComputedStyle($inp[0]);
                ['#ms-toggle-type', '#ms-toggle-class', '#ms-toggle-origin'].forEach(sel => {
                    const $btn = $(sel);
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

    const typeSet = new Set();
    const classSet = new Set();
    const originSet = new Set();

    rasgos.forEach(r => {
        typeSet.add((r.trait_category !== null && r.trait_category !== undefined && String(r.trait_category).trim() !== '') ? String(r.trait_category).trim() : '-');
        classSet.add((r.trait_subcategory !== null && r.trait_subcategory !== undefined && String(r.trait_subcategory).trim() !== '') ? String(r.trait_subcategory).trim() : '-');
        originSet.add((r.trait_origin !== null && r.trait_origin !== undefined && String(r.trait_origin).trim() !== '') ? String(r.trait_origin).trim() : '-');
    });

    const filterConfigs = [
        { key: 'type', column: 2, allLabel: 'Todos', values: sortValues(Array.from(typeSet)) },
        { key: 'class', column: 3, allLabel: 'Todas', values: sortValues(Array.from(classSet)) },
        { key: 'origin', column: 4, allLabel: 'Todos', values: sortValues(Array.from(originSet)) },
    ];

    function openPanel(key){ $('#ms-panel-' + key).show().attr('aria-hidden','false'); $('#ms-toggle-' + key).attr('aria-expanded','true'); }
    function closePanel(key){ $('#ms-panel-' + key).hide().attr('aria-hidden','true'); $('#ms-toggle-' + key).attr('aria-expanded','false'); }
    function togglePanel(key){ $('#ms-panel-' + key).is(':visible') ? closePanel(key) : openPanel(key); }

    function getSelected(key){
        const selected = $('#ms-options-' + key + ' input:checked').map(function(){ return $(this).val(); }).get();
        return selected.length ? selected : null;
    }
    function updateSummary(key, selected, allLabel){
        const $summary = $('#ms-summary-' + key);
        if (selected === null) { $summary.text(allLabel); return; }
        if (selected.length === 1) $summary.text(selected[0]);
        else $summary.text(selected.length + ' selecc.');
    }
    function applyFilters(){
        filterConfigs.forEach(cfg => {
            const selected = getSelected(cfg.key);
            updateSummary(cfg.key, selected, cfg.allLabel);
            if (selected === null) {
                dt.column(cfg.column).search('', true, false);
            } else {
                const pat = '^(?:' + selected.map(s => escapeRegex(s)).join('|') + ')$';
                dt.column(cfg.column).search(pat, true, false);
            }
        });
        dt.draw();
    }

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

function escapeHtml(text) {
    if (!text) return '';
    return text.replace(/[&<>\"']/g, function (m) {
        return ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        })[m];
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
