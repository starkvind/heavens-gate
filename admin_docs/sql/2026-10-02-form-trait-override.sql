-- Heaven's Gate
-- 2026-10-02
-- Absolute form trait values.
--
-- `modifier` remains additive. `override_value`, when non-NULL, takes
-- precedence and sets the transformed trait to an absolute value.
-- Example semantics: modifier = +4 => base + 4; override_value = 0 => 0.

ALTER TABLE bridge_forms_traits
  ADD COLUMN IF NOT EXISTS override_value SMALLINT NULL AFTER modifier;

SHOW COLUMNS FROM bridge_forms_traits LIKE 'override_value';
