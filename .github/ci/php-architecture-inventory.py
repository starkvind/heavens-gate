#!/usr/bin/env python3
from __future__ import annotations

from collections import Counter, defaultdict
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[2]
PHP_ROOTS = [ROOT / "app", ROOT / "api"]

PATTERNS = {
    "sql_calls": re.compile(r"\bmysqli_(?:query|prepare|real_query|multi_query)\b|->\s*(?:query|prepare)\s*\("),
    "schema_probe": re.compile(r"\binformation_schema\s*\.|\bSHOW\s+(?:TABLES|COLUMNS|INDEX|KEYS)\b", re.I),
    "get": re.compile(r"\$_GET\s*\["),
    "post": re.compile(r"\$_POST\s*\["),
    "cookie": re.compile(r"\$_COOKIE\s*\["),
    "request": re.compile(r"\$_REQUEST\s*\["),
    "include_require": re.compile(r"\b(?:include|include_once|require|require_once)\b"),
    "global": re.compile(r"\bglobal\s+\$[A-Za-z_]"),
    "header": re.compile(r"\bheader\s*\("),
}


def php_files() -> list[Path]:
    files: list[Path] = []
    for root in PHP_ROOTS:
        if root.exists():
            files.extend(root.rglob("*.php"))
    index = ROOT / "index.php"
    if index.exists():
        files.append(index)
    return sorted(set(files))


def area_for(path: Path) -> str:
    rel = path.relative_to(ROOT)
    parts = rel.parts
    if parts[:2] == ("app", "controllers") and len(parts) >= 3:
        return f"controllers/{parts[2]}"
    if parts[:2] == ("app", "mobile"):
        return "mobile"
    if parts[:2] == ("app", "bootstrap"):
        return "bootstrap"
    if parts[:2] == ("app", "routing"):
        return "routing"
    if parts[:2] == ("app", "http"):
        return "http"
    if parts[:2] == ("app", "presentation"):
        return "presentation"
    if parts[:2] == ("app", "views"):
        return "views"
    if parts[:2] == ("app", "domains") and len(parts) >= 3:
        return f"domains/{parts[2]}"
    if parts[:2] == ("app", "helpers"):
        return "helpers"
    if parts[:2] == ("app", "partials"):
        return "partials"
    if parts and parts[0] == "api":
        return "api"
    if rel.as_posix() == "index.php":
        return "index"
    return parts[0] if parts else "other"


def count_matches(text: str) -> dict[str, int]:
    return {name: len(pattern.findall(text)) for name, pattern in PATTERNS.items()}


def route_case_count(path: Path) -> tuple[int, list[str]]:
    if not path.exists():
        return 0, []
    text = path.read_text(encoding="utf-8", errors="ignore")
    cases = re.findall(r"\bcase\s+['\"]([^'\"]+)['\"]\s*:", text)
    return len(cases), cases


def is_public_controller(path: str) -> bool:
    if path.startswith("app/mobile/controllers/"):
        return True
    if not path.startswith("app/controllers/"):
        return False
    return not path.startswith("app/controllers/admin/")


def main() -> None:
    files = php_files()
    per_file = []
    area_totals: dict[str, Counter] = defaultdict(Counter)

    for path in files:
        text = path.read_text(encoding="utf-8", errors="ignore")
        counts = count_matches(text)
        rel = path.relative_to(ROOT).as_posix()
        lines = text.count("\n") + 1
        area = area_for(path)
        row = {"path": rel, "lines": lines, **counts}
        per_file.append(row)
        area_totals[area]["files"] += 1
        area_totals[area]["lines"] += lines
        for key, value in counts.items():
            area_totals[area][key] += value

    totals = Counter()
    for row in per_file:
        totals["files"] += 1
        totals["lines"] += row["lines"]
        for key in PATTERNS:
            totals[key] += row[key]

    legacy_count, legacy_cases = route_case_count(ROOT / "app/routing/legacy_query.php")

    print("# PHP architecture inventory")
    print()
    print(f"PHP files: {totals['files']}")
    print(f"PHP lines: {totals['lines']}")
    print(f"SQL call sites: {totals['sql_calls']}")
    print(f"Schema probes: {totals['schema_probe']}")
    print(f"Raw $_GET reads: {totals['get']}")
    print(f"Raw $_POST reads: {totals['post']}")
    print(f"Raw $_COOKIE reads: {totals['cookie']}")
    print(f"Raw $_REQUEST reads: {totals['request']}")
    print(f"include/require tokens: {totals['include_require']}")
    print(f"global declarations: {totals['global']}")
    print()

    print("## Routing compatibility concentration")
    print(f"legacy_query.php case labels: {legacy_count}")
    if legacy_cases:
        print("legacy cases: " + ", ".join(legacy_cases[:40]) + (" ..." if len(legacy_cases) > 40 else ""))
    print()

    print("## Areas")
    print("area | files | lines | sql | schema | GET | POST | globals | includes")
    print("--- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---:")
    for area, c in sorted(area_totals.items(), key=lambda item: (-item[1]["lines"], item[0])):
        print(
            f"{area} | {c['files']} | {c['lines']} | {c['sql_calls']} | {c['schema_probe']} | "
            f"{c['get']} | {c['post']} | {c['global']} | {c['include_require']}"
        )
    print()

    public_sql = sorted(
        (row for row in per_file if row["sql_calls"] > 0 and is_public_controller(row["path"])),
        key=lambda row: (-row["sql_calls"], row["path"]),
    )
    print("## Remaining public controller SQL owners")
    print(f"files: {len(public_sql)}")
    print(f"sql call sites: {sum(row['sql_calls'] for row in public_sql)}")
    if public_sql:
        for row in public_sql:
            print(
                f"{row['path']}: sql={row['sql_calls']}, schema={row['schema_probe']}, lines={row['lines']}"
            )
    else:
        print("none")
    print()

    schema_owners = sorted(
        (row for row in per_file if row["schema_probe"] > 0),
        key=lambda row: (-row["schema_probe"], row["path"]),
    )
    print("## Schema introspection owners")
    print(f"files: {len(schema_owners)}")
    print(f"probe tokens: {sum(row['schema_probe'] for row in schema_owners)}")
    if schema_owners:
        for row in schema_owners:
            print(f"{row['path']}: schema={row['schema_probe']}, lines={row['lines']}")
    else:
        print("none")
    print()

    def top(metric: str, limit: int = 15):
        return sorted(per_file, key=lambda row: (-row[metric], -row["lines"], row["path"]))[:limit]

    for metric, title in [
        ("lines", "Largest PHP files"),
        ("sql_calls", "Most SQL call sites"),
        ("get", "Most raw $_GET reads"),
        ("post", "Most raw $_POST reads"),
        ("global", "Most global declarations"),
    ]:
        print(f"## {title}")
        shown = 0
        for row in top(metric):
            if row[metric] <= 0:
                continue
            print(f"{row['path']}: {row[metric]} ({row['lines']} lines)")
            shown += 1
        if shown == 0:
            print("none")
        print()


if __name__ == "__main__":
    main()
