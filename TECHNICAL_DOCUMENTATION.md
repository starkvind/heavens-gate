# Technical Documentation - HeavensGate

## 1. Proposito

Este documento explica HeavensGate desde el punto de vista de uso real:

- como arrancar una instancia nueva;
- como preparar la base de datos de forma segura;
- como entrar al backend y mantener contenido;
- como dar de alta personajes y dejar sus fichas completas;
- como funcionan las secciones publicas que dependen de esos datos.

Referencias de trabajo actuales:

- `dump-u807926597_hg-202603282141.sql`
- `app/tools/install_schema_from_dump.php`
- `app/helpers/db_connection.php`
- `.htaccess`
- `index.php`
- `app/bootstrap/body_work.php`
- `TELEGRAM_BOT_BACKEND_GUIDE.md`

Este documento sustituye la version antigua basada en un dump anterior. El esquema vigente tiene 87 tablas:

- 33 tablas `dim_*`
- 27 tablas `fact_*`
- 27 tablas `bridge_*`

## 2. Arquitectura general

HeavensGate es una aplicacion PHP clasica con un unico front controller.

Flujo de peticion:

1. Apache recibe la URL y aplica `.htaccess`.
2. La ruta amigable se reescribe a `index.php?p=...`.
3. `index.php` abre la conexion MySQL y carga `app/bootstrap/body_work.php`.
4. `body_work.php` decide que controlador incluir segun `p`.
5. El controlador consulta la BDD y renderiza la pagina.
6. Si la ruta es bare o AJAX, se devuelve solo el contenido. Si no, `index.php` envuelve el resultado con layout completo.

Piezas clave:

- `index.php`: entrada unica publica.
- `.htaccess`: rutas amigables, redirects legacy, alias `/img` y `/sounds`, fallback 404.
- `app/bootstrap/body_work.php`: mapa real entre `p=` y controlador.
- `app/controllers/*`: logica por dominio.
- `app/controllers/admin/admin_main.php`: entrada administrativa en `/talim`.
- `app/helpers/db_connection.php`: conexion MySQL basada en `config.env`.

## 3. Arranque de una instancia nueva

### 3.1 Requisitos minimos

- Apache o servidor compatible con `.htaccess`
- PHP con `mysqli` y `openssl`
- MariaDB/MySQL con `utf8mb4`
- un `config.env` valido

Variables esperadas en `config.env`:

- `MYSQL_HOST`
- `MYSQL_USER`
- `MYSQL_PWD`
- `MYSQL_BDD`
- `ENCRYPTION_KEY`

`ENCRYPTION_KEY` es obligatoria para usar el login admin actual, porque `/talim` lee `rel_pwd` desde `dim_web_configuration` y lo descifra en runtime.

Ubicaciones aceptadas actualmente para `config.env`:

- directorio padre del repositorio;
- raiz del repositorio;
- fallback legacy bajo `app/`.

Recomendacion:

- guardar `config.env` fuera del document root siempre que sea posible.

### 3.2 Instalacion del esquema

Se ha creado un instalador CLI para dejar la estructura lista incluso si la web todavia no esta configurada:

- `app/tools/install_schema_from_dump.php`

Uso tipico:

```bash
php app/tools/install_schema_from_dump.php --host=127.0.0.1 --user=usuario --password=secreto --database=hg
```

Si existe `config.env`, el script intenta reutilizarlo. Si ademas quieres dejar el backend operativo desde el principio:

```bash
php app/tools/install_schema_from_dump.php --database=hg --admin-password="cambia-esto"
```

Que hace el script:

- crea la base de datos si no existe;
- crea las 87 tablas del dump;
- recrea las vistas `vw_sim_characters`, `vw_sim_forms` y `vw_sim_items`;
- crea valores seguros en `dim_web_configuration`;
- no importa por defecto la password admin de produccion.

Que no hace:

- no restaura el contenido editorial del dump;
- no puebla cronicas, realidades, jugadores, personajes, documentos o mapas;
- no copia secretos de produccion.

## 4. Configuracion sensible: `dim_web_configuration`

La tabla sensible del sistema es `dim_web_configuration`. En conversaciones antiguas puede aparecer como `dim_web_config`, pero el nombre real del esquema actual es `dim_web_configuration`.

Estructura:

```sql
CREATE TABLE `dim_web_configuration` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `config_name` varchar(255) NOT NULL,
  `config_value` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
);
```

