<?php
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, max-age=0');

function hg_gc_json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    require_once __DIR__ . '/../app/helpers/db_connection.php';
} catch (Throwable $e) {
    hg_gc_json_response(['success' => false, 'cards' => [], 'error' => 'database_unavailable'], 500);
}

require_once __DIR__ . '/../app/modules/game_cards/game_cards_catalog.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'HEAD'], true)) {
    hg_gc_json_response(['success' => false, 'cards' => [], 'error' => 'method_not_allowed'], 405);
}

if (!isset($link) || !($link instanceof mysqli)) {
    hg_gc_json_response(['success' => false, 'cards' => [], 'error' => 'database_unavailable'], 500);
}

$hasTable = hg_gc_table_exists($link, 'fact_game_card_collection');
if (!$hasTable) {
    hg_gc_json_response(['success' => true, 'cards' => []]);
}

$source = 'fact_game_card_collection';
$where = ' WHERE gc.is_active = 1';
$hasHpColumns = hg_gc_column_exists($link, $source, 'hp_min') && hg_gc_column_exists($link, $source, 'hp_max');
$hpSelect = $hasHpColumns ? "gc.hp_min,\n        gc.hp_max," : "gc.atk_min AS hp_min,\n        gc.atk_max AS hp_max,";
$hasCharacters = hg_gc_table_exists($link, 'fact_characters');
$characterImageSelect = $hasCharacters ? "fc.image_url AS character_image_url," : "NULL AS character_image_url,";
$characterJoin = $hasCharacters
    ? "LEFT JOIN fact_characters fc
        ON gc.source_type = 'character'
       AND gc.source_table = 'fact_characters'
       AND fc.id = gc.source_id"
    : '';
$sql = "
    SELECT
        gc.card_id,
        gc.source_type,
        gc.source_table,
        gc.source_id,
        gc.card_name,
        gc.card_slug,
        gc.card_text,
        gc.card_image_url,
        {$characterImageSelect}
        gc.card_rarity,
        {$hpSelect}
        gc.atk_min,
        gc.atk_max,
        gc.def_min,
        gc.def_max
    FROM {$source} gc
    {$characterJoin}
    {$where}
    ORDER BY gc.source_type ASC, gc.card_name ASC, gc.card_id ASC
";

$cards = [];
$rs = $link->query($sql);
if (!$rs) {
    hg_gc_json_response(['success' => false, 'cards' => [], 'error' => 'query_failed'], 500);
}

while ($row = $rs->fetch_assoc()) {
    $sourceType = (string)($row['source_type'] ?? '');
    $sourceTable = (string)($row['source_table'] ?? '');
    if (!hg_gc_allowed_source($sourceType, $sourceTable)) {
        continue;
    }

    $rarity = (string)($row['card_rarity'] ?? 'common');
    if (!hg_gc_valid_rarity($rarity)) {
        $rarity = 'common';
    }

    $imageUrl = trim((string)($row['card_image_url'] ?? ''));
    $characterImageUrl = trim((string)($row['character_image_url'] ?? ''));
    $normalizedImageUrl = hg_gc_normalize_image_url($imageUrl, $sourceType);
    if (
        $sourceType === 'character'
        && $characterImageUrl !== ''
        && ($imageUrl === '' || $normalizedImageUrl === hg_gc_fallback_image_url('character') || $normalizedImageUrl === hg_gc_fallback_image_url($sourceType))
    ) {
        $imageUrl = $characterImageUrl;
        $normalizedImageUrl = hg_gc_normalize_image_url($imageUrl, $sourceType);
    }
    $imageUrl = $normalizedImageUrl;
    $slug = trim((string)($row['card_slug'] ?? ''));

    $cards[] = [
        'card_id' => (int)($row['card_id'] ?? 0),
        'source_type' => $sourceType,
        'source_id' => (int)($row['source_id'] ?? 0),
        'card_name' => (string)($row['card_name'] ?? ''),
        'card_text' => hg_gc_excerpt((string)($row['card_text'] ?? ''), 240),
        'card_image_url' => $imageUrl,
        'card_url' => hg_gc_card_url($sourceType, $sourceTable, (int)($row['source_id'] ?? 0), $slug),
        'card_rarity' => $rarity,
        'hp_min' => (int)($row['hp_min'] ?? ($row['atk_min'] ?? 10)),
        'hp_max' => (int)($row['hp_max'] ?? ($row['atk_max'] ?? 40)),
        'atk_min' => (int)($row['atk_min'] ?? 10),
        'atk_max' => (int)($row['atk_max'] ?? 40),
        'def_min' => (int)($row['def_min'] ?? 10),
        'def_max' => (int)($row['def_max'] ?? 40),
    ];
}
$rs->free();

hg_gc_json_response(['success' => true, 'cards' => $cards]);
