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
    'system_details': {
        'controller': ROOT / 'app/controllers/admin/admin_system_details.php',
        'domain': ROOT / 'app/domains/systems/admin_details.php',
        'markers': [
            'hg_system_details_admin_fetch_origins(',
            'hg_system_details_admin_fetch_systems(',
            'hg_system_details_admin_delete(',
            'hg_system_details_admin_create(',
            'hg_system_details_admin_update(',
            'hg_system_details_admin_count(',
            'hg_system_details_admin_fetch_rows(',
        ],
    },
    'powers': {
        'controller': ROOT / 'app/controllers/admin/admin_powers.php',
        'domain': ROOT / 'app/domains/powers/admin.php',
        'markers': [
            'hg_powers_admin_fetch_pairs(',
            'hg_powers_admin_has_column(',
            'hg_powers_admin_create(',
            'hg_powers_admin_update(',
            'hg_powers_admin_fetch_image(',
            'hg_powers_admin_delete(',
            'hg_powers_admin_count(',
            'hg_powers_admin_fetch_rows(',
        ],
    },
    'maps_pois': {
        'controller': ROOT / 'app/controllers/admin/admin_pois.php',
        'domain': ROOT / 'app/domains/maps/admin.php',
        'markers': [
            'hg_maps_admin_fetch_all(',
            'hg_maps_admin_save_map(',
            'hg_maps_admin_delete_map(',
            'hg_maps_admin_save_category(',
            'hg_maps_admin_category_usage_count(',
            'hg_maps_admin_delete_category(',
            'hg_maps_admin_save_poi(',
            'hg_maps_admin_delete_poi(',
            'hg_maps_admin_save_area(',
            'hg_maps_admin_delete_area(',
        ],
    },
    'characters': {
        'controller': ROOT / 'app/controllers/admin/admin_characters.php',
        'domain': ROOT / 'app/domains/characters/admin_queries.php',
        'markers': [
            'hg_characters_admin_status_state(',
            'hg_characters_admin_reference_catalogs(',
            'hg_characters_admin_complex_catalogs(',
            'hg_characters_admin_system_dimensions(',
            'hg_characters_admin_group_maps(',
            'hg_characters_admin_inherited_totem(',
            'hg_characters_admin_current_image(',
            'hg_characters_admin_create(',
            'hg_characters_admin_update(',
            'hg_characters_admin_set_image(',
            'hg_characters_admin_clear_image(',
            'hg_characters_admin_soft_delete(',
            'hg_characters_admin_count(',
            'hg_characters_admin_fetch_page(',
            'hg_characters_admin_preload(',
        ],
    },
    'characters_ajax': {
        'controller': ROOT / 'app/controllers/admin/admin_characters_ajax.php',
        'domain': ROOT / 'app/domains/characters/admin_queries.php',
        'markers': [
            'hg_characters_admin_fetch_ajax_details(',
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


chronicles_domain = (ROOT / 'app/domains/chronicles/admin.php').read_text(encoding='utf-8', errors='replace')
if "$cols[] = 'pretty_id';" not in chronicles_domain:
    print('ERROR: Admin Chronicles create no longer seeds required pretty_id before INSERT', file=sys.stderr)
    sys.exit(1)

relations_controller = (ROOT / 'app/controllers/admin/admin_relations.php').read_text(encoding='utf-8', errors='replace')
if '<th>Tipo</th>' in relations_controller or '<th>Flechas</th>' in relations_controller:
    print('ERROR: Admin Relations compact table regained Type/Flechas columns', file=sys.stderr)
    sys.exit(1)


characters_service_wrapper = (ROOT / 'app/controllers/admin/admin_characters_service.php').read_text(encoding='utf-8', errors='replace')
if SQL.search(characters_service_wrapper):
    print('ERROR: Admin character service wrapper regained direct SQL', file=sys.stderr)
    sys.exit(1)
if '../../domains/characters/admin_service.php' not in characters_service_wrapper:
    print('ERROR: Admin character service wrapper lost domain delegation', file=sys.stderr)
    sys.exit(1)
