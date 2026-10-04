<?php
require_once __DIR__ . '/../../domains/powers/queries.php';

setMetaFromPage("Dones | Heaven's Gate", "Listado completo de dones.", null, 'website');
include("app/partials/main_nav_bar.php");

$dones = hg_powers_fetch_catalog($link, 'gifts');
if ($dones === false) $dones = [];

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

$dones = ensure_utf8($dones);

$pageSect = "Lista de Dones";
?>

<?php if (function_exists('hg_page_register_stylesheet')) { hg_page_register_stylesheet('/assets/css/hg-powers.css'); } else { ?><link rel="stylesheet" href="/assets/css/hg-powers.css"><?php } ?>
<?php include_once("app/partials/datatable_assets.php"); ?>
<?php include_once("app/partials/power_catalog_tabs.php"); ?>

<?php hg_render_power_catalog_tabs('gifts', 'table'); ?>
<h2 class="pwrs-table-title">Lista de Dones</h2>

<div class="pwrs-table-wrap">
  <div class="pwrs-table-inner">
	<div class="dt-toolbar">
		<div class="left">
			<div class="ms-wrap" id="filter-fera">
				<div class="ms-btn" id="ms-toggle-fera" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false">
					<span class="ms-label">F&ecirc;ra</span>
					<span class="ms-summary" id="ms-summary-fera">Todos</span>
				</div>
				<div class="ms-panel" id="ms-panel-fera" aria-hidden="true">
					<div id="ms-options-fera"></div>
					<div class="ms-actions">
						<button type="button" id="ms-select-all-fera">Todo</button>
						<button type="button" id="ms-clear-fera">Limpiar</button>
					</div>
				</div>
			</div>

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

			<div class="ms-wrap" id="filter-group">
				<div class="ms-btn" id="ms-toggle-group" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false">
					<span class="ms-label">Grupo</span>
					<span class="ms-summary" id="ms-summary-group">Todos</span>
				</div>
				<div class="ms-panel" id="ms-panel-group" aria-hidden="true">
					<div id="ms-options-group"></div>
					<div class="ms-actions">
						<button type="button" id="ms-select-all-group">Todo</button>
						<button type="button" id="ms-clear-group">Limpiar</button>
					</div>
				</div>
			</div>
		</div>
		<div class="left">
			<div class="ms-wrap" id="filter-rank">
				<div class="ms-btn" id="ms-toggle-rank" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false">
					<span class="ms-label">Rango</span>
					<span class="ms-summary" id="ms-summary-rank">Todos</span>
				</div>
				<div class="ms-panel" id="ms-panel-rank" aria-hidden="true">
					<div id="ms-options-rank"></div>
					<div class="ms-actions">
						<button type="button" id="ms-select-all-rank">Todo</button>
						<button type="button" id="ms-clear-rank">Limpiar</button>
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
    <table id="tabla-dones" class="display pwrs-table">
        <thead>
            <tr>
                <!-- <th>ID</th> -->
                <th>Nombre</th>
				<th>Fêra</th>
                <th>Tipo</th>
                <th>Grupo</th>
                <th>Rango</th>
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
	const dones = <?= json_encode($dones, JSON_UNESCAPED_UNICODE) ?>;
	const tbody = $('#tabla-dones tbody');
	
	//<td>${escapeHtml(String(d.gift_id))}</td>

	dones.forEach(d => {
		const giftSlug = d.gift_pretty_id || d.gift_id;
		const nombre = `<a href="/powers/gift/${escapeHtml(giftSlug)}" target="_blank">${escapeHtml(d.gift_name)}</a>`;

		const tipo   = d.gift_type ? escapeHtml(d.gift_type) : '-';
		const grupo  = d.gift_category ? escapeHtml(d.gift_category) : '-';
		const rango  = (d.gift_level !== null && d.gift_level !== undefined && d.gift_level !== '') ? escapeHtml(String(d.gift_level)) : '-';

		const attr = d.gift_roll_attribute ? escapeHtml(d.gift_roll_attribute) : '';
		const skill = d.gift_roll_skill ? escapeHtml(d.gift_roll_skill) : '';
		const tirada = (attr || skill) ? [attr, skill].filter(Boolean).join(' + ') : '-';
		const fera = d.gift_fera_system ? escapeHtml(d.gift_fera_system) : '-';

		const origen = d.gift_origin ? escapeHtml(d.gift_origin) : '-';
		const row = `<tr>
			<td>${nombre}</td>
			<td>${fera}</td>
			<td>${tipo}</td>
			<td>${grupo}</td>
			<td>${rango}</td>
			<td>${tirada}</td>
			<td>${origen}</td>
		</tr>`;
		tbody.append(row);
	});

	const dt = $('#tabla-dones').DataTable({
		pageLength: 25,
		lengthMenu: [10, 25, 50, 100],
		order: [[0, "asc"]],
		language: {
			search: "&#128269; Buscar:",
			lengthMenu: "Mostrar _MENU_ dones",
			info: "Mostrando _START_ a _END_ de _TOTAL_ dones",
			infoEmpty: "No hay dones disponibles",
			emptyTable: "No hay datos en la tabla",
			paginate: {
				first: "Primero",
				last: "&Uacute;ltimo",
				next: "&#9654;",
				previous: "&#9664;"
			}
		},
		initComplete: function(){
			$('#dt-search-slot').append($('#tabla-dones_filter'));
		}
	});

	HGDataTableFilters.create({
		table: dt,
		rows: dones,
		filters: [
			{ key: 'fera', source: 'gift_fera_system', column: 1, allLabel: 'Todos', dependsOn: [] },
			{ key: 'type', source: 'gift_type', column: 2, allLabel: 'Todos', dependsOn: ['fera'] },
			{ key: 'group', source: 'gift_category', column: 3, allLabel: 'Todos', dependsOn: ['fera', 'type'] },
			{ key: 'rank', source: 'gift_level', column: 4, allLabel: 'Todos', dependsOn: ['fera', 'type', 'group'] },
			{ key: 'origin', source: 'gift_origin', column: 6, allLabel: 'Todos', dependsOn: ['fera', 'type', 'group', 'rank'] }
		]
	});
});

function escapeHtml(text) {
	if (!text) return '';
	return String(text).replace(/[&<>"']/g, function (m) {
		return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m];
	});
}
</script>


