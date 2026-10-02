# Manual schema changes

SQL files in this directory document schema changes that must be applied manually to production before deploying code that depends on them.

Current change:

- `2026-10-02-form-trait-override.sql`: adds `bridge_forms_traits.override_value` for absolute transformed trait values.
