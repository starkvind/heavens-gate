#!/usr/bin/env python3
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[2]

sql = re.compile(r"\bmysqli_(?:query|prepare|real_query|multi_query)\b|->\s*(?:query|prepare)\s*\(")
errors = []
owners = []

for path in sorted((ROOT / "app/controllers").rglob("*.php")):
    rel = path.relative_to(ROOT).as_posix()
    if rel.startswith("app/controllers/admin/"):
        continue
    source = path.read_text(encoding="utf-8", errors="replace")
    count = len(sql.findall(source))
    if count:
        owners.append((rel, count))
        errors.append(f"public controller owns direct SQL: {rel}: {count}")

for path in sorted((ROOT / "app/mobile/controllers").glob("*.php")):
    rel = path.relative_to(ROOT).as_posix()
    source = path.read_text(encoding="utf-8", errors="replace")
    count = len(sql.findall(source))
    if count:
        owners.append((rel, count))
        errors.append(f"mobile controller owns direct SQL: {rel}: {count}")

print("# PHP Phase 10.1 public SQL boundary")
print("public_controller_sql_owners:", len(owners))
print("public_controller_sql_calls:", sum(count for _, count in owners))

if errors:
    for error in errors:
        print("ERROR:", error, file=sys.stderr)
    raise SystemExit(1)

print("PHP Phase 10.1 public SQL boundary: PASS")
