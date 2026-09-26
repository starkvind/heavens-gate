<?php

include_once(__DIR__ . '/../../helpers/public_response.php');
include_once(__DIR__ . '/../../helpers/pretty.php');
include_once(__DIR__ . '/../../helpers/character_avatar.php');
include_once(__DIR__ . '/../helpers/chronicle_scope.php');
require_once(__DIR__ . '/../../domains/inventory/queries.php');

$metaTitle = "Inventario | Heaven's Gate";
$metaDescription = 'Inventario móvil de objetos y artefactos.';
$pageSect = 'Inventario';

if (!function_exists('hg_mobile_inv_h')) {
    function hg_mobile_inv_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('hg_mobile_inv_url')) {
    function hg_mobile_inv_url(array $item): string
    {
        $typeSlug = trim((string)($item['item_type_pretty'] ?? $item['type_pretty'] ?? ''));
        if ($typeSlug === '') $typeSlug = (string)($item['item_type_id'] ?? 'type');
        $itemSlug = trim((string)($item['item_pretty_id'] ?? $item['pretty_id'] ?? ''));
        if ($itemSlug === '') $itemSlug = (string)($item['item_id'] ?? $item['id'] ?? '');
        return '/inventory/' . rawurlencode($typeSlug) . '/' . rawurlencode($itemSlug);
    }
}

if (!function_exists('hg_mobile_inv_type_url')) {
    function hg_mobile_inv_type_url(array $type): string
    {
        $slug = trim((string)($type['pretty_id'] ?? ''));
        if ($slug === '') $slug = (string)($type['id'] ?? '');
        return '/inventory/type/' . rawurlencode($slug);
    }
}

if (!function_exists('hg_mobile_inv_excerpt')) {
    function hg_mobile_inv_excerpt(string $text, int $max = 130): string
    {
        $text = trim(strip_tags($text));
        if ($text === '') return '';
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($text, 'UTF-8') > $max ? mb_substr($text, 0, $max, 'UTF-8') . '...' : $text;
        }
        return strlen($text) > $max ? substr($text, 0, $max) . '...' : $text;
    }
}

if (!function_exists('hg_mobile_inv_image')) {
    function hg_mobile_inv_image($value): string
    {
        $img = trim((string)$value);
        return $img !== '' ? $img : '/img/inv/no-photo.webp';
    }
}

if (!function_exists('hg_mobile_inv_damage_text')) {
    function hg_mobile_inv_damage_text(array $item): string
    {
        $skill = (string)($item['skill_name'] ?? '');
        $bonus = (int)($item['bonus'] ?? 0);
        $damage = trim(strtolower((string)($item['damage_type'] ?? '')));
        if ($damage === '') return '';
        $base = in_array($skill, ['Cuerpo a Cuerpo', 'Pelea', 'Arrojar'], true) ? ('Fuerza + ' . $bonus) : ($bonus . ' dados');
        $metal = '';
        $metalValue = (int)($item['metal'] ?? 0);
        if ($metalValue === 1) $metal = ' y de plata';
        if ($metalValue === 2) $metal = ' y de oro';
        return trim($base . ', ' . $damage . $metal);
    }
}

if (!function_exists('hg_mobile_inv_stat')) {
    function hg_mobile_inv_stat(string $label, $value): void
    {
        $text = trim((string)$value);
        if ($text === '' || $text === '0') return;
        ?>
        <div class="hg-mobile-inv-stat">
            <span><?= hg_mobile_inv_h($label) ?></span>
            <strong><?= hg_mobile_inv_h($text) ?></strong>
        </div>
        <?php
    }
}

