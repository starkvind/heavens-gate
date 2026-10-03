#!/usr/bin/env python3
from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parents[2]
QUERIES = ROOT / 'app/domains/systems/queries.php'
DESKTOP = ROOT / 'app/controllers/systems/system_detail_page.php'
MOBILE = ROOT / 'app/mobile/controllers/systems.php'

errors = []


def require_text(path: Path, needle: str, label: str) -> None:
    if not path.exists():
        errors.append(f'{label}: missing file {path.relative_to(ROOT)}')
        return
    text = path.read_text(encoding='utf-8', errors='replace')
    if needle not in text:
        errors.append(f'{label}: missing {needle!r} in {path.relative_to(ROOT)}')


for needle, label in [
    ('bridge_gifts_availability', 'availability bridge query'),
    ("'system'", 'whole-system scope'),
    ("'race'", 'race scope'),
    ("'auspice'", 'auspice scope'),
    ("'tribe'", 'tribe scope'),
    ('rank_override', 'rank override support'),
    ('hg_systems_gift_scope_candidates', 'canonical detail scope resolution'),
    ('hg_systems_fetch_legacy_gifts', 'legacy compatibility helper'),
    ("'legacy-fallback'", 'gradual migration fallback'),
    ('hg_systems_merge_gift_row', 'deduplication/precedence helper'),
]:
    require_text(QUERIES, needle, label)

# Both presentation surfaces must continue consuming the shared systems-domain helper.
require_text(DESKTOP, 'hg_systems_fetch_gifts(', 'desktop shared gift lookup')
require_text(MOBILE, 'hg_systems_fetch_gifts(', 'mobile shared gift lookup')

# Guard against accidentally reverting the domain helper to the old direct-only lookup.
queries_text = QUERIES.read_text(encoding='utf-8', errors='replace') if QUERIES.exists() else ''
fetch_pos = queries_text.find('function hg_systems_fetch_gifts(')
if fetch_pos >= 0:
    body = queries_text[fetch_pos:]
    if 'hg_systems_gift_availability_table_exists' not in body:
        errors.append('hg_systems_fetch_gifts no longer checks the availability bridge')
    if 'hg_systems_fetch_gift_availability_rows' not in body:
        errors.append('hg_systems_fetch_gifts no longer loads bridge availability rows')
    if 'hg_systems_fetch_legacy_gifts' not in body:
        errors.append('hg_systems_fetch_gifts lost legacy fallback during gradual migration')
else:
    errors.append('missing hg_systems_fetch_gifts domain helper')

if errors:
    print('Gift availability bridge audit FAILED:')
    for error in errors:
        print(f' - {error}')
    sys.exit(1)

print('Gift availability bridge audit OK')
