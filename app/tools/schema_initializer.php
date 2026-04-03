<?php
declare(strict_types=1);

function hg_schema_definition(): array
{
    static $definition = null;
    if ($definition !== null) {
        return $definition;
    }

    $definitionPath = __DIR__ . '/schema_definition.php';
    $loaded = is_file($definitionPath) ? require $definitionPath : null;
    if (!is_array($loaded)) {
        throw new RuntimeException('Schema definition file is missing or invalid.');
    }

    $loaded['tables'] = is_array($loaded['tables'] ?? null) ? array_values($loaded['tables']) : [];
    $loaded['views'] = is_array($loaded['views'] ?? null) ? array_values($loaded['views']) : [];
    $loaded['safe_web_configuration'] = is_array($loaded['safe_web_configuration'] ?? null)
        ? $loaded['safe_web_configuration']
        : [];

    $definition = $loaded;
    return $definition;
}

function hg_schema_table_exists(mysqli $link, string $table): bool
{
    $sql = 'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?';
    $st = $link->prepare($sql);
    if (!$st) {
        return false;
    }
    $st->bind_param('s', $table);
    $st->execute();
    $st->bind_result($count);
    $st->fetch();
    $st->close();
    return ((int)$count > 0);
}

function hg_schema_view_exists(mysqli $link, string $view): bool
{
    $sql = 'SELECT COUNT(*) FROM information_schema.VIEWS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?';
    $st = $link->prepare($sql);
    if (!$st) {
        return false;
    }
    $st->bind_param('s', $view);
    $st->execute();
    $st->bind_result($count);
    $st->fetch();
    $st->close();
    return ((int)$count > 0);
}

function hg_schema_existing_columns(mysqli $link, string $table): array
{
    $sql = 'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?';
    $st = $link->prepare($sql);
    if (!$st) {
        return [];
    }
    $st->bind_param('s', $table);
    $st->execute();
    $rs = $st->get_result();
    $columns = [];
    while ($rs && ($row = $rs->fetch_assoc())) {
        $columns[(string)$row['COLUMN_NAME']] = true;
    }
    $st->close();
    return $columns;
}

function hg_schema_existing_indexes(mysqli $link, string $table): array
{
    $sql = "
        SELECT INDEX_NAME, NON_UNIQUE, COLUMN_NAME, SUB_PART, SEQ_IN_INDEX, INDEX_TYPE
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
        ORDER BY INDEX_NAME, SEQ_IN_INDEX
    ";
    $st = $link->prepare($sql);
    if (!$st) {
        return ['by_name' => [], 'by_signature' => []];
    }
    $st->bind_param('s', $table);
    $st->execute();
    $rs = $st->get_result();
    $raw = [];
    while ($rs && ($row = $rs->fetch_assoc())) {
        $indexName = (string)$row['INDEX_NAME'];
        if (!isset($raw[$indexName])) {
            $raw[$indexName] = [
                'name' => $indexName,
                'non_unique' => (int)$row['NON_UNIQUE'],
                'index_type' => strtoupper((string)$row['INDEX_TYPE']),
                'columns' => [],
            ];
        }
        $column = (string)$row['COLUMN_NAME'];
        $subPart = isset($row['SUB_PART']) ? (int)$row['SUB_PART'] : 0;
        $raw[$indexName]['columns'][] = $subPart > 0 ? ($column . '(' . $subPart . ')') : $column;
    }
    $st->close();

    $byName = [];
    $bySignature = [];
    foreach ($raw as $index) {
        $signature = hg_schema_index_signature(
            $index['name'] === 'PRIMARY' ? 'PRIMARY KEY' : (
                $index['index_type'] === 'FULLTEXT' ? 'FULLTEXT KEY' : (
                    $index['index_type'] === 'SPATIAL' ? 'SPATIAL KEY' : (
                        ((int)$index['non_unique'] === 0 ? 'UNIQUE KEY' : 'KEY')
                    )
                )
            ),
            (array)$index['columns']
        );
        $byName[$index['name']] = true;
        $bySignature[$signature] = true;
    }

    return ['by_name' => $byName, 'by_signature' => $bySignature];
}

