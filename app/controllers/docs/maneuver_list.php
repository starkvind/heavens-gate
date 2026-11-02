<?php
require_once __DIR__ . '/../../domains/rules/queries.php';
require_once __DIR__ . '/../../helpers/public_response.php';

if (!isset($link) || !($link instanceof mysqli)) {
    hg_public_render_error('Maniobras no disponibles', 'No se pudo cargar este catálogo.', 500, true);
    return;
}

$maneuvers = hg_rules_fetch_maneuvers($link);
if ($maneuvers === null) {
    hg_public_log_error('maneuver_list', 'query failed: ' . $link->error);
    hg_public_render_error('Maniobras no disponibles', 'No se pudo cargar este catálogo.');
    return;
}

$originSystems = [];
foreach ($maneuvers as $maneuver) {
    $systemName = trim((string)($maneuver['system_name'] ?? ''));
    if ($systemName !== '') $originSystems[$systemName] = $systemName;
}
natcasesort($originSystems);

setMetaFromPage("Maniobras | Heaven's Gate", "Listado de maniobras de combate.", null, 'website');
$pageSect = 'Maniobras de combate';
include 'app/partials/main_nav_bar.php';
if (function_exists('hg_page_register_stylesheet')) hg_page_register_stylesheet('/assets/css/hg-docs.css');
else echo '<link rel="stylesheet" href="/assets/css/hg-docs.css">';
include_once 'app/partials/datatable_assets.php';

$h = static function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
?>
<h2 class="docs-table-title">Maniobras de combate</h2>
<div class="docs-table-wrap"><div class="docs-table-inner">
    <div class="dt-toolbar">
        <div class="left">
            <label for="hg-maneuvers-system-filter">Sistema de origen</label>
            <select id="hg-maneuvers-system-filter" aria-label="Filtrar por sistema de origen">
                <option value="">Todos los sistemas</option>
                <?php foreach ($originSystems as $systemName): ?>
                    <option value="<?= $h($systemName) ?>"><?= $h($systemName) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="right" id="dt-search-slot"></div>
    </div>
    <table id="tabla-maniobras" class="display docs-table">
        <thead><tr><th>Maniobra</th><th>Sistema de origen</th><th>Tirada</th><th>Dificultad</th><th>Daño</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php foreach ($maneuvers as $maneuver): ?>
            <?php
                $href = pretty_url($link, 'fact_combat_maneuvers', '/rules/maneuvers', (int)($maneuver['id'] ?? 0));
                $systemName = trim((string)($maneuver['system_name'] ?? ''));
            ?>
            <tr>
                <td><a href="<?= $h($href) ?>"><?= $h($maneuver['name'] ?? '') ?></a></td>
                <td><?= $h($systemName !== '' ? $systemName : '-') ?></td>
                <?php foreach (['roll', 'difficulty', 'damage', 'actions'] as $field): ?>
                    <?php $value = trim((string)($maneuver[$field] ?? '')); ?>
                    <td><?= $h($value !== '' ? $value : '-') ?></td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div></div>
<script>
jQuery(function ($) {
    if (!$.fn.DataTable) return;
    const table = $('#tabla-maniobras').DataTable({
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        order: [[0, 'asc']],
        language: {
            search: 'Buscar:',
            lengthMenu: 'Mostrar _MENU_ maniobras',
            info: 'Mostrando _START_ a _END_ de _TOTAL_ maniobras',
            infoEmpty: 'No hay maniobras disponibles',
            emptyTable: 'No hay maniobras disponibles',
            paginate: { first: 'Primero', last: 'Último', next: 'Siguiente', previous: 'Anterior' }
        },
        initComplete: function () {
            $('#dt-search-slot').append($('#tabla-maniobras_filter'));
        }
    });
    $('#hg-maneuvers-system-filter').on('change', function () {
        const value = this.value;
        const search = value ? '^' + $.fn.dataTable.util.escapeRegex(value) + '$' : '';
        table.column(1).search(search, true, false).draw();
    });
});
</script>
