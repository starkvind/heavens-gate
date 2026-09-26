# PHP Phase 10.1 — Public controller SQL extraction

## Status

**IMPLEMENTED — CI PASS — Raspberry smoke pending.**

Branch: `php-refactor`.

## Goal

Remove direct database ownership from every public controller.

Public controllers may orchestrate request/presentation behavior, but SQL belongs to explicit domain/tool data owners.

## Result

Direct public-controller SQL:

- owners: **0**;
- call sites: **0**.

The four remaining owners from the Phase 10 inventory were eliminated.

### 404

Controller:

`app/controllers/main/error404.php`

New query owner:

`app/domains/errors/queries.php`

The controller still owns deterministic suggestion selection and presentation. Counts and suggestion rows are fetched by the domain.

### Dice roller

Controller:

`app/controllers/tool/dice_roller.php`

Query owner:

`app/domains/dice/queries.php`

Moved:

- form/attribute modifier lookup;
- full roll history query;
- recent roll query.

### Forum avatar builder

Controller:

`app/controllers/tool/forum_avatar_builder.php`

New shared query owner:

`app/domains/forum/queries.php`

Moved:

- character selector query;
- avatar-variant query;
- text-color query.

### Forum topic viewer

Controller:

`app/controllers/tool/forum_topic_viewer.php`

Query owner:

`app/domains/forum/queries.php`

Moved topic metadata lookup, including the retained compatibility fallback.

## Retired card-game status residue

The public Status page still exposed:

`Cartas activas`

from the retired card-game table `fact_game_card_collection`.

This metric was removed from:

`app/helpers/public_status.php`

The retired-games runtime audit now also rejects `fact_game_card_collection` references under active PHP runtime code, while the historical database/archive remains untouched.

## Permanent guard

Added:

`.github/ci/php-phase10-public-sql-audit.py`

It enforces zero SQL query/prepare ownership in:

- public controllers;
- mobile controllers.

The existing Phase 7 baseline remains historical; Phase 10.1 establishes the stronger final rule.

## Validation

Implementation checkpoint:

- PHP Refactor Characterization: PASS;
- Project CI: PASS;
- Phase 10.1 public SQL guard: PASS;
- retired-games runtime guard: PASS;
- architecture inventory reports 0 public SQL owners / 0 public SQL calls.

## Closure gate

Raspberry smoke must verify:

- custom 404 suggestions;
- Dice roller list/detail and rolling;
- Forum Avatar Builder;
- Forum Topic Viewer;
- Status no longer shows retired card-game metrics;
- no new PHP/SQL errors.
