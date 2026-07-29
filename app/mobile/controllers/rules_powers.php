<?php

include_once(__DIR__ . '/../../helpers/public_response.php');
include_once(__DIR__ . '/../../helpers/character_avatar.php');

function hg_mrp_h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function hg_mrp_table(mysqli $db, string $table): bool {
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];
    $rs = $db->query("SHOW TABLES LIKE '" . $db->real_escape_string($table) . "'");
    if (!$rs) return $cache[$table] = false;
    $ok = $rs->num_rows > 0;
    $rs->free();
    return $cache[$table] = $ok;
}
function hg_mrp_col(mysqli $db, string $table, string $col): bool {
    static $cache = [];
    $key = $table . ':' . $col;
    if (isset($cache[$key])) return $cache[$key];
    $ok = false;
    if ($st = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?")) {
        $st->bind_param('ss', $table, $col);
        $st->execute();
        $st->bind_result($n);
        $st->fetch();
        $st->close();
        $ok = ((int)$n > 0);
    }
    return $cache[$key] = $ok;
}
function hg_mrp_url(mysqli $db, string $table, string $base, int $id): string {
    return $id > 0 && function_exists('pretty_url') ? pretty_url($db, $table, $base, $id) : rtrim($base, '/') . '/' . $id;
}
function hg_mrp_excerpt(string $html, int $max = 135): string {
    $text = trim(strip_tags($html));
    if ($text === '') return '';
    if (function_exists('mb_strlen') && function_exists('mb_substr')) return mb_strlen($text, 'UTF-8') > $max ? mb_substr($text, 0, $max, 'UTF-8') . '...' : $text;
    return strlen($text) > $max ? substr($text, 0, $max) . '...' : $text;
}
function hg_mrp_lookup(mysqli $db, string $table, $id, string $col = 'name'): string {
    static $cache = [];
    $id = (int)$id;
    $key = $table . ':' . $col . ':' . $id;
    if (isset($cache[$key])) return $cache[$key];
    if ($id <= 0 || !hg_mrp_table($db, $table) || !hg_mrp_col($db, $table, $col)) return $cache[$key] = '';
    if (!$st = $db->prepare("SELECT `{$col}` FROM `{$table}` WHERE id = ? LIMIT 1")) return $cache[$key] = '';
    $st->bind_param('i', $id);
    $st->execute();
    $rs = $st->get_result();
    $row = $rs ? $rs->fetch_assoc() : null;
    $st->close();
    return $cache[$key] = trim((string)($row[$col] ?? ''));
}
function hg_mrp_resolve(mysqli $db, string $table, string $raw): int {
    $raw = trim(rawurldecode($raw));
    if ($raw === '') return 0;
    if (function_exists('resolve_pretty_id')) {
        $id = resolve_pretty_id($db, $table, $raw);
        if ((int)$id > 0) return (int)$id;
    }
    if (preg_match('/^\d+$/', $raw)) return (int)$raw;
    if (!function_exists('slugify_pretty_id')) return 0;
    $pretty = hg_mrp_col($db, $table, 'pretty_id') ? 'pretty_id' : "'' AS pretty_id";
    if ($rs = $db->query("SELECT id, name, {$pretty} FROM `{$table}`")) {
        while ($r = $rs->fetch_assoc()) {
            if ((string)($r['pretty_id'] ?? '') === $raw || slugify_pretty_id((string)($r['name'] ?? '')) === $raw) {
                $id = (int)$r['id'];
                $rs->free();
                return $id;
            }
        }
        $rs->free();
    }
    return 0;
}
function hg_mrp_img(string $raw, string $dir): string {
    $raw = trim($raw);
    if ($raw === '') return '';
    return strpos($raw, '/') !== false ? $raw : trim($dir, '/') . '/' . $raw;
}
function hg_mrp_field(mysqli $db, array $row, array $field): string {
    $src = $field[1] ?? '';
    if (is_array($src)) {
        $parts = [];
        foreach ($src as $col) if (trim((string)($row[$col] ?? '')) !== '') $parts[] = trim((string)$row[$col]);
        return implode(' + ', $parts);
    }
    if (($field[2] ?? '') !== '') return hg_mrp_lookup($db, (string)$field[2], $row[$src] ?? 0, (string)($field[3] ?? 'name'));
    if (is_string($src) && strpos($src, '|') !== false) {
        foreach (explode('|', $src) as $col) if (array_key_exists($col, $row) && trim((string)$row[$col]) !== '') return trim((string)$row[$col]);
        return '';
    }
    return trim((string)($row[$src] ?? ''));
}
function hg_mrp_character_card(mysqli $db, array $c): void {
    $id = (int)($c['id'] ?? 0);
    $name = (string)($c['name'] ?? $c['nombre'] ?? '');
    $alias = (string)($c['alias'] ?? '');
    $status = (string)($c['status'] ?? '');
    $traitValue = (int)($c['trait_value'] ?? 0);
    $img = function_exists('hg_character_avatar_url') ? hg_character_avatar_url((string)($c['image_url'] ?? ''), (string)($c['gender'] ?? '')) : '';
    $href = hg_mrp_url($db, 'fact_characters', '/characters', $id);
    $meta = [];
    if ($traitValue > 0) $meta[] = $traitValue . ($traitValue === 1 ? ' punto' : ' puntos');
    if ($alias !== '') $meta[] = $alias;
    if ($status !== '') $meta[] = $status;
    ?>
    <a class="hg-mobile-character-card" href="<?= hg_mrp_h($href) ?>" data-mobile-item data-mobile-search="<?= hg_mrp_h($name . ' ' . $alias . ' ' . $status . ' ' . $traitValue) ?>">
        <?php if ($img !== ''): ?><img src="<?= hg_mrp_h($img) ?>" alt=""><?php else: ?><span class="hg-mobile-character-avatar" aria-hidden="true"></span><?php endif; ?>
        <span class="hg-mobile-character-main"><strong><?= hg_mrp_h($name) ?></strong><span><?= hg_mrp_h(implode(' · ', $meta)) ?></span></span>
    </a>
    <?php
}
function hg_mrp_owner_rows(mysqli $db, array $owner, int $id): array {
    if (empty($owner['table']) || empty($owner['where']) || !hg_mrp_table($db, $owner['table']) || !hg_mrp_table($db, 'fact_characters')) return [];
    $kindSql = function_exists('hg_character_kind_select') ? hg_character_kind_select($db, 'c') : "''";
    $chronicleAnd = function_exists('hg_mobile_chronicle_exclusion_and') ? hg_mobile_chronicle_exclusion_and('c') : ' AND c.chronicle_id NOT IN (2,7) ';
    $sql = "SELECT DISTINCT c.id, c.name, c.alias, c.image_url, c.gender, COALESCE(s.label, '') AS status, c.status_id, {$kindSql} AS character_kind FROM `{$owner['table']}` b JOIN fact_characters c ON c.id = b.character_id LEFT JOIN dim_character_status s ON s.id = c.status_id WHERE {$owner['where']} {$chronicleAnd} ORDER BY c.name";
    $rows = [];
    if ($st = $db->prepare($sql)) {
        $st->bind_param('i', $id);
        $st->execute();
        $rs = $st->get_result();
        while ($rs && ($r = $rs->fetch_assoc())) $rows[] = $r;
        $st->close();
    }
    return $rows;
}
function hg_mrp_render_hub(string $title, array $items): void { ?>
    <section class="hg-mobile-section"><h1><?= hg_mrp_h($title) ?></h1></section>
    <section class="hg-mobile-section"><div class="hg-mobile-card-list">
        <?php foreach ($items as $it): ?><a class="hg-mobile-card" href="<?= hg_mrp_h($it[1]) ?>"><strong><?= hg_mrp_h($it[0]) ?></strong><span><?= hg_mrp_h($it[2]) ?></span></a><?php endforeach; ?>
    </div></section>
<?php }
function hg_mrp_render_list(mysqli $db, array $cfg, array $rows): void { ?>
    <section class="hg-mobile-section"><h1><?= hg_mrp_h($cfg['title']) ?></h1><p class="hg-mobile-muted"><?= number_format(count($rows), 0, ',', '.') ?> elementos</p></section>
    <?php
        $isTraitList = ($cfg['key'] ?? '') === 'traits';
        $traitKinds = [];
        if ($isTraitList) {
            foreach ($rows as $traitRow) {
                $traitKind = hg_mrp_field($db, $traitRow, ['', 'kind|tipo']);
                if ($traitKind !== '') $traitKinds[$traitKind] = $traitKind;
            }
            natcasesort($traitKinds);
        }
    ?>
    <section class="hg-mobile-section"><div class="hg-mobile-card-list" data-mobile-paginated data-mobile-search="1" data-page-size="20" data-search-placeholder="Buscar">
        <?php if ($traitKinds): ?>
            <label class="hg-mobile-list-filter">Tipo de rasgo
                <select data-mobile-list-filter aria-label="Filtrar por tipo de rasgo">
                    <option value="">Todos los tipos</option>
                    <?php foreach ($traitKinds as $traitKind): ?><option value="<?= hg_mrp_h($traitKind) ?>"><?= hg_mrp_h($traitKind) ?></option><?php endforeach; ?>
                </select>
            </label>
        <?php endif; ?>
        <?php if (!$rows): ?><p class="hg-mobile-muted">No hay elementos disponibles.</p><?php endif; ?>
        <?php foreach ($rows as $r): ?>
            <?php
                $id = (int)($r['id'] ?? 0);
                $href = hg_mrp_url($db, $cfg['table'], $cfg['item_base'], $id);
                $bits = [];
                foreach ($cfg['fields'] as $f) { $v = hg_mrp_field($db, $r, $f); if ($v !== '') $bits[] = $f[0] . ': ' . $v; }
                $body = '';
                foreach (($cfg['body'] ?? []) as $b) { $body = hg_mrp_field($db, $r, ['', $b[1]]); if ($body !== '') break; }
                $search = trim((string)($r['name'] ?? '') . ' ' . implode(' ', $bits) . ' ' . strip_tags($body));
                $traitKind = $isTraitList ? hg_mrp_field($db, $r, ['', 'kind|tipo']) : '';
            ?>
            <a class="hg-mobile-card" href="<?= hg_mrp_h($href) ?>" data-mobile-item data-mobile-search="<?= hg_mrp_h($search) ?>"<?= $traitKind !== '' ? ' data-mobile-filter-value="' . hg_mrp_h($traitKind) . '"' : '' ?>>
                <strong><?= hg_mrp_h($r['name'] ?? '') ?></strong>
                <?php foreach ($bits as $bit): ?><span><?= hg_mrp_h($bit) ?></span><?php endforeach; ?>
                <?php $excerpt = hg_mrp_excerpt($body); if ($excerpt !== ''): ?><span><?= hg_mrp_h($excerpt) ?></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div></section>
<?php }
function hg_mrp_render_detail(mysqli $db, array $cfg, array $row, int $id): void {
    $title = (string)($row['name'] ?? $cfg['singular']);
    $img = '';
    if (!empty($cfg['image_dir']) && !empty($row['image_url'])) $img = hg_mrp_img((string)$row['image_url'], (string)$cfg['image_dir']);
    ?>
    <article class="hg-mobile-bio hg-mobile-rules-power">
        <nav class="hg-mobile-local-nav"><a href="<?= hg_mrp_h($cfg['list_base']) ?>?view=mobile">Volver a <?= hg_mrp_h(function_exists('mb_strtolower') ? mb_strtolower($cfg['title'], 'UTF-8') : strtolower($cfg['title'])) ?></a></nav>
        <section class="hg-mobile-section">
            <h1><?= hg_mrp_h($title) ?></h1>
            <?php if ($img !== ''): ?><img class="hg-mobile-power-image" src="<?= hg_mrp_h($img) ?>" alt=""><?php endif; ?>
            <div class="hg-mobile-fact-grid">
                <?php foreach ($cfg['fields'] as $f): ?>
                    <?php $v = hg_mrp_field($db, $row, $f); if ($v === '') continue; ?>
                    <div><span><?= hg_mrp_h($f[0]) ?></span><strong><?= hg_mrp_h($v) ?></strong></div>
                <?php endforeach; ?>
                <?php if (isset($row['bibliography_id'])): $origin = hg_mrp_lookup($db, 'dim_bibliographies', $row['bibliography_id']); if ($origin !== ''): ?><div><span>Origen</span><strong><?= hg_mrp_h($origin) ?></strong></div><?php endif; endif; ?>
            </div>
        </section>
        <?php foreach (($cfg['body'] ?? []) as $b): ?>
            <?php $html = hg_mrp_field($db, $row, ['', $b[1]]); if (trim(strip_tags($html)) === '') continue; ?>
            <section class="hg-mobile-section hg-mobile-prose hg-mobile-rich-body"><h2><?= hg_mrp_h($b[0]) ?></h2><?= $html ?></section>
        <?php endforeach; ?>
        <?php if (($cfg['key'] ?? '') === 'totems') hg_mrp_render_totem_links($db, $id); ?>
        <?php
            $owners = hg_mrp_special_owners($db, (string)($cfg['key'] ?? ''), $id);
            if (!$owners) $owners = hg_mrp_owner_rows($db, $cfg['owners'] ?? [], $id);
        ?>
        <?php if ($owners): ?>
            <section class="hg-mobile-section"><h2>Personajes relacionados</h2><div class="hg-mobile-character-list" data-mobile-paginated data-mobile-search="1" data-page-size="20" data-search-placeholder="Buscar personajes">
                <?php foreach ($owners as $o) hg_mrp_character_card($db, $o); ?>
            </div></section>
        <?php endif; ?>
    </article>
<?php }
function hg_mrp_special_owners(mysqli $db, string $key, int $id): array {
    if (!hg_mrp_table($db, 'fact_characters')) return [];
    $chronicleAnd = function_exists('hg_mobile_chronicle_exclusion_and') ? hg_mobile_chronicle_exclusion_and('c') : ' AND c.chronicle_id NOT IN (2,7) ';
    $sql = '';
    if ($key === 'traits' && hg_mrp_table($db, 'bridge_characters_traits')) {
        $sql = "SELECT c.id, c.name, c.alias, c.image_url, c.gender, COALESCE(s.label, '') AS status, b.value AS trait_value FROM bridge_characters_traits b JOIN fact_characters c ON c.id = b.character_id LEFT JOIN dim_character_status s ON s.id = c.status_id WHERE b.trait_id = ? AND b.value >= 1 {$chronicleAnd} ORDER BY b.value ASC, c.name ASC";
    } elseif ($key === 'archetypes' && hg_mrp_col($db, 'fact_characters', 'nature_id') && hg_mrp_col($db, 'fact_characters', 'demeanor_id')) {
        $sql = "SELECT c.id, c.name, c.alias, c.image_url, c.gender, COALESCE(s.label, '') AS status FROM fact_characters c LEFT JOIN dim_character_status s ON s.id = c.status_id WHERE (c.nature_id = ? OR c.demeanor_id = ?) {$chronicleAnd} ORDER BY c.name";
    } elseif ($key === 'totems' && hg_mrp_col($db, 'fact_characters', 'totem_id')) {
        $sql = "SELECT c.id, c.name, c.alias, c.image_url, c.gender, COALESCE(s.label, '') AS status FROM fact_characters c LEFT JOIN dim_character_status s ON s.id = c.status_id WHERE c.totem_id = ? {$chronicleAnd} ORDER BY c.name";
    }
    if ($sql === '') return [];
    $rows = [];
    if ($st = $db->prepare($sql)) {
        if ($key === 'archetypes') $st->bind_param('ii', $id, $id); else $st->bind_param('i', $id);
        $st->execute();
        $rs = $st->get_result();
        while ($rs && ($r = $rs->fetch_assoc())) $rows[] = $r;
        $st->close();
    }
    return $rows;
}
function hg_mrp_render_totem_links(mysqli $db, int $id): void {
    $links = [];
    if (hg_mrp_table($db, 'dim_groups') && hg_mrp_col($db, 'dim_groups', 'totem_id') && ($st = $db->prepare('SELECT id, name FROM dim_groups WHERE totem_id = ? ORDER BY name'))) {
        $st->bind_param('i', $id); $st->execute(); $rs = $st->get_result(); while ($rs && ($r = $rs->fetch_assoc())) $links[] = ['Grupo', $r['name'], hg_mrp_url($db, 'dim_groups', '/groups', (int)$r['id'])]; $st->close();
    }
    if (hg_mrp_table($db, 'dim_organizations') && hg_mrp_col($db, 'dim_organizations', 'totem_id') && ($st = $db->prepare('SELECT id, name FROM dim_organizations WHERE totem_id = ? ORDER BY name'))) {
        $st->bind_param('i', $id); $st->execute(); $rs = $st->get_result(); while ($rs && ($r = $rs->fetch_assoc())) $links[] = ['Organizacion', $r['name'], hg_mrp_url($db, 'dim_organizations', '/organizations', (int)$r['id'])]; $st->close();
    }
    if (!$links) return;
    echo '<section class="hg-mobile-section"><h2>Vinculos</h2><div class="hg-mobile-card-list">';
    foreach ($links as $l) echo '<a class="hg-mobile-card" href="' . hg_mrp_h($l[2]) . '"><strong>' . hg_mrp_h($l[1]) . '</strong><span>' . hg_mrp_h($l[0]) . '</span></a>';
    echo '</div></section>';
}
if (!isset($link) || !($link instanceof mysqli)) {
    hg_public_log_error('mobile_rules_powers', 'missing DB connection');
    hg_public_render_error('Contenido no disponible', 'No se pudo cargar esta sección.');
    return;
}

