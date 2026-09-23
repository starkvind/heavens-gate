<?php

function hg_asos_table_exists(mysqli $link, string $table): bool
{
    $stmt = $link->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return (int)$count > 0;
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

