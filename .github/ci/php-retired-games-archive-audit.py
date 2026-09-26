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
