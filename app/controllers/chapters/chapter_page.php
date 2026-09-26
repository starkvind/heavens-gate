<?php
include_once(__DIR__ . '/../../helpers/runtime_response.php');
include_once(__DIR__ . '/../../helpers/character_avatar.php');
include_once(__DIR__ . '/../../helpers/content_image.php');
require_once(__DIR__ . '/../../domains/chapters/queries.php');

if (!hg_runtime_require_db($link, 'chapter_page', 'public', [
    'title' => 'Capítulo no disponible',
    'message' => 'No se pudo conectar a la base de datos.',
    'include_nav' => true,
])) {
    return;
}

$chapter_numberRaw = hg_request_param($hgRequest, 'chapter');
$chapter_numberId = hg_chapters_resolve_chapter_id($link, (string)$chapter_numberRaw);
if ($chapter_numberId <= 0) {
    echo "No se encontraron resultados para la busqueda.";
    return;
}

$ResultQuery = hg_chapters_fetch_chapter_detail($link, $chapter_numberId);
if (!$ResultQuery) {
    echo "No se encontraron resultados para la busqueda.";
    return;
}

$nameCapi = (string)($ResultQuery['name'] ?? '');
$sinoCapi = (string)($ResultQuery['synopsis'] ?? '');
$chapterImage = hg_content_image_url($ResultQuery['image_url'] ?? '');
$noSinoCapi = "Este capitulo no dispone de informacion, disculpa las molestias.";
$tempSeasonId = (int)($ResultQuery['season_id'] ?? 0);
$numeCapi = (int)($ResultQuery['chapter_number'] ?? 0);
$dateCapi = (string)($ResultQuery['played_date'] ?? '');
$idTemporada = $tempSeasonId;
$nameTemporada = (string)($ResultQuery['season_name'] ?? 'Temporada');
$numbTemporada = (int)($ResultQuery['season_number'] ?? 0);
$seasonKind = trim((string)($ResultQuery['season_kind'] ?? 'temporada'));
if ($seasonKind === '') $seasonKind = 'temporada';

$checkNumCapi = ($numeCapi < 10) ? '0' : '';
$numeracionOK = ($seasonKind === 'temporada')
    ? ((string)$numbTemporada . "x" . $checkNumCapi . $numeCapi)
    : ($checkNumCapi . $numeCapi);

$goodFecha = ($dateCapi && $dateCapi !== '0000-00-00') ? date('d-m-Y', strtotime($dateCapi)) : '';

$pageSect = "{$nameTemporada} {$numeracionOK}";
$pageTitle2 = $nameCapi;
setMetaFromPage(
    $nameCapi . " | " . $nameTemporada . " | Heaven's Gate",
    meta_excerpt(!empty($sinoCapi) ? $sinoCapi : $noSinoCapi),
    $chapterImage !== '' ? $chapterImage : null,
    'article'
);

include("app/partials/main_nav_bar.php");
if (function_exists('hg_page_register_stylesheet')) {
    hg_page_register_stylesheet('/assets/css/hg-chapters.css');
} else {
    echo '<link rel="stylesheet" href="/assets/css/hg-chapters.css">';
}

echo "<div class='chapter-shell'>";
echo "<div class='chapter-hero'>";
echo "<h2>" . htmlspecialchars($nameCapi) . "</h2>";
echo "<span class='chapter-code'>Capítulo " . htmlspecialchars($numeracionOK) . "</span>";
echo "</div>";

if ($chapterImage !== '') {
    echo "<figure class='chapter-main-image'>";
    echo "<img src='" . htmlspecialchars($chapterImage, ENT_QUOTES, 'UTF-8') . "' alt='" . htmlspecialchars($nameCapi, ENT_QUOTES, 'UTF-8') . "' loading='lazy'>";
    echo "</figure>";
}

echo "<div class='chapter-grid'>";

echo "<section class='chapter-block'>";
echo "<h3 class='chapter-title'>Participantes</h3>";

