# Manual 06 — JavaScript y frontend

## Ubicación

Assets JS globales viven en assets/js/. Código Admin específico vive en assets/js/admin/.

## Globales importantes

- permutloading.js: comportamiento histórico global.
- hg-tabs.js: tabs compartidos.
- hg-tooltip.js: tooltips.
- hg_mentions.js: menciones.
- hg-datatables.js: tablas.
- hg-maps.js: mapas.
- hg-mobile.js: presentación móvil.
- hg-pwa.js: instalación/PWA.
- forum-avatar-embed.js: integración de avatar en foro.

## Reglas

- usa data-* como API DOM estable;
- no hagas depender JS nuevo de clases puramente visuales;
- no dupliques lógica de transporte Admin: usa admin/admin-http.js;
- si renderizas HTML dinámico, vuelve a enlazar solo los listeners necesarios;
- no pongas secretos/tokens permanentes en JS.

## Comprobación

Para ficheros cambiados:

    node --check assets/js/fichero.js

Después prueba manualmente el flujo interactivo correspondiente y ejecuta production_smoke si afecta shell, foro, PWA o rutas.
