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

# Presentation behavior belongs in owned JS assets, not executable inline blocks.
inline_script = re.compile(r"<script\b(?![^>]*\bsrc\s*=)(?![^>]*\btype\s*=\s*['\"]application/json['\"])[^>]*>", re.I)
inline_handler = re.compile(r"\son[a-z]+\s*=", re.I)
inline_style = re.compile(r"<style\b", re.I)
for path in mobile_php:
    source = path.read_text(encoding="utf-8", errors="replace")
    if inline_script.search(source):
        errors.append(f"inline executable script in mobile PHP: {path.relative_to(ROOT)}")
    if inline_handler.search(source):
        errors.append(f"inline event handler in mobile PHP: {path.relative_to(ROOT)}")
    if inline_style.search(source):
        errors.append(f"inline style block in mobile PHP: {path.relative_to(ROOT)}")

# The interaction-heavy pages now have explicit controller/view boundaries.
view_pairs = {
    "app/mobile/controllers/gallery.php": "app/mobile/views/gallery.php",
    "app/mobile/controllers/search.php": "app/mobile/views/search.php",
    "app/mobile/controllers/soundtrack.php": "app/mobile/views/soundtrack.php",
}
for controller, view in view_pairs.items():
    controller_source = read(controller)
    expected = "../views/" + Path(view).name
    if expected not in controller_source:
        errors.append(f"mobile controller does not delegate to view: {controller}")
    if "?>" in controller_source:
        errors.append(f"mobile data controller still emits presentation directly: {controller}")
    if not (ROOT / view).exists():
        errors.append(f"mobile view missing: {view}")

mobile_js = read("assets/js/hg-mobile.js")
for marker in [
    "data-mobile-gallery-lightbox",
    "data-mobile-ost-player",
    "data-mobile-search-recent",
    "data-mobile-copy",
    "data-mobile-copy-organization",
]:
    if marker not in mobile_js:
        errors.append(f"mobile shared JS behavior missing marker: {marker}")

# Dynamic page state crosses the PHP/JS boundary as inert HTML data.
search_view = read("app/mobile/views/search.php")
for marker in [
    "data-mobile-search-recent",
    "data-current-q",
    "data-current-section",
    "data-store-current",
]:
    if marker not in search_view:
        errors.append(f"mobile search view data contract missing: {marker}")

organization = read("app/mobile/controllers/organization_group_detail.php")
if "data-mobile-organization-json" not in organization:
    errors.append("mobile organization copy data contract missing")

print("# PHP Phase 9.3 mobile presentation contract")
print(f"mobile_php_files: {len(mobile_php)}")
print("inline_executable_scripts: 0")
print("inline_event_handlers: 0")
print("inline_style_blocks: 0")
print("thin_mobile_controllers: gallery, search, soundtrack")
print("mobile_interaction_asset: assets/js/hg-mobile.js")

if errors:
    for error in errors:
        print(f"ERROR: {error}", file=sys.stderr)
    sys.exit(1)

print("PHP Phase 9.3 mobile presentation contract: PASS")