$participants = hg_chapters_fetch_chapter_participants($link, $chapter_numberId) ?? [];
if (!empty($participants)) {
    $participantGroups = [
        'player' => [],
        'npc' => [],
    ];

    foreach ($participants as $pj) {
        $role = strtolower(trim((string)($pj['participation_role'] ?? 'npc')));
        if ($role !== 'player') $role = 'npc';
        $participantGroups[$role][] = $pj;
    }

    foreach ([
        'player' => 'Como jugadores',
        'npc' => 'Como PNJ',
    ] as $roleKey => $roleLabel) {
        if (empty($participantGroups[$roleKey])) continue;

        echo "<br />";
        echo "<div class='participants-grid'>";

        foreach ($participantGroups[$roleKey] as $pj) {
            $idPJSelect = (int)($pj['id'] ?? 0);
            $nombre = (string)($pj['name'] ?? '');
            $img = hg_character_avatar_url((string)($pj['image_url'] ?? ''), (string)($pj['gender'] ?? ''));
            $hrefChar = pretty_url($link, 'fact_characters', '/characters', $idPJSelect);
            $roleText = ($roleKey === 'player') ? 'Jugador' : 'PNJ';

            echo "<a class='participant-card participant-card--" . htmlspecialchars($roleKey) . " hg-tooltip' href='" . htmlspecialchars($hrefChar) . "' target='_blank' data-tip='character' data-id='" . $idPJSelect . "'>";
            echo "<img src='" . htmlspecialchars($img) . "' alt='" . htmlspecialchars($nombre) . "'>";
            echo "<span class='participant-name'>" . htmlspecialchars($nombre) . "</span>";
            echo "<small class='participant-role'>" . htmlspecialchars($roleText) . "</small>";
            echo "</a>";
        }

        echo "</div>";
    }
} else {
    echo "<p class='chapter-text'>No hay participantes registrados para este capitulo.</p>";
}
echo "</section>";

echo "<section class='chapter-block'>";
echo "<h3 class='chapter-title'>Eventos relacionados</h3>";

$eventsRows = hg_chapters_fetch_chapter_events($link, $chapter_numberId, 100) ?? [];
if (!empty($eventsRows)) {
    echo "<div class='chapter-events-grid'>";
    foreach ($eventsRows as $erow) {
        $eventId = (int)($erow['id'] ?? 0);
        $eventTitle = trim((string)($erow['title'] ?? 'Evento'));
        if ($eventTitle === '') $eventTitle = 'Evento';

        $eventSlug = trim((string)($erow['pretty_id'] ?? ''));
        if ($eventSlug === '') $eventSlug = (string)$eventId;
        $eventHref = '/timeline/event/' . rawurlencode($eventSlug);

        $eventDateRaw = trim((string)($erow['event_date'] ?? ''));
        $eventDateFmt = '-';
        if ($eventDateRaw !== '' && $eventDateRaw !== '0000-00-00') {
            $tsEvent = strtotime($eventDateRaw);
            if ($tsEvent !== false) $eventDateFmt = date('d-m-Y', $tsEvent);
            else $eventDateFmt = $eventDateRaw;
        }

        echo "<a class='chapter-event-item hg-tooltip' href='" . htmlspecialchars($eventHref) . "' target='_blank' data-tip='event' data-id='" . $eventId . "'>";
        echo "  <span class='chapter-event-title'>" . htmlspecialchars($eventTitle) . "</span>";
        echo "  <span class='chapter-event-date'>" . htmlspecialchars($eventDateFmt) . "</span>";
        echo "</a>";
    }
    echo "</div>";
} else {
    echo "<p class='chapter-text'>No hay eventos vinculados a este capitulo.</p>";
}

echo "</section>";

echo "<section class='chapter-block'>";
echo "<h3 class='chapter-title'>Resumen</h3>";

if ($goodFecha !== '') {
    echo "<ul class='chapter-dates'>";
    echo "<li><b>Fecha de juego:</b> " . htmlspecialchars($goodFecha) . "</li>";
    echo "</ul>";
}

echo "<div class='chapter-text'>";
echo (!empty($sinoCapi)) ? $sinoCapi : "<p>{$noSinoCapi}</p>";
echo "</div>";
echo "</section>";
echo "</div>";

