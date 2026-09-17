<?php

include_once(__DIR__ . '/../../helpers/public_response.php');
include_once(__DIR__ . '/../../helpers/pretty.php');
include_once(__DIR__ . '/../../helpers/character_avatar.php');
include_once(__DIR__ . '/../helpers/chronicle_scope.php');
include_once(__DIR__ . '/../../domains/timeline/queries.php');

$metaTitle = "Evento | Heaven's Gate";
$metaDescription = 'Ficha móvil de evento de línea temporal.';
$pageSect = 'Línea temporal';

if (!function_exists('hg_mobile_event_h')) {
    function hg_mobile_event_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dateValue, $parts)) return $note !== '' ? $note : $dateValue;
        $base = $parts[3] . '-' . $parts[2] . '-' . $parts[1];
        if ($precision === 'year') $base = $parts[1];
        elseif ($precision === 'month') $base = $parts[2] . '-' . $parts[1];
        elseif ($precision === 'approx') $base = 'Aprox. ' . $base;
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

if (!isset($link) || !($link instanceof mysqli)) {
    echo '<section class="hg-mobile-section"><h1>Evento no disponible</h1><p>No se pudo conectar con la base de datos.</p></section>';
    return;
}

$excludedIds = function_exists('hg_mobile_excluded_chronicles_csv')
    ? hg_timeline_normalize_ids(hg_mobile_excluded_chronicles_csv())
    : [];
$rawEvent = hg_request_param($hgRequest, 'event');
$eventId = hg_timeline_resolve_event_id($link, $rawEvent);

if ($eventId <= 0 || hg_timeline_event_is_excluded($link, $eventId, $excludedIds)) {
    echo '<section class="hg-mobile-section"><h1>Evento no encontrado</h1><p>No se puede mostrar este evento.</p><p><a href="/timeline">Volver a línea temporal</a></p></section>';
    return;
}

$event = hg_timeline_fetch_event($link, $eventId);
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

$chronicles = hg_timeline_fetch_event_chronicles($link, $eventId, $excludedIds) ?? [];
if (!$chronicles && $timeline !== '') {
    $chronicles[] = ['id' => 0, 'name' => $timeline, 'pretty_id' => ''];
}
$participants = hg_timeline_fetch_event_participants($link, $eventId, $excludedIds) ?? [];
$chapters = hg_timeline_fetch_event_chapters($link, $eventId, $excludedIds) ?? [];
$prevEvent = hg_timeline_fetch_neighbor($link, $eventId, $anchorDate, 'prev', $excludedIds);
$nextEvent = hg_timeline_fetch_neighbor($link, $eventId, $anchorDate, 'next', $excludedIds);
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
