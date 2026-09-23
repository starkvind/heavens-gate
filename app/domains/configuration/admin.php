<?php
// Admin data ownership for web configuration and menu editing.

if (!function_exists('hg_configuration_admin_load_admin_password')) {
    function hg_configuration_admin_load_admin_password(mysqli $link): array
    {
        try {
            $stmt = $link->prepare("SELECT config_value FROM dim_web_configuration WHERE config_name = 'rel_pwd' ORDER BY id DESC LIMIT 1");
            if (!$stmt) return ['ok' => false, 'found' => false, 'value' => '', 'error' => $link->error];
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result ? $result->fetch_assoc() : null;
            $stmt->close();
            if (!$row || !array_key_exists('config_value', $row)) {
                return ['ok' => true, 'found' => false, 'value' => '', 'error' => ''];
            }
            return ['ok' => true, 'found' => true, 'value' => (string)$row['config_value'], 'error' => ''];
        } catch (mysqli_sql_exception $e) {
            return ['ok' => false, 'found' => false, 'value' => '', 'error' => $e->getMessage()];
        }
    }
}

if (!function_exists('hg_admin_store_password_value')) {
    function hg_admin_store_password_value(mysqli $link, string $value): bool
    {
        try {
            $stmt = $link->prepare("UPDATE dim_web_configuration SET config_value = ? WHERE config_name = 'rel_pwd' ORDER BY id DESC LIMIT 1");
            if (!$stmt) return false;
            $stmt->bind_param('s', $value);
            $ok = $stmt->execute();
            $stmt->close();
            return $ok;
        } catch (mysqli_sql_exception $e) {
            return false;
        }
    }
}

if (!function_exists('hg_configuration_admin_menu_allowed_fields')) {
    function hg_configuration_admin_menu_allowed_fields(): array
    {
        return ['label','href','target','item_type','dynamic_source','css_class','icon','icon_hover','menu_key','enabled'];
    }
}

if (!function_exists('hg_configuration_admin_menu_prepare_fields')) {
    function hg_configuration_admin_menu_prepare_fields(array $fields): array
    {
        $allowed = hg_configuration_admin_menu_allowed_fields();
        $set = [];
        $types = '';
        $values = [];
        foreach ($fields as $key => $value) {
            if (!in_array($key, $allowed, true)) continue;
            if ($key === 'enabled') {
                $value = (int)((string)$value === '1' || $value === 1 || $value === true);
                $types .= 'i';
            } else {
                $value = (string)$value;
                $types .= 's';
            }
            $set[] = $key . '=?';
            $values[] = $value;
        }
        return ['set' => $set, 'types' => $types, 'values' => $values];
    }
}

if (!function_exists('hg_configuration_admin_menu_update')) {
    function hg_configuration_admin_menu_update(mysqli $link, int $id, array $fields): array
    {
        if ($id <= 0) return ['ok' => false, 'error' => 'invalid_id', 'allowed' => 0];
        $prepared = hg_configuration_admin_menu_prepare_fields($fields);
        if (empty($prepared['set'])) return ['ok' => false, 'error' => 'no_allowed_fields', 'allowed' => 0];

        $types = (string)$prepared['types'] . 'i';
        $values = (array)$prepared['values'];
        $values[] = $id;
        $sql = 'UPDATE dim_menu_items SET ' . implode(',', $prepared['set']) . ' WHERE id=?';

        try {
            $st = $link->prepare($sql);
            if (!$st) return ['ok' => false, 'error' => $link->error, 'allowed' => count($prepared['set'])];
            $st->bind_param($types, ...$values);
            $ok = $st->execute();
            $error = $st->error;
            $st->close();
            return ['ok' => $ok, 'error' => $error, 'allowed' => count($prepared['set'])];
        } catch (mysqli_sql_exception $e) {
            return ['ok' => false, 'error' => $e->getMessage(), 'allowed' => count($prepared['set'])];
        }
    }
}

if (!function_exists('hg_configuration_admin_menu_next_order')) {
    function hg_configuration_admin_menu_next_order(mysqli $link, ?int $parentId): int
    {
        try {
            if ($parentId === null) {
                $res = $link->query('SELECT COALESCE(MAX(sort_order),0)+1 AS n FROM dim_menu_items WHERE parent_id IS NULL');
                $next = 1;
                if ($res && ($row = $res->fetch_assoc())) $next = (int)$row['n'];
                if ($res) $res->close();
                return max(1, $next);
            }
            $st = $link->prepare('SELECT COALESCE(MAX(sort_order),0)+1 AS n FROM dim_menu_items WHERE parent_id = ?');
            if (!$st) return 1;
            $st->bind_param('i', $parentId);
            $st->execute();
            $st->bind_result($next);
            $value = $st->fetch() ? (int)$next : 1;
            $st->close();
            return max(1, $value);
        } catch (mysqli_sql_exception $e) {
            return 1;
        }
    }
}

