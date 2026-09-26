# Manual 07 — Móvil y responsive

## Estado

?view=mobile sigue siendo una capa de compatibilidad. No es un segundo sistema de datos ni un router independiente.

## Propietarios

- app/helpers/mobile_detection.php: detección/preferencia.
- app/mobile/mobile_index.php: shell móvil.
- app/mobile/mobile_routes.php: controlador móvil específico por route key.
- app/mobile/controllers/: controladores móviles.
- app/mobile/views/: vistas móviles.

Si una ruta no tiene controlador móvil específico, el fallback reutiliza app/http/page_dispatch.php.

## Reglas

- no dupliques consultas SQL en móvil;
- reutiliza app/domains/;
- no crees una segunda canonicalización de URLs;
- añade ruta móvil específica solo si la presentación lo necesita de verdad.

## Verificación

    python3 .github/ci/php-mobile-shared-data-audit.py
    python3 .github/ci/php-mobile-presentation-audit.py
    bash tools/production_smoke.sh

Prueba además la URL modificada con ?view=mobile.
