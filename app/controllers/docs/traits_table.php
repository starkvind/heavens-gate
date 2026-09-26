<?php
require_once __DIR__ . '/../../domains/rules/queries.php';
setMetaFromPage("Rasgos | Heaven's Gate", "Listado de rasgos y habilidades.", null, 'website');
include_once(__DIR__ . '/../../helpers/public_response.php');
header('Content-Type: text/html; charset=utf-8');

if (!$link) {
    hg_public_log_error('traits_table', 'missing DB connection');
    hg_public_render_error('Rasgos no disponibles', 'No se pudo cargar este listado en este momento.', 500, true);
    return;
}

mysqli_set_charset($link, "utf8mb4");
include("app/partials/main_nav_bar.php");

if (!function_exists('h')) {
    function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

$excludeChronicles = isset($excludeChronicles) ? hg_rules_normalize_int_csv($excludeChronicles) : '2,7';
$rasgos = hg_rules_fetch_traits_table($link, $excludeChronicles);
if ($rasgos === null) {
    hg_public_log_error('traits_table', 'query failed: ' . mysqli_error($link));
    hg_public_render_error('Rasgos no disponibles', 'No se pudo cargar este listado en este momento.');
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
    if (is_array($value)) {
        foreach ($value as $k => $v) $value[$k] = ensure_utf8($v);
    }
    return $value;
}

$rasgos = ensure_utf8($rasgos);
$pageSect = "Rasgos";
?>
<?php if (function_exists('hg_page_register_stylesheet')) { hg_page_register_stylesheet('/assets/css/hg-docs.css'); } else { ?><link rel="stylesheet" href="/assets/css/hg-docs.css"><?php } ?>
<?php include_once("app/partials/datatable_assets.php"); ?>

<h2 class="docs-table-title">Rasgos</h2>
<div class="docs-table-wrap"><div class="docs-table-inner">
    <div class="dt-toolbar">
        <div class="left">
            <div class="ms-wrap" id="filter-type"><div class="ms-btn" id="ms-toggle-type" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false"><span class="ms-label">Tipo</span><span class="ms-summary" id="ms-summary-type">Todos</span></div><div class="ms-panel" id="ms-panel-type" aria-hidden="true"><div id="ms-options-type"></div><div class="ms-actions"><button type="button" id="ms-select-all-type">Todo</button><button type="button" id="ms-clear-type">Limpiar</button></div></div></div>
            <div class="ms-wrap" id="filter-class"><div class="ms-btn" id="ms-toggle-class" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false"><span class="ms-label">Clasificación</span><span class="ms-summary" id="ms-summary-class">Todas</span></div><div class="ms-panel" id="ms-panel-class" aria-hidden="true"><div id="ms-options-class"></div><div class="ms-actions"><button type="button" id="ms-select-all-class">Todo</button><button type="button" id="ms-clear-class">Limpiar</button></div></div></div>
            <div class="ms-wrap" id="filter-origin"><div class="ms-btn" id="ms-toggle-origin" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false"><span class="ms-label">Origen</span><span class="ms-summary" id="ms-summary-origin">Todos</span></div><div class="ms-panel" id="ms-panel-origin" aria-hidden="true"><div id="ms-options-origin"></div><div class="ms-actions"><button type="button" id="ms-select-all-origin">Todo</button><button type="button" id="ms-clear-origin">Limpiar</button></div></div></div>
        </div>
        <div class="right" id="dt-search-slot"></div>
    </div>
    <table id="tabla-rasgos" class="display docs-table"><thead><tr><th>Nombre</th><th>Personajes</th><th>Tipo</th><th>Clasificación</th><th>Origen</th></tr></thead><tbody></tbody></table>
</div></div>

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
        tbody.append(`<tr><td>${nombre}</td><td>${holders}</td><td>${tipo}</td><td>${clasificacion}</td><td>${origen}</td></tr>`);
    });
    const dt = $('#tabla-rasgos').DataTable({pageLength:25,lengthMenu:[10,25,50,100],order:[[0,"asc"]],language:{search:"Buscar:&nbsp;",lengthMenu:"Mostrar _MENU_ rasgos",info:"Mostrando _START_ a _END_ de _TOTAL_ rasgos",infoEmpty:"No hay rasgos disponibles",emptyTable:"No hay datos en la tabla",paginate:{first:"Primero",last:"Último",next:"Siguiente",previous:"Anterior"}},initComplete:function(){$('#dt-search-slot').append($('#tabla-rasgos_filter'));}});
    const typeSet=new Set(), classSet=new Set(), originSet=new Set();
    rasgos.forEach(r=>{typeSet.add((r.trait_category!==null&&r.trait_category!==undefined&&String(r.trait_category).trim()!=='')?String(r.trait_category).trim():'-');classSet.add((r.trait_subcategory!==null&&r.trait_subcategory!==undefined&&String(r.trait_subcategory).trim()!=='')?String(r.trait_subcategory).trim():'-');originSet.add((r.trait_origin!==null&&r.trait_origin!==undefined&&String(r.trait_origin).trim()!=='')?String(r.trait_origin).trim():'-');});
    const filterConfigs=[{key:'type',column:2,allLabel:'Todos',values:sortValues(Array.from(typeSet))},{key:'class',column:3,allLabel:'Todas',values:sortValues(Array.from(classSet))},{key:'origin',column:4,allLabel:'Todos',values:sortValues(Array.from(originSet))}];
    function openPanel(key){$('#ms-panel-'+key).show().attr('aria-hidden','false');$('#ms-toggle-'+key).attr('aria-expanded','true');}
    function closePanel(key){$('#ms-panel-'+key).hide().attr('aria-hidden','true');$('#ms-toggle-'+key).attr('aria-expanded','false');}
    function togglePanel(key){$('#ms-panel-'+key).is(':visible')?closePanel(key):openPanel(key);}
    function getSelected(key){const selected=$('#ms-options-'+key+' input:checked').map(function(){return $(this).val();}).get();return selected.length?selected:null;}
    function updateSummary(key,selected,allLabel){const $summary=$('#ms-summary-'+key);if(selected===null){$summary.text(allLabel);return;}if(selected.length===1)$summary.text(selected[0]);else $summary.text(selected.length+' selecc.');}
    function applyFilters(){filterConfigs.forEach(cfg=>{const selected=getSelected(cfg.key);updateSummary(cfg.key,selected,cfg.allLabel);if(selected===null)dt.column(cfg.column).search('',true,false);else dt.column(cfg.column).search('^(?:'+selected.map(s=>escapeRegex(s)).join('|')+')$',true,false);});dt.draw();}
    filterConfigs.forEach(cfg=>{const $opts=$('#ms-options-'+cfg.key);cfg.values.forEach(v=>{const safe=escapeHtml(v);$opts.append(`<label class="ms-row"><input type="checkbox" value="${safe}" checked><span>${safe}</span></label>`);});$('#ms-toggle-'+cfg.key).on('click',()=>togglePanel(cfg.key));$('#ms-toggle-'+cfg.key).on('keydown',e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();togglePanel(cfg.key);}});$opts.on('change','input',applyFilters);$('#ms-select-all-'+cfg.key).on('click',function(){$opts.find('input').prop('checked',true);applyFilters();});$('#ms-clear-'+cfg.key).on('click',function(){$opts.find('input').prop('checked',false);applyFilters();});});
    $(document).on('click',function(e){filterConfigs.forEach(cfg=>{if(!$(e.target).closest('#filter-'+cfg.key).length)closePanel(cfg.key);});});
    applyFilters();
});
function escapeHtml(text){if(!text)return '';return text.replace(/[&<>\"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;',"'":'&#39;'})[m]);}
function escapeRegex(text){return String(text).replace(/[.*+?^${}()|[\]\\]/g,'\\$&');}
function sortValues(values){return values.sort((a,b)=>{if(a==='-'&&b!=='-')return 1;if(b==='-'&&a!=='-')return -1;return a.localeCompare(b,'es');});}
</script>
