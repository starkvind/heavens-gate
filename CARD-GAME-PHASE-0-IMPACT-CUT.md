# Card Game Phase 0 Impact Cut

Segundo artefacto de Fase 0 para `assets/js/game-cards-v2.js`.

Este documento no reexplica los dominios. Corta el monolito por tipo de impacto operativo:

- soberanía sobre `collection`
- soberanía sobre `combat`
- render/UI
- funciones mixtas que hoy mezclan cálculo, persistencia y render

## Conteo Heurístico

Clasificación heurística sobre `450` funciones declaradas:

- funciones con impacto sobre `collection`: `68`
- funciones con impacto sobre `combat`: `85`
- funciones con impacto de render/UI: `127`
- funciones mixtas de alto riesgo: `104`

No es una taxonomía perfecta. Es un corte práctico para decidir extracción incremental.

## 1. Soberanía sobre `collection`

Estas funciones no deberían quedarse repartidas cuando empecemos Fase 1 y Fase 2. Son el corazón de la colección persistida del jugador.

### Escritura/normalización principal

- `loadCollection()` `2717`
- `saveCollection()` `2743`
- `migrateCollectionQuality()` `3291`
- `importCollection()` `8009`
- `resetCollection()` `8038`

### Mutadores de moneda, materiales y packs

- `addMnemones()` `1505`
- `addRemorias()` `1518`
- `addMaterial()` `1536`
- `consumeMaterial()` `1545`
- `consumePack()` `1884`
- `addPack()` `1891`
- `openPack()` `2941`
- `buyPack()` `2988`
- `buyMaterial()` `3044`
- `claimShopDailyGift()` `3078`
- `buyRemoriaExchange()` `3100`
- `openAllPacks()` `3120`

### Mutadores de rememoración

- `claimWorkRewards()` `1774`
- `assignCopyToWork()` `1806`
- `stopCopyWork()` `1845`

### Mutadores de skills/progresión

- `applySkillRoll()` `4946`
- `applyRarityUpgrade()` `7174`
- `applyQualityUpgrade()` `7532`

### Mutadores de reciclaje/venta/favoritos

- `toggleFavoriteCopy()` `7608`
- `recycleCopy()` `7634`
- `recycleDuplicateCopies()` `7677`
- `recycleAllCopies()` `7721`
- `sellCardsByRarity()` `7827`

### Mutadores indirectos desde daily boss

- `destroyDailyBossCopies()` `865`
- `destroyDailyBossDefeatedCards()` `897`
- `awardTrainingVictory()` `5060`
- `awardDailyBossVictory()` `5133`

## 2. Soberanía sobre `combat`

Estas funciones gobiernan estado de combate, equipos o daily boss. No deberían salir mezcladas con colección ni con render de tabla.

### Equipos y perfil

- `loadCombatTeams()` `3830`
- `saveCombatTeams()` `3839`
- `cleanCombatTeamsAgainstCollection()` `3868`
- `removeCopiesFromCombatTeams()` `3896`
- `loadCombatProfile()` `3921`
- `saveCombatProfile()` `3928`
- `saveDraftCombatTeam()` `4369`

### Setup y modos

- `startTrainingCombat()` `4614`
- `startDailyBossCombat()` `6768`
- `startSelectedCombat()` `6839`
- `renderDailyBossSummary()` `4255`
- `updateCombatModeButtons()` `4305`
- `renderCombatSetup()` `4325`

### Estado daily boss

- `loadDailyBossState()` `784`
- `saveDailyBossState()` `795`
- `resetDailyBossState()` `801`
- `startDailyBossAttempt()` `841`
- `finishDailyBossAttempt()` `917`
- `interruptDailyBossCombat()` `926`
- `recoverAbandonedDailyBossAttempt()` `939`

### Motor base

- `createCombatUnit()` `4399`
- `applyCombatModifier()` `4734`
- `combatDamageForAttackValue()` `4783`
- `combatDamage()` `4797`
- `combatMoveDamage()` `4801`
- `applyMoveEffect()` `4812`
- `playerAttack()` `5243`
- `playerDefend()` `5281`
- `playerUseMove()` `5308`
- `switchPlayerCard()` `5425`
- `fleeCombat()` `5459`
- `enemyTurn()` `5597`
- `completePlayerTurn()` `5692`
- `resolveDefeatedSide()` `5906`

## 3. Renderizadores y UI Orchestrators

Aquí la separación importante no es “qué pintan”, sino “qué no deberían mutar”.

### Shell y navegación

- `setStatus()` `319`
- `decorateIconNavigation()` `334`
- `updateDesktopNavActive()` `366`
- `updateDesktopHashPanels()` `382`
- `renderMobileSwitchPrompt()` `8078`
- `bindMobilePanels()` `8093`

### Packs, tienda y resumen

