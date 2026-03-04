<?php
if (!$link) {
    die("Error de conexion a la base de datos.");
}

include_once(__DIR__ . '/../../helpers/pretty.php');
include_once(__DIR__ . '/../../helpers/character_avatar.php');

if (!function_exists('hg_ev_h')) {
    function hg_ev_h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('hg_ev_date_label')) {
    function hg_ev_date_label(?string $dateValue, string $precision, ?string $note): string {
        $precision = trim((string)$precision);
        $dateValue = trim((string)$dateValue);
        $note = trim((string)$note);

        if ($precision === 'unknown') {
            return $note !== '' ? $note : 'Desconocida';
        }

        if ($dateValue === '' || $dateValue === '0000-00-00') {
            return $note !== '' ? $note : '-';
        }

        $ts = strtotime($dateValue);
        if ($ts === false) {
            return $note !== '' ? $note : $dateValue;
        }

        if ($precision === 'year') {
            $base = date('Y', $ts);
        } elseif ($precision === 'month') {
            $base = date('m-Y', $ts);
        } elseif ($precision === 'approx') {
            $base = 'Aprox. ' . date('d-m-Y', $ts);
        } else {
            $base = date('d-m-Y', $ts);
        }

        return $note !== '' ? ($base . ' (' . $note . ')') : $base;
    }
}

if (!function_exists('hg_ev_event_url')) {
    function hg_ev_event_url(array $row): string {
        $slug = trim((string)($row['pretty_id'] ?? ''));
        if ($slug === '') {
            $slug = (string)($row['id'] ?? '');
        }
        return '/timeline/event/' . rawurlencode($slug);
    }
}

if (!function_exists('hg_ev_col_exists')) {
    function hg_ev_col_exists(mysqli $link, string $table, string $column): bool {
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

        $cache[$key] = $ok;
        return $ok;
    }
}

if (!function_exists('hg_ev_table_exists')) {
    function hg_ev_table_exists(mysqli $link, string $table): bool {
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

        $cache[$table] = $ok;
        return $ok;
    }
}

$hasTimelineTable = hg_ev_table_exists($link, 'fact_timeline_events');
if (!$hasTimelineTable) {
    include('app/partials/main_nav_bar.php');
    echo '<link rel="stylesheet" href="/assets/css/hg-main.css">';
    echo '<link rel="stylesheet" href="/assets/css/hg-events.css">';
    echo "<div class='event-page'><div class='events-empty'>No existe la tabla fact_timeline_events en esta base de datos.</div></div>";
    return;
}

$hasFtePretty = hg_ev_col_exists($link, 'fact_timeline_events', 'pretty_id');
$hasFteSortDate = hg_ev_col_exists($link, 'fact_timeline_events', 'sort_date');
$hasFteDatePrecision = hg_ev_col_exists($link, 'fact_timeline_events', 'date_precision');
$hasFteDateNote = hg_ev_col_exists($link, 'fact_timeline_events', 'date_note');
$hasFteLocation = hg_ev_col_exists($link, 'fact_timeline_events', 'location');
$hasFteSource = hg_ev_col_exists($link, 'fact_timeline_events', 'source');
$hasFteTimeline = hg_ev_col_exists($link, 'fact_timeline_events', 'timeline');
$hasFteIsActive = hg_ev_col_exists($link, 'fact_timeline_events', 'is_active');
$hasFteEventTypeId = hg_ev_col_exists($link, 'fact_timeline_events', 'event_type_id');

$hasTypesTable = hg_ev_table_exists($link, 'dim_timeline_events_types');
$hasSeasonsTable = hg_ev_table_exists($link, 'dim_seasons');
$hasSeasonsKind = true;

$rawEvent = (string)($_GET['t'] ?? '');
$eventId = resolve_pretty_id($link, 'fact_timeline_events', $rawEvent) ?? 0;
if ($eventId <= 0) {
    include('app/partials/main_nav_bar.php');
    echo '<link rel="stylesheet" href="/assets/css/hg-main.css">';
    echo '<link rel="stylesheet" href="/assets/css/hg-events.css">';
    echo "<div class='event-page'><div class='events-empty'>Evento no encontrado.</div></div>";
    return;
}

