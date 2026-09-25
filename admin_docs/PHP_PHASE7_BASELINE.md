# PHP Phase 7 — Final architecture baseline

Última revisión: 2026-09-25.

Este documento fija el estado arquitectónico al cierre de la Fase 7 del refactor PHP. No declara que la aplicación esté libre de deuda. Declara **qué deuda queda, dónde está y qué métricas no deben empeorar accidentalmente**.

La fuente ejecutable del baseline es:

`.github/ci/php-phase7-final-baseline-audit.py`

## Snapshot de cierre

Baseline obtenido del CI verde posterior a 7.4C:

| Métrica | Baseline |
|---|---:|
| Ficheros PHP runtime | 349 |
| Líneas PHP | 83.168 |
| Route keys activos | 98 |
| Route keys móviles | 76 |
| Controladores Admin | 59 |
| SQL call sites totales | 769 |
| Schema probes | 20 |
| Lecturas crudas `$_GET` | 192 |
| Lecturas crudas `$_POST` | 708 |
| Lecturas crudas `$_REQUEST` | 1 |
| Declaraciones `global` | 7 |
| `case` de compatibilidad en `legacy_query.php` | 47 |
| Controladores públicos con SQL directo | 4 |
| SQL call sites en controladores públicos | 16 |
| PHP públicos directos bajo `api/` | 0 |

El recuento de ficheros PHP y route keys activos es **snapshot**, no techo automático: una feature legítima puede añadir código o rutas. Las métricas de deuda sí funcionan como techo de regresión en CI.

## Fronteras consolidadas

El front controller es `index.php`.

Bootstrap contiene únicamente `app/bootstrap/runtime.php`.

Routing canónico vive bajo `app/routing/`.

`path_matcher.php` conoce formas de URL y no accede a MySQL.

`legacy_query.php` es la única frontera para canonicalización histórica `?p=...`.

El runtime no debe generar nuevas URLs `?p=...`.

Route keys desconocidos terminan en el controlador 404.

No existen PHP públicos directos bajo `api/`.

Admin mantiene 59 controladores registrados y **cero SQL directo en controladores**; el acceso a datos administrativo vive en dominios/servicios.

El Simulador de Combate y el Archivo de Mnemógeno no forman parte del runtime. Sus últimas versiones vivas están archivadas en:

- `archive/combat-simulator-last-live`;
- `archive/game-cards-last-live`.

## Deuda aceptada al cierre

### Compatibilidad query-string

`legacy_query.php` conserva 47 ramas `case`. Es deuda compatible y concentrada. Puede reducirse, pero no debe volver a dispersarse por controladores, vistas o JavaScript.

### Vista móvil separada

La capa móvil conserva 76 route keys y 38 ficheros PHP en el área móvil. Sigue siendo compatibilidad temporal. No debe crecer salvo necesidad explícita.

### SQL en controladores públicos

Quedan cuatro propietarios públicos con SQL directo:

- `app/controllers/main/error404.php` — 8 call sites;
- `app/controllers/tool/dice_roller.php` — 3;
- `app/controllers/tool/forum_avatar_builder.php` — 3;
- `app/controllers/tool/forum_topic_viewer.php` — 2.

Total: 16.

Esto es deuda localizada. CI impide que aumente.

### Request crudo

Las lecturas `$_GET` y `$_POST` restantes están prácticamente concentradas en Admin. La capa pública principal ya usa request context explícito. El baseline impide aumentar el volumen total sin revisar la decisión.

### Schema probes

Quedan 20 probes de esquema. Son deuda conocida; no deben multiplicarse en runtime.

## Qué bloquea CI

El guard final falla si aumenta:

- la superficie móvil;
- el número de controladores públicos con SQL directo;
- el SQL directo público;
- los schema probes;
- las lecturas crudas `$_GET`, `$_POST` o `$_REQUEST`;
- las ramas de compatibilidad de `legacy_query.php`.

También falla si:

- reaparecen PHP públicos directos bajo `api/`;
- bootstrap vuelve a contener algo distinto de `runtime.php`;
- cambia el inventario de controladores Admin sin revisar expresamente el baseline.

## Qué no bloquea por sí solo

Añadir una feature legítima puede aumentar:

- el número total de PHP;
- el número de route keys canónicos.

Esos valores se imprimen como snapshot y deben revisarse en cambios arquitectónicos, pero no se consideran deuda automáticamente.

## Regla de cierre

La Fase 7 queda cerrada cuando:

1. este baseline pasa;
2. PHP Refactor Characterization pasa;
3. Project CI pasa;
4. la documentación viva describe el runtime actual;
5. cualquier deuda restante está localizada y no puede crecer de forma silenciosa.
