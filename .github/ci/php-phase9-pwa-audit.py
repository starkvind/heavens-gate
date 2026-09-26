#!/usr/bin/env python3
from pathlib import Path
import json
import sys

ROOT = Path(__file__).resolve().parents[2]
errors = []

def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8", errors="replace")

manifest_path = ROOT / "manifest.json"
if not manifest_path.exists():
    errors.append("canonical PWA manifest missing: manifest.json")
    manifest = {}
else:
    try:
        manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
    except json.JSONDecodeError as exc:
        errors.append(f"manifest.json is invalid JSON: {exc}")
        manifest = {}

if manifest:
    if manifest.get("id") != "/":
        errors.append("PWA manifest id must remain '/'")
    if manifest.get("scope") != "/":
        errors.append("PWA manifest scope must remain '/'")
    start_url = str(manifest.get("start_url", ""))
    if "/home" not in start_url or "view=mobile" not in start_url:
        errors.append("PWA start_url must launch the dedicated mobile presentation")
    if manifest.get("display") != "standalone":
        errors.append("PWA display must remain standalone")
    if not (manifest.get("name") or manifest.get("short_name")):
        errors.append("PWA manifest name/short_name missing")

    icon_sizes = set()
    for icon in manifest.get("icons", []):
        sizes = str(icon.get("sizes", ""))
        src = str(icon.get("src", ""))
        if sizes:
            icon_sizes.update(sizes.split())
        if src.startswith("/img/"):
            disk = ROOT / "public" / src.lstrip("/")
            if not disk.exists():
                errors.append(f"PWA manifest icon missing from public tree: {src}")
    for required in ("192x192", "512x512"):
        if required not in icon_sizes:
            errors.append(f"PWA manifest required icon size missing: {required}")

desktop_head = read("app/views/layout/head.php")
mobile_shell = read("app/mobile/mobile_index.php")
for path, source in [
    ("app/views/layout/head.php", desktop_head),
    ("app/mobile/mobile_index.php", mobile_shell),
]:
    if 'rel="manifest" href="/manifest.json"' not in source:
        errors.append(f"canonical PWA manifest link missing: {path}")
    if "assets/js/hg-pwa.js" not in source:
        errors.append(f"PWA controller asset missing: {path}")

for marker in [
    "apple-mobile-web-app-capable",
    "apple-mobile-web-app-title",
    "data-hg-pwa-section",
    "data-hg-pwa-install",
    "data-hg-pwa-ios-help",
]:
    if marker not in mobile_shell:
        errors.append(f"mobile PWA shell contract missing: {marker}")

pwa_js = read("assets/js/hg-pwa.js")
for marker in [
    "navigator.serviceWorker.register('/service-worker.js'",
    "beforeinstallprompt",
    "appinstalled",
    "display-mode: standalone",
    "data-hg-pwa-install",
]:
    if marker not in pwa_js:
        errors.append(f"PWA install controller missing behavior: {marker}")

sw = read("service-worker.js")
for marker in [
    "OFFLINE_URL = '/offline.html'",
    "request.mode === 'navigate'",
    "fetch(request)",
    "cache.match(OFFLINE_URL)",
    "'/assets/'",
    "'/img/favicon/'",
]:
    if marker not in sw:
        errors.append(f"service worker conservative cache contract missing: {marker}")

for forbidden in [
    "'/talim'",
    '"/talim"',
    "'/api/'",
    '"/api/"',
]:
    if forbidden in sw:
        errors.append(f"service worker must not cache privileged/dynamic route marker: {forbidden}")

offline = read("offline.html")
if "Sin conexión" not in offline:
    errors.append("offline fallback content missing")
if "/home?view=mobile" not in offline:
    errors.append("offline fallback must retry into mobile shell")

print("# PHP Phase 9.4 PWA contract")
print("manifest: manifest.json")
print("start_url:", manifest.get("start_url", ""))
print("service_worker: service-worker.js")
print("offline_fallback: offline.html")
print("installed_shell: mobile")

if errors:
    for error in errors:
        print("ERROR:", error, file=sys.stderr)
    raise SystemExit(1)

print("PHP Phase 9.4 PWA contract: PASS")
