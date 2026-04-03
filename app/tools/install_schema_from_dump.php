<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be executed from CLI.\n");
    exit(1);
}

main($argv);

function main(array $argv): void
{
    $options = parseCliArgs($argv);

    if (isset($options['help'])) {
        printUsage();
        exit(0);
    }

    $projectRoot = realpath(__DIR__ . '/../../');
    if ($projectRoot === false) {
        throw new RuntimeException("Could not resolve project root.");
    }

    $env = loadEnvConfig($projectRoot, $options['config'] ?? null);

    $host = firstNonEmpty($options['host'] ?? null, $env['MYSQL_HOST'] ?? null, getenv('MYSQL_HOST') ?: null, '127.0.0.1');
    $user = firstNonEmpty($options['user'] ?? null, $env['MYSQL_USER'] ?? null, getenv('MYSQL_USER') ?: null);
    $password = firstNonEmpty($options['password'] ?? null, $env['MYSQL_PWD'] ?? null, getenv('MYSQL_PWD') ?: null, '');
    $database = firstNonEmpty($options['database'] ?? null, $env['MYSQL_BDD'] ?? null, getenv('MYSQL_BDD') ?: null);
    $dumpPath = resolvePath(firstNonEmpty($options['dump'] ?? null, guessDumpPath($projectRoot)), getcwd() ?: $projectRoot);
    $dryRun = cliBool($options, 'dry-run', false);
    $dropExisting = cliBool($options, 'drop-existing', true);
    $createViews = cliBool($options, 'create-views', true);
    $seedSafeConfig = cliBool($options, 'seed-safe-config', true);
    $adminPassword = $options['admin-password'] ?? null;

    if ($user === null || $user === '') {
        throw new InvalidArgumentException("Missing MySQL user. Pass --user or configure MYSQL_USER.");
    }

    if ($database === null || $database === '') {
        throw new InvalidArgumentException("Missing target database. Pass --database or configure MYSQL_BDD.");
    }

    if ($dumpPath === null || !is_file($dumpPath)) {
        throw new InvalidArgumentException("SQL dump not found. Pass --dump with a valid path.");
    }

    $dumpSql = file_get_contents($dumpPath);
    if ($dumpSql === false) {
        throw new RuntimeException("Could not read dump file: {$dumpPath}");
    }

    $tableStatements = extractCreateTableStatements($dumpSql, $dropExisting);
    $viewStatements = $createViews ? extractFinalViewStatements($dumpSql) : [];

    if (empty($tableStatements)) {
        throw new RuntimeException("No CREATE TABLE statements were extracted from the dump.");
    }

    echo "== HG schema installer ==\n";
    echo "Project root: {$projectRoot}\n";
    echo "Dump: {$dumpPath}\n";
    echo "Database: {$database}\n";
    echo "Tables: " . count($tableStatements) . "\n";
    echo "Views: " . count($viewStatements) . "\n";
    echo "Seed safe dim_web_configuration values: " . ($seedSafeConfig ? 'yes' : 'no') . "\n";
    echo "Drop existing tables: " . ($dropExisting ? 'yes' : 'no') . "\n";
    echo "Dry run: " . ($dryRun ? 'yes' : 'no') . "\n\n";

    if ($dryRun) {
        echo "Dry-run completed. No changes were executed.\n";
        return;
    }

    $mysqli = connectServer($host, $user, $password);

    try {
        createDatabaseIfNeeded($mysqli, $database);
        if (!$mysqli->select_db($database)) {
            throw new RuntimeException("Could not select database {$database}: " . $mysqli->error);
        }
        if (!$mysqli->set_charset('utf8mb4')) {
            throw new RuntimeException("Could not set utf8mb4 charset: " . $mysqli->error);
        }

        executeStatements($mysqli, array_values($tableStatements));
        if (!empty($viewStatements)) {
            executeStatements($mysqli, array_values($viewStatements), false);
        }

        if ($seedSafeConfig) {
            seedSafeWebConfiguration($mysqli);
        }

        if ($adminPassword !== null && $adminPassword !== '') {
            $hashedPassword = hashAdminPassword($adminPassword);
            upsertConfigValue($mysqli, 'rel_pwd', $hashedPassword);
            echo "Admin password stored in dim_web_configuration as rel_pwd.\n";
        } else {
            echo "rel_pwd was intentionally not imported. Configure it manually or rerun with --admin-password.\n";
        }

        echo "Schema installation completed successfully.\n";
    } finally {
        $mysqli->close();
    }
}

