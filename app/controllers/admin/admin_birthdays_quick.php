<?php
// admin_birthdays_quick.php
// Edicion rapida de cumpleanos: personaje + evento de nacimiento.

if (!isset($link) || !$link) { die('Error de conexion a la base de datos.'); }
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
include_once(__DIR__ . '/../../partials/admin/admin_styles.php');

if (!function_exists('hg_abq_h')) {
    function hg_abq_h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

if (!function_exists('hg_abq_col_exists')) {
    function hg_abq_col_exists(mysqli $db, string $table, string $column): bool {
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
}

if (!function_exists('hg_abq_table_exists')) {
    function hg_abq_table_exists(mysqli $db, string $table): bool {
        static $cache = [];
        if (isset($cache[$table])) return $cache[$table];
        $ok = false;
        if ($st = $db->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?")) {
            $st->bind_param('s', $table);
            $st->execute();
            $st->bind_result($count);
            $st->fetch();
            $st->close();
            $ok = ((int)$count > 0);
        }
        $cache[$table] = $ok;
        return $ok;
    }
}

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
        return hg_abq_col_exists($db, 'fact_characters', 'birthdate_text')
            ? 'p.birthdate_text'
            : "''";
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

$ADMIN_CSRF_SESSION_KEY = 'csrf_admin_birthdays_quick';
if (function_exists('hg_admin_ensure_csrf_token')) {
    $CSRF = hg_admin_ensure_csrf_token($ADMIN_CSRF_SESSION_KEY);
} else {
    if (empty($_SESSION[$ADMIN_CSRF_SESSION_KEY])) {
        $_SESSION[$ADMIN_CSRF_SESSION_KEY] = bin2hex(random_bytes(16));
    }
    $CSRF = $_SESSION[$ADMIN_CSRF_SESSION_KEY];
}

$hasSchema = hg_abq_table_exists($link, 'fact_characters')
    && hg_abq_table_exists($link, 'fact_timeline_events')
    && hg_abq_table_exists($link, 'bridge_timeline_events_characters')
    && hg_abq_table_exists($link, 'dim_timeline_events_types');

if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    if (function_exists('hg_admin_require_session')) hg_admin_require_session(true);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        hg_admin_json_error('Metodo invalido', 405, ['method' => 'POST requerido']);
    }

    $payload = function_exists('hg_admin_read_json_payload') ? hg_admin_read_json_payload() : [];
    $csrfToken = function_exists('hg_admin_extract_csrf_token')
        ? hg_admin_extract_csrf_token($payload)
        : (string)($_POST['csrf'] ?? '');
    $csrfOk = function_exists('hg_admin_csrf_valid')
        ? hg_admin_csrf_valid($csrfToken, $ADMIN_CSRF_SESSION_KEY)
        : (is_string($csrfToken) && $csrfToken !== '' && isset($_SESSION[$ADMIN_CSRF_SESSION_KEY]) && hash_equals($_SESSION[$ADMIN_CSRF_SESSION_KEY], $csrfToken));
    if (!$csrfOk) {
        hg_admin_json_error('CSRF invalido. Recarga la pagina.', 403, ['csrf' => 'invalid']);
    }
    if (!$hasSchema) {
        hg_admin_json_error('Falta esquema para este modulo.', 400, ['schema' => 'missing']);
    }

    $action = (string)($payload['action'] ?? ($_POST['action'] ?? ''));

    if ($action === 'list') {
        $q = (string)($payload['q'] ?? '');
        $status = (string)($payload['status'] ?? 'pending');
        $limit = (int)($payload['limit'] ?? 300);
        $rows = hg_abq_fetch_rows($link, $q, $status, $limit);
        hg_admin_json_success($rows, 'Listado cargado', ['count' => count($rows)]);
    }

    if ($action === 'save_row') {
        $characterId = (int)($payload['character_id'] ?? 0);
        $birthdateText = trim((string)($payload['birthdate_text'] ?? ''));
        $forcedEventDate = trim((string)($payload['event_date'] ?? ''));
        if ($characterId <= 0) {
            hg_admin_json_error('ID de personaje invalido', 400, ['character_id' => 'required']);
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
            hg_admin_json_error('Personaje no encontrado', 404, ['character_id' => 'not_found']);
        }

        $birthTypeId = hg_abq_find_birth_type_id($link);
        if ($birthTypeId <= 0) {
            hg_admin_json_error('No existe el tipo nacimiento en dim_timeline_events_types', 400, ['event_type' => 'nacimiento_missing']);
        }

        $parsedDate = hg_abq_parse_birth_to_ymd($forcedEventDate !== '' ? $forcedEventDate : $birthdateText);
        $prettyId = 'birthday-char-' . $characterId;
        $eventId = 0;

        $hasBirthTextCol = hg_abq_col_exists($link, 'fact_characters', 'birthdate_text');

        $link->begin_transaction();
        try {
            if ($hasBirthTextCol) {
                if ($st = $link->prepare('UPDATE fact_characters SET birthdate_text = ? WHERE id = ?')) {
                    $st->bind_param('si', $birthdateText, $characterId);
                    $st->execute();
                    $st->close();
                }
            }

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

                $title = 'Cumpleanos de ' . trim($characterName);
                $description = 'Evento de nacimiento del personaje ' . trim($characterName) . ' (id=' . $characterId . ').';

                $hasKindCol = hg_abq_col_exists($link, 'fact_timeline_events', 'kind');
                if ($eventId > 0) {
                    if ($hasKindCol) {
                        $sqlUpdateEvent = "
                            UPDATE fact_timeline_events
                            SET pretty_id = ?, event_date = ?, date_precision = 'day', date_note = NULL, sort_date = ?,
                                title = ?, description = ?, event_type_id = ?, kind = 'nacimiento', is_active = 1,
                                source = 'fact_characters.birthdate_text', updated_at = NOW()
                            WHERE id = ?
                        ";
                    } else {
                        $sqlUpdateEvent = "
                            UPDATE fact_timeline_events
                            SET pretty_id = ?, event_date = ?, date_precision = 'day', date_note = NULL, sort_date = ?,
                                title = ?, description = ?, event_type_id = ?, is_active = 1,
                                source = 'fact_characters.birthdate_text', updated_at = NOW()
                            WHERE id = ?
                        ";
                    }
                    if ($st = $link->prepare($sqlUpdateEvent)) {
                        $st->bind_param('sssssii', $prettyId, $parsedDate, $parsedDate, $title, $description, $birthTypeId, $eventId);
                        $st->execute();
                        $st->close();
                    }
                } else {
                    if ($hasKindCol) {
                        $sqlInsertEvent = "
                            INSERT INTO fact_timeline_events
                            (pretty_id, event_date, date_precision, date_note, sort_date, title, description, event_type_id, kind, is_active, source, timeline)
                            VALUES (?, ?, 'day', NULL, ?, ?, ?, ?, 'nacimiento', 1, 'fact_characters.birthdate_text', NULL)
                        ";
                    } else {
                        $sqlInsertEvent = "
                            INSERT INTO fact_timeline_events
                            (pretty_id, event_date, date_precision, date_note, sort_date, title, description, event_type_id, is_active, source, timeline)
                            VALUES (?, ?, 'day', NULL, ?, ?, ?, ?, 1, 'fact_characters.birthdate_text', NULL)
                        ";
                    }
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
                        $hasRole = hg_abq_col_exists($link, 'bridge_timeline_events_characters', 'role_label');
                        $hasSort = hg_abq_col_exists($link, 'bridge_timeline_events_characters', 'sort_order');
                        if ($hasRole && $hasSort) {
                            if ($st = $link->prepare('INSERT INTO bridge_timeline_events_characters (event_id, character_id, role_label, sort_order) VALUES (?, ?, "protagonista", 0)')) {
                                $st->bind_param('ii', $eventId, $characterId);
                                $st->execute();
                                $st->close();
                            }
                        } elseif ($hasRole) {
                            if ($st = $link->prepare('INSERT INTO bridge_timeline_events_characters (event_id, character_id, role_label) VALUES (?, ?, "protagonista")')) {
                                $st->bind_param('ii', $eventId, $characterId);
                                $st->execute();
                                $st->close();
                            }
                        } elseif ($hasSort) {
                            if ($st = $link->prepare('INSERT INTO bridge_timeline_events_characters (event_id, character_id, sort_order) VALUES (?, ?, 0)')) {
                                $st->bind_param('ii', $eventId, $characterId);
                                $st->execute();
                                $st->close();
                            }
                        } else {
                            if ($st = $link->prepare('INSERT INTO bridge_timeline_events_characters (event_id, character_id) VALUES (?, ?)')) {
                                $st->bind_param('ii', $eventId, $characterId);
                                $st->execute();
                                $st->close();
                            }
                        }
                    }
                }
            }

            $link->commit();
        } catch (Throwable $e) {
            $link->rollback();
            hg_admin_json_error('Error al guardar fila', 500, ['sql' => $e->getMessage()]);
        }

        $savedRow = hg_abq_fetch_row_by_character($link, $characterId);
        $msg = ($parsedDate === null)
            ? 'Cumpleanos actualizado en personaje. Fecha no valida para evento.'
            : 'Cumpleanos y evento guardados.';
        hg_admin_json_success($savedRow, $msg);
    }

    hg_admin_json_error('Accion no valida', 400, ['action' => 'unsupported']);
}

