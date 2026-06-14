# CARD-GAME NEXT BIG UPDATE

## Objetivo

Migrar [`assets/js/game-cards-v2.js`](assets/js/game-cards-v2.js) desde un único fichero monolítico a una arquitectura modular en `/assets/js/card-game/`, con gobernanza explícita de datos, orden de carga determinista y capacidad real de activar o desactivar módulos de juego sin romper el resto del sistema.

Este documento no define código final. Define el plan de ejecución, la arquitectura objetivo y las reglas que debe seguir un LLM agéntico al hacer la migración.

---

## Restricciones

- No se debe reescribir todo de golpe.
- La migración debe ser incremental y reversible.
- El comportamiento existente debe mantenerse durante la transición.
- El catálogo de cartas y la colección del jugador deben separarse conceptualmente y en código.
- Ningún módulo puede mutar datos que no le pertenezcan.
- `Mejora` y `Evolución` deben poder quitarse sin romper `Habilidades`, `Colección`, `Combate` ni `Tienda`.

---

## Diagnóstico del fichero actual

`game-cards-v2.js` mezcla en un solo ámbito:

- configuración global
- estado global
- persistencia local
- catálogo
- colección
- tienda
- sobres
- rememoración
- filtros y render de colección
- composición de equipos
- combate base
- entrenamiento
- jefe diario
- habilidades
- mejora de calidad
- evolución de rareza
- reciclaje
- import/export
- eventos globales
- bootstrap

### Problemas actuales

1. El estado está centralizado, pero no gobernado.
   No hay fronteras claras entre datos de catálogo, datos del jugador, datos de sesión de combate y datos UI.

2. La carga del catálogo provoca efectos colaterales sobre la colección.
   Ya se ha visto con rarezas evolucionadas y re-seed del catálogo.

3. Los módulos no son desacoplables.
   Quitar evolución, mejora o combate implica tocar zonas no aisladas.

4. El orden de inicialización es implícito.
   Hay dependencias invisibles entre `loadGameRules()`, `loadCollection()`, `loadCatalog()`, renderizadores y migraciones.

5. La lógica de dominio y la lógica de interfaz están mezcladas.
   Muchas funciones hacen cálculo, persistencia y render a la vez.

6. No existe capa formal de servicios ni contratos entre módulos.

---

## Principio rector

El sistema debe separarse en módulos orientados a dominio, no por conveniencia visual ni por “trozos del fichero”.

La división correcta es:

- núcleo
- datos
- catálogo
- colección
- progresión de cartas
- combate
- UI/arranque

---

## Carpeta objetivo

Crear:

`/assets/js/card-game/`

### Árbol propuesto

```text
/assets/js/card-game/
  bootstrap/
    game-card-app.js
    game-card-loader.js
    game-card-features.js

  core/
    game-card-state.js
    game-card-events.js
    game-card-utils.js
    game-card-storage.js
    game-card-config.js
    game-card-dom.js

  data/
    game-card-rules.js
    game-card-catalog.js
    game-card-copy-model.js
    game-card-governance.js
    game-card-migrations.js

  collection/
    game-card-collection.js
    game-card-collection-filters.js
    game-card-collection-render.js
    game-card-collection-import-export.js
    game-card-favorites.js

  packs/
    game-card-packs.js
    game-card-pack-reveal.js

  shop/
    game-card-shop.js
    game-card-shop-render.js
    game-card-currency.js
    game-card-materials.js

  memory/
    game-card-memory.js
    game-card-memory-render.js

  teams/
    game-card-teams.js
    game-card-loadout.js

  cards/
    game-card-skills.js
    game-card-improve.js
    game-card-evolve.js
    game-card-recycle.js

  combat/
    game-card-combat.js
    game-card-combat-state.js
    game-card-combat-rules.js
    game-card-combat-render.js
    game-card-combat-animations.js
    game-card-combat-ai.js
    modes/
      game-card-combat-training.js
      game-card-combat-daily-boss.js
      game-card-combat-dungeon.js
      game-card-combat-gauntlet.js
```

