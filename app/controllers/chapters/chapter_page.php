<?php
if (!$link) {
    die("Error de conexion a la base de datos: " . mysqli_connect_error());
}
include_once(__DIR__ . '/../../helpers/character_avatar.php');

if (!function_exists('hg_ch_table_exists')) {
    function hg_ch_table_exists(mysqli $link, string $table): bool
    {
        $table = str_replace('`', '', $table);
        $rs = $link->query("SHOW TABLES LIKE '" . $link->real_escape_string($table) . "'");
        if (!$rs) return false;
        $ok = ($rs->num_rows > 0);
        $rs->close();
        return $ok;
    }
}

if (!function_exists('hg_ch_col_exists')) {
    function hg_ch_col_exists(mysqli $link, string $table, string $column): bool
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

        $cache[$key] = $ok;
        return $ok;
    }
}

$chapter_numberRaw = $_GET['t'] ?? '';
$chapter_numberId = resolve_pretty_id($link, 'dim_chapters', (string)$chapter_numberRaw) ?? 0;

$Query = "SELECT * FROM dim_chapters WHERE id = ? LIMIT 1";
$stmt = mysqli_prepare($link, $Query);
if ($chapter_numberId > 0 && $stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $chapter_numberId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result && mysqli_num_rows($result) > 0) {
        $ResultQuery = mysqli_fetch_assoc($result);

        $nameCapi = (string)$ResultQuery['name'];
        $sinoCapi = (string)$ResultQuery['synopsis'];
        $noSinoCapi = "Este capitulo no dispone de informacion, disculpa las molestias.";
        $tempSeasonId = (int)($ResultQuery['season_id'] ?? 0);
        $numeCapi = (int)$ResultQuery['chapter_number'];
        $dateCapi = (string)$ResultQuery['played_date'];

        $tempQuery = "SELECT id, name, season_number, season_kind AS season_value FROM dim_seasons WHERE id = ? LIMIT 1";
        $stmtTemp = mysqli_prepare($link, $tempQuery);

        $idTemporada = 0;
        $nameTemporada = 'Temporada';
        $numbTemporada = 0;
        $seasonKind = 'temporada';

        if ($stmtTemp) {
            mysqli_stmt_bind_param($stmtTemp, 'i', $tempSeasonId);
            mysqli_stmt_execute($stmtTemp);
            $resultTemp = mysqli_stmt_get_result($stmtTemp);
            $resultDataTemp = $resultTemp ? mysqli_fetch_assoc($resultTemp) : null;

            if ($resultDataTemp) {
                $idTemporada = (int)$resultDataTemp['id'];
                $nameTemporada = (string)$resultDataTemp['name'];
                $numbTemporada = (int)$resultDataTemp['season_number'];
                $seasonKind = trim((string)($resultDataTemp['season_value'] ?? 'temporada'));
                if ($seasonKind === '') $seasonKind = 'temporada';
            }
        }

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
            null,
            'article'
        );

        include("app/partials/main_nav_bar.php");
        echo '<link rel="stylesheet" href="/assets/css/hg-chapters.css">';
        ?>

