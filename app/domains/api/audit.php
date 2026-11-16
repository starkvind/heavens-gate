<?php
/** Private, bounded API activity journal. Never pass credentials or response bodies. */
const HG_API_AUDIT_DIR = '/var/lib/heavensgate/api-audit';
const HG_API_AUDIT_MAX_BYTES = 4194304; // 4 MiB/day, at most 30 UTC day files.

function hg_api_audit_directory(): string
{
    // Environment override is for isolated tests/deployments, never a request parameter.
    $override = getenv('HG_API_AUDIT_DIR');
    return is_string($override) && $override !== '' ? $override : HG_API_AUDIT_DIR;
}

function hg_api_audit_ready(string $directory): bool
{
    if (is_link($directory) || !is_dir($directory)) return false;
    $mode = @fileperms($directory);
    return is_int($mode) && ($mode & 0077) === 0 && is_readable($directory) && is_writable($directory);
}

function hg_api_audit_query(array $query): array
{
    // Values outside this fixed vocabulary are never persisted, even if malformed.
    $resources = ['catalog', 'characters', 'episodes', 'seasons', 'inventory', 'systems',
        'attributes', 'skills', 'traits', 'merits-flaws', 'powers', 'gifts', 'breeds',
        'auspices', 'tribes', 'documents', 'chronicles', 'misc-systems', 'merits', 'flaws',
        'backgrounds', 'maneuvers', 'actions', 'conditions', 'rites', 'totems', 'disciplines',
        'archetypes', 'forms', 'resources', 'forum', 'biografias', 'episodios', 'temporadas',
        'objetos', 'sistemas', 'habilidades', 'merydef', 'dones', 'razas', 'auspicios',
        'tribus', 'escritos', 'cronicas', 'misc_sistemas', 'organizaciones'];
    $resource = $query['resource'] ?? 'catalog';
    $resource = is_string($resource) ? strtolower(trim($resource)) : '';
    $filters = [];
    foreach (['q', 'id', 'topic_id', 'page', 'limit'] as $key) {
        if (array_key_exists($key, $query)) $filters[] = $key;
    }
    return ['resource' => in_array($resource, $resources, true) ? $resource : 'other',
        'filters' => $filters];
}

function hg_api_audit_agent(string $agent): string
{
    if (stripos($agent, 'chatgpt') !== false || stripos($agent, 'openai') !== false) return 'OpenAI';
    if (stripos($agent, 'curl/') !== false) return 'curl';
    if (stripos($agent, 'python') !== false) return 'Python';
    if (stripos($agent, 'mozilla/') !== false) return 'browser';
    return 'other';
}

