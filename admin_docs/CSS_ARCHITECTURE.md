# Arquitectura CSS de Heaven's Gate

Última revisión: 2026-09-05.

Este documento define la propiedad de los estilos públicos y la convención de nombres que debe seguir el código nuevo. El objetivo de la Fase 4 del refactor es que `hg-core.css` sea un núcleo pequeño y que cada regla tenga un propietario reconocible.

## Capas globales

Los estilos globales se cargan en este orden desde `app/bootstrap/head_work.php`:

1. `hg-tokens.css`: variables y tokens de diseño.
2. `hg-base.css`: normalización y elementos HTML base.
3. `hg-core.css`: shell global mínimo.
4. `hg-legacy-components.css`: primitivas históricas que todavía conservan consumidores reales en varios dominios. Es una capa de compatibilidad, no un destino para código nuevo.
5. `hg-components.css`: componentes reutilizables con responsabilidad clara.
6. `hg-layout.css`: composición del layout general.
7. `hg-menu.css`: navegación global.
8. Estilos de dominio/página registrados mediante `hg_page_register_stylesheet()`.

El orden es deliberado. Los estilos de dominio deben poder especializar componentes globales sin recurrir a `!important` salvo incompatibilidad legacy ya existente.

## Propiedad por dominio

Los estilos que pertenecen a una sección concreta deben vivir en un fichero de dominio reconocible. La Fase 4 deja como propietarios explícitos, entre otros:

- `hg-bio.css`: personajes y ficha de personaje.
- `hg-docs.css`: documentación y reglas documentales compartidas.
- `hg-inventory.css`: inventario de escritorio, listados de objetos y especializaciones de la ficha de objeto.
- `hg-powers.css`: portada, categorías, listados, fichas y catálogos estándar de Dones, Rituales, Disciplinas y Tótems.
- `hg-power-custom.css`: compositor y vistas personalizadas/imprimibles de poderes; es un stylesheet especializado del dominio Powers, no una capa legacy.
- `hg-traits.css`: ficha de Rasgos, niveles, gemas y agrupación de portadores.
- `hg-systems.css`: sistemas de juego, sus fichas y listados de poderes asociados.
- `hg-events.css`: timeline y eventos.
- `hg-maps.css`: mapas.
- `hg-chapters.css`: temporadas y capítulos.
- `hg-chapters-runtime.css`: compatibilidad de presentación todavía necesaria para fragmentos históricos del dominio Chapters; no debe recibir estilos de otros dominios.
- `hg-gallery.css`: galería pública y lightbox.
- `hg-news.css`: listado público de noticias.
- `hg-status.css`: página de estado público.
- `hg-bibliography.css`: bibliografía pública.
- `hg-maneuvers.css`: listado de maniobras de combate.
- `hg-bso.css`: tarjetas de banda sonora embebidas.
- `hg-archive-panel.css`: fieldset compartido de apariencia archivística usado por páginas de capítulos y organizaciones.

Los estilos exclusivos de una sola página pueden vivir bajo `assets/css/pages/<dominio>/`. La carpeta `assets/css/pages/legacy/` es transitoria: contiene CSS extraído durante la Fase 1 cuya propiedad definitiva todavía no se ha resuelto o cuya recolocación exige tocar controladores grandes sin beneficio funcional inmediato.

La presencia de un fichero en `pages/legacy/` no autoriza a reutilizar sus clases en código nuevo. Si la propiedad del fichero ya es clara y el consumidor puede migrarse con bajo riesgo, debe moverse a `assets/css/pages/<dominio>/`.

### Inventario

Las rutas de escritorio de `/inventory` registran `hg-inventory.css`. El dominio posee sus celdas de objeto, iconos, miniaturas, agrupaciones por origen, tarjetas de listado, gemas de estadísticas, portadores y control de embed.

Inventario puede consumir componentes globales como `power-card`, `hg-tabs`, `hg-tab-panel`, `hg-affiliation-content` y los avatares de personaje, pero no debe volver a depender de primitivas históricas como `renglon2col`, `renglon2colIz`, `grupoBioClan`, `contenidoAfiliacion` o `bioAttCircle`.

`hg-docs.css` sigue siendo propietario del shell de DataTables que también utiliza el inventario (`docs-table-*`, `dt-toolbar`, `ms-*`). Esa dependencia es deliberada mientras ese shell siga siendo compartido con otras páginas documentales; no convierte las clases propias del inventario en clases de documentación.

### Powers

