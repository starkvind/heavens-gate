<?php

function hg_gc_source_whitelist(): array
{
    return [
        'character' => ['fact_characters'],
        'episode' => ['dim_chapters'],
        'season' => ['dim_seasons'],
        'chronicle' => ['dim_chronicles'],
        'system' => ['dim_systems'],
        'tribe' => ['dim_tribes'],
        'auspice' => ['dim_auspices'],
        'form' => ['dim_forms'],
        'object' => ['fact_items'],
        'document' => ['fact_docs'],
        'power' => ['fact_gifts', 'fact_rites', 'dim_totems', 'fact_discipline_powers'],
        'totem' => ['dim_totems'],
        'gift' => ['fact_gifts'],
        'rite' => ['fact_rites'],
        'discipline' => ['fact_discipline_powers'],
    ];
}

function hg_gc_allowed_source(string $sourceType, string $sourceTable): bool
{
    $map = hg_gc_source_whitelist();
    return isset($map[$sourceType]) && in_array($sourceTable, $map[$sourceType], true);
}

function hg_gc_rarity_ranges(): array
{
    return [
        'common' => [10, 40],
        'unusual' => [30, 60],
        'rare' => [50, 85],
        'epic' => [70, 105],
        'legendary' => [90, 125],
        'mythic' => [115, 155],
    ];
}

function hg_gc_apply_rarity_range(string $rarity): array
{
    $ranges = hg_gc_rarity_ranges();
    return $ranges[$rarity] ?? $ranges['common'];
}

function hg_gc_valid_rarity(string $rarity): bool
{
    return array_key_exists($rarity, hg_gc_rarity_ranges());
}

function hg_gc_type_labels(): array
{
    return [
        'character' => 'Personaje',
        'episode' => 'Episodio',
        'season' => 'Temporada',
        'chronicle' => 'Crónica',
        'system' => 'Sistema',
        'tribe' => 'Tribu',
        'auspice' => 'Auspicio',
        'form' => 'Forma',
        'object' => 'Objeto',
        'document' => 'Documento',
        'power' => 'Poder',
        'totem' => 'Tótem',
        'gift' => 'Don',
        'rite' => 'Rito',
        'discipline' => 'Disciplina',
    ];
}

function hg_gc_card_url(string $sourceType, string $sourceTable, int $sourceId, string $slug = ''): string
{
    if ($sourceId <= 0 || !hg_gc_allowed_source($sourceType, $sourceTable)) {
        return '';
    }

    $segment = rawurlencode($slug !== '' ? $slug : (string)$sourceId);

    if ($sourceType === 'character') {
        return '/characters/' . $segment;
    }
    if ($sourceType === 'episode') {
        return '/chapters/' . $segment;
    }
    if ($sourceType === 'season') {
        return '/seasons/' . $segment;
    }
    if ($sourceType === 'chronicle') {
        return '/chronicles/' . $segment;
    }
    if ($sourceType === 'system') {
        return '/systems/' . $segment;
    }
    if ($sourceType === 'tribe') {
        return '/systems/tribes/' . $segment;
    }
    if ($sourceType === 'auspice') {
        return '/systems/auspices/' . $segment;
    }
    if ($sourceType === 'form') {
        return '/systems/form/' . $segment;
    }
    if ($sourceType === 'object') {
        return '/inventory/items/' . $segment;
    }
    if ($sourceType === 'document') {
        return '/documents/' . $segment;
    }
    if ($sourceType === 'gift' || ($sourceType === 'power' && $sourceTable === 'fact_gifts')) {
        return '/powers/gift/' . $segment;
    }
    if ($sourceType === 'rite' || ($sourceType === 'power' && $sourceTable === 'fact_rites')) {
        return '/powers/rite/' . $segment;
    }
    if ($sourceType === 'totem' || ($sourceType === 'power' && $sourceTable === 'dim_totems')) {
        return '/powers/totem/' . $segment;
    }
    if ($sourceType === 'discipline' || ($sourceType === 'power' && $sourceTable === 'fact_discipline_powers')) {
        return '/powers/discipline/' . $segment;
    }

    return '';
}

function hg_gc_fallback_image_url(string $sourceType): string
{
    $fallbacks = [
        'character' => '/img/og/og_image_bio.jpg',
        'episode' => '/img/og/og_image_temp.jpg',
        'season' => '/img/og/og_image_temp.jpg',
        'chronicle' => '/img/og/og_image_bio.jpg',
        'system' => '/img/og/og_image_bio.jpg',
        'tribe' => '/img/og/og_image_bio.jpg',
        'auspice' => '/img/og/og_image_bio.jpg',
        'form' => '/img/og/og_image_monster.jpg',
        'object' => '/img/og/og_image_monster.jpg',
        'document' => '/img/og/og_image.jpg',
        'power' => '/img/og/og_image_power.jpg',
        'totem' => '/img/og/og_image_monster.jpg',
        'gift' => '/img/og/og_image_power.jpg',
        'rite' => '/img/og/og_image_power.jpg',
        'discipline' => '/img/og/og_image_power.jpg',
    ];

    return $fallbacks[$sourceType] ?? '/img/og/og_image.jpg';
}

