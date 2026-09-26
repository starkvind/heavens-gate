# PHP Phase 10.2 — Schema introspection classification

## Status

**IMPLEMENTED — CI PASS — Raspberry smoke pending.**

Branch: `php-refactor`.

## Goal

Review every remaining runtime schema probe, remove accidental duplication, and classify the probes that still have a legitimate architectural owner.

This phase does not alter the production database or historical data.

## Baseline correction

Phase 10.0 reported:

- schema probes: **20**;
- files: **10**.

That number came from the architecture inventory pattern, which counted `SHOW COLUMNS` and `information_schema` but did not count `SHOW TABLES`.

The broader schema guard already treated `SHOW TABLES`, `SHOW COLUMNS`, `SHOW INDEX`, `SHOW KEYS` and `information_schema.*` as schema introspection.

Using that complete definition, the pre-10.2 baseline was:

- introspection tokens: **21**;
- owners: **10**.

The architecture inventory now uses the same definition as the guard.

## Result

After 10.2:

- introspection tokens: **10**;
- intentional owners: **4**;
- unclassified owners: **0**.

The previous generic/ad-hoc probes were consolidated or replaced by explicit current-schema contracts.

## Classified owners

### Compatibility boundary

`app/helpers/schema_introspection.php`

Owns the two cached, ordinary current-database checks:

- table exists;
- column exists.

This replaces duplicate implementations previously spread across helpers and admin domains.

### Dynamic admin operation

`app/domains/characters/admin_clone.php`

Keeps live metadata discovery because character cloning deliberately finds all `bridge_*` tables and their copyable columns. Hard-coding that list would make new character bridges silently disappear from clone operations.

Ordinary table existence checks now use the shared compatibility boundary.

### External schema adapter

`app/tools/forum_topic_viewer_tool.php`

Keeps one probe against the external `smf` schema. The forum deployment can expose SMF tables either in the current database or in the separate `smf` schema, so this is deployment adaptation rather than application-schema uncertainty.

Current-database table existence checks now use the shared compatibility boundary.

### Diagnostic tool

`app/tools/inspect_db.php`

Keeps metadata queries because inspecting tables, foreign keys and indexes is the explicit purpose of this tool.

Its generic table/column existence fallbacks were removed; those now use the shared compatibility boundary.

## Removed probe ownership

The following files no longer own schema introspection:

- `app/domains/chapters/admin_season_order.php`;
- `app/domains/chapters/admin_season_order_schema.php`;
- `app/domains/characters/admin_collision_audit.php`;
- `app/domains/characters/admin_service.php`;
- `app/domains/organizations/admin_chart_schema.php`;
- `app/helpers/mentions.php`;
- `app/helpers/pretty.php`.

Behavior is preserved through shared contracts or canonical schema knowledge.

Notable cleanups:

- `pretty.php` consumes the dedicated schema compatibility helper instead of owning generic metadata SQL;
- character admin uses the canonical `fact_characters.character_kind` length contract instead of querying column metadata on every request;
- mentions now target their explicit canonical catalog instead of probing each configured table before searching it;
- season-order, collision-audit and organization-chart code reuse the same cached table/column checks.

## Guard

`.github/ci/php-schema-contract-audit.py` now classifies every permitted owner and rejects:

- schema introspection in any other runtime file;
- stale allowlist entries that no longer contain introspection.

The classifications are:

- `compatibility-boundary`;
- `dynamic-admin-operation`;
- `external-schema-adapter`;
- `diagnostic-tool`.

The architecture inventory now reports the same probe family as this guard and prints the remaining owners explicitly.

## Validation

Implementation checkpoint:

- PHP syntax/lint: PASS;
- PHP Refactor Characterization: PASS;
- Project CI: PASS;
- classified schema introspection audit: PASS;
- remaining schema owners: **4**;
- remaining introspection tokens: **10**;
- unclassified schema debt: **0**.

## Closure gate

Raspberry smoke should verify:

- normal pretty-ID routing still resolves public entities;
- mentions endpoint returns results;
- season-order admin/status pages still load;
- character collision audit still loads;
- character clone page still loads and presents its selectors;
- organization-chart admin page still loads;
- forum topic viewer still resolves configured topics;
- database inspector still renders schema/health output;
- no new PHP/SQL errors.

No destructive admin action is required for smoke testing.
