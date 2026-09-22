<?php

include_once(__DIR__ . '/../../helpers/admin_catalog_utils.php');

if (!function_exists('hg_chronicles_admin_fetch_current_image')) {
    function hg_chronicles_admin_fetch_current_image(mysqli $link, int $id): array
    {
        if ($id <= 0) {
            return ['exists' => false, 'image_url' => ''];
        }

        $stmt = $link->prepare('SELECT image_url FROM dim_chronicles WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return ['exists' => false, 'image_url' => ''];
        }

        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        return [
            'exists' => is_array($row),
            'image_url' => (string)($row['image_url'] ?? ''),
        ];
    }
}

if (!function_exists('hg_chronicles_admin_delete')) {
    function hg_chronicles_admin_delete(mysqli $link, int $id): array
    {
        $stmt = $link->prepare('DELETE FROM dim_chronicles WHERE id = ?');
        if (!$stmt) {
            hg_runtime_log_error('admin_chronicles.delete.prepare', $link->error);
            return ['ok' => false, 'message' => 'No se pudo preparar el borrado de la cronica.'];
        }

        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $error = $stmt->error;
        $stmt->close();

        if (!$ok) {
            hg_runtime_log_error('admin_chronicles.delete', $error);
            return ['ok' => false, 'message' => 'No se pudo eliminar la cronica.'];
        }

        return ['ok' => true, 'message' => 'Cronica eliminada.'];
    }
}

if (!function_exists('hg_chronicles_admin_create')) {
    function hg_chronicles_admin_create(
        mysqli $link,
        array $schema,
        string $name,
        string $description,
        int $sortOrder,
        string $imageUrl
    ): array {
        $cols = ['name', 'description'];
        $vals = [$name, $description];
        $types = 'ss';

        if (!empty($schema['pretty_id'])) {
            $basePretty = hg_pretty_expected_slug('dim_chronicles', $name, 0);
            if ($basePretty === '') {
                $basePretty = 'chronicle';
            }
            $insertPretty = $basePretty;
            $suffix = 2;
            while (hg_admin_catalog_pretty_exists($link, 'dim_chronicles', $insertPretty, 0)) {
                $insertPretty = $basePretty . '-' . $suffix;
                $suffix++;
            }
            $cols[] = 'pretty_id';
            $vals[] = $insertPretty;
            $types .= 's';
        }

        if (!empty($schema['sort_order'])) {
            $cols[] = 'sort_order';
            $vals[] = $sortOrder;
            $types .= 'i';
        }
        if (!empty($schema['image_url'])) {
            $cols[] = 'image_url';
            $vals[] = $imageUrl;
            $types .= 's';
        }
        if (!empty($schema['created_at'])) $cols[] = 'created_at';
        if (!empty($schema['updated_at'])) $cols[] = 'updated_at';

        $placeholders = [];
        foreach ($cols as $col) {
            $placeholders[] = ($col === 'created_at' || $col === 'updated_at') ? 'NOW()' : '?';
        }

        $sql = "INSERT INTO dim_chronicles (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', $placeholders) . ")";
        $stmt = $link->prepare($sql);
        if (!$stmt) {
            hg_runtime_log_error('admin_chronicles.create.prepare', $link->error);
            return ['ok' => false, 'message' => 'No se pudo preparar el alta de la cronica.'];
        }

        $stmt->bind_param($types, ...$vals);
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            hg_runtime_log_error('admin_chronicles.create', $error);
            return ['ok' => false, 'message' => 'No se pudo crear la cronica.'];
        }

        $newId = (int)$link->insert_id;
        $stmt->close();
        $prettyOk = hg_admin_catalog_persist_pretty_id($link, 'dim_chronicles', $newId, $name);

        return [
            'ok' => true,
            'id' => $newId,
            'pretty_ok' => $prettyOk,
            'message' => $prettyOk ? 'Cronica creada.' : 'Cronica creada, pero no se pudo guardar pretty_id.',
        ];
    }
}

