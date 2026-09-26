<?php
include_once(__DIR__ . '/../../helpers/pretty.php');

if (!function_exists('hg_acc_ident')) {
    function hg_acc_ident(string $name): string {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
            throw new RuntimeException('Identificador SQL invalido: ' . $name);
        }
        return '`' . $name . '`';
    }
}

if (!function_exists('hg_acc_table_exists')) {
    function hg_acc_table_exists(mysqli $db, string $table): bool {
        return hg_table_exists($db, $table);
    }
}

if (!function_exists('hg_acc_fetch_table_columns')) {
    function hg_acc_fetch_table_columns(mysqli $db, string $table): array {
        $rows = [];
        if ($st = $db->prepare("
            SELECT COLUMN_NAME, COALESCE(EXTRA, '') AS extra_info
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
            ORDER BY ORDINAL_POSITION
        ")) {
            $st->bind_param('s', $table);
            $st->execute();
            $rs = $st->get_result();
            while ($r = $rs->fetch_assoc()) {
                $rows[] = [
                    'column_name' => (string)($r['COLUMN_NAME'] ?? ''),
                    'extra' => (string)($r['extra_info'] ?? ''),
                ];
            }
            $st->close();
        }
        return $rows;
    }
}

if (!function_exists('hg_acc_fetch_pairs')) {
    function hg_acc_fetch_pairs(mysqli $db, string $sql): array {
        $out = [];
        $q = @$db->query($sql);
        if (!$q) return $out;
        while ($r = $q->fetch_assoc()) {
            $id = (int)($r['id'] ?? 0);
            $name = (string)($r['name'] ?? '');
            if ($id > 0) $out[$id] = $name;
        }
        $q->close();
        return $out;
    }
}

if (!function_exists('hg_acc_pretty_exists')) {
    function hg_acc_pretty_exists(mysqli $db, string $pretty): bool {
        if ($pretty === '') return false;
        $found = 0;
        if ($st = $db->prepare("SELECT id FROM fact_characters WHERE pretty_id = ? LIMIT 1")) {
            $st->bind_param('s', $pretty);
            $st->execute();
            $st->store_result();
            $found = $st->num_rows;
            $st->close();
        }
        return ((int)$found > 0);
    }
}

if (!function_exists('hg_acc_build_pretty_for_clone')) {
    function hg_acc_build_pretty_for_clone(mysqli $db, string $sourcePretty, string $sourceName, int $newCharacterId): string {
        $base = trim($sourcePretty);
        if ($base === '') {
            $base = slugify_pretty_id($sourceName);
        }
        if ($base === '') {
            $base = 'character';
        }
        $suffix = '-copia-' . (int)$newCharacterId;
        $maxLen = 190;
        $room = $maxLen - strlen($suffix);
        if ($room < 1) $room = 1;
        if (strlen($base) > $room) {
            $base = substr($base, 0, $room);
            $base = rtrim($base, '-');
        }
        if ($base === '') $base = 'character';
        $candidate = $base . $suffix;
        if (!hg_acc_pretty_exists($db, $candidate)) {
            return $candidate;
        }
        $i = 2;
        while ($i < 10000) {
            $suffix2 = $suffix . '-' . $i;
            $room2 = $maxLen - strlen($suffix2);
            $base2 = $base;
            if (strlen($base2) > $room2) {
                $base2 = substr($base2, 0, max(1, $room2));
                $base2 = rtrim($base2, '-');
            }
            if ($base2 === '') $base2 = 'character';
            $candidate2 = $base2 . $suffix2;
            if (!hg_acc_pretty_exists($db, $candidate2)) {
                return $candidate2;
            }
            $i++;
        }
        return 'character-copia-' . (int)$newCharacterId;
    }
}

if (!function_exists('hg_acc_copy_bridges_for_character')) {
    function hg_acc_copy_bridges_for_character(mysqli $db, int $sourceCharacterId, int $newCharacterId): array {
        $excluded = [
            'bridge_characters_groups' => true,
            'bridge_characters_items' => true,
            'bridge_characters_relations' => true,
            'bridge_timeline_events_characters' => true,
        ];

        $tables = [];
        $sqlTables = "
            SELECT TABLE_NAME
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_TYPE = 'BASE TABLE'
              AND TABLE_NAME LIKE 'bridge\\_%' ESCAPE '\\\\'
            ORDER BY TABLE_NAME ASC
        ";
        if ($rs = $db->query($sqlTables)) {
            while ($r = $rs->fetch_assoc()) {
                $t = (string)($r['TABLE_NAME'] ?? '');
                if ($t !== '') $tables[] = $t;
            }
            $rs->close();
        }

        $tablesChecked = 0;
        $tablesCopied = 0;
        $rowsInserted = 0;
        $details = [];

        foreach ($tables as $table) {
            if (isset($excluded[$table])) continue;
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) continue;

            $columns = hg_acc_fetch_table_columns($db, $table);
            if (empty($columns)) continue;

            $hasCharacterId = false;
            $copyColumns = [];
            foreach ($columns as $meta) {
                $col = (string)($meta['column_name'] ?? '');
                $extra = strtolower((string)($meta['extra'] ?? ''));
                if ($col === 'character_id') {
                    $hasCharacterId = true;
                    continue;
                }
                if ($col === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $col)) continue;
                if (strpos($extra, 'auto_increment') !== false) continue;
                if (strpos($extra, 'virtual generated') !== false) continue;
                if (strpos($extra, 'stored generated') !== false) continue;
                $copyColumns[] = $col;
            }
            if (!$hasCharacterId) continue;

            $tablesChecked++;

            $insertCols = [hg_acc_ident('character_id')];
            $selectCols = [(string)$newCharacterId];
            foreach ($copyColumns as $col) {
                $ident = hg_acc_ident($col);
                $insertCols[] = $ident;
                $selectCols[] = $ident;
            }

            $sql = "INSERT IGNORE INTO " . hg_acc_ident($table)
                . " (" . implode(', ', $insertCols) . ") "
                . "SELECT " . implode(', ', $selectCols) . " FROM " . hg_acc_ident($table)
                . " WHERE " . hg_acc_ident('character_id') . " = ?";
            $st = $db->prepare($sql);
            if (!$st) {
                throw new RuntimeException("No se pudo preparar clonado de {$table}: " . $db->error);
            }
            $st->bind_param('i', $sourceCharacterId);
            $ok = $st->execute();
            $affected = (int)$st->affected_rows;
            $st->close();
            if (!$ok) {
                throw new RuntimeException("Error al clonar filas de {$table}");
            }

            if ($affected > 0) {
                $tablesCopied++;
                $rowsInserted += $affected;
                $details[] = ['table' => $table, 'rows' => $affected];
            }
        }

        return [
            'tables_checked' => $tablesChecked,
            'tables_copied' => $tablesCopied,
            'rows_inserted' => $rowsInserted,
            'details' => $details,
        ];
    }
}

