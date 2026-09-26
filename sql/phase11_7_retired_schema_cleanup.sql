-- Heaven's Gate — Phase 11.7 retired database surface cleanup
-- DESTRUCTIVE.
-- Run only through tools/phase11_7_database_cleanup.sh --apply.
-- The wrapper performs live dependency checks and creates/verifies a full backup first.

-- Retired views.
DROP VIEW IF EXISTS
    vw_game_card_collection,
    vw_sim_characters,
    vw_sim_forms,
    vw_sim_items;

-- Retired combat simulator tables. Child/dependent tables first.
DROP TABLE IF EXISTS
    bridge_battle_sim_characters_seasons,
    fact_sim_character_scores,
    fact_sim_battles,
    fact_sim_tournaments,
    fact_sim_item_usage,
    fact_sim_characters_talk;

DROP TABLE IF EXISTS fact_sim_seasons;

-- Retired card-game relation/fact tables first.
DROP TABLE IF EXISTS
    fact_game_card_pack_rarity_weights,
    fact_game_card_pack_type_filters,
    fact_game_card_move_learn_rules,
    fact_game_card_collection;

-- Retired card-game dimensions/settings.
DROP TABLE IF EXISTS
    dim_game_card_shop_products,
    dim_game_card_settings,
    dim_game_card_ui_texts,
    dim_game_card_moves,
    dim_game_card_pack_types,
    dim_game_card_types,
    dim_game_card_rarities,
    dim_game_card_materials;

-- Obsolete one-use maintenance debris.
DROP PROCEDURE IF EXISTS audit_signed_id_columns;
DROP TABLE IF EXISTS _id_unsigned_audit;
DROP TABLE IF EXISTS admin_webp_image_migration_backup;

-- Stale rows in otherwise-live tables.
START TRANSACTION;

DELETE FROM dim_web_configuration
WHERE LEFT(config_name, 17) = 'combat_simulator_';

DELETE FROM dim_menu_items
WHERE href IN ('/games/card-game', '/games/combat-simulator');

DELETE parent
FROM dim_menu_items parent
LEFT JOIN dim_menu_items child
  ON child.parent_id = parent.id
WHERE parent.menu_key = 'gamesMenu'
  AND child.id IS NULL;

COMMIT;
