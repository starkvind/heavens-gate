<?php
// Scheduled CLI-only cleanup; no HTTP maintenance endpoint.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../app/domains/api/audit.php';
$directory = hg_api_audit_directory();
if (!hg_api_audit_ready($directory)) {
    fwrite(STDERR, "API audit directory unavailable\n");
    exit(1);
}
hg_api_audit_prune($directory, time());
echo "API audit retention complete (30 UTC days)\n";
