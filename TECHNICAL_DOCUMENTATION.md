# Technical Documentation - Heaven's Gate 5.0

## 1. Proposito

Este documento describe el estado tecnico real del proyecto despues de la actualizacion 5.0. El objetivo es que cualquier mantenimiento futuro parta del codigo y del dump vigentes, no de supuestos heredados.

Se ha contrastado con:

- `dump-u807926597_hg-202604031114.sql`
- `app/tools/install_schema_from_dump.php`
- `.htaccess`
- `index.php`
- `app/bootstrap/body_work.php`
- `app/helpers/db_connection.php`
- `app/helpers/pretty.php`
- `app/helpers/admin_auth.php`
- `app/helpers/public_response.php`
- `app/helpers/runtime_response.php`

Snapshot actual del esquema:

- 87 tablas
- 33 tablas `dim_*`
- 27 tablas `fact_*`
- 27 tablas `bridge_*`
- 3 vistas del simulador:
  - `vw_sim_characters`
  - `vw_sim_forms`
  - `vw_sim_items`

## 2. Arquitectura general

HeavensGate sigue siendo una aplicacion PHP clasica con un unico front controller.

Flujo de una request publica:

1. El servidor recibe la URL y aplica `.htaccess`.
2. La ruta amigable se reescribe a `index.php?p=...`.
3. `index.php` carga bootstrap, conexion y layout base.
4. `app/bootstrap/body_work.php` resuelve `p`, normaliza slugs y decide el controlador fisico.
5. El controlador consulta la BDD y renderiza HTML completo o salida bare.
6. Si la ruta es bare/AJAX, se devuelve solo el contenido. Si no, `index.php` envuelve la salida con el layout comun.

Piezas clave:

- `index.php`: front controller publico
- `.htaccess`: routing, redirecciones legacy, bloqueos de seguridad y fallback
- `app/bootstrap/body_work.php`: dispatch real entre `p=` y fichero fisico
- `app/bootstrap/head_work.php`: ensamblado del `head`
- `app/helpers/db_connection.php`: conexion robusta e idempotente
- `app/helpers/pretty.php`: resolucion y normalizacion de `pretty_id`

## 3. Configuracion y arranque

### 3.1 `config.env`

Variables esperadas:

- `MYSQL_HOST`
- `MYSQL_USER`
- `MYSQL_PWD`
- `MYSQL_BDD`
- `ENCRYPTION_KEY`

Resolucion actual de `config.env`:

1. directorio padre del repositorio
2. raiz del repositorio
3. ubicacion legacy bajo `app/`

Recomendacion:

- mantener `config.env` fuera del document root siempre que sea posible.

### 3.2 Conexion y fallos de arranque

`app/helpers/db_connection.php` ya no intenta reconectar sin control dentro de la misma request. Si `$link` ya existe y sigue vivo, reutiliza la conexion.

Si falta configuracion o falla la conexion:

- se registra el error con `hg_runtime_log_error()`
- se muestra una respuesta controlada via `app/helpers/runtime_response.php`
- no se exponen errores crudos de MariaDB al usuario final

### 3.3 Password admin

El valor `rel_pwd` vive en `dim_web_configuration`.

Politica actual:

- instalaciones nuevas pueden sembrarlo con hash usando `--admin-password`
- `admin_login.php` soporta hash moderno y compatibilidad con valores legacy
- en login correcto se puede migrar automaticamente a `password_hash()`
- `ENCRYPTION_KEY` se conserva por compatibilidad con secretos antiguos

## 4. Routing y puntos de entrada

### 4.1 Publico

Rutas canonicas importantes:

- `/timeline` -> `index.php?p=timeline` -> `app/controllers/main/events_main.php`
- `/timeline/event/{slug}` -> `index.php?p=timeline_event&t={slug}` -> `app/controllers/main/events_page.php`
- `/players/{slug}` -> `index.php?p=seeplayer&b={slug}` -> `app/controllers/playr/playr_page.php`
- `/chronicles/{slug}` -> `index.php?p=chronicles&t={slug}`
- `/music` -> `index.php?p=ost` -> `app/controllers/ost/bso_main.php`
- `/maps/api` -> `index.php?p=maps_api`
- `/ajax/tooltip` -> `index.php?p=tooltip`
- `/ajax/mentions` -> `index.php?p=mentions`
- `/ajax/epis` -> `index.php?p=mentions&type=episode`

### 4.2 Backend admin

Entrada administrativa:

- `/talim`

Router admin:

- `app/controllers/admin/admin_main.php`

Ese controlador ya integra los modulos nuevos de 5.0:

- `admin_chronicles`
- `admin_realities`
- `admin_players`
- `admin_bso`
- `admin_bso_link`
- `admin_birthdays_quick`

### 4.3 Rutas bare o especialmente utiles para automatizacion

Segun `body_work.php`, estas rutas se sirven sin layout completo:

