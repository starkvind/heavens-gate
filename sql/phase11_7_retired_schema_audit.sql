-- Heaven's Gate — Phase 11.7 retired database surface audit
-- READ-ONLY. Safe to run before the destructive cleanup.
-- Target: MariaDB production database selected by the caller.

SELECT DATABASE() AS database_name, NOW() AS audited_at;

-- Known retired tables/views and their current size/row estimate.
SELECT
    TABLE_NAME,
    TABLE_TYPE,
    COALESCE(TABLE_ROWS, 0) AS approx_rows,
    ROUND((COALESCE(DATA_LENGTH,0) + COALESCE(INDEX_LENGTH,0)) / 1024 / 1024, 2) AS size_mb
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
    'dim_game_card_materials',
    'dim_game_card_moves',
    'dim_game_card_pack_types',
    'dim_game_card_rarities',
    'dim_game_card_settings',
    'dim_game_card_shop_products',
    'dim_game_card_types',
    'dim_game_card_ui_texts',
    'fact_game_card_collection',
    'fact_game_card_move_learn_rules',
    'fact_game_card_pack_rarity_weights',
    'fact_game_card_pack_type_filters',
    'fact_sim_battles',
    'fact_sim_character_scores',
    'fact_sim_characters_talk',
    'fact_sim_item_usage',
    'fact_sim_seasons',
    'fact_sim_tournaments',
    'bridge_battle_sim_characters_seasons',
    'vw_game_card_collection',
    'vw_sim_characters',
    'vw_sim_forms',
    'vw_sim_items',
    'admin_webp_image_migration_backup',
    '_id_unsigned_audit'
  )
ORDER BY TABLE_TYPE, TABLE_NAME;

-- Retired-looking objects not covered by the reviewed allow-list.
SELECT
    TABLE_NAME,
    TABLE_TYPE
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND (
    TABLE_NAME LIKE '%game_card%'
    OR TABLE_NAME LIKE 'fact_sim_%'
    OR TABLE_NAME LIKE '%battle_sim%'
    OR TABLE_NAME LIKE 'vw_sim_%'
    OR TABLE_NAME LIKE '%combat_sim%'
  )
  AND TABLE_NAME NOT IN (
    'dim_game_card_materials',
    'dim_game_card_moves',
    'dim_game_card_pack_types',
    'dim_game_card_rarities',
    'dim_game_card_settings',
    'dim_game_card_shop_products',
    'dim_game_card_types',
    'dim_game_card_ui_texts',
    'fact_game_card_collection',
    'fact_game_card_move_learn_rules',
    'fact_game_card_pack_rarity_weights',
    'fact_game_card_pack_type_filters',
    'fact_sim_battles',
    'fact_sim_character_scores',
    'fact_sim_characters_talk',
    'fact_sim_item_usage',
    'fact_sim_seasons',
    'fact_sim_tournaments',
    'bridge_battle_sim_characters_seasons',
    'vw_game_card_collection',
    'vw_sim_characters',
    'vw_sim_forms',
    'vw_sim_items'
  )
ORDER BY TABLE_NAME;

-- Foreign keys FROM live tables INTO retired tables. This result must be empty before cleanup.
SELECT
    k.TABLE_NAME,
    k.COLUMN_NAME,
    k.CONSTRAINT_NAME,
    k.REFERENCED_TABLE_NAME,
    k.REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE k
WHERE k.TABLE_SCHEMA = DATABASE()
  AND k.REFERENCED_TABLE_SCHEMA = DATABASE()
  AND k.REFERENCED_TABLE_NAME IN (
    'dim_game_card_materials',
    'dim_game_card_moves',
    'dim_game_card_pack_types',
    'dim_game_card_rarities',
    'dim_game_card_settings',
    'dim_game_card_shop_products',
    'dim_game_card_types',
    'dim_game_card_ui_texts',
    'fact_game_card_collection',
    'fact_game_card_move_learn_rules',
    'fact_game_card_pack_rarity_weights',
    'fact_game_card_pack_type_filters',
    'fact_sim_battles',
    'fact_sim_character_scores',
    'fact_sim_characters_talk',
    'fact_sim_item_usage',
    'fact_sim_seasons',
    'fact_sim_tournaments',
    'bridge_battle_sim_characters_seasons'
  )
  AND k.TABLE_NAME NOT IN (
    'dim_game_card_materials',
    'dim_game_card_moves',
    'dim_game_card_pack_types',
    'dim_game_card_rarities',
    'dim_game_card_settings',
    'dim_game_card_shop_products',
    'dim_game_card_types',
    'dim_game_card_ui_texts',
    'fact_game_card_collection',
    'fact_game_card_move_learn_rules',
    'fact_game_card_pack_rarity_weights',
    'fact_game_card_pack_type_filters',
    'fact_sim_battles',
    'fact_sim_character_scores',
    'fact_sim_characters_talk',
    'fact_sim_item_usage',
    'fact_sim_seasons',
    'fact_sim_tournaments',
    'bridge_battle_sim_characters_seasons'
  )
ORDER BY k.TABLE_NAME, k.CONSTRAINT_NAME;

