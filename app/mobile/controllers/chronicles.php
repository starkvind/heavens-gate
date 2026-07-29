<?php

include_once(__DIR__ . '/../../helpers/public_response.php');
include_once(__DIR__ . '/../../helpers/character_avatar.php');

$metaTitle = "Crónicas | Heaven's Gate";
$metaDescription = 'Archivo móvil de crónicas.';
$pageSect = 'Crónicas';

if (!function_exists('hg_mobile_chr_h')) {
    function hg_mobile_chr_h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}
if (!function_exists('hg_mobile_chr_col_exists')) {
    function hg_mobile_chr_col_exists(mysqli $link, string $table, string $column): bool {
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
if (!function_exists('hg_mobile_chr_table_exists')) {
    function hg_mobile_chr_table_exists(mysqli $link, string $table): bool {
        static $cache = [];
        if (isset($cache[$table])) return $cache[$table];
        $rs = $link->query("SHOW TABLES LIKE '" . $link->real_escape_string($table) . "'");
        if (!$rs) return $cache[$table] = false;
        $ok = $rs->num_rows > 0;
        $rs->free();
        return $cache[$table] = $ok;
    }
}
if (!function_exists('hg_mobile_chr_int_csv')) {
    function hg_mobile_chr_int_csv($csv): string {
        $parts = preg_split('/\s*,\s*/', trim((string)$csv));
        $ids = [];
        foreach ($parts as $part) {
            if ($part !== '' && preg_match('/^\d+$/', $part)) $ids[] = (string)(int)$part;
        }
        return implode(',', array_values(array_unique($ids)));
    }
}
if (!function_exists('hg_mobile_chr_excerpt')) {
    function hg_mobile_chr_excerpt(string $text, int $max = 150): string {
        $text = trim(strip_tags($text));
        if ($text === '') return '';
        if (function_exists('mb_strlen') && function_exists('mb_substr')) return mb_strlen($text, 'UTF-8') > $max ? mb_substr($text, 0, $max, 'UTF-8') . '...' : $text;
        return strlen($text) > $max ? substr($text, 0, $max) . '...' : $text;
    }
}
if (!function_exists('hg_mobile_chr_render_text')) {
    function hg_mobile_chr_render_text(string $text): string {
        $text = trim($text);
        if ($text === '') return '';
        if (preg_match('/<[^>]+>/', $text)) return $text;
        return nl2br(hg_mobile_chr_h($text));
    }
}
if (!function_exists('hg_mobile_chr_url')) {
    function hg_mobile_chr_url(mysqli $link, string $table, string $base, int $id): string {
        return $id > 0 && function_exists('pretty_url') ? pretty_url($link, $table, $base, $id) : rtrim($base, '/') . '/' . $id;
    }
}
if (!function_exists('hg_mobile_chr_image_route')) {
    function hg_mobile_chr_image_route(string $prettyId = '', int $id = 0): string {
        $slug = trim($prettyId) !== '' ? trim($prettyId) : (string)$id;
        return '/chronicles/' . rawurlencode($slug) . '/image';
    }
}
if (!function_exists('hg_mobile_chr_kind_label')) {
    function hg_mobile_chr_kind_label(string $kind, int $number): string {
        if ($kind === 'historia_personal') return 'Historia personal';
        if ($kind === 'especial') return 'Especial';
        if ($kind === 'inciso') {
            if ($number >= 100 && $number < 200) $number -= 100;
            return 'Inciso ' . ($number > 0 ? $number : '?');
        }
        return 'Temporada ' . ($number > 0 ? $number : '?');
    }
}
if (!function_exists('hg_mobile_chr_count_label')) {
    function hg_mobile_chr_count_label(int $count, string $singular, string $plural): string {
        if ($count <= 0) return '';
        return number_format($count, 0, ',', '.') . ' ' . ($count === 1 ? $singular : $plural);
    }
}
if (!function_exists('hg_mobile_chr_resolve_id')) {
    function hg_mobile_chr_resolve_id(mysqli $link, string $raw): int {
        $raw = trim(rawurldecode($raw));
        if ($raw === '') return 0;
        $resolved = resolve_pretty_id($link, 'dim_chronicles', $raw);
        if ($resolved !== null && (int)$resolved > 0) return (int)$resolved;
        if (preg_match('/^\d+$/', $raw)) return (int)$raw;
        if (!function_exists('slugify_pretty_id')) return 0;
        $prettyExpr = hg_mobile_chr_col_exists($link, 'dim_chronicles', 'pretty_id') ? 'pretty_id' : "'' AS pretty_id";
        if ($res = $link->query("SELECT id, name, {$prettyExpr} FROM dim_chronicles")) {
            while ($row = $res->fetch_assoc()) {
                $id = (int)($row['id'] ?? 0);
                if ($id <= 0) continue;
                if (trim((string)($row['pretty_id'] ?? '')) === $raw || slugify_pretty_id((string)($row['name'] ?? '')) === $raw) {
                    $res->free();
                    return $id;
                }
            }
            $res->free();
        }
        return 0;
    }
}
if (!function_exists('hg_mobile_chr_character_card')) {
    function hg_mobile_chr_character_card(mysqli $link, array $character): void {
        $id = (int)($character['id'] ?? 0);
        $href = hg_mobile_chr_url($link, 'fact_characters', '/characters', $id);
        $avatar = hg_character_avatar_url((string)($character['image_url'] ?? ''), (string)($character['gender'] ?? ''));
        ?>
        <a class="hg-mobile-character-card" href="<?= hg_mobile_chr_h($href) ?>" data-mobile-item data-mobile-search="<?= hg_mobile_chr_h((string)($character['name'] ?? '') . ' ' . (string)($character['meta'] ?? '')) ?>">
            <?php if ($avatar !== ''): ?><img src="<?= hg_mobile_chr_h($avatar) ?>" alt=""><?php else: ?><span class="hg-mobile-character-avatar" aria-hidden="true"></span><?php endif; ?>
            <span class="hg-mobile-character-main">
                <strong><?= hg_mobile_chr_h($character['name'] ?? '') ?></strong>
                <span><?= hg_mobile_chr_h($character['meta'] ?? '') ?></span>
            </span>
        </a>
        <?php
    }
}

if (!isset($link) || !($link instanceof mysqli)) {
    hg_public_log_error('mobile_chronicles', 'missing DB connection');
    hg_public_render_error('Crónicas no disponibles', 'No se pudo cargar el archivo.');
    return;
}

$excludeChronicles = isset($excludeChronicles) ? preg_replace('/[^0-9,]/', '', (string)$excludeChronicles) : '';
$raw = trim((string)($_GET['t'] ?? ''));
$chronicleId = $raw !== '' ? hg_mobile_chr_resolve_id($link, $raw) : 0;

$hasPretty = hg_mobile_chr_col_exists($link, 'dim_chronicles', 'pretty_id');
$hasDescription = hg_mobile_chr_col_exists($link, 'dim_chronicles', 'description');
$hasSortOrder = hg_mobile_chr_col_exists($link, 'dim_chronicles', 'sort_order');
$hasImageUrl = hg_mobile_chr_col_exists($link, 'dim_chronicles', 'image_url');
$hasSeasonChronicle = hg_mobile_chr_col_exists($link, 'dim_seasons', 'chronicle_id');
$hasCharacterChronicle = hg_mobile_chr_col_exists($link, 'fact_characters', 'chronicle_id');

$prettyExpr = $hasPretty ? "COALESCE(ch.pretty_id, '')" : "''";
$descExpr = $hasDescription ? "COALESCE(ch.description, '')" : "''";
$sortExpr = $hasSortOrder ? "COALESCE(ch.sort_order, 999999)" : "999999";
$imageExpr = $hasImageUrl ? "COALESCE(ch.image_url, '')" : "''";
$seasonCountExpr = $hasSeasonChronicle ? '(SELECT COUNT(*) FROM dim_seasons s WHERE s.chronicle_id = ch.id)' : '0';
$characterCountExpr = $hasCharacterChronicle ? '(SELECT COUNT(*) FROM fact_characters fc WHERE fc.chronicle_id = ch.id)' : '0';

if ($chronicleId <= 0) {
    $chronicles = [];
    $whereExclude = $excludeChronicles !== '' ? "WHERE ch.id NOT IN ({$excludeChronicles})" : '';
    $sql = "
        SELECT ch.id, {$prettyExpr} AS pretty_id, ch.name, {$descExpr} AS description,
               {$imageExpr} AS image_url, {$seasonCountExpr} AS season_count,
               {$characterCountExpr} AS character_count, {$sortExpr} AS sort_order
        FROM dim_chronicles ch
        {$whereExclude}
        ORDER BY sort_order ASC, ch.name ASC
    ";
    if ($res = $link->query($sql)) {
        while ($row = $res->fetch_assoc()) $chronicles[] = $row;
        $res->free();
    } else {
        hg_public_log_error('mobile_chronicles', 'list query failed: ' . mysqli_error($link));
    }
    ?>
    <section class="hg-mobile-section">
        <h1>Crónicas</h1>
        <p class="hg-mobile-muted"><?= number_format(count($chronicles), 0, ',', '.') ?> crónicas</p>
    </section>
    <section class="hg-mobile-section">
        <div class="hg-mobile-card-list">
            <?php if (empty($chronicles)): ?><p class="hg-mobile-muted">No hay crónicas disponibles.</p><?php endif; ?>
            <?php foreach ($chronicles as $chronicle): ?>
                <?php
                    $id = (int)($chronicle['id'] ?? 0);
                    $pretty = (string)($chronicle['pretty_id'] ?? '');
                    $href = hg_mobile_chr_url($link, 'dim_chronicles', '/chronicles', $id);
                    $desc = hg_mobile_chr_excerpt((string)($chronicle['description'] ?? ''));
                    $seasonLabel = hg_mobile_chr_count_label((int)($chronicle['season_count'] ?? 0), 'temporada', 'temporadas');
                    $characterLabel = hg_mobile_chr_count_label((int)($chronicle['character_count'] ?? 0), 'personaje', 'personajes');
                ?>
                <a class="hg-mobile-card" href="<?= hg_mobile_chr_h($href) ?>">
                    <img src="<?= hg_mobile_chr_h(hg_mobile_chr_image_route($pretty, $id)) ?>" alt="" loading="lazy">
                    <strong><?= hg_mobile_chr_h($chronicle['name'] ?? '') ?></strong>
                    <?php if ($desc !== ''): ?><span><?= hg_mobile_chr_h($desc) ?></span><?php endif; ?>
                    <?php if ($seasonLabel !== '' || $characterLabel !== ''): ?><small class="hg-mobile-muted"><?= hg_mobile_chr_h(trim($seasonLabel . ' - ' . $characterLabel, ' -')) ?></small><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
    return;
}

$chronicle = null;
$sql = "
    SELECT ch.id, {$prettyExpr} AS pretty_id, ch.name, {$descExpr} AS description,
           {$imageExpr} AS image_url, {$seasonCountExpr} AS season_count,
           {$characterCountExpr} AS character_count, {$sortExpr} AS sort_order
    FROM dim_chronicles ch
    WHERE ch.id = ?
    LIMIT 1
";
if ($st = $link->prepare($sql)) {
    $st->bind_param('i', $chronicleId);
    $st->execute();
    $res = $st->get_result();
    $chronicle = $res ? $res->fetch_assoc() : null;
    $st->close();
}
if (!$chronicle) {
    hg_public_render_not_found('Cronica no encontrada', 'No se pudo localizar la cronica solicitada.');
    return;
}

$name = (string)($chronicle['name'] ?? 'Cronica');
$pretty = (string)($chronicle['pretty_id'] ?? '');
$description = (string)($chronicle['description'] ?? '');
$metaTitle = $name . " | Crónicas | Heaven's Gate";
$metaDescription = hg_mobile_chr_excerpt($description, 160);

$seasonRows = [];
if ($hasSeasonChronicle) {
    $hasSeasonPretty = hg_mobile_chr_col_exists($link, 'dim_seasons', 'pretty_id');
    $hasSeasonDesc = hg_mobile_chr_col_exists($link, 'dim_seasons', 'description');
    $hasSeasonKind = hg_mobile_chr_col_exists($link, 'dim_seasons', 'season_kind');
    $hasSeasonFinished = hg_mobile_chr_col_exists($link, 'dim_seasons', 'finished');
    $hasSeasonSort = hg_mobile_chr_col_exists($link, 'dim_seasons', 'sort_order');
    $hasChapterSeasonId = hg_mobile_chr_col_exists($link, 'dim_chapters', 'season_id');
    $seasonPrettyExpr = $hasSeasonPretty ? "COALESCE(s.pretty_id, '')" : "''";
    $seasonDescExpr = $hasSeasonDesc ? "COALESCE(s.description, '')" : "''";
    $seasonKindExpr = $hasSeasonKind ? "COALESCE(s.season_kind, 'temporada')" : "'temporada'";
    $seasonFinishedExpr = $hasSeasonFinished ? "COALESCE(s.finished, 0)" : "0";
    $seasonSortExpr = $hasSeasonSort ? "COALESCE(s.sort_order, 999999)" : "999999";
    $chapterCountExpr = $hasChapterSeasonId ? '(SELECT COUNT(*) FROM dim_chapters c WHERE c.season_id = s.id)' : '0';
    $seasonSql = "
        SELECT s.id, {$seasonPrettyExpr} AS pretty_id, s.name, {$seasonDescExpr} AS description,
               s.season_number, {$seasonKindExpr} AS season_kind, {$seasonFinishedExpr} AS finished,
               {$seasonSortExpr} AS sort_order, {$chapterCountExpr} AS chapter_count
        FROM dim_seasons s
        WHERE s.chronicle_id = ?
        ORDER BY
            CASE {$seasonKindExpr}
                WHEN 'temporada' THEN 1
                WHEN 'inciso' THEN 2
                WHEN 'historia_personal' THEN 3
                WHEN 'especial' THEN 4
                ELSE 99
            END ASC,
            sort_order ASC, s.season_number ASC, s.name ASC
    ";
    if ($st = $link->prepare($seasonSql)) {
        $st->bind_param('i', $chronicleId);
        $st->execute();
        $res = $st->get_result();
        while ($res && ($row = $res->fetch_assoc())) $seasonRows[] = $row;
        $st->close();
    }
}

$members = [];
if ($hasCharacterChronicle) {
    $hasCharImage = hg_mobile_chr_col_exists($link, 'fact_characters', 'image_url');
    $hasCharGender = hg_mobile_chr_col_exists($link, 'fact_characters', 'gender');
    $hasCharStatus = hg_mobile_chr_col_exists($link, 'fact_characters', 'status_id') && hg_mobile_chr_table_exists($link, 'dim_character_status');
    $imageSelect = $hasCharImage ? "COALESCE(p.image_url, '')" : "''";
    $genderSelect = $hasCharGender ? "COALESCE(p.gender, '')" : "''";
    $statusJoin = $hasCharStatus ? 'LEFT JOIN dim_character_status dcs ON dcs.id = p.status_id' : '';
    $statusSelect = $hasCharStatus ? "COALESCE(dcs.label, '')" : "''";
    $memberSql = "
        SELECT p.id, p.name, {$imageSelect} AS image_url, {$genderSelect} AS gender,
               {$statusSelect} AS status
        FROM fact_characters p
        {$statusJoin}
        WHERE p.chronicle_id = ?
        ORDER BY p.name ASC, p.id ASC
    ";
    if ($st = $link->prepare($memberSql)) {
        $st->bind_param('i', $chronicleId);
        $st->execute();
        $res = $st->get_result();
        while ($res && ($row = $res->fetch_assoc())) {
            $row['meta'] = trim((string)($row['status'] ?? '')) ?: 'Personaje';
            $members[] = $row;
        }
        $st->close();
    }
}
?>
<article class="hg-mobile-bio">
    <nav class="hg-mobile-local-nav"><a href="/chronicles?view=mobile">Volver a crónicas</a></nav>

    <section class="hg-mobile-section">
        <img class="hg-mobile-hero-image" src="<?= hg_mobile_chr_h(hg_mobile_chr_image_route($pretty, $chronicleId)) ?>" alt="" loading="lazy">
        <h1><?= hg_mobile_chr_h($name) ?></h1>
        <div class="hg-mobile-fact-grid">
            <div><span>Temporadas</span><strong><?= number_format(count($seasonRows), 0, ',', '.') ?></strong></div>
            <div><span>Personajes</span><strong><?= number_format(count($members), 0, ',', '.') ?></strong></div>
        </div>
    </section>

    <section class="hg-mobile-section hg-mobile-prose">
        <h2>Descripción</h2>
        <?= hg_mobile_chr_render_text($description) ?: '<p>Sin descripción.</p>' ?>
    </section>

    <section class="hg-mobile-section">
        <h2>Temporadas vinculadas</h2>
        <div class="hg-mobile-list hg-mobile-linked-list">
            <?php if (empty($seasonRows)): ?><p class="hg-mobile-muted">No hay temporadas vinculadas.</p><?php endif; ?>
            <?php foreach ($seasonRows as $season): ?>
                <?php
                    $sid = (int)($season['id'] ?? 0);
                    $href = hg_mobile_chr_url($link, 'dim_seasons', '/seasons', $sid);
                    $kind = (string)($season['season_kind'] ?? 'temporada');
                    $label = hg_mobile_chr_kind_label($kind, (int)($season['season_number'] ?? 0));
                    $desc = hg_mobile_chr_excerpt((string)($season['description'] ?? ''), 110);
                ?>
                <div>
                    <strong><a href="<?= hg_mobile_chr_h($href) ?>"><?= hg_mobile_chr_h(($season['name'] ?? 'Temporada')) ?></a></strong>
                    <span><?= hg_mobile_chr_h($label) ?> - <?= (int)($season['chapter_count'] ?? 0) ?> capítulos</span>
                    <?php if ($desc !== ''): ?><span><?= hg_mobile_chr_h($desc) ?></span><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="hg-mobile-section">
        <h2>Personajes asociados</h2>
        <?php if (empty($members)): ?><p class="hg-mobile-muted">No hay personajes asociados.</p><?php endif; ?>
        <div class="hg-mobile-character-list" data-mobile-paginated data-mobile-search="1" data-page-size="20" data-search-placeholder="Buscar personajes">
            <?php foreach ($members as $member): ?>
                <?php hg_mobile_chr_character_card($link, $member); ?>
            <?php endforeach; ?>
        </div>
    </section>
</article>