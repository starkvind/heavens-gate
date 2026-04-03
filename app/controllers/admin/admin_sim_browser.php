<?php
// admin_sim_browser.php - Gestion de browser del simulador por temporadas.

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

if (!function_exists('hg_asb_h')) {
    function hg_asb_h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

if (!function_exists('hg_asb_table_exists')) {
    function hg_asb_table_exists(mysqli $db, string $table): bool
    {
        $safe = $db->real_escape_string(str_replace('`', '', $table));
        $rs = $db->query("SHOW TABLES LIKE '{$safe}'");
        if (!$rs) return false;
        $ok = ($rs->num_rows > 0);
        $rs->close();
        return $ok;
    }
}

if (!function_exists('hg_asb_column_exists')) {
    function hg_asb_column_exists(mysqli $db, string $table, string $column): bool
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

if (!function_exists('hg_asb_characters_kind_clause')) {
    function hg_asb_characters_kind_clause(mysqli $db, string $alias = ''): string
    {
        $alias = trim($alias);
        $prefix = ($alias !== '') ? ($alias . '.') : '';
        if (hg_asb_column_exists($db, 'fact_characters', 'character_kind')) {
            return " AND {$prefix}character_kind = 'pj'";
        }
        if (hg_asb_column_exists($db, 'fact_characters', 'kes')) {
            return " AND {$prefix}kes = 'pj'";
        }
        return '';
    }
}

if (!function_exists('hg_asb_bootstrap_tables_meta')) {
    function hg_asb_bootstrap_tables_meta(mysqli $db): array
    {
        return array(
            'seasons_table' => hg_asb_table_exists($db, 'fact_sim_seasons'),
            'bridge_table' => hg_asb_table_exists($db, 'bridge_battle_sim_characters_seasons'),
        );
    }
}

if (!function_exists('hg_asb_collect_payload')) {
    function hg_asb_collect_payload(): array
    {
        $payload = function_exists('hg_admin_read_json_payload') ? hg_admin_read_json_payload() : array();
        if (!is_array($payload)) $payload = array();
        foreach ($_POST as $k => $v) {
            if (!array_key_exists($k, $payload)) $payload[$k] = $v;
        }
        return $payload;
    }
}

if (!function_exists('hg_asb_parse_bool')) {
    function hg_asb_parse_bool($v): int
    {
        if (is_bool($v)) return $v ? 1 : 0;
        $s = strtolower(trim((string)$v));
        return ($s === '1' || $s === 'true' || $s === 'yes' || $s === 'on') ? 1 : 0;
    }
}

$ADMIN_CSRF_SESSION_KEY = 'csrf_admin_sim_browser';
$CSRF = function_exists('hg_admin_ensure_csrf_token')
    ? hg_admin_ensure_csrf_token($ADMIN_CSRF_SESSION_KEY)
    : '';

$meta = hg_asb_bootstrap_tables_meta($link);
$hasTables = (!empty($meta['seasons_table']) && !empty($meta['bridge_table']));

if ((isset($_GET['ajax']) && $_GET['ajax'] === '1') || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
    if (function_exists('hg_admin_require_session')) {
        hg_admin_require_session(true);
    }

    if (!$hasTables) {
        hg_admin_json_error('Faltan tablas del browser por temporadas. Ejecuta el setup.', 400, array('table' => 'missing'));
    }

    $payload = hg_asb_collect_payload();
    $action = strtolower(trim((string)($payload['action'] ?? $_GET['action'] ?? 'list_seasons')));

    if ($action === 'list_seasons') {
        $rows = array();
        $sql = "
            SELECT
                s.id,
                s.name,
                COALESCE(s.description, '') AS description,
                COALESCE(s.character_limit, 35) AS character_limit,
                s.is_active,
                s.created_at,
                s.updated_at,
                COUNT(b.character_id) AS assigned_count
            FROM fact_sim_seasons s
            LEFT JOIN bridge_battle_sim_characters_seasons b ON b.season_id = s.id
            GROUP BY s.id
            ORDER BY s.is_active DESC, s.updated_at DESC, s.id DESC
        ";
        if ($rs = $link->query($sql)) {
            while ($r = $rs->fetch_assoc()) { $rows[] = $r; }
            $rs->close();
        }
        hg_admin_json_success(array('rows' => $rows), 'Listado de temporadas', array('count' => count($rows)));
    }

    if ($action === 'list_characters') {
        $seasonId = isset($payload['season_id']) ? (int)$payload['season_id'] : 0;
        $q = trim((string)($payload['q'] ?? ''));
        $limit = isset($payload['limit']) ? (int)$payload['limit'] : 1200;
        if ($limit < 1) $limit = 1;
        if ($limit > 2000) $limit = 2000;

        $kindClause = hg_asb_characters_kind_clause($link, 'c');
        $where = array("1=1{$kindClause}");
        if ($q !== '') {
            $like = $link->real_escape_string('%' . $q . '%');
            $where[] = "(c.name LIKE '{$like}' OR COALESCE(c.alias, '') LIKE '{$like}')";
        }

        $assignedExpr = ($seasonId > 0)
            ? "CASE WHEN b.character_id IS NULL THEN 0 ELSE 1 END AS is_assigned"
            : "0 AS is_assigned";
        $joinBridge = ($seasonId > 0)
            ? "LEFT JOIN bridge_battle_sim_characters_seasons b ON b.character_id = c.id AND b.season_id = {$seasonId}"
            : "LEFT JOIN bridge_battle_sim_characters_seasons b ON 1=0";

        $sql = "
            SELECT
                c.id,
                c.name,
                COALESCE(c.alias, '') AS alias,
                {$assignedExpr}
            FROM fact_characters c
            {$joinBridge}
            WHERE ".implode(' AND ', $where)."
            ORDER BY COALESCE(NULLIF(c.alias,''), c.name) ASC, c.id ASC
            LIMIT {$limit}
        ";

        $rows = array();
        if ($rs = $link->query($sql)) {
            while ($r = $rs->fetch_assoc()) { $rows[] = $r; }
            $rs->close();
        }
        hg_admin_json_success(array('rows' => $rows), 'Listado de personajes', array('count' => count($rows)));
    }

    if (in_array($action, array('save_season', 'delete_season', 'set_active', 'save_assignments', 'flush_combat_tables'), true)) {
        $csrfToken = function_exists('hg_admin_extract_csrf_token')
            ? hg_admin_extract_csrf_token($payload)
            : (string)($payload['csrf'] ?? '');
        $csrfOk = function_exists('hg_admin_csrf_valid')
            ? hg_admin_csrf_valid($csrfToken, $ADMIN_CSRF_SESSION_KEY)
            : (is_string($csrfToken) && $csrfToken !== '' && isset($_SESSION[$ADMIN_CSRF_SESSION_KEY]) && hash_equals($_SESSION[$ADMIN_CSRF_SESSION_KEY], $csrfToken));
        if (!$csrfOk) {
            hg_admin_json_error('CSRF inválido. Recarga la página.', 403, array('csrf' => 'invalid'));
        }
    }

    if ($action === 'save_season') {
        $id = isset($payload['id']) ? (int)$payload['id'] : 0;
        $name = trim((string)($payload['name'] ?? ''));
        $description = trim((string)($payload['description'] ?? ''));
        $limit = isset($payload['character_limit']) ? (int)$payload['character_limit'] : 35;
        $isActive = hg_asb_parse_bool($payload['is_active'] ?? 0);

        if ($name === '') {
            hg_admin_json_error('El nombre es obligatorio.', 422, array('name' => 'required'));
        }
        if (strlen($name) > 120) {
            hg_admin_json_error('El nombre supera 120 caracteres.', 422, array('name' => 'too_long'));
        }
        if (strlen($description) > 500) {
            hg_admin_json_error('La descripcion supera 500 caracteres.', 422, array('description' => 'too_long'));
        }
        if ($limit < 1) $limit = 1;
        if ($limit > 200) $limit = 200;

        if ($id > 0) {
            $sql = "UPDATE fact_sim_seasons SET name=?, description=?, character_limit=?, is_active=? WHERE id=? LIMIT 1";
            if ($st = $link->prepare($sql)) {
                $st->bind_param('ssiii', $name, $description, $limit, $isActive, $id);
                if (!$st->execute()) {
                    $st->close();
                    hg_admin_json_error('No se pudo actualizar la temporada.', 500, array('db' => 'update_failed'));
                }
                $st->close();
            } else {
                hg_admin_json_error('Error al preparar UPDATE.', 500, array('db' => 'prepare_failed'));
            }
        } else {
            $sql = "INSERT INTO fact_sim_seasons (name, description, character_limit, is_active) VALUES (?, ?, ?, ?)";
            if ($st = $link->prepare($sql)) {
                $st->bind_param('ssii', $name, $description, $limit, $isActive);
                if (!$st->execute()) {
                    $st->close();
                    hg_admin_json_error('No se pudo crear la temporada.', 500, array('db' => 'insert_failed'));
                }
                $id = (int)$st->insert_id;
                $st->close();
            } else {
                hg_admin_json_error('Error al preparar INSERT.', 500, array('db' => 'prepare_failed'));
            }
        }

        if ($isActive === 1 && $id > 0) {
            $link->query("UPDATE fact_sim_seasons SET is_active = 0 WHERE id <> {$id}");
            $link->query("UPDATE fact_sim_seasons SET is_active = 1 WHERE id = {$id} LIMIT 1");
        }

        hg_admin_json_success(array('id' => $id), 'Temporada guardada.');
    }

    if ($action === 'delete_season') {
        $id = isset($payload['id']) ? (int)$payload['id'] : 0;
        if ($id <= 0) {
            hg_admin_json_error('ID inválido.', 422, array('id' => 'invalid'));
        }

        $isActive = 0;
        if ($rs = $link->query("SELECT is_active FROM fact_sim_seasons WHERE id = {$id} LIMIT 1")) {
            if ($row = $rs->fetch_assoc()) {
                $isActive = (int)($row['is_active'] ?? 0);
            }
            $rs->close();
        }
        if ($isActive === 1) {
            hg_admin_json_error('No puedes borrar la temporada activa. Activa otra primero.', 422, array('season' => 'active_delete_blocked'));
        }

        if ($st = $link->prepare("DELETE FROM fact_sim_seasons WHERE id=? LIMIT 1")) {
            $st->bind_param('i', $id);
            if (!$st->execute()) {
                $st->close();
                hg_admin_json_error('No se pudo eliminar la temporada.', 500, array('db' => 'delete_failed'));
            }
            $st->close();
            hg_admin_json_success(array('id' => $id), 'Temporada eliminada.');
        }
        hg_admin_json_error('Error al preparar DELETE.', 500, array('db' => 'prepare_failed'));
    }

    if ($action === 'set_active') {
        $id = isset($payload['id']) ? (int)$payload['id'] : 0;
        if ($id <= 0) {
            hg_admin_json_error('ID inválido.', 422, array('id' => 'invalid'));
        }
        $exists = 0;
        if ($st = $link->prepare("SELECT COUNT(*) FROM fact_sim_seasons WHERE id=?")) {
            $st->bind_param('i', $id);
            $st->execute();
            $st->bind_result($exists);
            $st->fetch();
            $st->close();
        }
        if ($exists <= 0) {
            hg_admin_json_error('Temporada no encontrada.', 404, array('id' => 'not_found'));
        }

        $link->query("UPDATE fact_sim_seasons SET is_active = 0");
        $link->query("UPDATE fact_sim_seasons SET is_active = 1 WHERE id = {$id} LIMIT 1");
        hg_admin_json_success(array('id' => $id), 'Temporada activa actualizada.');
    }

    if ($action === 'save_assignments') {
        $seasonId = isset($payload['season_id']) ? (int)$payload['season_id'] : 0;
        $rawIds = $payload['character_ids'] ?? array();
        if ($seasonId <= 0) {
            hg_admin_json_error('Debes seleccionar una temporada.', 422, array('season_id' => 'required'));
        }
        if (!is_array($rawIds)) {
            $rawIds = array();
        }

        $seasonLimit = 35;
        $seasonExists = 0;
        if ($st = $link->prepare("SELECT character_limit FROM fact_sim_seasons WHERE id=? LIMIT 1")) {
            $st->bind_param('i', $seasonId);
            $st->execute();
            $st->bind_result($seasonLimit);
            if ($st->fetch()) {
                $seasonExists = 1;
            }
            $st->close();
        }
        if ($seasonExists <= 0) {
            hg_admin_json_error('Temporada no valida.', 422, array('season_id' => 'invalid'));
        }
        $seasonLimit = (int)$seasonLimit;
        if ($seasonLimit < 1) $seasonLimit = 1;
        if ($seasonLimit > 200) $seasonLimit = 200;

        $ids = array();
        foreach ($rawIds as $v) {
            $n = (int)$v;
            if ($n > 0) $ids[$n] = true;
        }
        $ids = array_keys($ids);

        if (count($ids) > $seasonLimit) {
            hg_admin_json_error('Superas el limite de personajes de la temporada.', 422, array('character_limit' => 'exceeded', 'limit' => $seasonLimit));
        }

        if (!empty($ids)) {
            $kindClause = hg_asb_characters_kind_clause($link);
            $idSql = implode(',', array_map('intval', $ids));
            $rsValid = $link->query("SELECT id FROM fact_characters WHERE id IN ({$idSql}){$kindClause}");
            $validCount = ($rsValid) ? $rsValid->num_rows : 0;
            if ($rsValid) $rsValid->close();
            if ($validCount !== count($ids)) {
                hg_admin_json_error('Hay personajes no válidos para el simulador.', 422, array('character_ids' => 'invalid_kind'));
            }
        }

        $link->begin_transaction();
        try {
            if (!$link->query("DELETE FROM bridge_battle_sim_characters_seasons WHERE season_id = {$seasonId}")) {
                throw new Exception('delete_failed');
            }
            if (!empty($ids)) {
                $values = array();
                foreach ($ids as $cid) {
                    $values[] = "({$seasonId}, " . (int)$cid . ")";
                }
                $sqlIns = "INSERT INTO bridge_battle_sim_characters_seasons (season_id, character_id) VALUES " . implode(', ', $values);
                if (!$link->query($sqlIns)) {
                    throw new Exception('insert_failed');
                }
            }
            $link->commit();
        } catch (Throwable $e) {
            $link->rollback();
            hg_admin_json_error('No se pudieron guardar las asignaciones.', 500, array('db' => 'assign_failed'));
        }

        hg_admin_json_success(array('season_id' => $seasonId, 'assigned_count' => count($ids)), 'Asignaciones guardadas.');
    }

    if ($action === 'flush_combat_tables') {
        $tables = array('fact_sim_battles', 'fact_sim_character_scores', 'fact_sim_item_usage');
        $flushed = array();
        $skipped = array();
        foreach ($tables as $tbl) {
            if (!hg_asb_table_exists($link, $tbl)) {
                $skipped[] = $tbl;
                continue;
            }
            $sql = "TRUNCATE TABLE `{$tbl}`";
            if (!$link->query($sql)) {
                // Fallback if truncate fails due to FK constraints.
                if (!$link->query("DELETE FROM `{$tbl}`")) {
                    hg_admin_json_error('No se pudo vaciar la tabla ' . $tbl . '.', 500, array('db' => 'flush_failed', 'table' => $tbl));
                }
            }
            $flushed[] = $tbl;
        }
        hg_admin_json_success(
            array('flushed' => $flushed, 'skipped' => $skipped),
            'Tablas de combate vaciadas.',
            array('flushed_count' => count($flushed), 'skipped_count' => count($skipped))
        );
    }

    hg_admin_json_error('Acción no soportada.', 400, array('action' => 'unsupported'));
}

if (!$isAjaxRequest) {
    $actions = '<span class="adm-flex-right-8">'
        . '<button class="btn btn-green" type="button" id="asbQuickNewBtn">+ Nueva temporada</button>'
        . '</span>';
    admin_panel_open('Browser Simulador', $actions);
}
?>

<?php if (!$hasTables): ?>
  <p class="adm-admin-error">Faltan tablas de temporadas del simulador. Ejecuta <code>app/tools/simulator_seasons_setup_20260309.php</code> y recarga este panel.</p>
<?php else: ?>
  <div class="adm-grid-1-2" style="margin-bottom:10px;">
    <fieldset class="bioSeccion">
      <legend>&nbsp;Temporadas&nbsp;</legend>
      <input type="hidden" id="asbSeasonId" value="0">
      <div class="adm-grid-1-2">
        <div>
          <label>Nombre</label>
          <input class="inp" type="text" id="asbSeasonName" maxlength="120" placeholder="Ej: Temporada 1">
        </div>
        <div>
          <label>Límite de personajes</label>
          <input class="inp" type="number" id="asbSeasonLimit" min="1" max="200" value="35">
        </div>
      </div>
      <label>Descripción</label>
      <textarea class="ta" id="asbSeasonDesc" rows="2" maxlength="500" placeholder="Descripción corta"></textarea>
      <div style="margin:8px 0;">
        <label><input type="checkbox" id="asbSeasonActive"> Temporada activa</label>
      </div>
      <div style="margin-top:8px;">
        <button type="button" class="btn btn-green" id="asbSaveSeasonBtn">Guardar temporada</button>
        <button type="button" class="btn" id="asbResetSeasonBtn">Nueva</button>
        <button type="button" class="btn btn-red" id="asbDeleteSeasonBtn">Borrar</button>
        <button type="button" class="btn btn-red" id="asbFlushCombatBtn">Flush Combate</button>
        <span id="asbSeasonMsg" class="adm-color-muted" style="margin-left:8px;"></span>
      </div>
      <table class="table" style="margin-top:10px;">
        <thead>
          <tr>
            <th class="adm-w-60">ID</th>
            <th>Nombre</th>
            <th class="adm-w-120">Asignados</th>
            <th class="adm-w-120">Limite</th>
            <th class="adm-w-80">Activa</th>
            <th class="adm-w-160">Acciones</th>
          </tr>
        </thead>
        <tbody id="asbSeasonRows">
          <tr><td colspan="6" class="adm-color-muted">Cargando...</td></tr>
        </tbody>
      </table>
    </fieldset>

    <fieldset class="bioSeccion">
      <legend>&nbsp;Personajes por temporada&nbsp;</legend>
      <div style="margin-bottom:8px;">
        <label>Temporada seleccionada: <strong id="asbCurrentSeasonLabel">-</strong></label>
      </div>
      <div class="adm-grid-1-2" style="margin-bottom:8px;">
        <div>
          <label>Buscar personaje</label>
          <input class="inp" type="text" id="asbCharSearch" placeholder="Nombre o alias...">
        </div>
        <div>
          <label>&nbsp;</label>
          <div id="asbAssignCounter" class="adm-color-muted">0 / 0</div>
        </div>
      </div>
      <div style="max-height:420px; overflow:auto; border:1px solid #2a3555; border-radius:8px;">
        <table class="table">
          <thead>
            <tr>
              <th class="adm-w-60">Sel</th>
              <th class="adm-w-60">ID</th>
              <th>Nombre</th>
              <th>Alias</th>
            </tr>
          </thead>
          <tbody id="asbCharacterRows">
            <tr><td colspan="4" class="adm-color-muted">Selecciona una temporada.</td></tr>
          </tbody>
        </table>
      </div>
      <div style="margin-top:8px;">
        <button type="button" class="btn" id="asbToggleAllBtn">Seleccionar todos / Deseleccionar todos</button>
        <button type="button" class="btn btn-green" id="asbSaveAssignBtn">Guardar asignaciones</button>
        <span id="asbAssignMsg" class="adm-color-muted" style="margin-left:8px;"></span>
      </div>
    </fieldset>
  </div>
<?php endif; ?>

<?php if ($hasTables): ?>
<?php
$adminHttpJs = '/assets/js/admin/admin-http.js';
$adminHttpJsVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . $adminHttpJs) ?: time();
?>
<script src="<?php echo hg_asb_h($adminHttpJs); ?>?v=<?php echo (int)$adminHttpJsVer; ?>"></script>
<script>
window.ADMIN_CSRF_TOKEN = <?php echo json_encode($CSRF, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
(function(){
  var endpoint = '/talim?s=admin_sim_browser&ajax=1';
  var $ = function(id){ return document.getElementById(id); };
  var state = {
    seasons: [],
    selectedSeasonId: 0,
    characters: [],
    selectedCharacterIds: {}
  };

  function esc(v){
    return String(v == null ? '' : v)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function seasonMsg(msg, ok) {
    var node = $('asbSeasonMsg');
    if (!node) return;
    node.className = ok ? 'adm-color-muted' : 'adm-color-error';
    node.style.color = ok ? '#9be7b1' : '';
    node.textContent = msg || '';
  }

  function assignMsg(msg, ok) {
    var node = $('asbAssignMsg');
    if (!node) return;
    node.className = ok ? 'adm-color-muted' : 'adm-color-error';
    node.style.color = ok ? '#9be7b1' : '';
    node.textContent = msg || '';
  }

  function getSelectedSeason() {
    var sid = Number(state.selectedSeasonId || 0);
    return state.seasons.find(function(s){ return Number(s.id || 0) === sid; }) || null;
  }

  function resetSeasonForm() {
    $('asbSeasonId').value = '0';
    $('asbSeasonName').value = '';
    $('asbSeasonDesc').value = '';
    $('asbSeasonLimit').value = '35';
    $('asbSeasonActive').checked = false;
    seasonMsg('', true);
  }

  function fillSeasonForm(row) {
    $('asbSeasonId').value = String(row.id || 0);
    $('asbSeasonName').value = String(row.name || '');
    $('asbSeasonDesc').value = String(row.description || '');
    $('asbSeasonLimit').value = String(row.character_limit || 35);
    $('asbSeasonActive').checked = Number(row.is_active || 0) === 1;
  }

  function renderSeasons() {
    var tbody = $('asbSeasonRows');
    if (!tbody) return;
    if (!state.seasons.length) {
      tbody.innerHTML = '<tr><td colspan="6" class="adm-color-muted">(Sin temporadas)</td></tr>';
      return;
    }

    var html = '';
    for (var i = 0; i < state.seasons.length; i++) {
      var s = state.seasons[i];
      var sid = Number(s.id || 0);
      var sel = (sid === Number(state.selectedSeasonId || 0)) ? ' style="outline:2px solid #2f95ff;"' : '';
      html += '<tr data-id="' + sid + '"' + sel + '>';
      html += '<td>' + sid + '</td>';
      html += '<td>' + esc(s.name || '') + '</td>';
      html += '<td>' + Number(s.assigned_count || 0) + '</td>';
      html += '<td>' + Number(s.character_limit || 35) + '</td>';
      html += '<td>' + (Number(s.is_active || 0) === 1 ? 'Si' : 'No') + '</td>';
      html += '<td>';
      html += '<button type="button" class="btn" data-edit="' + sid + '">Editar</button> ';
      html += '<button type="button" class="btn" data-active="' + sid + '">Activar</button>';
      html += '</td>';
      html += '</tr>';
    }
    tbody.innerHTML = html;
  }

  function renderCharacterRows() {
    var tbody = $('asbCharacterRows');
    if (!tbody) return;
    var season = getSelectedSeason();
    $('asbCurrentSeasonLabel').textContent = season ? String(season.name || '-') : '-';

    if (!season) {
      tbody.innerHTML = '<tr><td colspan="4" class="adm-color-muted">Selecciona una temporada.</td></tr>';
      $('asbAssignCounter').textContent = '0 / 0';
      return;
    }
    if (!state.characters.length) {
      tbody.innerHTML = '<tr><td colspan="4" class="adm-color-muted">(Sin personajes para este filtro)</td></tr>';
      var countEmpty = Object.keys(state.selectedCharacterIds).length;
      $('asbAssignCounter').textContent = countEmpty + ' / ' + Number(season.character_limit || 35);
      return;
    }

    var html = '';
    for (var i = 0; i < state.characters.length; i++) {
      var c = state.characters[i];
      var cid = Number(c.id || 0);
      var checked = !!state.selectedCharacterIds[cid] ? ' checked' : '';
      html += '<tr>';
      html += '<td><input type="checkbox" data-char="' + cid + '"' + checked + '></td>';
      html += '<td>' + cid + '</td>';
      html += '<td>' + esc(c.name || '') + '</td>';
      html += '<td>' + esc(c.alias || '') + '</td>';
      html += '</tr>';
    }
    tbody.innerHTML = html;

    var currentCount = Object.keys(state.selectedCharacterIds).length;
    $('asbAssignCounter').textContent = currentCount + ' / ' + Number(season.character_limit || 35);
  }

  async function refreshSeasons() {
    try {
      var res = await HGAdminHttp.postAction(endpoint, 'list_seasons', {}, { loadingEl: $('asbSeasonRows') });
      state.seasons = (res && res.data && Array.isArray(res.data.rows)) ? res.data.rows : [];
      if (!state.selectedSeasonId && state.seasons.length) {
        state.selectedSeasonId = Number(state.seasons[0].id || 0);
      }
      renderSeasons();
      renderCharacterRows();
    } catch (err) {
      seasonMsg(HGAdminHttp.errorMessage(err), false);
    }
  }

  async function refreshCharacters() {
    var season = getSelectedSeason();
    if (!season) {
      state.characters = [];
      state.selectedCharacterIds = {};
      renderCharacterRows();
      return;
    }
    try {
      var payload = {
        season_id: Number(season.id || 0),
        q: $('asbCharSearch').value || '',
        limit: 1500
      };
      var res = await HGAdminHttp.postAction(endpoint, 'list_characters', payload, { loadingEl: $('asbCharacterRows') });
      state.characters = (res && res.data && Array.isArray(res.data.rows)) ? res.data.rows : [];
      state.selectedCharacterIds = {};
      for (var i = 0; i < state.characters.length; i++) {
        var c = state.characters[i];
        var cid = Number(c.id || 0);
        if (cid > 0 && Number(c.is_assigned || 0) === 1) {
          state.selectedCharacterIds[cid] = true;
        }
      }
      renderCharacterRows();
    } catch (err) {
      assignMsg(HGAdminHttp.errorMessage(err), false);
    }
  }

  async function saveSeason() {
    var payload = {
      id: Number($('asbSeasonId').value || 0),
      name: $('asbSeasonName').value || '',
      description: $('asbSeasonDesc').value || '',
      character_limit: Number($('asbSeasonLimit').value || 35),
      is_active: $('asbSeasonActive').checked ? 1 : 0
    };
    try {
      var res = await HGAdminHttp.postAction(endpoint, 'save_season', payload, { loadingEl: $('asbSaveSeasonBtn') });
      seasonMsg((res && res.message) ? res.message : 'Temporada guardada.', true);
      await refreshSeasons();
      if (!payload.id && res && res.data && res.data.id) {
        state.selectedSeasonId = Number(res.data.id || 0);
      } else if (payload.id > 0) {
        state.selectedSeasonId = payload.id;
      }
      await refreshCharacters();
      if (!payload.id) resetSeasonForm();
    } catch (err) {
      seasonMsg(HGAdminHttp.errorMessage(err), false);
    }
  }

  async function deleteSeason() {
    var id = Number($('asbSeasonId').value || 0);
    if (!id) return;
    if (!confirm('Se eliminara la temporada #' + id + '. Continuar?')) return;
    try {
      var res = await HGAdminHttp.postAction(endpoint, 'delete_season', { id: id }, { loadingEl: $('asbDeleteSeasonBtn') });
      seasonMsg((res && res.message) ? res.message : 'Temporada eliminada.', true);
      if (Number(state.selectedSeasonId || 0) === id) {
        state.selectedSeasonId = 0;
      }
      resetSeasonForm();
      await refreshSeasons();
      await refreshCharacters();
    } catch (err) {
      seasonMsg(HGAdminHttp.errorMessage(err), false);
    }
  }

  async function activateSeason(id) {
    id = Number(id || 0);
    if (!id) return;
    try {
      var res = await HGAdminHttp.postAction(endpoint, 'set_active', { id: id }, { loadingEl: $('asbSeasonRows') });
      seasonMsg((res && res.message) ? res.message : 'Temporada activa actualizada.', true);
      await refreshSeasons();
    } catch (err) {
      seasonMsg(HGAdminHttp.errorMessage(err), false);
    }
  }

  async function saveAssignments() {
    var season = getSelectedSeason();
    if (!season) {
      assignMsg('Selecciona una temporada.', false);
      return;
    }
    var ids = Object.keys(state.selectedCharacterIds).map(function(k){ return Number(k); }).filter(function(n){ return n > 0; });
    var limit = Number(season.character_limit || 35);
    if (ids.length > limit) {
      assignMsg('Superas el limite de la temporada (' + limit + ').', false);
      return;
    }
    try {
      var res = await HGAdminHttp.postAction(
        endpoint,
        'save_assignments',
        { season_id: Number(season.id || 0), character_ids: ids },
        { loadingEl: $('asbSaveAssignBtn') }
      );
      assignMsg((res && res.message) ? res.message : 'Asignaciones guardadas.', true);
      await refreshSeasons();
      await refreshCharacters();
    } catch (err) {
      assignMsg(HGAdminHttp.errorMessage(err), false);
    }
  }

  function toggleAllAssignments() {
    var season = getSelectedSeason();
    if (!season) {
      assignMsg('Selecciona una temporada.', false);
      return;
    }

    var limit = Number(season.character_limit || 35);
    var currentCount = Object.keys(state.selectedCharacterIds).length;
    var visibleIds = [];
    for (var i = 0; i < state.characters.length; i++) {
      var cid = Number(state.characters[i].id || 0);
      if (cid > 0) visibleIds.push(cid);
    }
    if (!visibleIds.length) return;

    // If all visible are selected, deselect visible. Otherwise select as many as limit allows.
    var allVisibleSelected = visibleIds.every(function(cid){ return !!state.selectedCharacterIds[cid]; });
    if (allVisibleSelected) {
      visibleIds.forEach(function(cid){ delete state.selectedCharacterIds[cid]; });
      assignMsg('Personajes visibles deseleccionados.', true);
      renderCharacterRows();
      return;
    }

    for (var x = 0; x < visibleIds.length; x++) {
      var id = visibleIds[x];
      if (state.selectedCharacterIds[id]) continue;
      if (currentCount >= limit) break;
      state.selectedCharacterIds[id] = true;
      currentCount++;
    }
    if (currentCount >= limit) {
      assignMsg('Seleccionados hasta el limite de la temporada (' + limit + ').', true);
    } else {
      assignMsg('Personajes visibles seleccionados.', true);
    }
    renderCharacterRows();
  }

  async function flushCombatTables() {
    if (!confirm('Se vaciaran fact_sim_battles, fact_sim_character_scores y fact_sim_item_usage. Continuar?')) return;
    try {
      var res = await HGAdminHttp.postAction(endpoint, 'flush_combat_tables', {}, { loadingEl: $('asbFlushCombatBtn') });
      seasonMsg((res && res.message) ? res.message : 'Tablas vaciadas.', true);
    } catch (err) {
      seasonMsg(HGAdminHttp.errorMessage(err), false);
    }
  }

  function bindEvents() {
    $('asbSaveSeasonBtn').addEventListener('click', saveSeason);
    $('asbResetSeasonBtn').addEventListener('click', resetSeasonForm);
    $('asbDeleteSeasonBtn').addEventListener('click', deleteSeason);
    $('asbFlushCombatBtn').addEventListener('click', flushCombatTables);
    $('asbToggleAllBtn').addEventListener('click', toggleAllAssignments);
    $('asbSaveAssignBtn').addEventListener('click', saveAssignments);
    $('asbQuickNewBtn').addEventListener('click', function(){ resetSeasonForm(); $('asbSeasonName').focus(); });
    $('asbCharSearch').addEventListener('input', function(){ refreshCharacters(); });

    $('asbSeasonRows').addEventListener('click', function(ev){
      var editBtn = ev.target.closest('[data-edit]');
      if (editBtn) {
        var id = Number(editBtn.getAttribute('data-edit') || 0);
        var row = state.seasons.find(function(s){ return Number(s.id || 0) === id; });
        if (row) {
          state.selectedSeasonId = id;
          fillSeasonForm(row);
          renderSeasons();
          refreshCharacters();
        }
        return;
      }

      var activeBtn = ev.target.closest('[data-active]');
      if (activeBtn) {
        activateSeason(Number(activeBtn.getAttribute('data-active') || 0));
        return;
      }

      var tr = ev.target.closest('tr[data-id]');
      if (tr) {
        var sid = Number(tr.getAttribute('data-id') || 0);
        var srow = state.seasons.find(function(s){ return Number(s.id || 0) === sid; });
        if (srow) {
          state.selectedSeasonId = sid;
          fillSeasonForm(srow);
          renderSeasons();
          refreshCharacters();
        }
      }
    });

    $('asbCharacterRows').addEventListener('change', function(ev){
      var cb = ev.target.closest('[data-char]');
      if (!cb) return;
      var cid = Number(cb.getAttribute('data-char') || 0);
      if (!cid) return;
      if (cb.checked) state.selectedCharacterIds[cid] = true;
      else delete state.selectedCharacterIds[cid];
      renderCharacterRows();
    });

    $('asbCharacterRows').addEventListener('click', function(ev){
      var cbClick = ev.target.closest('[data-char]');
      if (cbClick) return; // already handled by change

      var tr = ev.target.closest('tr');
      if (!tr) return;
      var cb = tr.querySelector('[data-char]');
      if (!cb) return;

      var cid = Number(cb.getAttribute('data-char') || 0);
      if (!cid) return;

      var nextChecked = !cb.checked;
      cb.checked = nextChecked;
      if (nextChecked) state.selectedCharacterIds[cid] = true;
      else delete state.selectedCharacterIds[cid];
      renderCharacterRows();
    });
  }

  bindEvents();
  resetSeasonForm();
  refreshSeasons().then(refreshCharacters);
})();
</script>
<?php endif; ?>
<?php if (!$isAjaxRequest) { admin_panel_close(); } ?>

