<?php
require_once __DIR__ . '/../../domains/rules/queries.php';
setMetaFromPage("Condiciones | Heaven's Gate", "Listado de condiciones, deformidades, heridas y trastornos.", null, 'website');
include_once(__DIR__ . '/../../helpers/public_response.php');
header('Content-Type: text/html; charset=utf-8');

if (!$link) {
    hg_public_log_error('conditions_table', 'missing DB connection');
    hg_public_render_error('Condiciones no disponibles', 'No se pudo cargar este listado en este momento.', 500, true);
    return;
}

mysqli_set_charset($link, "utf8mb4");
include("app/partials/main_nav_bar.php");

$excludeChronicles = isset($excludeChronicles) ? hg_rules_normalize_int_csv($excludeChronicles) : '2,7';
$conditions = hg_rules_fetch_conditions_table($link, $excludeChronicles);
if ($conditions === null) {
    hg_public_log_error('conditions_table', 'query failed: ' . mysqli_error($link));
    hg_public_render_error('Condiciones no disponibles', 'No se pudo cargar este listado en este momento.');
    return;
}

function ensure_utf8($value) {
    if (is_string($value)) {
        if (function_exists('mb_check_encoding') && !mb_check_encoding($value, 'UTF-8')) {
            if (function_exists('mb_convert_encoding')) return mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
            if (function_exists('utf8_encode')) return utf8_encode($value);
        }
        return $value;
    }
    if (is_array($value)) foreach ($value as $k => $v) $value[$k] = ensure_utf8($v);
    return $value;
}

$conditions = ensure_utf8($conditions);
$pageSect = "Condiciones";
?>
<?php if (function_exists('hg_page_register_stylesheet')) { hg_page_register_stylesheet('/assets/css/hg-docs.css'); } else { ?><link rel="stylesheet" href="/assets/css/hg-docs.css"><?php } ?>
<?php include_once("app/partials/datatable_assets.php"); ?>

<h2 class="docs-table-title">Condiciones</h2>
<div class="docs-table-wrap"><div class="docs-table-inner">
    <div class="dt-toolbar">
        <div class="left">
            <div class="ms-wrap" id="filter-category"><div class="ms-btn" id="ms-toggle-category" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false"><span class="ms-label">Categoría</span><span class="ms-summary" id="ms-summary-category">Todas</span></div><div class="ms-panel" id="ms-panel-category" aria-hidden="true"><div id="ms-options-category"></div><div class="ms-actions"><button type="button" id="ms-select-all-category">Todo</button><button type="button" id="ms-clear-category">Limpiar</button></div></div></div>
            <div class="ms-wrap" id="filter-origin"><div class="ms-btn" id="ms-toggle-origin" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false"><span class="ms-label">Origen</span><span class="ms-summary" id="ms-summary-origin">Todos</span></div><div class="ms-panel" id="ms-panel-origin" aria-hidden="true"><div id="ms-options-origin"></div><div class="ms-actions"><button type="button" id="ms-select-all-origin">Todo</button><button type="button" id="ms-clear-origin">Limpiar</button></div></div></div>
        </div><div class="right" id="dt-search-slot"></div>
    </div>
    <table id="tabla-condiciones" class="display docs-table"><thead><tr><th>Nombre</th><th>Personajes</th><th>Categoría</th><th>Origen</th></tr></thead><tbody></tbody></table>
</div></div>
<script>
$(document).ready(function(){
    const conditions=<?= json_encode($conditions, JSON_UNESCAPED_UNICODE) ?>;
    const tbody=$('#tabla-condiciones tbody');
    conditions.forEach(c=>{
        const slug=c.condition_pretty_id||c.condition_id;
        const nombre=`<a href="/rules/conditions/${escapeHtml(slug)}">${escapeHtml(c.condition_name)}</a>`;
        const afectados=Number(c.affected_characters||0);
        const categoria=c.condition_category?escapeHtml(c.condition_category):'-';
        const origen=c.condition_origin?escapeHtml(c.condition_origin):'-';
        tbody.append(`<tr><td>${nombre}</td><td>${afectados}</td><td>${categoria}</td><td>${origen}</td></tr>`);
    });

    const dt=$('#tabla-condiciones').DataTable({pageLength:25,lengthMenu:[10,25,50,100],order:[[0,'asc']],language:{search:'Buscar:&nbsp;',lengthMenu:'Mostrar _MENU_ condiciones',info:'Mostrando _START_ a _END_ de _TOTAL_ condiciones',infoEmpty:'No hay condiciones disponibles',emptyTable:'No hay datos en la tabla',paginate:{first:'Primero',last:'Último',next:'Siguiente',previous:'Anterior'}},initComplete:function(){$('#dt-search-slot').append($('#tabla-condiciones_filter'));}});

    HGDataTableFilters.create({
        table: dt,
        rows: conditions,
        filters: [
            { key:'category', source:'condition_category', column:2, allLabel:'Todas', dependsOn:[] },
            { key:'origin', source:'condition_origin', column:3, allLabel:'Todos', dependsOn:['category'] }
        ]
    });
});
function escapeHtml(text){if(!text)return '';return String(text).replace(/[&<>\"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;',"'":'&#39;'})[m]);}
</script>
