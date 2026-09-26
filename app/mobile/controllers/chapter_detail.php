<?php

include_once(__DIR__ . '/../../helpers/public_response.php');
include_once(__DIR__ . '/../../helpers/character_avatar.php');
require_once(__DIR__ . '/../../domains/chapters/queries.php');
require_once(__DIR__ . '/../../domains/soundtracks/queries.php');

$metaTitle = "Capitulo | Heaven's Gate";
$metaDescription = "Ficha móvil de capitulo.";
$pageSect = 'Capítulos';

if (!function_exists('hg_mobile_cd_h')) {
    function hg_mobile_cd_h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}
if (!function_exists('hg_mobile_cd_url')) {
    function hg_mobile_cd_url(mysqli $link, string $table, string $base, int $id): string {
        return $id > 0 && function_exists('pretty_url') ? pretty_url($link, $table, $base, $id) : rtrim($base, '/') . '/' . $id;
    }
}
if (!function_exists('hg_mobile_cd_date')) {
    function hg_mobile_cd_date(?string $raw): string {
        $raw = trim((string)$raw);
        if ($raw === '' || $raw === '0000-00-00') return '';
        $ts = strtotime($raw);
        return $ts !== false ? date('d-m-Y', $ts) : $raw;
    }
}
if (!function_exists('hg_mobile_cd_character_card')) {
    function hg_mobile_cd_character_card(mysqli $link, array $character): void {
        $id = (int)($character['id'] ?? 0);
        $href = hg_mobile_cd_url($link, 'fact_characters', '/characters', $id);
        $avatar = hg_character_avatar_url((string)($character['image_url'] ?? ''), (string)($character['gender'] ?? ''));
        ?>
        <a class="hg-mobile-character-card" href="<?= hg_mobile_cd_h($href) ?>">
            <?php if ($avatar !== ''): ?><img src="<?= hg_mobile_cd_h($avatar) ?>" alt=""><?php else: ?><span class="hg-mobile-character-avatar" aria-hidden="true"></span><?php endif; ?>
            <span class="hg-mobile-character-main">
                <strong><?= hg_mobile_cd_h($character['name'] ?? '') ?></strong>
                <span><?= hg_mobile_cd_h($character['role_label'] ?? '') ?></span>
            </span>
        </a>
        <?php
    }
}
if (!function_exists('hg_mobile_cd_youtube_id')) {
    function hg_mobile_cd_youtube_id(string $url): string {
        if (preg_match('%(?:youtu\.be/|youtube\.com/watch\?v=|youtube\.com/embed/)([^&\n?#/]+)%i', $url, $m)) return (string)$m[1];
        return '';
    }
}

if (!isset($link) || !($link instanceof mysqli)) {
    hg_public_log_error('mobile_chapter_detail', 'missing DB connection');
    hg_public_render_error('Capitulo no disponible', 'No se pudo cargar el capitulo.');
    return;
}

$raw = hg_request_param($hgRequest, 'chapter');
$chapterId = hg_chapters_resolve_chapter_id($link, $raw);
if ($chapterId <= 0) {
    hg_public_render_not_found('Capitulo no encontrado', 'No se pudo localizar el capitulo solicitado.');
    return;
}

$chapter = hg_chapters_fetch_chapter_detail($link, $chapterId);
if (!$chapter) {
    if ($link->error !== '') hg_public_log_error('mobile_chapter_detail', 'chapter query failed: ' . $link->error);
    hg_public_render_not_found('Capitulo no encontrado', 'No se pudo localizar el capitulo solicitado.');
    return;
}

$name = (string)($chapter['name'] ?? 'Capitulo');
$kind = trim((string)($chapter['season_kind'] ?? 'temporada')) ?: 'temporada';
$seasonNumber = (int)($chapter['season_number'] ?? 0);
$chapterNumber = (int)($chapter['chapter_number'] ?? 0);
$code = $kind === 'temporada' ? sprintf('%dx%02d', $seasonNumber, $chapterNumber) : sprintf('%02d', $chapterNumber);
$metaTitle = $name . " | Capítulos | Heaven's Gate";
$metaDescription = trim(strip_tags((string)($chapter['synopsis'] ?? '')));

$excludedChronicles = function_exists('hg_chronicle_scope_excluded_csv')
    ? hg_chronicle_scope_excluded_csv()
    : '2,7';
