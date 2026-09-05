# Technical Documentation — Heaven's Gate

Última revisión: 2026-09-05.

## 1. Alcance y fuentes

Este documento describe el **runtime actual** del repositorio `starkvind/heavens-gate`. Se ha contrastado con el código de la rama activa y con el snapshot de producción del 1 de septiembre de 2026 conservado en `starkvind/heavens-gate-continuity`.

Fuentes principales:

- `index.php`
- `.htaccess`
- `app/bootstrap/request_router.php`
- `app/bootstrap/body_work.php`
- `app/helpers/db_connection.php`
- `app/helpers/pretty.php`
- `app/helpers/admin_auth.php`
- `app/helpers/admin_ajax.php`
- `app/controllers/admin/admin_main.php`
- `production-2026-09-01.sql`

La documentación anterior a esta revisión contenía referencias a herramientas ya retiradas. No deben considerarse parte del sistema actual.

## 2. Arquitectura de request

Flujo público:

1. Apache aplica `.htaccess`.
2. Los ficheros y directorios existentes se sirven directamente, salvo zonas bloqueadas.
3. El resto entra en `index.php`.
4. `index.php` abre la conexión y ejecuta `hg_request_router_bootstrap()`.
5. `app/bootstrap/request_router.php`:
   - normaliza la URL;
   - redirige rutas legacy;
   - convierte rutas amigables en parámetros internos;
   - resuelve slugs mediante `pretty_id`.
6. `index.php` decide si debe usarse la presentación móvil.
7. `app/bootstrap/body_work.php` asigna el route key a un controlador.
8. El controlador renderiza contenido completo o salida bare.
9. Si la página no es bare, `index.php` la integra en el layout común.

`app/` no es una superficie web pública. Las herramientas accesibles desde navegador deben tener una ruta explícita.

## 3. Configuración y conexión

`app/helpers/db_connection.php` requiere:

- `MYSQL_HOST`
- `MYSQL_USER`
- `MYSQL_PWD`
- `MYSQL_BDD`

Busca `config.env` en:

1. padre de la raíz del proyecto;
2. raíz del proyecto;
3. ubicación legacy bajo `app/`.

La conexión se reutiliza dentro de la request si sigue viva y fuerza `utf8mb4`.

El acceso administrativo usa hashes de contraseña y no mantiene compatibilidad con credenciales reversiblemente cifradas.

## 4. Seguridad web

`.htaccess` bloquea expresamente:

- repositorios ocultos;
- `config.env` y ficheros de entorno;
- `/app`;
- `/admin_docs`;
- dumps SQL;
- documentación técnica servida directamente;
- otros artefactos de desarrollo.

`Options -Indexes` evita listados de directorio.

Los errores de conexión y runtime pasan por `app/helpers/runtime_response.php`. La capa pública debe evitar SQL y stack traces crudos.

## 5. Routing

`request_router.php` contiene:

- mapeo de route keys legacy a URLs canónicas;
- rutas estáticas;
- rutas regex para entidades con slug;
- redirecciones históricas;
- resolución de `pretty_id`.

Ejemplos canónicos:

- `/characters/{slug}`
- `/characters/worlds/{slug}`
- `/chronicles/{slug}`
- `/seasons/{slug}`
- `/chapters/{slug}`
- `/organizations/{slug}`
- `/groups/{slug}`
- `/players/{slug}`
- `/timeline/event/{slug}`
- `/maps/poi/{slug}`
- `/systems/{slug}`
- `/powers/gift/{slug}`
- `/powers/rite/{slug}`
- `/powers/totem/{slug}`
- `/powers/discipline/{slug}`

Los joins internos deben usar `id`. `pretty_id` es la identidad pública de URL.

`app/helpers/pretty.php` mantiene resolución de aliases históricos mediante `fact_pretty_id_aliases`.

## 6. Modelo de datos

La base de producción revisada contiene **119 tablas**:

- 43 `dim_*`;
- 36 `fact_*`;
- 39 `bridge_*`;
- `admin_webp_image_migration_backup`.

Además contiene:

- `vw_game_card_collection`;
- `vw_sim_characters`;
- `vw_sim_forms`;
- `vw_sim_items`;
- procedimiento `audit_signed_id_columns()`.

Las vistas y tablas relacionadas con el antiguo juego de cartas y el simulador siguen formando parte del snapshot de producción del 1 de septiembre. Su presencia en el esquema no implica que exista runtime web activo para esas herramientas.

Convención general:

- `dim_*`: catálogos y entidades maestras;
- `fact_*`: contenido o hechos;
- `bridge_*`: relaciones N:M;
- tablas `admin_*`: auxiliares operativas/migración.

Véase [DATABASE_SCHEMA.md](./DATABASE_SCHEMA.md).

## 7. Hubs narrativos

### Personajes

`fact_characters` es el hub principal.

FK directas relevantes:

