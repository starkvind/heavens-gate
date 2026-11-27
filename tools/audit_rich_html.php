<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../app/helpers/html_sanitizer.php';

// Fixed, reviewed field list. Never interpolate identifiers from CLI input.
$fields = [
    'fact_docs' => ['content'],
    'fact_admin_posts' => ['message'],
    'dim_chapters' => ['synopsis'],
    'dim_seasons' => ['description', 'opening', 'main_cast'],
    'fact_items' => ['description'],
    'fact_gifts' => ['description', 'mechanics_text', 'system_name'],
    'fact_rites' => ['description', 'system_text'],
    'dim_totems' => ['description', 'traits', 'prohibited'],
    'fact_discipline_powers' => ['description', 'system_name'],
    'dim_systems' => ['description'],
    'dim_forms' => ['description'],
    'dim_breeds' => ['description'],
    'dim_auspices' => ['description'],
    'dim_tribes' => ['description', 'powers'],
    'fact_misc_systems' => ['description', 'extra_info'],
    'fact_characters' => ['info_text'],
    'dim_traits' => ['description', 'levels', 'posse', 'special'],
    'dim_merits_flaws' => ['description'],
    'dim_systems_resources' => ['description'],
];

function hg_audit_html_metrics(string $html): array
{
    // Diagnostics only; never used as an HTML security filter.
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
    preg_match_all('/<(table|tr|td|th|a|img|ul|ol|li|pre|code|h[1-6])\b/i', $html, $tags);
    $counts = array_count_values(array_map('strtolower', $tags[1]));
    ksort($counts);
    preg_match_all('/\\b(?:rowspan|colspan)\\s*=\\s*["\']?([1-9][0-9]*)/i', $html, $spans);
    preg_match_all('/\\bclass\\s*=\\s*["\'][^"\']*\\bhg-mention\\b[^"\']*["\']/i', $html, $mentions);
    return ['text' => trim($text), 'structures' => $counts,
        'tables' => (int)($counts['table'] ?? 0),
        'merged_cells' => count(array_filter($spans[1], static fn($value) => (int)$value > 1)),
        'mentions' => count($mentions[0])];
}

function hg_audit_html_compare(string $html): array
{
    $clean = hg_sanitize_rich_html($html);
    $before = hg_audit_html_metrics($html);
    $after = hg_audit_html_metrics($clean);
    return [
        'changed' => $html !== $clean,
        'text_changed' => $before['text'] !== $after['text'],
        'structure_changed' => $before['structures'] !== $after['structures']
            || $before['merged_cells'] !== $after['merged_cells']
            || $before['mentions'] !== $after['mentions'],
        'tables_before' => $before['tables'],
        'tables_after' => $after['tables'],
        'merged_cells_before' => $before['merged_cells'],
        'merged_cells_after' => $after['merged_cells'],
        'mentions_before' => $before['mentions'],
        'mentions_after' => $after['mentions'],
        'before_bytes' => strlen($html),
        'after_bytes' => strlen($clean),
    ];
}

function hg_audit_html_emit(array $record): void
{
    echo json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
}

if ($argc === 2 && $argv[1] === '--self-test') {
    $safe = hg_audit_html_compare('<p>Texto</p>');
    $unsafe = hg_audit_html_compare('<p onclick="void(0)">Texto</p>');
    if ($safe['text_changed'] || !$unsafe['changed'] || $unsafe['text_changed']) {
        fwrite(STDERR, "HTML audit self-test failed\n");
        exit(1);
    }
    $table = hg_audit_html_compare('<table><tbody><tr><td colspan="2">A</td></tr></tbody></table>');
    if ($table['tables_before'] !== 1 || $table['tables_after'] !== 1
        || $table['merged_cells_before'] !== 1 || $table['merged_cells_after'] !== 1) {
        fwrite(STDERR, "Table inventory self-test failed\n");
        exit(1);
    }
    echo "HTML audit self-test OK\n";
    exit;
}

