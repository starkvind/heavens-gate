# Card Game LLM Implementation Playbook

Este documento dice a un LLM cómo ejecutar la migración del card game sin tocar producción y cómo ir marcando hitos completados.

## Regla de uso

- Este archivo es el punto único de entrada para la implementación.
- Antes de empezar, lee también estos archivos de contexto:
  - `CARD-GAME-NEXT-BIG-UPDATE.md`
  - `CARD-GAME-PHASE-0-DEPENDENCY-MAP.md`
  - `CARD-GAME-PHASE-0-IMPACT-CUT.md`
  - `CARD-GAME-PHASE-1-EXTRACTION-SHORTLIST.md`
- Cada vez que completes un hito de este documento, cambia su casilla de:
  - `[ ]` pendiente
  - `[x]` completado
- No marques un punto como completado si no está realmente implementado y verificado.

---

## Objetivo operativo

Migrar de forma incremental `assets/js/game-cards-v2.js` a una arquitectura modular bajo:

- `assets/js/card-game/`

sin romper:

- producción: `/games/card-game`
- lab: `/games/hg-cardgame-dev-lab`

y manteniendo:

- mismo comportamiento visible
- mismo orden de arranque
- misma persistencia efectiva
- mismo aislamiento del lab

---

## Reglas duras

- No hacer un rewrite completo.
- No sustituir todavía `assets/js/game-cards-v2.js`.
- No tocar todavía combate modular completo, tienda completa ni colección completa.
- No mover aún funciones mixtas de alto riesgo sin partirlas antes.
- El lab debe seguir siendo el entorno principal de validación.
- Producción debe seguir funcionando con el wrapper actual.

---

## Archivos clave

### Wrapper actual

- `assets/js/game-cards-v2.js`

### Bootstrap modular ya existente

- `assets/js/card-game/bootstrap/game-card-features.js`
- `assets/js/card-game/bootstrap/game-card-loader.js`
- `assets/js/card-game/bootstrap/game-card-app.js`

### Rutas y controladores

- `app/bootstrap/request_router.php`
- `app/bootstrap/body_work.php`
- `app/controllers/tool/game_cards.php`
- `app/controllers/tool/game_cards_mobile.php`

### Vistas frontend

- `app/modules/game_cards/game_cards_page.php`
- `app/modules/game_cards/game_cards_collection_page.php`
- `app/modules/game_cards/game_cards_combat_page.php`
- `app/modules/game_cards/game_cards_mobile_page.php`
- `app/modules/game_cards/game_cards_explanation_page.php`

### Documentos de análisis

- `CARD-GAME-NEXT-BIG-UPDATE.md`
- `CARD-GAME-PHASE-0-DEPENDENCY-MAP.md`
- `CARD-GAME-PHASE-0-IMPACT-CUT.md`
- `CARD-GAME-PHASE-1-EXTRACTION-SHORTLIST.md`

---

## Estado actual

- [x] Existe entorno lab en `/games/hg-cardgame-dev-lab`
- [x] El lab usa storage aislado
- [x] Existe bootstrap modular mínimo
- [x] Existe mapa de dependencias de Fase 0
- [x] Existe corte de impacto de Fase 0
- [x] Existe shortlist de extracción para Fase 1
- [ ] La Fase 1 está implementada
- [ ] La Fase 2 está implementada

---

## Orden de implementación

## Fase 1

Objetivo:

extraer `core/` y `data/` seguros, dejando `game-cards-v2.js` como wrapper operativo.

### 1. Preparación

- [ ] Releer `CARD-GAME-NEXT-BIG-UPDATE.md`
- [ ] Releer `CARD-GAME-PHASE-0-DEPENDENCY-MAP.md`
- [ ] Releer `CARD-GAME-PHASE-0-IMPACT-CUT.md`
- [ ] Releer `CARD-GAME-PHASE-1-EXTRACTION-SHORTLIST.md`
- [ ] Confirmar que el trabajo se hará sobre el lab primero

### 2. Crear módulos nuevos

- [ ] Crear `assets/js/card-game/core/game-card-utils.js`
- [ ] Crear `assets/js/card-game/core/game-card-storage.js`
- [ ] Crear `assets/js/card-game/data/game-card-copy-model.js`
- [ ] Crear `assets/js/card-game/data/game-card-governance.js`
- [ ] Crear `assets/js/card-game/data/game-card-rules.js`

### 3. Registrar namespaces

