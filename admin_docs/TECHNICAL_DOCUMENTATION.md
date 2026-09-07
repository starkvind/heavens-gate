# Technical Documentation — Heaven's Gate

Última revisión: 2026-09-07.

## 1. Alcance y fuentes

Este documento describe el **runtime actual** de `starkvind/heavens-gate` durante el refactor PHP. Se ha contrastado con el código de `php-refactor` y con el snapshot de producción del 1 de septiembre de 2026 conservado en `starkvind/heavens-gate-continuity`.

Fuentes principales:

- `index.php`
- `.htaccess`
- `app/routing/request_runtime.php`
- `app/routing/path_matcher.php`
- `app/routing/routes.php`
- `app/http/dispatcher.php`
- `app/bootstrap/request_router.php` — compatibilidad legacy todavía activa
- `app/bootstrap/page_context.php`
- `app/mobile/mobile_routes.php`
- `app/helpers/db_connection.php`
- `app/helpers/pretty.php`
- `app/controllers/admin/admin_main.php`
- `production-2026-09-01.sql`

Para el inventario humano completo de route keys, aliases, URLs y controladores, véase [ROUTE_DICTIONARY.md](./ROUTE_DICTIONARY.md).

## 2. Arquitectura de request

Flujo público normal:

1. Apache aplica `.htaccess`.
2. Los ficheros y directorios existentes se sirven directamente, salvo zonas bloqueadas.
3. El resto entra en `index.php`.
4. `index.php` abre la conexión y entra en `app/routing/request_runtime.php`.
5. `request_runtime.php` normaliza la request y decide entre path canónico y compatibilidad legacy.
6. Las URLs canónicas pasan a `app/routing/path_matcher.php`, que **no usa MySQL ni request globals**.
7. `path_matcher.php` produce un `route key` y parámetros internos.
8. `index.php` decide si debe usarse la presentación móvil de compatibilidad.
9. En desktop, `app/bootstrap/body_work.php` orquesta `page_context.php` + `routes.php` + `dispatcher.php`.
10. `app/routing/routes.php` traduce el `route key` a controlador.
11. `app/http/dispatcher.php` incluye el controlador y decide si la respuesta es bare.
12. Si no es bare, `index.php` integra el contenido en el layout común.

Ruta conceptual:

`URL -> path matcher -> route key -> route registry -> dispatcher -> domain controller`

Ejemplo:

`/characters/{slug} -> muestrabio -> app/controllers/bio/bio_page.php`

`app/` no es superficie web pública.

## 3. Compatibilidad legacy del router

`app/bootstrap/request_router.php` ya no es el router normal de paths canónicos. Durante el refactor conserva temporalmente:

- canonicalización de URLs antiguas `?p=...`;
- resolución de `pretty_id` necesaria para convertir IDs/aliases antiguos en slugs actuales;
- compatibilidad de query strings de búsquedas, Admin, embeds y algunos endpoints históricos.

La intención arquitectónica es reducirlo a compatibilidad explícita y retirar funciones muertas sólo después de pruebas de regresión.

`app/helpers/pretty.php` mantiene la resolución de aliases históricos mediante `fact_pretty_id_aliases`.

## 4. Registro y dispatch

`app/routing/routes.php` contiene el registro `route key -> controlador + sección`.

`app/http/dispatcher.php` contiene la política de inclusión, fallback y respuestas bare.

Rutas bare activas incluyen embeds de foro, APIs de mapas/dados/avatar, AJAX de tooltip/menciones, imagen de crónica y crop.

El dispatcher conserva el token `snippet_forum_a` como bare legacy, aunque no existe route key correspondiente en el registro. Está documentado como marcador huérfano hasta que se demuestre eliminable.

## 5. Vista móvil de compatibilidad

`?view=mobile` usa la misma resolución de URL y el mismo `route key`, pero `app/mobile/mobile_index.php` selecciona un controlador desde `app/mobile/mobile_routes.php`.

No es un segundo router público. Si un route key no tiene controlador móvil específico se usa `app/mobile/controllers/fallback.php`.

Esta arquitectura permanece sólo por compatibilidad hasta que el menú responsive clásico sea reconstruido con SVG + CSS y aprobado visualmente.

## 6. Configuración y conexión

`app/helpers/db_connection.php` requiere:

- `MYSQL_HOST`
- `MYSQL_USER`
- `MYSQL_PWD`
- `MYSQL_BDD`

Busca `config.env` en el padre de la raíz, raíz del proyecto y ubicación legacy bajo `app/`. La conexión se reutiliza durante la request y fuerza `utf8mb4`.

## 7. Seguridad web

`.htaccess` bloquea expresamente repositorios ocultos, ficheros de entorno, `/app`, `/admin_docs`, dumps SQL y artefactos internos.

Los ficheros reales bajo `/tools` y `/sql` también quedan bloqueados. Las URLs virtuales `/tools/...` siguen llegando al front controller.

`Options -Indexes` evita listados de directorio.

Los dos PHP públicos directos relevantes bajo `api/` (`game_cards.php`, `game_card_rules.php`) son tombstones retirados y responden `HTTP 410 Gone`.

## 8. Routing canónico

Las formas principales están definidas en `app/routing/path_matcher.php`.

Ejemplos:

