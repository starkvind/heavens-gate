<?php
if (!$link) {
    die("Error de conexion a la base de datos: " . mysqli_connect_error());
}
include_once(__DIR__ . '/../../helpers/character_avatar.php');

$chapter_numberRaw = $_GET['t'] ?? '';
$chapter_numberId = resolve_pretty_id($link, 'dim_chapters', (string)$chapter_numberRaw) ?? 0;

$Query = "SELECT * FROM dim_chapters WHERE id = ? LIMIT 1";
$stmt = mysqli_prepare($link, $Query);
if ($chapter_numberId > 0 && $stmt) {
    mysqli_stmt_bind_param($stmt, 's', $chapter_numberId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result && mysqli_num_rows($result) > 0) {
        $ResultQuery = mysqli_fetch_assoc($result);

        $nameCapi = (string)$ResultQuery['name'];
        $sinoCapi = (string)$ResultQuery['synopsis'];
        $noSinoCapi = "Este capitulo no dispone de informacion, disculpa las molestias.";
        $tempCapi = (int)$ResultQuery['season_number'];
        $numeCapi = (int)$ResultQuery['chapter_number'];
        $dateCapi = (string)$ResultQuery['played_date'];
        $dateIngame = (string)$ResultQuery['in_game_date'];

        $tempQuery = "SELECT id, name, season_number, season AS season_flag FROM dim_seasons WHERE season_number = ? LIMIT 1";
        $stmtTemp = mysqli_prepare($link, $tempQuery);

        $idTemporada = 0;
        $nameTemporada = 'Temporada';
        $numbTemporada = $tempCapi;
        $seasonFlag = 0;

        if ($stmtTemp) {
            mysqli_stmt_bind_param($stmtTemp, 's', $tempCapi);
            mysqli_stmt_execute($stmtTemp);
            $resultTemp = mysqli_stmt_get_result($stmtTemp);
            $resultDataTemp = $resultTemp ? mysqli_fetch_assoc($resultTemp) : null;

            if ($resultDataTemp) {
                $idTemporada = (int)$resultDataTemp['id'];
                $nameTemporada = (string)$resultDataTemp['name'];
                $numbTemporada = (int)$resultDataTemp['season_number'];
                $seasonFlag = (int)$resultDataTemp['season_flag'];
            }
        }

        $checkNumCapi = ($numeCapi < 10) ? '0' : '';
        $goodNumTemp = ($numbTemporada >= 100) ? '' : (string)$numbTemporada;
        $numeracionOK = ($numbTemporada < 99)
            ? "{$goodNumTemp}x{$checkNumCapi}{$numeCapi}"
            : "{$checkNumCapi}{$numeCapi}";

        $goodFecha = ($dateCapi && $dateCapi !== '0000-00-00') ? date('d-m-Y', strtotime($dateCapi)) : '';
        $goodIngameFecha = ($dateIngame && $dateIngame !== '0000-00-00') ? date('d-m-Y', strtotime($dateIngame)) : '';

        $pageSect = "{$nameTemporada} {$numeracionOK}";
        $pageTitle2 = $nameCapi;
        setMetaFromPage($nameCapi . " | Capítulos | Heaven's Gate", meta_excerpt(!empty($sinoCapi) ? $sinoCapi : $noSinoCapi), null, 'article');

        include("app/partials/main_nav_bar.php");
        ?>

<style>
	.chapter-shell {
		--ch-bg: #05014E;
		--ch-panel: linear-gradient(180deg, #000066 0%, #05014E 100%);
		--ch-line: #000099;
		--ch-ink: #ffffff;
		--ch-muted: #b9d6ff;
		--ch-accent: #33CCCC;
		--ch-glow: rgba(51, 204, 204, .16);
		max-width: 980px;
		margin: 0 auto 2em;
		padding: 0 12px 20px;
		color: var(--ch-ink);
		font-family: "Trebuchet MS", Verdana, sans-serif;
	}

	.chapter-hero {
		display: grid;
		grid-template-columns: minmax(0, 1fr) auto;
		align-items: center;
		gap: 12px;
		padding: 13px 16px;
		border: 1px solid var(--ch-line);
		background: var(--ch-panel);
		border-radius: 10px;
		box-shadow: 0 10px 28px rgba(0,0,0,.24);
		margin-bottom: 14px;
	}

	.chapter-hero h2 {
		margin: 0;
		min-width: 0;
		font-size: clamp(1rem, 1.5vw, 1.45rem);
		line-height: 1.18;
		text-align: left;
		overflow-wrap: anywhere;
		word-break: normal;
		hyphens: auto;
		text-wrap: balance;
	}

	.chapter-code {
		display: inline-flex;
		align-items: center;
		padding: 6px 11px;
		border: 1px solid #000099;
		border-radius: 999px;
		background: rgba(0, 0, 102, .8);
		color: var(--ch-accent);
		font-size: .8rem;
		font-weight: bold;
		flex-shrink: 0;
	}

	.chapter-grid {
		display: flex;
		flex-direction: column;
		gap: 10px;
		margin-bottom: 14px;
	}

	.chapter-block {
		border: 1px solid var(--ch-line);
		background: var(--ch-bg);
		border-radius: 10px;
		padding: 12px 14px;
		box-shadow: inset 0 0 0 1px rgba(255,255,255,.03);
	}

	.chapter-title {
		font-size: .88rem;
		text-transform: uppercase;
		letter-spacing: .09em;
		color: var(--ch-accent);
		margin: 0 0 10px;
		font-weight: bold;
	}

	.chapter-dates {
		margin: 0 0 10px;
		padding-left: 16px;
		color: var(--ch-muted);
	}

	.chapter-dates li {
		margin-bottom: 4px;
	}

	.chapter-text {
		line-height: 1.58;
	}

	.participants-grid {
		display: grid;
		grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
		gap: 10px;
	}

	.participant-card {
		display: flex;
		flex-direction: column;
		align-items: center;
		padding: 10px 8px;
		text-decoration: none;
		background: rgba(0, 0, 85, .75);
		border: 1px solid rgba(0, 0, 153, .85);
		border-radius: 10px;
		color: var(--ch-ink);
		transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease;
	}

	.participant-card:hover {
		transform: translateY(-2px);
		border-color: var(--ch-accent);
		box-shadow: 0 0 0 3px var(--ch-glow);
	}

	.participant-card img {
		width: 62px;
		height: 62px;
		object-fit: cover;
		border-radius: 50%;
		border: 1px solid #000099;
		margin-bottom: 7px;
	}

	.participant-card span {
		font-size: .8rem;
		text-align: center;
		line-height: 1.3;
	}

	.chapter-shell .bioTextData {
		float: none;
		width: auto;
		padding: 0;
		margin: 0 0 14px 0;
	}

	.chapter-shell .bso-card {
		border: 1px solid #000099;
		background: rgba(5, 1, 78, .55);
	}

	.chapter-shell .bso-card legend {
		border: 1px solid #000099;
		background: #000066;
		color: #33CCCC;
		font-weight: bold;
		padding: 2px 6px;
	}

	.chapter-shell .video-wrapper {
		display: flex;
		justify-content: center;
		padding: 8px 6px;
	}

	.chapter-shell .video-wrapper iframe {
		width: min(100%, 640px);
		aspect-ratio: 16/9;
		height: auto;
		border: 1px solid #000099;
		background: #000033;
	}

	.chapter-shell .bso-card p {
		color: #fff;
		margin: 0 0 8px;
	}

	.chapter-nav {
		display: flex;
		justify-content: space-between;
		align-items: center;
		gap: 10px;
		margin-top: 14px;
	}

	.chapter-nav .nav-empty {
		width: 48%;
	}

	.chapter-nav-link {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		gap: 6px;
		width: 48%;
		padding: 9px 10px;
		border-radius: 9px;
		border: 1px solid rgba(0, 0, 153, .85);
		background: rgba(0, 0, 85, .75);
		color: var(--ch-ink);
		text-decoration: none;
		font-size: .85rem;
		transition: border-color .16s ease, background .16s ease;
	}

	.chapter-nav-link:hover {
		border-color: var(--ch-accent);
		background: rgba(0, 0, 102, .95);
	}

	.chapter-nav-link.next {
		text-align: right;
	}

	@media (max-width: 760px) {
		.chapter-hero {
			grid-template-columns: 1fr;
			align-items: flex-start;
		}

		.chapter-nav {
			flex-direction: column;
		}

		.chapter-nav-link,
		.chapter-nav .nav-empty {
			width: 100%;
		}
	}
</style>

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

                    echo "<a class='participant-card' href='" . htmlspecialchars($hrefChar) . "' title='" . htmlspecialchars($nombre) . "' target='_blank'>";
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
        echo "<h3 class='chapter-title'>Resumen</h3>";

        if (($goodFecha !== '') || ($goodIngameFecha !== '' && $goodIngameFecha !== '01-01-1970')) {
            echo "<ul class='chapter-dates'>";
            if ($goodIngameFecha !== '' && $goodIngameFecha !== '01-01-1970') {
                echo "<li><b>Fecha en ficción:</b> " . htmlspecialchars($goodIngameFecha) . "</li>";
            }
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
            WHERE season_number = ? AND chapter_number IN (?, ?)
            ORDER BY chapter_number ASC
        ";

        $stmtNav = mysqli_prepare($link, $navQuery);
        if ($stmtNav) {
            $prevCap = $numeCapi - 1;
            $nextCap = $numeCapi + 1;
            mysqli_stmt_bind_param($stmtNav, 'iii', $tempCapi, $prevCap, $nextCap);
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
        if ($seasonFlag === 0 && $numbTemporada < 101) {
            $seasonBoundsStmt = mysqli_prepare($link, "SELECT MIN(chapter_number) AS min_ch, MAX(chapter_number) AS max_ch FROM dim_chapters WHERE season_number = ?");
            if ($seasonBoundsStmt) {
                mysqli_stmt_bind_param($seasonBoundsStmt, 'i', $numbTemporada);
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
                    $stmtPrevSeason = mysqli_prepare($link, "SELECT id, season_number FROM dim_seasons WHERE season = 0 AND season_number < 101 AND season_number < ? ORDER BY season_number DESC LIMIT 1");
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
                    $stmtNextSeason = mysqli_prepare($link, "SELECT id, season_number FROM dim_seasons WHERE season = 0 AND season_number < 101 AND season_number > ? ORDER BY season_number ASC LIMIT 1");
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
