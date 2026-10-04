<?php
setMetaFromPage("Documentos | Heaven's Gate", "Listado de documentos de la campa?a.", null, 'website');
require_once(__DIR__ . '/../../domains/documents/queries.php');
include("app/partials/main_nav_bar.php");

$documentos = hg_documents_fetch_catalog($link) ?? [];

$pageSect = "Documentación";
?>
<?php if (function_exists('hg_page_register_stylesheet')) { hg_page_register_stylesheet('/assets/css/hg-docs.css'); } else { ?><link rel="stylesheet" href="/assets/css/hg-docs.css"><?php } ?>

<?php 
	$selectAll = "&nbsp;&nbsp;Todo&nbsp;&nbsp;";
	$clearAll = "&nbsp;Limpiar&nbsp;";
?>
<?php include_once("app/partials/datatable_assets.php"); ?>

<h2 class="docs-table-title">Documentación</h2>

<div class="docs-table-wrap">
	<div class="docs-table-inner">
		<div class="dt-toolbar">
			<div class="left">
				<div class="ms-wrap" id="cat-filter">
					<div class="ms-btn" id="ms-toggle-cat" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false">
						<span class="ms-label">Categorías</span>
						<span class="ms-summary" id="ms-summary-cat">Todas</span>
					</div>
					<div class="ms-panel" id="ms-panel-cat" aria-hidden="true">
						<div id="ms-options-cat"></div>
						<div class="ms-actions">
							<button type="button" id="ms-select-all-cat"><?php echo $selectAll; ?></button>
							<button type="button" id="ms-clear-cat"><?php echo $clearAll; ?></button>
						</div>
					</div>
				</div>

				<div class="ms-wrap" id="org-filter">
					<div class="ms-btn" id="ms-toggle-org" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false">
						<span class="ms-label">Origen</span>
						<span class="ms-summary" id="ms-summary-org">Todos</span>
					</div>
					<div class="ms-panel" id="ms-panel-org" aria-hidden="true">
						<div id="ms-options-org"></div>
						<div class="ms-actions">
							<button type="button" id="ms-select-all-org"><?php echo $selectAll; ?></button>
							<button type="button" id="ms-clear-org"><?php echo $clearAll; ?></button>
						</div>
					</div>
				</div>
			</div>
			<div class="right" id="dt-search-slot"></div>
		</div>		
		<table id="tabla-documentos" class="display docs-table">
			<thead>
				<tr>
					<th>Título</th>
					<th>Categoría</th>
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

$(document).ready(function () {
	const documentos = <?= json_encode($documentos, JSON_UNESCAPED_UNICODE) ?>;
	const tbody = $('#tabla-documentos tbody');

	documentos.forEach(d => {
		const docSlug = d.document_pretty_id || d.document_id;
		const titulo = `<a href="/documents/${escapeHtml(docSlug)}">${escapeHtml(d.document_name)}</a>`;
		const categoria = d.document_category ? escapeHtml(d.document_category) : '-';
		const origen = d.document_origin ? escapeHtml(d.document_origin) : '-';

		tbody.append(`<tr>
			<td>${titulo}</td>
			<td>${categoria}</td>
			<td>${origen}</td>
		</tr>`);
	});

	const dt = $('#tabla-documentos').DataTable({
		pageLength: 25,
		lengthMenu: [10, 25, 50, 100],
		order: [[0, "asc"]],
		language: {
			search: "🔍 Buscar:&nbsp; ",
			lengthMenu: "Mostrar _MENU_ documentos",
			info: "Mostrando _START_ a _END_ de _TOTAL_ documentos",
			infoEmpty: "No hay documentos disponibles",
			emptyTable: "No hay datos en la tabla",
			paginate: {
				first: "Primero",
				last: "&Uacute;ltimo",
				next: "&#9654;",
				previous: "&#9664;"
			}
		},
		initComplete: function(){
			$('#dt-search-slot').append($('#tabla-documentos_filter'));
		}
	});

	HGDataTableFilters.create({
		table: dt,
		rows: documentos,
		filters: [
			{ key: 'category', domKey: 'cat', source: 'document_category', column: 1, allLabel: 'Todas', dependsOn: [] },
			{ key: 'origin', domKey: 'org', source: 'document_origin', column: 2, allLabel: 'Todos', dependsOn: ['category'] }
		]
	});
});
</script>
