<?php

if (!function_exists('hg_admin_section_registry')) {
    function hg_admin_section_registry(): array
    {
        return [
            'admin_characters' => ['target' => 'admin_characters.php', 'normal' => true, 'ajax' => true],
            'admin_avatar_mass' => ['target' => 'admin_avatar_mass.php', 'normal' => true, 'ajax' => true],
            'admin_characters_worlds' => ['target' => 'admin_characters_worlds.php', 'normal' => true, 'ajax' => true],
            'admin_character_collision_audit' => ['target' => 'admin_character_collision_audit.php', 'normal' => true, 'ajax' => false],
            'admin_character_deaths' => ['target' => 'admin_character_deaths.php', 'normal' => true, 'ajax' => true],
            'admin_characters_clone' => ['target' => 'admin_characters_clone.php', 'normal' => true, 'ajax' => true],
            'admin_groups' => ['target' => 'admin_groups.php', 'normal' => true, 'ajax' => true],
            'admin_organizations' => ['target' => 'admin_organizations.php', 'normal' => true, 'ajax' => true],
            'admin_seasons' => ['target' => 'admin_seasons.php', 'normal' => true, 'ajax' => true],
            'admin_season_order' => ['target' => 'admin_season_order.php', 'normal' => true, 'ajax' => true],
            'admin_season_order_schema' => ['target' => 'admin_season_order_schema.php', 'normal' => true, 'ajax' => true],
            'admin_chapters' => ['target' => 'admin_chapters.php', 'normal' => true, 'ajax' => true],
            'admin_pois' => ['target' => 'admin_pois.php', 'normal' => true, 'ajax' => true],
            'admin_players' => ['target' => 'admin_players.php', 'normal' => true, 'ajax' => true],
            'admin_chronicles' => ['target' => 'admin_chronicles.php', 'normal' => true, 'ajax' => true],
            'admin_realities' => ['target' => 'admin_realities.php', 'normal' => true, 'ajax' => true],
            'admin_bso' => ['target' => 'admin_bso.php', 'normal' => true, 'ajax' => true],
            'admin_bso_link' => ['target' => 'admin_bso_link.php', 'normal' => true, 'ajax' => true],
            'admin_timelines' => ['target' => 'admin_timelines.php', 'normal' => true, 'ajax' => true],
            'admin_birthdays_quick' => ['target' => 'admin_birthdays_quick.php', 'normal' => true, 'ajax' => true],
            'admin_gallery' => ['target' => 'admin_gallery.php', 'normal' => true, 'ajax' => true],
            'admin_parties' => ['target' => 'admin_parties.php', 'normal' => true, 'ajax' => true],
            'admin_powers' => ['target' => 'admin_powers.php', 'normal' => true, 'ajax' => true],
            'admin_gift_image_mass' => ['target' => 'admin_gift_image_mass.php', 'normal' => true, 'ajax' => true],
            'admin_docs' => ['target' => 'admin_docs.php', 'normal' => true, 'ajax' => true],
            'admin_external_links' => ['target' => 'admin_external_links.php', 'normal' => true, 'ajax' => true],
            'admin_character_links' => ['target' => 'admin_character_links.php', 'normal' => true, 'ajax' => true],
            'admin_doc_links' => ['target' => 'admin_doc_links.php', 'normal' => true, 'ajax' => true],
            'admin_topic_viewer' => ['target' => 'admin_topic_viewer.php', 'normal' => true, 'ajax' => true],
            'admin_bridges' => ['target' => 'admin_bridges.php', 'normal' => true, 'ajax' => true],
            'admin_items' => ['target' => 'admin_items.php', 'normal' => true, 'ajax' => true],
            'admin_menu' => ['target' => 'admin_menu.php', 'normal' => true, 'ajax' => true],
            'admin_relations' => ['target' => 'admin_relations.php', 'normal' => true, 'ajax' => false],
            'admin_news' => ['target' => 'admin_news.php', 'normal' => true, 'ajax' => true],
            'admin_systems' => ['target' => 'admin_systems.php', 'normal' => true, 'ajax' => true],
            'admin_forms' => ['target' => 'admin_forms.php', 'normal' => true, 'ajax' => true],
            'admin_maneuvers' => ['target' => 'admin_maneuvers.php', 'normal' => true, 'ajax' => true],
            'admin_system_details' => ['target' => 'admin_system_details.php', 'normal' => true, 'ajax' => true],
            'admin_systems_extra_details' => ['target' => 'admin_systems_extra_details.php', 'normal' => true, 'ajax' => true],
            'admin_systems_energy' => ['target' => 'admin_systems_energy.php', 'normal' => true, 'ajax' => true],
            'admin_trait_sets' => ['target' => 'admin_trait_sets.php', 'normal' => true, 'ajax' => true],
            'admin_traits' => ['target' => 'admin_traits.php', 'normal' => true, 'ajax' => true],
            'admin_actions' => ['target' => 'admin_actions.php', 'normal' => true, 'ajax' => true],
            'admin_merits_flaws' => ['target' => 'admin_merits_flaws.php', 'normal' => true, 'ajax' => true],
            'admin_character_conditions' => ['target' => 'admin_character_conditions.php', 'normal' => true, 'ajax' => true],
            'admin_character_conditions_bridge' => ['target' => 'admin_character_conditions_bridge.php', 'normal' => true, 'ajax' => true],
            'admin_character_misc_bridge' => ['target' => 'admin_character_misc_bridge.php', 'normal' => true, 'ajax' => true],
            'admin_character_affiliations_canonical' => ['target' => 'admin_character_affiliations_canonical.php', 'normal' => true, 'ajax' => true],
            'admin_systems_resources' => ['target' => 'admin_systems_resources.php', 'normal' => true, 'ajax' => true],
            'admin_resources' => ['target' => 'admin_resources.php', 'normal' => true, 'ajax' => true],
            'admin_datatables' => ['target' => 'admin_datatables.php', 'normal' => true, 'ajax' => false],
            'admin_usage' => ['target' => 'admin_usage.php', 'normal' => true, 'ajax' => false],
            'admin_inspect_db' => ['target' => '../../tools/inspect_db.php', 'normal' => true, 'ajax' => false],
            'admin_mentions_help' => ['target' => 'mentions_help.html', 'normal' => true, 'ajax' => false],
            'admin_org_chart_schema' => ['target' => 'admin_org_chart_schema.php', 'normal' => true, 'ajax' => false],
            'logout' => ['target' => 'admin_logout.php', 'normal' => true, 'ajax' => false],
        ];
    }
}

if (!function_exists('hg_admin_section_aliases')) {
    function hg_admin_section_aliases(): array
    {
        return [];
    }
}

if (!function_exists('hg_admin_section_resolve')) {
    function hg_admin_section_resolve(string $section, string $mode = 'normal'): ?array
    {
        $aliases = hg_admin_section_aliases();
        $canonical = $aliases[$section] ?? $section;
        $registry = hg_admin_section_registry();
        $entry = $registry[$canonical] ?? null;

        if (!is_array($entry) || empty($entry[$mode])) {
            return null;
        }

        $entry['section'] = $canonical;
        $entry['requested_section'] = $section;
        return $entry;
    }
}