if (!function_exists('hg_acc_clone_character')) {
    function hg_acc_clone_character(mysqli $db, int $sourceCharacterId, int $targetChronicleId, int $targetRealityId): array {
        if ($sourceCharacterId <= 0) throw new RuntimeException('Personaje origen invalido');
        if ($targetChronicleId <= 0) throw new RuntimeException('Cronica destino invalida');
        if ($targetRealityId <= 0) throw new RuntimeException('Realidad destino invalida');

        if (!hg_acc_table_exists($db, 'fact_characters')) {
            throw new RuntimeException('No existe fact_characters');
        }

        $source = null;
        if ($st = $db->prepare("SELECT id, name, COALESCE(pretty_id, '') AS pretty_id FROM fact_characters WHERE id = ? LIMIT 1")) {
            $st->bind_param('i', $sourceCharacterId);
            $st->execute();
            $rs = $st->get_result();
            $source = $rs ? $rs->fetch_assoc() : null;
            $st->close();
        }
        if (!$source) throw new RuntimeException('No existe el personaje origen');

        $okChronicle = false;
        if ($st = $db->prepare("SELECT id FROM dim_chronicles WHERE id = ? LIMIT 1")) {
            $st->bind_param('i', $targetChronicleId);
            $st->execute();
            $st->store_result();
            $okChronicle = ($st->num_rows > 0);
            $st->close();
        }
        if (!$okChronicle) throw new RuntimeException('La cronica destino no existe');

        $okReality = false;
        if ($st = $db->prepare("SELECT id FROM dim_realities WHERE id = ? LIMIT 1")) {
            $st->bind_param('i', $targetRealityId);
            $st->execute();
            $st->store_result();
            $okReality = ($st->num_rows > 0);
            $st->close();
        }
        if (!$okReality) throw new RuntimeException('La realidad destino no existe');

        $columns = hg_acc_fetch_table_columns($db, 'fact_characters');
        if (empty($columns)) throw new RuntimeException('No se pudieron leer columnas de fact_characters');

        $insertCols = [];
        $selectExprs = [];
        $hasPrettyColumn = false;

        foreach ($columns as $meta) {
            $col = (string)($meta['column_name'] ?? '');
            $extra = strtolower((string)($meta['extra'] ?? ''));
            if ($col === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $col)) continue;
            if (strpos($extra, 'auto_increment') !== false) continue;

            $insertCols[] = hg_acc_ident($col);
            if ($col === 'chronicle_id') {
                $selectExprs[] = (string)$targetChronicleId;
            } elseif ($col === 'reality_id') {
                $selectExprs[] = (string)$targetRealityId;
            } elseif ($col === 'pretty_id') {
                $hasPrettyColumn = true;
                $selectExprs[] = "NULL";
            } else {
                $selectExprs[] = hg_acc_ident($col);
            }
        }

        if (empty($insertCols)) {
            throw new RuntimeException('No hay columnas insertables en fact_characters');
        }

        $db->begin_transaction();
        try {
            $sqlInsert = "INSERT INTO `fact_characters` (" . implode(', ', $insertCols) . ") "
                . "SELECT " . implode(', ', $selectExprs) . " FROM `fact_characters` WHERE `id` = " . (int)$sourceCharacterId . " LIMIT 1";
            $okInsert = $db->query($sqlInsert);
            if (!$okInsert) {
                throw new RuntimeException('Error al clonar personaje: ' . $db->error);
            }

            $newCharacterId = (int)$db->insert_id;
            if ($newCharacterId <= 0) {
                throw new RuntimeException('No se pudo obtener el ID del personaje clonado');
            }

            if ($hasPrettyColumn) {
                $newPretty = hg_acc_build_pretty_for_clone(
                    $db,
                    (string)($source['pretty_id'] ?? ''),
                    (string)($source['name'] ?? ''),
                    $newCharacterId
                );
                if ($stUp = $db->prepare("UPDATE fact_characters SET pretty_id = ? WHERE id = ? LIMIT 1")) {
                    $stUp->bind_param('si', $newPretty, $newCharacterId);
                    if (!$stUp->execute()) {
                        $stUp->close();
                        throw new RuntimeException('Error al fijar pretty_id del clon');
                    }
                    $stUp->close();
                } else {
                    throw new RuntimeException('No se pudo preparar update de pretty_id');
                }
            }

            $bridgeStats = hg_acc_copy_bridges_for_character($db, $sourceCharacterId, $newCharacterId);

            $db->commit();

            return [
                'new_character_id' => $newCharacterId,
                'source_character_id' => $sourceCharacterId,
                'bridge' => $bridgeStats,
                'source_name' => (string)($source['name'] ?? ''),
            ];
        } catch (Throwable $e) {
            $db->rollback();
            throw $e;
        }
    }
}