function parseCliArgs(array $argv): array
{
    $options = [];

    foreach ($argv as $index => $arg) {
        if ($index === 0) {
            continue;
        }

        if (substr($arg, 0, 2) !== '--') {
            continue;
        }

        $arg = substr($arg, 2);
        $parts = explode('=', $arg, 2);
        $key = trim($parts[0]);
        $value = $parts[1] ?? '1';

        if ($key !== '') {
            $options[$key] = $value;
        }
    }

    return $options;
}

function printUsage(): void
{
    echo "Usage:\n";
    echo "  php app/tools/install_schema_from_dump.php [options]\n\n";
    echo "Options:\n";
    echo "  --host=127.0.0.1\n";
    echo "  --user=root\n";
    echo "  --password=secret\n";
    echo "  --database=hg\n";
    echo "  --dump=path/to/dump.sql\n";
    echo "  --config=path/to/config.env\n";
    echo "  --drop-existing=1|0          Default: 1\n";
    echo "  --create-views=1|0           Default: 1\n";
    echo "  --seed-safe-config=1|0       Default: 1\n";
    echo "  --admin-password=plaintext   Optional, stored hashed as rel_pwd\n";
    echo "  --dry-run=1                  Validate config and count statements only\n";
    echo "  --help=1\n";
}

function loadEnvConfig(string $projectRoot, ?string $explicitConfigPath): array
{
    $candidates = [];

    if ($explicitConfigPath !== null && $explicitConfigPath !== '') {
        $resolved = resolvePath($explicitConfigPath, getcwd() ?: $projectRoot);
        if ($resolved !== null) {
            $candidates[] = $resolved;
        }
    }

    $candidates[] = $projectRoot . DIRECTORY_SEPARATOR . 'config.env';
    $candidates[] = dirname($projectRoot) . DIRECTORY_SEPARATOR . 'config.env';

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            $parsed = parse_ini_file($candidate);
            if (is_array($parsed)) {
                return $parsed;
            }
        }
    }

    return [];
}

function guessDumpPath(string $projectRoot): ?string
{
    $matches = glob($projectRoot . DIRECTORY_SEPARATOR . 'dump-*.sql');
    if ($matches === false || empty($matches)) {
        return null;
    }

    usort($matches, static function (string $left, string $right): int {
        return filemtime($right) <=> filemtime($left);
    });

    return $matches[0];
}

function resolvePath(?string $path, string $baseDir): ?string
{
    if ($path === null || $path === '') {
        return null;
    }

    if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1 || strpos($path, '\\\\') === 0 || strpos($path, '/') === 0) {
        $resolved = realpath($path);
        return $resolved !== false ? $resolved : $path;
    }

    $resolved = realpath($baseDir . DIRECTORY_SEPARATOR . $path);
    return $resolved !== false ? $resolved : ($baseDir . DIRECTORY_SEPARATOR . $path);
}

function firstNonEmpty(?string ...$values): ?string
{
    foreach ($values as $value) {
        if ($value !== null && $value !== '') {
            return $value;
        }
    }

    return null;
}

function cliBool(array $options, string $name, bool $default): bool
{
    if (!array_key_exists($name, $options)) {
        return $default;
    }

    $value = strtoupper(trim((string)$options[$name]));
    return in_array($value, ['1', 'TRUE', 'YES', 'ON'], true);
}

function connectServer(string $host, string $user, string $password): mysqli
{
    $mysqli = mysqli_init();
    if ($mysqli === false) {
        throw new RuntimeException("Could not initialize mysqli.");
    }

    if (!@$mysqli->real_connect($host, $user, $password)) {
        throw new RuntimeException("MySQL connection failed: " . mysqli_connect_error());
    }

    return $mysqli;
}

function createDatabaseIfNeeded(mysqli $mysqli, string $database): void
{
    $safeDbName = str_replace('`', '``', $database);
    $sql = "CREATE DATABASE IF NOT EXISTS `{$safeDbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    if (!$mysqli->query($sql)) {
        throw new RuntimeException("Could not create database {$database}: " . $mysqli->error);
    }
}

