from pathlib import Path


def replace_once(path, old, new):
    p = Path(path)
    text = p.read_text()
    count = text.count(old)
    if count != 1:
        raise SystemExit(f"{path}: expected one match, found {count}\n{old}")
    p.write_text(text.replace(old, new, 1))


def replace_count(path, old, new, expected):
    p = Path(path)
    text = p.read_text()
    count = text.count(old)
    if count != expected:
        raise SystemExit(f"{path}: expected {expected} matches, found {count}: {old}")
    p.write_text(text.replace(old, new))


replace_once(
    "assets/css/hg-tokens.css",
    "\t--hg-dt-option-bg: #000099;\n\t--hg-dt-page-hover-text: #00ccff;\n\t--hg-dt-page-current-bg: #000044;",
    "\t--hg-dt-option-bg: #000099;\n\t--hg-dt-page-text: var(--hg-text-on-surface);\n\t--hg-dt-page-bg: var(--hg-surface-raised);\n\t--hg-dt-page-border: var(--hg-border-strong);\n\t--hg-dt-page-hover-text: #00ccff;\n\t--hg-dt-page-hover-border: var(--hg-border);\n\t--hg-dt-page-current-bg: #000044;\n\t--hg-dt-page-current-text: var(--hg-text-on-surface);\n\t--hg-dt-page-current-border: var(--hg-border-strong);",
)

replace_once(
    "assets/css/hg-datatables.css",
    ".dataTables_filter input,\n.dataTables_length select,\n.ms-btn {\n    font-family: verdana, sans-serif;\n    font-size: 10px;\n    font-weight: normal;\n    line-height: normal;\n    background-color: var(--hg-surface-raised);\n    color: var(--hg-text-on-surface);\n    padding: 0.5em;\n    border: 1px solid var(--hg-border-strong) !important;\n    border-radius: 3px;\n}",
    ".dataTables_wrapper .dataTables_filter input,\n.dataTables_wrapper .dataTables_length select,\n.ms-btn {\n    font-family: verdana, sans-serif;\n    font-size: 10px;\n    font-weight: normal;\n    line-height: normal;\n    background-color: var(--hg-surface-raised);\n    color: var(--hg-text-on-surface);\n    padding: 0.5em;\n    border: 1px solid var(--hg-border-strong);\n    border-radius: 3px;\n}",
)
replace_once(
    "assets/css/hg-datatables.css",
    ".dataTables_length option {\n    background-color: var(--hg-dt-option-bg) !important;\n}\n\n.dataTables_paginate .paginate_button {\n    color: var(--hg-text-on-surface) !important;\n    background: var(--hg-surface-raised) !important;\n    border: 1px solid var(--hg-border-strong) !important;\n    margin: 2px;\n}\n\n.dataTables_paginate .paginate_button:hover {\n    color: var(--hg-dt-page-hover-text) !important;\n    cursor: pointer;\n    border: 1px solid var(--hg-border) !important;\n}\n\n.dataTables_paginate .paginate_button.current {\n    background: var(--hg-dt-page-current-bg) !important;\n}",
    ".dataTables_wrapper .dataTables_length option {\n    background-color: var(--hg-dt-option-bg);\n}\n\n/* DataTables ships button text colours as !important. These three colour\n * declarations intentionally mirror that importance; geometry and surfaces\n * are won through adapter specificity instead. */\n.dataTables_wrapper .dataTables_paginate .paginate_button {\n    color: var(--hg-dt-page-text) !important;\n    background: var(--hg-dt-page-bg);\n    border: 1px solid var(--hg-dt-page-border);\n    margin: 2px;\n}\n\n.dataTables_wrapper .dataTables_paginate .paginate_button:hover {\n    color: var(--hg-dt-page-hover-text) !important;\n    cursor: pointer;\n    border: 1px solid var(--hg-dt-page-hover-border);\n}\n\n.dataTables_wrapper .dataTables_paginate .paginate_button.current,\n.dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {\n    color: var(--hg-dt-page-current-text) !important;\n    background: var(--hg-dt-page-current-bg);\n    border-color: var(--hg-dt-page-current-border);\n}",
)
replace_once(
    "assets/css/hg-datatables.css",
    ".dataTables_wrapper .dataTables_filter {\n    margin: 0 !important;\n}",
    ".dataTables_wrapper .dataTables_filter {\n    margin: 0;\n}",
)
replace_once(
    "assets/css/hg-datatables.css",
    ".dataTables_wrapper .dataTables_filter input {\n    margin-left: 0 !important;\n}",
    ".dataTables_wrapper .dataTables_filter input {\n    margin-left: 0;\n}",
)
replace_once(
    "assets/css/hg-datatables.css",
    ".ms-btn:focus {\n    outline: none !important;\n    border-color: var(--hg-ms-focus-border) !important;\n    box-shadow: 0 0 0 3px var(--hg-ms-focus-ring) !important;\n}",
    ".ms-btn:focus {\n    outline: none;\n    border-color: var(--hg-ms-focus-border);\n    box-shadow: 0 0 0 3px var(--hg-ms-focus-ring);\n}",
)

