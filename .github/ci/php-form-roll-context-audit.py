#!/usr/bin/env python3
from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parents[2]
errors = []


def require(path: str, *needles: str) -> str:
    target = ROOT / path
    if not target.is_file():
        errors.append(f"missing file: {path}")
        return ""
    source = target.read_text(encoding="utf-8", errors="replace")
    for needle in needles:
        if needle not in source:
            errors.append(f"{path}: missing contract fragment: {needle}")
    return source


require(
    "app/partials/bio/bio_page_section_12_actions.php",
    "let activeFormId = 0;",
    "url.searchParams.set('form_id', String(activeFormId))",
    "document.addEventListener('hg:form-change'",
)

mobile_renderer = require(
    "assets/js/hg-mobile-form-position.js",
    "[data-hg-mobile-form-select]",
    ".hg-mobile-actions [data-mobile-action-card]",
    "const syncActionPools = (form) => {",
    "url.searchParams.get('attr_trait_id')",
    "url.searchParams.get('skill_trait_id')",
    "const pool = resolveValue(baseAttribute, form, attributeTraitId) + skillValue;",
    "target.dataset.hgMobileActionDice = '1';",
    "syncActionPools(form);",
    "const formId = Number(form && form.id ? form.id : 0);",
    "url.searchParams.set('form_id', String(formId))",
    "select.addEventListener('change', render)",
)
if "strength_bonus" in mobile_renderer or "dexterity_bonus" in mobile_renderer or "stamina_bonus" in mobile_renderer:
    errors.append("mobile Form renderer must not use legacy physical-bonus columns")

require(
    "app/mobile/controllers/character_detail.php",
    "/assets/js/hg-mobile-form-position.js",
)

dice_controller = require(
    "app/controllers/tool/dice_roller.php",
    "$form_active_form_id = (int)($queryInput['form_id'] ?? 0);",
    "$form_active_form_id = (int)($bodyInput['form_id'] ?? 0);",
    "hg_dice_resolve_form_attribute_value(",
    "$activeFormContext = hg_dice_resolve_form_context(",
    "name='form_id'",
    "selectedFormAttrModifier",
    "formAdjusted",
    "displayValue",
    "Forma activa:",
    "Armas de fuego:",
    "no permite utilizar armas de fuego",
)
for forbidden in (
    "$queryInput['form_name']",
    "$queryInput['weapons']",
    "$queryInput['firearms']",
    "$bodyInput['form_name']",
    "$bodyInput['weapons']",
    "$bodyInput['firearms']",
):
    if forbidden in dice_controller:
        errors.append(f"dice roller must not trust client-supplied Form presentation data: {forbidden}")

resolver = require(
    "app/domains/dice/form_effects.php",
    "hg_dice_resolve_form_context",
    "hg_characters_fetch_forms_for_system(",
    "hg_characters_fetch_form_presentation_by_ids(",
    "'weapons' => (int)($presentation['weapons'] ?? 0)",
    "'firearms' => (int)($presentation['firearms'] ?? 0)",
    "FROM bridge_forms_traits",
    "if ($row['override_value'] !== null)",
    "if ($row['modifier'] !== null)",
)
if "strength_bonus" in resolver or "dexterity_bonus" in resolver or "stamina_bonus" in resolver:
    errors.append("dice Form resolver must not reintroduce legacy dim_forms physical modifier columns")
for forbidden in ("strtolower(", "strcasecmp(", "LOWER(f.form)", "LIKE '%crinos%'"):
    if forbidden in resolver:
        errors.append(f"dice Form resolver must not infer Form identity from display names: {forbidden}")

if errors:
    for error in errors:
        print(f"ERROR: {error}", file=sys.stderr)
    sys.exit(1)

print("Form roll context regression audit: OK")
