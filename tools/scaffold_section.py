#!/usr/bin/env python3
"""
Scaffold for simple public sections in Heaven's Gate.

Creates a controller file and wires a static friendly route into:
- app/bootstrap/request_router.php
- app/bootstrap/body_work.php

Optionally:
- creates a CSS file under assets/css
- adds a fallback static menu entry in app/partials/main_menu.php
- maps the new slug to an existing static menu block

Intended scope:
- simple public pages such as /codex-guide
- not entity-detail routes with pretty_id / slug normalization
"""

from __future__ import annotations

import argparse
import re
import sys
from pathlib import Path


ROOT = Path(__file__).resolve().parent.parent
REQUEST_ROUTER = ROOT / "app" / "bootstrap" / "request_router.php"
BODY_WORK = ROOT / "app" / "bootstrap" / "body_work.php"
MAIN_MENU = ROOT / "app" / "partials" / "main_menu.php"
ASSETS_CSS = ROOT / "assets" / "css"
CONTROLLERS = ROOT / "app" / "controllers"

MENU_BLOCKS = {
    "startMenu",
    "bioMenu",
    "archivoMenu",
    "loreMenu",
    "systemMenu",
    "powersMenu",
    "toolsMenu",
}


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Create a simple public section and wire it into the site router."
    )
    parser.add_argument("--route-key", required=True, help="Internal route key, e.g. codex_guide")
    parser.add_argument("--slug", required=True, help="Public slug without leading slash, e.g. codex-guide")
    parser.add_argument("--title", required=True, help="Page title and visible H2")
    parser.add_argument(
        "--controller-group",
        default="main",
        help="Controller subdirectory under app/controllers. Default: main",
    )
    parser.add_argument(
        "--controller-file",
        default=None,
        help="Controller filename. Default: <group>_<route-key>.php for main, else <route-key>.php",
    )
    parser.add_argument(
        "--section-label",
        default=None,
        help="Section label used by body_work.php. Default: same as title",
    )
    parser.add_argument(
        "--description",
        default="Nueva seccion publica de Heaven's Gate.",
        help="Meta description for the generated controller",
    )
    parser.add_argument(
        "--css-file",
        default=None,
        help="Existing CSS file under /assets/css or filename to create there, e.g. hg-docs.css",
    )
    parser.add_argument(
        "--create-css",
        action="store_true",
        help="Create the CSS file passed in --css-file if it does not exist",
    )
    parser.add_argument(
        "--menu-label",
        default=None,
        help="If set, add the section to the fallback static menu with this label",
    )
    parser.add_argument(
        "--menu-block",
        choices=sorted(MENU_BLOCKS),
        default=None,
        help="Static menu block to use for fallback menu wiring and open-state mapping",
    )
    parser.add_argument(
        "--dry-run",
        action="store_true",
        help="Show intended changes without writing files",
    )
    return parser.parse_args()


def fail(message: str) -> None:
    print(f"ERROR: {message}", file=sys.stderr)
    sys.exit(1)


def normalize_slug(slug: str) -> str:
    slug = slug.strip().strip("/")
    if not slug:
        fail("slug cannot be empty")
    if "/" in slug:
        fail("this scaffold only supports one-level simple slugs like codex-guide")
    return slug


def normalize_route_key(route_key: str) -> str:
    route_key = route_key.strip()
    if not re.fullmatch(r"[A-Za-z0-9_]+", route_key):
        fail("route-key must contain only letters, numbers and underscore")
    return route_key


def default_controller_filename(group: str, route_key: str) -> str:
    if group == "main":
        return f"main_{route_key}.php"
    return f"{route_key}.php"


def controller_template(title: str, description: str, css_href: str | None) -> str:
    lines = [
        "<?php setMetaFromPage(",
        f'    "{php_string_escape(title)} | Heaven\'s Gate",',
        f'    "{php_string_escape(description)}",',
        '    null,',
        '    "website"',
        "); ?>",
    ]
    if css_href:
        lines.append(f'<link rel="stylesheet" href="{css_href}">')
    lines.extend(
        [
            "",
            f"<h2>{html_escape(title)}</h2>",
            f"<p>{html_escape(description)}</p>",
            "",
        ]
    )
    return "\n".join(lines)


