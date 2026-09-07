# Añadir una sección pública

Última revisión: 2026-09-07.

La web usa un front controller. Una página nueva no debe enlazarse directamente a un PHP bajo `app/`.

## Flujo actual

Una URL pública atraviesa:

`.htaccess -> index.php -> request_runtime.php -> path_matcher.php -> routes.php -> dispatcher.php -> controlador`

La traducción humana de los route keys existentes está en [ROUTE_DICTIONARY.md](./ROUTE_DICTIONARY.md).

## Alta manual vigente

Durante el refactor PHP, una sección pública simple debe darse de alta conscientemente en:

1. `app/routing/path_matcher.php`: URL canónica -> route key;
2. `app/routing/routes.php`: route key -> controlador + sección;
3. controlador bajo el dominio correcto en `app/controllers/`;
4. menú/activos sólo si corresponde;
5. `app/mobile/mobile_routes.php` únicamente si necesita implementación móvil específica durante la compatibilidad `?view=mobile`;
6. `ROUTE_DICTIONARY.md`.

Si la sección sustituye un `?p=...` histórico, revisar también la canonicalización legacy en `app/bootstrap/request_router.php`.

## Scaffold temporalmente congelado

`tools/scaffold_section.py` fue escrito para la arquitectura anterior y todavía intenta modificar directamente:

- `app/bootstrap/request_router.php`;
- `app/bootstrap/body_work.php`.

Tras la separación de routing/dispatch de Phase 1, **no debe usarse para crear secciones hasta que sea adaptado**. Un `--dry-run` tampoco convierte su plan en correcto: sigue describiendo destinos arquitectónicos antiguos.

Esto es deuda técnica conocida del refactor, no una invitación a devolver rutas a esos ficheros.

## Rutas con entidades

Para una entidad con slug:

- la URL pública debe usar `pretty_id`;
- los joins internos deben usar `id`;
- si cambia un slug ya público, valorar alias en `fact_pretty_id_aliases`;
- la compatibilidad legacy que necesite resolver IDs/slugs debe pasar por `app/helpers/pretty.php`;
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

No copiar el patrón de una página HTML normal para una API o embed. Las respuestas bare se controlan en `app/http/dispatcher.php`.

Si se añade una respuesta bare, documentarla expresamente en `ROUTE_DICTIONARY.md` y añadir caracterización/CI cuando sea razonable.

## Seguridad

`.htaccess` bloquea `/app` y `/admin_docs`. No se debe desactivar ese bloqueo para “hacer funcionar” un controlador.

Si una herramienta necesita ser accesible desde navegador, debe tener una ruta explícita en el front controller.