#!/usr/bin/env python3
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[2]
LEGACY = ROOT / 'app/routing/legacy_query.php'

EXPECTED_ALIASES = {
    'bio_chronicles': 'chronicles',
    'listaobj': 'inv',
    'seeitem': 'verobj',
    'dones': 'listadones',
    'rites': 'ritelist',
    'totems': 'listatotems',
    'imgz': 'gallery',
}

EXPECTED_CASE_LABELS = 36

source = LEGACY.read_text(encoding='utf-8', errors='replace')
errors = []

if 'function hg_request_router_public_aliases()' not in source:
    errors.append('central public alias catalog is missing')
if 'function hg_request_router_canonical_legacy_route(' not in source:
    errors.append('central legacy alias normalizer is missing')
if "$route = hg_request_router_canonical_legacy_route(" not in source:
    errors.append('legacy query routing is not normalized through the alias catalog')

for alias, canonical in EXPECTED_ALIASES.items():
    marker = f"'{alias}' => '{canonical}'"
    if marker not in source:
        errors.append(f'missing legacy edge alias mapping: {marker}')
    if re.search(rf"\bcase\s+['\"]{re.escape(alias)}['\"]\s*:", source):
        errors.append(f'alias owns a switch branch instead of central normalization: {alias}')

# The direct route table must contain canonical route keys only.
match = re.search(r"\$direct\s*=\s*\[(.*?)\n\s*\];", source, flags=re.S)
if not match:
    errors.append('cannot locate direct legacy route table')
else:
    direct_block = match.group(1)
    for alias in EXPECTED_ALIASES:
        if re.search(rf"['\"]{re.escape(alias)}['\"]\s*=>", direct_block):
            errors.append(f'alias leaked back into direct route table: {alias}')

cases = re.findall(r"\bcase\s+['\"]([^'\"]+)['\"]\s*:", source)
duplicates = sorted({case for case in cases if cases.count(case) > 1})
if len(cases) != EXPECTED_CASE_LABELS:
    errors.append(f'legacy case-label inventory changed: expected {EXPECTED_CASE_LABELS}, found {len(cases)}')
if duplicates:
    errors.append('duplicate legacy switch labels returned: ' + ', '.join(duplicates))

active_surfaces = [
    ROOT / 'app/routing/routes.php',
    ROOT / 'app/mobile/mobile_routes.php',
    ROOT / 'app/http/request_context.php',
]
for path in active_surfaces:
    text = path.read_text(encoding='utf-8', errors='replace')
    rel = path.relative_to(ROOT).as_posix()
    for alias in EXPECTED_ALIASES:
        if re.search(rf"^\s*['\"]{re.escape(alias)}['\"]\s*=>", text, flags=re.M):
            errors.append(f'legacy alias returned to active route ownership in {rel}: {alias}')

print('# Legacy alias audit')
print(f'Edge-only aliases: {len(EXPECTED_ALIASES)}')
print(f'Legacy switch case labels: {len(cases)}')
print(f'Unique legacy switch labels: {len(set(cases))}')
print(f'Duplicate switch labels: {len(duplicates)}')
print('Alias ownership: app/routing/legacy_query.php only')

if errors:
    for error in errors:
        print('ERROR:', error, file=sys.stderr)
    raise SystemExit(1)

print('Legacy alias audit: PASS')
