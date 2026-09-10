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
    'FILTER_GET': re.compile(r'filter_input\s*\(\s*INPUT_GET\s*,'),
    'FILTER_POST': re.compile(r'filter_input\s*\(\s*INPUT_POST\s*,'),
}
MAX_DIRECT_READS = 0

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
print(f'Files with direct request globals/input reads: {len(rows)}')
print(f'Direct reads: {total_reads}')
for path, counts, total in sorted(rows, key=lambda row: (-row[2], row[0])):
    parts = [f'{name}={count}' for name, count in counts.items() if count]
    print(f'{path}: {total} ({", ".join(parts)})')

if total_reads > MAX_DIRECT_READS:
    print(
        f'ERROR: public direct request reads regressed above baseline {MAX_DIRECT_READS}: {total_reads}',
        file=sys.stderr,
    )
    sys.exit(1)

# Phase 2 edge guard. Raw query globals belong only at index.php, where the
# transport input is converted into explicit routed query/request state.
EDGE_ZERO_FILES = [
    ROOT / 'app/bootstrap/body_work.php',
    ROOT / 'app/bootstrap/page_context.php',
    ROOT / 'app/mobile/mobile_index.php',
    ROOT / 'app/helpers/mobile_detection.php',
    ROOT / 'app/http/request_context.php',
    ROOT / 'app/http/pretty_request.php',
    ROOT / 'app/routing/request_runtime.php',
]
for path in EDGE_ZERO_FILES:
    text = path.read_text(encoding='utf-8', errors='replace')
    for name, pattern in PATTERNS.items():
        if pattern.search(text):
            print(f'ERROR: request pipeline regained {name} read: {path.relative_to(ROOT).as_posix()}', file=sys.stderr)
            sys.exit(1)
