# Esquema de base de datos — producción

Fuente: `starkvind/heavens-gate-continuity/snapshots/web/database/production-2026-09-01.sql`

Dump completado: **2026-09-01 23:36:12**  
Servidor: **MariaDB 10.5.29**  
Revisión documental: **2026-09-02**

Este documento es un **mapa mantenible**, no una copia literal del dump.

## Resumen

| Tipo | Cantidad |
|---|---:|
| Tablas `dim_*` | 43 |
| Tablas `fact_*` | 36 |
| Tablas `bridge_*` | 39 |
| Tablas auxiliares `admin_*` | 1 |
| Total tablas | 119 |
| Vistas | 4 |
| Procedimientos | 1 |

## Tablas `dim_*`

`dim_archetypes`, `dim_auspices`, `dim_bibliographies`, `dim_breeds`, `dim_chapters`, `dim_character_conditions`, `dim_character_status`, `dim_character_types`, `dim_chronicles`, `dim_discipline_types`, `dim_doc_categories`, `dim_forms`, `dim_game_card_materials`, `dim_game_card_moves`, `dim_game_card_pack_types`, `dim_game_card_rarities`, `dim_game_card_settings`, `dim_game_card_shop_products`, `dim_game_card_types`, `dim_game_card_ui_texts`, `dim_gift_types`, `dim_groups`, `dim_item_types`, `dim_map_categories`, `dim_maps`, `dim_menu_items`, `dim_merits_flaws`, `dim_organization_departments`, `dim_organizations`, `dim_parties`, `dim_players`, `dim_realities`, `dim_rite_types`, `dim_seasons`, `dim_soundtracks`, `dim_systems`, `dim_systems_resources`, `dim_timeline_events_types`, `dim_totem_types`, `dim_totems`, `dim_traits`, `dim_tribes`, `dim_web_configuration`.

## Tablas `fact_*`

`fact_actions`, `fact_admin_posts`, `fact_character_avatar_variants`, `fact_characters`, `fact_characters_comments`, `fact_characters_deaths`, `fact_combat_maneuvers`, `fact_content_updates`, `fact_csp_posts`, `fact_dice_rolls`, `fact_discipline_powers`, `fact_docs`, `fact_external_links`, `fact_game_card_collection`, `fact_game_card_move_learn_rules`, `fact_game_card_pack_rarity_weights`, `fact_game_card_pack_type_filters`, `fact_gifts`, `fact_items`, `fact_map_areas`, `fact_map_pois`, `fact_misc_systems`, `fact_party_members`, `fact_party_members_changes`, `fact_power_rolls`, `fact_pretty_id_aliases`, `fact_rites`, `fact_sim_battles`, `fact_sim_character_scores`, `fact_sim_characters_talk`, `fact_sim_item_usage`, `fact_sim_seasons`, `fact_sim_tournaments`, `fact_timeline_events`, `fact_tools_topic_viewer`, `fact_trait_sets`.

## Tablas `bridge_*`

`bridge_auspices_energy_resources`, `bridge_battle_sim_characters_seasons`, `bridge_breeds_energy_resources`, `bridge_chapters_characters`, `bridge_character_conditions_traits`, `bridge_characters_conditions`, `bridge_characters_docs`, `bridge_characters_external_links`, `bridge_characters_groups`, `bridge_characters_items`, `bridge_characters_merits_flaws`, `bridge_characters_misc_systems`, `bridge_characters_org`, `bridge_characters_organizations`, `bridge_characters_powers`, `bridge_characters_relations`, `bridge_characters_system_resources`, `bridge_characters_system_resources_log`, `bridge_characters_traits`, `bridge_characters_traits_log`, `bridge_forms_traits`, `bridge_maneuvers_forms`, `bridge_maneuvers_systems`, `bridge_misc_systems_energy_resources`, `bridge_organizations_groups`, `bridge_season_order_nodes`, `bridge_soundtrack_links`, `bridge_systems_detail_labels`, `bridge_systems_ex_auspices`, `bridge_systems_ex_races`, `bridge_systems_ex_tribes`, `bridge_systems_form_icons`, `bridge_systems_resources_to_system`, `bridge_timeline_events_chapters`, `bridge_timeline_events_characters`, `bridge_timeline_events_chronicles`, `bridge_timeline_events_realities`, `bridge_timeline_links`, `bridge_tribes_energy_resources`.

