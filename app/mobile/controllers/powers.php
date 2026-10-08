<?php

include_once(__DIR__ . '/../../helpers/public_response.php');
include_once(__DIR__ . '/../../helpers/character_avatar.php');
require_once __DIR__ . '/../../domains/powers/queries.php';
require_once __DIR__ . '/../../domains/bibliography/publication_context.php';

function hg_mpw_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function hg_mpw_url(mysqli $db, string $table, string $base, int $id): string
{
    return $id > 0 && function_exists('pretty_url')
        ? pretty_url($db, $table, $base, $id)
        : rtrim($base, '/') . '/' . $id;
}

function hg_mpw_resolve_id(mysqli $db, string $table, string $raw): int
{
    $raw = trim(rawurldecode($raw));
    if ($raw === '') return 0;
    if (preg_match('/^\d+$/', $raw)) return (int)$raw;
    if (!function_exists('resolve_pretty_id')) return 0;
    return (int)resolve_pretty_id($db, $table, $raw);
}

function hg_mpw_excerpt(string $html, int $max = 135): string
{
    $text = trim(strip_tags($html));
    if ($text === '') return '';
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($text, 'UTF-8') > $max ? mb_substr($text, 0, $max, 'UTF-8') . '...' : $text;
    }
    return strlen($text) > $max ? substr($text, 0, $max) . '...' : $text;
}

function hg_mpw_image(string $raw, string $dir): string
{
    $raw = trim($raw);
    if ($raw === '') return '';
    return strpos($raw, '/') !== false ? $raw : trim($dir, '/') . '/' . $raw;
}

function hg_mpw_roll($attribute, $skill): string
{
    $parts = [];
    foreach ([$attribute, $skill] as $value) {
        $value = trim((string)$value);
        if ($value !== '') $parts[] = $value;
    }
    return implode(' + ', $parts);
}

function hg_mpw_type_label(string $kind, array $type): string
{
    $name = trim((string)($type['name'] ?? ''));
    $det = trim((string)($type['determinant'] ?? ''));
    if ($kind === 'rites') return trim('Rito' . ($det !== '' ? ' ' . $det : '') . ' ' . $name);
    if ($kind === 'totems') return trim('Tótem' . ($det !== '' ? ' ' . $det : '') . ' ' . $name);
    return $name;
}

function hg_mpw_filter_type(array $rows, string $kind, array $type): array
{
    $field = [
        'gifts' => 'gift_type',
        'rites' => 'ritual_type',
        'totems' => 'totem_type',
        'disciplines' => 'disc_type',
    ][$kind] ?? '';
    if ($field === '') return $rows;

    $label = hg_mpw_type_label($kind, $type);
    return array_values(array_filter($rows, static function (array $row) use ($field, $label): bool {
        return trim((string)($row[$field] ?? '')) === $label;
    }));
}