$options = getopt('', ['config:', 'only:', 'batch:']);
$configPath = $options['config'] ?? null;
$only = $options['only'] ?? null;
$batch = filter_var($options['batch'] ?? 10, FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1, 'max_range' => 25]]);
if (!is_string($configPath) || $configPath === '' || !is_file($configPath)
    || $batch === false || ($only !== null && !is_string($only))) {
    fwrite(STDERR, "Usage: php tools/audit_rich_html.php --config=/private/copy-readonly.env [--only=fact_docs.content] [--batch=10]\n");
    exit(2);
}
$selected = [];
foreach ($fields as $table => $columns) {
    foreach ($columns as $column) {
        if ($only === null || $only === $table . '.' . $column) {
            $selected[] = [$table, $column];
        }
    }
}
if (!$selected) {
    fwrite(STDERR, "Unknown field selection\n");
    exit(2);
}
$config = parse_ini_file($configPath, false, INI_SCANNER_RAW);
if (!is_array($config)) {
    fwrite(STDERR, "Invalid database configuration\n");
    exit(2);
}
foreach (['MYSQL_HOST', 'MYSQL_USER', 'MYSQL_PWD', 'MYSQL_BDD'] as $key) {
    if (!isset($config[$key]) || !is_string($config[$key]) || $config[$key] === '') {
        fwrite(STDERR, "Missing database configuration key: " . $key . "\n");
        exit(2);
    }
}
$db = null;
try {
    // No application config fallback; use a copy and a SELECT-only account.
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db = new mysqli($config['MYSQL_HOST'], $config['MYSQL_USER'], $config['MYSQL_PWD'], $config['MYSQL_BDD']);
    unset($config);
    $db->set_charset('utf8mb4');
    $db->query('START TRANSACTION READ ONLY');
    // Verify sanitizer and installation before scanning any fields.
    hg_sanitize_rich_html('<p>preflight</p>');
    $meta = $db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $max = 2 * 1024 * 1024;
    $skipped = 0;
    $errors = 0;
    foreach ($selected as [$table, $column]) {
        $meta->bind_param('ss', $table, $column);
        $meta->execute();
        $result = $meta->get_result();
        $exists = (int)($result->fetch_row()[0] ?? 0) === 1;
        $result->free();
        if (!$exists) {
            hg_audit_html_emit(['field' => "$table.$column", 'status' => 'missing_column']);
            $skipped++;
            continue;
        }
        $stats = ['scanned'=>0, 'changed'=>0, 'text_changed'=>0, 'structure_changed'=>0,
            'oversized'=>0, 'errors'=>0, 'max_bytes'=>0, 'elapsed_ms'=>0,
            'rows_with_tables'=>0, 'tables_before'=>0, 'tables_after'=>0,
            'merged_cells_before'=>0, 'merged_cells_after'=>0,
            'mentions_before'=>0, 'mentions_after'=>0];
        $start = hrtime(true);
        $last = 0;
        $sql = "SELECT id, OCTET_LENGTH(`$column`) AS bytes, "
            . "CASE WHEN OCTET_LENGTH(`$column`) <= $max THEN `$column` ELSE NULL END AS html "
            . "FROM `$table` WHERE id > ? AND `$column` IS NOT NULL ORDER BY id LIMIT ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('ii', $last, $batch);
        do {
            $stmt->execute();
            $result = $stmt->get_result();
            $count = 0;
            while ($row = $result->fetch_assoc()) {
                $count++;
                $last = (int)$row['id'];
                $bytes = (int)$row['bytes'];
                $stats['scanned']++;
                $stats['max_bytes'] = max($stats['max_bytes'], $bytes);
                if ($bytes > $max) {
                    $stats['oversized']++;
                    $errors++;
                    hg_audit_html_emit(['field'=>"$table.$column", 'id'=>$last, 'status'=>'oversized', 'bytes'=>$bytes]);
                    continue;
                }
                try {
                    $comparison = hg_audit_html_compare((string)$row['html']);
                    foreach (['changed', 'text_changed', 'structure_changed'] as $key) {
                        $stats[$key] += (int)$comparison[$key];
                    }
                    $stats['rows_with_tables'] += (int)($comparison['tables_before'] > 0);
                    foreach (['tables_before','tables_after','merged_cells_before','merged_cells_after',
                        'mentions_before','mentions_after'] as $key) {
                        $stats[$key] += $comparison[$key];
                    }
                    if ($comparison['changed'] || $comparison['tables_before'] > 0) {
                        hg_audit_html_emit(['field'=>"$table.$column", 'id'=>$last] + $comparison);
                    }
                } catch (Throwable $e) {
                    $stats['errors']++;
                    $errors++;
                    hg_audit_html_emit(['field'=>"$table.$column", 'id'=>$last, 'status'=>'error', 'error_type'=>get_class($e)]);
                }
            }
            $result->free();
        } while ($count === $batch);
        $stmt->close();
        $stats['elapsed_ms'] = round((hrtime(true) - $start) / 1e6, 2);
        hg_audit_html_emit(['field'=>"$table.$column", 'status'=>'summary'] + $stats);
    }
    $meta->close();
    $db->rollback();
    $db->close();
    hg_audit_html_emit(['status'=>'complete', 'skipped_fields'=>$skipped, 'errors'=>$errors,
        'peak_memory_bytes'=>memory_get_peak_usage(true)]);
    exit($errors > 0 ? 3 : ($skipped > 0 ? 4 : 0));
} catch (Throwable $e) {
    if ($db instanceof mysqli) {
        try { $db->rollback(); $db->close(); } catch (Throwable $ignored) {}
    }
    fwrite(STDERR, 'Audit failed: ' . get_class($e) . PHP_EOL);
    exit(1);
}
