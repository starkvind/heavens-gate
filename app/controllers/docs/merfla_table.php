<?php
setMetaFromPage("Méritos y defectos | Heaven's Gate", "Listado de méritos y defectos.", null, 'website');
include_once(__DIR__ . '/../../helpers/public_response.php');
header('Content-Type: text/html; charset=utf-8');

if (!$link) {
    hg_public_log_error('merfla_table', 'missing DB connection');
    hg_public_render_error(
        'Méritos y defectos no disponibles',
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

// Cargar méritos y defectos con la query indicada
$query = "
    select
        nmyd.id as merit_id,
        nmyd.pretty_id as merit_pretty_id,
        nmyd.name as merit_name,
        nmyd.system_name as merit_system,
        nmyd.kind as merit_type,
        nmyd.affiliation as merit_category,
        nmyd.cost as merit_cost,
        COALESCE(nb.name, '') as merit_origin
    from dim_merits_flaws nmyd
        left join dim_bibliographies nb on nmyd.bibliography_id = nb.id
    order by
        CASE
            when nmyd.kind = 'Méritos' or nmyd.kind = 'Meritos' then 0
            else 9999
        END asc,
        nmyd.system_name ASC
";

$result = mysqli_query($link, $query);
if (!$result) {
    $err = mysqli_error($link);
    if (stripos($err, "Unknown column 'nmyd.kind'") !== false || stripos($err, "Unknown column `nmyd`.`kind`") !== false) {
        $query = str_replace("nmyd.kind", "nmyd.tipo", $query);
        $result = mysqli_query($link, $query);
    }
}

if (!$result) {
    $err = mysqli_error($link);
    if (stripos($err, "Unknown column 'nmyd.system_name'") !== false || stripos($err, "Unknown column `nmyd`.`system_name`") !== false) {
        $query = str_replace("nmyd.system_name", "nmyd.sistema", $query);
    }
    if (stripos($err, "Unknown column 'nmyd.affiliation'") !== false || stripos($err, "Unknown column `nmyd`.`affiliation`") !== false) {
        $query = str_replace("nmyd.affiliation", "nmyd.afiliacion", $query);
    }
    if (stripos($err, "Unknown column 'nmyd.cost'") !== false || stripos($err, "Unknown column `nmyd`.`cost`") !== false) {
        $query = str_replace("nmyd.cost", "nmyd.coste", $query);
    }
    if ($query !== '' && $query !== null) {
        $result = mysqli_query($link, $query);
    }
}

$meritos = [];
$isResult = ($result instanceof mysqli_result);
if ($result && $isResult) {
    while ($row = mysqli_fetch_assoc($result)) {
        $meritos[] = $row;
    }
    mysqli_free_result($result);
} else {
    $err = mysqli_error($link);
    hg_public_log_error('merfla_table', 'query failed: ' . $err);
    hg_public_render_error('Méritos y defectos no disponibles', 'No se pudo cargar este listado en este momento.');
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

$meritos = ensure_utf8($meritos);

$pageSect = "Méritos y Defectos";
?>
<link rel="stylesheet" href="/assets/css/hg-docs.css">
<?php include_once("app/partials/datatable_assets.php"); ?>

<h2 class="docs-table-title">Méritos y Defectos</h2>

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
                <div class="ms-wrap" id="filter-system">
                    <div class="ms-btn" id="ms-toggle-system" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false">
                        <span class="ms-label">Sistema</span>
                        <span class="ms-summary" id="ms-summary-system">Todos</span>
                    </div>
                    <div class="ms-panel" id="ms-panel-system" aria-hidden="true">
                        <div id="ms-options-system"></div>
                        <div class="ms-actions">
                            <button type="button" id="ms-select-all-system">Todo</button>
                            <button type="button" id="ms-clear-system">Limpiar</button>
                        </div>
                    </div>
                </div>
                <div class="ms-wrap" id="filter-category">
                    <div class="ms-btn" id="ms-toggle-category" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false">
                        <span class="ms-label">Categor&iacute;a</span>
                        <span class="ms-summary" id="ms-summary-category">Todas</span>
                    </div>
                    <div class="ms-panel" id="ms-panel-category" aria-hidden="true">
                        <div id="ms-options-category"></div>
                        <div class="ms-actions">
                            <button type="button" id="ms-select-all-category">Todo</button>
                            <button type="button" id="ms-clear-category">Limpiar</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="left docs-left-third">
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
        <table id="tabla-meritos" class="display docs-table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Tipo</th>
                    <th>Sistema</th>
                    <th>Categoría</th>
                    <th>Coste</th>
                    <th>Origen</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<script>
$(document).ready(function () {
    const meritos = <?= json_encode($meritos, JSON_UNESCAPED_UNICODE) ?>;
    const tbody = $('#tabla-meritos tbody');

    meritos.forEach(m => {
        const meritSlug = m.merit_pretty_id || m.merit_id;
        const nombre = `<a href="/rules/merits-flaws/${escapeHtml(meritSlug)}">${escapeHtml(m.merit_name)}</a>`;
        const tipo = m.merit_type ? escapeHtml(m.merit_type) : '-';
        const sistema = m.merit_system ? escapeHtml(m.merit_system) : '-';
        const afiliacion = m.merit_category ? escapeHtml(m.merit_category) : '-';
        const coste = (m.merit_cost !== null && m.merit_cost !== '') ? escapeHtml(String(m.merit_cost)) : '-';
        const origen = m.merit_origin ? escapeHtml(m.merit_origin) : '-';

        const row = `<tr>
            <td>${nombre}</td>
            <td>${tipo}</td>
            <td>${sistema}</td>
            <td>${afiliacion}</td>
            <td>${coste}</td>
            <td>${origen}</td>
        </tr>`;
        tbody.append(row);
    });

    const dt = $('#tabla-meritos').DataTable({
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        order: [[0, "asc"]],
        language: {
            search: "Buscar:&nbsp;",
            lengthMenu: "Mostrar _MENU_ méritos y defectos",
            info: "Mostrando _START_ a _END_ de _TOTAL_ entradas",
            infoEmpty: "No hay méritos ni defectos disponibles",
            emptyTable: "No hay datos en la tabla",
            paginate: {
                first: "Primero",
                last: "Último",
                next: "Siguiente",
                previous: "Anterior"
            }
        },
        initComplete: function(){
            $('#dt-search-slot').append($('#tabla-meritos_filter'));
            const $inp = $('#tabla-meritos_filter input');
            if ($inp.length) {
                const cs = window.getComputedStyle($inp[0]);
                ['#ms-toggle-type', '#ms-toggle-system', '#ms-toggle-category', '#ms-toggle-origin'].forEach(sel => {
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
    const systemSet = new Set();
    const categorySet = new Set();
    const originSet = new Set();

    meritos.forEach(m => {
        typeSet.add((m.merit_type !== null && m.merit_type !== undefined && String(m.merit_type).trim() !== '') ? String(m.merit_type).trim() : '-');
        systemSet.add((m.merit_system !== null && m.merit_system !== undefined && String(m.merit_system).trim() !== '') ? String(m.merit_system).trim() : '-');
        categorySet.add((m.merit_category !== null && m.merit_category !== undefined && String(m.merit_category).trim() !== '') ? String(m.merit_category).trim() : '-');
        originSet.add((m.merit_origin !== null && m.merit_origin !== undefined && String(m.merit_origin).trim() !== '') ? String(m.merit_origin).trim() : '-');
    });

    const filterConfigs = [
        { key: 'type', column: 1, allLabel: 'Todos', values: sortValues(Array.from(typeSet)) },
        { key: 'system', column: 2, allLabel: 'Todos', values: sortValues(Array.from(systemSet)) },
        { key: 'category', column: 3, allLabel: 'Todas', values: sortValues(Array.from(categorySet)) },
        { key: 'origin', column: 5, allLabel: 'Todos', values: sortValues(Array.from(originSet)) },
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
