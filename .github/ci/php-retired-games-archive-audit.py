#!/usr/bin/env python3
from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parents[2]

retired_files = [
    'api/game_cards.php',
    'api/game_card_rules.php',
    'app/controllers/tool/game_cards.php',
    'app/controllers/tool/game_cards_mobile.php',
    'app/controllers/tool/combat_simulator.php',
    'app/mobile/controllers/combat_simulator.php',
    'app/controllers/admin/admin_game_cards.php',
    'app/controllers/admin/admin_sim_browser.php',
    'app/controllers/admin/admin_sim_character_talk.php',
]

strong_markers = [
    'combat_simulator',
    '/combat-simulator',
    'game_cards',
    '/card-game',
    'hg-cardgame-dev-lab',
    '/game-cards',
    'admin_game_cards',
    'admin_sim_browser',
    'admin_sim_character_talk',
    'fact_game_card_collection',
    'dim_game_card_',
    'fact_game_card_',
    'vw_game_card_collection',
    'fact_sim_',
    'bridge_battle_sim_characters_seasons',
    'vw_sim_',
]

runtime_roots = [ROOT / 'app', ROOT / 'api']
errors = []

for rel in retired_files:
    if (ROOT / rel).exists():
        errors.append(f'retired game runtime file returned: {rel}')

for base in runtime_roots:
    if not base.exists():
        continue
    for path in base.rglob('*.php'):
        text = path.read_text(encoding='utf-8', errors='replace')
        rel = path.relative_to(ROOT).as_posix()
        for marker in strong_markers:
            if marker in text:
                errors.append(f'retired game marker in runtime: {rel}: {marker}')

retired_schema_objects = [
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
]

# Permanent repository guard: retired DB objects may be named by the audited
# Phase 11.7 maintenance files, but no SQL migration may CREATE them again.
sql_root = ROOT / 'sql'
if sql_root.exists():
    for path in sql_root.rglob('*.sql'):
        source = path.read_text(encoding='utf-8', errors='replace').lower()
        for name in retired_schema_objects:
            create_table = f'create table `{name}`'
            create_view = f'create view `{name}`'
            replace_view = f'create or replace view `{name}`'
            if create_table in source or create_view in source or replace_view in source:
                rel = path.relative_to(ROOT).as_posix()
                errors.append(f'retired database object recreated by SQL: {rel}: {name}')

legacy = (ROOT / 'app/routing/legacy_query.php').read_text(encoding='utf-8', errors='replace')
for marker in ['simulador', 'simulador2', 'combtodo', 'vercombat', 'punts', 'sim_tournament']:
    if marker in legacy:
        errors.append(f'retired simulator legacy alias returned: {marker}')

if errors:
    for error in errors:
        print('ERROR:', error, file=sys.stderr)
    raise SystemExit(1)

print('Retired games absent from production runtime: PASS')
print(f'Retired runtime files checked: {len(retired_files)}')
print(f'Retired database object names guarded: {len(retired_schema_objects)}')
