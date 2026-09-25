# Technical Documentation — Heaven's Gate

Última revisión: 2026-09-17.

## 1. Alcance y fuentes

Este documento describe el **runtime actual** de `starkvind/heavens-gate` durante el refactor PHP. Se ha contrastado con el código de `php-refactor` y con el snapshot de producción del 1 de septiembre de 2026 conservado en `starkvind/heavens-gate-continuity`.

Fuentes principales:

- `index.php`
- `.htaccess`
- `app/bootstrap/runtime.php`
- `app/routing/request_runtime.php`
- `app/routing/path_normalization.php`
- `app/routing/path_matcher.php`
- `app/routing/legacy_query.php`
- `app/routing/routes.php`
- `app/http/page_dispatch.php`
- `app/http/dispatch_policy.php`
- `app/http/dispatcher.php`
- `app/http/page_context.php`
- `app/http/request_context.php`
- `app/http/pretty_request.php`
- `app/http/output.php`
- `app/presentation/desktop_context.php`
- `app/views/layout/desktop.php`
- `app/views/layout/head.php`
- `app/mobile/mobile_routes.php`
- `app/helpers/db_connection.php`
- `app/helpers/pretty.php`
- `app/domains/configuration/queries.php`
- `app/controllers/admin/admin_main.php`
- `production-2026-09-01.sql`

Para el inventario humano completo de route keys, aliases, URLs y controladores, véase [ROUTE_DICTIONARY.md](./ROUTE_DICTIONARY.md).

## 2. Arquitectura de request

Flujo público normal:

1. Apache aplica `.htaccess`.
2. Los ficheros y directorios existentes se sirven directamente, salvo zonas bloqueadas.
3. El resto entra en `index.php`.
4. `index.php` inicializa output, conexión y `app/bootstrap/runtime.php`.
5. El bootstrap carga únicamente la pequeña configuración global previa al dispatch; su SQL vive en `app/domains/configuration/queries.php`.
6. `index.php` pasa URI, query y método HTTP explícitos a `app/routing/request_runtime.php`.
7. `request_runtime.php` normaliza el path mediante `path_normalization.php` y decide entre path canónico y compatibilidad legacy.
8. Las URLs canónicas pasan a `app/routing/path_matcher.php`, que **no usa MySQL ni request globals**.
9. Las URLs históricas `?p=...` pasan a `app/routing/legacy_query.php`, que conserva sólo canonicalización/compatibilidad y resolución de IDs/aliases necesaria para construir la URL moderna.
10. `index.php` construye `hgRequest` y decide si debe usarse la presentación móvil de compatibilidad.
11. Las páginas normales pasan por `app/http/page_dispatch.php`, compartido también por el fallback móvil.
12. `page_dispatch.php` aplica normalización pretty explícita, refresca el request context y carga `app/routing/routes.php`.
13. `app/http/dispatch_policy.php` resuelve controlador, sección, fallback y condición bare.
14. `app/http/dispatcher.php` aplica esa decisión e incluye el controlador.
15. Si la respuesta no es bare, `index.php` prepara el contexto de presentación y delega el HTML desktop en `app/views/layout/desktop.php`; el `<head>` vive en `app/views/layout/head.php`.

Ruta conceptual:

`URL -> request runtime -> path matcher/legacy compatibility -> request context -> page dispatch -> route registry -> dispatch policy -> dispatcher -> domain controller -> presentation`

Ejemplo:

`/characters/{slug} -> muestrabio -> app/controllers/bio/bio_page.php`

`app/` no es superficie web pública.

`app/bootstrap/body_work.php`, `app/bootstrap/request_router.php`, `app/bootstrap/head_work.php` y `app/bootstrap/error_reporting.php` han sido retirados. Bootstrap queda reducido a startup.

## 3. Compatibilidad legacy del router

`app/routing/legacy_query.php` es el único propietario de la canonicalización histórica `?p=...`.

Conserva:

- conversión de route keys históricos a URLs canónicas;
- resolución de `pretty_id` necesaria para convertir IDs/aliases antiguos en slugs actuales;
- query strings permitidas en búsquedas, Admin, embeds y algunos endpoints históricos.

No contiene el matcher de paths canónicos y no lee `$_GET`, `$_POST` ni `$_REQUEST`. El transporte llega como argumentos explícitos desde `index.php` / `request_runtime.php`.

`app/helpers/pretty.php` mantiene la resolución de aliases históricos mediante `fact_pretty_id_aliases`.

La presencia puntual de MySQL en esta capa responde a la necesidad histórica de resolver ID/alias -> slug antes de emitir el redirect. No convierte esta capa en el router canónico.

## 4. Registro y dispatch

`app/routing/routes.php` contiene el registro `route key -> controlador + sección`.

`app/http/dispatch_policy.php` contiene la política pura de fallback y respuesta bare.

`app/http/dispatcher.php` ya no decide rutas por su cuenta: recibe la resolución, aplica sección/bare e incluye el controlador.

Rutas bare activas incluyen embeds de foro, APIs de mapas/dados/avatar, AJAX de tooltip/menciones, imagen de crónica y crop.

El contrato todavía conserva el token `snippet_forum_a` como bare legacy, aunque no existe route key correspondiente en el registro. Está documentado como marcador huérfano hasta que se demuestre eliminable.

