# Diccionario de rutas — Heaven's Gate

Última revisión: 2026-09-07.

Este documento traduce los `route key` históricos de la web a lenguaje humano. Su objetivo es que una persona que no conozca la arqueología del proyecto pueda seguir una request sin tener que adivinar qué significan nombres como `muestrabio`, `busk`, `temp` o `vermyd`.

## Fuentes de verdad

Este inventario se ha contrastado contra el árbol completo de `php-refactor` y, en particular, contra:

- `index.php`;
- `.htaccess`;
- `app/routing/path_matcher.php`;
- `app/routing/request_runtime.php`;
- `app/routing/routes.php`;
- `app/http/dispatcher.php`;
- `app/mobile/mobile_routes.php`;
- `app/mobile/mobile_index.php`;
- `api/`;
- `app/controllers/`, `app/partials/` y `app/tools/`.

**El código manda.** Este fichero es documentación viva, no un segundo router.

## Cómo leer una ruta

Flujo normal actual:

`URL pública -> path_matcher.php -> route key -> routes.php -> dispatcher.php -> controlador`

Ejemplo:

`/characters/bruma-nocturna` -> `muestrabio` -> `app/controllers/bio/bio_page.php`

Parámetros legacy frecuentes:

- `p`: route key;
- `b`: entidad/elemento principal;
- `t`: tipo, temporada, capítulo o selector secundario según la ruta;
- `org`: organización;
- `tc`: tipo de detalle de sistema;
- `id`: identificador de POI de mapas;
- `s`: subsección administrativa.

## Principal

| Route key | URL canónica | Qué hace | Controlador / destino |
|---|---|---|---|
| `home` | `/home` | Portada. | `app/controllers/main/main_home.php` |
| `news` | `/news` | Noticias. | `app/controllers/main/main_news.php` |
| `status` | `/status` | Estado general del proyecto/web. | `app/controllers/main/main_status.php` |
| `about` | `/about` | Página acerca de Heaven's Gate. | `app/controllers/main/main_about.php` |
| `biblio` | `/bibliography` | Bibliografía y referencias. | `app/controllers/main/main_biblio.php` |
| `busq` | `/search` | Formulario de búsqueda. | `app/controllers/main/main_search_form.php` |
| `busk` | `/search/results` | Resultados de búsqueda. | `app/controllers/main/main_search_result.php` |
| `talim` | `/talim`, `/admin` | Entrada al backend administrativo; `s` selecciona módulo. | `app/controllers/admin/admin_main.php` |
| `error404` | fallback | Página de recurso no encontrado. | `app/controllers/main/error404.php` |

## Temporadas, capítulos y equipos

| Route key | URL canónica | Qué hace | Controlador |
|---|---|---|---|
| `seasons_home` | `/seasons` | Hub de temporadas. | `chapters/seasons_home.php` |
| `seasons_complete` | `/seasons/complete` | Temporadas completas. | `chapters/seasons_home.php` |
| `seasons_interludes` | `/seasons/interludes` | Interludios. | `chapters/seasons_home.php` |
| `seasons_personal` | `/seasons/personal-stories` | Historias personales. | `chapters/seasons_home.php` |
| `seasons_specials` | `/seasons/specials` | Especiales. | `chapters/seasons_home.php` |
| `season_order` | `/seasons/order` | Orden de temporadas. | `chapters/season_order.php` |
| `temp` | `/seasons/{slug}` | Detalle/archivo de una temporada. | `chapters/season_archive.php` |
| `chapters_table` | `/chapters` | Listado de capítulos. | `chapters/chapter_table.php` |
| `seechapter` | `/chapters/{slug}` | Detalle de capítulo. | `chapters/chapter_page.php` |
| `temp_analisis` | `/seasons/analysis` | Análisis de asistencia. | `chapters/season_attendance_analysis.php` |
| `party` | `/parties` | Equipos/tramas activas. | `main/main_parties.php` |

## Personajes, crónicas, organizaciones y relaciones

