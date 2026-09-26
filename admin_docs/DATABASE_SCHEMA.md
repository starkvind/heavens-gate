# Esquema de base de datos — producción

Fuente: estado de producción posterior a Phase 11.7, respaldado por `/home/starkvind/heavens-gate-db-backups/heavens-gate-post-phase11-7-20260927-001738.sql.gz` (SHA-256 `d35bfe9c4ea62d97fb799bdec9b488cad6f953115f08233be2d3d6d5465469ce`).

Limpieza verificada: **2026-09-27 00:17**  
Servidor: **MariaDB 10.5.29**  
Revisión documental: **2026-09-27**

Este documento es un **mapa mantenible**, no una copia literal del dump.

## Resumen

| Tipo | Cantidad |
|---|---:|
| Tablas `dim_*` | 35 |
| Tablas `fact_*` | 26 |
| Tablas `bridge_*` | 38 |
| Tablas auxiliares `admin_*` | 0 |
| Total tablas | 99 |
| Vistas | 0 |
| Procedimientos | 0 |

## Tablas `dim_*`

`dim_archetypes`, `dim_auspices`, `dim_bibliographies`, `dim_breeds`, `dim_chapters`, `dim_character_conditions`, `dim_character_status`, `dim_character_types`, `dim_chronicles`, `dim_discipline_types`, `dim_doc_categories`, `dim_forms`, `dim_gift_types`, `dim_groups`, `dim_item_types`, `dim_map_categories`, `dim_maps`, `dim_menu_items`, `dim_merits_flaws`, `dim_organization_departments`, `dim_organizations`, `dim_parties`, `dim_players`, `dim_realities`, `dim_rite_types`, `dim_seasons`, `dim_soundtracks`, `dim_systems`, `dim_systems_resources`, `dim_timeline_events_types`, `dim_totem_types`, `dim_totems`, `dim_traits`, `dim_tribes`, `dim_web_configuration`.

## Tablas `fact_*`

`fact_actions`, `fact_admin_posts`, `fact_character_avatar_variants`, `fact_characters`, `fact_characters_comments`, `fact_characters_deaths`, `fact_combat_maneuvers`, `fact_content_updates`, `fact_csp_posts`, `fact_dice_rolls`, `fact_discipline_powers`, `fact_docs`, `fact_external_links`, `fact_gifts`, `fact_items`, `fact_map_areas`, `fact_map_pois`, `fact_misc_systems`, `fact_party_members`, `fact_party_members_changes`, `fact_power_rolls`, `fact_pretty_id_aliases`, `fact_rites`, `fact_timeline_events`, `fact_tools_topic_viewer`, `fact_trait_sets`.

## Tablas `bridge_*`

`bridge_auspices_energy_resources`, `bridge_breeds_energy_resources`, `bridge_chapters_characters`, `bridge_character_conditions_traits`, `bridge_characters_conditions`, `bridge_characters_docs`, `bridge_characters_external_links`, `bridge_characters_groups`, `bridge_characters_items`, `bridge_characters_merits_flaws`, `bridge_characters_misc_systems`, `bridge_characters_org`, `bridge_characters_organizations`, `bridge_characters_powers`, `bridge_characters_relations`, `bridge_characters_system_resources`, `bridge_characters_system_resources_log`, `bridge_characters_traits`, `bridge_characters_traits_log`, `bridge_forms_traits`, `bridge_maneuvers_forms`, `bridge_maneuvers_systems`, `bridge_misc_systems_energy_resources`, `bridge_organizations_groups`, `bridge_season_order_nodes`, `bridge_soundtrack_links`, `bridge_systems_detail_labels`, `bridge_systems_ex_auspices`, `bridge_systems_ex_races`, `bridge_systems_ex_tribes`, `bridge_systems_form_icons`, `bridge_systems_resources_to_system`, `bridge_timeline_events_chapters`, `bridge_timeline_events_characters`, `bridge_timeline_events_chronicles`, `bridge_timeline_events_realities`, `bridge_timeline_links`, `bridge_tribes_energy_resources`.

## Superficies retiradas

Phase 11.7 eliminó de producción las tablas, vistas, procedimiento, configuración y entradas de menú pertenecientes a Game Cards y Combat Simulator, además de la tabla auxiliar de migración WebP. No quedan vistas ni procedimientos almacenados activos en el esquema actual.

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

La convivencia de más de una representación de afiliación se trata como compatibilidad histórica; no eliminar una de ellas solo por el nombre.

## Pretty IDs

`fact_pretty_id_aliases` guarda:

- `table_name`;
- `entity_id`;
- `old_pretty_id`;
- `new_pretty_id`;
- `source`.

El runtime puede resolver URLs antiguas sin usar IDs numéricos públicos.

## Criterios para cambios

Antes de alterar el esquema:

- comprobar consumidores en PHP/JS;
- revisar FKs y claves únicas;
- revisar bridges homónimos/legacy;
- comprobar rutas públicas dependientes de `pretty_id`;
- generar un snapshot nuevo después del cambio;
- actualizar este documento con la nueva fecha de producción.


