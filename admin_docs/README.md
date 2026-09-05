# Documentación técnica de Heaven's Gate

Esta carpeta reúne la documentación de mantenimiento de la web. Desde septiembre de 2026 se distingue entre **documentación viva** y **registros históricos de migración** para evitar que notas de una fase antigua se interpreten como arquitectura vigente.

## Documentación viva

| Documento | Uso |
|---|---|
| [TECHNICAL_DOCUMENTATION.md](./TECHNICAL_DOCUMENTATION.md) | Arquitectura actual, routing, modelo de datos y criterios de mantenimiento. |
| [DATABASE_SCHEMA.md](./DATABASE_SCHEMA.md) | Referencia del esquema de producción derivada del snapshot del 1 de septiembre de 2026. |
| [CSS_ARCHITECTURE.md](./CSS_ARCHITECTURE.md) | Capas CSS, propiedad de estilos, convención `hg-*` y estrategia de migración de nombres legacy. |
| [SCRIPTS_AND_MAINTENANCE.md](./SCRIPTS_AND_MAINTENANCE.md) | Inventario de scripts y herramientas, cómo ejecutarlos y qué riesgos tienen. |
| [ADMIN_MODULE_GUIDE.md](./ADMIN_MODULE_GUIDE.md) | Convenciones para crear o mantener módulos de `/talim`. |
| [PUBLIC_SECTION_GUIDE.md](./PUBLIC_SECTION_GUIDE.md) | Cómo añadir una sección pública y cómo usar `tools/scaffold_section.py`. |

## Registros históricos

Los ficheros `migration_manifest_*` y `migration_manifest_worlds_20260901.csv` documentan decisiones y operaciones de la migración de continuidad iniciada en septiembre de 2026. Son válidos como **registro de aquella migración**, no como descripción general del runtime.

`bdd_structure.txt` se conserva por compatibilidad documental con esos manifiestos. Su contenido original refleja una instantánea antigua y no debe utilizarse para conocer el esquema actual. La referencia vigente es [DATABASE_SCHEMA.md](./DATABASE_SCHEMA.md).

Los antiguos `ADD_SECTION_GUIDE.html` y `admin_maintenance_guide.txt` se mantienen como puntos de entrada heredados y remiten a sus sustitutos en Markdown.

La documentación específica del juego de cartas fue retirada junto con su implementación en septiembre de 2026. Su estado anterior sigue recuperable desde la rama de archivo `archive/legacy-tools-2026`.

## Fuente de verdad

Para arquitectura y comportamiento, manda el código de la rama activa.

Para la estructura de producción, la referencia utilizada en esta revisión es:

`starkvind/heavens-gate-continuity/snapshots/web/database/production-2026-09-01.sql`

El snapshot fue completado el **1 de septiembre de 2026 a las 23:36:12** y corresponde a **MariaDB 10.5.29**.

Cuando código, documentación histórica y snapshot discrepen, no se debe mezclar información: hay que comprobar primero qué capa se está documentando.