| Route key | URL canónica | Qué hace | Controlador |
|---|---|---|---|
| `list_table` | `/characters` | Listado general de personajes. | `bio/bio_table.php` |
| `bios` | `/characters/types` | Tipos/categorías de personajes. | `bio/bio_list.php` |
| `biogroup` | `/characters/type/{slug}` | Personajes de un tipo. | `bio/bio_group.php` |
| `muestrabio` | `/characters/{slug}` | **Detalle de personaje / biografía.** | `bio/bio_page.php` |
| `bio_worlds` | `/characters/worlds`, `/characters/worlds/{slug}` | Personajes por realidad/mundo. | `bio/bio_worlds.php` |
| `chronicles` | `/chronicles`, `/chronicles/{slug}` | Hub o detalle de crónica. | `main/main_chronicles.php` |
| `chronicle_image` | `/chronicles/{slug}/image` | Imagen de crónica; respuesta bare. | `main/chronicle_image.php` |
| `bio_chronicles` | legacy -> `/chronicles` | Alias histórico de crónicas. | `main/main_chronicles.php` |
| `listgroups` | `/organizations`, `/groups` | Hub de organizaciones y grupos. | `bio/bio_pack_list.php` |
| `seegroup` | `/organizations/{slug}`, `/groups/{slug}`, `/groups/{org}/{group}` | Detalle de organización o grupo. | `bio/bio_pack_page.php` |
| `org_chart` | `/organizations/{slug}/org-chart` | Organigrama de una organización. | `bio/bio_org_chart.php` |
| `nebula_clan` | `/relationship-map/organizations` | Mapa de relaciones entre organizaciones. | `bio/bio_reltree_clans.php` |
| `nebula_character` | `/relationship-map/characters` | Mapa de relaciones entre personajes. | `bio/bio_reltree_characters.php` |
| `nebula_groups` | `/relationship-map/groups` | Mapa de relaciones entre grupos. | `bio/bio_reltree_groups.php` |

## Documentos e inventario

| Route key | URL canónica | Qué hace | Controlador |
|---|---|---|---|
| `listadocs` | `/documents` | Listado de documentos. | `docs/docs_table.php` |
| `verdoc` | `/documents/{slug}` | Detalle de documento. | `docs/docs_page.php` |
| `rules` | `/rules` | Hub de reglas. | `docs/rules_home.php` |
| `inv` | `/inventory` | Inventario general. | `docs/item_table.php` |
| `inv_type` | `/inventory/type/{slug}` | Inventario por tipo. | `docs/item_list.php` |
| `verobj` | `/inventory/items/{slug}`, `/inventory/{type}/{slug}` | Detalle de objeto. | `docs/item_page.php` |
| `seeitem` | legacy | Nombre histórico para detalle de objeto; GET legacy termina en la ruta canónica de `verobj`. | `docs/item_page.php` |
| `listaobj` | legacy -> `/inventory` | Alias histórico del inventario. | `docs/item_table.php` |
| `imgz` | `?p=imgz` legacy-only | Tablero/herramienta histórica de imágenes. No tiene path canónico en `path_matcher.php`. | `tool/img_board.php` |

## Sistemas

| Route key | URL canónica | Qué hace | Controlador |
|---|---|---|---|
| `listasistemas` | `/systems` | Listado de sistemas. | `systems/systems_table.php` |
| `sistemas` | `/systems/{slug}` | Vista general de un sistema. | `systems/system_overview_page.php` |
| `versistdetalle` | `/systems/breeds/{slug}`, `/systems/auspices/{slug}`, `/systems/tribes/{slug}`, `/systems/misc/{slug}`, `/systems/detail/{tc}/{slug}` | Detalle especializado de sistema. | `systems/system_detail_page.php` |
| `verforma` | `/systems/form/{slug}` | Detalle de forma. | `systems/system_form_page.php` |

## Reglas: rasgos, condiciones, acciones, maniobras, arquetipos, méritos y fallos

