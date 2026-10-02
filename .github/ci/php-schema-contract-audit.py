#!/usr/bin/env python3
from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parents[2]

allowed = {
    'app/helpers/schema_introspection.php': {
        'class': 'compatibility-boundary',
        'reason': 'single cached owner for ordinary current-database table/column existence checks',
    },
    'app/domains/characters/admin_clone.php': {
        'class': 'dynamic-admin-operation',
        'reason': 'character cloning must discover bridge tables and copyable columns dynamically',
    },
    'app/tools/forum_topic_viewer_tool.php': {
        'class': 'external-schema-adapter',
        'reason': 'forum deployment may expose SMF tables in the external smf schema',
    },
    'app/tools/inspect_db.php': {
        'class': 'diagnostic-tool',
        'reason': 'the schema inspector intentionally enumerates live database metadata',
    },
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

# Legacy capability registries cover optional/compatibility tables consumed by
# shared character-sheet queries. They are not an authoritative mirror of the
# whole production schema.
character_queries = (ROOT / 'app/domains/characters/queries.php').read_text(encoding='utf-8', errors='replace')
required_character_sheet_tables = (
    'bridge_forms_traits',
    'bridge_maneuvers_forms',
    'bridge_maneuvers_systems',
    'dim_forms',
    'fact_actions',
    'fact_combat_maneuvers',
    'fact_power_rolls',
)
for table in required_character_sheet_tables:
    token = f"'{table}' => true"
    if token not in character_queries:
        errors.append(f'character sheet schema contract missing canonical table: {table}')

# Form-family resolution is different: dim_breeds.form_system_id is mandatory
# current production schema, not an optional legacy capability. The resolver
# must query that canonical contract directly. This guards the 2026-10-02
# regression where routing it through the partial compatibility maps made every
# breed resolve to form system 0 and removed Forms from all character sheets.
sheet_queries = (ROOT / 'app/domains/characters/sheet_queries.php').read_text(encoding='utf-8', errors='replace')
canonical_form_query = 'SELECT form_system_id FROM dim_breeds WHERE id = ? LIMIT 1'
if canonical_form_query not in sheet_queries:
    errors.append('form-family schema contract missing canonical dim_breeds.form_system_id query')

for forbidden in (
    "hg_characters_table_exists($link, 'dim_breeds')",
    "hg_characters_has_column($link, 'dim_breeds', 'form_system_id')",
):
    if forbidden in sheet_queries:
        errors.append(
            'form-family resolver must not depend on legacy compatibility schema maps: '
            + forbidden
        )

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

extra = sorted(set(allowed) - found)
if extra:
    errors.append('allowlist entries without schema introspection: ' + ', '.join(extra))

if errors:
    for error in errors:
        print('ERROR:', error, file=sys.stderr)
    raise SystemExit(1)

print('# Schema introspection audit')
print('Intentional introspection files:', len(found))
for rel in sorted(found):
    meta = allowed[rel]
    print(f"KEEP [{meta['class']}]: {rel} -- {meta['reason']}")
print('Canonical form-family contract: dim_breeds.form_system_id')
print('Schema introspection audit: PASS')
