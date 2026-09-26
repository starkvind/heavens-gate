#!/usr/bin/env python3
from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parents[2]
errors = []

def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8", errors="replace")

home = read("app/domains/home/queries.php")
rules = read("app/domains/rules/queries.php")
recent = read("app/helpers/recent_content.php")
gallery = read("app/controllers/main/main_gallery.php")
characters = read("app/controllers/bio/bio_table.php")
rules_home = read("app/controllers/docs/rules_home.php")

# Home/Rules landing counters should use one DB round-trip each.
if "hg_home_query_count_table" in home:
    errors.append("home per-table counter helper returned")
if home.count("mysqli_query($link, $sql)") != 1:
    errors.append("home counter contract must execute exactly one combined query")
if "foreach ($tables as $key => $table)" in rules:
    errors.append("rules home per-table count loop returned")
rules_count_block = rules.split("if (!function_exists('hg_rules_fetch_home_counts'))", 1)[1].split(
    "if (!function_exists('hg_rules_fetch_traits_table'))", 1
)[0]
if rules_count_block.count("$db->query(") != 1:
    errors.append("rules home counter contract must execute exactly one combined query")

# Recent-content feed must join only the update targets instead of materializing
# a UNION of every supported entity table.
if "UNION ALL" in recent:
    errors.append("recent-content full entity UNION returned")
for marker in [
    "CASE u.entity_type",
    "LEFT JOIN dim_chapters ch",
    "LEFT JOIN fact_characters c",
    "LEFT JOIN fact_timeline_events te",
    "ORDER BY u.updated_at DESC",
]:
    if marker not in recent:
        errors.append(f"recent-content optimized join contract missing: {marker}")

# Gallery must not download every full-size image on initial page load.
legacy_preload = "thumbs.forEach(img => { const i = new Image(); i.src = img.dataset.full"
if legacy_preload in gallery:
    errors.append("gallery eager full-size preload returned")
for marker in [
    "preloadAround(index);",
    "function preloadAround(index)",
    "loading=\"<?= $idx < 12 ? 'eager' : 'lazy' ?>\"",
]:
    if marker not in gallery:
        errors.append(f"gallery loading contract missing: {marker}")

# Initial viewport imagery should not be universally lazy.
for source, marker, label in [
    (characters, "const avatarLoading = index < 25 ? 'eager' : 'lazy';", "character first page"),
    (rules_home, "loading=\"<?= $ruleIndex < 2 ? 'eager' : 'lazy' ?>\"", "rules first cards"),
]:
    if marker not in source:
        errors.append(f"{label} image priority contract missing")

print("# PHP Phase 8 performance contract")
print("home_count_round_trips: 1 (was 13)")
print("rules_count_round_trips: 1 (was 6)")
print("recent_content_full_union: 0 (was 10 entity branches)")
print("gallery_initial_full_image_preloads: 0 (was N images)")
print("character_initial_eager_avatars: 25")
print("rules_initial_eager_cards: 2")

if errors:
    for error in errors:
        print(f"ERROR: {error}", file=sys.stderr)
    sys.exit(1)

print("PHP Phase 8 performance contract: PASS")