def css_template(slug: str) -> str:
    class_name = slug.replace("-", "_")
    return "\n".join(
        [
            f"/* Styles for /{slug} */",
            f".section-{class_name} {{",
            "    display: block;",
            "}",
            "",
        ]
    )


def html_escape(value: str) -> str:
    return (
        value.replace("&", "&amp;")
        .replace("<", "&lt;")
        .replace(">", "&gt;")
        .replace('"', "&quot;")
    )


def php_string_escape(value: str) -> str:
    return value.replace("\\", "\\\\").replace('"', '\\"')


def read_text(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def write_text(path: Path, content: str, dry_run: bool) -> None:
    if dry_run:
        return
    path.write_text(content, encoding="utf-8", newline="\n")


def insert_before_marker(text: str, marker: str, snippet: str, label: str) -> tuple[str, bool]:
    if snippet in text:
        return text, False
    index = text.find(marker)
    if index == -1:
        fail(f"could not find marker for {label}")
    return text[:index] + snippet + text[index:], True


def patch_request_router(route_key: str, slug: str, dry_run: bool) -> list[str]:
    content = read_text(REQUEST_ROUTER)
    changes: list[str] = []

    direct_snippet = f"        '{route_key}' => '/{slug}',\n"
    content, changed = insert_before_marker(
        content,
        "    ];\n\n    if (isset($direct[$route])) {",
        direct_snippet,
        "request_router direct routes",
    )
    if changed:
        changes.append(f"request_router.php: alta legacy -> /{slug}")

    static_snippet = f"        '/{slug}' => ['p' => '{route_key}'],\n"
    content, changed = insert_before_marker(
        content,
        "    ];\n\n    if (isset($static[$path])) {",
        static_snippet,
        "request_router static routes",
    )
    if changed:
        changes.append(f"request_router.php: alta /{slug} -> p={route_key}")

    write_text(REQUEST_ROUTER, content, dry_run)
    return changes


def patch_body_work(route_key: str, controller_rel: str, section_label: str, dry_run: bool) -> list[str]:
    content = read_text(BODY_WORK)
    changes: list[str] = []
    snippet = f"\t'{route_key}' => ['{controller_rel}', '{php_string_escape(section_label)}'],\n"
    content, changed = insert_before_marker(
        content,
        "\n\t// Legacy aliases\n",
        snippet,
        "body_work routes",
    )
    if changed:
        changes.append(f"body_work.php: alta route key {route_key}")
    write_text(BODY_WORK, content, dry_run)
    return changes


def find_matching_div_end(text: str, div_id: str) -> int:
    id_patterns = [f'id="{div_id}"', f"id='{div_id}'"]
    id_index = -1
    for pattern in id_patterns:
        id_index = text.find(pattern)
        if id_index != -1:
            break

    if id_index == -1:
        fail(f"could not find menu block {div_id} in main_menu.php")

    div_start = text.rfind("<div", 0, id_index)
    if div_start == -1:
        fail(f"could not find opening <div> for menu block {div_id}")

    pos = text.find(">", id_index)
    if pos == -1:
        fail(f"could not find end of opening <div> for menu block {div_id}")
    pos += 1

    depth = 1
    token_pattern = re.compile(r"<div\b[^>]*>|</div>")
    for token in token_pattern.finditer(text, pos):
        value = token.group(0)
        if value.startswith("<div"):
            depth += 1
        else:
            depth -= 1
            if depth == 0:
                return token.start()
    fail(f"could not resolve closing </div> for menu block {div_id}")


def patch_static_menu(slug: str, menu_label: str, menu_block: str, dry_run: bool) -> list[str]:
    content = read_text(MAIN_MENU)
    href = f'/{slug}'
    anchor_snippet = f'\n\t\t\t\t<a href="{href}"><div class="renglonMenu">{html_escape(menu_label)}</div></a>'
    changes: list[str] = []

    if href not in content:
        insert_at = find_matching_div_end(content, menu_block)
        content = content[:insert_at] + anchor_snippet + content[insert_at:]
        changes.append(f"main_menu.php: enlace fallback {href} en {menu_block}")

    path_clause = (
        f"\t\tif (hg_starts_with($path, '/{slug}')) {{\n"
        f"\t\t\treturn '{menu_block}';\n"
        "\t\t}\n"
    )
    if path_clause not in content:
        marker = "\t\treturn null;\n\t}\n"
        content, changed = insert_before_marker(content, marker, path_clause, "menu open mapping")
        if changed:
            changes.append(f"main_menu.php: apertura automatica para /{slug}")

    write_text(MAIN_MENU, content, dry_run)
    return changes


def create_controller(
    controller_path: Path,
    title: str,
    description: str,
    css_href: str | None,
    dry_run: bool,
) -> list[str]:
    if controller_path.exists():
        return [f"{controller_path.relative_to(ROOT)} ya existe, no se sobrescribe"]

    content = controller_template(title, description, css_href)
    if not dry_run:
        controller_path.parent.mkdir(parents=True, exist_ok=True)
        controller_path.write_text(content, encoding="utf-8", newline="\n")
    return [f"creado {controller_path.relative_to(ROOT)}"]


def create_css(css_path: Path, slug: str, dry_run: bool) -> list[str]:
    if css_path.exists():
        return [f"{css_path.relative_to(ROOT)} ya existe, no se sobrescribe"]
    content = css_template(slug)
    if not dry_run:
        css_path.parent.mkdir(parents=True, exist_ok=True)
        css_path.write_text(content, encoding="utf-8", newline="\n")
    return [f"creado {css_path.relative_to(ROOT)}"]


def main() -> None:
    args = parse_args()
    route_key = normalize_route_key(args.route_key)
    slug = normalize_slug(args.slug)
    title = args.title.strip()
    section_label = (args.section_label or title).strip()
    group = args.controller_group.strip().strip("/\\")
    if not re.fullmatch(r"[A-Za-z0-9_]+", group):
        fail("controller-group must contain only letters, numbers and underscore")

    controller_file = args.controller_file or default_controller_filename(group, route_key)
    if not controller_file.endswith(".php"):
        fail("controller-file must end with .php")

    controller_path = CONTROLLERS / group / controller_file
    controller_rel = f"app/controllers/{group}/{controller_file}"

    css_href = None
    css_path = None
    if args.css_file:
        css_name = Path(args.css_file).name
        if not css_name.endswith(".css"):
            fail("css-file must end with .css")
        css_path = ASSETS_CSS / css_name
        css_href = f"/assets/css/{css_name}"

    planned_changes: list[str] = []
    planned_changes.extend(create_controller(controller_path, title, args.description.strip(), css_href, args.dry_run))

    if args.create_css:
        if css_path is None:
            fail("--create-css requires --css-file")
        planned_changes.extend(create_css(css_path, slug, args.dry_run))

    planned_changes.extend(patch_request_router(route_key, slug, args.dry_run))
    planned_changes.extend(patch_body_work(route_key, controller_rel, section_label, args.dry_run))

    if args.menu_label or args.menu_block:
        if not args.menu_label or not args.menu_block:
            fail("--menu-label and --menu-block must be used together")
        planned_changes.extend(patch_static_menu(slug, args.menu_label.strip(), args.menu_block, args.dry_run))

    print("Scaffold completado." if not args.dry_run else "Dry run completado.")
    print("")
    print("Cambios:")
    for change in planned_changes:
        print(f"- {change}")

    print("")
    print("Recuerda:")
    print("- Este script resuelve solo secciones simples, no detalles con pretty_id.")
    print("- Si el menu real sale de dim_menu_items, tendras que dar de alta la entrada tambien en BD.")
    print("- Revisa el controlador generado y ajusta HTML, consultas y CSS antes de publicar.")


if __name__ == "__main__":
    main()
