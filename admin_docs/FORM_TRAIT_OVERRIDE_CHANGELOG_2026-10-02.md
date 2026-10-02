# 2026-10-02 — Absolute form trait overrides

Follow-up to the merged form-presentation refinement.

- `bridge_forms_traits.modifier` remains additive.
- New production column `override_value` represents an absolute transformed trait value and takes precedence over `modifier`.
- Form values may resolve to `0` but never below `0`.
- Biography Forms, biography Actions, mobile biographies and the protagonist dice roller use the same precedence rule.
- The production DDL is applied manually before deploying this code and is recorded in the continuity repository.
