#!/usr/bin/env python3
from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parents[2]

domain = (ROOT / 'app/domains/admin_usage/queries.php').read_text(encoding='utf-8', errors='replace')
controller = (ROOT / 'app/controllers/admin/admin_usage.php').read_text(encoding='utf-8', errors='replace')
main = (ROOT / 'app/controllers/admin/admin_main.php').read_text(encoding='utf-8', errors='replace')
sections = (ROOT / 'app/helpers/admin_sections.php').read_text(encoding='utf-8', errors='replace')
schema_doc = (ROOT / 'admin_docs/DATABASE_SCHEMA.md').read_text(encoding='utf-8', errors='replace')

errors = []

for marker in [
    'fact_admin_section_usage_daily',
    'ON DUPLICATE KEY UPDATE',
    'view_count = view_count + 1',
    'hg_admin_usage_record(',
    'hg_admin_usage_report(',
]:
    if marker not in domain:
        errors.append(f'Admin usage domain lost marker: {marker}')

for forbidden in [
    'REMOTE_ADDR',
    'HTTP_USER_AGENT',
    'session_id(',
    'admin_logged_in_at',
    'admin_last_seen_at',
]:
    if forbidden in domain:
        errors.append(f'Admin usage telemetry gained forbidden personal/session marker: {forbidden}')

for marker in [
    "strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'GET'",
    '!$isAjaxAdminRequest',
    "hg_admin_usage_record($link, $usageSection)",
]:
    if marker not in main:
        errors.append(f'Admin full-page usage tracker lost gate: {marker}')

if "'admin_usage' => ['target' => 'admin_usage.php'" not in sections:
    errors.append('Admin usage dashboard lost section registry entry')

for marker in [
    'Uso del Admin',
    'Solo cuenta cargas completas GET del Admin',
    'adminUsageTable',
]:
    if marker not in controller:
        errors.append(f'Admin usage dashboard lost UI marker: {marker}')

if 'fact_admin_section_usage_daily' not in schema_doc:
    errors.append('Admin usage table missing from production schema documentation')

print('# Admin usage telemetry audit')
print('Storage: daily aggregate by section')
print('Personal data: none')
print('Tracked requests: authenticated full-page GET only')

if errors:
    for error in errors:
        print(f'ERROR: {error}', file=sys.stderr)
    sys.exit(1)

print('Admin usage telemetry audit: PASS')
