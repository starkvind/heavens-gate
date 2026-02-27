<?php
setMetaFromPage("Sistemas | Heaven's Gate", "Listado de sistemas y categorias disponibles.", null, 'website');
include("app/partials/main_nav_bar.php");
if ($link) { mysqli_set_charset($link, "utf8mb4"); }

/*
	Listado de Sistemas (dim_systems) con DataTables + filtros multiselect (Sistema / Origen)
	- Link a la ficha: /systems/<id>  (ajusta el parámetro p a tu ruta real)
*/

$query = "
	SELECT
		s.id AS system_id,
		s.sort_order AS system_order,
		s.name AS system_name,
		s.image_url AS system_img,
		s.forms AS system_forms,
		COALESCE(nb.name, '') AS system_origin
	FROM dim_systems s
		LEFT JOIN dim_bibliographies nb ON s.bibliography_id = nb.id
	ORDER BY s.sort_order, s.name
";
$result = mysqli_query($link, $query);

$systems = [];
while ($row = mysqli_fetch_assoc($result)) {
	$systems[] = $row;
}
mysqli_free_result($result);

$pageSect   = null;
$pageTitle2 = "Sistemas";
?>

<?php 
	$selectAll = "&nbsp;&nbsp;Todo&nbsp;&nbsp;";
	$clearAll  = "&nbsp;Limpiar&nbsp;";
?>
<link rel="stylesheet" href="/assets/css/hg-systems.css">
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
function escapeRegex(text){
	return String(text).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}
function ynBadge(v){
	const n = Number(v);
	return n === 1 ? '<span class="badge-yes">Sí</span>' : '<span class="badge-no">No</span>';
}

