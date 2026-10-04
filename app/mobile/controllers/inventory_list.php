<?php

include_once(__DIR__ . '/../../helpers/public_response.php');
include_once(__DIR__ . '/../../helpers/pretty.php');
require_once(__DIR__ . '/../../domains/inventory/queries.php');

$metaTitle = "Inventario | Heaven's Gate";
$metaDescription = 'Inventario móvil de objetos y artefactos.';
$pageSect = 'Inventario';

if (!function_exists('hg_mobile_inv_list_h')) {
    function hg_mobile_inv_list_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('hg_mobile_inv_list_url')) {
    function hg_mobile_inv_list_url(array $item): string
    {
        $typeSlug = trim((string)($item['item_type_pretty'] ?? $item['type_pretty'] ?? ''));
        if ($typeSlug === '') $typeSlug = (string)($item['item_type_id'] ?? 'type');
        $itemSlug = trim((string)($item['item_pretty_id'] ?? $item['pretty_id'] ?? ''));
        if ($itemSlug === '') $itemSlug = (string)($item['item_id'] ?? $item['id'] ?? '');
        return '/inventory/' . rawurlencode($typeSlug) . '/' . rawurlencode($itemSlug);
    }
}

if (!function_exists('hg_mobile_inv_list_type_url')) {
    function hg_mobile_inv_list_type_url(array $type): string
    {
        $slug = trim((string)($type['pretty_id'] ?? ''));
        if ($slug === '') $slug = (string)($type['id'] ?? '');
        return '/inventory/type/' . rawurlencode($slug);
    }
}

if (!function_exists('hg_mobile_inv_list_excerpt')) {
    function hg_mobile_inv_list_excerpt(string $text, int $max = 130): string
    {
        $text = trim(strip_tags($text));
        if ($text === '') return '';
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($text, 'UTF-8') > $max ? mb_substr($text, 0, $max, 'UTF-8') . '...' : $text;
        }
        return strlen($text) > $max ? substr($text, 0, $max) . '...' : $text;
    }
}

if (!function_exists('hg_mobile_inv_list_image')) {
    function hg_mobile_inv_list_image($value): string
    {
        $img = trim((string)$value);
        return $img !== '' ? $img : '/img/inv/no-photo.webp';
    }
}

if (!function_exists('hg_mobile_inv_list_filter_value')) {
    function hg_mobile_inv_list_filter_value(array $item, string $key): string
    {
        if ($key === 'category') {
            $value = trim((string)($item['item_category'] ?? $item['type_name'] ?? ''));
        } else {
            $value = trim((string)($item['item_origin'] ?? $item['origin'] ?? ''));
        }
        return $value !== '' ? $value : '-';
    }
}