Claves observadas en el dump actual:

- `rel_pwd`
- `error_reporting`
- `exclude_chronicles`
- `combat_simulator_ip_limit_enabled`
- `combat_simulator_ip_limit_max_attempts_per_hour`
- `combat_simulator_ip_limit_max_attempts_per_day`
- `combat_simulator_rubberbanding_max_bonus_dice`
- `combat_simulator_rubberbanding_failures_per_bonus`

Regla importante:

- `rel_pwd` es un valor sensible y no debe copiarse desde produccion a una instalacion nueva.

Comportamiento recomendado:

- crear siempre la tabla;
- sembrar solo configuracion no sensible;
- generar `rel_pwd` solo si el admin lo decide, usando la `ENCRYPTION_KEY` del entorno.

Consumidores principales:

- `app/bootstrap/error_reporting.php`
- `app/modules/combat_simulator/script_rate_limit.php`
- `app/modules/combat_simulator/battle_turns.php`
- `app/controllers/admin/admin_get_pwd.php`

Hardening asociado ya aplicado:

- la documentacion tecnica se bloquea por `.htaccess`;
- el directorio `app/` se bloquea por acceso HTTP directo;
- el runtime de conexion ya no muestra errores crudos de MariaDB al usuario final.

## 5. Como usar HeavensGate

HeavensGate se usa en dos capas:

- capa publica: consulta de lore, fichas, cronologia, sistemas, mapas y utilidades;
- capa administrativa: edicion y mantenimiento desde `/talim`.

### 5.1 Capa publica

Dominios principales:

- `/` y `/home`: portada de bienvenida y orientacion
- `/news`: noticias del proyecto
- `/status`: estado general y contadores
- `/timeline`: cronologia y eventos
- `/seasons`: portada del archivo narrativo
- `/seasons/complete`, `/seasons/interludes`, `/seasons/personal-stories`, `/seasons/specials`: accesos por tipo de temporada
- `/chapters`: tabla global de episodios
- `/chapters/...`: ficha de episodio concreta
- `/characters`, `/organizations`, `/groups`, `/players`: universo de personajes
- `/documents`, `/inventory`, `/rules`, `/systems`, `/powers`: enciclopedia mecanica
- `/maps`: geografia
- `/music`: banda sonora
- `/tools/*`: utilidades tecnicas y de juego

### 5.2 Capa administrativa

La entrada es:

- `/talim`

Desde ahi se cargan modulos especializados con `?s=...`. Los mas importantes para el trabajo editorial diario son:

- `admin_characters`
- `admin_groups`
- `admin_relations`
- `admin_docs`
- `admin_powers`
- `admin_items`
- `admin_traits`
- `admin_systems`
- `admin_systems_resources`
- `admin_seasons`
- `admin_chapters`
- `admin_timelines`
- `admin_parties`
- `admin_pois`
- `admin_avatar_mass`
- `admin_characters_worlds`
- `admin_characters_clone`
- `admin_character_deaths`

## 6. Orden recomendado para poblar una instalacion vacia

Si la base de datos esta recien creada y no se ha restaurado el contenido del dump, conviene cargar los datos en este orden:

1. Catalogos base:
   - `dim_systems`
   - `dim_character_status`
   - `dim_character_types`
   - `dim_bibliographies`
   - `dim_doc_categories`
   - `dim_item_types`
   - `dim_timeline_events_types`
   - `dim_map_categories`
   - `dim_totem_types`
   - `dim_gift_types`
   - `dim_rite_types`
   - `dim_discipline_types`

2. Estructura de juego:
   - `dim_forms`
   - `dim_breeds`
   - `dim_auspices`
   - `dim_tribes`
   - `dim_systems_resources`
   - `fact_trait_sets`
   - `fact_misc_systems`

3. Catalogos narrativos:
   - `dim_chronicles`
   - `dim_realities`
   - `dim_players`
   - `dim_organizations`
   - `dim_groups`
   - `dim_parties`

4. Biblioteca jugable:
   - `dim_traits`
   - `dim_merits_flaws`
   - `dim_archetypes`
   - `fact_gifts`
   - `fact_rites`
   - `dim_totems`
   - `fact_discipline_powers`
   - `fact_items`
   - `fact_docs`

5. Narrativa episodica:
   - `dim_seasons`
   - `dim_chapters`
   - `fact_timeline_events`

