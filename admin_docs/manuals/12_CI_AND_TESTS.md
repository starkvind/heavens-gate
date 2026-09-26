# Manual 12 — CI y pruebas

## Workflows

### Project CI

.github/workflows/security-checks.yml

Protege sintaxis PHP, seguridad, árboles privados, CSS, cache/versionado, auth de APIs, ausencia de DDL en runtime y limpieza del repositorio de producción.

### Architecture Regression Checks

.github/workflows/architecture-checks.yml

Protege routing, request context, boundaries de SQL/dominio, aliases legacy, runtime, móvil, PWA, rendimiento, Admin y features retiradas.

## Scripts para ejecutar a mano

Smoke real:

    bash tools/production_smoke.sh

Scaffold sin escribir:

    python3 tools/scaffold_section.py --route-key test --slug test --title "Test" --dry-run

Inventario arquitectónico:

    python3 tools/architecture_inventory.py

Lint PHP:

    find app -type f -name '*.php' -print0 | xargs -0 -n1 php -l
    php -l index.php

Guard CSS:

    python3 .github/ci/css-architecture-guard.py

Contrato general:

    python3 .github/ci/php-architecture-contract-audit.py

Routing:

    php .github/ci/php-router-characterization.php
    php .github/ci/php-path-matcher-characterization.php
    php .github/ci/php-dispatch-characterization.php

Datos/SQL:

    python3 .github/ci/php-domain-query-boundary-audit.py
    python3 .github/ci/php-public-sql-boundary-audit.py
    python3 .github/ci/php-schema-contract-audit.py

Móvil/PWA:

    python3 .github/ci/php-mobile-shared-data-audit.py
    python3 .github/ci/php-mobile-presentation-audit.py
    python3 .github/ci/php-pwa-contract-audit.py

Admin:

    php .github/ci/php-admin-shell-characterization.php
    python3 .github/ci/php-admin-domain-boundary-audit.py
    python3 .github/ci/php-admin-structural-audit.py

## Qué ejecutar según cambio

Routing: routing + contrato general + smoke.

CSS: css guard + revisión visual.

BDD/query: domain/query + schema + contrato general.

Admin: Admin checks + prueba manual /talim.

Móvil/PWA: checks móviles/PWA + smoke.

Seguridad/.htaccess: Project CI + smoke obligatorio.