- `forum_message`
- `forum_diceroll`
- `forum_item`
- `keygen`
- `crop`
- `tooltip`
- `mentions`
- `maps_api`
- `chronicle_image`

Para integraciones internas esto es relevante porque son los puntos mas cercanos a una respuesta utilitaria o parcial.

## 5. Modelo de datos actual

### 5.1 Convenciones

- `dim_*`: catalogos, taxonomias y entidades maestras
- `fact_*`: contenido principal o hechos narrativos/operativos
- `bridge_*`: relaciones N:M y enlaces derivados

La navegacion publica se apoya en `pretty_id`, pero las relaciones internas siguen usando `id`.

### 5.2 Hubs principales

#### Personajes

Tabla central:

- `fact_characters`

Relaciona, entre otros, con:

- `dim_chronicles`
- `dim_realities`
- `dim_players`
- `dim_systems`
- `dim_breeds`
- `dim_auspices`
- `dim_tribes`
- `dim_character_status`

La ficha publica y muchas vistas derivadas dependen tambien de varias tablas `bridge_characters_*`.

#### Cronicas

Tabla:

- `dim_chronicles`

En el dump actual tiene:

- `pretty_id` unico y no nulo
- `sort_order`
- `name`
- `image_url`
- `description`
- timestamps

Dependencias funcionales relevantes:

- `fact_characters.chronicle_id`
- `bridge_timeline_events_chronicles.chronicle_id`
- `dim_seasons.chronicle_id`

#### Realidades

Tabla:

- `dim_realities`

En el dump actual tiene:

- `pretty_id` unico y nullable
- `name`
- `description`
- `is_active`
- timestamps

Dependencias funcionales:

- `fact_characters.reality_id`
- `bridge_timeline_events_realities.reality_id`

#### Jugadores

Tabla:

- `dim_players`

En el dump actual tiene:

- `pretty_id` unico y nullable
- `name`
- `surname`
- `show_in_catalog`
- `picture`
- `description`
- timestamps

Dependencia principal:

- `fact_characters.player_id`

#### Timeline

Tablas principales:

- `fact_timeline_events`
- `dim_timeline_events_types`
- `bridge_timeline_events_characters`
- `bridge_timeline_events_chapters`
- `bridge_timeline_events_chronicles`
- `bridge_timeline_events_realities`

Campos importantes observados en `fact_timeline_events`:

- `pretty_id`
- `event_date`
- `date_precision`
- `date_note`
- `sort_date`
- `title`
- `description`
- `event_type_id`
- `is_active`
- `location`
- `source`
- `timeline` como campo legacy de apoyo

Importante para no documentar mal el estado 5.0:

- los puentes actuales usan `event_id`, no `timeline_event_id`
- el dump vigente no debe tratarse como si mantuviera una columna `kind` operativa en `fact_timeline_events`

#### Soundtrack / BSO

Tablas:

- `dim_soundtracks`
- `bridge_soundtrack_links`

En el dump actual:

- `dim_soundtracks` usa `title`, `artist`, `youtube_url`, `context_title`, `added_at`
- no debe asumirse `pretty_id` en `dim_soundtracks`
- `bridge_soundtrack_links` usa:
  - `soundtrack_id`
  - `object_type` con valores `personaje`, `temporada`, `episodio`
  - `object_id`

El esquema actual ya incorpora endurecimiento seguro en este puente:

- `UNIQUE (soundtrack_id, object_type, object_id)`
- indice `idx_bsl_object_lookup (object_type, object_id)`

### 5.3 Politica actual de `pretty_id`

Reglas editoriales consolidadas en 5.0:

- `dim_chronicles`: se genera y persiste a partir del nombre
- `dim_realities`: se trata como slug manual/editorial en admin
- `dim_players`: puede ser manual; en alta se admite generacion por defecto si hace falta
- en enlaces publicos debe preferirse siempre `pretty_id`
- en consultas internas o joins debe seguir usandose `id`

El helper base para esta logica es `app/helpers/pretty.php`.

## 6. Capa publica

### 6.1 Timeline 5.0

Controladores:

- `app/controllers/main/events_main.php`
- `app/controllers/main/events_page.php`

Comportamiento actual:

- listado principal con filtro por tipo, cronica y busqueda
- ECharts en frontend
- DataTables cuando esta disponible
- detalle de evento con personajes, capitulos, cronicas y realidades relacionadas
- el filtro/section de realidades esta preparado pero oculto por defecto para controlar spoilers

### 6.2 Jugadores

Controladores:

- `app/controllers/playr/playr_list.php`
- `app/controllers/playr/playr_page.php`

Comportamiento actual:

- el listado respeta `show_in_catalog`
- el filtrado tambien tiene en cuenta cronicas excluidas desde configuracion web

### 6.3 Soundtrack publico

Controlador:

