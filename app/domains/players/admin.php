<?php
// Admin data ownership for Players.
// Controllers retain request validation, uploads, orchestration and rendering.

if (!function_exists('hg_players_admin_player_exists')) {
    function hg_players_admin_player_exists(
        mysqli $link,
        string $name,
        string $surname,
        int $excludeId,
        bool $hasSurname
    ): bool {
        $name = trim($name);
        $surname = trim($surname);
        if ($name === '') return false;

        $sql = $hasSurname
            ? "SELECT id FROM dim_players WHERE TRIM(COALESCE(name, '')) = ? AND TRIM(COALESCE(surname, '')) = ? AND id <> ? LIMIT 1"
            : "SELECT id FROM dim_players WHERE TRIM(COALESCE(name, '')) = ? AND id <> ? LIMIT 1";

        try {
            $st = $link->prepare($sql);
            if (!$st) return false;
            $foundId = 0;
            if ($hasSurname) {
                $st->bind_param('ssi', $name, $surname, $excludeId);
            } else {
                $st->bind_param('si', $name, $excludeId);
            }
            $ok = false;
            if ($st->execute()) {
                $st->bind_result($foundId);
                $ok = $st->fetch();
            }
            $st->close();
            return (bool)$ok && $foundId > 0;
        } catch (mysqli_sql_exception $e) {
            return false;
        }
    }
}

if (!function_exists('hg_players_admin_fetch_current')) {
    function hg_players_admin_fetch_current(
        mysqli $link,
        int $id,
        bool $hasPicture,
        bool $hasPrettyId
    ): array {
        $out = ['exists' => false, 'picture' => '', 'pretty_id' => ''];
        if ($id <= 0 || (!$hasPicture && !$hasPrettyId)) return $out;

        $select = [];
        if ($hasPicture) $select[] = 'picture';
        if ($hasPrettyId) $select[] = 'pretty_id';
        if (!$select) return $out;

        try {
            $st = $link->prepare('SELECT ' . implode(', ', $select) . ' FROM dim_players WHERE id = ? LIMIT 1');
            if (!$st) return $out;
            $st->bind_param('i', $id);
            $st->execute();
            $rs = $st->get_result();
            if ($rs && ($row = $rs->fetch_assoc())) {
                $out['exists'] = true;
                $out['picture'] = (string)($row['picture'] ?? '');
                $out['pretty_id'] = (string)($row['pretty_id'] ?? '');
            }
            $st->close();
        } catch (mysqli_sql_exception $e) {
            return $out;
        }
        return $out;
    }
}

