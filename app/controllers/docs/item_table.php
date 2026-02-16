<?php
setMetaFromPage("Inventario | Heaven's Gate", "Listado de objetos y artefactos.", null, 'website');
include("app/partials/main_nav_bar.php");
header('Content-Type: text/html; charset=utf-8');
if ($link) { mysqli_set_charset($link, "utf8mb4"); }

// Cargar inventario con la query indicada
$query = "
	SELECT
		no2.id as item_id,
		no2.pretty_id as item_pretty_id,
		no2.name as item_name,
		no2.img as item_img,
		nto.name as item_category,
		nto.pretty_id as item_type_pretty,
		nto.id as item_type_id,
		COALESCE(nb.name, '') as item_origin
	FROM fact_items no2
		left join dim_item_types nto on no2.item_type_id = nto.id 
		left join dim_bibliographies nb on no2.bibliography_id = nb.id
	order by
		nto.name ASC,
		no2.name ASC
";
$result = mysqli_query($link, $query);

$items = [];
while ($row = mysqli_fetch_assoc($result)) {
	$items[] = $row;
}
mysqli_free_result($result);

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

$items = ensure_utf8($items);

$pageSect = "Inventario";
?>

<link rel="stylesheet" href="/assets/vendor/datatables/jquery.dataTables.min.css">
<script src="/assets/vendor/jquery/jquery-3.7.1.min.js"></script>
<script src="/assets/vendor/datatables/jquery.dataTables.min.js"></script>

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

.item-thumb{ width:24px; height:24px; object-fit:contain; display:inline-block; vertical-align:middle; position:relative; z-index:1; }
.item-icon{ display:inline-flex; align-items:center; justify-content:center; width:24px; height:24px; margin-right:6px; position:relative; }
.item-icon::before{ content:""; position:absolute; left:50%; top:50%; width:18px; height:18px; border-radius:50%; background:#001188; opacity:.65; transform:translate(-50%,-50%); }
.item-cell{ display:inline-flex; align-items:center; gap:6px; }
</style>

<h2 style="text-align:right;">Inventario</h2>

<div style="display:flex; justify-content:center; width: 100%;">
	<div style="flex: 1; max-width:640px; min-width:640px;">
		<div class="dt-toolbar">
			<div class="left">
				<div class="ms-wrap" id="filter-category">
					<div class="ms-btn" id="ms-toggle-category" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false">
						<span class="ms-label">Categor&iacute;a</span>
						<span class="ms-summary" id="ms-summary-category">Todas</span>
					</div>
					<div class="ms-panel" id="ms-panel-category" aria-hidden="true">
						<div id="ms-options-category"></div>
						<div class="ms-actions">
							<button type="button" id="ms-select-all-category">Todo</button>
							<button type="button" id="ms-clear-category">Limpiar</button>
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
		<table id="tabla-inventario" class="display" style="width:100%">
			<thead>
				<tr>
					<th>Objeto</th>
					<th>Categor&iacute;a</th>
					<th>Origen</th>
				</tr>
			</thead>
			<tbody></tbody>
		</table>
	</div>
</div>

<script>
$(document).ready(function () {
	const items = <?= json_encode($items, JSON_UNESCAPED_UNICODE) ?>;
	const tbody = $('#tabla-inventario tbody');

	items.forEach(i => {
		const itemSlug = i.item_pretty_id || i.item_id;
		const typeSlug = i.item_type_pretty || i.item_type_id || 'tipo';
		const nombre = `<a href="/inventory/${escapeHtml(typeSlug)}/${escapeHtml(itemSlug)}">${escapeHtml(i.item_name)}</a>`;
		const imgSrc = i.item_img ? i.item_img : '/img/inv/no-photo.gif';
		const img = `<img src="${escapeHtml(imgSrc)}" alt="${escapeHtml(i.item_name)}" class="item-thumb">`;
		const categoria = i.item_category ? escapeHtml(i.item_category) : '-';
		const origen = i.item_origin ? escapeHtml(i.item_origin) : '-';

		const row = `<tr>
			<td><span class="item-cell"><span class="item-icon">${img}</span>${nombre}</span></td>
			<td>${categoria}</td>
			<td>${origen}</td>
		</tr>`;
		tbody.append(row);
	});

	const dt = $('#tabla-inventario').DataTable({
		pageLength: 25,
		lengthMenu: [10, 25, 50, 100],
		order: [[0, "asc"]],
		language: {
			search: "&#128269; Buscar:&nbsp;",
			lengthMenu: "Mostrar _MENU_ objetos",
			info: "Mostrando _START_ a _END_ de _TOTAL_ objetos",
			infoEmpty: "No hay objetos disponibles",
			emptyTable: "No hay datos en la tabla",
			paginate: {
				first: "Primero",
				last: "&Uacute;ltimo",
				next: "&#9654;",
				previous: "&#9664;"
			}
		},
		initComplete: function(){
			$('#dt-search-slot').append($('#tabla-inventario_filter'));
			const $inp = $('#tabla-inventario_filter input');
			if ($inp.length) {
				const cs = window.getComputedStyle($inp[0]);
				['#ms-toggle-category', '#ms-toggle-origin'].forEach(sel => {
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
	const categorySet = new Set();
	const originSet = new Set();

	items.forEach(i => {
		categorySet.add((i.item_category !== null && i.item_category !== undefined && String(i.item_category).trim() !== '') ? String(i.item_category).trim() : '-');
		originSet.add((i.item_origin !== null && i.item_origin !== undefined && String(i.item_origin).trim() !== '') ? String(i.item_origin).trim() : '-');
	});

	const filterConfigs = [
		{ key: 'category', column: 1, allLabel: 'Todas', values: sortValues(Array.from(categorySet)) },
		{ key: 'origin', column: 2, allLabel: 'Todos', values: sortValues(Array.from(originSet)) },
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