if (!function_exists('hg_mobile_inv_item_card')) {
    function hg_mobile_inv_item_card(array $item): void
    {
        $name = trim((string)($item['item_name'] ?? $item['name'] ?? ''));
        if ($name === '') $name = '#' . (string)($item['item_id'] ?? $item['id'] ?? '');
        $category = trim((string)($item['item_category'] ?? $item['type_name'] ?? ''));
        $origin = trim((string)($item['item_origin'] ?? $item['origin'] ?? ''));
        $desc = hg_mobile_inv_excerpt((string)($item['description'] ?? ''));
        $search = trim($name . ' ' . $category . ' ' . $origin . ' ' . $desc);
        ?>
        <a class="hg-mobile-card hg-mobile-inv-card" href="<?= hg_mobile_inv_h(hg_mobile_inv_url($item)) ?>" data-mobile-item data-mobile-search="<?= hg_mobile_inv_h($search) ?>">
            <img src="<?= hg_mobile_inv_h(hg_mobile_inv_image($item['item_img'] ?? $item['image_url'] ?? '')) ?>" alt="">
            <span class="hg-mobile-inv-card-main">
                <strong><?= hg_mobile_inv_h($name) ?></strong>
                <span><?= hg_mobile_inv_h(trim($category . ($origin !== '' ? ' | ' . $origin : ''))) ?></span>
                <?php if ($desc !== ''): ?><small><?= hg_mobile_inv_h($desc) ?></small><?php endif; ?>
            </span>
        </a>
        <?php
    }
}

if (!isset($link) || !($link instanceof mysqli)) {
    hg_public_log_error('mobile_inventory', 'missing DB connection');
    hg_public_render_error('Inventario no disponible', 'No se pudo cargar el inventario.');
    return;
}

mysqli_set_charset($link, 'utf8mb4');

$route = hg_request_route($hgRequest);
if ($route === '') $route = 'inv';
$rawType = hg_request_param($hgRequest, 'item_type');
$rawItem = hg_request_param($hgRequest, 'item');
$isItem = $route === 'verobj' || $rawItem !== '';
$isType = !$isItem && in_array($route, ['inv_type'], true) && $rawType !== '';