-- Views outside the retired set that still mention the retired surface. Must be empty.
SELECT
    TABLE_NAME AS view_name
FROM information_schema.VIEWS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME NOT IN (
    'vw_game_card_collection',
    'vw_sim_characters',
    'vw_sim_forms',
    'vw_sim_items'
  )
  AND LOWER(COALESCE(VIEW_DEFINITION, '')) REGEXP 'game_card|fact_sim_|battle_sim|combat_sim|vw_sim_'
ORDER BY TABLE_NAME;

-- Known object names with an unexpected object type. Must be empty.
SELECT TABLE_NAME, TABLE_TYPE
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND (
    (TABLE_NAME IN (
      'vw_game_card_collection',
      'vw_sim_characters',
      'vw_sim_forms',
      'vw_sim_items'
    ) AND TABLE_TYPE <> 'VIEW')
    OR
    (TABLE_NAME IN (
      'dim_game_card_materials',
      'dim_game_card_moves',
      'dim_game_card_pack_types',
      'dim_game_card_rarities',
      'dim_game_card_settings',
      'dim_game_card_shop_products',
      'dim_game_card_types',
      'dim_game_card_ui_texts',
      'fact_game_card_collection',
      'fact_game_card_move_learn_rules',
      'fact_game_card_pack_rarity_weights',
      'fact_game_card_pack_type_filters',
      'fact_sim_battles',
      'fact_sim_character_scores',
      'fact_sim_characters_talk',
      'fact_sim_item_usage',
      'fact_sim_seasons',
      'fact_sim_tournaments',
      'bridge_battle_sim_characters_seasons',
      'admin_webp_image_migration_backup',
      '_id_unsigned_audit'
    ) AND TABLE_TYPE <> 'BASE TABLE')
  )
ORDER BY TABLE_NAME;

-- Triggers on live tables that mention the retired surface. Must be empty.
SELECT
    TRIGGER_NAME,
    EVENT_OBJECT_TABLE,
    ACTION_TIMING,
    EVENT_MANIPULATION
FROM information_schema.TRIGGERS
WHERE TRIGGER_SCHEMA = DATABASE()
  AND EVENT_OBJECT_TABLE NOT IN (
    'dim_game_card_materials',
    'dim_game_card_moves',
    'dim_game_card_pack_types',
    'dim_game_card_rarities',
    'dim_game_card_settings',
    'dim_game_card_shop_products',
    'dim_game_card_types',
    'dim_game_card_ui_texts',
    'fact_game_card_collection',
    'fact_game_card_move_learn_rules',
    'fact_game_card_pack_rarity_weights',
    'fact_game_card_pack_type_filters',
    'fact_sim_battles',
    'fact_sim_character_scores',
    'fact_sim_characters_talk',
    'fact_sim_item_usage',
    'fact_sim_seasons',
    'fact_sim_tournaments',
    'bridge_battle_sim_characters_seasons'
  )
  AND LOWER(ACTION_STATEMENT) REGEXP 'game_card|fact_sim_|battle_sim|combat_sim|vw_sim_'
ORDER BY TRIGGER_NAME;

-- Scheduled events and routines that mention the retired surface.
SELECT
    EVENT_NAME
FROM information_schema.EVENTS
WHERE EVENT_SCHEMA = DATABASE()
  AND LOWER(COALESCE(EVENT_DEFINITION, '')) REGEXP 'game_card|fact_sim_|battle_sim|combat_sim|vw_sim_'
ORDER BY EVENT_NAME;

SELECT
    ROUTINE_NAME,
    ROUTINE_TYPE
FROM information_schema.ROUTINES
WHERE ROUTINE_SCHEMA = DATABASE()
  AND ROUTINE_NAME <> 'audit_signed_id_columns'
  AND LOWER(COALESCE(ROUTINE_DEFINITION, '')) REGEXP 'game_card|fact_sim_|battle_sim|combat_sim|vw_sim_'
ORDER BY ROUTINE_NAME;

-- The old schema-audit procedure itself is retired.
SELECT ROUTINE_NAME, ROUTINE_TYPE
FROM information_schema.ROUTINES
WHERE ROUTINE_SCHEMA = DATABASE()
  AND ROUTINE_NAME = 'audit_signed_id_columns';

-- Stale runtime configuration from the retired combat simulator.
SELECT id, config_name, config_value
FROM dim_web_configuration
WHERE LEFT(config_name, 17) = 'combat_simulator_'
ORDER BY id;

-- Stale navigation nodes from the retired Games menu.
SELECT
    i.id,
    i.parent_id,
    i.menu_key,
    i.label,
    i.href,
    i.enabled
FROM dim_menu_items i
WHERE i.href IN ('/games/card-game', '/games/combat-simulator')
   OR i.menu_key = 'gamesMenu'
   OR i.parent_id IN (
       SELECT g.id
       FROM dim_menu_items g
       WHERE g.menu_key = 'gamesMenu'
   )
ORDER BY COALESCE(i.parent_id, i.id), i.parent_id IS NOT NULL, i.sort_order, i.id;
