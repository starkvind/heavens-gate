<?php

include_once(__DIR__ . '/../../helpers/public_response.php');
include_once(__DIR__ . '/../../helpers/character_avatar.php');

$metaTitle = "Temporada | Heaven's Gate";
$metaDescription = "Ficha móvil de temporada.";
$pageSect = 'Temporadas';

if (!function_exists('hg_mobile_sd_h')) {
    function hg_mobile_sd_h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}
if (!function_exists('hg_mobile_sd_col_exists')) {
    function hg_mobile_sd_col_exists(mysqli $link, string $table, string $column): bool {
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
if (!function_exists('hg_mobile_sd_table_exists')) {
    function hg_mobile_sd_table_exists(mysqli $link, string $table): bool {
        $rs = $link->query("SHOW TABLES LIKE '" . $link->real_escape_string($table) . "'");
        if (!$rs) return false;
        $ok = $rs->num_rows > 0;
        $rs->free();
        return $ok;
    }
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
if (!function_exists('hg_mobile_sd_soundtracks')) {
    function hg_mobile_sd_soundtracks(mysqli $link, string $type, int $id): array {
        if ($id <= 0 || !hg_mobile_sd_table_exists($link, 'bridge_soundtrack_links') || !hg_mobile_sd_table_exists($link, 'dim_soundtracks')) return [];
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

$raw = trim((string)($_GET['t'] ?? ''));
$seasonId = $raw !== '' ? (int)(resolve_pretty_id($link, 'dim_seasons', $raw) ?? 0) : 0;
if ($seasonId <= 0) {
    hg_public_render_not_found('Temporada no encontrada', 'No se pudo localizar la temporada solicitada.');
    return;
}

$hasSeasonKind = hg_mobile_sd_col_exists($link, 'dim_seasons', 'season_kind');
$hasImageUrl = hg_mobile_sd_col_exists($link, 'dim_seasons', 'image_url');
$hasOpening = hg_mobile_sd_col_exists($link, 'dim_seasons', 'opening');
$hasMainCast = hg_mobile_sd_col_exists($link, 'dim_seasons', 'main_cast');
$hasChronicle = hg_mobile_sd_col_exists($link, 'dim_seasons', 'chronicle_id');
$kindExpr = $hasSeasonKind ? "COALESCE(s.season_kind, 'temporada')" : "'temporada'";
$imageExpr = $hasImageUrl ? "COALESCE(s.image_url, '')" : "''";
$openingExpr = $hasOpening ? "COALESCE(s.opening, '')" : "''";
$mainCastExpr = $hasMainCast ? "COALESCE(s.main_cast, '')" : "''";
$chronSelect = $hasChronicle ? "COALESCE(ch.name, '') AS chronicle_name, ch.id AS chronicle_id" : "'' AS chronicle_name, 0 AS chronicle_id";
$chronJoin = $hasChronicle ? "LEFT JOIN dim_chronicles ch ON ch.id = s.chronicle_id" : "";

$season = null;
$sql = "
    SELECT s.id, s.name, s.pretty_id, s.description, s.season_number,
           {$kindExpr} AS season_kind, COALESCE(s.finished, 0) AS finished,
           {$imageExpr} AS image_url, {$openingExpr} AS opening, {$mainCastExpr} AS main_cast,
           {$chronSelect}
    FROM dim_seasons s
    {$chronJoin}
    WHERE s.id = ?
    LIMIT 1
";
if ($st = $link->prepare($sql)) {
    $st->bind_param('i', $seasonId);
    $st->execute();
    $res = $st->get_result();
    $season = $res ? $res->fetch_assoc() : null;
    $st->close();
}
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

$hasChapterSeasonId = hg_mobile_sd_col_exists($link, 'dim_chapters', 'season_id');
$whereChapter = 'c.season_id = ?';
$chapters = [];
$chapterSql = "
    SELECT c.id, c.name, c.chapter_number, c.played_date, c.synopsis,
           (SELECT COUNT(DISTINCT bcc.character_id) FROM bridge_chapters_characters bcc WHERE bcc.chapter_id = c.id) AS participant_count
    FROM dim_chapters c
    WHERE {$whereChapter}
    ORDER BY c.chapter_number ASC, c.id ASC
";
if ($st = $link->prepare($chapterSql)) {
    $st->bind_param('i', $seasonId);
    $st->execute();
    $res = $st->get_result();
    while ($res && ($row = $res->fetch_assoc())) $chapters[] = $row;
    $st->close();
}
$totalPlayed = 0;
$chapterIds = [];
foreach ($chapters as $chapter) {
    $chapterIds[] = (int)($chapter['id'] ?? 0);
    if (hg_mobile_sd_date($chapter['played_date'] ?? '') !== '') $totalPlayed++;
}

$characterChronicleAnd = function_exists('hg_mobile_chronicle_exclusion_and') ? hg_mobile_chronicle_exclusion_and('p') : ' AND p.chronicle_id NOT IN (2,7) ';

$seasonCharacters = [];
if (!empty($chapterIds)) {
    $idsSql = implode(',', array_map('intval', $chapterIds));
    $hasParticipationRole = hg_mobile_sd_col_exists($link, 'bridge_chapters_characters', 'participation_role');
    $participationFilter = $hasParticipationRole
        ? " AND bcc.participation_role = 'player'"
        : " AND p.character_kind = 'pj' AND p.character_type_id = 1";
    $charSql = "
        SELECT p.id, p.name, p.image_url, p.gender, COUNT(DISTINCT bcc.chapter_id) AS played_count
        FROM bridge_chapters_characters bcc
        INNER JOIN fact_characters p ON p.id = bcc.character_id
        INNER JOIN dim_chapters c ON c.id = bcc.chapter_id
        WHERE bcc.chapter_id IN ({$idsSql})
          AND c.played_date != '0000-00-00'
          {$participationFilter}
          {$characterChronicleAnd}
        GROUP BY p.id, p.name, p.image_url, p.gender
        ORDER BY played_count DESC, p.name ASC
    ";
    if ($res = $link->query($charSql)) {
        while ($row = $res->fetch_assoc()) $seasonCharacters[] = $row;
        $res->free();
    } else {
        hg_public_log_error('mobile_season_detail', 'characters query failed: ' . mysqli_error($link));
    }
}

$soundtracks = hg_mobile_sd_soundtracks($link, 'temporada', $seasonId);
$seasonPrev = null;
$seasonNext = null;
if ($kind === 'temporada' && $number > 0) {
    $seasonKindWhere = $hasSeasonKind ? "season_kind = 'temporada' AND " : '';

    if ($st = $link->prepare("SELECT id, name, season_number FROM dim_seasons WHERE {$seasonKindWhere}season_number < ? ORDER BY season_number DESC LIMIT 1")) {
        $st->bind_param('i', $number);
        $st->execute();
        $res = $st->get_result();
        if ($res && ($row = $res->fetch_assoc())) {
            $seasonPrev = [
                'href' => hg_mobile_sd_url($link, 'dim_seasons', '/seasons', (int)$row['id']),
                'label' => 'Temporada ' . (int)$row['season_number'],
                'title' => (string)($row['name'] ?? ''),
            ];
        }
        $st->close();
    }

    if ($st = $link->prepare("SELECT id, name, season_number FROM dim_seasons WHERE {$seasonKindWhere}season_number > ? ORDER BY season_number ASC LIMIT 1")) {
        $st->bind_param('i', $number);
        $st->execute();
        $res = $st->get_result();
        if ($res && ($row = $res->fetch_assoc())) {
            $seasonNext = [
                'href' => hg_mobile_sd_url($link, 'dim_seasons', '/seasons', (int)$row['id']),
                'label' => 'Temporada ' . (int)$row['season_number'],
                'title' => (string)($row['name'] ?? ''),
            ];
        }
        $st->close();
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