- `chronicle_id` → `dim_chronicles`;
- `reality_id` → `dim_realities`;
- `player_id` → `dim_players`;
- `system_id` → `dim_systems`;
- `totem_id` → `dim_totems`;
- `status_id` → `dim_character_status`.

El personaje conserva muchos vínculos N:M en `bridge_characters_*`.

### Crónicas, temporadas y capítulos

- `dim_chronicles`
- `dim_seasons.chronicle_id`
- `dim_chapters.season_id`

Una temporada no guarda una columna de realidad. Las realidades de temporada se derivan de los eventos asociados a sus capítulos.

Una crónica tampoco debe asumirse unívocamente ligada a una sola realidad por columna propia; el análisis multiversal debe derivarse del contenido/eventos asociados.

### Realidades

`dim_realities` contiene el catálogo.

Los personajes tienen `reality_id` directo.

Los eventos se relacionan mediante `bridge_timeline_events_realities`.

### Timeline

Hub:

`fact_timeline_events`

Campos importantes:

- `event_date`;
- `date_precision`;
- `date_note`;
- `sort_date`;
- `event_type_id`;
- `location`;
- `source`;
- `timeline`, marcado como legacy.

Bridges:

- `bridge_timeline_events_characters`;
- `bridge_timeline_events_chapters`;
- `bridge_timeline_events_chronicles`;
- `bridge_timeline_events_realities`;
- `bridge_timeline_links`.

Los bridges usan `event_id`.

### Organizaciones y grupos

- `dim_organizations`
- `dim_groups`
- `bridge_organizations_groups`
- `bridge_characters_groups`
- `bridge_characters_organizations`
- `bridge_characters_org`

Existen capas históricas/canónicas que conviven en afiliaciones. Antes de consolidar tablas o eliminar un bridge, revisar consumidores reales y los manifiestos de migración.

### Sistemas y reglas

Catálogos:

- `dim_systems`
- `dim_breeds`
- `dim_auspices`
- `dim_tribes`
- `dim_forms`
- `dim_traits`
- `dim_systems_resources`

Relaciones extra:

- `bridge_systems_ex_races`
- `bridge_systems_ex_auspices`
- `bridge_systems_ex_tribes`
- `bridge_systems_detail_labels`
- `bridge_systems_resources_to_system`
- `bridge_systems_form_icons`

Estas tablas permiten reutilizar detalles entre sistemas sin duplicar catálogos.

## 8. Backend administrativo

La aplicación dispone de un backend editorial autenticado. Las rutas privilegiadas, el inventario completo de módulos y los procedimientos operativos no se documentan en detalle en el repositorio público.

Las mutaciones administrativas deben utilizar los helpers compartidos de autenticación y CSRF. El acceso administrativo usa sesiones endurecidas, caducidad y limitación de intentos de login.

## 9. Herramientas y mantenimiento

No existe actualmente un flujo canónico de “instalar el esquema desde un dump” dentro de este repo.

Herramientas existentes:

- `tools/scaffold_section.py`;
- `app/tools/backfill_content_updates.php`;
- `sql/audit_gaia0_content.sql`.

Las antiguas referencias a `install_schema_from_dump.php`, `schema_definition.php`, `schema_initializer.php` y `admin_schema_initializer` están retiradas.

Véase [SCRIPTS_AND_MAINTENANCE.md](./SCRIPTS_AND_MAINTENANCE.md).

## 10. Añadir secciones

Para páginas públicas simples usar `tools/scaffold_section.py` con `--dry-run`.

El script modifica router y dispatcher, y opcionalmente CSS y menú fallback.

No sirve para rutas de entidad o APIs.

Véase [PUBLIC_SECTION_GUIDE.md](./PUBLIC_SECTION_GUIDE.md).

## 11. Herramientas retiradas con compatibilidad de ruta

El Simulador de Combate y el Archivo de Mnemógeno fueron retirados del runtime en septiembre de 2026. Sus motores PHP, parciales, CSS y JavaScript específicos ya no forman parte de la rama activa.

Se conservan únicamente controladores mínimos y endpoints históricos que responden **HTTP 410 Gone**. Su función es mantener una retirada explícita y predecible para URLs antiguas, no proporcionar compatibilidad funcional con las herramientas eliminadas.

La implementación completa anterior permanece recuperable desde la rama de archivo `archive/legacy-tools-2026`.

## 12. Política documental

Cuando cambie código o esquema:

1. actualizar primero el documento especializado;
2. actualizar este mapa si cambia arquitectura general;
3. no convertir informes fechados en documentación viva;
4. marcar expresamente las notas históricas;
5. no copiar dumps completos dentro de `admin_docs`;
6. registrar la fecha y la fuente de cualquier recuento de tablas.

La referencia de esquema actual está fechada. Si producción cambia, debe regenerarse [DATABASE_SCHEMA.md](./DATABASE_SCHEMA.md) desde un snapshot nuevo.
