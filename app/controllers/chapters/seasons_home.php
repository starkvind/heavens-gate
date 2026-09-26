<?php
include_once(__DIR__ . '/../../helpers/runtime_response.php');
include_once(__DIR__ . '/../../helpers/content_image.php');
include_once(__DIR__ . '/../../domains/chapters/queries.php');

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
        'img' => '/img/og/og_image_temp.webp',
        'href' => '/seasons/complete',
        'cta' => 'Ver temporadas',
    ],
    'inciso' => [
        'title' => 'Incisos',
        'intro' => 'Interludios narrativos y episodios puente.',
        'empty' => 'No hay incisos disponibles.',
        'img' => '/img/og/og_image.webp',
        'href' => '/seasons/interludes',
        'cta' => 'Ver incisos',
    ],
    'historia_personal' => [
        'title' => 'Historias personales',
        'intro' => 'Arcos centrados en personajes y recorridos individuales.',
        'empty' => 'No hay historias personales disponibles.',
        'img' => '/img/og/og_image_bio.webp',
        'href' => '/seasons/personal-stories',
        'cta' => 'Ver historias personales',
    ],
    'especial' => [
        'title' => 'Especiales',
        'intro' => 'Piezas especiales del archivo narrativo.',
        'empty' => 'No hay especiales disponibles.',
        'img' => '/img/og/og_image_power.webp',
        'href' => '/seasons/specials',
        'cta' => 'Ver especiales',
    ],
];

$routeConfig = $seasonRouteConfigs[$routeKey] ?? $seasonRouteConfigs['seasons_home'];
setMetaFromPage($routeConfig['meta_title'], $routeConfig['meta_desc'], null, 'website');
if (function_exists('hg_page_register_stylesheet')) {
    hg_page_register_stylesheet('/assets/css/hg-seasons.css');
} else {
    echo '<link rel="stylesheet" href="/assets/css/hg-seasons.css">';
}

if (!hg_runtime_require_db($link, 'seasons_home', 'public', [
    'title' => 'Archivo de temporadas no disponible',
    'message' => 'No se pudo conectar a la base de datos.',
    'include_nav' => true,
])) {
    return;
}

include("app/partials/main_nav_bar.php");

$rows = hg_chapters_fetch_season_catalog($link) ?? [];
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
        <section class="season-home-order-promo">
            <div>
                <h2>Orden de temporadas</h2>
                <p>Consulta la cronología o el orden jugado desde una linea visual de temporadas y arcos de personaje.</p>
            </div>
            <a class="season-home-order-link" href="/seasons/order">Abrir orden narrativo</a>
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
                            $cardImage = hg_content_image_url($row['image_url'] ?? '', (string)$section['img']);
                            $statusText = ((int)($row['finished'] ?? 0) === 1) ? 'Finalizada' : ((((int)($row['finished'] ?? 0) === 2) ? 'Cancelada' : 'En curso'));
                            $statusClass = ((int)($row['finished'] ?? 0) === 1) ? 'season-home-status--done' : ((((int)($row['finished'] ?? 0) === 2) ? 'season-home-status--cancelled' : 'season-home-status--active'));
                        ?>
                        <a class="season-home-card" href="<?= hg_sh_h($href) ?>" title="<?= hg_sh_h($name) ?>">
                            <div class="season-home-card-media">
                                <img src="<?= hg_sh_h($cardImage) ?>" alt="<?= hg_sh_h($name) ?>">
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