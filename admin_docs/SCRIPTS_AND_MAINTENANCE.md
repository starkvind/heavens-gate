# Scripts y mantenimiento

Última revisión: 2026-09-02.

Este documento describe las herramientas que **existen realmente** en el repositorio en esta fecha. No presupone instaladores o migradores retirados.

## Regla general

No hay actualmente un instalador canónico que reconstruya toda la base de datos desde el repositorio web. El repositorio contiene runtime, utilidades concretas y scripts editoriales, pero el esquema de producción completo se conserva como snapshot en el repositorio de continuidad.

Antes de ejecutar una herramienta que escriba en base de datos:

1. disponer de un backup reciente;
2. comprobar que `config.env` apunta al entorno correcto;
3. usar `--dry-run` cuando la herramienta lo soporte;
4. revisar el resultado con `/talim?s=admin_inspect_db` o con consultas de auditoría;
5. no ejecutar herramientas de migración histórica por intuición.

## Configuración compartida

`app/helpers/db_connection.php` busca `config.env`, por este orden:

1. directorio padre de la raíz del proyecto;
2. raíz del proyecto;
3. ubicación legacy bajo `app/`.

Claves obligatorias para conexión:

- `MYSQL_HOST`
- `MYSQL_USER`
- `MYSQL_PWD`
- `MYSQL_BDD`

## Herramientas CLI

### `app/tools/backfill_content_updates.php`

Puebla `fact_content_updates` a partir del contenido público más reciente.

Uso:

~~~bash
php app/tools/backfill_content_updates.php
php app/tools/backfill_content_updates.php 250
php app/tools/backfill_content_updates.php 100 --dry-run
~~~

El límite por defecto es 100 y el script acepta como máximo 1000 filas. `--dry-run` permite inspeccionar sin escribir.

No es una migración de esquema.

### `tools/seed_game_cards.php`

Wrapper CLI del seed del Archivo de Mnemógeno. Delega en `app/tools/seed_game_cards.php`.

Uso normal:

~~~bash
php tools/seed_game_cards.php
~~~

Regeneración destructiva del catálogo de cartas:

~~~bash
php tools/seed_game_cards.php --reset
~~~

`--reset` elimina las filas del catálogo `fact_game_card_collection` antes de regenerarlo. No borra el progreso local de los navegadores, pero puede cambiar IDs y dejar colecciones locales desalineadas; debe usarse de forma deliberada.

El seed también sincroniza las tablas de reglas del juego de cartas.

### `tools/scaffold_section.py`

Genera el esqueleto de una sección pública sencilla y cablea:

- `app/bootstrap/request_router.php`;
- `app/bootstrap/body_work.php`;
- opcionalmente un CSS en `assets/css`;
- opcionalmente una entrada del menú fallback.

Ejemplo seguro:

~~~bash
python tools/scaffold_section.py \
  --route-key codex_guide \
  --slug codex-guide \
  --title "Guía del códice" \
  --dry-run
~~~

Después del dry-run, repetir sin `--dry-run` si el plan es correcto.

No sirve para rutas de detalle con `pretty_id` ni para CRUD complejos. Véase [PUBLIC_SECTION_GUIDE.md](./PUBLIC_SECTION_GUIDE.md).

## Herramientas administrativas

Las herramientas internas de inspección y mantenimiento se ejecutan exclusivamente dentro del backend autenticado o mediante CLI. El repositorio público no documenta rutas privilegiadas concretas.

## Herramientas públicas enrutadas

Los ficheros bajo `app/` no son accesibles directamente: `.htaccess` bloquea `/app/...`. Cuando una herramienta es pública, debe pasar por el front controller.

Rutas vigentes:

| Ruta | Route key | Implementación |
|---|---|---|
| `/tools/crop` | `crop` | `app/tools/crop.html` |
| `/tools/forum-topic-viewer` | `forum_topic_viewer` | controlador público + `app/tools/forum_topic_viewer_tool.php` |
| `/tools/forum-avatar` | `forum_avatar_tool` | `app/controllers/tool/forum_avatar_builder.php` |

La existencia física de un fichero en `app/tools` **no implica** que tenga una URL pública.

### Operaciones de esquema

Las operaciones que cambian estructura o realizan migraciones destructivas no se exponen como rutas web. Deben ejecutarse mediante un flujo de mantenimiento controlado y fuera del runtime público.

## Herramientas auxiliares no enrutadas

`app/tools/forum_resumee_builder.html` existe como fichero auxiliar, pero no posee una ruta pública válida por sí mismo porque `/app` está bloqueado. Si aparece enlazado directamente desde alguna interfaz, ese enlace es un residuo de implementación y no debe documentarse como ruta funcional.

## SQL y reportes editoriales

### `sql/audit_gaia0_content.sql`

Consulta de **solo lectura** para medir huecos editoriales de Gaia0: biografías vacías, episodios sin sinopsis, eventos sin realidad, duplicados de `pretty_id` y puentes huérfanos.

No crea tablas, no migra el esquema y no debe confundirse con un instalador.

### `reports/gaia0_gap_analysis_20260901.md`

Informe derivado de la auditoría anterior. Es una fotografía editorial fechada, no documentación de runtime.

## Herramientas retiradas

La documentación antigua mencionaba herramientas que ya no existen en el repositorio actual, entre ellas:

- `app/tools/install_schema_from_dump.php`;
- `app/tools/schema_definition.php`;
- `app/tools/schema_initializer.php`;
- `app/controllers/admin/admin_schema_initializer.php`;
- `app/tools/generate_pretty_ids.php`;
- `app/controllers/admin/admin_map_kmz_import.php`.

No deben recrearse ni invocarse siguiendo documentación vieja. Si una tarea futura necesita esa capacidad, debe diseñarse contra el esquema vigente y no resucitar automáticamente el flujo anterior.

## Checklist de mantenimiento

Antes de tocar base de datos o scripts:

- confirmar entorno y rama;
- obtener backup/snapshot;
- identificar si la herramienta es lectura, escritura o destructiva;
- comprobar que la tabla y columnas existen en [DATABASE_SCHEMA.md](./DATABASE_SCHEMA.md);
- ejecutar dry-run cuando exista;
- revisar logs y respuesta;
- verificar rutas públicas a través de `request_router.php`;
- actualizar esta documentación cuando cambie el comportamiento operativo.

