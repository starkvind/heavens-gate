<?php

include_once(__DIR__ . '/../../helpers/content_updates.php');
include_once(__DIR__ . '/../../helpers/pretty.php');

if (!function_exists('hg_powers_admin_slugify_pretty')) {
    function hg_powers_admin_slugify_pretty(string $text): string
    {
        $text = trim($text);
        if ($text === '') return '';
        if (function_exists('iconv')) {
            $text = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
        }
        $text = preg_replace('~[^\\pL\\d]+~u', '-', $text);
        $text = trim($text, '-');
        $text = strtolower($text);
        return preg_replace('~[^-a-z0-9]+~', '', $text);
    }
}

if (!function_exists('hg_powers_admin_update_pretty_id')) {
    function hg_powers_admin_update_pretty_id(mysqli $link, string $table, int $id, string $source): void
    {
        if ($id <= 0) return;
        $slug = hg_powers_admin_slugify_pretty($source);
        if ($slug === '') $slug = (string)$id;
        $stmt = $link->prepare("UPDATE `{$table}` SET pretty_id=? WHERE id=?");
        if (!$stmt) return;
        $stmt->bind_param('si', $slug, $id);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('hg_powers_admin_fetch_pairs')) {
    function hg_powers_admin_fetch_pairs(mysqli $link, string $kind): array
    {
        $queries = [
            'origins' => "SELECT id, name FROM dim_bibliographies ORDER BY name",
            'systems' => "SELECT id, name FROM dim_systems ORDER BY name",
            'gift_types' => "SELECT id, name FROM dim_gift_types ORDER BY id",
            'rite_types_full' => "SELECT id, CONCAT(name, IFNULL(CONCAT(' ', determinant),'')) AS name FROM dim_rite_types ORDER BY id",
            'rite_types' => "SELECT id, name FROM dim_rite_types ORDER BY id",
            'totem_types' => "SELECT id, name FROM dim_totem_types ORDER BY id",
            'discipline_types' => "SELECT id, name FROM dim_discipline_types ORDER BY id",
        ];
        if (!isset($queries[$kind])) return [];

        $out = [];
        $result = @$link->query($queries[$kind]);
        if (!$result) return $out;
        while ($row = $result->fetch_assoc()) {
            $id = isset($row['id']) ? (int)$row['id'] : (int)($row['value'] ?? 0);
            $out[$id] = (string)($row['name'] ?? '');
        }
        $result->close();
        return $out;
    }
}

if (!function_exists('hg_powers_admin_has_column')) {
    function hg_powers_admin_has_column(mysqli $link, string $table, string $column): bool
    {
        return hg_table_has_column($link, $table, $column);
    }
}

if (!function_exists('hg_powers_admin_create')) {
    function hg_powers_admin_create(mysqli $link, array $meta, array $vals): array
    {
        $table = (string)$meta['table'];
        $cols = [];
        $ph = [];
        $types = '';
        $bind = [];
        foreach ($meta['fields'] as $field) {
            $cols[] = (string)$field['k'];
            $ph[] = '?';
            $types .= (($field['db'] ?? 's') === 'i') ? 'i' : 's';
            $bind[] = $vals[$field['k']];
        }

        $quoted = implode(',', array_map(static fn($col) => "`{$col}`", $cols));
        $sql = "INSERT INTO `{$table}` ({$quoted}) VALUES (" . implode(',', $ph) . ")";
        if (!empty($meta['has_timestamps'])) {
            $sqlTry = "INSERT INTO `{$table}` ({$quoted}, `created_at`, `updated_at`) VALUES (" . implode(',', $ph) . ", NOW(), NOW())";
            $probe = @$link->prepare($sqlTry);
            if ($probe) {
                $probe->close();
                $sql = $sqlTry;
            }
        }

        $stmt = $link->prepare($sql);
        if (!$stmt) return ['ok' => false, 'error' => 'prepare', 'message' => $link->error];

        $stmt->bind_param($types, ...$bind);
        if (!$stmt->execute()) {
            $message = $stmt->error;
            $stmt->close();
            return ['ok' => false, 'error' => 'execute', 'message' => $message];
        }

        $id = (int)$stmt->insert_id;
        $stmt->close();
        $source = (string)($vals[$meta['name_col']] ?? '');
        hg_powers_admin_update_pretty_id($link, $table, $id, $source);
        hg_content_touch_table($link, $table, $id);
        return ['ok' => true, 'id' => $id];
    }
}

if (!function_exists('hg_powers_admin_update')) {
    function hg_powers_admin_update(mysqli $link, array $meta, array $vals, int $id): array
    {
        $table = (string)$meta['table'];
        $pk = (string)$meta['pk'];
        $sets = [];
        $types = '';
        $bind = [];
        foreach ($meta['fields'] as $field) {
            $sets[] = "`" . $field['k'] . "`=?";
            $types .= (($field['db'] ?? 's') === 'i') ? 'i' : 's';
            $bind[] = $vals[$field['k']];
        }

        $sql = "UPDATE `{$table}` SET " . implode(', ', $sets);
        if (!empty($meta['has_timestamps'])) $sql .= ", `updated_at`=NOW()";
        $sql .= " WHERE `{$pk}`=?";
        $types .= 'i';
        $bind[] = $id;

        $stmt = $link->prepare($sql);
        if (!$stmt) return ['ok' => false, 'error' => 'prepare', 'message' => $link->error];
        $stmt->bind_param($types, ...$bind);
        if (!$stmt->execute()) {
            $message = $stmt->error;
            $stmt->close();
            return ['ok' => false, 'error' => 'execute', 'message' => $message];
        }
        $stmt->close();

        $source = (string)($vals[$meta['name_col']] ?? '');
        hg_powers_admin_update_pretty_id($link, $table, $id, $source);
        hg_content_touch_table($link, $table, $id);
        return ['ok' => true];
    }
}

if (!function_exists('hg_powers_admin_fetch_image')) {
    function hg_powers_admin_fetch_image(mysqli $link, string $table, string $pk, int $id): string
    {
        $stmt = $link->prepare("SELECT `image_url` FROM `{$table}` WHERE `{$pk}`=? LIMIT 1");
        if (!$stmt) return '';
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();
        return (string)($row['image_url'] ?? '');
    }
}

if (!function_exists('hg_powers_admin_delete')) {
    function hg_powers_admin_delete(mysqli $link, string $table, string $pk, int $id): array
    {
        $stmt = $link->prepare("DELETE FROM `{$table}` WHERE `{$pk}`=?");
        if (!$stmt) return ['ok' => false, 'error' => 'prepare', 'message' => $link->error];
        $stmt->bind_param('i', $id);
        if (!$stmt->execute()) {
            $message = $stmt->error;
            $stmt->close();
            return ['ok' => false, 'error' => 'execute', 'message' => $message];
        }
        $affected = (int)$stmt->affected_rows;
        $stmt->close();
        return ['ok' => $affected > 0, 'affected' => $affected];
    }
}

if (!function_exists('hg_powers_admin_count')) {
    function hg_powers_admin_count(mysqli $link, array $meta, string $q): array
    {
        $table = (string)$meta['table'];
        $nameCol = (string)$meta['name_col'];
        $where = "WHERE 1=1";
        $types = '';
        $params = [];
        if ($q !== '') {
            $where .= " AND `{$nameCol}` LIKE ?";
            $types = 's';
            $params[] = '%' . $q . '%';
        }

        $stmt = $link->prepare("SELECT COUNT(*) AS c FROM `{$table}` {$where}");
        if (!$stmt) return ['ok' => false, 'count' => 0, 'error' => $link->error];
        if ($types !== '') $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            return ['ok' => false, 'count' => 0, 'error' => $error];
        }
        $result = $stmt->get_result();
        $count = ($result && ($row = $result->fetch_assoc())) ? (int)$row['c'] : 0;
        $stmt->close();
        return ['ok' => true, 'count' => $count];
    }
}

