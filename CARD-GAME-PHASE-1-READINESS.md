# Card Game Phase 1 Readiness

Estado actual: `READY`

## Condiciones de entrada revisadas

- existe entorno de trabajo aislado: `OK`
- existe storage aislado para lab: `OK`
- existe bootstrap modular mínimo: `OK`
- Fase 0 de dominios completada: `OK`
- Fase 0 de impacto completada: `OK`
- shortlist de extracción segura definida: `OK`
- handoff operativo para LLM preparado: `OK`

## Qué significa `READY`

Significa que ya no hace falta más análisis grueso para empezar Fase 1.

La siguiente tanda puede pasar directamente a implementación sobre:

- `core/`
- `data/`

sin reabrir la discusión de arquitectura base.

## Primer lote recomendado al arrancar

1. `core/game-card-utils.js`
2. `data/game-card-copy-model.js`
3. `data/game-card-governance.js`
4. `core/game-card-storage.js`
5. `data/game-card-rules.js`

## Criterio para decir que Fase 1 ha empezado de verdad

Se considerará iniciada cuando:

- exista al menos un módulo nuevo fuera de `bootstrap/` con funciones reales migradas
- `game-cards-v2.js` delegue en él
- el wrapper antiguo siga funcionando igual
