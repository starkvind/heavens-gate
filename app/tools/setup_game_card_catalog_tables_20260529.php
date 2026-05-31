<?php
/**
 * Setup: game card catalog/rules tables (idempotent)
 * Date: 2026-05-29
 *
 * Creates and seeds tables prefixed with dim_game_card_* and fact_game_card_*
 * so they are clearly scoped to the card game.
 */

require_once __DIR__ . '/../helpers/db_connection.php';
require_once __DIR__ . '/../modules/game_cards/game_card_rules_catalog.php';

function gcr_setup_fail(string $message, int $status = 1)
{
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
    }
    echo rtrim($message) . PHP_EOL;
    exit($status);
}

if (!isset($link) || !($link instanceof mysqli)) {
    gcr_setup_fail('DB connection error.');
}

mysqli_set_charset($link, 'utf8mb4');

if (!headers_sent()) {
    header('Content-Type: text/plain; charset=UTF-8');
}

function gcr_setup_log(string $text): void
{
    echo $text . PHP_EOL;
}

function gcr_setup_run(mysqli $db, string $sql, string $label): bool
{
    $ok = mysqli_query($db, $sql);
    if ($ok) {
        gcr_setup_log("[OK] {$label}");
        return true;
    }
    gcr_setup_log("[ERR] {$label} :: " . mysqli_error($db));
    return false;
}

function gcr_setup_stmt(mysqli $db, string $sql, string $types, array $values, string $label): bool
{
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        gcr_setup_log("[ERR] {$label} :: " . $db->error);
        return false;
    }
    $params = [$types];
    foreach ($values as $key => &$value) {
        $params[] = &$value;
    }
    unset($value);
    call_user_func_array([$stmt, 'bind_param'], $params);
    $ok = $stmt->execute();
    if ($ok) {
        gcr_setup_log("[OK] {$label}");
    } else {
        gcr_setup_log("[ERR] {$label} :: " . $stmt->error);
    }
    $stmt->close();
    return $ok;
}

function gcr_setup_seed_rarities(mysqli $db, array $rows): void
{
    $sql = "INSERT INTO dim_game_card_rarities
        (rarity_key, label, short_label, icon_url, stat_min, stat_max, natural_order, upgrade_order, base_weight, recycle_value, work_base, skill_cost_multiplier, upgrade_cost, upgrade_required_material_key, skin_color, skin_bg, skin_head, skin_body, skin_row_bg, is_active, sort_order)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)
        ON DUPLICATE KEY UPDATE
            label = VALUES(label), short_label = VALUES(short_label), icon_url = VALUES(icon_url),
            stat_min = VALUES(stat_min), stat_max = VALUES(stat_max), natural_order = VALUES(natural_order),
            upgrade_order = VALUES(upgrade_order), base_weight = VALUES(base_weight), recycle_value = VALUES(recycle_value),
            work_base = VALUES(work_base), skill_cost_multiplier = VALUES(skill_cost_multiplier),
            upgrade_cost = VALUES(upgrade_cost), upgrade_required_material_key = VALUES(upgrade_required_material_key),
            skin_color = VALUES(skin_color), skin_bg = VALUES(skin_bg), skin_head = VALUES(skin_head),
            skin_body = VALUES(skin_body), skin_row_bg = VALUES(skin_row_bg),
            is_active = 1, sort_order = VALUES(sort_order), updated_at = CURRENT_TIMESTAMP";
    foreach ($rows as $index => $row) {
        [$key, $label, $short, $icon, $min, $max, $naturalOrder, $upgradeOrder, $weight, $recycle, $work, $skillMultiplier, $upgradeCost, $material, $skinColor, $skinBg, $skinHead, $skinBody, $skinRowBg] = $row;
        gcr_setup_stmt($db, $sql, 'ssssiiiidiiiissssssi', [
            $key, $label, $short, $icon, $min, $max, $naturalOrder, $upgradeOrder, $weight, $recycle, $work, $skillMultiplier, $upgradeCost, $material, $skinColor, $skinBg, $skinHead, $skinBody, $skinRowBg, $index
        ], "dim_game_card_rarities: seed {$key}");
    }
}

function gcr_setup_seed_types(mysqli $db, array $rows): void
{
    $sql = "INSERT INTO dim_game_card_types
        (type_key, label, emoji, icon_svg, canonical_type_key, is_filterable, is_active, sort_order)
        VALUES (?, ?, ?, ?, ?, ?, 1, ?)
        ON DUPLICATE KEY UPDATE
            label = VALUES(label), emoji = VALUES(emoji), icon_svg = VALUES(icon_svg),
            canonical_type_key = VALUES(canonical_type_key), is_filterable = VALUES(is_filterable),
            is_active = 1, sort_order = VALUES(sort_order), updated_at = CURRENT_TIMESTAMP";
    foreach ($rows as $row) {
        [$key, $label, $emoji, $iconSvg, $canonical, $filterable, $sort] = $row;
        gcr_setup_stmt($db, $sql, 'sssssii', [$key, $label, $emoji, $iconSvg, $canonical, $filterable, $sort], "dim_game_card_types: seed {$key}");
    }
}

