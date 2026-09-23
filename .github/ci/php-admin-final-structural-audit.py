#!/usr/bin/env python3
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[2]
ADMIN = ROOT / 'app/controllers/admin'

SQL = re.compile(r"\bmysqli_(?:query|prepare|real_query|multi_query)\b|->\s*(?:query|prepare)\s*\(")
SCHEMA = re.compile(r"\bSHOW\s+COLUMNS\b|\binformation_schema\b", re.I)

EXPECTED_CONTROLLER_COUNT = 62

WRAPPERS = {
    'admin_episodios.php': {
        'max_lines': 8,
        'markers': ["admin_chapters.php"],
    },
    'admin_characters_service.php': {
        'max_lines': 8,
        'markers': ["domains/characters/admin_service.php"],
    },
    'admin_logout.php': {
        'max_lines': 8,
        'markers': ["hg_admin_logout("],
    },
}

TOMBSTONES = {
    'admin_game_cards.php': 12,
    'admin_sim_browser.php': 12,
    'admin_sim_character_talk.php': 12,
}

files = sorted(ADMIN.glob('*.php'))
errors = []
sql_total = 0
schema_total = 0
line_total = 0

if len(files) != EXPECTED_CONTROLLER_COUNT:
    errors.append(
        f'Admin controller inventory changed: expected {EXPECTED_CONTROLLER_COUNT}, found {len(files)}. '
        'Review ownership before updating the Phase 6.13 baseline.'
    )

for path in files:
    text = path.read_text(encoding='utf-8', errors='replace')
    sql = len(SQL.findall(text))
    schema = len(SCHEMA.findall(text))
    lines = text.count('\n') + 1
    sql_total += sql
    schema_total += schema
    line_total += lines
    if sql:
        errors.append(f'{path.relative_to(ROOT)} contains {sql} direct SQL call site(s)')
    if schema:
        errors.append(f'{path.relative_to(ROOT)} contains {schema} schema probe(s)')

for name, rule in WRAPPERS.items():
    path = ADMIN / name
    if not path.exists():
        errors.append(f'missing compatibility wrapper: {path.relative_to(ROOT)}')
        continue
    text = path.read_text(encoding='utf-8', errors='replace')
    lines = text.count('\n') + 1
    if lines > rule['max_lines']:
        errors.append(f'{path.relative_to(ROOT)} stopped being thin: {lines} lines > {rule["max_lines"]}')
    for marker in rule['markers']:
        if marker not in text:
            errors.append(f'{path.relative_to(ROOT)} lost wrapper marker: {marker}')

for name, max_lines in TOMBSTONES.items():
    path = ADMIN / name
    if not path.exists():
        errors.append(f'missing Admin tombstone: {path.relative_to(ROOT)}')
        continue
    text = path.read_text(encoding='utf-8', errors='replace')
    lines = text.count('\n') + 1
    if lines > max_lines:
        errors.append(f'{path.relative_to(ROOT)} tombstone grew unexpectedly: {lines} lines > {max_lines}')
    if 'http_response_code(410)' not in text:
        errors.append(f'{path.relative_to(ROOT)} is no longer an explicit 410 tombstone')

print('# Phase 6.13 final Admin structural audit')
print(f'Controllers: {len(files)}')
print(f'Controller lines: {line_total}')
print(f'Direct SQL call sites: {sql_total}')
print(f'Schema probes: {schema_total}')
print(f'Thin wrappers checked: {len(WRAPPERS)}')
print(f'410 tombstones checked: {len(TOMBSTONES)}')

if errors:
    for error in errors:
        print(f'ERROR: {error}', file=sys.stderr)
    sys.exit(1)

print('Phase 6.13 final Admin structural audit: PASS')
