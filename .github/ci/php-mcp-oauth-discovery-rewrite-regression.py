#!/usr/bin/env python3
"""Protect the literal Apache OAuth discovery rewrite from escaping regressions."""
from pathlib import Path

htaccess = Path(".htaccess").read_text(encoding="utf-8")
expected = r"RewriteRule ^\.well-known/oauth-(?:protected-resource|authorization-server)$ index.php [L,QSA]"
assert expected in htaccess.splitlines(), "OAuth discovery rewrite must escape only the dot in .well-known"
print("PASS: both OAuth discovery endpoints have the early front-controller rewrite")
