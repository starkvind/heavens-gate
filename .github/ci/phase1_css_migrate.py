from __future__ import annotations

from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[2]
APP = ROOT / "app"
LEGACY_CSS = ROOT / "assets" / "css" / "pages" / "legacy"

SKIP_PREFIXES = (
    "app/controllers/admin/",
    "app/mobile/",
    "app/partials/admin/",
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
    "app/tools/forum_topic_viewer_tool.php",
}

RAW_LINK_RE = re.compile(
    r"(?m)^(?P<indent>[ \t]*)(?P<tag><link\b(?=[^>]*\brel\s*=\s*(['\"])stylesheet\3)[^>]*\bhref\s*=\s*(['\"])(?P<href>[^'\"]+)\4[^>]*>)[ \t]*$",
    re.I,
)
RAW_STYLE_RE = re.compile(
    r"(?ms)^(?P<indent>[ \t]*)<style\b[^>]*>(?P<css>.*?)</style\s*>[ \t]*$",
    re.I,
)
ECHO_LINK_SINGLE_RE = re.compile(
    r"(?P<indent>^[ \t]*)echo\s+'(?P<tag><link\b[^'\n]*\bstylesheet\b[^'\n]*>)'\s*;",
    re.I | re.M,
)
ECHO_LINK_DOUBLE_RE = re.compile(
    r'(?P<indent>^[ \t]*)echo\s+"(?P<tag><link\b[^"\n]*\bstylesheet\b[^"\n]*>)"\s*;',
    re.I | re.M,
)
ECHO_STYLE_DOUBLE_RE = re.compile(
    r'(?ms)(?P<indent>^[ \t]*)echo\s+"<style>(?P<css>.*?)</style>"\s*;'
)
ECHO_STYLE_SINGLE_RE = re.compile(
    r"(?ms)(?P<indent>^[ \t]*)echo\s+'<style>(?P<css>.*?)</style>'\s*;"
)
HREF_RE = re.compile(r"\bhref\s*=\s*(['\"])(?P<href>[^'\"]+)\1", re.I)


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
    last_open = text.rfind("<?", 0, start)
    last_close = text.rfind("?>", 0, start)
    return last_open > last_close


def php_string(value: str) -> str:
    return "'" + value.replace("\\", "\\\\").replace("'", "\\'") + "'"


def register_or_fallback_raw(href: str, original_tag: str, indent: str = "") -> str:
    return (
        indent
        + "<?php if (function_exists('hg_page_register_stylesheet')) { "
        + f"hg_page_register_stylesheet({php_string(href)}); "
        + f"}} else {{ ?>{original_tag}<?php }} ?>"
    )


def register_or_fallback_php(href: str, original_tag: str, indent: str = "") -> str:
    return (
        indent
        + "if (function_exists('hg_page_register_stylesheet')) {\n"
        + indent
        + f"    hg_page_register_stylesheet({php_string(href)});\n"
        + indent
        + "} else {\n"
        + indent
        + f"    echo {php_string(original_tag)};\n"
        + indent
        + "}"
    )


def legacy_css_href(path: Path) -> str:
    rel = path.relative_to(APP).with_suffix("").as_posix().replace("/", "-")
    return f"/assets/css/pages/legacy/{rel}.css"


def write_legacy_css(path: Path, blocks: list[str]) -> str:
    href = legacy_css_href(path)
    target = ROOT / href.lstrip("/")
    target.parent.mkdir(parents=True, exist_ok=True)
    existing = target.read_text(encoding="utf-8") if target.exists() else ""
    if not existing:
        existing = (
            "/* Phase 1 extraction from "
            + path.relative_to(ROOT).as_posix()
            + ". Presentation intentionally unchanged; ownership will be refined in later phases. */\n"
        )
    for css in blocks:
        css = css.strip()
        if css and css not in existing:
            existing = existing.rstrip() + "\n\n" + css + "\n"
    target.write_text(existing, encoding="utf-8")
    return target.relative_to(ROOT).as_posix()


def migrate_echo_links(text: str) -> tuple[str, int]:
    count = 0
    for pattern in (ECHO_LINK_SINGLE_RE, ECHO_LINK_DOUBLE_RE):
        matches = list(pattern.finditer(text))
        for match in reversed(matches):
            tag = match.group("tag")
            href_match = HREF_RE.search(tag)
            if not href_match:
                continue
            href = href_match.group("href")
            if "<?" in href or "?>" in href:
                continue
            replacement = register_or_fallback_php(href, tag, match.group("indent") or "")
            text = text[:match.start()] + replacement + text[match.end():]
            count += 1
    return text, count


def migrate_echo_styles(path: Path, text: str) -> tuple[str, int, list[str]]:
    count = 0
    blocks: list[str] = []
    for pattern in (ECHO_STYLE_DOUBLE_RE, ECHO_STYLE_SINGLE_RE):
        matches = list(pattern.finditer(text))
        for match in reversed(matches):
            css = match.group("css").strip()
            # Persistent presentation only. Skip anything with PHP interpolation.
            if "<?" in css or "?>" in css or "${" in css:
                continue
            href = legacy_css_href(path)
            replacement = register_or_fallback_php(
                href,
                f'<link rel="stylesheet" href="{href}">',
                match.group("indent") or "",
            )
            text = text[:match.start()] + replacement + text[match.end():]
            blocks.insert(0, css)
            count += 1
    extracted = [write_legacy_css(path, blocks)] if blocks else []
    return text, count, extracted


def migrate_file(path: Path) -> tuple[bool, int, int, list[str]]:
    text = path.read_text(encoding="utf-8")
    original = text
    links_migrated = 0
    styles_migrated = 0
    extracted: list[str] = []

    link_matches = [m for m in RAW_LINK_RE.finditer(text) if not is_inside_php(text, m.start())]
    for match in reversed(link_matches):
        href = match.group("href")
        tag = match.group("tag")
        if "<?" in href or "?>" in href:
            continue
        replacement = register_or_fallback_raw(href, tag, match.group("indent") or "")
        text = text[:match.start()] + replacement + text[match.end():]
        links_migrated += 1

    style_matches = [m for m in RAW_STYLE_RE.finditer(text) if not is_inside_php(text, m.start())]
    raw_blocks: list[str] = []
    for match in reversed(style_matches):
        css = match.group("css").strip()
        if "<?" in css or "?>" in css:
            continue
        raw_blocks.insert(0, css)
        href = legacy_css_href(path)
        fallback = f'<link rel="stylesheet" href="{href}">'
        replacement = register_or_fallback_raw(href, fallback, match.group("indent") or "")
        text = text[:match.start()] + replacement + text[match.end():]
        styles_migrated += 1
    if raw_blocks:
        extracted.append(write_legacy_css(path, raw_blocks))

    text, echo_links = migrate_echo_links(text)
    links_migrated += echo_links

    text, echo_styles, echo_extracted = migrate_echo_styles(path, text)
    styles_migrated += echo_styles
    extracted.extend(echo_extracted)

    if text != original:
        path.write_text(text, encoding="utf-8")
        return True, links_migrated, styles_migrated, sorted(set(extracted))
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
    for item in sorted(set(extracted_files)):
        print(f"  CSS  {item}")


if __name__ == "__main__":
    main()