function extractCreateTableStatements(string $dumpSql, bool $dropExisting): array
{
    $matches = [];
    preg_match_all('/CREATE TABLE `([^`]+)` \((?:.|\n)*?\)[^;]*;/u', $dumpSql, $matches, PREG_SET_ORDER);

    $statements = [];
    foreach ($matches as $match) {
        $tableName = $match[1];
        $sql = trim($match[0]);

        if (!$dropExisting) {
            $sql = preg_replace('/^CREATE TABLE /', 'CREATE TABLE IF NOT EXISTS ', $sql, 1) ?? $sql;
        } else {
            $statements["drop_table_{$tableName}"] = "DROP TABLE IF EXISTS `{$tableName}`;";
        }

        $statements["create_table_{$tableName}"] = $sql;
    }

    return $statements;
}

function extractFinalViewStatements(string $dumpSql): array
{
    $matches = [];
    preg_match_all('/\/\*!50001 VIEW `([^`]+)` AS (.*?) \*\/;/su', $dumpSql, $matches, PREG_SET_ORDER);

    $statements = [];
    foreach ($matches as $match) {
        $viewName = $match[1];
        $viewDefinition = trim($match[2]);

        $statements["drop_view_{$viewName}"] = "DROP VIEW IF EXISTS `{$viewName}`;";
        $statements["create_view_{$viewName}"] = "CREATE OR REPLACE VIEW `{$viewName}` AS {$viewDefinition};";
    }

    return $statements;
}

function executeStatements(mysqli $mysqli, array $statements, bool $toggleForeignKeys = true): void
{
    if ($toggleForeignKeys) {
        if (!$mysqli->query("SET FOREIGN_KEY_CHECKS=0")) {
            throw new RuntimeException("Could not disable foreign keys: " . $mysqli->error);
        }
    }

    try {
        foreach ($statements as $statement) {
            if (!$mysqli->query($statement)) {
                throw new RuntimeException(
                    "SQL error: " . $mysqli->error . "\nStatement:\n" . $statement . "\n"
                );
            }
        }
    } finally {
        if ($toggleForeignKeys) {
            $mysqli->query("SET FOREIGN_KEY_CHECKS=1");
        }
    }
}

function seedSafeWebConfiguration(mysqli $mysqli): void
{
    $defaults = [
        'error_reporting' => 'FALSE',
        'exclude_chronicles' => '',
        'combat_simulator_ip_limit_enabled' => 'TRUE',
        'combat_simulator_ip_limit_max_attempts_per_hour' => '25',
        'combat_simulator_ip_limit_max_attempts_per_day' => '120',
        'combat_simulator_rubberbanding_max_bonus_dice' => '10',
        'combat_simulator_rubberbanding_failures_per_bonus' => '1',
    ];

    foreach ($defaults as $name => $value) {
        upsertConfigValue($mysqli, $name, $value);
    }

    echo "Safe dim_web_configuration values were created or refreshed.\n";
}

function upsertConfigValue(mysqli $mysqli, string $name, string $value): void
{
    $select = $mysqli->prepare(
        "SELECT id FROM dim_web_configuration WHERE config_name = ? ORDER BY id DESC LIMIT 1"
    );
    if (!$select) {
        throw new RuntimeException("Could not prepare dim_web_configuration lookup: " . $mysqli->error);
    }

    $select->bind_param('s', $name);
    if (!$select->execute()) {
        $select->close();
        throw new RuntimeException("Could not query dim_web_configuration: " . $select->error);
    }

    $result = $select->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $select->close();

    if ($row !== null && isset($row['id'])) {
        $id = (int)$row['id'];
        $update = $mysqli->prepare(
            "UPDATE dim_web_configuration SET config_value = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? LIMIT 1"
        );
        if (!$update) {
            throw new RuntimeException("Could not prepare dim_web_configuration update: " . $mysqli->error);
        }
        $update->bind_param('si', $value, $id);
        if (!$update->execute()) {
            $update->close();
            throw new RuntimeException("Could not update dim_web_configuration: " . $update->error);
        }
        $update->close();
        return;
    }

    $insert = $mysqli->prepare(
        "INSERT INTO dim_web_configuration (config_name, config_value) VALUES (?, ?)"
    );
    if (!$insert) {
        throw new RuntimeException("Could not prepare dim_web_configuration insert: " . $mysqli->error);
    }
    $insert->bind_param('ss', $name, $value);
    if (!$insert->execute()) {
        $insert->close();
        throw new RuntimeException("Could not insert dim_web_configuration: " . $insert->error);
    }
    $insert->close();
}

function hashAdminPassword(string $plainPassword): string
{
    $hashed = password_hash($plainPassword, PASSWORD_DEFAULT);
    if (!is_string($hashed) || $hashed === '') {
        throw new RuntimeException("Could not hash admin password.");
    }

    return $hashed;
}