$event = null;
$selectPretty = $hasFtePretty ? 'e.pretty_id AS pretty_id' : 'CAST(e.id AS CHAR) AS pretty_id';
$selectSortDate = $hasFteSortDate ? 'e.sort_date AS sort_date' : 'e.event_date AS sort_date';
$selectDatePrecision = $hasFteDatePrecision ? 'e.date_precision AS date_precision' : "'day' AS date_precision";
$selectDateNote = $hasFteDateNote ? 'e.date_note AS date_note' : 'NULL AS date_note';
$selectLocation = $hasFteLocation ? 'e.location AS location' : 'NULL AS location';
$selectSource = $hasFteSource ? 'e.source AS source' : 'NULL AS source';
$selectTimeline = $hasFteTimeline ? 'e.timeline AS timeline' : 'NULL AS timeline';
$selectIsActive = $hasFteIsActive ? 'e.is_active AS is_active' : '1 AS is_active';

$typeJoin = '';
if ($hasTypesTable && $hasFteEventTypeId) {
    $typeJoin = 'LEFT JOIN dim_timeline_events_types t ON t.id = e.event_type_id';
    $typeNameExpr = "COALESCE(t.name, 'Evento')";
    $typeSlugExpr = "COALESCE(t.pretty_id, 'evento')";
} else {
    $typeNameExpr = "'Evento'";
    $typeSlugExpr = "'evento'";
}

$eventSql = "
    SELECT
        e.id,
        {$selectPretty},
        e.title,
        e.description,
        e.event_date,
        {$selectSortDate},
        {$selectDatePrecision},
        {$selectDateNote},
        {$selectLocation},
        {$selectSource},
        {$selectTimeline},
        {$selectIsActive},
        {$typeNameExpr} AS type_name,
        {$typeSlugExpr} AS type_slug
    FROM fact_timeline_events e
    {$typeJoin}
    WHERE e.id = ?
    LIMIT 1
";
$st = $link->prepare($eventSql);
if ($st) {
    $st->bind_param('i', $eventId);
    $st->execute();
    $rs = $st->get_result();
    $event = $rs ? $rs->fetch_assoc() : null;
    $st->close();
}

if (!$event) {
    include('app/partials/main_nav_bar.php');
    echo '<link rel="stylesheet" href="/assets/css/hg-main.css">';
    echo '<link rel="stylesheet" href="/assets/css/hg-events.css">';
    echo "<div class='event-page'><div class='events-empty'>Evento no encontrado.</div></div>";
    return;
}

$title = trim((string)($event['title'] ?? 'Evento'));
$description = trim((string)($event['description'] ?? ''));
$metaDesc = $description !== '' ? $description : 'Detalle del evento de la linea temporal de Heaven\'s Gate.';
if (function_exists('meta_excerpt')) {
    $metaDesc = meta_excerpt($metaDesc);
}

setMetaFromPage($title . " | Evento | Heaven's Gate", $metaDesc, null, 'article');

$participants = [];
if (hg_ev_table_exists($link, 'bridge_timeline_events_characters') && hg_ev_table_exists($link, 'fact_characters')) {
    $characterOrder = [];
    if (hg_ev_col_exists($link, 'bridge_timeline_events_characters', 'sort_order')) $characterOrder[] = 'b.sort_order ASC';
    $characterOrder[] = 'c.name ASC';
    $characterOrder[] = 'c.id ASC';
    $characterOrderSql = implode(', ', $characterOrder);
    $roleExpr = hg_ev_col_exists($link, 'bridge_timeline_events_characters', 'role_label') ? 'b.role_label' : 'NULL';

    $kindExpr = function_exists('hg_character_kind_select') ? hg_character_kind_select($link, 'c') : "''";
    if ($st = $link->prepare("SELECT c.id, c.name, c.pretty_id, c.alias, c.image_url, c.gender, COALESCE(dcs.label, '') AS status, {$kindExpr} AS character_kind, {$roleExpr} AS role_label FROM bridge_timeline_events_characters b INNER JOIN fact_characters c ON c.id = b.character_id LEFT JOIN dim_character_status dcs ON dcs.id = c.status_id WHERE b.event_id = ? ORDER BY {$characterOrderSql}")) {
        $st->bind_param('i', $eventId);
        $st->execute();
        $rs = $st->get_result();
        while ($rs && ($row = $rs->fetch_assoc())) {
            $participants[] = $row;
        }
        $st->close();
    }
}