$(document).ready(function () {
	const systems = <?= json_encode($systems, JSON_UNESCAPED_UNICODE) ?>;
	const tbody = $('#tabla-systems tbody');

	// Pintamos filas
	systems.forEach(s => {
		const sysName = escapeHtml(s.system_name);
		// AJUSTA p=... a tu página real (esto es un ejemplo)
		const titulo = `<a href="/systems/${s.system_id}">${sysName}</a>`;
		const formas = ynBadge(s.system_forms);
		const origen = s.system_origin ? escapeHtml(s.system_origin) : '-';

		const row = `<tr>
			<td>${titulo}</td>
			<td>${formas}</td>
			<td>${origen}</td>
		</tr>`;
		tbody.append(row);
	});

	// DataTable
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
			{ targets: [1], searchable: false } // badge
		],
		initComplete: function(){
			// mover buscador
			$('#dt-search-slot').append($('#tabla-systems_filter'));

			// Copiar estilo del input de buscar a los botones multiselect
			const $inp = $('#tabla-systems_filter input');
			const cs = window.getComputedStyle($inp[0]);

			['#ms-toggle-name', '#ms-toggle-origin'].forEach(sel => {
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
	   MULTISELECT SISTEMA (col 0)
	   ========================================================= */
	const $panelName   = $('#ms-panel-name');
	const $toggleName  = $('#ms-toggle-name');
	const $optsName    = $('#ms-options-name');
	const $summaryName = $('#ms-summary-name');

	const namesSet = new Set();
	systems.forEach(s => {
		const n = (s.system_name && String(s.system_name).trim() !== '') ? String(s.system_name).trim() : '-';
		namesSet.add(n);
	});
	const names = Array.from(namesSet).sort((a,b)=>a.localeCompare(b,'es'));

	names.forEach(n => {
		const safe = escapeHtml(n);
		$optsName.append(`
			<label class="ms-row">
				<input type="checkbox" class="name-item" value="${safe}" checked>
				<span>${safe}</span>
			</label>
		`);
	});

	function openName(){ $panelName.show().attr('aria-hidden','false'); $toggleName.attr('aria-expanded','true'); }
	function closeName(){ $panelName.hide().attr('aria-hidden','true'); $toggleName.attr('aria-expanded','false'); }
	function toggleName(){ $panelName.is(':visible') ? closeName() : openName(); }

	$toggleName.on('click', toggleName);
	$toggleName.on('keydown', (e)=>{ if(e.key==='Enter' || e.key===' ') { e.preventDefault(); toggleName(); } });

	function getSelectedNames(){
		const selected = $('#ms-options-name .name-item:checked').map(function(){ return $(this).val(); }).get();
		return selected.length ? selected : null;
	}
	function updateSummaryNames(selected){
		if (selected === null) { $summaryName.text('Todos'); return; }
		if (selected.length === 1) $summaryName.text(selected[0]);
		else $summaryName.text(selected.length + ' selecc.');
	}

	/* =========================================================
	/* =========================================================
	   MULTISELECT ORIGEN (col 2)
	   ========================================================= */
	const $panelOrigin   = $('#ms-panel-origin');
	const $toggleOrigin  = $('#ms-toggle-origin');
	const $optsOrigin    = $('#ms-options-origin');
	const $summaryOrigin = $('#ms-summary-origin');

	const originSet = new Set();
	systems.forEach(s => {
		const o = (s.system_origin && String(s.system_origin).trim() !== '') ? String(s.system_origin).trim() : '-';
		originSet.add(o);
	});
	const origins = Array.from(originSet).sort((a,b)=>a.localeCompare(b,'es'));

	origins.forEach(o => {
		const safe = escapeHtml(o);
		$optsOrigin.append(`
			<label class="ms-row">
				<input type="checkbox" class="origin-item" value="${safe}" checked>
				<span>${safe}</span>
			</label>
		`);
	});

	function openOrigin(){ $panelOrigin.show().attr('aria-hidden','false'); $toggleOrigin.attr('aria-expanded','true'); }
	function closeOrigin(){ $panelOrigin.hide().attr('aria-hidden','true'); $toggleOrigin.attr('aria-expanded','false'); }
	function toggleOrigin(){ $panelOrigin.is(':visible') ? closeOrigin() : openOrigin(); }

	$toggleOrigin.on('click', toggleOrigin);
	$toggleOrigin.on('keydown', (e)=>{ if(e.key==='Enter' || e.key===' ') { e.preventDefault(); toggleOrigin(); } });

	function getSelectedOrigins(){
		const selected = $('#ms-options-origin .origin-item:checked').map(function(){ return $(this).val(); }).get();
		return selected.length ? selected : null;
	}
	function updateSummaryOrigins(selected){
		if (selected === null) { $summaryOrigin.text('Todos'); return; }
		if (selected.length === 1) $summaryOrigin.text(selected[0]);
		else $summaryOrigin.text(selected.length + ' selecc.');
	}

	/* =========================================================
	   APLICAR FILTROS COMBINADOS (Sistema + Origen)
	   ========================================================= */
	function applyFilters(){
		// Sistema (col 0) => OJO: la columna 0 contiene HTML (<a>),
		// así que filtramos por texto exacto con regex "contiene"
		const selNames = getSelectedNames();
		updateSummaryNames(selNames);

		if (selNames === null) {
			dt.column(0).search('', true, false);
		} else {
			const pat = '(?:' + selNames.map(s => escapeRegex(s)).join('|') + ')';
			dt.column(0).search(pat, true, false);
		}

		// Origen (col 2)
		const selOrigins = getSelectedOrigins();
		updateSummaryOrigins(selOrigins);

		if (selOrigins === null) {
			dt.column(2).search('', true, false);
		} else {
			const pat = '(?:' + selOrigins.map(s => escapeRegex(s)).join('|') + ')';
			dt.column(2).search(pat, true, false);
		}

		dt.draw();
	}

	// Eventos

	// Eventos: checks
	$optsName.on('change', '.name-item', applyFilters);
	$optsOrigin.on('change', '.origin-item', applyFilters);

	// Botones
	$('#ms-select-all-name').on('click', function(){
		$('#ms-options-name .name-item').prop('checked', true);
		applyFilters();
	});
	$('#ms-clear-name').on('click', function(){
		$('#ms-options-name .name-item').prop('checked', false);
		applyFilters();
	});
	$('#ms-select-all-origin').on('click', function(){
		$('#ms-options-origin .origin-item').prop('checked', true);
		applyFilters();
	});
	$('#ms-clear-origin').on('click', function(){
		$('#ms-options-origin .origin-item').prop('checked', false);
		applyFilters();
	});

	// Cierre al click fuera
	$(document).on('click', function(e){
		if (!$(e.target).closest('#name-filter').length) closeName();
		if (!$(e.target).closest('#origin-filter').length) closeOrigin();
	});

	// Estado inicial
	updateSummaryNames(null);
	updateSummaryOrigins(null);
});
</script>



