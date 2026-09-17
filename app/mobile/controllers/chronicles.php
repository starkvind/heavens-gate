<?php

include_once(__DIR__ . '/../../helpers/public_response.php');
include_once(__DIR__ . '/../../helpers/character_avatar.php');
require_once(__DIR__ . '/../../domains/chronicles/queries.php');

$metaTitle = "Crónicas | Heaven's Gate";
$metaDescription = 'Archivo móvil de crónicas.';
$pageSect = 'Crónicas';

if (!function_exists('hg_mobile_chr_h')) {
    function hg_mobile_chr_h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
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

$schema = hg_chronicles_schema($link);
$excludedChronicleIds = hg_chronicles_normalize_ids($excludeChronicles ?? '');
$raw = hg_request_param($hgRequest, 'chronicle');
$chronicleId = $raw !== '' ? hg_chronicles_resolve_id($link, $raw) : 0;
$hasSeasonChronicle = !empty($schema['season_chronicle']);
$hasCharacterChronicle = !empty($schema['character_chronicle']);

if ($chronicleId <= 0) {
    $chronicles = hg_chronicles_fetch_catalog($link, $schema, $excludedChronicleIds);
    if ($chronicles === null) {
        hg_public_log_error('mobile_chronicles', 'list query failed: ' . mysqli_error($link));
        $chronicles = [];
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

$chronicle = hg_chronicles_fetch_one($link, $schema, $chronicleId);
if (!$chronicle) {
    hg_public_render_not_found('Cronica no encontrada', 'No se pudo localizar la cronica solicitada.');
    return;
}

$name = (string)($chronicle['name'] ?? 'Cronica');
$pretty = (string)($chronicle['pretty_id'] ?? '');
$description = (string)($chronicle['description'] ?? '');
$metaTitle = $name . " | Crónicas | Heaven's Gate";
$metaDescription = hg_mobile_chr_excerpt($description, 160);

$seasonRows = $hasSeasonChronicle ? hg_chronicles_fetch_seasons($link, $chronicleId) : [];
$members = $hasCharacterChronicle ? hg_chronicles_fetch_mobile_members($link, $chronicleId) : [];
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