---

## División funcional obligatoria

### 1. Configuración

Fichero objetivo:

- `core/game-card-config.js`

Responsabilidad:

- constantes globales
- claves de `localStorage`
- timers
- defaults
- feature flags
- nombres de versión/cache-busting si se quiere centralizar

No debe contener:

- lógica de negocio
- render
- eventos DOM

### 2. Gobernanza de datos

Ficheros objetivo:

- `data/game-card-governance.js`
- `data/game-card-copy-model.js`
- `data/game-card-migrations.js`

Responsabilidad:

- definir qué datos son de catálogo y cuáles son de colección
- normalizar copias del jugador
- migrar versiones de colección
- proteger datos no resembrables

Debe contener reglas explícitas como:

- `catalog` es solo lectura en cliente
- `collection.ownedCards` es solo escritura a través de servicios de colección/progresión
- `combat` nunca modifica `catalog`
- `seed` o refresco de catálogo nunca reescribe rarezas progresadas del jugador
- toda copia con `upgraded = true` es inmutable respecto a su rareza base de catálogo

### 3. Colección

Ficheros objetivo:

- `collection/game-card-collection.js`
- `collection/game-card-collection-render.js`
- `collection/game-card-collection-filters.js`
- `collection/game-card-collection-import-export.js`
- `collection/game-card-favorites.js`

Responsabilidad:

- carga y guardado de colección
- render de álbum/tabla
- filtros
- favoritos
- import/export

No debe conocer combate interno ni reglas de Jefe diario.

### 4. Mejora

Fichero objetivo:

- `cards/game-card-improve.js`

Responsabilidad:

- mejora de calidad
- costes
- sacrificios
- mutación de stats por calidad

Debe poder desactivarse sin romper:

- colección
- render de cartas
- combate
- habilidades

### 5. Evolución

Fichero objetivo:

- `cards/game-card-evolve.js`

Responsabilidad:

- evolución de rareza
- costes
- sacrificios
- retune de stats
- marcado `upgraded`

Regla crítica:

- toda evolución debe marcar la copia con `upgraded = true`
- ese flag nunca debe ser borrado por migraciones ordinarias

### 6. Habilidades

Fichero objetivo:

- `cards/game-card-skills.js`

Responsabilidad:

- aprendizaje/cambio de habilidades
- coste de habilidad
- slots
- reglas por rareza

Requisito:

- debe vivir separado de mejora y evolución
- debe poder mantenerse aunque se eliminen `game-card-improve.js` y `game-card-evolve.js`

### 7. Combate

Ficheros objetivo:

- `combat/game-card-combat.js`
- `combat/game-card-combat-state.js`
- `combat/game-card-combat-rules.js`
- `combat/game-card-combat-render.js`
- `combat/game-card-combat-animations.js`
- `combat/game-card-combat-ai.js`

Responsabilidad:

- sistema base de turnos
- unidades
- daño
- buffs/debuffs
- cambios
- animaciones
- render del HUD
- IA

Regla:

- `combat/` contiene motor base
- `combat/modes/` contiene adaptación por modo

### 8. Modos de combate

Ficheros objetivo:

- `combat/modes/game-card-combat-training.js`
- `combat/modes/game-card-combat-daily-boss.js`
- `combat/modes/game-card-combat-dungeon.js`
- `combat/modes/game-card-combat-gauntlet.js`

Responsabilidad:

- construir el combate según el modo
- definir reglas especiales
- gestionar recompensas y penalizaciones del modo

Orden obligatorio:

1. cargar motor base
2. crear estado base de combate
3. aplicar adaptador del modo
4. renderizar
5. ejecutar turno

---

## Gobernanza de datos

Esta parte es obligatoria. Sin ella, la modularización será cosmética.

### A. Separación de modelos

