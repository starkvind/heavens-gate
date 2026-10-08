#!/usr/bin/env python3
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[2]

SQL = re.compile(r"\bmysqli_(?:query|prepare|real_query|multi_query)\b|->\s*(?:query|prepare)\s*\(")
REQUEST_PATTERNS = {
    'GET': re.compile(r"\$_GET\s*\["),
    'POST': re.compile(r"\$_POST\s*\["),
    'COOKIE': re.compile(r"\$_COOKIE\s*\["),
    'REQUEST': re.compile(r"\$_REQUEST\s*\["),
}
SCHEMA_TOKENS = (
    'information_schema.',
    'SHOW TABLES',
    'SHOW COLUMNS',
    'SHOW INDEX',
    'SHOW KEYS',
)

EXPECTED_SCHEMA_OWNERS = {
    'app/helpers/schema_introspection.php',
    'app/domains/characters/admin_clone.php',
    'app/domains/bibliography/admin.php',  # Phase 6B: audited dynamic FK/column dependency inventory
    'app/tools/forum_topic_viewer_tool.php',
    'app/tools/inspect_db.php',
}
EXPECTED_SCHEMA_TOKENS = 14  # +4 strictly scoped information_schema references in bibliography Admin
EXPECTED_PUBLIC_ALIASES = {
    'bio_chronicles': 'chronicles',
    'listaobj': 'inv',
    'seeitem': 'verobj',
    'dones': 'listadones',
    'rites': 'ritelist',
    'totems': 'listatotems',
    'imgz': 'gallery',
}
EXPECTED_LEGACY_CASES = 36
REQUEST_CEILINGS = {
    'GET': 192,
    'POST': 708,
}
EXPECTED_COOKIE_OWNERS = {
    'app/helpers/mobile_detection.php',
    'app/mobile/mobile_index.php',
    'app/presentation/desktop_context.php',
}

REQUIRED_GUARDS = [
    '.github/ci/php-public-sql-boundary-audit.py',
    '.github/ci/php-schema-contract-audit.py',
    '.github/ci/php-request-state-boundary-audit.py',
    '.github/ci/php-legacy-alias-audit.py',
]
REQUIRED_WORKFLOW_MARKERS = [
    'php-public-sql-boundary-audit.py',
    'php-schema-contract-audit.py',
    'php-request-state-boundary-audit.py',
    'php-legacy-alias-audit.py',
    'php-architecture-contract-audit.py',
]


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


errors = []
files = runtime_php_files()

# 10.1: public/mobile controllers own no SQL.
public_sql_owners = []
for path in files:
    rel = path.relative_to(ROOT).as_posix()
    is_public_controller = (
        rel.startswith('app/mobile/controllers/')
        or (rel.startswith('app/controllers/') and not rel.startswith('app/controllers/admin/'))
    )
    if not is_public_controller:
        continue
    count = len(SQL.findall(path.read_text(encoding='utf-8', errors='replace')))
    if count:
        public_sql_owners.append((rel, count))
        errors.append(f'public SQL owner returned: {rel}: {count}')

# 10.2: schema introspection remains limited to the four classified owners.
schema_owners = set()
schema_tokens = 0
for path in files:
    source = path.read_text(encoding='utf-8', errors='replace')
    count = sum(source.count(token) for token in SCHEMA_TOKENS)
    if count:
        rel = path.relative_to(ROOT).as_posix()
        schema_owners.add(rel)
        schema_tokens += count

if schema_owners != EXPECTED_SCHEMA_OWNERS:
    errors.append(
        'schema owner set changed: expected '
        + ', '.join(sorted(EXPECTED_SCHEMA_OWNERS))
        + '; found '
        + ', '.join(sorted(schema_owners))
    )
if schema_tokens != EXPECTED_SCHEMA_TOKENS:
    errors.append(f'schema introspection token inventory changed: expected {EXPECTED_SCHEMA_TOKENS}, found {schema_tokens}')