if (!function_exists('hg_chronicles_admin_update')) {
    function hg_chronicles_admin_update(
        mysqli $link,
        array $schema,
        int $id,
        string $name,
        string $description,
        int $sortOrder,
        string $imageUrl
    ): array {
        $sets = ['`name` = ?', '`description` = ?'];
        $vals = [$name, $description];
        $types = 'ss';

        if (!empty($schema['sort_order'])) {
            $sets[] = '`sort_order` = ?';
            $vals[] = $sortOrder;
            $types .= 'i';
        }
        if (!empty($schema['image_url'])) {
            $sets[] = '`image_url` = ?';
            $vals[] = $imageUrl;
            $types .= 's';
        }
        if (!empty($schema['updated_at'])) {
            $sets[] = '`updated_at` = NOW()';
        }

        $vals[] = $id;
        $types .= 'i';

        $sql = "UPDATE dim_chronicles SET " . implode(', ', $sets) . " WHERE id = ?";
        $stmt = $link->prepare($sql);
        if (!$stmt) {
            hg_runtime_log_error('admin_chronicles.update.prepare', $link->error);
            return ['ok' => false, 'message' => 'No se pudo preparar la actualizacion de la cronica.'];
        }

        $stmt->bind_param($types, ...$vals);
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            hg_runtime_log_error('admin_chronicles.update', $error);
            return ['ok' => false, 'message' => 'No se pudo actualizar la cronica.'];
        }

        $stmt->close();
        $prettyOk = hg_admin_catalog_persist_pretty_id($link, 'dim_chronicles', $id, $name);

        return [
            'ok' => true,
            'pretty_ok' => $prettyOk,
            'message' => $prettyOk ? 'Cronica actualizada.' : 'Cronica actualizada, pero no se pudo guardar pretty_id.',
        ];
    }
}

if (!function_exists('hg_chronicles_admin_set_image')) {
    function hg_chronicles_admin_set_image(mysqli $link, int $id, string $imageUrl): bool
    {
        $stmt = $link->prepare('UPDATE dim_chronicles SET image_url = ? WHERE id = ?');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('si', $imageUrl, $id);
        $ok = $stmt->execute();
        $stmt->close();
        return (bool)$ok;
    }
}

if (!function_exists('hg_chronicles_admin_fetch_rows')) {
    function hg_chronicles_admin_fetch_rows(mysqli $link, array $schema): array
    {
        $select = [
            'c.id',
            !empty($schema['pretty_id']) ? "COALESCE(c.pretty_id, '') AS pretty_id" : "'' AS pretty_id",
            'c.name',
            !empty($schema['sort_order']) ? 'COALESCE(c.sort_order, 0) AS sort_order' : '0 AS sort_order',
            "COALESCE(c.description, '') AS description",
            !empty($schema['image_url']) ? "COALESCE(c.image_url, '') AS image_url" : "'' AS image_url",
            !empty($schema['character_chronicle']) ? '(SELECT COUNT(*) FROM fact_characters fc WHERE fc.chronicle_id = c.id) AS characters_count' : '0 AS characters_count',
            !empty($schema['timeline_chronicle']) ? '(SELECT COUNT(*) FROM bridge_timeline_events_chronicles bt WHERE bt.chronicle_id = c.id) AS timeline_count' : '0 AS timeline_count',
            !empty($schema['season_chronicle']) ? '(SELECT COUNT(*) FROM dim_seasons s WHERE s.chronicle_id = c.id) AS seasons_count' : '0 AS seasons_count',
        ];

        $orderBy = !empty($schema['sort_order'])
            ? 'COALESCE(c.sort_order, 999999) ASC, c.name ASC, c.id ASC'
            : 'c.name ASC, c.id ASC';

        $result = $link->query('SELECT ' . implode(', ', $select) . ' FROM dim_chronicles c ORDER BY ' . $orderBy);
        if (!$result) {
            return [];
        }

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $result->close();

        return $rows;
    }
}