#### Catálogo

Fuente:

- API / catálogo sembrado

Propiedades:

- `card_id`
- `source_type`
- `source_id`
- `card_name`
- `card_text`
- `card_image_url`
- `card_rarity`
- rangos base
- metadata visual

Regla:

- no se muta en cliente

#### Copia del jugador

Fuente:

- colección persistida del usuario

Propiedades:

- `instanceId`
- `cardId`
- `rarity`
- `hp`
- `atk`
- `def`
- `quality`
- `moves`
- `moveRollRarity`
- `obtainedAt`
- `upgraded`
- futuras flags derivadas si hacen falta

Regla:

- la copia es el único sitio donde viven progresión, rareza evolucionada, stats personalizados y habilidades aprendidas

### B. Reglas de propiedad de escritura

#### Solo estos módulos pueden modificar `collection.ownedCards`

- `collection/game-card-collection.js`
- `packs/game-card-packs.js`
- `cards/game-card-improve.js`
- `cards/game-card-evolve.js`
- `cards/game-card-recycle.js`
- `combat/modes/game-card-combat-daily-boss.js`

#### `data/game-card-catalog.js` nunca puede mutar `ownedCards`

#### `data/game-card-migrations.js` no puede:

- resetear rarezas progresadas
- recalcular rareza desde catálogo para cartas `upgraded`
- sobreescribir stats de una carta progresada salvo migración explícita de versión

### C. Regla de seed

El seed:

- actualiza la BDD del catálogo
- actualiza imágenes, texto, stats base y rareza base del catálogo
- no toca colección del jugador

Si existe sincronización cliente-catálog:

- debe ignorar copias `upgraded = true`
- debe tratar `rarity` de la copia como dato soberano si la carta fue evolucionada

### D. Nuevas migraciones

Crear versión explícita de colección:

- `version: 3` o superior

Migración mínima:

- si una copia tiene `rarity !== card.card_rarity`, establecer `upgraded = true`
- si una copia ya trae `upgraded`, preservarlo

---

## Arquitectura de carga

### Bootstrapping deseado

`bootstrap/game-card-loader.js`

Debe cargar módulos en este orden:

1. `core/game-card-config.js`
2. `core/game-card-utils.js`
3. `core/game-card-events.js`
4. `core/game-card-storage.js`
5. `core/game-card-state.js`
6. `core/game-card-dom.js`
7. `data/game-card-governance.js`
8. `data/game-card-copy-model.js`
9. `data/game-card-rules.js`
10. `data/game-card-catalog.js`
11. `data/game-card-migrations.js`
12. `collection/*`
13. `shop/*`
14. `packs/*`
15. `memory/*`
16. `teams/*`
17. `cards/game-card-skills.js`
18. `cards/game-card-improve.js` si feature activa
19. `cards/game-card-evolve.js` si feature activa
20. `cards/game-card-recycle.js`
21. `combat/game-card-combat-state.js`
22. `combat/game-card-combat-rules.js`
23. `combat/game-card-combat-ai.js`
24. `combat/game-card-combat-animations.js`
25. `combat/game-card-combat-render.js`
26. `combat/game-card-combat.js`
27. `combat/modes/game-card-combat-training.js`
28. `combat/modes/game-card-combat-daily-boss.js`
29. `bootstrap/game-card-app.js`

### Feature flags

`bootstrap/game-card-features.js`

Debe permitir algo así:

```js
window.HG_CARD_GAME_FEATURES = {
  skills: true,
  improve: true,
  evolve: true,
  recycle: true,
  trainingCombat: true,
  dailyBoss: true,
  dungeon: false,
  gauntlet: false
};
```

Regla:

- si `improve` es `false`, no se registra UI ni eventos de mejora
- si `evolve` es `false`, no se registra UI ni eventos de evolución
- `skills` debe poder seguir activo aunque `improve` y `evolve` estén desactivados

---

## Contratos entre módulos

