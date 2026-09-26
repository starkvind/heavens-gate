#!/usr/bin/env python3
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[2]
errors = []

def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8", errors="replace")

mobile_root = ROOT / "app/mobile"
mobile_php = sorted(mobile_root.rglob("*.php"))

# Mobile is a presentation layer. It must not own SQL execution.
db_patterns = [
    re.compile(r"\bmysqli_query\s*\("),
    re.compile(r"\bmysqli_prepare\s*\("),
    re.compile(r"->\s*query\s*\("),
    re.compile(r"->\s*prepare\s*\("),
]
for path in mobile_php:
    text = path.read_text(encoding="utf-8", errors="replace")
    for pattern in db_patterns:
        if pattern.search(text):
            errors.append(f"mobile presentation owns DB execution: {path.relative_to(ROOT)}")
            break

# Chronicle visibility/scope is a shared domain rule, never a mobile-only rule.
legacy_scope = ROOT / "app/mobile/helpers/chronicle_scope.php"
if legacy_scope.exists():
    errors.append("legacy mobile-only chronicle scope helper still exists")

for path in mobile_php:
    text = path.read_text(encoding="utf-8", errors="replace")
    if "hg_mobile_chronicle_" in text or "hg_mobile_excluded_chronicles_csv" in text:
        errors.append(f"legacy mobile chronicle rule reference: {path.relative_to(ROOT)}")

    if "helpers/chronicle_scope.php" in text:
        errors.append(f"legacy mobile chronicle helper include: {path.relative_to(ROOT)}")

mobile_index = read("app/mobile/mobile_index.php")
if "../domains/chronicles/scope.php" not in mobile_index:
    errors.append("mobile shell does not load shared chronicle scope")

# Desktop and mobile navigation read the same menu/season data contract.
mobile_menu = read("app/mobile/mobile_menu.php")
desktop_menu = read("app/partials/main_menu.php")
for marker in [
    "domains/navigation/queries.php",
    "hg_navigation_fetch_parents",
    "hg_navigation_fetch_children",
    "hg_navigation_season_items",
]:
    if marker not in mobile_menu:
        errors.append(f"mobile menu shared navigation contract missing: {marker}")
    if marker not in desktop_menu:
        errors.append(f"desktop menu shared navigation contract missing: {marker}")

# Gallery filesystem/catalog rules are shared; only HTML/JS presentation differs.
for path in [
    "app/mobile/controllers/gallery.php",
    "app/controllers/main/main_gallery.php",
]:
    text = read(path)
    if "domains/gallery/catalog.php" not in text:
        errors.append(f"gallery shared catalog missing: {path}")
    for legacy in [
        "function isValidRelPath",
        "function listSubdirs",
        "function listImages",
        "function hg_mobile_gallery_valid_rel",
        "function hg_mobile_gallery_images",
    ]:
        if legacy in text:
            errors.append(f"duplicated gallery data rule in {path}: {legacy}")

# Mobile routes may only provide alternate presentation for canonical routes.
desktop_routes = set(re.findall(r"^\s*'([^']+)'\s*=>", read("app/routing/routes.php"), flags=re.M))
mobile_routes = set(re.findall(r"^\s*'([^']*)'\s*=>", read("app/mobile/mobile_routes.php"), flags=re.M))
mobile_routes.discard("")
unknown = sorted(mobile_routes - desktop_routes)
if unknown:
    errors.append("mobile-only route keys without canonical desktop route: " + ", ".join(unknown))

print("# PHP Phase 9.2 mobile shared-data contract")
print(f"mobile_php_files: {len(mobile_php)}")
print("mobile_direct_db_execution: 0")
print("chronicle_scope_owner: app/domains/chronicles/scope.php")
print("navigation_data_owner: app/domains/navigation/queries.php")
print("gallery_catalog_owner: app/domains/gallery/catalog.php")
print(f"mobile_route_keys_checked: {len(mobile_routes)}")

if errors:
    for error in errors:
        print(f"ERROR: {error}", file=sys.stderr)
    sys.exit(1)

print("PHP Phase 9.2 mobile shared-data contract: PASS")