if (!function_exists('hg_mobile_inv_list_filter_options')) {
    function hg_mobile_inv_list_filter_options(array $rows, string $key): array
    {
        $values = [];
        foreach ($rows as $row) {
            $value = hg_mobile_inv_list_filter_value($row, $key);
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

if (!function_exists('hg_mobile_inv_list_item_card')) {
    function hg_mobile_inv_list_item_card(array $item): void
    {
        $name = trim((string)($item['item_name'] ?? $item['name'] ?? ''));
        if ($name === '') $name = '#' . (string)($item['item_id'] ?? $item['id'] ?? '');
        $category = hg_mobile_inv_list_filter_value($item, 'category');
        $origin = hg_mobile_inv_list_filter_value($item, 'origin');
        $desc = hg_mobile_inv_list_excerpt((string)($item['description'] ?? ''));
        $search = trim($name . ' ' . $category . ' ' . $origin . ' ' . $desc);
        ?>
        <a class="hg-mobile-card hg-mobile-inv-card" href="<?= hg_mobile_inv_list_h(hg_mobile_inv_list_url($item)) ?>"
           data-mobile-item
           data-mobile-search="<?= hg_mobile_inv_list_h($search) ?>"
           data-mobile-filter-category="<?= hg_mobile_inv_list_h($category) ?>"
           data-mobile-filter-origin="<?= hg_mobile_inv_list_h($origin) ?>">
            <img src="<?= hg_mobile_inv_list_h(hg_mobile_inv_list_image($item['item_img'] ?? $item['image_url'] ?? '')) ?>" alt="">
            <span class="hg-mobile-inv-card-main">
                <strong><?= hg_mobile_inv_list_h($name) ?></strong>
                <span><?= hg_mobile_inv_list_h($category) ?><?= $origin !== '-' ? ' | ' . hg_mobile_inv_list_h($origin) : '' ?></span>
                <?php if ($desc !== ''): ?><small><?= hg_mobile_inv_list_h($desc) ?></small><?php endif; ?>
            </span>
        </a>
        <?php
    }
}

if (!isset($link) || !($link instanceof mysqli)) {
    hg_public_log_error('mobile_inventory_list', 'missing DB connection');
    hg_public_render_error('Inventario no disponible', 'No se pudo cargar el inventario.');
    return;
}

mysqli_set_charset($link, 'utf8mb4');

$types = hg_inventory_fetch_mobile_types($link);
$items = hg_inventory_fetch_mobile_catalog($link);
if ($items === null) {
    hg_public_log_error('mobile_inventory_list', 'list query failed: ' . mysqli_error($link));
    $items = [];
}

$filters = [
    ['key' => 'category', 'label' => 'Categoría', 'all' => 'Todas', 'depends_on' => []],
    ['key' => 'origin', 'label' => 'Origen', 'all' => 'Todos', 'depends_on' => ['category']],
];
?>
<section class="hg-mobile-section">
    <h1>Inventario</h1>
    <p class="hg-mobile-muted"><?= number_format(count($items), 0, ',', '.') ?> objetos en <?= number_format(count($types), 0, ',', '.') ?> categorías</p>
</section>

<section class="hg-mobile-section">
    <h2>Categorías</h2>
    <div class="hg-mobile-inv-type-grid" data-mobile-paginated data-mobile-search="1" data-page-size="12" data-search-placeholder="Buscar categoría" data-empty-text="No hay categorías con ese filtro.">
        <?php foreach ($types as $type): ?>
            <a class="hg-mobile-inv-type-card" href="<?= hg_mobile_inv_list_h(hg_mobile_inv_list_type_url($type)) ?>" data-mobile-item data-mobile-search="<?= hg_mobile_inv_list_h($type['name'] ?? '') ?>">
                <img src="<?= hg_mobile_inv_list_h(hg_mobile_inv_list_image($type['cover_image'] ?? '')) ?>" alt="">
                <strong><?= hg_mobile_inv_list_h($type['name'] ?? '') ?></strong>
                <span><?= number_format((int)($type['item_count'] ?? 0), 0, ',', '.') ?> objetos</span>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="hg-mobile-section">
    <h2>Objetos</h2>
    <div class="hg-mobile-card-list hg-mobile-inv-list" data-mobile-paginated data-mobile-search="1" data-page-size="20" data-search-placeholder="Buscar objeto, categoría u origen" data-empty-text="No hay objetos con esos filtros." data-mobile-cascading-filters="1">
        <details class="hg-mobile-details">
            <summary>Filtros <span data-mobile-list-filter-count hidden></span></summary>
            <div class="hg-mobile-filterbar">
                <?php foreach ($filters as $filter): ?>
                    <?php
                        $options = hg_mobile_inv_list_filter_options($items, (string)$filter['key']);
                        $dependsOn = implode(',', $filter['depends_on']);
                    ?>
                    <label>
                        <span><?= hg_mobile_inv_list_h($filter['label']) ?></span>
                        <select data-mobile-list-filter data-mobile-filter-key="<?= hg_mobile_inv_list_h($filter['key']) ?>" data-mobile-filter-depends-on="<?= hg_mobile_inv_list_h($dependsOn) ?>" aria-label="Filtrar por <?= hg_mobile_inv_list_h($filter['label']) ?>">
                            <option value=""><?= hg_mobile_inv_list_h($filter['all']) ?></option>
                            <?php foreach ($options as $option): ?>
                                <option value="<?= hg_mobile_inv_list_h($option) ?>"><?= hg_mobile_inv_list_h($option) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                <?php endforeach; ?>
                <button type="button" data-mobile-list-filter-clear disabled>Limpiar filtros</button>
            </div>
        </details>

        <?php if (empty($items)): ?><p class="hg-mobile-muted">No hay objetos disponibles.</p><?php endif; ?>
        <?php foreach ($items as $item) hg_mobile_inv_list_item_card($item); ?>
    </div>
</section>