function hg_schema_existing_foreign_keys(mysqli $link, string $table): array
{
    $sql = "
        SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME, ORDINAL_POSITION
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND REFERENCED_TABLE_NAME IS NOT NULL
        ORDER BY CONSTRAINT_NAME, ORDINAL_POSITION
    ";
    $st = $link->prepare($sql);
    if (!$st) {
        return ['by_name' => [], 'by_signature' => []];
    }
    $st->bind_param('s', $table);
    $st->execute();
    $rs = $st->get_result();
    $raw = [];
    while ($rs && ($row = $rs->fetch_assoc())) {
        $constraintName = (string)$row['CONSTRAINT_NAME'];
        if (!isset($raw[$constraintName])) {
            $raw[$constraintName] = [
                'name' => $constraintName,
                'columns' => [],
                'ref_table' => (string)$row['REFERENCED_TABLE_NAME'],
                'ref_columns' => [],
            ];
        }
        $raw[$constraintName]['columns'][] = (string)$row['COLUMN_NAME'];
        $raw[$constraintName]['ref_columns'][] = (string)$row['REFERENCED_COLUMN_NAME'];
    }
    $st->close();

    $byName = [];
    $bySignature = [];
    foreach ($raw as $fk) {
        $byName[$fk['name']] = true;
        $bySignature[hg_schema_fk_signature((array)$fk['columns'], (string)$fk['ref_table'], (array)$fk['ref_columns'])] = true;
    }

    return ['by_name' => $byName, 'by_signature' => $bySignature];
}

function hg_schema_index_signature(string $kind, array $columns): string
{
    $normalized = [];
    foreach ($columns as $column) {
        $piece = trim((string)$column);
        $piece = preg_replace('/`/', '', $piece) ?? $piece;
        $piece = preg_replace('/\s+(ASC|DESC)\b/i', '', $piece) ?? $piece;
        $piece = preg_replace('/\s+/', '', $piece) ?? $piece;
        $normalized[] = strtolower($piece);
    }
    return strtoupper(trim($kind)) . ':' . implode(',', $normalized);
}

function hg_schema_fk_signature(array $columns, string $refTable, array $refColumns): string
{
    $normalize = static function (array $items): array {
        $out = [];
        foreach ($items as $item) {
            $piece = strtolower(trim((string)$item));
            $piece = preg_replace('/`/', '', $piece) ?? $piece;
            $piece = preg_replace('/\s+/', '', $piece) ?? $piece;
            $out[] = $piece;
        }
        return $out;
    };

    return implode(',', $normalize($columns)) . '->' . strtolower(trim($refTable)) . ':' . implode(',', $normalize($refColumns));
}

function hg_schema_extract_table_parts(string $createSql): array
{
    if (!preg_match('/^CREATE TABLE `([^`]+)` \((.*)\)([^()]*)$/su', trim(rtrim($createSql, ';')), $match)) {
        return ['columns' => [], 'indexes' => [], 'foreign_keys' => []];
    }

    $body = (string)$match[2];
    $parts = hg_schema_split_sql_definitions($body);
    $columns = [];
    $indexes = [];
    $foreignKeys = [];

    foreach ($parts as $part) {
        $fragment = trim($part);
        if ($fragment === '') {
            continue;
        }

        if ($fragment[0] === '`' && preg_match('/^`([^`]+)`\s+/u', $fragment, $columnMatch)) {
            $columns[] = ['name' => (string)$columnMatch[1], 'sql' => $fragment];
            continue;
        }

        if (stripos($fragment, 'PRIMARY KEY') === 0) {
            $openPos = strpos($fragment, '(');
            if ($openPos !== false) {
                [$columnSql] = hg_schema_extract_balanced_segment($fragment, $openPos);
                $indexes[] = [
                    'name' => 'PRIMARY',
                    'kind' => 'PRIMARY KEY',
                    'columns' => hg_schema_parse_index_columns($columnSql),
                    'sql' => $fragment,
                ];
            }
            continue;
        }

        if (preg_match('/^(UNIQUE KEY|FULLTEXT KEY|SPATIAL KEY|KEY)\s+`([^`]+)`/iu', $fragment, $indexMatch, PREG_OFFSET_CAPTURE)) {
            $kind = (string)$indexMatch[1][0];
            $name = (string)$indexMatch[2][0];
            $offset = (int)$indexMatch[2][1] + strlen($name) + 2;
            $openPos = strpos($fragment, '(', $offset);
            if ($openPos !== false) {
                [$columnSql] = hg_schema_extract_balanced_segment($fragment, $openPos);
                $indexes[] = [
                    'name' => $name,
                    'kind' => strtoupper($kind),
                    'columns' => hg_schema_parse_index_columns($columnSql),
                    'sql' => $fragment,
                ];
            }
            continue;
        }

        if (stripos($fragment, 'CONSTRAINT ') === 0 && stripos($fragment, ' FOREIGN KEY ') !== false) {
            preg_match('/^CONSTRAINT\s+`([^`]+)`/u', $fragment, $nameMatch);
            preg_match('/REFERENCES\s+`([^`]+)`/u', $fragment, $refTableMatch, PREG_OFFSET_CAPTURE);
            $constraintName = (string)($nameMatch[1] ?? '');
            $refTable = (string)($refTableMatch[1][0] ?? '');

            $fkPos = stripos($fragment, 'FOREIGN KEY');
            $firstOpen = $fkPos !== false ? strpos($fragment, '(', $fkPos) : false;
            $secondOpen = !empty($refTableMatch[0][1]) ? strpos($fragment, '(', (int)$refTableMatch[0][1]) : false;

            if ($constraintName !== '' && $refTable !== '' && $firstOpen !== false && $secondOpen !== false) {
                [$sourceSql] = hg_schema_extract_balanced_segment($fragment, $firstOpen);
                [$targetSql] = hg_schema_extract_balanced_segment($fragment, $secondOpen);
                $foreignKeys[] = [
                    'name' => $constraintName,
                    'columns' => hg_schema_parse_index_columns($sourceSql),
                    'ref_table' => $refTable,
                    'ref_columns' => hg_schema_parse_index_columns($targetSql),
                    'sql' => $fragment,
                ];
            }
        }
    }

    return ['columns' => $columns, 'indexes' => $indexes, 'foreign_keys' => $foreignKeys];
}

