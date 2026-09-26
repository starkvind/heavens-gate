<?php
require_once __DIR__ . '/../../domains/rules/queries.php';
setMetaFromPage("Arquetipos | Heaven's Gate", "Listado de arquetipos de personalidad.", null, 'website');
include("app/partials/main_nav_bar.php");
header('Content-Type: text/html; charset=utf-8');
if ($link) mysqli_set_charset($link, 'utf8mb4');
$archetypes = hg_rules_fetch_archetypes($link) ?: [];
$pageSect = 'Arquetipos de personalidad';
?>
<?php if (function_exists('hg_page_register_stylesheet')) { hg_page_register_stylesheet('/assets/css/hg-docs.css'); } else { ?><link rel="stylesheet" href="/assets/css/hg-docs.css"><?php } ?>
<?php include_once("app/partials/datatable_assets.php"); ?>
<h2 class="docs-table-title">Arquetipos de personalidad</h2>
<div class="docs-table-wrap"><div class="docs-table-inner">
<div class="dt-toolbar"><div class="left"><div class="ms-wrap ms-wrap--wide" id="filter-origin"><div class="ms-btn" id="ms-toggle-origin" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false"><span class="ms-label">Origen</span><span class="ms-summary" id="ms-summary-origin">Todos</span></div><div class="ms-panel" id="ms-panel-origin" aria-hidden="true"><div id="ms-options-origin"></div><div class="ms-actions"><button type="button" id="ms-select-all-origin">Todo</button><button type="button" id="ms-clear-origin">Limpiar</button></div></div></div></div><div class="right" id="dt-search-slot"></div></div>
<table id="tabla-arquetipos" class="display docs-table"><thead><tr><th>Arquetipo</th><th>Personajes</th><th>Origen</th></tr></thead><tbody></tbody></table>
</div></div>
<script>
$(document).ready(function(){
 const archetypes=<?= json_encode($archetypes, JSON_UNESCAPED_UNICODE) ?>,tbody=$('#tabla-arquetipos tbody');
 archetypes.forEach(a=>{const slug=a.arche_pretty_id||a.arche_id,nombre=`<a href="/rules/archetypes/${escapeHtml(slug)}">${escapeHtml(a.arche_name)}</a>`,origen=a.arche_origin?escapeHtml(a.arche_origin):'-',holders=Number(a.arche_holders||0);tbody.append(`<tr><td>${nombre}</td><td>${holders}</td><td>${origen}</td></tr>`);});
 const dt=$('#tabla-arquetipos').DataTable({pageLength:25,lengthMenu:[10,25,50,100],order:[[0,'asc']],language:{search:'&#128269; Buscar: ',lengthMenu:'Mostrar _MENU_ arquetipos',info:'Mostrando _START_ a _END_ de _TOTAL_ arquetipos',infoEmpty:'No hay arquetipos disponibles',emptyTable:'No hay datos en la tabla',paginate:{first:'Primero',last:'&Uacute;ltimo',next:'&#9654;',previous:'&#9664;'}},initComplete:function(){$('#dt-search-slot').append($('#tabla-arquetipos_filter'));}});
 const values=sortValues(Array.from(new Set(archetypes.map(a=>a.arche_origin&&String(a.arche_origin).trim()?String(a.arche_origin).trim():'-'))));const opts=$('#ms-options-origin');values.forEach(v=>{const safe=escapeHtml(v);opts.append(`<label class="ms-row"><input type="checkbox" value="${safe}" checked><span>${safe}</span></label>`);});
 function close(){$('#ms-panel-origin').hide().attr('aria-hidden','true');$('#ms-toggle-origin').attr('aria-expanded','false');}function toggle(){const p=$('#ms-panel-origin');if(p.is(':visible'))close();else{p.show().attr('aria-hidden','false');$('#ms-toggle-origin').attr('aria-expanded','true');}}function apply(){const s=opts.find('input:checked').map(function(){return $(this).val();}).get();const selected=s.length?s:null;$('#ms-summary-origin').text(selected===null?'Todos':(selected.length===1?selected[0]:selected.length+' selecc.'));dt.column(2).search(selected===null?'':'^(?:'+selected.map(escapeRegex).join('|')+')$',true,false).draw();}
 $('#ms-toggle-origin').on('click',toggle).on('keydown',e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();toggle();}});opts.on('change','input',apply);$('#ms-select-all-origin').on('click',()=>{opts.find('input').prop('checked',true);apply();});$('#ms-clear-origin').on('click',()=>{opts.find('input').prop('checked',false);apply();});$(document).on('click',e=>{if(!$(e.target).closest('#filter-origin').length)close();});apply();
});
function escapeHtml(text){if(!text)return '';return String(text).replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m]);}function escapeRegex(text){return String(text).replace(/[.*+?^${}()|[\]\\]/g,'\\$&');}function sortValues(values){return values.sort((a,b)=>{if(a==='-'&&b!=='-')return 1;if(b==='-'&&a!=='-')return -1;return a.localeCompare(b,'es');});}
</script>
