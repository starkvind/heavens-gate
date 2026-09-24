#!/usr/bin/env python3
from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parents[2]

allowed = {
    'app/domains/chapters/admin_season_order.php',
    'app/domains/chapters/admin_season_order_schema.php',
    'app/domains/characters/admin_clone.php',
    'app/domains/characters/admin_collision_audit.php',
    'app/domains/characters/admin_service.php',
    'app/domains/maps/queries.php',
    'app/domains/organizations/admin_chart_schema.php',
    'app/helpers/mentions.php',
    'app/helpers/pretty.php',
    'app/tools/forum_topic_viewer_tool.php',
    'app/tools/inspect_db.php',
}

tokens = (
    'information_schema.',
    'SHOW TABLES',
    'SHOW COLUMNS',
    'SHOW INDEX',
    'SHOW KEYS',
)

found = set()
errors = []

for root_name in ('app', 'api'):
    root = ROOT / root_name
    if not root.exists():
        continue
    for path in root.rglob('*.php'):
        text = path.read_text(encoding='utf-8', errors='replace')
        if any(token in text for token in tokens):
            rel = path.relative_to(ROOT).as_posix()
            found.add(rel)
            if rel not in allowed:
                errors.append(f'unapproved schema introspection: {rel}')

extra = sorted(allowed - found)
if extra:
    errors.append('allowlist entries without schema introspection: ' + ', '.join(extra))

if errors:
    for error in errors:
        print('ERROR:', error, file=sys.stderr)
    raise SystemExit(1)

print('# Phase 7.3 schema contract audit')
print('Intentional introspection files:', len(found))
for rel in sorted(found):
    print('KEEP:', rel)
print('Phase 7.3 schema contract audit: PASS')
