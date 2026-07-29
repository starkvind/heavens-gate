<?php

include_once(__DIR__ . '/../../helpers/public_response.php');
include_once(__DIR__ . '/../../helpers/pretty.php');
include_once(__DIR__ . '/../../helpers/character_avatar.php');
include_once(__DIR__ . '/../../helpers/system_energy_resource.php');
include_once(__DIR__ . '/../helpers/chronicle_scope.php');

$metaTitle = "Sistemas | Heaven's Gate";
$metaDescription = 'Sistemas móviles de Heaven\'s Gate.';
$pageSect = 'Sistemas';

if (!function_exists('hg_mobile_sys_h')) {
    function hg_mobile_sys_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('hg_mobile_sys_col_exists')) {
    function hg_mobile_sys_col_exists(mysqli $link, string $table, string $column): bool
    {
        static $cache = [];
        $key = $table . ':' . $column;
        if (isset($cache[$key])) return $cache[$key];
        $ok = false;
        if ($st = $link->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?")) {
            $st->bind_param('ss', $table, $column);
            $st->execute();
            $st->bind_result($count);
            $st->fetch();
            $st->close();
            $ok = ((int)$count > 0);
        }
        return $cache[$key] = $ok;
    }
}

if (!function_exists('hg_mobile_sys_table_exists')) {
    function hg_mobile_sys_table_exists(mysqli $link, string $table): bool
    {
        static $cache = [];
        if (isset($cache[$table])) return $cache[$table];
        $rs = $link->query("SHOW TABLES LIKE '" . $link->real_escape_string($table) . "'");
        if (!$rs) return $cache[$table] = false;
        $ok = $rs->num_rows > 0;
        $rs->free();
        return $cache[$table] = $ok;
    }
}

if (!function_exists('hg_mobile_sys_resolve')) {
    function hg_mobile_sys_resolve(mysqli $link, string $table, string $raw): int
    {
        $raw = trim(rawurldecode($raw));
        if ($raw === '') return 0;
        if (preg_match('/^\d+$/', $raw)) return (int)$raw;
        if (function_exists('resolve_pretty_id')) {
            $resolved = resolve_pretty_id($link, $table, $raw);
            if ((int)$resolved > 0) return (int)$resolved;
        }
        return 0;
    }
}

if (!function_exists('hg_mobile_sys_url')) {
    function hg_mobile_sys_url(mysqli $link, string $table, string $base, int $id): string
    {
        return $id > 0 && function_exists('pretty_url') ? pretty_url($link, $table, $base, $id) : (rtrim($base, '/') . '/' . $id);
    }
}

if (!function_exists('hg_mobile_sys_image')) {
    function hg_mobile_sys_image($value, string $fallback = 'img/system/nada.webp'): string
    {
        $img = trim((string)$value);
        return $img !== '' ? $img : $fallback;
    }
}

if (!function_exists('hg_mobile_sys_excerpt')) {
    function hg_mobile_sys_excerpt(string $text, int $max = 140): string
    {
        $text = trim(strip_tags($text));
        if ($text === '') return '';
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($text, 'UTF-8') > $max ? mb_substr($text, 0, $max, 'UTF-8') . '...' : $text;
        }
        return strlen($text) > $max ? substr($text, 0, $max) . '...' : $text;
    }
}

if (!function_exists('hg_mobile_sys_stat')) {
    function hg_mobile_sys_stat(string $label, $value): void
    {
        $text = trim((string)$value);
        if ($text === '' || $text === '0') return;
        ?>
        <div class="hg-mobile-sys-stat"><span><?= hg_mobile_sys_h($label) ?></span><strong><?= hg_mobile_sys_h($text) ?></strong></div>
        <?php
    }
}

