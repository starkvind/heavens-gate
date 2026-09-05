from pathlib import Path

shared = Path('assets/css/hg-datatables.css')
docs = Path('assets/css/hg-docs.css')
powers = Path('assets/css/hg-powers.css')
systems = Path('assets/css/hg-systems.css')
bio_table = Path('assets/css/pages/legacy/controllers-bio-bio_table.css')

shared_css = shared.read_text(encoding='utf-8')
marker = '/* Shared DataTables toolbar and multiselect controls */'
if marker in shared_css:
    raise SystemExit('shared DataTables component already present; refusing second application')

shared_block = '''

/* Shared DataTables toolbar and multiselect controls */
.dt-toolbar {
    display: flex;
    gap: 10px;
    margin: 0 0 10px;
}

.dt-toolbar .left {
    flex: 0 0 auto;
    display: flex;
    gap: 10px;
}

.dt-toolbar .right {
    flex: 1 1 auto;
    display: flex;
}

.ms-wrap {
    position: relative;
}

.ms-btn {
    width: 100%;
    box-sizing: border-box;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #fff;
}

.ms-btn .ms-label {
    opacity: .9;
}

.ms-btn .ms-summary {
    opacity: .8;
    margin-left: 10px;
}

.ms-panel {
    text-align: left;
    position: absolute;
    z-index: 9999;
    top: calc(100% + 6px);
    left: 0;
    right: 0;
    border: 1px solid rgb(0, 0, 153);
    background: rgb(0, 0, 102);
    border-radius: 6px;
    box-shadow: 0 8px 24px rgba(0,0,0,.12);
    padding: 4px;
    display: none;
    max-height: 280px;
    overflow: auto;
}

.ms-row {
    display: flex;
    align-items: center;
    background: rgb(0, 0, 102);
    gap: 10px;
    padding: 6px 8px;
    border-radius: 4px;
    cursor: pointer;
    margin: 0;
}

.ms-row:hover {
    background: rgba(0,0,0,.04);
}

.ms-row input {
    width: 16px;
    height: 16px;
}

.ms-row span {
    text-align: left;
}

.ms-actions {
    display: flex;
    gap: 8px;
}

.ms-actions button {
    font-family: Verdana, sans-serif;
    font-size: 10px;
    background-color: #000066;
    color: #fff;
    padding: 0.5em;
    border: 1px solid #000099;
}

.ms-actions button:hover {
    border-color: #003399;
    background: #000099;
    color: #01b3fa;
    cursor: pointer;
}
'''
shared.write_text(shared_css.rstrip() + shared_block + '\n', encoding='utf-8')


def replace_region(path: Path, start_token: str, end_token: str, replacement: str) -> None:
    css = path.read_text(encoding='utf-8')
    start = css.find(start_token)
    end = css.find(end_token, start + 1)
    if start < 0 or end < 0 or end <= start:
        raise SystemExit(f'could not isolate shared block in {path}')
    path.write_text(css[:start] + replacement.rstrip() + '\n\n' + css[end:], encoding='utf-8')


docs_variant = '''.dt-toolbar {
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
}

.dt-toolbar .left {
    align-items: center;
    flex-wrap: wrap;
}

.dt-toolbar .right {
    justify-content: flex-end;
}

.ms-wrap {
    width: 150px;
}

.ms-wrap--wide {
    width: 180px;
}'''
replace_region(docs, '.dt-toolbar {', '.docs-left-third {', docs_variant)

powers_variant = '''.dt-toolbar {
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
}

.dt-toolbar .left {
    align-items: center;
    flex-wrap: wrap;
}

.dt-toolbar .right {
    justify-content: flex-end;
}

.ms-wrap {
    width: 150px;
}

.ms-btn .ms-summary {
    text-align: left !important;
}'''
replace_region(powers, '.dt-toolbar {', '/* Powers landing page. */', powers_variant)

systems_variant = '''.dt-toolbar {
    align-items: center;
    justify-content: space-between;
}

.dt-toolbar .left {
    align-items: center;
}

.dt-toolbar .right {
    justify-content: flex-end;
}

.ms-wrap {
    width: 190px;
}'''
replace_region(systems, '.dt-toolbar {', '.badge-yes,', systems_variant)

bio_variant = '''.dt-toolbar {
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
}

.dt-toolbar .left {
    align-items: center;
    flex-wrap: wrap;
}

.dt-toolbar .right {
    justify-content: flex-end;
}

.ms-wrap {
    width: 150px;
}'''
replace_region(bio_table, '.dt-toolbar{', '@media (max-width: 720px){', bio_variant)

registry = Path('app/partials/datatable_assets.php').read_text(encoding='utf-8')
if "hg_page_register_stylesheet('/assets/css/hg-datatables.css')" not in registry:
    raise SystemExit('datatable asset registry no longer loads hg-datatables.css')

for path in (shared, docs, powers, systems, bio_table):
    css = path.read_text(encoding='utf-8')
    if css.count('{') != css.count('}'):
        raise SystemExit(f'unbalanced braces in {path}')

shared_only = [
    '.ms-btn {',
    '.ms-btn .ms-label {',
    '.ms-panel {',
    '.ms-row {',
    '.ms-actions {',
    '.ms-actions button {',
]
for selector in shared_only:
    current_shared = shared.read_text(encoding='utf-8')
    if selector not in current_shared:
        raise SystemExit(f'missing shared selector {selector}')
    for path in (docs, powers, systems, bio_table):
        if selector in path.read_text(encoding='utf-8'):
            raise SystemExit(f'{selector} still duplicated in {path}')
