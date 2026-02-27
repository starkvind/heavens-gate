<?php
setMetaFromPage("Totems | Heaven's Gate", "Listado completo de totems.", null, 'website');
include("app/partials/main_nav_bar.php");

// Cargar tótems con la query indicada
$query = "
	select 
		nt.id as totem_id,
		nt.pretty_id as totem_pretty_id,
		nt.name as totem_name,
		ntt.name as totem_type,
		nt.cost as totem_cost,
		nb.name as totem_origin
	from dim_totems nt
		left join dim_totem_types ntt on ntt.id = nt.totem_type_id
		left join dim_bibliographies nb on nt.bibliography_id = nb.id
	order by nt.bibliography_id, nt.cost
";
$result = mysqli_query($link, $query);

$totems = [];
while ($row = mysqli_fetch_assoc($result)) {
	$totems[] = $row;
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

$totems = ensure_utf8($totems);

$pageSect = "Lista de Tótems";
?>

<link rel="stylesheet" href="/assets/css/hg-powers.css">
<?php include_once("app/partials/datatable_assets.php"); ?>

<h2 class="pwrs-table-title">Lista de Tótems</h2>

<div class="pwrs-table-wrap">
  <div class="pwrs-table-inner">
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

			<div class="ms-wrap" id="filter-cost">
				<div class="ms-btn" id="ms-toggle-cost" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false">
					<span class="ms-label">Coste</span>
					<span class="ms-summary" id="ms-summary-cost">Todos</span>
				</div>
				<div class="ms-panel" id="ms-panel-cost" aria-hidden="true">
					<div id="ms-options-cost"></div>
					<div class="ms-actions">
						<button type="button" id="ms-select-all-cost">Todo</button>
						<button type="button" id="ms-clear-cost">Limpiar</button>
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
    <table id="tabla-totems" class="display pwrs-table">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Tipo</th>
                <th>Coste</th>
                <th>Origen</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
  </div>
</div>

<script>
$(document).ready(function () {
	const totems = <?= json_encode($totems, JSON_UNESCAPED_UNICODE) ?>;
	const tbody = $('#tabla-totems tbody');

	totems.forEach(t => {
		const totemSlug = t.totem_pretty_id || t.totem_id;
		const nombre = `<a href="/powers/totem/${escapeHtml(totemSlug)}" target="_blank">${escapeHtml(t.totem_name)}</a>`;
		const tipo   = t.totem_type ? escapeHtml(t.totem_type) : '-';
		const coste  = (t.totem_cost !== null && t.totem_cost !== undefined && t.totem_cost !== '') ? escapeHtml(String(t.totem_cost)) : '-';
		const origen = t.totem_origin ? escapeHtml(t.totem_origin) : '-';

		const row = `<tr>
			<td>${nombre}</td>
			<td>${tipo}</td>
			<td>${coste}</td>
			<td>${origen}</td>
		</tr>`;
		tbody.append(row);
	});

	const dt = $('#tabla-totems').DataTable({
		pageLength: 25,
		lengthMenu: [10, 25, 50, 100],
		order: [[0, "asc"]],
		language: {
			search: "🔍 Buscar:&nbsp;",
			lengthMenu: "Mostrar _MENU_ tótems",
			info: "Mostrando _START_ a _END_ de _TOTAL_ tótems",
			infoEmpty: "No hay tótems disponibles",
			emptyTable: "No hay datos en la tabla",
			paginate: {
				first: "Primero",
				last: "Último",
				next: "▶",
				previous: "◀"
			}
		},
		initComplete: function(){
			$('#dt-search-slot').append($('#tabla-totems_filter'));
			const $inp = $('#tabla-totems_filter input');
			if ($inp.length) {
				const cs = window.getComputedStyle($inp[0]);
				['#ms-toggle-type', '#ms-toggle-cost', '#ms-toggle-origin'].forEach(sel => {
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
	const costSet = new Set();
	const originSet = new Set();

	totems.forEach(t => {
		typeSet.add((t.totem_type !== null && t.totem_type !== undefined && String(t.totem_type).trim() !== '') ? String(t.totem_type).trim() : '-');
		costSet.add((t.totem_cost !== null && t.totem_cost !== undefined && String(t.totem_cost).trim() !== '') ? String(t.totem_cost).trim() : '-');
		originSet.add((t.totem_origin !== null && t.totem_origin !== undefined && String(t.totem_origin).trim() !== '') ? String(t.totem_origin).trim() : '-');
	});

	const filterConfigs = [
		{ key: 'type', column: 1, allLabel: 'Todos', values: sortValues(Array.from(typeSet)) },
		{ key: 'cost', column: 2, allLabel: 'Todos', values: sortValues(Array.from(costSet)) },
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
		return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m];
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



