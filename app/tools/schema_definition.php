<?php
return array (
  'generated_from' => 'dump-u807926597_hg-202604031114.sql',
  'generated_at' => '2026-04-03T12:26:24+00:00',
  'safe_web_configuration' => 
  array (
    'error_reporting' => 'FALSE',
    'exclude_chronicles' => '',
    'combat_simulator_ip_limit_enabled' => 'TRUE',
    'combat_simulator_ip_limit_max_attempts_per_hour' => '25',
    'combat_simulator_ip_limit_max_attempts_per_day' => '120',
    'combat_simulator_rubberbanding_max_bonus_dice' => '10',
    'combat_simulator_rubberbanding_failures_per_bonus' => '1',
  ),
  'tables' => 
  array (
    0 => 
    array (
      'name' => 'bridge_battle_sim_characters_seasons',
      'create_sql' => 'CREATE TABLE `bridge_battle_sim_characters_seasons` (
  `season_id` int(10) unsigned NOT NULL,
  `character_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`season_id`,`character_id`),
  KEY `idx_bbscs_character` (`character_id`),
  KEY `idx_bbscs_updated` (`updated_at`),
  CONSTRAINT `fk_bbscs_character` FOREIGN KEY (`character_id`) REFERENCES `fact_characters` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_bbscs_season` FOREIGN KEY (`season_id`) REFERENCES `fact_sim_seasons` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    1 => 
    array (
      'name' => 'bridge_chapters_characters',
      'create_sql' => 'CREATE TABLE `bridge_chapters_characters` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `chapter_id` int(10) unsigned NOT NULL,
  `character_id` int(10) unsigned NOT NULL,
  `participation_role` enum(\'npc\',\'player\') NOT NULL DEFAULT \'npc\',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `id_capitulo` (`chapter_id`),
  KEY `id_personaje` (`character_id`)
) ENGINE=InnoDB AUTO_INCREMENT=766 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    2 => 
    array (
      'name' => 'bridge_characters_docs',
      'create_sql' => 'CREATE TABLE `bridge_characters_docs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `character_id` int(10) unsigned NOT NULL,
  `doc_id` int(10) unsigned NOT NULL,
  `relation_label` varchar(120) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bcd_character_doc` (`character_id`,`doc_id`),
  KEY `idx_bcd_doc` (`doc_id`),
  KEY `idx_bcd_character_sort` (`character_id`,`sort_order`),
  CONSTRAINT `fk_bcd_character` FOREIGN KEY (`character_id`) REFERENCES `fact_characters` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_bcd_doc` FOREIGN KEY (`doc_id`) REFERENCES `fact_docs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=99 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    3 => 
    array (
      'name' => 'bridge_characters_external_links',
      'create_sql' => 'CREATE TABLE `bridge_characters_external_links` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `character_id` int(10) unsigned NOT NULL,
  `external_link_id` int(10) unsigned NOT NULL,
  `relation_label` varchar(120) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bcel_character_link` (`character_id`,`external_link_id`),
  KEY `idx_bcel_external_link` (`external_link_id`),
  KEY `idx_bcel_character_sort` (`character_id`,`sort_order`),
  CONSTRAINT `fk_bcel_character` FOREIGN KEY (`character_id`) REFERENCES `fact_characters` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_bcel_external_link` FOREIGN KEY (`external_link_id`) REFERENCES `fact_external_links` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    4 => 
    array (
      'name' => 'bridge_characters_groups',
      'create_sql' => 'CREATE TABLE `bridge_characters_groups` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `character_id` int(10) unsigned NOT NULL,
  `group_id` int(10) unsigned NOT NULL,
  `position` varchar(100) NOT NULL DEFAULT \'\',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_char_group` (`character_id`,`group_id`),
  KEY `fk_bcg_group` (`group_id`),
  CONSTRAINT `fk_bcg_character` FOREIGN KEY (`character_id`) REFERENCES `fact_characters` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_bcg_group` FOREIGN KEY (`group_id`) REFERENCES `dim_groups` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=579 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    5 => 
    array (
      'name' => 'bridge_characters_items',
      'create_sql' => 'CREATE TABLE `bridge_characters_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `character_id` int(10) unsigned NOT NULL,
  `item_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_personaje` (`character_id`),
  KEY `idx_objeto` (`item_id`),
  CONSTRAINT `fk_bci_character` FOREIGN KEY (`character_id`) REFERENCES `fact_characters` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_bci_item` FOREIGN KEY (`item_id`) REFERENCES `fact_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=249 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    6 => 
    array (
      'name' => 'bridge_characters_merits_flaws',
      'create_sql' => 'CREATE TABLE `bridge_characters_merits_flaws` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `character_id` int(10) unsigned NOT NULL,
  `merit_flaw_id` int(10) unsigned NOT NULL,
  `level` tinyint(2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_personaje` (`character_id`),
  KEY `idx_mer_y_def` (`merit_flaw_id`),
  CONSTRAINT `fk_bcmf_character` FOREIGN KEY (`character_id`) REFERENCES `fact_characters` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_bcmf_merit_flaw` FOREIGN KEY (`merit_flaw_id`) REFERENCES `dim_merits_flaws` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=627 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    7 => 
    array (
      'name' => 'bridge_characters_organizations',
      'create_sql' => 'CREATE TABLE `bridge_characters_organizations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `character_id` int(10) unsigned NOT NULL,
  `organization_id` int(10) unsigned NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `role` varchar(100) NOT NULL DEFAULT \'\',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_char_clan` (`character_id`,`organization_id`),
  KEY `idx_hccb_char_active` (`character_id`,`is_active`),
  KEY `idx_hccb_clan` (`organization_id`),
  CONSTRAINT `fk_bco_character` FOREIGN KEY (`character_id`) REFERENCES `fact_characters` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_bco_organization` FOREIGN KEY (`organization_id`) REFERENCES `dim_organizations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=563 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    8 => 
    array (
      'name' => 'bridge_characters_powers',
      'create_sql' => 'CREATE TABLE `bridge_characters_powers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `character_id` int(10) unsigned NOT NULL,
  `power_kind` enum(\'dones\',\'disciplinas\',\'rituales\') NOT NULL,
  `power_id` int(10) unsigned NOT NULL,
  `power_level` int(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_bcp_character` (`character_id`),
  CONSTRAINT `fk_bcp_character` FOREIGN KEY (`character_id`) REFERENCES `fact_characters` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1631 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    9 => 
    array (
      'name' => 'bridge_characters_relations',
      'create_sql' => 'CREATE TABLE `bridge_characters_relations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `source_id` int(10) unsigned NOT NULL,
  `target_id` int(10) unsigned NOT NULL,
  `relation_type` varchar(100) NOT NULL,
  `tag` varchar(100) DEFAULT NULL,
  `importance` int(11) DEFAULT 0,
  `description` text DEFAULT NULL,
  `arrows` enum(\'\',\'to\',\'from\',\'to,from\') NOT NULL DEFAULT \'\',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `source_id` (`source_id`),
  KEY `target_id` (`target_id`)
) ENGINE=InnoDB AUTO_INCREMENT=715 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    10 => 
    array (
      'name' => 'bridge_characters_system_resources',
      'create_sql' => 'CREATE TABLE `bridge_characters_system_resources` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `character_id` int(10) unsigned NOT NULL,
  `resource_id` int(10) unsigned NOT NULL,
  `value_permanent` int(11) NOT NULL DEFAULT 0,
  `value_temporary` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_char_resource` (`character_id`,`resource_id`),
  KEY `idx_bcsr_character` (`character_id`),
  KEY `idx_bcsr_resource` (`resource_id`),
  CONSTRAINT `fk_bcsr_character` FOREIGN KEY (`character_id`) REFERENCES `fact_characters` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_bcsr_resource` FOREIGN KEY (`resource_id`) REFERENCES `dim_systems_resources` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=809 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    11 => 
    array (
      'name' => 'bridge_characters_system_resources_log',
      'create_sql' => 'CREATE TABLE `bridge_characters_system_resources_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `character_id` int(10) unsigned NOT NULL,
  `resource_id` int(10) unsigned NOT NULL,
  `old_permanent` int(11) DEFAULT NULL,
  `new_permanent` int(11) DEFAULT NULL,
  `old_temporary` int(11) DEFAULT NULL,
  `new_temporary` int(11) DEFAULT NULL,
  `delta_permanent` int(11) DEFAULT NULL,
  `delta_temporary` int(11) DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `source` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` varchar(80) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_bcsrl_character` (`character_id`),
  KEY `idx_bcsrl_resource` (`resource_id`),
  KEY `idx_bcsrl_source_created` (`source`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=698 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    12 => 
    array (
      'name' => 'bridge_characters_traits',
      'create_sql' => 'CREATE TABLE `bridge_characters_traits` (
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `character_id` int(10) unsigned NOT NULL,
  `trait_id` int(10) unsigned NOT NULL,
  `value` tinyint(4) NOT NULL DEFAULT 0,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`character_id`,`trait_id`),
  KEY `idx_fct_trait_id` (`trait_id`),
  CONSTRAINT `fk_bct_character` FOREIGN KEY (`character_id`) REFERENCES `fact_characters` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_bct_trait` FOREIGN KEY (`trait_id`) REFERENCES `dim_traits` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    13 => 
    array (
      'name' => 'bridge_characters_traits_log',
      'create_sql' => 'CREATE TABLE `bridge_characters_traits_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `character_id` int(10) unsigned NOT NULL,
  `trait_id` int(10) unsigned NOT NULL,
  `old_value` tinyint(4) DEFAULT NULL,
  `new_value` tinyint(4) DEFAULT NULL,
  `delta` smallint(6) DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `source` varchar(50) NOT NULL DEFAULT \'backfill\',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` varchar(80) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_fctl_character` (`character_id`),
  KEY `idx_fctl_trait` (`trait_id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    14 => 
    array (
      'name' => 'bridge_organizations_groups',
      'create_sql' => 'CREATE TABLE `bridge_organizations_groups` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int(10) unsigned NOT NULL,
  `group_id` int(10) unsigned NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_clan_group` (`organization_id`,`group_id`),
  KEY `group_id` (`group_id`)
) ENGINE=InnoDB AUTO_INCREMENT=70 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    15 => 
    array (
      'name' => 'bridge_soundtrack_links',
      'create_sql' => 'CREATE TABLE `bridge_soundtrack_links` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `soundtrack_id` int(10) unsigned NOT NULL,
  `object_type` enum(\'personaje\',\'temporada\',\'episodio\') NOT NULL,
  `object_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bsl_triplet` (`soundtrack_id`,`object_type`,`object_id`),
  KEY `idx_bsl_object_lookup` (`object_type`,`object_id`)
) ENGINE=InnoDB AUTO_INCREMENT=62 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    16 => 
    array (
      'name' => 'bridge_systems_detail_labels',
      'create_sql' => 'CREATE TABLE `bridge_systems_detail_labels` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `system_id` int(10) unsigned NOT NULL,
  `label_auspice` varchar(120) NOT NULL DEFAULT \'\',
  `label_breed` varchar(120) NOT NULL DEFAULT \'\',
  `label_tribe` varchar(120) NOT NULL DEFAULT \'\',
  `label_misc` varchar(120) NOT NULL DEFAULT \'\',
  `label_pack` varchar(120) NOT NULL DEFAULT \'\',
  `label_clan` varchar(120) NOT NULL DEFAULT \'\',
  `label_pk_name` varchar(120) NOT NULL DEFAULT \'\',
  `label_social` varchar(120) NOT NULL DEFAULT \'\',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bridge_systems_detail_labels_system` (`system_id`),
  KEY `idx_bridge_systems_detail_labels_active` (`is_active`),
  CONSTRAINT `fk_bridge_systems_detail_labels_sys` FOREIGN KEY (`system_id`) REFERENCES `dim_systems` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    17 => 
    array (
      'name' => 'bridge_systems_ex_auspices',
      'create_sql' => 'CREATE TABLE `bridge_systems_ex_auspices` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `system_id` int(10) unsigned NOT NULL,
  `auspice_id` int(10) unsigned NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_system_ex_auspice` (`system_id`,`auspice_id`),
  KEY `idx_bsea_system` (`system_id`),
  KEY `idx_bsea_auspice` (`auspice_id`),
  CONSTRAINT `fk_bsea_auspice` FOREIGN KEY (`auspice_id`) REFERENCES `dim_auspices` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_bsea_system` FOREIGN KEY (`system_id`) REFERENCES `dim_systems` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    18 => 
    array (
      'name' => 'bridge_systems_ex_races',
      'create_sql' => 'CREATE TABLE `bridge_systems_ex_races` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `system_id` int(10) unsigned NOT NULL,
  `race_id` int(10) unsigned NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_system_ex_race` (`system_id`,`race_id`),
  KEY `idx_bser_system` (`system_id`),
  KEY `idx_bser_race` (`race_id`),
  CONSTRAINT `fk_bser_race` FOREIGN KEY (`race_id`) REFERENCES `dim_breeds` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_bser_system` FOREIGN KEY (`system_id`) REFERENCES `dim_systems` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    19 => 
    array (
      'name' => 'bridge_systems_ex_tribes',
      'create_sql' => 'CREATE TABLE `bridge_systems_ex_tribes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `system_id` int(10) unsigned NOT NULL,
  `tribe_id` int(10) unsigned NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_system_ex_tribe` (`system_id`,`tribe_id`),
  KEY `idx_best_system` (`system_id`),
  KEY `idx_best_tribe` (`tribe_id`),
  CONSTRAINT `fk_best_system` FOREIGN KEY (`system_id`) REFERENCES `dim_systems` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_best_tribe` FOREIGN KEY (`tribe_id`) REFERENCES `dim_tribes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    20 => 
    array (
      'name' => 'bridge_systems_form_icons',
      'create_sql' => 'CREATE TABLE `bridge_systems_form_icons` (
  `system_id` int(10) unsigned NOT NULL,
  `icon_html` varchar(64) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`system_id`),
  CONSTRAINT `fk_bridge_systems_form_icons_system` FOREIGN KEY (`system_id`) REFERENCES `dim_systems` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    21 => 
    array (
      'name' => 'bridge_systems_resources_to_system',
      'create_sql' => 'CREATE TABLE `bridge_systems_resources_to_system` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `system_id` int(10) unsigned NOT NULL,
  `resource_id` int(10) unsigned NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_system_resource` (`system_id`,`resource_id`),
  KEY `idx_bridge_system` (`system_id`),
  KEY `idx_bridge_resource` (`resource_id`),
  CONSTRAINT `fk_bridge_sys_res_to_system_sys` FOREIGN KEY (`system_id`) REFERENCES `dim_systems` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=136 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    22 => 
    array (
      'name' => 'bridge_timeline_events_chapters',
      'create_sql' => 'CREATE TABLE `bridge_timeline_events_chapters` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` int(10) unsigned NOT NULL,
  `chapter_id` int(10) unsigned NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_btech_event_chapter` (`event_id`,`chapter_id`),
  KEY `idx_btech_chapter` (`chapter_id`),
  KEY `idx_btech_event_sort` (`event_id`,`sort_order`),
  CONSTRAINT `fk_btech_chapter` FOREIGN KEY (`chapter_id`) REFERENCES `dim_chapters` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_btech_event` FOREIGN KEY (`event_id`) REFERENCES `fact_timeline_events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=90 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    23 => 
    array (
      'name' => 'bridge_timeline_events_characters',
      'create_sql' => 'CREATE TABLE `bridge_timeline_events_characters` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` int(10) unsigned NOT NULL,
  `character_id` int(10) unsigned NOT NULL,
  `role_label` varchar(80) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_btec_event_character` (`event_id`,`character_id`),
  KEY `idx_btec_character` (`character_id`),
  KEY `idx_btec_event_sort` (`event_id`,`sort_order`),
  CONSTRAINT `fk_btec_character` FOREIGN KEY (`character_id`) REFERENCES `fact_characters` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_btec_event` FOREIGN KEY (`event_id`) REFERENCES `fact_timeline_events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=720 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    24 => 
    array (
      'name' => 'bridge_timeline_events_chronicles',
      'create_sql' => 'CREATE TABLE `bridge_timeline_events_chronicles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` int(10) unsigned NOT NULL,
  `chronicle_id` int(10) unsigned NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_btecr_event_chronicle` (`event_id`,`chronicle_id`),
  KEY `idx_btecr_chronicle` (`chronicle_id`),
  KEY `idx_btecr_event_sort` (`event_id`,`sort_order`),
  CONSTRAINT `fk_btecr_chronicle` FOREIGN KEY (`chronicle_id`) REFERENCES `dim_chronicles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_btecr_event` FOREIGN KEY (`event_id`) REFERENCES `fact_timeline_events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=229 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    25 => 
    array (
      'name' => 'bridge_timeline_events_realities',
      'create_sql' => 'CREATE TABLE `bridge_timeline_events_realities` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` int(10) unsigned NOT NULL,
  `reality_id` int(10) unsigned NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_bter_event_reality` (`event_id`,`reality_id`),
  KEY `idx_bter_reality` (`reality_id`),
  KEY `idx_bter_event_sort` (`event_id`,`sort_order`),
  CONSTRAINT `fk_bter_event` FOREIGN KEY (`event_id`) REFERENCES `fact_timeline_events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_bter_reality` FOREIGN KEY (`reality_id`) REFERENCES `dim_realities` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=194 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    26 => 
    array (
      'name' => 'bridge_timeline_links',
      'create_sql' => 'CREATE TABLE `bridge_timeline_links` (
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` int(10) unsigned NOT NULL,
  `relation_type` enum(\'capitulo\',\'personaje\') NOT NULL,
  `ref_id` int(10) unsigned NOT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `evento_id` (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    27 => 
    array (
      'name' => 'dim_archetypes',
      'create_sql' => 'CREATE TABLE `dim_archetypes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pretty_id` varchar(190) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `description` longtext NOT NULL,
  `willpower_text` longtext NOT NULL,
  `bibliography_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_pretty_id` (`pretty_id`),
  KEY `idx_dim_archetypes_biblio` (`bibliography_id`),
  CONSTRAINT `fk_dim_archetypes_biblio` FOREIGN KEY (`bibliography_id`) REFERENCES `dim_bibliographies` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=57 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    28 => 
    array (
      'name' => 'dim_auspices',
      'create_sql' => 'CREATE TABLE `dim_auspices` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pretty_id` varchar(190) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `system_name` varchar(100) NOT NULL,
  `system_id` int(10) unsigned DEFAULT NULL,
  `energy` int(11) NOT NULL,
  `description` longtext NOT NULL,
  `image_url` varchar(600) NOT NULL,
  `bibliography_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_pretty_id` (`pretty_id`),
  KEY `idx_dim_auspices_biblio` (`bibliography_id`),
  KEY `idx_dim_auspices_system_id` (`system_id`),
  CONSTRAINT `fk_dim_auspices_biblio` FOREIGN KEY (`bibliography_id`) REFERENCES `dim_bibliographies` (`id`),
  CONSTRAINT `fk_dim_auspices_system` FOREIGN KEY (`system_id`) REFERENCES `dim_systems` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=55 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    29 => 
    array (
      'name' => 'dim_bibliographies',
      'create_sql' => 'CREATE TABLE `dim_bibliographies` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pretty_id` varchar(190) DEFAULT NULL,
  `sort_order` int(3) NOT NULL,
  `name` varchar(100) NOT NULL,
  `year` int(4) NOT NULL,
  `publisher` varchar(100) NOT NULL,
  `description` longtext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_pretty_id` (`pretty_id`)
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    30 => 
    array (
      'name' => 'dim_breeds',
      'create_sql' => 'CREATE TABLE `dim_breeds` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pretty_id` varchar(190) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `system_name` varchar(100) NOT NULL,
  `system_id` int(10) unsigned DEFAULT NULL,
  `forms` varchar(100) NOT NULL,
  `energy` int(11) NOT NULL,
  `description` longtext NOT NULL,
  `image_url` varchar(600) NOT NULL,
  `bibliography_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_pretty_id` (`pretty_id`),
  KEY `idx_dim_breeds_biblio` (`bibliography_id`),
  KEY `idx_dim_breeds_system_id` (`system_id`),
  CONSTRAINT `fk_dim_breeds_biblio` FOREIGN KEY (`bibliography_id`) REFERENCES `dim_bibliographies` (`id`),
  CONSTRAINT `fk_dim_breeds_system` FOREIGN KEY (`system_id`) REFERENCES `dim_systems` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    31 => 
    array (
      'name' => 'dim_chapters',
      'create_sql' => 'CREATE TABLE `dim_chapters` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pretty_id` varchar(190) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `chapter_number` int(10) NOT NULL,
  `season_id` int(10) unsigned DEFAULT NULL,
  `synopsis` longtext NOT NULL,
  `played_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_pretty_id` (`pretty_id`),
  KEY `idx_dim_chapters_season_id` (`season_id`),
  CONSTRAINT `fk_dim_chapters_season_id` FOREIGN KEY (`season_id`) REFERENCES `dim_seasons` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=291 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    32 => 
    array (
      'name' => 'dim_character_status',
      'create_sql' => 'CREATE TABLE `dim_character_status` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pretty_id` varchar(60) NOT NULL,
  `label` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(10) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_dcs_label` (`label`),
  UNIQUE KEY `uniq_dcs_pretty_id` (`pretty_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    33 => 
    array (
      'name' => 'dim_character_types',
      'create_sql' => 'CREATE TABLE `dim_character_types` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pretty_id` varchar(190) DEFAULT NULL,
  `kind` varchar(100) NOT NULL,
  `sort_order` int(2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`),
  UNIQUE KEY `uniq_pretty_id` (`pretty_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    34 => 
    array (
      'name' => 'dim_chronicles',
      'create_sql' => 'CREATE TABLE `dim_chronicles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pretty_id` varchar(190) NOT NULL,
  `sort_order` int(2) NOT NULL DEFAULT 0,
  `name` varchar(100) NOT NULL,
  `image_url` varchar(600) NOT NULL DEFAULT \'\',
  `description` longtext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_pretty_id` (`pretty_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    35 => 
    array (
      'name' => 'dim_discipline_types',
      'create_sql' => 'CREATE TABLE `dim_discipline_types` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pretty_id` varchar(190) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `description` longtext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_pretty_id` (`pretty_id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    36 => 
    array (
      'name' => 'dim_doc_categories',
      'create_sql' => 'CREATE TABLE `dim_doc_categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `kind` varchar(100) NOT NULL,
  `sort_order` int(2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    37 => 
    array (
      'name' => 'dim_forms',
      'create_sql' => 'CREATE TABLE `dim_forms` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pretty_id` varchar(190) DEFAULT NULL,
  `affiliation` varchar(100) NOT NULL,
  `race` varchar(30) NOT NULL,
  `system_id` int(10) unsigned DEFAULT NULL,
  `form` varchar(30) NOT NULL,
  `description` longtext NOT NULL,
  `image_url` varchar(600) NOT NULL,
  `weapons` tinyint(1) NOT NULL,
  `firearms` tinyint(1) NOT NULL,
  `strength_bonus` varchar(10) NOT NULL,
  `dexterity_bonus` varchar(10) NOT NULL,
  `stamina_bonus` varchar(10) NOT NULL,
  `regeneration` tinyint(1) NOT NULL,
  `hpregen` int(10) NOT NULL,
  `bibliography_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_pretty_id` (`pretty_id`),
  KEY `idx_dim_forms_biblio` (`bibliography_id`),
  KEY `idx_dim_forms_system_id` (`system_id`),
  CONSTRAINT `fk_dim_forms_biblio` FOREIGN KEY (`bibliography_id`) REFERENCES `dim_bibliographies` (`id`),
  CONSTRAINT `fk_dim_forms_system` FOREIGN KEY (`system_id`) REFERENCES `dim_systems` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=83 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    38 => 
    array (
      'name' => 'dim_gift_types',
      'create_sql' => 'CREATE TABLE `dim_gift_types` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pretty_id` varchar(190) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `sort_order` int(2) NOT NULL,
  `determinant` varchar(100) NOT NULL,
  `description` longtext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_pretty_id` (`pretty_id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    39 => 
    array (
      'name' => 'dim_groups',
      'create_sql' => 'CREATE TABLE `dim_groups` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pretty_id` varchar(190) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `chronicle_id` int(10) unsigned NOT NULL,
  `totem_id` int(10) unsigned NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `description` longtext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_pretty_id` (`pretty_id`)
) ENGINE=InnoDB AUTO_INCREMENT=72 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    40 => 
    array (
      'name' => 'dim_item_types',
      'create_sql' => 'CREATE TABLE `dim_item_types` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pretty_id` varchar(190) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `sort_order` int(2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_pretty_id` (`pretty_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    41 => 
    array (
      'name' => 'dim_map_categories',
      'create_sql' => 'CREATE TABLE `dim_map_categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(80) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `color_hex` char(7) NOT NULL DEFAULT \'#95a5a6\',
  `icon` varchar(255) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `slug` (`slug`),
  KEY `sort_order` (`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    42 => 
    array (
      'name' => 'dim_maps',
      'create_sql' => 'CREATE TABLE `dim_maps` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `center_lat` decimal(9,6) NOT NULL,
  `center_lng` decimal(9,6) NOT NULL,
  `default_zoom` tinyint(3) unsigned NOT NULL DEFAULT 8,
  `min_zoom` tinyint(3) unsigned DEFAULT 3,
  `max_zoom` tinyint(3) unsigned DEFAULT 19,
  `bounds_sw_lat` decimal(9,6) DEFAULT NULL,
  `bounds_sw_lng` decimal(9,6) DEFAULT NULL,
  `bounds_ne_lat` decimal(9,6) DEFAULT NULL,
  `bounds_ne_lng` decimal(9,6) DEFAULT NULL,
  `default_tile` varchar(120) DEFAULT \'carto-dark\',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    43 => 
    array (
      'name' => 'dim_menu_items',
      'create_sql' => 'CREATE TABLE `dim_menu_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int(10) unsigned DEFAULT NULL,
  `menu_key` varchar(60) DEFAULT NULL,
  `label` varchar(120) NOT NULL,
  `href` varchar(255) NOT NULL DEFAULT \'#\',
  `icon` varchar(255) DEFAULT NULL,
  `icon_hover` varchar(255) DEFAULT NULL,
  `item_type` enum(\'static\',\'dynamic\',\'separator\') NOT NULL DEFAULT \'static\',
  `dynamic_source` varchar(50) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `target` enum(\'_self\',\'_blank\') NOT NULL DEFAULT \'_self\',
  `css_class` varchar(120) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=57 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    44 => 
    array (
      'name' => 'dim_merits_flaws',
      'create_sql' => 'CREATE TABLE `dim_merits_flaws` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pretty_id` varchar(190) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `kind` varchar(100) NOT NULL,
  `affiliation` varchar(100) NOT NULL,
  `cost` varchar(3) NOT NULL,
  `description` longtext NOT NULL,
  `system_name` varchar(100) NOT NULL,
  `system_id` int(10) unsigned DEFAULT NULL,
  `bibliography_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_pretty_id` (`pretty_id`),
  KEY `idx_dim_merits_flaws_biblio` (`bibliography_id`),
  KEY `idx_dim_merits_flaws_system_id` (`system_id`),
  FULLTEXT KEY `descripcion` (`description`,`system_name`),
  FULLTEXT KEY `name` (`name`),
  CONSTRAINT `fk_dim_merits_flaws_biblio` FOREIGN KEY (`bibliography_id`) REFERENCES `dim_bibliographies` (`id`),
  CONSTRAINT `fk_dim_merits_flaws_system` FOREIGN KEY (`system_id`) REFERENCES `dim_systems` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=322 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    45 => 
    array (
      'name' => 'dim_organizations',
      'create_sql' => 'CREATE TABLE `dim_organizations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pretty_id` varchar(190) DEFAULT NULL,
  `sort_order` int(3) NOT NULL,
  `name` varchar(100) NOT NULL,
  `totem_id` int(10) unsigned NOT NULL,
  `color` varchar(7) DEFAULT \'#eeeeee\',
  `is_npc` tinyint(1) NOT NULL,
  `description` longtext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_pretty_id` (`pretty_id`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    46 => 
    array (
      'name' => 'dim_parties',
      'create_sql' => 'CREATE TABLE `dim_parties` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    47 => 
    array (
      'name' => 'dim_players',
      'create_sql' => 'CREATE TABLE `dim_players` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pretty_id` varchar(190) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `surname` varchar(100) NOT NULL,
  `show_in_catalog` tinyint(1) NOT NULL DEFAULT 0,
  `picture` varchar(600) NOT NULL,
  `description` longtext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_pretty_id` (`pretty_id`)
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    48 => 
    array (
      'name' => 'dim_realities',
      'create_sql' => 'CREATE TABLE `dim_realities` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pretty_id` varchar(190) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `description` longtext NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_name` (`name`),
  UNIQUE KEY `uniq_pretty_id` (`pretty_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    49 => 
    array (
      'name' => 'dim_rite_types',
      'create_sql' => 'CREATE TABLE `dim_rite_types` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pretty_id` varchar(190) DEFAULT NULL,
  `sort_order` int(3) NOT NULL,
  `name` varchar(100) NOT NULL,
  `determinant` varchar(100) NOT NULL,
  `description` longtext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_pretty_id` (`pretty_id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    50 => 
    array (
      'name' => 'dim_seasons',
      'create_sql' => 'CREATE TABLE `dim_seasons` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pretty_id` varchar(190) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `season_kind` enum(\'temporada\',\'inciso\',\'historia_personal\',\'especial\') DEFAULT NULL,
  `chronicle_id` int(10) unsigned DEFAULT NULL,
  `season_number` int(10) NOT NULL,
  `sort_order` int(11) DEFAULT NULL,
  `finished` tinyint(1) DEFAULT 0,
  `description` longtext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_pretty_id` (`pretty_id`),
  KEY `idx_dim_seasons_chronicle_id` (`chronicle_id`),
  CONSTRAINT `fk_dim_seasons_chronicle` FOREIGN KEY (`chronicle_id`) REFERENCES `dim_chronicles` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    51 => 
    array (
      'name' => 'dim_soundtracks',
      'create_sql' => 'CREATE TABLE `dim_soundtracks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `artist` varchar(255) DEFAULT NULL,
  `youtube_url` varchar(255) DEFAULT NULL,
  `context_title` varchar(255) DEFAULT NULL,
  `added_at` date DEFAULT curdate(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=79 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    52 => 
    array (
      'name' => 'dim_systems',
      'create_sql' => 'CREATE TABLE `dim_systems` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pretty_id` varchar(190) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `image_url` varchar(600) NOT NULL,
  `sort_order` int(3) NOT NULL,
  `forms` tinyint(1) NOT NULL,
  `description` longtext NOT NULL,
  `bibliography_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_pretty_id` (`pretty_id`),
  KEY `idx_dim_systems_biblio` (`bibliography_id`),
  CONSTRAINT `fk_dim_systems_biblio` FOREIGN KEY (`bibliography_id`) REFERENCES `dim_bibliographies` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    53 => 
    array (
      'name' => 'dim_systems_resources',
      'create_sql' => 'CREATE TABLE `dim_systems_resources` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pretty_id` varchar(190) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `kind` varchar(30) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `description` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dim_systems_resources_name_kind` (`name`,`kind`),
  KEY `idx_dim_systems_resources_kind_sort` (`kind`,`sort_order`),
  KEY `idx_dim_systems_resources_pretty_id` (`pretty_id`)
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    54 => 
    array (
      'name' => 'dim_timeline_events_types',
      'create_sql' => 'CREATE TABLE `dim_timeline_events_types` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pretty_id` varchar(80) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) NOT NULL DEFAULT \'\',
  `color_hex` char(7) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_pretty_id` (`pretty_id`),
  UNIQUE KEY `uniq_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    55 => 
    array (
      'name' => 'dim_totem_types',
      'create_sql' => 'CREATE TABLE `dim_totem_types` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pretty_id` varchar(190) DEFAULT NULL,
  `sort_order` int(3) NOT NULL,
  `name` varchar(100) NOT NULL,
  `determinant` varchar(10) NOT NULL,
  `description` longtext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_pretty_id` (`pretty_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    56 => 
    array (
      'name' => 'dim_totems',
      'create_sql' => 'CREATE TABLE `dim_totems` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pretty_id` varchar(190) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `totem_type_id` int(3) NOT NULL,
  `cost` int(2) NOT NULL,
  `description` longtext NOT NULL,
  `traits` longtext NOT NULL,
  `prohibited` longtext NOT NULL,
  `image_url` varchar(600) NOT NULL,
  `bibliography_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_pretty_id` (`pretty_id`),
  KEY `idx_dim_totems_biblio` (`bibliography_id`),
  CONSTRAINT `fk_dim_totems_biblio` FOREIGN KEY (`bibliography_id`) REFERENCES `dim_bibliographies` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=117 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    57 => 
    array (
      'name' => 'dim_traits',
      'create_sql' => 'CREATE TABLE `dim_traits` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pretty_id` varchar(190) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `kind` varchar(100) NOT NULL,
  `classification` varchar(100) NOT NULL,
  `description` longtext NOT NULL,
  `levels` longtext NOT NULL,
  `posse` longtext NOT NULL,
  `special` longtext NOT NULL,
  `bibliography_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_pretty_id` (`pretty_id`),
  KEY `idx_dim_traits_biblio` (`bibliography_id`),
  FULLTEXT KEY `descripcion` (`description`),
  FULLTEXT KEY `name` (`name`),
  CONSTRAINT `fk_dim_traits_biblio` FOREIGN KEY (`bibliography_id`) REFERENCES `dim_bibliographies` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=451 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    58 => 
    array (
      'name' => 'dim_tribes',
      'create_sql' => 'CREATE TABLE `dim_tribes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pretty_id` varchar(190) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `system_name` varchar(100) NOT NULL,
  `system_id` int(10) unsigned DEFAULT NULL,
  `affiliation` varchar(100) NOT NULL,
  `energy` int(11) NOT NULL,
  `description` longtext NOT NULL,
  `powers` longtext NOT NULL,
  `image_url` varchar(600) NOT NULL,
  `bibliography_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_pretty_id` (`pretty_id`),
  KEY `idx_dim_tribes_biblio` (`bibliography_id`),
  KEY `idx_dim_tribes_system_id` (`system_id`),
  CONSTRAINT `fk_dim_tribes_biblio` FOREIGN KEY (`bibliography_id`) REFERENCES `dim_bibliographies` (`id`),
  CONSTRAINT `fk_dim_tribes_system` FOREIGN KEY (`system_id`) REFERENCES `dim_systems` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=101 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    59 => 
    array (
      'name' => 'dim_web_configuration',
      'create_sql' => 'CREATE TABLE `dim_web_configuration` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `config_name` varchar(255) NOT NULL,
  `config_value` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    60 => 
    array (
      'name' => 'fact_admin_posts',
      'create_sql' => 'CREATE TABLE `fact_admin_posts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `author` varchar(30) NOT NULL,
  `title` varchar(50) NOT NULL,
  `posted_at` date NOT NULL,
  `message` longtext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    61 => 
    array (
      'name' => 'fact_characters',
      'create_sql' => 'CREATE TABLE `fact_characters` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pretty_id` varchar(190) DEFAULT NULL,
  `name` varchar(50) NOT NULL,
  `alias` varchar(20) NOT NULL,
  `garou_name` varchar(100) NOT NULL,
  `gender` varchar(1) DEFAULT \'f\',
  `concept` varchar(50) NOT NULL,
  `chronicle_id` int(10) unsigned NOT NULL,
  `reality_id` int(10) unsigned NOT NULL DEFAULT 1,
  `player_id` int(10) unsigned NOT NULL,
  `character_kind` varchar(3) NOT NULL,
  `system_id` int(10) unsigned DEFAULT NULL,
  `totem_id` int(10) unsigned DEFAULT NULL,
  `status_id` int(10) unsigned DEFAULT NULL,
  `character_type_id` int(10) unsigned NOT NULL,
  `breed_id` int(10) unsigned NOT NULL,
  `auspice_id` int(10) unsigned NOT NULL,
  `tribe_id` int(10) unsigned NOT NULL,
  `nature_id` int(10) unsigned NOT NULL,
  `demeanor_id` int(10) unsigned NOT NULL,
  `birthdate_text` varchar(50) NOT NULL DEFAULT \'Desconocido\',
  `rank` varchar(30) NOT NULL DEFAULT \'\',
  `image_url` varchar(600) NOT NULL,
  `text_color` varchar(100) NOT NULL DEFAULT \'SkyBlue\',
  `info_text` longtext NOT NULL DEFAULT \'\',
  `notes` longtext NOT NULL,
  `is_abandoned` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_pretty_id` (`pretty_id`),
  KEY `idx_fact_characters_system_id` (`system_id`),
  KEY `idx_fact_characters_totem_id` (`totem_id`),
  KEY `idx_fact_characters_reality_id` (`reality_id`),
  KEY `idx_fc_chron_kind_abandoned` (`chronicle_id`,`character_kind`,`is_abandoned`),
  KEY `idx_fc_kind_status_abandoned` (`character_kind`,`is_abandoned`),
  KEY `idx_fc_player_abandoned` (`player_id`,`is_abandoned`),
  KEY `idx_fc_system_kind` (`system_id`,`character_kind`),
  KEY `idx_fc_status_id` (`status_id`),
  FULLTEXT KEY `nombre` (`name`,`info_text`),
  CONSTRAINT `fk_fact_characters_reality` FOREIGN KEY (`reality_id`) REFERENCES `dim_realities` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_fact_characters_system` FOREIGN KEY (`system_id`) REFERENCES `dim_systems` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_fc_status` FOREIGN KEY (`status_id`) REFERENCES `dim_character_status` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=363 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'Personajes jugadores\';',
    ),
    62 => 
    array (
      'name' => 'fact_characters_comments',
      'create_sql' => 'CREATE TABLE `fact_characters_comments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `character_id` int(10) unsigned NOT NULL,
  `nick` varchar(25) NOT NULL,
  `comment_time` time NOT NULL,
  `commented_at` date NOT NULL,
  `message` longtext NOT NULL,
  `ip` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=55 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    63 => 
    array (
      'name' => 'fact_characters_deaths',
      'create_sql' => 'CREATE TABLE `fact_characters_deaths` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `character_id` int(10) unsigned NOT NULL,
  `killer_character_id` int(10) unsigned DEFAULT NULL,
  `death_timeline_event_id` int(10) unsigned DEFAULT NULL,
  `death_type` enum(\'accidente\',\'asesinato\',\'catastrofe\',\'suicidio\',\'sacrificio\',\'enfermedad\',\'natural\',\'radiacion\',\'absorcion\',\'destruccion\',\'desconexion\',\'ritual\',\'sobredosis\',\'otros\') NOT NULL DEFAULT \'otros\',
  `death_date` date DEFAULT NULL,
  `death_description` text DEFAULT NULL,
  `narrative_weight` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_fcd_character_id` (`character_id`),
  KEY `idx_fcd_character_id` (`character_id`),
  KEY `idx_fcd_killer_character_id` (`killer_character_id`),
  KEY `idx_fcd_death_timeline_event_id` (`death_timeline_event_id`),
  CONSTRAINT `fk_fcd_character` FOREIGN KEY (`character_id`) REFERENCES `fact_characters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fcd_death_timeline_event` FOREIGN KEY (`death_timeline_event_id`) REFERENCES `fact_timeline_events` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fcd_killer_character` FOREIGN KEY (`killer_character_id`) REFERENCES `fact_characters` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=262 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    64 => 
    array (
      'name' => 'fact_combat_maneuvers',
      'create_sql' => 'CREATE TABLE `fact_combat_maneuvers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pretty_id` varchar(190) DEFAULT NULL,
  `image_url` varchar(190) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `text` longtext NOT NULL,
  `user` varchar(100) NOT NULL,
  `roll` varchar(100) NOT NULL,
  `difficulty` varchar(100) NOT NULL,
  `damage` varchar(100) NOT NULL,
  `actions` varchar(100) NOT NULL,
  `system_name` varchar(100) NOT NULL,
  `system_id` int(10) unsigned DEFAULT NULL,
  `bibliography_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_pretty_id` (`pretty_id`),
  KEY `idx_fact_combat_maneuvers_biblio` (`bibliography_id`),
  KEY `idx_fact_combat_maneuvers_system_id` (`system_id`),
  CONSTRAINT `fk_fact_combat_maneuvers_biblio` FOREIGN KEY (`bibliography_id`) REFERENCES `dim_bibliographies` (`id`),
  CONSTRAINT `fk_fact_combat_maneuvers_system` FOREIGN KEY (`system_id`) REFERENCES `dim_systems` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    65 => 
    array (
      'name' => 'fact_csp_posts',
      'create_sql' => 'CREATE TABLE `fact_csp_posts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `author` varchar(30) NOT NULL,
  `title` varchar(50) NOT NULL,
  `message` longtext NOT NULL,
  `posted_at` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    66 => 
    array (
      'name' => 'fact_dice_rolls',
      'create_sql' => 'CREATE TABLE `fact_dice_rolls` (
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `roll_name` varchar(150) NOT NULL,
  `dice_pool` int(11) NOT NULL,
  `difficulty` int(11) NOT NULL,
  `roll_results` text NOT NULL,
  `successes` int(11) NOT NULL,
  `botch` tinyint(1) NOT NULL,
  `willpower_spent` tinyint(1) NOT NULL DEFAULT 0,
  `ip` varchar(45) NOT NULL,
  `rolled_at` datetime DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `tirada_nombre` (`roll_name`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    67 => 
    array (
      'name' => 'fact_discipline_powers',
      'create_sql' => 'CREATE TABLE `fact_discipline_powers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pretty_id` varchar(190) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `image_url` varchar(600) NOT NULL,
  `disc` varchar(100) NOT NULL,
  `level` int(2) NOT NULL,
  `description` longtext NOT NULL,
  `system_name` longtext NOT NULL,
  `attribute` varchar(100) NOT NULL,
  `skill` varchar(100) NOT NULL,
  `bibliography_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_pretty_id` (`pretty_id`),
  KEY `idx_fact_discipline_powers_biblio` (`bibliography_id`),
  CONSTRAINT `fk_fact_discipline_powers_biblio` FOREIGN KEY (`bibliography_id`) REFERENCES `dim_bibliographies` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    68 => 
    array (
      'name' => 'fact_docs',
      'create_sql' => 'CREATE TABLE `fact_docs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pretty_id` varchar(190) DEFAULT NULL,
  `section_id` int(10) unsigned NOT NULL,
  `title` varchar(150) NOT NULL,
  `content` longtext NOT NULL,
  `source` longtext NOT NULL,
  `bibliography_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_pretty_id` (`pretty_id`),
  KEY `idx_fact_docs_biblio` (`bibliography_id`),
  CONSTRAINT `fk_fact_docs_biblio` FOREIGN KEY (`bibliography_id`) REFERENCES `dim_bibliographies` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    69 => 
    array (
      'name' => 'fact_external_links',
      'create_sql' => 'CREATE TABLE `fact_external_links` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pretty_id` varchar(190) DEFAULT NULL,
  `title` varchar(180) NOT NULL,
  `url` varchar(700) NOT NULL,
  `kind` varchar(100) NOT NULL DEFAULT \'\',
  `source_label` varchar(140) NOT NULL DEFAULT \'\',
  `description` longtext DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fact_external_links_pretty_id` (`pretty_id`),
  KEY `idx_fact_external_links_active_title` (`is_active`,`title`),
  KEY `idx_fact_external_links_kind` (`kind`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    70 => 
    array (
      'name' => 'fact_gifts',
      'create_sql' => 'CREATE TABLE `fact_gifts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pretty_id` varchar(190) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `image_url` varchar(190) DEFAULT NULL,
  `kind` varchar(50) NOT NULL,
  `gift_group` varchar(55) NOT NULL,
  `rank` varchar(25) NOT NULL,
  `attribute_name` varchar(50) NOT NULL,
  `ability_name` varchar(50) NOT NULL,
  `description` longtext NOT NULL,
  `mechanics_text` longtext NOT NULL,
  `system_name` varchar(100) NOT NULL,
  `system_id` int(10) unsigned DEFAULT NULL,
  `bibliography_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_pretty_id` (`pretty_id`),
  KEY `idx_fact_gifts_biblio` (`bibliography_id`),
  KEY `idx_fact_gifts_system_id` (`system_id`),
  FULLTEXT KEY `nombre` (`name`,`description`,`mechanics_text`),
  CONSTRAINT `fk_fact_gifts_biblio` FOREIGN KEY (`bibliography_id`) REFERENCES `dim_bibliographies` (`id`),
  CONSTRAINT `fk_fact_gifts_system` FOREIGN KEY (`system_id`) REFERENCES `dim_systems` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=630 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    71 => 
    array (
      'name' => 'fact_items',
      'create_sql' => 'CREATE TABLE `fact_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pretty_id` varchar(190) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `item_type_id` int(10) unsigned NOT NULL,
  `level` int(2) NOT NULL,
  `gnosis` int(2) NOT NULL,
  `skill_name` varchar(50) NOT NULL,
  `damage_type` varchar(12) NOT NULL,
  `metal` tinyint(1) NOT NULL DEFAULT 0 COMMENT \'1 plata, 2 oro, 3 oro y plata\',
  `rating` tinyint(1) NOT NULL,
  `bonus` int(2) NOT NULL,
  `strength_req` int(2) NOT NULL,
  `dexterity_req` int(2) NOT NULL,
  `image_url` varchar(600) NOT NULL,
  `description` longtext DEFAULT NULL,
  `bibliography_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_pretty_id` (`pretty_id`),
  KEY `idx_fact_items_biblio` (`bibliography_id`),
  FULLTEXT KEY `nombre` (`name`,`description`),
  CONSTRAINT `fk_fact_items_biblio` FOREIGN KEY (`bibliography_id`) REFERENCES `dim_bibliographies` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=160 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    72 => 
    array (
      'name' => 'fact_map_areas',
      'create_sql' => 'CREATE TABLE `fact_map_areas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `map_id` int(10) unsigned NOT NULL,
  `category_id` int(10) unsigned DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `color_hex` char(7) DEFAULT \'#3388ff\',
  `geometry` longtext NOT NULL CHECK (json_valid(`geometry`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_area_map` (`map_id`),
  KEY `fk_area_cat` (`category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    73 => 
    array (
      'name' => 'fact_map_pois',
      'create_sql' => 'CREATE TABLE `fact_map_pois` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pretty_id` varchar(190) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `map_id` int(10) unsigned NOT NULL,
  `category_id` int(10) unsigned NOT NULL,
  `description` text DEFAULT NULL,
  `thumbnail` varchar(255) DEFAULT NULL,
  `latitude` decimal(9,6) NOT NULL,
  `longitude` decimal(9,6) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_pretty_id` (`pretty_id`),
  KEY `fk_pois_map` (`map_id`),
  KEY `fk_pois_cat` (`category_id`),
  FULLTEXT KEY `ft_pois` (`name`,`description`)
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    74 => 
    array (
      'name' => 'fact_misc_systems',
      'create_sql' => 'CREATE TABLE `fact_misc_systems` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pretty_id` varchar(190) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `kind` varchar(100) NOT NULL,
  `system_name` varchar(100) NOT NULL,
  `system_id` int(10) unsigned DEFAULT NULL,
  `energy_name` varchar(100) NOT NULL,
  `energy_value` int(10) NOT NULL,
  `description` longtext NOT NULL,
  `extra_info` longtext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_pretty_id` (`pretty_id`),
  KEY `idx_fact_misc_systems_system_id` (`system_id`),
  CONSTRAINT `fk_fact_misc_systems_system` FOREIGN KEY (`system_id`) REFERENCES `dim_systems` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    75 => 
    array (
      'name' => 'fact_party_members',
      'create_sql' => 'CREATE TABLE `fact_party_members` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `party_id` int(10) unsigned NOT NULL,
  `base_char_id` int(10) unsigned NOT NULL,
  `alias` varchar(255) DEFAULT NULL,
  `m_hp` int(11) NOT NULL,
  `m_rage` int(11) DEFAULT 0,
  `m_gnosis` int(11) DEFAULT 0,
  `m_glamour` int(11) DEFAULT 0,
  `m_mana` int(11) DEFAULT 0,
  `m_blood` int(11) DEFAULT 0,
  `m_wp` int(11) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_fpm_party` (`party_id`),
  KEY `fk_fpm_base_char` (`base_char_id`),
  CONSTRAINT `fk_fpm_base_char` FOREIGN KEY (`base_char_id`) REFERENCES `fact_characters` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_fpm_party` FOREIGN KEY (`party_id`) REFERENCES `dim_parties` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    76 => 
    array (
      'name' => 'fact_party_members_changes',
      'create_sql' => 'CREATE TABLE `fact_party_members_changes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `party_member_id` int(10) unsigned NOT NULL,
  `resource` enum(\'hp\',\'rage\',\'gnosis\',\'blood\',\'glamour\',\'mana\',\'wp\') NOT NULL,
  `value` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_fpmc_party_member` (`party_member_id`),
  CONSTRAINT `fk_fpmc_party_member` FOREIGN KEY (`party_member_id`) REFERENCES `fact_party_members` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    77 => 
    array (
      'name' => 'fact_rites',
      'create_sql' => 'CREATE TABLE `fact_rites` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pretty_id` varchar(190) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `image_url` varchar(190) DEFAULT NULL,
  `kind` varchar(100) NOT NULL,
  `level` int(100) NOT NULL,
  `race` varchar(100) NOT NULL,
  `description` longtext NOT NULL,
  `system_text` longtext NOT NULL,
  `system_name` varchar(100) NOT NULL,
  `system_id` int(10) unsigned DEFAULT NULL,
  `bibliography_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_pretty_id` (`pretty_id`),
  KEY `idx_fact_rites_biblio` (`bibliography_id`),
  KEY `idx_fact_rites_system_id` (`system_id`),
  CONSTRAINT `fk_fact_rites_biblio` FOREIGN KEY (`bibliography_id`) REFERENCES `dim_bibliographies` (`id`),
  CONSTRAINT `fk_fact_rites_system` FOREIGN KEY (`system_id`) REFERENCES `dim_systems` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=100 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    78 => 
    array (
      'name' => 'fact_sim_battles',
      'create_sql' => 'CREATE TABLE `fact_sim_battles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `fighter_one_character_id` int(10) unsigned DEFAULT NULL,
  `fighter_two_character_id` int(10) unsigned DEFAULT NULL,
  `winner_character_id` int(10) unsigned DEFAULT NULL,
  `season_id` int(10) unsigned DEFAULT NULL,
  `is_tournament` tinyint(1) NOT NULL DEFAULT 0,
  `tournament_key` varchar(64) DEFAULT NULL,
  `tournament_round` smallint(5) unsigned DEFAULT NULL,
  `tournament_match` smallint(5) unsigned DEFAULT NULL,
  `fighter_one_alias_snapshot` varchar(120) NOT NULL,
  `fighter_two_alias_snapshot` varchar(120) NOT NULL,
  `winner_summary` varchar(255) NOT NULL,
  `outcome` enum(\'win\',\'draw\') NOT NULL DEFAULT \'draw\',
  `request_ip` varchar(45) NOT NULL DEFAULT \'\',
  `turns_payload` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_fact_sim_battles_created` (`created_at`),
  KEY `idx_fact_sim_battles_outcome` (`outcome`),
  KEY `idx_fact_sim_battles_fighter_one` (`fighter_one_character_id`),
  KEY `idx_fact_sim_battles_fighter_two` (`fighter_two_character_id`),
  KEY `idx_fact_sim_battles_winner_character` (`winner_character_id`),
  KEY `idx_fact_sim_battles_request_ip_created` (`request_ip`,`created_at`),
  KEY `idx_fact_sim_battles_season` (`season_id`),
  KEY `idx_fact_sim_battles_tournament_flag` (`is_tournament`,`created_at`),
  KEY `idx_fact_sim_battles_tournament_key` (`tournament_key`,`tournament_round`,`tournament_match`),
  CONSTRAINT `fk_fact_sim_battles_fighter_one` FOREIGN KEY (`fighter_one_character_id`) REFERENCES `fact_characters` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_fact_sim_battles_fighter_two` FOREIGN KEY (`fighter_two_character_id`) REFERENCES `fact_characters` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_fact_sim_battles_season` FOREIGN KEY (`season_id`) REFERENCES `fact_sim_seasons` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_fact_sim_battles_winner` FOREIGN KEY (`winner_character_id`) REFERENCES `fact_characters` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=477 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    79 => 
    array (
      'name' => 'fact_sim_character_scores',
      'create_sql' => 'CREATE TABLE `fact_sim_character_scores` (
  `character_id` int(10) unsigned NOT NULL,
  `season_id` int(10) unsigned NOT NULL,
  `character_name_snapshot` varchar(120) NOT NULL,
  `wins` int(10) unsigned NOT NULL DEFAULT 0,
  `draws` int(10) unsigned NOT NULL DEFAULT 0,
  `losses` int(10) unsigned NOT NULL DEFAULT 0,
  `battles` int(10) unsigned NOT NULL DEFAULT 0,
  `points` int(10) unsigned NOT NULL DEFAULT 0,
  `damage_dealt` int(10) unsigned NOT NULL DEFAULT 0,
  `damage_taken` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`season_id`,`character_id`),
  KEY `idx_fact_sim_character_scores_points` (`points`),
  KEY `idx_fact_sim_character_scores_wins` (`wins`),
  KEY `idx_fact_sim_character_scores_battles` (`battles`),
  KEY `idx_fact_sim_character_scores_character` (`character_id`),
  KEY `idx_fact_sim_character_scores_season` (`season_id`),
  CONSTRAINT `fk_fact_sim_character_scores_character` FOREIGN KEY (`character_id`) REFERENCES `fact_characters` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_fact_sim_character_scores_season` FOREIGN KEY (`season_id`) REFERENCES `fact_sim_seasons` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    80 => 
    array (
      'name' => 'fact_sim_characters_talk',
      'create_sql' => 'CREATE TABLE `fact_sim_characters_talk` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `character_id` int(10) unsigned DEFAULT NULL,
  `talk_type` varchar(32) NOT NULL,
  `phrase` varchar(500) NOT NULL,
  `flags` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `weight` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sim_talk_type_char_active` (`talk_type`,`character_id`,`is_active`),
  KEY `idx_sim_talk_weight` (`weight`),
  KEY `idx_sim_talk_lookup` (`talk_type`,`is_active`,`character_id`,`weight`,`id`),
  KEY `fk_sim_talk_character` (`character_id`),
  CONSTRAINT `fk_sim_talk_character` FOREIGN KEY (`character_id`) REFERENCES `fact_characters` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    81 => 
    array (
      'name' => 'fact_sim_item_usage',
      'create_sql' => 'CREATE TABLE `fact_sim_item_usage` (
  `item_id` int(10) unsigned NOT NULL,
  `item_name_snapshot` varchar(120) NOT NULL,
  `times_used` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`item_id`),
  KEY `idx_fact_sim_item_usage_times_used` (`times_used`),
  CONSTRAINT `fk_fact_sim_item_usage_item` FOREIGN KEY (`item_id`) REFERENCES `fact_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    82 => 
    array (
      'name' => 'fact_sim_seasons',
      'create_sql' => 'CREATE TABLE `fact_sim_seasons` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `character_limit` int(10) unsigned NOT NULL DEFAULT 35,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fact_sim_seasons_name` (`name`),
  KEY `idx_fact_sim_seasons_active` (`is_active`),
  KEY `idx_fact_sim_seasons_updated` (`updated_at`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    83 => 
    array (
      'name' => 'fact_sim_tournaments',
      'create_sql' => 'CREATE TABLE `fact_sim_tournaments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tournament_key` varchar(64) NOT NULL,
  `name` varchar(120) NOT NULL,
  `season_id` int(10) unsigned DEFAULT NULL,
  `bracket_size` smallint(5) unsigned NOT NULL DEFAULT 8,
  `seed_mode` enum(\'rank\',\'random\') NOT NULL DEFAULT \'rank\',
  `status` enum(\'active\',\'finished\',\'cancelled\') NOT NULL DEFAULT \'active\',
  `state_payload` longtext NOT NULL,
  `champion_character_id` int(10) unsigned DEFAULT NULL,
  `created_by_ip` varchar(45) NOT NULL DEFAULT \'\',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `finished_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fact_sim_tournaments_key` (`tournament_key`),
  KEY `idx_fact_sim_tournaments_status_updated` (`status`,`updated_at`),
  KEY `idx_fact_sim_tournaments_season` (`season_id`),
  KEY `idx_fact_sim_tournaments_champion` (`champion_character_id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    84 => 
    array (
      'name' => 'fact_timeline_events',
      'create_sql' => 'CREATE TABLE `fact_timeline_events` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pretty_id` varchar(190) DEFAULT NULL,
  `event_date` date NOT NULL,
  `date_precision` enum(\'day\',\'month\',\'year\',\'approx\',\'unknown\') NOT NULL DEFAULT \'day\',
  `date_note` varchar(120) DEFAULT NULL,
  `sort_date` date DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `event_type_id` int(10) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `location` varchar(255) DEFAULT NULL,
  `source` varchar(255) DEFAULT NULL,
  `timeline` varchar(100) DEFAULT NULL COMMENT \'LEGACY: reemplazar por bridge eventos-cronicas\',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_pretty_id` (`pretty_id`),
  KEY `idx_fte_event_date` (`event_date`),
  KEY `idx_fte_sort_date` (`sort_date`),
  KEY `idx_fte_kind_sort` (`sort_date`),
  KEY `idx_fte_event_type` (`event_type_id`),
  KEY `idx_fte_event_type_sort` (`event_type_id`,`sort_date`),
  KEY `idx_fte_active_sort` (`is_active`,`sort_date`),
  CONSTRAINT `fk_fte_event_type` FOREIGN KEY (`event_type_id`) REFERENCES `dim_timeline_events_types` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=474 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    85 => 
    array (
      'name' => 'fact_tools_topic_viewer',
      'create_sql' => 'CREATE TABLE `fact_tools_topic_viewer` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `topic_name` varchar(180) NOT NULL,
  `topic_id` int(10) unsigned NOT NULL,
  `topic_url` varchar(255) DEFAULT NULL,
  `topic_description` varchar(500) DEFAULT NULL,
  `chapter_id` int(10) unsigned DEFAULT NULL,
  `link_scope_type` enum(\'character\',\'group\',\'organization\') DEFAULT NULL,
  `link_scope_id` int(10) unsigned DEFAULT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fact_tools_topic_viewer_topic_id` (`topic_id`),
  KEY `idx_fact_tools_topic_viewer_active_sort` (`is_active`,`sort_order`,`topic_name`),
  KEY `idx_fact_tools_topic_viewer_updated` (`updated_at`),
  KEY `idx_fact_tools_topic_viewer_chapter` (`chapter_id`),
  KEY `idx_fact_tools_topic_viewer_scope` (`link_scope_type`,`link_scope_id`),
  CONSTRAINT `fk_fttv_chapter` FOREIGN KEY (`chapter_id`) REFERENCES `dim_chapters` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
    86 => 
    array (
      'name' => 'fact_trait_sets',
      'create_sql' => 'CREATE TABLE `fact_trait_sets` (
  `system_id` int(10) unsigned NOT NULL,
  `trait_id` int(10) unsigned NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`system_id`,`trait_id`),
  KEY `idx_fts_trait` (`trait_id`),
  CONSTRAINT `fk_fact_trait_sets_system` FOREIGN KEY (`system_id`) REFERENCES `dim_systems` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ),
  ),
  'views' => 
  array (
    0 => 
    array (
      'name' => 'vw_sim_characters',
      'create_sql' => 'CREATE OR REPLACE VIEW `vw_sim_characters` AS select `fc`.`id` AS `id`,`fc`.`name` AS `nombre`,`fc`.`alias` AS `alias`,coalesce(case when `dt`.`name` is not null and exists(select 1 from `dim_forms` `fchk` where `fchk`.`race` = `dt`.`name` limit 1) then `dt`.`name` else NULL end,`ds`.`name`,`db`.`system_name`,\'\') AS `fera`,coalesce(`ds`.`name`,`db`.`system_name`,\'\') AS `sistema`,`fc`.`character_kind` AS `kes`,`fc`.`image_url` AS `img`,coalesce(`tr`.`fuerza`,0) AS `fuerza`,coalesce(`tr`.`destreza`,0) AS `destreza`,coalesce(`tr`.`resistencia`,0) AS `resistencia`,coalesce(`tr`.`astucia`,0) AS `astucia`,coalesce(`tr`.`atletismo`,0) AS `atletismo`,coalesce(`tr`.`pelea`,0) AS `pelea`,coalesce(`tr`.`esquivar`,0) AS `esquivar`,coalesce(`tr`.`armascc`,0) AS `armascc`,coalesce(`tr`.`armasdefuego`,0) AS `armasdefuego`,coalesce(`tr`.`informatica`,0) AS `informatica`,coalesce(`sr`.`rabiap`,0) AS `rabiap`,coalesce(`sr`.`gnosisp`,0) AS `gnosisp`,coalesce(`sr`.`fvp`,0) AS `fvp` from (((((`fact_characters` `fc` left join `dim_systems` `ds` on(`ds`.`id` = `fc`.`system_id`)) left join `dim_breeds` `db` on(`db`.`id` = `fc`.`breed_id`)) left join `dim_tribes` `dt` on(`dt`.`id` = `fc`.`tribe_id`)) left join (select `bct`.`character_id` AS `character_id`,max(case when `t`.`pretty_id` = \'fuerza\' then `bct`.`value` end) AS `fuerza`,max(case when `t`.`pretty_id` = \'destreza\' then `bct`.`value` end) AS `destreza`,max(case when `t`.`pretty_id` = \'resistencia\' then `bct`.`value` end) AS `resistencia`,max(case when `t`.`pretty_id` = \'astucia\' then `bct`.`value` end) AS `astucia`,max(case when `t`.`pretty_id` = \'atletismo\' then `bct`.`value` end) AS `atletismo`,max(case when `t`.`pretty_id` = \'pelea\' then `bct`.`value` end) AS `pelea`,max(case when `t`.`pretty_id` = \'esquivar\' then `bct`.`value` end) AS `esquivar`,max(case when `t`.`pretty_id` = \'armas-cuerpo-a-cuerpo\' then `bct`.`value` end) AS `armascc`,max(case when `t`.`pretty_id` = \'armas-de-fuego\' then `bct`.`value` end) AS `armasdefuego`,max(case when `t`.`pretty_id` = \'informatica\' then `bct`.`value` end) AS `informatica` from (`bridge_characters_traits` `bct` join `dim_traits` `t` on(`t`.`id` = `bct`.`trait_id`)) where `t`.`pretty_id` in (\'fuerza\',\'destreza\',\'resistencia\',\'astucia\',\'atletismo\',\'pelea\',\'esquivar\',\'armas-cuerpo-a-cuerpo\',\'armas-de-fuego\',\'informatica\') group by `bct`.`character_id`) `tr` on(`tr`.`character_id` = `fc`.`id`)) left join (select `bcsr`.`character_id` AS `character_id`,max(case when `r`.`pretty_id` = \'rabia\' then `bcsr`.`value_permanent` end) AS `rabiap`,max(case when `r`.`pretty_id` = \'gnosis\' then `bcsr`.`value_permanent` end) AS `gnosisp`,max(case when `r`.`pretty_id` = \'fuerza-de-voluntad\' then `bcsr`.`value_permanent` end) AS `fvp` from (`bridge_characters_system_resources` `bcsr` join `dim_systems_resources` `r` on(`r`.`id` = `bcsr`.`resource_id`)) where `r`.`pretty_id` in (\'rabia\',\'gnosis\',\'fuerza-de-voluntad\') group by `bcsr`.`character_id`) `sr` on(`sr`.`character_id` = `fc`.`id`));',
    ),
    1 => 
    array (
      'name' => 'vw_sim_forms',
      'create_sql' => 'CREATE OR REPLACE VIEW `vw_sim_forms` AS select `f`.`id` AS `id`,`f`.`race` AS `raza`,`f`.`form` AS `forma`,`f`.`weapons` AS `armas`,`f`.`firearms` AS `armasfuego`,cast(coalesce(`f`.`strength_bonus`,\'0\') as signed) AS `bonfue`,cast(coalesce(`f`.`dexterity_bonus`,\'0\') as signed) AS `bondes`,cast(coalesce(`f`.`stamina_bonus`,\'0\') as signed) AS `bonres`,`f`.`regeneration` AS `regenera`,coalesce(`f`.`hpregen`,0) AS `hpregen` from `dim_forms` `f`;',
    ),
    2 => 
    array (
      'name' => 'vw_sim_items',
      'create_sql' => 'CREATE OR REPLACE VIEW `vw_sim_items` AS select `i`.`id` AS `id`,`i`.`name` AS `name`,`i`.`item_type_id` AS `tipo`,coalesce(`i`.`skill_name`,\'\') AS `habilidad`,coalesce(`i`.`bonus`,0) AS `bonus`,coalesce(`i`.`damage_type`,\'\') AS `dano`,case when coalesce(`i`.`metal`,0) in (1,3) then 1 else 0 end AS `metal`,coalesce(`i`.`dexterity_req`,0) AS `destreza` from `fact_items` `i`;',
    ),
  ),
);