## 5. Front controller y presentaciones

`index.php` se mantiene como front controller único. Tras Phase 4.1/4.2 ya no contiene el shell HTML desktop, helpers de output/UTF-8, lógica de configuración de aplicación ni lógica interna del router.

Responsabilidades actuales:

- bootstrap mínimo de output y conexión;
- lectura de los transportes HTTP en el borde (`$_SERVER`, `$_GET`, `$_POST`);
- entrega explícita de método/URI/query al runtime de routing;
- construcción del request context explícito;
- decisión desktop/móvil;
- captura de la salida del page dispatch;
- entrega de respuesta bare o delegación a presentación.

El shell desktop vive en `app/views/layout/desktop.php`; su `<head>` vive en `app/views/layout/head.php`. El tema y la URL de cambio a vista móvil se preparan en `app/presentation/desktop_context.php`.

`?view=mobile` usa la misma resolución de URL y el mismo `route key`, pero `app/mobile/mobile_index.php` selecciona un controlador desde `app/mobile/mobile_routes.php`.

No es un segundo router público. Si un route key no tiene controlador móvil específico se usa `app/mobile/controllers/fallback.php`, que delega en el mismo `app/http/page_dispatch.php` que desktop.

Esta arquitectura móvil permanece sólo por compatibilidad hasta que el menú responsive clásico sea reconstruido con SVG + CSS y aprobado visualmente.

## 6. Configuración y conexión

`app/helpers/db_connection.php` requiere:

- `MYSQL_HOST`
- `MYSQL_USER`
- `MYSQL_PWD`
- `MYSQL_BDD`

Busca `config.env` en el padre de la raíz, raíz del proyecto y ubicación legacy bajo `app/`. La conexión se reutiliza durante la request y fuerza `utf8mb4`.

La configuración global previa al dispatch se carga mediante `app/bootstrap/runtime.php`, que delega las consultas en `app/domains/configuration/queries.php`. Actualmente sólo se cargan aquí los valores necesarios antes de despachar (`error_reporting` y `exclude_chronicles`).

## 7. Seguridad web

`.htaccess` bloquea expresamente repositorios ocultos, ficheros de entorno, `/app`, `/admin_docs`, dumps SQL y artefactos internos.

Los ficheros reales bajo `/tools` y `/sql` también quedan bloqueados. Las URLs virtuales `/tools/...` siguen llegando al front controller.

`Options -Indexes` evita listados de directorio.

No existen PHP públicos directos bajo `api/`. Las APIs activas se resuelven mediante el front controller y el routing canónico.

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

`tools/scaffold_section.py` vuelve a estar operativo para **secciones públicas simples**: crea el controlador y cablea `app/routing/path_matcher.php` + `app/routing/routes.php`. Si se solicita CSS, el controlador generado lo registra mediante `hg_page_register_stylesheet()` para mantener la carga en `<head>`.

No sirve para rutas de detalle con `pretty_id`, CRUD complejo ni para decidir automáticamente compatibilidad histórica `?p=...`.

Véase [SCRIPTS_AND_MAINTENANCE.md](./SCRIPTS_AND_MAINTENANCE.md).

## 13. Añadir secciones

Para una sección pública simple puede usarse:

~~~bash
python tools/scaffold_section.py --route-key example --slug example --title "Example" --dry-run
~~~

El flujo que debe quedar claro desde el árbol es:

1. `app/routing/path_matcher.php` — URL -> route key;
2. `app/routing/routes.php` — route key -> controlador;
3. controlador del dominio correcto;
4. `app/mobile/mobile_routes.php` sólo si necesita implementación móvil específica durante el periodo de compatibilidad;
5. [ROUTE_DICTIONARY.md](./ROUTE_DICTIONARY.md).

Si la nueva sección sustituye una URL histórica `?p=...`, la canonicalización correspondiente se añade conscientemente a `app/routing/legacy_query.php`; no es responsabilidad del scaffold por defecto.

No crear accesos directos a PHP bajo `app/`.

Véase [PUBLIC_SECTION_GUIDE.md](./PUBLIC_SECTION_GUIDE.md).

## 14. Herramientas retiradas y archivadas

El Simulador de Combate y el Archivo de Mnemógeno fueron retirados por completo del runtime en septiembre de 2026: no conservan rutas, aliases, APIs, menú ni tombstones 410.

Sus últimas versiones vivas permanecen recuperables en `archive/combat-simulator-last-live` y `archive/game-cards-last-live`. `.github/ci/php-retired-games-archive-audit.py` impide su reintroducción accidental.

El baseline de cierre de la Fase 7 está documentado en [PHP_PHASE7_BASELINE.md](./PHP_PHASE7_BASELINE.md).

## 15. Política documental

Cuando cambie routing/dispatch:

1. cambiar primero el código ejecutable;
2. actualizar `ROUTE_DICTIONARY.md` si cambia el contrato de rutas;
3. actualizar este documento si cambia la arquitectura general;
4. no convertir logs de refactor en documentación viva;
5. mantener separada la documentación histórica de la vigente.

Cuando cambie esquema, regenerar la referencia desde un snapshot nuevo en lugar de editar recuentos por intuición.
