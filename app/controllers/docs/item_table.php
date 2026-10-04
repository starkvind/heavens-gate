<?php
if (function_exists("setMetaFromPage")) setMetaFromPage("Inventario | Heaven's Gate", "Listado de objetos y artefactos.", null, 'website');
require_once(__DIR__ . '/../../domains/inventory/queries.php');
if (!defined("HG_MOBILE_DESKTOP_EMBED") || !HG_MOBILE_DESKTOP_EMBED) include("app/partials/main_nav_bar.php");
header('Content-Type: text/html; charset=utf-8');
if ($link) { mysqli_set_charset($link, "utf8mb4"); }

$items = hg_inventory_fetch_catalog($link) ?? [];

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
<?php
if (function_exists('hg_page_register_stylesheet')) {
    hg_page_register_stylesheet('/assets/css/hg-docs.css');
    hg_page_register_stylesheet('/assets/css/hg-inventory.css');
} else {
    echo '<link rel="stylesheet" href="/assets/css/hg-docs.css">';
    echo '<link rel="stylesheet" href="/assets/css/hg-inventory.css">';
}
?>
<?php include_once("app/partials/datatable_assets.php"); ?>

<h2 class="docs-table-title">Inventario</h2>

<div class="docs-table-wrap">
	<div class="docs-table-inner">
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
		<table id="tabla-inventario" class="display docs-table">
			<thead>
				<tr>
					<th>Objeto</th>
					<th>Categor&iacute;a</th>
					<th>Origen</th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ($items as $i):
				$itemSlug = $i['item_pretty_id'] ?: $i['item_id'];
				$typeSlug = $i['item_type_pretty'] ?: ($i['item_type_id'] ?: 'tipo');
				$itemName = (string)($i['item_name'] ?? '');
				$imgSrc = !empty($i['item_img']) ? (string)$i['item_img'] : '/img/inv/no-photo.webp';
				$category = trim((string)($i['item_category'] ?? ''));
				$origin = trim((string)($i['item_origin'] ?? ''));
				if ($category === '') $category = '-';
				if ($origin === '') $origin = '-';
			?>
				<tr>
					<td><span class="hg-inventory-item-cell"><span class="hg-inventory-item-icon"><img src="<?= htmlspecialchars($imgSrc, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($itemName, ENT_QUOTES, 'UTF-8') ?>" class="hg-inventory-item-thumb"></span><a href="/inventory/<?= htmlspecialchars((string)$typeSlug, ENT_QUOTES, 'UTF-8') ?>/<?= htmlspecialchars((string)$itemSlug, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($itemName, ENT_QUOTES, 'UTF-8') ?></a></span></td>
					<td><?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?></td>
					<td><?= htmlspecialchars($origin, ENT_QUOTES, 'UTF-8') ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>

<script>
$(document).ready(function () {
	const items = <?= json_encode($items, JSON_UNESCAPED_UNICODE) ?>;

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
		}
	});

	HGDataTableFilters.create({
		table: dt,
		rows: items,
		filters: [
			{ key: 'category', source: 'item_category', column: 1, allLabel: 'Todas', dependsOn: [] },
			{ key: 'origin', source: 'item_origin', column: 2, allLabel: 'Todos', dependsOn: ['category'] }
		]
	});
});
</script>
