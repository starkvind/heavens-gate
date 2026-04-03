<?php
// admin_sim_character_talk.php - CRUD de frases del simulador (fact_sim_characters_talk)

include_once(__DIR__ . '/../../helpers/admin_ajax.php');
if (!hg_admin_require_db($link)) { return; }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
include(__DIR__ . '/../../partials/admin/admin_styles.php');
include_once(__DIR__ . '/../../helpers/admin_ajax.php');

$isAjaxRequest = (
    ((string)($_GET['ajax'] ?? '') === '1')
    || (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest')
);

if (!function_exists('hg_ast_h')) {
    function hg_ast_h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

if (!function_exists('hg_ast_table_exists')) {
    function hg_ast_table_exists(mysqli $db, string $table): bool
    {
        $safe = $db->real_escape_string(str_replace('`', '', $table));
        $rs = $db->query("SHOW TABLES LIKE '{$safe}'");
        if (!$rs) return false;
        $ok = ($rs->num_rows > 0);
        $rs->close();
        return $ok;
    }
}

if (!function_exists('hg_ast_column_exists')) {
    function hg_ast_column_exists(mysqli $db, string $table, string $column): bool
    {
        $table = str_replace('`', '', $table);
        $column = str_replace('`', '', $column);
        $rs = $db->query("SHOW COLUMNS FROM `{$table}` LIKE '".$db->real_escape_string($column)."'");
        if (!$rs) return false;
        $ok = ($rs->num_rows > 0);
        $rs->close();
        return $ok;
    }
}

if (!function_exists('hg_ast_bootstrap_table')) {
    function hg_ast_bootstrap_table(mysqli $db): array
    {
        $meta = array('table_exists' => false, 'flags_column' => false);

        if (!hg_ast_table_exists($db, 'fact_sim_characters_talk')) {
            return $meta;
        }
        $meta['table_exists'] = true;

        if (!hg_ast_column_exists($db, 'fact_sim_characters_talk', 'flags')) {
            // Backward compatible extension for admin-managed placeholders/flags.
            $db->query("ALTER TABLE fact_sim_characters_talk ADD COLUMN flags VARCHAR(255) NULL DEFAULT NULL AFTER phrase");
        }
        $meta['flags_column'] = hg_ast_column_exists($db, 'fact_sim_characters_talk', 'flags');
        return $meta;
    }
}

if (!function_exists('hg_ast_characters_kind_clause')) {
    function hg_ast_characters_kind_clause(mysqli $db, string $alias = ''): string
    {
        $alias = trim($alias);
        $prefix = ($alias !== '') ? ($alias . '.') : '';
        if (hg_ast_column_exists($db, 'fact_characters', 'character_kind')) {
            return " AND {$prefix}character_kind = 'pj'";
        }
        return '';
    }
}

$ADMIN_CSRF_SESSION_KEY = 'csrf_admin_sim_character_talk';
$CSRF = function_exists('hg_admin_ensure_csrf_token')
    ? hg_admin_ensure_csrf_token($ADMIN_CSRF_SESSION_KEY)
    : '';

$tableMeta = hg_ast_bootstrap_table($link);
$tableExists = !empty($tableMeta['table_exists']);
$hasFlagsColumn = !empty($tableMeta['flags_column']);

if (!function_exists('hg_ast_talk_types')) {
    function hg_ast_talk_types(): array
    {
        return array('victory', 'defeat', 'draw', 'intro', 'taunt', 'generic');
    }
}

if (!function_exists('hg_ast_collect_payload')) {
    function hg_ast_collect_payload(): array
    {
        $payload = function_exists('hg_admin_read_json_payload') ? hg_admin_read_json_payload() : array();
        if (!is_array($payload)) $payload = array();
        foreach ($_POST as $k => $v) {
            if (!array_key_exists($k, $payload)) $payload[$k] = $v;
        }
        return $payload;
    }
}

if (!function_exists('hg_ast_parse_bool')) {
    function hg_ast_parse_bool($v): int
    {
        if (is_bool($v)) return $v ? 1 : 0;
        $s = strtolower(trim((string)$v));
        if ($s === '1' || $s === 'true' || $s === 'yes' || $s === 'on') return 1;
        return 0;
    }
}

if ((isset($_GET['ajax']) && $_GET['ajax'] === '1') || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
    if (function_exists('hg_admin_require_session')) {
        hg_admin_require_session(true);
    }
    if (!$tableExists) {
        hg_admin_json_error('La tabla fact_sim_characters_talk no existe.', 400, array('table' => 'missing'));
    }

    $payload = hg_ast_collect_payload();
    $action = strtolower(trim((string)($payload['action'] ?? $_GET['action'] ?? 'list')));

    if ($action === 'list') {
        $q = trim((string)($payload['q'] ?? ''));
        $talkType = trim((string)($payload['talk_type'] ?? ''));
        $characterId = isset($payload['character_id']) ? (int)$payload['character_id'] : -1;
        $onlyActive = isset($payload['only_active']) ? hg_ast_parse_bool($payload['only_active']) : -1;
        $limit = isset($payload['limit']) ? (int)$payload['limit'] : 500;
        if ($limit < 1) $limit = 1;
        if ($limit > 1000) $limit = 1000;

        $where = array("t.phrase IS NOT NULL", "TRIM(t.phrase) <> ''");

        if ($talkType !== '') {
            $where[] = "t.talk_type = '".$link->real_escape_string($talkType)."'";
        }
        if ($characterId >= 0) {
            if ($characterId === 0) {
                $where[] = "(t.character_id IS NULL OR t.character_id = 0)";
            } else {
                $where[] = "t.character_id = ".(int)$characterId;
            }
        }
        if ($onlyActive === 0 || $onlyActive === 1) {
            $where[] = "t.is_active = ".(int)$onlyActive;
        }
        if ($q !== '') {
            $like = $link->real_escape_string('%' . $q . '%');
            $where[] = "(t.phrase LIKE '{$like}' OR t.talk_type LIKE '{$like}'".($hasFlagsColumn ? " OR COALESCE(t.flags, '') LIKE '{$like}'" : "").")";
        }

        $selectFlags = $hasFlagsColumn ? "COALESCE(t.flags, '') AS flags" : "'' AS flags";
        $sql = "
            SELECT
                t.id,
                COALESCE(t.character_id, 0) AS character_id,
                COALESCE(c.name, '[Generica]') AS character_name,
                t.talk_type,
                t.phrase,
                t.is_active,
                t.weight,
                {$selectFlags}
            FROM fact_sim_characters_talk t
            LEFT JOIN fact_characters c ON c.id = t.character_id
            WHERE ".implode(' AND ', $where)."
            ORDER BY t.talk_type ASC, t.character_id ASC, t.weight DESC, t.id DESC
            LIMIT {$limit}
        ";

        $rows = array();
        if ($rs = $link->query($sql)) {
            while ($r = $rs->fetch_assoc()) { $rows[] = $r; }
            $rs->close();
        }

        hg_admin_json_success(
            array('rows' => $rows),
            'Listado',
            array('count' => count($rows), 'has_flags_column' => $hasFlagsColumn)
        );
    }

    if ($action === 'save') {
        $csrfToken = function_exists('hg_admin_extract_csrf_token')
            ? hg_admin_extract_csrf_token($payload)
            : (string)($payload['csrf'] ?? '');
        $csrfOk = function_exists('hg_admin_csrf_valid')
            ? hg_admin_csrf_valid($csrfToken, $ADMIN_CSRF_SESSION_KEY)
            : (is_string($csrfToken) && $csrfToken !== '' && isset($_SESSION[$ADMIN_CSRF_SESSION_KEY]) && hash_equals($_SESSION[$ADMIN_CSRF_SESSION_KEY], $csrfToken));
        if (!$csrfOk) {
            hg_admin_json_error('CSRF invalido. Recarga la pagina.', 403, array('csrf' => 'invalid'));
        }

        $id = isset($payload['id']) ? (int)$payload['id'] : 0;
        $characterIdRaw = isset($payload['character_id']) ? (int)$payload['character_id'] : 0;
        $characterId = ($characterIdRaw > 0) ? $characterIdRaw : null;
        $talkType = strtolower(trim((string)($payload['talk_type'] ?? 'victory')));
        $phrase = trim((string)($payload['phrase'] ?? ''));
        $flags = trim((string)($payload['flags'] ?? ''));
        $weight = isset($payload['weight']) ? (int)$payload['weight'] : 1;
        $isActive = hg_ast_parse_bool($payload['is_active'] ?? 1);

        if ($phrase === '') {
            hg_admin_json_error('La frase no puede estar vacia.', 422, array('phrase' => 'required'));
        }
        if (strlen($phrase) > 500) {
            hg_admin_json_error('La frase supera los 500 caracteres.', 422, array('phrase' => 'too_long'));
        }
        if (!preg_match('/^[a-z0-9_\\-]{2,32}$/', $talkType)) {
            hg_admin_json_error('talk_type invalido (usa minusculas, numeros, _ o -).', 422, array('talk_type' => 'invalid'));
        }
        if ($weight < 1) $weight = 1;
        if ($weight > 100) $weight = 100;
        if (strlen($flags) > 255) {
            hg_admin_json_error('flags supera 255 caracteres.', 422, array('flags' => 'too_long'));
        }
        if ($characterId !== null) {
            $existsChar = 0;
            $kindClause = hg_ast_characters_kind_clause($link);
            if ($st = $link->prepare("SELECT COUNT(*) FROM fact_characters WHERE id=?{$kindClause}")) {
                $st->bind_param('i', $characterId);
                $st->execute();
                $st->bind_result($existsChar);
                $st->fetch();
                $st->close();
            }
            if ($existsChar <= 0) {
                hg_admin_json_error('El personaje seleccionado no es valido para frases de PJ.', 422, array('character_id' => 'invalid_kind'));
            }
        }

        $flagsValue = ($flags === '') ? null : $flags;

        if ($id > 0) {
            if ($hasFlagsColumn) {
                $sql = "UPDATE fact_sim_characters_talk
                        SET character_id=?, talk_type=?, phrase=?, flags=?, is_active=?, weight=?
                        WHERE id=?
                        LIMIT 1";
                if ($st = $link->prepare($sql)) {
                    $st->bind_param('isssiii', $characterId, $talkType, $phrase, $flagsValue, $isActive, $weight, $id);
                    if (!$st->execute()) {
                        $st->close();
                        hg_admin_json_error('No se pudo actualizar la frase.', 500, array('db' => 'update_failed'));
                    }
                    $st->close();
                    hg_admin_json_success(array('id' => $id), 'Frase actualizada.');
                }
                hg_admin_json_error('Error al preparar UPDATE.', 500, array('db' => 'prepare_failed'));
            } else {
                $sql = "UPDATE fact_sim_characters_talk
                        SET character_id=?, talk_type=?, phrase=?, is_active=?, weight=?
                        WHERE id=?
                        LIMIT 1";
                if ($st = $link->prepare($sql)) {
                    $st->bind_param('issiii', $characterId, $talkType, $phrase, $isActive, $weight, $id);
                    if (!$st->execute()) {
                        $st->close();
                        hg_admin_json_error('No se pudo actualizar la frase.', 500, array('db' => 'update_failed'));
                    }
                    $st->close();
                    hg_admin_json_success(array('id' => $id), 'Frase actualizada.');
                }
                hg_admin_json_error('Error al preparar UPDATE.', 500, array('db' => 'prepare_failed'));
            }
        }

        if ($hasFlagsColumn) {
            $sql = "INSERT INTO fact_sim_characters_talk (character_id, talk_type, phrase, flags, is_active, weight)
                    VALUES (?, ?, ?, ?, ?, ?)";
            if ($st = $link->prepare($sql)) {
                $st->bind_param('isssii', $characterId, $talkType, $phrase, $flagsValue, $isActive, $weight);
                if (!$st->execute()) {
                    $st->close();
                    hg_admin_json_error('No se pudo crear la frase.', 500, array('db' => 'insert_failed'));
                }
                $newId = (int)$st->insert_id;
                $st->close();
                hg_admin_json_success(array('id' => $newId), 'Frase creada.');
            }
            hg_admin_json_error('Error al preparar INSERT.', 500, array('db' => 'prepare_failed'));
        } else {
            $sql = "INSERT INTO fact_sim_characters_talk (character_id, talk_type, phrase, is_active, weight)
                    VALUES (?, ?, ?, ?, ?)";
            if ($st = $link->prepare($sql)) {
                $st->bind_param('issii', $characterId, $talkType, $phrase, $isActive, $weight);
                if (!$st->execute()) {
                    $st->close();
                    hg_admin_json_error('No se pudo crear la frase.', 500, array('db' => 'insert_failed'));
                }
                $newId = (int)$st->insert_id;
                $st->close();
                hg_admin_json_success(array('id' => $newId), 'Frase creada.');
            }
            hg_admin_json_error('Error al preparar INSERT.', 500, array('db' => 'prepare_failed'));
        }
    }

    if ($action === 'delete') {
        $csrfToken = function_exists('hg_admin_extract_csrf_token')
            ? hg_admin_extract_csrf_token($payload)
            : (string)($payload['csrf'] ?? '');
        $csrfOk = function_exists('hg_admin_csrf_valid')
            ? hg_admin_csrf_valid($csrfToken, $ADMIN_CSRF_SESSION_KEY)
            : (is_string($csrfToken) && $csrfToken !== '' && isset($_SESSION[$ADMIN_CSRF_SESSION_KEY]) && hash_equals($_SESSION[$ADMIN_CSRF_SESSION_KEY], $csrfToken));
        if (!$csrfOk) {
            hg_admin_json_error('CSRF invalido. Recarga la pagina.', 403, array('csrf' => 'invalid'));
        }

        $id = isset($payload['id']) ? (int)$payload['id'] : 0;
        if ($id <= 0) {
            hg_admin_json_error('ID invalido.', 422, array('id' => 'invalid'));
        }
        if ($st = $link->prepare("DELETE FROM fact_sim_characters_talk WHERE id=? LIMIT 1")) {
            $st->bind_param('i', $id);
            if (!$st->execute()) {
                $st->close();
                hg_admin_json_error('No se pudo eliminar la frase.', 500, array('db' => 'delete_failed'));
            }
            $st->close();
            hg_admin_json_success(array('id' => $id), 'Frase eliminada.');
        }
        hg_admin_json_error('Error al preparar DELETE.', 500, array('db' => 'prepare_failed'));
    }

    hg_admin_json_error('Accion no soportada.', 400, array('action' => 'unsupported'));
}

$characters = array();
if ($tableExists) {
    $kindClause = hg_ast_characters_kind_clause($link, 'c');
    if ($rs = $link->query("
        SELECT
            c.id,
            c.name,
            COALESCE(ch.name, 'Sin cronica') AS chronicle_name
        FROM fact_characters c
        LEFT JOIN dim_chronicles ch ON ch.id = c.chronicle_id
        WHERE 1=1{$kindClause}
        ORDER BY c.name ASC, c.id ASC
    ")) {
        while ($r = $rs->fetch_assoc()) { $characters[] = $r; }
        $rs->close();
    }
}
$talkTypes = hg_ast_talk_types();

if (!$isAjaxRequest) {
    $actions = '<span class="adm-flex-right-8">'
        . '<button class="btn btn-green" type="button" id="astQuickNewBtn">+ Nueva frase</button>'
        . '<label class="adm-text-left">Filtro rapido '
        . '<input class="inp" type="text" id="astSearch" placeholder="Frase o flags..."></label>'
        . '</span>';
    admin_panel_open('Frases del Simulador', $actions);
}
?>

<?php if (!$tableExists): ?>
  <p class="adm-admin-error">No existe <code>fact_sim_characters_talk</code>. Ejecuta el upgrade del simulador antes de usar este panel.</p>
<?php else: ?>
  <div class="adm-grid-1-2" style="margin-bottom:10px;">
    <div>
      <label>Tipo</label>
      <select class="inp" id="astFilterType">
        <option value="">Todos los tipos</option>
        <?php foreach ($talkTypes as $tt): ?>
          <option value="<?php echo hg_ast_h($tt); ?>"><?php echo hg_ast_h($tt); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label>Personaje</label>
      <select class="inp" id="astFilterCharacter">
        <option value="-1">Todos los personajes</option>
        <option value="0">Genericas</option>
        <?php foreach ($characters as $ch): ?>
          <option value="<?php echo (int)$ch['id']; ?>"><?php echo hg_ast_h((string)$ch['name'] . ' [ID:' . (int)$ch['id'] . '] [' . (string)($ch['chronicle_name'] ?? 'Sin cronica') . ']'); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
  <div style="margin:0 0 10px 0;">
    <label><input type="checkbox" id="astOnlyActive" checked> Solo activas</label>
  </div>

  <input type="hidden" id="astId" value="0">
  <fieldset class="bioSeccion" style="margin:0 0 10px 0;">
    <legend>&nbsp;Editor de frase&nbsp;</legend>
    <div class="adm-grid-1-2">
      <div>
        <label>Personaje</label>
        <select class="inp" id="astCharacter">
          <option value="0">Generica (sin personaje)</option>
          <?php foreach ($characters as $ch): ?>
            <option value="<?php echo (int)$ch['id']; ?>"><?php echo hg_ast_h((string)$ch['name'] . ' [ID:' . (int)$ch['id'] . '] [' . (string)($ch['chronicle_name'] ?? 'Sin cronica') . ']'); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Tipo</label>
        <select class="inp" id="astType">
          <?php foreach ($talkTypes as $tt): ?>
            <option value="<?php echo hg_ast_h($tt); ?>"><?php echo hg_ast_h($tt); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Flags (opcional)</label>
        <input class="inp" type="text" id="astFlags" placeholder="rival_name,winner_name" <?php echo $hasFlagsColumn ? '' : 'disabled'; ?>>
      </div>
      <div>
        <label>Peso</label>
        <input class="inp" type="number" id="astWeight" min="1" max="100" value="1">
      </div>
      <div>
        <label>Estado</label>
        <label><input type="checkbox" id="astActive" checked> Activa</label>
      </div>
    </div>
    <label>Frase</label>
    <textarea class="ta" id="astPhrase" rows="4" placeholder="Tokens: {winner}, {loser}, {winner_alias}, {loser_alias}, {winner_real_name}, {loser_real_name}, {winner_id}, {loser_id}, %WINNER%, %LOSER%, {bd_char:100}"></textarea>
    <div style="margin-top:8px;">
      <button type="button" class="btn btn-green" id="astSaveBtn">Guardar frase</button>
      <button type="button" class="btn" id="astResetBtn">Nueva</button>
      <span id="astFormMsg" class="adm-color-muted" style="margin-left:8px;"></span>
    </div>
  </fieldset>

  <table class="table" id="astTable">
    <thead>
      <tr>
        <th class="adm-w-60">ID</th>
        <th>Tipo</th>
        <th>Personaje</th>
        <th>Frase</th>
        <th>Flags</th>
        <th class="adm-w-60">Peso</th>
        <th class="adm-w-60">Activa</th>
        <th class="adm-w-160">Acciones</th>
      </tr>
    </thead>
    <tbody id="astRows">
      <tr><td colspan="8" class="adm-color-muted">Cargando...</td></tr>
    </tbody>
  </table>
<?php endif; ?>

<?php if ($tableExists): ?>
<?php
$adminHttpJs = '/assets/js/admin/admin-http.js';
$adminHttpJsVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . $adminHttpJs) ?: time();
?>
<script src="<?php echo hg_ast_h($adminHttpJs); ?>?v=<?php echo (int)$adminHttpJsVer; ?>"></script>
<script>
window.ADMIN_CSRF_TOKEN = <?php echo json_encode($CSRF, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
(function(){
  var endpoint = '/talim?s=admin_sim_character_talk&ajax=1';
  var $ = function(id){ return document.getElementById(id); };
  var state = { rows: [] };

  function esc(v){
    return String(v == null ? '' : v)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function formMessage(msg, ok) {
    var node = $('astFormMsg');
    if (!node) return;
    node.className = ok ? 'adm-color-muted' : 'adm-color-error';
    node.style.color = ok ? '#9be7b1' : '';
    node.textContent = msg || '';
  }

  function resetForm() {
    $('astId').value = '0';
    $('astCharacter').value = '0';
    $('astType').value = 'victory';
    if ($('astFlags')) $('astFlags').value = '';
    $('astWeight').value = '1';
    $('astActive').checked = true;
    $('astPhrase').value = '';
    formMessage('', true);
  }

  function fillForm(row) {
    $('astId').value = String(row.id || 0);
    $('astCharacter').value = String(row.character_id || 0);
    $('astType').value = String(row.talk_type || 'victory');
    if ($('astFlags')) $('astFlags').value = String(row.flags || '');
    $('astWeight').value = String(row.weight || 1);
    $('astActive').checked = Number(row.is_active || 0) === 1;
    $('astPhrase').value = String(row.phrase || '');
  }

  function renderRows() {
    var tbody = $('astRows');
    if (!tbody) return;
    if (!state.rows.length) {
      tbody.innerHTML = '<tr><td colspan="8" class="adm-color-muted">(Sin frases para el filtro actual)</td></tr>';
      return;
    }
    var html = '';
    for (var i = 0; i < state.rows.length; i++) {
      var r = state.rows[i];
      html += '<tr data-id="' + Number(r.id || 0) + '">';
      html += '<td>' + Number(r.id || 0) + '</td>';
      html += '<td>' + esc(r.talk_type || '') + '</td>';
      html += '<td>' + esc(r.character_name || '[Generica]') + '</td>';
      html += '<td>' + esc(r.phrase || '') + '</td>';
      html += '<td>' + esc(r.flags || '') + '</td>';
      html += '<td>' + Number(r.weight || 1) + '</td>';
      html += '<td>' + (Number(r.is_active || 0) === 1 ? 'Si' : 'No') + '</td>';
      html += '<td>';
      html += '<button type="button" class="btn" data-edit="' + Number(r.id || 0) + '">Editar</button> ';
      html += '<button type="button" class="btn btn-red" data-del="' + Number(r.id || 0) + '">Borrar</button>';
      html += '</td>';
      html += '</tr>';
    }
    tbody.innerHTML = html;
  }

  async function refreshList() {
    try {
      var payload = {
        q: $('astSearch').value || '',
        talk_type: $('astFilterType').value || '',
        character_id: parseInt($('astFilterCharacter').value || '-1', 10),
        only_active: $('astOnlyActive').checked ? 1 : 0,
        limit: 700
      };
      var res = await HGAdminHttp.postAction(endpoint, 'list', payload, { loadingEl: $('astRows') });
      state.rows = (res && res.data && Array.isArray(res.data.rows)) ? res.data.rows : [];
      renderRows();
    } catch (err) {
      state.rows = [];
      renderRows();
      formMessage(HGAdminHttp.errorMessage(err), false);
    }
  }

  async function saveCurrent() {
    var phrase = $('astPhrase').value || '';
    if (!phrase.trim()) {
      formMessage('La frase no puede estar vacia.', false);
      return;
    }
    var payload = {
      id: parseInt($('astId').value || '0', 10) || 0,
      character_id: parseInt($('astCharacter').value || '0', 10) || 0,
      talk_type: $('astType').value || 'victory',
      flags: ($('astFlags') ? $('astFlags').value : ''),
      weight: parseInt($('astWeight').value || '1', 10) || 1,
      is_active: $('astActive').checked ? 1 : 0,
      phrase: phrase
    };
    try {
      var res = await HGAdminHttp.postAction(endpoint, 'save', payload, { loadingEl: $('astSaveBtn') });
      formMessage((res && res.message) ? res.message : 'Guardado.', true);
      await refreshList();
      if (!payload.id) resetForm();
    } catch (err) {
      formMessage(HGAdminHttp.errorMessage(err), false);
    }
  }

  async function removeRow(id) {
    if (!id || id <= 0) return;
    if (!confirm('Se eliminara la frase #' + id + '. Continuar?')) return;
    try {
      var res = await HGAdminHttp.postAction(endpoint, 'delete', { id: id }, { loadingEl: $('astRows') });
      formMessage((res && res.message) ? res.message : 'Eliminada.', true);
      await refreshList();
      if (parseInt($('astId').value || '0', 10) === id) resetForm();
    } catch (err) {
      formMessage(HGAdminHttp.errorMessage(err), false);
    }
  }

  function bindEvents() {
    ['astSearch', 'astFilterType', 'astFilterCharacter', 'astOnlyActive'].forEach(function(id){
      var node = $(id);
      if (!node) return;
      node.addEventListener(id === 'astSearch' ? 'input' : 'change', refreshList);
    });

    $('astSaveBtn').addEventListener('click', saveCurrent);
    $('astResetBtn').addEventListener('click', resetForm);
    if ($('astQuickNewBtn')) {
      $('astQuickNewBtn').addEventListener('click', function(){ resetForm(); $('astPhrase').focus(); });
    }

    $('astRows').addEventListener('click', function(ev){
      var editBtn = ev.target.closest('[data-edit]');
      if (editBtn) {
        var id = parseInt(editBtn.getAttribute('data-edit') || '0', 10) || 0;
        var row = state.rows.find(function(r){ return Number(r.id || 0) === id; });
        if (row) fillForm(row);
        return;
      }
      var delBtn = ev.target.closest('[data-del]');
      if (delBtn) {
        var did = parseInt(delBtn.getAttribute('data-del') || '0', 10) || 0;
        removeRow(did);
      }
    });
  }

  bindEvents();
  resetForm();
  refreshList();
})();
</script>
<?php endif; ?>
<?php if (!$isAjaxRequest) { admin_panel_close(); } ?>