- `app/controllers/ost/bso_main.php`

Mejoras relevantes:

- normalizacion de URLs de YouTube
- soporte `youtu.be`
- embed via `youtube-nocookie`
- degradacion segura si el enlace no es valido

### 6.4 Respuestas publicas seguras

Helper comun:

- `app/helpers/public_response.php`

Patron ya extendido por muchas secciones publicas:

- log del error real en servidor
- mensaje limpio al usuario
- `404` publico controlado cuando el recurso no existe
- sin `die()` ni texto SQL crudo en pagina

## 7. Backend admin

### 7.1 Seguridad y contrato AJAX

Helpers base:

- `app/helpers/admin_auth.php`
- `app/helpers/admin_ajax.php`

Capacidades clave:

- sesion admin con cookie endurecida
- `session.use_strict_mode`
- regeneracion de `session_id` al autenticar
- logout centralizado
- CSRF por modulo
- contrato JSON comun con claves `ok`, `message`, `msg`, `data`, `errors`, `meta`
- `403` JSON cuando una llamada AJAX no esta autorizada

### 7.2 Modulos 5.0 relevantes

#### `admin_chronicles`

- CRUD completo de cronicas
- subida de imagen a `public/img/chronicles`
- guardas de borrado por personajes, timeline y temporadas
- persistencia segura de `pretty_id`

#### `admin_realities`

- CRUD completo de realidades
- `pretty_id` obligatorio y editorial
- soporte `is_active`
- guardas de borrado por personajes y timeline
- resumen visual de revision/auditoria

#### `admin_players`

- CRUD completo de jugadores
- soporte `show_in_catalog`
- soporte `picture`
- guardas de borrado por personajes
- `pretty_id` editable y visible en admin

#### `admin_birthdays_quick`

- edicion rapida de `birthdate_text`
- crea o actualiza eventos de nacimiento en `fact_timeline_events`
- asegura el puente en `bridge_timeline_events_characters`

#### `admin_bso`

- catalogo CRUD real de soundtracks
- normalizacion de YouTube
- visibilidad de usos por personajes, temporadas y episodios
- filtros de auditoria y estado

#### `admin_bso_link`

- gestor relacional de `bridge_soundtrack_links`
- alta y borrado de vinculos
- deteccion de duplicados y estados inconsistentes
- acceso rapido al destino publico cuando existe slug

### 7.3 Helpers de soporte creados o consolidados

- `app/helpers/admin_catalog_utils.php`
- `app/helpers/admin_uploads.php`
- `app/helpers/pretty.php`

## 8. Provisioning y mantenimiento

### 8.1 Instalacion desde dump

Script:

- `app/tools/install_schema_from_dump.php`

Puntos importantes:

- solo CLI
- si no se pasa `--dump`, intenta usar el `dump-*.sql` mas reciente de la raiz
- crea BDD, tablas y vistas finales
- si se pide, siembra `rel_pwd` con hash
- evita restaurar secretos de produccion por defecto

Uso base:

```bash
php app/tools/install_schema_from_dump.php --database=hg
```

Dry run:

```bash
php app/tools/install_schema_from_dump.php --database=hg --dry-run=1
```

### 8.2 Endurecimiento acotado de esquema

Script:

- `app/tools/phase7_schema_hardening_20260403.php`

Objetivo:

- reforzar la integridad de `bridge_soundtrack_links`
- aplicar un cambio seguro y acotado
- emitir plan SQL incluso cuando no hay conexion disponible

### 8.3 Herramientas de soporte revisadas en esta tanda

Tambien se han alineado con el runtime actual:

- `app/tools/generate_pretty_ids.php`
- `app/tools/inspect_db.php`
- `app/tools/forum_topic_viewer_tool.php`

## 9. Seguridad y hardening operativo

Blindajes ya activos:

- `.htaccess` bloquea acceso HTTP directo a `app/`, dumps, docs markdown y secretos
- `db_connection.php` falla con pantalla controlada, no con warning crudo
- `public_response.php` y `runtime_response.php` centralizan el tratamiento de errores visibles
- muchas rutas publicas y admin dejaron de exponer mensajes SQL al usuario
- se han eliminado varios patrones de borrado destructivo por GET en admin

Si se despliega en un servidor que no respeta `.htaccess`, esos bloqueos deben replicarse manualmente.

## 10. Checklist rapido para futuras tandas

Antes de documentar o tocar datos, asumir siempre:

1. el dump de referencia actual es `dump-u807926597_hg-202604031114.sql`
2. el routing real sale de `.htaccess` y `app/bootstrap/body_work.php`
3. la politica de `pretty_id` ya no es uniforme para todas las tablas
4. `dim_soundtracks` y `bridge_soundtrack_links` tienen reglas especiales respecto a slugs e integridad
5. cualquier guia antigua que cite rutas de timeline bajo `app/controllers/timeline/` esta desfasada