- [ ] Asegurar que `window.HGCardGame.core` existe
- [ ] Asegurar que `window.HGCardGame.data` existe
- [ ] Exponer sólo API pública por módulo

### 4. Mover utilidades puras a `core/game-card-utils.js`

Funciones objetivo desde `assets/js/game-cards-v2.js`:

- `normalizeStorageScope`
- `clampInt`
- `clampQuality`
- `formatNumber`
- `formatDate`
- `escapeHtml`
- `normalizeSearchText`
- `nowIso`

- [ ] Mover funciones puras a `core/game-card-utils.js`
- [ ] Dejar `game-cards-v2.js` consumiendo la API nueva
- [ ] Verificar que no cambian resultados ni firmas

### 5. Mover storage base a `core/game-card-storage.js`

Funciones objetivo:

- `readJson`
- `readMigratedJson`
- `readText`
- `writeText`
- `readMigratedText`

Precondición:

- `writeJson` no debe quedarse mezclado con UI si se extrae

- [ ] Separar `writeJson` base de cualquier feedback UI
- [ ] Mover helpers de storage a `core/game-card-storage.js`
- [ ] Dejar wrapper compatible en `game-cards-v2.js`
- [ ] Verificar lectura/escritura de storage en lab

### 6. Mover copy-model a `data/game-card-copy-model.js`

Funciones objetivo:

- `normalizeMoveId`
- `cloneMoveDefinition`
- `normalizeCardMoves`
- `normalizeCopyMoveIds`
- `initialMoveIdsForCopy`
- `highestMoveCheckpoint`
- `addMoveIdsToCopy`
- `ensureCopyMovesForRarity`
- `validCard`
- `normalizeCard`

- [ ] Mover helpers de copy-model a `data/game-card-copy-model.js`
- [ ] Inyectar o resolver correctamente dependencias de reglas
- [ ] Sustituir implementación local en `game-cards-v2.js`
- [ ] Verificar carga de catálogo y creación de copias

### 7. Mover gobernanza a `data/game-card-governance.js`

Funciones objetivo:

- `copyRarity`
- `rarityStatRange`
- `statBoundsForRarity`
- `statPercentInBounds`
- `scaledStatForRarity`
- `retuneCopyStatsForRarity`
- `statForQuality`
- `applyQualityToCopyStats`
- `statsBelowRarityFloor`
- `cardForCopy`
- `totalStats`
- `calculatedQualityScore`
- `copyUpgradedFlag`
- `qualityScore`

- [ ] Mover helpers de rareza/calidad a `data/game-card-governance.js`
- [ ] Sustituir usos en el wrapper
- [ ] Verificar que cartas evolucionadas y mejoradas siguen calculando igual

### 8. Preparar base de reglas en `data/game-card-rules.js`

Funciones objetivo:

- `hasOwn`
- `assignObjectValue`
- `assignArrayValue`
- `normalizeRewardRangeConfig`
- `normalizeDropConfigList`
- `normalizeCombatDifficultyTable`
- `normalizeCombatAdvancedRules`

No mover aún sin refactor mayor:

- `applyGameRulesSettings`
- `applyGameRulesPayload`

- [ ] Mover normalizadores de reglas a `data/game-card-rules.js`
- [ ] Mantener `applyGameRulesSettings` y `applyGameRulesPayload` en wrapper por ahora, o partirlos antes
- [ ] Verificar que reglas siguen cargando igual

### 9. Verificación de Fase 1

- [ ] Verificar que producción sigue cargando `/games/card-game`
- [ ] Verificar que lab sigue cargando `/games/hg-cardgame-dev-lab`
- [ ] Verificar packs
- [ ] Verificar tienda
- [ ] Verificar colección
- [ ] Verificar mobile prompt
- [ ] Verificar imports/exports
- [ ] Verificar que no hay listeners duplicados
- [ ] Verificar que no hay errores JS por namespaces faltantes
- [ ] Verificar sintaxis PHP de archivos tocados

### 10. Cierre de Fase 1

- [ ] Confirmar que `game-cards-v2.js` quedó más fino
- [ ] Confirmar que `core/` y `data/` ya tienen funciones reales migradas
- [ ] Actualizar este documento marcando todos los hitos completados
- [ ] Declarar Fase 1 lista para pasar a Fase 2

---

## Fase 2

Objetivo:

extraer núcleo y datos ya en uso, y empezar a delegar dominios sin romper compatibilidad.

### 1. Partir funciones mixtas antes de moverlas