include("app/partials/snippet_bso_card.php");
mostrarTarjetaBSO($link, 'episodio', $chapter_numberId);

$prevLink = '';
$nextLink = '';
$prevHrefKey = '';
$nextHrefKey = '';
$prevCap = $numeCapi - 1;
$nextCap = $numeCapi + 1;
$chapterNumbersNavegacion = hg_chapters_fetch_neighbor_chapters($link, $tempSeasonId, $numeCapi) ?? [];

if (isset($chapterNumbersNavegacion[$prevCap])) {
    $prevId = (int)$chapterNumbersNavegacion[$prevCap]['id'];
    $prevName = (string)$chapterNumbersNavegacion[$prevCap]['name'];
    $prevHref = pretty_url($link, 'dim_chapters', '/chapters', $prevId);
    $prevHrefKey = $prevHref;
    $prevLink = "<a class='chapter-nav-link prev' href='" . htmlspecialchars($prevHref) . "'>&laquo; " . htmlspecialchars($prevName) . "</a>";
}

if (isset($chapterNumbersNavegacion[$nextCap])) {
    $nextId = (int)$chapterNumbersNavegacion[$nextCap]['id'];
    $nextName = (string)$chapterNumbersNavegacion[$nextCap]['name'];
    $nextHref = pretty_url($link, 'dim_chapters', '/chapters', $nextId);
    $nextHrefKey = $nextHref;
    $nextLink = "<a class='chapter-nav-link next' href='" . htmlspecialchars($nextHref) . "'>" . htmlspecialchars($nextName) . " &raquo;</a>";
}

$prevSeasonBoundaryLink = '';
$nextSeasonBoundaryLink = '';
if ($seasonKind === 'temporada') {
    $seasonBounds = hg_chapters_fetch_chapter_bounds($link, $tempSeasonId) ?? ['min_ch' => 0, 'max_ch' => 0];
    $minChapter = (int)($seasonBounds['min_ch'] ?? 0);
    $maxChapter = (int)($seasonBounds['max_ch'] ?? 0);

    if ($numeCapi === $minChapter) {
        $rowPrevSeason = hg_chapters_fetch_neighbor_season($link, $numbTemporada, 'prev');
        if ($rowPrevSeason) {
            $prevNum = (int)($rowPrevSeason['season_number'] ?? 0);
            $prevHref = pretty_url($link, 'dim_seasons', '/seasons', (int)$rowPrevSeason['id']);
            $prevSeasonBoundaryLink = "<a class='chapter-nav-link prev' href='" . htmlspecialchars($prevHref) . "'>&laquo; " . $prevNum . "ª Temporada</a>";
        }
    }

    if ($numeCapi === $maxChapter) {
        $rowNextSeason = hg_chapters_fetch_neighbor_season($link, $numbTemporada, 'next');
        if ($rowNextSeason) {
            $nextNum = (int)($rowNextSeason['season_number'] ?? 0);
            $nextHref = pretty_url($link, 'dim_seasons', '/seasons', (int)$rowNextSeason['id']);
            $nextSeasonBoundaryLink = "<a class='chapter-nav-link next' href='" . htmlspecialchars($nextHref) . "'>" . $nextNum . "ª Temporada &raquo;</a>";
        }
    }
}

$leftNav = ($prevLink !== '') ? $prevLink : (($prevSeasonBoundaryLink !== '') ? $prevSeasonBoundaryLink : "<div class='nav-empty'></div>");
$rightNav = ($nextLink !== '') ? $nextLink : (($nextSeasonBoundaryLink !== '') ? $nextSeasonBoundaryLink : "<div class='nav-empty'></div>");

echo "<div class='chapter-nav'>";
echo $leftNav;
echo $rightNav;
echo "</div>";

$prevHrefJs = json_encode((string)$prevHrefKey, JSON_UNESCAPED_UNICODE);
$nextHrefJs = json_encode((string)$nextHrefKey, JSON_UNESCAPED_UNICODE);
echo "<script>
    (function() {
        var prevHref = {$prevHrefJs};
        var nextHref = {$nextHrefJs};
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
</script>";

echo "</div>";
?>