function hg_mpw_catalog_config(string $kind): ?array
{
    $configs = [
        'gifts' => [
            'title' => 'Dones', 'singular' => 'Don', 'table' => 'fact_gifts', 'item_base' => '/powers/gift', 'list_base' => '/powers/gifts', 'image_dir' => 'img/gifts',
            'id' => 'gift_id', 'name' => 'gift_name', 'pretty' => 'gift_pretty_id', 'description' => 'gift_description', 'cascading_filters' => true,
            'fields' => [
                ['Fêra', 'gift_fera_system'], ['Tipo', 'gift_type'], ['Grupo', 'gift_category'], ['Rango', 'gift_level'], ['Tirada', ['gift_roll_attribute', 'gift_roll_skill']],
            ],
            'filters' => [
                ['key' => 'fera', 'label' => 'Fêra', 'source' => 'gift_fera_system', 'all' => 'Todas', 'depends_on' => []],
                ['key' => 'type', 'label' => 'Tipo', 'source' => 'gift_type', 'all' => 'Todos', 'depends_on' => ['fera']],
                ['key' => 'group', 'label' => 'Grupo', 'source' => 'gift_category', 'all' => 'Todos', 'depends_on' => ['fera', 'type']],
                ['key' => 'rank', 'label' => 'Rango', 'source' => 'gift_level', 'all' => 'Todos', 'depends_on' => ['fera', 'type', 'group']],
                ['key' => 'origin', 'label' => 'Origen', 'source' => 'gift_origin', 'all' => 'Todos', 'depends_on' => ['fera', 'type', 'group', 'rank']],
            ],
        ],
        'rites' => [
            'title' => 'Rituales', 'singular' => 'Ritual', 'table' => 'fact_rites', 'item_base' => '/powers/rite', 'list_base' => '/powers/rites', 'image_dir' => 'img/rites',
            'id' => 'ritual_id', 'name' => 'ritual_name', 'pretty' => 'ritual_pretty_id', 'description' => 'ritual_description',
            'fields' => [
                ['Fêra', 'ritual_fera_system'], ['Tipo', 'ritual_type'], ['Nivel', 'ritual_level'], ['Raza', 'ritual_species'],
            ],
            'filters' => [
                ['key' => 'fera', 'label' => 'Fêra', 'source' => 'ritual_fera_system', 'all' => 'Todas'],
                ['key' => 'type', 'label' => 'Tipo', 'source' => 'ritual_type', 'all' => 'Todos'],
                ['key' => 'level', 'label' => 'Nivel', 'source' => 'ritual_level', 'all' => 'Todos'],
                ['key' => 'origin', 'label' => 'Origen', 'source' => 'ritual_origin', 'all' => 'Todos'],
            ],
        ],
        'totems' => [
            'title' => 'Totems', 'singular' => 'Totem', 'table' => 'dim_totems', 'item_base' => '/powers/totem', 'list_base' => '/powers/totems', 'image_dir' => 'img/totems',
            'id' => 'totem_id', 'name' => 'totem_name', 'pretty' => 'totem_pretty_id', 'description' => 'totem_description',
            'fields' => [
                ['Tipo', 'totem_type'], ['Coste', 'totem_cost'],
            ],
            'filters' => [
                ['key' => 'type', 'label' => 'Tipo', 'source' => 'totem_type', 'all' => 'Todos'],
                ['key' => 'cost', 'label' => 'Coste', 'source' => 'totem_cost', 'all' => 'Todos'],
                ['key' => 'origin', 'label' => 'Origen', 'source' => 'totem_origin', 'all' => 'Todos'],
            ],
        ],
        'disciplines' => [
            'title' => 'Disciplinas', 'singular' => 'Disciplina', 'table' => 'fact_discipline_powers', 'item_base' => '/powers/discipline', 'list_base' => '/powers/disciplines', 'image_dir' => 'img/disciplines',
            'id' => 'disc_id', 'name' => 'disc_name', 'pretty' => 'disc_pretty_id', 'description' => 'disc_description',
            'fields' => [
                ['Disciplina', 'disc_type'], ['Nivel', 'disc_level'], ['Tirada', ['disc_roll_attribute', 'disc_roll_skill']],
            ],
            'filters' => [
                ['key' => 'type', 'label' => 'Disciplina', 'source' => 'disc_type', 'all' => 'Todas'],
                ['key' => 'level', 'label' => 'Nivel', 'source' => 'disc_level', 'all' => 'Todos'],
                ['key' => 'origin', 'label' => 'Origen', 'source' => 'disc_origin', 'all' => 'Todos'],
            ],
        ],
    ];
    return $configs[$kind] ?? null;
}

function hg_mpw_value(array $row, $source): string
{
    if (is_array($source)) {
        return hg_mpw_roll($row[$source[0]] ?? '', $row[$source[1]] ?? '');
    }
    return trim((string)($row[$source] ?? ''));
}

function hg_mpw_filter_value(array $row, string $source): string
{
    $value = trim((string)($row[$source] ?? ''));
    return $value !== '' ? $value : '-';
}

