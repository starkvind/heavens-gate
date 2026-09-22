<?php
// admin_birthdays_quick.php
// Edicion rapida de cumpleanos: personaje + evento de nacimiento.

include_once(__DIR__ . '/../../helpers/admin_ajax.php');
if (!hg_admin_require_db($link)) { return; }
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
include_once(__DIR__ . '/../../helpers/character_birth_events.php');
include_once(__DIR__ . '/../../domains/characters/admin_birthdays.php');
include_once(__DIR__ . '/../../partials/admin/admin_styles.php');

if (!function_exists('hg_abq_h')) {
    function hg_abq_h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
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
        hg_admin_json_error('Método inválido', 405, ['method' => 'POST requerido']);
    }

    $payload = function_exists('hg_admin_read_json_payload') ? hg_admin_read_json_payload() : [];
    $csrfToken = function_exists('hg_admin_extract_csrf_token')
        ? hg_admin_extract_csrf_token($payload)
        : (string)($_POST['csrf'] ?? '');
    $csrfOk = function_exists('hg_admin_csrf_valid')
        ? hg_admin_csrf_valid($csrfToken, $ADMIN_CSRF_SESSION_KEY)
        : (is_string($csrfToken) && $csrfToken !== '' && isset($_SESSION[$ADMIN_CSRF_SESSION_KEY]) && hash_equals($_SESSION[$ADMIN_CSRF_SESSION_KEY], $csrfToken));
    if (!$csrfOk) {
        hg_admin_json_error('CSRF inválido. Recarga la página.', 403, ['csrf' => 'invalid']);
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
            hg_admin_json_error('ID de personaje inválido', 400, ['character_id' => 'required']);
        }

        $result = hg_abq_save_row($link, $characterId, $birthdateText, $forcedEventDate);
        if (empty($result['ok'])) {
            hg_admin_json_error(
                (string)($result['message'] ?? 'Error al guardar fila'),
                (int)($result['status'] ?? 500),
                (array)($result['errors'] ?? [])
            );
        }
        hg_admin_json_success($result['row'] ?? null, (string)($result['message'] ?? 'Evento de nacimiento guardado.'));
    }

    hg_admin_json_error('Acción no válida', 400, ['action' => 'unsupported']);
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

admin_panel_open('Cumpleaños Rápidos', $actions);
?>
<div class="adm-callout">
  Revisa y corrige la fecha de nacimiento directamente sobre el evento de timeline.
</div>

<div class="adm-grid-table">
  <table class="table" id="abqTable">
    <thead>
      <tr>
        <th class="adm-w-60">ID</th>
        <th class="adm-w-160">Pretty</th>
        <th>Personaje</th>
        <th class="adm-w-170">Fecha nacimiento</th>
        <th class="adm-w-120">Fecha evento</th>
        <th class="adm-w-90">Evento ID</th>
        <th class="adm-w-160">Estado</th>
        <th class="adm-w-100">Acción</th>
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