### Propuesta de namespace global único

Usar un único contenedor:

```js
window.HGCardGame
```

Subespacios:

```js
HGCardGame.core
HGCardGame.data
HGCardGame.collection
HGCardGame.shop
HGCardGame.memory
HGCardGame.teams
HGCardGame.cards
HGCardGame.combat
HGCardGame.bootstrap
```

### Contrato mínimo por módulo

Cada fichero debe:

1. validar que `window.HGCardGame` existe
2. registrar solo su propia API pública
3. no arrancarse solo salvo los módulos de bootstrap
4. no leer nodos DOM si el contexto actual no los necesita

### Ejemplo

`cards/game-card-evolve.js` debería exponer:

```js
HGCardGame.cards.evolve = {
  canUpgradeCopy,
  getUpgradeCost,
  openUpgradeModal,
  applyUpgrade
};
```

No debe:

- bindear todos los eventos globales por su cuenta
- crear `state` propio paralelo
- tocar combate ni tienda

---

## Plan de migración por fases

### Fase 0. Congelación y mapa de dependencias

Objetivo:

- no cambiar lógica
- solo mapear responsabilidades

Tareas:

- inventariar todas las funciones por dominio
- marcar side effects
- localizar dependencias cruzadas
- identificar estado compartido

Salida:

- tabla de funciones por módulo destino

### Fase 1. Crear estructura y loader

Objetivo:

- crear `/assets/js/card-game/`
- crear loader y namespace

Tareas:

- añadir carpetas vacías y módulos mínimos
- registrar `window.HGCardGame`
- mover solo utilidades puras y config

No mover aún:

- combate
- upgrades
- render complejo

### Fase 2. Extraer núcleo y datos

Objetivo:

- sacar del monolito lo que no depende de la vista

Mover primero:

- config
- utils
- storage
- state
- DOM refs
- reglas
- catálogo
- migraciones

Regla:

- `game-cards-v2.js` puede seguir delegando en módulos nuevos mientras la migración conviva

### Fase 3. Extraer colección y memoria

Objetivo:

- aislar la colección del resto del juego

Mover:

- persistencia de colección
- render de colección
- filtros
- import/export
- favoritos
- rememoración

Requisito:

- colección debe ser usable sin cargar combate

### Fase 4. Extraer progresión de cartas

Objetivo:

- separar habilidades, mejora, evolución y reciclaje

Orden:

1. habilidades
2. mejora
3. evolución
4. reciclaje

Motivo:

- habilidades deben independizarse antes de quitar mejora/evolución

### Fase 5. Extraer equipos

Objetivo:

- aislar equipos guardados y draft de combate

Mover:

- equipos
- perfil de combate
- selección de 5 cartas
- restricciones por rememoración

Requisito:

- el selector de equipos debe funcionar aunque combate aún siga dentro del monolito

### Fase 6. Extraer combate base

Objetivo:

- tener motor de combate sin conocimiento de modos específicos

Mover:

- unidades de combate
- stats temporales
- daño
- turnos
- cooldowns
- buffs/debuffs
- cambio de carta
- animaciones
- render base

### Fase 7. Extraer modos de combate

Objetivo:

- separar entrenamiento y jefe diario como adaptadores del motor

Mover:

- entrenamiento a `combat/modes/game-card-combat-training.js`
- jefe diario a `combat/modes/game-card-combat-daily-boss.js`

Requisito:

- cada modo define:
  - cómo se construye el combate
  - qué riesgos tiene
  - cómo se recompensa
  - qué mensajes muestra

### Fase 8. Registrar nuevos modos

Objetivo:

- permitir alta de nuevos modos sin tocar el motor

Propuesta:

```js
HGCardGame.combat.modes.register('training', trainingMode)
HGCardGame.combat.modes.register('daily-boss', dailyBossMode)
HGCardGame.combat.modes.register('dungeon', dungeonMode)
HGCardGame.combat.modes.register('gauntlet', gauntletMode)
```

