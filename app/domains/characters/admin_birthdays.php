<?php
include_once(__DIR__ . '/../../helpers/character_birth_events.php');

if (!function_exists('hg_abq_parse_birth_to_ymd')) {
    function hg_abq_parse_birth_to_ymd(string $raw): ?string {
        $raw = trim($raw);
        if ($raw === '' || strcasecmp($raw, 'desconocido') === 0 || $raw === '0000-00-00') return null;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) return $raw;
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $raw, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }
        return null;
    }
}

if (!function_exists('hg_abq_find_birth_type_id')) {
    function hg_abq_find_birth_type_id(mysqli $db): int {
        $id = 0;
        if ($st = $db->prepare("SELECT id FROM dim_timeline_events_types WHERE pretty_id = 'nacimiento' LIMIT 1")) {
            $st->execute();
            $st->bind_result($id);
            $st->fetch();
            $st->close();
        }
        return (int)$id;
    }
}

if (!function_exists('hg_abq_birthtext_expr')) {
    function hg_abq_birthtext_expr(mysqli $db): string {
        return hg_cbe_birthtext_expr($db, 'p');
    }
}

if (!function_exists('hg_abq_fetch_rows')) {
    function hg_abq_fetch_rows(mysqli $db, string $q, string $status, int $limit): array {
        $rows = [];
        $q = trim($q);
        if ($limit <= 0) $limit = 300;
        if ($limit > 1000) $limit = 1000;

        $statusWhere = '';
        if ($status === 'pending') {
            $statusWhere = "AND (be.id IS NULL OR be.event_date IS NULL OR be.event_date = '0000-00-00')";
        } elseif ($status === 'ok') {
            $statusWhere = "AND (be.id IS NOT NULL AND be.event_date IS NOT NULL AND be.event_date <> '0000-00-00')";
        }

        $searchSql = '';
        $types = '';
        $params = [];
        if ($q !== '') {
            $searchSql = " AND (p.name LIKE CONCAT('%', ?, '%') OR p.pretty_id LIKE CONCAT('%', ?, '%') OR CAST(p.id AS CHAR) = ?)";
            $types .= 'sss';
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
        }

        $birthTextExpr = hg_abq_birthtext_expr($db);
        $sql = "
            SELECT
              p.id AS character_id,
              p.pretty_id AS character_pretty_id,
              p.name AS character_name,
              {$birthTextExpr} AS character_birthdate_text,
              be.id AS birth_event_id,
              be.pretty_id AS birth_event_pretty_id,
              be.event_date AS birth_event_date,
              be.title AS birth_event_title,
              CASE
                WHEN be.id IS NULL THEN 'SIN_EVENTO'
                WHEN be.event_date IS NULL OR be.event_date = '0000-00-00' THEN 'EVENTO_SIN_FECHA'
                ELSE 'OK'
              END AS estado
            FROM fact_characters p
            LEFT JOIN (
              SELECT b.character_id, MIN(e.id) AS event_id
              FROM bridge_timeline_events_characters b
              INNER JOIN fact_timeline_events e ON e.id = b.event_id
              INNER JOIN dim_timeline_events_types t ON t.id = e.event_type_id
              WHERE t.pretty_id = 'nacimiento'
              GROUP BY b.character_id
            ) bx ON bx.character_id = p.id
            LEFT JOIN fact_timeline_events be ON be.id = bx.event_id
            WHERE 1=1
              {$statusWhere}
              {$searchSql}
            ORDER BY p.id ASC
            LIMIT {$limit}
        ";

        $st = $db->prepare($sql);
        if (!$st) return $rows;
        if ($types !== '') {
            $st->bind_param($types, ...$params);
        }
        $st->execute();
        $rs = $st->get_result();
        while ($rs && ($row = $rs->fetch_assoc())) {
            $rows[] = $row;
        }
        $st->close();
        return $rows;
    }
}

if (!function_exists('hg_abq_fetch_row_by_character')) {
    function hg_abq_fetch_row_by_character(mysqli $db, int $characterId): ?array {
        if ($characterId <= 0) return null;
        $birthTextExpr = hg_abq_birthtext_expr($db);
        $sql = "
            SELECT
              p.id AS character_id,
              p.pretty_id AS character_pretty_id,
              p.name AS character_name,
              {$birthTextExpr} AS character_birthdate_text,
              be.id AS birth_event_id,
              be.pretty_id AS birth_event_pretty_id,
              be.event_date AS birth_event_date,
              be.title AS birth_event_title,
              CASE
                WHEN be.id IS NULL THEN 'SIN_EVENTO'
                WHEN be.event_date IS NULL OR be.event_date = '0000-00-00' THEN 'EVENTO_SIN_FECHA'
                ELSE 'OK'
              END AS estado
            FROM fact_characters p
            LEFT JOIN (
              SELECT b.character_id, MIN(e.id) AS event_id
              FROM bridge_timeline_events_characters b
              INNER JOIN fact_timeline_events e ON e.id = b.event_id
              INNER JOIN dim_timeline_events_types t ON t.id = e.event_type_id
              WHERE t.pretty_id = 'nacimiento'
              GROUP BY b.character_id
            ) bx ON bx.character_id = p.id
            LEFT JOIN fact_timeline_events be ON be.id = bx.event_id
            WHERE p.id = ?
            LIMIT 1
        ";
        $st = $db->prepare($sql);
        if (!$st) return null;
        $st->bind_param('i', $characterId);
        $st->execute();
        $rs = $st->get_result();
        $row = $rs ? $rs->fetch_assoc() : null;
        $st->close();
        return $row ?: null;
    }
}



