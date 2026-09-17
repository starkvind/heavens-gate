<?php if (function_exists('setMetaFromPage')) { setMetaFromPage("Linea temporal | Heaven's Gate", "Linea temporal de eventos y sucesos.", null, 'website'); } ?>
<?php
include_once(__DIR__ . '/../../helpers/public_response.php');
include_once(__DIR__ . '/../../domains/timeline/queries.php');

if (!$link) {
    hg_public_log_error('events_main', 'missing DB connection');
    hg_public_render_error('Linea temporal no disponible', 'No se pudo cargar la linea temporal en este momento.');
    return;
}

if (!function_exists('hg_events_h')) {
    function hg_events_h($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('hg_events_date_label')) {
    function hg_events_date_label(?string $dateValue, string $precision, ?string $note): string {
        $precision = trim((string)$precision);
        $dateValue = trim((string)$dateValue);
        $note = trim((string)$note);
        if ($precision === 'unknown') return $note !== '' ? $note : 'Desconocida';
        if ($dateValue === '' || $dateValue === '0000-00-00') return $note !== '' ? $note : '-';
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dateValue, $parts)) return $note !== '' ? $note : $dateValue;
        $base = $parts[3] . '/' . $parts[2] . '/' . $parts[1];
        if ($precision === 'year') $base = $parts[1];
        elseif ($precision === 'month') $base = $parts[2] . '/' . $parts[1];
        elseif ($precision === 'approx') $base = 'Aprox. ' . $base;
        return $note !== '' ? ($base . ' (' . $note . ')') : $base;
    }
}

if (!function_exists('hg_events_excerpt')) {
    function hg_events_excerpt(string $text, int $max = 180): string {
        $txt = trim($text);
        if ($txt === '') return '';
        $txt = preg_replace('/\s+/', ' ', $txt);
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return (mb_strlen($txt, 'UTF-8') > $max) ? (mb_substr($txt, 0, $max, 'UTF-8') . '...') : $txt;
        }
        return (strlen($txt) > $max) ? (substr($txt, 0, $max) . '...') : $txt;
    }
}

$rows = hg_timeline_fetch_index_rows($link);
if ($rows === null) {
    if (!defined('HG_MOBILE_TIMELINE_EMBED') || !HG_MOBILE_TIMELINE_EMBED) { include("app/partials/main_nav_bar.php"); }
    if (function_exists('hg_page_register_stylesheet')) hg_page_register_stylesheet('/assets/css/hg-events.css');
    else echo '<link rel="stylesheet" href="/assets/css/hg-events.css">';
    if (defined('HG_MOBILE_TIMELINE_EMBED') && HG_MOBILE_TIMELINE_EMBED) {
        if (function_exists('hg_page_register_stylesheet')) hg_page_register_stylesheet('/assets/css/hg-mobile-timeline.css');
        else echo '<link rel="stylesheet" href="/assets/css/hg-mobile-timeline.css">';
    }
    echo "<div class='events-wrap'><div class='events-empty'>No existe la tabla fact_timeline_events en esta base de datos.</div></div>";
    return;
}

$excludedChronicleIds = hg_timeline_normalize_ids($excludeChronicles ?? []);
$excludedChronicleIdSet = array_fill_keys($excludedChronicleIds, true);
$events = [];
$showRealityFilter = false;

