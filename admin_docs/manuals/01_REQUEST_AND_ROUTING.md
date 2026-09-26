# Manual 01 — Request y routing

## Qué hace

Convierte una URL pública en un route key y después en un controlador.

## Propietarios

- .htaccess: frontera Apache, seguridad y front controller.
- index.php: borde HTTP y coordinación principal.
- app/routing/path_normalization.php: normaliza paths.
- app/routing/request_runtime.php: decide path canónico frente a compatibilidad legacy.
- app/routing/path_matcher.php: URL canónica -> route key. Debe seguir sin SQL.
- app/routing/legacy_query.php: solo compatibilidad histórica ?p=....
- app/routing/routes.php: route key -> controlador y sección.
- app/http/dispatch_policy.php: bare/fallback/política.
- app/http/dispatcher.php: incluye el controlador.
- app/http/request_context.php: contexto explícito de request.

## Para añadir una página

Si es una sección simple, usa primero:

    python3 tools/scaffold_section.py --route-key example --slug example --title "Example" --dry-run

Si es una ruta de detalle, edita conscientemente path_matcher.php y routes.php y usa pretty_id para la URL pública.

## No hagas

- No metas SQL en path_matcher.php.
- No leas $_GET/$_POST desde controladores públicos.
- No generes URLs ?p=... nuevas.
- No apuntes un enlace público directamente a app/*.php.
- No añadas lógica de dominio a page_dispatch.php.

## Verificación

    php .github/ci/php-router-characterization.php
    php .github/ci/php-path-matcher-characterization.php
    php .github/ci/php-dispatch-characterization.php
    python3 .github/ci/php-legacy-alias-audit.py
    bash tools/production_smoke.sh

Actualiza ROUTE_DICTIONARY.md en el mismo cambio si cambia una URL o route key.
