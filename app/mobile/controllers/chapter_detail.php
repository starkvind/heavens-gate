<?php

include_once(__DIR__ . '/../../helpers/public_response.php');
include_once(__DIR__ . '/../../helpers/character_avatar.php');

$metaTitle = "Capitulo | Heaven's Gate";
$metaDescription = "Ficha móvil de capitulo.";
$pageSect = 'Capítulos';

if (!function_exists('hg_mobile_cd_h')) {
    function hg_mobile_cd_h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}
if (!function_exists('hg_mobile_cd_col_exists')) {
    function hg_mobile_cd_col_exists(mysqli $link, string $table, string $column): bool {
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
        return $cache[$key] = $ok;
    }
}
if (!function_exists('hg_mobile_cd_table_exists')) {
    function hg_mobile_cd_table_exists(mysqli $link, string $table): bool {
        $rs = $link->query("SHOW TABLES LIKE '" . $link->real_escape_string($table) . "'");
        if (!$rs) return false;
        $ok = $rs->num_rows > 0;
        $rs->free();
        return $ok;
    }
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
if (!function_exists('hg_mobile_cd_resolve_chapter_id')) {
    function hg_mobile_cd_resolve_chapter_id(mysqli $link, string $raw): int {
        $raw = trim(rawurldecode($raw));
        if ($raw === '') return 0;
        $resolved = resolve_pretty_id($link, 'dim_chapters', $raw);
        if ($resolved !== null && (int)$resolved > 0) return (int)$resolved;
        if (preg_match('/^\d+$/', $raw)) return (int)$raw;
        if (!function_exists('slugify_pretty_id')) return 0;

        $hasPretty = hg_mobile_cd_col_exists($link, 'dim_chapters', 'pretty_id');
        $prettySelect = $hasPretty ? 'pretty_id' : "'' AS pretty_id";
        $sql = "SELECT id, name, {$prettySelect} FROM dim_chapters";
        if ($res = $link->query($sql)) {
            while ($row = $res->fetch_assoc()) {
                $id = (int)($row['id'] ?? 0);
                if ($id <= 0) continue;
                $pretty = trim((string)($row['pretty_id'] ?? ''));
                $nameSlug = slugify_pretty_id((string)($row['name'] ?? ''));
                if ($pretty === $raw || $nameSlug === $raw) {
                    $res->free();
                    return $id;
                }
            }
            $res->free();
        }
        return 0;
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

if (!function_exists('hg_mobile_cd_soundtracks')) {
    function hg_mobile_cd_soundtracks(mysqli $link, string $type, int $id): array {
        if ($id <= 0 || !hg_mobile_cd_table_exists($link, 'bridge_soundtrack_links') || !hg_mobile_cd_table_exists($link, 'dim_soundtracks')) return [];
        $rows = [];
        $sql = "
            SELECT bs.context_title, bs.title, bs.artist, bs.youtube_url
            FROM bridge_soundtrack_links br
            INNER JOIN dim_soundtracks bs ON bs.id = br.soundtrack_id
            WHERE br.object_type = ? AND br.object_id = ?
            ORDER BY bs.added_at DESC, bs.id DESC
        ";
        if ($st = $link->prepare($sql)) {
            $st->bind_param('si', $type, $id);
            $st->execute();
            $res = $st->get_result();
            while ($res && ($row = $res->fetch_assoc())) $rows[] = $row;
            $st->close();
        }
        return $rows;
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

$raw = trim((string)($_GET['t'] ?? ''));
$chapterId = hg_mobile_cd_resolve_chapter_id($link, $raw);
if ($chapterId <= 0) {
    hg_public_render_not_found('Capitulo no encontrado', 'No se pudo localizar el capitulo solicitado.');
    return;
}

$hasSeasonId = hg_mobile_cd_col_exists($link, 'dim_chapters', 'season_id');
$hasSeasonKind = hg_mobile_cd_col_exists($link, 'dim_seasons', 'season_kind');
$hasChapterImage = hg_mobile_cd_col_exists($link, 'dim_chapters', 'image_url');
$seasonJoin = 'LEFT JOIN dim_seasons s ON s.id = c.season_id';
$kindExpr = $hasSeasonKind ? "COALESCE(s.season_kind, 'temporada')" : "'temporada'";
$imageExpr = $hasChapterImage ? "COALESCE(c.image_url, '')" : "''";
$inGameExpr = hg_mobile_cd_col_exists($link, 'dim_chapters', 'in_game_date') ? "c.in_game_date" : "''";

$chapter = null;
$sql = "
    SELECT c.id, c.name, c.chapter_number,
           c.synopsis, c.played_date, {$inGameExpr} AS in_game_date, {$imageExpr} AS image_url,
           COALESCE(s.id, 0) AS season_id, COALESCE(s.name, '') AS season_name,
           COALESCE(s.season_number, 0) AS season_number,
           {$kindExpr} AS season_kind
    FROM dim_chapters c
    {$seasonJoin}
    WHERE c.id = ?
    LIMIT 1
";
if ($st = $link->prepare($sql)) {
    $st->bind_param('i', $chapterId);
    $st->execute();
    $res = $st->get_result();
    $chapter = $res ? $res->fetch_assoc() : null;
    $st->close();
}
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

$hasRole = hg_mobile_cd_col_exists($link, 'bridge_chapters_characters', 'participation_role');
$roleExpr = $hasRole ? "COALESCE(NULLIF(TRIM(bcc.participation_role), ''), 'npc')" : "CASE WHEN p.character_kind = 'pj' THEN 'player' ELSE 'npc' END";
$characterChronicleAnd = function_exists('hg_mobile_chronicle_exclusion_and') ? hg_mobile_chronicle_exclusion_and('p') : ' AND p.chronicle_id NOT IN (2,7) ';

$participants = [];
$participantSql = "
    SELECT p.id, p.name, p.image_url, p.gender, {$roleExpr} AS participation_role
    FROM bridge_chapters_characters bcc
    INNER JOIN fact_characters p ON p.id = bcc.character_id
    WHERE bcc.chapter_id = ? {$characterChronicleAnd}
    ORDER BY CASE WHEN {$roleExpr} = 'player' THEN 0 ELSE 1 END, p.name ASC
";
if ($st = $link->prepare($participantSql)) {
    $st->bind_param('i', $chapterId);
    $st->execute();
    $res = $st->get_result();
    while ($res && ($row = $res->fetch_assoc())) {
        $role = strtolower(trim((string)($row['participation_role'] ?? 'npc')));
        $row['role_label'] = $role === 'player' ? 'Protagonista' : 'PNJ';
        $participants[] = $row;
    }
    $st->close();
}

$participantsByRole = [
    'player' => [],
    'npc' => [],
];
foreach ($participants as $participant) {
    $role = strtolower(trim((string)($participant['participation_role'] ?? 'npc')));
    $participantsByRole[$role === 'player' ? 'player' : 'npc'][] = $participant;
}

$soundtracks = hg_mobile_cd_soundtracks($link, 'episodio', $chapterId);
$chapterPrev = null;
$chapterNext = null;
$chapterSeasonId = (int)($chapter['season_id'] ?? 0);
if ($chapterSeasonId > 0 && $chapterNumber > 0) {
    $prevChapterNumber = $chapterNumber - 1;
    $nextChapterNumber = $chapterNumber + 1;
    if ($st = $link->prepare('SELECT id, name, chapter_number FROM dim_chapters WHERE season_id = ? AND chapter_number IN (?, ?) ORDER BY chapter_number ASC')) {
        $st->bind_param('iii', $chapterSeasonId, $prevChapterNumber, $nextChapterNumber);
        $st->execute();
        $res = $st->get_result();
        while ($res && ($row = $res->fetch_assoc())) {
            $rowNumber = (int)($row['chapter_number'] ?? 0);
            $target = [
                'href' => hg_mobile_cd_url($link, 'dim_chapters', '/chapters', (int)$row['id']),
                'label' => $kind === 'temporada' ? sprintf('%dx%02d', $seasonNumber, $rowNumber) : sprintf('%02d', $rowNumber),
                'title' => (string)($row['name'] ?? ''),
            ];
            if ($rowNumber === $prevChapterNumber) $chapterPrev = $target;
            if ($rowNumber === $nextChapterNumber) $chapterNext = $target;
        }
        $st->close();
    }

    if ($kind === 'temporada') {
        $minChapter = 0;
        $maxChapter = 0;
        if ($st = $link->prepare('SELECT MIN(chapter_number) AS min_ch, MAX(chapter_number) AS max_ch FROM dim_chapters WHERE season_id = ?')) {
            $st->bind_param('i', $chapterSeasonId);
            $st->execute();
            $res = $st->get_result();
            if ($res && ($row = $res->fetch_assoc())) {
                $minChapter = (int)($row['min_ch'] ?? 0);
                $maxChapter = (int)($row['max_ch'] ?? 0);
            }
            $st->close();
        }

        $seasonKindWhere = $hasSeasonKind ? "season_kind = 'temporada' AND " : '';
        if (!$chapterPrev && $minChapter > 0 && $chapterNumber === $minChapter && $seasonNumber > 0) {
            if ($st = $link->prepare("SELECT id, name, season_number FROM dim_seasons WHERE {$seasonKindWhere}season_number < ? ORDER BY season_number DESC LIMIT 1")) {
                $st->bind_param('i', $seasonNumber);
                $st->execute();
                $res = $st->get_result();
                if ($res && ($row = $res->fetch_assoc())) {
                    $chapterPrev = [
                        'href' => hg_mobile_cd_url($link, 'dim_seasons', '/seasons', (int)$row['id']),
                        'label' => 'Temporada ' . (int)$row['season_number'],
                        'title' => (string)($row['name'] ?? ''),
                    ];
                }
                $st->close();
            }
        }

        if (!$chapterNext && $maxChapter > 0 && $chapterNumber === $maxChapter && $seasonNumber > 0) {
            if ($st = $link->prepare("SELECT id, name, season_number FROM dim_seasons WHERE {$seasonKindWhere}season_number > ? ORDER BY season_number ASC LIMIT 1")) {
                $st->bind_param('i', $seasonNumber);
                $st->execute();
                $res = $st->get_result();
                if ($res && ($row = $res->fetch_assoc())) {
                    $chapterNext = [
                        'href' => hg_mobile_cd_url($link, 'dim_seasons', '/seasons', (int)$row['id']),
                        'label' => 'Temporada ' . (int)$row['season_number'],
                        'title' => (string)($row['name'] ?? ''),
                    ];
                }
                $st->close();
            }
        }
    }
}

$events = [];
if (hg_mobile_cd_table_exists($link, 'bridge_timeline_events_chapters') && hg_mobile_cd_table_exists($link, 'fact_timeline_events')) {
    $hasEventPretty = hg_mobile_cd_col_exists($link, 'fact_timeline_events', 'pretty_id');
    $prettyExpr = $hasEventPretty ? 'e.pretty_id' : "''";
    $eventSql = "
        SELECT e.id, {$prettyExpr} AS pretty_id, e.title, e.event_date
        FROM bridge_timeline_events_chapters b
        INNER JOIN fact_timeline_events e ON e.id = b.event_id
        WHERE b.chapter_id = ?
        ORDER BY e.event_date ASC, e.id ASC
        LIMIT 20
    ";
    if ($st = $link->prepare($eventSql)) {
        $st->bind_param('i', $chapterId);
        $st->execute();
        $res = $st->get_result();
        while ($res && ($row = $res->fetch_assoc())) $events[] = $row;
        $st->close();
    }
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