$chapters = [];
if (hg_ev_table_exists($link, 'bridge_timeline_events_chapters') && hg_ev_table_exists($link, 'dim_chapters')) {
    $chapterOrder = [];
    if (hg_ev_col_exists($link, 'bridge_timeline_events_chapters', 'sort_order')) $chapterOrder[] = 'b.sort_order ASC';
    $chapterOrder[] = 'COALESCE(s.sort_order, 9999) ASC';
    $chapterOrder[] = 'c.chapter_number ASC';
    $chapterOrder[] = 'c.id ASC';
    $chapterOrderSql = implode(', ', $chapterOrder);

    $seasonJoin = '';
    if ($hasSeasonsTable) {
        $seasonJoin = "LEFT JOIN dim_seasons s ON s.id = c.season_id";
    }
    $seasonNameExpr = $hasSeasonsTable ? "s.name" : "NULL";
    $seasonKindExpr = "COALESCE(s.season_kind, 'temporada')";
    if ($st = $link->prepare("SELECT c.id, c.name, c.pretty_id, s.season_number AS season_number, c.season_id AS season_id, c.chapter_number, {$seasonNameExpr} AS season_name, {$seasonKindExpr} AS season_kind FROM bridge_timeline_events_chapters b INNER JOIN dim_chapters c ON c.id = b.chapter_id {$seasonJoin} WHERE b.event_id = ? ORDER BY {$chapterOrderSql}")) {
        $st->bind_param('i', $eventId);
        $st->execute();
        $rs = $st->get_result();
        while ($rs && ($row = $rs->fetch_assoc())) {
            $chapters[] = $row;
        }
        $st->close();
    }
}

$chronicles = [];
if (hg_ev_table_exists($link, 'bridge_timeline_events_chronicles') && hg_ev_table_exists($link, 'dim_chronicles')) {
    $chronOrder = [];
    if (hg_ev_col_exists($link, 'bridge_timeline_events_chronicles', 'sort_order')) $chronOrder[] = 'b.sort_order ASC';
    if (hg_ev_col_exists($link, 'dim_chronicles', 'sort_order')) $chronOrder[] = 'c.sort_order ASC';
    $chronOrder[] = 'c.name ASC';
    $chronOrderSql = implode(', ', $chronOrder);

    if ($st = $link->prepare("SELECT c.id, c.name, c.pretty_id FROM bridge_timeline_events_chronicles b INNER JOIN dim_chronicles c ON c.id = b.chronicle_id WHERE b.event_id = ? ORDER BY {$chronOrderSql}")) {
        $st->bind_param('i', $eventId);
        $st->execute();
        $rs = $st->get_result();
        while ($rs && ($row = $rs->fetch_assoc())) {
            $chronicles[] = $row;
        }
        $st->close();
    }
} elseif ($hasFteTimeline) {
    $legacy = trim((string)($event['timeline'] ?? ''));
    if ($legacy !== '') {
        $chronicles[] = ['id' => 0, 'name' => $legacy, 'pretty_id' => ''];
    }
}

$realities = [];
if (hg_ev_table_exists($link, 'bridge_timeline_events_realities') && hg_ev_table_exists($link, 'dim_realities')) {
    $realOrder = [];
    if (hg_ev_col_exists($link, 'bridge_timeline_events_realities', 'sort_order')) $realOrder[] = 'b.sort_order ASC';
    if (hg_ev_col_exists($link, 'dim_realities', 'sort_order')) $realOrder[] = 'r.sort_order ASC';
    $realOrder[] = 'r.name ASC';
    $realOrderSql = implode(', ', $realOrder);

    if ($st = $link->prepare("SELECT r.id, r.name, r.pretty_id FROM bridge_timeline_events_realities b INNER JOIN dim_realities r ON r.id = b.reality_id WHERE b.event_id = ? ORDER BY {$realOrderSql}")) {
        $st->bind_param('i', $eventId);
        $st->execute();
        $rs = $st->get_result();
        while ($rs && ($row = $rs->fetch_assoc())) {
            $realities[] = $row;
        }
        $st->close();
    }
}

$showRealitiesSection = false; // Preparado para activarse cuando no sea spoiler.

$anchorDate = trim((string)($event['sort_date'] ?? ''));
if ($anchorDate === '' || $anchorDate === '0000-00-00') {
    $anchorDate = trim((string)($event['event_date'] ?? ''));
}
if ($anchorDate === '' || $anchorDate === '0000-00-00') {
    $anchorDate = '1000-01-01';
}

$navSortExpr = $hasFteSortDate ? 'COALESCE(sort_date, event_date)' : 'event_date';
$navPrettyExpr = $hasFtePretty ? 'pretty_id' : "CAST(id AS CHAR)";
$navActiveCond = $hasFteIsActive ? 'is_active = 1 AND ' : '';

