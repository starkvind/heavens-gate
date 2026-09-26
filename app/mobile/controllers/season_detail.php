<?php

include_once(__DIR__ . '/../../helpers/public_response.php');
include_once(__DIR__ . '/../../helpers/character_avatar.php');
require_once(__DIR__ . '/../../domains/chapters/queries.php');
require_once(__DIR__ . '/../../domains/soundtracks/queries.php');

$metaTitle = "Temporada | Heaven's Gate";
$metaDescription = "Ficha móvil de temporada.";
$pageSect = 'Temporadas';

if (!function_exists('hg_mobile_sd_h')) {
    function hg_mobile_sd_h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}
if (!function_exists('hg_mobile_sd_url')) {
    function hg_mobile_sd_url(mysqli $link, string $table, string $base, int $id): string {
        return $id > 0 && function_exists('pretty_url') ? pretty_url($link, $table, $base, $id) : rtrim($base, '/') . '/' . $id;
    }
}
if (!function_exists('hg_mobile_sd_date')) {
    function hg_mobile_sd_date(?string $raw): string {
        $raw = trim((string)$raw);
        if ($raw === '' || $raw === '0000-00-00') return '';
        $ts = strtotime($raw);
        return $ts !== false ? date('d-m-Y', $ts) : $raw;
    }
}
if (!function_exists('hg_mobile_sd_kind_label')) {
    function hg_mobile_sd_kind_label(string $kind, int $number): string {
        if ($kind === 'historia_personal') return 'Historia personal';
        if ($kind === 'especial') return 'Especial';
        if ($kind === 'inciso') {
            if ($number >= 100 && $number < 200) $number -= 100;
            return 'Inciso ' . ($number > 0 ? $number : '?');
        }
        return 'Temporada ' . ($number > 0 ? $number : '?');
    }
}
if (!function_exists('hg_mobile_sd_youtube_id')) {
    function hg_mobile_sd_youtube_id(string $url): string {
        if (preg_match('%(?:youtu\.be/|youtube\.com/watch\?v=|youtube\.com/embed/)([^&\n?#/]+)%i', $url, $m)) return (string)$m[1];
        return '';
    }
}
if (!function_exists('hg_mobile_sd_character_card')) {
    function hg_mobile_sd_character_card(mysqli $link, array $character, ?int $played = null, ?int $total = null): void {
        $id = (int)($character['id'] ?? $character['pj_id'] ?? 0);
        $href = hg_mobile_sd_url($link, 'fact_characters', '/characters', $id);
        $avatar = hg_character_avatar_url((string)($character['image_url'] ?? ''), (string)($character['gender'] ?? ''));
        ?>
        <a class="hg-mobile-character-card" href="<?= hg_mobile_sd_h($href) ?>">
            <?php if ($avatar !== ''): ?><img src="<?= hg_mobile_sd_h($avatar) ?>" alt=""><?php else: ?><span class="hg-mobile-character-avatar" aria-hidden="true"></span><?php endif; ?>
            <span class="hg-mobile-character-main">
                <strong><?= hg_mobile_sd_h($character['name'] ?? '') ?></strong>
                <?php if ($played !== null && $total !== null): ?><span><?= (int)$played ?> / <?= (int)$total ?> capítulos</span><?php endif; ?>
            </span>
        </a>
        <?php
    }
}

if (!isset($link) || !($link instanceof mysqli)) {
    hg_public_log_error('mobile_season_detail', 'missing DB connection');
    hg_public_render_error('Temporada no disponible', 'No se pudo cargar la temporada.');
    return;
}

$raw = hg_request_param($hgRequest, 'season');
$seasonId = $raw !== '' ? (int)(resolve_pretty_id($link, 'dim_seasons', $raw) ?? 0) : 0;
if ($seasonId <= 0) {
    hg_public_render_not_found('Temporada no encontrada', 'No se pudo localizar la temporada solicitada.');
    return;
}

$season = hg_chapters_fetch_season_detail($link, $seasonId);
if (!$season) {
    hg_public_render_not_found('Temporada no encontrada', 'No se pudo localizar la temporada solicitada.');
    return;
}

