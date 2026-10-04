<?php
if (function_exists("setMetaFromPage")) setMetaFromPage("Sistemas | Heaven's Gate", "Listado de sistemas y categorias disponibles.", null, 'website');
require_once __DIR__ . '/../../domains/systems/queries.php';
if (!defined("HG_MOBILE_DESKTOP_EMBED") || !HG_MOBILE_DESKTOP_EMBED) include("app/partials/main_nav_bar.php");
if ($link) { mysqli_set_charset($link, "utf8mb4"); }

$systems = hg_systems_fetch_catalog($link);
if ($systems === false) $systems = [];
foreach ($systems as &$row) {
	$row['system_href'] = pretty_url($link, 'dim_systems', '/systems', (int)($row['system_id'] ?? 0));
}
unset($row);

$pageSect   = null;
$pageTitle2 = "Sistemas";
?>

<?php 
	$selectAll = "&nbsp;&nbsp;Todo&nbsp;&nbsp;";
	$clearAll  = "&nbsp;Limpiar&nbsp;";
?>
<?php if (function_exists('hg_page_register_stylesheet')) { hg_page_register_stylesheet('/assets/css/hg-systems.css'); } else { ?><link rel="stylesheet" href="/assets/css/hg-systems.css"><?php } ?>
<?php include_once("app/partials/datatable_assets.php"); ?>

<h2 class="syst-table-title">Seres sobrenaturales</h2>

<div class="syst-table-wrap">
	<div class="syst-table-inner">

		<div class="dt-toolbar">
			<div class="left">

				<!-- Selector Sistema (nombre) -->
				<div class="ms-wrap" id="name-filter">
					<div class="ms-btn" id="ms-toggle-name" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false">
						<span class="ms-label">Sistema</span>
						<span class="ms-summary" id="ms-summary-name">Todos</span>
					</div>
					<div class="ms-panel" id="ms-panel-name" aria-hidden="true">
						<div id="ms-options-name"></div>
						<div class="ms-actions">
							<button type="button" id="ms-select-all-name"><?php echo $selectAll; ?></button>
							<button type="button" id="ms-clear-name"><?php echo $clearAll; ?></button>
						</div>
					</div>
				</div>

				<!-- Selector Origen -->
				<div class="ms-wrap" id="origin-filter">
					<div class="ms-btn" id="ms-toggle-origin" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false">
						<span class="ms-label">Origen</span>
						<span class="ms-summary" id="ms-summary-origin">Todos</span>
					</div>
					<div class="ms-panel" id="ms-panel-origin" aria-hidden="true">
						<div id="ms-options-origin"></div>
						<div class="ms-actions">
							<button type="button" id="ms-select-all-origin"><?php echo $selectAll; ?></button>
							<button type="button" id="ms-clear-origin"><?php echo $clearAll; ?></button>
						</div>
					</div>
				</div>

			</div>

			<div class="right" id="dt-search-slot"></div>
		</div>

		<table id="tabla-systems" class="display syst-table">
			<thead>
				<tr>
					<th>Sistema</th>
					<th>Formas</th>
					<th>Origen</th>
				</tr>
			</thead>
			<tbody></tbody>
		</table>

	</div>
</div>

<script>
function escapeHtml(text) {
	if (!text) return '';
	return String(text).replace(/[&<>"']/g, function (m) {
		return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m];
	});
}
function ynBadge(v){
	const n = Number(v);
	return n === 1 ? '<span class="badge-yes">Sí</span>' : '<span class="badge-no">No</span>';
}

$(document).ready(function () {
	const systems = <?= json_encode($systems, JSON_UNESCAPED_UNICODE) ?>;
	const tbody = $('#tabla-systems tbody');

	systems.forEach(s => {
		const sysName = escapeHtml(s.system_name);
		const href = s.system_href ? String(s.system_href) : `/systems/${s.system_id}`;
		const titulo = `<a href="${escapeHtml(href)}">${sysName}</a>`;
		const formas = ynBadge(s.system_forms);
		const origen = s.system_origin ? escapeHtml(s.system_origin) : '-';

		const row = `<tr>
			<td>${titulo}</td>
			<td>${formas}</td>
			<td>${origen}</td>
		</tr>`;
		tbody.append(row);
	});

	const dt = $('#tabla-systems').DataTable({
		pageLength: 25,
		lengthMenu: [10, 25, 50, 100],
		order: [[0, "asc"]],
		language: {
			search: "🔍 Buscar:&nbsp; ",
			lengthMenu: "Mostrar _MENU_ sistemas",
			info: "Mostrando _START_ a _END_ de _TOTAL_ sistemas",
			infoEmpty: "No hay sistemas disponibles",
			emptyTable: "No hay datos en la tabla",
			paginate: { first: "Primero", last: "Último", next: "▶", previous: "◀" }
		},
		columnDefs: [
			{ targets: [1], searchable: false }
		],
		initComplete: function(){
			$('#dt-search-slot').append($('#tabla-systems_filter'));
		}
	});

	HGDataTableFilters.create({
		table: dt,
		rows: systems,
		filters: [
			{ key: 'system', domKey: 'name', source: 'system_name', column: 0, allLabel: 'Todos', dependsOn: [] },
			{ key: 'origin', source: 'system_origin', column: 2, allLabel: 'Todos', dependsOn: ['system'] }
		]
	});
});
</script>