$prevEvent = null;
if ($st = $link->prepare("SELECT id, {$navPrettyExpr} AS pretty_id, title FROM fact_timeline_events WHERE {$navActiveCond} ({$navSortExpr} < ? OR ({$navSortExpr} = ? AND id < ?)) ORDER BY {$navSortExpr} DESC, id DESC LIMIT 1")) {
    $st->bind_param('ssi', $anchorDate, $anchorDate, $eventId);
    $st->execute();
    $rs = $st->get_result();
    $prevEvent = $rs ? $rs->fetch_assoc() : null;
    $st->close();
}

$nextEvent = null;
if ($st = $link->prepare("SELECT id, {$navPrettyExpr} AS pretty_id, title FROM fact_timeline_events WHERE {$navActiveCond} ({$navSortExpr} > ? OR ({$navSortExpr} = ? AND id > ?)) ORDER BY {$navSortExpr} ASC, id ASC LIMIT 1")) {
    $st->bind_param('ssi', $anchorDate, $anchorDate, $eventId);
    $st->execute();
    $rs = $st->get_result();
    $nextEvent = $rs ? $rs->fetch_assoc() : null;
    $st->close();
}

$dateLabel = hg_ev_date_label(
    (string)($event['event_date'] ?? ''),
    (string)($event['date_precision'] ?? 'day'),
    (string)($event['date_note'] ?? '')
);

$typeName = trim((string)($event['type_name'] ?? 'Evento'));
$typeSlug = trim((string)($event['type_slug'] ?? 'evento'));
$location = trim((string)($event['location'] ?? ''));
$source = trim((string)($event['source'] ?? ''));
$legacyTimeline = trim((string)($event['timeline'] ?? ''));
$primaryChronicle = !empty($chronicles) ? $chronicles[0] : null;
$chronicleName = $primaryChronicle ? trim((string)($primaryChronicle['name'] ?? '')) : '';
$chronicleHref = '';
if ($primaryChronicle && (int)($primaryChronicle['id'] ?? 0) > 0) {
    $chronicleHref = pretty_url($link, 'dim_chronicles', '/chronicles', (int)$primaryChronicle['id']);
}
$showTypeField = ($typeName !== '');
$showDateField = ($dateLabel !== '' && $dateLabel !== '-' && stripos($dateLabel, 'desconocida') === false);
$showLocationField = ($location !== '');
$showChronicleField = ($chronicleName !== '');
$showAnyTechField = ($showTypeField || $showDateField || $showLocationField || $showChronicleField);

$chaptersBySeason = [];
foreach ($chapters as $chapterRow) {
    $seasonId = (int)($chapterRow['season_id'] ?? 0);
    $seasonNum = (int)($chapterRow['season_number'] ?? 0);
    $seasonKey = ($seasonId > 0 ? $seasonId : ($seasonNum > 0 ? $seasonNum : 9999));
    if (!isset($chaptersBySeason[$seasonKey])) $chaptersBySeason[$seasonKey] = [];
    $chaptersBySeason[$seasonKey][] = $chapterRow;
}
ksort($chaptersBySeason, SORT_NUMERIC);

include('app/partials/main_nav_bar.php');
echo '<link rel="stylesheet" href="/assets/css/hg-main.css">';
echo '<link rel="stylesheet" href="/assets/css/hg-events.css">';
echo '<link rel="stylesheet" href="/assets/css/hg-chapters.css">';

$prevHrefKey = $prevEvent ? hg_ev_event_url($prevEvent) : '';
$nextHrefKey = $nextEvent ? hg_ev_event_url($nextEvent) : '';
?>

