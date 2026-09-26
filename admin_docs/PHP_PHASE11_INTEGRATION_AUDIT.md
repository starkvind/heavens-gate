# PHP Phase 11.3 — Final integration audit

## Status

**INTEGRATION READY — RASPBERRY SMOKE STILL REQUIRED — MASTER UNTOUCHED.**

Branch under validation: `php-refactor`.

Target branch: `master`.

Validation PR: `#15`, kept as draft until explicit authorization.

## Branch reconciliation

At Phase 11 entry, `php-refactor` was 1039 commits ahead and 5 commits behind `master`.

The five master-only commits were the September 7 `hg_avatar` resize hotfix sequence.

Before integration, their effective behavior was compared against the refactor branch:

- the shared parent resize listener was already identical;
- the iframe request/response height protocol was already present;
- font-ready and `ResizeObserver` remeasurement was already present;
- the final feedback-loop padding correction was already present;
- the refactored request/query boundaries around the forum snippet were preserved.

The reconciliation merge changed ancestry only and introduced zero file changes relative to the previous `php-refactor` tree.

After reconciliation, `master` is an ancestor of `php-refactor`; the refactor branch has zero commits behind the target.

## Architecture contract

The Release Candidate preserves the Phase 10 final contract:

- public/mobile controller SQL: **0**;
- classified schema-introspection owners: **4**;
- schema-introspection probes/tokens: **10**;
- raw `$_REQUEST`: **0**;
- direct request-state reads in public/mobile controllers: **0**;
- legacy public route-key aliases: **7**, compatibility boundary only;
- `legacy_query.php` switch labels: **36**;
- duplicate switch labels: **0**.

The focused Phase 10 guards and the independent final audit remain wired into PHP Refactor Characterization.

## CI

The two release gates are:

- **Project CI**;
- **PHP Refactor Characterization**.

Both workflows now also publish classic commit statuses so their result can be read unambiguously through GitHub's commit-status API.

No CI guard was weakened to make Phase 11 pass.

## Runtime and scope audit

No Phase 11 change so far alters campaign/canon data, database schema, SQL data, routing behavior, desktop design or mobile compatibility semantics.

The following remain deliberately out of scope:

- desktop redesign;
- complete desktop theme selector;
- responsive SVG + CSS menu rebuild;
- removal of `?view=mobile`;
- new features.

Classic remains the canonical desktop presentation for release.

## Documentation audit

Living documentation now records:

- the Phase 11 Release Candidate boundary;
- branch reconciliation and master safety;
- the Raspberry smoke harness;
- manual smoke requirements;
- the final authorization gate.

The smoke harness is documented in `SCRIPTS_AND_MAINTENANCE.md` and the Phase 11 documents are indexed from `admin_docs/README.md`.

## Raspberry gate

Production validation is defined in:

`admin_docs/PHP_PHASE11_SMOKE.md`

Automated command:

```bash
git switch php-refactor
git pull --ff-only
bash tools/phase11_smoke.sh
```

The automated harness is read-only. The remaining manual checks cover Classic desktop, real-forum `hg_avatar` behavior, mobile compatibility, Gallery, PWA/device behavior and authenticated Admin read-only navigation.

Phase 11.2 is not considered passed until those Raspberry/manual checks are completed.

## Master authorization boundary

`master` must remain unchanged until explicit authorization.

Do not merge PR `#15`, update the `master` ref, deploy from `master` or retire `php-refactor` before the Raspberry gate is green and authorization is given.

After authorization, Phase 11.5 may consolidate the already-reviewed Release Candidate into `master`, run CI again and deploy from `master`. Phase 11.6 then performs the short post-consolidation smoke and only afterwards may `php-refactor` be retired.
