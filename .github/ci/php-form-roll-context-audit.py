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


desktop_actions = require(
    "app/partials/bio/bio_page_section_12_actions.php",
    "let activeFormId = 0;",
    "url.searchParams.set('form_id', String(activeFormId))",
    "document.addEventListener('hg:form-change'",
)

mobile_bridge = require(
    "assets/js/hg-mobile-form-dice-context.js",
    "[data-hg-mobile-form-select]",
    "[data-mobile-action-card] a.boton2",
    "url.searchParams.set('form_id', String(formId))",
    "document.addEventListener('hg-mobile-form-change'",
)
if ".name" in mobile_bridge or "form.name" in mobile_bridge:
    errors.append("mobile Form dice bridge must propagate canonical form_id, not infer Form identity from display names")

require(
    "app/mobile/controllers/character_detail.php",
    "/assets/js/hg-mobile-form-dice-context.js",
)

dice = require(
    "app/controllers/tool/dice_roller.php",
    "$form_active_form_id = (int)($queryInput['form_id'] ?? 0);",
    "$form_active_form_id = (int)($bodyInput['form_id'] ?? 0);",
    "hg_dice_resolve_form_attribute_value(",
    "name='form_id'",
    "selectedFormAttrModifier",
    "formAdjusted",
    "displayValue",
)

resolver = require(
    "app/domains/dice/form_effects.php",
    "JOIN dim_breeds br ON br.id = c.breed_id AND br.form_system_id = f.system_id",
    "LEFT JOIN bridge_forms_traits b ON b.form_id = f.id AND b.trait_id = ?",
    "if ($row['override_value'] !== null)",
    "if ($row['modifier'] !== null)",
)
if "strength_bonus" in resolver or "dexterity_bonus" in resolver or "stamina_bonus" in resolver:
    errors.append("dice Form resolver must not reintroduce legacy dim_forms physical modifier columns")

if errors:
    for error in errors:
        print(f"ERROR: {error}", file=sys.stderr)
    sys.exit(1)

print("Form roll context regression audit: OK")
