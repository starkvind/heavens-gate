# Card Game Phase 0 Dependency Map

Primer artefacto de Fase 0 para `assets/js/game-cards-v2.js`.

## Snapshot

- Fichero auditado: `assets/js/game-cards-v2.js`
- Tamaño: `7867` líneas
- Funciones declaradas detectadas: `450`
- Estado global principal: `state` en línea `185`
- Registro global de nodos DOM: `els` en línea `223`
- Export público actual: `window.hgGameCards` en línea `8425`

## Arranque real actual

Secuencia observada al final del monolito:

1. `loadCollectionViewPrefs()`
2. `decorateIconNavigation()`
3. `updateDesktopHashPanels()`
4. `window.addEventListener('hashchange', updateDesktopHashPanels)`
5. `bindEvents()`
6. `loadGameRules()`
7. `loadCollection()`
8. `renderMobileSwitchPrompt()`
9. `renderDailyCounter()`
10. `renderPackInventory()`
11. `startShopStateTimer()`
12. `loadCatalog()`

Esto confirma que el orden actual depende de reglas antes de colección y catálogo, y de colección antes de varios renders derivados.

## Side Effects Compartidos

### Persistencia local

Puntos base:

- `readJson()` `547`
- `writeJson()` `556`
- `readText()` `575`
- `writeText()` `584`
- `resetCollection()` `8038`

Persistencias activas detectadas:

- colección
- tienda
- preferencias de vista
- equipos de combate
- perfil de combate
- jefe diario
- aviso de móvil

### Red

Fetches principales:

- `loadCatalog()` `2446`
- `loadGameRules()` `2687`

### Eventos globales

Listeners globales detectados:

- `keydown` para modales y overlays
- `document.click` delegado para packs, tienda y acciones globales
- `window.hashchange` para navegación desktop
- `setInterval` en `startShopStateTimer()` `8335`

## Estado Compartido que Más Acopla

### `state`

Áreas más sensibles dentro de `state`:

- `collection`
- `catalog`
- `shopState`
- `combat`
- `combatTeams`
- `combatProfile`
- `dailyBoss`
- flags de filtros de colección
- flags de pantalla y modo de combate

### `els`

`els` mezcla en un único registro:

- packs
- tienda
- colección
- rememoración
- mobile tabs
- preparación de equipo
- arena de combate
- overlays y paneles

Conclusión: hoy no hay frontera de DOM por dominio. El fichero asume que puede consultar todo el árbol de UI desde el mismo ámbito.

## Mapa por Dominio

### 1. Core / Utilidades / Shell UI

Rango principal:

- `7-633`
- `2492-2556`
- `8060-8072`

Funciones clave:

- scope y storage: `normalizeStorageScope`, `scopedStorageKey`
- utilidades puras: `clampInt`, `clampQuality`, `normalizeTimestamp`, `formatNumber`, `formatDate`, `escapeHtml`
- feedback UI: `setStatus`, `uiText`
- navegación shell: `decorateIconNavigation`, `updateDesktopNavActive`, `updateDesktopHashPanels`
- confirm modal genérico: `confirmGameAction`

Destino propuesto:

- `core/game-card-utils.js`
- `core/game-card-config.js`
- `core/game-card-dom.js`
- `core/game-card-storage.js`

### 2. Datos, reglas y gobernanza de copias

Rango principal:

- `2284-2417`
- `2446-2743`
- `3175-3291`

Funciones clave:

- normalización catálogo: `validCard`, `normalizeCard`
- normalización de habilidades/copies: `normalizeCardMoves`, `normalizeCopyMoveIds`, `initialMoveIdsForCopy`, `ensureCopyMovesForRarity`
- carga remota: `loadCatalog`, `loadGameRules`
- aplicación de reglas: `applyGameRulesSettings`, `applyGameRulesPayload`
- soberanía de copia: `copyRarity`, `retuneCopyStatsForRarity`, `applyQualityToCopyStats`, `copyUpgradedFlag`
- migración existente: `migrateCollectionQuality`

Riesgo detectado:

- la gobernanza de copia del jugador está dispersa entre carga de reglas, normalización de carta, calidad, rareza y skills.

Destino propuesto:

- `data/game-card-rules.js`
- `data/game-card-catalog.js`
- `data/game-card-copy-model.js`
- `data/game-card-governance.js`
- `data/game-card-migrations.js`

### 3. Tienda, packs y economía

Rango principal:

- `664-1079`
- `1111-1176`
- `1176-2242`
- `2754-3150`

Funciones clave:

- tienda: `createShopState`, `loadShopState`, `saveShopState`, `syncShopState`
- regalos diarios: `dailyFreePacksRemaining`, `claimDailyFreePacks`, `claimDailyGift`
- layout dinámico: `buildShopGroup`, `ensurePackLayout`, `ensureDynamicShopItems`, `ensureShopLayout`
- inventario de packs: `packStock`, `consumePack`, `addPack`, `renderPackInventory`
- transacciones: `buyPack`, `buyMaterial`, `claimShopDailyGift`, `buyRemoriaExchange`, `openAllPacks`

Riesgo detectado:

- tienda mezcla reloj diario, render, economía y acceso a colección.

Destino propuesto:

- `shop/game-card-shop.js`
- `shop/game-card-shop-render.js`
- `shop/game-card-currency.js`
- `shop/game-card-materials.js`
- `packs/game-card-packs.js`
- `packs/game-card-pack-reveal.js`

### 4. Rememoración / Memory

Rango principal:

- `1089-1107`
- `1457-1845`

Funciones clave:

- asignaciones: `normalizeWorkAssignments`, `ensureWorkAssignments`, `cleanWorkAssignments`, `limitWorkAssignments`
- cálculo: `workRatePerMinute`, `workEntryFromAssignment`, `activeWorkEntries`, `totalWorkClaimable`
- UI: `renderWorkBench`
- mutaciones: `claimWorkRewards`, `assignCopyToWork`, `stopCopyWork`

Riesgo detectado:

- rememoración toca directamente economía y colección persistida.

Destino propuesto:

- `memory/game-card-memory.js`
- `memory/game-card-memory-render.js`

### 5. Colección y vistas

Rango principal:

- `3162-3770`
- `7897-8038`

Funciones clave:

- agrupación y filtros: `collectionGroups`, `collectionFilterSets`, `cardPassesCollectionFilters`, `albumCategories`
- render: `renderAlbumTabs`, `renderAlbum`, `renderCollectionTable`, `renderPagination`
- contexto: `activeMobilePanel`, `isCollectionContext`, `isMemoryContext`
- IO: `exportCollection`, `validateCollection`, `importCollection`, `resetCollection`

Riesgo detectado:

- filtros de colección dependen de equipos de combate y estados de rememoración.

Destino propuesto:

- `collection/game-card-collection.js`
- `collection/game-card-collection-render.js`
- `collection/game-card-collection-filters.js`
- `collection/game-card-collection-import-export.js`

### 6. Teams / Loadout

Rango principal:

- `3785-4383`

Funciones clave:

- equipos: `createEmptyCombatTeams`, `normalizeCombatTeams`, `loadCombatTeams`, `saveCombatTeams`
- perfil: `normalizeCombatProfile`, `loadCombatProfile`, `saveCombatProfile`
- limpieza contra colección: `cleanCombatTeamsAgainstCollection`, `removeCopiesFromCombatTeams`
- UI loadout: `renderCombatTeamSelect`, `renderCombatTeamSlots`, `renderCombatCardList`, `autoBuildCombatTeam`

Riesgo detectado:

- el loadout depende de colección, skills aprendidas y jefe diario.

Destino propuesto:

- `teams/game-card-teams.js`
- `teams/game-card-loadout.js`

### 7. Combate base

Rango principal:

- `4383-6191`

Funciones clave:

- setup: `combatDifficultyConfig`, `createCombatUnit`, `createMoveState`
- reglas: `combatDamage`, `combatMoveDamage`, `applyMoveEffect`, `applyCombatModifier`, `healCombatUnit`, `breakCombatShields`
- turnos: `playerAttack`, `playerDefend`, `playerUseMove`, `switchPlayerCard`, `enemyTurn`
- IA: `enemyUsableMoves`, `enemyMoveScore`, `pickEnemyMove`, `enemySwitchScore`, `pickEnemySwitch`
- render/HUD: `renderCombatActionState`, `renderCombatMoveSlots`, `renderCombatUnit`, `renderCombatBench`, `renderCombatBattle`, `renderCombat`
- animación/VFX: `animateCombatAttack`, `spawnCombatOrb`, `playMoveVfx`, `playCombatRivalIntro`

Riesgo detectado:

- combate comparte datos de habilidades, rareza, recompensas, daily boss y colección.

Destino propuesto:

- `combat/game-card-combat.js`
- `combat/game-card-combat-state.js`
- `combat/game-card-combat-rules.js`
- `combat/game-card-combat-render.js`
- `combat/game-card-combat-animations.js`
- `combat/game-card-combat-ai.js`

### 8. Modos de combate

Rango principal:

- `688-949`
- `4255-4296`
- `4597-4614`
- `5024-5174`
- `6768-6843`

Funciones clave:

- daily boss state: `createDailyBossState`, `loadDailyBossState`, `saveDailyBossState`, `recoverAbandonedDailyBossAttempt`
- training setup: `startTrainingCombat`, `createEnemyCard`, `createTrainingRivalProfile`
- daily boss setup y recompensas: `pickDailyBossCard`, `createDailyBoss`, `awardDailyBossLoot`, `awardDailyBossVictory`, `startDailyBossCombat`
- selector de modo: `updateCombatModeButtons`, `renderDailyBossSummary`, `startSelectedCombat`

Riesgo detectado:

- el modo daily boss modifica colección, destruye copias, altera equipos y persiste estado propio.

Destino propuesto:

- `combat/modes/game-card-combat-training.js`
- `combat/modes/game-card-combat-daily-boss.js`

### 9. Skills / Improve / Evolve / Recycle / Favorites

Rango principal:

- `4852-5003`
- `6717-7532`
- `7581-7827`

Funciones clave:

- skills: `copyMoveDefinitions`, `skillCostMultiplier`, `availableSkillMoveIds`, `applySkillRoll`, `confirmSkillRoll`
- evolve: `nextRarity`, `rarityUpgradeCandidates`, `showRarityUpgradeModal`, `applyRarityUpgrade`
- improve: `qualityUpgradeCandidates`, `showQualityUpgradeModal`, `applyQualityUpgrade`
- recycle/favorites: `recycleCopy`, `recycleDuplicateCopies`, `recycleAllCopies`, `toggleFavoriteCopy`, `sellCardsByRarity`

Riesgo detectado:

- estos subdominios comparten directamente colección, tienda/economía, equipos y a veces daily boss.

Destino propuesto:

- `cards/game-card-skills.js`
- `cards/game-card-improve.js`
- `cards/game-card-evolve.js`
- `cards/game-card-recycle.js`
- `collection/game-card-favorites.js`

### 10. Modales y UI transversal

Rango principal:

- `6198-6597`
- `6405-6549`
- `6555-7873`
- `8078-8345`

Funciones clave:

- cartas y modales: `renderCard`, `showPackReveal`, `showCardModal`, `renderCopyList`
- carrusel mobile: `renderMobileCardCarousel`
- navegación/paneles: `renderMobileSwitchPrompt`, `bindMobilePanels`, `bindCollectionControls`

Riesgo detectado:

- varios modales contienen subfunciones locales que además mutan dominio, no sólo interfaz.

Destino propuesto:

- `bootstrap/game-card-app.js`
- `core/game-card-dom.js`
- módulos render específicos por dominio

## Acoplamientos Críticos Confirmados

1. `loadGameRules()` gobierna constantes y UI antes de `loadCollection()` y `loadCatalog()`.
2. `copyRarity()`, `copyUpgradedFlag()` y `retuneCopyStatsForRarity()` son piezas soberanas de gobernanza y hoy están lejos de la capa de storage.
3. `cleanCombatTeamsAgainstCollection()` demuestra que la colección muta estructuras de equipos.
4. `destroyDailyBossDefeatedCards()` y `awardDailyBossVictory()` prueban que daily boss toca colección persistida.
5. `refreshCollectionViews()` se usa como puente transversal entre skills, upgrades, recycle y equipos.
6. `bindEvents()` concentra demasiados comandos globales y es un cuello de botella de bootstrap.

## Primer Corte Recomendado para Fase 1

El corte más seguro sigue siendo:

1. extraer `core`: config, utils, storage, state shell
2. extraer `data`: rules payload, catalog normalizer, copy-model, governance helpers
3. dejar `game-cards-v2.js` delegando en esos módulos nuevos

Funciones candidatas para el primer lote:

- `normalizeStorageScope`
- `scopedStorageKey`
- `clampInt`
- `clampQuality`
- `readJson`
- `writeJson`
- `readText`
- `writeText`
- `readMigratedJson`
- `readMigratedText`
- `normalizePageSize`
- `normalizeCollectionRarity`
- `normalizeSearchText`
- `normalizeRarity`
- `validCard`
- `normalizeMoveId`
- `cloneMoveDefinition`
- `normalizeCardMoves`
- `normalizeCopyMoveIds`
- `initialMoveIdsForCopy`
- `highestMoveCheckpoint`
- `addMoveIdsToCopy`
- `ensureCopyMovesForRarity`
- `normalizeCard`
- `hasOwn`
- `assignObjectValue`
- `assignArrayValue`
- `applyGameRulesSettings`
- `applyGameRulesPayload`
- `copyRarity`
- `copyUpgradedFlag`

## Siguiente Punto de Trabajo

En la siguiente actualización de Fase 0 conviene hacer una tabla más fina de:

- funciones que sólo leen estado
- funciones que mutan `collection`
- funciones que mutan `combat`
- funciones que disparan render
- funciones que mezclan cálculo + persistencia + render

Ese corte permitirá decidir qué sale primero a `core/` y `data/` sin mover accidentalmente dependencias de UI.