if (!function_exists('hg_mobile_sys_section')) {
    function hg_mobile_sys_section(string $title, array $items, string $empty = ''): void
    {
        ?>
        <section class="hg-mobile-section">
            <h2><?= hg_mobile_sys_h($title) ?></h2>
            <?php if (empty($items)): ?>
                <?php if ($empty !== ''): ?><p class="hg-mobile-muted"><?= hg_mobile_sys_h($empty) ?></p><?php endif; ?>
            <?php else: ?>
                <div class="hg-mobile-sys-chip-grid" data-mobile-paginated data-mobile-search="1" data-page-size="18" data-search-placeholder="Buscar en <?= hg_mobile_sys_h($title) ?>" data-empty-text="No hay resultados.">
                    <?php foreach ($items as $item): ?>
                        <a class="hg-mobile-sys-chip" href="<?= hg_mobile_sys_h($item['href'] ?? '#') ?>" data-mobile-item data-mobile-search="<?= hg_mobile_sys_h($item['search'] ?? $item['label'] ?? '') ?>">
                            <strong><?= hg_mobile_sys_h($item['label'] ?? '') ?></strong>
                            <?php if (trim((string)($item['meta'] ?? '')) !== ''): ?><span><?= hg_mobile_sys_h($item['meta']) ?></span><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php
    }
}

if (!isset($link) || !($link instanceof mysqli)) {
    hg_public_log_error('mobile_systems', 'missing DB connection');
    hg_public_render_error('Sistemas no disponibles', 'No se pudo cargar Sistemas.');
    return;
}
mysqli_set_charset($link, 'utf8mb4');

$route = (string)($_GET['p'] ?? 'listasistemas');

if ($route === 'listasistemas') {
    $systems = [];
    $sql = "
        SELECT s.id, s.pretty_id, s.sort_order, s.name, s.image_url, s.description, s.forms, COALESCE(b.name, '') AS origin
        FROM dim_systems s
        LEFT JOIN dim_bibliographies b ON b.id = s.bibliography_id
        ORDER BY s.sort_order ASC, s.name ASC, s.id ASC
    ";
    if ($res = $link->query($sql)) {
        while ($row = $res->fetch_assoc()) $systems[] = $row;
        $res->free();
    } else {
        hg_public_log_error('mobile_systems', 'list query failed: ' . mysqli_error($link));
    }
    ?>
    <section class="hg-mobile-section">
        <h1>Sistemas</h1>
        <p class="hg-mobile-muted"><?= number_format(count($systems), 0, ',', '.') ?> sistemas</p>
    </section>
    <section class="hg-mobile-section">
        <div class="hg-mobile-card-list hg-mobile-sys-list" data-mobile-paginated data-mobile-search="1" data-page-size="20" data-search-placeholder="Buscar sistema u origen" data-empty-text="No hay sistemas con ese filtro.">
            <?php foreach ($systems as $system): ?>
                <?php
                    $id = (int)($system['id'] ?? 0);
                    $name = trim((string)($system['name'] ?? ''));
                    $origin = trim((string)($system['origin'] ?? ''));
                    $desc = hg_mobile_sys_excerpt((string)($system['description'] ?? ''));
                    $search = trim($name . ' ' . $origin . ' ' . $desc);
                ?>
                <a class="hg-mobile-card hg-mobile-sys-card" href="<?= hg_mobile_sys_h(hg_mobile_sys_url($link, 'dim_systems', '/systems', $id)) ?>" data-mobile-item data-mobile-search="<?= hg_mobile_sys_h($search) ?>">
                    <img src="<?= hg_mobile_sys_h(hg_mobile_sys_image($system['image_url'] ?? '')) ?>" alt="">
                    <span class="hg-mobile-sys-card-main">
                        <strong><?= hg_mobile_sys_h($name !== '' ? $name : ('#' . $id)) ?></strong>
                        <span><?= hg_mobile_sys_h(trim($origin . ((int)($system['forms'] ?? 0) === 1 ? ' | Formas' : ''))) ?></span>
                        <?php if ($desc !== ''): ?><small><?= hg_mobile_sys_h($desc) ?></small><?php endif; ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
    return;
}