if ($isItem) {
    $itemId = hg_inventory_resolve_id($link, 'fact_items', $rawItem);
    if ($itemId <= 0) {
        hg_public_render_not_found('Objeto no encontrado', 'El objeto solicitado no esta disponible.');
        return;
    }

    $item = hg_inventory_fetch_mobile_item($link, $itemId);
    if (!$item) {
        hg_public_render_not_found('Objeto no encontrado', 'El objeto solicitado no esta disponible.');
        return;
    }

    $name = trim((string)($item['name'] ?? ''));
    if ($name === '') $name = 'Objeto #' . $itemId;
    $typeName = trim((string)($item['type_name'] ?? 'Objeto'));
    $typePretty = trim((string)($item['type_pretty'] ?? ''));
    $typeHref = '/inventory/type/' . rawurlencode($typePretty !== '' ? $typePretty : (string)($item['item_type_id'] ?? ''));
    $description = (string)($item['description'] ?? '');
    $metaTitle = $name . " | Inventario | Heaven's Gate";
    $metaDescription = hg_mobile_inv_excerpt($description, 160);
    $pageTitle2 = $name;

    $damageText = hg_mobile_inv_damage_text($item);
    $embedCode = '[hg_item]' . $itemId . '[/hg_item]';

    $owners = hg_inventory_fetch_mobile_owners(
        $link,
        $itemId,
        function_exists('hg_chronicle_scope_excluded_csv') ? hg_chronicle_scope_excluded_csv() : '2,7'
    );
    ?>
    <section class="hg-mobile-section">
        <a class="hg-mobile-back-link" href="<?= hg_mobile_inv_h($typeHref) ?>">Volver a <?= hg_mobile_inv_h($typeName) ?></a>
    </section>

    <section class="hg-mobile-section hg-mobile-inv-detail">
        <img class="hg-mobile-inv-hero" src="<?= hg_mobile_inv_h(hg_mobile_inv_image($item['image_url'] ?? '')) ?>" alt="<?= hg_mobile_inv_h($name) ?>">
        <div class="hg-mobile-inv-title">
            <h1><?= hg_mobile_inv_h($name) ?></h1>
            <a href="<?= hg_mobile_inv_h($typeHref) ?>"><?= hg_mobile_inv_h($typeName) ?></a>
        </div>
    </section>

    <section class="hg-mobile-section">
        <div class="hg-mobile-inv-stats">
            <?php hg_mobile_inv_stat('Tipo', $typeName); ?>
            <?php hg_mobile_inv_stat('Habilidad', $item['skill_name'] ?? ''); ?>
            <?php hg_mobile_inv_stat('Daño', $damageText); ?>
            <?php if ((int)($item['bonus'] ?? 0) !== 0 && trim((string)($item['skill_name'] ?? '')) === '') hg_mobile_inv_stat('Bonificacion', '+' . (int)$item['bonus'] . ' de absorcion'); ?>
            <?php hg_mobile_inv_stat('Nivel', (int)($item['level'] ?? 0)); ?>
            <?php hg_mobile_inv_stat('Gnosis', (int)($item['gnosis'] ?? 0)); ?>
            <?php if ((int)($item['strength_req'] ?? 0) !== 0) hg_mobile_inv_stat('Requiere', 'Fuerza ' . (int)$item['strength_req'] . ' mínimo'); ?>
            <?php if ((int)($item['dexterity_req'] ?? 0) !== 0) hg_mobile_inv_stat('Penalizacion', 'Destreza -' . (int)$item['dexterity_req']); ?>
            <?php hg_mobile_inv_stat('Origen', $item['origin'] ?? ''); ?>
            <?php hg_mobile_inv_stat('Valor', $item['rating'] ?? ''); ?>
        </div>
    </section>

    <?php if (trim(strip_tags($description)) !== ''): ?>
        <section class="hg-mobile-section">
            <h2>Descripción</h2>
            <div class="hg-mobile-rich-body"><?= $description ?></div>
        </section>
    <?php endif; ?>

    <section class="hg-mobile-section hg-mobile-inv-embed-code">
        <h2>Embeber en el foro</h2>
        <code><?= hg_mobile_inv_h($embedCode) ?></code>
        <button type="button" data-mobile-copy="<?= hg_mobile_inv_h($embedCode) ?>">Copiar</button>
    </section>

    <section class="hg-mobile-section">
        <h2>Portadores</h2>
        <?php if (empty($owners)): ?>
            <p class="hg-mobile-muted">No hay portadores publicados.</p>
        <?php else: ?>
            <div class="hg-mobile-character-list" data-mobile-paginated data-mobile-search="1" data-page-size="20" data-search-placeholder="Buscar portador" data-empty-text="No hay portadores con ese filtro.">
                <?php foreach ($owners as $owner): ?>
                    <?php
                        $ownerId = (int)($owner['id'] ?? 0);
                        $ownerName = trim((string)($owner['name'] ?? ''));
                        $ownerAlias = trim((string)($owner['alias'] ?? ''));
                        $status = trim((string)($owner['status'] ?? ''));
                        $avatar = function_exists('hg_character_avatar_url') ? hg_character_avatar_url((string)($owner['image_url'] ?? ''), (string)($owner['gender'] ?? '')) : (string)($owner['image_url'] ?? '');
                        $href = function_exists('pretty_url') ? pretty_url($link, 'fact_characters', '/characters', $ownerId) : ('/characters/' . $ownerId);
                    ?>
                    <a class="hg-mobile-character-card" href="<?= hg_mobile_inv_h($href) ?>" data-mobile-item data-mobile-search="<?= hg_mobile_inv_h($ownerName . ' ' . $ownerAlias . ' ' . $status) ?>">
                        <?php if ($avatar !== ''): ?><img src="<?= hg_mobile_inv_h($avatar) ?>" alt=""><?php else: ?><span class="hg-mobile-character-avatar" aria-hidden="true"></span><?php endif; ?>
                        <span class="hg-mobile-character-main">
                            <strong><?= hg_mobile_inv_h($ownerName !== '' ? $ownerName : ('#' . $ownerId)) ?></strong>
                            <span><?= hg_mobile_inv_h(trim($ownerAlias . ' ' . $status)) ?></span>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <script>
    document.addEventListener('click', function (event) {
        var btn = event.target.closest('[data-mobile-copy]');
        if (!btn) return;
        var text = btn.getAttribute('data-mobile-copy') || '';
        if (!text) return;
        if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(text).catch(function () {});
        btn.textContent = 'Copiado';
        setTimeout(function () { btn.textContent = 'Copiar'; }, 1200);
    });
    </script>
    <?php
    return;
}