$p = trim((string)($_GET['p'] ?? ''));
$raw = trim((string)($_GET['b'] ?? ''));
$metaTitle = "Reglas y poderes | Heaven's Gate";
$pageSect = 'Reglas y poderes';

$rulesHub = [
    ['Rasgos', '/rules/traits', 'Atributos, habilidades, trasfondos y otros rasgos numericos.'],
    ['Méritos y Defectos', '/rules/merits-flaws', 'Ventajas, desventajas y rasgos especiales.'],
    ['Condiciones', '/rules/conditions', 'Estados, heridas, trastornos y efectos persistentes.'],
    ['Personalidades', '/rules/archetypes', 'Naturaleza, conducta y arquetipos de interpretacion.'],
    ['Maniobras de pelea', '/rules/maneuvers', 'Tecnicas de combate y acciones especiales.'],
];
$powersHub = [
    ['Dones', '/powers/gifts', 'Poderes espirituales Garou y Fera.'],
    ['Rituales', '/powers/rites', 'Ritos y ceremonias con efecto mistico.'],
    ['Totems', '/powers/totems', 'Espíritus guia, beneficios y prohibiciónes.'],
    ['Disciplinas', '/powers/disciplines', 'Poderes vampiricos organizados por disciplina.'],
];

$catalogs = [
    'traits' => [
        'key' => 'traits', 'title' => 'Rasgos', 'singular' => 'Rasgo', 'table' => 'dim_traits', 'list_base' => '/rules/traits', 'item_base' => '/rules/traits',
        'list_routes' => ['listarasgos'], 'detail_routes' => ['verrasgo'],
        'fields' => [['Tipo', 'kind|tipo'], ['Clasificacion', 'classification']],
        'body' => [['Descripción', 'description'], ['Niveles', 'levels'], ['Poseedores', 'posse'], ['Especial', 'special']],
        'owners' => ['table' => 'bridge_characters_traits', 'where' => 'b.trait_id = ? AND b.value >= 1'],
        'order' => 'name ASC',
    ],
    'conditions' => [
        'key' => 'conditions', 'title' => 'Condiciones', 'singular' => 'Condicion', 'table' => 'dim_character_conditions', 'list_base' => '/rules/conditions', 'item_base' => '/rules/conditions',
        'list_routes' => ['listconditions'], 'detail_routes' => ['vercondition'],
        'fields' => [['Categoría', 'category'], ['Max. repeticiones', 'max_instances']],
        'body' => [['Descripción', 'description']],
        'owners' => ['table' => 'bridge_characters_conditions', 'where' => hg_mrp_col($link, 'bridge_characters_conditions', 'is_active') ? 'b.condition_id = ? AND (b.is_active = 1 OR b.is_active IS NULL)' : 'b.condition_id = ?'],
        'order' => 'name ASC',
    ],
    'merits' => [
        'key' => 'merits', 'title' => 'Méritos y Defectos', 'singular' => 'Merito o defecto', 'table' => 'dim_merits_flaws', 'list_base' => '/rules/merits-flaws', 'item_base' => '/rules/merits-flaws',
        'list_routes' => ['listamyd'], 'detail_routes' => ['vermyd'],
        'fields' => [['Tipo', 'kind|tipo'], ['Sistema', 'system_name|sistema'], ['Categoría', 'affiliation|afiliación'], ['Coste', 'cost|coste']],
        'body' => [['Descripción', 'description|descripción']],
        'owners' => ['table' => 'bridge_characters_merits_flaws', 'where' => 'b.merit_flaw_id = ?'],
        'order' => 'name ASC',
    ],
    'maneuvers' => [
        'key' => 'maneuvers', 'title' => 'Maniobras de pelea', 'singular' => 'Maniobra', 'table' => 'fact_combat_maneuvers', 'list_base' => '/rules/maneuvers', 'item_base' => '/rules/maneuvers', 'image_dir' => 'img/maneuvers',
        'list_routes' => ['maneuver'], 'detail_routes' => ['vermaneu'],
        'fields' => [['Sistema', 'system_name'], ['Acciones', 'actions'], ['Tirada', 'roll'], ['Dificultad', 'difficulty'], ['Daño', 'damage']],
        'body' => [['Descripción', 'description']],
        'order' => 'system_name ASC, name ASC',
    ],
    'archetypes' => [
        'key' => 'archetypes', 'title' => 'Arquetipos', 'singular' => 'Arquetipo', 'table' => 'dim_archetypes', 'list_base' => '/rules/archetypes', 'item_base' => '/rules/archetypes',
        'list_routes' => ['arquetip'], 'detail_routes' => ['verarch'],
        'fields' => [],
        'body' => [['Descripción', 'description'], ['Fuerza de Voluntad', 'willpower_text']],
        'order' => 'name ASC',
    ],
    'gifts' => [
        'key' => 'gifts', 'title' => 'Dones', 'singular' => 'Don', 'table' => 'fact_gifts', 'list_base' => '/powers/gifts', 'item_base' => '/powers/gift', 'image_dir' => 'img/gifts',
        'list_routes' => ['listadones','fulldon','customdon','dones','tipodon'], 'detail_routes' => ['muestradon'], 'type_route' => 'tipodon', 'type_col' => 'kind', 'type_table' => 'dim_gift_types',
        'fields' => [['Fera', 'system_id', 'dim_systems'], ['Tipo', 'kind', 'dim_gift_types'], ['Grupo', 'gift_group'], ['Rango', 'rank'], ['Tirada', ['attribute_name','ability_name']]],
        'body' => [['Descripción', 'description'], ['Sistema', hg_mrp_col($link, 'fact_gifts', 'mechanics_text') ? 'mechanics_text' : 'system_name']],
        'owners' => ['table' => 'bridge_characters_powers', 'where' => "b.power_kind = 'dones' AND b.power_id = ?"],
        'order' => 'rank ASC, name ASC',
    ],
    'rites' => [
        'key' => 'rites', 'title' => 'Rituales', 'singular' => 'Ritual', 'table' => 'fact_rites', 'list_base' => '/powers/rites', 'item_base' => '/powers/rite', 'image_dir' => 'img/rites',
        'list_routes' => ['ritelist','fullrite','customrite','rites','tiporite'], 'detail_routes' => ['seerite'], 'type_route' => 'tiporite', 'type_col' => 'kind', 'type_table' => 'dim_rite_types',
        'fields' => [['Fera', 'system_id', 'dim_systems'], ['Tipo', 'kind', 'dim_rite_types'], ['Nivel', 'level'], ['Raza', 'race']],
        'body' => [['Descripción', 'description'], ['Sistema', 'system_text|system_name']],
        'owners' => ['table' => 'bridge_characters_powers', 'where' => "b.power_kind = 'rituales' AND b.power_id = ?"],
        'order' => 'level ASC, name ASC',
    ],
    'totems' => [
        'key' => 'totems', 'title' => 'Totems', 'singular' => 'Totem', 'table' => 'dim_totems', 'list_base' => '/powers/totems', 'item_base' => '/powers/totem', 'image_dir' => 'img/totems',
        'list_routes' => ['listatotems','fulltotem','customtotem','totems','tipototm'], 'detail_routes' => ['muestratotem'], 'type_route' => 'tipototm', 'type_col' => 'totem_type_id', 'type_table' => 'dim_totem_types',
        'fields' => [['Tipo', 'totem_type_id', 'dim_totem_types'], ['Coste', 'cost']],
        'body' => [['Descripción', 'description'], ['Rasgos', 'traits'], ['Prohibición', 'prohibited']],
        'order' => 'cost ASC, name ASC',
    ],
    'disciplines' => [
        'key' => 'disciplines', 'title' => 'Disciplinas', 'singular' => 'Disciplina', 'table' => 'fact_discipline_powers', 'list_base' => '/powers/disciplines', 'item_base' => '/powers/discipline', 'image_dir' => 'img/disciplines',
        'list_routes' => ['disciplinas','fulldisc','customdisc','tipodisc'], 'detail_routes' => ['muestradisc'], 'type_route' => 'tipodisc', 'type_col' => 'disc', 'type_table' => 'dim_discipline_types',
        'fields' => [['Disciplina', 'disc', 'dim_discipline_types'], ['Nivel', 'level'], ['Tirada', ['attribute','skill']]],
        'body' => [['Descripción', 'description'], ['Sistema', 'system_name']],
        'order' => 'disc ASC, level ASC, name ASC',
    ],
];
if ($p === 'rules') {
    $metaTitle = "Reglas | Heaven's Gate";
    $pageSect = 'Reglas';
    hg_mrp_render_hub('Reglas', $rulesHub);
    return;
}
if ($p === 'powers') {
    $metaTitle = "Poderes | Heaven's Gate";
    $pageSect = 'Poderes';
    hg_mrp_render_hub('Poderes', $powersHub);
    return;
}