function hg_schema_split_sql_definitions(string $sql): array
{
    $parts = [];
    $buffer = '';
    $depth = 0;
    $length = strlen($sql);
    $quote = '';

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $buffer .= $char;

        if ($quote !== '') {
            if ($char === $quote && ($i === 0 || $sql[$i - 1] !== '\\')) {
                $quote = '';
            }
            continue;
        }

        if ($char === '\'' || $char === '"' || $char === '`') {
            $quote = $char;
            continue;
        }

        if ($char === '(') {
            $depth++;
            continue;
        }

        if ($char === ')') {
            $depth = max(0, $depth - 1);
            continue;
        }

        if ($char === ',' && $depth === 0) {
            $parts[] = trim(substr($buffer, 0, -1));
            $buffer = '';
        }
    }

    $tail = trim($buffer);
    if ($tail !== '') {
        $parts[] = $tail;
    }

    return $parts;
}

function hg_schema_extract_balanced_segment(string $sql, int $openPos): array
{
    $depth = 0;
    $length = strlen($sql);
    $quote = '';
    for ($i = $openPos; $i < $length; $i++) {
        $char = $sql[$i];
        if ($quote !== '') {
            if ($char === $quote && ($i === 0 || $sql[$i - 1] !== '\\')) {
                $quote = '';
            }
            continue;
        }
        if ($char === '\'' || $char === '"' || $char === '`') {
            $quote = $char;
            continue;
        }
        if ($char === '(') {
            $depth++;
            continue;
        }
        if ($char === ')') {
            $depth--;
            if ($depth === 0) {
                return [substr($sql, $openPos + 1, $i - $openPos - 1), $i];
            }
        }
    }

    return ['', $openPos];
}

function hg_schema_parse_index_columns(string $columnSql): array
{
    $parts = hg_schema_split_sql_definitions($columnSql);
    $columns = [];
    foreach ($parts as $part) {
        $columns[] = trim($part);
    }
    return $columns;
}