function gcr_setup_seed_packs(mysqli $db, array $rows): void
{
    $sql = "INSERT INTO dim_game_card_pack_types
        (pack_key, label, description, price, pack_size, max_stock, daily_cap, seal_text, short_description, skin_primary, skin_secondary, skin_dark, skin_accent, is_shop_pack, is_free_pack, is_active, sort_order)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)
        ON DUPLICATE KEY UPDATE
            label = VALUES(label), description = VALUES(description), price = VALUES(price),
            pack_size = VALUES(pack_size), max_stock = VALUES(max_stock), daily_cap = VALUES(daily_cap),
            seal_text = VALUES(seal_text), short_description = VALUES(short_description),
            skin_primary = VALUES(skin_primary), skin_secondary = VALUES(skin_secondary),
            skin_dark = VALUES(skin_dark), skin_accent = VALUES(skin_accent),
            is_shop_pack = VALUES(is_shop_pack), is_free_pack = VALUES(is_free_pack),
            is_active = 1, sort_order = VALUES(sort_order), updated_at = CURRENT_TIMESTAMP";
    foreach ($rows as $row) {
        [$key, $label, $description, $price, $packSize, $maxStock, $dailyCap, $sealText, $shortDescription, $skinPrimary, $skinSecondary, $skinDark, $skinAccent, $shop, $free, $sort] = $row;
        gcr_setup_stmt($db, $sql, 'sssiiiissssssiii', [$key, $label, $description, $price, $packSize, $maxStock, $dailyCap, $sealText, $shortDescription, $skinPrimary, $skinSecondary, $skinDark, $skinAccent, $shop, $free, $sort], "dim_game_card_pack_types: seed {$key}");
    }
}

function gcr_setup_seed_weights(mysqli $db, array $weights): void
{
    $sql = "INSERT INTO fact_game_card_pack_rarity_weights
        (pack_key, rarity_key, weight)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE weight = VALUES(weight), updated_at = CURRENT_TIMESTAMP";
    foreach ($weights as $packKey => $rarityWeights) {
        foreach ($rarityWeights as $rarityKey => $weight) {
            gcr_setup_stmt($db, $sql, 'ssd', [$packKey, $rarityKey, $weight], "fact_game_card_pack_rarity_weights: seed {$packKey}/{$rarityKey}");
        }
    }
}

function gcr_setup_seed_pack_filters(mysqli $db, array $filters): void
{
    $sql = "INSERT INTO fact_game_card_pack_type_filters
        (pack_key, type_key, sort_order)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order), updated_at = CURRENT_TIMESTAMP";
    foreach ($filters as $packKey => $types) {
        foreach (array_values($types) as $index => $typeKey) {
            gcr_setup_stmt($db, $sql, 'ssi', [$packKey, $typeKey, $index], "fact_game_card_pack_type_filters: seed {$packKey}/{$typeKey}");
        }
    }
}

function gcr_setup_seed_materials(mysqli $db, array $rows): void
{
    $sql = "INSERT INTO dim_game_card_materials
        (material_key, label, price, rarity_key, description, is_daily_gift, is_active, sort_order)
        VALUES (?, ?, ?, ?, ?, ?, 1, ?)
        ON DUPLICATE KEY UPDATE
            label = VALUES(label), price = VALUES(price), rarity_key = VALUES(rarity_key),
            description = VALUES(description), is_daily_gift = VALUES(is_daily_gift),
            is_active = 1, sort_order = VALUES(sort_order), updated_at = CURRENT_TIMESTAMP";
    foreach ($rows as $row) {
        [$key, $label, $price, $rarity, $description, $dailyGift, $sort] = $row;
        gcr_setup_stmt($db, $sql, 'ssissii', [$key, $label, $price, $rarity, $description, $dailyGift, $sort], "dim_game_card_materials: seed {$key}");
    }
}

