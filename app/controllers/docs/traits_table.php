<?php
setMetaFromPage("Rasgos | Heaven's Gate", "Listado de rasgos y habilidades.", null, 'website');
include("app/partials/main_nav_bar.php");
header('Content-Type: text/html; charset=utf-8');
if ($link) { mysqli_set_charset($link, "utf8mb4"); }
if (!function_exists('h')) {
	function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

// Cargar rasgos con la query indicada
$query = "
	select 
		nh.id as trait_id,
		nh.pretty_id as trait_pretty_id,
		nh.name as trait_name,
		nh.kind as trait_category,
		SUBSTRING(nh.clasificacion, 5) as trait_subcategory,
		COALESCE(nb.name, '') as trait_origin
	from dim_traits nh 
		left join dim_bibliographies nb on nh.bibliography_id = nb.id
	order by
		CASE
			when nh.kind = 'Atributos' then 0
			when nh.kind = 'Talentos' then 1
			when nh.kind = 'T�cnicas' then 2
			when nh.kind = 'Conocimientos' then 3
			when nh.kind = 'Trasfondos' then 4
			else 9999
		END ASC,
		nh.clasificacion ASC,
		nh.id ASC
";
$result = mysqli_query($link, $query);
if (!$result) {
  $err = mysqli_error($link);
  if (stripos($err, "Unknown column 'nh.kind'") !== false || stripos($err, "Unknown column `nh`.`kind`") !== false) {
    $query = str_replace("nh.kind", "nh.tipo", $query);
    $result = mysqli_query($link, $query);
  }
}

$rasgos = [];
$isResult = ($result instanceof mysqli_result);
if ($result && $isResult) {
  while ($row = mysqli_fetch_assoc($result)) {
    $rasgos[] = $row;
  }
  mysqli_free_result($result);
} else {
  $err = mysqli_error($link);
  echo "<p class=\'texti\'>Error en consulta: " . h($err) . "</p>";
}

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

$rasgos = ensure_utf8($rasgos);

$pageSect = "Rasgos";
?>

<link rel="stylesheet" href="assets/vendor/datatables/jquery.dataTables.min.css">
<script src="assets/vendor/jquery/jquery-3.7.1.min.js"></script>
<script src="assets/vendor/datatables/jquery.dataTables.min.js"></script>

<style>
/* Toolbar: Multi-checks (izq) + Buscar DT (dcha) */
.dt-toolbar{
	display:flex;
	align-items:center;
	justify-content:space-between;
	gap:10px;
	margin: 0 0 10px 0;
	flex-wrap: wrap;
}
.dt-toolbar .left{
	flex: 0 0 auto;
	display:flex;
	align-items:center;
	gap:10px;
	flex-wrap: wrap;
}
.dt-toolbar .right{ flex: 1 1 auto; display:flex; justify-content:flex-end; }

/* ===== Multi-select con checks ===== */
.ms-wrap{ position:relative; width: 150px; }
.ms-btn{
	width:100%;
	box-sizing:border-box;
	cursor:pointer;
	display:flex;
	justify-content:space-between;
	align-items:center;
	background:#fff;
}
.ms-btn .ms-label{ opacity:.9; }
.ms-btn .ms-summary{ opacity:.8; margin-left:10px; }

.ms-panel{
	text-align:left;
	position:absolute;
	z-index:9999;
	top: calc(100% + 6px);
	left:0; right:0;
	border:1px solid rgb(0, 0, 153);
	background: rgb(0, 0, 102);
	border-radius:6px;
	box-shadow:0 8px 24px rgba(0,0,0,.12);
	padding:4px;
	display:none;
	max-height:280px;
	overflow:auto;
}
.ms-row{
	display:flex;
	align-items:center;
	background: rgb(0, 0, 102);
	gap:10px;
	padding:6px 8px;
	border-radius:4px;
	cursor:pointer;
	margin:0;
}
.ms-row:hover{ background: rgba(0,0,0,.04); }
.ms-row input{ width:16px; height:16px; }

.ms-row span{ text-align:left; }

.ms-actions{
	display:flex;
	gap:8px;
}
.ms-actions button{
	font-family: verdana;
	font-size: 10px;
	background-color: #000066;
	color: #fff;
	padding: 0.5em;
	border: 1px solid #000099;
}
.ms-actions button:hover{ border-color: #003399; background: #000099; color: #01b3fa; cursor: pointer; }

/* DataTables filter alineado */
.dataTables_wrapper .dataTables_filter{ margin:0 !important; }
.dataTables_wrapper .dataTables_filter label{
	display:flex;
	align-items:center;
	gap:8px;
	margin:0;
	white-space:nowrap;
}
.dataTables_wrapper .dataTables_filter input{ margin-left:0 !important; }

@media (max-width: 720px){
	.dt-toolbar{ flex-direction:column; align-items:stretch; }
	.dt-toolbar .left{ justify-content:flex-start; }
	.dt-toolbar .right{ justify-content:flex-start; }
	.ms-wrap{ width: 100%; }
}
</style>

<h2 style="text-align:right;">Rasgos</h2>

<div style="display:flex; justify-content:center; width: 100%;">
	<div style="flex: 1; max-width:640px; min-width:640px;">
		<div class="dt-toolbar">
			<div class="left">
				<div class="ms-wrap" id="filter-type">
					<div class="ms-btn" id="ms-toggle-type" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false">
						<span class="ms-label">Tipo</span>
						<span class="ms-summary" id="ms-summary-type">Todos</span>
					</div>
					<div class="ms-panel" id="ms-panel-type" aria-hidden="true">
						<div id="ms-options-type"></div>
						<div class="ms-actions">
							<button type="button" id="ms-select-all-type">Todo</button>
							<button type="button" id="ms-clear-type">Limpiar</button>
						</div>
					</div>
				</div>
				<div class="ms-wrap" id="filter-class">
					<div class="ms-btn" id="ms-toggle-class" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false">
						<span class="ms-label">Clasificaci&oacute;n</span>
						<span class="ms-summary" id="ms-summary-class">Todas</span>
					</div>
					<div class="ms-panel" id="ms-panel-class" aria-hidden="true">
						<div id="ms-options-class"></div>
						<div class="ms-actions">
							<button type="button" id="ms-select-all-class">Todo</button>
							<button type="button" id="ms-clear-class">Limpiar</button>
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
		<table id="tabla-rasgos" class="display" style="width:100%">
			<thead>
				<tr>
					<th>Nombre</th>
					<th>Tipo</th>
					<th>Clasificación</th>
					<th>Origen</th>
				</tr>
			</thead>
			<tbody></tbody>
		</table>
	</div>
</div>

<script>
$(document).ready(function () {
	const rasgos = <?= json_encode($rasgos, JSON_UNESCAPED_UNICODE) ?>;
	const tbody = $('#tabla-rasgos tbody');

	rasgos.forEach(r => {
		const traitSlug = r.trait_pretty_id || r.trait_id;
		const nombre = `<a href="/rules/traits/${escapeHtml(traitSlug)}">${escapeHtml(r.trait_name)}</a>`;
		const tipo = r.trait_category ? escapeHtml(r.trait_category) : '-';
		const clasificacion = r.trait_subcategory ? escapeHtml(r.trait_subcategory) : '-';
		const origen = r.trait_origin ? escapeHtml(r.trait_origin) : '-';

		const row = `<tr>
			<td>${nombre}</td>
			<td>${tipo}</td>
			<td>${clasificacion}</td>
			<td>${origen}</td>
		</tr>`;
		tbody.append(row);
	});

	const dt = $('#tabla-rasgos').DataTable({
		pageLength: 25,
		lengthMenu: [10, 25, 50, 100],
		order: [[0, "asc"]],
		language: {
			search: "🔍 Buscar:&nbsp;",
			lengthMenu: "Mostrar _MENU_ rasgos",
			info: "Mostrando _START_ a _END_ de _TOTAL_ rasgos",
			infoEmpty: "No hay rasgos disponibles",
			emptyTable: "No hay datos en la tabla",
			paginate: {
				first: "Primero",
				last: "Último",
				next: "▶",
				previous: "◀"
			}
		},
		initComplete: function(){
			$('#dt-search-slot').append($('#tabla-rasgos_filter'));
			const $inp = $('#tabla-rasgos_filter input');
			if ($inp.length) {
				const cs = window.getComputedStyle($inp[0]);
				['#ms-toggle-type', '#ms-toggle-class', '#ms-toggle-origin'].forEach(sel => {
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

	// ========= Generar opciones =========
	const typeSet = new Set();
	const classSet = new Set();
	const originSet = new Set();

	rasgos.forEach(r => {
		typeSet.add((r.trait_category !== null && r.trait_category !== undefined && String(r.trait_category).trim() !== '') ? String(r.trait_category).trim() : '-');
		classSet.add((r.trait_subcategory !== null && r.trait_subcategory !== undefined && String(r.trait_subcategory).trim() !== '') ? String(r.trait_subcategory).trim() : '-');
		originSet.add((r.trait_origin !== null && r.trait_origin !== undefined && String(r.trait_origin).trim() !== '') ? String(r.trait_origin).trim() : '-');
	});

	const filterConfigs = [
		{ key: 'type', column: 1, allLabel: 'Todos', values: sortValues(Array.from(typeSet)) },
		{ key: 'class', column: 2, allLabel: 'Todas', values: sortValues(Array.from(classSet)) },
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

		$opts.on('change', 'input', applyFilters);

		$('#ms-select-all-' + cfg.key).on('click', function(){
			$opts.find('input').prop('checked', true);
			applyFilters();
		});
		$('#ms-clear-' + cfg.key).on('click', function(){
			$opts.find('input').prop('checked', false);
			applyFilters();
		});
	});

	$(document).on('click', function(e){
		filterConfigs.forEach(cfg => {
			if (!$(e.target).closest('#filter-' + cfg.key).length) closePanel(cfg.key);
		});
	});

	applyFilters();

});

function escapeHtml(text) {
	if (!text) return '';
	return text.replace(/[&<>"']/g, function (m) {
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





