<?php
setMetaFromPage("Disciplinas | Heaven's Gate", "Listado completo de disciplinas.", null, 'website');
include("app/partials/main_nav_bar.php");

// Cargar disciplinas con la query indicada
$query = "
	select
		d.id as disc_id,
		d.pretty_id as disc_pretty_id,
		d.name as disc_name,
		ddt.name as disc_type,
		d.level as disc_level,
		d.attribute as disc_roll_attribute,
		d.skill as disc_roll_skill,
		nb.name as disc_origin
	from fact_discipline_powers d
		left join dim_discipline_types ddt on d.disc = ddt.id
		left join dim_bibliographies nb on d.bibliography_id = nb.id
	order by d.bibliography_id, d.disc, d.level, d.name
";
$result = mysqli_query($link, $query);

$disciplinas = [];
while ($row = mysqli_fetch_assoc($result)) {
	$disciplinas[] = $row;
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

$disciplinas = ensure_utf8($disciplinas);

$pageSect = "Lista de Disciplinas";
?>

<link rel="stylesheet" href="/assets/css/hg-powers.css">
<?php include_once("app/partials/datatable_assets.php"); ?>

<h2 class="pwrs-table-title">Lista de Disciplinas</h2>

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

				<div class="ms-wrap" id="filter-level">
					<div class="ms-btn" id="ms-toggle-level" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false">
						<span class="ms-label">Nivel</span>
						<span class="ms-summary" id="ms-summary-level">Todos</span>
					</div>
					<div class="ms-panel" id="ms-panel-level" aria-hidden="true">
						<div id="ms-options-level"></div>
						<div class="ms-actions">
							<button type="button" id="ms-select-all-level">Todo</button>
							<button type="button" id="ms-clear-level">Limpiar</button>
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
    <table id="tabla-disciplinas" class="display pwrs-table">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Disciplina</th>
                <th>Nivel</th>
                <th>Tirada</th>
                <th>Origen</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
  </div>
</div>

<script>
$(document).ready(function () {
	const disciplinas = <?= json_encode($disciplinas, JSON_UNESCAPED_UNICODE) ?>;
	const tbody = $('#tabla-disciplinas tbody');

	disciplinas.forEach(d => {
		const discSlug = d.disc_pretty_id || d.disc_id;
		const nombre = `<a href="/powers/discipline/${escapeHtml(discSlug)}" target="_blank">${escapeHtml(d.disc_name)}</a>`;

		const tipo = d.disc_type ? escapeHtml(d.disc_type) : '-';
		const nivel = (d.disc_level !== null && d.disc_level !== undefined && d.disc_level !== '') ? escapeHtml(String(d.disc_level)) : '-';
		const attr = d.disc_roll_attribute ? escapeHtml(d.disc_roll_attribute) : '';
		const skill = d.disc_roll_skill ? escapeHtml(d.disc_roll_skill) : '';
		const tirada = (attr || skill) ? [attr, skill].filter(Boolean).join(' + ') : '-';
		const origen = d.disc_origin ? escapeHtml(d.disc_origin) : '-';

		const row = `<tr>
			<td>${nombre}</td>
			<td>${tipo}</td>
			<td>${nivel}</td>
			<td>${tirada}</td>
			<td>${origen}</td>
		</tr>`;
		tbody.append(row);
	});

	const dt = $('#tabla-disciplinas').DataTable({
		pageLength: 25,
		lengthMenu: [10, 25, 50, 100],
		order: [[0, "asc"]],
		language: {
			search: "&#128269; Buscar:",
			lengthMenu: "Mostrar _MENU_ disciplinas",
			info: "Mostrando _START_ a _END_ de _TOTAL_ disciplinas",
			infoEmpty: "No hay disciplinas disponibles",
			emptyTable: "No hay datos en la tabla",
			paginate: {
				first: "Primero",
				last: "&Uacute;ltimo",
				next: "&#9654;",
				previous: "&#9664;"
			}
		},
		initComplete: function(){
			$('#dt-search-slot').append($('#tabla-disciplinas_filter'));
			const $inp = $('#tabla-disciplinas_filter input');
			if ($inp.length) {
				const cs = window.getComputedStyle($inp[0]);
				['#ms-toggle-type', '#ms-toggle-level', '#ms-toggle-origin'].forEach(sel => {
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
	const levelSet = new Set();
	const originSet = new Set();

	disciplinas.forEach(d => {
		typeSet.add((d.disc_type !== null && d.disc_type !== undefined && String(d.disc_type).trim() !== '') ? String(d.disc_type).trim() : '-');
		levelSet.add((d.disc_level !== null && d.disc_level !== undefined && String(d.disc_level).trim() !== '') ? String(d.disc_level).trim() : '-');
		originSet.add((d.disc_origin !== null && d.disc_origin !== undefined && String(d.disc_origin).trim() !== '') ? String(d.disc_origin).trim() : '-');
	});

	const filterConfigs = [
		{ key: 'type', column: 1, allLabel: 'Todos', values: sortValues(Array.from(typeSet)) },
		{ key: 'level', column: 2, allLabel: 'Todos', values: sortValues(Array.from(levelSet)) },
		{ key: 'origin', column: 4, allLabel: 'Todos', values: sortValues(Array.from(originSet)) },
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