6. Personajes:
   - `fact_characters`
   - todas sus tablas `bridge_characters_*`

7. Capas derivadas:
   - relaciones
   - links
   - participaciones en capitulos
   - eventos de timeline
   - parties
   - mapas
   - soundtrack

Observacion importante:

- actualmente no hay un CRUD tan evidente para `dim_players`, `dim_chronicles` y `dim_realities` como para otros modulos;
- en una instancia totalmente vacia conviene sembrarlos por SQL o desde una migracion/seed controlada antes de empezar con personajes.

## 7. Flujo recomendado para dar de alta un personaje

Esta es la parte mas importante del uso de HeavensGate. Un personaje no esta realmente "listo" cuando solo existe en `fact_characters`; necesita varias capas derivadas para comportarse bien en toda la web.

### 7.1 Alta base

Modulo principal:

- `/talim?s=admin_characters`

Tabla central:

- `fact_characters`

Datos que conviene definir desde el principio:

- nombre
- alias
- `pretty_id`
- avatar o `image_url`
- tipo de personaje
- estado
- cronica
- realidad
- jugador
- sistema
- raza
- auspicio
- tribu
- totem
- texto de descripcion o biografia

Si alguno de esos catalogos no existe todavia, la ficha quedara a medias y otras secciones de la web no podran clasificar bien al personaje.

### 7.2 Visibilidad publica

Para que un personaje sea navegable y aparezca bien en la capa publica, conviene revisar:

- que tenga `pretty_id`;
- que tenga un `status_id` valido;
- que no quede asociado a una cronica excluida por configuracion;
- que el slug no choque con otros personajes;
- que la imagen, si existe, apunte a `/public/img/characters`.

Notas practicas:

- `pretty_id` se usa para URLs publicas;
- internamente la web sigue trabajando con `id`;
- si faltan slugs, se puede usar `app/tools/generate_pretty_ids.php`.

### 7.3 Afiliacion narrativa

Un personaje suele necesitar dos capas de afiliacion:

- organizacion: `bridge_characters_organizations`
- grupo o manada: `bridge_characters_groups`

Tablas implicadas:

- `dim_organizations`
- `dim_groups`
- `bridge_organizations_groups`

Impacto funcional:

- se muestra en la ficha publica;
- afecta a `/chronicles`;
- alimenta `/organizations`, `/groups` y los mapas relacionales.

### 7.4 Cronica y realidad

Modulo de apoyo:

- `/talim?s=admin_characters_worlds`

Campos implicados:

- `fact_characters.chronicle_id`
- `fact_characters.reality_id`

Uso:

- reasignacion masiva de personajes;
- saneado rapido cuando cambian de cronica o mundo;
- correccion de consistencia en lotes.

### 7.5 Rasgos y recursos

Sin esta capa la ficha mecanica queda incompleta.

Tablas clave:

- `bridge_characters_traits`
- `dim_traits`
- `bridge_characters_system_resources`
- `dim_systems_resources`
- `fact_trait_sets`

Impacto:

- ficha publica del personaje;
- tirador de dados `/tools/dice`;
- vistas mecanicas del simulador;
- presentacion de rasgos agrupados por sistema.

Flujo recomendado:

1. crear o revisar rasgos en `admin_traits`;
2. crear recursos de sistema en `admin_systems_resources`;
3. asegurar que el personaje esta asociado a un sistema valido;
4. cargar sus valores en las tablas puente.

### 7.6 Poderes

Modulo:

- `/talim?s=admin_powers`

Catalogos:

- `fact_gifts`
- `fact_rites`
- `dim_totems`
- `fact_discipline_powers`

Asignacion al personaje:

- `bridge_characters_powers`
- `fact_characters.totem_id`

Impacto:

- ficha del personaje;
- secciones `/powers/*`;
- integridad mecanica del sistema.

### 7.7 Inventario

Modulo:

- `/talim?s=admin_items`

Tablas:

- `fact_items`
- `bridge_characters_items`

Impacto:

- ficha del personaje;
- `/inventory`;
- simulador de combate cuando los objetos tienen uso mecanico.

### 7.8 Meritos, defectos y arquetipos

Tablas:

- `dim_merits_flaws`
- `bridge_characters_merits_flaws`
- `dim_archetypes`

Impacto:

- profundidad de ficha;
- seccion de reglas;
- coherencia de conceptos y builds.

