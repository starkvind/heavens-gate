# Issue #37 — Canonical basal Forms

The basal state of a shapeshifting species is a real `dim_forms` row. It must never be reconstructed as an empty/synthetic frontend option.

## Canonical contract

- Character breed resolves its Form family through `dim_breeds.form_system_id`.
- Available Forms are real `dim_forms` rows filtered through `bridge_forms_applicability` where required.
- The basal Form participates in the same read model as every transformed Form.
- Species-specific basal names are preserved (`Hitogata`, `Antropos`, `Balaram`, `Bajopiel`, etc.).
- Capability fields come from the selected row itself:
  - `dim_forms.weapons`
  - `dim_forms.firearms`
  - `dim_forms.hpregen`
- Basal Forms are mechanically neutral in physical trait modifiers and currently use `weapons=1`, `firearms=1`, `hpregen=0`.

## Regression rule

Do not reintroduce:

- `<option value="">Homínido</option>`;
- `<option value="">Forma base</option>`;
- an empty `form_id` meaning the basal state;
- frontend defaults that replace capability values from the selected `dim_forms` row.

Desktop and mobile character sheets must resolve the same capability payload for a given `form_id`.

## Data normalization

The one-off migration for existing basal rows lives at:

`tools/sql/2026-10-04-issue-37-basal-form-capabilities.sql`

It previews all targeted rows before updating them and normalizes only `weapons`, `firearms` and `hpregen`.
