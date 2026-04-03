<?php
// Pretty ID helpers

function hg_pretty_normalize_source(string $text): string {
    $text = trim($text);
    if ($text === '') return '';

    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // Repair obvious legacy single-byte payloads before slugging.
    if (!preg_match('//u', $text)) {
        $converted = @iconv('Windows-1252', 'UTF-8//IGNORE', $text);
        if (is_string($converted) && $converted !== '') {
            $text = $converted;
        }
    }

    $text = strtr($text, [
        'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ä' => 'A', 'Ã' => 'A', 'Å' => 'A',
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a',
        'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Ö' => 'O', 'Õ' => 'O',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
        'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'Ñ' => 'N', 'ñ' => 'n',
        'Ç' => 'C', 'ç' => 'c',
        'Ý' => 'Y', 'Ÿ' => 'Y', 'ý' => 'y', 'ÿ' => 'y',
        'Æ' => 'AE', 'æ' => 'ae',
        'Œ' => 'OE', 'œ' => 'oe',
        'Ø' => 'O', 'ø' => 'o',
        'Ð' => 'D', 'ð' => 'd',
        'Þ' => 'TH', 'þ' => 'th',
        'ß' => 'ss',
        'ª' => 'a', 'º' => 'o',
    ]);

    if (class_exists('Normalizer')) {
        $normalized = \Normalizer::normalize($text, \Normalizer::FORM_D);
        if (is_string($normalized) && $normalized !== '') {
            $text = preg_replace('/\p{Mn}+/u', '', $normalized);
        }
    }

    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if (is_string($converted) && $converted !== '') {
            $text = $converted;
        }
    }

    return $text;
}

function hg_pretty_policy_source_key(string $text): string {
    $text = trim(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $text = preg_replace('/\s+/u', ' ', $text);
    if (!is_string($text) || $text === '') return '';
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($text, 'UTF-8');
    }
    return strtolower($text);
}

function hg_pretty_policy_slug(string $table, string $source): string {
    $table = trim($table);
    $sourceKey = hg_pretty_policy_source_key($source);
    if ($table === '' || $sourceKey === '') return '';

    static $policies = [
        'dim_realities' => [
            'gaia2' => 'gaia-2a',
            'gaia2β' => 'gaia-2b',
            'gaia1' => 'gaia-1',
            'gaia0' => 'gaia-zero',
        ],
    ];

    return (string)($policies[$table][$sourceKey] ?? '');
}

function hg_pretty_expected_slug(string $table, string $source, int $fallbackId = 0): string {
    $slug = hg_pretty_policy_slug($table, $source);
    if ($slug === '') {
        $slug = slugify_pretty_id($source);
    }
    if ($slug === '') {
        $slug = $fallbackId > 0 ? (string)$fallbackId : '';
    }
    return $slug;
}

function slugify_pretty_id(string $text): string {
    $text = hg_pretty_normalize_source($text);
    if ($text === '') return '';

    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim($text, '-');
    return $text;
}

function get_pretty_id(mysqli $link, string $table, int $id): ?string {
    static $cache = [];
    if ($id <= 0) return null;
    if (isset($cache[$table][$id])) return $cache[$table][$id];
    if (!hg_table_has_column($link, $table, 'pretty_id')) {
        $cache[$table][$id] = null;
        return null;
    }

    $stmt = $link->prepare("SELECT pretty_id FROM $table WHERE id = ? LIMIT 1");
    if (!$stmt) {
        $cache[$table][$id] = null;
        return null;
    }
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
    if (!hg_table_has_column($link, $table, 'pretty_id')) return null;

    $stmt = $link->prepare("SELECT id FROM $table WHERE pretty_id = ? LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param('s', $value);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if ($row) {
        return (int)$row['id'];
    }

    if (hg_table_exists($link, 'fact_pretty_id_aliases')) {
        $aliasStmt = $link->prepare("
            SELECT entity_id
            FROM fact_pretty_id_aliases
            WHERE table_name = ?
              AND old_pretty_id = ?
            LIMIT 1
        ");
        if ($aliasStmt) {
            $aliasStmt->bind_param('ss', $table, $value);
            $aliasStmt->execute();
            $aliasResult = $aliasStmt->get_result();
            $aliasRow = $aliasResult ? $aliasResult->fetch_assoc() : null;
            $aliasStmt->close();
            if ($aliasRow && isset($aliasRow['entity_id'])) {
                return (int)$aliasRow['entity_id'];
            }
        }
    }

    return null;
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
    // MariaDB/MySQL can fail preparing SHOW ... LIKE ? with placeholders.
    // Use information_schema with bind params for full compatibility.
    if ($st = $link->prepare("
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ")) {
        $st->bind_param('ss', $table, $column);
        $st->execute();
        $st->bind_result($count);
        $st->fetch();
        $ok = ((int)$count > 0);
        $st->close();
    }

    $cache[$key] = $ok;
    return $ok;
}

function hg_table_exists(mysqli $link, string $table): bool {
    static $cache = [];
    $table = trim($table);
    if ($table === '') return false;
    if (isset($cache[$table])) return $cache[$table];

    $ok = false;
    if ($st = $link->prepare("
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
    ")) {
        $st->bind_param('s', $table);
        $st->execute();
        $st->bind_result($count);
        $st->fetch();
        $ok = ((int)$count > 0);
        $st->close();
    }

    $cache[$table] = $ok;
    return $ok;
}

function hg_update_pretty_id_if_exists(mysqli $link, string $table, int $id, string $source): void {
    if ($id <= 0) return;
    if (!hg_table_has_column($link, $table, 'pretty_id')) return;
    $slug = hg_pretty_expected_slug($table, $source, $id);

    if ($st = $link->prepare("UPDATE `$table` SET pretty_id=? WHERE id=?")) {
        $st->bind_param('si', $slug, $id);
        $st->execute();
        $st->close();
    }
}
