#!/usr/bin/env python3
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[2]
ADMIN = ROOT / 'app/controllers/admin'

PATTERNS = {
    'sql': re.compile(r"\bmysqli_(?:query|prepare|real_query|multi_query)\b|->\s*(?:query|prepare)\s*\("),
    'schema': re.compile(r"\bSHOW\s+COLUMNS\b|\binformation_schema\b", re.I),
    'get': re.compile(r"\$_GET\s*\["),
    'post': re.compile(r"\$_POST\s*\["),
    'request': re.compile(r"\$_REQUEST\s*\["),
}

CEILINGS = {
    'sql': 24,
    'schema': 0,
    'get': 203,
    'post': 714,
    'request': 1,
}

RESIDUAL_SQL_CEILINGS = {
    'app/controllers/admin/admin_menu.php': 10,
    'app/controllers/admin/admin_players.php': 8,
    'app/controllers/admin/admin_datatables.php': 4,
    'app/controllers/admin/admin_get_pwd.php': 2,
}

rows = []
totals = {key: 0 for key in PATTERNS}
total_lines = 0
files = sorted(ADMIN.glob('*.php'))

for path in files:
    text = path.read_text(encoding='utf-8', errors='replace')
    counts = {key: len(pattern.findall(text)) for key, pattern in PATTERNS.items()}
    lines = text.count('\n') + 1
    total_lines += lines
    for key, value in counts.items():
        totals[key] += value
    rows.append((path.relative_to(ROOT).as_posix(), lines, counts))

print('# Admin procedural-debt baseline')
print(f'PHP controllers: {len(files)}')
print(f'PHP lines: {total_lines}')
print(f"SQL call sites: {totals['sql']}")
print(f"Schema probes: {totals['schema']}")
print(f"Raw $_GET reads: {totals['get']}")
print(f"Raw $_POST reads: {totals['post']}")
print(f"Raw $_REQUEST reads: {totals['request']}")
print()

for metric, title in [
    ('sql', 'Admin SQL hotspots'),
    ('schema', 'Admin schema-probe hotspots'),
    ('get', 'Admin GET hotspots'),
    ('post', 'Admin POST hotspots'),
]:
    print(f'## {title}')
    shown = 0
    for path, lines, counts in sorted(rows, key=lambda row: (-row[2][metric], -row[1], row[0])):
        if counts[metric] <= 0:
            continue
        print(f'{path}: {counts[metric]} ({lines} lines)')
        shown += 1
        if shown >= 15:
            break
    if shown == 0:
        print('none')
    print()

errors = []
for metric, ceiling in CEILINGS.items():
    if totals[metric] > ceiling:
        errors.append(f'{metric} regressed above Phase 6.11 package baseline {ceiling}: {totals[metric]}')

row_by_path = {path: counts for path, _lines, counts in rows}
for path, ceiling in RESIDUAL_SQL_CEILINGS.items():
    current = row_by_path.get(path, {}).get('sql', 0)
    if current > ceiling:
        errors.append(f'{path} SQL regressed above current Phase 6 residual ceiling {ceiling}: {current}')

if errors:
    for error in errors:
        print(f'ERROR: {error}', file=sys.stderr)
    sys.exit(1)

print('Admin procedural-debt baseline: PASS')
