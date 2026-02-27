<?php setMetaFromPage("Temporadas | Heaven's Gate", "Consulta temporadas y capitulos de la campana.", null, 'website'); ?>
<?php include_once(__DIR__ . '/../../helpers/character_avatar.php'); ?>
<link rel="stylesheet" href="/assets/css/hg-chapters.css">

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

        include("app/partials/chapters/season_barchart_prepare.php");

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
                    SELECT p.id, p.name, p.image_url, p.gender
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
                            echo "<img src='" . htmlspecialchars(hg_character_avatar_url((string)($row['image_url'] ?? ''), (string)($row['gender'] ?? ''))) . "' class='photochapter' alt='" . htmlspecialchars((string)$row['name']) . "'>";
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
            include("app/partials/chapters/season_barchart.php");
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
