#!/usr/bin/env python3
from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parents[2]

css_path = ROOT / 'assets/css/hg-admin.css'
css = css_path.read_text(encoding='utf-8', errors='replace')
dense_js_path = ROOT / 'assets/js/hg-admin-dense-tables.js'
dense_js = dense_js_path.read_text(encoding='utf-8', errors='replace') if dense_js_path.exists() else ''
admin_main = (ROOT / 'app/controllers/admin/admin_main.php').read_text(encoding='utf-8', errors='replace')
admin_styles = (ROOT / 'app/partials/admin/admin_styles.php').read_text(encoding='utf-8', errors='replace')
index_php = (ROOT / 'index.php').read_text(encoding='utf-8', errors='replace')
admin_dir = ROOT / 'app/controllers/admin'
admin_files = sorted(admin_dir.glob('*.php'))
table_files = []
inline_style_files = []
for path in admin_files:
    source = path.read_text(encoding='utf-8', errors='replace')
    if '<table' in source:
        table_files.append(path)
    if '<style' in source:
        inline_style_files.append(path)

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
    '.adm-admin-wide-panel',
    '.adm-table-bottom-rail',
    '.adm-column-picker',
    '.adm-freeze-left',
    '#mainBody.route-admin .main-wrapper',
    '.adm-shell-tier-standard',
    '.adm-shell-tier-wide',
    '.adm-shell-tier-max',
    '--adm-shell-max',
    '--adm-table-min',
    'Phase 6.99z: global Admin visual language',
]:
    if marker not in css:
        errors.append(f'hg-admin.css lost UX foundation marker: {marker}')


for marker in [
    'STORAGE_PREFIX',
    'defaultHidden',
    'freezeCount',
    'localStorage',
    'adm-table-bottom-rail',
    'adm-admin-wide-panel',
    'Mostrar todas',
    'Vista inicial',
    'isDenseCandidate',
    "headers(table).length >= 5",
    'compactActionColumn',
    "querySelectorAll('table')",
    'workspaceTier',
    'applyWorkspaceTier',
    'adm-shell-tier-',
]:
    if marker not in dense_js:
        errors.append(f'dense Admin table JS lost spreadsheet marker: {marker}')

if '/assets/js/hg-admin-dense-tables.js' not in admin_main:
    errors.append('admin_main no longer loads the shared dense table controller')

if "route-admin" not in index_php or "hg_request_route($hgRequest) === 'talim'" not in index_php:
    errors.append('talim no longer receives the global route-admin shell class')

for marker in ['adm-panel', 'adm-panel-header', 'adm-panel-actions', 'adm-panel-back']:
    if marker not in admin_styles:
        errors.append(f'Admin panel helper lost shared markup marker: {marker}')

if len(admin_files) != 63:
    errors.append(f'Admin UX inventory changed unexpectedly: expected 63 controllers, found {len(admin_files)}')

if inline_style_files:
    errors.append(
        'Admin controller inline CSS returned after 6.99z centralization: '
        + ', '.join(path.name for path in inline_style_files)
    )

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
print(f'Admin controllers scanned: {len(admin_files)}')
print(f'Controllers emitting tables: {len(table_files)}')
print(f'Controllers with inline style blocks: {len(inline_style_files)} (required: 0)')
print(f'Explicit wide-table regression targets: {len(SCROLL_TARGETS)}')
print(f'Birthdates labels: {len(birthdates)}')

if errors:
    for error in errors:
        print(f'ERROR: {error}', file=sys.stderr)
    sys.exit(1)

print('Phase 6.99z Admin UX foundation audit: PASS')
