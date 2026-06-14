# Card Game Phase 1 Extraction Shortlist

Documento operativo derivado de Fase 0.

Objetivo: definir qué se puede extraer ya de `assets/js/game-cards-v2.js` al árbol modular sin cambiar comportamiento ni abrir regresiones innecesarias.

## Principio

En Fase 1 no se extraen todavía dominios con UI compleja. Se extraen:

- utilidades puras
- helpers de storage base
- helpers de normalización
- helpers de copy-model y gobernanza de rareza/calidad
- bootstrap mínimo para registrar API modular

`game-cards-v2.js` debe seguir siendo el wrapper vivo durante esta fase.

## Lote 1A: `core/game-card-utils.js`

Funciones seguras para mover casi tal cual:

- `nowIso()` `507`
- `clampInt()` `511`
- `clampQuality()` `517`
- `formatNumber()` `1525`
- `formatDate()` `8060`
- `escapeHtml()` `8066`
- `normalizeSearchText()` `620`
- `normalizeStorageScope()` `7`

Funciones movibles con revisión menor:

- `scopedStorageKey()` `11`
  Requisito:
  No debe leer `root` directamente en el módulo. Debe aceptar `storageScope` como argumento o leerlo desde `HGCardGame.bootstrap.env`.

No mover aún:

- `setStatus()`
- `uiText()`
- cualquier helper que consulte `document`

## Lote 1B: `core/game-card-storage.js`

Funciones candidatas:

- `readJson()` `547`
- `readMigratedJson()` `564`
- `readText()` `575`
- `writeText()` `584`
- `readMigratedText()` `590`

Micro-refactor obligatorio antes de extraer:

- `writeJson()` `556`

Motivo:

Ahora mezcla storage y feedback UI porque invoca `setStatus()` y `uiText()`.

Cambio recomendado:

1. crear una versión base que sólo haga storage y devuelva `true/false`
2. dejar el mensaje UI en el wrapper monolítico o en un adaptador de app

Contrato deseado:

- `readJson(key, fallback)`
- `writeJson(key, value) -> boolean`
- `readText(key, fallback)`
- `writeText(key, value) -> boolean`
- `readMigratedJson(key, legacyKey, fallback)`
- `readMigratedText(key, legacyKey, fallback)`

## Lote 1C: `core/game-card-state.js`

No mover aún el objeto `state` completo.

Sí preparar:

- factoría o inicializador mínimo de estado shell
- factoría de preferencias de colección si se separa de DOM

No extraer todavía:

- `loadCollectionViewPrefs()` `633`

Motivo:

Lee storage, muta `state` y además sincroniza `els`.

Precondición para moverlo después:

- dividir en:
  - lector de prefs
  - normalizador de prefs
  - aplicador a estado
  - sincronizador de UI

## Lote 1D: `data/game-card-copy-model.js`

Funciones seguras o casi seguras para mover:

- `normalizeMoveId()` `2290`
- `cloneMoveDefinition()` `2295`
- `normalizeCardMoves()` `2321`
- `normalizeCopyMoveIds()` `2354`
- `initialMoveIdsForCopy()` `2366`
- `highestMoveCheckpoint()` `2385`
- `addMoveIdsToCopy()` `2390`
- `ensureCopyMovesForRarity()` `2403`
- `validCard()` `2284`
- `normalizeCard()` `2417`

Observación:

Estas funciones dependen de constantes globales como:

- `MOVE_LIBRARY`
- `MOVE_LEARN_RULES`
- `RARITY_ORDER`

Por tanto, al extraerlas no deben leer variables sueltas del archivo. Deben recibir dependencias o consumirlas desde `HGCardGame.data.rules`.

## Lote 1E: `data/game-card-governance.js`

Funciones candidatas:

- `copyRarity()` `3175`
- `rarityStatRange()` `3179`
- `statBoundsForRarity()` `3183`
- `statPercentInBounds()` `3193`
- `scaledStatForRarity()` `3200`
- `retuneCopyStatsForRarity()` `3207`
- `statForQuality()` `3216`
- `applyQualityToCopyStats()` `3222`
- `statsBelowRarityFloor()` `3232`
- `cardForCopy()` `3239`
- `totalStats()` `3261`
- `calculatedQualityScore()` `3266`
- `copyUpgradedFlag()` `3279`
- `qualityScore()` `3286`

No mover aún:

- `migrateCollectionQuality()` `3291`

Motivo:

Ya muta `state.collection`, lee `state.catalogById` y persiste cambios. Primero hay que partirla en:

- cálculo de flags `upgraded`
- aplicación sobre una lista de copias
- persistencia externa

## Lote 1F: `data/game-card-rules.js`

Funciones candidatas con cuidado:

- `hasOwn()` `2492`
- `assignObjectValue()` `2496`
- `assignArrayValue()` `2500`
- `normalizeRewardRangeConfig()` `2504`
- `normalizeDropConfigList()` `2511`
- `normalizeCombatDifficultyTable()` `2524`
- `normalizeCombatAdvancedRules()` `2538`

Mover más tarde dentro de Fase 1:

- `applyGameRulesSettings()` `2556`
- `applyGameRulesPayload()` `2633`

Motivo:

No son puras porque hoy escriben masivamente sobre variables globales del monolito. Antes de extraerlas hay que definir el contenedor de reglas/config destino.

## Funciones que requieren partición previa

No iniciar Fase 1 con estas:

- `loadCatalog()` `2446`
- `loadGameRules()` `2687`
- `loadCollection()` `2717`
- `renderSummary()` `3340`
- `openPack()` `2941`
- `buyPack()` `2988`
- `claimWorkRewards()` `1774`
- `startTrainingCombat()` `4614`
- `startDailyBossCombat()` `6768`
- `applySkillRoll()` `4946`
- `applyRarityUpgrade()` `7174`
- `applyQualityUpgrade()` `7532`
- `recycleCopy()` `7634`
- `sellCardsByRarity()` `7827`
- `importCollection()` `8009`
- `bindCollectionControls()` `8140`
- `bindEvents()` `8345`

## Primer objetivo real de Fase 1

Al terminar el primer lote, el repo debería tener:

- `assets/js/card-game/core/game-card-utils.js`
- `assets/js/card-game/core/game-card-storage.js`
- `assets/js/card-game/data/game-card-copy-model.js`
- `assets/js/card-game/data/game-card-governance.js`
- `assets/js/card-game/data/game-card-rules.js`

Y `game-cards-v2.js` debería:

- importar o consumir esas APIs
- mantener mismo orden de arranque
- no cambiar rutas ni comportamiento visible

## Definición de éxito del primer lote

- no cambia la URL ni el bootstrap de producción
- no cambia la URL ni el bootstrap del lab
- no cambian las claves de storage ya activas
- colección sigue cargando igual
- catálogo sigue cargando igual
- packs, tienda y colección siguen funcionando como antes
- no aparecen dobles listeners
- `game-cards-v2.js` reduce responsabilidad, aunque siga vivo
