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

// Verified detail paths only. Not all bibliography consumers have a public page.
if (!function_exists('hg_bib_admin_public_routes')) {
    function hg_bib_admin_public_routes(): array {
        return [
            'fact_gifts' => '/powers/gift/',
            'fact_rites' => '/powers/rite/',
            'dim_totems' => '/powers/totem/',
            'fact_discipline_powers' => '/powers/discipline/',
            'fact_docs' => '/documents/',
            'fact_items' => '/inventory/items/',
            'dim_systems' => '/systems/',
            'dim_breeds' => '/systems/breeds/',
            'dim_auspices' => '/systems/auspices/',
            'dim_tribes' => '/systems/tribes/',
            'fact_misc_systems' => '/systems/misc/',
            'dim_forms' => '/systems/form/',
            'dim_traits' => '/rules/traits/',
            'dim_merits_flaws' => '/rules/merits-flaws/',
            'fact_actions' => '/rules/actions/',
            'fact_combat_maneuvers' => '/rules/maneuvers/',
            'fact_timeline_events' => '/timeline/event/',
            'fact_map_pois' => '/maps/poi/',
            'bridge_gifts_availability' => '/powers/gift/',
        ];
    }
}


// Resolve labels for the publication bridge without N+1 lookups. The bridge
// stores content identities, not copies of source titles or public slugs.
if (!function_exists('hg_bib_admin_enrich_publication_members')) {
    function hg_bib_admin_enrich_publication_members(mysqli $db, array &$items): void {
        $catalog = [
            'fact_gifts' => 'name',
            'dim_systems' => 'name',
            'dim_breeds' => 'name',
            'dim_auspices' => 'name',
            'dim_tribes' => 'name',
            'dim_forms' => 'form',
            'dim_totems' => 'name',
            'dim_traits' => 'name',
            'dim_merits_flaws' => 'name',
        ];
        $grouped = [];
        foreach ($items as $entry) {
            $type = (string)($entry['entity_table'] ?? '');
            $entity = (int)($entry['entity_id'] ?? 0);
            if (isset($catalog[$type]) && $entity > 0) $grouped[$type][$entity] = $entity;
        }
        $resolved = [];
        foreach ($grouped as $type => $ids) {
            $field = $catalog[$type]; // static trusted SQL identifier
            $sql = 'SELECT id, pretty_id, ' . $field . ' AS title FROM ' . $type .
                   ' WHERE id IN (' . implode(',', array_map('intval', $ids)) . ')';
            $rs = $db->query($sql);
            if (!$rs) continue;
            while ($row = $rs->fetch_assoc()) {
                $resolved[$type][(int)$row['id']] = $row;
            }
            $rs->free();
        }
        $routes = hg_bib_admin_public_routes();
        foreach ($items as &$entry) {
            $type = (string)($entry['entity_table'] ?? '');
            $entity = (int)($entry['entity_id'] ?? 0);
            $source = $resolved[$type][$entity] ?? null;
            if (!$source) continue;
            $title = trim((string)($source['title'] ?? ''));
            $entry['_display_name'] = $title !== '' ? $title : '(sin nombre disponible)';
            $slug = trim((string)($source['pretty_id'] ?? ''));
            if ($slug === '') $slug = (string)$entity;
            $base = $routes[$type] ?? '';
            if ($base !== '') {
                $entry['_url'] = $base . rawurlencode($slug) .
                    '?edition=heavens-gate-camazotz';
            }
        }
        unset($entry);
    }
}