<div class="event-page event-power-page">
    <div class="power-card power-card--event">
        <div class="power-card__banner">
            <span class="power-card__title"><?= hg_ev_h($title) ?></span>
        </div>

        <div class="power-card__body">
            <div class="power-card__media">
                <img class="power-card__img event-power__img" src="/public/img/inv/no-photo.gif" alt="<?= hg_ev_h($title) ?>">
            </div>
            <div class="power-card__stats">
                <?php if ($showTypeField): ?>
                <div class="power-stat">
                    <div class="power-stat__label">Tipo de evento</div>
                    <div class="power-stat__value"><?= hg_ev_h($typeName) ?></div>
                </div>
                <?php endif; ?>

                <?php if ($showDateField): ?>
                <div class="power-stat">
                    <div class="power-stat__label">Fecha</div>
                    <div class="power-stat__value"><?= hg_ev_h($dateLabel) ?></div>
                </div>
                <?php endif; ?>

                <?php if ($showLocationField): ?>
                <div class="power-stat">
                    <div class="power-stat__label">Lugar</div>
                    <div class="power-stat__value"><?= hg_ev_h($location) ?></div>
                </div>
                <?php endif; ?>

                <?php if ($showChronicleField): ?>
                <div class="power-stat">
                    <div class="power-stat__label">Cr&oacute;nica</div>
                    <div class="power-stat__value">
                        <?php if ($chronicleHref !== ''): ?>
                        <a class="event-top-link hg-tooltip" href="<?= hg_ev_h($chronicleHref) ?>" target="_blank" data-tip="dim_chronicle" data-id="<?= (int)$primaryChronicle['id'] ?>"><?= hg_ev_h($chronicleName) ?></a>
                        <?php else: ?>
                        <?= hg_ev_h($chronicleName) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!$showAnyTechField): ?>
                <div class="power-stat">
                    <div class="power-stat__label">Ficha tecnica</div>
                    <div class="power-stat__value">Sin datos completos.</div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="power-card__desc">
            <div class="power-card__desc-title">Descripcion</div>
            <div class="power-card__desc-body">
                <?php if ($description !== ''): ?>
                <?= nl2br(($description)) ?>
                <?php else: ?>
                Sin descripcion registrada.
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($participants)): ?>
        <div class="power-card__desc event-rel-block">
            <div class="power-card__desc-title">Personajes</div>
            <div class="power-card__desc-body">
                <div class="event-char-grid">
                    <?php foreach ($participants as $row):
                        $href = pretty_url($link, 'fact_characters', '/characters', (int)$row['id']);
                        $avatar = function_exists('hg_character_avatar_url')
                            ? hg_character_avatar_url((string)($row['image_url'] ?? ''), (string)($row['gender'] ?? ''))
                            : '/img/ui/avatar/avatar_nadie_3.png';
                        $role = trim((string)($row['role_label'] ?? ''));
                        $charName = trim((string)($row['name'] ?? ''));
                        $charTitle = $role !== '' ? ($charName . ' - ' . $role) : $charName;
                    ?>
                    <a class="event-char-mini hg-tooltip" href="<?= hg_ev_h($href) ?>" target="_blank" data-tip="character" data-id="<?= (int)$row['id'] ?>">
                        <img src="<?= hg_ev_h($avatar) ?>" alt="<?= hg_ev_h($charName) ?>">
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($chaptersBySeason)): ?>
        <div class="power-card__desc event-rel-block">
            <?php //<div class="power-card__desc-title">Cap&iacute;tulos (<?= (int)count($chapters) )</div> ?>
            <div class="power-card__desc-body">
                <div class="event-rel-lista">
                    <?php foreach ($chaptersBySeason as $seasonNum => $seasonChapters): ?>
                    <div class="event-rel-group">
                        <?php
                            $seasonHeader = 'Capitulos sin temporada';
                            if ($seasonNum !== 9999 && !empty($seasonChapters)) {
                                $firstSeason = $seasonChapters[0];
                                $sk = trim((string)($firstSeason['season_kind'] ?? 'temporada'));
                                $sn = (int)($firstSeason['season_number'] ?? 0);
                                $sname = trim((string)($firstSeason['season_name'] ?? ''));
                                if ($sk === 'historia_personal') {
                                    $seasonHeader = ($sname !== '' ? ($sname . ' (Historia personal)') : 'Historia personal');
                                } elseif ($sk === 'inciso') {
                                    $incisoNum = $sn;
                                    if ($incisoNum >= 100 && $incisoNum < 200) $incisoNum -= 100;
                                    $seasonHeader = 'Inciso ' . $incisoNum . ($sname !== '' ? (' - ' . $sname) : '');
                                } elseif ($sk === 'especial') {
                                    $seasonHeader = ($sname !== '' ? ('Especial - ' . $sname) : 'Especial');
                                } else {
                                    $seasonHeader = ($sname !== '' ? ($sname . ' (Temporada ' . $sn . ')') : ('Temporada ' . $sn));
                                }
                            }
                        ?>
                        <div class="event-rel-group-title"><?= hg_ev_h($seasonHeader) ?></div>
                        <div class="event-rel-items event-rel-items--chapters">
                            <?php foreach ($seasonChapters as $row):
                                $href = pretty_url($link, 'dim_chapters', '/chapters', (int)$row['id']);
                                $chapterKind = trim((string)($row['season_kind'] ?? 'temporada'));
                                if ($chapterKind === 'temporada') {
                                    $chapterCode = ((int)$row['season_number']) . 'x' . str_pad((string)(int)$row['chapter_number'], 2, '0', STR_PAD_LEFT);
                                } else {
                                    $chapterCode = str_pad((string)(int)$row['chapter_number'], 2, '0', STR_PAD_LEFT);
                                }
                            ?>
                            <a class="event-rel-item hg-tooltip" href="<?= hg_ev_h($href) ?>" target="_blank" data-tip="chapter" data-id="<?= (int)$row['id'] ?>">
                                <span class="event-rel-item-name"><?= hg_ev_h((string)$row['name']) ?></span>
                                <span class="event-rel-item-meta"><?= hg_ev_h($chapterCode) ?></span>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($showRealitiesSection && !empty($realities)): ?>
        <div class="power-card__desc event-rel-block">
            <div class="power-card__desc-title">Realidades (<?= (int)count($realities) ?>)</div>
            <div class="power-card__desc-body">
                <div class="event-rel-lista">
                    <div class="event-rel-group">
                        <div class="event-rel-group-title">Realidades vinculadas</div>
                        <div class="event-rel-items">
                            <?php foreach ($realities as $row):
                                $href = pretty_url($link, 'dim_realities', '/characters/worlds', (int)$row['id']);
                            ?>
                            <a class="event-rel-item" href="<?= hg_ev_h($href) ?>" target="_blank">
                                <span class="event-rel-item-name"><?= hg_ev_h((string)$row['name']) ?></span>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="chapter-nav event-nav-chapter-style">
        <?php if ($prevEvent): ?>
        <a class="chapter-nav-link prev" href="<?= hg_ev_h($prevHrefKey) ?>">&laquo; <?= hg_ev_h((string)$prevEvent['title']) ?></a>
        <?php else: ?>
        <div class="nav-empty"></div>
        <?php endif; ?>

        <?php if ($nextEvent): ?>
        <a class="chapter-nav-link next" href="<?= hg_ev_h($nextHrefKey) ?>"><?= hg_ev_h((string)$nextEvent['title']) ?> &raquo;</a>
        <?php else: ?>
        <div class="nav-empty"></div>
        <?php endif; ?>
    </div>
