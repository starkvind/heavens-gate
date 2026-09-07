#!/usr/bin/env python3
from __future__ import annotations

from collections import Counter, defaultdict
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[2]
PHP_ROOTS = [ROOT / "app", ROOT / "api"]

PATTERNS = {
    "sql_calls": re.compile(r"\bmysqli_(?:query|prepare|real_query|multi_query)\b|->\s*(?:query|prepare)\s*\("),
    "schema_probe": re.compile(r"\bSHOW\s+COLUMNS\b|\binformation_schema\b", re.I),
    "get": re.compile(r"\$_GET\s*\["),
    "post": re.compile(r"\$_POST\s*\["),
    "cookie": re.compile(r"\$_COOKIE\s*\["),
    "request": re.compile(r"\$_REQUEST\s*\["),
    "include_require": re.compile(r"\b(?:include|include_once|require|require_once)\b"),
    "global": re.compile(r"\bglobal\s+\$[A-Za-z_]") ,
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

    body_count, body_cases = route_case_count(ROOT / "app/bootstrap/body_work.php")
    router_count, router_cases = route_case_count(ROOT / "app/bootstrap/request_router.php")
    overlap = sorted(set(body_cases) & set(router_cases))

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

    print("## Route concentration")
    print(f"body_work.php case labels: {body_count}")
    print(f"request_router.php case labels: {router_count}")
    print(f"case labels present in both: {len(overlap)}")
    if overlap:
        print("overlap: " + ", ".join(overlap[:40]) + (" ..." if len(overlap) > 40 else ""))
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
