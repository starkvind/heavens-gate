<?php

include_once(__DIR__ . '/../../helpers/public_response.php');
include_once(__DIR__ . '/../../domains/chapters/queries.php');

$metaTitle = "Temporadas | Heaven's Gate";
$metaDescription = "Archivo móvil de temporadas y capítulos.";
$pageSect = 'Temporadas';

if (!function_exists('hg_mobile_season_h')) {
    function hg_mobile_season_h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}
if (!function_exists('hg_mobile_season_url')) {
    function hg_mobile_season_url(mysqli $link, string $table, string $base, int $id): string {
        return $id > 0 && function_exists('pretty_url') ? pretty_url($link, $table, $base, $id) : rtrim($base, '/') . '/' . $id;
    }
}
if (!function_exists('hg_mobile_season_kind_label')) {
    function hg_mobile_season_kind_label(string $kind, int $number, string $name = ''): string {
        $kind = trim($kind);
        if ($kind === 'historia_personal') return 'Historia personal';
        if ($kind === 'especial') return 'Especial';
        if ($kind === 'inciso') {
            $n = $number;
            if ($n >= 100 && $n < 200) $n -= 100;
            return 'Inciso ' . ($n > 0 ? $n : '?');
        }
        return 'Temporada ' . ($number > 0 ? $number : '?');
    }
}
if (!function_exists('hg_mobile_season_excerpt')) {
    function hg_mobile_season_excerpt(string $text, int $max = 140): string {
        $text = trim(strip_tags($text));
        if ($text === '') return '';
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($text, 'UTF-8') > $max ? mb_substr($text, 0, $max, 'UTF-8') . '...' : $text;
        }
        return strlen($text) > $max ? substr($text, 0, $max) . '...' : $text;
    }
}

if (!isset($link) || !($link instanceof mysqli)) {
    hg_public_log_error('mobile_seasons_list', 'missing DB connection');
    hg_public_render_error('Temporadas no disponibles', 'No se pudo cargar el archivo.');
    return;
}

$route = hg_request_route($hgRequest);
if ($route === '') $route = 'seasons_home';
$routeKinds = [
    'seasons_complete' => ['temporada'],
    'seasons_interludes' => ['inciso'],
    'seasons_personal' => ['historia_personal'],
    'seasons_specials' => ['especial'],
];
$wantedKinds = $routeKinds[$route] ?? ['temporada', 'inciso', 'historia_personal', 'especial'];

$catalogRows = hg_chapters_fetch_season_catalog($link);
$rows = [];
if ($catalogRows === null) {
    hg_public_log_error('mobile_seasons_list', 'query failed: ' . mysqli_error($link));
} else {
    foreach ($catalogRows as $row) {
        $kind = trim((string)($row['season_kind'] ?? 'temporada')) ?: 'temporada';
        if (in_array($kind, $wantedKinds, true)) $rows[] = $row;
    }
}

$titleByRoute = [
    'seasons_complete' => 'Temporadas',
    'seasons_interludes' => 'Incisos',
    'seasons_personal' => 'Historias personales',
    'seasons_specials' => 'Especiales',
];
$title = $titleByRoute[$route] ?? 'Archivo de temporadas';
$metaTitle = $title . " | Heaven's Gate";
?>

<section class="hg-mobile-section">
    <h1><?= hg_mobile_season_h($title) ?></h1>
    <div class="hg-mobile-action-row">
        <a href="/seasons?view=mobile">Todo</a>
        <a href="/chapters?view=mobile">Capítulos</a>
    </div>
</section>

<section class="hg-mobile-section">
    <div class="hg-mobile-card-list">
        <?php if (empty($rows)): ?>
            <p class="hg-mobile-muted">No hay entradas disponibles.</p>
        <?php endif; ?>
        <?php foreach ($rows as $row): ?>
            <?php
                $id = (int)($row['id'] ?? 0);
                $href = hg_mobile_season_url($link, 'dim_seasons', '/seasons', $id);
                $kind = trim((string)($row['season_kind'] ?? 'temporada')) ?: 'temporada';
                $label = hg_mobile_season_kind_label($kind, (int)($row['season_number'] ?? 0));
                $finished = (int)($row['finished'] ?? 0);
            ?>
            <a class="hg-mobile-card" href="<?= hg_mobile_season_h($href) ?>">
                <strong><?= hg_mobile_season_h($row['name'] ?? '') ?></strong>
                <span><?= hg_mobile_season_h($label) ?> - <?= (int)($row['chapter_count'] ?? 0) ?> capítulos</span>
                <?php $excerpt = hg_mobile_season_excerpt((string)($row['description'] ?? '')); ?>
                <?php if ($excerpt !== ''): ?><span><?= hg_mobile_season_h($excerpt) ?></span><?php endif; ?>
                <small class="hg-mobile-muted"><?= $finished === 1 ? 'Finalizada' : ($finished === 2 ? 'Cancelada' : 'En curso') ?></small>
            </a>
        <?php endforeach; ?>
    </div>
</section>