if (!function_exists('hg_bib_admin_references')) {
    function hg_bib_admin_references(mysqli $db, int $id, string $table, int $page = 1): array {
        $source = null;
        foreach (hg_bib_admin_sources($db) as $s) {
            if ($s['table'] === $table) {
                $source = $s;
                break;
            }
        }
        if ($source === null || $id <= 0 || $page < 1 || $page > 100000) {
            throw new InvalidArgumentException('Tabla, bibliografia o pagina no valida.');
        }

        // Read the actual schema: dimensions do not all use the same title column.
        $st = $db->prepare("SELECT COLUMN_NAME, COLUMN_KEY FROM information_schema.COLUMNS
                           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
                           ORDER BY ORDINAL_POSITION");
        if (!$st) throw new RuntimeException('No se pudo inspeccionar la tabla.');
        $st->bind_param('s', $table);
        $st->execute();
        $columnResult = $st->get_result();
        $available = [];
        $pk = [];
        while ($col = $columnResult->fetch_assoc()) {
            $field = (string)$col['COLUMN_NAME'];
            if (!preg_match('/^[A-Za-z0-9_]+$/D', $field)) {
                $st->close();
                throw new RuntimeException('Nombre de columna no seguro.');
            }
            $available[$field] = true;
            if ((string)$col['COLUMN_KEY'] === 'PRI') $pk[] = $field;
        }
        $st->close();

        $titleColumn = null;
        foreach (['name', 'title', 'form', 'label', 'event_name'] as $candidate) {
            if (isset($available[$candidate])) {
                $titleColumn = $candidate;
                break;
            }
        }
        $isGiftAvailability = $table === 'bridge_gifts_availability' && isset($available['gift_id']);
        $isPublicationBridge = $table === 'bridge_bibliography_publications'
            && isset($available['entity_table'], $available['entity_id'], $available['context_system_id']);
        $routes = hg_bib_admin_public_routes();
        $route = empty($source['archive']) ? ($routes[$table] ?? '') : '';

        $keyColumns = $pk;
        if ($isGiftAvailability && !in_array('gift_id', $keyColumns, true)) {
            $keyColumns[] = 'gift_id';
        }
        if ($isPublicationBridge) {
            foreach (['entity_table', 'entity_id', 'context_system_id'] as $field) {
                if (!in_array($field, $keyColumns, true)) $keyColumns[] = $field;
            }
        }
        $sqlColumns = [];
        foreach ($keyColumns as $key) $sqlColumns[] = 't.'.$key;
        if ($titleColumn !== null && !in_array($titleColumn, $keyColumns, true)) {
            $sqlColumns[] = 't.'.$titleColumn;
        }
        if (isset($available['pretty_id']) && !in_array('pretty_id', $keyColumns, true)) {
            $sqlColumns[] = 't.pretty_id';
        }
        if ($isGiftAvailability) {
            $sqlColumns[] = 'g.name AS _linked_name';
            $sqlColumns[] = 'g.pretty_id AS _linked_pretty';
        }
        if (!$sqlColumns) {
            return ['table' => $table, 'columns' => [], 'rows' => [],
                'page' => $page, 'page_size' => 50, 'total' => 0,
                'notice' => 'Esta tabla no tiene columnas identificativas disponibles.'];
        }

        $countSt = $db->prepare('SELECT COUNT(*) AS n FROM '.$table.' WHERE bibliography_id = ?');
        if (!$countSt) throw new RuntimeException('No se pudo contar el material.');
        $countSt->bind_param('i', $id);
        $countSt->execute();
        $countResult = $countSt->get_result();
        $total = (int)($countResult->fetch_assoc()['n'] ?? 0);
        $countSt->close();

        $size = 50;
        $offset = ($page - 1) * $size;
        $orderColumns = $pk;
        if (!$orderColumns && $isGiftAvailability) {
            foreach (['gift_id', 'scope_type', 'scope_id', 'system_id'] as $candidate) {
                if (isset($available[$candidate])) $orderColumns[] = $candidate;
            }
        }
        if (!$orderColumns && $titleColumn !== null) $orderColumns[] = $titleColumn;
        if (!$orderColumns) $orderColumns = $keyColumns;
        $order = implode(', ', array_map(static function ($column) {
            return 't.'.$column;
        }, $orderColumns));
        $sql = 'SELECT '.implode(', ', $sqlColumns).' FROM '.$table.' t';
        if ($isGiftAvailability) $sql .= ' LEFT JOIN fact_gifts g ON g.id = t.gift_id';
        $sql .= ' WHERE t.bibliography_id = ? ORDER BY '.$order.' LIMIT ? OFFSET ?';
        $st = $db->prepare($sql);
        if (!$st) throw new RuntimeException('No se pudieron cargar las referencias.');
        $st->bind_param('iii', $id, $size, $offset);
        $st->execute();
        $rs = $st->get_result();
        $items = [];
        while ($record = $rs->fetch_assoc()) {
            $name = $isGiftAvailability
                ? trim((string)($record['_linked_name'] ?? ''))
                : trim((string)($titleColumn === null ? '' : ($record[$titleColumn] ?? '')));
            $publicId = '';
            if ($isGiftAvailability) {
                $publicId = trim((string)($record['_linked_pretty'] ?? ''));
                if ($publicId === '') $publicId = (string)($record['gift_id'] ?? '');
            } elseif (count($pk) === 1 && $pk[0] === 'id') {
                $publicId = trim((string)($record['pretty_id'] ?? ''));
                if ($publicId === '') $publicId = (string)($record['id'] ?? '');
            }
            $record['_display_name'] = $name !== '' ? $name : '(sin nombre disponible)';
            $record['_url'] = ($route !== '' && $publicId !== '') ? $route.rawurlencode($publicId) : '';
            unset($record['_linked_name'], $record['_linked_pretty']);
            $items[] = $record;
        }
        $st->close();
        if ($isPublicationBridge) {
            hg_bib_admin_enrich_publication_members($db, $items);
        }

        return [
            'table' => $table, 'columns' => $keyColumns, 'rows' => $items,
            'page' => $page, 'page_size' => $size, 'total' => $total,
            'notice' => 'Nombres y enlaces cuando la web dispone de una pagina publica de ese material.',
        ];
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
