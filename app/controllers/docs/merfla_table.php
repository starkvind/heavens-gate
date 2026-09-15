<?php
require_once __DIR__ . '/../../domains/rules/queries.php';
setMetaFromPage("Méritos y defectos | Heaven's Gate", "Listado de méritos y defectos.", null, 'website');
include_once(__DIR__ . '/../../helpers/public_response.php');
header('Content-Type: text/html; charset=utf-8');

if (!$link) {
    hg_public_log_error('merfla_table', 'missing DB connection');
    hg_public_render_error('Méritos y defectos no disponibles', 'No se pudo cargar este listado en este momento.', 500, true);
    return;
}
mysqli_set_charset($link, 'utf8mb4');
include("app/partials/main_nav_bar.php");
$meritos = hg_rules_fetch_merits($link);
if ($meritos === null) {
    hg_public_log_error('merfla_table', 'query failed: ' . mysqli_error($link));
    hg_public_render_error('Méritos y defectos no disponibles', 'No se pudo cargar este listado en este momento.');
    return;
}
$pageSect = 'Méritos y Defectos';
?>
<?php if (function_exists('hg_page_register_stylesheet')) { hg_page_register_stylesheet('/assets/css/hg-docs.css'); } else { ?><link rel="stylesheet" href="/assets/css/hg-docs.css"><?php } ?>
<?php include_once("app/partials/datatable_assets.php"); ?>
<h2 class="docs-table-title">Méritos y Defectos</h2>
<div class="docs-table-wrap"><div class="docs-table-inner">
<div class="dt-toolbar"><div class="left">
<div class="ms-wrap" id="filter-type"><div class="ms-btn" id="ms-toggle-type" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false"><span class="ms-label">Tipo</span><span class="ms-summary" id="ms-summary-type">Todos</span></div><div class="ms-panel" id="ms-panel-type" aria-hidden="true"><div id="ms-options-type"></div><div class="ms-actions"><button type="button" id="ms-select-all-type">Todo</button><button type="button" id="ms-clear-type">Limpiar</button></div></div></div>
<div class="ms-wrap" id="filter-system"><div class="ms-btn" id="ms-toggle-system" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false"><span class="ms-label">Sistema</span><span class="ms-summary" id="ms-summary-system">Todos</span></div><div class="ms-panel" id="ms-panel-system" aria-hidden="true"><div id="ms-options-system"></div><div class="ms-actions"><button type="button" id="ms-select-all-system">Todo</button><button type="button" id="ms-clear-system">Limpiar</button></div></div></div>
<div class="ms-wrap" id="filter-category"><div class="ms-btn" id="ms-toggle-category" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false"><span class="ms-label">Categor&iacute;a</span><span class="ms-summary" id="ms-summary-category">Todas</span></div><div class="ms-panel" id="ms-panel-category" aria-hidden="true"><div id="ms-options-category"></div><div class="ms-actions"><button type="button" id="ms-select-all-category">Todo</button><button type="button" id="ms-clear-category">Limpiar</button></div></div></div>
</div><div class="left docs-left-third"><div class="ms-wrap" id="filter-origin"><div class="ms-btn" id="ms-toggle-origin" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false"><span class="ms-label">Origen</span><span class="ms-summary" id="ms-summary-origin">Todos</span></div><div class="ms-panel" id="ms-panel-origin" aria-hidden="true"><div id="ms-options-origin"></div><div class="ms-actions"><button type="button" id="ms-select-all-origin">Todo</button><button type="button" id="ms-clear-origin">Limpiar</button></div></div></div></div><div class="right" id="dt-search-slot"></div></div>
<table id="tabla-meritos" class="display docs-table"><thead><tr><th>Nombre</th><th>Tipo</th><th>Sistema</th><th>Categoría</th><th>Coste</th><th>Origen</th></tr></thead><tbody></tbody></table>
</div></div>
<script>
$(document).ready(function(){
 const meritos=<?= json_encode($meritos, JSON_UNESCAPED_UNICODE) ?>,tbody=$('#tabla-meritos tbody');
 meritos.forEach(m=>{const slug=m.merit_pretty_id||m.merit_id,nombre=`<a href="/rules/merits-flaws/${escapeHtml(slug)}">${escapeHtml(m.merit_name)}</a>`,tipo=m.merit_type?escapeHtml(m.merit_type):'-',sistema=m.merit_system?escapeHtml(m.merit_system):'-',categoria=m.merit_category?escapeHtml(m.merit_category):'-',coste=(m.merit_cost!==null&&m.merit_cost!=='')?escapeHtml(String(m.merit_cost)):'-',origen=m.merit_origin?escapeHtml(m.merit_origin):'-';tbody.append(`<tr><td>${nombre}</td><td>${tipo}</td><td>${sistema}</td><td>${categoria}</td><td>${coste}</td><td>${origen}</td></tr>`);});
 const dt=$('#tabla-meritos').DataTable({pageLength:25,lengthMenu:[10,25,50,100],order:[[0,'asc']],language:{search:'Buscar:&nbsp;',lengthMenu:'Mostrar _MENU_ méritos y defectos',info:'Mostrando _START_ a _END_ de _TOTAL_ entradas',infoEmpty:'No hay méritos ni defectos disponibles',emptyTable:'No hay datos en la tabla',paginate:{first:'Primero',last:'Último',next:'Siguiente',previous:'Anterior'}},initComplete:function(){$('#dt-search-slot').append($('#tabla-meritos_filter'));}});
 const sets={type:new Set(),system:new Set(),category:new Set(),origin:new Set()};meritos.forEach(m=>{sets.type.add(m.merit_type&&String(m.merit_type).trim()?String(m.merit_type).trim():'-');sets.system.add(m.merit_system&&String(m.merit_system).trim()?String(m.merit_system).trim():'-');sets.category.add(m.merit_category&&String(m.merit_category).trim()?String(m.merit_category).trim():'-');sets.origin.add(m.merit_origin&&String(m.merit_origin).trim()?String(m.merit_origin).trim():'-');});
 const cfgs=[{key:'type',column:1,allLabel:'Todos'},{key:'system',column:2,allLabel:'Todos'},{key:'category',column:3,allLabel:'Todas'},{key:'origin',column:5,allLabel:'Todos'}];cfgs.forEach(c=>c.values=sortValues(Array.from(sets[c.key])));
 function close(k){$('#ms-panel-'+k).hide().attr('aria-hidden','true');$('#ms-toggle-'+k).attr('aria-expanded','false');}function toggle(k){const p=$('#ms-panel-'+k);if(p.is(':visible'))close(k);else{p.show().attr('aria-hidden','false');$('#ms-toggle-'+k).attr('aria-expanded','true');}}function selected(k){const s=$('#ms-options-'+k+' input:checked').map(function(){return $(this).val();}).get();return s.length?s:null;}function apply(){cfgs.forEach(c=>{const s=selected(c.key);$('#ms-summary-'+c.key).text(s===null?c.allLabel:(s.length===1?s[0]:s.length+' selecc.'));dt.column(c.column).search(s===null?'':'^(?:'+s.map(escapeRegex).join('|')+')$',true,false);});dt.draw();}
 cfgs.forEach(c=>{const o=$('#ms-options-'+c.key);c.values.forEach(v=>{const safe=escapeHtml(v);o.append(`<label class="ms-row"><input type="checkbox" value="${safe}" checked><span>${safe}</span></label>`);});$('#ms-toggle-'+c.key).on('click',()=>toggle(c.key)).on('keydown',e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();toggle(c.key);}});o.on('change','input',apply);$('#ms-select-all-'+c.key).on('click',()=>{o.find('input').prop('checked',true);apply();});$('#ms-clear-'+c.key).on('click',()=>{o.find('input').prop('checked',false);apply();});});$(document).on('click',e=>cfgs.forEach(c=>{if(!$(e.target).closest('#filter-'+c.key).length)close(c.key);}));apply();
});
function escapeHtml(text){if(!text)return '';return String(text).replace(/[&<>\"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;',"'":'&#39;'})[m]);}function escapeRegex(text){return String(text).replace(/[.*+?^${}()|[\]\\]/g,'\\$&');}function sortValues(v){return v.sort((a,b)=>{if(a==='-'&&b!=='-')return 1;if(b==='-'&&a!=='-')return -1;return a.localeCompare(b,'es');});}
</script>
