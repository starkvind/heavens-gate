# Manual 08 — PWA y assets estáticos

## Piezas

- manifest.json: manifiesto canónico.
- service-worker.js: service worker.
- offline.html: fallback offline.
- assets/js/hg-pwa.js: instalación y registro.
- public/: imágenes y sonidos públicos.

## Reglas

El service worker no debe cachear Admin ni endpoints dinámicos sensibles. Mantén el fallback offline pequeño y estable.

Los assets públicos existentes pueden servirse directamente; el runtime PHP bajo app/ nunca debe exponerse así.

## Verificación

    python3 .github/ci/php-pwa-contract-audit.py
    node --check assets/js/hg-pwa.js
    node --check service-worker.js
    bash tools/production_smoke.sh

Si cambias iconos, valida tamaños 192x192 y 512x512.
