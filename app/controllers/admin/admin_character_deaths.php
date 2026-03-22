<?php
// admin_character_deaths.php - Gestion de muertes de personajes con guardado Ajax por fila.

if (!isset($link) || !$link) { die("Error de conexion a la base de datos."); }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
include_once(__DIR__ . '/../../helpers/admin_ajax.php');

if (!function_exists('hg_acd_h')) {
    function hg_acd_h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

function hg_acd_has_table(mysqli $db, string $table): bool {
    $table = str_replace('`', '', $table);
    $rs = $db->query("SHOW TABLES LIKE '".$db->real_escape_string($table)."'");
    if (!$rs) return false;
    $ok = ($rs->num_rows > 0);
    $rs->close();
    return $ok;
}

function hg_acd_pick_deaths_table(mysqli $db): string {
    if (hg_acd_has_table($db, 'fact_characters_deaths')) return 'fact_characters_deaths';
    if (hg_acd_has_table($db, 'fact_characters_death')) return 'fact_characters_death';
    return '';
}

function hg_acd_col_exists(mysqli $db, string $table, string $column): bool {
    static $cache = [];
    $key = $table . ':' . $column;
    if (isset($cache[$key])) return $cache[$key];
    $ok = false;
    if ($st = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?")) {
        $st->bind_param('ss', $table, $column);
        $st->execute();
        $st->bind_result($count);
        $st->fetch();
        $st->close();
        $ok = ((int)$count > 0);
    }
    $cache[$key] = $ok;
    return $ok;
}

function hg_acd_character_name(mysqli $db, int $characterId): string {
    if ($characterId <= 0) return '';
    $name = '';
    if ($st = $db->prepare("SELECT name FROM fact_characters WHERE id = ? LIMIT 1")) {
        $st->bind_param('i', $characterId);
        $st->execute();
        $st->bind_result($name);
        $st->fetch();
        $st->close();
    }
    return trim((string)$name);
}

function hg_acd_resolve_status_id(mysqli $db, string $prettyId, string $label, int $fallback): int {
    if (!hg_acd_has_table($db, 'dim_character_status')) {
        return $fallback;
    }
    $id = 0;
    if ($st = $db->prepare("SELECT id FROM dim_character_status WHERE pretty_id = ? LIMIT 1")) {
        $st->bind_param('s', $prettyId);
        $st->execute();
        $st->bind_result($id);
        $st->fetch();
        $st->close();
    }
    if ((int)$id > 0) return (int)$id;

    if ($st = $db->prepare("SELECT id FROM dim_character_status WHERE LOWER(label) = LOWER(?) LIMIT 1")) {
        $st->bind_param('s', $label);
        $st->execute();
        $st->bind_result($id);
        $st->fetch();
        $st->close();
    }
    if ((int)$id > 0) return (int)$id;

    return $fallback;
}

function hg_acd_set_character_status_by_death(mysqli $db, int $characterId, bool $hasDeath): void {
    if ($characterId <= 0 || !hg_acd_has_table($db, 'fact_characters')) return;
    $statusId = $hasDeath
        ? hg_acd_resolve_status_id($db, 'cadaver', 'Cadáver', 3)
        : hg_acd_resolve_status_id($db, 'en_activo', 'En activo', 1);
    if ($statusId <= 0) return;

    if ($st = $db->prepare("UPDATE fact_characters SET status_id = ? WHERE id = ? LIMIT 1")) {
        $st->bind_param('ii', $statusId, $characterId);
        $st->execute();
        $st->close();
    }
}

function hg_acd_sync_event_characters(mysqli $db, int $eventId, int $characterId, ?int $killerId): void {
    if ($eventId <= 0 || $characterId <= 0) return;
    if (!hg_acd_has_table($db, 'bridge_timeline_events_characters')) return;

    $hasSortOrder = hg_acd_col_exists($db, 'bridge_timeline_events_characters', 'sort_order');
    $hasRoleLabel = hg_acd_col_exists($db, 'bridge_timeline_events_characters', 'role_label');

    if ($st = $db->prepare("DELETE FROM bridge_timeline_events_characters WHERE event_id = ?")) {
        $st->bind_param('i', $eventId);
        $st->execute();
        $st->close();
    }

    $pairs = [
        ['id' => $characterId, 'role' => 'victima', 'sort' => 0],
    ];
    if ($killerId !== null && $killerId > 0 && $killerId !== $characterId) {
        $pairs[] = ['id' => $killerId, 'role' => 'killer', 'sort' => 1];
    }

    if ($hasRoleLabel && $hasSortOrder) {
        $sql = "INSERT INTO bridge_timeline_events_characters (event_id, character_id, role_label, sort_order) VALUES (?, ?, ?, ?)";
        if ($st = $db->prepare($sql)) {
            foreach ($pairs as $p) {
                $cid = (int)$p['id'];
                $role = (string)$p['role'];
                $sort = (int)$p['sort'];
                $st->bind_param('iisi', $eventId, $cid, $role, $sort);
                $st->execute();
            }
            $st->close();
        }
        return;
    }

    if ($hasRoleLabel && !$hasSortOrder) {
        $sql = "INSERT INTO bridge_timeline_events_characters (event_id, character_id, role_label) VALUES (?, ?, ?)";
        if ($st = $db->prepare($sql)) {
            foreach ($pairs as $p) {
                $cid = (int)$p['id'];
                $role = (string)$p['role'];
                $st->bind_param('iis', $eventId, $cid, $role);
                $st->execute();
            }
            $st->close();
        }
        return;
    }

    if (!$hasRoleLabel && $hasSortOrder) {
        $sql = "INSERT INTO bridge_timeline_events_characters (event_id, character_id, sort_order) VALUES (?, ?, ?)";
        if ($st = $db->prepare($sql)) {
            foreach ($pairs as $p) {
                $cid = (int)$p['id'];
                $sort = (int)$p['sort'];
                $st->bind_param('iii', $eventId, $cid, $sort);
                $st->execute();
            }
            $st->close();
        }
        return;
    }

    $sql = "INSERT INTO bridge_timeline_events_characters (event_id, character_id) VALUES (?, ?)";
    if ($st = $db->prepare($sql)) {
        foreach ($pairs as $p) {
            $cid = (int)$p['id'];
            $st->bind_param('ii', $eventId, $cid);
            $st->execute();
        }
        $st->close();
    }
}

function hg_acd_sync_event_chronicle(mysqli $db, int $eventId, int $characterId): void {
    if ($eventId <= 0 || $characterId <= 0) return;
    if (!hg_acd_has_table($db, 'bridge_timeline_events_chronicles')) return;

    $chronicleId = 0;
    if ($st = $db->prepare("SELECT chronicle_id FROM fact_characters WHERE id = ? LIMIT 1")) {
        $st->bind_param('i', $characterId);
        $st->execute();
        $st->bind_result($chronicleId);
        $st->fetch();
        $st->close();
    }
    $chronicleId = (int)$chronicleId;

    if ($st = $db->prepare("DELETE FROM bridge_timeline_events_chronicles WHERE event_id = ?")) {
        $st->bind_param('i', $eventId);
        $st->execute();
        $st->close();
    }
    if ($chronicleId <= 0) return;

    $hasSortOrder = hg_acd_col_exists($db, 'bridge_timeline_events_chronicles', 'sort_order');
    if ($hasSortOrder) {
        if ($st = $db->prepare("INSERT INTO bridge_timeline_events_chronicles (event_id, chronicle_id, sort_order) VALUES (?, ?, 0)")) {
            $st->bind_param('ii', $eventId, $chronicleId);
            $st->execute();
            $st->close();
        }
    } else {
        if ($st = $db->prepare("INSERT INTO bridge_timeline_events_chronicles (event_id, chronicle_id) VALUES (?, ?)")) {
            $st->bind_param('ii', $eventId, $chronicleId);
            $st->execute();
            $st->close();
        }
    }
}

function hg_acd_sync_timeline_from_death(mysqli $db, string $deathsTable, ?int $eventId, int $characterId, ?int $killerId, string $deathType, ?string $deathDate, ?string $deathDescription): ?int {
    if ($eventId !== null && $eventId > 0) {
        $eventId = (int)$eventId;
    } else {
        $eventId = null;
    }

    $charName = hg_acd_character_name($db, $characterId);
    if ($charName === '') {
        return $eventId;
    }
    $killerName = ($killerId !== null && $killerId > 0) ? hg_acd_character_name($db, $killerId) : '';

    $eventDate = ($deathDate !== null && $deathDate !== '') ? $deathDate : '1000-01-01';
    $precision = ($deathDate !== null && $deathDate !== '') ? 'day' : 'unknown';
    $dateNote = ($deathDate !== null && $deathDate !== '')
        ? null
        : "Fecha de muerte no especificada (sincronizado desde {$deathsTable}).";
    $title = 'Muerte de ' . $charName;
    $description = trim((string)$deathDescription);
    if ($description === '') {
        $description = $charName . ' muere';
        if ($killerName !== '') {
            $description .= ' a manos de ' . $killerName;
        }
        $description .= '.';
    }
    if ($deathType !== '') {
        $description .= ' [tipo: ' . $deathType . ']';
    }
    $source = $deathsTable . '.character#' . $characterId;
    $isActive = 1;

    if ($eventId === null) {
        if ($st = $db->prepare("
            INSERT INTO fact_timeline_events
                (title, event_date, date_precision, date_note, sort_date, description, source, event_type_id, is_active)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, 5, ?)
        ")) {
            $st->bind_param('sssssssi', $title, $eventDate, $precision, $dateNote, $eventDate, $description, $source, $isActive);
            $ok = $st->execute();
            if ($ok) {
                $eventId = (int)$db->insert_id;
            }
            $st->close();
        }
    } else if ($st = $db->prepare("
        UPDATE fact_timeline_events
        SET
            title = ?,
            description = ?,
            source = ?,
            event_type_id = 5,
            event_date = ?,
            sort_date = ?,
            date_precision = ?,
            date_note = ?,
            is_active = 1,
            updated_at = NOW()
        WHERE id = ?
        LIMIT 1
    ")) {
        $st->bind_param('sssssssi', $title, $description, $source, $eventDate, $eventDate, $precision, $dateNote, $eventId);
        $st->execute();
        $st->close();
    }

    if ($eventId !== null && $eventId > 0) {
        hg_acd_sync_event_characters($db, $eventId, $characterId, $killerId);
        hg_acd_sync_event_chronicle($db, $eventId, $characterId);
    }

    return $eventId;
}

$deathsTable = hg_acd_pick_deaths_table($link);
$hasSchema = ($deathsTable !== '')
    && hg_acd_has_table($link, 'fact_characters')
    && hg_acd_has_table($link, 'fact_timeline_events');

$ADMIN_CSRF_SESSION_KEY = 'csrf_admin_character_deaths';
if (function_exists('hg_admin_ensure_csrf_token')) {
    $CSRF = hg_admin_ensure_csrf_token($ADMIN_CSRF_SESSION_KEY);
} else {
    if (empty($_SESSION[$ADMIN_CSRF_SESSION_KEY])) {
        $_SESSION[$ADMIN_CSRF_SESSION_KEY] = bin2hex(random_bytes(16));
    }
    $CSRF = $_SESSION[$ADMIN_CSRF_SESSION_KEY];
}

$deathTypes = ['asesinato','catastrofe','suicidio','sacrificio','radiacion','absorcion','destruccion','desconexion','ritual','accidente','sobredosis','otros'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    if (ob_get_level() === 0) { ob_start(); }
    header('Content-Type: application/json; charset=UTF-8');

    $jsonExit = function(array $payload){
        $noise = '';
        if (ob_get_level() > 0) { $noise = (string)ob_get_clean(); }
        if (trim($noise) !== '') { $payload['_debug_noise'] = substr(trim($noise), 0, 1200); }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    };

    if (!$hasSchema) {
        $jsonExit(['ok' => false, 'msg' => 'Falta esquema para muertes de personajes']);
    }

    if (function_exists('hg_admin_require_session')) {
        hg_admin_require_session(true);
    }
    $csrfPayload = function_exists('hg_admin_read_json_payload') ? hg_admin_read_json_payload() : [];
    $csrfToken = function_exists('hg_admin_extract_csrf_token')
        ? hg_admin_extract_csrf_token($csrfPayload)
        : (string)($_POST['csrf'] ?? '');
    $csrfOk = function_exists('hg_admin_csrf_valid')
        ? hg_admin_csrf_valid($csrfToken, $ADMIN_CSRF_SESSION_KEY)
        : (is_string($csrfToken) && $csrfToken !== '' && isset($_SESSION[$ADMIN_CSRF_SESSION_KEY]) && hash_equals($_SESSION[$ADMIN_CSRF_SESSION_KEY], $csrfToken));
    if (!$csrfOk) {
        $jsonExit(['ok' => false, 'msg' => 'CSRF invalido. Recarga la pagina.']);
    }

    $ajaxMode = (string)($_POST['ajax'] ?? '');

    if ($ajaxMode === 'save_character_death') {
        $characterId = isset($_POST['character_id']) ? (int)$_POST['character_id'] : 0;
        $killerId = isset($_POST['killer_character_id']) ? (int)$_POST['killer_character_id'] : 0;
        $timelineEventId = isset($_POST['death_timeline_event_id']) ? (int)$_POST['death_timeline_event_id'] : 0;
        $deathType = trim((string)($_POST['death_type'] ?? ''));
        $deathDate = trim((string)($_POST['death_date'] ?? ''));
        $deathDescription = trim((string)($_POST['death_description'] ?? ''));
        $weight = isset($_POST['narrative_weight']) ? (int)$_POST['narrative_weight'] : 1;

        if ($characterId <= 0) $jsonExit(['ok' => false, 'msg' => 'Personaje invalido']);
        if ($killerId < 0 || $timelineEventId < 0) $jsonExit(['ok' => false, 'msg' => 'IDs invalidos']);
        if ($deathType === '') $deathType = 'otros';
        if (!in_array($deathType, $deathTypes, true)) $jsonExit(['ok' => false, 'msg' => 'Tipo de muerte invalido']);

        $killerId = $killerId > 0 ? $killerId : null;
        $timelineEventId = $timelineEventId > 0 ? $timelineEventId : null;
        $deathDate = ($deathDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $deathDate)) ? $deathDate : null;
        $deathDescription = ($deathDescription !== '') ? $deathDescription : null;
        if ($weight < 1) $weight = 1;
        if ($weight > 10) $weight = 10;

        $existsCharacter = 0;
        if ($st = $link->prepare("SELECT COUNT(*) FROM fact_characters WHERE id=?")) {
            $st->bind_param('i', $characterId);
            $st->execute();
            $st->bind_result($existsCharacter);
            $st->fetch();
            $st->close();
        }
        if ($existsCharacter <= 0) $jsonExit(['ok' => false, 'msg' => 'Personaje no encontrado']);

        if ($killerId !== null) {
            $existsKiller = 0;
            if ($st = $link->prepare("SELECT COUNT(*) FROM fact_characters WHERE id=?")) {
                $st->bind_param('i', $killerId);
                $st->execute();
                $st->bind_result($existsKiller);
                $st->fetch();
                $st->close();
            }
            if ($existsKiller <= 0) $jsonExit(['ok' => false, 'msg' => 'Responsable no encontrado']);
        }

        if ($timelineEventId !== null) {
            $existsEvent = 0;
            if ($st = $link->prepare("SELECT COUNT(*) FROM fact_timeline_events WHERE id=?")) {
                $st->bind_param('i', $timelineEventId);
                $st->execute();
                $st->bind_result($existsEvent);
                $st->fetch();
                $st->close();
            }
            if ($existsEvent <= 0) $jsonExit(['ok' => false, 'msg' => 'Evento no encontrado']);
        }

        $existingId = 0;
        if ($st = $link->prepare("SELECT id FROM `{$deathsTable}` WHERE character_id=? LIMIT 1")) {
            $st->bind_param('i', $characterId);
            $st->execute();
            $st->bind_result($existingId);
            $st->fetch();
            $st->close();
        }

        if ($existingId > 0) {
            if ($st = $link->prepare("
                UPDATE `{$deathsTable}`
                SET killer_character_id=?, death_timeline_event_id=?, death_type=?, death_date=?, death_description=?, narrative_weight=?
                WHERE id=?
                LIMIT 1
            ")) {
                $st->bind_param('iisssii', $killerId, $timelineEventId, $deathType, $deathDate, $deathDescription, $weight, $existingId);
                if (!$st->execute()) {
                    $st->close();
                    $jsonExit(['ok' => false, 'msg' => 'Error al actualizar la muerte']);
                }
                $st->close();
                $timelineEventId = hg_acd_sync_timeline_from_death(
                    $link,
                    $deathsTable,
                    $timelineEventId,
                    $characterId,
                    $killerId,
                    $deathType,
                    $deathDate,
                    $deathDescription
                );
                if ($timelineEventId !== null && $timelineEventId > 0) {
                    if ($stEv = $link->prepare("UPDATE `{$deathsTable}` SET death_timeline_event_id = ? WHERE id = ? LIMIT 1")) {
                        $stEv->bind_param('ii', $timelineEventId, $existingId);
                        $stEv->execute();
                        $stEv->close();
                    }
                }
                hg_acd_set_character_status_by_death($link, $characterId, true);
                $eventLabel = '';
                if ($timelineEventId !== null && $timelineEventId > 0) {
                    $eventTitle = '';
                    $eventDateOut = '';
                    if ($stEv = $link->prepare("SELECT event_date, title FROM fact_timeline_events WHERE id = ? LIMIT 1")) {
                        $stEv->bind_param('i', $timelineEventId);
                        $stEv->execute();
                        $stEv->bind_result($eventDateOut, $eventTitle);
                        $stEv->fetch();
                        $stEv->close();
                    }
                    $eventLabel = '#' . (int)$timelineEventId . ($eventDateOut !== '' ? (' [' . $eventDateOut . '] ') : ' ') . trim((string)$eventTitle);
                }
                $jsonExit([
                    'ok' => true,
                    'msg' => 'Guardado',
                    'mode' => 'update',
                    'event_id' => $timelineEventId ? (int)$timelineEventId : 0,
                    'event_label' => $eventLabel
                ]);
            }
            $jsonExit(['ok' => false, 'msg' => 'Error al preparar UPDATE']);
        }

        if ($st = $link->prepare("
            INSERT INTO `{$deathsTable}`
            (character_id, killer_character_id, death_timeline_event_id, death_type, death_date, death_description, narrative_weight)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ")) {
            $st->bind_param('iiisssi', $characterId, $killerId, $timelineEventId, $deathType, $deathDate, $deathDescription, $weight);
            if (!$st->execute()) {
                $st->close();
                $jsonExit(['ok' => false, 'msg' => 'Error al insertar la muerte']);
            }
            $st->close();
            $newDeathId = (int)$link->insert_id;
            $timelineEventId = hg_acd_sync_timeline_from_death(
                $link,
                $deathsTable,
                $timelineEventId,
                $characterId,
                $killerId,
                $deathType,
                $deathDate,
                $deathDescription
            );
            if ($timelineEventId !== null && $timelineEventId > 0) {
                if ($stEv = $link->prepare("UPDATE `{$deathsTable}` SET death_timeline_event_id = ? WHERE id = ? LIMIT 1")) {
                    $stEv->bind_param('ii', $timelineEventId, $newDeathId);
                    $stEv->execute();
                    $stEv->close();
                }
            }
            hg_acd_set_character_status_by_death($link, $characterId, true);
            $eventLabel = '';
            if ($timelineEventId !== null && $timelineEventId > 0) {
                $eventTitle = '';
                $eventDateOut = '';
                if ($stEv = $link->prepare("SELECT event_date, title FROM fact_timeline_events WHERE id = ? LIMIT 1")) {
                    $stEv->bind_param('i', $timelineEventId);
                    $stEv->execute();
                    $stEv->bind_result($eventDateOut, $eventTitle);
                    $stEv->fetch();
                    $stEv->close();
                }
                $eventLabel = '#' . (int)$timelineEventId . ($eventDateOut !== '' ? (' [' . $eventDateOut . '] ') : ' ') . trim((string)$eventTitle);
            }
            $jsonExit([
                'ok' => true,
                'msg' => 'Guardado',
                'mode' => 'insert',
                'event_id' => $timelineEventId ? (int)$timelineEventId : 0,
                'event_label' => $eventLabel
            ]);
        }

        $jsonExit(['ok' => false, 'msg' => 'Error al preparar INSERT']);
    }

    if ($ajaxMode === 'delete_character_death') {
        $characterId = isset($_POST['character_id']) ? (int)$_POST['character_id'] : 0;
        if ($characterId <= 0) $jsonExit(['ok' => false, 'msg' => 'Personaje invalido']);
        if ($st = $link->prepare("DELETE FROM `{$deathsTable}` WHERE character_id=? LIMIT 1")) {
            $st->bind_param('i', $characterId);
            if (!$st->execute()) {
                $st->close();
                $jsonExit(['ok' => false, 'msg' => 'Error al borrar']);
            }
            $st->close();
            hg_acd_set_character_status_by_death($link, $characterId, false);
            $jsonExit(['ok' => true, 'msg' => 'Muerte eliminada']);
        }
        $jsonExit(['ok' => false, 'msg' => 'Error al preparar DELETE']);
    }

    $jsonExit(['ok' => false, 'msg' => 'Modo AJAX no soportado']);
}

$killers = [];
$charactersForSelect = [];
$events = [];
$rows = [];
$rowMap = [];
$perPage = isset($_GET['pp']) ? max(10, min(200, (int)$_GET['pp'])) : 50;
$page = isset($_GET['pg']) ? max(1, (int)$_GET['pg']) : 1;
$q = trim((string)($_GET['q'] ?? ''));
$total = 0;
$pages = 1;

function hg_acd_fetch_deaths_rows(mysqli $link, string $deathsTable, string $q = '', int $limit = 0, int $offset = 0, ?int &$totalOut = null): array {
    $rows = [];
    $q = trim($q);
    $hasQ = ($q !== '');
    $needle = '%' . $q . '%';

    $fromSql = "
        FROM `{$deathsTable}` d
        INNER JOIN fact_characters p ON p.id = d.character_id
        LEFT JOIN dim_character_status dcs ON dcs.id = p.status_id
        LEFT JOIN fact_characters k ON k.id = d.killer_character_id
        LEFT JOIN fact_timeline_events e ON e.id = d.death_timeline_event_id
    ";
    $whereSql = $hasQ
        ? " WHERE (
                p.name LIKE ? OR
                p.pretty_id LIKE ? OR
                COALESCE(d.death_type, '') LIKE ? OR
                COALESCE(k.name, '') LIKE ? OR
                COALESCE(e.title, '') LIKE ?
            ) "
        : "";

    if ($totalOut !== null) {
        $totalOut = 0;
        if ($hasQ) {
            $sqlCnt = "SELECT COUNT(*) " . $fromSql . $whereSql;
            if ($stCnt = $link->prepare($sqlCnt)) {
                $stCnt->bind_param('sssss', $needle, $needle, $needle, $needle, $needle);
                $stCnt->execute();
                $stCnt->bind_result($totalOut);
                $stCnt->fetch();
                $stCnt->close();
            }
        } else if ($rsCnt = $link->query("SELECT COUNT(*) AS c " . $fromSql)) {
            if ($rCnt = $rsCnt->fetch_assoc()) {
                $totalOut = (int)($rCnt['c'] ?? 0);
            }
            $rsCnt->close();
        }
    }

    $selectSql = "
        SELECT
            d.id AS death_id,
            d.character_id,
            p.pretty_id,
            p.name AS character_name,
            COALESCE(dcs.label, '') AS status_label,
            d.killer_character_id,
            d.death_timeline_event_id,
            d.death_type,
            d.death_date,
            d.death_description,
            d.narrative_weight,
            COALESCE(k.name, '') AS killer_name,
            COALESCE(e.title, '') AS event_title,
            COALESCE(e.event_date, '') AS event_date
        " . $fromSql . $whereSql . "
        ORDER BY
            COALESCE(d.death_date, '1000-01-01') DESC,
            d.id DESC
    ";
    if ($limit > 0) {
        $selectSql .= " LIMIT ?, ?";
    }

    if ($st = $link->prepare($selectSql)) {
        if ($hasQ && $limit > 0) {
            $st->bind_param('sssssii', $needle, $needle, $needle, $needle, $needle, $offset, $limit);
        } elseif ($hasQ) {
            $st->bind_param('sssss', $needle, $needle, $needle, $needle, $needle);
        } elseif ($limit > 0) {
            $st->bind_param('ii', $offset, $limit);
        }
        $st->execute();
        $rs = $st->get_result();
        while ($r = $rs->fetch_assoc()) {
            $r['character_id'] = (int)($r['character_id'] ?? 0);
            $r['death_id'] = (int)($r['death_id'] ?? 0);
            $r['killer_character_id'] = (int)($r['killer_character_id'] ?? 0);
            $r['death_timeline_event_id'] = (int)($r['death_timeline_event_id'] ?? 0);
            $r['narrative_weight'] = (int)($r['narrative_weight'] ?? 1);
            if ($r['narrative_weight'] < 1) $r['narrative_weight'] = 1;
            if ($r['narrative_weight'] > 10) $r['narrative_weight'] = 10;

            $summary = [];
            $deathType = trim((string)($r['death_type'] ?? ''));
            if ($deathType !== '') $summary[] = ucfirst($deathType);
            if ((int)$r['killer_character_id'] > 0) {
                $summary[] = 'Por: ' . (trim((string)$r['killer_name']) !== '' ? (string)$r['killer_name'] : ('#' . (int)$r['killer_character_id']));
            }
            if ((int)$r['death_timeline_event_id'] > 0) {
                $summary[] = 'Evento: ' . (trim((string)$r['event_title']) !== '' ? (string)$r['event_title'] : ('#' . (int)$r['death_timeline_event_id']));
            }
            $r['summary_text'] = !empty($summary) ? implode(' | ', $summary) : 'Sin resumen';
            $rows[] = $r;
        }
        $st->close();
    }

    return $rows;
}

if ($hasSchema) {
    if ($rs = $link->query("SELECT id, name FROM fact_characters ORDER BY name ASC")) {
        while ($r = $rs->fetch_assoc()) {
            $cid = (int)($r['id'] ?? 0);
            if ($cid <= 0) continue;
            $charactersForSelect[] = [
                'id' => $cid,
                'name' => (string)($r['name'] ?? ''),
            ];
            $killers[] = [
                'id' => $cid,
                'name' => (string)($r['name'] ?? ''),
            ];
        }
        $rs->close();
    }

    if ($rs = $link->query("SELECT id, event_date, title FROM fact_timeline_events ORDER BY event_date DESC, id DESC")) {
        while ($r = $rs->fetch_assoc()) {
            $events[] = $r;
        }
        $rs->close();
    }

    $offset = ($page - 1) * $perPage;
    $rows = hg_acd_fetch_deaths_rows($link, $deathsTable, $q, $perPage, $offset, $total);
    $pages = max(1, (int)ceil($total / $perPage));
    if ($page > $pages) {
        $page = $pages;
    }
    foreach ($rows as $r) {
        $rowMap[(string)((int)($r['character_id'] ?? 0))] = $r;
    }
}

$ajaxModeGet = (string)($_GET['ajax_mode'] ?? '');
if ($hasSchema && $ajaxModeGet === 'search') {
    if (function_exists('hg_admin_require_session')) {
        hg_admin_require_session(true);
    }
    $qAjax = trim((string)($_GET['q'] ?? ''));
    $rowsAjax = hg_acd_fetch_deaths_rows($link, $deathsTable, $qAjax);
    $rowMapAjax = [];
    foreach ($rowsAjax as $r) {
        $rowMapAjax[(string)((int)($r['character_id'] ?? 0))] = $r;
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'message' => 'OK',
        'rows' => $rowsAjax,
        'rowMap' => $rowMapAjax,
        'total' => count($rowsAjax),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

include_once(__DIR__ . '/../../partials/admin/admin_styles.php');
$actions = '<span class="adm-flex-right-8">'
    . '<button class="btn btn-green" type="button" id="btnNewDeath">+ Nueva muerte</button>'
    . '</span>';
admin_panel_open('Muertes de personajes', $actions);
?>

<?php if (!$hasSchema): ?>
  <div class="warn-box">
    Falta esquema. Necesario: <code>fact_characters_deaths</code> (o <code>fact_characters_death</code>), <code>fact_characters</code> y <code>fact_timeline_events</code>.
  </div>
<?php else: ?>
  <form method="get" id="acdFilterForm" class="adm-flex-8-m10">
    <input type="hidden" name="s" value="admin_character_deaths">
    <label class="small">Busqueda
      <input class="inp" type="text" name="q" id="quickFilterAcd" value="<?= hg_acd_h($q) ?>" placeholder="Personaje, tipo, responsable o evento">
    </label>
    <label class="small">Por pag
      <select class="select" name="pp" onchange="this.form.submit()">
        <?php foreach ([25,50,100,200] as $pp): ?>
          <option value="<?= $pp ?>" <?= $perPage === $pp ? 'selected' : '' ?>><?= $pp ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <button class="btn" type="button" id="btnApplyAcdFilter">Aplicar</button>
  </form>

  <table class="table" id="acdTable">
    <thead>
      <tr>
        <th class="adm-w-80">ID PJ</th>
        <th class="adm-w-220">Personaje</th>
        <th class="adm-w-140">Estado PJ</th>
        <th class="adm-w-120">Tipo</th>
        <th class="adm-w-120">Fecha</th>
        <th class="adm-w-180">Responsable</th>
        <th>Evento</th>
        <th class="adm-w-80">Peso</th>
        <th class="adm-w-170">Acciones</th>
      </tr>
    </thead>
    <tbody id="acdTbody">
      <?php foreach ($rows as $r): ?>
        <?php
          $charId = (int)($r['character_id'] ?? 0);
          $eventLabel = '-';
          if ((int)($r['death_timeline_event_id'] ?? 0) > 0) {
              $eventLabel = '#' . (int)$r['death_timeline_event_id'];
              if (trim((string)($r['event_date'] ?? '')) !== '') {
                  $eventLabel .= ' [' . (string)$r['event_date'] . ']';
              }
              if (trim((string)($r['event_title'] ?? '')) !== '') {
                  $eventLabel .= ' ' . (string)$r['event_title'];
              }
          }
        ?>
        <tr>
          <td><strong class="adm-color-accent"><?= $charId ?></strong></td>
          <td><?= hg_acd_h((string)($r['character_name'] ?? '')) ?></td>
          <td><?= hg_acd_h((string)($r['status_label'] ?? '-')) ?></td>
          <td><?= hg_acd_h((string)($r['death_type'] ?? '-')) ?></td>
          <td><?= hg_acd_h((string)($r['death_date'] ?? '-')) ?></td>
          <td><?= hg_acd_h(trim((string)($r['killer_name'] ?? '')) !== '' ? (string)$r['killer_name'] : '-') ?></td>
          <td><?= hg_acd_h($eventLabel) ?></td>
          <td><?= (int)($r['narrative_weight'] ?? 1) ?></td>
          <td>
            <button class="btn" type="button" data-edit="<?= $charId ?>">Editar</button>
            <button class="btn btn-red" type="button" data-del="<?= $charId ?>">Borrar</button>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($rows)): ?>
        <tr><td colspan="9" class="adm-color-muted">(Sin muertes registradas)</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <div class="pager" id="acdPager">
    <?php
      $base = "/talim?s=admin_character_deaths&pp=".$perPage."&q=".urlencode($q);
      $prev = max(1, $page - 1);
      $next = min($pages, $page + 1);
    ?>
    <a href="<?= $base ?>&pg=1">&laquo; Primero</a>
    <a href="<?= $base ?>&pg=<?= $prev ?>">&lsaquo; Anterior</a>
    <span class="cur">Pag <?= $page ?>/<?= $pages ?> - Total <?= (int)$total ?></span>
    <a href="<?= $base ?>&pg=<?= $next ?>">Siguiente &rsaquo;</a>
    <a href="<?= $base ?>&pg=<?= $pages ?>">Ultimo &raquo;</a>
  </div>

  <div class="modal-back" id="mbAcd">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="acdModalTitle">
      <h3 id="acdModalTitle">Nueva muerte</h3>
      <form id="acdForm" class="adm-m-0">
        <input type="hidden" name="csrf" value="<?= hg_acd_h($CSRF) ?>">
        <input type="hidden" id="acd_mode" value="create">
        <input type="hidden" id="acd_fixed_character_id" value="0">

        <div class="grid">
          <label><span>Personaje</span> <span class="badge">oblig.</span>
            <select class="select" id="acd_character_id" required>
              <option value="0">-- Selecciona --</option>
              <?php foreach ($charactersForSelect as $c): ?>
                <option value="<?= (int)($c['id'] ?? 0) ?>">#<?= (int)($c['id'] ?? 0) ?> - <?= hg_acd_h((string)($c['name'] ?? '')) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label><span>Tipo</span>
            <select class="select" id="acd_death_type">
              <?php foreach ($deathTypes as $t): ?>
                <option value="<?= hg_acd_h($t) ?>"><?= hg_acd_h(ucfirst($t)) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label><span>Responsable</span>
            <select class="select" id="acd_killer_id">
              <option value="0">- Sin responsable -</option>
              <?php foreach ($killers as $k): ?>
                <option value="<?= (int)($k['id'] ?? 0) ?>">#<?= (int)($k['id'] ?? 0) ?> - <?= hg_acd_h((string)($k['name'] ?? '')) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label><span>Evento</span>
            <select class="select" id="acd_event_id">
              <option value="0">- Sin evento (auto) -</option>
              <?php foreach ($events as $e):
                  $eid = (int)($e['id'] ?? 0);
                  $etitle = (string)($e['title'] ?? '');
                  $edate = (string)($e['event_date'] ?? '');
              ?>
                <option value="<?= $eid ?>">#<?= $eid ?> <?= $edate !== '' ? '['.hg_acd_h($edate).'] ' : '' ?><?= hg_acd_h($etitle) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label><span>Fecha</span>
            <input class="inp" type="date" id="acd_death_date">
          </label>
          <label><span>Peso narrativo</span>
            <input class="inp" type="number" min="1" max="10" id="acd_weight" value="1">
          </label>
          <label class="field-full"><span>Descripcion</span>
            <textarea class="inp ta-md" id="acd_description" rows="4" placeholder="Descripcion..."></textarea>
          </label>
        </div>

        <div class="modal-actions">
          <button type="button" class="btn" id="btnAcdCancel">Cancelar</button>
          <button type="submit" class="btn btn-green">Guardar</button>
        </div>
      </form>
    </div>
  </div>

  <div class="modal-back" id="mbAcdDel">
    <div class="modal adm-modal-sm">
      <h3>Confirmar borrado</h3>
      <div class="adm-help-text">
        Se eliminara la muerte registrada del personaje seleccionado.
      </div>
      <form id="acdDelForm" class="adm-m-0">
        <input type="hidden" name="csrf" value="<?= hg_acd_h($CSRF) ?>">
        <input type="hidden" id="acd_del_character_id" value="0">
        <div class="modal-actions">
          <button type="button" class="btn" id="btnAcdDelCancel">Cancelar</button>
          <button type="submit" class="btn btn-red">Borrar</button>
        </div>
      </form>
    </div>
  </div>
<?php endif; ?>

<?php if ($hasSchema): ?>
<?php
  $adminHttpJs = '/assets/js/admin/admin-http.js';
  $adminHttpJsVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . $adminHttpJs) ?: time();
?>
<script src="<?= hg_acd_h($adminHttpJs) ?>?v=<?= (int)$adminHttpJsVer ?>"></script>
<script>
window.ADMIN_CSRF_TOKEN = <?= json_encode($CSRF, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
var ACD_ROWS = <?= json_encode($rowMap, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE); ?>;
(function(){
  var modal = document.getElementById('mbAcd');
  var delModal = document.getElementById('mbAcdDel');
  var form = document.getElementById('acdForm');
  var delForm = document.getElementById('acdDelForm');
  var filterInput = document.getElementById('quickFilterAcd');
  var filterBtn = document.getElementById('btnApplyAcdFilter');
  var filterForm = document.getElementById('acdFilterForm');
  var tbody = document.getElementById('acdTbody');
  var pager = document.getElementById('acdPager');
  if (!modal || !delModal || !form || !delForm || !filterInput || !tbody) return;

  var initialHtml = tbody.innerHTML;
  var reqSeq = 0;
  var timer = null;

  function esc(s){
    return String(s || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function endpointUrl(mode, term){
    var url = new URL(window.location.href);
    url.searchParams.set('s', 'admin_character_deaths');
    url.searchParams.set('ajax', '1');
    if (mode) url.searchParams.set('ajax_mode', mode);
    if (typeof term === 'string') {
      if (term.trim()) url.searchParams.set('q', term.trim());
      else url.searchParams.delete('q');
    }
    url.searchParams.set('_ts', Date.now());
    return url.toString();
  }

  function request(url, opts){
    if (window.HGAdminHttp && typeof window.HGAdminHttp.request === 'function') {
      return window.HGAdminHttp.request(url, opts || {});
    }
    return fetch(url, Object.assign({
      method: 'GET',
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }, opts || {})).then(async function(r){
      var json = {};
      try {
        json = await r.json();
      } catch (e) {
        json = {};
      }
      if (!r.ok || !json || json.ok === false) {
        var err = new Error((json && (json.message || json.msg || json.error)) || ('HTTP ' + r.status));
        err.payload = json;
        err.status = r.status;
        throw err;
      }
      return json;
    });
  }

  function errorMessage(err){
    if (window.HGAdminHttp && typeof window.HGAdminHttp.errorMessage === 'function') {
      return window.HGAdminHttp.errorMessage(err);
    }
    return (err && (err.message || err.error)) ? (err.message || err.error) : 'Error';
  }

  function bindRowButtons(scope){
    Array.prototype.forEach.call((scope || document).querySelectorAll('button[data-edit]'), function(btn){
      btn.onclick = function(){
        openEdit(parseInt(btn.getAttribute('data-edit') || '0', 10) || 0);
      };
    });
    Array.prototype.forEach.call((scope || document).querySelectorAll('button[data-del]'), function(btn){
      btn.onclick = function(){
        openDelete(parseInt(btn.getAttribute('data-del') || '0', 10) || 0);
      };
    });
  }

  function summaryRow(r){
    var eventLabel = '-';
    if (Number(r.death_timeline_event_id || 0) > 0) {
      eventLabel = '#' + Number(r.death_timeline_event_id || 0);
      if (String(r.event_date || '').trim()) eventLabel += ' [' + String(r.event_date || '').trim() + ']';
      if (String(r.event_title || '').trim()) eventLabel += ' ' + String(r.event_title || '').trim();
    }
    return ''
      + '<tr>'
      + '<td><strong class="adm-color-accent">' + Number(r.character_id || 0) + '</strong></td>'
      + '<td>' + esc(r.character_name || '') + '</td>'
      + '<td>' + esc(r.status_label || '-') + '</td>'
      + '<td>' + esc(r.death_type || '-') + '</td>'
      + '<td>' + esc(r.death_date || '-') + '</td>'
      + '<td>' + esc((r.killer_name || '').trim() ? r.killer_name : '-') + '</td>'
      + '<td>' + esc(eventLabel) + '</td>'
      + '<td>' + Number(r.narrative_weight || 1) + '</td>'
      + '<td>'
      + '<button class="btn" type="button" data-edit="' + Number(r.character_id || 0) + '">Editar</button> '
      + '<button class="btn btn-red" type="button" data-del="' + Number(r.character_id || 0) + '">Borrar</button>'
      + '</td>'
      + '</tr>';
  }

  function renderRows(rows, rowMap){
    ACD_ROWS = rowMap || {};
    if (!rows || !rows.length) {
      tbody.innerHTML = '<tr><td colspan="9" class="adm-color-muted">(Sin resultados)</td></tr>';
      return;
    }
    var html = '';
    rows.forEach(function(r){ html += summaryRow(r); });
    tbody.innerHTML = html;
    bindRowButtons(tbody);
  }

  function runSearch(forceAjax){
    var term = (filterInput.value || '').trim();
    if (!forceAjax && term === '') {
      tbody.innerHTML = initialHtml;
      if (pager) pager.style.display = '';
      bindRowButtons(tbody);
      return Promise.resolve();
    }
    var mySeq = ++reqSeq;
    if (pager) pager.style.display = 'none';
    return request(endpointUrl('search', term), { method: 'GET' })
      .then(function(data){
        if (mySeq !== reqSeq) return;
        if (!data || data.ok !== true) return;
        renderRows(data.rows || [], data.rowMap || {});
      })
      .catch(function(){
        if (!forceAjax && term === '' && pager) pager.style.display = '';
      });
  }

  function openCreate(){
    document.getElementById('acdModalTitle').textContent = 'Nueva muerte';
    document.getElementById('acd_mode').value = 'create';
    document.getElementById('acd_fixed_character_id').value = '0';
    var charSel = document.getElementById('acd_character_id');
    charSel.disabled = false;
    charSel.value = '0';
    document.getElementById('acd_death_type').value = 'asesinato';
    document.getElementById('acd_killer_id').value = '0';
    document.getElementById('acd_event_id').value = '0';
    document.getElementById('acd_death_date').value = '';
    document.getElementById('acd_weight').value = '1';
    document.getElementById('acd_description').value = '';
    modal.style.display = 'flex';
  }

  function openEdit(characterId){
    var row = ACD_ROWS[String(characterId)];
    if (!row) return;
    document.getElementById('acdModalTitle').textContent = 'Editar muerte';
    document.getElementById('acd_mode').value = 'update';
    document.getElementById('acd_fixed_character_id').value = String(characterId);
    var charSel = document.getElementById('acd_character_id');
    charSel.value = String(characterId);
    charSel.disabled = true;
    document.getElementById('acd_death_type').value = String(row.death_type || 'asesinato');
    document.getElementById('acd_killer_id').value = String(Number(row.killer_character_id || 0));
    document.getElementById('acd_event_id').value = String(Number(row.death_timeline_event_id || 0));
    document.getElementById('acd_death_date').value = String(row.death_date || '');
    document.getElementById('acd_weight').value = String(Number(row.narrative_weight || 1));
    document.getElementById('acd_description').value = String(row.death_description || '');
    modal.style.display = 'flex';
  }

  function openDelete(characterId){
    document.getElementById('acd_del_character_id').value = String(characterId || 0);
    delModal.style.display = 'flex';
  }

  function closeModals(){
    modal.style.display = 'none';
    delModal.style.display = 'none';
  }

  document.getElementById('btnNewDeath').addEventListener('click', openCreate);
  document.getElementById('btnAcdCancel').addEventListener('click', closeModals);
  document.getElementById('btnAcdDelCancel').addEventListener('click', closeModals);
  modal.addEventListener('click', function(e){ if (e.target === modal) closeModals(); });
  delModal.addEventListener('click', function(e){ if (e.target === delModal) closeModals(); });
  bindRowButtons(document);

  form.addEventListener('submit', function(ev){
    ev.preventDefault();
    var fixedCharId = parseInt(document.getElementById('acd_fixed_character_id').value || '0', 10) || 0;
    var selectedCharId = parseInt(document.getElementById('acd_character_id').value || '0', 10) || 0;
    var characterId = fixedCharId > 0 ? fixedCharId : selectedCharId;
    if (!characterId) {
      alert('Selecciona un personaje.');
      return;
    }

    var fd = new FormData();
    fd.set('csrf', String(window.ADMIN_CSRF_TOKEN || ''));
    fd.set('ajax', 'save_character_death');
    fd.set('character_id', String(characterId));
    fd.set('death_type', String(document.getElementById('acd_death_type').value || 'asesinato'));
    fd.set('killer_character_id', String(parseInt(document.getElementById('acd_killer_id').value || '0', 10) || 0));
    fd.set('death_timeline_event_id', String(parseInt(document.getElementById('acd_event_id').value || '0', 10) || 0));
    fd.set('death_date', String(document.getElementById('acd_death_date').value || ''));
    fd.set('narrative_weight', String(parseInt(document.getElementById('acd_weight').value || '1', 10) || 1));
    fd.set('death_description', String(document.getElementById('acd_description').value || ''));

    request(endpointUrl('', ''), {
      method: 'POST',
      body: fd,
      loadingEl: form
    }).then(function(payload){
      closeModals();
      if (window.HGAdminHttp && window.HGAdminHttp.notify) {
        window.HGAdminHttp.notify((payload && (payload.message || payload.msg)) || 'Guardado', 'ok');
      }
      return runSearch(true);
    }).catch(function(err){
      alert(errorMessage(err));
    });
  });

  delForm.addEventListener('submit', function(ev){
    ev.preventDefault();
    var characterId = parseInt(document.getElementById('acd_del_character_id').value || '0', 10) || 0;
    if (!characterId) return;

    var fd = new FormData();
    fd.set('csrf', String(window.ADMIN_CSRF_TOKEN || ''));
    fd.set('ajax', 'delete_character_death');
    fd.set('character_id', String(characterId));

    request(endpointUrl('', ''), {
      method: 'POST',
      body: fd,
      loadingEl: delForm
    }).then(function(payload){
      closeModals();
      if (window.HGAdminHttp && window.HGAdminHttp.notify) {
        window.HGAdminHttp.notify((payload && (payload.message || payload.msg)) || 'Eliminado', 'ok');
      }
      return runSearch(true);
    }).catch(function(err){
      alert(errorMessage(err));
    });
  });

  filterInput.addEventListener('input', function(){
    clearTimeout(timer);
    timer = setTimeout(function(){ runSearch(false); }, 180);
  });
  if (filterBtn) {
    filterBtn.addEventListener('click', function(){ runSearch(false); });
  }
  if (filterForm) {
    filterForm.addEventListener('submit', function(e){
      e.preventDefault();
      runSearch(false);
    });
  }
})();
</script>
<?php endif; ?>

<?php admin_panel_close(); ?>
