# Añadir una sección pública

Última revisión: 2026-09-02.

La web usa un front controller. Una página nueva no debe enlazarse directamente a un PHP bajo `app/`.

## Flujo actual

Una URL pública atraviesa:

`.htaccess` → `index.php` → `app/bootstrap/request_router.php` → `app/bootstrap/body_work.php` → controlador.

`request_router.php` resuelve URLs canónicas y redirecciones legacy. `body_work.php` asigna el `route key` al controlador que renderiza la página.

## Opción recomendada para páginas simples

Existe `tools/scaffold_section.py`.

Primero ejecutar un dry-run:

~~~bash
python tools/scaffold_section.py \
  --route-key codex_guide \
  --slug codex-guide \
  --title "Guía del códice" \
  --dry-run
~~~

Si el plan es correcto:

~~~bash
python tools/scaffold_section.py \
  --route-key codex_guide \
  --slug codex-guide \
  --title "Guía del códice"
~~~

El script crea un controlador y añade la ruta al router y al dispatcher.

Opciones útiles:

- `--controller-group`: subdirectorio de `app/controllers`; por defecto `main`;
- `--controller-file`: nombre del fichero a crear;
- `--section-label`: etiqueta de sección usada por el layout;
- `--description`: descripción/meta de la página;
- `--css-file`: CSS bajo `assets/css`;
- `--create-css`: crea ese CSS;
- `--menu-label` + `--menu-block`: añade una entrada al menú fallback;
- `--dry-run`: no escribe cambios.

Bloques de menú soportados por el scaffold: `startMenu`, `bioMenu`, `archivoMenu`, `loreMenu`, `systemMenu`, `powersMenu` y `toolsMenu`.

## Límites del scaffold

No usarlo para:

- detalles con `pretty_id`;
- rutas con varios segmentos dinámicos;
- APIs;
- módulos administrativos;
- páginas que necesiten lógica de autorización;
- nuevas familias completas de entidades.

En esos casos se debe editar el router conscientemente y seguir el patrón de una sección equivalente ya existente.

## Rutas con entidades

Para una entidad con slug:

- la URL pública debe usar `pretty_id`;
- los joins internos deben usar `id`;
- si se cambia un slug que ya fue público, valorar un alias en `fact_pretty_id_aliases`;
- la resolución legacy debe pasar por `app/helpers/pretty.php`.

No generar enlaces públicos con IDs numéricos salvo que la ruta esté diseñada expresamente para ello.

## Menú

El menú real puede venir de `dim_menu_items`. La entrada fallback en `app/partials/main_menu.php` no sustituye necesariamente el alta editorial en base de datos.

Después de crear una sección, comprobar:

- URL canónica;
- redirección desde el antiguo `?p=...` si existía;
- menú desktop;
- menú móvil;
- título y metadatos;
- 404 para slugs inexistentes;
- comportamiento con `view=mobile`.

## Seguridad

`.htaccess` bloquea `/app` y `/admin_docs`. No se debe desactivar ese bloqueo para “hacer funcionar” un controlador.

Si una herramienta necesita ser accesible desde navegador, debe tener una ruta explícita en el front controller.

