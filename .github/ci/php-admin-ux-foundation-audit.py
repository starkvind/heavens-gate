#!/usr/bin/env python3
from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parents[2]

css_path = ROOT / 'assets/css/hg-admin.css'
css = css_path.read_text(encoding='utf-8', errors='replace')

errors = []

for marker in [
    '.adm-table-scroll',
    '.adm-wide-table',
    '.adm-sticky-actions',
    '.adm-cell-actions',
    '.adm-th-actions',
    'width:max-content',
    'overflow-wrap:break-word',
    'min-width:1500px',
    '.adm-icon-btn',
    'min-width:76px',
]:
    if marker not in css:
        errors.append(f'hg-admin.css lost UX foundation marker: {marker}')

SCROLL_TARGETS = {
    'app/controllers/admin/admin_actions.php': ['adm-table-scroll', 'adm-sticky-actions', 'adm-wide-table'],
    'app/controllers/admin/admin_traits.php': ['adm-table-scroll', 'adm-sticky-actions', 'adm-wide-table'],
    'app/controllers/admin/admin_character_conditions.php': ['adm-table-scroll', 'adm-sticky-actions', 'adm-wide-table'],
    'app/controllers/admin/admin_merits_flaws.php': ['adm-table-scroll', 'adm-sticky-actions', 'adm-wide-table'],
    'app/controllers/admin/admin_items.php': ['adm-table-scroll', 'adm-sticky-actions', 'adm-wide-table'],
    'app/controllers/admin/admin_character_deaths.php': ['adm-table-scroll', 'adm-sticky-actions', 'adm-wide-table'],
    'app/controllers/admin/admin_characters_clone.php': ['adm-table-scroll', 'adm-sticky-actions', 'adm-wide-table'],
    'app/controllers/admin/admin_timelines.php': ['adm-table-scroll', 'adm-sticky-actions', 'adm-wide-table'],
    'app/controllers/admin/admin_season_order.php': ['adm-table-scroll', 'adm-sticky-actions', 'adm-wide-table'],
    'app/controllers/admin/admin_birthdays_quick.php': ['adm-table-scroll', 'adm-sticky-actions', 'adm-wide-table'],
    'app/controllers/admin/admin_characters_worlds.php': ['worlds-table-wrap', 'adm-wide-table'],
    'app/controllers/admin/admin_gift_image_mass.php': ['table-scroll', 'adm-wide-table', 'autoWidth: false'],
    'app/controllers/admin/admin_docs.php': ['adm-table-scroll', 'adm-sticky-actions', 'adm-wide-table'],
    'app/controllers/admin/admin_parties.php': ['adm-table-scroll', 'adm-sticky-actions', 'adm-wide-table'],
}

for rel, markers in SCROLL_TARGETS.items():
    path = ROOT / rel
    if not path.exists():
        errors.append(f'missing UX target: {rel}')
        continue
    text = path.read_text(encoding='utf-8', errors='replace')
    for marker in markers:
        if marker not in text:
            errors.append(f'{rel} lost UX marker: {marker}')

ICON_ACTION_TARGETS = [
    'app/controllers/admin/admin_actions.php',
    'app/controllers/admin/admin_traits.php',
    'app/controllers/admin/admin_character_conditions.php',
    'app/controllers/admin/admin_merits_flaws.php',
    'app/controllers/admin/admin_items.php',
    'app/controllers/admin/admin_character_deaths.php',
    'app/controllers/admin/admin_timelines.php',
    'app/controllers/admin/admin_season_order.php',
    'app/controllers/admin/admin_docs.php',
    'app/controllers/admin/admin_parties.php',
]
for rel in ICON_ACTION_TARGETS:
    text = (ROOT / rel).read_text(encoding='utf-8', errors='replace')
    if 'adm-icon-btn' not in text:
        errors.append(f'{rel} lost compact icon actions')
    if 'aria-label=' not in text or 'title=' not in text:
        errors.append(f'{rel} icon actions lost accessible labels')

birthdates = {
    'app/controllers/admin/admin_birthdays_quick.php': 'Fechas de nacimiento',
    'app/controllers/admin/admin_characters.php': 'Fechas de nacimiento',
    'app/controllers/admin/admin_main.php': 'Editar fechas de nacimiento',
    'app/partials/main_nav_bar.php': 'Fechas de nacimiento',
}
for rel, marker in birthdates.items():
    text = (ROOT / rel).read_text(encoding='utf-8', errors='replace')
    if marker not in text:
        errors.append(f'{rel} lost Birthdates label: {marker}')

print('# Phase 6.99z Admin UX foundation audit')
print(f'Wide-table targets: {len(SCROLL_TARGETS)}')
print(f'Birthdates labels: {len(birthdates)}')

if errors:
    for error in errors:
        print(f'ERROR: {error}', file=sys.stderr)
    sys.exit(1)

print('Phase 6.99z Admin UX foundation audit: PASS')
