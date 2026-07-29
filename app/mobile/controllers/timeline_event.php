<?php

include_once(__DIR__ . '/../../helpers/public_response.php');
include_once(__DIR__ . '/../../helpers/pretty.php');
include_once(__DIR__ . '/../../helpers/character_avatar.php');
include_once(__DIR__ . '/../helpers/chronicle_scope.php');

$metaTitle = "Evento | Heaven's Gate";
$metaDescription = 'Ficha móvil de evento de línea temporal.';
$pageSect = 'Línea temporal';

if (!function_exists('hg_mobile_event_h')) {
    function hg_mobile_event_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('hg_mobile_event_table_exists')) {
    function hg_mobile_event_table_exists(mysqli $link, string $table): bool
    {
        static $cache = [];
        if (isset($cache[$table])) return $cache[$table];
        $ok = false;
        if ($st = $link->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?")) {
            $st->bind_param('s', $table);
            $st->execute();
            $st->bind_result($count);
            $st->fetch();
            $st->close();
            $ok = ((int)$count > 0);
        }
        return $cache[$table] = $ok;
    }
}

if (!function_exists('hg_mobile_event_col_exists')) {
    function hg_mobile_event_col_exists(mysqli $link, string $table, string $column): bool
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

if (!function_exists('hg_mobile_event_date_label')) {
    function hg_mobile_event_date_label(?string $dateValue, string $precision, ?string $note): string
    {
        $precision = trim((string)$precision);
        $dateValue = trim((string)$dateValue);
        $note = trim((string)$note);
        if ($precision === 'unknown') return $note !== '' ? $note : 'Desconocida';
        if ($dateValue === '' || $dateValue === '0000-00-00') return $note !== '' ? $note : '';
        $ts = strtotime($dateValue);
        if ($ts === false) return $note !== '' ? $note : $dateValue;
        if ($precision === 'year') $base = date('Y', $ts);
        elseif ($precision === 'month') $base = date('m-Y', $ts);
        elseif ($precision === 'approx') $base = 'Aprox. ' . date('d-m-Y', $ts);
        else $base = date('d-m-Y', $ts);
        return $note !== '' ? ($base . ' (' . $note . ')') : $base;
    }
}

if (!function_exists('hg_mobile_event_url')) {
    function hg_mobile_event_url(array $row): string
    {
        $slug = trim((string)($row['pretty_id'] ?? ''));
        return '/timeline/event/' . rawurlencode($slug !== '' ? $slug : (string)($row['id'] ?? ''));
    }
}

if (!function_exists('hg_mobile_event_pretty_href')) {
    function hg_mobile_event_pretty_href(mysqli $link, string $table, string $base, int $id): string
    {
        if ($id <= 0) return '#';
        return function_exists('pretty_url') ? pretty_url($link, $table, $base, $id) : rtrim($base, '/') . '/' . $id;
    }
}

if (!function_exists('hg_mobile_event_is_excluded')) {
    function hg_mobile_event_is_excluded(mysqli $link, int $eventId): bool
    {
        $csv = hg_mobile_excluded_chronicles_csv();
        if ($eventId <= 0 || $csv === '' || !hg_mobile_event_table_exists($link, 'bridge_timeline_events_chronicles')) return false;
        $sql = "SELECT 1 FROM bridge_timeline_events_chronicles WHERE event_id = ? AND chronicle_id IN ({$csv}) LIMIT 1";
        if (!$st = $link->prepare($sql)) return false;
        $st->bind_param('i', $eventId);
        $st->execute();
        $rs = $st->get_result();
        $excluded = $rs && $rs->num_rows > 0;
        $st->close();
        return $excluded;
    }
}

if (!function_exists('hg_mobile_event_nav')) {
    function hg_mobile_event_nav(mysqli $link, int $eventId, string $anchorDate, string $direction, bool $hasPretty, bool $hasSortDate, bool $hasActive): ?array
    {
        $sortExpr = $hasSortDate ? 'COALESCE(e.sort_date, e.event_date)' : 'e.event_date';
        $prettyExpr = $hasPretty ? 'e.pretty_id' : 'CAST(e.id AS CHAR)';
        $active = $hasActive ? 'e.is_active = 1 AND ' : '';
        $csv = hg_mobile_excluded_chronicles_csv();
        $excludeJoin = '';
        $excludeWhere = '';
        if ($csv !== '' && hg_mobile_event_table_exists($link, 'bridge_timeline_events_chronicles')) {
            $excludeWhere = " AND NOT EXISTS (SELECT 1 FROM bridge_timeline_events_chronicles bx WHERE bx.event_id = e.id AND bx.chronicle_id IN ({$csv}))";
        }
        if ($direction === 'prev') {
            $op = '<';
            $idOp = '<';
            $order = 'DESC';
        } else {
            $op = '>';
            $idOp = '>';
            $order = 'ASC';
        }
        $sql = "SELECT e.id, {$prettyExpr} AS pretty_id, e.title FROM fact_timeline_events e {$excludeJoin} WHERE {$active} ({$sortExpr} {$op} ? OR ({$sortExpr} = ? AND e.id {$idOp} ?)) {$excludeWhere} ORDER BY {$sortExpr} {$order}, e.id {$order} LIMIT 1";
        if (!$st = $link->prepare($sql)) return null;
        $st->bind_param('ssi', $anchorDate, $anchorDate, $eventId);
        $st->execute();
        $rs = $st->get_result();
        $row = $rs ? $rs->fetch_assoc() : null;
        $st->close();
        return $row ?: null;
    }
}

if (!isset($link) || !($link instanceof mysqli)) {
    echo '<section class="hg-mobile-section"><h1>Evento no disponible</h1><p>No se pudo conectar con la base de datos.</p></section>';
    return;
}

$rawEvent = trim((string)($_GET['t'] ?? ''));
$eventId = 0;
if ($rawEvent !== '') {
    if (preg_match('/^\d+$/', $rawEvent)) $eventId = (int)$rawEvent;
    if ($eventId <= 0 && function_exists('resolve_pretty_id')) $eventId = (int)(resolve_pretty_id($link, 'fact_timeline_events', $rawEvent) ?? 0);
}

if ($eventId <= 0 || !hg_mobile_event_table_exists($link, 'fact_timeline_events') || hg_mobile_event_is_excluded($link, $eventId)) {
    echo '<section class="hg-mobile-section"><h1>Evento no encontrado</h1><p>No se puede mostrar este evento.</p><p><a href="/timeline">Volver a línea temporal</a></p></section>';
    return;
}

$hasPretty = hg_mobile_event_col_exists($link, 'fact_timeline_events', 'pretty_id');
$hasSortDate = hg_mobile_event_col_exists($link, 'fact_timeline_events', 'sort_date');
$hasDatePrecision = hg_mobile_event_col_exists($link, 'fact_timeline_events', 'date_precision');
$hasDateNote = hg_mobile_event_col_exists($link, 'fact_timeline_events', 'date_note');
$hasLocation = hg_mobile_event_col_exists($link, 'fact_timeline_events', 'location');
$hasSource = hg_mobile_event_col_exists($link, 'fact_timeline_events', 'source');
$hasTimeline = hg_mobile_event_col_exists($link, 'fact_timeline_events', 'timeline');
$hasActive = hg_mobile_event_col_exists($link, 'fact_timeline_events', 'is_active');
$hasTypeId = hg_mobile_event_col_exists($link, 'fact_timeline_events', 'event_type_id');
$hasTypes = hg_mobile_event_table_exists($link, 'dim_timeline_events_types');

$selectPretty = $hasPretty ? 'e.pretty_id AS pretty_id' : 'CAST(e.id AS CHAR) AS pretty_id';
$selectSortDate = $hasSortDate ? 'e.sort_date AS sort_date' : 'e.event_date AS sort_date';
$selectDatePrecision = $hasDatePrecision ? 'e.date_precision AS date_precision' : "'day' AS date_precision";
$selectDateNote = $hasDateNote ? 'e.date_note AS date_note' : 'NULL AS date_note';
$selectLocation = $hasLocation ? 'e.location AS location' : 'NULL AS location';
$selectSource = $hasSource ? 'e.source AS source' : 'NULL AS source';
$selectTimeline = $hasTimeline ? 'e.timeline AS timeline' : 'NULL AS timeline';
$typeJoin = '';
if ($hasTypes && $hasTypeId) {
    $typeJoin = 'LEFT JOIN dim_timeline_events_types t ON t.id = e.event_type_id';
    $typeNameExpr = "COALESCE(t.name, 'Evento')";
} else {
    $typeNameExpr = "'Evento'";
}

$event = null;
$sql = "SELECT e.id, {$selectPretty}, e.title, e.description, e.event_date, {$selectSortDate}, {$selectDatePrecision}, {$selectDateNote}, {$selectLocation}, {$selectSource}, {$selectTimeline}, {$typeNameExpr} AS type_name FROM fact_timeline_events e {$typeJoin} WHERE e.id = ? LIMIT 1";
if ($st = $link->prepare($sql)) {
    $st->bind_param('i', $eventId);
    $st->execute();
    $rs = $st->get_result();
    $event = $rs ? $rs->fetch_assoc() : null;
    $st->close();
}

if (!$event) {
    echo '<section class="hg-mobile-section"><h1>Evento no encontrado</h1><p>No se puede mostrar este evento.</p><p><a href="/timeline">Volver a línea temporal</a></p></section>';
    return;
}

$title = trim((string)($event['title'] ?? 'Evento'));
$description = trim((string)($event['description'] ?? ''));
$dateLabel = hg_mobile_event_date_label((string)($event['event_date'] ?? ''), (string)($event['date_precision'] ?? 'day'), (string)($event['date_note'] ?? ''));
$typeName = trim((string)($event['type_name'] ?? 'Evento'));
$location = trim((string)($event['location'] ?? ''));
$source = trim((string)($event['source'] ?? ''));
$timeline = trim((string)($event['timeline'] ?? ''));
$anchorDate = trim((string)($event['sort_date'] ?? '')) ?: trim((string)($event['event_date'] ?? '')) ?: '1000-01-01';

$metaTitle = $title . " | Evento | Heaven's Gate";
$metaDescription = $description !== '' ? trim(strip_tags($description)) : 'Detalle de evento de línea temporal.';

$chronicles = [];
if (hg_mobile_event_table_exists($link, 'bridge_timeline_events_chronicles') && hg_mobile_event_table_exists($link, 'dim_chronicles')) {
    $csv = hg_mobile_excluded_chronicles_csv();
    $chronWhere = $csv !== '' ? " AND c.id NOT IN ({$csv})" : '';
    $order = hg_mobile_event_col_exists($link, 'bridge_timeline_events_chronicles', 'sort_order') ? 'b.sort_order ASC, c.name ASC' : 'c.name ASC';
    if ($st = $link->prepare("SELECT c.id, c.name, c.pretty_id FROM bridge_timeline_events_chronicles b INNER JOIN dim_chronicles c ON c.id = b.chronicle_id WHERE b.event_id = ? {$chronWhere} ORDER BY {$order}")) {
        $st->bind_param('i', $eventId);
        $st->execute();
        $rs = $st->get_result();
        while ($rs && ($row = $rs->fetch_assoc())) $chronicles[] = $row;
        $st->close();
    }
} elseif ($timeline !== '') {
    $chronicles[] = ['id' => 0, 'name' => $timeline, 'pretty_id' => ''];
}

$participants = [];
if (hg_mobile_event_table_exists($link, 'bridge_timeline_events_characters') && hg_mobile_event_table_exists($link, 'fact_characters')) {
    $roleExpr = hg_mobile_event_col_exists($link, 'bridge_timeline_events_characters', 'role_label') ? 'b.role_label' : 'NULL';
    $chronAnd = hg_mobile_chronicle_exclusion_and('c');
    $order = hg_mobile_event_col_exists($link, 'bridge_timeline_events_characters', 'sort_order') ? 'b.sort_order ASC, c.name ASC' : 'c.name ASC';
    if ($st = $link->prepare("SELECT c.id, c.name, c.alias, c.image_url, c.gender, COALESCE(s.label, '') AS status, {$roleExpr} AS role_label FROM bridge_timeline_events_characters b INNER JOIN fact_characters c ON c.id = b.character_id LEFT JOIN dim_character_status s ON s.id = c.status_id WHERE b.event_id = ? {$chronAnd} ORDER BY {$order}")) {
        $st->bind_param('i', $eventId);
        $st->execute();
        $rs = $st->get_result();
        while ($rs && ($row = $rs->fetch_assoc())) $participants[] = $row;
        $st->close();
    }
}

$chapters = [];
if (hg_mobile_event_table_exists($link, 'bridge_timeline_events_chapters') && hg_mobile_event_table_exists($link, 'dim_chapters')) {
    $seasonJoin = hg_mobile_event_table_exists($link, 'dim_seasons') ? 'LEFT JOIN dim_seasons s ON s.id = c.season_id' : '';
    $seasonSelect = $seasonJoin !== '' ? 's.name AS season_name, s.season_number, COALESCE(s.season_kind, \'temporada\') AS season_kind' : 'NULL AS season_name, NULL AS season_number, \'temporada\' AS season_kind';
    $chapterChron = ($seasonJoin !== '' && hg_mobile_event_col_exists($link, 'dim_seasons', 'chronicle_id')) ? ' AND (s.id IS NULL OR ' . hg_mobile_chronicle_exclusion_condition('s') . ')' : '';
    $order = hg_mobile_event_col_exists($link, 'bridge_timeline_events_chapters', 'sort_order') ? 'b.sort_order ASC, COALESCE(s.sort_order, 9999) ASC, c.chapter_number ASC' : 'COALESCE(s.sort_order, 9999) ASC, c.chapter_number ASC';
    if ($st = $link->prepare("SELECT c.id, c.name, c.pretty_id, c.chapter_number, c.season_id, {$seasonSelect} FROM bridge_timeline_events_chapters b INNER JOIN dim_chapters c ON c.id = b.chapter_id {$seasonJoin} WHERE b.event_id = ? {$chapterChron} ORDER BY {$order}")) {
        $st->bind_param('i', $eventId);
        $st->execute();
        $rs = $st->get_result();
        while ($rs && ($row = $rs->fetch_assoc())) $chapters[] = $row;
        $st->close();
    }
}

$prevEvent = hg_mobile_event_nav($link, $eventId, $anchorDate, 'prev', $hasPretty, $hasSortDate, $hasActive);
$nextEvent = hg_mobile_event_nav($link, $eventId, $anchorDate, 'next', $hasPretty, $hasSortDate, $hasActive);
?>

<section class="hg-mobile-section hg-mobile-event-head">
    <a class="hg-mobile-back-link" href="/timeline">Línea temporal</a>
    <h1><?= hg_mobile_event_h($title) ?></h1>
    <div class="hg-mobile-event-tags">
        <?php if ($typeName !== ''): ?><span><?= hg_mobile_event_h($typeName) ?></span><?php endif; ?>
        <?php if ($dateLabel !== ''): ?><span><?= hg_mobile_event_h($dateLabel) ?></span><?php endif; ?>
        <?php if ($location !== ''): ?><span><?= hg_mobile_event_h($location) ?></span><?php endif; ?>
    </div>
</section>

<?php if (!empty($chronicles) || $source !== ''): ?>
<section class="hg-mobile-section hg-mobile-event-meta">
    <h2>Datos</h2>
    <div class="hg-mobile-fact-grid">
        <?php if (!empty($chronicles)): ?>
            <div><span>Cronica</span><strong>
                <?php foreach ($chronicles as $idx => $chronicle): ?>
                    <?php if ($idx > 0): ?>, <?php endif; ?>
                    <?php $cid = (int)($chronicle['id'] ?? 0); ?>
                    <?php if ($cid > 0): ?><a href="<?= hg_mobile_event_h(hg_mobile_event_pretty_href($link, 'dim_chronicles', '/chronicles', $cid)) ?>"><?= hg_mobile_event_h($chronicle['name'] ?? '') ?></a><?php else: ?><?= hg_mobile_event_h($chronicle['name'] ?? '') ?><?php endif; ?>
                <?php endforeach; ?>
            </strong></div>
        <?php endif; ?>
        <?php if ($source !== ''): ?><div><span>Fuente</span><strong><?= hg_mobile_event_h($source) ?></strong></div><?php endif; ?>
    </div>
</section>
<?php endif; ?>

<section class="hg-mobile-section hg-mobile-event-body">
    <h2>Descripción</h2>
    <?php if ($description !== ''): ?>
        <div class="hg-mobile-rich-body"><?= nl2br($description) ?></div>
    <?php else: ?>
        <p class="hg-mobile-empty">Sin descripción registrada.</p>
    <?php endif; ?>
</section>

<?php if (!empty($participants)): ?>
<section class="hg-mobile-section">
    <h2>Personajes</h2>
    <div class="hg-mobile-character-list" data-mobile-paginated data-mobile-search="1" data-page-size="20" data-search-placeholder="Buscar personaje">
        <?php foreach ($participants as $character): ?>
            <?php
                $cid = (int)($character['id'] ?? 0);
                $href = hg_mobile_event_pretty_href($link, 'fact_characters', '/characters', $cid);
                $name = (string)($character['name'] ?? '');
                $alias = (string)($character['alias'] ?? '');
                $status = (string)($character['status'] ?? '');
                $role = trim((string)($character['role_label'] ?? ''));
                $avatar = function_exists('hg_character_avatar_url') ? hg_character_avatar_url((string)($character['image_url'] ?? ''), (string)($character['gender'] ?? '')) : '/img/ui/avatar/avatar_nadie_3.webp';
            ?>
            <a class="hg-mobile-character-card" href="<?= hg_mobile_event_h($href) ?>" data-mobile-item data-mobile-search="<?= hg_mobile_event_h($name . ' ' . $alias . ' ' . $status . ' ' . $role) ?>">
                <img src="<?= hg_mobile_event_h($avatar) ?>" alt="<?= hg_mobile_event_h($name) ?>">
                <span class="hg-mobile-character-main"><strong><?= hg_mobile_event_h($name) ?></strong><span><?= hg_mobile_event_h(trim($alias . ' ' . $status)) ?></span></span>
                <?php if ($role !== ''): ?><small><?= hg_mobile_event_h($role) ?></small><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($chapters)): ?>
