<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/domains/api/audit.php';

if (($argv[1] ?? '') === 'writer') {
    for ($i = 0; $i < 25; $i++) {
        if (!hg_api_audit_write($argv[2], ['at' => gmdate('c'), 'client' => 'rest-shared', 'index' => $i], time())) exit(1);
    }
    exit;
}
if (($argv[1] ?? '') === 'request') {
    $state = hg_api_audit_begin('mcp', 'POST', [], '192.0.2.42', "curl/8 test-private-agent");
    $state->row['client'] = 'chatgpt-oauth';
    hg_api_audit_mcp($state, ['method' => 'tools/call', 'params' => ['name' => 'heavens_gate_content',
        'arguments' => ['resource' => 'characters', 'q' => 'test-private-search', 'id' => 'test-private-id',
            'Authorization' => 'test-private-credential']]]);
    hg_api_audit_response($state, '{"ok":false,"private":"test-private-response"}', true);
    http_response_code(200); // MCP application error with successful HTTP transport.
    exit;
}
function check(bool $value, string $label): void {
    if (!$value) throw new RuntimeException($label);
}
function child(array $args): void {
    $pipes = [];
    $process = proc_open(array_merge([PHP_BINARY, __FILE__], $args), [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    check(is_resource($process), 'start child');
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]); fclose($pipes[1]);
    $errors = stream_get_contents($pipes[2]); fclose($pipes[2]);
    check(proc_close($process) === 0, 'child failed: ' . $output . $errors);
}
$directory = sys_get_temp_dir() . '/hg-api-audit-test-' . bin2hex(random_bytes(8));
mkdir($directory, 0700);
putenv('HG_API_AUDIT_DIR=' . $directory);
file_put_contents($directory . '/origin.key', random_bytes(32));
chmod($directory . '/origin.key', 0600);
try {
    check(hg_api_audit_ready($directory), 'private storage ready');
    $safe = hg_api_audit_query(['resource' => "credential\n<script>", 'q' => 'private', 'id' => 123, 'token' => 'private']);
    check($safe === ['resource' => 'other', 'filters' => ['q', 'id']], 'strict query allowlist');
    $now = strtotime('2026-11-16T23:30:00Z');
    check(hg_api_audit_day('../../config.env', $now) === '2026-11-16', 'path traversal blocked');
    check(hg_api_audit_day('2026-11-31', $now) === '2026-11-16', 'invalid day rejected');
    check(hg_api_audit_day('2026-10-18', $now) === '2026-10-18', '30-day boundary included');
    check(hg_api_audit_day('2026-10-17', $now) === '2026-11-16', 'expired day rejected');
    child(['request']);
    $file = $directory . '/' . gmdate('Y-m-d') . '.jsonl';
    $raw = file_get_contents($file);
    check(!str_contains($raw, 'test-private') && !str_contains($raw, '192.0.2.42'), 'no credentials, response, search, ID, agent or raw IP');
    $row = json_decode(trim($raw), true, 512, JSON_THROW_ON_ERROR);
    check($row['outcome'] === 'error' && $row['status'] === 200, 'MCP errors separate from HTTP status');
    check($row['operation'] === 'tools/call' && $row['resource'] === 'characters', 'MCP operation observed');
    check($row['filters'] === ['q', 'id'] && $row['response_bytes'] > 0, 'filter names and bytes');
    check(preg_match('/^[a-f0-9]{16}$/D', $row['origin']) === 1, 'origin pseudonym');
    check((fileperms($file) & 0077) === 0, 'journal private');
    unlink($file);
    // Concurrent processes must never interleave JSON lines or lose records.
    $processes = [];
    for ($i = 0; $i < 4; $i++) {
        $processes[] = proc_open([PHP_BINARY, __FILE__, 'writer', $directory], [], $pipes);
    }
    foreach ($processes as $process) check(proc_close($process) === 0, 'concurrent writer');
    $lines = file($file, FILE_IGNORE_NEW_LINES);
    check(count($lines) === 100, 'all concurrent records retained');
    foreach ($lines as $line) check(is_array(json_decode($line, true, 512, JSON_THROW_ON_ERROR)), 'valid independent lines');
    unlink($file);
    for ($i = 0; $i < 73; $i++) {
        check(hg_api_audit_write($directory, ['client' => $i % 2 ? 'unauthenticated' : 'chatgpt-oauth',
            'outcome' => $i % 2 ? 'error' : 'ok', 'status' => $i % 2 ? 401 : 200,
            'response_bytes' => 10, 'duration_ms' => 2, 'index' => $i], time()), 'write report fixture');
    }
    $report = hg_api_audit_report($directory, gmdate('Y-m-d'));
    check($report['total'] === 73 && $report['errors'] === 36 && $report['bytes'] === 730, 'aggregate summary');
    check(count($report['rows']) === 50 && $report['rows'][0]['index'] === 72 && $report['pages'] === 2, 'latest page');
    $next = hg_api_audit_report($directory, gmdate('Y-m-d'), '', '', 2);
    check(count($next['rows']) === 23 && $next['rows'][0]['index'] === 22, 'older page without duplication');
    $filtered = hg_api_audit_report($directory, gmdate('Y-m-d'), 'unauthenticated', 'error');
    check($filtered['total'] === 36 && $filtered['errors'] === 36, 'client and outcome filters');
    file_put_contents($file, "broken-json\n", FILE_APPEND);
    check(hg_api_audit_report($directory, gmdate('Y-m-d'))['total'] === 73, 'malformed journal row ignored');
    $handle = fopen($file, 'c+b'); ftruncate($handle, HG_API_AUDIT_MAX_BYTES); fclose($handle);
    check(!hg_api_audit_write($directory, ['safe' => true], time()), 'daily cap enforced');
    check(filesize($file) === HG_API_AUDIT_MAX_BYTES, 'daily bound unchanged');
    unlink($file);
    $expired = $directory . '/' . gmdate('Y-m-d', time() - 30 * 86400) . '.jsonl';
    $retained = $directory . '/' . gmdate('Y-m-d', time() - 29 * 86400) . '.jsonl';
    file_put_contents($expired, '{}'); file_put_contents($retained, '{}');
    file_put_contents($directory . '/do-not-delete.txt', 'safe');
    hg_api_audit_prune($directory, time());
    check(!file_exists($expired) && file_exists($retained) && file_exists($directory . '/do-not-delete.txt'), 'precise retention boundary');
    check(!hg_api_audit_write($directory . '/missing', [], time()), 'missing storage returns failure without throwing');
    check(!hg_api_audit_report($directory . '/missing', gmdate('Y-m-d'))['ready'], 'admin shows unavailable storage');
    require_once dirname(__DIR__, 2) . '/app/helpers/admin_sections.php';
    check(hg_admin_section_resolve('admin_api_activity') !== null, 'normal admin registration');
    check(hg_admin_section_resolve('admin_api_activity', 'ajax') === null, 'no AJAX exposure');
    // The actual view must deny access before reading the journal.
    ob_start();
    include dirname(__DIR__, 2) . '/app/controllers/admin/admin_api_activity.php';
    $html = ob_get_clean();
    check(http_response_code() === 403 && $html === '', 'view denies unauthenticated access');
    echo "API audit privacy, locking, bounds, reporting, retention and admin access: PASS\n";
} finally {
    foreach (glob($directory . '/*') ?: [] as $file) unlink($file);
    rmdir($directory);
}
