<?php

include_once(__DIR__ . '/../../helpers/public_response.php');
include_once(__DIR__ . '/../../domains/chapters/queries.php');

$metaTitle = "Capítulos | Heaven's Gate";
$metaDescription = "Listado móvil de capítulos.";
$pageSect = 'Capítulos';

if (!function_exists('hg_mobile_chl_h')) {
    function hg_mobile_chl_h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
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

$q = hg_request_query_param($hgRequest, 'q');
$seasonFilter = max(0, (int)hg_request_query_param($hgRequest, 'season'));

$seasonRows = hg_chapters_fetch_season_options($link) ?? [];
$rows = hg_chapters_fetch_mobile_list($link, $q, $seasonFilter);
if ($rows === null) {
    hg_public_log_error('mobile_chapters_list', 'query failed: ' . mysqli_error($link));
    $rows = [];
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