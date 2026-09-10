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
ACTIVE_EDGE_TARGETS = [
    ROOT / 'app/controllers/tool',
    ROOT / 'app/partials/forum_message_snippet.php',
    ROOT / 'app/partials/forum_diceroll_snippet.php',
    ROOT / 'app/partials/forum_item_snippet.php',
    ROOT / 'app/partials/main_nav_bar.php',
    ROOT / 'app/helpers/power_custom_pages.php',
]
PATTERNS = {
    'GET': re.compile(r'\$_GET\s*\['),
    'POST': re.compile(r'\$_POST\s*\['),
    'REQUEST': re.compile(r'\$_REQUEST\s*\['),
    'FILTER_GET': re.compile(r'filter_input\s*\(\s*INPUT_GET\s*,'),
    'FILTER_POST': re.compile(r'filter_input\s*\(\s*INPUT_POST\s*,'),
}
MAX_DIRECT_READS = 0
MAX_ACTIVE_EDGE_READS = 0


def request_rows(targets):
    rows = []
    seen = set()
    for target in targets:
        paths = sorted(target.rglob('*.php')) if target.is_dir() else [target]
        for path in paths:
            if not path.exists() or path in seen:
                continue
            seen.add(path)
            text = path.read_text(encoding='utf-8', errors='replace')
            counts = {name: len(pattern.findall(text)) for name, pattern in PATTERNS.items()}
            total = sum(counts.values())
            if total:
                rows.append((path.relative_to(ROOT).as_posix(), counts, total))
    return rows


rows = request_rows(TARGETS)
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

active_rows = request_rows(ACTIVE_EDGE_TARGETS)
active_reads = sum(row[2] for row in active_rows)
print('# Active public support request-state audit')
print(f'Files with direct request globals/input reads: {len(active_rows)}')
print(f'Direct reads: {active_reads}')
for path, counts, total in sorted(active_rows, key=lambda row: (-row[2], row[0])):
    parts = [f'{name}={count}' for name, count in counts.items() if count]
    print(f'{path}: {total} ({", ".join(parts)})')

if active_reads > MAX_ACTIVE_EDGE_READS:
    print(
        f'ERROR: active public support direct request reads exceed ceiling {MAX_ACTIVE_EDGE_READS}: {active_reads}',
        file=sys.stderr,
    )
    sys.exit(1)
