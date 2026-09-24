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
    'avatar_mass': {
        'controller': ROOT / 'app/controllers/admin/admin_avatar_mass.php',
        'domain': ROOT / 'app/domains/characters/admin_avatars.php',
        'markers': [
            'hg_avatar_admin_get_character(',
            'hg_avatar_admin_update_base(',
            'hg_avatar_admin_upsert_variant(',
            'hg_avatar_admin_load_state(',
        ],
    },
    'birthdays_quick': {
        'controller': ROOT / 'app/controllers/admin/admin_birthdays_quick.php',
        'domain': ROOT / 'app/domains/characters/admin_birthdays.php',
        'markers': [
            'hg_abq_fetch_rows(',
            'hg_abq_save_row(',
        ],
    },
    'character_deaths': {
        'controller': ROOT / 'app/controllers/admin/admin_character_deaths.php',
        'domain': ROOT / 'app/domains/characters/admin_deaths.php',
        'markers': [
            'hg_acd_save_death(',
            'hg_acd_delete_death(',
            'hg_acd_load_state(',
            'hg_acd_fetch_deaths_rows(',
        ],
    },
    'characters_clone': {
        'controller': ROOT / 'app/controllers/admin/admin_characters_clone.php',
        'domain': ROOT / 'app/domains/characters/admin_clone.php',
        'markers': [
            'hg_acc_clone_character(',
            'hg_acc_load_listing(',
        ],
    },
    'characters_worlds': {
        'controller': ROOT / 'app/controllers/admin/admin_characters_worlds.php',
        'domain': ROOT / 'app/domains/characters/admin_worlds.php',
        'markers': [
            'hg_acw_save_character_world(',
            'hg_acw_load_state(',
        ],
    },
    'character_collision_audit': {
        'controller': ROOT / 'app/controllers/admin/admin_character_collision_audit.php',
        'domain': ROOT / 'app/domains/characters/admin_collision_audit.php',
        'markers': [
            'hg_cca_load_state(',
        ],
    },
    'groups': {
        'controller': ROOT / 'app/controllers/admin/admin_groups.php',
        'domain': ROOT / 'app/domains/organizations/admin_groups.php',
        'markers': [
            'hg_groups_clans_table(',
            'hg_groups_groups_table(',
            'hg_groups_clan_detail(',
            'hg_groups_group_members(',
            'hg_groups_create_clan(',
            'hg_groups_create_group(',
        ],
    },
    'organizations': {
        'controller': ROOT / 'app/controllers/admin/admin_organizations.php',
        'domain': ROOT / 'app/domains/organizations/admin.php',
        'markers': [
            'hg_aorg_fetch_organizations(',
            'hg_aorg_fetch_organization(',
            'hg_aorg_update_org(',
            'hg_aorg_save_department(',
            'hg_aorg_save_position(',
        ],
    },
    'bridges': {
        'controller': ROOT / 'app/controllers/admin/admin_bridges.php',
        'domain': ROOT / 'app/domains/organizations/admin_bridges.php',
        'markers': [
            'set_active_character_group(',
            'set_active_character_clan(',
            'set_clan_group(',
            'bridges_deactivate_row(',
            'bridges_load_state(',
        ],
    },
    'character_conditions_bridge': {
        'controller': ROOT / 'app/controllers/admin/admin_character_conditions_bridge.php',
        'domain': ROOT / 'app/domains/characters/admin_conditions_bridge.php',
        'markers': [
            'hg_accb_character_options(',
            'hg_accb_condition_catalog(',
            'hg_accb_delete(',
            'hg_accb_save(',
            'hg_accb_fetch_assignments(',
        ],
    },
    'character_misc_bridge': {
        'controller': ROOT / 'app/controllers/admin/admin_character_misc_bridge.php',
        'domain': ROOT / 'app/domains/characters/admin_misc_bridge.php',
        'markers': [
            'acmb_character_options(',
            'acmb_misc_options(',
            'acmb_save_assignment(',
            'acmb_delete_assignment(',
            'acmb_fetch_assignments(',
        ],
    },
    'character_affiliations_canonical': {
        'controller': ROOT / 'app/controllers/admin/admin_character_affiliations_canonical.php',
        'domain': ROOT / 'app/domains/organizations/admin_canonical.php',
        'markers': [
            'acac_run_canonicalizer(',
        ],
    },
    'org_chart_schema': {
        'controller': ROOT / 'app/controllers/admin/admin_org_chart_schema.php',
        'domain': ROOT / 'app/domains/organizations/admin_chart_schema.php',
        'markers': [
            'hg_aocs_table_exists(',
            'hg_aocs_fetch_organizations(',
            'hg_aocs_fetch_departments(',
            'hg_aocs_upsert_department(',
        ],
    },
    'chapters': {
        'controller': ROOT / 'app/controllers/admin/admin_chapters.php',
        'domain': ROOT / 'app/domains/chapters/admin.php',
        'markers': [
            'hg_chapters_admin_get_relations(',
            'hg_chapters_admin_save(',
            'hg_chapters_admin_fetch_row(',
            'hg_chapters_admin_rows(',
        ],
    },
    'seasons': {
        'controller': ROOT / 'app/controllers/admin/admin_seasons.php',
        'domain': ROOT / 'app/domains/chapters/admin_seasons.php',
        'markers': [
            'hg_seasons_admin_create(',
            'hg_seasons_admin_update(',
            'hg_seasons_admin_set_image(',
            'hg_seasons_admin_rows(',
        ],
    },
    'season_order': {
        'controller': ROOT / 'app/controllers/admin/admin_season_order.php',
        'domain': ROOT / 'app/domains/chapters/admin_season_order.php',
        'markers': [
            'hg_aso_fetch_season_options(',
            'hg_aso_fetch_orders(',
            'hg_aso_save_node(',
            'hg_aso_delete_node(',
        ],
    },
    'season_order_schema': {
        'controller': ROOT / 'app/controllers/admin/admin_season_order_schema.php',
        'domain': ROOT / 'app/domains/chapters/admin_season_order_schema.php',
        'markers': [
            'hg_asos_table_exists(',
            'hg_asos_count_rows(',
        ],
    },
    'timelines': {
        'controller': ROOT / 'app/controllers/admin/admin_timelines.php',
        'domain': ROOT / 'app/domains/timeline/admin.php',
        'markers': [
            'hg_timeline_admin_event_types(',
            'hg_timeline_admin_save_event(',
            'hg_timeline_admin_event_row(',
            'hg_timeline_admin_events(',
        ],
    },
    'parties': {
        'controller': ROOT / 'app/controllers/admin/admin_parties.php',
        'domain': ROOT / 'app/domains/parties/admin.php',
        'markers': [
            'hg_parties_admin_save_plot(',
            'hg_parties_admin_save_member(',
            'hg_parties_admin_add_change(',
            'hg_parties_admin_load_state(',
        ],
    },
    'topic_viewer': {
        'controller': ROOT / 'app/controllers/admin/admin_topic_viewer.php',
        'domain': ROOT / 'app/domains/chapters/admin_topic_viewer.php',
        'markers': [
            'hg_topic_viewer_move(',
            'hg_topic_viewer_save(',
            'hg_topic_viewer_chapter_options(',
            'hg_topic_viewer_rows(',
        ],
    },
    'realities': {
        'controller': ROOT / 'app/controllers/admin/admin_realities.php',
        'domain': ROOT / 'app/domains/timeline/admin_realities.php',
        'markers': [
            'hg_realities_admin_delete(',
            'hg_realities_admin_create(',
            'hg_realities_admin_update(',
            'hg_realities_admin_rows(',
        ],
    },
    'docs': {
        'controller': ROOT / 'app/controllers/admin/admin_docs.php',
        'domain': ROOT / 'app/domains/documents/admin_docs.php',
        'markers': [
            'hg_docs_admin_delete(',
            'hg_docs_admin_create(',
            'hg_docs_admin_update(',
            'hg_docs_admin_rows(',
        ],
    },
    'character_links': {
        'controller': ROOT / 'app/controllers/admin/admin_character_links.php',
        'domain': ROOT / 'app/domains/documents/admin_character_links.php',
        'markers': [
            'acl_doc_link_mutation(',
            'acl_external_link_mutation(',
            'acl_fetch_characters(',
            'acl_fetch_current_character(',
        ],
    },
    'doc_links': {
        'controller': ROOT / 'app/controllers/admin/admin_doc_links.php',
        'domain': ROOT / 'app/domains/documents/admin_links.php',
        'markers': [
            'adl_fetch_docs(',
            'adl_fetch_characters_for_doc(',
            'adl_sync_doc_characters(',
        ],
    },
    'bso': {
        'controller': ROOT / 'app/controllers/admin/admin_bso.php',
        'domain': ROOT / 'app/domains/soundtracks/admin.php',
        'markers': [
            'hg_abs_delete_soundtrack(',
            'hg_abs_add_link(',
            'hg_abs_save_soundtrack(',
            'hg_abs_fetch_soundtrack_rows(',
        ],
    },
    'bso_link': {
        'controller': ROOT / 'app/controllers/admin/admin_bso_link.php',
        'domain': ROOT / 'app/domains/soundtracks/admin_links.php',
        'markers': [
            'hg_abl_create_link(',
            'hg_abl_delete_link(',
            'hg_abl_dedupe(',
            'hg_abl_fetch_payload(',
        ],
    },
    'gift_image_mass': {
        'controller': ROOT / 'app/controllers/admin/admin_gift_image_mass.php',
        'domain': ROOT / 'app/domains/powers/admin_gift_images.php',
        'markers': [
            'hg_gift_image_mass_fetch_prompt_gift(',
            'hg_gift_image_mass_fetch_image_row(',
            'hg_gift_image_mass_update_image(',
            'hg_gift_image_mass_fetch_gifts(',
        ],
    },
    'actions': {
        'controller': ROOT / 'app/controllers/admin/admin_actions.php',
        'domain': ROOT / 'app/domains/rules/admin.php',
        'markers': [
            'hg_rules_admin_actions_options(',
            'hg_rules_admin_actions_delete(',
            'hg_rules_admin_actions_create(',
            'hg_rules_admin_actions_update(',
            'hg_rules_admin_actions_rows(',
        ],
    },
    'forms': {
        'controller': ROOT / 'app/controllers/admin/admin_forms.php',
        'domain': ROOT / 'app/domains/systems/admin.php',
        'markers': [
            'hg_systems_admin_forms_options(',
            'hg_systems_admin_form_delete(',
            'hg_systems_admin_form_save(',
            'hg_systems_admin_form_rows(',
        ],
    },
    'items': {
        'controller': ROOT / 'app/controllers/admin/admin_items.php',
        'domain': ROOT / 'app/domains/inventory/admin.php',
        'markers': [
            'hg_inventory_admin_item_options(',
            'hg_inventory_admin_item_delete(',
            'hg_inventory_admin_item_save(',
            'hg_inventory_admin_item_fetch(',
            'hg_inventory_admin_item_rows(',
        ],
    },
    'maneuvers': {
        'controller': ROOT / 'app/controllers/admin/admin_maneuvers.php',
        'domain': ROOT / 'app/domains/rules/admin.php',
        'markers': [
            'hg_rules_admin_maneuver_save_links(',
            'hg_rules_admin_maneuver_state(',
        ],
    },
    'merits_flaws': {
        'controller': ROOT / 'app/controllers/admin/admin_merits_flaws.php',
        'domain': ROOT / 'app/domains/rules/admin.php',
        'markers': [
            'hg_rules_admin_merits_options(',
            'hg_rules_admin_merits_create(',
            'hg_rules_admin_merits_update(',
            'hg_rules_admin_merits_delete(',
            'hg_rules_admin_merits_search(',
            'hg_rules_admin_merits_count(',
            'hg_rules_admin_merits_page(',
        ],
    },
    'character_conditions': {
        'controller': ROOT / 'app/controllers/admin/admin_character_conditions.php',
        'domain': ROOT / 'app/domains/rules/admin.php',
        'markers': [
            'hg_rules_admin_conditions_origins(',
            'hg_rules_admin_conditions_create(',
            'hg_rules_admin_conditions_update(',
            'hg_rules_admin_conditions_delete(',
            'hg_rules_admin_conditions_search(',
            'hg_rules_admin_conditions_count(',
            'hg_rules_admin_conditions_page(',
        ],
    },
    'systems': {
        'controller': ROOT / 'app/controllers/admin/admin_systems.php',
        'domain': ROOT / 'app/domains/systems/admin.php',
        'markers': [
            'hg_systems_admin_origins(',
            'hg_systems_admin_system_delete(',
            'hg_systems_admin_system_save(',
            'hg_systems_admin_system_rows(',
        ],
    },
    'systems_energy': {
        'controller': ROOT / 'app/controllers/admin/admin_systems_energy.php',
        'domain': ROOT / 'app/domains/systems/admin.php',
        'markers': [
            'ase_load_systems(',
            'ase_load_rows(',
            'ase_save_assignments(',
        ],
    },
    'systems_extra_details': {
        'controller': ROOT / 'app/controllers/admin/admin_systems_extra_details.php',
        'domain': ROOT / 'app/domains/systems/admin.php',
        'markers': [
            'ased_table_exists(',
            'ased_column_exists(',
            'ased_systems(',
            'ased_catalog(',
            'ased_existing_assignments(',
            'ased_save_group(',
        ],
    },
    'systems_resources': {
        'controller': ROOT / 'app/controllers/admin/admin_systems_resources.php',
        'domain': ROOT / 'app/domains/systems/admin.php',
        'markers': [
            'asr_table_exists(',
            'asr_table_columns(',
            'asr_load_state(',
            'asr_save(',
        ],
    },
    'trait_sets': {
        'controller': ROOT / 'app/controllers/admin/admin_trait_sets.php',
        'domain': ROOT / 'app/domains/rules/admin.php',
        'markers': [
            'hg_rules_admin_trait_sets_systems(',
            'hg_rules_admin_trait_sets_state(',
            'hg_rules_admin_trait_sets_save(',
        ],
    },
    'traits': {
        'controller': ROOT / 'app/controllers/admin/admin_traits.php',
        'domain': ROOT / 'app/domains/rules/admin.php',
        'markers': [
            'hg_rules_admin_traits_options(',
            'hg_rules_admin_traits_create(',
            'hg_rules_admin_traits_update(',
            'hg_rules_admin_traits_delete(',
            'hg_rules_admin_traits_search(',
            'hg_rules_admin_traits_count(',
            'hg_rules_admin_traits_page(',
        ],
    },
    'players': {
        'controller': ROOT / 'app/controllers/admin/admin_players.php',
        'domain': ROOT / 'app/domains/players/admin.php',
        'markers': [
            'hg_players_admin_fetch_current(',
            'hg_players_admin_player_exists(',
            'hg_players_admin_create(',
            'hg_players_admin_update(',
            'hg_players_admin_delete(',
            'hg_players_admin_fetch_rows(',
        ],
    },
    'datatables': {
        'controller': ROOT / 'app/controllers/admin/admin_datatables.php',
        'domain': ROOT / 'app/domains/configuration/admin_datatables.php',
        'markers': [
            'hg_configuration_admin_datatable_update(',
            'hg_configuration_admin_datatable_insert(',
            'hg_configuration_admin_datatable_delete(',
            'hg_configuration_admin_datatable_rows(',
        ],
    },
    'menu': {
        'controller': ROOT / 'app/controllers/admin/admin_menu.php',
        'domain': ROOT / 'app/domains/configuration/admin.php',
        'markers': [
            'hg_configuration_admin_menu_update(',
            'hg_configuration_admin_menu_create(',
            'hg_configuration_admin_menu_delete(',
            'hg_configuration_admin_menu_update_bulk(',
            'hg_configuration_admin_menu_reorder(',
            'hg_configuration_admin_menu_rows(',
        ],
    },
    'admin_password': {
        'controller': ROOT / 'app/controllers/admin/admin_get_pwd.php',
        'domain': ROOT / 'app/domains/configuration/admin.php',
        'markers': [
            'hg_configuration_admin_load_admin_password(',
        ],
    },
    'admin_usage': {
        'controller': ROOT / 'app/controllers/admin/admin_usage.php',
        'domain': ROOT / 'app/domains/admin_usage/queries.php',
        'markers': [
            'hg_admin_usage_report(',
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


characters_controller = (ROOT / 'app/controllers/admin/admin_characters.php').read_text(encoding='utf-8', errors='replace')
if "if ($action === 'create' || $action === 'update')" not in characters_controller:
    print('ERROR: Character form validation is no longer scoped away from delete payloads', file=sys.stderr)
    sys.exit(1)


# Phase 6.11 smoke regressions: optional bibliography FKs must map 0 -> NULL,
# item type is required, and Misc energy rows may deliberately use resources
# owned by another System.
systems_domain = (ROOT / 'app/domains/systems/admin.php').read_text(encoding='utf-8', errors='replace')
if "bibliography_id=NULLIF(?, 0)" not in systems_domain or "NULLIF(?, 0),NOW(),NOW()" not in systems_domain:
    print('ERROR: Admin Systems no longer maps empty bibliography to NULL', file=sys.stderr)
    sys.exit(1)

inventory_domain = (ROOT / 'app/domains/inventory/admin.php').read_text(encoding='utf-8', errors='replace')
if "bibliography_id=NULLIF(?, 0)" not in inventory_domain or "NULLIF(?, 0))" not in inventory_domain:
    print('ERROR: Admin Items no longer maps empty bibliography to NULL', file=sys.stderr)
    sys.exit(1)

items_controller = (ROOT / 'app/controllers/admin/admin_items.php').read_text(encoding='utf-8', errors='replace')
if "Selecciona un tipo de objeto válido." not in items_controller:
    print('ERROR: Admin Items lost required item-type validation', file=sys.stderr)
    sys.exit(1)

systems_energy_controller = (ROOT / 'app/controllers/admin/admin_systems_energy.php').read_text(encoding='utf-8', errors='replace')
if "$allowAllStateResources = ($tab === 'misc')" not in systems_energy_controller:
    print('ERROR: Admin Systems Energy lost Misc cross-system resource allowance', file=sys.stderr)
    sys.exit(1)
if "return state.tab === 'misc' ||" not in systems_energy_controller:
    print('ERROR: Admin Systems Energy UI no longer exposes all resources for Misc', file=sys.stderr)
    sys.exit(1)
