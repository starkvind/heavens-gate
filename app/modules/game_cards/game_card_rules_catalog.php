<?php

function hg_gcr_schema_sql(): array
{
    return [
        "CREATE TABLE IF NOT EXISTS `dim_game_card_rarities` (
            `rarity_key` varchar(40) NOT NULL,
            `label` varchar(80) NOT NULL,
            `short_label` varchar(8) NOT NULL,
            `icon_url` varchar(255) DEFAULT NULL,
            `stat_min` smallint unsigned NOT NULL DEFAULT 10,
            `stat_max` smallint unsigned NOT NULL DEFAULT 40,
            `natural_order` smallint unsigned DEFAULT NULL,
            `upgrade_order` smallint unsigned DEFAULT NULL,
            `base_weight` decimal(8,3) NOT NULL DEFAULT 0,
            `recycle_value` int unsigned NOT NULL DEFAULT 0,
            `work_base` int unsigned NOT NULL DEFAULT 1,
            `skill_cost_multiplier` int unsigned NOT NULL DEFAULT 1,
            `upgrade_cost` int unsigned NOT NULL DEFAULT 0,
            `upgrade_required_material_key` varchar(80) DEFAULT NULL,
            `skin_color` varchar(32) DEFAULT NULL,
            `skin_bg` varchar(180) DEFAULT NULL,
            `skin_head` varchar(80) DEFAULT NULL,
            `skin_body` varchar(80) DEFAULT NULL,
            `skin_row_bg` varchar(180) DEFAULT NULL,
            `is_active` tinyint(1) NOT NULL DEFAULT 1,
            `sort_order` smallint unsigned NOT NULL DEFAULT 0,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
            PRIMARY KEY (`rarity_key`),
            KEY `idx_game_card_rarities_active_sort` (`is_active`, `sort_order`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "ALTER TABLE `dim_game_card_rarities` ADD COLUMN IF NOT EXISTS `skin_color` varchar(32) DEFAULT NULL AFTER `upgrade_required_material_key`",
        "ALTER TABLE `dim_game_card_rarities` ADD COLUMN IF NOT EXISTS `skin_bg` varchar(180) DEFAULT NULL AFTER `skin_color`",
        "ALTER TABLE `dim_game_card_rarities` ADD COLUMN IF NOT EXISTS `skin_head` varchar(80) DEFAULT NULL AFTER `skin_bg`",
        "ALTER TABLE `dim_game_card_rarities` ADD COLUMN IF NOT EXISTS `skin_body` varchar(80) DEFAULT NULL AFTER `skin_head`",
        "ALTER TABLE `dim_game_card_rarities` ADD COLUMN IF NOT EXISTS `skin_row_bg` varchar(180) DEFAULT NULL AFTER `skin_body`",
        "CREATE TABLE IF NOT EXISTS `dim_game_card_types` (
            `type_key` varchar(40) NOT NULL,
            `label` varchar(80) NOT NULL,
            `emoji` varchar(32) DEFAULT NULL,
            `icon_svg` text DEFAULT NULL,
            `canonical_type_key` varchar(40) DEFAULT NULL,
            `is_filterable` tinyint(1) NOT NULL DEFAULT 1,
            `is_active` tinyint(1) NOT NULL DEFAULT 1,
            `sort_order` smallint unsigned NOT NULL DEFAULT 0,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
            PRIMARY KEY (`type_key`),
            KEY `idx_game_card_types_active_sort` (`is_active`, `sort_order`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS `dim_game_card_pack_types` (
            `pack_key` varchar(40) NOT NULL,
            `label` varchar(120) NOT NULL,
            `description` varchar(255) NOT NULL,
            `price` int unsigned NOT NULL DEFAULT 0,
            `pack_size` smallint unsigned NOT NULL DEFAULT 5,
            `max_stock` smallint unsigned NOT NULL DEFAULT 99,
            `daily_cap` smallint unsigned DEFAULT NULL,
            `seal_text` varchar(16) DEFAULT NULL,
            `short_description` varchar(120) DEFAULT NULL,
            `skin_primary` varchar(32) DEFAULT NULL,
            `skin_secondary` varchar(32) DEFAULT NULL,
            `skin_dark` varchar(32) DEFAULT NULL,
            `skin_accent` varchar(32) DEFAULT NULL,
            `is_shop_pack` tinyint(1) NOT NULL DEFAULT 1,
            `is_free_pack` tinyint(1) NOT NULL DEFAULT 0,
            `is_active` tinyint(1) NOT NULL DEFAULT 1,
            `sort_order` smallint unsigned NOT NULL DEFAULT 0,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
            PRIMARY KEY (`pack_key`),
            KEY `idx_game_card_pack_types_active_sort` (`is_active`, `sort_order`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "ALTER TABLE `dim_game_card_pack_types` ADD COLUMN IF NOT EXISTS `seal_text` varchar(16) DEFAULT NULL AFTER `daily_cap`",
        "ALTER TABLE `dim_game_card_pack_types` ADD COLUMN IF NOT EXISTS `short_description` varchar(120) DEFAULT NULL AFTER `seal_text`",
        "ALTER TABLE `dim_game_card_pack_types` ADD COLUMN IF NOT EXISTS `skin_primary` varchar(32) DEFAULT NULL AFTER `short_description`",
        "ALTER TABLE `dim_game_card_pack_types` ADD COLUMN IF NOT EXISTS `skin_secondary` varchar(32) DEFAULT NULL AFTER `skin_primary`",
        "ALTER TABLE `dim_game_card_pack_types` ADD COLUMN IF NOT EXISTS `skin_dark` varchar(32) DEFAULT NULL AFTER `skin_secondary`",
        "ALTER TABLE `dim_game_card_pack_types` ADD COLUMN IF NOT EXISTS `skin_accent` varchar(32) DEFAULT NULL AFTER `skin_dark`",
        "CREATE TABLE IF NOT EXISTS `fact_game_card_pack_rarity_weights` (
            `pack_key` varchar(40) NOT NULL,
            `rarity_key` varchar(40) NOT NULL,
            `weight` decimal(8,3) NOT NULL DEFAULT 0,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
            PRIMARY KEY (`pack_key`, `rarity_key`),
            KEY `idx_game_card_pack_weights_rarity` (`rarity_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS `fact_game_card_pack_type_filters` (
            `pack_key` varchar(40) NOT NULL,
            `type_key` varchar(40) NOT NULL,
            `sort_order` smallint unsigned NOT NULL DEFAULT 0,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
            PRIMARY KEY (`pack_key`, `type_key`),
            KEY `idx_game_card_pack_filters_type` (`type_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS `dim_game_card_materials` (
            `material_key` varchar(80) NOT NULL,
            `label` varchar(120) NOT NULL,
            `price` int unsigned NOT NULL DEFAULT 0,
            `rarity_key` varchar(40) NOT NULL DEFAULT 'common',
            `description` varchar(255) NOT NULL,
            `is_daily_gift` tinyint(1) NOT NULL DEFAULT 0,
            `is_active` tinyint(1) NOT NULL DEFAULT 1,
            `sort_order` smallint unsigned NOT NULL DEFAULT 0,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
            PRIMARY KEY (`material_key`),
            KEY `idx_game_card_materials_active_sort` (`is_active`, `sort_order`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS `dim_game_card_shop_products` (
            `product_key` varchar(80) NOT NULL,
            `product_type` enum('material','exchange_remorias','daily_gift') NOT NULL,
            `label` varchar(120) NOT NULL,
            `description` varchar(255) DEFAULT NULL,
            `price_mnemones` int unsigned NOT NULL DEFAULT 0,
            `material_key` varchar(80) DEFAULT NULL,
            `remorias_amount` int unsigned DEFAULT NULL,
            `daily_cap` smallint unsigned DEFAULT NULL,
            `is_active` tinyint(1) NOT NULL DEFAULT 1,
            `sort_order` smallint unsigned NOT NULL DEFAULT 0,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
            PRIMARY KEY (`product_key`),
            KEY `idx_game_card_shop_products_active_sort` (`is_active`, `sort_order`),
            KEY `idx_game_card_shop_products_type` (`product_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS `dim_game_card_moves` (
            `move_key` varchar(80) NOT NULL,
            `label` varchar(120) NOT NULL,
            `icon` varchar(32) DEFAULT NULL,
            `move_type` varchar(40) NOT NULL,
            `power` decimal(8,4) DEFAULT NULL,
            `formula` varchar(80) DEFAULT NULL,
            `accuracy` decimal(8,4) NOT NULL DEFAULT 1,
            `cooldown` smallint unsigned NOT NULL DEFAULT 0,
            `target` varchar(40) NOT NULL DEFAULT 'enemy',
            `effect_json` text DEFAULT NULL,
            `description` varchar(255) NOT NULL,
            `is_active` tinyint(1) NOT NULL DEFAULT 1,
            `sort_order` smallint unsigned NOT NULL DEFAULT 0,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
            PRIMARY KEY (`move_key`),
            KEY `idx_game_card_moves_active_sort` (`is_active`, `sort_order`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS `fact_game_card_move_learn_rules` (
            `rarity_key` varchar(40) NOT NULL,
            `chance` decimal(8,4) NOT NULL DEFAULT 0,
            `move_count` smallint unsigned NOT NULL DEFAULT 0,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
            PRIMARY KEY (`rarity_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS `dim_game_card_settings` (
            `setting_key` varchar(100) NOT NULL,
            `setting_value` text NOT NULL,
            `value_type` enum('string','int','float','bool','json') NOT NULL DEFAULT 'string',
            `description` varchar(255) DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
            PRIMARY KEY (`setting_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS `dim_game_card_ui_texts` (
            `text_key` varchar(120) NOT NULL,
            `text_value` text NOT NULL,
            `description` varchar(255) DEFAULT NULL,
            `is_active` tinyint(1) NOT NULL DEFAULT 1,
            `sort_order` smallint unsigned NOT NULL DEFAULT 0,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
            PRIMARY KEY (`text_key`),
            KEY `idx_game_card_ui_texts_active_sort` (`is_active`, `sort_order`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
}

function hg_gcr_default_catalog(): array
{
    $rarities = [
        ['common', 'Común', 'C', '/img/ui/rarity_icons/common.svg', 10, 40, 0, 0, 64, 10, 1, 1, 100, null, '#f4f4f4', 'linear-gradient(180deg, #eeeeea, #424242 42%, #151515)', 'rgba(232, 232, 224, 0.96)', 'rgba(58, 58, 58, 0.92)', 'linear-gradient(90deg, rgba(244,244,244,0.08), #151515 18%)'],
        ['unusual', 'Inusual', 'I', '/img/ui/rarity_icons/unusual.svg', 30, 60, 1, 1, 22, 30, 2, 2, 300, null, '#42d67b', 'linear-gradient(180deg, #173825, #0b1510)', 'rgba(25, 75, 45, 0.96)', 'rgba(13, 48, 29, 0.92)', 'linear-gradient(90deg, rgba(66,214,123,0.12), #151515 18%)'],
        ['rare', 'Raro', 'R', '/img/ui/rarity_icons/rare.svg', 50, 85, 2, 2, 9, 80, 4, 3, 900, null, '#4e9cff', 'linear-gradient(180deg, #172c4c, #0b1019)', 'rgba(24, 55, 98, 0.96)', 'rgba(15, 38, 70, 0.92)', 'linear-gradient(90deg, rgba(78,156,255,0.13), #151515 18%)'],
        ['epic', 'Épico', 'E', '/img/ui/rarity_icons/epic.svg', 70, 105, 3, 3, 3.5, 250, 7, 4, 2000, 'icarus_vial', '#b56cff', 'linear-gradient(180deg, #4c1530, #190912)', 'rgba(116, 31, 70, 0.96)', 'rgba(74, 18, 45, 0.92)', 'linear-gradient(90deg, rgba(255,79,143,0.15), #151515 18%)'],
        ['legendary', 'Legendario', 'L', '/img/ui/rarity_icons/legendary.svg', 90, 125, 4, 4, 1.2, 750, 11, 5, 8000, 'stigma_orb', '#f39a32', 'linear-gradient(180deg, #4b2b12, #16100a)', 'rgba(102, 57, 20, 0.96)', 'rgba(72, 38, 15, 0.92)', 'linear-gradient(90deg, rgba(243,154,50,0.15), #151515 18%)'],
        ['mythic', 'Mítico', 'M', '/img/ui/rarity_icons/mythic.svg', 115, 160, 5, 5, 0.3, 2000, 18, 6, 24000, 'babylon_shred', '#ff4f8f', 'linear-gradient(180deg, #37204e, #120b19)', 'rgba(79, 39, 116, 0.96)', 'rgba(55, 27, 82, 0.92)', 'linear-gradient(90deg, rgba(181,108,255,0.16), #151515 18%)'],
        ['stigmatic', 'Estigmático', 'S', '/img/ui/rarity_icons/stigmatic.svg', 180, 260, null, null, 0, 0, 32, 7, 80000, null, '#8a0303', 'linear-gradient(180deg, #4b0509, #150203)', 'rgba(102, 6, 12, 0.96)', 'rgba(54, 3, 7, 0.94)', 'linear-gradient(90deg, rgba(138,3,3,0.22), #151515 18%)'],
    ];

    $types = [
        ['all', 'Todas', '·', null, null, 1, 0],
        ['character', 'Personaje', '👤', null, null, 1, 1],
        ['system', 'Sistema', '⬡', '<svg viewBox="0 0 24 24" focusable="false"><path d="M12 3l7 4v10l-7 4-7-4V7l7-4z"></path><path d="M12 8v8"></path><path d="M8.5 10.5l7 3"></path><path d="M15.5 10.5l-7 3"></path></svg>', null, 1, 2],
        ['tribe', 'Tribu', '⬟', '<svg viewBox="0 0 24 24" focusable="false"><path d="M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7l8-4z"></path><path d="M8 10h8"></path><path d="M12 7v10"></path></svg>', null, 1, 3],
        ['auspice', 'Auspicio', '☾', '<svg viewBox="0 0 24 24" focusable="false"><path d="M15.5 3.5a8.8 8.8 0 1 0 0 17 7 7 0 0 1 0-17z"></path><path d="M6.5 12h3"></path></svg>', null, 1, 4],
        ['form', 'Forma', '⇄', '<svg viewBox="0 0 24 24" focusable="false"><path d="M4 7h10l-3-3"></path><path d="M14 4l3 3-3 3"></path><path d="M20 17H10l3 3"></path><path d="M10 20l-3-3 3-3"></path></svg>', null, 1, 5],
        ['gift', 'Don', '✨', null, null, 1, 6],
        ['rite', 'Rito', '🕯️', null, null, 1, 7],
        ['power', 'Poder', '✦', null, null, 1, 8],
        ['discipline', 'Disciplina', '🩸', null, null, 1, 9],
        ['totem', 'Tótem', '🪶', null, null, 1, 10],
        ['chronicle', 'Crónica', '🌌', null, null, 1, 11],
        ['season', 'Temporada', '📚', null, null, 1, 12],
        ['episode', 'Episodio', '🎬', null, null, 1, 13],
        ['object', 'Objeto', '🗝️', null, null, 1, 14],
        ['document', 'Documento', '📜', null, null, 1, 15],
        ['creature', 'Criatura', '♢', null, null, 0, 16],
        ['systems', 'Sistema', null, null, 'system', 0, 100],
        ['tribes', 'Tribu', null, null, 'tribe', 0, 101],
        ['auspices', 'Auspicio', null, null, 'auspice', 0, 102],
        ['forms', 'Forma', null, null, 'form', 0, 103],
    ];

    $packs = [
        ['standard', 'Sobre mnemónico', '5 cartas de cualquier colección.', 50, 5, 99, null, 'HG', '5 cartas', '#edcc81', '#583b30', '#131921', '#ffe296', 1, 0, 0],
        ['echoes', 'Sobre de ecos', '5 cartas comunes o inusuales.', 90, 5, 99, null, 'EC', 'Común e inusual', '#e2e8e1', '#3f534b', '#080c0d', '#d6ecdb', 1, 0, 1],
        ['magic', 'Sobre mágico', '5 cartas de cualquier colección, con mejores pesos de rareza.', 220, 5, 99, 3, '✦', 'Mejor rareza', '#b56cff', '#341359', '#070d23', '#d5a6ff', 1, 0, 2],
        ['characters', 'Sobre de personajes', '5 cartas de personaje.', 240, 5, 99, null, 'PJ', 'Sólo personajes', '#4e9cff', '#113466', '#050a1a', '#7cb6ff', 1, 0, 3],
        ['lineage', 'Sobre de linaje', '5 cartas de personajes, tribus o auspicios.', 420, 5, 99, null, 'LN', 'Personajes y linajes', '#f55878', '#5b132a', '#0a0918', '#ff8ea6', 1, 0, 4],
        ['essence', 'Sobre de esencia', '5 cartas de sistemas, tribus, auspicios o formas.', 300, 5, 99, null, 'ES', 'Sistemas y formas', '#78daaf', '#295559', '#080d17', '#99e8ca', 1, 0, 5],
        ['powers', 'Sobre arcano', '5 cartas de dones, ritos, tótems o disciplinas.', 240, 5, 99, null, 'Ω', 'Poderes y ritos', '#42d67b', '#145636', '#051016', '#70eba1', 1, 0, 6],
        ['chronicles', 'Sobre de crónica', '5 cartas de crónicas, temporadas o episodios.', 140, 5, 99, null, 'CR', 'Historias y temporadas', '#5ed0dd', '#123f60', '#060b1b', '#80e7f0', 1, 0, 7],
        ['relics', 'Sobre de reliquias', '5 cartas de objetos, documentos o tótems.', 160, 5, 99, null, 'RX', 'Objetos y documentos', '#e2b958', '#523e22', '#111113', '#f1cf7c', 1, 0, 8],
        ['omens', 'Sobre de presagios', '5 cartas raras o superiores.', 650, 5, 99, null, 'PR', 'Raro o superior', '#ff4f8f', '#491859', '#0d0718', '#ff8bb5', 0, 0, 9],
        ['gaian', 'Sobre gaiano', '5 cartas épicas, legendarias o míticas.', 2000, 5, 99, 3, 'GA', 'Épico o superior', '#4cd292', '#215a37', '#071410', '#87eeb2', 1, 0, 10],
    ];

    $weights = [
        'standard' => ['common' => 64, 'unusual' => 22, 'rare' => 9, 'epic' => 3.5, 'legendary' => 1.2, 'mythic' => 0.3],
        'magic' => ['common' => 20, 'unusual' => 38, 'rare' => 24, 'epic' => 11, 'legendary' => 5, 'mythic' => 2],
        'echoes' => ['common' => 82, 'unusual' => 18, 'rare' => 0, 'epic' => 0, 'legendary' => 0, 'mythic' => 0],
        'characters' => ['common' => 64, 'unusual' => 22, 'rare' => 9, 'epic' => 3.5, 'legendary' => 1.2, 'mythic' => 0.3],
        'lineage' => ['common' => 46, 'unusual' => 30, 'rare' => 16, 'epic' => 6, 'legendary' => 1.6, 'mythic' => 0.4],
        'essence' => ['common' => 52, 'unusual' => 29, 'rare' => 13, 'epic' => 4.2, 'legendary' => 1.4, 'mythic' => 0.4],
        'powers' => ['common' => 64, 'unusual' => 22, 'rare' => 9, 'epic' => 3.5, 'legendary' => 1.2, 'mythic' => 0.3],
        'chronicles' => ['common' => 58, 'unusual' => 26, 'rare' => 11, 'epic' => 3.8, 'legendary' => 1, 'mythic' => 0.2],
        'relics' => ['common' => 55, 'unusual' => 28, 'rare' => 12, 'epic' => 3.8, 'legendary' => 1, 'mythic' => 0.2],
        'omens' => ['common' => 0, 'unusual' => 0, 'rare' => 70, 'epic' => 21, 'legendary' => 7, 'mythic' => 2],
        'gaian' => ['common' => 0, 'unusual' => 0, 'rare' => 0, 'epic' => 55, 'legendary' => 30, 'mythic' => 15],
    ];

    $packFilters = [
        'powers' => ['power', 'gift', 'rite', 'totem', 'discipline'],
        'chronicles' => ['chronicle', 'season', 'episode'],
        'relics' => ['object', 'document', 'totem'],
        'lineage' => ['character', 'tribe', 'auspice'],
        'essence' => ['system', 'tribe', 'auspice', 'form'],
    ];

    $materials = [
        ['icarus_vial', 'Vial de Ícaro', 10000, 'epic', 'Necesario para evolucionar de Raro a Épico.', 1, 0],
        ['stigma_orb', 'Orbe de Estigma', 50000, 'legendary', 'Necesario para evolucionar de Épico a Legendario.', 0, 1],
        ['babylon_shred', 'Retal de Babilonia', 125000, 'mythic', 'Necesario para evolucionar de Legendario a Mítico.', 0, 2],
        ['mnemo_glyph', 'Glifo mnemón', 500, 'common', 'Catalizador para aprender o cambiar habilidades en cartas.', 1, 3],
    ];

    $shopProducts = [
        ['daily_gift', 'daily_gift', 'Regalo diario', 'Cada día sale al azar uno de los materiales marcados como regalo diario.', 0, null, null, 1, 0],
        ['material_icarus_vial', 'material', 'Vial de Ícaro', 'Necesario para evolucionar de Raro a Épico.', 10000, 'icarus_vial', null, null, 10],
        ['material_stigma_orb', 'material', 'Orbe de Estigma', 'Necesario para evolucionar de Épico a Legendario.', 50000, 'stigma_orb', null, null, 11],
        ['material_babylon_shred', 'material', 'Retal de Babilonia', 'Necesario para evolucionar de Legendario a Mítico.', 125000, 'babylon_shred', null, null, 12],
        ['material_mnemo_glyph', 'material', 'Glifo mnemón', 'Catalizador para aprender o cambiar habilidades en cartas.', 500, 'mnemo_glyph', null, null, 13],
        ['exchange_remorias_10', 'exchange_remorias', 'Cambio por 10 Remorias', 'Tasa fija: 10 Mnemones = 1 Remoria.', 100, null, 10, null, 20],
        ['exchange_remorias_100', 'exchange_remorias', 'Cambio por 100 Remorias', 'Tasa fija: 10 Mnemones = 1 Remoria.', 1000, null, 100, null, 21],
        ['exchange_remorias_1000', 'exchange_remorias', 'Cambio por 1000 Remorias', 'Tasa fija: 10 Mnemones = 1 Remoria.', 10000, null, 1000, null, 22],
    ];

    $moves = [
        ['weakening_blow', 'Golpe debilitador', '🔵', 'damage', 0.8, null, 1, 2, 'enemy', ['kind' => 'debuff_atk', 'amount' => 0.1, 'minRatio' => 0.33], 'Inflige 80% del ATQ y reduce el ATQ enemigo hasta un mínimo del 33%.', 0],
        ['armor_breaker', 'Rompecorazas', '🟢', 'damage', 0.8, null, 1, 2, 'enemy', ['kind' => 'debuff_def', 'amount' => 0.1, 'minRatio' => 0.33], 'Inflige 80% del ATQ y reduce la DEF enemiga hasta un mínimo del 33%.', 1],
        ['discouraging_impact', 'Impacto descorazonador', '🧡', 'damage', 1.2, null, 1, 4, 'enemy', ['kind' => 'shield_break', 'chance' => 0.2, 'amount' => 1], 'Inflige 120% del ATQ y tiene un 20% de romper 1 escudo enemigo.', 2],
        ['brutal_strike', 'Golpe brutal', '💥', 'damage', 2, null, 1, 4, 'enemy', ['kind' => 'recoil', 'ratio' => 1 / 3], 'Inflige 200% del ATQ y devuelve un tercio del daño causado.', 3],
        ['phantom_leda', 'Leda fantasma', '🔮', 'damage', null, 'average_atk_def', 1, 3, 'enemy', ['kind' => 'lifesteal', 'ratio' => 0.5], 'Inflige (ATQ + DEF) / 2 y recupera la mitad del daño causado.', 4],
        ['hero_stance', 'Postura de héroe', '✨', 'buff', null, null, 1, 6, 'self', ['kind' => 'buff_atk_def', 'amount' => 0.1, 'maxRatio' => 1.5], 'Aumenta ATQ y DEF un 10% por uso, hasta un máximo de +50%.', 5],
    ];

    $moveLearnRules = [
        ['common', 0, 0],
        ['unusual', 0, 0],
        ['rare', 0.1, 1],
        ['epic', 0.3, 1],
        ['legendary', 0.6, 1],
        ['mythic', 1, 1],
        ['stigmatic', 1, 2],
    ];

    $settings = [
        ['starting_mnemones', '500', 'int', 'Mnemones iniciales.'],
        ['starting_remorias', '0', 'int', 'Remorias iniciales.'],
        ['max_mnemones', '9999999', 'int', 'Límite de Mnemones.'],
        ['max_remorias', '9999999', 'int', 'Limite de Remorias.'],
        ['daily_free_pack_cap', '3', 'int', 'Sobres gratis diarios.'],
        ['daily_shop_pack_cap', '10', 'int', 'Compras diarias por sobre de tienda.'],
        ['daily_magic_pack_cap', '3', 'int', 'Compras diarias de sobre mágico.'],
        ['shop_quantities', '[1,5,20]', 'json', 'Cantidades de compra de tienda.'],
        ['free_shop_quantities', '[1,3]', 'json', 'Cantidades de sobres gratis.'],
        ['rarity_upgrade_required', '5', 'int', 'Copias requeridas para evolucionar rareza.'],
        ['rarity_upgrade_min_quality', '50', 'int', 'Calidad minima para evolucionar rareza.'],
        ['rarity_upgrade_multipliers', '[1,1.2,1.5,2]', 'json', 'Multiplicadores de contribucion por diferencia de rareza.'],
        ['quality_upgrade_max_slots', '5', 'int', 'Maximo de copias para mejorar atributos.'],
        ['skill_slot_count', '3', 'int', 'Huecos de habilidades por carta.'],
        ['skill_base_mnemones', '100', 'int', 'Coste base de habilidad en Mnemones.'],
        ['skill_material_key', 'mnemo_glyph', 'string', 'Material usado para habilidades.'],
        ['work_max_assignments', '5', 'int', 'Cartas maximas rememorando.'],
        ['work_min_duration_ms', (string)(24 * 60 * 60 * 1000), 'int', 'Duracion minima de rememoracion.'],
        ['training_reward_table', json_encode([
            'base' => 5,
            'roll_min' => 1,
            'roll_max' => 5,
            'difficulty_multipliers' => [
                'apprentice' => 1,
                'hobbyist' => 1.25,
                'expert' => 1.5,
                'master' => 2,
                'nemesis' => 3,
            ],
        ], JSON_UNESCAPED_SLASHES), 'json', 'Tabla de recompensa de combate de entrenamiento.'],
        ['daily_boss_card_reward', json_encode([
            'rarity' => 'stigmatic',
        ], JSON_UNESCAPED_SLASHES), 'json', 'Recompensa especial de carta del jefe diario.'],
        ['daily_boss_loot_table', json_encode([
            'mnemones' => ['min' => 500, 'max' => 1200],
            'remorias' => ['min' => 120, 'max' => 420],
            'guaranteed_material_drop' => [
                ['key' => 'babylon_shred', 'chance' => 0.12, 'amount' => 1],
                ['key' => 'stigma_orb', 'chance' => 0.30, 'amount' => 1],
                ['key' => 'icarus_vial', 'chance' => 0.58, 'amount' => 1],
            ],
            'bonus_drops' => [
                ['key' => 'stigma_orb', 'chance' => 0.18, 'amount' => 1],
            ],
        ], JSON_UNESCAPED_SLASHES), 'json', 'Tabla de botin adicional del jefe diario.'],
        ['daily_boss_hp_multiplier_min', '30', 'int', 'Multiplicador mínimo de PV del jefe diario.'],
        ['daily_boss_hp_multiplier_max', '50', 'int', 'Multiplicador máximo de PV del jefe diario.'],
        ['daily_boss_stigmatic_damage_multiplier', '4', 'int', 'Multiplicador de daño estigmático contra jefe diario.'],
        ['daily_boss_shield_break_chance', '0.01', 'float', 'Probabilidad de romper escudo del jefe diario.'],
        ['move_debuff_min_ratio', '0.33', 'float', 'Limite inferior de debuffs de habilidad.'],
        ['move_buff_max_ratio', '1.5', 'float', 'Limite superior de buffs de habilidad.'],
        ['card_game_icons', json_encode([
            'evolve' => '/img/ui/card_game_icons/card_game_evolve_card.png',
            'upgrade' => '/img/ui/card_game_icons/card_game_upgrade_card.png',
            'sell' => '/img/ui/card_game_icons/card_game_sell_card.png',
            'remembrance' => '/img/ui/card_game_icons/card_game_remembrance.png',
        ], JSON_UNESCAPED_SLASHES), 'json', 'Iconos de acciones del juego de cartas.'],
        ['combat_sounds', json_encode([
            'attack' => ['/sounds/ui/attack1.ogg', '/sounds/ui/attack2.ogg'],
            'defend' => '/sounds/ui/heal.ogg',
            'switch' => '',
            'damage' => ['/sounds/ui/hit1.ogg', '/sounds/ui/hit2.ogg'],
            'victory' => '',
            'defeat' => '/sounds/ui/card_defeat.ogg',
        ], JSON_UNESCAPED_SLASHES), 'json', 'Sonidos de combate.'],
        ['combat_difficulty_table', json_encode([
            'apprentice' => [
                'label' => 'Aprendiz',
                'weights' => ['common' => 72, 'unusual' => 22, 'rare' => 6, 'epic' => 0, 'legendary' => 0, 'mythic' => 0],
            ],
            'hobbyist' => [
                'label' => 'Aficionado',
                'weights' => ['common' => 44, 'unusual' => 34, 'rare' => 17, 'epic' => 5, 'legendary' => 0, 'mythic' => 0],
            ],
            'expert' => [
                'label' => 'Experto',
                'weights' => ['common' => 12, 'unusual' => 28, 'rare' => 34, 'epic' => 18, 'legendary' => 6, 'mythic' => 2],
            ],
            'master' => [
                'label' => 'Maestro',
                'weights' => ['common' => 0, 'unusual' => 8, 'rare' => 32, 'epic' => 36, 'legendary' => 18, 'mythic' => 6],
            ],
            'nemesis' => [
                'label' => 'Némesis',
                'weights' => ['common' => 0, 'unusual' => 0, 'rare' => 12, 'epic' => 34, 'legendary' => 36, 'mythic' => 18],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 'json', 'Configuracion de dificultades de entrenamiento y pesos de rival.'],
        ['combat_advanced_rules', json_encode([
            'defend_heal_ratio' => 0.33,
            'defend_def_multiplier' => 1.5,
            'enemy_defend_hp_ratio' => 0.35,
            'enemy_defend_chance' => 0.34,
            'enemy_pick_attempts' => 12,
            'damage_random_bonus_min' => 1,
            'damage_random_bonus_max' => 20,
            'rarity_advantage_step' => 0.2,
            'rarity_disadvantage_step' => 0.13,
            'rarity_disadvantage_min_multiplier' => 0.35,
            'rarity_shields' => [
                'common' => 1,
                'unusual' => 2,
                'rare' => 3,
                'epic' => 4,
                'legendary' => 5,
                'mythic' => 6,
                'stigmatic' => 7,
            ],
        ], JSON_UNESCAPED_SLASHES), 'json', 'Reglas avanzadas parametrizables del motor de combate cliente.'],
    ];

    $uiTexts = [
        ['nav.shop', 'Tienda', 'Etiqueta de navegación.'],
        ['nav.collection', 'Colección', 'Etiqueta de navegación.'],
        ['confirm.default_title', 'Confirmar acción', 'Título por defecto de confirmación.'],
        ['confirm.cancel', 'Cancelar', 'Botón cancelar.'],
        ['status.local_storage_error', 'No se pudo guardar en localStorage.', 'Error de guardado local.'],
        ['status.loading_catalog', 'Cargando catálogo...', 'Estado de carga de catálogo.'],
        ['status.catalog_ready', 'Listo.', 'Catálogo cargado.'],
        ['status.catalog_empty', 'No hay cartas activas en el catálogo.', 'Catálogo vacío.'],
        ['status.catalog_load_error', 'No se pudo cargar el catálogo.', 'Error cargando catálogo.'],
        ['status.loading_rules', 'Cargando reglas del juego...', 'Estado de carga de reglas.'],
        ['status.rules_load_error', 'No se pudieron cargar las reglas del juego.', 'Error cargando reglas.'],
        ['status.no_cards_for_packs', 'No hay cartas disponibles para abrir sobres.', 'Sin cartas para sobres.'],
        ['status.no_standard_packs', 'No tienes sobres mnemónicos disponibles. Compra unidades desde la tienda.', 'Sin sobres básicos.'],
        ['status.no_pack_units', 'No tienes unidades de {pack}.', 'Sin unidades de un sobre.'],
        ['status.no_cards_for_pack_type', 'No hay cartas disponibles para este tipo de sobre.', 'Sin cartas para tipo de sobre.'],
        ['status.pack_opened', '{pack}: {count} cartas obtenidas.', 'Sobre abierto.'],
        ['status.pack_unknown', 'Ese sobre no existe.', 'Sobre desconocido.'],
        ['status.pack_not_shop', 'Ese sobre no está disponible en la tienda normal.', 'Sobre no comprable.'],
        ['status.pack_stock_full', 'No puedes acumular más de {max} sobres de cada tipo.', 'Stock de sobres lleno.'],
        ['status.free_pack_not_enough', 'No puedes reclamar {amount} sobres gratis. Quedan {remaining} hoy.', 'Sobres gratis insuficientes.'],
        ['status.free_pack_claimed', '{amount} sobre(s) mnemónicos gratis añadidos. Quedan {remaining} gratis hoy.', 'Sobres gratis reclamados.'],
        ['status.not_enough_mnemones_pack', 'No tienes Mnemones suficientes para comprar {pack}.', 'Saldo insuficiente para sobre.'],
        ['status.pack_daily_limit', 'Límite diario alcanzado para {pack}. Quedan {remaining} hoy.', 'Límite diario de sobre.'],
        ['status.pack_bought', '{amount} x {pack} añadidos a tus sobres.', 'Sobre comprado.'],
        ['status.material_unknown', 'Ese objeto no existe.', 'Objeto desconocido.'],
        ['status.not_enough_mnemones_material', 'No tienes Mnemones suficientes para comprar {material}.', 'Saldo insuficiente para objeto.'],
        ['status.material_inventory_error', 'No se ha podido añadir ese objeto al inventario.', 'Error inventario objeto.'],
        ['status.material_bought', '{amount} x {material} añadido(s) al inventario por {price} Mnemones. Tienes {stock}.', 'Objeto comprado.'],
        ['status.daily_gift_unknown', 'Ese regalo diario no existe.', 'Regalo diario desconocido.'],
        ['status.daily_gift_already_claimed', 'Ya has reclamado el regalo diario de hoy.', 'Regalo ya reclamado.'],
        ['status.daily_gift_claimed', 'Regalo diario reclamado: 1 x {material}.', 'Regalo reclamado.'],
        ['status.not_enough_mnemones_exchange', 'No tienes Mnemones suficientes para ese cambio.', 'Saldo insuficiente cambio.'],
        ['status.exchange_done', 'Cambio realizado: -{mnemones} Mnemones, +{remorias} Remorias.', 'Cambio realizado.'],
        ['status.no_packs_left_open_all', 'No te quedan sobres por abrir.', 'Sin sobres para abrir todo.'],
        ['status.open_all_done', 'Sobres abiertos: {opened}. Mostrando las últimas 5 cartas obtenidas.', 'Abrir todos.'],
        ['status.no_memory_claim', 'Todavía no hay Mnemones de rememoración para reclamar.', 'Sin rememoración reclamable.'],
        ['status.memory_claimed', 'Rememoración reclamada. +{amount} Mnemones.', 'Rememoración reclamada.'],
        ['status.card_already_remembering', 'Esta carta ya está rememorando.', 'Carta ya rememorando.'],
        ['status.memory_limit', 'Sólo puedes tener {max} cartas rememorando a la vez.', 'Límite rememoración.'],
        ['status.memory_card_in_team', 'Quita la carta del equipo antes de ponerla a recordar.', 'Carta en equipo.'],
        ['status.memory_started', 'Carta puesta a recordar: +{rate} Mnemones/min.', 'Rememoración iniciada.'],
        ['status.memory_card_missing', 'No se encontró esa carta para recordar.', 'Carta no encontrada.'],
        ['status.memory_min_duration', 'Esta carta debe rememorar al menos 24 horas. Quedan {remaining}.', 'Duración mínima rememoración.'],
        ['status.memory_stopped', 'Carta retirada de la rememoración. Sus ganancias quedan pendientes en Reclamar.', 'Rememoración retirada.'],
        ['shop.admin', 'Admin', 'Texto modo admin.'],
        ['shop.free_group', 'Gratis hoy', 'Grupo tienda gratis.'],
        ['shop.daily_gift_name', 'Regalo diario', 'Nombre regalo diario.'],
        ['shop.daily_gift_price_available', 'Gratis - 1 al día', 'Precio regalo diario disponible.'],
        ['shop.daily_gift_price_claimed', 'Reclamado hoy', 'Precio regalo diario reclamado.'],
        ['shop.daily_gift_description', 'Cada día sale al azar: {materials}.', 'Descripción regalo diario.'],
        ['shop.claim', 'Reclamar', 'Botón reclamar.'],
        ['shop.free_available', 'Gratis - quedan {remaining}', 'Sobres gratis disponibles.'],
        ['shop.free_sold_out', 'Agotado hoy', 'Sobres gratis agotados.'],
        ['shop.pack_price_stock', '{price} Mnemones', 'Precio de sobre.'],
        ['shop.pack_stock', 'quedan {remaining}', 'Stock diario de sobre.'],
        ['shop.free_pack_description', 'Reclama hasta {cap} sobres mnemónicos gratis al día.', 'Descripción sobre gratis.'],
        ['shop.free_pack_title', 'Sobres mnemónicos gratis. Quedan {remaining} hoy.', 'Título sobre gratis.'],
        ['shop.pack_title', '{pack}: {description} Precio: {price} Mnemones.', 'Título sobre.'],
        ['shop.free_pack_buy_title', 'Reclamar {amount} sobre(s) mnemónicos gratis', 'Título botón sobre gratis.'],
        ['shop.pack_buy_title', 'Comprar {amount} por {price} Mnemones{remainingText}', 'Título botón comprar sobre.'],
        ['shop.material_have', '{description} Tienes: {stock}.', 'Descripción material en tienda.'],
        ['shop.material_title', '{material}: {description} Precio: {price} Mnemones.', 'Título material en tienda.'],
        ['shop.material_buy_title', 'Comprar {amount} por {price} Mnemones', 'Título botón comprar material.'],
        ['shop.exchange_name', 'Cambio por {remorias} Remorias', 'Nombre cambio.'],
        ['shop.exchange_rate', 'Tasa fija: 10 Mnemones = 1 Remoria.', 'Descripción cambio.'],
        ['shop.exchange_title', 'Cambiar {mnemones} Mnemones por {remorias} Remorias.', 'Título cambio.'],
        ['shop.exchange_button', 'Cambiar', 'Botón cambio.'],
        ['packs.available_title', 'Sobres mnemónicos disponibles.', 'Título contador de sobres.'],
        ['packs.empty', 'No te quedan sobres. Puedes comprar más en la tienda o probar suerte en las Mazmorras.', 'Sin sobres.'],
        ['packs.admin_daily_free', 'Cupo diario libre en modo admin.', 'Cupo admin.'],
        ['collection.empty_filters', 'No hay cartas con estos filtros.', 'Colección vacía filtrada.'],
        ['collection.empty_obtained_filters', 'No hay cartas obtenidas con estos filtros.', 'Colección obtenida vacía filtrada.'],
        ['collection.undiscovered_slot', 'Ese hueco todavía no está descubierto.', 'Hueco no descubierto.'],
        ['collection.page_previous', 'Página anterior', 'Aria página anterior.'],
        ['collection.page_next', 'Página siguiente', 'Aria página siguiente.'],
        ['combat.team_empty', 'Equipo vacío. Prepáralo antes de combatir.', 'Equipo vacío.'],
        ['combat.team_full', 'El equipo ya tiene 5 cartas.', 'Equipo lleno.'],
        ['combat.auto_no_cards', 'No hay cartas disponibles para crear autoequipo con esos filtros.', 'Sin cartas para autoequipo.'],
        ['combat.auto_need_full', 'Necesitas al menos 5 cartas disponibles para crear un equipo rápido.', 'Autoequipo insuficiente.'],
        ['combat.auto_saved', 'Autoequipo guardado: {count}/5 mejores cartas disponibles.', 'Autoequipo guardado.'],
        ['combat.quick_team_prompt', '¿Quieres crear un equipo de 5 cartas rápido?', 'Pregunta autoequipo.'],
        ['combat.quick_team_title', 'Equipo rápido', 'Título autoequipo.'],
        ['combat.quick_team_confirm', 'Sí, crear equipo', 'Confirmar autoequipo.'],
        ['combat.quick_team_cancel', 'Ahora no', 'Cancelar autoequipo.'],
        ['combat.daily_reset', 'Jefe diario reiniciado para depuración.', 'Reset jefe diario.'],
        ['combat.daily_interrupted', 'Intento del Jefe diario interrumpido. Cartas derrotadas perdidas: {lost}.', 'Intento interrumpido.'],
        ['combat.daily_previous_closed', 'Intento anterior del Jefe diario cerrado. Cartas derrotadas perdidas: {lost}.', 'Intento anterior cerrado.'],
        ['combat.team_missing_card', 'Alguna carta del equipo ya no existe en la colección.', 'Carta de equipo perdida.'],
        ['combat.no_catalog_for_enemy', 'No hay suficientes cartas en el catálogo para generar rival.', 'Sin cartas rival.'],
        ['combat.training_log', 'Entrenamiento contra {label}.', 'Log entrenamiento.'],
        ['combat.enemy_draw', 'El rival saca una carta.', 'Rival saca carta.'],
        ['combat.started', '¡Combate iniciado!', 'Combate iniciado.'],
        ['combat.no_daily_characters', 'No hay personajes disponibles para generar el jefe diario.', 'Sin personajes jefe.'],
        ['combat.daily_completed', 'Desafío diario completado.', 'Jefe completado.'],
        ['combat.daily_started', 'Jefe diario iniciado. Alto riesgo.', 'Jefe iniciado.'],
        ['combat.daily_boss_log', 'Jefe diario: {card} emerge como Estigmático.', 'Log jefe diario.'],
        ['combat.daily_risk_log', 'Si tu equipo cae, esas 5 cartas se pierden.', 'Riesgo jefe diario.'],
        ['combat.daily_reward_already', 'Victoria contra el Jefe diario. La recompensa diaria ya fue reclamada.', 'Victoria jefe sin recompensa.'],
        ['combat.daily_reward_already_log', 'Has derrotado al Jefe diario, pero la carta Estigmática de hoy ya fue reclamada.', 'Log victoria jefe sin recompensa.'],
        ['combat.daily_victory_reward', 'Victoria contra el Jefe diario. Obtienes {card} Estigmático y botín: {loot}.', 'Victoria jefe con recompensa.'],
        ['combat.daily_victory_reward_log', 'Has derrotado al Jefe diario. Obtienes {card} Estigmático.', 'Log recompensa jefe.'],
        ['combat.daily_loot_log', 'Botín adicional: {loot}.', 'Log botín jefe.'],
        ['combat.daily_casualties_log', 'Cartas caídas durante el desafío: {count}.', 'Log bajas jefe.'],
        ['combat.daily_victory', 'Victoria contra el Jefe diario.', 'Victoria jefe.'],
        ['combat.daily_survives_log', 'Has derrotado al Jefe diario. Tu equipo sobrevive.', 'Log jefe sobrevive.'],
        ['combat.training_victory', 'Victoria de entrenamiento. +{reward} Mnemones.', 'Victoria entrenamiento.'],
        ['combat.training_victory_log', 'Has vencido al equipo rival. Ganas {reward} Mnemones.', 'Log victoria entrenamiento.'],
        ['combat.daily_destroy_team_log', 'El Jefe diario consume tu equipo. Pierdes {count} cartas.', 'Log equipo consumido.'],
        ['combat.training_defeat_log', 'Tu equipo ha caído. No pierdes cartas en entrenamiento.', 'Log derrota entrenamiento.'],
        ['combat.daily_shield_break_log', 'El impacto del Jefe diario quiebra 1 escudo de {card}.', 'Log rompe escudo jefe.'],
        ['combat.no_shields', 'Esta carta ya no tiene escudos.', 'Sin escudos.'],
        ['combat.move_unavailable', 'Movimiento no disponible.', 'Movimiento no disponible.'],
        ['combat.move_cooldown', 'Movimiento en recarga.', 'Movimiento recarga.'],
        ['combat.move_not_implemented', 'Movimiento aún no implementado.', 'Movimiento no implementado.'],
        ['combat.no_daily_flee', 'No puedes huir del Jefe diario.', 'No huir jefe.'],
        ['combat.flee_log', 'Huyes del entrenamiento. Sin coste y sin pérdida de cartas.', 'Log huida.'],
        ['combat.flee_done', 'Combate finalizado porque has huido.', 'Huida completada.'],
        ['combat.finish_before_mode', 'Termina el combate actual antes de cambiar de modo.', 'Cambio modo bloqueado.'],
        ['skill.no_new_moves', 'No quedan habilidades nuevas para este hueco.', 'Sin habilidades nuevas.'],
        ['skill.learned', 'Habilidad aprendida: {move}.', 'Habilidad aprendida.'],
        ['skill.changed', 'Habilidad cambiada: {move}.', 'Habilidad cambiada.'],
        ['skill.change_action', 'Cambiar', 'Acción cambiar habilidad.'],
        ['skill.learn_action', 'Aprender', 'Acción aprender habilidad.'],
        ['skill.empty_slot', 'Hueco vacío', 'Hueco habilidad vacío.'],
        ['skill.confirm_title_change', 'Cambiar habilidad', 'Título cambiar habilidad.'],
        ['skill.confirm_title_learn', 'Aprender habilidad', 'Título aprender habilidad.'],
        ['skill.confirm_random', 'Resultado aleatorio y sin duplicados. ¿Seguir?', 'Aviso habilidad aleatoria.'],
        ['upgrade.rarity_max', 'Esta copia ya está en la rareza máxima.', 'Rareza máxima.'],
        ['upgrade.rarity_title', 'Evolucionar rareza', 'Título evolución rareza.'],
        ['upgrade.close', 'Cerrar', 'Cerrar modal.'],
        ['upgrade.confirm', 'Evolucionar', 'Confirmar evolución.'],
        ['upgrade.cancel', 'Cancelar', 'Cancelar evolución.'],
        ['upgrade.remove_memory_first', 'Retira la carta de la rememoración antes de evolucionarla.', 'Evolución bloqueada por rememoración.'],
        ['upgrade.reset_skills_message', 'Esta evolución reinicia todas las habilidades aprendidas de la carta. Si ahora tiene habilidades, pasará a {rarity} sin habilidades. ¿Seguir?', 'Aviso pérdida habilidades.'],
        ['upgrade.reset_skills_title', 'Perder habilidades', 'Título pérdida habilidades.'],
        ['upgrade.reset_skills_confirm', 'Sí, evolucionar', 'Confirmar pérdida habilidades.'],
        ['upgrade.need_sacrifices', 'Elige sacrificios suficientes para completar la evolución.', 'Faltan sacrificios.'],
        ['upgrade.missing_cost', 'Faltan Remorias u objetos rituales para evolucionar.', 'Falta coste evolución.'],
        ['upgrade.done', 'Rareza evolucionada a {rarity}. Coste: {cost} Remorias.', 'Evolución completada.'],
        ['improve.quality_max', 'Esta copia ya tiene calidad 100%.', 'Calidad máxima.'],
        ['improve.title', 'Mejorar atributos', 'Título mejorar atributos.'],
        ['improve.confirm', 'Mejorar', 'Confirmar mejorar.'],
        ['improve.remove_memory_first', 'Retira la carta de la rememoración antes de mejorarla.', 'Mejora bloqueada por rememoración.'],
        ['improve.need_sacrifices', 'Elige al menos una carta para mejorar atributos.', 'Faltan sacrificios mejora.'],
        ['improve.no_gain', 'Esos sacrificios no mejoran la calidad.', 'Sin mejora.'],
        ['improve.missing_cost', 'Faltan Remorias para mejorar atributos.', 'Falta coste mejora.'],
        ['improve.done', 'Atributos mejorados a CAL {quality}%. Coste: {cost} Remorias.', 'Mejora completada.'],
        ['recycle.favorite_only_owned', 'Solo puedes marcar como favorita una copia que tengas.', 'Favorita no propia.'],
        ['recycle.favorite_removed', 'Copia retirada de favoritas.', 'Favorita retirada.'],
        ['recycle.favorite_added', 'Copia marcada como favorita.', 'Favorita añadida.'],
        ['recycle.favorite_blocked', 'Esta copia es favorita y no se puede vender.', 'Venta bloqueada favorita.'],
        ['recycle.remove_memory_first', 'Retira la carta de la rememoración antes de venderla.', 'Venta bloqueada rememoración.'],
        ['recycle.confirm_single', 'Vas a desintegrar una copia {rarity}.', 'Confirmar venta copia.'],
        ['recycle.single_title', 'Desintegrar carta', 'Título venta copia.'],
        ['recycle.confirm', 'Desintegrar', 'Confirmar desintegrar.'],
        ['recycle.single_done', 'Copia desintegrada. +{gained} Remorias.{extra}', 'Venta copia completada.'],
        ['recycle.no_duplicates', 'No hay duplicadas que desintegrar.', 'Sin duplicadas.'],
        ['recycle.no_sellable_duplicates', 'No hay duplicadas vendibles: las duplicadas son favoritas.', 'Sin duplicadas vendibles.'],
        ['recycle.duplicates_memory_blocked', 'Retira primero las duplicadas que están rememorando.', 'Duplicadas bloqueadas por rememoración.'],
        ['recycle.duplicates_title', 'Desintegrar duplicadas', 'Título duplicadas.'],
        ['recycle.duplicates_done', 'Duplicadas desintegradas. +{gained} Remorias.{extra}', 'Duplicadas vendidas.'],
        ['recycle.no_copies', 'No hay copias que desintegrar.', 'Sin copias.'],
        ['recycle.all_favorites', 'Todas las copias son favoritas.', 'Todas favoritas.'],
        ['recycle.all_memory_blocked', 'Retira primero las cartas que están rememorando.', 'Todas bloqueadas por rememoración.'],
        ['recycle.all_title', 'Desintegrar todas', 'Título desintegrar todas.'],
        ['recycle.all_done', 'Copias no favoritas desintegradas. +{gained} Remorias.{extra}', 'Todas vendidas.'],
        ['recycle.wait_catalog', 'Espera a que cargue el catálogo.', 'Catálogo no cargado.'],
        ['recycle.invalid_rarity', 'Rareza no válida.', 'Rareza inválida.'],
        ['recycle.sale_done', 'Venta completada. +{gained} Remorias.{extra}', 'Venta por rareza completada.'],
        ['collection.export_done', 'Colección y equipos exportados a JSON.', 'Export completado.'],
        ['collection.import_done', 'Colección importada correctamente.', 'Import completado.'],
        ['collection.reset_done', 'Colección local borrada.', 'Reset colección.'],
        ['collection.read_json_error', 'No se pudo leer el archivo JSON.', 'Error lectura JSON.'],
        ['memory.summary_active', 'rememorando', 'Resumen rememorando.'],
        ['memory.summary_rate', 'Mn/min', 'Resumen ritmo rememoración.'],
        ['memory.summary_claimable', 'reclamables', 'Resumen reclamables.'],
        ['memory.claim_button', 'Reclamar', 'Botón reclamar rememoración.'],
        ['memory.claim_button_amount', 'Reclamar +{amount}', 'Botón reclamar con cantidad.'],
        ['memory.slot_label', 'Hueco {slot}', 'Etiqueta hueco.'],
        ['memory.empty_slot_text', 'Elige una carta para recordar.', 'Texto hueco vacío rememoración.'],
        ['memory.select_label', 'Carta para recordar en hueco {slot}', 'Aria selector rememoración.'],
        ['memory.remember_action', 'Recordar', 'Acción recordar.'],
        ['memory.remember_selected_label', 'Recordar carta seleccionada', 'Aria recordar carta.'],
        ['memory.no_cards', 'No hay cartas disponibles.', 'Sin cartas rememoración.'],
        ['memory.gains', 'Ganancias: +{amount}', 'Ganancias rememoración.'],
        ['memory.can_return', 'Puede volver', 'Puede volver rememoración.'],
        ['memory.returns_in', 'Vuelve en {time}', 'Vuelve en rememoración.'],
        ['memory.stop', 'Retirar', 'Retirar rememoración.'],
        ['memory.min_duration_title', 'Debe rememorar al menos 24 horas.', 'Título duración mínima.'],
        ['combat.slot_empty', 'Sin carta', 'Hueco combate sin carta.'],
        ['combat.team_total', 'Total equipo', 'Total equipo.'],
        ['combat.team_total_full', 'Total del equipo', 'Total del equipo.'],
        ['combat.choose_card', 'Elige una carta', 'Elegir carta combate.'],
        ['combat.cards_count', '{count} / 5 cartas', 'Contador cartas equipo.'],
        ['combat.cards_filter_empty', 'No hay cartas disponibles con esos filtros.', 'Sin cartas combate filtro.'],
        ['combat.cards_loading', 'Cargando cartas...', 'Cargando cartas combate.'],
        ['combat.daily_unavailable_title', 'Jefe diario no disponible', 'Título jefe no disponible.'],
        ['combat.daily_unavailable_text', 'No hay carta válida para generar el desafío.', 'Texto jefe no disponible.'],
        ['combat.daily_completed_title', 'Desafío diario completado', 'Título jefe completado.'],
        ['combat.daily_completed_text', 'Vuelve mañana para otro Jefe diario.', 'Texto jefe completado.'],
        ['combat.daily_reset_button', 'Reset admin', 'Botón reset jefe admin.'],
        ['combat.switch_log', 'Cambias a {card}.', 'Log cambio carta.'],
        ['combat.action_slot', 'Acción {slot}', 'Hueco acción combate.'],
        ['combat.shields_title', 'Escudos {current} / {max}', 'Título escudos.'],
        ['combat.cancel', 'Cancelar', 'Cancelar combate.'],
        ['combat.back', 'Volver', 'Volver combate.'],
        ['combat.switch_empty', 'No hay cartas disponibles para cambiar.', 'Sin cartas cambio.'],
        ['combat.training_victory_title', '¡Superaste el entrenamiento!', 'Título victoria entrenamiento.'],
        ['combat.training_defeat_title', '¡Te han derrotado!', 'Título derrota entrenamiento.'],
        ['combat.daily_victory_title', 'Jefe diario derrotado', 'Título victoria jefe.'],
        ['combat.daily_defeat_title', 'El Jefe diario vence', 'Título derrota jefe.'],
        ['combat.daily_card_reward_text', 'Obtienes la carta Estigmática del Jefe diario.', 'Texto recompensa jefe.'],
        ['combat.daily_card_already_text', 'Ya habías reclamado la carta Estigmática de hoy.', 'Texto recompensa ya reclamada.'],
        ['combat.daily_team_lost_text', 'Las 5 cartas usadas en el intento se han perdido.', 'Texto equipo perdido.'],
        ['combat.training_reward_text', 'Recompensa: +{reward} Mnemones.', 'Texto recompensa entrenamiento.'],
        ['combat.training_no_loss_text', 'No pierdes cartas en entrenamiento.', 'Texto sin pérdida entrenamiento.'],
        ['combat.retry_daily', 'Reintentar jefe diario', 'Botón reintentar jefe.'],
        ['combat.restart_training', 'Empezar otro combate', 'Botón reiniciar entrenamiento.'],
        ['card.flip_label', 'Girar carta', 'Aria girar carta.'],
        ['card.favorite_label', 'Carta favorita', 'Aria favorita.'],
        ['card.total', 'Total {total}', 'Total de carta.'],
        ['card.skill_slots', '{count} huecos', 'Huecos de habilidades.'],
        ['card.learned_moves', 'Habilidades aprendidas', 'Título habilidades aprendidas.'],
        ['card.results_label', 'Cartas obtenidas', 'Aria resultados sobre.'],
        ['card.close', 'Cerrar', 'Cerrar modal carta.'],
        ['card.previous', 'Carta anterior', 'Aria carta anterior.'],
        ['card.next', 'Carta siguiente', 'Aria carta siguiente.'],
        ['card.view_card', 'Ver carta {index}', 'Aria ver carta.'],
        ['card.close_card', 'Cerrar carta', 'Aria cerrar carta.'],
        ['card.variants_summary', 'Variantes obtenidas ({count})', 'Resumen variantes.'],
        ['card.recycle_all_title', 'Desintegrar no favoritas: +{amount} Remorias', 'Título desintegrar no favoritas.'],
        ['card.recycle_all_label', 'Desintegrar todas', 'Aria desintegrar todas.'],
        ['card.favorite_remove', 'Quitar favorita', 'Quitar favorita.'],
        ['card.favorite_add', 'Marcar como favorita', 'Marcar favorita.'],
        ['card.recycle_title', 'Desintegrar: +{amount} Remorias', 'Título desintegrar copia.'],
        ['card.recycle_copy_label', 'Desintegrar esta copia por {amount} Remorias', 'Aria desintegrar copia.'],
        ['card.evolve_title', 'Evolucionar', 'Título evolucionar carta.'],
        ['card.evolve_label', 'Evolucionar rareza de esta copia', 'Aria evolucionar carta.'],
        ['card.improve_title', 'Mejorar', 'Título mejorar carta.'],
        ['card.improve_label', 'Mejorar atributos de esta copia', 'Aria mejorar carta.'],
    ];

    return compact('rarities', 'types', 'packs', 'weights', 'packFilters', 'materials', 'shopProducts', 'moves', 'moveLearnRules', 'settings', 'uiTexts');
}

function hg_gcr_table_exists(mysqli $link, string $table): bool
{
    $safe = $link->real_escape_string($table);
    $rs = $link->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$safe}' LIMIT 1");
    return $rs instanceof mysqli_result && $rs->num_rows > 0;
}

function hg_gcr_decode_setting(string $value, string $type)
{
    if ($type === 'int') {
        return (int)$value;
    }
    if ($type === 'float') {
        return (float)$value;
    }
    if ($type === 'bool') {
        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }
    if ($type === 'json') {
        $decoded = json_decode($value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }
    return $value;
}

function hg_gcr_build_payload(mysqli $link): array
{
    $required = [
        'dim_game_card_rarities',
        'dim_game_card_types',
        'dim_game_card_pack_types',
        'fact_game_card_pack_rarity_weights',
        'fact_game_card_pack_type_filters',
        'dim_game_card_materials',
        'dim_game_card_shop_products',
        'dim_game_card_moves',
        'fact_game_card_move_learn_rules',
        'dim_game_card_settings',
        'dim_game_card_ui_texts',
    ];
    foreach ($required as $table) {
        if (!hg_gcr_table_exists($link, $table)) {
            throw new RuntimeException('missing_table:' . $table);
        }
    }

    $legacy = [
        'RARITY_LABELS' => [],
        'RARITY_ICONS' => [],
        'RARITY_SHORT' => [],
        'RARITY_SKINS' => [],
        'RARITY_STAT_RANGES' => [],
        'RARITY_ORDER' => [],
        'NATURAL_RARITY_ORDER' => [],
        'RARITY_UPGRADE_ORDER' => [],
        'RARITY_WEIGHTS' => [],
        'RECYCLE_VALUES' => [],
        'WORK_RARITY_BASE' => [],
        'SKILL_COST_MULTIPLIER_BY_RARITY' => [],
        'UPGRADE_COST_BY_RARITY' => [],
        'RARITY_UPGRADE_MATERIALS' => [],
        'TYPE_LABELS' => [],
        'TYPE_ORDER' => [],
        'TYPE_EMOJI' => [],
        'TYPE_ALIASES' => [],
        'TYPE_ICON_SVG' => [],
        'PACK_KINDS' => [],
        'SHOP_PACK_KINDS' => [],
        'PACK_PRICES' => [],
        'PACK_LABELS' => [],
        'PACK_CONTENTS' => [],
        'PACK_SKINS' => [],
        'PACK_RARITY_WEIGHTS' => [],
        'UPGRADE_MATERIALS' => [],
        'DAILY_GIFT_MATERIAL_KEYS' => [],
        'MOVE_LIBRARY' => [],
        'MOVE_LEARN_RULES' => [],
        'UI_TEXTS' => [],
    ];

    $rarities = [];
    $rs = $link->query("SELECT * FROM dim_game_card_rarities WHERE is_active = 1 ORDER BY sort_order, rarity_key");
    while ($rs && ($row = $rs->fetch_assoc())) {
        $key = (string)$row['rarity_key'];
        $rarities[] = $row;
        $legacy['RARITY_LABELS'][$key] = (string)$row['label'];
        $legacy['RARITY_ICONS'][$key] = (string)$row['icon_url'];
        $legacy['RARITY_SHORT'][$key] = (string)$row['short_label'];
        $legacy['RARITY_SKINS'][$key] = [
            'color' => (string)($row['skin_color'] ?? ''),
            'bg' => (string)($row['skin_bg'] ?? ''),
            'head' => (string)($row['skin_head'] ?? ''),
            'body' => (string)($row['skin_body'] ?? ''),
            'rowBg' => (string)($row['skin_row_bg'] ?? ''),
        ];
        $legacy['RARITY_STAT_RANGES'][$key] = [(int)$row['stat_min'], (int)$row['stat_max']];
        $legacy['RARITY_ORDER'][] = $key;
        if ($row['natural_order'] !== null) {
            $legacy['NATURAL_RARITY_ORDER'][] = $key;
            $legacy['RARITY_WEIGHTS'][$key] = (float)$row['base_weight'];
        }
        if ($row['upgrade_order'] !== null) {
            $legacy['RARITY_UPGRADE_ORDER'][] = $key;
        }
        $legacy['RECYCLE_VALUES'][$key] = (int)$row['recycle_value'];
        $legacy['WORK_RARITY_BASE'][$key] = (int)$row['work_base'];
        $legacy['SKILL_COST_MULTIPLIER_BY_RARITY'][$key] = (int)$row['skill_cost_multiplier'];
        $legacy['UPGRADE_COST_BY_RARITY'][$key] = (int)$row['upgrade_cost'];
        if (!empty($row['upgrade_required_material_key'])) {
            $legacy['RARITY_UPGRADE_MATERIALS'][$key] = (string)$row['upgrade_required_material_key'];
        }
    }

    $types = [];
    $rs = $link->query("SELECT * FROM dim_game_card_types WHERE is_active = 1 ORDER BY sort_order, type_key");
    while ($rs && ($row = $rs->fetch_assoc())) {
        $key = (string)$row['type_key'];
        $types[] = $row;
        $legacy['TYPE_LABELS'][$key] = (string)$row['label'];
        if ((string)($row['emoji'] ?? '') !== '') {
            $legacy['TYPE_EMOJI'][$key] = (string)$row['emoji'];
        }
        if ((string)($row['icon_svg'] ?? '') !== '') {
            $legacy['TYPE_ICON_SVG'][$key] = (string)$row['icon_svg'];
        }
        if ((int)$row['is_filterable'] === 1) {
            $legacy['TYPE_ORDER'][] = $key;
        }
        if ((string)($row['canonical_type_key'] ?? '') !== '') {
            $legacy['TYPE_ALIASES'][$key] = (string)$row['canonical_type_key'];
        }
    }

    $packs = [];
    $rs = $link->query("SELECT * FROM dim_game_card_pack_types WHERE is_active = 1 ORDER BY sort_order, pack_key");
    while ($rs && ($row = $rs->fetch_assoc())) {
        $key = (string)$row['pack_key'];
        $packs[] = $row;
        $legacy['PACK_KINDS'][] = $key;
        if ((int)$row['is_shop_pack'] === 1) {
            $legacy['SHOP_PACK_KINDS'][] = $key;
        }
        $legacy['PACK_PRICES'][$key] = (int)$row['price'];
        $legacy['PACK_LABELS'][$key] = (string)$row['label'];
        $legacy['PACK_CONTENTS'][$key] = (string)$row['description'];
        $legacy['PACK_SKINS'][$key] = [
            'seal' => (string)($row['seal_text'] ?? ''),
            'summary' => (string)($row['short_description'] ?? ''),
            'primary' => (string)($row['skin_primary'] ?? ''),
            'secondary' => (string)($row['skin_secondary'] ?? ''),
            'dark' => (string)($row['skin_dark'] ?? ''),
            'accent' => (string)($row['skin_accent'] ?? ''),
        ];
    }

    $rs = $link->query("SELECT pack_key, rarity_key, weight FROM fact_game_card_pack_rarity_weights ORDER BY pack_key, rarity_key");
    while ($rs && ($row = $rs->fetch_assoc())) {
        $pack = (string)$row['pack_key'];
        if (!isset($legacy['PACK_RARITY_WEIGHTS'][$pack])) {
            $legacy['PACK_RARITY_WEIGHTS'][$pack] = [];
        }
        $legacy['PACK_RARITY_WEIGHTS'][$pack][(string)$row['rarity_key']] = (float)$row['weight'];
    }

    $packFilters = [];
    $rs = $link->query("SELECT pack_key, type_key FROM fact_game_card_pack_type_filters ORDER BY pack_key, sort_order, type_key");
    while ($rs && ($row = $rs->fetch_assoc())) {
        $pack = (string)$row['pack_key'];
        if (!isset($packFilters[$pack])) {
            $packFilters[$pack] = [];
        }
        $packFilters[$pack][] = (string)$row['type_key'];
    }
    $legacy['POWER_TYPES'] = $packFilters['powers'] ?? [];
    $legacy['CHRONICLE_TYPES'] = $packFilters['chronicles'] ?? [];
    $legacy['RELIC_TYPES'] = $packFilters['relics'] ?? [];
    $legacy['LINEAGE_TYPES'] = $packFilters['lineage'] ?? [];
    $legacy['ESSENCE_TYPES'] = $packFilters['essence'] ?? [];

    $materials = [];
    $rs = $link->query("SELECT * FROM dim_game_card_materials WHERE is_active = 1 ORDER BY sort_order, material_key");
    while ($rs && ($row = $rs->fetch_assoc())) {
        $key = (string)$row['material_key'];
        $materials[] = $row;
        $legacy['UPGRADE_MATERIALS'][$key] = [
            'label' => (string)$row['label'],
            'price' => (int)$row['price'],
            'rarity' => (string)$row['rarity_key'],
            'description' => (string)$row['description'],
        ];
        if ((int)$row['is_daily_gift'] === 1) {
            $legacy['DAILY_GIFT_MATERIAL_KEYS'][] = $key;
        }
    }

    $shopProducts = [];
    $rs = $link->query("SELECT * FROM dim_game_card_shop_products WHERE is_active = 1 ORDER BY sort_order, product_key");
    while ($rs && ($row = $rs->fetch_assoc())) {
        $shopProducts[] = [
            'product_key' => (string)$row['product_key'],
            'product_type' => (string)$row['product_type'],
            'label' => (string)$row['label'],
            'description' => (string)($row['description'] ?? ''),
            'price_mnemones' => (int)$row['price_mnemones'],
            'material_key' => (string)($row['material_key'] ?? ''),
            'remorias_amount' => $row['remorias_amount'] !== null ? (int)$row['remorias_amount'] : null,
            'daily_cap' => $row['daily_cap'] !== null ? (int)$row['daily_cap'] : null,
        ];
    }

    $moves = [];
    $rs = $link->query("SELECT * FROM dim_game_card_moves WHERE is_active = 1 ORDER BY sort_order, move_key");
    while ($rs && ($row = $rs->fetch_assoc())) {
        $key = (string)$row['move_key'];
        $effect = json_decode((string)($row['effect_json'] ?? ''), true);
        if (!is_array($effect)) {
            $effect = null;
        }
        $move = [
            'id' => $key,
            'label' => (string)$row['label'],
            'icon' => (string)$row['icon'],
            'type' => (string)$row['move_type'],
            'accuracy' => (float)$row['accuracy'],
            'cooldown' => (int)$row['cooldown'],
            'target' => (string)$row['target'],
            'description' => (string)$row['description'],
        ];
        if ($row['power'] !== null) {
            $move['power'] = (float)$row['power'];
        }
        if (!empty($row['formula'])) {
            $move['formula'] = (string)$row['formula'];
        }
        if ($effect !== null) {
            $move['effect'] = $effect;
        }
        $moves[] = $move;
        $legacy['MOVE_LIBRARY'][$key] = $move;
    }

    $rs = $link->query("SELECT rarity_key, chance, move_count FROM fact_game_card_move_learn_rules ORDER BY rarity_key");
    while ($rs && ($row = $rs->fetch_assoc())) {
        $legacy['MOVE_LEARN_RULES'][(string)$row['rarity_key']] = [
            'chance' => (float)$row['chance'],
            'count' => (int)$row['move_count'],
        ];
    }

    $settings = [];
    $rs = $link->query("SELECT setting_key, setting_value, value_type FROM dim_game_card_settings ORDER BY setting_key");
    while ($rs && ($row = $rs->fetch_assoc())) {
        $settings[(string)$row['setting_key']] = hg_gcr_decode_setting((string)$row['setting_value'], (string)$row['value_type']);
    }

    $rs = $link->query("SELECT text_key, text_value FROM dim_game_card_ui_texts WHERE is_active = 1 ORDER BY sort_order, text_key");
    while ($rs && ($row = $rs->fetch_assoc())) {
        $legacy['UI_TEXTS'][(string)$row['text_key']] = (string)$row['text_value'];
    }

    return [
        'success' => true,
        'version' => '2026-05-31-v4',
        'settings' => $settings,
        'rarities' => $rarities,
        'types' => $types,
        'packs' => $packs,
        'packTypeFilters' => $packFilters,
        'materials' => $materials,
        'shopProducts' => $shopProducts,
        'moves' => $moves,
        'legacy' => $legacy,
    ];
}
