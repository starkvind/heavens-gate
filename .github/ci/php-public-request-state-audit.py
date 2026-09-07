#!/usr/bin/env python3
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[2]
TARGETS = [
    ROOT / 'app/controllers/bio',
    ROOT / 'app/controllers/chapters',
    ROOT / 'app/controllers/docs',
    ROOT / 'app/controllers/main',
    ROOT / 'app/controllers/maps',
    ROOT / 'app/controllers/ost',
    ROOT / 'app/controllers/playr',
    ROOT / 'app/controllers/pwrs',
    ROOT / 'app/controllers/systems',
    ROOT / 'app/mobile/controllers',
]
PATTERNS = {
    'GET': re.compile(r'\$_GET\s*\['),
    'POST': re.compile(r'\$_POST\s*\['),
    'REQUEST': re.compile(r'\$_REQUEST\s*\['),
}
MIGRATED_ZERO_PREFIXES = (
    'app/controllers/playr/',
    'app/controllers/pwrs/',
)
MAX_DIRECT_READS = 96

rows = []
for target in TARGETS:
    if not target.exists():
        continue
    for path in sorted(target.rglob('*.php')):
        text = path.read_text(encoding='utf-8', errors='replace')
        counts = {name: len(pattern.findall(text)) for name, pattern in PATTERNS.items()}
        total = sum(counts.values())
        if total:
            rows.append((path.relative_to(ROOT).as_posix(), counts, total))

total_reads = sum(row[2] for row in rows)
print('# Public request-state audit')
print(f'Files with direct request globals: {len(rows)}')
print(f'Direct reads: {total_reads}')
for path, counts, total in sorted(rows, key=lambda row: (-row[2], row[0])):
    parts = [f'{name}={count}' for name, count in counts.items() if count]
    print(f'{path}: {total} ({", ".join(parts)})')

violations = [path for path, _counts, _total in rows if path.startswith(MIGRATED_ZERO_PREFIXES)]
if violations:
    print('ERROR: migrated domains regained direct request globals:', file=sys.stderr)
    for path in violations:
        print(f'  - {path}', file=sys.stderr)
    sys.exit(1)

if total_reads > MAX_DIRECT_READS:
    print(
        f'ERROR: public direct request reads regressed above baseline {MAX_DIRECT_READS}: {total_reads}',
        file=sys.stderr,
    )
    sys.exit(1)