if (!function_exists('hg_powers_admin_fetch_rows')) {
    function hg_powers_admin_fetch_rows(
        mysqli $link,
        array $meta,
        string $q,
        ?int $offset = null,
        ?int $limit = null
    ): array {
        $table = (string)$meta['table'];
        $pk = (string)$meta['pk'];
        $nameCol = (string)$meta['name_col'];
        $where = "WHERE 1=1";
        $types = '';
        $params = [];
        if ($q !== '') {
            $where .= " AND `{$nameCol}` LIKE ?";
            $types = 's';
            $params[] = '%' . $q . '%';
        }

        $cols = array_map(static fn($field) => "`" . $field['k'] . "`", $meta['fields']);
        $cols[] = "`{$pk}`";
        $cols = array_values(array_unique($cols));

        $sql = "SELECT " . implode(',', $cols) . " FROM `{$table}` {$where} ORDER BY " . $meta['order_by'];
        if ($offset !== null && $limit !== null) {
            $sql .= " LIMIT ?, ?";
            $types .= 'ii';
            $params[] = $offset;
            $params[] = $limit;
        }

        $stmt = $link->prepare($sql);
        if (!$stmt) return ['ok' => false, 'rows' => [], 'error' => $link->error];
        if ($types !== '') $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            return ['ok' => false, 'rows' => [], 'error' => $error];
        }
        $result = $stmt->get_result();
        if (!$result) {
            $stmt->close();
            return ['ok' => false, 'rows' => [], 'error' => $link->error];
        }

        $rows = [];
        while ($row = $result->fetch_assoc()) $rows[] = $row;
        $stmt->close();
        return ['ok' => true, 'rows' => $rows];
    }
}