$actions = '<span class="adm-flex-right-wrap-8">'
    . '<label class="adm-text-left">Estado '
    . '<select id="abqStatus" class="select">'
    . '<option value="pending">Pendientes</option>'
    . '<option value="all">Todos</option>'
    . '<option value="ok">Solo OK</option>'
    . '</select></label>'
    . '<label class="adm-text-left">Buscar '
    . '<input class="inp" type="text" id="abqSearch" placeholder="Nombre, pretty_id o ID"></label>'
    . '<button class="btn btn-green" type="button" id="abqReload">Recargar</button>'
    . '</span>';

admin_panel_open('Cumpleanos Rapidos', $actions);
?>
<div class="adm-callout">
  Revisa y corrige cumpleaños para generar/vincular su evento de nacimiento.
</div>

<div class="adm-grid-table">
  <table class="table" id="abqTable">
    <thead>
      <tr>
        <th class="adm-w-60">ID</th>
        <th class="adm-w-160">Pretty</th>
        <th>Personaje</th>
        <th class="adm-w-170">Cumpleanos (texto)</th>
        <th class="adm-w-120">Fecha evento</th>
        <th class="adm-w-90">Evento ID</th>
        <th class="adm-w-160">Estado</th>
        <th class="adm-w-100">Accion</th>
      </tr>
    </thead>
    <tbody></tbody>
  </table>
