<?php setMetaFromPage("Temporadas | Heaven's Gate", "Consulta temporadas y capitulos de la campana.", null, 'website'); ?>

<style>
	.archive-shell {
		--arc-bg: #05014E;
		--arc-panel: linear-gradient(180deg, #000066 0%, #05014E 100%);
		--arc-line: #000099;
		--arc-ink: #ffffff;
		--arc-muted: #b9d6ff;
		--arc-accent: #33CCCC;
		--arc-glow: rgba(51, 204, 204, .16);
		max-width: 1020px;
		margin: 0 auto 2em;
		padding: 0 12px 20px;
		color: var(--arc-ink);
		font-family: "Trebuchet MS", Verdana, sans-serif;
	}

	.archive-hero {
		display: flex;
		justify-content: space-between;
		align-items: flex-end;
		gap: 12px;
		padding: 12px 16px;
		border: 1px solid var(--arc-line);
		background: var(--arc-panel);
		border-radius: 10px;
		box-shadow: 0 10px 30px rgba(0, 0, 0, .22);
		margin-bottom: 14px;
	}

	.archive-hero h2 {
		margin: 0;
		font-size: clamp(1.2rem, 2.2vw, 1.7rem);
		letter-spacing: .02em;
	}

	.archive-chip {
		display: inline-flex;
		align-items: center;
		gap: 6px;
		padding: 6px 10px;
		border: 1px solid #000099;
		border-radius: 999px;
		background: rgba(0, 0, 102, .8);
		color: var(--arc-accent);
		font-size: .8rem;
		font-weight: bold;
		white-space: nowrap;
	}

	.archive-block {
		border: 1px solid var(--arc-line);
		background: var(--arc-bg);
		border-radius: 10px;
		padding: 12px 14px;
		margin: 0 0 14px;
		box-shadow: inset 0 0 0 1px rgba(255,255,255,.03);
	}

	.archive-title {
		font-size: .88rem;
		text-transform: uppercase;
		letter-spacing: .09em;
		color: var(--arc-accent);
		margin: 0 0 10px;
		font-weight: bold;
	}

	.archive-text {
		color: var(--arc-ink);
		line-height: 1.55;
	}

	.prota-grid {
		display: grid;
		grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
		gap: 10px;
	}

	.prota-card {
		display: flex;
		flex-direction: column;
		align-items: center;
		text-decoration: none;
		background: rgba(0, 0, 85, .75);
		border: 1px solid rgba(0, 0, 153, .85);
		border-radius: 10px;
		padding: 10px 8px;
		color: var(--arc-ink);
		transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
	}

	.prota-card:hover {
		transform: translateY(-2px);
		border-color: var(--arc-accent);
		box-shadow: 0 0 0 3px var(--arc-glow);
		color: #fff;
	}

	.prota-card span {
		text-align: center;
		font-size: .8rem;
		line-height: 1.3;
	}

	.prota-card img.photochapter {
		width: 62px;
		height: 62px;
		border-radius: 50%;
		border: 1px solid #000099;
		object-fit: cover;
		margin-bottom: 7px;
	}

	.chapters-list {
		display: grid;
		grid-template-columns: 1fr;
		gap: 8px;
	}

	.chapters-item {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 10px;
		padding: 9px 10px;
		border: 1px solid rgba(0, 0, 153, .75);
		border-radius: 8px;
		text-decoration: none;
		background: rgba(0, 0, 85, .72);
		color: var(--arc-ink);
		transition: border-color .16s ease, background .16s ease;
	}

	.chapters-item:hover {
		border-color: var(--arc-accent);
		background: rgba(12, 25, 67, .9);
	}

	.chapters-code {
		font-size: .75rem;
		letter-spacing: .05em;
		color: var(--arc-muted);
		white-space: nowrap;
	}

	.chapters-name {
		font-size: .9rem;
		font-weight: bold;
		text-align: left;
		flex: 1;
	}

	.db-text-pad {
		padding: 0 2px;
	}

	.archive-shell .bioTextData {
		float: none;
		width: auto;
		padding: 0;
		margin: 0 0 14px 0;
	}

	.archive-shell .bso-card {
		border: 1px solid #000099;
		background: rgba(5, 1, 78, .55);
	}

	.archive-shell .bso-card legend {
		border: 1px solid #000099;
		background: #000066;
		color: #33CCCC;
		font-weight: bold;
		padding: 2px 6px;
	}

	.archive-shell .video-wrapper {
		display: flex;
		justify-content: center;
		padding: 8px 6px;
	}

	.archive-shell .video-wrapper iframe {
		width: min(100%, 640px);
		aspect-ratio: 16/9;
		height: auto;
		border: 1px solid #000099;
		background: #000033;
	}

	.archive-shell .bso-card p {
		color: #fff;
		margin: 0 0 8px;
	}

	.archive-season-nav {
		display: flex;
		justify-content: space-between;
		align-items: center;
		gap: 10px;
		margin-top: 14px;
	}

	.archive-season-nav .nav-empty {
		width: 48%;
	}

	.archive-season-link {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		gap: 6px;
		width: 48%;
		padding: 9px 10px;
		border-radius: 9px;
		border: 1px solid rgba(0, 0, 153, .85);
		background: rgba(0, 0, 85, .75);
		color: var(--arc-ink);
		text-decoration: none;
		font-size: .85rem;
		transition: border-color .16s ease, background .16s ease;
	}

	.archive-season-link:hover {
		border-color: var(--arc-accent);
		background: rgba(0, 0, 102, .95);
	}

	.archive-season-link.next {
		text-align: right;
	}

	@media (max-width: 680px) {
		.archive-hero {
			flex-direction: column;
			align-items: flex-start;
		}

		.archive-chip {
			margin-top: 4px;
		}

		.archive-season-nav {
			flex-direction: column;
		}

		.archive-season-link,
		.archive-season-nav .nav-empty {
			width: 100%;
		}
	}
</style>

<?php
if (!$link) {
    die("Error de conexion a la base de datos: " . mysqli_connect_error());
}

$temporadaRaw = $_GET['t'] ?? '';
$temporadaId = resolve_pretty_id($link, 'dim_seasons', (string)$temporadaRaw) ?? 0;

$consulta = "SELECT * FROM dim_seasons WHERE id = ? LIMIT 1";
$stmt = mysqli_prepare($link, $consulta);
if ($temporadaId > 0 && $stmt) {
    mysqli_stmt_bind_param($stmt, 's', $temporadaId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result && mysqli_num_rows($result) > 0) {
        $ResultQuery = mysqli_fetch_assoc($result);

        $nameTemp = (string)$ResultQuery['name'];
        $numberTemp = (int)$ResultQuery['season_number'];
        $sinopsis = (string)$ResultQuery['description'];

        $titleSinop = "Sinopsis";
        $titleProta = "Protagonistas";
        $titleChapt = "Capítulos";

        $esTempOno = (int)$ResultQuery['season'];
        $pageSect = ($esTempOno === 0) ? "Temporadas" : "Historia personal";
        $pageTitle2 = $nameTemp;

        include("app/partials/main_nav_bar.php");

        echo "<div class='archive-shell'>";
        echo "<div class='archive-hero'>";
        echo "<h2>" . htmlspecialchars($nameTemp) . "</h2>";
        echo "<span class='archive-chip'>Temporada " . htmlspecialchars((string)$numberTemp) . "</span>";
        echo "</div>";

        echo "<div class='bioBody'>";

        include("app/partials/chapt/chapt_archivo_barchart_prepare.php");

        echo "<section class='archive-block'>";
        echo "<h3 class='archive-title'>{$titleSinop}</h3>";
        echo "<div class='archive-text db-text-pad'>{$sinopsis}</div>";
        echo "</section>";

        if (isset($player_ids) && is_array($player_ids) && count($player_ids) > 0) {
            $numProtagonistas = count($player_ids);
            if ($numProtagonistas > 0) {
                echo "<section class='archive-block'>";
                echo "<h3 class='archive-title'>{$titleProta}</h3>";
                echo "<div class='prota-grid'>";

                $ids = implode(',', array_map('intval', $player_ids));
                $query = "
                    SELECT p.id, p.name, p.image_url
                    FROM fact_characters p
                    WHERE p.id IN ($ids)
                      AND p.player_id > 0
                    ORDER BY p.name ASC
                ";
                $resultProtas = $link->query($query);

                $maxJugados = (!empty($jugados) && is_array($jugados)) ? max($jugados) : 0;
                $umbral = ($maxJugados > 0) ? ($maxJugados / 2) : 0;

                if ($resultProtas) {
                    while ($row = $resultProtas->fetch_assoc()) {
                        $checkId = (int)$row['id'];
                        $index = array_search($checkId, $player_ids);
                        $participaciones = ($index !== false && isset($jugados[$index])) ? (int)$jugados[$index] : 0;

                        if ($participaciones >= $umbral) {
                            $hrefProta = pretty_url($link, 'fact_characters', '/characters', $checkId);
                            echo "<a href='" . htmlspecialchars($hrefProta) . "' class='prota-card' target='_blank' title='" . htmlspecialchars((string)$row['name']) . "'>";
                            echo "<img src='" . htmlspecialchars((string)$row['image_url']) . "' class='photochapter' alt='" . htmlspecialchars((string)$row['name']) . "'>";
                            echo "<span>" . htmlspecialchars((string)$row['name']) . "</span>";
                            echo "</a>";
                        }
                    }
                    mysqli_free_result($resultProtas);
                }

                echo "</div>";
                echo "</section>";
            }
        }

        echo "<section class='archive-block'>";
        echo "<h3 class='archive-title'>{$titleChapt}</h3>";
        echo "<div class='chapters-list'>";

        $consultaChapt = "SELECT id, name, chapter_number FROM dim_chapters WHERE season_number = ? ORDER BY chapter_number";
        $stmtChapt = mysqli_prepare($link, $consultaChapt);
        if ($stmtChapt) {
            mysqli_stmt_bind_param($stmtChapt, 's', $numberTemp);
            mysqli_stmt_execute($stmtChapt);
            $resultChapt = mysqli_stmt_get_result($stmtChapt);

            if ($resultChapt && mysqli_num_rows($resultChapt) > 0) {
                while ($ResultQueryChapt = mysqli_fetch_assoc($resultChapt)) {
                    $idEpi = (int)$ResultQueryChapt['id'];
                    $nameEpi = (string)$ResultQueryChapt['name'];
                    $capiEpi = (int)$ResultQueryChapt['chapter_number'];

                    if ($esTempOno === 0) {
                        $chapterCode = ($numberTemp < 100)
                            ? sprintf('%dx%02d', $numberTemp, $capiEpi)
                            : sprintf('%02d', $capiEpi);
                    } else {
                        $chapterCode = sprintf('%02d', $capiEpi);
                    }

                    $hrefChap = pretty_url($link, 'dim_chapters', '/chapters', $idEpi);
                    echo "<a class='chapters-item' href='" . htmlspecialchars($hrefChap) . "' title='Capitulo {$chapterCode}'>";
                    echo "<span class='chapters-code'>{$chapterCode}</span>";
                    echo "<span class='chapters-name'>" . htmlspecialchars($nameEpi) . "</span>";
                    echo "<span class='chapters-code'>&rsaquo;</span>";
                    echo "</a>";
                }
            }

            if ($resultChapt) {
                mysqli_free_result($resultChapt);
            }
            mysqli_stmt_close($stmtChapt);
        }

        echo "</div>";
        echo "</section>";

        include("app/partials/snippet_bso_card.php");
        mostrarTarjetaBSO($link, 'temporada', $temporadaId);

        if (isset($player_ids)) {
            include("app/partials/chapt/chapt_archivo_barchart.php");
        }

        $prevSeasonLink = '';
        $nextSeasonLink = '';
        if ($esTempOno === 0 && $numberTemp < 101) {
            $stmtPrevSeason = mysqli_prepare($link, "SELECT id, season_number FROM dim_seasons WHERE season = 0 AND season_number < 101 AND season_number < ? ORDER BY season_number DESC LIMIT 1");
            if ($stmtPrevSeason) {
                mysqli_stmt_bind_param($stmtPrevSeason, 'i', $numberTemp);
                mysqli_stmt_execute($stmtPrevSeason);
                $resPrevSeason = mysqli_stmt_get_result($stmtPrevSeason);
                if ($rowPrevSeason = mysqli_fetch_assoc($resPrevSeason)) {
                    $prevNum = (int)$rowPrevSeason['season_number'];
                    $prevHref = pretty_url($link, 'dim_seasons', '/seasons', (int)$rowPrevSeason['id']);
                    $prevSeasonLink = "<a class='archive-season-link prev' href='" . htmlspecialchars($prevHref) . "'>&laquo; " . $prevNum . "ª Temporada</a>";
                }
                if ($resPrevSeason) {
                    mysqli_free_result($resPrevSeason);
                }
                mysqli_stmt_close($stmtPrevSeason);
            }

            $stmtNextSeason = mysqli_prepare($link, "SELECT id, season_number FROM dim_seasons WHERE season = 0 AND season_number < 101 AND season_number > ? ORDER BY season_number ASC LIMIT 1");
            if ($stmtNextSeason) {
                mysqli_stmt_bind_param($stmtNextSeason, 'i', $numberTemp);
                mysqli_stmt_execute($stmtNextSeason);
                $resNextSeason = mysqli_stmt_get_result($stmtNextSeason);
                if ($rowNextSeason = mysqli_fetch_assoc($resNextSeason)) {
                    $nextNum = (int)$rowNextSeason['season_number'];
                    $nextHref = pretty_url($link, 'dim_seasons', '/seasons', (int)$rowNextSeason['id']);
                    $nextSeasonLink = "<a class='archive-season-link next' href='" . htmlspecialchars($nextHref) . "'>" . $nextNum . "ª Temporada &raquo;</a>";
                }
                if ($resNextSeason) {
                    mysqli_free_result($resNextSeason);
                }
                mysqli_stmt_close($stmtNextSeason);
            }
        }

        if ($prevSeasonLink !== '' || $nextSeasonLink !== '') {
            echo "<div class='archive-season-nav'>";
            echo ($prevSeasonLink !== '') ? $prevSeasonLink : "<div class='nav-empty'></div>";
            echo ($nextSeasonLink !== '') ? $nextSeasonLink : "<div class='nav-empty'></div>";
            echo "</div>";
        }

        echo "</div>";
        echo "</div>";
    } else {
        echo "No se encontraron resultados para la busqueda.";
    }

    if ($result) {
        mysqli_free_result($result);
    }
    mysqli_stmt_close($stmt);
} elseif ($temporadaId <= 0) {
    echo "No se encontraron resultados para la busqueda.";
} else {
    echo "Error al preparar la consulta: " . mysqli_error($link);
}
?>