- `renderDailyCounter()` `1176`
- `renderPackInventory()` `1914`
- `renderShop()` `2001`
- `renderPackResults()` `3150`
- `renderSummary()` `3340`

### Colección

- `renderAlbumTabs()` `3480`
- `renderCollectionTypeFilter()` `3504`
- `renderAlbumSlot()` `3520`
- `renderPagination()` `3632`
- `renderAlbum()` `3671`
- `renderCollectionTable()` `3738`

### Combate

- `renderCombatTeamSelect()` `4056`
- `renderCombatTeamPreview()` `4078`
- `renderCombatProfile()` `4115`
- `renderCombatCardList()` `4162`
- `renderCombatActionState()` `5942`
- `renderCombatMoveSlots()` `5967`
- `renderCombatUnit()` `5999`
- `renderCombatBench()` `6050`
- `renderCombatEndOverlay()` `6094`
- `renderCombatBattle()` `6170`
- `renderCombat()` `6191`

### Modales y overlays

- `showPackReveal()` `6405`
- `showCardModal()` `6555`
- `showRarityUpgradeModal()` `6925`
- `showQualityUpgradeModal()` `7312`

## 4. Funciones Mixtas de Alto Riesgo

Estas son las funciones que no conviene “mover de bloque” sin partirlas antes.

### Grupo A. Cálculo + persistencia + render

- `claimWorkRewards()` `1774`
- `assignCopyToWork()` `1806`
- `stopCopyWork()` `1845`
- `openPack()` `2941`
- `buyPack()` `2988`
- `buyMaterial()` `3044`
- `claimShopDailyGift()` `3078`
- `buyRemoriaExchange()` `3100`
- `applySkillRoll()` `4946`
- `awardTrainingVictory()` `5060`
- `awardDailyBossVictory()` `5133`
- `applyRarityUpgrade()` `7174`
- `applyQualityUpgrade()` `7532`
- `recycleCopy()` `7634`
- `recycleDuplicateCopies()` `7677`
- `recycleAllCopies()` `7721`
- `sellCardsByRarity()` `7827`
- `importCollection()` `8009`
- `resetCollection()` `8038`

### Grupo B. Setup mixto con múltiples dominios

- `loadCatalog()` `2446`
- `loadGameRules()` `2687`
- `loadCollection()` `2717`
- `renderSummary()` `3340`
- `startTrainingCombat()` `4614`
- `startDailyBossCombat()` `6768`

### Grupo C. Bootstrap acoplado

- `bindCollectionControls()` `8140`
- `bindEvents()` `8345`

Conclusión:

Estas funciones son candidatas a refactor previo, no a extracción directa.

## 5. Botellas de Acoplamiento Confirmadas

### `renderSummary()` `3340`

Hoy dispara:

- `renderDailyCounter()`
- `renderShop()`
- `renderBulkSellPreview()`
- `renderWorkBench()`
- `renderCombatProfile()`
- `renderCombatSetup()`

Esto la convierte en un “hub” transversal. No debe migrarse tal cual.

### `bindCollectionControls()` `8140`

Mezcla eventos de:

- colección
- filtros
- loadout de combate
- modo de combate
- perfil de combate
- workbench

Debe romperse por dominios antes de modularizar bootstrap.

### `bindEvents()` `8345`

Concentra delegación global de:

- apertura de packs
- abrir todos
- tienda de sobres
- compra de materiales
- exchange de Remorias
- daily gift
- export/import
- borrado
- venta masiva

Debe terminar en bootstrap + registros por dominio.

## 6. Regla de Extracción Refinada

### Sí se pueden extraer pronto

Funciones casi seguras para `core/` y `data/`:

- utilidades puras
- normalizadores
- helpers de rareza y calidad sin side effects
- helpers de copy-model
- helpers de settings payload

### No extraer aún sin cirugía

- `renderSummary()`
- `loadCollection()`
- `loadGameRules()`
- `openPack()`
- `buyPack()`
- `startTrainingCombat()`
- `startDailyBossCombat()`
- `applySkillRoll()`
- `applyRarityUpgrade()`
- `applyQualityUpgrade()`
- `recycleCopy()`
- `sellCardsByRarity()`
- `importCollection()`
- `bindCollectionControls()`
- `bindEvents()`

## 7. Corte Operativo para la Siguiente Actualización

La siguiente tanda útil ya no debería ser más inventario general. Debería ser una tabla concreta de funciones candidatas a extracción inmediata a:

- `core/game-card-utils.js`
- `core/game-card-storage.js`
- `core/game-card-state.js`
- `data/game-card-copy-model.js`
- `data/game-card-governance.js`

Prioridad de análisis fino:

1. funciones puras sin DOM ni storage
2. funciones de storage sin render
3. funciones de copy-model sin UI
4. hubs mixtos que requieren partición previa
