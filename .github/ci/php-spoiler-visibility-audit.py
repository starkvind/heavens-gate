#!/usr/bin/env python3
"""Enforce that unreleased spoiler layers remain outside all public read models."""
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[2]
errors = []

def read(path):
    file = ROOT / path
    if not file.is_file():
        errors.append(f"Missing {path}")
        return ""
    return file.read_text(encoding="utf-8")

public = read("app/domains/spoilers/queries.php")
admin = read("app/domains/spoilers/admin.php")
controller = read("app/controllers/admin/admin_spoilers.php")
desktop = read("app/controllers/bio/bio_page.php")
mobile = read("app/mobile/controllers/character_detail_data.php")
desktop_view = read("app/views/characters/biography.php")
mobile_view = read("app/mobile/views/character_detail.php")
relations = read("app/domains/characters/detail_queries.php")
graph = read("app/domains/relationships/queries.php")
export = read("app/presentation/characters/biography_export.php")
registry = read("app/helpers/admin_sections.php")

# Neither a public page nor an API may read the private tables itself:
# a single owning public domain exposes only released records.
for folder in ("app/controllers", "app/mobile", "app/views", "app/presentation", "assets"):
    for file in (ROOT / folder).rglob("*"):
        if file.is_file() and file.suffix in {".php", ".js"}:
            source = file.read_text(encoding="utf-8", errors="replace")
            if re.search(r"\b(?:fact_spoiler_layers|bridge_spoiler_character_relations)\b", source):
                errors.append(f"{file.relative_to(ROOT)} directly references spoiler storage")

if "is_published = 1" not in public:
    errors.append("Public spoilers read model must require explicit publication")
if public.count("is_published = 1") < 3:
    errors.append("All three public spoiler read paths must guard publication")
if "sl.entity_id = sr.source_id" not in public:
    errors.append("Public spoiler relations must match their layer owner")
if "SELECT id, title, content_text" not in public:
    errors.append("Public text must be read only from the scoped published-layers query")
if "is_published = 0" not in admin and "VALUES ('character', ?, ?, ?, 0)" not in admin:
    errors.append("New spoiler layers must default to unpublished")
if "hg_admin_require_session(false)" not in controller or "hg_admin_csrf_valid(" not in controller:
    errors.append("Spoiler editor must require authenticated Admin + CSRF for writes")
if "hg_spoilers_admin_schema_ready" not in controller:
    errors.append("Spoiler editor must fail closed before schema migration")
if "admin_spoilers" not in registry:
    errors.append("Spoiler editor must have an explicit Admin route")

for label, source, needle in [
    ("desktop controller", desktop, "hg_spoilers_public_layers("),
    ("mobile controller", mobile, "hg_spoilers_public_layers("),
    ("desktop view", desktop_view, "$bioPublishedRevelations"),
    ("mobile view", mobile_view, "$mobilePublishedRevelations"),
    ("biography export", export, "$bioPublishedRevelations"),
    ("profile relations", relations, "hg_spoilers_public_character_relations("),
    ("public graph", graph, "hg_spoilers_public_graph_relations("),
]:
    if needle not in source:
        errors.append(f"{label} does not integrate released spoilers")

if "htmlspecialchars" not in controller:
    errors.append("Admin form contents must be escaped")
if "nl2br(h(" not in desktop_view or "nl2br(hg_mobile_bio_h(" not in mobile_view:
    errors.append("Public revealed text must be HTML-escaped")
if not (ROOT / "admin_docs/SPOILER_LAYERS_ROLLOUT.md").exists():
    errors.append("Spoiler rollout/migration contract missing")

if errors:
    for error in errors:
        print("ERROR:", error, file=sys.stderr)
    raise SystemExit(1)
print("Spoiler public/private separation and Admin boundary: PASS")
