<?php
include_once(__DIR__ . '/../../helpers/runtime_response.php');

if (!function_exists('hg_sh_h')) {
    function hg_sh_h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

if (!function_exists('hg_sh_excerpt')) {
    function hg_sh_excerpt(string $txt, int $max = 180): string {
        $txt = trim(strip_tags($txt));
        if ($txt === '') return '';
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return (mb_strlen($txt, 'UTF-8') > $max) ? (mb_substr($txt, 0, $max, 'UTF-8') . '...') : $txt;
        }
        return (strlen($txt) > $max) ? (substr($txt, 0, $max) . '...') : $txt;
    }
}

if (!function_exists('hg_sh_kind_badge')) {
    function hg_sh_kind_badge(string $kind, int $number = 0): string {
        $kind = trim($kind);
        if ($kind === 'historia_personal') return 'Historia personal';
        if ($kind === 'especial') return 'Especial';
        if ($kind === 'inciso') {
            $incisoNum = $number;
            if ($incisoNum >= 100 && $incisoNum < 200) $incisoNum -= 100;
            return 'Inciso ' . ($incisoNum > 0 ? $incisoNum : '?');
        }
        return 'Temporada ' . ($number > 0 ? $number : '?');
    }
}

$seasonRouteConfigs = [
    'seasons_home' => [
        'meta_title' => "Temporadas e historias personales | Heaven's Gate",
        'meta_desc' => "Portada del archivo de temporadas, incisos, historias personales y especiales de Heaven's Gate.",
        'heading' => 'Archivo de temporadas',
        'intro' => 'Desde aqui puedes entrar en temporadas completas, incisos, historias personales y especiales del archivo narrativo.',
        'sections' => ['temporada', 'inciso', 'historia_personal', 'especial'],
    ],
    'seasons_complete' => [
        'meta_title' => "Temporadas completas | Heaven's Gate",
        'meta_desc' => "Listado de temporadas completas de Heaven's Gate.",
        'heading' => 'Temporadas completas',
        'intro' => 'Temporadas principales de la cronica, ordenadas para entrar directamente al archivo episodico.',
        'sections' => ['temporada'],
    ],
    'seasons_interludes' => [
        'meta_title' => "Interludes | Heaven's Gate",
        'meta_desc' => "Listado de incisos e interludios narrativos de Heaven's Gate.",
        'heading' => 'Incisos',
        'intro' => 'Interludios narrativos que amplian o conectan momentos clave entre temporadas principales.',
        'sections' => ['inciso'],
    ],
    'seasons_personal' => [
        'meta_title' => "Historias personales | Heaven's Gate",
        'meta_desc' => "Listado de historias personales de Heaven's Gate.",
        'heading' => 'Historias personales',
        'intro' => 'Arcos centrados en personajes concretos y su continuidad propia dentro de Heaven\'s Gate.',
        'sections' => ['historia_personal'],
    ],
    'seasons_specials' => [
        'meta_title' => "Especiales | Heaven's Gate",
        'meta_desc' => "Listado de especiales de Heaven's Gate.",
        'heading' => 'Especiales',
        'intro' => 'Piezas especiales del archivo que no encajan como temporada principal, inciso o historia personal.',
        'sections' => ['especial'],
    ],
];

$seasonSectionDefs = [
    'temporada' => [
        'title' => 'Temporadas',
        'intro' => 'Temporadas principales de la crónica.',
        'empty' => 'No hay temporadas disponibles.',
        'img' => '/img/og/og_image_temp.jpg',
        'href' => '/seasons/complete',
        'cta' => 'Ver temporadas',
    ],
    'inciso' => [
        'title' => 'Incisos',
        'intro' => 'Interludios narrativos y episodios puente.',
        'empty' => 'No hay incisos disponibles.',
        'img' => '/img/og/og_image.jpg',
        'href' => '/seasons/interludes',
        'cta' => 'Ver incisos',
    ],
    'historia_personal' => [
        'title' => 'Historias personales',
        'intro' => 'Arcos centrados en personajes y recorridos individuales.',
        'empty' => 'No hay historias personales disponibles.',
        'img' => '/img/og/og_image_bio.jpg',
        'href' => '/seasons/personal-stories',
        'cta' => 'Ver historias personales',
    ],
    'especial' => [
        'title' => 'Especiales',
        'intro' => 'Piezas especiales del archivo narrativo.',
        'empty' => 'No hay especiales disponibles.',
        'img' => '/img/og/og_image_power.jpg',
        'href' => '/seasons/specials',
        'cta' => 'Ver especiales',
    ],
];

