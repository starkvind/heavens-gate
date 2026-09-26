# PHP Phase 10.4 — Legacy alias normalization

## Status

**CLOSED — CI PASS — Raspberry smoke accepted.**

Branch: `php-refactor`.

## Goal

Keep the historical public query aliases that still matter for incoming old links, but make them a single explicit compatibility catalog instead of letting aliases own duplicate routing branches.

This phase does not remove supported legacy entry URLs.

## Baseline

Before 10.4, `app/routing/legacy_query.php` contained:

- **7** edge-only public aliases;
- **47** `case` labels;
- **41** unique `case` labels;
- **6** duplicated labels caused by query-policy switches.

The seven supported aliases were already intentionally absent from active dispatch, mobile routing and request context, but their compatibility behavior was split between the direct route table and dedicated switch labels.

## Result

The supported edge aliases now live in one catalog:

`hg_request_router_public_aliases()`

Mappings:

- `bio_chronicles -> chronicles`;
- `listaobj -> inv`;
- `seeitem -> verobj`;
- `dones -> listadones`;
- `rites -> ritelist`;
- `totems -> listatotems`;
- `imgz -> gallery`.

Incoming legacy `p` values are normalized once through `hg_request_router_canonical_legacy_route()` before the normal compatibility mapping runs.

No alias owns:

- an active `routes.php` entry;
- a mobile route;
- a request-context route;
- a duplicate switch branch;
- a duplicate direct-route entry.

## Switch cleanup

The forum-embed query policy and the final query-preservation policy were changed from repeated switch labels to declarative route sets/maps.

After 10.4:

- `case` labels: **36**;
- unique `case` labels: **36**;
- duplicated `case` labels: **0**.

This is structural cleanup only. Canonical targets and accepted legacy entry URLs remain unchanged.

## Guards

`.github/ci/php-phase10-legacy-alias-audit.py` now enforces:

- the exact seven edge-only aliases;
- centralized alias normalization;
- no alias-specific switch branch;
- no alias inside the direct canonical route table;
- no alias returning to active desktop/mobile/request-context dispatch;
- exactly **36** legacy switch labels;
- zero duplicated switch labels.

The older Phase 7 alias and compatibility guards were updated to follow the centralized catalog.

The Phase 7 architecture ceiling for `legacy_query.php` case labels is tightened from **47** to **36**.

The architecture inventory now reports total, unique and duplicated case labels separately.

## Validation

Implementation checkpoint:

- supported legacy public aliases: **7**;
- alias owners outside compatibility edge: **0**;
- legacy switch labels: **36**;
- duplicated switch labels: **0**;
- canonical targets changed: **0**;
- PHP Refactor Characterization: PASS;
- Project CI: PASS.

## Closure

The legacy alias compatibility surface was accepted and the refactor proceeded to Phase 10.5 without a reported redirect regression.

Phase 10.4 is closed.
