<?php
require_once __DIR__ . '/../../domains/rules/queries.php';
setMetaFromPage("Acciones | Heaven's Gate", 'Catálogo de acciones básicas y sus tiradas.', null, 'website');
include_once __DIR__ . '/../../helpers/public_response.php';

if (!$link || !($link instanceof mysqli)) {
    hg_public_render_error('Acciones no disponibles', 'No se pudo cargar este catálogo.', 500, true);
    return;
}

mysqli_set_charset($link, 'utf8mb4');
$pageSect = 'Acciones';
include 'app/partials/main_nav_bar.php';

$actions = hg_rules_fetch_actions($link);
if ($actions === null) {
    hg_public_log_error('action_table', 'query failed: ' . $link->error);
    hg_public_render_error('Acciones no disponibles', 'Ejecuta primero la migración del catálogo.', 503, true);
    return;
}
foreach ($actions as &$action) {
    $action['href'] = pretty_url($link, 'fact_actions', '/rules/actions', (int)$action['id']);
}
unset($action);
?>
<?php if (function_exists('hg_page_register_stylesheet')) { hg_page_register_stylesheet('/assets/css/hg-docs.css'); } else { ?><link rel="stylesheet" href="/assets/css/hg-docs.css"><?php } ?>
<?php include_once 'app/partials/datatable_assets.php'; ?>

<h2 class="docs-table-title">Acciones</h2>
<div class="docs-table-wrap"><div class="docs-table-inner">
    <table id="tabla-acciones" class="display docs-table"><thead><tr><th>Acción</th><th>Categoría</th><th>Tirada</th><th>Dificultad</th><th>Origen</th></tr></thead><tbody></tbody></table>
</div></div>
<script>
$(function(){
 const actions=<?= json_encode($actions, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
 const h=value=>$('<div>').text(value||'').html();
 const difficulty=action=>{if(action.difficulty_mode==='fixed')return 'Fija: '+Number(action.fixed_difficulty||0);const suggested=Number(action.suggested_difficulty||0),min=Number(action.min_difficulty||0),max=Number(action.max_difficulty||0);return 'Variable'+(suggested?' (sugerida: '+suggested+')':'')+(min&&max?' · '+min+'–'+max:'');};
 const tbody=$('#tabla-acciones tbody'); actions.forEach(action=>tbody.append(`<tr><td><a href="${h(action.href)}">${h(action.name)}</a></td><td>${h(action.category||'-')}</td><td>${h(action.attribute_name)} + ${h(action.skill_name)}</td><td>${h(difficulty(action))}</td><td>${h(action.origin_name||'-')}</td></tr>`));
 $('#tabla-acciones').DataTable({pageLength:25,lengthMenu:[10,25,50,100],order:[[1,'asc'],[0,'asc']],language:{search:'Buscar:',lengthMenu:'Mostrar _MENU_ acciones',info:'Mostrando _START_ a _END_ de _TOTAL_ acciones',infoEmpty:'No hay acciones disponibles',emptyTable:'No hay datos en la tabla',paginate:{first:'Primero',last:'Último',next:'Siguiente',previous:'Anterior'}}});
});
</script>
