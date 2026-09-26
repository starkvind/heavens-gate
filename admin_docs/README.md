# Documentación técnica de Heaven's Gate

Esta carpeta reúne la documentación vigente para mantener la aplicación web.

## Documentación viva

| Documento | Uso |
|---|---|
| [TECHNICAL_DOCUMENTATION.md](./TECHNICAL_DOCUMENTATION.md) | Arquitectura actual, routing, modelo de datos y criterios de mantenimiento. |
| [ROUTE_DICTIONARY.md](./ROUTE_DICTIONARY.md) | Diccionario humano de route keys, URLs canónicas, aliases legacy, controladores, APIs, embeds y compatibilidad móvil. |
| [DATABASE_SCHEMA.md](./DATABASE_SCHEMA.md) | Referencia del esquema de producción derivada del snapshot de producción vigente. |
| [CSS_ARCHITECTURE.md](./CSS_ARCHITECTURE.md) | Capas CSS, propiedad de estilos y convención `hg-*`. |
| [SCRIPTS_AND_MAINTENANCE.md](./SCRIPTS_AND_MAINTENANCE.md) | Inventario de scripts y herramientas, cómo ejecutarlos y qué riesgos tienen. |
| [ADMIN_MODULE_GUIDE.md](./ADMIN_MODULE_GUIDE.md) | Convenciones para crear o mantener módulos de `/talim`. |
| [PUBLIC_SECTION_GUIDE.md](./PUBLIC_SECTION_GUIDE.md) | Cómo añadir una sección pública y cómo usar `tools/scaffold_section.py`. |

Los antiguos `ADD_SECTION_GUIDE.html` y `admin_maintenance_guide.txt` se mantienen únicamente como puntos de entrada heredados hacia sus sustitutos actuales.

## Fuente de verdad

Para arquitectura y comportamiento, manda el código de la rama activa.

Para routing, `ROUTE_DICTIONARY.md` es el mapa humano, pero las fuentes ejecutables siguen siendo `app/routing/path_matcher.php`, `app/routing/routes.php`, `app/http/dispatcher.php` y la capa de compatibilidad legacy.

Para la estructura de producción, la referencia mantenida es [DATABASE_SCHEMA.md](./DATABASE_SCHEMA.md), contrastada con el snapshot de producción conservado fuera del runtime público.

Cuando código y documentación discrepen, debe comprobarse primero el código ejecutable y actualizar después la documentación viva.
