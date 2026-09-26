# PHP Phase 10.5 — Final architecture guards

## Status

**IMPLEMENTED — CI PASS.**

Branch: `php-refactor`.

## Goal

Turn the Phase 10 cleanup into a permanent architecture contract.

The previous subphases each own a focused guard. Phase 10.5 adds an independent final audit that checks the resulting architecture directly and also verifies that the focused guards remain installed in CI.

No production runtime behavior is changed by this phase.

## Final Phase 10 baseline

### Public SQL

- public/mobile controller SQL owners: **0**;
- public/mobile controller SQL call sites: **0**.

SQL belongs to explicit domain/query owners.

### Schema introspection

- intentional owners: **4**;
- introspection tokens: **10**;
- unclassified owners: **0**.

Allowed owners remain:

- `app/helpers/schema_introspection.php`;
- `app/domains/characters/admin_clone.php`;
- `app/tools/forum_topic_viewer_tool.php`;
- `app/tools/inspect_db.php`.

### Request state

Regression ceilings:

- raw GET reads: **192**;
- raw POST reads: **708**;
- raw `$_REQUEST`: **0**.

Public/mobile controllers remain at zero direct GET/POST/REQUEST reads.

Direct application-cookie ownership remains limited to presentation preferences in:

- `app/helpers/mobile_detection.php`;
- `app/mobile/mobile_index.php`;
- `app/presentation/desktop_context.php`.

### Legacy query compatibility

- supported edge-only route-key aliases: **7**;
- legacy switch labels: **36**;
- duplicated switch labels: **0**.

Aliases remain compatibility input only; they do not own active dispatch, mobile routes or request-context entries.

## Final guard

Added:

`.github/ci/php-phase10-final-audit.py`

It independently enforces the final Phase 10 contracts and checks that these focused guards still exist and remain wired into the characterization workflow:

- `php-phase10-public-sql-audit.py`;
- `php-schema-contract-audit.py`;
- `php-phase10-request-state-audit.py`;
- `php-phase10-legacy-alias-audit.py`.

This prevents a future change from passing merely by accidentally dropping one of the focused checks from CI.

## Validation

At implementation checkpoint:

- Phase 10.1 public SQL guard: PASS;
- Phase 10.2 schema classification guard: PASS;
- Phase 10.3 request-state guard: PASS;
- Phase 10.4 alias guard: PASS;
- Phase 10.5 final architecture guard: PASS;
- PHP Refactor Characterization: PASS;
- Project CI: PASS.

## Runtime smoke

Phase 10.5 changes CI and documentation only. It does not modify the PHP runtime, routes, SQL, request handling, presentation or database.

No additional Raspberry smoke is required specifically for 10.5.

## Closure

Phase 10 is protected by permanent CI contracts rather than by a historical snapshot alone. Future reductions of compatibility/debt are allowed through an explicit baseline review; silent regression is not.