$routeConfig = $seasonRouteConfigs[$routeKey] ?? $seasonRouteConfigs['seasons_home'];
setMetaFromPage($routeConfig['meta_title'], $routeConfig['meta_desc'], null, 'website');
echo '<link rel="stylesheet" href="/assets/css/hg-main.css">';

if (!hg_runtime_require_db($link, 'seasons_home', 'public', [
    'title' => 'Archivo de temporadas no disponible',
    'message' => 'No se pudo conectar a la base de datos.',
    'include_nav' => true,
])) {
    return;
}

include("app/partials/main_nav_bar.php");

$rows = [];
$sql = "
    SELECT
        s.id,
        s.name,
        s.pretty_id,
        s.description,
        s.season_number,
        COALESCE(s.season_kind, 'temporada') AS season_kind,
        COALESCE(s.finished, 0) AS finished,
        COALESCE(s.sort_order, 999999) AS sort_order,
        COUNT(c.id) AS chapter_count
    FROM dim_seasons s
    LEFT JOIN dim_chapters c ON c.season_id = s.id
    GROUP BY
        s.id, s.name, s.pretty_id, s.description, s.season_number,
        s.season_kind, s.finished, s.sort_order
    ORDER BY
        CASE
            WHEN COALESCE(s.season_kind, 'temporada') = 'temporada' THEN 1
            WHEN COALESCE(s.season_kind, 'temporada') = 'inciso' THEN 2
            WHEN COALESCE(s.season_kind, 'temporada') = 'historia_personal' THEN 3
            WHEN COALESCE(s.season_kind, 'temporada') = 'especial' THEN 4
            ELSE 99
        END ASC,
        COALESCE(s.sort_order, 999999) ASC,
        s.season_number ASC,
        s.name ASC
";

if ($rs = mysqli_query($link, $sql)) {
    while ($row = mysqli_fetch_assoc($rs)) {
        $rows[] = $row;
    }
    mysqli_free_result($rs);
}

$rowsByKind = [
    'temporada' => [],
    'inciso' => [],
    'historia_personal' => [],
    'especial' => [],
];

foreach ($rows as $row) {
    $kind = trim((string)($row['season_kind'] ?? 'temporada'));
    if (!isset($rowsByKind[$kind])) {
        $kind = 'temporada';
    }
    $rowsByKind[$kind][] = $row;
}

$sectionStats = [];
foreach ($seasonSectionDefs as $kind => $sectionDef) {
    $entries = $rowsByKind[$kind] ?? [];
    $chapterTotal = 0;
    foreach ($entries as $entry) {
        $chapterTotal += (int)($entry['chapter_count'] ?? 0);
    }
    $sectionStats[$kind] = [
        'items' => count($entries),
        'chapters' => $chapterTotal,
    ];
}

/*
    <!-- <div class="season-home-head">
        <h2><?= hg_sh_h($routeConfig['heading']) ?></h2>
        <p><?= hg_sh_h($routeConfig['intro']) ?></p>
        <?php if ($routeKey === 'seasons_home'): ?>
            <div class="season-home-nav">
                <a class="season-home-nav-link" href="/seasons/complete">Temporadas completas</a>
                <a class="season-home-nav-link" href="/seasons/interludes">Incisos</a>
                <a class="season-home-nav-link" href="/seasons/personal-stories">Historias personales</a>
                <a class="season-home-nav-link" href="/seasons/specials">Especiales</a>
                <a class="season-home-nav-link" href="/chapters">Tabla de episodios</a>
            </div>
            <p>Cada puerta de entrada te lleva a un handler distinto del archivo, para no mezclar todas las temporadas en una sola pagina.</p>
        <?php endif; ?>
    </div> -->

*/
?>