| Route key | URL canónica | Qué hace | Controlador |
|---|---|---|---|
| `listarasgos` | `/rules/traits` | Listado de rasgos. | `docs/traits_table.php` |
| `verrasgo` | `/rules/traits/{slug}` | Detalle de rasgo. | `docs/traits_page.php` |
| `listconditions` | `/rules/conditions` | Listado de condiciones. | `docs/conditions_table.php` |
| `vercondition` | `/rules/conditions/{slug}` | Detalle de condición. | `docs/condition_page.php` |
| `actions` | `/rules/actions` | Listado de acciones. | `docs/action_table.php` |
| `veraction` | `/rules/actions/{slug}` | Detalle de acción. | `docs/action_page.php` |
| `maneuver` | `/rules/maneuvers` | Listado de maniobras. | `docs/maneuver_list.php` |
| `vermaneu` | `/rules/maneuvers/{slug}` | Detalle de maniobra. | `docs/maneuver_page.php` |
| `arquetip` | `/rules/archetypes` | Listado de arquetipos. | `docs/arche_table.php` |
| `verarch` | `/rules/archetypes/{slug}` | Detalle de arquetipo. | `docs/arche_page.php` |
| `listamyd` | `/rules/merits-flaws` | Listado de méritos y fallos. | `docs/merfla_table.php` |
| `vermyd` | `/rules/merits-flaws/{slug}` | Detalle de mérito o fallo. | `docs/merfla_page.php` |

## Poderes

| Route key | URL canónica | Qué hace | Controlador |
|---|---|---|---|
| `powers` | `/powers` | Hub de poderes. | `pwrs/powers_home.php` |
| `listadones` | `/powers/gifts` | Listado de Dones. | `pwrs/don_table.php` |
| `dones` | legacy -> `/powers/gifts` | Nombre histórico de la portada/categorías de Dones. | `pwrs/don_category_list.php` |
| `tipodon` | `/powers/gift/type/{slug}` | Dones por tipo. | `pwrs/don_group_list.php` |
| `muestradon` | `/powers/gift/{slug}` | Detalle de Don. | `pwrs/don_page.php` |
| `fulldon` | `/powers/gifts/full` | Listado completo de Dones. | `pwrs/don_full_list.php` |
| `customdon` | `/powers/gifts/custom` | Dones personalizados. | `pwrs/don_custom_list.php` |
| `ritelist` | `/powers/rites` | Listado de Rituales. | `pwrs/rite_table.php` |
| `rites` | legacy -> `/powers/rites` | Nombre histórico de categorías de Rituales. | `pwrs/rite_category_list.php` |
| `tiporite` | `/powers/rite/type/{slug}` | Rituales por tipo. | `pwrs/rite_group_list.php` |
| `seerite` | `/powers/rite/{slug}` | Detalle de Ritual. | `pwrs/rite_page.php` |
| `fullrite` | `/powers/rites/full` | Listado completo de Rituales. | `pwrs/rite_full_list.php` |
| `customrite` | `/powers/rites/custom` | Rituales personalizados. | `pwrs/rite_custom_list.php` |
| `listatotems` | `/powers/totems` | Listado de Tótems. | `pwrs/totm_table.php` |
| `totems` | legacy -> `/powers/totems` | Nombre histórico de categorías de Tótems. | `pwrs/totm_category_list.php` |
| `tipototm` | `/powers/totem/type/{slug}` | Tótems por tipo. | `pwrs/totm_group_list.php` |
| `muestratotem` | `/powers/totem/{slug}` | Detalle de Tótem. | `pwrs/totm_page.php` |
| `fulltotem` | `/powers/totems/full` | Listado completo de Tótems. | `pwrs/totm_full_list.php` |
| `customtotem` | `/powers/totems/custom` | Tótems personalizados. | `pwrs/totm_custom_list.php` |
| `disciplinas` | `/powers/disciplines` | Listado de Disciplinas. | `pwrs/disc_table.php` |
| `tipodisc` | `/powers/discipline/type/{slug}` | Disciplinas por tipo. | `pwrs/disc_group_list.php` |
| `muestradisc` | `/powers/discipline/{slug}` | Detalle de poder de Disciplina. | `pwrs/disc_page.php` |
| `fulldisc` | `/powers/disciplines/full` | Listado completo de Disciplinas. | `pwrs/disc_full_list.php` |
| `customdisc` | `/powers/disciplines/custom` | Disciplinas personalizadas. | `pwrs/disc_custom_list.php` |

