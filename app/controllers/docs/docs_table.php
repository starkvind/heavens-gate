<?php
setMetaFromPage("Documentos | Heaven's Gate", "Listado de documentos de la campa?a.", null, 'website');
include("app/partials/main_nav_bar.php");

// Cargar documentos con la query indicada
$query = "
	select
		d2.id as document_id, 
		d2.pretty_id as document_pretty_id,
		d2.title as document_name,
		d.kind as document_category,
		COALESCE(nb.name, '') as document_origin
	from fact_docs d2
		left join dim_doc_categories d on d2.section_id = d.id
		left join dim_bibliographies nb on d2.bibliography_id = nb.id
	order by d.sort_order
";
$result = mysqli_query($link, $query);

$documentos = [];
while ($row = mysqli_fetch_assoc($result)) {
	$documentos[] = $row;
}
mysqli_free_result($result);

$pageSect = "Documentación";
?>
<link rel="stylesheet" href="/assets/css/hg-docs.css">


<?php 
	$selectAll = "&nbsp;&nbsp;Todo&nbsp;&nbsp;";
	$clearAll = "&nbsp;Limpiar&nbsp;";
?>
<?php include_once("app/partials/datatable_assets.php"); ?>

<h2 class="docs-table-title">Documentación</h2>

<div class="docs-table-wrap">
	<div class="docs-table-inner">
		<!-- Toolbar -->
		<div class="dt-toolbar">
			<div class="left">

				<!-- Selector Categorías -->
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

				<!-- Selector Origen -->
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
			<!-- Slot del buscador de DataTables -->
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
function escapeRegex(text){
	return String(text).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

$(document).ready(function () {
	const documentos = <?= json_encode($documentos, JSON_UNESCAPED_UNICODE) ?>;
	const tbody = $('#tabla-documentos tbody');

	// Pintamos filas
	documentos.forEach(d => {
		const docSlug = d.document_pretty_id || d.document_id;
		const titulo = `<a href="/documents/${escapeHtml(docSlug)}">${escapeHtml(d.document_name)}</a>`;
		const categoria = d.document_category ? escapeHtml(d.document_category) : '-';
		const origen = d.document_origin ? escapeHtml(d.document_origin) : '-';

		const row = `<tr>
			<td>${titulo}</td>
			<td>${categoria}</td>
			<td>${origen}</td>
		</tr>`;
		tbody.append(row);
	});

	// DataTable
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
			// Mover buscador a la derecha
			$('#dt-search-slot').append($('#tabla-documentos_filter'));

			// Copiar estilo exacto del input de buscar a los “botones” de los multiselects
			const $inp = $('#tabla-documentos_filter input');
			const cs = window.getComputedStyle($inp[0]);

			['#ms-toggle-cat', '#ms-toggle-org'].forEach(sel => {
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
	});

	/* =========================================================
	   MULTISELECT CATEGORÍAS (columna 1)
	   ========================================================= */
	const $panelCat   = $('#ms-panel-cat');
	const $toggleCat  = $('#ms-toggle-cat');
	const $optsCat    = $('#ms-options-cat');
	const $summaryCat = $('#ms-summary-cat');

	const cats = new Set();
	documentos.forEach(d => {
		const c = (d.document_category && String(d.document_category).trim() !== '') ? String(d.document_category).trim() : '-';
		cats.add(c);
	});
	const categorias = Array.from(cats).sort((a,b)=>a.localeCompare(b,'es'));

	categorias.forEach(cat => {
		const safe = escapeHtml(cat);
		$optsCat.append(`
			<label class="ms-row">
				<input type="checkbox" class="cat-item" value="${safe}" checked>
				<span>${safe}</span>
			</label>
		`);
	});

	function openCat(){ $panelCat.show().attr('aria-hidden','false'); $toggleCat.attr('aria-expanded','true'); }
	function closeCat(){ $panelCat.hide().attr('aria-hidden','true'); $toggleCat.attr('aria-expanded','false'); }
	function toggleCat(){ $panelCat.is(':visible') ? closeCat() : openCat(); }

	$toggleCat.on('click', toggleCat);
	$toggleCat.on('keydown', (e)=>{ if(e.key==='Enter' || e.key===' ') { e.preventDefault(); toggleCat(); } });

	function getSelectedCats(){
		const selected = $('#ms-options-cat .cat-item:checked').map(function(){ return $(this).val(); }).get();
		return selected.length ? selected : null; // null => todas
	}
	function updateSummaryCats(selected){
		if (selected === null) { $summaryCat.text('Todas'); return; }
		if (selected.length === 1) $summaryCat.text(selected[0]);
		else $summaryCat.text(selected.length + ' selecc.');
	}

	/* =========================================================
	   MULTISELECT ORIGEN (columna 2)
	   ========================================================= */
	const $panelOrg   = $('#ms-panel-org');
	const $toggleOrg  = $('#ms-toggle-org');
	const $optsOrg    = $('#ms-options-org');
	const $summaryOrg = $('#ms-summary-org');

	const orgs = new Set();
	documentos.forEach(d => {
		const o = (d.document_origin && String(d.document_origin).trim() !== '') ? String(d.document_origin).trim() : '-';
		orgs.add(o);
	});
	const origenes = Array.from(orgs).sort((a,b)=>a.localeCompare(b,'es'));

	origenes.forEach(org => {
		const safe = escapeHtml(org);
		$optsOrg.append(`
			<label class="ms-row">
				<input type="checkbox" class="org-item" value="${safe}" checked>
				<span>${safe}</span>
			</label>
		`);
	});

	function openOrg(){ $panelOrg.show().attr('aria-hidden','false'); $toggleOrg.attr('aria-expanded','true'); }
	function closeOrg(){ $panelOrg.hide().attr('aria-hidden','true'); $toggleOrg.attr('aria-expanded','false'); }
	function toggleOrg(){ $panelOrg.is(':visible') ? closeOrg() : openOrg(); }

	$toggleOrg.on('click', toggleOrg);
	$toggleOrg.on('keydown', (e)=>{ if(e.key==='Enter' || e.key===' ') { e.preventDefault(); toggleOrg(); } });

	function getSelectedOrgs(){
		const selected = $('#ms-options-org .org-item:checked').map(function(){ return $(this).val(); }).get();
		return selected.length ? selected : null; // null => todos
	}
	function updateSummaryOrgs(selected){
		if (selected === null) { $summaryOrg.text('Todos'); return; }
		if (selected.length === 1) $summaryOrg.text(selected[0]);
		else $summaryOrg.text(selected.length + ' selecc.');
	}

	/* =========================================================
	   APLICAR FILTROS COMBINADOS (Categoría + Origen)
	   ========================================================= */
	function applyFilters(){
		// Categorías
		const selCats = getSelectedCats();
		updateSummaryCats(selCats);

		if (selCats === null) {
			dt.column(1).search('', true, false);
		} else {
			const pat = '^(?:' + selCats.map(s => escapeRegex(s)).join('|') + ')$';
			dt.column(1).search(pat, true, false);
		}

		// Orígenes
		const selOrgs = getSelectedOrgs();
		updateSummaryOrgs(selOrgs);

		if (selOrgs === null) {
			dt.column(2).search('', true, false);
		} else {
			const pat = '^(?:' + selOrgs.map(s => escapeRegex(s)).join('|') + ')$';
			dt.column(2).search(pat, true, false);
		}

		dt.draw();
	}

	// Eventos: checks
	$optsCat.on('change', '.cat-item', applyFilters);
	$optsOrg.on('change', '.org-item', applyFilters);

	// Botones categorías
	$('#ms-select-all-cat').on('click', function(){
		$('#ms-options-cat .cat-item').prop('checked', true);
		applyFilters();
	});
	$('#ms-clear-cat').on('click', function(){
		$('#ms-options-cat .cat-item').prop('checked', false);
		applyFilters();
	});

	// Botones origen
	$('#ms-select-all-org').on('click', function(){
		$('#ms-options-org .org-item').prop('checked', true);
		applyFilters();
	});
	$('#ms-clear-org').on('click', function(){
		$('#ms-options-org .org-item').prop('checked', false);
		applyFilters();
	});

	// Cierre al click fuera (para ambos)
	$(document).on('click', function(e){
		if (!$(e.target).closest('#cat-filter').length) closeCat();
		if (!$(e.target).closest('#org-filter').length) closeOrg();
	});

	// Estado inicial
	updateSummaryCats(null);
	updateSummaryOrgs(null);
});
</script>