- `/characters/{slug}`
- `/characters/worlds/{slug}`
- `/chronicles/{slug}`
- `/seasons/{slug}`
- `/chapters/{slug}`
- `/organizations/{slug}`
- `/groups/{slug}` y `/groups/{org}/{group}`
- `/players/{slug}`
- `/documents/{slug}`
- `/inventory/{type}/{slug}`
- `/timeline/event/{slug}`
- `/maps/poi/{slug}`
- `/systems/{slug}`
- `/rules/.../{slug}`
- `/powers/gift/{slug}`
- `/powers/rite/{slug}`
- `/powers/totem/{slug}`
- `/powers/discipline/{slug}`

Los joins internos deben usar `id`. `pretty_id` es identidad pública de URL.

La traducción completa de nombres internos históricos está en [ROUTE_DICTIONARY.md](./ROUTE_DICTIONARY.md).

## 9. Modelo de datos

La base de producción revisada contiene **119 tablas**:

- 43 `dim_*`;
- 36 `fact_*`;
- 39 `bridge_*`;
- `admin_webp_image_migration_backup`.

Además contiene vistas legacy relacionadas con herramientas retiradas y el procedimiento `audit_signed_id_columns()`. La presencia de objetos de datos del antiguo juego de cartas o simulador no implica runtime activo.

Convención general:

- `dim_*`: catálogos y entidades maestras;
- `fact_*`: contenido o hechos;
- `bridge_*`: relaciones N:M;
- `admin_*`: auxiliares operativas/migración.

Véase [DATABASE_SCHEMA.md](./DATABASE_SCHEMA.md).

## 10. Hubs narrativos

### Personajes

`fact_characters` es el hub principal. Conserva FK directas a crónica, realidad, jugador, sistema, tótem y estado, además de relaciones N:M en `bridge_characters_*`.

### Crónicas, temporadas y capítulos

- `dim_chronicles`
- `dim_seasons.chronicle_id`
- `dim_chapters.season_id`

Las realidades de temporada/crónica no deben inferirse de una columna inexistente; se derivan del contenido/eventos asociados.

### Realidades

`dim_realities` contiene el catálogo. Personajes usan `reality_id`; eventos se relacionan mediante `bridge_timeline_events_realities`.

### Timeline

Hub: `fact_timeline_events`, con bridges a personajes, capítulos, crónicas, realidades y links.

### Organizaciones y grupos

- `dim_organizations`
- `dim_groups`
- `bridge_organizations_groups`
- `bridge_characters_groups`
- `bridge_characters_organizations`
- `bridge_characters_org`

Antes de consolidar afiliaciones históricas, revisar consumidores reales y manifiestos de migración.

### Sistemas y reglas

Catálogos principales: `dim_systems`, `dim_breeds`, `dim_auspices`, `dim_tribes`, `dim_forms`, `dim_traits`, `dim_systems_resources`, más bridges especializados.

## 11. Backend administrativo

El backend editorial entra por `talim` (`/talim` y alias `/admin`). Las subsecciones usan el parámetro interno `s`.

Las mutaciones administrativas deben seguir usando helpers compartidos de autenticación, sesión y CSRF. Este refactor no cambia límites de seguridad.

## 12. Herramientas y mantenimiento

Herramientas internas existentes incluyen:

- `tools/scaffold_section.py`;
- `app/tools/backfill_content_updates.php`;
- `app/tools/inspect_db.php`;
- `sql/audit_gaia0_content.sql`.

**Atención:** `tools/scaffold_section.py` todavía intenta modificar el antiguo `request_router.php` + `body_work.php`. Tras la extracción de Phase 1 ya no representa la arquitectura vigente y **no debe ejecutarse para altas nuevas hasta ser adaptado**.

Véase [SCRIPTS_AND_MAINTENANCE.md](./SCRIPTS_AND_MAINTENANCE.md).

## 13. Añadir secciones

Mientras el scaffold no sea actualizado, una sección pública nueva debe añadirse conscientemente en:

1. `app/routing/path_matcher.php` — URL -> route key;
2. `app/routing/routes.php` — route key -> controlador;
3. controlador del dominio correcto;
4. `app/mobile/mobile_routes.php` sólo si necesita implementación móvil específica durante el periodo de compatibilidad;
5. [ROUTE_DICTIONARY.md](./ROUTE_DICTIONARY.md).

No crear accesos directos a PHP bajo `app/`.

Véase [PUBLIC_SECTION_GUIDE.md](./PUBLIC_SECTION_GUIDE.md).

## 14. Herramientas retiradas con compatibilidad de ruta

El Simulador de Combate y el Archivo de Mnemógeno fueron retirados en septiembre de 2026. Sus rutas y endpoints históricos conservados responden `HTTP 410 Gone`.

Su implementación completa permanece únicamente en `archive/legacy-tools-2026`. No debe reintroducirse durante mantenimiento ordinario.

## 15. Política documental

Cuando cambie routing:

1. cambiar primero el código ejecutable;
2. actualizar `ROUTE_DICTIONARY.md` en el mismo cambio;
3. actualizar este documento sólo si cambia la arquitectura general;
4. no convertir logs de refactor en documentación viva;
5. mantener separada la documentación histórica de la vigente.

Cuando cambie esquema, regenerar la referencia desde un snapshot nuevo en lugar de editar recuentos por intuición.