<?php
        echo "<div class='chapter-shell'>";
        echo "<div class='chapter-hero'>";
        echo "<h2>" . htmlspecialchars($nameCapi) . "</h2>";
        echo "<span class='chapter-code'>Capítulo " . htmlspecialchars($numeracionOK) . "</span>";
        echo "</div>";

        echo "<div class='chapter-grid'>";

        echo "<section class='chapter-block'>";
        echo "<h3 class='chapter-title'>Participantes</h3>";

        $protaQuery = "
            SELECT p.id, p.name, p.image_url, p.gender
            FROM bridge_chapters_characters acp
            INNER JOIN fact_characters p ON acp.character_id = p.id
            WHERE acp.chapter_id = ?
            ORDER BY p.name ASC
        ";

        $stmtProta = mysqli_prepare($link, $protaQuery);
        if ($stmtProta) {
            mysqli_stmt_bind_param($stmtProta, 'i', $chapter_numberId);
            mysqli_stmt_execute($stmtProta);
            $resultProta = mysqli_stmt_get_result($stmtProta);

            if ($resultProta && mysqli_num_rows($resultProta) > 0) {
                echo "<div class='participants-grid'>";
                while ($pj = mysqli_fetch_assoc($resultProta)) {
                    $idPJSelect = (int)$pj['id'];
                    $nombre = (string)$pj['name'];
                    $img = hg_character_avatar_url((string)($pj['image_url'] ?? ''), (string)($pj['gender'] ?? ''));
                    $hrefChar = pretty_url($link, 'fact_characters', '/characters', $idPJSelect);

                    echo "<a class='participant-card hg-tooltip' href='" . htmlspecialchars($hrefChar) . "' target='_blank' data-tip='character' data-id='" . $idPJSelect . "'>";
                    echo "<img src='" . htmlspecialchars($img) . "' alt='" . htmlspecialchars($nombre) . "'>";
                    echo "<span>" . htmlspecialchars($nombre) . "</span>";
                    echo "</a>";
                }
                echo "</div>";
            } else {
                echo "<p class='chapter-text'>No hay participantes registrados para este capitulo.</p>";
            }

            if ($resultProta) {
                mysqli_free_result($resultProta);
            }
            mysqli_stmt_close($stmtProta);
        }
        echo "</section>";

        echo "<section class='chapter-block'>";
        echo "<h3 class='chapter-title'>Eventos relacionados</h3>";

        $eventsRows = [];
        $hasBridgeEvents = hg_ch_table_exists($link, 'bridge_timeline_events_chapters');
        $hasTimelineEvents = hg_ch_table_exists($link, 'fact_timeline_events');
        $hasEventTypes = hg_ch_table_exists($link, 'dim_timeline_events_types');

        if ($hasBridgeEvents && $hasTimelineEvents) {
            $eventOrder = [];
            if (hg_ch_col_exists($link, 'bridge_timeline_events_chapters', 'sort_order')) $eventOrder[] = 'b.sort_order ASC';
            $eventOrder[] = "CASE WHEN e.event_date = '0000-00-00' OR e.event_date IS NULL THEN 1 ELSE 0 END ASC";
            $eventOrder[] = 'e.event_date ASC';
            $eventOrder[] = 'e.id ASC';
            $eventOrderSql = implode(', ', $eventOrder);

            $prettyExpr = hg_ch_col_exists($link, 'fact_timeline_events', 'pretty_id') ? 'e.pretty_id' : 'NULL';
            $joinType = $hasEventTypes ? 'LEFT JOIN dim_timeline_events_types t ON t.id = e.event_type_id' : '';
            $typeExpr = $hasEventTypes ? "COALESCE(t.name, 'Evento')" : "'Evento'";

            $eventsQuery = "
                SELECT
                    e.id,
                    {$prettyExpr} AS pretty_id,
                    e.title,
                    e.event_date,
                    {$typeExpr} AS type_name
                FROM bridge_timeline_events_chapters b
                INNER JOIN fact_timeline_events e ON e.id = b.event_id
                {$joinType}
                WHERE b.chapter_id = ?
                ORDER BY {$eventOrderSql}
            ";

            if ($stmtEvents = mysqli_prepare($link, $eventsQuery)) {
                mysqli_stmt_bind_param($stmtEvents, 'i', $chapter_numberId);
                mysqli_stmt_execute($stmtEvents);
                $resultEvents = mysqli_stmt_get_result($stmtEvents);
                if ($resultEvents) {
                    while ($erow = mysqli_fetch_assoc($resultEvents)) {
                        $eventsRows[] = $erow;
                    }
                    mysqli_free_result($resultEvents);
                }
                mysqli_stmt_close($stmtEvents);
            }
        }

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
            if ($goodFecha !== '') {
                echo "<li><b>Fecha de juego:</b> " . htmlspecialchars($goodFecha) . "</li>";
            }
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

        $navQuery = "
            SELECT id, chapter_number, name
            FROM dim_chapters
            WHERE season_id = ? AND chapter_number IN (?, ?)
            ORDER BY chapter_number ASC
        ";

        $stmtNav = mysqli_prepare($link, $navQuery);
        if ($stmtNav) {
            $prevCap = $numeCapi - 1;
            $nextCap = $numeCapi + 1;
            mysqli_stmt_bind_param($stmtNav, 'iii', $tempSeasonId, $prevCap, $nextCap);
            mysqli_stmt_execute($stmtNav);
            $resultNav = mysqli_stmt_get_result($stmtNav);

            $chapterNumbersNavegacion = [];
            while ($fila = mysqli_fetch_assoc($resultNav)) {
                $chapterNumbersNavegacion[(int)$fila['chapter_number']] = $fila;
            }

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

            if ($resultNav) {
                mysqli_free_result($resultNav);
            }
            mysqli_stmt_close($stmtNav);
        }

        $prevSeasonBoundaryLink = '';
        $nextSeasonBoundaryLink = '';
        if ($seasonKind === 'temporada') {
            $seasonBoundsStmt = mysqli_prepare($link, "SELECT MIN(chapter_number) AS min_ch, MAX(chapter_number) AS max_ch FROM dim_chapters WHERE season_id = ?");
            if ($seasonBoundsStmt) {
                mysqli_stmt_bind_param($seasonBoundsStmt, 'i', $tempSeasonId);
                mysqli_stmt_execute($seasonBoundsStmt);
                $seasonBoundsRes = mysqli_stmt_get_result($seasonBoundsStmt);
                $seasonBounds = $seasonBoundsRes ? mysqli_fetch_assoc($seasonBoundsRes) : null;
                $minChapter = isset($seasonBounds['min_ch']) ? (int)$seasonBounds['min_ch'] : 0;
                $maxChapter = isset($seasonBounds['max_ch']) ? (int)$seasonBounds['max_ch'] : 0;
                if ($seasonBoundsRes) {
                    mysqli_free_result($seasonBoundsRes);
                }
                mysqli_stmt_close($seasonBoundsStmt);

                if ($numeCapi === $minChapter) {
                    $stmtPrevSeason = mysqli_prepare($link, "SELECT id, season_number FROM dim_seasons WHERE season_kind = 'temporada' AND season_number < ? ORDER BY season_number DESC LIMIT 1");
                    if ($stmtPrevSeason) {
                        mysqli_stmt_bind_param($stmtPrevSeason, 'i', $numbTemporada);
                        mysqli_stmt_execute($stmtPrevSeason);
                        $resPrevSeason = mysqli_stmt_get_result($stmtPrevSeason);
                        if ($rowPrevSeason = mysqli_fetch_assoc($resPrevSeason)) {
                            $prevNum = (int)$rowPrevSeason['season_number'];
                            $prevHref = pretty_url($link, 'dim_seasons', '/seasons', (int)$rowPrevSeason['id']);
                            $prevSeasonBoundaryLink = "<a class='chapter-nav-link prev' href='" . htmlspecialchars($prevHref) . "'>&laquo; " . $prevNum . "ª Temporada</a>";
                        }
                        if ($resPrevSeason) {
                            mysqli_free_result($resPrevSeason);
                        }
                        mysqli_stmt_close($stmtPrevSeason);
                    }
                }

                if ($numeCapi === $maxChapter) {
                    $stmtNextSeason = mysqli_prepare($link, "SELECT id, season_number FROM dim_seasons WHERE season_kind = 'temporada' AND season_number > ? ORDER BY season_number ASC LIMIT 1");
                    if ($stmtNextSeason) {
                        mysqli_stmt_bind_param($stmtNextSeason, 'i', $numbTemporada);
                        mysqli_stmt_execute($stmtNextSeason);
                        $resNextSeason = mysqli_stmt_get_result($stmtNextSeason);
                        if ($rowNextSeason = mysqli_fetch_assoc($resNextSeason)) {
                            $nextNum = (int)$rowNextSeason['season_number'];
                            $nextHref = pretty_url($link, 'dim_seasons', '/seasons', (int)$rowNextSeason['id']);
                            $nextSeasonBoundaryLink = "<a class='chapter-nav-link next' href='" . htmlspecialchars($nextHref) . "'>" . $nextNum . "ª Temporada &raquo;</a>";
                        }
                        if ($resNextSeason) {
                            mysqli_free_result($resNextSeason);
                        }
                        mysqli_stmt_close($stmtNextSeason);
                    }
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

        if ($result) {
            mysqli_free_result($result);
        }
        if (isset($resultTemp) && $resultTemp) {
            mysqli_free_result($resultTemp);
        }
        mysqli_stmt_close($stmt);
        if (isset($stmtTemp) && $stmtTemp) {
            mysqli_stmt_close($stmtTemp);
        }
    } else {
        echo "No se encontraron resultados para la busqueda.";
    }
} elseif ($chapter_numberId <= 0) {
    echo "No se encontraron resultados para la busqueda.";
} else {
    echo "Error al preparar la consulta: " . mysqli_error($link);
}
?>

