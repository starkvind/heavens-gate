#!/usr/bin/env python3
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[2]

errors = []

runtime_roots = [
    ROOT / 'app',
    ROOT / 'assets' / 'js',
]
suffixes = {'.php', '.js', '.html'}

legacy_edge = (ROOT / 'app/routing/legacy_query.php').resolve()

for root in runtime_roots:
    if not root.exists():
        continue
    for path in root.rglob('*'):
        if not path.is_file() or path.suffix.lower() not in suffixes:
            continue
        if path.resolve() == legacy_edge:
            continue
        source = path.read_text(encoding='utf-8', errors='replace')
        rel = path.relative_to(ROOT).as_posix()

        for marker in ['?p=', 'index.php?p=', 'name="p"', "name='p'"]:
            if marker in source:
                errors.append(f'legacy route producer returned in {rel}: {marker}')

        for marker in ["$_GET['p']", '$_GET["p"]']:
            if marker in source:
                errors.append(f'direct legacy route read returned in {rel}: {marker}')

routes = (ROOT / 'app/routing/routes.php').read_text(encoding='utf-8', errors='replace')
if re.search(r"^\s*'imgz'\s*=>", routes, flags=re.MULTILINE):
    errors.append('retired imgz route returned to active dispatch')

if (ROOT / 'app/controllers/tool/img_board.php').exists():
    errors.append('retired image board controller returned')

legacy_query = (ROOT / 'app/routing/legacy_query.php').read_text(encoding='utf-8', errors='replace')
if "'imgz' => '/gallery'" not in legacy_query:
    errors.append('imgz no longer canonicalizes to /gallery at the compatibility edge')

dispatch = (ROOT / 'app/http/dispatch_policy.php').read_text(encoding='utf-8', errors='replace')
for marker in [
    "'file' => 'app/controllers/main/error404.php'",
    "'section' => 'Error'",
]:
    if marker not in dispatch:
        errors.append(f'unknown-route 404 fallback disappeared: {marker}')

htaccess = (ROOT / '.htaccess').read_text(encoding='utf-8', errors='replace')
rewrite_targets = re.findall(r'index\.php\?p=([^\s\]]+)', htaccess)
if not rewrite_targets:
    errors.append('security rewrite boundary lost explicit error404 targets')
elif any(target.lower() != 'error404' for target in rewrite_targets):
    errors.append('htaccess contains a legacy p= rewrite target other than error404')

path_matcher = (ROOT / 'app/routing/path_matcher.php').read_text(encoding='utf-8', errors='replace')
for marker in [
    "#^/index\\.php$#",
    "#^/crop\\.html$#",
    "#^/sep/snippet_forum_hg\\.php$#",
    "#^/characters/chronicles$#",
    "#^/inventory/item/(.+)$#",
]:
    if marker not in path_matcher:
        errors.append(f'intentional public compatibility redirect disappeared: {marker}')

if errors:
    for error in errors:
        print(f'ERROR: {error}', file=sys.stderr)
    sys.exit(1)

print('# Phase 7.4C compatibility residue audit')
print('Runtime legacy URL producers: 0')
print('Direct runtime $_GET[p] readers: 0')
print('Retired imgz runtime: 0')
print('Unknown dispatch fallback: 404')
print('Security p=error404 rewrite boundary: PRESERVED')
print('Intentional public compatibility redirects: PRESERVED')
print('Phase 7.4C compatibility residue audit: PASS')
