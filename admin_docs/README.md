# Documentación técnica de Heaven's Gate

Esta carpeta contiene únicamente documentación viva de producción.

## Empieza aquí

Si vas a tocar la web, abre primero [manuals/README.md](./manuals/README.md). Los manuales están pensados como cableado operativo: qué fichero tocar, qué fichero no tocar y qué prueba ejecutar después.

## Referencias principales

| Documento | Para qué sirve |
|---|---|
| [manuals/README.md](./manuals/README.md) | Índice de manuales de operación por subsistema. |
| [TECHNICAL_DOCUMENTATION.md](./TECHNICAL_DOCUMENTATION.md) | Arquitectura completa del runtime actual. |
| [ROUTE_DICTIONARY.md](./ROUTE_DICTIONARY.md) | URLs, route keys, controladores, APIs, embeds y compatibilidad legacy/móvil. |
| [DATABASE_SCHEMA.md](./DATABASE_SCHEMA.md) | Mapa del esquema que existe en producción. |
| [CSS_ARCHITECTURE.md](./CSS_ARCHITECTURE.md) | Propiedad de estilos, capas CSS y convenciones. |
| [SCRIPTS_AND_MAINTENANCE.md](./SCRIPTS_AND_MAINTENANCE.md) | Scripts operativos y comandos de comprobación. |
| [PUBLIC_SECTION_GUIDE.md](./PUBLIC_SECTION_GUIDE.md) | Alta de una sección pública. |
| [ADMIN_MODULE_GUIDE.md](./ADMIN_MODULE_GUIDE.md) | Alta y mantenimiento de módulos de /talim. |

## Fuente de verdad

Para comportamiento manda el código de master.

Para rutas mandan app/routing/path_matcher.php, app/routing/routes.php, app/http/dispatcher.php y app/routing/legacy_query.php.

Para datos manda la base de producción; DATABASE_SCHEMA.md es su mapa mantenido.

Para historia de migraciones, refactors, auditorías editoriales y operaciones ya terminadas manda starkvind/heavens-gate-continuity.

La rama de producción no debe acumular SQL de una sola ejecución, scripts numerados por fases ni documentación histórica.
