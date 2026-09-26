# Scripts y mantenimiento

Última revisión: 2026-09-27.

Este documento enumera herramientas que siguen teniendo utilidad operativa. Si un script no aparece aquí ni está llamado por CI, no debe asumirse que sea seguro ejecutarlo.

## Política

heavens-gate es producción. No contiene migraciones SQL históricas ni scripts de una sola ejecución. Ese material se archiva en heavens-gate-continuity.

Antes de cualquier operación con escritura:

1. confirmar master y working tree limpio;
2. comprobar el entorno y config.env;
3. obtener backup si se toca BDD o contenido crítico;
4. usar dry-run cuando exista;
5. ejecutar las verificaciones indicadas en este documento.

## Herramientas de tools/

### production_smoke.sh

Objetivo: verificar la instalación web real después de despliegues o cambios sensibles.

Ejecutar:

    bash tools/production_smoke.sh

Comprueba hubs públicos, vista móvil, Admin shell, PWA, embeds, redirects legacy, árboles privados y cabeceras de caché.

No modifica la base de datos.

### scaffold_section.py

Objetivo: crear el esqueleto de una sección pública simple sin editar a mano las piezas básicas de routing.

Primero:

    python3 tools/scaffold_section.py --route-key example --slug example --title "Example" --dry-run

Solo después de revisar el plan se ejecuta sin --dry-run.

Puede tocar path_matcher.php, routes.php, crear controlador, CSS opcional y menú fallback. No resuelve rutas de detalle, pretty_id, CRUD ni compatibilidad ?p=... por sí solo.

### architecture_inventory.py

Objetivo: fotografía read-only de la arquitectura PHP: tamaño, SQL directo, request globals, schema probes y concentración de compatibilidad.

Ejecutar:

    python3 tools/architecture_inventory.py

No modifica código ni BDD. Sirve para investigar antes de una refactorización o para comparar deuda.

## Herramientas internas bajo app/tools/

app/tools no es una carpeta pública. .htaccess bloquea /app.

- crop.html: implementación física usada por la ruta pública /tools/crop.
- forum_topic_viewer_tool.php: adaptador de lectura del foro para la herramienta enrutada.
- inspect_db.php: diagnóstico de esquema usado desde la superficie administrativa autorizada.

No enlaces nunca directamente a /app/tools/....

## Pruebas locales útiles

Lint PHP:

    find app -type f -name '*.php' -print0 | xargs -0 -n1 php -l
    php -l index.php

Guard CSS:

    python3 .github/ci/css-architecture-guard.py

Contrato arquitectónico principal:

    python3 .github/ci/php-architecture-contract-audit.py

Límites de dominio/SQL:

    python3 .github/ci/php-domain-query-boundary-audit.py
    python3 .github/ci/php-public-sql-boundary-audit.py
    python3 .github/ci/php-schema-contract-audit.py

Routing:

    php .github/ci/php-router-characterization.php
    php .github/ci/php-path-matcher-characterization.php
    php .github/ci/php-dispatch-characterization.php

PWA y móvil:

    python3 .github/ci/php-pwa-contract-audit.py
    python3 .github/ci/php-mobile-shared-data-audit.py
    python3 .github/ci/php-mobile-presentation-audit.py

Admin:

    php .github/ci/php-admin-shell-characterization.php
    python3 .github/ci/php-admin-domain-boundary-audit.py
    python3 .github/ci/php-admin-structural-audit.py

La ejecución normal de todos estos contratos se deja a GitHub Actions.

## Base de datos

No hay directorio SQL operativo en producción.

Si una operación futura necesita DDL o una auditoría SQL puntual:

- se prepara fuera de master;
- se revisa contra DATABASE_SCHEMA.md;
- se ejecuta con backup y cuenta adecuada;
- una vez terminada se archiva en continuity;
- no se deja como residuo permanente en heavens-gate.

## Después de un despliegue

Ejecutar:

    bash tools/production_smoke.sh

Después comprobar manualmente al menos una página pública compleja, una ficha de personaje, una pantalla de /talim y cualquier área modificada.