### 7.9 Documentacion y enlaces

Modulos:

- `/talim?s=admin_docs`
- `/talim?s=admin_external_links`
- `/talim?s=admin_character_links`
- `/talim?s=admin_doc_links`

Tablas:

- `fact_docs`
- `bridge_characters_docs`
- `fact_external_links`
- `bridge_characters_external_links`

Uso:

- relacionar a cada personaje con documentos de lore;
- enlazar fuentes externas;
- enriquecer la ficha para bot, wiki y lectura publica.

### 7.10 Relaciones entre personajes

Modulo:

- `/talim?s=admin_relations`

Tabla:

- `bridge_characters_relations`

Uso:

- red de aliados, rivales, mentores y vinculos;
- alimenta mapas relacionales;
- da contexto a biografias y cronologia.

### 7.11 Participacion en temporadas y capitulos

Modulos:

- `/talim?s=admin_seasons`
- `/talim?s=admin_chapters`

Tablas:

- `dim_seasons`
- `dim_chapters`
- `bridge_chapters_characters`

Uso:

- indicar en que capitulos aparece el personaje;
- calcular protagonismo y asistencia;
- alimentar `/seasons`, `/chapters/...` y analitica de asistencia.

### 7.12 Timeline, nacimiento y muerte

Modulo:

- `/talim?s=admin_timelines`

Modulos complementarios:

- `/talim?s=admin_birthdays_quick`
- `/talim?s=admin_character_deaths`

Tablas:

- `fact_timeline_events`
- `dim_timeline_events_types`
- `bridge_timeline_events_characters`
- `bridge_timeline_events_chapters`
- `bridge_timeline_events_chronicles`
- `bridge_timeline_events_realities`
- `fact_characters_deaths`

Reglas actuales:

- el nacimiento se trata como evento de timeline;
- la muerte debe quedar sincronizada con `fact_characters_deaths` y con el evento de timeline asociado;
- la timeline es ya el origen de verdad para cronologia publica.

### 7.13 Party tracker

Modulo:

- `/talim?s=admin_parties`

Tablas:

- `dim_parties`
- `fact_party_members`
- `fact_party_members_changes`

Uso:

- formar grupos activos;
- registrar HP, rabia, gnosis, glamour, mana, sangre o voluntad;
- mantener historico de cambios.

### 7.14 Operaciones masivas

Modulos:

- `/talim?s=admin_avatar_mass`
- `/talim?s=admin_characters_clone`
- `/talim?s=admin_characters_worlds`

Uso:

- cargar o corregir avatares en lote;
- clonar personajes entre cronicas o realidades;
- reasignar mundo/cronica en bloque.

## 8. Que necesita un personaje para considerarse "listo"

Checklist practico:

1. Alta base en `fact_characters`
2. `pretty_id` unico
3. Estado valido
4. Cronica y realidad asignadas
5. Sistema y taxonomia mecanica resueltos
6. Organizacion o grupo, si aplica
7. Rasgos cargados
8. Recursos de sistema cargados
9. Poderes y totem, si aplica
10. Inventario, si aplica
11. Meritos y defectos, si aplica
12. Documentos y links, si aplica
13. Participacion en capitulos
14. Eventos de timeline relevantes
15. Relaciones con otros personajes

Si solo se cumple el punto 1, el personaje existe, pero no esta realmente integrado en HeavensGate.

## 9. Secciones publicas y de donde sacan valor

### 9.1 Personajes

Rutas:

- `/characters`
- `/characters/types`
- `/characters/worlds`
- `/characters/{slug}`

Dependen sobre todo de:

- `fact_characters`
- `dim_character_types`
- `dim_realities`
- `bridge_characters_groups`
- `bridge_characters_organizations`
- `bridge_characters_traits`
- `bridge_characters_docs`
- `bridge_characters_relations`
- `bridge_timeline_events_characters`

### 9.2 Cronologia

Rutas:

- `/timeline`
- `/timeline/event/{slug}`

Dependen de:

- `fact_timeline_events`
- `dim_timeline_events_types`
- bridges a personajes, capitulos, cronicas y realidades

### 9.3 Temporadas y capitulos

Rutas:

- `/seasons`
- `/seasons/{slug}`
- `/seasons/analysis`
- `/chapters/{slug}`

Dependen de:

