# Database schema notes

This document records the runtime schema contracts relied upon by the public application. Production schema changes are applied manually and should be versioned under `admin_docs/sql/` before the dependent application code is deployed.

## Character forms

Character transformation forms are stored in `dim_forms`. Generic trait effects are stored in `bridge_forms_traits`.

### `dim_forms`

Relevant presentation/mechanics fields include:

- `id`
- `system_id`
- `race`
- `form`
- `sort_order`
- `description`
- `image_url`
- `silhouette_image_url`
- `weapons`
- `firearms`
- `strength_bonus`
- `dexterity_bonus`
- `stamina_bonus`
- `regeneration`
- `hpregen`

`silhouette_image_url` is nullable and is reserved for standardized biography UI silhouettes. It is distinct from the normal illustration in `image_url`.

### `bridge_forms_traits`

Relevant columns:

- `form_id`
- `trait_id`
- `modifier`
- `override_value`

Form trait resolution follows this precedence:

1. If `override_value` is not `NULL`, the transformed trait is `max(0, override_value)`.
2. Otherwise the transformed trait is `max(0, base_value + modifier)`.

`modifier` therefore remains the normal additive mechanism, while `override_value` represents an absolute transformed value such as a social Attribute becoming `0` in a form. A value of `0` is meaningful and must not be treated as missing data.

The schema change introducing `override_value` is versioned in:

`admin_docs/sql/2026-10-02-form-trait-override.sql`

## Canonical production tables

The application also relies on normalized bridge/dimension/fact tables including:

- `bridge_characters_traits`
- `bridge_characters_traits_log`
- `bridge_forms_traits`
- `bridge_maneuvers_forms`
- `bridge_maneuvers_systems`
- `bridge_misc_systems_energy_resources`
- `dim_forms`
- `dim_traits`
- `fact_characters`
- `fact_combat_maneuvers`
- `fact_power_rolls`

Runtime code should use the domain query layer for these contracts rather than adding controller-level schema probes or DDL.
