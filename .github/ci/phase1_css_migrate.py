from __future__ import annotations

from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[2]
APP = ROOT / "app"
LEGACY_CSS = ROOT / "assets" / "css" / "pages" / "legacy"

SKIP_PREFIXES = (
    "app/controllers/admin/",
    "app/mobile/",
)
SKIP_FILES = {
    "app/bootstrap/head_work.php",
    "app/helpers/page_assets.php",
    "app/helpers/public_response.php",
    "app/helpers/runtime_response.php",
    "app/partials/datatable_assets.php",
    "app/partials/power_catalog_tabs.php",
    "app/controllers/tool/combat_simulator.php",
    "app/controllers/tool/game_cards.php",
    "app/controllers/tool/game_cards_mobile.php",
}

RAW_LINK_RE = re.compile(
    r"(?P<tag><link\b(?=[^>]*\brel\s*=\s*(['\"])stylesheet\2)[^>]*\bhref\s*=\s*(['\"])(?P<href>[^'\"]+)\3[^>]*>)",
    re.I,
)
RAW_STYLE_RE = re.compile(r"<style\b[^>]*>(?P<css>.*?)</style\s*>", re.I | re.S)
PHP_CHUNK_RE = re.compile(r"<\?(?:php|=)?.*?\?>", re.I | re.S)


def public_migration_file(path: Path) -> bool:
    rel = path.relative_to(ROOT).as_posix()
    if rel in SKIP_FILES:
        return False
    if any(rel.startswith(prefix) for prefix in SKIP_PREFIXES):
        return False
    if rel.startswith("app/partials/forum_"):
        return False
    return True


def is_inside_php(text: str, start: int) -> bool:
    """Return True when start lies in an open PHP block.

    This keeps the codemod away from tags embedded in PHP strings/heredocs.
    """
    last_open = text.rfind("<?", 0, start)
    last_close = text.rfind("?>", 0, start)
    return last_open > last_close


def php_string(value: str) -> str:
    return "'" + value.replace("\\", "\\\\").replace("'", "\\'") + "'"


def register_or_fallback(href: str, original_tag: str) -> str:
    return (
        "<?php if (function_exists('hg_page_register_stylesheet')) { "
        f"hg_page_register_stylesheet({php_string(href)}); "
        f"}} else {{ ?>{original_tag}<?php }} ?>"
    )


def legacy_css_href(path: Path) -> str:
    rel = path.relative_to(APP).with_suffix("").as_posix().replace("/", "-")
    return f"/assets/css/pages/legacy/{rel}.css"


def migrate_file(path: Path) -> tuple[bool, int, int, list[str]]:
    text = path.read_text(encoding="utf-8")
    original = text
    links_migrated = 0
    styles_migrated = 0
    extracted: list[str] = []

    # Work from right to left so offsets remain stable.
    link_matches = [m for m in RAW_LINK_RE.finditer(text) if not is_inside_php(text, m.start())]
    for match in reversed(link_matches):
        href = match.group("href")
        tag = match.group("tag")
        if "<?" in href or "?>" in href:
            continue
        replacement = register_or_fallback(href, tag)
        text = text[: match.start()] + replacement + text[match.end() :]
        links_migrated += 1

    # Extract raw static style blocks. Dynamic PHP-bearing styles are left for manual migration.
    style_matches = [m for m in RAW_STYLE_RE.finditer(text) if not is_inside_php(text, m.start())]
    css_blocks: list[str] = []
    for match in reversed(style_matches):
        css = match.group("css").strip()
        if "<?" in css or "?>" in css:
            continue
        css_blocks.insert(0, css)
        href = legacy_css_href(path)
        fallback = f'<link rel="stylesheet" href="{href}">'
        replacement = register_or_fallback(href, fallback)
        text = text[: match.start()] + replacement + text[match.end() :]
        styles_migrated += 1

    if css_blocks:
        LEGACY_CSS.mkdir(parents=True, exist_ok=True)
        target = ROOT / legacy_css_href(path).lstrip("/")
        header = (
            "/* Phase 1 extraction from "
            + path.relative_to(ROOT).as_posix()
            + ". Presentation intentionally unchanged; ownership will be refined in later phases. */\n\n"
        )
        target.write_text(header + "\n\n".join(css_blocks) + "\n", encoding="utf-8")
        extracted.append(target.relative_to(ROOT).as_posix())

    if text != original:
        path.write_text(text, encoding="utf-8")
        return True, links_migrated, styles_migrated, extracted
    return False, 0, 0, []


def main() -> None:
    changed_files: list[str] = []
    extracted_files: list[str] = []
    total_links = 0
    total_styles = 0

    for path in sorted(APP.rglob("*.php")):
        if not public_migration_file(path):
            continue
        changed, links, styles, extracted = migrate_file(path)
        if changed:
            changed_files.append(path.relative_to(ROOT).as_posix())
            total_links += links
            total_styles += styles
            extracted_files.extend(extracted)

    print(f"Migrated stylesheet tags: {total_links}")
    print(f"Extracted static style blocks: {total_styles}")
    print(f"Changed PHP files: {len(changed_files)}")
    for item in changed_files:
        print(f"  PHP  {item}")
    for item in extracted_files:
        print(f"  CSS  {item}")


if __name__ == "__main__":
    main()
