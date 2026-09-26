# Manual 05 — CSS y presentación

## Capas globales

El orden conceptual es tokens -> base -> core -> legacy components -> shared components -> layout/menu -> dominio/página.

Consulta CSS_ARCHITECTURE.md para el inventario detallado.

## Dónde tocar

- hg-tokens.css: variables.
- hg-base.css: HTML base.
- hg-core.css: shell global mínimo.
- hg-components.css: componentes reutilizables.
- hg-layout.css y hg-menu.css: composición global.
- hg-<dominio>.css: estilos de dominio.
- assets/css/pages/: excepciones de página.

## Regla principal

No metas CSS de dominio en hg-core.css.

Código nuevo reutilizable usa prefijo hg-. Para hooks de JS, prefiere data-* antes que acoplar comportamiento a clases visuales.

## Legacy

hg-legacy-components.css existe porque todavía hay consumidores reales. No lo uses como vertedero. Antes de retirar un selector, busca todos sus consumidores.

## Verificación

    python3 .github/ci/css-architecture-guard.py

Y revisión visual desktop + móvil de la zona tocada.
