<?php
require_once(__DIR__ . '/../../helpers/schema_introspection.php');

function hg_asos_table_exists(mysqli $link, string $table): bool
{
    return hg_table_exists($link, $table);
}

function hg_asos_count_rows(mysqli $link, string $table): int
{
    if (!hg_asos_table_exists($link, $table)) {
        return 0;
    }
    $rs = $link->query("SELECT COUNT(*) AS c FROM `$table`");
    if (!$rs) {
        return 0;
    }
    $row = $rs->fetch_assoc();
    $rs->close();
    return (int)($row['c'] ?? 0);
}

