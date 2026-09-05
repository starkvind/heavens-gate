# Arquitectura CSS de Heaven's Gate

Última revisión: 2026-09-05.

Este documento define la propiedad de los estilos públicos y la convención de nombres que debe seguir el código nuevo. El objetivo de la Fase 4 del refactor es que `hg-core.css` vuelva a ser un núcleo pequeño y que cada regla tenga un propietario reconocible.

## Capas globales

Los estilos globales se cargan en este orden desde `app/bootstrap/head_work.php`:

1. `hg-tokens.css`: variables y tokens de diseño.
2. `hg-base.css`: normalización y elementos HTML base.
3. `hg-core.css`: shell global mínimo y compatibilidad estructural que todavía no tenga un propietario mejor.
4. `hg-legacy-components.css`: primitivas históricas compartidas por varios dominios. Es una capa de compatibilidad, no un destino para código nuevo.
5. `hg-components.css`: componentes reutilizables con responsabilidad clara.
6. `hg-layout.css`: composición del layout general.
7. `hg-menu.css`: navegación global.
8. Estilos de dominio/página registrados mediante `hg_page_register_stylesheet()`.

El orden es deliberado. Los estilos de dominio deben poder especializar componentes globales sin recurrir a `!important` salvo incompatibilidad legacy ya existente.

## Propiedad por dominio

Los estilos que pertenecen a una sección concreta deben vivir en un fichero de dominio reconocible:

- `hg-bio.css`: personajes y ficha de personaje.
- `hg-docs.css`: documentación y reglas documentales.
- `hg-events.css`: timeline y eventos.
- `hg-maps.css`: mapas.
- `hg-systems.css`: sistemas de juego.
- ficheros equivalentes para inventario, poderes, capítulos, galería y demás dominios cuando termine su extracción.

Los estilos exclusivos de una sola página pueden vivir bajo `assets/css/pages/<dominio>/`. La carpeta `assets/css/pages/legacy/` es transitoria: contiene CSS extraído durante la Fase 1 cuya propiedad definitiva todavía no se ha resuelto.

## Convención de nombres nueva

Todo selector nuevo reutilizable debe comenzar por `hg-`.

Para componentes se usa una variante sencilla de BEM:

```text
.hg-character-avatar
.hg-character-avatar__image
.hg-character-avatar__label
.hg-character-avatar--pnj
```

Para clases estrictamente ligadas a un dominio, el dominio forma parte del nombre:

```text
.hg-bio-actions
.hg-bio-actions__row
.hg-inventory-item
.hg-powers-list
.hg-system-form
```

Los nombres deben describir **qué es el elemento o qué función cumple**, no su posición visual. Evitar nombres nuevos como `renglon2col`, `cajaIzq`, `grupo1`, `celdaDe`, `cosita`, etc.

No codificar en el nombre una implementación que pueda cambiar. Por ejemplo, preferir `hg-bio-actions__entry` a `hg-bio-actions__grid-cell` si la entrada puede dejar de usar grid en el futuro.

## JavaScript y CSS

Una clase de presentación no debe convertirse accidentalmente en API de JavaScript.

Para comportamiento nuevo, preferir:

- atributos `data-*` para estado, identificadores y configuración;
- clases `js-*` únicamente cuando sea imprescindible un hook de DOM sin semántica visual.

Si JavaScript depende de una clase legacy existente, documentar esa dependencia antes de renombrarla.

## Migración de nombres legacy

No se deben renombrar selectores históricos mediante sustitución masiva.

La migración correcta es:

1. identificar todos los consumidores reales;
2. crear el nombre `hg-*` nuevo junto al selector antiguo;
3. añadir la clase nueva al markup sin retirar la antigua;
4. migrar consumidores por dominios;
5. comprobar que el selector antiguo ya no aparece en runtime;
6. retirar entonces el alias legacy.

Mientras una clase histórica sea usada por varios dominios, debe permanecer en `hg-legacy-components.css` y llevarse como deuda explícita. Un nombre como `bioSeccion` no implica propiedad de biografías si mapas, capítulos o páginas de error también lo consumen.

## Criterio para `hg-core.css`

Una regla solo debe quedarse en `hg-core.css` si cumple al menos una de estas condiciones:

- forma parte del shell general de todas o casi todas las páginas;
- establece una compatibilidad estructural necesaria antes de que exista un propietario mejor;
- moverla a un dominio cambiaría la cascada de páginas no relacionadas.

Tarjetas, tabs, tooltips, breadcrumbs y otros widgets reutilizables pertenecen a `hg-components.css`, no al core.

## Regla para código nuevo

No añadir CSS nuevo directamente a `hg-core.css` por comodidad.

Antes de crear una regla, decidir si es:

- token;
- base;
- layout/menu;
- componente compartido;
- dominio;
- excepción de una única página.

Si no está claro quién es el propietario de un estilo, esa ambigüedad debe resolverse antes de nombrarlo. La carpeta o fichero debe permitir reconocer su responsabilidad sin tener que buscar qué controlador lo utiliza.
