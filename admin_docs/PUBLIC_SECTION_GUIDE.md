# Añadir una sección pública

Última revisión: 2026-09-17.

La web usa un front controller. Una página nueva no debe enlazarse directamente a un PHP bajo `app/`.

## Flujo actual

Una URL pública atraviesa:

`.htaccess -> index.php -> request_runtime.php -> path_matcher.php -> page_dispatch.php -> routes.php -> dispatch_policy.php / dispatcher.php -> controlador`

Las URLs históricas `?p=...` pasan por `app/routing/legacy_query.php` sólo para canonicalizarse hacia la URL moderna.

La traducción humana de los route keys existentes está en [ROUTE_DICTIONARY.md](./ROUTE_DICTIONARY.md).

## Alta simple con scaffold

Para una sección pública simple puede usarse:

~~~bash
python tools/scaffold_section.py \
  --route-key codex_guide \
  --slug codex-guide \
  --title "Guía del códice" \
  --dry-run
~~~

El scaffold crea el controlador y cablea:

- `app/routing/path_matcher.php`: URL canónica -> route key;
- `app/routing/routes.php`: route key -> controlador + sección.

Opcionalmente puede crear CSS y añadir una entrada al menú fallback. Si se genera CSS, el controlador lo registra mediante `hg_page_register_stylesheet()` para mantener la carga dentro de `<head>`.

El scaffold **no** crea rutas de detalle con `pretty_id`, CRUD complejos ni compatibilidad histórica `?p=...`.

## Alta manual vigente

Una sección pública debe tener propietarios claros:

1. `app/routing/path_matcher.php`: URL canónica -> route key;
2. `app/routing/routes.php`: route key -> controlador + sección;
3. controlador bajo el dominio correcto en `app/controllers/`;
4. menú/activos sólo si corresponde;
5. `app/mobile/mobile_routes.php` únicamente si necesita implementación móvil específica durante la compatibilidad `?view=mobile`;
6. `ROUTE_DICTIONARY.md`.

Si la sección sustituye un `?p=...` histórico, añadir conscientemente la canonicalización correspondiente en `app/routing/legacy_query.php`.

Las páginas normales no deben añadir lógica nueva a `app/http/page_dispatch.php`: ese fichero coordina normalización + dispatch compartido. La política de respuestas bare/fallback vive en `app/http/dispatch_policy.php`.

## Rutas con entidades

Para una entidad con slug:

- la URL pública debe usar `pretty_id`;
- los joins internos deben usar `id`;
- si cambia un slug ya público, valorar alias en `fact_pretty_id_aliases`;
- la compatibilidad legacy que necesite resolver IDs/slugs debe pasar por `app/routing/legacy_query.php` y los helpers de `app/helpers/pretty.php`;
- `path_matcher.php` debe seguir siendo independiente de MySQL.

No generar enlaces públicos con IDs numéricos salvo diseño explícito de esa ruta.

## Route keys

Los nombres históricos (`muestrabio`, `busk`, `temp`, `vermyd`, etc.) se conservan durante el refactor para no mezclar una migración nominal con cambios de arquitectura.

Para nuevas rutas, preferir nombres legibles en inglés o vocabulario de dominio claro. No introducir abreviaturas crípticas nuevas.

## Menú

El menú real puede venir de `dim_menu_items`. La entrada fallback en `app/partials/main_menu.php` no sustituye necesariamente el alta editorial en base de datos.

Después de crear una sección, comprobar:

- URL canónica;
- redirección desde `?p=...` si procede;
- desktop;
- móvil/fallback;
- título y metadatos;
- 404 para slugs inexistentes;
- `view=mobile` mientras exista compatibilidad;
- actualización del diccionario de rutas.

## APIs, embeds y respuestas bare

No copiar el patrón de una página HTML normal para una API o embed. La política de respuestas bare se controla en `app/http/dispatch_policy.php`; `app/http/dispatcher.php` aplica la resolución.

Si se añade una respuesta bare, documentarla expresamente en `ROUTE_DICTIONARY.md` y añadir caracterización/CI cuando sea razonable.

## Seguridad

`.htaccess` bloquea `/app` y `/admin_docs`. No se debe desactivar ese bloqueo para “hacer funcionar” un controlador.

Si una herramienta necesita ser accesible desde navegador, debe tener una ruta explícita en el front controller.
