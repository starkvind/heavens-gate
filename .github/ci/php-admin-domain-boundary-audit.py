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