function hg_gc_normalize_image_url(string $imageUrl, string $sourceType = 'document'): string
{
    $img = trim(str_replace('\\', '/', html_entity_decode($imageUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    if ($img === '' || strtolower($img) === 'null') {
        return hg_gc_fallback_image_url($sourceType);
    }

    if (preg_match('~^(?:https?:)?//~i', $img) || strpos($img, 'data:image/') === 0) {
        return $img;
    }

    if (strpos($img, '/public/') === 0) {
        $img = substr($img, 7);
    } elseif (strpos($img, 'public/') === 0) {
        $img = '/' . substr($img, 7);
    } elseif (strpos($img, 'img/') === 0 || strpos($img, 'assets/') === 0 || strpos($img, 'sounds/') === 0) {
        $img = '/' . $img;
    }

    return $img;
}

function hg_gc_excerpt(string $html, int $maxLen = 220): string
{
    $text = strip_tags($html);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = trim((string)preg_replace('/\s+/u', ' ', $text));
    if ($text === '') {
        return '';
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($text, 'UTF-8') <= $maxLen) {
            return $text;
        }
        return rtrim(mb_substr($text, 0, $maxLen - 1, 'UTF-8')) . '...';
    }

    if (strlen($text) <= $maxLen) {
        return $text;
    }
    return rtrim(substr($text, 0, $maxLen - 1)) . '...';
}

function hg_gc_table_exists(mysqli $link, string $table): bool
{
    if ($st = $link->prepare("
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
    ")) {
        $st->bind_param('s', $table);
        $st->execute();
        $st->bind_result($count);
        $st->fetch();
        $st->close();
        return ((int)$count > 0);
    }

    return false;
}

function hg_gc_column_exists(mysqli $link, string $table, string $column): bool
{
    if ($st = $link->prepare("
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ")) {
        $st->bind_param('ss', $table, $column);
        $st->execute();
        $st->bind_result($count);
        $st->fetch();
        $st->close();
        return ((int)$count > 0);
    }

    return false;
}

function hg_gc_schema_sql(): array
{
    return [
        "CREATE TABLE IF NOT EXISTS fact_game_card_collection (
            card_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            source_type ENUM(
                'character',
                'episode',
                'season',
                'chronicle',
                'system',
                'tribe',
                'auspice',
                'form',
                'object',
                'document',
                'power',
                'totem',
                'gift',
                'rite',
                'discipline'
            ) NOT NULL,
            source_table VARCHAR(64) NOT NULL,
            source_id INT UNSIGNED NOT NULL,
            card_name VARCHAR(190) NOT NULL,
            card_slug VARCHAR(220) NULL,
            card_text TEXT NULL,
            card_image_url VARCHAR(500) NULL,
            card_rarity ENUM(
                'common',
                'unusual',
                'rare',
                'epic',
                'legendary',
                'mythic'
            ) NOT NULL DEFAULT 'common',
            hp_min SMALLINT UNSIGNED NOT NULL DEFAULT 10,
            hp_max SMALLINT UNSIGNED NOT NULL DEFAULT 40,
            atk_min SMALLINT UNSIGNED NOT NULL DEFAULT 10,
            atk_max SMALLINT UNSIGNED NOT NULL DEFAULT 40,
            def_min SMALLINT UNSIGNED NOT NULL DEFAULT 10,
            def_max SMALLINT UNSIGNED NOT NULL DEFAULT 40,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (card_id),
            UNIQUE KEY uk_game_card_source (source_type, source_table, source_id),
            KEY idx_game_card_rarity (card_rarity),
            KEY idx_game_card_type (source_type),
            KEY idx_game_card_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "ALTER TABLE fact_game_card_collection
            MODIFY source_type ENUM(
                'character',
                'episode',
                'season',
                'chronicle',
                'system',
                'tribe',
                'auspice',
                'form',
                'object',
                'document',
                'power',
                'totem',
                'gift',
                'rite',
                'discipline'
            ) NOT NULL",
        "ALTER TABLE fact_game_card_collection
            MODIFY card_rarity ENUM(
                'common',
                'unusual',
                'rare',
                'epic',
                'legendary',
                'mythic'
            ) NOT NULL DEFAULT 'common'",
        "ALTER TABLE fact_game_card_collection
            ADD COLUMN IF NOT EXISTS hp_min SMALLINT UNSIGNED NOT NULL DEFAULT 10 AFTER card_rarity",
        "ALTER TABLE fact_game_card_collection
            ADD COLUMN IF NOT EXISTS hp_max SMALLINT UNSIGNED NOT NULL DEFAULT 40 AFTER hp_min",
    ];
}