function hg_schema_analyze(mysqli $link): array
{
    $definition = hg_schema_definition();
    $plan = [];
    $stats = [
        'tables_missing' => 0,
        'columns_missing' => 0,
        'indexes_missing' => 0,
        'foreign_keys_missing' => 0,
        'views_missing' => 0,
        'config_missing' => 0,
    ];

    foreach ($definition['tables'] as $tableDef) {
        $table = (string)($tableDef['name'] ?? '');
        $createSql = trim((string)($tableDef['create_sql'] ?? ''));
        if ($table === '' || $createSql === '') {
            continue;
        }

        if (!hg_schema_table_exists($link, $table)) {
            $plan[] = ['phase' => 'create_tables', 'type' => 'table', 'table' => $table, 'target' => $table, 'sql' => $createSql];
            $stats['tables_missing']++;
            continue;
        }

        $parts = hg_schema_extract_table_parts($createSql);
        $existingColumns = hg_schema_existing_columns($link, $table);
        foreach ($parts['columns'] as $columnDef) {
            $column = (string)$columnDef['name'];
            if (!isset($existingColumns[$column])) {
                $plan[] = [
                    'phase' => 'add_columns',
                    'type' => 'column',
                    'table' => $table,
                    'target' => $column,
                    'sql' => 'ALTER TABLE `' . $table . '` ADD COLUMN ' . $columnDef['sql'] . ';',
                ];
                $stats['columns_missing']++;
            }
        }

        $existingIndexes = hg_schema_existing_indexes($link, $table);
        foreach ($parts['indexes'] as $indexDef) {
            $name = (string)$indexDef['name'];
            $signature = hg_schema_index_signature((string)$indexDef['kind'], (array)$indexDef['columns']);
            if (!isset($existingIndexes['by_name'][$name]) && !isset($existingIndexes['by_signature'][$signature])) {
                $plan[] = [
                    'phase' => 'add_indexes',
                    'type' => 'index',
                    'table' => $table,
                    'target' => $name,
                    'sql' => 'ALTER TABLE `' . $table . '` ADD ' . $indexDef['sql'] . ';',
                ];
                $stats['indexes_missing']++;
            }
        }

        $existingForeignKeys = hg_schema_existing_foreign_keys($link, $table);
        foreach ($parts['foreign_keys'] as $fkDef) {
            $name = (string)$fkDef['name'];
            $signature = hg_schema_fk_signature((array)$fkDef['columns'], (string)$fkDef['ref_table'], (array)$fkDef['ref_columns']);
            if (!isset($existingForeignKeys['by_name'][$name]) && !isset($existingForeignKeys['by_signature'][$signature])) {
                $plan[] = [
                    'phase' => 'add_foreign_keys',
                    'type' => 'foreign_key',
                    'table' => $table,
                    'target' => $name,
                    'sql' => 'ALTER TABLE `' . $table . '` ADD ' . $fkDef['sql'] . ';',
                ];
                $stats['foreign_keys_missing']++;
            }
        }
    }

    foreach ($definition['views'] as $viewDef) {
        $view = (string)($viewDef['name'] ?? '');
        $createSql = trim((string)($viewDef['create_sql'] ?? ''));
        if ($view === '' || $createSql === '') {
            continue;
        }
        if (!hg_schema_view_exists($link, $view)) {
            $plan[] = ['phase' => 'views', 'type' => 'view', 'table' => '', 'target' => $view, 'sql' => $createSql];
            $stats['views_missing']++;
        }
    }

    if (hg_schema_table_exists($link, 'dim_web_configuration')) {
        foreach ($definition['safe_web_configuration'] as $configName => $configValue) {
            $sql = 'SELECT COUNT(*) FROM dim_web_configuration WHERE config_name = ?';
            $st = $link->prepare($sql);
            if (!$st) {
                continue;
            }
            $st->bind_param('s', $configName);
            $st->execute();
            $st->bind_result($count);
            $st->fetch();
            $st->close();
            if ((int)$count <= 0) {
                $plan[] = [
                    'phase' => 'seed_config',
                    'type' => 'config',
                    'table' => 'dim_web_configuration',
                    'target' => (string)$configName,
                    'config_value' => (string)$configValue,
                ];
                $stats['config_missing']++;
            }
        }
    }

    return [
        'definition' => $definition,
        'plan' => $plan,
        'stats' => $stats,
        'total_pending' => count($plan),
    ];
}