function hg_mpw_filter_options(array $rows, string $source): array
{
    $values = [];
    foreach ($rows as $row) {
        $value = hg_mpw_filter_value($row, $source);
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

function hg_mpw_render_hub(): void
{
    $items = [
        ['Dones', '/powers/gifts', 'Poderes espirituales Garou y Fera.'],
        ['Rituales', '/powers/rites', 'Ritos y ceremonias con efecto mistico.'],
        ['Totems', '/powers/totems', 'Espíritus guia, beneficios y prohibiciónes.'],
        ['Disciplinas', '/powers/disciplines', 'Poderes vampiricos organizados por disciplina.'],
    ];
    ?>
    <section class="hg-mobile-section"><h1>Poderes</h1></section>
    <section class="hg-mobile-section"><div class="hg-mobile-card-list">
        <?php foreach ($items as $item): ?>
            <a class="hg-mobile-card" href="<?= hg_mpw_h($item[1]) ?>"><strong><?= hg_mpw_h($item[0]) ?></strong><span><?= hg_mpw_h($item[2]) ?></span></a>
        <?php endforeach; ?>
    </div></section>
    <?php
}

function hg_mpw_render_list(mysqli $db, string $kind, array $rows): void
{
    $cfg = hg_mpw_catalog_config($kind);
    if (!$cfg) return;
    $filters = is_array($cfg['filters'] ?? null) ? $cfg['filters'] : [];
    $cascadingFilters = !empty($cfg['cascading_filters']);
    ?>
    <section class="hg-mobile-section"><h1><?= hg_mpw_h($cfg['title']) ?></h1><p class="hg-mobile-muted"><?= number_format(count($rows), 0, ',', '.') ?> elementos</p></section>
    <section class="hg-mobile-section"><div class="hg-mobile-card-list" data-mobile-paginated data-mobile-search="1" data-page-size="20" data-search-placeholder="Buscar" data-empty-text="No hay elementos con esos filtros."<?= $cascadingFilters ? ' data-mobile-cascading-filters="1"' : '' ?>>
        <?php if ($filters): ?>
            <details class="hg-mobile-details">
                <summary>Filtros <span data-mobile-list-filter-count hidden></span></summary>
                <div class="hg-mobile-filterbar">
                    <?php foreach ($filters as $filter): ?>
                        <?php
                            $options = hg_mpw_filter_options($rows, (string)$filter['source']);
                            $dependsOn = is_array($filter['depends_on'] ?? null) ? implode(',', $filter['depends_on']) : '';
                        ?>
                        <label>
                            <span><?= hg_mpw_h($filter['label']) ?></span>
                            <select data-mobile-list-filter data-mobile-filter-key="<?= hg_mpw_h($filter['key']) ?>"<?= $cascadingFilters ? ' data-mobile-filter-depends-on="' . hg_mpw_h($dependsOn) . '"' : '' ?> aria-label="Filtrar por <?= hg_mpw_h($filter['label']) ?>">
                                <option value=""><?= hg_mpw_h($filter['all']) ?></option>
                                <?php foreach ($options as $option): ?><option value="<?= hg_mpw_h($option) ?>"><?= hg_mpw_h($option) ?></option><?php endforeach; ?>
                            </select>
                        </label>
                    <?php endforeach; ?>
                    <button type="button" data-mobile-list-filter-clear disabled>Limpiar filtros</button>
                </div>
            </details>
        <?php endif; ?>
        <?php if (!$rows): ?><p class="hg-mobile-muted">No hay elementos disponibles.</p><?php endif; ?>
        <?php foreach ($rows as $row): ?>
            <?php
                $id = (int)($row[$cfg['id']] ?? 0);
                $name = (string)($row[$cfg['name']] ?? '');
                $href = hg_mpw_url($db, $cfg['table'], $cfg['item_base'], $id);
                $bits = [];
                foreach ($cfg['fields'] as $field) {
                    $value = hg_mpw_value($row, $field[1]);
                    if ($value !== '') $bits[] = $field[0] . ': ' . $value;
                }
                $body = (string)($row[$cfg['description']] ?? '');
                $search = trim($name . ' ' . implode(' ', $bits) . ' ' . strip_tags($body));
                $filterAttrs = '';
                foreach ($filters as $filter) {
                    $key = preg_replace('/[^a-z0-9_-]+/i', '-', strtolower((string)$filter['key']));
                    $value = hg_mpw_filter_value($row, (string)$filter['source']);
                    $filterAttrs .= ' data-mobile-filter-' . $key . '="' . hg_mpw_h($value) . '"';
                }
            ?>
            <a class="hg-mobile-card" href="<?= hg_mpw_h($href) ?>" data-mobile-item data-mobile-search="<?= hg_mpw_h($search) ?>"<?= $filterAttrs ?>>
                <strong><?= hg_mpw_h($name) ?></strong>
                <?php foreach ($bits as $bit): ?><span><?= hg_mpw_h($bit) ?></span><?php endforeach; ?>
                <?php $excerpt = hg_mpw_excerpt($body); if ($excerpt !== ''): ?><span><?= hg_mpw_h($excerpt) ?></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div></section>
    <?php
}

function hg_mpw_character_card(mysqli $db, array $character): void
{
    $id = (int)($character['id'] ?? 0);
    $name = (string)($character['name'] ?? $character['nombre'] ?? '');
    $alias = (string)($character['alias'] ?? '');
    $status = (string)($character['status'] ?? '');
    $img = function_exists('hg_character_avatar_url')
        ? hg_character_avatar_url((string)($character['image_url'] ?? ''), (string)($character['gender'] ?? ''))
        : '';
    $href = hg_mpw_url($db, 'fact_characters', '/characters', $id);
    $meta = array_values(array_filter([$alias, $status], static fn($value) => trim((string)$value) !== ''));
    ?>
    <a class="hg-mobile-character-card" href="<?= hg_mpw_h($href) ?>" data-mobile-item data-mobile-search="<?= hg_mpw_h($name . ' ' . $alias . ' ' . $status) ?>">
        <?php if ($img !== ''): ?><img src="<?= hg_mpw_h($img) ?>" alt=""><?php else: ?><span class="hg-mobile-character-avatar" aria-hidden="true"></span><?php endif; ?>
        <span class="hg-mobile-character-main"><strong><?= hg_mpw_h($name) ?></strong><span><?= hg_mpw_h(implode(' · ', $meta)) ?></span></span>
    </a>
    <?php
}

function hg_mpw_excluded_chronicles(): string
{
    if (function_exists('hg_chronicle_scope_excluded_csv')) return hg_chronicle_scope_excluded_csv();
    return '2,7';
}

function hg_mpw_detail_data(mysqli $db, string $kind, int $id): ?array
{
    if ($kind === 'gifts') {
        $row = hg_powers_fetch_gift($db, $id);
        if (!$row || !is_array($row)) return null;
        return [
            'name' => $row['name'] ?? '', 'image' => hg_mpw_image((string)($row['image_url'] ?? ''), 'img/gifts'), 'origin' => $row['origin_name'] ?? '', 'owning_system_id' => (int)($row['system_id'] ?? 0),
            'fields' => [['Fêra', $row['resolved_system_name'] ?? ''], ['Tipo', $row['type_name'] ?? ''], ['Grupo', $row['gift_group'] ?? ''], ['Rango', $row['rank'] ?? ''], ['Tirada', hg_mpw_roll($row['attribute_name'] ?? '', $row['ability_name'] ?? '')]],
            'sections' => [['Descripción', $row['description'] ?? ''], ['Sistema', $row['mechanics_resolved'] ?? '']],
            'owners' => hg_powers_fetch_bridge_owners($db, 'dones', $id, hg_mpw_excluded_chronicles()), 'links' => [],
        ];
    }

    if ($kind === 'rites') {
        $row = hg_powers_fetch_rite($db, $id);
        if (!$row || !is_array($row)) return null;
        $rules = trim((string)($row['system_text'] ?? '')) !== '' ? $row['system_text'] : ($row['system_name'] ?? '');
        return [
            'name' => $row['name'] ?? '', 'image' => hg_mpw_image((string)($row['image_url'] ?? ''), 'img/rites'), 'origin' => $row['origin_name'] ?? '',
            'fields' => [['Fêra', $row['resolved_system_name'] ?? ''], ['Tipo', $row['type_name'] ?? ''], ['Nivel', $row['level'] ?? ''], ['Raza', $row['race'] ?? '']],
            'sections' => [['Descripción', $row['description'] ?? ''], ['Sistema', $rules]],
            'owners' => hg_powers_fetch_bridge_owners($db, 'rituales', $id, hg_mpw_excluded_chronicles()), 'links' => [],
        ];
    }

    if ($kind === 'totems') {
        $row = hg_powers_fetch_totem($db, $id);
        if (!$row || !is_array($row)) return null;
        $links = [];
        $groups = hg_powers_fetch_totem_links($db, $id, 'dim_groups');
        if (is_array($groups)) foreach ($groups as $group) $links[] = ['Grupo', $group['name'] ?? '', hg_mpw_url($db, 'dim_groups', '/groups', (int)($group['id'] ?? 0))];
        $organizations = hg_powers_fetch_totem_links($db, $id, 'dim_organizations');
        if (is_array($organizations)) foreach ($organizations as $organization) $links[] = ['Organizacion', $organization['name'] ?? '', hg_mpw_url($db, 'dim_organizations', '/organizations', (int)($organization['id'] ?? 0))];
        return [
            'name' => $row['name'] ?? '', 'image' => hg_mpw_image((string)($row['image_url'] ?? ''), 'img/totems'), 'origin' => $row['origin_name'] ?? '',
            'fields' => [['Tipo', $row['type_name'] ?? ''], ['Coste', $row['cost'] ?? '']],
            'sections' => [['Descripción', $row['description'] ?? ''], ['Rasgos', $row['traits'] ?? ''], ['Prohibición', $row['prohibited'] ?? '']],
            'owners' => hg_powers_fetch_totem_character_owners($db, $id, hg_mpw_excluded_chronicles()), 'links' => $links,
        ];
    }

    if ($kind === 'disciplines') {
        $row = hg_powers_fetch_discipline($db, $id);
        if (!$row || !is_array($row)) return null;
        return [
            'name' => $row['name'] ?? '', 'image' => hg_mpw_image((string)($row['image_url'] ?? ''), 'img/disciplines'), 'origin' => $row['origin_name'] ?? '',
            'fields' => [['Disciplina', $row['type_name'] ?? ''], ['Nivel', $row['level'] ?? ''], ['Tirada', hg_mpw_roll($row['attribute'] ?? '', $row['skill'] ?? '')]],
            'sections' => [['Descripción', $row['description'] ?? ''], ['Sistema', $row['system_name'] ?? '']],
            'owners' => [], 'links' => [],
        ];
    }

    return null;
}

function hg_mpw_render_detail(mysqli $db, string $kind, array $data): void
{
    $cfg = hg_mpw_catalog_config($kind);
    if (!$cfg) return;
    ?>
    <article class="hg-mobile-bio hg-mobile-rules-power">
        <nav class="hg-mobile-local-nav"><a href="<?= hg_mpw_h($cfg['list_base']) ?>?view=mobile">Volver a <?= hg_mpw_h(function_exists('mb_strtolower') ? mb_strtolower($cfg['title'], 'UTF-8') : strtolower($cfg['title'])) ?></a></nav>
        <section class="hg-mobile-section">
            <h1><?= hg_mpw_h($data['name'] ?? $cfg['singular']) ?></h1>
            <?php if (!empty($data['image'])): ?><img class="hg-mobile-power-image" src="<?= hg_mpw_h($data['image']) ?>" alt=""><?php endif; ?>
            <div class="hg-mobile-fact-grid">
                <?php foreach (($data['fields'] ?? []) as $field): ?>
                    <?php $value = trim((string)($field[1] ?? '')); if ($value === '') continue; ?>
                    <div><span><?= hg_mpw_h($field[0] ?? '') ?></span><strong><?= hg_mpw_h($value) ?></strong></div>
                <?php endforeach; ?>
                <?php $origin = trim((string)($data['origin'] ?? '')); if ($origin !== ''): ?><div><span><?= !empty($data['publication']) ? 'Fuente original' : 'Origen' ?></span><strong><?= hg_mpw_h($origin) ?></strong></div><?php endif; ?>
                <?php if (!empty($data['publication'])): ?><div><span>Publicación / versión</span><strong><?= hg_mpw_h($data['publication']) ?></strong></div><?php endif; ?>
                <?php if (!empty($data['edition_source'])): ?><div><span>Fuente de la versión Camazotz</span><strong><?= hg_mpw_h($data['edition_source']) ?></strong></div><?php endif; ?>
            </div>
        </section>

        <?php foreach (($data['sections'] ?? []) as $section): ?>
            <?php $html = (string)($section[1] ?? ''); if (trim(strip_tags($html)) === '') continue; ?>
            <section class="hg-mobile-section hg-mobile-prose hg-mobile-rich-body"><h2><?= hg_mpw_h($section[0] ?? '') ?></h2><?= $html ?></section>
        <?php endforeach; ?>

        <?php if (!empty($data['links'])): ?>
            <section class="hg-mobile-section"><h2>Vinculos</h2><div class="hg-mobile-card-list">
                <?php foreach ($data['links'] as $link): ?><a class="hg-mobile-card" href="<?= hg_mpw_h($link[2] ?? '#') ?>"><strong><?= hg_mpw_h($link[1] ?? '') ?></strong><span><?= hg_mpw_h($link[0] ?? '') ?></span></a><?php endforeach; ?>
            </div></section>
        <?php endif; ?>

        <?php $owners = is_array($data['owners'] ?? null) ? $data['owners'] : []; if ($owners): ?>
            <section class="hg-mobile-section"><h2>Personajes relacionados</h2><div class="hg-mobile-character-list" data-mobile-paginated data-mobile-search="1" data-page-size="20" data-search-placeholder="Buscar personajes">
                <?php foreach ($owners as $owner) hg_mpw_character_card($db, $owner); ?>
            </div></section>
        <?php endif; ?>
    </article>
    <?php
}

if (!isset($link) || !($link instanceof mysqli)) {
    hg_public_log_error('mobile_powers', 'missing DB connection');
    hg_public_render_error('Contenido no disponible', 'No se pudo cargar esta sección.');
    return;
}

$route = hg_request_route($hgRequest);
$raw = hg_request_query_param($hgRequest, 'b');
$metaTitle = "Poderes | Heaven's Gate";
$pageSect = 'Poderes';

if ($route === 'powers') {
    hg_mpw_render_hub();
    return;
}

$routes = [
    'gifts' => ['list' => ['listadones', 'fulldon', 'customdon', 'tipodon'], 'detail' => ['muestradon'], 'type_route' => 'tipodon', 'type_table' => 'dim_gift_types'],
    'rites' => ['list' => ['ritelist', 'fullrite', 'customrite', 'tiporite'], 'detail' => ['seerite'], 'type_route' => 'tiporite', 'type_table' => 'dim_rite_types'],
    'totems' => ['list' => ['listatotems', 'fulltotem', 'customtotem', 'tipototm'], 'detail' => ['muestratotem'], 'type_route' => 'tipototm', 'type_table' => 'dim_totem_types'],
    'disciplines' => ['list' => ['disciplinas', 'fulldisc', 'customdisc', 'tipodisc'], 'detail' => ['muestradisc'], 'type_route' => 'tipodisc', 'type_table' => 'dim_discipline_types'],
];

$kind = '';
$mode = '';
$routeCfg = null;
foreach ($routes as $candidateKind => $candidate) {
    if (in_array($route, $candidate['list'], true)) {
        $kind = $candidateKind;
        $mode = 'list';
        $routeCfg = $candidate;
        break;
    }
    if (in_array($route, $candidate['detail'], true)) {
        $kind = $candidateKind;
        $mode = 'detail';
        $routeCfg = $candidate;
        break;
    }
}

$cfg = $kind !== '' ? hg_mpw_catalog_config($kind) : null;
if (!$cfg || !$routeCfg) {
    hg_public_render_not_found('Sección no disponible', 'Esta parte aún no tiene controlador móvil.');
    return;
}

$metaTitle = $cfg['title'] . " | Heaven's Gate";
$pageSect = $cfg['title'];

if ($mode === 'list') {
    $rows = hg_powers_fetch_catalog($link, $kind);
    if ($rows === false) {
        hg_public_log_error('mobile_powers', 'catalog query failed for ' . $kind);
        hg_public_render_error('Listado no disponible', 'No se pudo cargar esta lista.');
        return;
    }

    if ($route === $routeCfg['type_route'] && $raw !== '') {
        $typeId = hg_mpw_resolve_id($link, $routeCfg['type_table'], $raw);
        $type = $typeId > 0 ? hg_powers_fetch_type($link, $kind, $typeId) : null;
        if ($type) $rows = hg_mpw_filter_type($rows, $kind, $type);
    }

    hg_mpw_render_list($link, $kind, $rows);
    return;
}

$id = hg_mpw_resolve_id($link, $cfg['table'], $raw);
if ($id <= 0) {
    hg_public_render_not_found($cfg['singular'] . ' no encontrado', 'No se pudo localizar el elemento solicitado.');
    return;
}

$data = hg_mpw_detail_data($link, $kind, $id);
if (!$data) {
    hg_public_render_not_found($cfg['singular'] . ' no encontrado', 'No se pudo localizar el elemento solicitado.');
    return;
}

if (in_array($kind, ['gifts', 'totems'], true)) {
    $explicitContext = hg_camazotz_publication_requested($hgRequest);
    $allowed = $kind === 'gifts'
        ? hg_camazotz_publication_eligible((int)($data['owning_system_id'] ?? 0), $explicitContext)
        : $explicitContext;
    $publication = hg_camazotz_publication_for($link, $cfg['table'], $id, $allowed);
    if ($publication) {
        $data['publication'] = hg_camazotz_publication_label($publication);
        if ($kind === 'gifts') {
            $source = hg_camazotz_gift_availability_source($link, $id);
            if ($source !== '' && $source !== trim((string)($data['origin'] ?? ''))) {
                $data['edition_source'] = $source;
            }
        }
    }
}
$metaTitle = (string)($data['name'] ?? $cfg['singular']) . ' | ' . $cfg['title'] . " | Heaven's Gate";
hg_mpw_render_detail($link, $kind, $data);