if (!function_exists('hg_acc_load_listing')) {
    function hg_acc_load_listing(mysqli $link, string $q, int $page, int $perPage): array {
        $optsChronicles = hg_acc_fetch_pairs($link, "SELECT id, name FROM dim_chronicles ORDER BY sort_order ASC, name ASC");
        $optsRealities = hg_acc_fetch_pairs($link, "SELECT id, name FROM dim_realities ORDER BY is_active DESC, name ASC");

        $where = "WHERE 1=1";
        $types = '';
        $params = [];
        if ($q !== '') {
            $where .= " AND (
                p.name LIKE ?
                OR COALESCE(p.pretty_id, '') LIKE ?
                OR COALESCE(c.name, '') LIKE ?
                OR COALESCE(r.name, '') LIKE ?
                OR CAST(p.id AS CHAR) LIKE ?
            )";
            $needle = '%' . $q . '%';
            $types = 'sssss';
            $params = [$needle, $needle, $needle, $needle, $needle];
        }

        $total = 0;
        $sqlCount = "
            SELECT COUNT(*) AS c
            FROM fact_characters p
            LEFT JOIN dim_chronicles c ON c.id = p.chronicle_id
            LEFT JOIN dim_realities r ON r.id = p.reality_id
            {$where}
        ";
        $stCnt = $link->prepare($sqlCount);
        if ($stCnt) {
            if ($types !== '') $stCnt->bind_param($types, ...$params);
            $stCnt->execute();
            $rsCnt = $stCnt->get_result();
            if ($rsCnt && ($rowCnt = $rsCnt->fetch_assoc())) {
                $total = (int)($rowCnt['c'] ?? 0);
            }
            $stCnt->close();
        }

        $pages = max(1, (int)ceil(($total > 0 ? $total : 1) / $perPage));
        $page = min(max(1,$page), $pages);
        $offset = ($page - 1) * $perPage;

        $rows = [];
        $rowMap = [];
        $sqlList = "
            SELECT
                p.id,
                p.name,
                p.chronicle_id,
                p.reality_id,
                COALESCE(c.name, '') AS chronicle_name,
                COALESCE(r.name, '') AS reality_name
            FROM fact_characters p
            LEFT JOIN dim_chronicles c ON c.id = p.chronicle_id
            LEFT JOIN dim_realities r ON r.id = p.reality_id
            {$where}
            ORDER BY p.id DESC
            LIMIT ?, ?
        ";
        $stList = $link->prepare($sqlList);
        if ($stList) {
            $types2 = $types . 'ii';
            $params2 = $params;
            $params2[] = $offset;
            $params2[] = $perPage;
            $stList->bind_param($types2, ...$params2);
            $stList->execute();
            $rsList = $stList->get_result();
            while ($row = $rsList->fetch_assoc()) {
                $row['id'] = (int)($row['id'] ?? 0);
                $row['chronicle_id'] = (int)($row['chronicle_id'] ?? 0);
                $row['reality_id'] = (int)($row['reality_id'] ?? 0);
                $rows[] = $row;
                $rowMap[$row['id']] = $row;
            }
            $stList->close();
        }

        return compact('optsChronicles','optsRealities','total','pages','page','offset','rows','rowMap');
    }
}