$active = null;
$mode = '';
foreach ($catalogs as $cfg) {
    if (in_array($p, $cfg['list_routes'], true)) { $active = $cfg; $mode = 'list'; break; }
    if (in_array($p, $cfg['detail_routes'], true)) { $active = $cfg; $mode = 'detail'; break; }
}
if (!$active) {
    hg_public_render_not_found('Sección no disponible', 'Esta parte aún no tiene controlador móvil.');
    return;
}

$metaTitle = $active['title'] . " | Heaven's Gate";
$pageSect = $active['title'];

if ($mode === 'list') {
    $where = '';
    if (($active['type_route'] ?? '') === $p && $raw !== '') {
        $typeId = hg_mrp_resolve($link, (string)$active['type_table'], $raw);
        if ($typeId > 0 && hg_mrp_col($link, $active['table'], (string)$active['type_col'])) $where = ' WHERE `' . $active['type_col'] . '` = ' . (int)$typeId;
    }
    $order = (string)($active['order'] ?? 'name ASC');
    if (!hg_mrp_table($link, $active['table'])) {
        hg_public_render_error('Listado no disponible', 'Falta la tabla requerida.');
        return;
    }
    $sql = 'SELECT * FROM `' . $active['table'] . '`' . $where . ' ORDER BY ' . $order;
    $rows = [];
    if ($rs = $link->query($sql)) {
        while ($r = $rs->fetch_assoc()) $rows[] = $r;
        $rs->free();
    } else {
        hg_public_log_error('mobile_rules_powers', 'list query failed: ' . $link->error . ' sql=' . $sql);
        hg_public_render_error('Listado no disponible', 'No se pudo cargar esta lista.');
        return;
    }
    hg_mrp_render_list($link, $active, $rows);
    return;
}

$id = hg_mrp_resolve($link, $active['table'], $raw);
if ($id <= 0 || !$st = $link->prepare('SELECT * FROM `' . $active['table'] . '` WHERE id = ? LIMIT 1')) {
    hg_public_render_not_found($active['singular'] . ' no encontrado', 'No se pudo localizar el elemento solicitado.');
    return;
}
$st->bind_param('i', $id);
$st->execute();
$rs = $st->get_result();
$row = $rs ? $rs->fetch_assoc() : null;
$st->close();
if (!$row) {
    hg_public_render_not_found($active['singular'] . ' no encontrado', 'No se pudo localizar el elemento solicitado.');
    return;
}
$metaTitle = (string)($row['name'] ?? $active['singular']) . ' | ' . $active['title'] . " | Heaven's Gate";
hg_mrp_render_detail($link, $active, $row, $id);
