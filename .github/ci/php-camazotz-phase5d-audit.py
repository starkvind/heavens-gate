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
            errors.append(f"{path}: missing Camazotz Phase 5D contract fragment: {needle}")
    return source


presentation = require(
    "app/domains/characters/form_presentation_queries.php",
    "regen_normal_per_turn",
    "native_form_id",
    "regen_in_native_form",
    "regeneration_label",
)

bio = require(
    "app/controllers/bio/bio_page.php",
    "hg_characters_fetch_form_presentation_by_ids($link, $bioFormIds, (int)$bioRace)",
    "regeneration_label",
)

require(
    "app/mobile/controllers/character_detail.php",
    "hg_characters_fetch_form_presentation_by_ids($link, $mobileFormIds, (int)$breedId)",
    "regeneration_label",
)

bio_forms = require(
    "app/partials/bio/bio_page_section_05_forms.php",
    "form.regeneration_label",
)
if "Según Raza natal" in bio_forms:
    errors.append("character Form renderer must resolve actual birth-Race regeneration, not show generic contextual text")

systems_queries = require(
    "app/domains/systems/queries.php",
    "function hg_systems_fetch_form_modifiers",
    "FROM bridge_forms_traits bft",
    "AS public_hpregen",
    "native_breed.native_form_id = f.id",
)

desktop_form = require(
    "app/controllers/systems/system_form_page.php",
    "hg_systems_fetch_form_modifiers($link, $formId)",
    "$row['public_hpregen']",
    "foreach ($formModifiers as $modifier)",
)
for forbidden in ("$bonusSTR", "$bonusDEX", "$bonusRES", "Según Raza natal"):
    if forbidden in desktop_form:
        errors.append(f"desktop public Form page reintroduced obsolete presentation: {forbidden}")

mobile_systems = require(
    "app/mobile/controllers/systems.php",
    "hg_systems_fetch_form_modifiers($link, $formId)",
    "$form['public_hpregen']",
    "foreach ($formModifiers as $modifier)",
    "patron_totem_id",
    "Patrono tribal",
)
if "Según Raza natal" in mobile_systems:
    errors.append("mobile public Form page reintroduced misleading generic regeneration label")

admin_service = require(
    "app/domains/characters/admin_service.php",
    "'bridge_tribes_breeds' => true",
    "'bridge_traits_availability' => true",
)

admin_queries = require(
    "app/domains/characters/admin_queries.php",
    "bridge_tribes_breeds",
    "bridge_traits_availability",
    "breed_compatibility",
    "traitAvailability",
)

admin_controller = require(
    "app/controllers/admin/admin_characters.php",
    "TRIBE_BREED_COMPATIBILITY",
    "TRAIT_AVAILABILITY",
    "La Tribu seleccionada no admite esa Raza de nacimiento.",
    "no está disponible para la combinación de Raza/Auspicio/Tribu seleccionada.",
)

admin_js = require(
    "assets/js/admin/admin-characters.js",
    "TRIBE_BREED_COMPATIBILITY",
    "TRAIT_AVAILABILITY",
    "function isScopedTraitAllowed",
    "function tribesForBreed",
    "function updateTribeSet",
    "La Tribu seleccionada no admite esa Raza de nacimiento.",
)

sheet_queries = require(
    "app/domains/characters/queries.php",
    "bridge_traits_availability",
    "a.scope_type = 'tribe'",
)

tribe_page = require(
    "app/controllers/systems/system_detail_page.php",
    "patron_totem_id",
    "Patrono tribal",
    "Forma natal:",
    "Regeneración fuera de la Forma natal:",
)

dice = require(
    "app/domains/dice/form_effects.php",
    "hg_characters_fetch_form_presentation_by_ids($db, [$formId], $breedId)",
)

if errors:
    for error in errors:
        print(f"ERROR: {error}", file=sys.stderr)
    raise SystemExit(1)

print("Camazotz Phase 5D runtime contract: OK")
