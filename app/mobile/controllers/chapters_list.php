<?php

include_once(__DIR__ . '/../../helpers/public_response.php');

$metaTitle = "Capítulos | Heaven's Gate";
$metaDescription = "Listado móvil de capítulos.";
$pageSect = 'Capítulos';

if (!function_exists('hg_mobile_chl_h')) {
    function hg_mobile_chl_h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}
if (!function_exists('hg_mobile_chl_col_exists')) {
    function hg_mobile_chl_col_exists(mysqli $link, string $table, string $column): bool {
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
if (!function_exists('hg_mobile_chl_url')) {
    function hg_mobile_chl_url(mysqli $link, string $table, string $base, int $id): string {
        return $id > 0 && function_exists('pretty_url') ? pretty_url($link, $table, $base, $id) : rtrim($base, '/') . '/' . $id;
    }
}
if (!function_exists('hg_mobile_chl_date')) {
    function hg_mobile_chl_date(?string $raw): string {
        $raw = trim((string)$raw);
        if ($raw === '' || $raw === '0000-00-00') return '';
        $ts = strtotime($raw);
        return $ts !== false ? date('d-m-Y', $ts) : $raw;
    }
}
if (!function_exists('hg_mobile_chl_excerpt')) {
    function hg_mobile_chl_excerpt(string $text, int $max = 120): string {
        $text = trim(strip_tags($text));
        if ($text === '') return '';
        if (function_exists('mb_strlen') && function_exists('mb_substr')) return mb_strlen($text, 'UTF-8') > $max ? mb_substr($text, 0, $max, 'UTF-8') . '...' : $text;
        return strlen($text) > $max ? substr($text, 0, $max) . '...' : $text;
    }
}

if (!isset($link) || !($link instanceof mysqli)) {
    hg_public_log_error('mobile_chapters_list', 'missing DB connection');
    hg_public_render_error('Capítulos no disponibles', 'No se pudo cargar el listado.');
    return;
}

$q = trim((string)($_GET['q'] ?? ''));
$seasonFilter = filter_input(INPUT_GET, 'season', FILTER_VALIDATE_INT) ?: 0;

$hasSeasonId = hg_mobile_chl_col_exists($link, 'dim_chapters', 'season_id');
$hasSeasonKind = hg_mobile_chl_col_exists($link, 'dim_seasons', 'season_kind');
$kindExpr = $hasSeasonKind ? "COALESCE(s.season_kind, 'temporada')" : "'temporada'";
$seasonJoin = 'LEFT JOIN dim_seasons s ON s.id = c.season_id';
$seasonWhere = 'c.season_id';

$seasonRows = [];
if ($res = $link->query("SELECT id, name, season_number FROM dim_seasons ORDER BY COALESCE(sort_order, 999999), season_number, name")) {
    while ($row = $res->fetch_assoc()) $seasonRows[] = $row;
    $res->free();
}

$where = ['1=1'];
if ($q !== '') {
    $like = mysqli_real_escape_string($link, '%' . $q . '%');
    $where[] = "(c.name LIKE '{$like}' OR c.synopsis LIKE '{$like}')";
}
if ($seasonFilter > 0) {
    $where[] = $seasonWhere . ' = ' . (int)$seasonFilter;
}
$whereSql = implode(' AND ', $where);

$rows = [];
$sql = "
    SELECT c.id, c.name, c.chapter_number, c.played_date, c.synopsis,
           COALESCE(s.id, 0) AS season_id, COALESCE(s.name, '') AS season_name,
           COALESCE(s.season_number, 0) AS season_number,
           {$kindExpr} AS season_kind,
           COALESCE(s.sort_order, 999999) AS season_sort_order,
           (SELECT COUNT(DISTINCT bcc.character_id) FROM bridge_chapters_characters bcc WHERE bcc.chapter_id = c.id) AS participant_count
    FROM dim_chapters c
    {$seasonJoin}
    WHERE {$whereSql}
    ORDER BY COALESCE(s.sort_order, 999999), COALESCE(s.season_number, 0), c.chapter_number, c.id
";
if ($res = $link->query($sql)) {
    while ($row = $res->fetch_assoc()) $rows[] = $row;
    $res->free();
} else {
    hg_public_log_error('mobile_chapters_list', 'query failed: ' . mysqli_error($link));
}
?>

<section class="hg-mobile-section">
    <h1>Capítulos</h1>
    <form class="hg-mobile-filterbar" action="/chapters" method="get">
        <label>
            <span>Buscar</span>
            <input type="search" name="q" value="<?= hg_mobile_chl_h($q) ?>" placeholder="Título o sinopsis">
        </label>
        <label>
            <span>Temporada</span>
            <select name="season">
                <option value="0">Todas</option>
                <?php foreach ($seasonRows as $season): ?>
                    <?php $sid = (int)($season['id'] ?? 0); ?>
                    <option value="<?= $sid ?>"<?= $sid === $seasonFilter ? ' selected' : '' ?>><?= hg_mobile_chl_h($season['name'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit">Filtrar</button>
    </form>
    <p class="hg-mobile-muted"><?= number_format(count($rows), 0, ',', '.') ?> capítulos</p>
</section>

<section class="hg-mobile-section">
    <div class="hg-mobile-card-list" data-mobile-paginated data-mobile-search="1" data-page-size="20" data-search-placeholder="Buscar capítulos">
        <?php if (empty($rows)): ?><p class="hg-mobile-muted">No hay capítulos para este filtro.</p><?php endif; ?>
        <?php foreach ($rows as $row): ?>
            <?php
                $id = (int)($row['id'] ?? 0);
                $href = hg_mobile_chl_url($link, 'dim_chapters', '/chapters', $id);
                $kind = (string)($row['season_kind'] ?? 'temporada');
                $code = $kind === 'temporada' ? sprintf('%dx%02d', (int)$row['season_number'], (int)$row['chapter_number']) : sprintf('%02d', (int)$row['chapter_number']);
                $date = hg_mobile_chl_date($row['played_date'] ?? '');
                $excerpt = hg_mobile_chl_excerpt((string)($row['synopsis'] ?? ''));
            ?>
            <a class="hg-mobile-card" href="<?= hg_mobile_chl_h($href) ?>" data-mobile-item data-mobile-search="<?= hg_mobile_chl_h($code . ' ' . (string)($row['name'] ?? '') . ' ' . (string)($row['season_name'] ?? '') . ' ' . strip_tags((string)($row['synopsis'] ?? ''))) ?>">
                <strong><?= hg_mobile_chl_h($code . ' - ' . (string)($row['name'] ?? '')) ?></strong>
                <span><?= hg_mobile_chl_h($row['season_name'] ?? '') ?><?= $date !== '' ? ' - ' . hg_mobile_chl_h($date) : '' ?> - <?= (int)($row['participant_count'] ?? 0) ?> participantes</span>
                <?php if ($excerpt !== ''): ?><span><?= hg_mobile_chl_h($excerpt) ?></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
</section>