</div>

<script>
(function() {
    var prevHref = <?= json_encode((string)$prevHrefKey, JSON_UNESCAPED_UNICODE) ?>;
    var nextHref = <?= json_encode((string)$nextHrefKey, JSON_UNESCAPED_UNICODE) ?>;
    function trimNavLabels() {
        var links = document.querySelectorAll('.event-nav-chapter-style .chapter-nav-link');
        if (!links || !links.length) return;
        links.forEach(function(link) {
            var full = link.getAttribute('data-full-label');
            if (!full) {
                full = (link.textContent || '').trim();
                link.setAttribute('data-full-label', full);
            }
            link.textContent = full;
            if (link.scrollWidth <= link.clientWidth) return;

            var text = full;
            var prefix = '';
            var suffix = '';

            if (/^Â«\s*/.test(text)) {
                prefix = 'Â« ';
                text = text.replace(/^Â«\s*/, '');
            }
            if (/\s*Â»$/.test(text)) {
                suffix = ' Â»';
                text = text.replace(/\s*Â»$/, '');
            }

            var lo = 0;
            var hi = text.length;
            var best = '';
            while (lo <= hi) {
                var mid = (lo + hi) >> 1;
                var core = text.slice(0, mid).trimEnd();
                var candidate = prefix + (core ? (core + '...') : '...') + suffix;
                link.textContent = candidate;
                if (link.scrollWidth <= link.clientWidth) {
                    best = candidate;
                    lo = mid + 1;
                } else {
                    hi = mid - 1;
                }
            }

            if (best) {
                link.textContent = best;
            } else {
                link.textContent = prefix + '...' + suffix;
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', trimNavLabels);
    } else {
        trimNavLabels();
    }
    window.addEventListener('resize', trimNavLabels);
    window.setTimeout(trimNavLabels, 0);
    window.setTimeout(trimNavLabels, 120);

    document.addEventListener('keydown', function(e) {
        if (e.defaultPrevented || e.ctrlKey || e.altKey || e.metaKey) return;
        var t = e.target;
        if (t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.tagName === 'SELECT' || t.isContentEditable)) return;
        if (e.key === 'ArrowLeft' && prevHref) {
            window.location.href = prevHref;
        } else if (e.key === 'ArrowRight' && nextHref) {
            window.location.href = nextHref;
        }
    });
})();
</script>