Las rutas estándar de `/powers` registran `hg-powers.css`. El dominio posee la portada de poderes, tarjetas de categoría y grupo, agrupaciones de Tótems por origen, iconos y gemas de las fichas, relaciones de Tótems con grupos/organizaciones y el formato completo compartido por los catálogos de Dones y Rituales.

Las vistas de tabla reutilizan también el shell `pwrs-table-*`, `dt-toolbar` y `ms-*` que vive en `hg-powers.css`. Las vistas personalizadas y sus versiones imprimibles se mantienen en `hg-power-custom.css` porque forman un componente de página autónomo con un ciclo de vida distinto.

Powers consume componentes globales como `power-card`, `hg-tabs`, `hg-tab-panel`, `hg-affiliation-content` y los avatares de personaje. No debe volver a depender para su presentación de `grupoHabilidad`, `grupoBioClan`, `contenidoAfiliacion`, `renglon3col`, `renglon2col`, `renglon2colIz`, `renglon2colDe`, `descripcionGrupo` o `bioAttCircle`.

El comportamiento de agrupaciones plegables de Powers se enlaza mediante atributos `data-powers-toggle`; las clases visuales no son API de JavaScript.

### Traits

La ficha de Rasgos registra `hg-traits.css`. Las gemas de nivel y las agrupaciones de portadores usan clases `hg-traits-*` y el componente global `hg-affiliation-content`.

Traits no debe volver a usar `bioAttCircle`, `grupoBioClan`, `contenidoAfiliacion` ni el antiguo helper visual de tabs como dependencia de presentación.

### Systems

La ficha de sistema y sus listados propios viven en `hg-systems.css`. Los Dones disponibles dentro de una ficha usan `hg-system-power-*` y no las antiguas filas `renglon2col*`.

El dominio puede especializar componentes globales, por ejemplo el tooltip, siempre de forma acotada al contexto de Sistemas.

### Gallery

`/gallery` registra `hg-gallery.css` y ya no necesita cargar `hg-main.css` para su presentación. El bloque histórico de reglas de galería que todavía pueda permanecer físicamente en `hg-main.css` se considera código muerto pendiente de una limpieza segura del fichero grande; no es la fuente de estilos de runtime para la galería.

### Shared archive panel

`hg-archive-panel.css` contiene el pequeño componente compartido por el gráfico de participación de temporadas y el listado de organizaciones/grupos. Sustituye los IDs históricos `archivosLegend`, `renglonArchivos` y `renglonArchivosTop`, que no deben volver a introducirse.

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
2. crear el nombre `hg-*` nuevo junto al selector antiguo cuando la migración no pueda ser atómica;
3. añadir la clase nueva al markup sin retirar la antigua;
4. migrar consumidores por dominios;
5. comprobar que el selector antiguo ya no aparece en runtime;
6. retirar entonces el alias legacy.

Mientras una clase histórica sea usada por varios dominios, debe permanecer en `hg-legacy-components.css` y llevarse como deuda explícita. Un nombre como `bioSeccion` no implica propiedad de biografías si otros dominios aún lo consumen.

Cuando un dominio puede migrarse por completo en una sola operación controlada, como Inventory, Powers, Traits o Systems en la Fase 4, puede pasar directamente al nombre semántico nuevo siempre que las reglas legacy permanezcan disponibles para los demás consumidores reales.

## Criterio para `hg-core.css`

`hg-core.css` queda reservado al shell público global: layout estructural histórico que utiliza `index.php`, botones/enlaces compartidos y cabecera global. No debe contener presentación de dominios, páginas, tablas funcionales concretas, galerías, capítulos, poderes, sistemas, inventario, estado, bibliografía ni embeds.

Una regla solo debe quedarse en `hg-core.css` si cumple al menos una de estas condiciones:

- forma parte del shell general de todas o casi todas las páginas;
- moverla a un dominio cambiaría la cascada de páginas no relacionadas;
- es una primitiva global todavía utilizada de manera transversal y no tiene un componente mejor definido.

Tarjetas, tabs, tooltips, breadcrumbs y otros widgets reutilizables pertenecen a `hg-components.css`, no al core.

## CI

`.github/workflows/security-checks.yml` funciona como CI general del proyecto. Se ejecuta en cualquier `push`, en pull requests hacia `master` y mediante `workflow_dispatch`.

Además del lint PHP y los guards de seguridad, verifica referencias a stylesheets locales y protege la propiedad del core frente a regresiones conocidas. El guard debe evolucionar con la arquitectura, pero no sustituye las pruebas visuales o HTTP de una instalación real.

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
