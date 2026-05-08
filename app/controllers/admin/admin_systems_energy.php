<?php
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
if (!hg_admin_require_db($link)) { return; }
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }

include_once(__DIR__ . '/../../helpers/system_energy_resource.php');
include(__DIR__ . '/../../partials/admin/admin_styles.php');

$isAjaxRequest = (
    ((string)($_GET['ajax'] ?? '') === '1')
    || (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest')
);
if ($isAjaxRequest && function_exists('hg_admin_require_session')) {
    hg_admin_require_session(true);
}

$csrfKey = 'csrf_admin_systems_energy';
$csrf = function_exists('hg_admin_ensure_csrf_token')
    ? hg_admin_ensure_csrf_token($csrfKey)
    : ((empty($_SESSION[$csrfKey]) ? ($_SESSION[$csrfKey] = bin2hex(random_bytes(16))) : $_SESSION[$csrfKey]));

function ase_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ase_csrf_ok(string $csrfKey): bool
{
    $payload = function_exists('hg_admin_read_json_payload') ? hg_admin_read_json_payload() : [];
    $token = function_exists('hg_admin_extract_csrf_token')
        ? hg_admin_extract_csrf_token($payload)
        : (string)($_POST['csrf'] ?? '');
    return function_exists('hg_admin_csrf_valid')
        ? hg_admin_csrf_valid($token, $csrfKey)
        : ($token !== '' && isset($_SESSION[$csrfKey]) && hash_equals((string)$_SESSION[$csrfKey], $token));
}

function ase_bind_params(mysqli_stmt $stmt, string $types, array $params): void
{
    if ($types === '' || empty($params)) return;
    $refs = [];
    foreach ($params as $idx => $value) $refs[$idx] = &$params[$idx];
    array_unshift($refs, $types);
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

function ase_meta(string $tab): array
{
    if ($tab === 'misc') {
        return ['tab' => 'misc', 'title' => 'Misc Systems', 'table' => 'fact_misc_systems'];
    }
    if ($tab === 'auspices') {
        return ['tab' => 'auspices', 'title' => 'Auspicios', 'table' => 'dim_auspices'];
    }
    if ($tab === 'tribes') {
        return ['tab' => 'tribes', 'title' => 'Tribus', 'table' => 'dim_tribes'];
    }
    return ['tab' => 'breeds', 'title' => 'Razas', 'table' => 'dim_breeds'];
}

function ase_load_systems(mysqli $link): array
{
    $rows = [];
    if ($rs = $link->query("SELECT id, name FROM dim_systems ORDER BY sort_order ASC, name ASC")) {
        while ($row = $rs->fetch_assoc()) {
            $rows[] = ['id' => (int)($row['id'] ?? 0), 'name' => (string)($row['name'] ?? '')];
        }
        $rs->close();
    }
    return $rows;
}

function ase_load_rows(mysqli $link, string $tab, int $systemId, string $q): array
{
    $meta = ase_meta($tab);
    $table = $meta['table'];
    $where = 'WHERE 1=1';
    $types = '';
    $params = [];

    if ($systemId > 0) {
        $where .= ' AND t.system_id = ?';
        $types .= 'i';
        $params[] = $systemId;
    }
    if ($q !== '') {
        $where .= ' AND t.name LIKE ?';
        $types .= 's';
        $params[] = '%' . $q . '%';
    }

    $energySql = hg_ser_energy_sql_parts($link, $table, 't', 'er');
    $sql = "
        SELECT
            t.id,
            t.name,
            COALESCE(t.system_id, 0) AS system_id,
            COALESCE(s.name, t.system_name, '') AS system_name,
            " . hg_ser_energy_value_sql_expr($link, $table, 't') . " AS energy
            {$energySql['select']}
        FROM `$table` t
        LEFT JOIN dim_systems s ON s.id = t.system_id
        {$energySql['join']}
        {$where}
        ORDER BY s.name ASC, t.name ASC
    ";

    $rows = [];
    if ($st = $link->prepare($sql)) {
        ase_bind_params($st, $types, $params);
        $st->execute();
        $rs = $st->get_result();
        while ($rs && ($row = $rs->fetch_assoc())) {
            $rows[] = [
                'id' => (int)($row['id'] ?? 0),
                'name' => (string)($row['name'] ?? ''),
                'system_id' => (int)($row['system_id'] ?? 0),
                'system_name' => (string)($row['system_name'] ?? ''),
                'energy' => (int)($row['energy'] ?? 0),
                'energy_resource_id' => (int)($row['energy_resource_id'] ?? 0),
                'energy_resource_name' => (string)($row['energy_resource_name'] ?? ''),
                'energy_resource_pretty_id' => (string)($row['energy_resource_pretty_id'] ?? ''),
            ];
        }
        $st->close();
    }

    return hg_ser_attach_energy_summary($link, $table, $rows);
}

function ase_load_legacy_pending_rows(mysqli $link): array
{
    $rows = [];
    foreach (hg_ser_energy_tables() as $table => $meta) {
        $rows[$table] = hg_ser_legacy_pending_rows($link, $table, 50);
    }
    return $rows;
}

function ase_state(mysqli $link, string $tab, int $systemId, string $q, array $resourcesAll, array $resourcesBySystem): array
{
    $meta = ase_meta($tab);
    $table = $meta['table'];
    $schema = hg_ser_schema_status($link);
    $legacy = hg_ser_legacy_status($link);
    return [
        'tab' => $tab,
        'meta' => $meta,
        'system_id' => $systemId,
        'q' => $q,
        'systems' => ase_load_systems($link),
        'rows' => ase_load_rows($link, $tab, $systemId, $q),
        'resources_all' => array_values($resourcesAll),
        'resources_by_system' => $resourcesBySystem,
        'schema_status' => $schema,
        'legacy_status' => $legacy,
        'legacy_pending_rows' => ase_load_legacy_pending_rows($link),
        'schema_ready' => !empty($schema[$table]['bridge_table']) && !empty($schema[$table]['config_column']),
    ];
}

function ase_save_assignments(mysqli $link, string $tab, array $updates, array $resourcesBySystem, array $resourcesAll, bool $allowAllStateResources = false): array
{
    $meta = ase_meta($tab);
    $table = $meta['table'];
    if (!hg_ser_has_energy_bridge_table($link, $table)) {
        return ['ok' => false, 'message' => 'El schema aún no está preparado para esta tabla.'];
    }

    $rowsById = [];
    $ids = [];
    foreach ($updates as $rawId => $payload) {
        $detailId = (int)$rawId;
        if ($detailId <= 0) continue;
        $ids[$detailId] = $detailId;
        $rowsById[$detailId] = hg_ser_normalize_posted_energy_assignments($payload);
    }
    if (empty($ids)) {
        return ['ok' => true, 'message' => 'No había cambios que guardar.'];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT id, COALESCE(system_id, 0) AS system_id FROM `$table` WHERE id IN ($placeholders)";
    $detailSystems = [];
    if ($st = $link->prepare($sql)) {
        $params = array_values($ids);
        ase_bind_params($st, str_repeat('i', count($params)), $params);
        $st->execute();
        $rs = $st->get_result();
        while ($rs && ($row = $rs->fetch_assoc())) {
            $detailSystems[(int)$row['id']] = (int)($row['system_id'] ?? 0);
        }
        $st->close();
    }

    foreach ($rowsById as $detailId => $assignments) {
        if (!isset($detailSystems[$detailId])) {
            return ['ok' => false, 'message' => 'Hay filas que ya no existen. Recarga la página.'];
        }
        $energyError = hg_ser_validate_energy_assignments($assignments, (int)$detailSystems[$detailId], $resourcesBySystem, $resourcesAll, $allowAllStateResources);
        if ($energyError !== null) {
            return ['ok' => false, 'message' => $energyError];
        }
    }

    $link->begin_transaction();
    try {
        foreach ($rowsById as $detailId => $assignments) {
            $save = hg_ser_save_energy_assignments($link, $table, $detailId, $assignments);
            if (empty($save['ok'])) {
                throw new RuntimeException((string)($save['message'] ?? 'No se pudieron guardar los recursos de energía.'));
            }
        }
        $link->commit();
        return ['ok' => true, 'message' => 'Recursos de energía actualizados.'];
    } catch (Throwable $e) {
        $link->rollback();
        return ['ok' => false, 'message' => 'No se pudieron guardar los cambios: ' . $e->getMessage()];
    }
}

$allowedTabs = ['breeds', 'auspices', 'tribes', 'misc'];
$tab = (string)($_GET['tab'] ?? $_POST['tab'] ?? 'breeds');
if (!in_array($tab, $allowedTabs, true)) $tab = 'breeds';
$systemId = max(0, (int)($_GET['system_id'] ?? $_POST['system_id'] ?? 0));
$q = trim((string)($_GET['q'] ?? $_POST['q'] ?? ''));

$resourcesAll = hg_ser_fetch_state_resources_all($link);
$resourcesBySystem = hg_ser_fetch_state_resources_by_system($link);

if ($isAjaxRequest && (string)($_GET['ajax_mode'] ?? '') === 'state') {
    $state = ase_state($link, $tab, $systemId, $q, $resourcesAll, $resourcesBySystem);
    if (function_exists('hg_admin_json_success')) {
        hg_admin_json_success(['state' => $state], 'Estado');
    }
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => true, 'data' => ['state' => $state]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!ase_csrf_ok($csrfKey)) {
        if (function_exists('hg_admin_json_error')) {
            hg_admin_json_error('CSRF inválido. Recarga la página.', 400, ['csrf' => 'invalid']);
        }
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['ok' => false, 'message' => 'CSRF inválido.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $action = (string)($_POST['action'] ?? '');
    if ($action === 'schema_apply') {
        $apply = hg_ser_ensure_energy_schema($link);
        $state = ase_state($link, $tab, $systemId, $q, $resourcesAll, $resourcesBySystem);
        if (!empty($apply['ok'])) {
            if (function_exists('hg_admin_json_success')) {
                hg_admin_json_success(['state' => $state, 'messages' => $apply['messages']], 'Schema preparado.');
            }
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['ok' => true, 'message' => 'Schema preparado.', 'data' => ['state' => $state, 'messages' => $apply['messages']]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            exit;
        }
        $error = (string)($apply['errors'][0]['error'] ?? 'No se pudo preparar el schema.');
        if (function_exists('hg_admin_json_error')) {
            hg_admin_json_error($error, 400, ['errors' => $apply['errors']], ['state' => $state, 'messages' => $apply['messages']]);
        }
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['ok' => false, 'message' => $error, 'errors' => $apply['errors'], 'data' => ['state' => $state, 'messages' => $apply['messages']]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    if ($action === 'save_assignments') {
        $updatesRaw = isset($_POST['updates']) && is_array($_POST['updates']) ? $_POST['updates'] : [];
        $allowAllStateResources = ((int)($_POST['allow_all_state_resources'] ?? 0) === 1);
        $save = ase_save_assignments($link, $tab, $updatesRaw, $resourcesBySystem, $resourcesAll, $allowAllStateResources);
        $state = ase_state($link, $tab, $systemId, $q, $resourcesAll, $resourcesBySystem);
        if (!empty($save['ok'])) {
            if (function_exists('hg_admin_json_success')) {
                hg_admin_json_success(['state' => $state], (string)$save['message']);
            }
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['ok' => true, 'message' => (string)$save['message'], 'data' => ['state' => $state]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            exit;
        }
        if (function_exists('hg_admin_json_error')) {
            hg_admin_json_error((string)$save['message'], 400, [], ['state' => $state]);
        }
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['ok' => false, 'message' => (string)$save['message'], 'data' => ['state' => $state]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    if ($action === 'legacy_retire') {
        $retire = hg_ser_retire_legacy_schema($link);
        $state = ase_state($link, $tab, $systemId, $q, $resourcesAll, $resourcesBySystem);
        if (!empty($retire['ok'])) {
            if (function_exists('hg_admin_json_success')) {
                hg_admin_json_success(['state' => $state, 'messages' => $retire['messages']], 'Columnas legacy retiradas.');
            }
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['ok' => true, 'message' => 'Columnas legacy retiradas.', 'data' => ['state' => $state, 'messages' => $retire['messages']]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            exit;
        }
        $error = (string)($retire['errors'][0]['error'] ?? 'No se pudieron retirar las columnas legacy.');
        if (function_exists('hg_admin_json_error')) {
            hg_admin_json_error($error, 400, ['errors' => $retire['errors']], ['state' => $state, 'messages' => $retire['messages']]);
        }
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['ok' => false, 'message' => $error, 'errors' => $retire['errors'], 'data' => ['state' => $state, 'messages' => $retire['messages']]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
}

$initialState = ase_state($link, $tab, $systemId, $q, $resourcesAll, $resourcesBySystem);
$systemOptions = '<option value="0">-- Todos --</option>';
foreach ((array)$initialState['systems'] as $systemRow) {
    $sid = (int)($systemRow['id'] ?? 0);
    $selected = $sid === $systemId ? ' selected' : '';
    $systemOptions .= '<option value="' . $sid . '"' . $selected . '>' . ase_h((string)($systemRow['name'] ?? '')) . '</option>';
}

$actions = '<span class="adm-flex-right-8">'
    . '<label class="adm-text-left">Sistema <select class="select" id="aseSystemFilter">' . $systemOptions . '</select></label>'
    . '<label class="adm-text-left">Filtro rápido <input class="inp" type="text" id="aseQuickFilter" placeholder="En esta vista..."></label>'
    . '<label class="adm-text-left"><input type="checkbox" id="aseAllowAllStateResources" value="1"> Mostrar todos los recursos de estado</label>'
    . '<button class="btn" type="button" id="aseReloadBtn">Recargar</button>'
    . '<button class="btn" type="button" id="aseSchemaBtn">Preparar schema</button>'
    . '<button class="btn" type="button" id="aseLegacyBtn">Retirar legacy</button>'
    . '<button class="btn btn-green" type="button" id="aseSaveBtn">Guardar cambios</button>'
    . '</span>';
admin_panel_open('Vincular energías a recursos', $actions);
?>

<style>
.ase-tabs{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 10px 0}
.ase-tab{padding:7px 12px;border:1px solid #17366e;border-radius:999px;background:#071b4a;color:#dfefff;cursor:pointer}
.ase-tab.active{background:#0d356f;color:#fff}
.ase-status{display:flex;gap:10px;flex-wrap:wrap;margin:0 0 10px 0}
.ase-pill{padding:6px 10px;border-radius:999px;border:1px solid #17366e;background:#071b4a;color:#dfefff}
.ase-note{margin:0 0 12px 0;color:#d7e7ff}
.ase-muted{color:#9fb6dc}
.ase-energy-cell{min-width:340px}
.ase-energy-list{display:flex;flex-direction:column;gap:6px}
.ase-energy-row{display:grid;grid-template-columns:minmax(160px,2fr) 84px 78px 78px;gap:6px;align-items:center}
.ase-energy-head{display:grid;grid-template-columns:minmax(160px,2fr) 84px 78px 78px;gap:6px;font-size:12px;color:#9fb6dc;margin:0 0 6px 0}
.ase-energy-actions{margin-top:6px}
@media (max-width: 920px){
  .ase-energy-head,.ase-energy-row{grid-template-columns:1fr}
}
</style>

<div class="ase-tabs" id="aseTabs">
  <button class="ase-tab<?= $tab === 'breeds' ? ' active' : '' ?>" type="button" data-tab="breeds">Razas</button>
  <button class="ase-tab<?= $tab === 'auspices' ? ' active' : '' ?>" type="button" data-tab="auspices">Auspicios</button>
  <button class="ase-tab<?= $tab === 'tribes' ? ' active' : '' ?>" type="button" data-tab="tribes">Tribus</button>
  <button class="ase-tab<?= $tab === 'misc' ? ' active' : '' ?>" type="button" data-tab="misc">Misc</button>
</div>

<div class="ase-status" id="aseSchemaStatus">
  <?php foreach (['dim_breeds' => 'Razas', 'dim_auspices' => 'Auspicios', 'dim_tribes' => 'Tribus', 'fact_misc_systems' => 'Misc'] as $tableName => $label): ?>
    <?php
      $ok = !empty($initialState['schema_status'][$tableName]['bridge_table']);
      $pending = (int)($initialState['legacy_status'][$tableName]['pending_count'] ?? 0);
    ?>
    <span class="ase-pill"><?= ase_h($label) ?>: <?= $ok ? 'OK' : 'Pendiente' ?><?php if ($pending > 0): ?> · legacy pendientes <?= $pending ?><?php endif; ?></span>
  <?php endforeach; ?>
</div>

<p class="ase-note" id="aseNote">
  Cada fila puede tener uno o varios recursos de <code>dim_systems_resources</code>. Así cubrimos casos simples como Garou y casos múltiples como Ananasi sin asumir un único recurso fijo por Raza, Auspicio o Tribu.
</p>
<div class="ase-note ase-muted" id="aseLegacyPending"></div>

<table class="table" id="aseTable">
  <thead>
    <tr>
      <th width="60">ID</th>
      <th width="220">Nombre</th>
      <th width="150">Sistema</th>
      <th width="90">Valor legacy</th>
      <th width="380">Recursos de energía</th>
    </tr>
  </thead>
  <tbody id="aseTbody"></tbody>
</table>

<?php
$adminHttpJs = '/assets/js/admin/admin-http.js';
$adminHttpJsVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . $adminHttpJs) ?: time();
?>
<script>window.ADMIN_CSRF_TOKEN = <?= json_encode($csrf, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;</script>
<script src="<?= ase_h($adminHttpJs) ?>?v=<?= (int)$adminHttpJsVer ?>"></script>
<script>
(function(){
  var endpoint = '/talim?s=admin_systems_energy';
  var state = <?= json_encode($initialState, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
  var tbody = document.getElementById('aseTbody');
  var tabs = Array.from(document.querySelectorAll('.ase-tab'));
  var quickFilter = document.getElementById('aseQuickFilter');
  var systemFilter = document.getElementById('aseSystemFilter');
  var schemaBtn = document.getElementById('aseSchemaBtn');
  var legacyBtn = document.getElementById('aseLegacyBtn');
  var saveBtn = document.getElementById('aseSaveBtn');
  var reloadBtn = document.getElementById('aseReloadBtn');
  var schemaStatus = document.getElementById('aseSchemaStatus');
  var legacyPending = document.getElementById('aseLegacyPending');
  var allowAllStateResources = document.getElementById('aseAllowAllStateResources');

  function esc(str){
    return String(str || '')
      .replace(/&/g,'&amp;')
      .replace(/</g,'&lt;')
      .replace(/>/g,'&gt;')
      .replace(/"/g,'&quot;')
      .replace(/'/g,'&#039;');
  }

  function useAllStateResources(){
    return !!(allowAllStateResources && allowAllStateResources.checked);
  }

  function resourcesForSystem(systemId){
    if (useAllStateResources()) {
      return Array.isArray(state.resources_all) ? state.resources_all : [];
    }
    var key = String(parseInt(systemId || '0', 10) || 0);
    var rows = (state.resources_by_system && (state.resources_by_system[key] || state.resources_by_system[parseInt(key, 10)])) || [];
    if (Array.isArray(rows) && rows.length) return rows;
    return Array.isArray(state.resources_all) ? state.resources_all : [];
  }

  function refreshVisibleResourceOptions(){
    if (!tbody) return;
    tbody.querySelectorAll('tr[data-row-id]').forEach(function(tr){
      var systemId = parseInt(tr.dataset.systemId || '0', 10) || 0;
      tr.querySelectorAll('select.ase-energy-resource').forEach(function(select){
        var currentValue = select.value || '0';
        var currentLabel = '';
        if (select.selectedOptions && select.selectedOptions[0]) currentLabel = select.selectedOptions[0].textContent || '';
        select.innerHTML = buildResourceOptions(systemId, currentValue, currentLabel);
      });
    });
  }

  function rowSearchText(row){
    return String((row.name || '') + ' ' + (row.system_name || '') + ' ' + (row.energy_resources_summary || '')).toLowerCase();
  }

  function buildResourceOptions(systemId, selectedId, selectedLabel){
    var html = '<option value="0">--</option>';
    resourcesForSystem(systemId).forEach(function(resource){
      var rid = parseInt(resource.id || 0, 10) || 0;
      var selected = rid === (parseInt(selectedId || 0, 10) || 0) ? ' selected' : '';
      html += '<option value="' + rid + '"' + selected + '>' + esc(resource.name || '') + '</option>';
    });
    if (selectedLabel && parseInt(selectedId || 0, 10) > 0 && html.indexOf('value="' + parseInt(selectedId || 0, 10) + '"') === -1) {
      html += '<option value="' + parseInt(selectedId || 0, 10) + '" selected>' + esc(selectedLabel) + '</option>';
    }
    return html;
  }

  function buildEnergyRow(row, entry, idx){
    var disabledAttr = state.schema_ready ? '' : ' disabled';
    return '' +
      '<div class="ase-energy-row" data-energy-row="1">' +
        '<select class="select ase-energy-resource" data-entry="' + idx + '"' + disabledAttr + '>' + buildResourceOptions(row.system_id, entry && entry.resource_id, entry && entry.resource_name) + '</select>' +
        '<input class="inp ase-energy-value" type="number" min="0" value="' + esc(String((entry && entry.energy_value) || '')) + '"' + disabledAttr + '>' +
        '<input class="inp ase-energy-sort" type="number" value="' + esc(String((entry && entry.sort_order) || 0)) + '"' + disabledAttr + '>' +
        '<button class="btn btn-red" type="button" data-energy-remove="1"' + disabledAttr + '>Quitar</button>' +
      '</div>';
  }

  function renderSchemaStatus(schemaState){
    if (!schemaStatus) return;
    var labels = {
      dim_breeds: 'Razas',
      dim_auspices: 'Auspicios',
      dim_tribes: 'Tribus',
      fact_misc_systems: 'Misc'
    };
    var html = '';
    Object.keys(labels).forEach(function(key){
      var ok = !!(schemaState && schemaState[key] && schemaState[key].bridge_table);
      var pending = parseInt((((state.legacy_status || {})[key] || {}).pending_count || 0), 10) || 0;
      html += '<span class="ase-pill">' + esc(labels[key]) + ': ' + (ok ? 'OK' : 'Pendiente') + (pending > 0 ? ' · legacy pendientes ' + pending : '') + '</span>';
    });
    schemaStatus.innerHTML = html;
  }

  function pendingLabelForTable(tableName){
    var labels = {
      dim_breeds: 'Razas',
      dim_auspices: 'Auspicios',
      dim_tribes: 'Tribus',
      fact_misc_systems: 'Misc'
    };
    return labels[tableName] || tableName;
  }

  function pendingRowsForTable(tableName){
    return ((state.legacy_pending_rows || {})[tableName] || []);
  }

  function formatPendingRow(row){
    var text = '#' + (parseInt(row.id || 0, 10) || 0) + ' ' + String(row.name || '');
    if (row.system_name) text += ' (' + String(row.system_name) + ')';
    return text;
  }

  function renderRows(rows){
    if (!tbody) return;
    if (!Array.isArray(rows) || !rows.length) {
      tbody.innerHTML = '<tr><td colspan="5" class="ase-muted">(Sin resultados)</td></tr>';
      return;
    }
    var html = '';
    rows.forEach(function(row){
      var energyRows = Array.isArray(row.energy_resources_rows) ? row.energy_resources_rows : [];
      html += '<tr data-row-id="' + (parseInt(row.id || 0, 10) || 0) + '" data-system-id="' + (parseInt(row.system_id || 0, 10) || 0) + '" data-search="' + esc(rowSearchText(row)) + '">';
      html += '<td><strong class="adm-color-accent">' + (parseInt(row.id || 0, 10) || 0) + '</strong></td>';
      html += '<td>' + esc(row.name || '') + '</td>';
      html += '<td>' + esc(row.system_name || '') + '</td>';
      html += '<td>' + (parseInt(row.energy || 0, 10) || 0) + '</td>';
      html += '<td class="ase-energy-cell">';
      html += '<div class="ase-energy-head"><div>Recurso</div><div>Valor</div><div>Orden</div><div></div></div>';
      html += '<div class="ase-energy-list">';
      if (energyRows.length) {
        energyRows.forEach(function(entry, idx){ html += buildEnergyRow(row, entry, idx); });
      }
      html += '</div>';
      html += '<div class="ase-energy-actions"><button class="btn" type="button" data-energy-add="1"' + (state.schema_ready ? '' : ' disabled') + '>+ Recurso</button></div>';
      html += '</td></tr>';
    });
    tbody.innerHTML = html;
    applyQuickFilter();
  }

  function renderLegacyPending(){
    if (!legacyPending) return;
    var tableName = (((state.meta || {}).table) || '');
    var rows = pendingRowsForTable(tableName);
    if (!rows.length) {
      legacyPending.innerHTML = '';
      legacyPending.style.display = 'none';
      return;
    }
    legacyPending.style.display = '';
    legacyPending.innerHTML = 'Pendientes legacy en ' + esc(pendingLabelForTable(tableName)) + ': ' + rows.map(formatPendingRow).map(esc).join(' · ');
  }

  function renderState(nextState){
    state = nextState || state;
    tabs.forEach(function(tabBtn){
      tabBtn.classList.toggle('active', tabBtn.dataset.tab === state.tab);
    });
    if (systemFilter) systemFilter.value = String(parseInt(state.system_id || 0, 10) || 0);
    if (saveBtn) saveBtn.disabled = !state.schema_ready;
    if (legacyBtn) {
      var blockers = ['dim_breeds','dim_auspices','dim_tribes','fact_misc_systems'].map(function(key){
        var row = ((state.legacy_status || {})[key] || {});
        var hasLegacy = !!(row.has_energy || row.has_resource || row.has_energy_name);
        var pending = parseInt(row.pending_count || 0, 10) || 0;
        if (hasLegacy && !row.can_retire) {
          var names = pendingRowsForTable(key).slice(0, 3).map(function(entry){ return formatPendingRow(entry); }).join(', ');
          return pendingLabelForTable(key) + ':' + pending + (names ? ' [' + names + ']' : '');
        }
        return '';
      }).filter(Boolean);
      legacyBtn.disabled = false;
      legacyBtn.title = blockers.length ? ('Pendientes antes de retirar legacy: ' + blockers.join(', ')) : 'Retirar columnas legacy';
    }
    renderSchemaStatus(state.schema_status || {});
    renderLegacyPending();
    renderRows(state.rows || []);
  }

  function applyQuickFilter(){
    if (!tbody || !quickFilter) return;
    var q = String(quickFilter.value || '').trim().toLowerCase();
    tbody.querySelectorAll('tr').forEach(function(row){
      row.style.display = (!q || String(row.dataset.search || '').indexOf(q) !== -1) ? '' : 'none';
    });
  }

  function currentStateUrl(){
    var url = new URL(window.location.href);
    url.searchParams.set('ajax', '1');
    url.searchParams.set('ajax_mode', 'state');
    url.searchParams.set('tab', state.tab || 'breeds');
    url.searchParams.set('system_id', String(parseInt(systemFilter && systemFilter.value || state.system_id || 0, 10) || 0));
    return url.toString();
  }

  async function fetchState(){
    var payload = await window.HGAdminHttp.request(currentStateUrl(), { method: 'GET' });
    var nextState = payload && payload.data ? payload.data.state : null;
    if (!payload || payload.ok === false || !nextState) throw payload || new Error('No se pudo cargar el estado.');
    renderState(nextState);
  }

  async function postAction(action, body, loadingEl){
    var fd = new FormData();
    fd.set('csrf', window.ADMIN_CSRF_TOKEN || '');
    fd.set('action', action);
    fd.set('tab', state.tab || 'breeds');
    fd.set('system_id', String(parseInt(systemFilter && systemFilter.value || state.system_id || 0, 10) || 0));
    fd.set('allow_all_state_resources', useAllStateResources() ? '1' : '0');
    Object.keys(body || {}).forEach(function(key){
      var value = body[key];
      if (value && typeof value === 'object' && !Array.isArray(value)) {
        Object.keys(value).forEach(function(innerKey){
          var innerValue = value[innerKey];
          if (Array.isArray(innerValue)) {
            innerValue.forEach(function(entry, entryIdx){
              Object.keys(entry || {}).forEach(function(entryKey){
                fd.set(key + '[' + innerKey + '][' + entryIdx + '][' + entryKey + ']', String(entry[entryKey] ?? ''));
              });
            });
          } else {
            fd.set(key + '[' + innerKey + ']', String(innerValue ?? ''));
          }
        });
      } else {
        fd.set(key, String(value ?? ''));
      }
    });
    var payload = await window.HGAdminHttp.request(endpoint, {
      method: 'POST',
      body: fd,
      loadingEl: loadingEl
    });
    if (!payload || payload.ok === false) throw payload || new Error('Error en la operación.');
    var nextState = payload && payload.data ? payload.data.state : null;
    if (nextState) renderState(nextState);
    if (window.HGAdminHttp && window.HGAdminHttp.notify) {
      window.HGAdminHttp.notify(payload.message || 'Guardado', 'success');
    }
  }

  function refreshRowOptions(tr){
    if (!tr) return;
    var systemId = parseInt(tr.dataset.systemId || '0', 10) || 0;
    tr.querySelectorAll('select.ase-energy-resource').forEach(function(select){
      var currentValue = select.value || '0';
      var currentLabel = '';
      if (select.selectedOptions && select.selectedOptions[0]) currentLabel = select.selectedOptions[0].textContent || '';
      select.innerHTML = buildResourceOptions(systemId, currentValue, currentLabel);
    });
  }

  function addEnergyRow(tr, entry){
    var list = tr && tr.querySelector('.ase-energy-list');
    if (!list) return;
    var row = {
      system_id: parseInt(tr.dataset.systemId || '0', 10) || 0
    };
    var idx = list.querySelectorAll('[data-energy-row]').length;
    var holder = document.createElement('div');
    holder.innerHTML = buildEnergyRow(row, entry || {}, idx);
    list.appendChild(holder.firstChild);
  }

  function collectUpdates(){
    var updates = {};
    tbody.querySelectorAll('tr[data-row-id]').forEach(function(tr){
      var rowId = parseInt(tr.dataset.rowId || '0', 10) || 0;
      if (!rowId) return;
      updates[rowId] = [];
      tr.querySelectorAll('[data-energy-row]').forEach(function(line){
        updates[rowId].push({
          resource_id: parseInt((line.querySelector('.ase-energy-resource') || {}).value || '0', 10) || 0,
          energy_value: parseInt((line.querySelector('.ase-energy-value') || {}).value || '0', 10) || 0,
          sort_order: parseInt((line.querySelector('.ase-energy-sort') || {}).value || '0', 10) || 0,
          is_active: 1
        });
      });
    });
    return updates;
  }

  tabs.forEach(function(tabBtn){
    tabBtn.addEventListener('click', function(){
      state.tab = tabBtn.dataset.tab || 'breeds';
      fetchState().catch(function(err){
        alert((window.HGAdminHttp && window.HGAdminHttp.errorMessage) ? window.HGAdminHttp.errorMessage(err) : (err.message || 'Error'));
      });
    });
  });

  if (quickFilter) quickFilter.addEventListener('input', applyQuickFilter);
  if (systemFilter) {
    systemFilter.addEventListener('change', function(){
      fetchState().catch(function(err){
        alert((window.HGAdminHttp && window.HGAdminHttp.errorMessage) ? window.HGAdminHttp.errorMessage(err) : (err.message || 'Error'));
      });
    });
  }
  if (reloadBtn) {
    reloadBtn.addEventListener('click', function(){
      fetchState().catch(function(err){
        alert((window.HGAdminHttp && window.HGAdminHttp.errorMessage) ? window.HGAdminHttp.errorMessage(err) : (err.message || 'Error'));
      });
    });
  }
  if (allowAllStateResources) {
    allowAllStateResources.addEventListener('change', function(){
      refreshVisibleResourceOptions();
    });
  }
  if (schemaBtn) {
    schemaBtn.addEventListener('click', function(){
      postAction('schema_apply', {}, schemaBtn).catch(function(err){
        alert((window.HGAdminHttp && window.HGAdminHttp.errorMessage) ? window.HGAdminHttp.errorMessage(err) : (err.message || 'Error'));
      });
    });
  }
  if (legacyBtn) {
    legacyBtn.addEventListener('click', function(){
      var blockers = ['dim_breeds','dim_auspices','dim_tribes','fact_misc_systems'].map(function(key){
        var row = ((state.legacy_status || {})[key] || {});
        var hasLegacy = !!(row.has_energy || row.has_resource || row.has_energy_name);
        var pending = parseInt(row.pending_count || 0, 10) || 0;
        if (hasLegacy && !row.can_retire) {
          var names = pendingRowsForTable(key).slice(0, 5).map(function(entry){ return formatPendingRow(entry); }).join(', ');
          return pendingLabelForTable(key) + ' (' + pending + ')' + (names ? ': ' + names : '');
        }
        return '';
      }).filter(Boolean);
      if (blockers.length) {
        alert('Aun quedan legacy pendientes: ' + blockers.join(', '));
        return;
      }
      postAction('legacy_retire', {}, legacyBtn).catch(function(err){
        alert((window.HGAdminHttp && window.HGAdminHttp.errorMessage) ? window.HGAdminHttp.errorMessage(err) : (err.message || 'Error'));
      });
    });
  }
  if (saveBtn) {
    saveBtn.addEventListener('click', function(){
      postAction('save_assignments', { updates: collectUpdates() }, saveBtn).catch(function(err){
        alert((window.HGAdminHttp && window.HGAdminHttp.errorMessage) ? window.HGAdminHttp.errorMessage(err) : (err.message || 'Error'));
      });
    });
  }

  if (tbody) {
    tbody.addEventListener('click', function(ev){
      var addBtn = ev.target && ev.target.closest ? ev.target.closest('[data-energy-add]') : null;
      if (addBtn) {
        ev.preventDefault();
        if (!state.schema_ready) return;
        addEnergyRow(addBtn.closest('tr[data-row-id]'), {});
        return;
      }
      var removeBtn = ev.target && ev.target.closest ? ev.target.closest('[data-energy-remove]') : null;
      if (removeBtn) {
        ev.preventDefault();
        var row = removeBtn.closest('[data-energy-row]');
        if (row) row.remove();
      }
    });
    tbody.addEventListener('change', function(ev){
      var select = ev.target && ev.target.closest ? ev.target.closest('select.ase-energy-resource') : null;
      if (!select) return;
      refreshRowOptions(select.closest('tr[data-row-id]'));
    });
  }

  renderState(state);
})();
</script>

<?php admin_panel_close(); ?>
