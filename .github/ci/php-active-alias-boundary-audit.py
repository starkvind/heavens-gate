#!/usr/bin/env python3
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[2]

PUBLIC_EDGE_ALIASES = {
    'bio_chronicles': 'chronicles',
    'listaobj': 'inv',
    'seeitem': 'verobj',
    'dones': 'listadones',
    'rites': 'ritelist',
    'totems': 'listatotems',
}

ADMIN_RETIRED_ALIASES = [
    'admin_pjs',
    'admin_epis',
    'admin_temp',
    'admin_plots',
    'admin_characters_conditions_brige',
]

errors = []

routes = (ROOT / 'app/routing/routes.php').read_text(encoding='utf-8', errors='replace')
route_keys = set(re.findall(r"^\s*'([^']+)'\s*=>\s*\[", routes, flags=re.MULTILINE))
for alias in PUBLIC_EDGE_ALIASES:
    if alias in route_keys:
        errors.append(f'legacy public alias returned to active dispatch: {alias}')

mobile_routes = (ROOT / 'app/mobile/mobile_routes.php').read_text(encoding='utf-8', errors='replace')
for alias in PUBLIC_EDGE_ALIASES:
    if re.search(rf"^\s*'{re.escape(alias)}'\s*=>", mobile_routes, flags=re.MULTILINE):
        errors.append(f'legacy public alias returned to mobile routing: {alias}')

request_context = (ROOT / 'app/http/request_context.php').read_text(encoding='utf-8', errors='replace')
for alias in ['bio_chronicles', 'seeitem']:
    if re.search(rf"^\s*'{re.escape(alias)}'\s*=>", request_context, flags=re.MULTILINE):
        errors.append(f'legacy public alias returned to request context: {alias}')

search_catalog = (ROOT / 'app/helpers/search_catalog.php').read_text(encoding='utf-8', errors='replace')
if "'route' => 'seeitem'" in search_catalog:
    errors.append('search catalog emits retired seeitem route')
if "'route' => 'verobj'" not in search_catalog:
    errors.append('search catalog lost canonical verobj route')

legacy_query = (ROOT / 'app/routing/legacy_query.php').read_text(encoding='utf-8', errors='replace')
for marker in [
    "'listaobj' => '/inventory'",
    "'dones' => '/powers/gifts'",
    "'rites' => '/powers/rites'",
    "'totems' => '/powers/totems'",
    "case 'bio_chronicles':",
    "case 'seeitem':",
]:
    if marker not in legacy_query:
        errors.append(f'edge compatibility alias disappeared unexpectedly: {marker}')

for rel in [
    'app/helpers/admin_sections.php',
    'app/helpers/admin_auth.php',
    'app/partials/main_nav_bar.php',
]:
    source = (ROOT / rel).read_text(encoding='utf-8', errors='replace')
    for alias in ADMIN_RETIRED_ALIASES:
        if alias in source:
            errors.append(f'retired Admin alias returned in {rel}: {alias}')

admin_sections = (ROOT / 'app/helpers/admin_sections.php').read_text(encoding='utf-8', errors='replace')
if 'function hg_admin_section_aliases()' not in admin_sections or 'return [];' not in admin_sections:
    errors.append('Admin alias seam is not explicitly empty')

path_matcher = (ROOT / 'app/routing/path_matcher.php').read_text(encoding='utf-8', errors='replace')
for marker in [
    "#^/crop\\.html$#",
    "#^/sep/snippet_forum_hg\\.php$#",
    "#^/characters/chronicles$#",
    "#^/inventory/item/(.+)$#",
]:
    if marker not in path_matcher:
        errors.append(f'public compatibility redirect disappeared unexpectedly: {marker}')

if errors:
    for error in errors:
        print(f'ERROR: {error}', file=sys.stderr)
    sys.exit(1)

print('# Phase 7.4B active alias boundary')
print(f'Edge-only public aliases: {len(PUBLIC_EDGE_ALIASES)}')
print(f'Retired Admin aliases: {len(ADMIN_RETIRED_ALIASES)}')
print('Public path compatibility redirects: PRESERVED')
print('Internal legacy dispatch aliases: 0')
print('Phase 7.4B alias boundary: PASS')
