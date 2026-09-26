<?php setMetaFromPage("Temporadas | Heaven's Gate", "Consulta temporadas y capitulos de la campana.", null, 'website'); ?>
<?php include_once(__DIR__ . '/../../helpers/character_avatar.php'); ?>
<?php include_once(__DIR__ . '/../../helpers/content_image.php'); ?>
<?php include_once(__DIR__ . '/../../helpers/runtime_response.php'); ?>
<?php require_once(__DIR__ . '/../../domains/chapters/queries.php'); ?>
<?php if (function_exists('hg_page_register_stylesheet')) { hg_page_register_stylesheet('/assets/css/hg-chapters.css'); } else { ?><link rel="stylesheet" href="/assets/css/hg-chapters.css"><?php } ?>

<?php
if (!hg_runtime_require_db($link, 'season_archive', 'public', [
    'title' => 'Temporadas no disponibles',
    'message' => 'No se pudo conectar a la base de datos.',
    'include_nav' => true,
])) {
    return;
}

$temporadaRaw = hg_request_param($hgRequest, 'season');
if (trim((string)$temporadaRaw) === '') {
    include(__DIR__ . '/seasons_home.php');
    return;
}

$temporadaId = resolve_pretty_id($link, 'dim_seasons', (string)$temporadaRaw) ?? 0;
if ($temporadaId <= 0) {
    echo "No se encontraron resultados para la busqueda.";
    return;
}

$ResultQuery = hg_chapters_fetch_season_detail($link, $temporadaId);
if (!$ResultQuery) {
    echo "No se encontraron resultados para la busqueda.";
    return;
}

$nameTemp = (string)($ResultQuery['name'] ?? '');
$numberTemp = (int)($ResultQuery['season_number'] ?? 0);
$sinopsis = (string)($ResultQuery['description'] ?? '');
$seasonImage = hg_content_image_url($ResultQuery['image_url'] ?? '');
$seasonFinished = (int)($ResultQuery['finished'] ?? 0);
$seasonChronicleId = (int)($ResultQuery['chronicle_id'] ?? 0);
$seasonChronicleName = (string)($ResultQuery['chronicle_name'] ?? '');
$seasonChronicleHref = $seasonChronicleId > 0
    ? pretty_url($link, 'dim_chronicles', '/chronicles', $seasonChronicleId)
    : '';

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
    $seasonImage !== '' ? $seasonImage : null,
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

if ($seasonImage !== '') {
    echo "<figure class='archive-main-image'>";
    echo "<img src='" . htmlspecialchars($seasonImage, ENT_QUOTES, 'UTF-8') . "' alt='" . htmlspecialchars($nameTemp, ENT_QUOTES, 'UTF-8') . "' loading='lazy'>";
    echo "</figure>";
}

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
    $protagonistas = hg_chapters_fetch_characters_by_ids($link, $player_ids) ?? [];
    if (!empty($protagonistas)) {
        echo "<section class='archive-block'>";
        echo "<h3 class='archive-title'>{$titleProta}</h3>";
        echo "<div class='prota-grid'>";

        foreach ($protagonistas as $row) {
            $checkId = (int)($row['id'] ?? 0);
            $hrefProta = pretty_url($link, 'fact_characters', '/characters', $checkId);
            echo "<a href='" . htmlspecialchars($hrefProta) . "' class='prota-card hg-tooltip' target='_blank' data-tip='character' data-id='" . $checkId . "'>";
            echo "<img src='" . htmlspecialchars(hg_character_avatar_url((string)($row['image_url'] ?? ''), (string)($row['gender'] ?? ''))) . "' class='photochapter' alt='" . htmlspecialchars((string)($row['name'] ?? '')) . "'>";
            echo "<span>" . htmlspecialchars((string)($row['name'] ?? '')) . "</span>";
            echo "</a>";
        }

        echo "</div>";
        echo "</section>";
    }
}

echo "<section class='archive-block'>";
echo "<h3 class='archive-title'>{$titleChapt}</h3>";
echo "<div class='chapters-list'>";

$seasonChapters = hg_chapters_fetch_season_chapters($link, $temporadaId) ?? [];
foreach ($seasonChapters as $ResultQueryChapt) {
    $idEpi = (int)($ResultQueryChapt['id'] ?? 0);
    $nameEpi = (string)($ResultQueryChapt['name'] ?? '');
    $capiEpi = (int)($ResultQueryChapt['chapter_number'] ?? 0);

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

echo "</div>";
echo "</section>";

include("app/partials/snippet_bso_card.php");
mostrarTarjetaBSO($link, 'temporada', $temporadaId);

if (!empty($player_ids)) {
    include("app/partials/chapters/season_barchart.php");
}

$prevSeasonLink = '';
$nextSeasonLink = '';
$prevSeasonHref = '';
$nextSeasonHref = '';
if ($seasonKind === 'temporada') {
    $rowPrevSeason = hg_chapters_fetch_neighbor_season($link, $numberTemp, 'prev');
    if ($rowPrevSeason) {
        $prevNum = (int)($rowPrevSeason['season_number'] ?? 0);
        $prevSeasonHref = pretty_url($link, 'dim_seasons', '/seasons', (int)$rowPrevSeason['id']);
        $prevSeasonLink = "<a class='archive-season-link prev' href='" . htmlspecialchars($prevSeasonHref) . "'>&laquo; " . $prevNum . "a Temporada</a>";
    }

    $rowNextSeason = hg_chapters_fetch_neighbor_season($link, $numberTemp, 'next');
    if ($rowNextSeason) {
        $nextNum = (int)($rowNextSeason['season_number'] ?? 0);
        $nextSeasonHref = pretty_url($link, 'dim_seasons', '/seasons', (int)$rowNextSeason['id']);
        $nextSeasonLink = "<a class='archive-season-link next' href='" . htmlspecialchars($nextSeasonHref) . "'>" . $nextNum . "a Temporada &raquo;</a>";
    }
}

if ($prevSeasonLink !== '' || $nextSeasonLink !== '') {
    echo "<div class='archive-season-nav'>";
    echo ($prevSeasonLink !== '') ? $prevSeasonLink : "<div class='nav-empty'></div>";
    echo ($nextSeasonLink !== '') ? $nextSeasonLink : "<div class='nav-empty'></div>";
    echo "</div>";

    $prevSeasonHrefJs = json_encode($prevSeasonHref, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $nextSeasonHrefJs = json_encode($nextSeasonHref, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    echo "<script>
        (function() {
            var prevHref = {$prevSeasonHrefJs};
            var nextHref = {$nextSeasonHrefJs};
            document.addEventListener('keydown', function(e) {
                if (e.defaultPrevented || e.ctrlKey || e.altKey || e.metaKey || e.shiftKey) return;
                var t = e.target;
                if (t && t.closest && t.closest('input, textarea, select, button, a, [contenteditable=\"true\"]')) return;
                if (e.key === 'ArrowLeft' && prevHref) {
                    window.location.href = prevHref;
                } else if (e.key === 'ArrowRight' && nextHref) {
                    window.location.href = nextHref;
                }
            });
        })();
    </script>";
}

echo "</div>";
echo "</div>";
?>
