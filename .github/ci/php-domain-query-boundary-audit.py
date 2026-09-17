from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[2]

EXTRACTED_CONTROLLERS = {
    'bibliography': [
        ROOT / 'app/controllers/main/main_biblio.php',
    ],
    'news': [
        ROOT / 'app/controllers/main/main_news.php',
        ROOT / 'app/mobile/controllers/news.php',
    ],
    'players': [
        ROOT / 'app/controllers/playr/playr_list.php',
        ROOT / 'app/controllers/playr/playr_page.php',
        ROOT / 'app/mobile/controllers/players.php',
    ],
    'characters': [
        ROOT / 'app/controllers/bio/bio_list.php',
        ROOT / 'app/controllers/bio/bio_table.php',
        ROOT / 'app/controllers/bio/bio_worlds.php',
        ROOT / 'app/mobile/controllers/characters_list.php',
        ROOT / 'app/mobile/controllers/character_types.php',
        ROOT / 'app/mobile/controllers/character_type_detail.php',
        ROOT / 'app/partials/bio/bio_page_section_01_data.php',
        ROOT / 'app/partials/bio/bio_page_section_06_skills.php',
        ROOT / 'app/partials/bio/bio_page_section_07_resources.php',
        ROOT / 'app/partials/bio/bio_page_section_08_merits.php',
        ROOT / 'app/partials/bio/bio_page_section_09_conditions.php',
        ROOT / 'app/partials/bio/bio_page_section_11_power.php',
        ROOT / 'app/partials/bio/bio_page_section_13_items.php',
        ROOT / 'app/partials/bio/bio_page_section_19_participation.php',
    ],
    'documents': [
        ROOT / 'app/controllers/docs/docs_table.php',
        ROOT / 'app/controllers/docs/docs_page.php',
        ROOT / 'app/mobile/controllers/documents.php',
    ],
    'inventory': [
        ROOT / 'app/controllers/docs/item_table.php',
        ROOT / 'app/controllers/docs/item_list.php',
        ROOT / 'app/controllers/docs/item_page.php',
        ROOT / 'app/mobile/controllers/inventory.php',
    ],
    'soundtracks': [
        ROOT / 'app/partials/snippet_bso_card.php',
        ROOT / 'app/controllers/ost/bso_main.php',
        ROOT / 'app/mobile/controllers/soundtrack.php',
    ],
    'systems': [
        ROOT / 'app/controllers/systems/systems_table.php',
        ROOT / 'app/controllers/systems/system_overview_page.php',
        ROOT / 'app/controllers/systems/system_detail_page.php',
        ROOT / 'app/controllers/systems/system_form_page.php',
        ROOT / 'app/mobile/controllers/systems.php',
    ],
    'rules': [
        ROOT / 'app/controllers/docs/rules_home.php',
        ROOT / 'app/controllers/docs/traits_table.php',
        ROOT / 'app/controllers/docs/traits_page.php',
        ROOT / 'app/controllers/docs/conditions_table.php',
        ROOT / 'app/controllers/docs/condition_page.php',
        ROOT / 'app/controllers/docs/action_table.php',
        ROOT / 'app/controllers/docs/action_page.php',
        ROOT / 'app/controllers/docs/maneuver_list.php',
        ROOT / 'app/controllers/docs/maneuver_page.php',
        ROOT / 'app/controllers/docs/arche_table.php',
        ROOT / 'app/controllers/docs/arche_page.php',
        ROOT / 'app/controllers/docs/merfla_table.php',
        ROOT / 'app/controllers/docs/merfla_page.php',
        ROOT / 'app/mobile/controllers/rules.php',
    ],
    'powers': [
        ROOT / 'app/controllers/pwrs/powers_home.php',
        ROOT / 'app/controllers/pwrs/don_category_list.php',
        ROOT / 'app/controllers/pwrs/don_group_list.php',
        ROOT / 'app/controllers/pwrs/don_page.php',
        ROOT / 'app/controllers/pwrs/don_table.php',
        ROOT / 'app/controllers/pwrs/don_full_list.php',
        ROOT / 'app/controllers/pwrs/don_custom_list.php',
        ROOT / 'app/controllers/pwrs/rite_category_list.php',
        ROOT / 'app/controllers/pwrs/rite_group_list.php',
        ROOT / 'app/controllers/pwrs/rite_page.php',
        ROOT / 'app/controllers/pwrs/rite_table.php',
        ROOT / 'app/controllers/pwrs/rite_full_list.php',
        ROOT / 'app/controllers/pwrs/rite_custom_list.php',
        ROOT / 'app/controllers/pwrs/totm_category_list.php',
        ROOT / 'app/controllers/pwrs/totm_group_list.php',
        ROOT / 'app/controllers/pwrs/totm_page.php',
        ROOT / 'app/controllers/pwrs/totm_table.php',
        ROOT / 'app/controllers/pwrs/totm_full_list.php',
        ROOT / 'app/controllers/pwrs/totm_custom_list.php',
        ROOT / 'app/controllers/pwrs/disc_category_list.php',
        ROOT / 'app/controllers/pwrs/disc_group_list.php',
        ROOT / 'app/controllers/pwrs/disc_page.php',
        ROOT / 'app/controllers/pwrs/disc_table.php',
        ROOT / 'app/controllers/pwrs/disc_full_list.php',
        ROOT / 'app/controllers/pwrs/disc_custom_list.php',
        ROOT / 'app/mobile/controllers/powers.php',
    ],
    'chapters': [
        ROOT / 'app/controllers/chapters/seasons_home.php',
        ROOT / 'app/controllers/chapters/season_archive.php',
        ROOT / 'app/controllers/chapters/season_order.php',
        ROOT / 'app/controllers/chapters/chapter_table.php',
        ROOT / 'app/controllers/chapters/chapter_page.php',
        ROOT / 'app/controllers/chapters/season_attendance_analysis.php',
        ROOT / 'app/mobile/controllers/seasons_list.php',
        ROOT / 'app/mobile/controllers/chapters_list.php',
        ROOT / 'app/mobile/controllers/season_detail.php',
        ROOT / 'app/mobile/controllers/chapter_detail.php',
        ROOT / 'app/partials/chapters/season_barchart_prepare.php',
    ],
    'maps': [
        ROOT / 'app/controllers/maps/maps_main.php',
        ROOT / 'app/controllers/maps/maps_detail.php',
        ROOT / 'app/controllers/maps/maps_api.php',
        ROOT / 'app/mobile/controllers/maps.php',
    ],
    'relationships': [
        ROOT / 'app/controllers/bio/bio_reltree_clans.php',
        ROOT / 'app/controllers/bio/bio_reltree_characters.php',
        ROOT / 'app/controllers/bio/bio_reltree_groups.php',
    ],
    'home': [
        ROOT / 'app/controllers/main/main_home.php',
        ROOT / 'app/mobile/controllers/home.php',
    ],
    'parties': [
        ROOT / 'app/controllers/main/main_parties.php',
    ],
    'chronicles': [
        ROOT / 'app/controllers/main/main_chronicles.php',
        ROOT / 'app/controllers/main/chronicle_image.php',
        ROOT / 'app/mobile/controllers/chronicles.php',
    ],
}

QUERY_CALLS = [
    re.compile(r'\bmysqli_(?:query|prepare)\s*\('),
    re.compile(r'->\s*(?:query|prepare)\s*\('),
]

errors = []
for domain, paths in EXTRACTED_CONTROLLERS.items():
    helper = ROOT / f'app/domains/{domain}/queries.php'
    if not helper.exists():
        errors.append(f'{domain}: missing {helper.relative_to(ROOT)}')

    for path in paths:
        if not path.exists():
            errors.append(f'{domain}: missing {path.relative_to(ROOT)}')
            continue

        text = path.read_text(encoding='utf-8', errors='replace')
        if any(pattern.search(text) for pattern in QUERY_CALLS):
            errors.append(f'{domain}: controller regained direct SQL execution: {path.relative_to(ROOT)}')

if errors:
    for error in errors:
        print(f'ERROR: {error}', file=sys.stderr)
    sys.exit(1)

print('Extracted domain query boundaries: PASS')
