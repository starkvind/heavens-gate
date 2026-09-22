#!/usr/bin/env python3
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[2]

PILOTS = {
    'news': {
        'controller': ROOT / 'app/controllers/admin/admin_news.php',
        'domain': ROOT / 'app/domains/news/admin.php',
        'markers': [
            'hg_news_admin_delete(',
            'hg_news_admin_save(',
            'hg_news_admin_fetch_rows(',
            'hg_news_admin_fetch_rows_full(',
        ],
    },
    'external_links': {
        'controller': ROOT / 'app/controllers/admin/admin_external_links.php',
        'domain': ROOT / 'app/domains/documents/admin_external_links.php',
        'markers': [
            'hg_external_links_admin_table_exists(',
            'hg_external_links_admin_create(',
            'hg_external_links_admin_update(',
            'hg_external_links_admin_delete(',
            'hg_external_links_admin_fetch_rows(',
        ],
    },
    'resources': {
        'controller': ROOT / 'app/controllers/admin/admin_resources.php',
        'domain': ROOT / 'app/domains/systems/admin_resources.php',
        'markers': [
            'hg_resources_admin_delete(',
            'hg_resources_admin_create(',
            'hg_resources_admin_update(',
            'hg_resources_admin_fetch_rows(',
        ],
    },
    'chronicles': {
        'controller': ROOT / 'app/controllers/admin/admin_chronicles.php',
        'domain': ROOT / 'app/domains/chronicles/admin.php',
        'markers': [
            'hg_chronicles_admin_fetch_current_image(',
            'hg_chronicles_admin_delete(',
            'hg_chronicles_admin_create(',
            'hg_chronicles_admin_update(',
            'hg_chronicles_admin_set_image(',
            'hg_chronicles_admin_fetch_rows(',
        ],
    },
    'relations': {
        'controller': ROOT / 'app/controllers/admin/admin_relations.php',
        'domain': ROOT / 'app/domains/relationships/admin.php',
        'markers': [
            'hg_relationships_admin_fetch_characters(',
            'hg_relationships_admin_delete(',
            'hg_relationships_admin_create(',
            'hg_relationships_admin_update(',
            'hg_relationships_admin_fetch_rows(',
        ],
    },
}

SQL = re.compile(r"\bmysqli_(?:query|prepare|real_query|multi_query)\b|->\s*(?:query|prepare)\s*\(")

for name, spec in PILOTS.items():
    controller = spec['controller'].read_text(encoding='utf-8', errors='replace')
    domain = spec['domain'].read_text(encoding='utf-8', errors='replace')

    if SQL.search(controller):
        print(f'ERROR: Admin {name} controller regained direct SQL', file=sys.stderr)
        sys.exit(1)

    for marker in spec['markers']:
        if marker not in controller:
            print(f'ERROR: Admin {name} controller lost domain call {marker}', file=sys.stderr)
            sys.exit(1)
        if marker not in domain:
            print(f'ERROR: Admin {name} domain lost function {marker}', file=sys.stderr)
            sys.exit(1)

print('Admin domain boundaries: PASS')
