<?php
// Administration-only bibliography queries and writes. No schema changes.
if (!function_exists('hg_bib_admin_sources')) {
    function hg_bib_admin_sources(mysqli $db): array {
        $sql = "SELECT c.TABLE_NAME AS table_name,
                    EXISTS (
                      SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k
                      WHERE k.TABLE_SCHEMA = c.TABLE_SCHEMA
                        AND k.TABLE_NAME = c.TABLE_NAME
                        AND k.COLUMN_NAME = 'bibliography_id'
                        AND k.REFERENCED_TABLE_NAME = 'dim_bibliographies'
                    ) AS has_fk
                FROM information_schema.COLUMNS c
                JOIN information_schema.TABLES t
                  ON t.TABLE_SCHEMA = c.TABLE_SCHEMA AND t.TABLE_NAME = c.TABLE_NAME
                WHERE c.TABLE_SCHEMA = DATABASE()
                  AND c.COLUMN_NAME = 'bibliography_id'
                  AND c.TABLE_NAME <> 'dim_bibliographies'
                  AND t.TABLE_TYPE = 'BASE TABLE'
                ORDER BY c.TABLE_NAME";
        $rs = $db->query($sql);
        if (!$rs) throw new RuntimeException('No se pudieron inspeccionar las dependencias.');
        $tables = [];
        while ($r = $rs->fetch_assoc()) {
            $table = (string)$r['table_name'];
            if (!preg_match('/^[A-Za-z0-9_]+$/D', $table)) throw new RuntimeException('Nombre de tabla no seguro.');
            $tables[] = ['table' => $table, 'fk' => (int)$r['has_fk'] === 1,
                'archive' => strncmp($table, 'backup_', 7) === 0];
        }
        $rs->free();
        // Fail closed if any of the 18 FK consumers verified by the Phase-6A
        // production inventory are missing. Historical backup tables are
        // reported whenever present, rather than silently discarded.
        $fkCount = count(array_filter($tables, static function ($entry) {
            return $entry['fk'];
        }));
        if ($fkCount < 18) {
            throw new RuntimeException('Inventario de claves bibliograficas incompleto; borrado deshabilitado.');
        }
        return $tables;
    }
}

