<?php
if (!isset($link) || !$link) {
    die('Error de conexion a la base de datos.');
}
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
if (method_exists($link, 'set_charset')) {
    $link->set_charset('utf8mb4');
} else {
    mysqli_set_charset($link, 'utf8mb4');
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
include_once(__DIR__ . '/../../helpers/pretty.php');
include_once(__DIR__ . '/../../partials/admin/admin_styles.php');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function hg_admin_col_exists(mysqli $link, string $table, string $column): bool {
    static $cache = [];
    $key = $table . ':' . $column;
    if (isset($cache[$key])) return $cache[$key];

    $ok = false;
    if ($st = $link->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?")) {
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

function hg_admin_has_table(mysqli $link, string $table): bool {
    $table = str_replace('`', '', $table);
    $rs = $link->query("SHOW TABLES LIKE '" . $link->real_escape_string($table) . "'");
    if (!$rs) return false;
    $ok = ($rs->num_rows > 0);
    $rs->close();
    return $ok;
}

function hg_admin_pick_deaths_table(mysqli $link): string {
    if (hg_admin_has_table($link, 'fact_characters_deaths')) return 'fact_characters_deaths';
    if (hg_admin_has_table($link, 'fact_characters_death')) return 'fact_characters_death';
    return '';
}

function hg_admin_sync_death_ground_truth(mysqli $link, string $deathsTable, int $eventId, ?string $deathDate): void {
    if ($deathsTable === '' || $eventId <= 0) return;

    $linkedDeaths = 0;
    if ($stCount = $link->prepare("SELECT COUNT(*) FROM `{$deathsTable}` WHERE death_timeline_event_id = ?")) {
        $stCount->bind_param('i', $eventId);
        $stCount->execute();
        $stCount->bind_result($linkedDeaths);
        $stCount->fetch();
        $stCount->close();
    }
    if ((int)$linkedDeaths <= 0) return;

    if ($stDeaths = $link->prepare("UPDATE `{$deathsTable}` SET death_date = ? WHERE death_timeline_event_id = ?")) {
        $stDeaths->bind_param('si', $deathDate, $eventId);
        $stDeaths->execute();
        $stDeaths->close();
    }

    $eventDate = ($deathDate !== null && $deathDate !== '') ? $deathDate : '1000-01-01';
    $precision = ($deathDate !== null && $deathDate !== '') ? 'day' : 'unknown';
    $dateNote = ($deathDate !== null && $deathDate !== '')
        ? null
        : 'Fecha de muerte no especificada (sincronizado desde muertes).';

    if ($stEv = $link->prepare("
        UPDATE fact_timeline_events
        SET
            event_type_id = 5,
            event_date = ?,
            sort_date = ?,
            date_precision = ?,
            date_note = ?,
            updated_at = NOW()
        WHERE id = ?
        LIMIT 1
    ")) {
        $stEv->bind_param('ssssi', $eventDate, $eventDate, $precision, $dateNote, $eventId);
        $stEv->execute();
        $stEv->close();
    }
}

$ADMIN_CSRF_SESSION_KEY = 'csrf_admin_timelines';
if (function_exists('hg_admin_ensure_csrf_token')) {
    $CSRF = hg_admin_ensure_csrf_token($ADMIN_CSRF_SESSION_KEY);
} else {
    if (empty($_SESSION[$ADMIN_CSRF_SESSION_KEY])) {
        $_SESSION[$ADMIN_CSRF_SESSION_KEY] = bin2hex(random_bytes(16));
    }
    $CSRF = $_SESSION[$ADMIN_CSRF_SESSION_KEY];
}

$hasTypeSortOrder = hg_admin_col_exists($link, 'dim_timeline_events_types', 'sort_order');
$hasChronicleSortOrder = hg_admin_col_exists($link, 'dim_chronicles', 'sort_order');
$hasRealitySortOrder = hg_admin_col_exists($link, 'dim_realities', 'sort_order');
$hasChronBridgeSortOrder = hg_admin_col_exists($link, 'bridge_timeline_events_chronicles', 'sort_order');

$eventTypes = [];
$eventTypeById = [];
$eventTypeByPretty = [];
$defaultEventTypeId = 0;

$eventTypeSortSelect = $hasTypeSortOrder ? 'sort_order' : '0';
$eventTypeOrderSql = $hasTypeSortOrder ? 'sort_order ASC, name ASC' : 'name ASC, id ASC';
if ($rs = $link->query("SELECT id, pretty_id, name, {$eventTypeSortSelect} AS sort_order, is_active FROM dim_timeline_events_types ORDER BY {$eventTypeOrderSql}")) {
    while ($row = $rs->fetch_assoc()) {
        $eventTypes[] = $row;
        $eventTypeById[(int)$row['id']] = $row;
        $eventTypeByPretty[(string)$row['pretty_id']] = $row;
        if ((string)$row['pretty_id'] === 'evento') {
            $defaultEventTypeId = (int)$row['id'];
        }
    }
    $rs->close();
}
if ($defaultEventTypeId <= 0 && !empty($eventTypes)) {
    $defaultEventTypeId = (int)$eventTypes[0]['id'];
}
$deathsTable = hg_admin_pick_deaths_table($link);

$chronicles = [];
$chronicleById = [];
$chronicleOrderSql = $hasChronicleSortOrder ? 'sort_order ASC, name ASC' : 'name ASC, id ASC';
if ($rs = $link->query("SELECT id, name FROM dim_chronicles ORDER BY {$chronicleOrderSql}")) {
    while ($row = $rs->fetch_assoc()) {
        $chronicles[] = $row;
        $chronicleById[(int)$row['id']] = (string)$row['name'];
    }
    $rs->close();
}

$realities = [];
$realityOrderSql = $hasRealitySortOrder ? 'sort_order ASC, name ASC' : 'name ASC, id ASC';
if ($rs = $link->query("SELECT id, name FROM dim_realities ORDER BY {$realityOrderSql}")) {
    while ($row = $rs->fetch_assoc()) {
        $realities[] = $row;
    }
    $rs->close();
}

$chapters = [];
if ($rs = $link->query("SELECT c.id, c.name, s.season_number, s.season_kind, c.chapter_number FROM dim_chapters c LEFT JOIN dim_seasons s ON s.id = c.season_id ORDER BY COALESCE(s.sort_order, 9999) ASC, c.chapter_number ASC, c.id ASC")) {
    while ($row = $rs->fetch_assoc()) {
        $chapters[] = $row;
    }
    $rs->close();
}

$characters = [];
if ($rs = $link->query("SELECT p.id, p.name, COALESCE(ch.name, '') AS chronicle_name FROM fact_characters p LEFT JOIN dim_chronicles ch ON ch.id = p.chronicle_id ORDER BY p.name ASC, p.id ASC")) {
    while ($row = $rs->fetch_assoc()) {
        $characters[] = $row;
    }
    $rs->close();
}

function hg_admin_parse_int_ids($raw): array {
    $values = [];
    if (is_array($raw)) {
        $values = $raw;
    } else {
        $raw = trim((string)$raw);
        if ($raw !== '') {
            $values = preg_split('/\s*,\s*/', $raw);
        }
    }

    $out = [];
    foreach ($values as $v) {
        if (is_int($v) || (is_string($v) && preg_match('/^\d+$/', $v))) {
            $id = (int)$v;
            if ($id > 0) $out[] = $id;
        }
    }
    $out = array_values(array_unique($out));
    return $out;
}

function hg_admin_sync_bridge_ids(mysqli $link, string $table, string $refCol, int $eventId, array $ids): void {
    if ($eventId <= 0) return;
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $refCol = preg_replace('/[^a-zA-Z0-9_]/', '', $refCol);
    if ($table === '' || $refCol === '') return;
    $hasSortOrder = hg_admin_col_exists($link, $table, 'sort_order');

    if ($stDel = $link->prepare("DELETE FROM {$table} WHERE event_id = ?")) {
        $stDel->bind_param('i', $eventId);
        $stDel->execute();
        $stDel->close();
    }

    if (empty($ids)) return;

    if ($hasSortOrder) {
        $sqlIns = "INSERT INTO {$table} (event_id, {$refCol}, sort_order) VALUES (?, ?, ?)";
        if ($stIns = $link->prepare($sqlIns)) {
            foreach (array_values($ids) as $i => $refId) {
                $sortOrder = (int)$i;
                $stIns->bind_param('iii', $eventId, $refId, $sortOrder);
                $stIns->execute();
            }
            $stIns->close();
        }
    } else {
        $sqlIns = "INSERT INTO {$table} (event_id, {$refCol}) VALUES (?, ?)";
        if ($stIns = $link->prepare($sqlIns)) {
            foreach (array_values($ids) as $refId) {
                $stIns->bind_param('ii', $eventId, $refId);
                $stIns->execute();
            }
            $stIns->close();
        }
    }
}

function hg_admin_sync_bridge_characters(mysqli $link, int $eventId, array $characterIds): void {
    if ($eventId <= 0) return;
    $hasSortOrder = hg_admin_col_exists($link, 'bridge_timeline_events_characters', 'sort_order');
    $hasRoleLabel = hg_admin_col_exists($link, 'bridge_timeline_events_characters', 'role_label');

    if ($stDel = $link->prepare("DELETE FROM bridge_timeline_events_characters WHERE event_id = ?")) {
        $stDel->bind_param('i', $eventId);
        $stDel->execute();
        $stDel->close();
    }

    if (empty($characterIds)) return;

    if ($hasRoleLabel && $hasSortOrder) {
        $sqlIns = "INSERT INTO bridge_timeline_events_characters (event_id, character_id, role_label, sort_order) VALUES (?, ?, NULL, ?)";
        if ($stIns = $link->prepare($sqlIns)) {
            foreach (array_values($characterIds) as $i => $characterId) {
                $sortOrder = (int)$i;
                $stIns->bind_param('iii', $eventId, $characterId, $sortOrder);
                $stIns->execute();
            }
            $stIns->close();
        }
        return;
    }

    if ($hasRoleLabel && !$hasSortOrder) {
        $sqlIns = "INSERT INTO bridge_timeline_events_characters (event_id, character_id, role_label) VALUES (?, ?, NULL)";
        if ($stIns = $link->prepare($sqlIns)) {
            foreach (array_values($characterIds) as $characterId) {
                $stIns->bind_param('ii', $eventId, $characterId);
                $stIns->execute();
            }
            $stIns->close();
        }
        return;
    }

    if (!$hasRoleLabel && $hasSortOrder) {
        $sqlIns = "INSERT INTO bridge_timeline_events_characters (event_id, character_id, sort_order) VALUES (?, ?, ?)";
        if ($stIns = $link->prepare($sqlIns)) {
            foreach (array_values($characterIds) as $i => $characterId) {
                $sortOrder = (int)$i;
                $stIns->bind_param('iii', $eventId, $characterId, $sortOrder);
                $stIns->execute();
            }
            $stIns->close();
        }
        return;
    }

    $sqlIns = "INSERT INTO bridge_timeline_events_characters (event_id, character_id) VALUES (?, ?)";
    if ($stIns = $link->prepare($sqlIns)) {
        foreach (array_values($characterIds) as $characterId) {
            $stIns->bind_param('ii', $eventId, $characterId);
            $stIns->execute();
        }
        $stIns->close();
    }
}

function hg_admin_get_bridge_ids(mysqli $link, string $table, string $refCol, int $eventId): array {
    $ids = [];
    if ($eventId <= 0) return $ids;
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $refCol = preg_replace('/[^a-zA-Z0-9_]/', '', $refCol);
    if ($table === '' || $refCol === '') return $ids;
    $orderBy = hg_admin_col_exists($link, $table, 'sort_order') ? 'sort_order ASC, id ASC' : 'id ASC';
    $sql = "SELECT {$refCol} AS ref_id FROM {$table} WHERE event_id = ? ORDER BY {$orderBy}";
    if ($st = $link->prepare($sql)) {
        $st->bind_param('i', $eventId);
        $st->execute();
        $rs = $st->get_result();
        while ($rs && ($row = $rs->fetch_assoc())) {
            $rid = (int)($row['ref_id'] ?? 0);
            if ($rid > 0) $ids[] = $rid;
        }
        $st->close();
    }
    return $ids;
}

$getEventLinks = function(int $eventId) use ($link): array {
    return [
        'chronicle_ids' => hg_admin_get_bridge_ids($link, 'bridge_timeline_events_chronicles', 'chronicle_id', $eventId),
        'character_ids' => hg_admin_get_bridge_ids($link, 'bridge_timeline_events_characters', 'character_id', $eventId),
        'chapter_ids' => hg_admin_get_bridge_ids($link, 'bridge_timeline_events_chapters', 'chapter_id', $eventId),
        'reality_ids' => hg_admin_get_bridge_ids($link, 'bridge_timeline_events_realities', 'reality_id', $eventId),
    ];
};

$chronicleConcatOrderSql = ($hasChronBridgeSortOrder ? 'bec.sort_order ASC, ' : '')
    . ($hasChronicleSortOrder ? 'c.sort_order ASC, ' : '')
    . 'c.name ASC';
$primaryChronicleExpr = $hasChronBridgeSortOrder
    ? "COALESCE(MIN(CASE WHEN bec.sort_order = 0 THEN bec.chronicle_id END), MIN(bec.chronicle_id), 0)"
    : "COALESCE(MIN(bec.chronicle_id), 0)";

$getEventRow = function(int $eventId) use ($link, $getEventLinks, $chronicleConcatOrderSql, $primaryChronicleExpr): ?array {
    if ($eventId <= 0) return null;

    $sql = "
        SELECT
            e.id,
            e.pretty_id,
            e.title,
            e.event_date,
            e.date_precision,
            e.date_note,
            e.location,
            e.source,
            e.description,
            e.is_active,
            e.event_type_id,
            COALESCE(t.name, 'Evento') AS type_name,
            COALESCE(t.pretty_id, 'evento') AS type_slug,
            {$primaryChronicleExpr} AS primary_chronicle_id,
            COALESCE(
                NULLIF(GROUP_CONCAT(DISTINCT c.name ORDER BY {$chronicleConcatOrderSql} SEPARATOR ' | '), ''),
                e.timeline,
                ''
            ) AS chronicle_line
        FROM fact_timeline_events e
        LEFT JOIN dim_timeline_events_types t ON t.id = e.event_type_id
        LEFT JOIN bridge_timeline_events_chronicles bec ON bec.event_id = e.id
        LEFT JOIN dim_chronicles c ON c.id = bec.chronicle_id
        WHERE e.id = ?
        GROUP BY
            e.id, e.pretty_id, e.title, e.event_date, e.date_precision, e.date_note,
            e.location, e.source, e.description, e.is_active, e.event_type_id,
            t.name, t.pretty_id
        LIMIT 1
    ";

    $st = $link->prepare($sql);
    if (!$st) return null;
    $st->bind_param('i', $eventId);
    $st->execute();
    $rs = $st->get_result();
    $row = $rs ? $rs->fetch_assoc() : null;
    $st->close();
    if (!$row) return null;

    $links = $getEventLinks($eventId);
    $row['chronicle_ids'] = implode(',', $links['chronicle_ids']);
    $row['character_ids'] = implode(',', $links['character_ids']);
    $row['chapter_ids'] = implode(',', $links['chapter_ids']);
    $row['reality_ids'] = implode(',', $links['reality_ids']);
    $row['chronicles_count'] = count($links['chronicle_ids']);
    $row['characters_count'] = count($links['character_ids']);
    $row['chapters_count'] = count($links['chapter_ids']);
    $row['realities_count'] = count($links['reality_ids']);

    return $row;
};

$isAjax = isset($_GET['ajax']) && (string)$_GET['ajax'] === '1';
if ($isAjax) {
    header('Content-Type: application/json; charset=UTF-8');
    if (function_exists('hg_admin_require_session')) {
        hg_admin_require_session(true);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        if (function_exists('hg_admin_json_error')) hg_admin_json_error('Metodo invalido', 405);
        echo json_encode(['ok' => false, 'error' => 'Metodo invalido']);
        exit;
    }

    $payload = function_exists('hg_admin_read_json_payload') ? hg_admin_read_json_payload() : [];
    $req = function(string $key, $default = '') use ($payload) {
        if (isset($_POST[$key])) return $_POST[$key];
        if (is_array($payload) && array_key_exists($key, $payload)) return $payload[$key];
        return $default;
    };

    $csrfToken = function_exists('hg_admin_extract_csrf_token')
        ? hg_admin_extract_csrf_token($payload)
        : (string)$req('csrf', '');
    $csrfOk = function_exists('hg_admin_csrf_valid')
        ? hg_admin_csrf_valid($csrfToken, $ADMIN_CSRF_SESSION_KEY)
        : (is_string($csrfToken) && $csrfToken !== '' && isset($_SESSION[$ADMIN_CSRF_SESSION_KEY]) && hash_equals($_SESSION[$ADMIN_CSRF_SESSION_KEY], $csrfToken));

    if (!$csrfOk) {
        if (function_exists('hg_admin_json_error')) hg_admin_json_error('CSRF invalido. Recarga la pagina.', 400);
        echo json_encode(['ok' => false, 'error' => 'CSRF invalido. Recarga la pagina.']);
        exit;
    }

    $action = (string)$req('action', '');

    if ($action === 'delete_event') {
        $eventId = (int)$req('event_id', 0);
        if ($eventId <= 0) {
            if (function_exists('hg_admin_json_error')) hg_admin_json_error('ID de evento invalido', 400);
            echo json_encode(['ok' => false, 'error' => 'ID de evento invalido']);
            exit;
        }

        $ok = false;
        if ($st = $link->prepare('DELETE FROM fact_timeline_events WHERE id = ?')) {
            $st->bind_param('i', $eventId);
            $ok = $st->execute();
            $st->close();
        }

        if (!$ok) {
            if (function_exists('hg_admin_json_error')) hg_admin_json_error('No se pudo eliminar el evento.', 500);
            echo json_encode(['ok' => false, 'error' => 'No se pudo eliminar el evento.']);
            exit;
        }

        if (function_exists('hg_admin_json_success')) {
            hg_admin_json_success(['event_id' => $eventId], 'Evento eliminado.');
        }
        echo json_encode(['ok' => true, 'message' => 'Evento eliminado.', 'data' => ['event_id' => $eventId]]);
        exit;
    }

    if ($action === 'save_event') {
        $id = (int)$req('id', 0);
        $title = trim((string)$req('title', ''));
        $eventDate = trim((string)$req('event_date', ''));
        $datePrecision = trim((string)$req('date_precision', 'day'));
        $dateNote = trim((string)$req('date_note', ''));
        $description = trim((string)$req('description', ''));
        $location = trim((string)$req('location', ''));
        $source = trim((string)$req('source', ''));
        $eventTypeId = (int)$req('event_type_id', 0);
        $chronicleIds = hg_admin_parse_int_ids($req('chronicle_ids', $req('chronicle_id', [])));
        $characterIds = hg_admin_parse_int_ids($req('character_ids', []));
        $chapterIds = hg_admin_parse_int_ids($req('chapter_ids', []));
        $realityIds = hg_admin_parse_int_ids($req('reality_ids', []));
        $isActive = (int)$req('is_active', 1) === 1 ? 1 : 0;

        $allowedPrecision = ['day', 'month', 'year', 'approx', 'unknown'];
        if (!in_array($datePrecision, $allowedPrecision, true)) {
            $datePrecision = 'day';
        }

        if ($eventTypeId <= 0 || !isset($eventTypeById[$eventTypeId])) {
            $eventTypeId = $defaultEventTypeId;
        }

        if ($title === '') {
            if (function_exists('hg_admin_json_error')) hg_admin_json_error('El titulo es obligatorio.', 400);
            echo json_encode(['ok' => false, 'error' => 'El titulo es obligatorio.']);
            exit;
        }
        if ($eventDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate)) {
            if (function_exists('hg_admin_json_error')) hg_admin_json_error('La fecha del evento es obligatoria.', 400);
            echo json_encode(['ok' => false, 'error' => 'La fecha del evento es obligatoria.']);
            exit;
        }

        $primaryChronicleId = !empty($chronicleIds) ? (int)$chronicleIds[0] : 0;
        $timelineLegacy = null;
        if ($primaryChronicleId > 0 && isset($chronicleById[$primaryChronicleId])) {
            $timelineLegacy = $chronicleById[$primaryChronicleId];
        }

        $sortDate = $eventDate;
        $dateNoteSql = ($dateNote !== '') ? $dateNote : null;
        $timelineLegacySql = ($timelineLegacy !== null && trim($timelineLegacy) !== '') ? $timelineLegacy : null;

        $savedId = 0;
        try {
            $link->begin_transaction();

            if ($id > 0) {
                $sql = "
                    UPDATE fact_timeline_events
                    SET
                        title = ?,
                        event_date = ?,
                        date_precision = ?,
                        date_note = ?,
                        sort_date = ?,
                        description = ?,
                        location = ?,
                        source = ?,
                        event_type_id = ?,
                        timeline = ?,
                        is_active = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ";
                $st = $link->prepare($sql);
                $st->bind_param(
                    'ssssssssisii',
                    $title,
                    $eventDate,
                    $datePrecision,
                    $dateNoteSql,
                    $sortDate,
                    $description,
                    $location,
                    $source,
                    $eventTypeId,
                    $timelineLegacySql,
                    $isActive,
                    $id
                );
                $ok = $st->execute();
                $st->close();
                if (!$ok) {
                    throw new RuntimeException('No se pudo actualizar el evento.');
                }
                $savedId = $id;
            } else {
                $sql = "
                    INSERT INTO fact_timeline_events
                    (title, event_date, date_precision, date_note, sort_date, description, location, source, event_type_id, timeline, is_active)
                    VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ";
                $st = $link->prepare($sql);
                $st->bind_param(
                    'ssssssssisi',
                    $title,
                    $eventDate,
                    $datePrecision,
                    $dateNoteSql,
                    $sortDate,
                    $description,
                    $location,
                    $source,
                    $eventTypeId,
                    $timelineLegacySql,
                    $isActive
                );
                $ok = $st->execute();
                $savedId = (int)$link->insert_id;
                $st->close();
                if (!$ok || $savedId <= 0) {
                    throw new RuntimeException('No se pudo crear el evento.');
                }
                hg_update_pretty_id_if_exists($link, 'fact_timeline_events', $savedId, $title);
            }

            hg_admin_sync_bridge_ids($link, 'bridge_timeline_events_chronicles', 'chronicle_id', $savedId, $chronicleIds);
            hg_admin_sync_bridge_characters($link, $savedId, $characterIds);
            hg_admin_sync_bridge_ids($link, 'bridge_timeline_events_chapters', 'chapter_id', $savedId, $chapterIds);
            hg_admin_sync_bridge_ids($link, 'bridge_timeline_events_realities', 'reality_id', $savedId, $realityIds);

            $syncDeathDate = ($datePrecision === 'unknown' || $eventDate === '1000-01-01') ? null : $eventDate;
            hg_admin_sync_death_ground_truth($link, $deathsTable, $savedId, $syncDeathDate);

            $link->commit();
        } catch (Throwable $e) {
            $link->rollback();
            if (function_exists('hg_admin_json_error')) hg_admin_json_error($e->getMessage(), 500);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
            exit;
        }

        $saved = $getEventRow($savedId);
        if (!$saved) {
            if (function_exists('hg_admin_json_error')) hg_admin_json_error('Evento guardado pero no se pudo leer para refresco.', 500);
            echo json_encode(['ok' => false, 'error' => 'Evento guardado pero no se pudo leer para refresco.']);
            exit;
        }

        if (function_exists('hg_admin_json_success')) {
            hg_admin_json_success($saved, $id > 0 ? 'Evento actualizado.' : 'Evento creado.');
        }
        echo json_encode([
            'ok' => true,
            'message' => $id > 0 ? 'Evento actualizado.' : 'Evento creado.',
            'data' => $saved,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (function_exists('hg_admin_json_error')) {
        hg_admin_json_error('Accion no valida', 400);
    }
    echo json_encode(['ok' => false, 'error' => 'Accion no valida']);
    exit;
}

$events = [];
$sql = "
    SELECT
        e.id,
        e.pretty_id,
        e.title,
        e.event_date,
        e.date_precision,
        e.date_note,
        e.location,
        e.source,
        e.description,
        e.is_active,
        e.event_type_id,
        COALESCE(t.name, 'Evento') AS type_name,
        COALESCE(t.pretty_id, 'evento') AS type_slug,
        {$primaryChronicleExpr} AS primary_chronicle_id,
        COALESCE(
            NULLIF(GROUP_CONCAT(DISTINCT c.name ORDER BY {$chronicleConcatOrderSql} SEPARATOR ' | '), ''),
            e.timeline,
            ''
        ) AS chronicle_line
    FROM fact_timeline_events e
    LEFT JOIN dim_timeline_events_types t ON t.id = e.event_type_id
    LEFT JOIN bridge_timeline_events_chronicles bec ON bec.event_id = e.id
    LEFT JOIN dim_chronicles c ON c.id = bec.chronicle_id
    GROUP BY
        e.id, e.pretty_id, e.title, e.event_date, e.date_precision, e.date_note,
        e.location, e.source, e.description, e.is_active, e.event_type_id,
        t.name, t.pretty_id
    ORDER BY COALESCE(e.sort_date, e.event_date) DESC, e.id DESC
";
if ($rs = $link->query($sql)) {
    while ($row = $rs->fetch_assoc()) {
        $events[] = $row;
    }
    $rs->close();
}

foreach ($events as &$row) {
    $eventId = (int)($row['id'] ?? 0);
    if ($eventId <= 0) {
        $row['chronicle_ids'] = '';
        $row['character_ids'] = '';
        $row['chapter_ids'] = '';
        $row['reality_ids'] = '';
        $row['chronicles_count'] = 0;
        $row['characters_count'] = 0;
        $row['chapters_count'] = 0;
        $row['realities_count'] = 0;
        continue;
    }
    $links = $getEventLinks($eventId);
    $row['chronicle_ids'] = implode(',', $links['chronicle_ids']);
    $row['character_ids'] = implode(',', $links['character_ids']);
    $row['chapter_ids'] = implode(',', $links['chapter_ids']);
    $row['reality_ids'] = implode(',', $links['reality_ids']);
    $row['chronicles_count'] = count($links['chronicle_ids']);
    $row['characters_count'] = count($links['character_ids']);
    $row['chapters_count'] = count($links['chapter_ids']);
    $row['realities_count'] = count($links['reality_ids']);
}
unset($row);

$actions = '<span class="adm-flex-right-wrap-8">'
    . '<label class="adm-text-left">Filtro rapido <input class="inp" type="text" id="quickFilter" placeholder="Titulo o fuente..."></label>'
    . '<label class="adm-text-left">Tipo <select id="typeFilter" class="select"><option value="">Todos</option>';
foreach ($eventTypes as $typeRow) {
    $actions .= '<option value="' . (int)$typeRow['id'] . '">' . h((string)$typeRow['name']) . '</option>';
}
$actions .= '</select></label>'
    . '<label class="adm-text-left">Estado <select id="activeFilter" class="select"><option value="">Todos</option><option value="1">Activos</option><option value="0">Inactivos</option></select></label>'
    . '<button class="btn btn-green" type="button" onclick="openEventModal(0)">+ Nuevo evento</button>'
    . '</span>';

admin_panel_open('Linea temporal', $actions);
?>
<style>
.admin-multi{
    min-height: 120px;
}
.adm-help{
    display:block;
    margin-top:4px;
    color:#8fb1d9;
    font-size:10px;
}
.adm-rel-row{
    display:flex;
    gap:8px;
    margin-bottom:8px;
    align-items:center;
    flex-wrap:wrap;
}
.adm-rel-select{
    max-width:420px;
}
.adm-rel-box{
    border:1px solid #1d3f88;
    background:#071544;
    border-radius:6px;
    padding:8px;
    min-height:42px;
}
.adm-rel-box ul{
    margin:0;
    padding-left:18px;
}
.adm-rel-box li{
    margin:0 0 6px;
}
</style>

<table class="table" id="eventsTable">
    <thead>
        <tr>
            <th>Fecha</th>
            <th>Titulo</th>
            <th>Tipo</th>
            <th>Cronica</th>
            <th>Vinculos</th>
            <th>Estado</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody></tbody>
</table>
<div id="eventsPager" class="pager adm-justify-end"></div>

<div class="chap-modal-back" id="eventModalBack" aria-hidden="true">
    <div class="chap-modal adm-modal-980">
        <h3 id="eventModalTitle">Evento</h3>
        <form method="post" action="/talim?s=admin_timelines" id="eventForm">
            <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
            <input type="hidden" name="id" id="f_id" value="0">

            <div class="grid">
                <label>Titulo
                    <input class="inp" type="text" name="title" id="f_title" required>
                </label>
                <label>Fecha del evento
                    <input class="inp" type="date" name="event_date" id="f_event_date" required>
                </label>
                <label>Tipo
                    <select class="select" name="event_type_id" id="f_event_type_id" required>
                        <?php foreach ($eventTypes as $typeRow): ?>
                        <option value="<?= (int)$typeRow['id'] ?>" <?= ((int)$typeRow['id'] === $defaultEventTypeId ? 'selected' : '') ?>><?= h((string)$typeRow['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Precision fecha
                    <select class="select" name="date_precision" id="f_date_precision">
                        <option value="day">Dia exacto</option>
                        <option value="month">Mes aproximado</option>
                        <option value="year">Ano aproximado</option>
                        <option value="approx">Aproximada</option>
                        <option value="unknown">Desconocida</option>
                    </select>
                </label>
                <label>Nota de fecha
                    <input class="inp" type="text" name="date_note" id="f_date_note" maxlength="120" placeholder="Ej. mediados de marzo">
                </label>
                <label class="field-full">Cronicas vinculadas
                    <select class="select admin-multi" name="chronicle_ids[]" id="f_chronicle_ids" multiple size="6">
                        <?php foreach ($chronicles as $chronicleRow): ?>
                        <option value="<?= (int)$chronicleRow['id'] ?>"><?= h((string)$chronicleRow['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="adm-help">Seleccion multiple. La primera cronica se usa como referencia legacy.</small>
                </label>
                <label class="field-full">Personajes vinculados
                    <input type="hidden" id="f_character_ids_csv" value="">
                    <div class="adm-rel-row">
                        <select id="characterSelect" class="select adm-rel-select">
                            <option value="">Seleccionar personaje</option>
                            <?php foreach ($characters as $characterRow): ?>
                            <option value="<?= (int)$characterRow['id'] ?>"><?= h((string)$characterRow['name']) ?> (#<?= (int)$characterRow['id'] ?><?= ((string)($characterRow['chronicle_name'] ?? '') !== '' ? ' - "' . h((string)$characterRow['chronicle_name']) . '"' : '') ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn" type="button" id="btnAddCharacterRel">Agregar</button>
                    </div>
                    <div id="relationsCharactersList" class="adm-rel-box small">Sin personajes vinculados.</div>
                </label>
                <label class="field-full">Capitulos vinculados
                    <input type="hidden" id="f_chapter_ids_csv" value="">
                    <div class="adm-rel-row">
                        <select id="chapterSelect" class="select adm-rel-select">
                            <option value="">Seleccionar capitulo</option>
                            <?php foreach ($chapters as $chapterRow):
                                $seasonNum = (int)($chapterRow['season_number'] ?? 0);
                                $seasonKind = trim((string)($chapterRow['season_kind'] ?? 'temporada'));
                                $chapterNum = (int)($chapterRow['chapter_number'] ?? 0);
                                $chapterCode = ($seasonKind === 'temporada')
                                    ? ($seasonNum . 'x' . str_pad((string)$chapterNum, 2, '0', STR_PAD_LEFT))
                                    : str_pad((string)$chapterNum, 2, '0', STR_PAD_LEFT);
                                $chapterName = trim((string)($chapterRow['name'] ?? ''));
                            ?>
                            <option value="<?= (int)$chapterRow['id'] ?>">[<?= h($chapterCode) ?>] <?= h($chapterName) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn" type="button" id="btnAddChapterRel">Agregar</button>
                    </div>
                    <div id="relationsChaptersList" class="adm-rel-box small">Sin capitulos vinculados.</div>
                </label>
                <label class="field-full">Realidades vinculadas
                    <select class="select admin-multi" name="reality_ids[]" id="f_reality_ids" multiple size="6">
                        <?php foreach ($realities as $realityRow): ?>
                        <option value="<?= (int)$realityRow['id'] ?>"><?= h((string)$realityRow['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Lugar
                    <input class="inp" type="text" name="location" id="f_location" maxlength="255">
                </label>
                <label>Fuente
                    <input class="inp" type="text" name="source" id="f_source" maxlength="255">
                </label>
                <label>Activo
                    <select class="select" name="is_active" id="f_is_active">
                        <option value="1">Si</option>
                        <option value="0">No</option>
                    </select>
                </label>
                <label class="field-full">Descripcion
                    <textarea class="inp" name="description" id="f_description" rows="8"></textarea>
                </label>
            </div>

            <div class="modal-actions">
                <button class="btn btn-green" type="submit">Guardar</button>
                <button class="btn" type="button" onclick="closeEventModal()">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<?php
$adminHttpJs = '/assets/js/admin/admin-http.js';
$adminHttpJsVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . $adminHttpJs) ?: time();
?>
<script>window.ADMIN_CSRF_TOKEN = <?= json_encode($CSRF, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;</script>
<script src="<?= h($adminHttpJs) ?>?v=<?= (int)$adminHttpJsVer ?>"></script>
<script>
const eventsData = <?= json_encode($events, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const charactersCatalog = <?= json_encode($characters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const chaptersCatalog = <?= json_encode($chapters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
let page = 1;
const pageSize = 20;
let pendingCharacterIds = [];
let pendingChapterIds = [];
const characterById = new Map((charactersCatalog || []).map(c => [Number(c.id), c]));
const chapterById = new Map((chaptersCatalog || []).map(c => [Number(c.id), c]));

function esc(s){
    if (!s) return '';
    return String(s).replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#39;');
}

function filteredEvents(){
    const q = (document.getElementById('quickFilter').value || '').toLowerCase();
    const typeId = document.getElementById('typeFilter').value;
    const active = document.getElementById('activeFilter').value;

    return eventsData.filter(e => {
        const hay = [e.title, e.source, e.chronicle_line, e.type_name, e.pretty_id].join(' ').toLowerCase();
        const okQ = hay.includes(q);
        const okType = (typeId === '' || String(e.event_type_id || '') === typeId);
        const okActive = (active === '' || String(Number(e.is_active || 0)) === active);
        return okQ && okType && okActive;
    });
}

function renderTable(){
    const rows = filteredEvents();
    const tbody = document.querySelector('#eventsTable tbody');
    tbody.innerHTML = '';

    const totalPages = Math.max(1, Math.ceil(rows.length / pageSize));
    if (page > totalPages) page = totalPages;

    const start = (page - 1) * pageSize;
    const end = Math.min(start + pageSize, rows.length);

    for (let i = start; i < end; i++) {
        const e = rows[i];
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${esc(e.event_date || '-')}</td>
            <td><a href="/timeline/event/${encodeURIComponent(e.pretty_id || e.id)}" target="_blank" rel="noopener">${esc(e.title || '(Sin titulo)')}</a></td>
            <td>${esc(e.type_name || 'Evento')}</td>
            <td>${esc(e.chronicle_line || '-')}</td>
            <td>
                <span title="Personajes">P:${Number(e.characters_count || 0)}</span>
                <span title="Capitulos"> C:${Number(e.chapters_count || 0)}</span>
                <span title="Cronicas"> Cr:${Number(e.chronicles_count || 0)}</span>
                <span title="Realidades"> R:${Number(e.realities_count || 0)}</span>
            </td>
            <td>${Number(e.is_active || 0) === 1 ? 'Activo' : 'Inactivo'}</td>
            <td>
                <button class="btn" type="button" onclick="openEventModal(${Number(e.id)})">Editar</button>
                <button class="btn btn-red" type="button" onclick="deleteEvent(${Number(e.id)})">Borrar</button>
            </td>
        `;
        tbody.appendChild(tr);
    }

    const pager = document.getElementById('eventsPager');
    pager.innerHTML = '';
    if (totalPages <= 1) return;
    if (page > 1) pager.innerHTML += `<button class="btn" type="button" onclick="goPage(${page - 1})">Anterior</button>`;
    pager.innerHTML += `<span class="cur">${page}/${totalPages}</span>`;
    if (page < totalPages) pager.innerHTML += `<button class="btn" type="button" onclick="goPage(${page + 1})">Siguiente</button>`;
}

function goPage(n){
    page = n;
    renderTable();
}

function eventById(id){
    return eventsData.find(e => Number(e.id) === Number(id)) || null;
}

function parseIdList(v){
    if (Array.isArray(v)) {
        return v.map(n => Number(n)).filter(n => Number.isInteger(n) && n > 0);
    }
    if (typeof v !== 'string') return [];
    return v.split(',').map(x => Number(String(x).trim())).filter(n => Number.isInteger(n) && n > 0);
}

function setMultiSelectValue(selectId, ids){
    const node = document.getElementById(selectId);
    if (!node) return;
    const wanted = new Set((ids || []).map(n => Number(n)));
    Array.from(node.options).forEach(opt => {
        const val = Number(opt.value || 0);
        opt.selected = wanted.has(val);
    });
}

function getMultiSelectValue(selectId){
    const node = document.getElementById(selectId);
    if (!node) return [];
    return Array.from(node.selectedOptions)
        .map(opt => Number(opt.value || 0))
        .filter(n => Number.isInteger(n) && n > 0);
}

function characterLabelById(id){
    const c = characterById.get(Number(id));
    if (!c) return `#${Number(id)}`;
    const chron = c.chronicle_name ? ` - "${c.chronicle_name}"` : '';
    return `${c.name} (#${Number(c.id)}${chron})`;
}

function chapterLabelById(id){
    const c = chapterById.get(Number(id));
    if (!c) return `#${Number(id)}`;
    const seasonNum = Number(c.season_number || 0);
    const seasonKind = String(c.season_kind || 'temporada');
    const chapterNum = Number(c.chapter_number || 0);
    const chapterCode = seasonKind === 'temporada'
        ? (seasonNum + 'x' + String(chapterNum).padStart(2, '0'))
        : String(chapterNum).padStart(2, '0');
    return `[${chapterCode}] ${c.name || ''}`;
}

function syncPendingFields(){
    const fChar = document.getElementById('f_character_ids_csv');
    const fChap = document.getElementById('f_chapter_ids_csv');
    if (fChar) fChar.value = pendingCharacterIds.join(',');
    if (fChap) fChap.value = pendingChapterIds.join(',');
}

function renderPendingCharacters(){
    const box = document.getElementById('relationsCharactersList');
    if (!box) return;
    if (!pendingCharacterIds.length) {
        box.textContent = 'Sin personajes vinculados.';
        return;
    }
    let html = '<ul>';
    for (const cid of pendingCharacterIds) {
        html += `<li>${esc(characterLabelById(cid))} <button class="btn btn-red adm-pad-2-6-fs10" type="button" onclick="removePendingCharacter(${Number(cid)})">Quitar</button></li>`;
    }
    html += '</ul>';
    box.innerHTML = html;
}

function renderPendingChapters(){
    const box = document.getElementById('relationsChaptersList');
    if (!box) return;
    if (!pendingChapterIds.length) {
        box.textContent = 'Sin capitulos vinculados.';
        return;
    }
    let html = '<ul>';
    for (const cid of pendingChapterIds) {
        html += `<li>${esc(chapterLabelById(cid))} <button class="btn btn-red adm-pad-2-6-fs10" type="button" onclick="removePendingChapter(${Number(cid)})">Quitar</button></li>`;
    }
    html += '</ul>';
    box.innerHTML = html;
}

function addCharacterRelation(){
    const select = document.getElementById('characterSelect');
    const characterId = Number((select && select.value) || 0);
    if (!characterId) return;
    if (!pendingCharacterIds.includes(characterId)) {
        pendingCharacterIds.push(characterId);
        syncPendingFields();
        renderPendingCharacters();
    }
    if (select) select.value = '';
}

function addChapterRelation(){
    const select = document.getElementById('chapterSelect');
    const chapterId = Number((select && select.value) || 0);
    if (!chapterId) return;
    if (!pendingChapterIds.includes(chapterId)) {
        pendingChapterIds.push(chapterId);
        syncPendingFields();
        renderPendingChapters();
    }
    if (select) select.value = '';
}

function removePendingCharacter(characterId){
    pendingCharacterIds = pendingCharacterIds.filter(id => Number(id) !== Number(characterId));
    syncPendingFields();
    renderPendingCharacters();
}

function removePendingChapter(chapterId){
    pendingChapterIds = pendingChapterIds.filter(id => Number(id) !== Number(chapterId));
    syncPendingFields();
    renderPendingChapters();
}

function upsertEvent(row){
    if (!row || !row.id) return;
    const id = Number(row.id);
    const idx = eventsData.findIndex(e => Number(e.id) === id);
    if (idx >= 0) {
        eventsData[idx] = row;
    } else {
        eventsData.unshift(row);
    }

    eventsData.sort((a, b) => {
        const ad = String(a.event_date || '0000-00-00');
        const bd = String(b.event_date || '0000-00-00');
        if (ad !== bd) return ad < bd ? 1 : -1;
        return Number(b.id || 0) - Number(a.id || 0);
    });
}

function openEventModal(id){
    const current = eventById(id);
    pendingCharacterIds = current ? parseIdList(current.character_ids || '') : [];
    pendingChapterIds = current ? parseIdList(current.chapter_ids || '') : [];

    document.getElementById('f_id').value = current ? Number(current.id) : 0;
    document.getElementById('f_title').value = current ? (current.title || '') : '';
    document.getElementById('f_event_date').value = current ? (current.event_date || '') : '';
    document.getElementById('f_event_type_id').value = current ? String(current.event_type_id || '') : String(<?= (int)$defaultEventTypeId ?>);
    document.getElementById('f_date_precision').value = current ? (current.date_precision || 'day') : 'day';
    document.getElementById('f_date_note').value = current ? (current.date_note || '') : '';
    document.getElementById('f_location').value = current ? (current.location || '') : '';
    document.getElementById('f_source').value = current ? (current.source || '') : '';
    document.getElementById('f_description').value = current ? (current.description || '') : '';
    document.getElementById('f_is_active').value = current ? String(Number(current.is_active || 0)) : '1';
    setMultiSelectValue('f_chronicle_ids', current ? parseIdList(current.chronicle_ids || '') : []);
    setMultiSelectValue('f_reality_ids', current ? parseIdList(current.reality_ids || '') : []);
    syncPendingFields();
    renderPendingCharacters();
    renderPendingChapters();

    document.getElementById('eventModalTitle').textContent = current ? 'Editar evento' : 'Nuevo evento';
    document.getElementById('eventModalBack').style.display = 'flex';
}

function closeEventModal(){
    document.getElementById('eventModalBack').style.display = 'none';
}

async function postAjax(payload){
    const endpoint = '/talim?s=admin_timelines&ajax=1';
    if (window.HGAdminHttp && typeof window.HGAdminHttp.request === 'function') {
        return window.HGAdminHttp.request(endpoint, {
            method: 'POST',
            json: payload
        });
    }

    const res = await fetch(endpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json; charset=UTF-8',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': window.ADMIN_CSRF_TOKEN || ''
        },
        body: JSON.stringify(payload)
    });
    const txt = await res.text();
    const json = txt ? JSON.parse(txt) : {};
    if (!res.ok || !json || json.ok === false) {
        throw new Error((json && (json.message || json.error || json.msg)) || ('HTTP ' + res.status));
    }
    return json;
}

async function deleteEvent(id){
    const eventId = Number(id || 0);
    if (!eventId) return;
    if (!confirm('Eliminar este evento?')) return;

    try {
        const payload = await postAjax({ action: 'delete_event', event_id: eventId, csrf: window.ADMIN_CSRF_TOKEN || '' });
        const idx = eventsData.findIndex(e => Number(e.id) === eventId);
        if (idx >= 0) eventsData.splice(idx, 1);
        renderTable();
        if (window.HGAdminHttp && window.HGAdminHttp.notify) {
            window.HGAdminHttp.notify((payload && payload.message) || 'Evento eliminado.', 'ok');
        }
    } catch (e) {
        alert((window.HGAdminHttp && window.HGAdminHttp.errorMessage) ? window.HGAdminHttp.errorMessage(e) : (e.message || 'Error al eliminar.'));
    }
}

document.getElementById('quickFilter').addEventListener('input', () => { page = 1; renderTable(); });
document.getElementById('typeFilter').addEventListener('change', () => { page = 1; renderTable(); });
document.getElementById('activeFilter').addEventListener('change', () => { page = 1; renderTable(); });
document.getElementById('btnAddCharacterRel').addEventListener('click', addCharacterRelation);
document.getElementById('btnAddChapterRel').addEventListener('click', addChapterRelation);

document.getElementById('eventForm').addEventListener('submit', async function(ev){
    ev.preventDefault();
    try {
        const fd = new FormData(this);
        const payload = { action: 'save_event', csrf: window.ADMIN_CSRF_TOKEN || '' };
        fd.forEach((value, key) => {
            if (typeof key === 'string' && key.endsWith('[]')) return;
            payload[key] = value;
        });
        payload.chronicle_ids = getMultiSelectValue('f_chronicle_ids');
        payload.character_ids = pendingCharacterIds.slice();
        payload.chapter_ids = pendingChapterIds.slice();
        payload.reality_ids = getMultiSelectValue('f_reality_ids');
        const res = await postAjax(payload);
        if (res && res.data) upsertEvent(res.data);
        renderTable();
        closeEventModal();
        if (window.HGAdminHttp && window.HGAdminHttp.notify) {
            window.HGAdminHttp.notify((res && res.message) || 'Evento guardado.', 'ok');
        }
    } catch (e) {
        alert((window.HGAdminHttp && window.HGAdminHttp.errorMessage) ? window.HGAdminHttp.errorMessage(e) : (e.message || 'Error al guardar.'));
    }
});

document.addEventListener('keydown', function(e){
    if (e.key === 'Escape') closeEventModal();
});

renderTable();
</script>

<?php admin_panel_close(); ?>
