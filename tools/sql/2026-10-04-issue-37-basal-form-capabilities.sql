-- Issue #37: normalize canonical basal Form capabilities.
--
-- Canonical model (2026-10-03): basal Forms are real dim_forms rows.
-- They are not a synthetic frontend state.
--
-- Basal Form names currently used by the project:
--   Homínido / Hominido
--   Antropos  (Abraxas)
--   Hitogata  (Kitsune)
--   Balaram   (Nagah)
--   Bajopiel  (Nerai)
--
-- Canonical basal capability values:
--   weapons  = 1
--   firearms = 1
--   hpregen  = 0
--
-- This migration intentionally does not infer or alter transformed Forms.
-- Review the first SELECT before executing the UPDATE on production.

START TRANSACTION;

-- Before: inspect exactly which rows will be touched.
SELECT
    f.id,
    ds.name AS system_name,
    f.form,
    f.weapons,
    f.firearms,
    f.hpregen,
    f.sort_order
FROM dim_forms f
LEFT JOIN dim_systems ds ON ds.id = f.system_id
WHERE f.form IN ('Homínido', 'Hominido', 'Antropos', 'Hitogata', 'Balaram', 'Bajopiel')
ORDER BY ds.name, f.sort_order, f.id;

-- Normalize only the three capability fields covered by issue #37.
UPDATE dim_forms
SET
    weapons = 1,
    firearms = 1,
    hpregen = 0,
    updated_at = NOW()
WHERE form IN ('Homínido', 'Hominido', 'Antropos', 'Hitogata', 'Balaram', 'Bajopiel')
  AND (
      COALESCE(weapons, 0) <> 1
      OR COALESCE(firearms, 0) <> 1
      OR COALESCE(hpregen, 0) <> 0
  );

-- After: all targeted basal Forms must now resolve to 1 / 1 / 0.
SELECT
    f.id,
    ds.name AS system_name,
    f.form,
    f.weapons,
    f.firearms,
    f.hpregen,
    f.sort_order
FROM dim_forms f
LEFT JOIN dim_systems ds ON ds.id = f.system_id
WHERE f.form IN ('Homínido', 'Hominido', 'Antropos', 'Hitogata', 'Balaram', 'Bajopiel')
ORDER BY ds.name, f.sort_order, f.id;

COMMIT;
