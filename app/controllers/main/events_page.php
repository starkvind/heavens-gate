<?php
include_once(__DIR__ . '/../../helpers/public_response.php');
include_once(__DIR__ . '/../../helpers/pretty.php');
include_once(__DIR__ . '/../../helpers/character_avatar.php');
include_once(__DIR__ . '/../../domains/timeline/queries.php');

if (!$link) {
    hg_public_log_error('events_page', 'missing DB connection');
    hg_public_render_error('Evento no disponible', 'No se pudo cargar el evento solicitado en este momento.');
    return;
}

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
        if ($precision === 'unknown') return $note !== '' ? $note : 'Desconocida';
        if ($dateValue === '' || $dateValue === '0000-00-00') return $note !== '' ? $note : '-';
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dateValue, $parts)) return $note !== '' ? $note : $dateValue;
        $base = $parts[3] . '-' . $parts[2] . '-' . $parts[1];
        if ($precision === 'year') $base = $parts[1];
        elseif ($precision === 'month') $base = $parts[2] . '-' . $parts[1];
        elseif ($precision === 'approx') $base = 'Aprox. ' . $base;
        return $note !== '' ? ($base . ' (' . $note . ')') : $base;
    }
}

if (!function_exists('hg_ev_event_url')) {
    function hg_ev_event_url(array $row): string {
        $slug = trim((string)($row['pretty_id'] ?? ''));
        if ($slug === '') $slug = (string)($row['id'] ?? '');
        return '/timeline/event/' . rawurlencode($slug);
    }
}

if (!hg_timeline_table_exists($link, 'fact_timeline_events')) {
    if (!defined('HG_MOBILE_TIMELINE_EMBED') || !HG_MOBILE_TIMELINE_EMBED) { include('app/partials/main_nav_bar.php'); }
    if (function_exists('hg_page_register_stylesheet')) hg_page_register_stylesheet('/assets/css/hg-events.css');
    else echo '<link rel="stylesheet" href="/assets/css/hg-events.css">';
    echo "<div class='event-page'><div class='events-empty'>No existe la tabla fact_timeline_events en esta base de datos.</div></div>";
    return;
}

$rawEvent = hg_request_param($hgRequest, 'event');
$eventId = hg_timeline_resolve_event_id($link, $rawEvent);
if ($eventId <= 0) {
    if (!defined('HG_MOBILE_TIMELINE_EMBED') || !HG_MOBILE_TIMELINE_EMBED) { include('app/partials/main_nav_bar.php'); }
    if (function_exists('hg_page_register_stylesheet')) hg_page_register_stylesheet('/assets/css/hg-events.css');
    else echo '<link rel="stylesheet" href="/assets/css/hg-events.css">';
    echo "<div class='event-page'><div class='events-empty'>Evento no encontrado.</div></div>";
    return;
}

$event = hg_timeline_fetch_event($link, $eventId);
if (!$event) {
    if (!defined('HG_MOBILE_TIMELINE_EMBED') || !HG_MOBILE_TIMELINE_EMBED) { include('app/partials/main_nav_bar.php'); }
    if (function_exists('hg_page_register_stylesheet')) hg_page_register_stylesheet('/assets/css/hg-events.css');
    else echo '<link rel="stylesheet" href="/assets/css/hg-events.css">';
    echo "<div class='event-page'><div class='events-empty'>Evento no encontrado.</div></div>";
    return;
}

$title = trim((string)($event['title'] ?? 'Evento'));
$description = trim((string)($event['description'] ?? ''));
$metaDesc = $description !== '' ? $description : 'Detalle del evento de la linea temporal de Heaven\'s Gate.';
if (function_exists('meta_excerpt')) $metaDesc = meta_excerpt($metaDesc);
if (function_exists('setMetaFromPage')) setMetaFromPage($title . " | Evento | Heaven's Gate", $metaDesc, null, 'article');

$participants = hg_timeline_fetch_event_participants($link, $eventId) ?? [];
$chapters = hg_timeline_fetch_event_chapters($link, $eventId) ?? [];
$chronicles = hg_timeline_fetch_event_chronicles($link, $eventId) ?? [];
if (!$chronicles) {
    $legacy = trim((string)($event['timeline'] ?? ''));
    if ($legacy !== '') $chronicles[] = ['id' => 0, 'name' => $legacy, 'pretty_id' => ''];
}
$realities = hg_timeline_fetch_event_realities($link, $eventId) ?? [];
$showRealitiesSection = false;

$anchorDate = trim((string)($event['sort_date'] ?? ''));
if ($anchorDate === '' || $anchorDate === '0000-00-00') $anchorDate = trim((string)($event['event_date'] ?? ''));
if ($anchorDate === '' || $anchorDate === '0000-00-00') $anchorDate = '1000-01-01';
$prevEvent = hg_timeline_fetch_neighbor($link, $eventId, $anchorDate, 'prev');
$nextEvent = hg_timeline_fetch_neighbor($link, $eventId, $anchorDate, 'next');

$dateLabel = hg_ev_date_label((string)($event['event_date'] ?? ''), (string)($event['date_precision'] ?? 'day'), (string)($event['date_note'] ?? ''));
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

if (!defined('HG_MOBILE_TIMELINE_EMBED') || !HG_MOBILE_TIMELINE_EMBED) { include('app/partials/main_nav_bar.php'); }
if (function_exists('hg_page_register_stylesheet')) hg_page_register_stylesheet('/assets/css/hg-events.css');
else echo '<link rel="stylesheet" href="/assets/css/hg-events.css">';
if (function_exists('hg_page_register_stylesheet')) hg_page_register_stylesheet('/assets/css/hg-chapters.css');
else echo '<link rel="stylesheet" href="/assets/css/hg-chapters.css">';
if (defined('HG_MOBILE_TIMELINE_EMBED') && HG_MOBILE_TIMELINE_EMBED) echo '<link rel="stylesheet" href="/assets/css/hg-mobile-timeline.css">';

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
                <img class="power-card__img event-power__img" src="/public/img/inv/no-photo.webp" alt="<?= hg_ev_h($title) ?>">
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
            <div class="power-card__desc-title">Descripción</div>
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
                            : '/img/ui/avatar/avatar_nadie_3.webp';
                        $role = trim((string)($row['role_label'] ?? ''));
                        $charName = trim((string)($row['name'] ?? ''));
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
            <div class="power-card__desc-body">
                <div class="event-rel-lista">
                    <?php foreach ($chaptersBySeason as $seasonNum => $seasonChapters): ?>
                    <div class="event-rel-group">
                        <?php
                            $seasonHeader = 'Capítulos sin temporada';
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

            if (/^«\s*/.test(text)) {
                prefix = '« ';
                text = text.replace(/^«\s*/, '');
            }
            if (/\s*»$/.test(text)) {
                suffix = ' »';
                text = text.replace(/\s*»$/, '');
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

            if (best) link.textContent = best;
            else link.textContent = prefix + '...' + suffix;
        });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', trimNavLabels);
    else trimNavLabels();
    window.addEventListener('resize', trimNavLabels);
    window.setTimeout(trimNavLabels, 0);
    window.setTimeout(trimNavLabels, 120);

    document.addEventListener('keydown', function(e) {
        if (e.defaultPrevented || e.ctrlKey || e.altKey || e.metaKey) return;
        var t = e.target;
        if (t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.tagName === 'SELECT' || t.isContentEditable)) return;
        if (e.key === 'ArrowLeft' && prevHref) window.location.href = prevHref;
        else if (e.key === 'ArrowRight' && nextHref) window.location.href = nextHref;
    });
})();
</script>