## Tabla auxiliar

`admin_webp_image_migration_backup` conserva el histórico de la migración de rutas de imagen a WebP. No forma parte del modelo editorial normal.

## Vistas

### `vw_game_card_collection`

Proyección de `fact_game_card_collection` limitada a cartas activas.

### `vw_sim_characters`

Adaptador para el simulador. Combina `fact_characters` con sistema/raza/tribu, rasgos y recursos.

### `vw_sim_forms`

Adaptador del simulador sobre `dim_forms`.

### `vw_sim_items`

Adaptador del simulador sobre `fact_items`.

## Procedimiento

`audit_signed_id_columns()` inspecciona columnas de IDs firmadas y vuelca resultados en la tabla de auditoría usada por el procedimiento. Es una herramienta de diagnóstico de endurecimiento del esquema.

## Núcleo narrativo

### `fact_characters`

Campos relacionales principales:

- `chronicle_id`;
- `reality_id`;
- `player_id`;
- `system_id`;
- `totem_id`;
- `status_id`;
- `character_type_id`;
- `breed_id`;
- `auspice_id`;
- `tribe_id`.

`pretty_id` es único y sirve de slug público.

### `dim_realities`

- `id`;
- `pretty_id` único;
- `name` único;
- `description`;
- `is_active`.

Los personajes tienen realidad directa. Los eventos usan bridge.

### `dim_chronicles`

- `pretty_id` obligatorio y único;
- `sort_order`;
- `name`;
- `image_url`;
- `description`.

### `dim_seasons`

- `season_kind`: `temporada`, `inciso`, `historia_personal` o `especial`;
- `chronicle_id`;
- `season_number`;
- `sort_order`;
- `finished`.

No contiene `reality_id`.

### `dim_chapters`

- `season_id`;
- `chapter_number`;
- `synopsis`;
- `played_date`.

No contiene `reality_id`.

### `fact_timeline_events`

Campos temporales:

- `event_date`;
- `date_precision`: `day`, `month`, `year`, `approx`, `unknown`;
- `date_note`;
- `sort_date`.

Otros:

- `title`;
- `description`;
- `event_type_id`;
- `is_active`;
- `location`;
- `source`;
- `timeline`, comentario de esquema: “LEGACY: reemplazar por bridge eventos-cronicas”.

Relaciones N:M:

- personajes;
- capítulos;
- crónicas;
- realidades.

Los bridges usan `event_id`.

## Organizaciones y grupos

`dim_organizations` puede referenciar `totem_id`.

`dim_groups` requiere `chronicle_id` y puede referenciar `totem_id`.

Relación organización-grupo:

`bridge_organizations_groups`

Relaciones de personajes relevantes:

- `bridge_characters_groups`;
- `bridge_characters_organizations`;
- `bridge_characters_org`.

La convivencia de más de una representación de afiliación es deliberadamente tratada como área de migración/compatibilidad; no eliminar una de ellas solo por el nombre.

## Pretty IDs

`fact_pretty_id_aliases` guarda:

- `table_name`;
- `entity_id`;
- `old_pretty_id`;
- `new_pretty_id`;
- `source`.

El runtime puede resolver URLs antiguas sin usar IDs numéricos públicos.

## Juego de cartas

Catálogo:

`fact_game_card_collection`

`source_type` admite: `character`, `episode`, `season`, `chronicle`, `system`, `tribe`, `auspice`, `form`, `object`, `document`, `power`, `totem`, `gift`, `rite` y `discipline`.

`card_rarity` admite: `common`, `unusual`, `rare`, `epic`, `legendary`, `mythic` y `stigmatic`.

Reglas principales:

- `dim_game_card_moves`;
- `fact_game_card_move_learn_rules`;
- `dim_game_card_settings`;
- `dim_game_card_pack_types`;
- `dim_game_card_rarities`;
- `dim_game_card_shop_products`;
- `fact_game_card_pack_rarity_weights`;
- `fact_game_card_pack_type_filters`.

## Criterios para cambios

Antes de alterar el esquema:

- comprobar consumidores en PHP/JS;
- revisar FKs y claves únicas;
- revisar bridges homónimos/legacy;
- comprobar rutas públicas dependientes de `pretty_id`;
- generar un snapshot nuevo después del cambio;
- actualizar este documento con la nueva fecha de producción.

`admin_docs/bdd_structure.txt` no sustituye esta referencia.

