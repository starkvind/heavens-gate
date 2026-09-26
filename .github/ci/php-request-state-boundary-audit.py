#!/usr/bin/env python3
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[2]

PATTERNS = {
    'GET': re.compile(r'\$_GET\s*\['),
    'POST': re.compile(r'\$_POST\s*\['),
    'COOKIE': re.compile(r'\$_COOKIE\s*\['),
    'REQUEST': re.compile(r'\$_REQUEST\s*\['),
    'FILTER_GET': re.compile(r'filter_input\s*\(\s*INPUT_GET\s*,'),
    'FILTER_POST': re.compile(r'filter_input\s*\(\s*INPUT_POST\s*,'),
}

ADMIN_RAW_CEILINGS = {
    'GET': 189,
    'POST': 706,
}

SPECIAL_OWNERS = {
    'app/helpers/admin_ajax.php': {
        'class': 'admin-transport-helper',
        'allowed': {'GET', 'POST'},
        'reason': 'shared AJAX detection and CSRF transport fallback',
    },
    'app/tools/inspect_db.php': {
        'class': 'admin-diagnostic-boundary',
        'allowed': {'GET'},
        'reason': 'admin-only diagnostic mode selector',
    },
    'app/helpers/mobile_detection.php': {
        'class': 'presentation-preference-cookie',
        'allowed': {'COOKIE'},
        'reason': 'mobile/desktop presentation preference',
    },
    'app/mobile/mobile_index.php': {
        'class': 'presentation-preference-cookie',
        'allowed': {'COOKIE'},
        'reason': 'mobile theme preference',
    },
    'app/presentation/desktop_context.php': {
        'class': 'presentation-preference-cookie',
        'allowed': {'COOKIE'},
        'reason': 'desktop theme preference',
    },
}


def executable_php(text: str) -> str:
    text = re.sub(r'/\*.*?\*/', '', text, flags=re.S)
    return re.sub(r'(?m)^\s*//.*$', '', text)


def runtime_php_files():
    files = []
    for root_name in ('app', 'api'):
        root = ROOT / root_name
        if root.exists():
            files.extend(root.rglob('*.php'))
    index = ROOT / 'index.php'
    if index.exists():
        files.append(index)
    return sorted(set(files))


def classify(rel: str):
    if rel.startswith('app/controllers/admin/'):
        return {
            'class': 'admin-form-boundary',
            'allowed': {'GET', 'POST', 'FILTER_GET', 'FILTER_POST'},
            'reason': 'legacy admin form/action controller; direct form transport is intentionally retained',
        }
    return SPECIAL_OWNERS.get(rel)


rows = []
errors = []
admin_raw = {'GET': 0, 'POST': 0}
totals = {key: 0 for key in PATTERNS}

for path in runtime_php_files():
    rel = path.relative_to(ROOT).as_posix()
    source = executable_php(path.read_text(encoding='utf-8', errors='replace'))
    counts = {name: len(pattern.findall(source)) for name, pattern in PATTERNS.items()}
    active = {name for name, count in counts.items() if count}
    if not active:
        continue

    owner = classify(rel)
    if owner is None:
        errors.append(f'unclassified request-state owner: {rel} ({", ".join(sorted(active))})')
        continue

    disallowed = active - owner['allowed']
    if disallowed:
        errors.append(
            f'{rel} [{owner["class"]}] has disallowed request state: {", ".join(sorted(disallowed))}'
        )

    if 'REQUEST' in active:
        errors.append(f'ambiguous $_REQUEST is forbidden: {rel}')

    if rel.startswith('app/controllers/admin/'):
        admin_raw['GET'] += counts['GET']
        admin_raw['POST'] += counts['POST']

    for key, value in counts.items():
        totals[key] += value

    rows.append((rel, owner, counts))

for key, ceiling in ADMIN_RAW_CEILINGS.items():
    if admin_raw[key] > ceiling:
        errors.append(f'admin raw {key} reads regressed above production ceiling {ceiling}: {admin_raw[key]}')

if totals['REQUEST'] != 0:
    errors.append(f'raw $_REQUEST reads must remain zero: {totals["REQUEST"]}')

cookie_owners = {
    rel for rel, owner, counts in rows
    if counts['COOKIE'] > 0
}
expected_cookie_owners = {
    'app/helpers/mobile_detection.php',
    'app/mobile/mobile_index.php',
    'app/presentation/desktop_context.php',
}
if cookie_owners != expected_cookie_owners:
    errors.append(
        'cookie ownership changed: expected '
        + ', '.join(sorted(expected_cookie_owners))
        + '; found '
        + ', '.join(sorted(cookie_owners))
    )

print('# Request-state classification')
print(f'Admin raw GET reads: {admin_raw["GET"]}')
print(f'Admin raw POST reads: {admin_raw["POST"]}')
print(f'Raw $_REQUEST reads: {totals["REQUEST"]}')
print(f'Cookie reads/writes: {totals["COOKIE"]}')
print(f'Classified files: {len(rows)}')
for rel, owner, counts in sorted(rows):
    detail = ', '.join(f'{key}={value}' for key, value in counts.items() if value)
    print(f'KEEP [{owner["class"]}]: {rel} -- {detail} -- {owner["reason"]}')

if errors:
    for error in errors:
        print('ERROR:', error, file=sys.stderr)
    raise SystemExit(1)

print('Request-state classification: PASS')
