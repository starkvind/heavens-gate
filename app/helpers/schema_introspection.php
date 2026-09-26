<?php
/**
 * Cached current-database schema compatibility checks.
 *
 * This is the single shared owner for ordinary table/column existence probes.
 * Callers should use these helpers instead of embedding ad-hoc schema SQL.
 * Dynamic metadata discovery and diagnostics remain in their specialized owners.
 */

if (!function_exists('hg_table_has_column')) {
    function hg_table_has_column(mysqli $link, string $table, string $column): bool
    {
        static $cache = [];
        $table = trim($table);
        $column = trim($column);
        if ($table === '' || $column === '') {
            return false;
        }

        $key = $table . ':' . $column;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $ok = false;
        $stmt = $link->prepare(
            "SELECT COUNT(*)
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?"
        );
        if ($stmt) {
            $stmt->bind_param('ss', $table, $column);
            $stmt->execute();
            $stmt->bind_result($count);
            $stmt->fetch();
            $ok = ((int)$count > 0);
            $stmt->close();
        }

        $cache[$key] = $ok;
        return $ok;
    }
}

if (!function_exists('hg_table_exists')) {
    function hg_table_exists(mysqli $link, string $table): bool
    {
        static $cache = [];
        $table = trim($table);
        if ($table === '') {
            return false;
        }
        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }

        $ok = false;
        $stmt = $link->prepare(
            "SELECT COUNT(*)
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?"
        );
        if ($stmt) {
            $stmt->bind_param('s', $table);
            $stmt->execute();
            $stmt->bind_result($count);
            $stmt->fetch();
            $ok = ((int)$count > 0);
            $stmt->close();
        }

        $cache[$table] = $ok;
        return $ok;
    }
}