if (!function_exists('hg_players_admin_delete')) {
    function hg_players_admin_delete(mysqli $link, int $id): array
    {
        if ($id <= 0) return ['ok' => false, 'error' => 'invalid_id'];
        try {
            $st = $link->prepare('DELETE FROM dim_players WHERE id = ?');
            if (!$st) return ['ok' => false, 'error' => $link->error];
            $st->bind_param('i', $id);
            $ok = $st->execute();
            $error = $st->error;
            $st->close();
            return ['ok' => $ok, 'error' => $error];
        } catch (mysqli_sql_exception $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}

if (!function_exists('hg_players_admin_create')) {
    function hg_players_admin_create(mysqli $link, array $data, array $schema): array
    {
        $cols = ['name'];
        $vals = [(string)$data['name']];
        $types = 's';

        if (!empty($schema['surname'])) {
            $cols[] = 'surname';
            $vals[] = (string)($data['surname'] ?? '');
            $types .= 's';
        }
        if (!empty($schema['show_in_catalog'])) {
            $cols[] = 'show_in_catalog';
            $vals[] = (int)($data['show_in_catalog'] ?? 0);
            $types .= 'i';
        }
        if (!empty($schema['picture'])) {
            $cols[] = 'picture';
            $vals[] = (string)($data['picture'] ?? '');
            $types .= 's';
        }
        if (!empty($schema['description'])) {
            $cols[] = 'description';
            $vals[] = (string)($data['description'] ?? '');
            $types .= 's';
        }
        if (!empty($schema['created_at'])) $cols[] = 'created_at';
        if (!empty($schema['updated_at'])) $cols[] = 'updated_at';

        $ph = [];
        foreach ($cols as $col) {
            $ph[] = ($col === 'created_at' || $col === 'updated_at') ? 'NOW()' : '?';
        }

        $sql = "INSERT INTO dim_players (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', $ph) . ")";
        try {
            $st = $link->prepare($sql);
            if (!$st) return ['ok' => false, 'error' => $link->error, 'id' => 0];
            $st->bind_param($types, ...$vals);
            $ok = $st->execute();
            $error = $st->error;
            $id = $ok ? (int)$link->insert_id : 0;
            $st->close();
            return ['ok' => $ok, 'error' => $error, 'id' => $id];
        } catch (mysqli_sql_exception $e) {
            return ['ok' => false, 'error' => $e->getMessage(), 'id' => 0];
        }
    }
}

if (!function_exists('hg_players_admin_update')) {
    function hg_players_admin_update(mysqli $link, int $id, array $data, array $schema): array
    {
        if ($id <= 0) return ['ok' => false, 'error' => 'invalid_id'];

        $sets = ['`name` = ?'];
        $vals = [(string)$data['name']];
        $types = 's';

        if (!empty($schema['surname'])) {
            $sets[] = '`surname` = ?';
            $vals[] = (string)($data['surname'] ?? '');
            $types .= 's';
        }
        if (!empty($schema['show_in_catalog'])) {
            $sets[] = '`show_in_catalog` = ?';
            $vals[] = (int)($data['show_in_catalog'] ?? 0);
            $types .= 'i';
        }
        if (!empty($schema['picture'])) {
            $sets[] = '`picture` = ?';
            $vals[] = (string)($data['picture'] ?? '');
            $types .= 's';
        }
        if (!empty($schema['description'])) {
            $sets[] = '`description` = ?';
            $vals[] = (string)($data['description'] ?? '');
            $types .= 's';
        }
        if (!empty($schema['updated_at'])) $sets[] = '`updated_at` = NOW()';

        $vals[] = $id;
        $types .= 'i';
        $sql = 'UPDATE dim_players SET ' . implode(', ', $sets) . ' WHERE id = ?';

        try {
            $st = $link->prepare($sql);
            if (!$st) return ['ok' => false, 'error' => $link->error];
            $st->bind_param($types, ...$vals);
            $ok = $st->execute();
            $error = $st->error;
            $st->close();
            return ['ok' => $ok, 'error' => $error];
        } catch (mysqli_sql_exception $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}

if (!function_exists('hg_players_admin_set_picture')) {
    function hg_players_admin_set_picture(mysqli $link, int $id, string $picture): array
    {
        if ($id <= 0) return ['ok' => false, 'error' => 'invalid_id'];
        try {
            $st = $link->prepare('UPDATE dim_players SET picture = ? WHERE id = ?');
            if (!$st) return ['ok' => false, 'error' => $link->error];
            $st->bind_param('si', $picture, $id);
            $ok = $st->execute();
            $error = $st->error;
            $st->close();
            return ['ok' => $ok, 'error' => $error];
        } catch (mysqli_sql_exception $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}

if (!function_exists('hg_players_admin_fetch_rows')) {
    function hg_players_admin_fetch_rows(mysqli $link, array $schema): array
    {
        $hasPrettyId = !empty($schema['pretty_id']);
        $hasSurname = !empty($schema['surname']);
        $hasShowInCatalog = !empty($schema['show_in_catalog']);
        $hasPicture = !empty($schema['picture']);
        $hasDescription = !empty($schema['description']);
        $hasPlayerId = !empty($schema['character_player_id']);

        $select = [
            'p.id',
            $hasPrettyId ? "COALESCE(p.pretty_id, '') AS pretty_id" : "'' AS pretty_id",
            "COALESCE(p.name, '') AS name",
            $hasSurname ? "COALESCE(p.surname, '') AS surname" : "'' AS surname",
            $hasShowInCatalog ? 'COALESCE(p.show_in_catalog, 0) AS show_in_catalog' : '0 AS show_in_catalog',
            $hasPicture ? "COALESCE(p.picture, '') AS picture" : "'' AS picture",
            $hasDescription ? "COALESCE(p.description, '') AS description" : "'' AS description",
            $hasPlayerId ? '(SELECT COUNT(*) FROM fact_characters fc WHERE fc.player_id = p.id) AS characters_count' : '0 AS characters_count',
        ];
        $orderBy = 'p.name ASC' . ($hasSurname ? ', p.surname ASC' : '') . ', p.id ASC';

        $rows = [];
        try {
            $rs = $link->query('SELECT ' . implode(', ', $select) . ' FROM dim_players p ORDER BY ' . $orderBy);
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
