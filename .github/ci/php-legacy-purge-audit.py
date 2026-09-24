#!/usr/bin/env python3
from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parents[2]

REMOVED = [
    'app/controllers/bio/bio_chronicles.php',
    'app/controllers/docs/arche_list.php',
    'app/controllers/pwrs/disc_category_list.php',
    'app/mobile/controllers/desktop_embed.php',
    'app/mobile/controllers/rules_powers.php',
    'app/domains/rules_powers/queries.php',
    'app/controllers/admin/admin_episodios.php',
    'app/tools/backfill_content_updates.php',
]

FORBIDDEN_TOKENS = [
    'bio_chronicles.php',
    'arche_list.php',
    'disc_category_list.php',
    'desktop_embed.php',
    'rules_powers.php',
    'rules_powers/queries.php',
    'admin_episodios.php',
    'backfill_content_updates.php',
    'hg_rules_powers_',
    'snippet_forum_a',
]

SCAN_ROOTS = [
    ROOT / 'app',
    ROOT / 'api',
    ROOT / '.github' / 'ci',
    ROOT / '.github' / 'workflows',
]
SCAN_SUFFIXES = {'.php', '.py', '.yml', '.yaml', '.js'}

errors = []

for rel in REMOVED:
    path = ROOT / rel
    if path.exists():
        errors.append(f'removed Phase 7.1 file returned: {rel}')

this_file = Path(__file__).resolve()
for root in SCAN_ROOTS:
    if not root.exists():
        continue
    for path in root.rglob('*'):
        if not path.is_file() or path.suffix.lower() not in SCAN_SUFFIXES:
            continue
        if path.resolve() == this_file:
            continue
        text = path.read_text(encoding='utf-8', errors='replace')
        rel = path.relative_to(ROOT).as_posix()
        for token in FORBIDDEN_TOKENS:
            if token in text:
                errors.append(f'stale Phase 7.1 consumer/token {token!r}: {rel}')

page_context = (ROOT / 'app/http/page_context.php').read_text(encoding='utf-8', errors='replace')
for token in ["case 'doc':", "case 'plots':"]:
    if token in page_context:
        errors.append(f'dead metadata branch returned: {token}')

routes = (ROOT / 'app/routing/routes.php').read_text(encoding='utf-8', errors='replace')
for marker in [
    "'bio_chronicles'    => ['app/controllers/main/main_chronicles.php'",
    "'arquetip'       => ['app/controllers/docs/arche_table.php'",
    "'disciplinas' => ['app/controllers/pwrs/disc_table.php'",
]:
    if marker not in routes:
        errors.append(f'live replacement route changed unexpectedly: {marker}')

mobile_routes = (ROOT / 'app/mobile/mobile_routes.php').read_text(encoding='utf-8', errors='replace')
for marker in [
    "'rules' => __DIR__ . '/controllers/rules.php'",
    "'powers' => __DIR__ . '/controllers/powers.php'",
]:
    if marker not in mobile_routes:
        errors.append(f'live mobile replacement changed unexpectedly: {marker}')

admin_sections = (ROOT / 'app/helpers/admin_sections.php').read_text(encoding='utf-8', errors='replace')
if "'admin_epis' => 'admin_chapters'" not in admin_sections:
    errors.append('Admin episodes compatibility alias no longer resolves directly to admin_chapters')

if errors:
    for error in errors:
        print(f'ERROR: {error}', file=sys.stderr)
    sys.exit(1)

print('# Phase 7.1 legacy purge audit')
print(f'Removed files guarded: {len(REMOVED)}')
print('Stale runtime/CI consumers: 0')
print('Dead dispatch/metadata branches: 0')
print('Live replacement routes: PASS')
print('Phase 7.1 legacy purge audit: PASS')
