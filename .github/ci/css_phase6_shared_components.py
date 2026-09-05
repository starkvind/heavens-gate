from pathlib import Path
import re

components_path = Path('assets/css/hg-components.css')
systems_path = Path('assets/css/hg-systems.css')
seasons_path = Path('assets/css/hg-seasons.css')
archive_path = Path('assets/css/hg-archive.css')

components = components_path.read_text(encoding='utf-8')
systems = systems_path.read_text(encoding='utf-8')
seasons = seasons_path.read_text(encoding='utf-8')
archive = archive_path.read_text(encoding='utf-8')


def rule_match(css: str, selector_pattern: str):
    pattern = re.compile(r'(?ms)^\s*' + selector_pattern + r'\s*\{([^{}]*)\}\s*')
    matches = list(pattern.finditer(css))
    if len(matches) != 1:
        raise SystemExit(f'expected one rule for /{selector_pattern}/, found {len(matches)}')
    return pattern, matches[0]


def standalone_after_rule(css: str, selector: str):
    pattern = re.compile(r'(?s)(?P<prefix>})\s*' + re.escape(selector) + r'\s*\{([^{}]*)\}\s*')
    matches = list(pattern.finditer(css))
    if len(matches) != 1:
        raise SystemExit(f'expected one standalone rule for {selector}, found {len(matches)}')
    return pattern, matches[0]


def normalize_body(body: str) -> str:
    return re.sub(r'\s+', ' ', body).strip()


def decl_map(body: str) -> dict[str, str]:
    out: dict[str, str] = {}
    for raw in body.split(';'):
        raw = raw.strip()
        if not raw:
            continue
        if ':' not in raw:
            raise SystemExit(f'cannot parse CSS declaration: {raw!r}')
        prop, value = raw.split(':', 1)
        out[prop.strip()] = re.sub(r'\s+', ' ', value).strip()
    return out

# 1) Systems tooltip: keep its route-specific root box, but remove the four
# descendant rules that are equivalent to the shared tooltip component.
for selector in (
    '#hg-tooltip .hg-tip-title',
    '#hg-tooltip .hg-tip-meta',
    '#hg-tooltip .hg-tip-label',
    '#hg-tooltip .hg-tip-text',
):
    escaped = re.escape(selector)
    comp_pattern, comp_match = rule_match(components, escaped)
    syst_pattern, syst_match = rule_match(systems, escaped)
    if normalize_body(comp_match.group(1)) != normalize_body(syst_match.group(1)):
        raise SystemExit(f'tooltip declarations differ for {selector}; refusing consolidation')
    systems = syst_pattern.sub('\n', systems, count=1)

for selector in (
    '#hg-tooltip .hg-tip-title',
    '#hg-tooltip .hg-tip-meta',
    '#hg-tooltip .hg-tip-label',
    '#hg-tooltip .hg-tip-text',
):
    if selector in systems:
        raise SystemExit(f'duplicated tooltip selector survives in Systems: {selector}')

# 2) Season/chronicle status pill: verify the effective declarations are the
# same before moving ownership to hg-components.css. The old class remains as
# a compatibility alias while a semantic hg-status-pill name becomes available.
if '.season-home-status' in components or '.hg-status-pill' in components:
    raise SystemExit('shared status pill already exists; refusing second application')

common_pattern, common_match = rule_match(
    seasons,
    re.escape('.season-home-count') + r'\s*,\s*' + re.escape('.season-home-status'),
)
season_status_pattern, season_status_match = standalone_after_rule(seasons, '.season-home-status')
archive_status_pattern, archive_status_match = rule_match(archive, re.escape('.season-home-status'))

season_effective = decl_map(common_match.group(1))
season_effective.update(decl_map(season_status_match.group(2)))
archive_effective = decl_map(archive_status_match.group(1))
if season_effective != archive_effective:
    raise SystemExit(f'season/archive status base differs: {season_effective!r} != {archive_effective!r}')

modifier_names = ('done', 'cancelled', 'active')
modifier_bodies: dict[str, str] = {}
for name in modifier_names:
    selector = f'.season-home-status--{name}'
    sp, sm = rule_match(seasons, re.escape(selector))
    ap, am = rule_match(archive, re.escape(selector))
    if decl_map(sm.group(1)) != decl_map(am.group(1)):
        raise SystemExit(f'season/archive status modifier differs: {selector}')
    modifier_bodies[name] = am.group(1).strip('\n')
    seasons = sp.sub('\n', seasons, count=1)
    archive = ap.sub('\n', archive, count=1)

# Preserve the count pill's common geometry in the Seasons domain.
count_body = common_match.group(1).strip('\n')
seasons = common_pattern.sub('\n.season-home-count {\n' + count_body + '\n}\n', seasons, count=1)
seasons = season_status_pattern.sub(lambda m: m.group('prefix') + '\n', seasons, count=1)
archive = archive_status_pattern.sub('\n', archive, count=1)

for css_name, css in (('hg-seasons.css', seasons), ('hg-archive.css', archive)):
    if '.season-home-status {' in css:
        raise SystemExit(f'standalone season status base survives in {css_name}')
    for name in modifier_names:
        if f'.season-home-status--{name}' in css:
            raise SystemExit(f'season status modifier {name} survives in {css_name}')

status_body = archive_status_match.group(1).strip('\n')
shared_block = '''

/* Status pill -------------------------------------------------------------- */
.hg-status-pill,
.season-home-status {
%s
}

.hg-status-pill--done,
.season-home-status--done {
%s
}

.hg-status-pill--cancelled,
.season-home-status--cancelled {
%s
}

.hg-status-pill--active,
.season-home-status--active {
%s
}
''' % (
    status_body,
    modifier_bodies['done'],
    modifier_bodies['cancelled'],
    modifier_bodies['active'],
)
components = components.rstrip() + shared_block + '\n'

for path, css in (
    (components_path, components),
    (systems_path, systems),
    (seasons_path, seasons),
    (archive_path, archive),
):
    if css.count('{') != css.count('}'):
        raise SystemExit(f'unbalanced braces after consolidation in {path}')
    path.write_text(css, encoding='utf-8')
