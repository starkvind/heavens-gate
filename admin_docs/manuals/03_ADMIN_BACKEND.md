# Manual 03 — Backend Admin

## Superficie

El backend editorial entra por /talim. Los controladores viven en app/controllers/admin/.

## Seguridad obligatoria

- Sesión: app/helpers/admin_auth.php.
- AJAX/CSRF: app/helpers/admin_ajax.php.
- Uploads: app/helpers/admin_uploads.php.
- Rate limit de login: app/helpers/admin_login_rate_limit.php.

Nunca inventes autenticación paralela ni confíes en cookies controladas por cliente.

## Datos

La lógica SQL compartida debe estar fuera del controlador cuando exista propietario de dominio. Los controladores Admin son borde HTTP: pueden manejar GET/POST explícitos, pero $_REQUEST está prohibido.

## Añadir un módulo

1. comprueba que no exista módulo equivalente;
2. crea controlador;
3. registra dispatch Admin;
4. registra navegación si procede;
5. reutiliza admin-http.js si es AJAX;
6. aplica CSRF a toda mutación;
7. prueba alta, edición, borrado/desactivación, filtros y sesión expirada.

## Verificación

    php .github/ci/php-admin-shell-characterization.php
    python3 .github/ci/php-admin-domain-boundary-audit.py
    python3 .github/ci/php-admin-structural-audit.py
    python3 .github/ci/php-admin-ux-foundation-audit.py

Y después abre manualmente /talim.