if (!function_exists('hg_bib_admin_snapshot')) {
    function hg_bib_admin_snapshot(mysqli $db): array {
        $rs = $db->query("SELECT id, pretty_id, sort_order, name, year, publisher, description
                          FROM dim_bibliographies ORDER BY sort_order, name, id");
        if (!$rs) throw new RuntimeException('No se pudo cargar la bibliografia.');
        $rows = [];
        while ($r = $rs->fetch_assoc()) {
            $r['id'] = (int)$r['id'];
            $r['sort_order'] = (int)$r['sort_order'];
            $r['year'] = (int)$r['year'];
            $r['active_count'] = 0;
            $r['archive_count'] = 0;
            $rows[(int)$r['id']] = $r;
        }
        $rs->free();
        $sources = hg_bib_admin_sources($db);
        $details = [];
        foreach ($sources as $src) {
            $table = $src['table']; // validated using schema metadata
            $uses = $db->query("SELECT bibliography_id, COUNT(*) AS n FROM ".$table."
                                WHERE bibliography_id IS NOT NULL GROUP BY bibliography_id");
            if (!$uses) throw new RuntimeException('No se pudieron contar todas las referencias.');
            while ($use = $uses->fetch_assoc()) {
                $id = (int)$use['bibliography_id'];
                $n = (int)$use['n'];
                if (!isset($rows[$id])) throw new RuntimeException('Referencia huerfana en '.$table);
                $key = $src['archive'] ? 'archive_count' : 'active_count';
                $rows[$id][$key] += $n;
                if ($n > 0) {
                    $details[$id][] = ['table' => $table, 'count' => $n,
                        'archive' => $src['archive'], 'fk' => $src['fk']];
                }
            }
            $uses->free();
        }
        return ['rows' => array_values($rows), 'details' => $details,
                'sources_count' => count($sources),
                'fk_count' => count(array_filter($sources, static function ($s) { return $s['fk']; }))];
    }
}

if (!function_exists('hg_bib_admin_references')) {
    function hg_bib_admin_references(mysqli $db, int $id, string $table): array {
        $sources = hg_bib_admin_sources($db);
        $source = null;
        foreach ($sources as $s) if ($s['table'] === $table) $source = $s;
        if ($source === null || $id <= 0) throw new InvalidArgumentException('Tabla o bibliografia no valida.');
        // Display only index-like identities, not complete rows or private content.
        $st = $db->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS
                           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
                             AND COLUMN_KEY = 'PRI' ORDER BY ORDINAL_POSITION");
        if (!$st) throw new RuntimeException('No se pudieron consultar las claves.');
        $st->bind_param('s', $table);
        $st->execute();
        $res = $st->get_result();
        $pk = [];
        while ($r = $res->fetch_assoc()) {
            $key = (string)$r['COLUMN_NAME'];
            if (!preg_match('/^[A-Za-z0-9_]+$/D', $key)) throw new RuntimeException('Clave invalida.');
            $pk[] = $key;
        }
        $st->close();
        if (!$pk) {
            return ['table' => $table, 'columns' => [], 'rows' => [],
                    'notice' => 'Sin clave primaria individual inspeccionable; consulta el desglose de usos.'];
        }
        $select = implode(', ', array_map(static function ($s) { return $s; }, $pk));
        $st = $db->prepare("SELECT ".$select." FROM ".$table." WHERE bibliography_id = ? LIMIT 30");
        if (!$st) throw new RuntimeException('No se pudieron inspeccionar los registros.');
        $st->bind_param('i', $id);
        $st->execute();
        $rs = $st->get_result();
        $items = [];
        while ($r = $rs->fetch_assoc()) $items[] = $r;
        $st->close();
        return ['table' => $table, 'columns' => $pk, 'rows' => $items,
                'notice' => 'Primeros 30 identificadores. No se ha modificado ningun registro.'];
    }
}

if (!function_exists('hg_bib_admin_validate')) {
    function hg_bib_admin_validate(array $input): array {
        $name = trim((string)($input['name'] ?? ''));
        $publisher = trim((string)($input['publisher'] ?? ''));
        $description = (string)($input['description'] ?? '');
        $slug = trim((string)($input['pretty_id'] ?? ''));
        $sort = filter_var($input['sort_order'] ?? null, FILTER_VALIDATE_INT);
        $year = filter_var($input['year'] ?? null, FILTER_VALIDATE_INT);
        $strlen = static function ($s) { return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s); };
        if ($name === '' || $strlen($name) > 180) throw new InvalidArgumentException('Nombre obligatorio (maximo 180 caracteres).');
        if ($strlen($publisher) > 100) throw new InvalidArgumentException('Editorial demasiado larga.');
        if ($strlen($description) > 100000) throw new InvalidArgumentException('Descripcion demasiado larga.');
        if ($sort === false || $year === false || $year < 0 || $year > 9999) {
            throw new InvalidArgumentException('Orden y año deben ser numeros validos.');
        }
        if ($slug !== '' && (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) || strlen($slug) > 190)) {
            throw new InvalidArgumentException('El slug solo admite minusculas, numeros y guiones (maximo 190).');
        }
        return [$name, $publisher, $description, $slug, (int)$sort, (int)$year];
    }
}