function hg_schema_apply(mysqli $link, array $analysis): array
{
    $plan = array_values((array)($analysis['plan'] ?? []));
    $executed = [];
    $errors = [];
    if (empty($plan)) {
        return ['executed' => [], 'errors' => []];
    }

    $runStatement = static function (mysqli $db, string $sql, array &$executed, array &$errors, array $entry): bool {
        if ($db->query($sql)) {
            $executed[] = $entry;
            return true;
        }
        $errors[] = [
            'entry' => $entry,
            'error' => $db->error,
            'sql' => $sql,
        ];
        return false;
    };

    $phases = ['create_tables', 'add_columns', 'add_indexes'];
    $link->query('SET FOREIGN_KEY_CHECKS=0');
    foreach ($phases as $phase) {
        foreach ($plan as $entry) {
            if (($entry['phase'] ?? '') !== $phase || empty($entry['sql'])) {
                continue;
            }
            if (!$runStatement($link, (string)$entry['sql'], $executed, $errors, $entry)) {
                $link->query('SET FOREIGN_KEY_CHECKS=1');
                return ['executed' => $executed, 'errors' => $errors];
            }
        }
    }
    $link->query('SET FOREIGN_KEY_CHECKS=1');

    foreach (['add_foreign_keys', 'views'] as $phase) {
        foreach ($plan as $entry) {
            if (($entry['phase'] ?? '') !== $phase || empty($entry['sql'])) {
                continue;
            }
            if (!$runStatement($link, (string)$entry['sql'], $executed, $errors, $entry)) {
                return ['executed' => $executed, 'errors' => $errors];
            }
        }
    }

    foreach ($plan as $entry) {
        if (($entry['phase'] ?? '') !== 'seed_config') {
            continue;
        }
        $configName = (string)($entry['target'] ?? '');
        $configValue = (string)($entry['config_value'] ?? '');
        $st = $link->prepare('INSERT INTO dim_web_configuration (config_name, config_value) VALUES (?, ?)');
        if (!$st) {
            $errors[] = ['entry' => $entry, 'error' => $link->error, 'sql' => 'INSERT INTO dim_web_configuration ...'];
            return ['executed' => $executed, 'errors' => $errors];
        }
        $st->bind_param('ss', $configName, $configValue);
        if ($st->execute()) {
            $executed[] = $entry;
            $st->close();
            continue;
        }
        $errors[] = ['entry' => $entry, 'error' => $st->error, 'sql' => 'INSERT INTO dim_web_configuration ...'];
        $st->close();
        return ['executed' => $executed, 'errors' => $errors];
    }

    return ['executed' => $executed, 'errors' => $errors];
}

function hg_schema_cli_main(mysqli $link, array $argv): int
{
    $apply = in_array('--apply', $argv, true);
    $analysis = hg_schema_analyze($link);

    echo "HG schema initializer\n";
    echo "Definition: " . (string)($analysis['definition']['generated_from'] ?? 'embedded') . "\n";
    echo "Pending actions: " . (int)$analysis['total_pending'] . "\n";
    foreach ((array)$analysis['stats'] as $key => $value) {
        echo ' - ' . $key . ': ' . (int)$value . "\n";
    }

    if (!$apply) {
        echo "\nDry run only. Re-run with --apply to execute pending actions.\n";
        return 0;
    }

    $result = hg_schema_apply($link, $analysis);
    echo "\nExecuted: " . count((array)$result['executed']) . "\n";
    echo "Errors: " . count((array)$result['errors']) . "\n";
    if (!empty($result['errors'])) {
        foreach ($result['errors'] as $error) {
            echo " - " . (($error['entry']['target'] ?? 'unknown')) . ': ' . ($error['error'] ?? 'SQL error') . "\n";
        }
        return 1;
    }

    return 0;
}

function hg_schema_cli_load_env_config(): array
{
    $projectRoot = realpath(__DIR__ . '/../../');
    if ($projectRoot === false) {
        return [];
    }

    $candidates = [
        dirname($projectRoot) . DIRECTORY_SEPARATOR . 'config.env',
        $projectRoot . DIRECTORY_SEPARATOR . 'config.env',
        $projectRoot . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'config.env',
    ];

    foreach ($candidates as $candidate) {
        if (!is_file($candidate)) {
            continue;
        }
        $parsed = parse_ini_file($candidate);
        if (is_array($parsed)) {
            return $parsed;
        }
    }

    return [];
}

function hg_schema_cli_connect(): ?mysqli
{
    $env = hg_schema_cli_load_env_config();
    $host = trim((string)($env['MYSQL_HOST'] ?? ''));
    $user = trim((string)($env['MYSQL_USER'] ?? ''));
    $password = (string)($env['MYSQL_PWD'] ?? '');
    $database = trim((string)($env['MYSQL_BDD'] ?? ''));

    if ($host === '' || $user === '' || $database === '') {
        fwrite(STDERR, "Missing MYSQL_* values in config.env.\n");
        return null;
    }

    $mysqli = mysqli_init();
    if (!$mysqli instanceof mysqli) {
        fwrite(STDERR, "Could not initialize mysqli.\n");
        return null;
    }

    if (!@$mysqli->real_connect($host, $user, $password, $database)) {
        fwrite(STDERR, "Database connection failed: " . mysqli_connect_error() . "\n");
        return null;
    }

    @$mysqli->set_charset('utf8mb4');
    return $mysqli;
}

if (PHP_SAPI === 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    mysqli_report(MYSQLI_REPORT_OFF);
    $link = hg_schema_cli_connect();
    if (!$link instanceof mysqli) {
        exit(1);
    }
    exit(hg_schema_cli_main($link, $argv ?? []));
}