$participants = hg_chapters_fetch_chapter_participants($link, $chapterId, $excludedChronicles);
if ($participants === null) {
    hg_public_log_error('mobile_chapter_detail', 'participants query failed: ' . mysqli_error($link));
    $participants = [];
}
foreach ($participants as &$participant) {
    $role = strtolower(trim((string)($participant['participation_role'] ?? 'npc')));
    $participant['role_label'] = $role === 'player' ? 'Protagonista' : 'PNJ';
}
unset($participant);

$participantsByRole = ['player' => [], 'npc' => []];
foreach ($participants as $participant) {
    $role = strtolower(trim((string)($participant['participation_role'] ?? 'npc')));
    $participantsByRole[$role === 'player' ? 'player' : 'npc'][] = $participant;
}

$soundtracksRaw = hg_soundtracks_fetch_for_object($link, 'episodio', $chapterId);
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

$chapterPrev = null;
$chapterNext = null;
$chapterSeasonId = (int)($chapter['season_id'] ?? 0);
if ($chapterSeasonId > 0 && $chapterNumber > 0) {
    $neighbors = hg_chapters_fetch_neighbor_chapters($link, $chapterSeasonId, $chapterNumber) ?? [];
    $prevNumber = $chapterNumber - 1;
    $nextNumber = $chapterNumber + 1;

    if (isset($neighbors[$prevNumber])) {
        $row = $neighbors[$prevNumber];
        $chapterPrev = [
            'href' => hg_mobile_cd_url($link, 'dim_chapters', '/chapters', (int)$row['id']),
            'label' => $kind === 'temporada' ? sprintf('%dx%02d', $seasonNumber, $prevNumber) : sprintf('%02d', $prevNumber),
            'title' => (string)($row['name'] ?? ''),
        ];
    }
    if (isset($neighbors[$nextNumber])) {
        $row = $neighbors[$nextNumber];
        $chapterNext = [
            'href' => hg_mobile_cd_url($link, 'dim_chapters', '/chapters', (int)$row['id']),
            'label' => $kind === 'temporada' ? sprintf('%dx%02d', $seasonNumber, $nextNumber) : sprintf('%02d', $nextNumber),
            'title' => (string)($row['name'] ?? ''),
        ];
    }

    if ($kind === 'temporada') {
        $bounds = hg_chapters_fetch_chapter_bounds($link, $chapterSeasonId) ?? ['min_ch' => 0, 'max_ch' => 0];
        if (!$chapterPrev && $chapterNumber === (int)$bounds['min_ch'] && $seasonNumber > 0) {
            $row = hg_chapters_fetch_neighbor_season($link, $seasonNumber, 'prev');
            if ($row) {
                $chapterPrev = [
                    'href' => hg_mobile_cd_url($link, 'dim_seasons', '/seasons', (int)$row['id']),
                    'label' => 'Temporada ' . (int)$row['season_number'],
                    'title' => (string)($row['name'] ?? ''),
                ];
            }
        }
        if (!$chapterNext && $chapterNumber === (int)$bounds['max_ch'] && $seasonNumber > 0) {
            $row = hg_chapters_fetch_neighbor_season($link, $seasonNumber, 'next');
            if ($row) {
                $chapterNext = [
                    'href' => hg_mobile_cd_url($link, 'dim_seasons', '/seasons', (int)$row['id']),
                    'label' => 'Temporada ' . (int)$row['season_number'],
                    'title' => (string)($row['name'] ?? ''),
                ];
            }
        }
    }
}

$events = hg_chapters_fetch_chapter_events($link, $chapterId, 20);
if ($events === null) {
    hg_public_log_error('mobile_chapter_detail', 'events query failed: ' . mysqli_error($link));
    $events = [];
}
?>

