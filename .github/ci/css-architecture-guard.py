#!/usr/bin/env python3
from pathlib import Path
import re

FAILURES = []
ROOT = Path('.')
CSS_ROOT = ROOT / 'assets' / 'css'


def fail(message):
    FAILURES.append(message)


def read(path):
    return path.read_text(encoding='utf-8', errors='ignore')


# 1) Retired bundle and local CSS references.
if (CSS_ROOT / 'hg-main.css').exists():
    fail('assets/css/hg-main.css: retired legacy bundle must not be restored')

css_ref = re.compile(r'/assets/css/[A-Za-z0-9_./-]+\.css')
for root in [Path('app'), Path('api')]:
    for path in root.rglob('*.php'):
        source = read(path)
        if '/assets/css/hg-main.css' in source:
            fail(f'{path}: forbidden retired stylesheet reference /assets/css/hg-main.css')
        for ref in sorted(set(css_ref.findall(source))):
            if not Path(ref.lstrip('/')).is_file():
                fail(f'{path}: missing referenced stylesheet {ref}')


# 2) Normal public controllers may register styles, but must not inject new CSS into page content.
# Existing raw links are defensive standalone fallbacks and are legal only when the exact same
# stylesheet is also registered through the deterministic page-assets API.
register_ref = re.compile(r"hg_page_register_stylesheet\(\s*['\"](/assets/css/[A-Za-z0-9_./-]+\.css)['\"]")
link_ref = re.compile(r'<link\b[^>]*\brel\s*=\s*["\']stylesheet["\'][^>]*\bhref\s*=\s*["\'](/assets/css/[A-Za-z0-9_./-]+\.css)["\']', re.I)
compatibility_links = {
    ('app/controllers/main/events_page.php', '/assets/css/hg-mobile-timeline.css'),
}
for path in Path('app/controllers').rglob('*.php'):
    rel = path.as_posix()
    if rel.startswith('app/controllers/admin/') or rel.startswith('app/controllers/tool/'):
        continue
    source = read(path)
    if re.search(r'<style\b', source, re.I):
        fail(f'{path}: public controller emits a <style> block; register/extract the stylesheet instead')
    registered = set(register_ref.findall(source))
    for ref in link_ref.findall(source):
        if ref not in registered and (rel, ref) not in compatibility_links:
            fail(f'{path}: stylesheet fallback {ref} has no matching page-asset registration')


# 3) Core stays shell-only.
core = read(CSS_ROOT / 'hg-core.css')
for marker, label in {
    '.hg-inventory-': 'inventory domain selector',
    '.hg-powers-': 'powers domain selector',
    '.power-card': 'shared power-card component',
    '.hg-tabs': 'shared tabs component',
    '#hg-tooltip': 'shared tooltip component',
    '.nav-breadcrumb': 'shared breadcrumb component',
}.items():
    if marker in core:
        fail(f'assets/css/hg-core.css: {label} returned to core ({marker})')


# Lightweight selector extraction. This deliberately guards exact bare/global owners;
# contextual selectors remain legal for domain-specific composition.
comment_re = re.compile(r'/\*.*?\*/', re.S)
rule_re = re.compile(r'(^|})\s*([^@{}][^{}]*)\{', re.M)
bare_elements = {
    'html', 'body', 'a', 'p', 'div', 'span', 'table', 'thead', 'tbody', 'tr', 'th', 'td',
    'ul', 'ol', 'li', 'img', 'form', 'label', 'input', 'select', 'option', 'button', 'textarea',
    'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'pre', 'code', 'blockquote',
}

bare_allowed = {
    'assets/css/hg-base.css',
    'assets/css/hg-admin.css',
    'assets/css/hg-mobile.css',
    'assets/css/hg-mobile-timeline.css',
    'assets/css/hg-powers-print.css',
    'assets/css/hg-embeds.css',
    'assets/css/embeds/item.css',
    'assets/css/hg-tools.css',
    'assets/css/tools/csp-board.css',
}
# Reviewed legacy exceptions present at Phase 11 baseline. New bare selector families remain blocked.
bare_legacy_allowed = {
    ('assets/css/hg-layout.css', 'select'),
    ('assets/css/hg-home.css', 'h2'),
    ('assets/css/hg-home.css', 'input'),
}