if (!function_exists('hg_abq_save_row')) {
    function hg_abq_save_row(mysqli $link, int $characterId, string $birthdateText, string $forcedEventDate): array {
        if ($characterId <= 0) {
            return ['ok'=>false,'status'=>400,'message'=>'ID de personaje inválido','errors'=>['character_id'=>'required']];
        }

        $characterName = '';
        $exists = 0;
        if ($st = $link->prepare('SELECT COUNT(*), COALESCE(MAX(name), "") FROM fact_characters WHERE id = ?')) {
            $st->bind_param('i', $characterId);
            $st->execute();
            $st->bind_result($exists, $characterName);
            $st->fetch();
            $st->close();
        }
        if ((int)$exists <= 0) {
            return ['ok'=>false,'status'=>404,'message'=>'Personaje no encontrado','errors'=>['character_id'=>'not_found']];
        }

        $birthTypeId = hg_abq_find_birth_type_id($link);
        if ($birthTypeId <= 0) {
            return ['ok'=>false,'status'=>400,'message'=>'No existe el tipo nacimiento en dim_timeline_events_types','errors'=>['event_type'=>'nacimiento_missing']];
        }

        $parsedDate = hg_abq_parse_birth_to_ymd($forcedEventDate !== '' ? $forcedEventDate : $birthdateText);
        $prettyId = 'birthday-char-' . $characterId;
        $eventId = 0;

        $link->begin_transaction();
        try {
            if ($parsedDate !== null) {
                if ($st = $link->prepare('SELECT id FROM fact_timeline_events WHERE pretty_id = ? LIMIT 1')) {
                    $st->bind_param('s', $prettyId);
                    $st->execute();
                    $st->bind_result($eventId);
                    $st->fetch();
                    $st->close();
                }

                if ($eventId <= 0 && ($st = $link->prepare("
                    SELECT e.id
                    FROM bridge_timeline_events_characters b
                    INNER JOIN fact_timeline_events e ON e.id = b.event_id
                    INNER JOIN dim_timeline_events_types t ON t.id = e.event_type_id
                    WHERE b.character_id = ? AND t.pretty_id = 'nacimiento'
                    ORDER BY e.id ASC
                    LIMIT 1
                "))) {
                    $st->bind_param('i', $characterId);
                    $st->execute();
                    $st->bind_result($eventId);
                    $st->fetch();
                    $st->close();
                }

                $title = 'Cumpleaños de ' . trim($characterName);
                $description = 'Evento de nacimiento del personaje ' . trim($characterName) . ' (id=' . $characterId . ').';
                if ($eventId > 0) {
                    $sqlUpdateEvent = "UPDATE fact_timeline_events
                           SET pretty_id = ?, event_date = ?, date_precision = 'day', date_note = NULL, sort_date = ?,
                               title = ?, description = ?, event_type_id = ?, is_active = 1,
                               source = 'admin_birthdays_quick', updated_at = NOW()
                           WHERE id = ?";
                    if ($st = $link->prepare($sqlUpdateEvent)) {
                        $st->bind_param('sssssii', $prettyId, $parsedDate, $parsedDate, $title, $description, $birthTypeId, $eventId);
                        $st->execute();
                        $st->close();
                    }
                } else {
                    $sqlInsertEvent = "INSERT INTO fact_timeline_events
                           (pretty_id, event_date, date_precision, date_note, sort_date, title, description, event_type_id, is_active, source, timeline)
                           VALUES (?, ?, 'day', NULL, ?, ?, ?, ?, 1, 'admin_birthdays_quick', NULL)";
                    if ($st = $link->prepare($sqlInsertEvent)) {
                        $st->bind_param('sssssi', $prettyId, $parsedDate, $parsedDate, $title, $description, $birthTypeId);
                        $st->execute();
                        $eventId = (int)$link->insert_id;
                        $st->close();
                    }
                }

                if ($eventId > 0) {
                    $bridgeExists = 0;
                    if ($st = $link->prepare('SELECT COUNT(*) FROM bridge_timeline_events_characters WHERE event_id = ? AND character_id = ?')) {
                        $st->bind_param('ii', $eventId, $characterId);
                        $st->execute();
                        $st->bind_result($bridgeExists);
                        $st->fetch();
                        $st->close();
                    }
                    if ((int)$bridgeExists <= 0) {
                        $st = $link->prepare('INSERT INTO bridge_timeline_events_characters (event_id, character_id, role_label, sort_order) VALUES (?, ?, "protagonista", 0)');
                        if ($st) {
                            $st->bind_param('ii', $eventId, $characterId);
                            $st->execute();
                            $st->close();
                        }
                    }
                }
            }
            $link->commit();
        } catch (Throwable $e) {
            $link->rollback();
            return ['ok'=>false,'status'=>500,'message'=>'Error al guardar fila','errors'=>['sql'=>$e->getMessage()]];
        }

        $savedRow = hg_abq_fetch_row_by_character($link, $characterId);
        $message = ($parsedDate === null)
            ? 'La fecha indicada no se ha podido convertir en evento de nacimiento.'
            : 'Evento de nacimiento guardado.';
        return ['ok'=>true,'status'=>200,'message'=>$message,'row'=>$savedRow];
    }
}
