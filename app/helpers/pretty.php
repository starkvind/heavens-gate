<?php
// Pretty ID helpers

function slugify_pretty_id(string $text): string {
    $text = trim($text);
    if ($text === '') return '';

    if (function_exists('iconv')) {
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    }
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim($text, '-');
    return $text;
}

function get_pretty_id(mysqli $link, string $table, int $id): ?string {
    static $cache = [];
    if ($id <= 0) return null;
    if (isset($cache[$table][$id])) return $cache[$table][$id];

    $stmt = $link->prepare("SELECT pretty_id FROM $table WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    $pretty = isset($row['pretty_id']) && $row['pretty_id'] !== '' ? (string)$row['pretty_id'] : null;
    $cache[$table][$id] = $pretty;
    return $pretty;
}

function resolve_pretty_id(mysqli $link, string $table, string $value): ?int {
    $value = trim($value);
    if ($value === '') return null;
    if (preg_match('/^\d+$/', $value)) return (int)$value;

    $stmt = $link->prepare("SELECT id FROM $table WHERE pretty_id = ? LIMIT 1");
    $stmt->bind_param('s', $value);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ? (int)$row['id'] : null;
}

function pretty_url(mysqli $link, string $table, string $base, int $id): string {
    $pretty = get_pretty_id($link, $table, $id);
    if ($pretty) return rtrim($base, '/') . '/' . rawurlencode($pretty);
    return rtrim($base, '/') . '/' . $id;
}

function ensure_trailing_query(string $url, string $query): string {
    if ($query === '') return $url;
    if (str_contains($url, '?')) return $url . '&' . $query;
    return $url . '?' . $query;
}

function hg_table_has_column(mysqli $link, string $table, string $column): bool {
    static $cache = [];
    $key = $table . ':' . $column;
    if (isset($cache[$key])) return $cache[$key];

    $ok = false;
    if ($st = $link->prepare("SHOW COLUMNS FROM `$table` LIKE ?")) {
        $st->bind_param('s', $column);
        $st->execute();
        $res = $st->get_result();
        $ok = $res && $res->num_rows > 0;
        $st->close();
    }

    $cache[$key] = $ok;
    return $ok;
}

function hg_update_pretty_id_if_exists(mysqli $link, string $table, int $id, string $source): void {
    if ($id <= 0) return;
    if (!hg_table_has_column($link, $table, 'pretty_id')) return;
    $slug = slugify_pretty_id($source);
    if ($slug === '') $slug = (string)$id;

    if ($st = $link->prepare("UPDATE `$table` SET pretty_id=? WHERE id=?")) {
        $st->bind_param('si', $slug, $id);
        $st->execute();
        $st->close();
    }
}