shared_owners = {
    '.hg-tabs': 'assets/css/hg-components.css',
    '.hg-tab-panel': 'assets/css/hg-components.css',
    '#hg-tooltip': 'assets/css/hg-components.css',
    '.nav-breadcrumb': 'assets/css/hg-components.css',
    '.power-card': 'assets/css/hg-components.css',
}

for path in CSS_ROOT.rglob('*.css'):
    rel = path.as_posix()
    if rel.startswith('assets/css/vendor/'):
        continue
    source = comment_re.sub('', read(path))
    for match in rule_re.finditer(source):
        selector_group = match.group(2).strip()
        for selector in selector_group.split(','):
            selector = ' '.join(selector.split())
            if not selector:
                continue
            if (selector in bare_elements and rel not in bare_allowed
                    and (rel, selector) not in bare_legacy_allowed):
                fail(f'{rel}: bare global selector {selector!r} is outside the Phase 11 baseline')
            owner = shared_owners.get(selector)
            if owner and rel != owner:
                fail(f'{rel}: duplicate ownership of {selector}; canonical owner is {owner}')


# 4) New !important debt is blocked. Existing first-party exceptions are explicit and capped.
# Counts ignore comments, so explanatory references to !important do not consume the budget.
important_caps = {
    'assets/css/hg-datatables.css': 3,    # mirrors DataTables vendor colour importance
    'assets/css/hg-components.css': 1,    # print-only catalog navigation hide
    'assets/css/hg-power-custom.css': 12, # print-only custom power sheet
    'assets/css/hg-maps.css': 1,          # [hidden] must override component display modes
}
important_exempt_prefixes = (
    'assets/css/hg-admin.css',
    'assets/css/hg-mobile.css',
    'assets/css/hg-mobile-timeline.css',
    'assets/css/hg-powers-print.css',
    'assets/css/vendor/',
)
for path in CSS_ROOT.rglob('*.css'):
    rel = path.as_posix()
    code = comment_re.sub('', read(path))
    count = code.count('!important')
    if not count:
        continue
    if rel.startswith(important_exempt_prefixes):
        continue
    allowed = important_caps.get(rel, 0)
    if count > allowed:
        fail(f'{rel}: {count} !important declarations exceed approved cap {allowed}')


# 5) Global shell order and stable file-version cache busting are architectural contracts.
head_path = Path('app/bootstrap/head_work.php')
head = read(head_path)
global_css = [
    ('hg-tokens.css', 'tokensCssVersion'),
    ('hg-base.css', 'baseCssVersion'),
    ('hg-core.css', 'coreCssVersion'),
    ('hg-legacy-components.css', 'legacyComponentsCssVersion'),
    ('hg-components.css', 'componentsCssVersion'),
    ('hg-layout.css', 'layoutCssVersion'),
    ('hg-menu.css', 'menuCssVersion'),
]
positions = []
for filename, variable in global_css:
    filemtime = f"@filemtime(__DIR__ . '/../../assets/css/{filename}')"
    href = f'assets/css/{filename}?v=<?= ${variable} ?>'
    if filemtime not in head:
        fail(f'{head_path}: missing stable filemtime versioning for {filename}')
    if href not in head:
        fail(f'{head_path}: missing versioned global stylesheet link for {filename}')
    pos = head.find(href)
    if pos >= 0:
        positions.append((filename, pos))

if len(positions) == len(global_css):
    actual = [name for name, _ in sorted(positions, key=lambda item: item[1])]
    expected = [name for name, _ in global_css]
    if actual != expected:
        fail(f'{head_path}: global CSS load order changed: {actual}; expected {expected}')

render_call = 'hg_page_render_registered_styles();'
render_pos = head.find(render_call)
menu_pos = head.find('assets/css/hg-menu.css?v=<?= $menuCssVersion ?>')
if render_pos < 0:
    fail(f'{head_path}: registered route/domain stylesheet renderer is missing')
elif menu_pos >= 0 and render_pos < menu_pos:
    fail(f'{head_path}: route/domain styles must render after the global shell styles')

if '?: time()' in head:
    fail(f'{head_path}: time() asset-version fallback defeats browser caching')


if FAILURES:
    raise SystemExit('\n'.join(FAILURES))

print('CSS architecture guards: OK')