## Timeline, música, galería, mapas y jugadores

| Route key | URL canónica | Qué hace | Controlador |
|---|---|---|---|
| `timeline` | `/timeline` | Línea temporal. | `main/events_main.php` |
| `timeline_event` | `/timeline/event/{slug}` | Detalle de evento. | `main/events_page.php` |
| `ost` | `/music` | Banda sonora. | `ost/bso_main.php` |
| `gallery` | `/gallery` | Galería. | `main/main_gallery.php` |
| `maps` | `/maps` | Mapa/hub de mapas. | `maps/maps_main.php` |
| `maps_detail` | `/maps/poi/{slug}` | Detalle de punto de interés. | `maps/maps_detail.php` |
| `maps_api` | `/maps/api` | API de mapas; bare. | `maps/maps_api.php` |
| `players` | `/players` | Listado de jugadores. | `playr/playr_list.php` |
| `seeplayer` | `/players/{slug}` | Detalle de jugador. | `playr/playr_page.php` |

## Herramientas, APIs y embeds

| Route key | URL canónica | Qué hace | Salida / controlador |
|---|---|---|---|
| `dados` | `/tools/dice` | Tirador de dados. | `tool/dice_roller.php` |
| `dice_api` | `/api/dice` | API del tirador; bare. | `tool/dice_api.php` |
| `csp` | `/tools/csp` | Herramienta CSP. | `tool/csp_board.php` |
| `garou_name_gen` | `/tools/garou-name-generator` | Generador de nombres Garou. | `tool/garou_name_generator.php` |
| `forum_avatar_tool` | `/tools/forum-avatar` | Constructor de mensajes/avatar de foro. | `tool/forum_avatar_builder.php` |
| `forum_avatar_api` | `/api/forum-avatar` | API del constructor de foro; bare. | `tool/forum_avatar_api.php` |
| `forum_topic_viewer` | `/tools/forum-topic-viewer` | Visor de temas del foro. | `tool/forum_topic_viewer.php` |
| `crop` | `/tools/crop` | Recortador de imágenes; documento autocontenido y bare. | `app/tools/crop.html` |
| `tooltip` | `/ajax/tooltip` | Fragmento AJAX de tooltip; bare. | `tool/tooltip.php` |
| `mentions` | `/ajax/mentions`, `/ajax/epis` | Fragmentos AJAX de menciones/episodios; bare. | `tool/mentions.php` |
| `forum_message` | `/forum/message` | Snippet de mensaje de foro; bare. | `app/partials/forum_message_snippet.php` |
| `forum_diceroll` | `/forum/diceroll` | Snippet de tirada de foro; bare. | `app/partials/forum_diceroll_snippet.php` |
| `forum_item` | `/forum/item` | Snippet de objeto de foro; bare. | `app/partials/forum_item_snippet.php` |

`dispatcher.php` conserva además `snippet_forum_a` dentro de la lista de respuestas bare, pero **no existe una entrada con ese route key en `routes.php`**. Debe tratarse como marcador legacy huérfano hasta que se demuestre que puede eliminarse.

## Herramientas retiradas: tombstones HTTP 410

El Simulador de Combate y el Archivo de Mnemógeno están retirados. Sus rutas permanecen únicamente para responder de forma explícita y predecible.

### Simulador

