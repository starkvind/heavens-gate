#!/usr/bin/env python3
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[2]

SQL = re.compile(r"\bmysqli_(?:query|prepare|real_query|multi_query)\b|->\s*(?:query|prepare)\s*\(")
SCHEMA = re.compile(r"\bSHOW\s+COLUMNS\b|\binformation_schema\b", re.I)
RAW_GET = re.compile(r"\$_GET\s*\[")
RAW_POST = re.compile(r"\$_POST\s*\[")
RAW_REQUEST = re.compile(r"\$_REQUEST\s*\[")

BASELINE = {
    "php_files": 349,
    "active_routes": 98,
    "mobile_routes": 76,
    "admin_controllers": 59,
    "public_controller_sql_owners": 4,
    "public_controller_sql_calls": 16,
    "schema_probes": 20,
    "raw_get_reads": 192,
    "raw_post_reads": 708,
    "raw_request_reads": 1,
    "legacy_query_cases": 47,
}

errors = []

php_files = sorted((ROOT / "app").rglob("*.php"))
api_root = ROOT / "api"
if api_root.exists():
    php_files.extend(sorted(api_root.rglob("*.php")))
if (ROOT / "index.php").exists():
    php_files.append(ROOT / "index.php")
php_files = sorted(set(php_files))

totals = {
    "schema_probes": 0,
    "raw_get_reads": 0,
    "raw_post_reads": 0,
    "raw_request_reads": 0,
}

public_sql_owners = 0
public_sql_calls = 0

for path in php_files:
    source = path.read_text(encoding="utf-8", errors="replace")
    rel = path.relative_to(ROOT).as_posix()
    sql_calls = len(SQL.findall(source))

    totals["schema_probes"] += len(SCHEMA.findall(source))
    totals["raw_get_reads"] += len(RAW_GET.findall(source))
    totals["raw_post_reads"] += len(RAW_POST.findall(source))
    totals["raw_request_reads"] += len(RAW_REQUEST.findall(source))

    is_public_controller = (
        rel.startswith("app/mobile/controllers/")
        or (rel.startswith("app/controllers/") and not rel.startswith("app/controllers/admin/"))
    )
    if is_public_controller and sql_calls:
        public_sql_owners += 1
        public_sql_calls += sql_calls

routes_source = (ROOT / "app/routing/routes.php").read_text(encoding="utf-8", errors="replace")
active_routes = len(re.findall(r"^\s*'([^']+)'\s*=>\s*\[", routes_source, flags=re.MULTILINE))

mobile_source = (ROOT / "app/mobile/mobile_routes.php").read_text(encoding="utf-8", errors="replace")
mobile_routes = len(re.findall(r"^\s*'([^']+)'\s*=>", mobile_source, flags=re.MULTILINE))

admin_controllers = len(list((ROOT / "app/controllers/admin").glob("*.php")))

legacy_source = (ROOT / "app/routing/legacy_query.php").read_text(encoding="utf-8", errors="replace")
legacy_cases = len(re.findall(r"\bcase\s+['\"]([^'\"]+)['\"]\s*:", legacy_source))

bootstrap_files = sorted(path.name for path in (ROOT / "app/bootstrap").glob("*.php"))
if bootstrap_files != ["runtime.php"]:
    errors.append(f"bootstrap surface changed: expected only runtime.php, found {bootstrap_files}")

api_php = sorted(path.relative_to(ROOT).as_posix() for path in api_root.rglob("*.php")) if api_root.exists() else []
if api_php:
    errors.append("direct public API PHP returned: " + ", ".join(api_php))

current = {
    "php_files": len(php_files),
    "active_routes": active_routes,
    "mobile_routes": mobile_routes,
    "admin_controllers": admin_controllers,
    "public_controller_sql_owners": public_sql_owners,
    "public_controller_sql_calls": public_sql_calls,
    "schema_probes": totals["schema_probes"],
    "raw_get_reads": totals["raw_get_reads"],
    "raw_post_reads": totals["raw_post_reads"],
    "raw_request_reads": totals["raw_request_reads"],
    "legacy_query_cases": legacy_cases,
}

# Snapshot-only metrics. They are documented, but adding legitimate runtime code/routes
# does not fail CI by itself.
for key in ["php_files", "active_routes"]:
    pass

# Architectural debt must not grow after Phase 7 closure.
for key in [
    "mobile_routes",
    "public_controller_sql_owners",
    "public_controller_sql_calls",
    "schema_probes",
    "raw_get_reads",
    "raw_post_reads",
    "raw_request_reads",
    "legacy_query_cases",
]:
    if current[key] > BASELINE[key]:
        errors.append(f"{key} regressed: baseline <= {BASELINE[key]}, found {current[key]}")

# Admin is a closed registry surface: additions/removals require an explicit baseline review.
if current["admin_controllers"] != BASELINE["admin_controllers"]:
    errors.append(
        f"admin controller inventory changed: baseline {BASELINE['admin_controllers']}, "
        f"found {current['admin_controllers']}"
    )

print("# Phase 7 final architecture baseline")
for key, value in current.items():
    print(f"{key}: {value}")
print("bootstrap_php: " + ", ".join(bootstrap_files))
print(f"direct_api_php: {len(api_php)}")

if errors:
    for error in errors:
        print(f"ERROR: {error}", file=sys.stderr)
    sys.exit(1)

print("Phase 7 final architecture baseline: PASS")