replace_once(
    "assets/css/hg-maps.css",
    "    --map-accent: #33cccc;\n    --map-glow: rgba(51, 204, 204, .16);",
    "    --map-accent: #33cccc;\n    --map-glow: rgba(51, 204, 204, .16);\n    --hg-dt-page-text: var(--map-ink);\n    --hg-dt-page-hover-text: var(--map-ink);\n    --hg-dt-page-current-text: #ffffff;\n    --hg-dt-page-current-bg: rgba(0, 0, 102, .95);\n    --hg-dt-page-current-border: rgba(51, 204, 204, .28);",
)
replace_once(
    "assets/css/hg-maps.css",
    ".map-table-wrap .dataTables_wrapper .dataTables_paginate .paginate_button {\n    color: var(--map-ink) !important;\n}\n\n.map-table-wrap .dataTables_wrapper .dataTables_paginate .paginate_button.current,\n.map-table-wrap .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {\n    background: rgba(0, 0, 102, .95) !important;\n    border-color: rgba(51, 204, 204, .28) !important;\n    color: #ffffff !important;\n}\n\n",
    "",
)
replace_once(
    "assets/css/hg-maps.css",
    ".map-shell-root .leaflet-popup-close-button {\n    color: #b9d6ff !important;\n}\n\n.map-shell-root .leaflet-popup-close-button:hover {\n    color: #ffffff !important;\n}",
    ".map-shell-root .leaflet-container a.leaflet-popup-close-button {\n    color: #b9d6ff;\n}\n\n.map-shell-root .leaflet-container a.leaflet-popup-close-button:hover {\n    color: #ffffff;\n}",
)

replace_count(
    "assets/css/hg-components.css",
    "justify-content: flex-start !important;",
    "justify-content: flex-start;",
    2,
)
replace_once(
    "assets/css/hg-components.css",
    "\tpadding: 8px 0 12px 0 !important;\n\tmargin-left: 28px !important;",
    "\tpadding: 8px 0 12px 0;\n\tmargin-left: 28px;",
)

replace_once(
    "assets/css/hg-docs.css",
    ".hg-tab-panel[data-tab=\"owners\"] .grupoBioClan {\n    display: flex;\n    justify-content: flex-start !important;\n}\n\n.hg-tab-panel[data-tab=\"owners\"] .contenidoAfiliacion {\n    display: flex;\n    flex-wrap: wrap;\n    gap: 6px;\n    padding: 8px 0 12px !important;\n    margin-left: 28px !important;\n    justify-content: flex-start !important;\n}\n\n",
    "",
)
replace_count(
    "assets/css/hg-docs.css",
    "text-align: left !important;",
    "text-align: left;",
    3,
)

replace_once(
    "assets/css/hg-systems.css",
    "    text-align: left !important;",
    "    text-align: left;",
)
replace_once(
    "assets/css/hg-ost.css",
    "    text-align: left !important;",
    "    text-align: left;",
)
replace_once(
    "assets/css/hg-powers.css",
    "    text-align: left !important;",
    "    text-align: left;",
)
replace_once(
    "assets/css/hg-tools.css",
    "#garou-name-gen button {\n    cursor: pointer;\n    padding: 1em !important;\n}",
    "#garou-name-gen button {\n    cursor: pointer;\n}",
)

replace_count(
    "assets/css/pages/bio/pack-list.css",
    " !important",
    "",
    2,
)
replace_count(
    "assets/css/pages/legacy/controllers-bio-bio_pack_page.css",
    " !important",
    "",
    2,
)

print("Phase 8 public specificity cleanup staged successfully")