if (!function_exists('hg_bib_admin_save')) {
    function hg_bib_admin_save(mysqli $db, array $input, int $id): int {
        [$name, $publisher, $description, $slug, $sort, $year] = hg_bib_admin_validate($input);
        if ($slug === '') {
            // Existing editions can deliberately retain a NULL pretty_id.
            if ($id === 0) $slug = slugify_pretty_id($name);
        }
        if ($id < 0 || ($id === 0 && $slug === '')) throw new InvalidArgumentException('Identificador invalido.');
        if ($id > 0) {
            $st = $db->prepare("SELECT id FROM dim_bibliographies WHERE id = ?");
            $st->bind_param('i', $id);
            $st->execute();
            $exists = $st->get_result()->num_rows > 0;
            $st->close();
            if (!$exists) throw new InvalidArgumentException('Bibliografia no encontrada.');
        }
        if ($slug !== '') {
            $st = $db->prepare("SELECT id FROM dim_bibliographies WHERE pretty_id = ? AND id <> ? LIMIT 1");
            $st->bind_param('si', $slug, $id);
            $st->execute();
            $taken = $st->get_result()->num_rows > 0;
            $st->close();
            if ($taken) throw new InvalidArgumentException('El slug ya existe. Introduce otro.');
        }
        $pretty = $slug === '' ? null : $slug;
        if ($id === 0) {
            $st = $db->prepare("INSERT INTO dim_bibliographies
                (pretty_id, sort_order, name, year, publisher, description)
                VALUES (?, ?, ?, ?, ?, ?)");
            if (!$st) throw new RuntimeException('No se pudo preparar el alta.');
            $st->bind_param('sisiss', $pretty, $sort, $name, $year, $publisher, $description);
        } else {
            $st = $db->prepare("UPDATE dim_bibliographies
                SET pretty_id = ?, sort_order = ?, name = ?, year = ?, publisher = ?,
                    description = ?, updated_at = NOW() WHERE id = ?");
            if (!$st) throw new RuntimeException('No se pudo preparar la edicion.');
            $st->bind_param('sisissi', $pretty, $sort, $name, $year, $publisher, $description, $id);
        }
        $ok = $st->execute();
        $newId = $id === 0 ? (int)$db->insert_id : $id;
        $st->close();
        if (!$ok) throw new RuntimeException('No se pudo guardar la bibliografia.');
        return $newId;
    }
}

if (!function_exists('hg_bib_admin_delete')) {
    function hg_bib_admin_delete(mysqli $db, int $id): void {
        if ($id <= 0) throw new InvalidArgumentException('Identificador invalido.');
        $db->begin_transaction();
        try {
            $st = $db->prepare("SELECT id FROM dim_bibliographies WHERE id = ? FOR UPDATE");
            if (!$st) throw new RuntimeException('No se pudo bloquear la bibliografia.');
            $st->bind_param('i', $id);
            $st->execute();
            $found = $st->get_result()->num_rows > 0;
            $st->close();
            if (!$found) throw new InvalidArgumentException('Bibliografia no encontrada.');
            // ALL bibliography_id columns, not just restrictive FKs. Backups
            // are blocked as well so archived attributions stay traceable.
            $sources = hg_bib_admin_sources($db);
            foreach ($sources as $source) {
                $table = $source['table'];
                $st = $db->prepare("SELECT 1 FROM ".$table." WHERE bibliography_id = ? LIMIT 1");
                if (!$st) throw new RuntimeException('No se pudo verificar el borrado.');
                $st->bind_param('i', $id);
                $st->execute();
                $used = $st->get_result()->num_rows > 0;
                $st->close();
                if ($used) throw new InvalidArgumentException('Borrado bloqueado: referencias en '.$table.'. Examina el desglose.');
            }
            $st = $db->prepare("DELETE FROM dim_bibliographies WHERE id = ?");
            if (!$st) throw new RuntimeException('No se pudo preparar el borrado.');
            $st->bind_param('i', $id);
            if (!$st->execute()) throw new RuntimeException('No se pudo eliminar la bibliografia.');
            $st->close();
            $db->commit();
        } catch (Throwable $e) {
            $db->rollback();
            throw $e;
        }
    }
}
