#!/usr/bin/env python3
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[2]
probe_re = re.compile(r"\bSHOW\s+COLUMNS\b|\binformation_schema\b", re.I)

runtime = []
for base in (ROOT / "app", ROOT / "api"):
    if base.exists():
        runtime.extend(base.rglob("*.php"))
if (ROOT / "index.php").exists():
    runtime.append(ROOT / "index.php")

hits = []
for path in sorted(set(runtime)):
    lines = path.read_text(encoding="utf-8", errors="replace").splitlines()
    for i, line in enumerate(lines, 1):
        if probe_re.search(line):
            start = max(0, i - 3)
            end = min(len(lines), i + 2)
            ctx = " || ".join(f"{j+1}:{lines[j].strip()}" for j in range(start, end))
            hits.append((path.relative_to(ROOT).as_posix(), i, ctx))

print(f"SCHEMA_PROBE_LINES={len(hits)}")
for path, line, ctx in hits:
    print(f"PROBE|{path}|{line}|{ctx}")