Funciones candidatas a cirugía:

- `loadCollection`
- `loadGameRules`
- `loadCatalog`
- `renderSummary`

- [ ] Separar cálculo de `loadCollection`
- [ ] Separar persistencia de `loadCollection`
- [ ] Separar render de `loadCollection`
- [ ] Separar aplicación de payload de reglas
- [ ] Separar render global de `renderSummary`

### 2. Consolidar `core/state`

- [ ] Diseñar `core/game-card-state.js`
- [ ] Separar estado shell de estado de dominio
- [ ] Reducir dependencia directa del wrapper sobre globals internos

### 3. Consolidar `data/migrations`

- [ ] Crear `data/game-card-migrations.js`
- [ ] Sacar lógica de migración de calidad
- [ ] Preparar migración explícita de `upgraded`

### 4. Cierre de Fase 2

- [ ] Confirmar que wrapper delega ya parte real del arranque a módulos
- [ ] Confirmar que `core/` y `data/` gobiernan las piezas soberanas
- [ ] Actualizar este documento

---

## Fase 3

Objetivo:

extraer colección y memory de forma usable sin combate.

- [ ] Crear `collection/game-card-collection.js`
- [ ] Crear `collection/game-card-collection-render.js`
- [ ] Crear `collection/game-card-collection-filters.js`
- [ ] Crear `collection/game-card-collection-import-export.js`
- [ ] Crear `memory/game-card-memory.js`
- [ ] Crear `memory/game-card-memory-render.js`
- [ ] Verificar que colección funciona sin cargar combate

---

## Fase 4

Objetivo:

extraer progresión de cartas.

- [ ] Crear `cards/game-card-skills.js`
- [ ] Crear `cards/game-card-improve.js`
- [ ] Crear `cards/game-card-evolve.js`
- [ ] Crear `cards/game-card-recycle.js`
- [ ] Verificar que `skills` puede sobrevivir sin `improve` y `evolve`

---

## Fase 5

Objetivo:

extraer equipos y loadout.

- [ ] Crear `teams/game-card-teams.js`
- [ ] Crear `teams/game-card-loadout.js`
- [ ] Verificar que loadout funciona aunque combate siga parcialmente en wrapper

---

## Fase 6

Objetivo:

extraer combate base.

- [ ] Crear `combat/game-card-combat.js`
- [ ] Crear `combat/game-card-combat-state.js`
- [ ] Crear `combat/game-card-combat-rules.js`
- [ ] Crear `combat/game-card-combat-render.js`
- [ ] Crear `combat/game-card-combat-animations.js`
- [ ] Crear `combat/game-card-combat-ai.js`
- [ ] Verificar que el motor base funciona sin conocimiento de modos específicos

---

## Fase 7

Objetivo:

extraer modos de combate.

- [ ] Crear `combat/modes/game-card-combat-training.js`
- [ ] Crear `combat/modes/game-card-combat-daily-boss.js`
- [ ] Verificar secuencia:
  - motor base
  - estado base
  - adaptador de modo
  - render
  - turno

---

## Fase 8

Objetivo:

dejar el sistema listo para nuevos modos y para retirar el wrapper.

- [ ] Añadir registro formal de modos
- [ ] Dejar `game-cards-v2.js` como wrapper mínimo
- [ ] Eliminar el wrapper sólo cuando todas las rutas usen loader modular real

---

## Lista de funciones que NO mover primero

No empezar una fase extrayendo estas funciones tal cual:

- `renderSummary`
- `loadCollection`
- `loadGameRules`
- `loadCatalog`
- `openPack`
- `buyPack`
- `claimWorkRewards`
- `startTrainingCombat`
- `startDailyBossCombat`
- `applySkillRoll`
- `applyRarityUpgrade`
- `applyQualityUpgrade`
- `recycleCopy`
- `sellCardsByRarity`
- `importCollection`
- `bindCollectionControls`
- `bindEvents`

Antes de moverlas, partirlas en:

- cálculo
- persistencia
- render
- wiring de eventos

---

## Definition of done

No se considera terminada una fase si:

- sólo se han creado archivos vacíos
- el wrapper sigue duplicando la lógica migrada
- no se ha validado lab
- no se han marcado los hitos `[x]`

Se considera terminada una fase cuando:

- los hitos de esta fase están marcados `[x]`
- el comportamiento sigue igual o mejor
- el wrapper antiguo ha perdido responsabilidad real
- el siguiente corte puede empezar sin reanalizar toda la base