</div>

<script src="/assets/js/admin/admin-http.js"></script>
<script>
(function() {
  window.ADMIN_CSRF_TOKEN = <?= json_encode($CSRF, JSON_UNESCAPED_UNICODE) ?>;
  var hasSchema = <?= $hasSchema ? 'true' : 'false' ?>;
  var endpoint = '/talim?s=admin_birthdays_quick&ajax=1';
  var tableBody = document.querySelector('#abqTable tbody');
  var searchInput = document.getElementById('abqSearch');
  var statusSelect = document.getElementById('abqStatus');
  var reloadBtn = document.getElementById('abqReload');

  function esc(v) {
    return String(v == null ? '' : v)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function toDateInput(value) {
    var s = String(value || '').trim();
    return (/^\d{4}-\d{2}-\d{2}$/).test(s) ? s : '';
  }

  function renderRows(rows) {
    if (!tableBody) return;
    if (!rows || !rows.length) {
      tableBody.innerHTML = '<tr><td colspan="8">Sin resultados.</td></tr>';
      return;
    }
    var html = rows.map(function(r) {
      return '' +
        '<tr data-character-id="' + Number(r.character_id || 0) + '">' +
          '<td>' + Number(r.character_id || 0) + '</td>' +
          '<td><code>' + esc(r.character_pretty_id || '') + '</code></td>' +
          '<td>' + esc(r.character_name || '') + '</td>' +
          '<td><input type="text" class="inp abq-birthtext" value="' + esc(r.character_birthdate_text || '') + '" placeholder="dd/mm/aaaa o yyyy-mm-dd"></td>' +
          '<td><input type="date" class="inp abq-eventdate" value="' + esc(toDateInput(r.birth_event_date || '')) + '"></td>' +
          '<td>' + (r.birth_event_id ? Number(r.birth_event_id) : '-') + '</td>' +
          '<td><span class="abq-state state-' + esc((r.estado || '').toLowerCase()) + '">' + esc(r.estado || '') + '</span></td>' +
          '<td><button type="button" class="btn btn-sm btn-blue abq-save">Guardar</button></td>' +
        '</tr>';
    }).join('');
    tableBody.innerHTML = html;
  }

  async function loadRows() {
    if (!hasSchema) {
      if (tableBody) tableBody.innerHTML = '<tr><td colspan="8">Falta esquema requerido para el modulo.</td></tr>';
      return;
    }
    try {
      var payload = await HGAdminHttp.postAction(endpoint, 'list', {
        q: searchInput ? searchInput.value.trim() : '',
        status: statusSelect ? statusSelect.value : 'pending',
        limit: 500
      }, { loadingEl: document.getElementById('abqTable') });
      renderRows((payload && payload.data) ? payload.data : []);
    } catch (e) {
      renderRows([]);
      HGAdminHttp.notify(HGAdminHttp.errorMessage(e), 'err', 3200);
    }
  }

  async function saveRow(tr) {
    if (!tr) return;
    var characterId = Number(tr.getAttribute('data-character-id') || 0);
    if (!characterId) return;
    var birthInput = tr.querySelector('.abq-birthtext');
    var dateInput = tr.querySelector('.abq-eventdate');
    var btn = tr.querySelector('.abq-save');
    if (btn) btn.disabled = true;
    try {
      var payload = await HGAdminHttp.postAction(endpoint, 'save_row', {
        character_id: characterId,
        birthdate_text: birthInput ? birthInput.value : '',
        event_date: dateInput ? dateInput.value : ''
      }, { loadingEl: tr });
      HGAdminHttp.notify((payload && payload.message) ? payload.message : 'Guardado', 'ok');
      await loadRows();
    } catch (e) {
      HGAdminHttp.notify(HGAdminHttp.errorMessage(e), 'err', 3600);
    } finally {
      if (btn) btn.disabled = false;
    }
  }

  if (reloadBtn) reloadBtn.addEventListener('click', loadRows);
  if (statusSelect) statusSelect.addEventListener('change', loadRows);
  if (searchInput) {
    var tmr = null;
    searchInput.addEventListener('input', function() {
      clearTimeout(tmr);
      tmr = setTimeout(loadRows, 250);
    });
  }
  if (tableBody) {
    tableBody.addEventListener('click', function(e) {
      var btn = e.target && e.target.closest('.abq-save');
      if (!btn) return;
      var tr = btn.closest('tr');
      saveRow(tr);
    });
  }

  loadRows();
})();
</script>

<style>
.adm-callout { margin: 10px 0; font-size: 13px; color: #b7cdf8; }
.adm-grid-table { overflow-x: auto; }
.abq-state { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; letter-spacing: .02em; }
.state-ok { background: #123f2a; color: #79f0b0; }
.state-sin_evento { background: #532121; color: #ffb3b3; }
.state-evento_sin_fecha { background: #4f3f13; color: #ffe08f; }
.abq-birthtext { min-width: 150px; }
.abq-eventdate { min-width: 120px; }
</style>
<?php admin_panel_close(); ?>
