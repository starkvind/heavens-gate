<?php setMetaFromPage("Temporadas | Heaven's Gate", "Consulta temporadas y capitulos de la campana.", null, 'website'); ?>
<?php include_once(__DIR__ . '/../../helpers/character_avatar.php'); ?>
<?php include_once(__DIR__ . '/../../helpers/runtime_response.php'); ?>
<link rel="stylesheet" href="/assets/css/hg-chapters.css">

<?php
if (!hg_runtime_require_db($link, 'season_archive', 'public', [
    'title' => 'Temporadas no disponibles',
    'message' => 'No se pudo conectar a la base de datos.',
    'include_nav' => true,
])) {
    return;
}

if (!function_exists('hg_sa_col_exists')) {
    function hg_sa_col_exists(mysqli $link, string $table, string $column): bool
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

$temporadaRaw = $_GET['t'] ?? '';
$temporadaId = resolve_pretty_id($link, 'dim_seasons', (string)$temporadaRaw) ?? 0;
if (trim((string)$temporadaRaw) === '') {
    include(__DIR__ . '/seasons_home.php');
    return;
}
$consulta = "SELECT * FROM dim_seasons WHERE id = ? LIMIT 1";
$stmt = mysqli_prepare($link, $consulta);
if ($temporadaId > 0 && $stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $temporadaId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result && mysqli_num_rows($result) > 0) {
        $ResultQuery = mysqli_fetch_assoc($result);

        $nameTemp = (string)$ResultQuery['name'];
        $numberTemp = (int)$ResultQuery['season_number'];
        $sinopsis = (string)$ResultQuery['description'];
        $seasonFinished = (int)($ResultQuery['finished'] ?? 0);
        $seasonChronicleId = hg_sa_col_exists($link, 'dim_seasons', 'chronicle_id') ? (int)($ResultQuery['chronicle_id'] ?? 0) : 0;
        $seasonChronicleName = '';
        $seasonChronicleHref = '';

        if ($seasonChronicleId > 0 && ($stmtChron = mysqli_prepare($link, "SELECT id, name FROM dim_chronicles WHERE id = ? LIMIT 1"))) {
            mysqli_stmt_bind_param($stmtChron, 'i', $seasonChronicleId);
            mysqli_stmt_execute($stmtChron);
            $resChron = mysqli_stmt_get_result($stmtChron);
            if ($rowChron = mysqli_fetch_assoc($resChron)) {
                $seasonChronicleName = (string)($rowChron['name'] ?? '');
                $seasonChronicleHref = pretty_url($link, 'dim_chronicles', '/chronicles', (int)$rowChron['id']);
            }
            if ($resChron) {
                mysqli_free_result($resChron);
            }
            mysqli_stmt_close($stmtChron);
        }

        $titleSinop = "Sinopsis";
        $titleProta = "Protagonistas";
        $titleChapt = "Capítulos";

        $seasonKind = trim((string)($ResultQuery['season_kind'] ?? 'temporada'));
        if ($seasonKind === '') $seasonKind = 'temporada';
        $titleSection = ($seasonKind === 'historia_personal') ? "Historias personales" : "Temporadas";
        $pageSect = $titleSection;
        $pageTitle2 = $nameTemp;
        setMetaFromPage(
            $nameTemp . " | " . $titleSection . " | Heaven's Gate",
            meta_excerpt($sinopsis),
            null,
            'article'
        );

        include("app/partials/main_nav_bar.php");

        echo "<div class='archive-shell'>";
        echo "<div class='archive-hero'>";
        echo "<div class='archive-hero-main'>";
        echo "<h2>" . htmlspecialchars($nameTemp) . "</h2>";
        echo "</div>";
        if ($seasonKind === 'temporada') {
            $archiveChip = "Temporada " . (string)$numberTemp;
        } elseif ($seasonKind === 'inciso') {
            $incisoNum = $numberTemp;
            if ($incisoNum >= 100 && $incisoNum < 200) $incisoNum -= 100;
            $archiveChip = "Inciso " . $incisoNum;
        } elseif ($seasonKind === 'historia_personal') {
            $archiveChip = "Historia personal";
        } else {
            $archiveChip = "Especial";
        }
        echo "<span class='archive-chip'>" . htmlspecialchars($archiveChip) . "</span>";
        echo "</div>";

        echo "<div class='bioBody'>";

        include("app/partials/chapters/season_barchart_prepare.php");

        echo "<section class='archive-block'>";
        echo "<h3 class='archive-title'>{$titleSinop}</h3>";
        echo "<div class='archive-text db-text-pad'>{$sinopsis}</div>";
        $archivePills = [];
        if ($seasonChronicleName !== '' && $seasonChronicleHref !== '') {
            $archivePills[] = "<a class='archive-pill archive-pill--chronicle' href='" . htmlspecialchars($seasonChronicleHref) . "'>Cr&oacute;nica: " . htmlspecialchars($seasonChronicleName) . "</a>";
        }
        if ($seasonFinished === 1) {
            $archivePills[] = "<span class='archive-pill archive-pill--done'>Finalizada</span>";
        } elseif ($seasonFinished === 2) {
            $archivePills[] = "<span class='archive-pill archive-pill--cancelled'>Cancelada</span>";
        } else {
            $archivePills[] = "<span class='archive-pill archive-pill--active'>En curso</span>";
        }
        if (!empty($archivePills)) {
            echo "<div class='archive-pills'>" . implode('', $archivePills) . "</div>";
        }
        echo "</section>";

        $player_ids = (isset($player_ids) && is_array($player_ids))
            ? array_values(array_unique(array_filter(array_map('intval', $player_ids))))
            : [];

        if (!empty($player_ids)) {
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
                    ORDER BY p.name ASC, p.id ASC
                ";
                $resultProtas = $link->query($query);

                if ($resultProtas) {
                    while ($row = $resultProtas->fetch_assoc()) {
                        $checkId = (int)$row['id'];
                        $hrefProta = pretty_url($link, 'fact_characters', '/characters', $checkId);
                        echo "<a href='" . htmlspecialchars($hrefProta) . "' class='prota-card hg-tooltip' target='_blank' data-tip='character' data-id='" . $checkId . "'>";
                        echo "<img src='" . htmlspecialchars(hg_character_avatar_url((string)($row['image_url'] ?? ''), (string)($row['gender'] ?? ''))) . "' class='photochapter' alt='" . htmlspecialchars((string)$row['name']) . "'>";
                        echo "<span>" . htmlspecialchars((string)$row['name']) . "</span>";
                        echo "</a>";
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

        $consultaChapt = "SELECT id, name, chapter_number FROM dim_chapters WHERE season_id = ? ORDER BY chapter_number";
        $stmtChapt = mysqli_prepare($link, $consultaChapt);
        if ($stmtChapt) {
            mysqli_stmt_bind_param($stmtChapt, 'i', $temporadaId);
            mysqli_stmt_execute($stmtChapt);
            $resultChapt = mysqli_stmt_get_result($stmtChapt);

            if ($resultChapt && mysqli_num_rows($resultChapt) > 0) {
                while ($ResultQueryChapt = mysqli_fetch_assoc($resultChapt)) {
                    $idEpi = (int)$ResultQueryChapt['id'];
                    $nameEpi = (string)$ResultQueryChapt['name'];
                    $capiEpi = (int)$ResultQueryChapt['chapter_number'];

                    if ($seasonKind === 'temporada') {
                        $chapterCode = sprintf('%dx%02d', $numberTemp, $capiEpi);
                    } else {
                        $chapterCode = sprintf('%02d', $capiEpi);
                    }

                    $hrefChap = pretty_url($link, 'dim_chapters', '/chapters', $idEpi);
                    echo "<a class='chapters-item' href='" . htmlspecialchars($hrefChap) . "' title='Capítulo {$chapterCode}'>";
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

        if (!empty($player_ids)) {
            include("app/partials/chapters/season_barchart.php");
        }

        $prevSeasonLink = '';
        $nextSeasonLink = '';
        if ($seasonKind === 'temporada') {
            $stmtPrevSeason = mysqli_prepare($link, "SELECT id, season_number FROM dim_seasons WHERE season_kind = 'temporada' AND season_number < ? ORDER BY season_number DESC LIMIT 1");
            if ($stmtPrevSeason) {
                mysqli_stmt_bind_param($stmtPrevSeason, 'i', $numberTemp);
                mysqli_stmt_execute($stmtPrevSeason);
                $resPrevSeason = mysqli_stmt_get_result($stmtPrevSeason);
                if ($rowPrevSeason = mysqli_fetch_assoc($resPrevSeason)) {
                    $prevNum = (int)$rowPrevSeason['season_number'];
                    $prevHref = pretty_url($link, 'dim_seasons', '/seasons', (int)$rowPrevSeason['id']);
                    $prevSeasonLink = "<a class='archive-season-link prev' href='" . htmlspecialchars($prevHref) . "'>&laquo; " . $prevNum . "a Temporada</a>";
                }
                if ($resPrevSeason) {
                    mysqli_free_result($resPrevSeason);
                }
                mysqli_stmt_close($stmtPrevSeason);
            }

            $stmtNextSeason = mysqli_prepare($link, "SELECT id, season_number FROM dim_seasons WHERE season_kind = 'temporada' AND season_number > ? ORDER BY season_number ASC LIMIT 1");
            if ($stmtNextSeason) {
                mysqli_stmt_bind_param($stmtNextSeason, 'i', $numberTemp);
                mysqli_stmt_execute($stmtNextSeason);
                $resNextSeason = mysqli_stmt_get_result($stmtNextSeason);
                if ($rowNextSeason = mysqli_fetch_assoc($resNextSeason)) {
                    $nextNum = (int)$rowNextSeason['season_number'];
                    $nextHref = pretty_url($link, 'dim_seasons', '/seasons', (int)$rowNextSeason['id']);
                    $nextSeasonLink = "<a class='archive-season-link next' href='" . htmlspecialchars($nextHref) . "'>" . $nextNum . "a Temporada &raquo;</a>";
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