---

## Reglas para el LLM agéntico

### Regla 1

No hacer un “big bang rewrite”.

### Regla 2

Cada PR o lote de trabajo debe mover un dominio completo o una capa completa.

### Regla 3

Antes de mover una función:

- identificar de qué datos depende
- identificar qué muta
- identificar qué render dispara

### Regla 4

Si una función:

- calcula
- guarda
- renderiza

entonces debe dividirse en al menos 2 o 3 funciones.

### Regla 5

No se permite que `catalog` vuelva a gobernar la rareza de una copia `upgraded`.

### Regla 6

Todo módulo nuevo debe declarar:

- inputs
- outputs
- side effects
- dependencias

### Regla 7

Si un módulo necesita otro, debe consumir su API pública, no variables internas.

### Regla 8

Cada fase debe dejar el sistema funcionando exactamente igual o mejor que antes.

---

## Checklist técnico de la migración

### Datos

- [ ] crear modelo formal de `CatalogCard`
- [ ] crear modelo formal de `OwnedCopy`
- [ ] crear modelo formal de `CombatState`
- [ ] crear modelo formal de `DailyBossState`
- [ ] crear migración de colección con `upgraded`

### Núcleo

- [ ] centralizar `state`
- [ ] centralizar `els`
- [ ] centralizar `localStorage`
- [ ] centralizar feature flags

### Progresión

- [ ] aislar habilidades
- [ ] aislar mejora
- [ ] aislar evolución
- [ ] aislar reciclaje

### Combate

- [ ] extraer motor base
- [ ] extraer render base
- [ ] extraer IA
- [ ] extraer animaciones
- [ ] extraer entrenamiento
- [ ] extraer jefe diario
- [ ] preparar dungeon
- [ ] preparar gauntlet

### UI

- [ ] bootstrap independiente por página
- [ ] carga condicional de módulos según contexto
- [ ] eventos globales no duplicados

---

## Riesgos conocidos

1. Dependencias ocultas por orden de declaración.
2. Funciones de render que asumen `root` y `els` globales.
3. Migraciones de colección que disparan `saveCollection()` demasiado pronto.
4. Reglas del catálogo que rehidratan copias del jugador de forma agresiva.
5. Side effects de combate sobre colección y equipos.
6. Cache del navegador si se fragmenta el script sin estrategia de versionado.

---

## Criterios de aceptación

La migración se considerará válida cuando:

1. `game-cards-v2.js` deje de ser el punto único de negocio.
2. Se pueda desactivar `improve` y `evolve` sin romper `skills`.
3. Se pueda cargar `collection` sin cargar `combat`.
4. Se pueda cargar `combat` base y luego elegir `training` o `daily-boss` de forma secuencial.
5. Un re-seed del catálogo no altere rareza ni stats de cartas `upgraded`.
6. La colección del jugador tenga gobernanza de datos separada del catálogo.
7. Añadir `dungeon` o `gauntlet` no obligue a tocar el motor base.

---

## Primera implementación recomendada

Orden recomendado para empezar:

1. crear `/assets/js/card-game/`
2. crear `window.HGCardGame`
3. mover `config`, `utils`, `storage`, `state`
4. mover `data/copy-model`, `data/governance`, `data/migrations`
5. mover `collection`
6. mover `skills`
7. mover `teams`
8. mover `combat` base
9. mover `training`
10. mover `daily-boss`
11. mover `improve`
12. mover `evolve`
13. dejar `game-cards-v2.js` como wrapper temporal
14. eliminar el wrapper cuando todas las rutas usen loader modular

---

## Nota final

La migración no debe plantearse como “partir un fichero grande en varios ficheros”, sino como “redefinir contratos de datos y responsabilidades para que el juego soporte más modos, menos regresiones y módulos opcionales”.

Si un cambio no mejora esa gobernanza, no forma parte de esta migración.
