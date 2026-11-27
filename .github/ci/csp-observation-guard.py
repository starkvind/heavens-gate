from pathlib import Path
import re

root = Path(__file__).resolve().parents[2]
sample = (root / "admin_docs/security/csp-report-only.conf.example").read_text()
line = re.search(r'Header always set Content-Security-Policy-Report-Only "([^"]+)"', sample)
assert line, "Missing observation policy"
directives = dict((p.split()[0], p.split()[1:]) for p in line.group(1).split(";") if p.strip())
assert directives["script-src"] == ["'self'"]
assert directives["script-src-attr"] == ["'none'"]
assert directives["object-src"] == ["'none'"]
assert "report-uri" not in directives and "report-to" not in directives
assert "Content-Security-Policy-Report-Only" not in (root / ".htaccess").read_text()
assert "frame-ancestors 'none'" in (root / "app/helpers/admin_auth.php").read_text()
print("CSP observation guard: OK")
