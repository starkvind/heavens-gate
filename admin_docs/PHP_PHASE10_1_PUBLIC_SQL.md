# PHP Phase 10.1 — Public controller SQL extraction

## Status

**IMPLEMENTED — CI PASS — Raspberry re-smoke pending after theme fix.**

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

## Raspberry smoke regression: mobile themes

The first Raspberry smoke exposed a presentation regression in four public surfaces when using a light mobile theme:

- custom 404;
- Dice roller;
- Forum Avatar Builder;
- Forum Topic Viewer.

Cause: these routes reuse desktop/page styles containing fixed classic-theme colors. Mobile already remapped the parent surfaces and form controls, but child labels, help text, 404 suggestion cards and the forum viewer chrome could still keep their original palette because page styles are emitted after `hg-mobile.css`.

Remediation:

- `assets/css/hg-mobile.css` now provides explicit theme bridges based on `--hg-mobile-*` tokens for the affected shared tools;
- the 404 fallback is rebound to the active mobile theme without changing controller behavior;
- the forum viewer chrome follows the active mobile theme while forum message/dice embeds retain their own palette/contrast logic;
- `.github/ci/php-phase9-mobile-presentation-audit.py` guards the bridge markers and rejects restoration of the deprecated fixed white forum-viewer skin.

No database, query or request behavior changed in this correction.

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

- custom 404 suggestions in Classic and at least one light theme;
- Dice roller list/detail and rolling in Classic and at least one light theme;
- Forum Avatar Builder in Classic and at least one light theme;
- Forum Topic Viewer in Classic and at least one light theme;
- Status no longer shows retired card-game metrics;
- no new PHP/SQL errors.