$name = (string)($season['name'] ?? 'Temporada');
$kind = trim((string)($season['season_kind'] ?? 'temporada')) ?: 'temporada';
$number = (int)($season['season_number'] ?? 0);
$label = hg_mobile_sd_kind_label($kind, $number);
$metaTitle = $name . " | Temporadas | Heaven's Gate";
$metaDescription = trim(strip_tags((string)($season['description'] ?? '')));

$chapters = hg_chapters_fetch_season_chapters($link, $seasonId);
if ($chapters === null) {
    hg_public_log_error('mobile_season_detail', 'chapters query failed: ' . mysqli_error($link));
    $chapters = [];
}
$totalPlayed = 0;
foreach ($chapters as $chapter) {
    if (hg_mobile_sd_date($chapter['played_date'] ?? '') !== '') $totalPlayed++;
}

$excludedChronicles = function_exists('hg_chronicle_scope_excluded_csv')
    ? hg_chronicle_scope_excluded_csv()
    : '2,7';
$seasonCharacters = hg_chapters_fetch_season_players($link, $seasonId, $excludedChronicles);
if ($seasonCharacters === null) {
    hg_public_log_error('mobile_season_detail', 'characters query failed: ' . mysqli_error($link));
    $seasonCharacters = [];
}

$soundtracksRaw = hg_soundtracks_fetch_for_object($link, 'temporada', $seasonId);
$soundtracks = [];
if ($soundtracksRaw !== null) {
    foreach ($soundtracksRaw as $track) {
        $soundtracks[] = [
            'context_title' => (string)($track['context_title'] ?? ''),
            'title' => (string)($track['titulo_real'] ?? ''),
            'artist' => (string)($track['artist'] ?? ''),
            'youtube_url' => (string)($track['enlace'] ?? ''),
        ];
    }
}

