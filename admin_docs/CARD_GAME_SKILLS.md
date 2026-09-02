# Habilidades del Archivo de Mnemógeno

Última revisión: 2026-09-02.

La guía antigua que indicaba editar un objeto `MOVE_LIBRARY` dentro de un único runtime JS ya no describe la arquitectura vigente.

## Fuente actual

Las habilidades se almacenan en la tabla `dim_game_card_moves`.

Campos principales:

| Campo | Uso |
|---|---|
| `move_key` | Identificador estable y clave primaria. |
| `label` | Nombre visible. |
| `icon` | Icono de UI. |
| `move_type` | Tipo de acción, actualmente daño o buff según las reglas cargadas. |
| `power` | Multiplicador de ATQ cuando la habilidad usa potencia directa. |
| `formula` | Fórmula especial, por ejemplo `average_atk_def`. |
| `accuracy` | Precisión. |
| `cooldown` | Turnos de espera. |
| `target` | Objetivo, por ejemplo `enemy` o `self`. |
| `effect_json` | Efecto secundario serializado como JSON. |
| `description` | Texto visible. |
| `is_active` / `sort_order` | Activación y orden. |

Las reglas de aprendizaje por rareza viven en `fact_game_card_move_learn_rules`.

## Defaults y sincronización

Los valores por defecto se declaran en:

`app/modules/game_cards/game_card_rules_catalog.php`

`hg_gcr_default_catalog()` contiene el catálogo de movimientos y el seed los sincroniza con la base de datos.

Para aplicar cambios de defaults:

~~~bash
php tools/seed_game_cards.php
~~~

No usar `--reset` solo para añadir una habilidad: el reset afecta al catálogo de cartas y es innecesariamente destructivo.

## Habilidades existentes

El catálogo de defaults actual incluye:

- `weakening_blow` — Golpe debilitador;
- `armor_breaker` — Rompecorazas;
- `discouraging_impact` — Impacto descorazonador;
- `brutal_strike` — Golpe brutal;
- `phantom_leda` — Leda fantasma;
- `hero_stance` — Postura de héroe.

## Efectos soportados por combate

La aplicación de efectos vive en:

`assets/js/card-game/combat/game-card-combat-rules.js`

`applyMoveEffect()` reconoce actualmente:

- `debuff_atk`;
- `debuff_def`;
- `shield_break`;
- `recoil`;
- `lifesteal`;
- `buff_atk_def`.

Añadir un `effect.kind` nuevo a la base de datos **no lo implementa automáticamente**: hay que programar su comportamiento en este módulo.

## Fórmulas de daño

`combatMoveDamage()` usa:

- `formula = average_atk_def` para calcular `(ATQ + DEF) / 2`;
- `power` para multiplicar el ATQ;
- las reglas normales de daño para resolver el valor contra la DEF.

Si se introduce una fórmula nueva, debe añadirse explícitamente a `combatMoveDamage()`.

## VFX

Las animaciones específicas se resuelven en:

`assets/js/card-game/combat/game-card-combat-animations.js`

`playMoveVfx()` contiene casos por `move.id`. Una habilidad puede funcionar sin VFX dedicado, pero si requiere uno debe añadirse allí.

## Flujo correcto para añadir una habilidad

1. Elegir un `move_key` estable.
2. Añadirla al catálogo por defecto de `game_card_rules_catalog.php`.
3. Reutilizar un `effect.kind` existente o implementar el nuevo en `game-card-combat-rules.js`.
4. Añadir fórmula especial solo si no basta con `power`.
5. Añadir VFX opcional en `game-card-combat-animations.js`.
6. Ejecutar el seed sin `--reset`.
7. Comprobar `/api/game_card_rules.php`.
8. Probar aprendizaje, persistencia, cooldown, IA y combate.
9. Probar producción y Dev Lab por separado: sus scopes de almacenamiento están aislados.

## Qué no hacer

- No editar `assets/js/card-game/bootstrap/game-card-runtime.js` para meter un `MOVE_LIBRARY` manual.
- No duplicar una habilidad solo para cambiar el texto.
- No inventar un `effect.kind` sin soporte de combate.
- No cambiar `move_key` de una habilidad publicada sin plan de migración.
- No ejecutar `--reset` como rutina de sincronización.