function gcr_setup_seed_shop_products(mysqli $db, array $rows): void
{
    $sql = "INSERT INTO dim_game_card_shop_products
        (product_key, product_type, label, description, price_mnemones, material_key, remorias_amount, daily_cap, is_active, sort_order)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)
        ON DUPLICATE KEY UPDATE
            product_type = VALUES(product_type), label = VALUES(label), description = VALUES(description),
            price_mnemones = VALUES(price_mnemones), material_key = VALUES(material_key),
            remorias_amount = VALUES(remorias_amount), daily_cap = VALUES(daily_cap),
            is_active = 1, sort_order = VALUES(sort_order), updated_at = CURRENT_TIMESTAMP";
    foreach ($rows as $row) {
        [$key, $type, $label, $description, $price, $materialKey, $remoriasAmount, $dailyCap, $sort] = $row;
        gcr_setup_stmt($db, $sql, 'ssssisiii', [$key, $type, $label, $description, $price, $materialKey, $remoriasAmount, $dailyCap, $sort], "dim_game_card_shop_products: seed {$key}");
    }
}

function gcr_setup_seed_moves(mysqli $db, array $rows): void
{
    $sql = "INSERT INTO dim_game_card_moves
        (move_key, label, icon, move_type, power, formula, accuracy, cooldown, target, effect_json, description, is_active, sort_order)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)
        ON DUPLICATE KEY UPDATE
            label = VALUES(label), icon = VALUES(icon), move_type = VALUES(move_type), power = VALUES(power),
            formula = VALUES(formula), accuracy = VALUES(accuracy), cooldown = VALUES(cooldown),
            target = VALUES(target), effect_json = VALUES(effect_json), description = VALUES(description),
            is_active = 1, sort_order = VALUES(sort_order), updated_at = CURRENT_TIMESTAMP";
    foreach ($rows as $row) {
        [$key, $label, $icon, $type, $power, $formula, $accuracy, $cooldown, $target, $effect, $description, $sort] = $row;
        $effectJson = json_encode($effect, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        gcr_setup_stmt($db, $sql, 'ssssdsdisssi', [$key, $label, $icon, $type, $power, $formula, $accuracy, $cooldown, $target, $effectJson, $description, $sort], "dim_game_card_moves: seed {$key}");
    }
}

function gcr_setup_seed_move_rules(mysqli $db, array $rows): void
{
    $sql = "INSERT INTO fact_game_card_move_learn_rules
        (rarity_key, chance, move_count)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE chance = VALUES(chance), move_count = VALUES(move_count), updated_at = CURRENT_TIMESTAMP";
    foreach ($rows as $row) {
        [$rarity, $chance, $count] = $row;
        gcr_setup_stmt($db, $sql, 'sdi', [$rarity, $chance, $count], "fact_game_card_move_learn_rules: seed {$rarity}");
    }
}

function gcr_setup_seed_settings(mysqli $db, array $rows): void
{
    $sql = "INSERT INTO dim_game_card_settings
        (setting_key, setting_value, value_type, description)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value), value_type = VALUES(value_type),
            description = VALUES(description), updated_at = CURRENT_TIMESTAMP";
    foreach ($rows as $row) {
        [$key, $value, $type, $description] = $row;
        gcr_setup_stmt($db, $sql, 'ssss', [$key, $value, $type, $description], "dim_game_card_settings: seed {$key}");
    }
}

function gcr_setup_seed_ui_texts(mysqli $db, array $rows): void
{
    $sql = "INSERT INTO dim_game_card_ui_texts
        (text_key, text_value, description, is_active, sort_order)
        VALUES (?, ?, ?, 1, ?)
        ON DUPLICATE KEY UPDATE
            text_value = VALUES(text_value), description = VALUES(description),
            is_active = 1, sort_order = VALUES(sort_order), updated_at = CURRENT_TIMESTAMP";
    foreach ($rows as $index => $row) {
        [$key, $value, $description] = $row;
        gcr_setup_stmt($db, $sql, 'sssi', [$key, $value, $description, $index], "dim_game_card_ui_texts: seed {$key}");
    }
}

gcr_setup_log('=== Setup game card catalog/rules tables start ===');

foreach (hg_gcr_schema_sql() as $index => $sql) {
    gcr_setup_run($link, $sql, "game card catalog schema statement #" . ($index + 1));
}

$defaults = hg_gcr_default_catalog();
gcr_setup_seed_rarities($link, $defaults['rarities']);
gcr_setup_seed_types($link, $defaults['types']);
gcr_setup_seed_packs($link, $defaults['packs']);
gcr_setup_seed_weights($link, $defaults['weights']);
gcr_setup_seed_pack_filters($link, $defaults['packFilters']);
gcr_setup_seed_materials($link, $defaults['materials']);
gcr_setup_seed_shop_products($link, $defaults['shopProducts']);
gcr_setup_seed_moves($link, $defaults['moves']);
gcr_setup_seed_move_rules($link, $defaults['moveLearnRules']);
gcr_setup_seed_settings($link, $defaults['settings']);
gcr_setup_seed_ui_texts($link, $defaults['uiTexts']);

gcr_setup_log('=== Setup game card catalog/rules tables end ===');
