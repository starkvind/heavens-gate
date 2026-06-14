# Card Game Phase 1 LLM Handoff

Este archivo está pensado para el siguiente LLM que implemente Fase 1.

## Contexto mínimo que debes leer antes de tocar código

1. `CARD-GAME-NEXT-BIG-UPDATE.md`
2. `CARD-GAME-PHASE-0-DEPENDENCY-MAP.md`
3. `CARD-GAME-PHASE-0-IMPACT-CUT.md`
4. `CARD-GAME-PHASE-1-EXTRACTION-SHORTLIST.md`

## Estado actual del repo

- existe un lab aislado en `/games/hg-cardgame-dev-lab`
- el lab usa `storage scope` propio
- el árbol `assets/js/card-game/bootstrap/` ya existe
- `game-cards-v2.js` sigue siendo el motor real
- Fase 0 ya dejó identificado qué funciones son seguras y cuáles no

## Objetivo estricto de Fase 1

No modularices todavía combate, tienda ni colección completa.

Haz sólo esto:

1. crear módulos nuevos para core/data seguros
2. mover helpers puros o casi puros
3. dejar `game-cards-v2.js` consumiendo esos módulos
4. mantener el comportamiento intacto

## Orden recomendado de implementación

### Paso 1

Crear módulos:

- `assets/js/card-game/core/game-card-utils.js`
- `assets/js/card-game/core/game-card-storage.js`
- `assets/js/card-game/data/game-card-copy-model.js`
- `assets/js/card-game/data/game-card-governance.js`
- `assets/js/card-game/data/game-card-rules.js`

### Paso 2

Registrar API pública en:

- `window.HGCardGame.core`
- `window.HGCardGame.data`

### Paso 3

Extraer primero funciones puras:

- clamps
- formatters
- normalizers
- move/copy helpers
- rarity/quality governance helpers

### Paso 4

Reemplazar en `game-cards-v2.js` las implementaciones locales por llamadas a módulo.

### Paso 5

Sólo después, extraer storage base.

## Reglas de implementación

- no cambies el orden de bootstrap actual
- no cambies los nombres de rutas
- no cambies las claves de storage
- no muevas aún funciones que disparan render múltiple
- no muevas aún funciones que hacen fetch y además persisten y renderizan
- si una función escribe globales monolíticas, primero conviértela en función que recibe y devuelve datos

## Micro-refactors permitidos

- dividir `writeJson()` en storage base + feedback UI
- dividir `migrateCollectionQuality()` en cálculo puro + aplicador
- dividir `applyGameRulesSettings()` en:
  - normalización de payload
  - aplicación a contenedor de reglas

## Prohibiciones en esta fase

No hagas todavía:

- combate modular completo
- modales modulares
- extracción de `bindEvents()`
- extracción de `bindCollectionControls()`
- sustitución total de `game-cards-v2.js`
- cambios de UI por estética

## Checklist antes de cerrar la fase

- producción sigue cargando `game-cards-v2.js`
- lab sigue cargando `game-cards-v2.js` más bootstrap modular
- no hay errores de sintaxis PHP
- no hay errores JS obvios por namespaces faltantes
- las APIs nuevas quedan documentadas en cada módulo
- `game-cards-v2.js` queda más corto y con menos helpers internos

## Resultado esperado al final

El proyecto queda listo para arrancar Fase 2 con:

- `core/` y `data/` ya vivos
- wrapper monolítico más fino
- gobernanza de copia y normalización fuera del bloque principal
