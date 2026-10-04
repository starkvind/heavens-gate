#!/usr/bin/env python3
from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parents[2]


def fail(message: str) -> None:
    print(f"Basal Form contract: FAIL - {message}", file=sys.stderr)
    raise SystemExit(1)


def read(relative: str) -> str:
    path = ROOT / relative
    try:
        return path.read_text(encoding="utf-8")
    except OSError as exc:
        fail(f"cannot read {relative}: {exc}")


presentation = read("app/domains/characters/form_presentation_queries.php")
for needle in (
    "SELECT id, description, silhouette_image_url, weapons, firearms, hpregen",
    "FROM dim_forms",
):
    if needle not in presentation:
        fail(f"canonical presentation query lost {needle!r}")

for relative in (
    "app/controllers/bio/bio_page.php",
    "app/mobile/controllers/character_detail.php",
):
    source = read(relative)
    if "hg_characters_fetch_form_presentation_by_ids" not in source:
        fail(f"{relative} no longer consumes the canonical Form presentation resolver")

mobile_view = read("app/mobile/views/character_detail.php")
for forbidden in (
    '<option value="">Forma base</option>',
    'Forma base: atributos originales.',
):
    if forbidden in mobile_view:
        fail(f"mobile view reintroduced synthetic basal state: {forbidden!r}")

mobile_position = read("assets/js/hg-mobile-form-position.js")
for forbidden in (
    "legacyBaseOption",
    "option[value=\"\"]",
):
    if forbidden in mobile_position:
        fail(f"mobile Form renderer still contains legacy synthetic-base compatibility: {forbidden!r}")

migration = read("tools/sql/2026-10-04-issue-37-basal-form-capabilities.sql")
for basal_name in ("Homínido", "Antropos", "Hitogata", "Balaram", "Bajopiel"):
    if basal_name not in migration:
        fail(f"data normalization does not cover canonical basal Form {basal_name}")
for assignment in ("weapons = 1", "firearms = 1", "hpregen = 0"):
    if assignment not in migration:
        fail(f"data normalization lost canonical capability assignment {assignment!r}")

print("Basal Form contract: OK")