function hg_api_audit_begin(string $transport, string $method, array $query, string $address, string $agent): object
{
    $state = (object)['started' => hrtime(true), 'bytes' => 0, 'error' => false, 'row' => [
        'at' => gmdate('Y-m-d\TH:i:s\Z'),
        'transport' => $transport === 'mcp' ? 'mcp' : 'rest',
        'endpoint' => $transport === 'mcp' ? '/mcp' : '/api/v1/content',
        'method' => in_array($method, ['GET', 'POST', 'HEAD', 'OPTIONS', 'PUT', 'PATCH', 'DELETE'], true) ? $method : 'OTHER',
        'client' => 'unchecked', 'agent' => hg_api_audit_agent($agent),
        'operation' => $transport === 'mcp' ? 'unparsed' : 'content',
    ] + hg_api_audit_query($query)];
    $directory = hg_api_audit_directory();
    // A separate local key creates a non-reversible origin label. Never store raw IPs.
    $key = hg_api_audit_ready($directory) ? @file_get_contents($directory . '/origin.key') : false;
    $state->row['origin'] = is_string($key) && strlen($key) >= 32
        ? substr(hash_hmac('sha256', $address, $key), 0, 16) : 'unavailable';
    register_shutdown_function(static function() use ($state, $directory): void {
        $last = error_get_last();
        $fatal = is_array($last) && in_array($last['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true);
        $status = http_response_code() ?: 200;
        $row = $state->row + [
            'status' => $fatal ? 500 : $status,
            'outcome' => $fatal || $state->error || $status >= 400 ? 'error' : 'ok',
            'duration_ms' => round((hrtime(true) - $state->started) / 1000000, 2),
            'response_bytes' => $state->bytes,
        ];
        try {
            if (!hg_api_audit_write($directory, $row, time())) error_log('HG API audit: journal unavailable or daily limit reached');
        } catch (Throwable $ignored) {
            // Audit failure must not corrupt JSON or change authentication/API behavior.
            error_log('HG API audit: journal write failed');
        }
    });
    return $state;
}

function hg_api_audit_mcp(object $state, array $message): void
{
    $method = $message['method'] ?? '';
    $state->row['operation'] = in_array($method, ['initialize', 'ping', 'tools/list', 'tools/call', 'notifications/initialized'], true)
        ? $method : 'other';
    if ($method === 'tools/call') {
        $params = is_array($message['params'] ?? null) ? $message['params'] : [];
        $state->row['tool'] = ($params['name'] ?? null) === 'heavens_gate_content' ? 'heavens_gate_content' : 'other';
        $args = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];
        $state->row = array_replace($state->row, hg_api_audit_query($args));
    }
}

function hg_api_audit_response(object $state, string $encoded, bool $error): void
{
    $state->bytes += strlen($encoded);
    $state->error = $state->error || $error;
}

function hg_api_audit_prune(string $directory, int $now): void
{
    $cutoff = gmdate('Y-m-d', $now - 29 * 86400);
    foreach (glob($directory . '/????-??-??.jsonl') ?: [] as $file) {
        if (preg_match('/^\d{4}-\d{2}-\d{2}\.jsonl$/D', basename($file))
            && substr(basename($file), 0, 10) < $cutoff && !is_link($file)) @unlink($file);
    }
}

function hg_api_audit_write(string $directory, array $row, int $now): bool
{
    if (!hg_api_audit_ready($directory)) return false;
    // Both writer and scheduled retention job only handle fixed-format day files.
    hg_api_audit_prune($directory, $now);
    $file = $directory . '/' . gmdate('Y-m-d', $now) . '.jsonl';
    if (is_link($file)) return false;
    $handle = @fopen($file, 'c+b');
    if ($handle === false) return false;
    try {
        if (!flock($handle, LOCK_EX)) return false;
        @chmod($file, 0600);
        $stat = fstat($handle);
        $encoded = json_encode($row, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if (!is_string($encoded) || strlen($encoded) > 4094) return false;
        $line = $encoded . "\n";
        if (($stat['size'] ?? HG_API_AUDIT_MAX_BYTES) + strlen($line) > HG_API_AUDIT_MAX_BYTES) return false;
        fseek($handle, 0, SEEK_END);
        return fwrite($handle, $line) === strlen($line);
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function hg_api_audit_day(mixed $input, int $now): string
{
    $today = gmdate('Y-m-d', $now);
    if (!is_string($input) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $input)) return $today;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $input, new DateTimeZone('UTC'));
    return $date && $date->format('Y-m-d') === $input
        && $input >= gmdate('Y-m-d', $now - 29 * 86400) && $input <= $today ? $input : $today;
}

function hg_api_audit_report(string $directory, string $day, string $client = '', string $outcome = '', int $page = 1): array
{
    $report = ['ready' => hg_api_audit_ready($directory), 'rows' => [], 'clients' => [],
        'total' => 0, 'errors' => 0, 'bytes' => 0, 'duration_ms' => 0, 'capped' => false, 'page' => 1, 'pages' => 1];
    if (!$report['ready'] || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $day)) return $report;
    $file = $directory . '/' . $day . '.jsonl';
    if (is_link($file)) return $report;
    $handle = @fopen($file, 'rb');
    if ($handle === false) return $report; // Empty day, not an installation failure.
    try {
        if (!flock($handle, LOCK_SH)) return $report;
        $report['capped'] = (fstat($handle)['size'] ?? 0) > HG_API_AUDIT_MAX_BYTES - 4096;
        $matches = static function(array $row) use ($client, $outcome): bool {
            return ($client === '' || ($row['client'] ?? '') === $client)
                && ($outcome === '' || ($row['outcome'] ?? '') === $outcome);
        };
        while (($line = fgets($handle, 4096)) !== false) {
            $row = json_decode($line, true);
            if (!is_array($row) || !$matches($row)) continue;
            $report['total']++;
            $report['errors'] += ($row['outcome'] ?? '') === 'error' ? 1 : 0;
            $report['bytes'] += (int)($row['response_bytes'] ?? 0);
            $report['duration_ms'] += (float)($row['duration_ms'] ?? 0);
            $name = (string)($row['client'] ?? 'unchecked');
            $report['clients'][$name] = ($report['clients'][$name] ?? 0) + 1;
        }
        $report['pages'] = max(1, (int)ceil($report['total'] / 50));
        $report['page'] = max(1, min($report['pages'], $page));
        $last = $report['total'] - ($report['page'] - 1) * 50;
        $first = max(0, $last - 50);
        $index = 0;
        rewind($handle);
        while (($line = fgets($handle, 4096)) !== false) {
            $row = json_decode($line, true);
            if (!is_array($row) || !$matches($row)) continue;
            if ($index >= $first && $index < $last) $report['rows'][] = $row;
            $index++;
        }
        $report['rows'] = array_reverse($report['rows']);
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
    return $report;
}
