<?php
setMetaFromPage("Condiciones | Heaven's Gate", "Listado de condiciones, deformidades, heridas y trastornos.", null, 'website');
include_once(__DIR__ . '/../../helpers/public_response.php');
header('Content-Type: text/html; charset=utf-8');

if (!$link) {
    hg_public_log_error('conditions_table', 'missing DB connection');
    hg_public_render_error(
        'Condiciones no disponibles',
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
if (!function_exists('conditions_table_column_exists')) {
    function conditions_table_column_exists(mysqli $db, string $table, string $column): bool {
        if ($st = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?")) {
            $st->bind_param('ss', $table, $column);
            $st->execute();
            $st->bind_result($count);
            $st->fetch();
            $st->close();
            return ((int)$count > 0);
        }
        return false;
    }
}

$excludeChronicles = isset($excludeChronicles) ? sanitize_int_csv($excludeChronicles) : '2,7';
$whereChron = ($excludeChronicles !== '') ? "p.chronicle_id NOT IN ($excludeChronicles)" : "1=1";
$conditionActiveSql = conditions_table_column_exists($link, 'bridge_characters_conditions', 'is_active')
    ? "(bcc.is_active = 1 OR bcc.is_active IS NULL)"
    : "1=1";

$query = "
    SELECT
        c.id AS condition_id,
        c.pretty_id AS condition_pretty_id,
        c.name AS condition_name,
        c.category AS condition_category,
        COALESCE(b.name, '') AS condition_origin,
        COUNT(DISTINCT CASE
            WHEN p.id IS NOT NULL AND {$conditionActiveSql} THEN p.id
            ELSE NULL
        END) AS affected_characters
    FROM dim_character_conditions c
        LEFT JOIN dim_bibliographies b ON b.id = c.bibliography_id
        LEFT JOIN bridge_characters_conditions bcc ON bcc.condition_id = c.id
        LEFT JOIN fact_characters p
            ON p.id = bcc.character_id
           AND $whereChron
    GROUP BY c.id, c.pretty_id, c.name, c.category, b.name
    ORDER BY
        CASE
            WHEN c.category = 'Deformidad Metis' THEN 0
            WHEN c.category = 'Herida de Guerra' THEN 1
            WHEN c.category = 'Trastorno Mental' THEN 2
            ELSE 9999
        END ASC,
        c.name ASC
";

$result = mysqli_query($link, $query);
if (!$result) {
    $err = mysqli_error($link);
    hg_public_log_error('conditions_table', 'query failed: ' . $err);
    hg_public_render_error('Condiciones no disponibles', 'No se pudo cargar este listado en este momento.');
    return;
}

$conditions = [];
while ($row = mysqli_fetch_assoc($result)) {
    $conditions[] = $row;
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

$conditions = ensure_utf8($conditions);
$pageSect = "Condiciones";
?>
<link rel="stylesheet" href="/assets/css/hg-docs.css">
<?php include_once("app/partials/datatable_assets.php"); ?>

<h2 class="docs-table-title">Condiciones</h2>

<div class="docs-table-wrap">
    <div class="docs-table-inner">
        <div class="dt-toolbar">
            <div class="left">
                <div class="ms-wrap" id="filter-category">
                    <div class="ms-btn" id="ms-toggle-category" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false">
                        <span class="ms-label">Categoría</span>
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
        <table id="tabla-condiciones" class="display docs-table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Personajes</th>
                    <th>Categoría</th>
                    <th>Origen</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<script>
$(document).ready(function () {
    const conditions = <?= json_encode($conditions, JSON_UNESCAPED_UNICODE) ?>;
    const tbody = $('#tabla-condiciones tbody');

    conditions.forEach(c => {
        const conditionSlug = c.condition_pretty_id || c.condition_id;
        const nombre = `<a href="/rules/conditions/${escapeHtml(conditionSlug)}">${escapeHtml(c.condition_name)}</a>`;
        const afectados = Number(c.affected_characters || 0);
        const categoria = c.condition_category ? escapeHtml(c.condition_category) : '-';
        const origen = c.condition_origin ? escapeHtml(c.condition_origin) : '-';

        const row = `<tr>
            <td>${nombre}</td>
            <td>${afectados}</td>
            <td>${categoria}</td>
            <td>${origen}</td>
        </tr>`;
        tbody.append(row);
    });

    const dt = $('#tabla-condiciones').DataTable({
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        order: [[0, "asc"]],
        language: {
            search: "Buscar:&nbsp;",
            lengthMenu: "Mostrar _MENU_ condiciones",
            info: "Mostrando _START_ a _END_ de _TOTAL_ condiciones",
            infoEmpty: "No hay condiciones disponibles",
            emptyTable: "No hay datos en la tabla",
            paginate: {
                first: "Primero",
                last: "Último",
                next: "Siguiente",
                previous: "Anterior"
            }
        },
        initComplete: function(){
            $('#dt-search-slot').append($('#tabla-condiciones_filter'));
            const $inp = $('#tabla-condiciones_filter input');
            if ($inp.length) {
                const cs = window.getComputedStyle($inp[0]);
                ['#ms-toggle-category', '#ms-toggle-origin'].forEach(sel => {
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

    const categorySet = new Set();
    const originSet = new Set();

    conditions.forEach(c => {
        categorySet.add((c.condition_category !== null && c.condition_category !== undefined && String(c.condition_category).trim() !== '') ? String(c.condition_category).trim() : '-');
        originSet.add((c.condition_origin !== null && c.condition_origin !== undefined && String(c.condition_origin).trim() !== '') ? String(c.condition_origin).trim() : '-');
    });

    const filterConfigs = [
        { key: 'category', column: 2, allLabel: 'Todas', values: sortValues(Array.from(categorySet)) },
        { key: 'origin', column: 3, allLabel: 'Todos', values: sortValues(Array.from(originSet)) },
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
        $('#ms-options-' + cfg.key).on('change', 'input', applyFilters);
        $('#ms-select-all-' + cfg.key).on('click', function(){
            $('#ms-options-' + cfg.key + ' input').prop('checked', true);
            applyFilters();
        });
        $('#ms-clear-' + cfg.key).on('click', function(){
            $('#ms-options-' + cfg.key + ' input').prop('checked', false);
            applyFilters();
        });
    });

    $(document).on('click', function(e){
        filterConfigs.forEach(cfg => {
            const $wrap = $('#filter-' + cfg.key);
            if ($wrap.length && !$wrap.is(e.target) && $wrap.has(e.target).length === 0) {
                closePanel(cfg.key);
            }
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
