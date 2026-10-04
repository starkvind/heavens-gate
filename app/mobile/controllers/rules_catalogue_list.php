<?php

require_once __DIR__ . '/../../domains/rules/queries.php';
include_once __DIR__ . '/../../helpers/public_response.php';

if (!function_exists('hg_mobile_rules_list_h')) {
    function hg_mobile_rules_list_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('hg_mobile_rules_list_filter_value')) {
    function hg_mobile_rules_list_filter_value(array $row, string $source): string
    {
        $value = trim((string)($row[$source] ?? ''));
        return $value !== '' ? $value : '-';
    }
}

if (!function_exists('hg_mobile_rules_list_filter_options')) {
    function hg_mobile_rules_list_filter_options(array $rows, string $source): array
    {
        $values = [];
        foreach ($rows as $row) {
            $value = hg_mobile_rules_list_filter_value($row, $source);
            $values[$value] = $value;
        }
        $values = array_values($values);
        usort($values, static function (string $a, string $b): int {
            if ($a === '-' && $b !== '-') return 1;
            if ($b === '-' && $a !== '-') return -1;
            return strnatcasecmp($a, $b);
        });
        return $values;
    }
}

if (!isset($link) || !($link instanceof mysqli)) {
    hg_public_log_error('mobile_rules_catalogue_list', 'missing DB connection');
    hg_public_render_error('Contenido no disponible', 'No se pudo cargar esta sección.');
    return;
}

$route = hg_request_route($hgRequest);
$configs = [
    'listarasgos' => [
        'kind' => 'traits',
        'title' => 'Rasgos',
        'base' => '/rules/traits',
        'id' => 'trait_id',
        'name' => 'trait_name',
        'pretty' => 'trait_pretty_id',
        'meta' => ['trait_category', 'trait_subcategory', 'trait_origin'],
        'filters' => [
            ['key'=>'type','label'=>'Tipo','source'=>'trait_category','all'=>'Todos','depends_on'=>[]],
            ['key'=>'class','label'=>'Clasificación','source'=>'trait_subcategory','all'=>'Todas','depends_on'=>['type']],
            ['key'=>'origin','label'=>'Origen','source'=>'trait_origin','all'=>'Todos','depends_on'=>['type','class']],
        ],
    ],
    'listconditions' => [
        'kind' => 'conditions',
        'title' => 'Condiciones',
        'base' => '/rules/conditions',
        'id' => 'condition_id',
        'name' => 'condition_name',
        'pretty' => 'condition_pretty_id',
        'meta' => ['condition_category', 'condition_origin'],
        'filters' => [
            ['key'=>'category','label'=>'Categoría','source'=>'condition_category','all'=>'Todas','depends_on'=>[]],
            ['key'=>'origin','label'=>'Origen','source'=>'condition_origin','all'=>'Todos','depends_on'=>['category']],
        ],
    ],
    'listamyd' => [
        'kind' => 'merits',
        'title' => 'Méritos y Defectos',
        'base' => '/rules/merits-flaws',
        'id' => 'merit_id',
        'name' => 'merit_name',
        'pretty' => 'merit_pretty_id',
        'meta' => ['merit_type', 'merit_system', 'merit_category', 'merit_cost', 'merit_origin'],
        'filters' => [
            ['key'=>'type','label'=>'Tipo','source'=>'merit_type','all'=>'Todos','depends_on'=>[]],
            ['key'=>'system','label'=>'Sistema','source'=>'merit_system','all'=>'Todos','depends_on'=>['type']],
            ['key'=>'category','label'=>'Categoría','source'=>'merit_category','all'=>'Todas','depends_on'=>['type','system']],
            ['key'=>'origin','label'=>'Origen','source'=>'merit_origin','all'=>'Todos','depends_on'=>['type','system','category']],
        ],
    ],
    'arquetip' => [
        'kind' => 'archetypes',
        'title' => 'Arquetipos',
        'base' => '/rules/archetypes',
        'id' => 'arche_id',
        'name' => 'arche_name',
        'pretty' => 'arche_pretty_id',
        'meta' => ['arche_origin'],
        'filters' => [
            ['key'=>'origin','label'=>'Origen','source'=>'arche_origin','all'=>'Todos','depends_on'=>[]],
        ],
    ],
];