# 10.3: raw request state cannot regrow or regain ambiguity.
request_totals = {key: 0 for key in REQUEST_PATTERNS}
cookie_owners = set()
public_request_reads = []
for path in files:
    rel = path.relative_to(ROOT).as_posix()
    source = executable_php(path.read_text(encoding='utf-8', errors='replace'))
    counts = {key: len(pattern.findall(source)) for key, pattern in REQUEST_PATTERNS.items()}
    for key, value in counts.items():
        request_totals[key] += value
    if counts['COOKIE']:
        cookie_owners.add(rel)

    is_public_controller = (
        rel.startswith('app/mobile/controllers/')
        or (rel.startswith('app/controllers/') and not rel.startswith('app/controllers/admin/'))
    )
    if is_public_controller and (counts['GET'] or counts['POST'] or counts['REQUEST']):
        public_request_reads.append((rel, counts))
        errors.append(
            f'public request-global owner returned: {rel}: '
            f"GET={counts['GET']} POST={counts['POST']} REQUEST={counts['REQUEST']}"
        )

for key, ceiling in REQUEST_CEILINGS.items():
    if request_totals[key] > ceiling:
        errors.append(f'raw {key} request reads regressed above production ceiling {ceiling}: {request_totals[key]}')
if request_totals['REQUEST'] != 0:
    errors.append(f'raw $_REQUEST must remain zero: {request_totals["REQUEST"]}')
if cookie_owners != EXPECTED_COOKIE_OWNERS:
    errors.append(
        'cookie owner set changed: expected '
        + ', '.join(sorted(EXPECTED_COOKIE_OWNERS))
        + '; found '
        + ', '.join(sorted(cookie_owners))
    )

# 10.4: route aliases remain edge-only and switch duplication stays eliminated.
legacy_path = ROOT / 'app/routing/legacy_query.php'
legacy = legacy_path.read_text(encoding='utf-8', errors='replace')
for alias, canonical in EXPECTED_PUBLIC_ALIASES.items():
    marker = f"'{alias}' => '{canonical}'"
    if marker not in legacy:
        errors.append(f'legacy alias mapping missing: {marker}')

legacy_cases = re.findall(r"\bcase\s+['\"]([^'\"]+)['\"]\s*:", legacy)
duplicate_cases = sorted({case for case in legacy_cases if legacy_cases.count(case) > 1})
if len(legacy_cases) != EXPECTED_LEGACY_CASES:
    errors.append(f'legacy case-label count changed: expected {EXPECTED_LEGACY_CASES}, found {len(legacy_cases)}')
if duplicate_cases:
    errors.append('duplicate legacy switch labels returned: ' + ', '.join(duplicate_cases))

for rel in (
    'app/routing/routes.php',
    'app/mobile/mobile_routes.php',
    'app/http/request_context.php',
):
    source = (ROOT / rel).read_text(encoding='utf-8', errors='replace')
    for alias in EXPECTED_PUBLIC_ALIASES:
        if re.search(rf"^\s*['\"]{re.escape(alias)}['\"]\s*=>", source, flags=re.M):
            errors.append(f'legacy alias returned to active runtime surface {rel}: {alias}')

# 10.5: the individual guards themselves remain installed in CI.
for rel in REQUIRED_GUARDS:
    if not (ROOT / rel).is_file():
        errors.append(f'required production architecture guard missing: {rel}')

workflow = (ROOT / '.github/workflows/architecture-checks.yml').read_text(encoding='utf-8', errors='replace')
for marker in REQUIRED_WORKFLOW_MARKERS:
    if marker not in workflow:
        errors.append(f'production architecture workflow guard missing: {marker}')

print('# PHP production architecture final architecture audit')
print(f'Public SQL owners: {len(public_sql_owners)}')
print(f'Public SQL calls: {sum(count for _, count in public_sql_owners)}')
print(f'Schema introspection owners: {len(schema_owners)}')
print(f'Schema introspection tokens: {schema_tokens}')
print(f"Raw GET reads: {request_totals['GET']}")
print(f"Raw POST reads: {request_totals['POST']}")
print(f"Raw REQUEST reads: {request_totals['REQUEST']}")
print(f"Cookie owners: {len(cookie_owners)}")
print(f'Edge-only legacy aliases: {len(EXPECTED_PUBLIC_ALIASES)}')
print(f'Legacy switch labels: {len(legacy_cases)}')
print(f'Duplicate legacy switch labels: {len(duplicate_cases)}')
print(f'production architecture subguards installed: {len(REQUIRED_GUARDS)}')

if errors:
    for error in errors:
        print('ERROR:', error, file=sys.stderr)
    raise SystemExit(1)

print('PHP production architecture final architecture audit: PASS')