if ($isType) {
    $typeId = hg_inventory_resolve_id($link, 'dim_item_types', $rawType);
    if ($typeId <= 0) {
        hg_public_render_not_found('Tipo no encontrado', 'La categoría de inventario solicitada no esta disponible.');
        return;
    }

    $type = hg_inventory_fetch_type($link, $typeId);
    if (!$type) {
        hg_public_render_not_found('Tipo no encontrado', 'La categoría de inventario solicitada no esta disponible.');
        return;
    }

    $typeName = trim((string)($type['name'] ?? 'Objetos'));
    $metaTitle = $typeName . " | Inventario | Heaven's Gate";
    $metaDescription = 'Listado móvil de objetos por tipo.';

    $items = hg_inventory_fetch_mobile_items_by_type($link, $typeId);
    ?>
    <section class="hg-mobile-section">
        <a class="hg-mobile-back-link" href="/inventory">Volver a inventario</a>
    </section>
    <section class="hg-mobile-section">
        <h1><?= hg_mobile_inv_h($typeName) ?></h1>
        <p class="hg-mobile-muted"><?= number_format(count($items), 0, ',', '.') ?> objetos</p>
    </section>
    <section class="hg-mobile-section">
        <div class="hg-mobile-card-list hg-mobile-inv-list" data-mobile-paginated data-mobile-search="1" data-page-size="20" data-search-placeholder="Buscar objeto" data-empty-text="No hay objetos con ese filtro.">
            <?php if (empty($items)): ?><p class="hg-mobile-muted">No hay objetos disponibles.</p><?php endif; ?>
            <?php foreach ($items as $item) hg_mobile_inv_item_card($item); ?>
        </div>
    </section>
    <?php
    return;
}

$types = hg_inventory_fetch_mobile_types($link);
$items = hg_inventory_fetch_mobile_catalog($link);
if ($items === null) {
    hg_public_log_error('mobile_inventory', 'list query failed: ' . mysqli_error($link));
    $items = [];
}
?>
<section class="hg-mobile-section">
    <h1>Inventario</h1>
    <p class="hg-mobile-muted"><?= number_format(count($items), 0, ',', '.') ?> objetos en <?= number_format(count($types), 0, ',', '.') ?> categorías</p>
</section>

<section class="hg-mobile-section">
    <h2>Categorías</h2>
    <div class="hg-mobile-inv-type-grid" data-mobile-paginated data-mobile-search="1" data-page-size="12" data-search-placeholder="Buscar categoría" data-empty-text="No hay categorías con ese filtro.">
        <?php foreach ($types as $type): ?>
            <a class="hg-mobile-inv-type-card" href="<?= hg_mobile_inv_h(hg_mobile_inv_type_url($type)) ?>" data-mobile-item data-mobile-search="<?= hg_mobile_inv_h($type['name'] ?? '') ?>">
                <img src="<?= hg_mobile_inv_h(hg_mobile_inv_image($type['cover_image'] ?? '')) ?>" alt="">
                <strong><?= hg_mobile_inv_h($type['name'] ?? '') ?></strong>
                <span><?= number_format((int)($type['item_count'] ?? 0), 0, ',', '.') ?> objetos</span>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="hg-mobile-section">
    <h2>Objetos</h2>
    <div class="hg-mobile-card-list hg-mobile-inv-list" data-mobile-paginated data-mobile-search="1" data-page-size="20" data-search-placeholder="Buscar objeto, categoría u origen" data-empty-text="No hay objetos con ese filtro.">
        <?php foreach ($items as $item) hg_mobile_inv_item_card($item); ?>
    </div>
</section>