- `dim_seasons`
- `dim_chapters`
- `bridge_chapters_characters`
- `bridge_timeline_events_chapters`

### 9.4 Reglas, sistemas, poderes y objetos

Rutas:

- `/systems`
- `/rules/*`
- `/powers/*`
- `/documents/*`
- `/inventory/*`

Dependen de:

- `dim_systems`
- `dim_forms`
- `dim_breeds`
- `dim_auspices`
- `dim_tribes`
- `fact_misc_systems`
- `dim_traits`
- `dim_merits_flaws`
- `dim_archetypes`
- `fact_combat_maneuvers`
- `fact_gifts`
- `fact_rites`
- `dim_totems`
- `fact_discipline_powers`
- `fact_items`
- `fact_docs`

### 9.5 Mapas

Rutas:

- `/maps`
- `/maps/poi/{slug}`
- `/maps/api`

Dependen de:

- `dim_maps`
- `dim_map_categories`
- `fact_map_pois`
- `fact_map_areas`

### 9.6 Herramientas

Rutas relevantes:

- `/tools/dice`
- `/tools/forum-topic-viewer`
- `/tools/combat-simulator`
- `/ajax/tooltip`
- `/ajax/mentions`

Dependen de fichas y catalogos bien preparados. Si personajes, rasgos o recursos no estan consistentes, estas utilidades se degradan rapido.

## 10. Modelo de datos resumido

### 10.1 Hub principal de personajes

`fact_characters` es la tabla central de HeavensGate.

Se relaciona con:

- `dim_realities`
- `dim_systems`
- `dim_character_status`
- `dim_players`
- `dim_chronicles`
- `dim_breeds`
- `dim_auspices`
- `dim_tribes`
- `dim_totems`
- `dim_character_types`
- todas las `bridge_characters_*`

### 10.2 Hub principal de timeline

`fact_timeline_events` es el segundo eje del sistema.

Se relaciona con:

- `dim_timeline_events_types`
- `bridge_timeline_events_characters`
- `bridge_timeline_events_chapters`
- `bridge_timeline_events_chronicles`
- `bridge_timeline_events_realities`
- `bridge_timeline_links`
- `fact_characters_deaths`

### 10.3 Eje episodico

- `dim_seasons`
- `dim_chapters`
- `bridge_chapters_characters`

### 10.4 Eje mecanico

- `dim_systems`
- `dim_forms`
- `dim_breeds`
- `dim_auspices`
- `dim_tribes`
- `dim_systems_resources`
- `dim_traits`
- `fact_trait_sets`

## 11. Observaciones practicas para edicion y mantenimiento

- usar siempre `pretty_id` para construir URLs publicas;
- usar siempre `id` para relaciones internas;
- no restaurar `rel_pwd` desde dumps productivos;
- si se crea una instalacion vacia, sembrar primero catalogos antes de personajes;
- si un personaje "no sale", revisar: estado, slug, cronica, realidad y relaciones puente;
- si una pagina publica esta vacia, comprobar primero sus tablas puente, no solo la tabla maestra;
- para automatizacion o bots, la referencia mas exhaustiva por rutas y queries esta en `TELEGRAM_BOT_BACKEND_GUIDE.md`.

## 12. Seguridad de despliegue

Medidas ya presentes o recomendadas:

- `.htaccess` deniega acceso directo a:
  - `app/`
  - dumps SQL
  - notas de upgrade
  - documentacion markdown sensible
- `config.env` debe mantenerse fuera de la raiz servida si la infraestructura lo permite;
- si se despliega con Nginx, Caddy u otro servidor sin soporte `.htaccess`, hay que replicar manualmente esas reglas;
- no se deben publicar dumps productivos ni documentacion interna detallada en un host publico;
- las incidencias de conexion a BDD deben registrarse en logs del servidor, no mostrarse al cliente.

## 13. Checklist de cambios de esquema o contenido

Cuando se modifique el modelo o se amplie funcionalidad:

1. actualizar el dump o la migracion correspondiente;
2. revisar `body_work.php` si aparecen nuevas rutas;
3. revisar el CRUD admin afectado;
4. revisar la vista publica relacionada;
5. revisar `dim_web_configuration` si entra una nueva bandera de sistema;
6. actualizar este documento;
7. actualizar `TELEGRAM_BOT_BACKEND_GUIDE.md` si el cambio afecta a routing, tablas o queries.
