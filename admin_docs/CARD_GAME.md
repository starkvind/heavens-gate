# Archivo de Mnemógeno — arquitectura técnica

Última revisión: 2026-09-02.

Esta referencia sustituye a la documentación monolítica anterior al runtime modular de julio de 2026.

## Modelo

El juego usa dos capas distintas:

- **catálogo y reglas en MariaDB**, servido por API;
- **progreso del jugador en el navegador**, persistido en `localStorage`.

El servidor no guarda la colección personal de cada usuario.

## Rutas

Producción:

- `/games/card-game`
- `/games/card-game/collection`
- `/games/card-game/combat`
- `/games/card-game/mobile`
- `/games/card-game/explanation`

Dev Lab aislado:

- `/games/hg-cardgame-dev-lab`
- `/games/hg-cardgame-dev-lab/collection`
- `/games/hg-cardgame-dev-lab/combat`
- `/games/hg-cardgame-dev-lab/mobile`
- `/games/hg-cardgame-dev-lab/explanation`

APIs:

- `/api/game_cards.php` — catálogo activo;
- `/api/game_card_rules.php` — reglas, movimientos, sobres, tienda y configuración.

Ambas APIs aceptan GET/HEAD.

## PHP

Piezas principales:

- `app/controllers/tool/game_cards.php` — controlador de vistas;
- `app/modules/game_cards/game_cards_catalog.php` — tipos de carta, URLs y catálogo;
- `app/modules/game_cards/game_card_rules_catalog.php` — reglas y esquema específico;
- `app/modules/game_cards/game_cards_page.php` — gacha/tienda;
- `app/modules/game_cards/game_cards_collection_page.php` — colección;
- `app/modules/game_cards/game_cards_combat_page.php` — combate;
- `app/modules/game_cards/game_cards_mobile_page.php` — superficie móvil;
- `app/helpers/game_cards_runtime.php` — composición y versionado del stack JS;
- `app/tools/seed_game_cards.php` — lógica de seed;
- `tools/seed_game_cards.php` — wrapper CLI;
- `app/controllers/admin/admin_game_cards.php` — mantenimiento del catálogo.

## Runtime JS modular

`app/helpers/game_cards_runtime.php` carga módulos por dominio y solo incorpora colección/combate cuando la vista los necesita.

Capas:

- `bootstrap/` — loader, features, app y wrapper;
- `core/` — utilidades, storage, estado, DOM y eventos;
- `data/` — modelo de copias, reglas, migraciones y gobernanza;
- `packs/` — sobres;
- `shop/` — tienda;
- `collection/` — filtros, render e import/export;
- `memory/` — rememoración;
- `cards/` — habilidades, evolución, mejora y reciclaje;
- `teams/` — equipos/loadouts;
- `combat/` — estado, reglas, IA, animaciones, render y modos.

La baseline de cache-busting declarada en el helper es `20260712-runtime-hotfix6`.

El fichero `bootstrap/game-card-runtime.js` existe como wrapper/entrada de compatibilidad. No debe volver a convertirse en el runtime monolítico.

## Esquema de datos

Tablas principales:

- `fact_game_card_collection` — catálogo de cartas;
- `dim_game_card_settings` — configuración;
- `dim_game_card_moves` — habilidades;
- `fact_game_card_move_learn_rules` — aprendizaje por rareza;
- `dim_game_card_materials` — materiales;
- `dim_game_card_pack_types` — tipos de sobre;
- `dim_game_card_rarities` — rarezas;
- `dim_game_card_shop_products` — tienda;
- `dim_game_card_types` — tipos;
- `dim_game_card_ui_texts` — textos de UI;
- `fact_game_card_pack_rarity_weights` — pesos de rareza;
- `fact_game_card_pack_type_filters` — filtros de contenido por sobre.

La vista `vw_game_card_collection` expone las cartas activas del catálogo.

`fact_game_card_collection.card_rarity` admite: `common`, `unusual`, `rare`, `epic`, `legendary`, `mythic` y `stigmatic`.

## Seed

Ejecución normal:

~~~bash
php tools/seed_game_cards.php
~~~

Regeneración completa del catálogo:

~~~bash
php tools/seed_game_cards.php --reset
~~~

El seed:

- asegura el esquema específico;
- sincroniza settings y reglas;
- respeta crónicas excluidas;
- desactiva cartas excluidas;
- inserta/actualiza cartas generables.

`--reset` borra el catálogo antes de regenerarlo y no debe formar parte del mantenimiento rutinario.

## Estado local

El progreso personal se mantiene en el navegador. El runtime modular conserva compatibilidad mediante migraciones de storage y separa el scope de producción del Dev Lab.

Consecuencias:

- limpiar storage puede borrar progreso;
- export/import sigue siendo la salvaguarda del usuario;
- cambiar IDs de catálogo puede romper referencias locales;
- una migración de estructura local debe implementarse en `data/game-card-migrations.js`, no confiar en que el navegador “se arregle”.

## Habilidades

Las habilidades ya no se definen en un objeto JS manual. Véase [CARD_GAME_SKILLS.md](./CARD_GAME_SKILLS.md).

## Mantenimiento

Después de tocar PHP:

- ejecutar `php -l` sobre los ficheros modificados;
- comprobar APIs y rutas.

Después de tocar reglas:

- ejecutar el seed;
- comprobar `/api/game_card_rules.php`;
- validar producción y Dev Lab.

Después de tocar JS:

- revisar que `game_cards_runtime.php` carga el módulo en la vista adecuada;
- evitar añadir dependencias globales que obliguen a cargar combate en gacha;
- comprobar persistencia tras F5 y navegación entre superficies.

Después de tocar catálogo:

- no usar `--reset` salvo necesidad real;
- revisar IDs y slugs;
- comprobar cartas excluidas e inactivas.