| Route key | URL histórica/canónica de retirada |
|---|---|
| `combat_simulator` | `/games/combat-simulator`, `/tools/combat-simulator` |
| `combat_simulator_result` | `.../result` |
| `combat_simulator_logs` | `.../log` |
| `combat_simulator_log` | `.../log/{id}` |
| `combat_simulator_scores` | `.../scores` |
| `combat_simulator_weapons` | `.../weapons` |
| `combat_simulator_tournament` | `.../tournament` |
| `simulador` | alias histórico de `combat_simulator` |
| `simulador2` | alias histórico de resultado |
| `combtodo` | alias histórico de registro |
| `vercombat` | alias histórico de detalle |
| `punts` | alias histórico de puntuaciones |
| `arms` | alias histórico de armas |
| `sim_tournament` | alias histórico de torneo |

Todas terminan en el stub `app/controllers/tool/combat_simulator.php` y deben seguir retiradas.

### Archivo de Mnemógeno / Game Cards

Los route keys `game_cards`, `game_cards_collection`, `game_cards_combat`, `game_cards_mobile`, `game_cards_explanation` y todas las variantes `game_cards_lab*` apuntan a stubs retirados. Las URLs `/games/card-game*`, `/games/hg-cardgame-dev-lab*`, `/game-cards` y `/tools/game-cards` no reactivan la herramienta.

Existen además dos ficheros públicos directos bajo `api/`:

- `api/game_cards.php`;
- `api/game_card_rules.php`.

Ambos responden `HTTP 410 Gone` y son la única excepción PHP directa relevante encontrada fuera del front controller en la superficie pública actual.

## Redirecciones históricas de path

`path_matcher.php` conserva redirecciones 301 para:

- `/index.php` -> `/`;
- `/game_cards.php` -> `/games/card-game`;
- `/crop.html` -> `/tools/crop`;
- `/sep/snippet_forum_hg.php` -> `/forum/message`;
- `/characters/chronicles[/...]` -> `/chronicles[/...]`;
- formas antiguas de inventario -> paths canónicos actuales.

Las URLs legacy `?p=...` se canonicalizan todavía mediante la capa temporal de compatibilidad de `app/bootstrap/request_router.php`.

## Vista móvil de compatibilidad

`?view=mobile` no define un segundo sistema de URLs. Consume el mismo `route key` y selecciona un controlador móvil desde `app/mobile/mobile_routes.php`.

La mayoría de dominios principales tienen controlador móvil específico: home, news, búsqueda, timeline, música, galería, jugadores, inventario, sistemas, personajes, organizaciones, temporadas, capítulos, crónicas, mapas, documentos, reglas/poderes y varias herramientas.

Si un route key no está en `$hgMobileRoutes`, `mobile_index.php` usa `app/mobile/controllers/fallback.php`.

La tabla móvil es **compatibilidad temporal**, no la autoridad de routing. No debe ampliarse como arquitectura paralela salvo necesidad de compatibilidad hasta retirar `?view=mobile` tras el proyecto responsive SVG + CSS.

## Superficies internas que no son rutas públicas

El escaneo del árbol confirma:

- `/app` está bloqueado por `.htaccess`;
- `/admin_docs` está bloqueado;
- ficheros reales bajo `/tools` y `/sql` están bloqueados;
- `app/tools/backfill_content_updates.php`, `app/tools/inspect_db.php` y otros scripts internos no son endpoints públicos;
- `tools/scaffold_section.py` es herramienta de mantenimiento, no ruta web;
- `public/` contiene assets y un `index.html`, no controladores de aplicación.

## Regla para futuras altas o renombrados

Una feature pública normal debe poder rastrearse así sin documentación auxiliar:

`path_matcher.php -> route key legible -> routes.php -> controlador de dominio`

Los route keys históricos **no se renombran todavía** porque son contrato interno/legacy durante el refactor. Este diccionario permite entenderlos ahora y prepara una futura migración controlada si se decide hacerla.

Cuando se añada o retire una ruta, actualizar este documento en el mismo cambio.