<article class="hg-mobile-bio">
    <nav class="hg-mobile-local-nav">
        <a href="/chapters?view=mobile">Volver a capítulos</a>
    </nav>

    <section class="hg-mobile-section">
        <h1><?= hg_mobile_cd_h($name) ?></h1>
        <div class="hg-mobile-fact-grid">
            <div><span>Codigo</span><strong><?= hg_mobile_cd_h($code) ?></strong></div>
            <?php if (!empty($chapter['season_id'])): ?>
                <div><span>Temporada</span><strong><a href="<?= hg_mobile_cd_h(hg_mobile_cd_url($link, 'dim_seasons', '/seasons', (int)$chapter['season_id'])) ?>"><?= hg_mobile_cd_h($chapter['season_name'] ?? '') ?></a></strong></div>
            <?php endif; ?>
            <?php $played = hg_mobile_cd_date($chapter['played_date'] ?? ''); if ($played !== ''): ?><div><span>Fecha jugada</span><strong><?= hg_mobile_cd_h($played) ?></strong></div><?php endif; ?>
            <?php $ingame = hg_mobile_cd_date($chapter['in_game_date'] ?? ''); if ($ingame !== ''): ?><div><span>Fecha en juego</span><strong><?= hg_mobile_cd_h($ingame) ?></strong></div><?php endif; ?>
        </div>
    </section>

    <?php if (trim(strip_tags((string)($chapter['synopsis'] ?? ''))) !== ''): ?>
        <section class="hg-mobile-section hg-mobile-prose">
            <h2>Sinopsis</h2>
            <?= (string)$chapter['synopsis'] ?>
        </section>
    <?php endif; ?>

    <section class="hg-mobile-section">
        <h2>Participantes</h2>
        <?php if (empty($participants)): ?><p class="hg-mobile-muted">No hay participantes registrados.</p><?php endif; ?>
        <?php foreach (['player' => 'Protagonistas', 'npc' => 'PNJ'] as $roleKey => $roleTitle): ?>
            <?php if (empty($participantsByRole[$roleKey])) { continue; } ?>
            <details class="hg-mobile-details" open>
                <summary><?= hg_mobile_cd_h($roleTitle) ?> - <?= count($participantsByRole[$roleKey]) ?></summary>
                <div class="hg-mobile-character-list">
                    <?php foreach ($participantsByRole[$roleKey] as $participant): ?>
                        <?php hg_mobile_cd_character_card($link, $participant); ?>
                    <?php endforeach; ?>
                </div>
            </details>
        <?php endforeach; ?>
    </section>

    <?php if (!empty($soundtracks)): ?>
        <section class="hg-mobile-section">
            <h2>Temas musicales</h2>
            <div class="hg-mobile-list hg-mobile-linked-list">
                <?php foreach ($soundtracks as $track): ?>
                    <?php $youtubeId = hg_mobile_cd_youtube_id((string)($track['youtube_url'] ?? '')); ?>
                    <div>
                        <strong><?= hg_mobile_cd_h($track['context_title'] ?: $track['title']) ?></strong>
                        <span><?= hg_mobile_cd_h(trim((string)($track['title'] ?? '') . ' - ' . (string)($track['artist'] ?? ''), ' -')) ?></span>
                        <?php if ($youtubeId !== ''): ?><iframe src="https://www.youtube-nocookie.com/embed/<?= hg_mobile_cd_h($youtubeId) ?>" loading="lazy" allowfullscreen></iframe><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!empty($events)): ?>
        <section class="hg-mobile-section">
            <h2>Eventos</h2>
            <div class="hg-mobile-list hg-mobile-linked-list">
                <?php foreach ($events as $event): ?>
                    <?php
                        $eventId = (int)($event['id'] ?? 0);
                        $slug = trim((string)($event['pretty_id'] ?? '')) ?: (string)$eventId;
                        $href = '/timeline/event/' . rawurlencode($slug);
                        $date = hg_mobile_cd_date($event['event_date'] ?? '');
                    ?>
                    <div><strong><a href="<?= hg_mobile_cd_h($href) ?>"><?= hg_mobile_cd_h($event['title'] ?? 'Evento') ?></a></strong><?php if ($date !== ''): ?><span><?= hg_mobile_cd_h($date) ?></span><?php endif; ?></div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($chapterPrev || $chapterNext): ?>
        <nav class="hg-mobile-prev-next" aria-label="Navegación de capítulos">
            <?php if ($chapterPrev): ?>
                <a class="hg-mobile-prev-next-link" href="<?= hg_mobile_cd_h($chapterPrev['href']) ?>"><span>Anterior</span><strong><?= hg_mobile_cd_h($chapterPrev['label']) ?></strong><small><?= hg_mobile_cd_h($chapterPrev['title']) ?></small></a>
            <?php else: ?>
                <span class="hg-mobile-prev-next-empty" aria-hidden="true"></span>
            <?php endif; ?>
            <?php if ($chapterNext): ?>
                <a class="hg-mobile-prev-next-link hg-mobile-prev-next-link--next" href="<?= hg_mobile_cd_h($chapterNext['href']) ?>"><span>Siguiente</span><strong><?= hg_mobile_cd_h($chapterNext['label']) ?></strong><small><?= hg_mobile_cd_h($chapterNext['title']) ?></small></a>
            <?php else: ?>
                <span class="hg-mobile-prev-next-empty" aria-hidden="true"></span>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
</article>