if (!function_exists('hg_configuration_admin_menu_create')) {
    function hg_configuration_admin_menu_create(mysqli $link, ?int $parentId, string $label): array
    {
        $label = trim($label);
        if ($label === '') $label = 'Nuevo menu';
        $next = hg_configuration_admin_menu_next_order($link, $parentId);

        try {
            if ($parentId === null) {
                $sql = "INSERT INTO dim_menu_items (parent_id,label,href,target,item_type,dynamic_source,css_class,icon,icon_hover,menu_key,enabled,sort_order) VALUES (NULL, ?, '', '_self', 'static', '', '', '', '', '', 1, ?)";
                $st = $link->prepare($sql);
                if (!$st) return ['ok' => false, 'error' => $link->error, 'data' => null];
                $st->bind_param('si', $label, $next);
            } else {
                $sql = "INSERT INTO dim_menu_items (parent_id,label,href,target,item_type,dynamic_source,css_class,icon,icon_hover,menu_key,enabled,sort_order) VALUES (?, ?, '', '_self', 'static', '', '', '', '', '', 1, ?)";
                $st = $link->prepare($sql);
                if (!$st) return ['ok' => false, 'error' => $link->error, 'data' => null];
                $st->bind_param('isi', $parentId, $label, $next);
            }
            $ok = $st->execute();
            $error = $st->error;
            $newId = $ok ? (int)$st->insert_id : 0;
            $st->close();
            if (!$ok) return ['ok' => false, 'error' => $error, 'data' => null];

            return [
                'ok' => true,
                'error' => '',
                'data' => [
                    'id' => $newId,
                    'parent_id' => $parentId,
                    'label' => $label,
                    'href' => '',
                    'target' => '_self',
                    'item_type' => 'static',
                    'dynamic_source' => '',
                    'css_class' => '',
                    'icon' => '',
                    'icon_hover' => '',
                    'menu_key' => '',
                    'enabled' => 1,
                    'sort_order' => $next,
                ],
            ];
        } catch (mysqli_sql_exception $e) {
            return ['ok' => false, 'error' => $e->getMessage(), 'data' => null];
        }
    }
}

if (!function_exists('hg_configuration_admin_menu_delete')) {
    function hg_configuration_admin_menu_delete(mysqli $link, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn($v) => $v > 0)));
        if (!$ids) return ['ok' => false, 'error' => 'empty_ids', 'ids' => []];

        try {
            $link->begin_transaction();

            // Menu has a self-reference through parent_id. Delete selected children first
            // so deleting a top-level menu is safe without changing FK semantics.
            $in = implode(',', array_fill(0, count($ids), '?'));
            $types = str_repeat('i', count($ids));

            $children = $link->prepare("DELETE FROM dim_menu_items WHERE parent_id IN ($in) AND id IN ($in)");
            if (!$children) throw new RuntimeException($link->error);
            $args = array_merge($ids, $ids);
            $children->bind_param($types . $types, ...$args);
            if (!$children->execute()) throw new RuntimeException($children->error);
            $children->close();

            $rows = $link->prepare("DELETE FROM dim_menu_items WHERE id IN ($in)");
            if (!$rows) throw new RuntimeException($link->error);
            $rows->bind_param($types, ...$ids);
            if (!$rows->execute()) throw new RuntimeException($rows->error);
            $rows->close();

            $link->commit();
            return ['ok' => true, 'error' => '', 'ids' => $ids];
        } catch (Throwable $e) {
            $link->rollback();
            return ['ok' => false, 'error' => $e->getMessage(), 'ids' => $ids];
        }
    }
}

if (!function_exists('hg_configuration_admin_menu_update_bulk')) {
    function hg_configuration_admin_menu_update_bulk(mysqli $link, array $items): array
    {
        if (!$items) return ['ok' => false, 'error' => 'empty_items', 'updated' => 0];
        $ok = true;
        $updated = 0;
        foreach ($items as $item) {
            $id = (int)($item['id'] ?? 0);
            $fields = (array)($item['fields'] ?? []);
            if ($id <= 0 || !$fields) continue;
            $result = hg_configuration_admin_menu_update($link, $id, $fields);
            if (!empty($result['ok'])) {
                $updated++;
            } elseif (($result['error'] ?? '') !== 'no_allowed_fields') {
                $ok = false;
            }
        }
        return ['ok' => $ok, 'error' => $ok ? '' : 'bulk_update_failed', 'updated' => $updated];
    }
}

if (!function_exists('hg_configuration_admin_menu_reorder')) {
    function hg_configuration_admin_menu_reorder(mysqli $link, array $items): array
    {
        if (!$items) return ['ok' => false, 'error' => 'empty_items', 'updated' => 0];

        $ok = true;
        $updated = 0;
        foreach ($items as $item) {
            $id = (int)($item['id'] ?? 0);
            $parentId = $item['parent_id'] ?? null;
            $order = (int)($item['sort_order'] ?? 0);
            if ($id <= 0) continue;
            try {
                if ($parentId === null || $parentId === '') {
                    $st = $link->prepare('UPDATE dim_menu_items SET parent_id=NULL, sort_order=? WHERE id=?');
                    if (!$st) { $ok = false; continue; }
                    $st->bind_param('ii', $order, $id);
                } else {
                    $pid = (int)$parentId;
                    $st = $link->prepare('UPDATE dim_menu_items SET parent_id=?, sort_order=? WHERE id=?');
                    if (!$st) { $ok = false; continue; }
                    $st->bind_param('iii', $pid, $order, $id);
                }
                $thisOk = $st->execute();
                $st->close();
                $ok = $ok && $thisOk;
                if ($thisOk) $updated++;
            } catch (mysqli_sql_exception $e) {
                $ok = false;
            }
        }
        return ['ok' => $ok, 'error' => $ok ? '' : 'reorder_failed', 'updated' => $updated];
    }
}

if (!function_exists('hg_configuration_admin_menu_rows')) {
    function hg_configuration_admin_menu_rows(mysqli $link): array
    {
        $rows = [];
        try {
            $rs = $link->query('SELECT id,parent_id,label,href,target,item_type,dynamic_source,css_class,icon,icon_hover,menu_key,enabled,sort_order FROM dim_menu_items ORDER BY parent_id, sort_order, id');
            if ($rs) {
                while ($row = $rs->fetch_assoc()) $rows[] = $row;
                $rs->close();
            }
        } catch (mysqli_sql_exception $e) {
            return [];
        }
        return $rows;
    }
}
