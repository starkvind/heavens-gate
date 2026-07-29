<?php

include_once(__DIR__ . '/../../helpers/public_response.php');

$metaTitle = "Temporadas | Heaven's Gate";
$metaDescription = "Archivo móvil de temporadas y capítulos.";
$pageSect = 'Temporadas';

if (!function_exists('hg_mobile_season_h')) {
    function hg_mobile_season_h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}
if (!function_exists('hg_mobile_season_col_exists')) {
    function hg_mobile_season_col_exists(mysqli $link, string $table, string $column): bool {
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

$route = trim((string)($_GET['p'] ?? 'seasons_home'));
$routeKinds = [
    'seasons_complete' => ['temporada'],
    'seasons_interludes' => ['inciso'],
    'seasons_personal' => ['historia_personal'],
    'seasons_specials' => ['especial'],
];
$wantedKinds = $routeKinds[$route] ?? ['temporada', 'inciso', 'historia_personal', 'especial'];

$hasSeasonKind = hg_mobile_season_col_exists($link, 'dim_seasons', 'season_kind');
$hasImageUrl = hg_mobile_season_col_exists($link, 'dim_seasons', 'image_url');
$kindExpr = $hasSeasonKind ? "COALESCE(s.season_kind, 'temporada')" : "'temporada'";
$imageSelect = $hasImageUrl ? "COALESCE(s.image_url, '') AS image_url" : "'' AS image_url";
$kindGroup = $hasSeasonKind ? ', s.season_kind' : '';
$imageGroup = $hasImageUrl ? ', s.image_url' : '';
$chapterJoin = 'c.season_id = s.id';

$rows = [];
$sql = "
    SELECT s.id, s.name, s.pretty_id, s.description, s.season_number,
           {$kindExpr} AS season_kind,
           COALESCE(s.finished, 0) AS finished,
           COALESCE(s.sort_order, 999999) AS sort_order,
           {$imageSelect},
           COUNT(c.id) AS chapter_count
    FROM dim_seasons s
    LEFT JOIN dim_chapters c ON {$chapterJoin}
    GROUP BY s.id, s.name, s.pretty_id, s.description, s.season_number{$kindGroup}, s.finished, s.sort_order{$imageGroup}
    ORDER BY
        CASE {$kindExpr}
            WHEN 'temporada' THEN 1
            WHEN 'inciso' THEN 2
            WHEN 'historia_personal' THEN 3
            WHEN 'especial' THEN 4
            ELSE 99
        END,
        COALESCE(s.sort_order, 999999), s.season_number, s.name
";
if ($res = $link->query($sql)) {
    while ($row = $res->fetch_assoc()) {
        $kind = trim((string)($row['season_kind'] ?? 'temporada')) ?: 'temporada';
        if (in_array($kind, $wantedKinds, true)) $rows[] = $row;
    }
    $res->free();
} else {
    hg_public_log_error('mobile_seasons_list', 'query failed: ' . mysqli_error($link));
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