<div class="season-home">
    <?php if ($routeKey === 'seasons_home'): ?>
        <section class="season-home-block">
            <div class="season-home-block-head">
                <h2>Temporadas de Heaven's Gate</h2>
            </div>
            <div class="season-home-hub-grid">
                <?php foreach ($routeConfig['sections'] as $sectionKind): ?>
                    <?php
                        $section = $seasonSectionDefs[$sectionKind];
                        $stats = $sectionStats[$sectionKind] ?? ['items' => 0, 'chapters' => 0];
                    ?>
                    <a class="season-home-hub-card" href="<?= hg_sh_h($section['href']) ?>" title="<?= hg_sh_h($section['title']) ?>">
                        <?php /* <span class="season-home-hub-kicker">Archivo narrativo</span> */ ?>
                        <h3><?= hg_sh_h($section['title']) ?></h3>
                        <p><?= hg_sh_h($section['intro']) ?></p>
                        <div class="season-home-hub-meta">
                            <span><?= number_format((int)$stats['items'], 0, ',', '.') ?> entradas</span>
                            <span><?= number_format((int)$stats['chapters'], 0, ',', '.') ?> capitulos</span>
                        </div>
                        <span class="season-home-hub-cta"><?= hg_sh_h($section['cta']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php else: ?>
    <?php foreach ($routeConfig['sections'] as $sectionKind): ?>
        <?php $section = $seasonSectionDefs[$sectionKind]; ?>
        <?php $sectionRows = $rowsByKind[$sectionKind] ?? []; ?>
        <section class="season-home-block">
            <div class="season-home-block-head">
                <h2><?= hg_sh_h($section['title']) ?></h2>
                <p><?= hg_sh_h($section['intro']) ?></p>
            </div>
            <?php if (count($sectionRows) === 0): ?>
                <p class="texti"><?= hg_sh_h($section['empty']) ?></p>
            <?php else: ?>
                <div class="chron-grid season-home-grid">
                    <?php foreach ($sectionRows as $row): ?>
                        <?php
                            $sid = (int)($row['id'] ?? 0);
                            $kind = trim((string)($row['season_kind'] ?? 'temporada'));
                            $number = (int)($row['season_number'] ?? 0);
                            $name = (string)($row['name'] ?? '');
                            $desc = (string)($row['description'] ?? '');
                            $href = pretty_url($link, 'dim_seasons', '/seasons', $sid);
                            $badge = hg_sh_kind_badge($kind, $number);
                            $chapterCount = (int)($row['chapter_count'] ?? 0);
                            $statusText = ((int)($row['finished'] ?? 0) === 1) ? 'Finalizada' : ((((int)($row['finished'] ?? 0) === 2) ? 'Cancelada' : 'En curso'));
                            $statusClass = ((int)($row['finished'] ?? 0) === 1) ? 'season-home-status--done' : ((((int)($row['finished'] ?? 0) === 2) ? 'season-home-status--cancelled' : 'season-home-status--active'));
                        ?>
                        <a class="season-home-card" href="<?= hg_sh_h($href) ?>" title="<?= hg_sh_h($name) ?>">
                            <div class="season-home-card-media">
                                <img src="<?= hg_sh_h($section['img']) ?>" alt="<?= hg_sh_h($name) ?>">
                                <div class="season-home-card-overlay">
                                    <span class="season-home-card-kicker"><?= hg_sh_h($badge) ?></span>
                                    <h3><?= hg_sh_h($name) ?></h3>
                                </div>
                            </div>
                            <div class="season-home-card-body">
                                <p><?= hg_sh_h(hg_sh_excerpt($desc !== '' ? $desc : 'Sin descripcion.', 170)) ?></p>
                                <div class="season-home-card-summary">
                                    <span class="season-home-count"><?= number_format($chapterCount, 0, ',', '.') ?> capitulos</span>
                                    <span class="season-home-status <?= hg_sh_h($statusClass) ?>"><?= hg_sh_h($statusText) ?></span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
    <?php endif; ?>
</div>