if ($route === 'sistemas') {
    $systemId = hg_mobile_sys_resolve($link, 'dim_systems', (string)($_GET['b'] ?? ''));
    if ($systemId <= 0) {
        hg_public_render_not_found('Sistema no encontrado', 'El sistema solicitado no existe.');
        return;
    }

    $system = null;
    if ($stmt = $link->prepare("SELECT * FROM dim_systems WHERE id = ? LIMIT 1")) {
        $stmt->bind_param('i', $systemId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) $system = $res->fetch_assoc();
        $stmt->close();
    }
    if (!$system) {
        hg_public_render_not_found('Sistema no encontrado', 'El sistema solicitado no existe.');
        return;
    }

    $name = trim((string)($system['name'] ?? ''));
    $description = (string)($system['description'] ?? '');
    $metaTitle = $name . " | Sistemas | Heaven's Gate";
    $metaDescription = hg_mobile_sys_excerpt($description, 160);
    $pageTitle2 = $name;

    $forms = [];
    if ((int)($system['forms'] ?? 0) === 1) {
        $hasBreedId = hg_mobile_sys_col_exists($link, 'dim_forms', 'breed_id');
        $hasRace = hg_mobile_sys_col_exists($link, 'dim_forms', 'race');
        $selectBreed = "''";
        $joins = '';
        if ($hasBreedId) {
            $selectBreed = $hasRace ? "COALESCE(NULLIF(db.name,''), NULLIF(f.race,''))" : "COALESCE(NULLIF(db.name,''), '')";
            $joins = " LEFT JOIN dim_breeds db ON db.id = f.breed_id ";
        } elseif ($hasRace) {
            $selectBreed = "COALESCE(NULLIF(db.name,''), NULLIF(f.race,''))";
            $joins = " LEFT JOIN dim_breeds db ON db.system_id = f.system_id AND db.name = f.race ";
        }
        $raceOrder = $hasRace ? "f.race ASC," : "";
        $sql = "SELECT f.id, f.form, {$selectBreed} AS breed_name FROM dim_forms f {$joins} WHERE f.system_id = ? ORDER BY {$raceOrder} f.form ASC";
        if ($stmt = $link->prepare($sql)) {
            $stmt->bind_param('i', $systemId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($res && ($row = $res->fetch_assoc())) {
                $forms[] = [
                    'label' => (string)($row['form'] ?? ''),
                    'meta' => (string)($row['breed_name'] ?? ''),
                    'href' => hg_mobile_sys_url($link, 'dim_forms', '/systems/form', (int)($row['id'] ?? 0)),
                ];
            }
            $stmt->close();
        }
    }

    $sections = [
        ['Razas', 'dim_breeds', '/systems/breeds'],
        ['Auspicios', 'dim_auspices', '/systems/auspices'],
        ['Tribus', 'dim_tribes', '/systems/tribes'],
    ];
    $sectionRows = [];
    foreach ($sections as $def) {
        [$label, $table, $base] = $def;
        $rows = [];
        if ($stmt = $link->prepare("SELECT id, name FROM `$table` WHERE system_id = ? ORDER BY id ASC")) {
            $stmt->bind_param('i', $systemId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($res && ($row = $res->fetch_assoc())) {
                $rows[] = ['label' => (string)$row['name'], 'href' => hg_mobile_sys_url($link, $table, $base, (int)$row['id'])];
            }
            $stmt->close();
        }
        $sectionRows[$label] = $rows;
    }

    $misc = [];
    if ($stmt = $link->prepare("SELECT id, name, kind FROM fact_misc_systems WHERE system_name = ? ORDER BY id ASC")) {
        $stmt->bind_param('s', $name);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && ($row = $res->fetch_assoc())) {
            $misc[] = ['label' => (string)$row['name'], 'meta' => (string)($row['kind'] ?? ''), 'href' => hg_mobile_sys_url($link, 'fact_misc_systems', '/systems/misc', (int)$row['id'])];
        }
        $stmt->close();
    }

    $resources = [];
    if (hg_mobile_sys_table_exists($link, 'bridge_systems_resources_to_system')) {
        $hasActive = hg_mobile_sys_col_exists($link, 'bridge_systems_resources_to_system', 'is_active');
        $hasBridgeSort = hg_mobile_sys_col_exists($link, 'bridge_systems_resources_to_system', 'sort_order');
        $activeSql = $hasActive ? "AND (b.is_active = 1 OR b.is_active IS NULL)" : '';
        $sortExpr = $hasBridgeSort ? 'COALESCE(b.sort_order, r.sort_order, 9999)' : 'COALESCE(r.sort_order, 9999)';
        $sql = "
            SELECT r.name, r.kind, r.description
            FROM bridge_systems_resources_to_system b
            INNER JOIN dim_systems_resources r ON r.id = b.resource_id
            WHERE b.system_id = ? $activeSql
            ORDER BY r.kind ASC, $sortExpr ASC, r.name ASC
        ";
        if ($stmt = $link->prepare($sql)) {
            $stmt->bind_param('i', $systemId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($res && ($row = $res->fetch_assoc())) $resources[] = $row;
            $stmt->close();
        }
    }
    ?>
    <section class="hg-mobile-section">
        <a class="hg-mobile-back-link" href="/systems">Volver a sistemas</a>
    </section>
    <section class="hg-mobile-section hg-mobile-sys-detail-head">
        <img src="<?= hg_mobile_sys_h(hg_mobile_sys_image($system['image_url'] ?? '')) ?>" alt="<?= hg_mobile_sys_h($name) ?>">
        <div>
            <h1><?= hg_mobile_sys_h($name) ?></h1>
            <?php if (trim(strip_tags($description)) !== ''): ?><div class="hg-mobile-rich-body"><?= $description ?></div><?php endif; ?>
        </div>
    </section>
    <?php if (!empty($forms)) hg_mobile_sys_section('Formas', $forms); ?>
    <?php foreach ($sectionRows as $label => $rows) if (!empty($rows)) hg_mobile_sys_section($label, $rows); ?>
    <?php if (!empty($misc)) hg_mobile_sys_section('Miscelanea', $misc); ?>
    <?php if (!empty($resources)): ?>
        <section class="hg-mobile-section">
            <h2>Recursos</h2>
            <div class="hg-mobile-card-list" data-mobile-paginated data-mobile-search="1" data-page-size="12" data-search-placeholder="Buscar recurso" data-empty-text="No hay recursos con ese filtro.">
                <?php foreach ($resources as $resource): ?>
                    <article class="hg-mobile-card hg-mobile-sys-resource" data-mobile-item data-mobile-search="<?= hg_mobile_sys_h(($resource['name'] ?? '') . ' ' . ($resource['kind'] ?? '')) ?>">
                        <strong><?= hg_mobile_sys_h($resource['name'] ?? '') ?></strong>
                        <span><?= hg_mobile_sys_h($resource['kind'] ?? '') ?></span>
                        <?php if (trim(strip_tags((string)($resource['description'] ?? ''))) !== ''): ?><div class="hg-mobile-rich-body"><?= $resource['description'] ?></div><?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
    <?php
    return;
}

if ($route === 'versistdetalle') {
    $type = (int)($_GET['tc'] ?? 0);
    $tableMap = [1 => ['dim_breeds', '/systems/breeds', 'breed_id'], 2 => ['dim_auspices', '/systems/auspices', 'auspice_id'], 3 => ['dim_tribes', '/systems/tribes', 'tribe_id'], 4 => ['fact_misc_systems', '/systems/misc', '']];
    if (!isset($tableMap[$type])) {
        hg_public_render_not_found('Elemento no encontrado', 'El contenido solicitado no existe.');
        return;
    }
    [$table, $base, $charField] = $tableMap[$type];
    $detailId = hg_mobile_sys_resolve($link, $table, (string)($_GET['b'] ?? ''));
    if ($detailId <= 0) {
        hg_public_render_not_found('Elemento no encontrado', 'El contenido solicitado no existe.');
        return;
    }

    $energySql = hg_ser_energy_sql_parts($link, $table, 't');
    $sql = "SELECT t.*{$energySql['select']} FROM `$table` t{$energySql['join']} WHERE t.id = ? LIMIT 1";
    $detail = null;
    if ($stmt = $link->prepare($sql)) {
        $stmt->bind_param('i', $detailId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) $detail = $res->fetch_assoc();
        $stmt->close();
    }
    if (!$detail) {
        hg_public_render_not_found('Elemento no encontrado', 'El contenido solicitado no existe.');
        return;
    }

    $name = trim((string)($detail['name'] ?? ''));
    $systemName = trim((string)($detail['system_name'] ?? ''));
    $systemId = (int)($detail['system_id'] ?? 0);
    $description = (string)($detail['description'] ?? '');
    $image = hg_mobile_sys_image($detail['image_url'] ?? '');
    $metaTitle = $name . " | Sistemas | Heaven's Gate";
    $metaDescription = hg_mobile_sys_excerpt($description, 160);

    $stats = [];
    $energyEntries = hg_ser_energy_entries_for_row($link, $table, $detailId, $detail, $systemName);
    if (!empty($energyEntries)) {
        foreach ($energyEntries as $entry) {
            $label = trim((string)($entry['resource_name'] ?? ''));
            $value = (int)($entry['energy_value'] ?? 0);
            if ($label !== '' && $value > 0) $stats[] = [$label . ' inicial', $value];
        }
    } elseif (isset($detail['energy']) && (int)$detail['energy'] > 0) {
        $stats[] = [hg_ser_energy_label_from_row($table, $detail, $systemName) . ' inicial', (int)$detail['energy']];
    }
    if ($type === 4) {
        if (trim((string)($detail['extra_info'] ?? '')) !== '') $stats[] = ['Info', (string)$detail['extra_info']];
        if (trim((string)($detail['energy_name'] ?? '')) !== '') $stats[] = [(string)$detail['energy_name'], (string)($detail['energy_value'] ?? '')];
    }

    $gifts = [];
    if ($systemId > 0 && $name !== '') {
        if ($stmt = $link->prepare("SELECT id, name, rank FROM fact_gifts WHERE gift_group = ? AND system_id = ? ORDER BY rank ASC, name ASC")) {
            $stmt->bind_param('si', $name, $systemId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($res && ($row = $res->fetch_assoc())) {
                $gifts[] = ['label' => (string)$row['name'], 'meta' => (string)$row['rank'], 'href' => hg_mobile_sys_url($link, 'fact_gifts', '/powers/gift', (int)$row['id'])];
            }
            $stmt->close();
        }
    }

    $members = [];
    if ($charField !== '') {
        $sqlMembers = "
            SELECT p.id, p.name, p.alias, p.image_url, p.gender, COALESCE(dcs.label, '') AS status
            FROM fact_characters p
            LEFT JOIN dim_character_status dcs ON dcs.id = p.status_id
            WHERE p.`$charField` = ? " . hg_mobile_chronicle_exclusion_and('p') . "
            ORDER BY p.name ASC, p.id ASC
        ";
        if ($stmt = $link->prepare($sqlMembers)) {
            $stmt->bind_param('i', $detailId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($res && ($row = $res->fetch_assoc())) $members[] = $row;
            $stmt->close();
        }
    } elseif ($type === 4 && hg_mobile_sys_table_exists($link, 'bridge_characters_misc_systems')) {
        $hasActive = hg_mobile_sys_col_exists($link, 'bridge_characters_misc_systems', 'is_active');
        $activeSql = $hasActive ? "AND (bcms.is_active = 1 OR bcms.is_active IS NULL)" : '';
        $sqlMembers = "
            SELECT p.id, p.name, p.alias, p.image_url, p.gender, COALESCE(dcs.label, '') AS status
            FROM bridge_characters_misc_systems bcms
            INNER JOIN fact_characters p ON p.id = bcms.character_id
            LEFT JOIN dim_character_status dcs ON dcs.id = p.status_id
            WHERE bcms.misc_system_id = ? $activeSql " . hg_mobile_chronicle_exclusion_and('p') . "
            ORDER BY p.name ASC, p.id ASC
        ";
        if ($stmt = $link->prepare($sqlMembers)) {
            $stmt->bind_param('i', $detailId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($res && ($row = $res->fetch_assoc())) $members[] = $row;
            $stmt->close();
        }
    }
    ?>
    <section class="hg-mobile-section">
        <a class="hg-mobile-back-link" href="<?= $systemId > 0 ? hg_mobile_sys_h(hg_mobile_sys_url($link, 'dim_systems', '/systems', $systemId)) : '/systems' ?>">Volver al sistema</a>
    </section>
    <section class="hg-mobile-section hg-mobile-sys-detail-head">
        <img src="<?= hg_mobile_sys_h($image) ?>" alt="<?= hg_mobile_sys_h($name) ?>">
        <div>
            <h1><?= hg_mobile_sys_h($name) ?></h1>
            <?php if ($systemName !== ''): ?><p class="hg-mobile-muted"><?= hg_mobile_sys_h($systemName) ?></p><?php endif; ?>
        </div>
    </section>
    <?php if (!empty($stats)): ?>
        <section class="hg-mobile-section"><div class="hg-mobile-sys-stats"><?php foreach ($stats as $stat) hg_mobile_sys_stat($stat[0], $stat[1]); ?></div></section>
    <?php endif; ?>
    <?php if (trim(strip_tags($description)) !== ''): ?>
        <section class="hg-mobile-section"><h2>Descripción</h2><div class="hg-mobile-rich-body"><?= $description ?></div></section>
    <?php endif; ?>
    <?php if (!empty($gifts)) hg_mobile_sys_section('Dones disponibles', $gifts); ?>
    <section class="hg-mobile-section">
        <h2>Miembros</h2>
        <?php if (empty($members)): ?><p class="hg-mobile-muted">No hay personajes publicados.</p><?php else: ?>
            <div class="hg-mobile-character-list" data-mobile-paginated data-mobile-search="1" data-page-size="20" data-search-placeholder="Buscar personaje" data-empty-text="No hay personajes con ese filtro.">
                <?php foreach ($members as $member): ?>
                    <?php
                        $cid = (int)($member['id'] ?? 0);
                        $cname = trim((string)($member['name'] ?? ''));
                        $alias = trim((string)($member['alias'] ?? ''));
                        $status = trim((string)($member['status'] ?? ''));
                        $avatar = function_exists('hg_character_avatar_url') ? hg_character_avatar_url((string)($member['image_url'] ?? ''), (string)($member['gender'] ?? '')) : (string)($member['image_url'] ?? '');
                        $href = hg_mobile_sys_url($link, 'fact_characters', '/characters', $cid);
                    ?>
                    <a class="hg-mobile-character-card" href="<?= hg_mobile_sys_h($href) ?>" data-mobile-item data-mobile-search="<?= hg_mobile_sys_h($cname . ' ' . $alias . ' ' . $status) ?>">
                        <?php if ($avatar !== ''): ?><img src="<?= hg_mobile_sys_h($avatar) ?>" alt=""><?php else: ?><span class="hg-mobile-character-avatar" aria-hidden="true"></span><?php endif; ?>
                        <span class="hg-mobile-character-main"><strong><?= hg_mobile_sys_h($cname) ?></strong><span><?= hg_mobile_sys_h(trim($alias . ' ' . $status)) ?></span></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <?php
    return;
}

if ($route === 'verforma') {
    $formId = hg_mobile_sys_resolve($link, 'dim_forms', (string)($_GET['b'] ?? ''));
    if ($formId <= 0) {
        hg_public_render_not_found('Forma no encontrada', 'La forma solicitada no existe.');
        return;
    }
    $hasBreedId = hg_mobile_sys_col_exists($link, 'dim_forms', 'breed_id');
    $hasRace = hg_mobile_sys_col_exists($link, 'dim_forms', 'race');
    $selectBreed = "'' AS breed_name";
    $joins = " LEFT JOIN dim_systems ds ON ds.id = f.system_id ";
    if ($hasBreedId) {
        $selectBreed = ($hasRace ? "COALESCE(NULLIF(db.name,''), NULLIF(f.race,''))" : "COALESCE(NULLIF(db.name,''), '')") . " AS breed_name";
        $joins .= " LEFT JOIN dim_breeds db ON db.id = f.breed_id ";
    } elseif ($hasRace) {
        $selectBreed = "COALESCE(NULLIF(db.name,''), NULLIF(f.race,'')) AS breed_name";
        $joins .= " LEFT JOIN dim_breeds db ON db.system_id = f.system_id AND db.name = f.race ";
    }
    $form = null;
    $sql = "SELECT f.*, COALESCE(ds.name, '') AS system_name, {$selectBreed} FROM dim_forms f {$joins} WHERE f.id = ? LIMIT 1";
    if ($stmt = $link->prepare($sql)) {
        $stmt->bind_param('i', $formId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) $form = $res->fetch_assoc();
        $stmt->close();
    }
    if (!$form) {
        hg_public_render_not_found('Forma no encontrada', 'La forma solicitada no existe.');
        return;
    }
    $name = trim((string)($form['form'] ?? ''));
    $systemName = trim((string)($form['system_name'] ?? ''));
    $breedName = trim((string)($form['breed_name'] ?? ''));
    $display = ($systemName === 'Bastet' && $breedName !== '') ? ($name . ' (' . $breedName . ')') : $name;
    $description = (string)($form['description'] ?? '');
    $metaTitle = $display . " | Formas | Heaven's Gate";
    $metaDescription = hg_mobile_sys_excerpt($description, 160);
    $systemId = (int)($form['system_id'] ?? 0);

    $maneuvers = [];
    if ($systemId > 0 && $name !== '') {
        $like = '%' . $name . '%';
        if ($stmt = $link->prepare("SELECT id, pretty_id, name, image_url FROM fact_combat_maneuvers WHERE system_id = ? AND (user LIKE ? OR user LIKE '%Todas%') ORDER BY name ASC")) {
            $stmt->bind_param('is', $systemId, $like);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($res && ($row = $res->fetch_assoc())) $maneuvers[] = $row;
            $stmt->close();
        }
    }
    ?>
    <section class="hg-mobile-section">
        <a class="hg-mobile-back-link" href="<?= $systemId > 0 ? hg_mobile_sys_h(hg_mobile_sys_url($link, 'dim_systems', '/systems', $systemId)) : '/systems' ?>">Volver al sistema</a>
    </section>
    <section class="hg-mobile-section hg-mobile-sys-detail-head">
        <img src="<?= hg_mobile_sys_h(hg_mobile_sys_image($form['image_url'] ?? '')) ?>" alt="<?= hg_mobile_sys_h($display) ?>">
        <div><h1><?= hg_mobile_sys_h($display) ?></h1><p class="hg-mobile-muted"><?= hg_mobile_sys_h(trim($systemName . ($breedName !== '' ? ' | ' . $breedName : ''))) ?></p></div>
    </section>
    <section class="hg-mobile-section">
        <div class="hg-mobile-sys-stats">
            <?php hg_mobile_sys_stat('Fuerza', ((int)($form['strength_bonus'] ?? 0) > 0 ? '+' : '') . (string)($form['strength_bonus'] ?? '0')); ?>
            <?php hg_mobile_sys_stat('Destreza', ((int)($form['dexterity_bonus'] ?? 0) > 0 ? '+' : '') . (string)($form['dexterity_bonus'] ?? '0')); ?>
            <?php hg_mobile_sys_stat('Resistencia', ((int)($form['stamina_bonus'] ?? 0) > 0 ? '+' : '') . (string)($form['stamina_bonus'] ?? '0')); ?>
            <?php hg_mobile_sys_stat('Armas cuerpo a cuerpo', ((int)($form['weapons'] ?? 0) === 1 ? 'Si' : 'No')); ?>
            <?php hg_mobile_sys_stat('Armas de fuego', ((int)($form['firearms'] ?? 0) === 1 ? 'Si' : 'No')); ?>
            <?php hg_mobile_sys_stat('Regeneracion', ((int)($form['hpregen'] ?? 0) > 0 ? ((int)$form['hpregen'] . ' / turno') : 'No')); ?>
        </div>
    </section>
    <?php if (trim(strip_tags($description)) !== ''): ?><section class="hg-mobile-section"><h2>Descripción</h2><div class="hg-mobile-rich-body"><?= $description ?></div></section><?php endif; ?>
    <?php if (!empty($maneuvers)): ?>
        <section class="hg-mobile-section">
            <h2>Maniobras de combate</h2>
            <div class="hg-mobile-card-list" data-mobile-paginated data-mobile-search="1" data-page-size="20" data-search-placeholder="Buscar maniobra" data-empty-text="No hay maniobras con ese filtro.">
                <?php foreach ($maneuvers as $m): ?>
                    <?php
                        $maneId = (int)($m['id'] ?? 0);
                        $maneName = trim((string)($m['name'] ?? ''));
                        $pretty = trim((string)($m['pretty_id'] ?? ''));
                        $img = trim((string)($m['image_url'] ?? ''));
                        if ($img === '') $img = 'img/inv/no-photo.webp';
                        elseif (strpos($img, '/') === false) $img = 'img/maneuvers/' . $img;
                    ?>
                    <a class="hg-mobile-card hg-mobile-sys-mini-card" href="/rules/maneuvers/<?= hg_mobile_sys_h(rawurlencode($pretty !== '' ? $pretty : (string)$maneId)) ?>" data-mobile-item data-mobile-search="<?= hg_mobile_sys_h($maneName) ?>">
                        <img src="<?= hg_mobile_sys_h($img) ?>" alt=""><strong><?= hg_mobile_sys_h($maneName) ?></strong>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
    <?php
    return;
}

include(__DIR__ . '/fallback.php');