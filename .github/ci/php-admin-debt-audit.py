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
    'sql': 567,
    'schema': 37,
    'get': 203,
    'post': 717,
    'request': 0,
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
        errors.append(f'{metric} regressed above Phase 6.1a baseline {ceiling}: {totals[metric]}')

if errors:
    for error in errors:
        print(f'ERROR: {error}', file=sys.stderr)
    sys.exit(1)

print('Admin procedural-debt baseline: PASS')
