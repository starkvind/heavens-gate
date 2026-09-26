# PHP Phase 11 — Release Candidate

## Status

**RELEASE CANDIDATE — VALIDATED — READY FOR AUTHORIZATION.**

Branch: `php-refactor`.

Master baseline at RC entry: `e614e6bacff25f6d6f7bfe04fc815e8112fd83e3`.

Reconciled refactor baseline: `612c86545f8b4e17a2c60a09fc05e747a5cea0db`.

Validation PR: `#15` (`php-refactor -> master`), draft and non-mergeable by policy until explicit authorization.

## Purpose

Freeze the PHP refactor as a release candidate before the final Raspberry smoke, integration audit and explicit authorization to consolidate into `master`.

No production runtime feature work belongs in this phase.

## Reconciliation

The five commits exclusive to `master` at Phase 11 entry were the 2026-09-07 `hg_avatar` resize hotfixes.

Before reconciliation:

- `php-refactor`: 1039 commits ahead / 5 behind `master`;
- merge base: `82d91147c1799ef4956504a3ca0dd562b7fa3eff`.

The hotfix behavior was inspected before integration:

- `assets/js/forum-avatar-embed.js` was byte-identical on both branches;
- the iframe height request, measurement, font-ready refresh and `ResizeObserver` behavior was already present in `app/partials/forum_message_snippet.php` on `php-refactor`;
- the refactor-specific request/query boundaries in that partial were preserved.

The reconciliation merge added ancestry only: comparing the pre-reconciliation refactor HEAD to the merge result produced zero changed files.

After reconciliation, `php-refactor` is 1040 commits ahead / 0 behind `master`.

## Frozen Phase 10 contracts

The RC inherits these permanent contracts:

- direct SQL in public/mobile controllers: **0**;
- classified schema-introspection owners: **4**;
- schema-introspection probes/tokens: **10**;
- raw `$_REQUEST`: **0**;
- direct public/mobile request-state reads: **0**;
- public legacy route-key aliases: **7**, compatibility edge only;
- `legacy_query.php` switch labels: **36**;
- duplicated legacy switch labels: **0**;
- Phase 10 focused guards and final guard remain wired into CI.

## Scope freeze

Out of scope until after refactor consolidation:

- desktop redesign;
- complete desktop theme selector;
- SVG + CSS responsive-menu rebuild;
- removal of `?view=mobile`;
- new features.

Classic remains the canonical desktop appearance for this release candidate.

## Validation gates

The RC acceptance gates are:

1. **Project CI** passes on the RC commit;
2. **PHP Refactor Characterization** passes on the RC commit;
3. Phase 11.2 Raspberry smoke passes;
4. Phase 11.3 final integration/documentation audit passes;
5. explicit authorization is received before modifying `master`.

Gates 1–4 are complete and green. Gate 5 is now the only remaining precondition for Phase 11.5.

## Master safety

Phase 11.0 and 11.1 must not modify `master`.

The draft validation pull request must not be merged before explicit authorization.
