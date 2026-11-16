#!/usr/bin/env python3
"""Protect the OAuth discovery URI dispatch from accidental escaping regressions."""
from pathlib import Path
import re

htaccess = Path(".htaccess").read_text(encoding="utf-8")
rules = [
    line.split()
    for line in htaccess.splitlines()
    if line.lstrip().startswith("RewriteRule ")
]
oauth_paths = [
    ".well-known/oauth-protected-resource",
    ".well-known/oauth-authorization-server",
]
for path in oauth_paths:
    matches = [
        parts for parts in rules
        if len(parts) >= 3
        and re.match(parts[1], path)
        and parts[2] == "index.php"
        and "[L,QSA]" in parts[3:]
    ]
    assert len(matches) == 1, f"Expected exactly one OAuth discovery rewrite for {path}"
    print(f"PASS: {path} -> index.php")
