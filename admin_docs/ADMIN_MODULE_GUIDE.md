# Guía para módulos de administración

Última revisión: 2026-09-02.

Los módulos del backend viven bajo `app/controllers/admin` y se despachan desde `app/controllers/admin/admin_main.php` mediante `/talim?s=<modulo>`.

## Contrato de seguridad

Toda mutación debe partir de los helpers administrativos existentes:

- `app/helpers/admin_auth.php` para sesión;
- `app/helpers/admin_ajax.php` para CSRF y respuestas AJAX.

Para peticiones que modifican datos:

- exigir sesión administrativa;
- validar CSRF;
- no aceptar que una llamada AJAX atraviese el módulo sin autorización;
- no exponer errores SQL completos al navegador.

El patrón habitual para respuestas AJAX usa:

- `ok`;
- `message` / `msg`;
- `data`;
- `errors`;
- `meta`.

No mezclar HTML del layout con una respuesta JSON.

## Registro del módulo

Un controlador nuevo no queda disponible solo por existir como fichero.

Debe añadirse a `app/controllers/admin/admin_main.php`:

1. en el despacho normal;
2. en el flujo AJAX si lo necesita;
3. en el menú administrativo si debe ser accesible desde la UI.

Antes de añadir un módulo, comprobar que no exista ya uno que cubra el mismo dominio.

## Frontend administrativo

Cuando el módulo sea asíncrono, reutilizar `assets/js/admin/admin-http.js` y el contrato común.

Buenas prácticas:

- filtros y búsqueda con debounce cuando proceda;
- CRUD sin recarga completa cuando el módulo ya sigue ese patrón;
- conservar filtros/paginación en querystring si aporta continuidad;
- volver a enlazar eventos después de renderizados parciales;
- mostrar mensajes de error legibles sin ocultar el fallo al administrador.

## Modelo de datos

No inferir la estructura desde el nombre del módulo. Consultar [DATABASE_SCHEMA.md](./DATABASE_SCHEMA.md) antes de escribir SQL.

Puntos especialmente sensibles:

- los eventos usan `event_id` en sus bridges;
- las realidades de temporadas y crónicas se derivan de eventos asociados, no de una columna de realidad propia;
- `fact_characters` conserva `chronicle_id` y `reality_id` directos;
- conviven bridges históricos y canónicos en algunas zonas de afiliaciones; comprobar el controlador antes de consolidarlos;
- `pretty_id` es identificador público, no FK interna.

## Uploads

Para módulos que suben imágenes, reutilizar `app/helpers/admin_uploads.php`. No crear un sistema paralelo de nombres, validación o rutas si el helper actual cubre el caso.

## QA mínimo

Antes de dar un módulo por terminado:

- alta;
- edición;
- borrado o desactivación;
- búsqueda/filtro;
- CSRF inválido;
- sesión expirada;
- registro correcto en `admin_main.php`;
- ausencia de errores JS;
- ausencia de SQL o stack traces en la respuesta pública;
- revisión de integridad de claves/bridges afectados.

## Cuando el módulo toca esquema

Un controlador administrativo no debe convertirse accidentalmente en migrador general.

Si una operación modifica estructura:

- documentar expresamente que escribe DDL;
- exigir backup;
- limitarla a un caso concreto y repetible;
- preferir una migración versionada y auditable antes que DDL escondido dentro de un CRUD.