foreach ($rows as $row) {
    $typeSlug = trim((string)($row['type_slug'] ?? 'evento')) ?: 'evento';
    $typeName = trim((string)($row['type_name'] ?? 'Evento')) ?: 'Evento';
    $typeColor = trim((string)($row['type_color'] ?? '')) ?: null;

    $icon = 'O';
    switch ($typeSlug) {
        case 'catastrofe': $icon = 'F'; break;
        case 'batalla': $icon = 'X'; break;
        case 'nacimiento': $icon = 'N'; break;
        case 'muerte': $icon = 'M'; break;
        case 'descubrimiento': $icon = 'D'; break;
        case 'traicion': $icon = 'T'; break;
        case 'romance': $icon = 'R'; break;
        case 'fundacion': $icon = 'U'; break;
        case 'alianza': $icon = 'A'; break;
        case 'enemistad': $icon = 'E'; break;
        case 'reclutamiento': $icon = 'Q'; break;
        case 'otros': $icon = 'P'; break;
    }

    $chronicleIds = [];
    $chronicleNames = [];
    $chronicleRefsRaw = trim((string)($row['chronicle_refs'] ?? ''));
    if ($chronicleRefsRaw !== '') {
        foreach (explode('||', $chronicleRefsRaw) as $ref) {
            $parts = explode('::', $ref, 2);
            $cid = (int)($parts[0] ?? 0);
            $cname = trim((string)($parts[1] ?? ''));
            if ($cid > 0) {
                $chronicleIds[] = $cid;
                if ($cname !== '') $chronicleNames[] = $cname;
            }
        }
    }
    $chronicleIds = array_values(array_unique($chronicleIds));
    $chronicleNames = array_values(array_unique($chronicleNames));
    if ($excludedChronicleIdSet) {
        $skip = false;
        foreach ($chronicleIds as $cid) {
            if (isset($excludedChronicleIdSet[$cid])) { $skip = true; break; }
        }
        if ($skip) continue;
    }

    $realityIds = [];
    $realityNames = [];
    $realityRefsRaw = trim((string)($row['reality_refs'] ?? ''));
    if ($realityRefsRaw !== '') {
        foreach (explode('||', $realityRefsRaw) as $ref) {
            $parts = explode('::', $ref, 2);
            $rid = (int)($parts[0] ?? 0);
            $rname = trim((string)($parts[1] ?? ''));
            if ($rid > 0) {
                $realityIds[] = $rid;
                if ($rname !== '') $realityNames[] = $rname;
            }
        }
    }

    $eventId = (int)($row['id'] ?? 0);
    $slug = trim((string)($row['pretty_id'] ?? '')) ?: (string)$eventId;
    $eventDate = trim((string)($row['event_date'] ?? ''));
    $sortDate = trim((string)($row['sort_date'] ?? '')) ?: $eventDate;
    $description = trim((string)($row['description'] ?? ''));
    $chronicleLine = trim((string)($row['chronicle_line'] ?? '-')) ?: '-';
    $realityLine = trim((string)($row['reality_line'] ?? '-')) ?: '-';

    $events[] = [
        'id' => $eventId,
        'pretty_id' => $slug,
        'url' => '/timeline/event/' . rawurlencode($slug),
        'title' => trim((string)($row['title'] ?? '')),
        'event_date' => $eventDate,
        'sort_date' => $sortDate,
        'date_label' => hg_events_date_label($eventDate, (string)($row['date_precision'] ?? 'day'), (string)($row['date_note'] ?? '')),
        'type_slug' => $typeSlug,
        'type_name' => $typeName,
        'type_color' => $typeColor,
        'description' => $description,
        'short_desc' => hg_events_excerpt($description, 190),
        'location' => trim((string)($row['location'] ?? '')),
        'source' => trim((string)($row['source'] ?? '')),
        'chronicle_line' => $chronicleLine,
        'reality_line' => $realityLine,
        'chronicle_ids' => $chronicleIds,
        'chronicle_names' => $chronicleNames,
        'reality_ids' => array_values(array_unique($realityIds)),
        'reality_names' => array_values(array_unique($realityNames)),
        'icon' => $icon,
    ];
}

$typeStats = [];
$chronicleFilterMap = [];
$realityFilterMap = [];
foreach ($events as $ev) {
    $slug = (string)($ev['type_slug'] ?? 'evento');
    $name = (string)($ev['type_name'] ?? 'Evento');
    if (!isset($typeStats[$slug])) $typeStats[$slug] = ['slug' => $slug, 'name' => $name, 'count' => 0];
    $typeStats[$slug]['count']++;

    $ids = $ev['chronicle_ids'] ?? [];
    $names = $ev['chronicle_names'] ?? [];
    for ($i = 0, $len = min(count($ids), count($names)); $i < $len; $i++) {
        $cid = (int)$ids[$i]; $cname = trim((string)$names[$i]);
        if ($cid > 0 && $cname !== '') $chronicleFilterMap[$cid] = $cname;
    }
    $ids = $ev['reality_ids'] ?? [];
    $names = $ev['reality_names'] ?? [];
    for ($i = 0, $len = min(count($ids), count($names)); $i < $len; $i++) {
        $rid = (int)$ids[$i]; $rname = trim((string)$names[$i]);
        if ($rid > 0 && $rname !== '') $realityFilterMap[$rid] = $rname;
    }
}
uasort($typeStats, static fn($a, $b) => strcasecmp((string)$a['name'], (string)$b['name']));
ksort($chronicleFilterMap);
ksort($realityFilterMap);

$totalEvents = count($events);
$rangeStart = '';
$rangeEnd = '';
if ($totalEvents > 0) {
    $dates = array_values(array_filter(array_column($events, 'event_date')));
    sort($dates);
    if ($dates) { $rangeStart = (string)$dates[0]; $rangeEnd = (string)end($dates); }
}

$timelineStart = date('Y-m-d', strtotime('-10 years'));
$timelineEnd = date('Y-m-d', strtotime('+5 years'));
if ($rangeStart !== '' && $rangeEnd !== '') {
    $timelineStart = (new DateTime($rangeStart))->modify('-10 years')->format('Y-m-d');
    $timelineEnd = (new DateTime($rangeEnd))->modify('+5 years')->format('Y-m-d');
}

include(__DIR__ . '/../../views/timeline/index.php');
