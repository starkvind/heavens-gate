# PHP Phase 11.4 — Release Candidate sign-off

## Status

**READY FOR AUTHORIZATION — MASTER UNTOUCHED.**

Branch: `php-refactor`.

Target: `master`.

Validation PR: `#15`, kept draft until explicit authorization.

## Purpose

Close the pre-consolidation work after a green Release Candidate, full CI and successful Raspberry smoke.

Phase 11.4 does not merge, deploy from, rewrite or otherwise modify `master`.

## Accepted gates

The following gates are green:

- Phase 11.0 branch reconciliation;
- Phase 11.1 Release Candidate freeze;
- Project CI;
- PHP Refactor Characterization;
- Phase 11.2 automated Raspberry smoke;
- Phase 11.2 manual Classic/mobile/Gallery/PWA/Admin smoke;
- real-forum `hg_avatar` regression;
- Phase 11.3 final integration/documentation audit.

The operator confirmed the Raspberry/manual smoke green on 2026-09-26.

## Branch state at sign-off

Before the Phase 11.4 documentation commits:

- `master`: `e614e6bacff25f6d6f7bfe04fc815e8112fd83e3`;
- `php-refactor`: `784eca3c62ad084c69762e5d6b7b92231bce777e`;
- `php-refactor` was 1051 commits ahead / 0 behind `master`;
- PR `#15` was open, draft, unmerged and mergeable;
- both release CI statuses were success.

Phase 11.4 only records sign-off/documentation and may therefore advance the `php-refactor` HEAD without changing runtime behavior.

## Release contracts

The final Phase 10 architecture contract remains unchanged:

- direct SQL in public/mobile controllers: **0**;
- classified schema-introspection owners: **4**;
- schema-introspection probes/tokens: **10**;
- raw `$_REQUEST`: **0**;
- direct public/mobile request-state reads: **0**;
- public legacy route-key aliases: **7**, compatibility edge only;
- `legacy_query.php` switch labels: **36**;
- duplicate switch labels: **0**.

Classic remains the canonical desktop appearance.

## Frozen scope

Still out of scope:

- desktop redesign;
- complete desktop theme selector;
- SVG + CSS responsive-menu rebuild;
- removal of `?view=mobile`;
- new features.

## Phase 11.5 authorization gate

The technical Release Candidate is ready to consolidate.

Phase 11.5 may begin only after explicit authorization to modify `master`.

When authorized, the consolidation procedure is:

1. verify `master` has not moved unexpectedly;
2. verify `php-refactor` is still zero commits behind;
3. merge the reviewed PR `#15` into `master`;
4. verify exact resulting `master` HEAD;
5. run Project CI and PHP Refactor Characterization on `master`;
6. deploy/pull from `master` on Raspberry;
7. perform the short Phase 11.6 post-consolidation smoke;
8. only then retire `php-refactor`.

No step in this document is authorization by itself.