$cfg = $configs[$route] ?? null;
if (!$cfg) {
    hg_public_render_not_found('Sección no disponible', 'Esta parte no corresponde a un catálogo filtrable.');
    return;
}

$kind = $cfg['kind'];
if ($kind === 'traits') {
    $rows = hg_rules_fetch_traits_table($link, '2,7') ?: [];
} elseif ($kind === 'conditions') {
    $rows = hg_rules_fetch_conditions_table($link, '2,7') ?: [];
} elseif ($kind === 'merits') {
    $rows = hg_rules_fetch_merits($link) ?: [];
} else {
    $rows = hg_rules_fetch_archetypes($link) ?: [];
}

$metaTitle = $cfg['title'] . " | Heaven's Gate";
$pageSect = $cfg['title'];
$filters = $cfg['filters'];
?>
<section class="hg-mobile-section">
    <h1><?= hg_mobile_rules_list_h($cfg['title']) ?></h1>
    <p class="hg-mobile-muted"><?= number_format(count($rows), 0, ',', '.') ?> elementos</p>
</section>

<section class="hg-mobile-section">
    <div class="hg-mobile-card-list"
         data-mobile-paginated
         data-mobile-search="1"
         data-page-size="20"
         data-search-placeholder="Buscar"
         data-empty-text="No hay elementos con esos filtros."
         data-mobile-cascading-filters="1">
        <details class="hg-mobile-details">
            <summary>Filtros <span data-mobile-list-filter-count hidden></span></summary>
            <div class="hg-mobile-filterbar">
                <?php foreach ($filters as $filter): ?>
                    <?php
                        $options = hg_mobile_rules_list_filter_options($rows, (string)$filter['source']);
                        $dependsOn = implode(',', $filter['depends_on']);
                    ?>
                    <label>
                        <span><?= hg_mobile_rules_list_h($filter['label']) ?></span>
                        <select data-mobile-list-filter
                                data-mobile-filter-key="<?= hg_mobile_rules_list_h($filter['key']) ?>"
                                data-mobile-filter-depends-on="<?= hg_mobile_rules_list_h($dependsOn) ?>"
                                aria-label="Filtrar por <?= hg_mobile_rules_list_h($filter['label']) ?>">
                            <option value=""><?= hg_mobile_rules_list_h($filter['all']) ?></option>
                            <?php foreach ($options as $option): ?>
                                <option value="<?= hg_mobile_rules_list_h($option) ?>"><?= hg_mobile_rules_list_h($option) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                <?php endforeach; ?>
                <button type="button" data-mobile-list-filter-clear disabled>Limpiar filtros</button>
            </div>
        </details>

        <?php if (!$rows): ?><p class="hg-mobile-muted">No hay elementos disponibles.</p><?php endif; ?>
        <?php foreach ($rows as $row): ?>
            <?php
                $id = (int)($row[$cfg['id']] ?? 0);
                $name = trim((string)($row[$cfg['name']] ?? ''));
                $slug = trim((string)($row[$cfg['pretty']] ?? ''));
                if ($slug === '') $slug = (string)$id;
                $href = rtrim($cfg['base'], '/') . '/' . rawurlencode($slug);

                $meta = [];
                foreach ($cfg['meta'] as $source) {
                    $value = trim((string)($row[$source] ?? ''));
                    if ($value !== '') $meta[] = $value;
                }
                $search = trim($name . ' ' . implode(' ', $meta));

                $filterAttrs = '';
                foreach ($filters as $filter) {
                    $key = preg_replace('/[^a-z0-9_-]+/i', '-', strtolower((string)$filter['key']));
                    $value = hg_mobile_rules_list_filter_value($row, (string)$filter['source']);
                    $filterAttrs .= ' data-mobile-filter-' . $key . '="' . hg_mobile_rules_list_h($value) . '"';
                }
            ?>
            <a class="hg-mobile-card"
               href="<?= hg_mobile_rules_list_h($href) ?>"
               data-mobile-item
               data-mobile-search="<?= hg_mobile_rules_list_h($search) ?>"<?= $filterAttrs ?>>
                <strong><?= hg_mobile_rules_list_h($name !== '' ? $name : ('#' . $id)) ?></strong>
                <?php foreach ($meta as $value): ?><span><?= hg_mobile_rules_list_h($value) ?></span><?php endforeach; ?>
            </a>
        <?php endforeach; ?>
    </div>
</section>
