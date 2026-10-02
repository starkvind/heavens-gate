# Esquema de base de datos — producción

Revisión documental: 2026-09-27.

## Fuente y estado

La referencia de base es el dump completo de producción del 24 de septiembre de 2026 conservado en continuity, que ya contiene las tablas dim_datatable_columns y fact_admin_section_usage_daily.

El 27 de septiembre se ejecutó y verificó la retirada de 20 tablas obsoletas vinculadas a Game Cards, Combat Simulator y la copia auxiliar de migración WebP. El dump posterior verificado quedó en:

    /home/starkvind/heavens-gate-db-backups/heavens-gate-post-phase11-7-20260927-001738.sql.gz

SHA-256:

    d35bfe9c4ea62d97fb799bdec9b488cad6f953115f08233be2d3d6d5465469ce

Este documento describe el estado resultante.

## Resumen

| Tipo | Cantidad |
|---|---:|
| Tablas dim_* | 36 |
| Tablas fact_* | 27 |
| Tablas bridge_* | 38 |
| Otras tablas | 0 |
| Total tablas | 102 |
| Vistas | 0 |
| Procedimientos | 0 |

## Tablas dim_*

dim_archetypes, dim_auspices, dim_bibliographies, dim_breeds, dim_chapters, dim_character_conditions, dim_character_status, dim_character_types, dim_chronicles, dim_datatable_columns, dim_discipline_types, dim_doc_categories, dim_forms, dim_gift_types, dim_groups, dim_item_types, dim_map_categories, dim_maps, dim_menu_items, dim_merits_flaws, dim_organization_departments, dim_organizations, dim_parties, dim_players, dim_realities, dim_rite_types, dim_seasons, dim_soundtracks, dim_systems, dim_systems_resources, dim_timeline_events_types, dim_totem_types, dim_totems, dim_traits, dim_tribes, dim_web_configuration.

## Tablas fact_*

fact_actions, fact_admin_posts, fact_admin_section_usage_daily, fact_help_pages, fact_character_avatar_variants, fact_characters, fact_characters_comments, fact_characters_deaths, fact_combat_maneuvers, fact_content_updates, fact_csp_posts, fact_dice_rolls, fact_discipline_powers, fact_docs, fact_external_links, fact_gifts, fact_items, fact_map_areas, fact_map_pois, fact_misc_systems, fact_party_members, fact_party_members_changes, fact_power_rolls, fact_pretty_id_aliases, fact_rites, fact_timeline_events, fact_tools_topic_viewer, fact_trait_sets.

### Ayuda pública

`fact_help_pages` almacena las páginas editoriales de `/help/{slug}`: título, etiqueta, resumen, entradilla, HTML, orden y estado de publicación. El catálogo `/help` se genera directamente desde las filas publicadas.

## Tablas bridge_*

bridge_auspices_energy_resources, bridge_breeds_energy_resources, bridge_chapters_characters, bridge_character_conditions_traits, bridge_characters_conditions, bridge_characters_docs, bridge_characters_external_links, bridge_characters_groups, bridge_characters_items, bridge_characters_merits_flaws, bridge_characters_misc_systems, bridge_characters_org, bridge_characters_organizations, bridge_characters_powers, bridge_characters_relations, bridge_characters_system_resources, bridge_characters_system_resources_log, bridge_characters_traits, bridge_characters_traits_log, bridge_forms_traits, bridge_maneuvers_forms, bridge_maneuvers_systems, bridge_misc_systems_energy_resources, bridge_organizations_groups, bridge_season_order_nodes, bridge_soundtrack_links, bridge_systems_detail_labels, bridge_systems_ex_auspices, bridge_systems_ex_races, bridge_systems_ex_tribes, bridge_systems_form_icons, bridge_systems_resources_to_system, bridge_timeline_events_chapters, bridge_timeline_events_characters, bridge_timeline_events_chronicles, bridge_timeline_events_realities, bridge_timeline_links, bridge_tribes_energy_resources.

## Hubs que no debes romper

### Personajes

fact_characters usa relaciones directas a crónica, realidad, jugador, sistema, tótem, estado, tipo, raza, auspicio y tribu, además de los bridges bridge_characters_*.

pretty_id es identidad pública de URL; las FK internas usan id.

### Crónicas, temporadas y capítulos

- dim_chronicles
- dim_seasons.chronicle_id
- dim_chapters.season_id

No inventar reality_id en temporadas o capítulos.

### Timeline

fact_timeline_events se relaciona con personajes, capítulos, crónicas, realidades y links mediante bridges. Los bridges de timeline usan event_id.

### Organizaciones y grupos

- dim_organizations
- dim_groups
- bridge_organizations_groups
- bridge_characters_groups
- bridge_characters_organizations
- bridge_characters_org

Hay representaciones históricas coexistentes. No consolidarlas solo porque parezcan duplicadas.

### Pretty IDs

fact_pretty_id_aliases conserva compatibilidad de URLs antiguas. Cambiar un slug público puede requerir alias.

### Configuración y navegación

dim_web_configuration contiene configuración runtime. dim_menu_items alimenta navegación configurable. No asumir que el menú fallback PHP es la fuente principal.

### DataTables y uso Admin

dim_datatable_columns configura columnas públicas de DataTables.

fact_admin_section_usage_daily conserva telemetría agregada por sección/día; no almacena IP, user-agent ni identidad de usuario.

## Objetos retirados

Game Cards, Combat Simulator, sus vistas, su procedimiento auxiliar y la tabla admin_webp_image_migration_backup no forman parte del esquema de producción y no deben reaparecer.

El guard .github/ci/php-retired-games-archive-audit.py protege esta frontera.

## Cambios de esquema

Antes de DDL:

1. buscar consumidores PHP/JS;
2. revisar FKs e índices;
3. generar backup;
4. ejecutar fuera del runtime web;
5. verificar producción;
6. generar snapshot nuevo;
7. actualizar este documento;
8. archivar SQL y evidencia en continuity, no en master.