<section class="hg-mobile-section">
    <h2>Capítulos</h2>
    <div class="hg-mobile-card-list" data-mobile-paginated data-mobile-search="1" data-page-size="20" data-search-placeholder="Buscar capitulo">
        <?php foreach ($chapters as $chapter): ?>
            <?php
                $chapterId = (int)($chapter['id'] ?? 0);
                $href = hg_mobile_event_pretty_href($link, 'dim_chapters', '/chapters', $chapterId);
                $seasonKind = (string)($chapter['season_kind'] ?? 'temporada');
                $seasonNumber = (int)($chapter['season_number'] ?? 0);
                $chapterNumber = (int)($chapter['chapter_number'] ?? 0);
                $code = $seasonKind === 'temporada' ? ($seasonNumber . 'x' . str_pad((string)$chapterNumber, 2, '0', STR_PAD_LEFT)) : str_pad((string)$chapterNumber, 2, '0', STR_PAD_LEFT);
                $seasonName = (string)($chapter['season_name'] ?? '');
            ?>
            <a class="hg-mobile-card" href="<?= hg_mobile_event_h($href) ?>" data-mobile-item data-mobile-search="<?= hg_mobile_event_h(($chapter['name'] ?? '') . ' ' . $seasonName . ' ' . $code) ?>">
                <strong><?= hg_mobile_event_h((string)($chapter['name'] ?? '')) ?></strong>
                <span><?= hg_mobile_event_h(trim($code . ' ' . $seasonName)) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<nav class="hg-mobile-prev-next">
    <?php if ($prevEvent): ?>
        <a class="hg-mobile-prev-next-link" href="<?= hg_mobile_event_h(hg_mobile_event_url($prevEvent)) ?>"><span>Anterior</span><strong><?= hg_mobile_event_h((string)($prevEvent['title'] ?? '')) ?></strong></a>
    <?php else: ?><span class="hg-mobile-prev-next-empty"></span><?php endif; ?>
    <?php if ($nextEvent): ?>
        <a class="hg-mobile-prev-next-link hg-mobile-prev-next-link--next" href="<?= hg_mobile_event_h(hg_mobile_event_url($nextEvent)) ?>"><span>Siguiente</span><strong><?= hg_mobile_event_h((string)($nextEvent['title'] ?? '')) ?></strong></a>
    <?php else: ?><span class="hg-mobile-prev-next-empty"></span><?php endif; ?>
</nav>