$seasonPrev = null;
$seasonNext = null;
if ($kind === 'temporada' && $number > 0) {
    $prevRow = hg_chapters_fetch_neighbor_season($link, $number, 'prev');
    if ($prevRow) {
        $seasonPrev = [
            'href' => hg_mobile_sd_url($link, 'dim_seasons', '/seasons', (int)$prevRow['id']),
            'label' => 'Temporada ' . (int)$prevRow['season_number'],
            'title' => (string)($prevRow['name'] ?? ''),
        ];
    }

    $nextRow = hg_chapters_fetch_neighbor_season($link, $number, 'next');
    if ($nextRow) {
        $seasonNext = [
            'href' => hg_mobile_sd_url($link, 'dim_seasons', '/seasons', (int)$nextRow['id']),
            'label' => 'Temporada ' . (int)$nextRow['season_number'],
            'title' => (string)($nextRow['name'] ?? ''),
        ];
    }
}
?>
<article class="hg-mobile-bio">
    <nav class="hg-mobile-local-nav"><a href="/seasons?view=mobile">Volver a temporadas</a></nav>

    <section class="hg-mobile-section">
        <h1><?= hg_mobile_sd_h($name) ?></h1>
        <div class="hg-mobile-fact-grid">
            <div><span>Tipo</span><strong><?= hg_mobile_sd_h($label) ?></strong></div>
            <div><span>Capítulos</span><strong><?= count($chapters) ?></strong></div>
            <div><span>Estado</span><strong><?= (int)($season['finished'] ?? 0) === 1 ? 'Finalizada' : ((int)($season['finished'] ?? 0) === 2 ? 'Cancelada' : 'En curso') ?></strong></div>
            <?php if (!empty($season['chronicle_name'])): ?><div><span>Cronica</span><strong><?= hg_mobile_sd_h($season['chronicle_name']) ?></strong></div><?php endif; ?>
        </div>
    </section>

    <?php if (trim(strip_tags((string)($season['opening'] ?? ''))) !== ''): ?>
        <section class="hg-mobile-section hg-mobile-prose"><h2>Opening</h2><?= (string)$season['opening'] ?></section>
    <?php endif; ?>

    <?php if (trim(strip_tags((string)($season['description'] ?? ''))) !== ''): ?>
        <section class="hg-mobile-section hg-mobile-prose"><h2>Sinopsis</h2><?= (string)$season['description'] ?></section>
    <?php endif; ?>

    <?php if (trim(strip_tags((string)($season['main_cast'] ?? ''))) !== ''): ?>
        <section class="hg-mobile-section hg-mobile-prose"><h2>Reparto principal</h2><?= (string)$season['main_cast'] ?></section>
    <?php endif; ?>

    <?php if (!empty($seasonCharacters)): ?>
        <section class="hg-mobile-section">
            <h2>Personajes</h2>
            <div class="hg-mobile-character-list">
                <?php foreach ($seasonCharacters as $character): ?>
                    <?php hg_mobile_sd_character_card($link, $character, (int)($character['played_count'] ?? 0), max(1, $totalPlayed)); ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!empty($soundtracks)): ?>
        <section class="hg-mobile-section">
            <h2>Temas musicales</h2>
            <div class="hg-mobile-list hg-mobile-linked-list">
                <?php foreach ($soundtracks as $track): ?>
                    <?php $youtubeId = hg_mobile_sd_youtube_id((string)($track['youtube_url'] ?? '')); ?>
                    <div>
                        <strong><?= hg_mobile_sd_h($track['context_title'] ?: $track['title']) ?></strong>
                        <span><?= hg_mobile_sd_h(trim((string)($track['title'] ?? '') . ' - ' . (string)($track['artist'] ?? ''), ' -')) ?></span>
                        <?php if ($youtubeId !== ''): ?><iframe src="https://www.youtube-nocookie.com/embed/<?= hg_mobile_sd_h($youtubeId) ?>" loading="lazy" allowfullscreen></iframe><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="hg-mobile-section">
        <h2>Capítulos</h2>
        <div class="hg-mobile-list hg-mobile-linked-list">
            <?php if (empty($chapters)): ?><p class="hg-mobile-muted">No hay capítulos registrados.</p><?php endif; ?>
            <?php foreach ($chapters as $chapter): ?>
                <?php
                    $chapterId = (int)($chapter['id'] ?? 0);
                    $href = hg_mobile_sd_url($link, 'dim_chapters', '/chapters', $chapterId);
                    $code = $kind === 'temporada' ? sprintf('%dx%02d', $number, (int)$chapter['chapter_number']) : sprintf('%02d', (int)$chapter['chapter_number']);
                    $date = hg_mobile_sd_date($chapter['played_date'] ?? '');
                ?>
                <div>
                    <strong><a href="<?= hg_mobile_sd_h($href) ?>"><?= hg_mobile_sd_h($code . ' - ' . (string)($chapter['name'] ?? '')) ?></a></strong>
                    <span><?= (int)($chapter['participant_count'] ?? 0) ?> participantes<?= $date !== '' ? ' - ' . hg_mobile_sd_h($date) : '' ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <?php if ($seasonPrev || $seasonNext): ?>
        <nav class="hg-mobile-prev-next" aria-label="Navegación de temporadas">
            <?php if ($seasonPrev): ?>
                <a class="hg-mobile-prev-next-link" href="<?= hg_mobile_sd_h($seasonPrev['href']) ?>"><span>Anterior</span><strong><?= hg_mobile_sd_h($seasonPrev['label']) ?></strong><small><?= hg_mobile_sd_h($seasonPrev['title']) ?></small></a>
            <?php else: ?>
                <span class="hg-mobile-prev-next-empty" aria-hidden="true"></span>
            <?php endif; ?>
            <?php if ($seasonNext): ?>
                <a class="hg-mobile-prev-next-link hg-mobile-prev-next-link--next" href="<?= hg_mobile_sd_h($seasonNext['href']) ?>"><span>Siguiente</span><strong><?= hg_mobile_sd_h($seasonNext['label']) ?></strong><small><?= hg_mobile_sd_h($seasonNext['title']) ?></small></a>
            <?php else: ?>
                <span class="hg-mobile-prev-next-empty" aria-hidden="true"></span>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
</article>