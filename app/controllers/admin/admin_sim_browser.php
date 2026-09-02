<?php
// admin_sim_browser.php - Gestion del browser del simulador por temporadas.

include_once(__DIR__ . '/../../helpers/admin_ajax.php');
include_once(__DIR__ . '/../../helpers/character_avatar.php');
if (!hg_admin_require_db($link)) { return; }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
include(__DIR__ . '/../../partials/admin/admin_styles.php');

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

if (!function_exists('hg_asb_character_query_meta')) {
    function hg_asb_character_query_meta(mysqli $db): array
    {
        $meta = array(
            'selects' => array(),
            'joins' => array(),
            'order_label' => "COALESCE(NULLIF(c.alias, ''), c.name)",
        );

        $meta['selects'][] = "COALESCE(c.alias, '') AS alias";
        $meta['selects'][] = "COALESCE(c.image_url, '') AS image_url";
        $meta['selects'][] = "COALESCE(c.gender, '') AS gender";

        if (hg_asb_column_exists($db, 'fact_characters', 'status_id') && hg_asb_table_exists($db, 'dim_character_status')) {
            $meta['joins'][] = "LEFT JOIN dim_character_status dcs ON dcs.id = c.status_id";
            $meta['selects'][] = "COALESCE(dcs.label, '') AS status_name";
        } else {
            $meta['selects'][] = "'' AS status_name";
        }

        if (hg_asb_column_exists($db, 'fact_characters', 'system_id') && hg_asb_table_exists($db, 'dim_systems')) {
            $meta['joins'][] = "LEFT JOIN dim_systems ds ON ds.id = c.system_id";
            $meta['selects'][] = "COALESCE(ds.name, '') AS system_name";
        } else {
            $meta['selects'][] = "'' AS system_name";
        }

        $hasCharOrgBridge = hg_asb_table_exists($db, 'bridge_characters_organizations');
        $hasCharGroupBridge = hg_asb_table_exists($db, 'bridge_characters_groups');
        $hasOrgGroupBridge = hg_asb_table_exists($db, 'bridge_organizations_groups');
        $hasOrganizations = hg_asb_table_exists($db, 'dim_organizations');

        $clanExpr = "''";
        if ($hasCharOrgBridge && $hasOrganizations) {
            $meta['joins'][] = "LEFT JOIN (
                SELECT character_id, MIN(organization_id) AS organization_id
                FROM bridge_characters_organizations
                WHERE (is_active = 1 OR is_active IS NULL)
                GROUP BY character_id
            ) bco ON bco.character_id = c.id";
            $meta['joins'][] = "LEFT JOIN dim_organizations dco ON dco.id = bco.organization_id";
            $clanExpr = "COALESCE(dco.name, '')";
        }

        if ($hasCharGroupBridge && $hasOrgGroupBridge && $hasOrganizations) {
            $meta['joins'][] = "LEFT JOIN (
                SELECT cg.character_id, MIN(og.organization_id) AS organization_id
                FROM bridge_characters_groups cg
                INNER JOIN bridge_organizations_groups og
                    ON og.group_id = cg.group_id
                   AND (og.is_active = 1 OR og.is_active IS NULL)
                WHERE (cg.is_active = 1 OR cg.is_active IS NULL)
                GROUP BY cg.character_id
            ) pco_bridge ON pco_bridge.character_id = c.id";
            $meta['joins'][] = "LEFT JOIN dim_organizations pco ON pco.id = pco_bridge.organization_id";
            $clanExpr = ($clanExpr === "''")
                ? "COALESCE(pco.name, '')"
                : "COALESCE(dco.name, pco.name, '')";
        }

        $meta['selects'][] = "{$clanExpr} AS clan_name";

        $raceParts = array();
        if (hg_asb_column_exists($db, 'fact_characters', 'breed_id') && hg_asb_table_exists($db, 'dim_breeds')) {
            $meta['joins'][] = "LEFT JOIN dim_breeds nr ON nr.id = c.breed_id";
            $raceParts[] = "NULLIF(COALESCE(nr.name, ''), '')";
        }
        if (hg_asb_column_exists($db, 'fact_characters', 'auspice_id') && hg_asb_table_exists($db, 'dim_auspices')) {
            $meta['joins'][] = "LEFT JOIN dim_auspices na ON na.id = c.auspice_id";
            $raceParts[] = "NULLIF(COALESCE(na.name, ''), '')";
        }
        if (hg_asb_column_exists($db, 'fact_characters', 'tribe_id') && hg_asb_table_exists($db, 'dim_tribes')) {
            $meta['joins'][] = "LEFT JOIN dim_tribes nt ON nt.id = c.tribe_id";
            $raceParts[] = "NULLIF(COALESCE(nt.name, ''), '')";
        }
        if (!empty($raceParts)) {
            $meta['selects'][] = "TRIM(CONCAT_WS(' · ', " . implode(', ', $raceParts) . ")) AS race_label";
        } else {
            $meta['selects'][] = "'' AS race_label";
        }

        return $meta;
    }
}

$ADMIN_CSRF_SESSION_KEY = 'csrf_admin_sim_browser';
$CSRF = function_exists('hg_admin_ensure_csrf_token')
    ? hg_admin_ensure_csrf_token($ADMIN_CSRF_SESSION_KEY)
    : '';

$meta = hg_asb_bootstrap_tables_meta($link);
$hasTables = (!empty($meta['seasons_table']) && !empty($meta['bridge_table']));

if ($isAjaxRequest) {
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
        $limit = isset($payload['limit']) ? (int)$payload['limit'] : 1800;
        if ($limit < 1) $limit = 1;
        if ($limit > 2500) $limit = 2500;

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
            : "LEFT JOIN bridge_battle_sim_characters_seasons b ON 1 = 0";

        $qMeta = hg_asb_character_query_meta($link);
        $selects = array_merge(
            array(
                'c.id',
                'c.name',
                $assignedExpr,
            ),
            $qMeta['selects']
        );
        $joins = array_merge(array($joinBridge), $qMeta['joins']);

        $sql = "
            SELECT
                " . implode(",\n                ", $selects) . "
            FROM fact_characters c
            " . implode("\n            ", $joins) . "
            WHERE " . implode(' AND ', $where) . "
            ORDER BY {$qMeta['order_label']} ASC, c.id ASC
            LIMIT {$limit}
        ";

        $rows = array();
        if ($rs = $link->query($sql)) {
            while ($r = $rs->fetch_assoc()) {
                $rows[] = array(
                    'id' => (int)($r['id'] ?? 0),
                    'name' => (string)($r['name'] ?? ''),
                    'alias' => (string)($r['alias'] ?? ''),
                    'avatar_url' => (string)hg_character_avatar_url((string)($r['image_url'] ?? ''), (string)($r['gender'] ?? '')),
                    'clan_name' => (string)($r['clan_name'] ?? ''),
                    'race_label' => (string)($r['race_label'] ?? ''),
                    'system_name' => (string)($r['system_name'] ?? ''),
                    'status_name' => (string)($r['status_name'] ?? ''),
                    'is_assigned' => (int)($r['is_assigned'] ?? 0),
                );
            }
            $rs->close();
        }
        hg_admin_json_success(array('rows' => $rows), 'Listado de personajes', array('count' => count($rows)));
    }

    if (in_array($action, array('save_season', 'delete_season', 'set_active', 'save_assignments'), true)) {
        $csrfToken = function_exists('hg_admin_extract_csrf_token')
            ? hg_admin_extract_csrf_token($payload)
            : (string)($payload['csrf'] ?? '');
        $csrfOk = function_exists('hg_admin_csrf_valid')
            ? hg_admin_csrf_valid($csrfToken, $ADMIN_CSRF_SESSION_KEY)
            : (is_string($csrfToken) && $csrfToken !== '' && isset($_SESSION[$ADMIN_CSRF_SESSION_KEY]) && hash_equals($_SESSION[$ADMIN_CSRF_SESSION_KEY], $csrfToken));
        if (!$csrfOk) {
            hg_admin_json_error('CSRF invalido. Recarga la pagina.', 403, array('csrf' => 'invalid'));
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
            hg_admin_json_error('ID invalido.', 422, array('id' => 'invalid'));
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
            hg_admin_json_error('ID invalido.', 422, array('id' => 'invalid'));
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
                hg_admin_json_error('Hay personajes no validos para el simulador.', 422, array('character_ids' => 'invalid_kind'));
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

    hg_admin_json_error('Accion no soportada.', 400, array('action' => 'unsupported'));
}

if (!$isAjaxRequest) {
    $actions = '<span class="adm-flex-right-8">'
        . '<button class="btn btn-green" type="button" id="asbQuickNewBtn">+ Nueva temporada</button>'
        . '</span>';
    admin_panel_open('Browser Simulador', $actions);
}
?>

<?php if (!$hasTables): ?>
  <p class="adm-admin-error">Faltan tablas de temporadas del simulador en esta base de datos.</p>
<?php else: ?>
  <style>
    .asb-shell { display:grid; gap:12px; }
    .asb-season-table tr.is-selected { outline:2px solid #2f95ff; }
    .asb-workspace {
      display:grid;
      grid-template-columns:minmax(320px, 1fr) minmax(320px, 1fr);
      gap:12px;
      align-items:start;
    }
    .asb-panel {
      border:1px solid #2a3555;
      border-radius:12px;
      background:rgba(8, 14, 30, 0.45);
      overflow:hidden;
    }
    .asb-panel-head {
      display:flex;
      justify-content:space-between;
      gap:10px;
      align-items:center;
      padding:12px 14px;
      border-bottom:1px solid #23304f;
      background:rgba(21, 31, 57, 0.9);
    }
    .asb-panel-title { font-weight:700; color:#dbe7ff; }
    .asb-panel-sub { font-size:12px; color:#9db0d7; }
    .asb-panel-body { padding:12px; }
    .asb-filter-grid {
      display:grid;
      grid-template-columns:repeat(2, minmax(0, 1fr));
      gap:10px;
      margin-bottom:12px;
    }
    .asb-dropzone {
      min-height:420px;
      max-height:620px;
      overflow:auto;
      border:1px dashed #3d4f7a;
      border-radius:12px;
      padding:10px;
      background:rgba(4, 9, 20, 0.55);
    }
    .asb-dropzone.is-over {
      border-color:#7cc5ff;
      box-shadow:inset 0 0 0 1px rgba(124, 197, 255, 0.6);
      background:rgba(15, 32, 58, 0.7);
    }
    .asb-card-list { display:grid; gap:8px; }
    .asb-card {
      display:grid;
      grid-template-columns:56px minmax(0, 1fr) auto;
      gap:10px;
      align-items:center;
      padding:10px;
      border:1px solid #2c3d63;
      border-radius:10px;
      background:linear-gradient(180deg, rgba(13, 23, 43, 0.95), rgba(7, 13, 27, 0.95));
      cursor:grab;
    }
    .asb-card:active { cursor:grabbing; }
    .asb-card-avatar {
      width:56px;
      height:56px;
      border-radius:10px;
      object-fit:cover;
      border:1px solid #31456f;
      background:#0b1324;
    }
    .asb-card-name { font-weight:700; color:#f0f5ff; }
    .asb-card-alias { color:#a6b6da; font-size:12px; margin-top:2px; }
    .asb-card-meta {
      display:flex;
      flex-wrap:wrap;
      gap:6px;
      margin-top:6px;
    }
    .asb-chip {
      display:inline-flex;
      align-items:center;
      padding:2px 8px;
      border-radius:999px;
      background:#182643;
      border:1px solid #2b416f;
      color:#cae0ff;
      font-size:11px;
      line-height:1.4;
    }
    .asb-card button { white-space:nowrap; }
    .asb-empty {
      padding:28px 14px;
      text-align:center;
      color:#8ea4cc;
      border:1px dashed #31456f;
      border-radius:10px;
    }
    .asb-toolbar {
      display:flex;
      gap:8px;
      flex-wrap:wrap;
      align-items:center;
      justify-content:space-between;
      margin-bottom:10px;
    }
    .asb-toolbar-actions {
      display:flex;
      gap:8px;
      flex-wrap:wrap;
      align-items:center;
    }
    .asb-season-summary {
      display:flex;
      gap:12px;
      flex-wrap:wrap;
      align-items:center;
      color:#a8bbdf;
      margin-bottom:10px;
    }
    .asb-season-summary strong { color:#fff; }
    @media (max-width: 960px) {
      .asb-workspace { grid-template-columns:1fr; }
      .asb-filter-grid { grid-template-columns:1fr; }
    }
  </style>

  <div class="asb-shell">
    <fieldset class="bioSeccion">
      <legend>&nbsp;Temporadas&nbsp;</legend>
      <input type="hidden" id="asbSeasonId" value="0">
      <div class="adm-grid-1-2">
        <div>
          <label>Nombre</label>
          <input class="inp" type="text" id="asbSeasonName" maxlength="120" placeholder="Ej: Temporada 1">
        </div>
        <div>
          <label>Limite de personajes</label>
          <input class="inp" type="number" id="asbSeasonLimit" min="1" max="200" value="35">
        </div>
      </div>
      <label>Descripcion</label>
      <textarea class="ta" id="asbSeasonDesc" rows="2" maxlength="500" placeholder="Descripcion corta"></textarea>
      <div style="margin:8px 0;">
        <label><input type="checkbox" id="asbSeasonActive"> Temporada activa</label>
      </div>
      <div style="margin-top:8px;">
        <button type="button" class="btn btn-green" id="asbSaveSeasonBtn">Guardar temporada</button>
        <button type="button" class="btn" id="asbResetSeasonBtn">Nueva</button>
        <button type="button" class="btn btn-red" id="asbDeleteSeasonBtn">Borrar</button>
        <button type="button" class="btn btn-red" id="asbFlushCombatBtn">Flush combate</button>
        <span id="asbSeasonMsg" class="adm-color-muted" style="margin-left:8px;"></span>
      </div>
      <table class="table asb-season-table" style="margin-top:10px;">
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
      <div class="asb-season-summary">
        <span>Temporada seleccionada: <strong id="asbCurrentSeasonLabel">-</strong></span>
        <span id="asbAssignCounter">0 / 0</span>
        <span id="asbAssignMsg" class="adm-color-muted"></span>
      </div>

      <div class="asb-workspace">
        <section class="asb-panel">
          <div class="asb-panel-head">
            <div>
              <div class="asb-panel-title">Bolsa de personajes</div>
              <div class="asb-panel-sub">Filtra y arrastra a la temporada.</div>
            </div>
            <div class="asb-panel-sub" id="asbBagCounter">0 visibles</div>
          </div>
          <div class="asb-panel-body">
            <div class="asb-filter-grid">
              <div>
                <label>Nombre o alias</label>
                <input class="inp" type="text" id="asbCharSearch" placeholder="Buscar personaje...">
              </div>
              <div>
                <label>Clan</label>
                <select class="select" id="asbFilterClan">
                  <option value="">Todos</option>
                </select>
              </div>
              <div>
                <label>Raza</label>
                <select class="select" id="asbFilterRace">
                  <option value="">Todas</option>
                </select>
              </div>
              <div>
                <label>Sistema</label>
                <select class="select" id="asbFilterSystem">
                  <option value="">Todos</option>
                </select>
              </div>
              <div>
                <label>Estado</label>
                <select class="select" id="asbFilterStatus">
                  <option value="">Todos</option>
                </select>
              </div>
              <div>
                <label>&nbsp;</label>
                <button type="button" class="btn" id="asbClearFiltersBtn">Limpiar filtros</button>
              </div>
            </div>

            <div class="asb-toolbar">
              <div class="asb-toolbar-actions">
                <button type="button" class="btn" id="asbAddVisibleBtn">Anadir visibles</button>
              </div>
              <div class="asb-panel-sub">Tambien puedes hacer clic sobre una tarjeta para anadirla.</div>
            </div>

            <div class="asb-dropzone" id="asbBagZone" data-zone="bag">
              <div id="asbBagList" class="asb-card-list"></div>
            </div>
          </div>
        </section>

        <section class="asb-panel">
          <div class="asb-panel-head">
            <div>
              <div class="asb-panel-title">Temporada actual</div>
              <div class="asb-panel-sub">Suelta aqui para asignar. Arrastra fuera o pulsa quitar para sacar.</div>
            </div>
            <div class="asb-panel-sub" id="asbSelectedCounter">0 asignados</div>
          </div>
          <div class="asb-panel-body">
            <div class="asb-toolbar">
              <div class="asb-toolbar-actions">
                <button type="button" class="btn" id="asbClearAssignedBtn">Vaciar temporada</button>
                <button type="button" class="btn btn-green" id="asbSaveAssignBtn">Guardar asignaciones</button>
              </div>
              <div class="asb-panel-sub">Haz clic sobre una tarjeta para quitarla.</div>
            </div>

            <div class="asb-dropzone" id="asbSeasonZone" data-zone="season">
              <div id="asbSelectedList" class="asb-card-list"></div>
            </div>
          </div>
        </section>
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
    selectedCharacterIds: {},
    filters: {
      q: '',
      clan: '',
      race: '',
      system: '',
      status: ''
    },
    dragCharacterId: 0
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

  function normalizeText(value) {
    return String(value || '').toLocaleLowerCase('es');
  }

  function getSelectedSeason() {
    var sid = Number(state.selectedSeasonId || 0);
    return state.seasons.find(function(s){ return Number(s.id || 0) === sid; }) || null;
  }

  function getCharacterLabel(c) {
    var alias = String(c.alias || '').trim();
    return alias !== '' ? alias : String(c.name || '');
  }

  function getCharacterSearchHaystack(c) {
    return normalizeText([
      c.name || '',
      c.alias || '',
      c.clan_name || '',
      c.race_label || '',
      c.system_name || '',
      c.status_name || ''
    ].join(' '));
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
      var cls = (sid === Number(state.selectedSeasonId || 0)) ? ' class="is-selected"' : '';
      html += '<tr data-id="' + sid + '"' + cls + '>';
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

  function getAssignedCount() {
    return Object.keys(state.selectedCharacterIds).length;
  }

  function getSortedCharacters(source) {
    return source.slice().sort(function(a, b){
      return getCharacterLabel(a).localeCompare(getCharacterLabel(b), 'es', { sensitivity: 'base' }) || (Number(a.id || 0) - Number(b.id || 0));
    });
  }

  function getFilteredBagCharacters() {
    var q = normalizeText(state.filters.q);
    var clan = state.filters.clan;
    var race = state.filters.race;
    var system = state.filters.system;
    var status = state.filters.status;

    return getSortedCharacters(state.characters.filter(function(c){
      var cid = Number(c.id || 0);
      if (state.selectedCharacterIds[cid]) return false;
      if (q && getCharacterSearchHaystack(c).indexOf(q) === -1) return false;
      if (clan && String(c.clan_name || '') !== clan) return false;
      if (race && String(c.race_label || '') !== race) return false;
      if (system && String(c.system_name || '') !== system) return false;
      if (status && String(c.status_name || '') !== status) return false;
      return true;
    }));
  }

  function getAssignedCharacters() {
    return getSortedCharacters(state.characters.filter(function(c){
      return !!state.selectedCharacterIds[Number(c.id || 0)];
    }));
  }

  function cardMetaChip(value) {
    var text = String(value || '').trim();
    return text ? '<span class="asb-chip">' + esc(text) + '</span>' : '';
  }

  function renderCharacterCard(c, zone) {
    var cid = Number(c.id || 0);
    var alias = String(c.alias || '').trim();
    var buttonLabel = zone === 'bag' ? 'Anadir' : 'Quitar';
    var buttonClass = zone === 'bag' ? 'btn btn-green' : 'btn btn-red';
    var chips = [
      cardMetaChip(c.clan_name),
      cardMetaChip(c.race_label),
      cardMetaChip(c.system_name),
      cardMetaChip(c.status_name)
    ].join('');

    return ''
      + '<article class="asb-card" draggable="true" data-char-card="' + cid + '" data-zone="' + esc(zone) + '">'
      + '  <img class="asb-card-avatar" src="' + esc(c.avatar_url || '') + '" alt="">'
      + '  <div>'
      + '    <div class="asb-card-name">' + esc(c.name || '') + '</div>'
      + (alias ? '    <div class="asb-card-alias">' + esc(alias) + '</div>' : '')
      + '    <div class="asb-card-meta">' + chips + '</div>'
      + '  </div>'
      + '  <button type="button" class="' + buttonClass + '" data-card-action="' + esc(zone) + '" data-id="' + cid + '">' + buttonLabel + '</button>'
      + '</article>';
  }

  function fillSelectOptions(selectId, values, emptyLabel) {
    var select = $(selectId);
    if (!select) return;
    var current = select.value || '';
    var html = '<option value="">' + esc(emptyLabel) + '</option>';
    values.forEach(function(value){
      html += '<option value="' + esc(value) + '">' + esc(value) + '</option>';
    });
    select.innerHTML = html;
    select.value = values.indexOf(current) !== -1 ? current : '';
  }

  function uniqueValues(key) {
    var map = {};
    state.characters.forEach(function(c){
      var value = String(c[key] || '').trim();
      if (value) map[value] = true;
    });
    return Object.keys(map).sort(function(a, b){
      return a.localeCompare(b, 'es', { sensitivity: 'base' });
    });
  }

  function renderFilters() {
    fillSelectOptions('asbFilterClan', uniqueValues('clan_name'), 'Todos');
    fillSelectOptions('asbFilterRace', uniqueValues('race_label'), 'Todas');
    fillSelectOptions('asbFilterSystem', uniqueValues('system_name'), 'Todos');
    fillSelectOptions('asbFilterStatus', uniqueValues('status_name'), 'Todos');
    state.filters.clan = $('asbFilterClan').value || '';
    state.filters.race = $('asbFilterRace').value || '';
    state.filters.system = $('asbFilterSystem').value || '';
    state.filters.status = $('asbFilterStatus').value || '';
  }

  function renderCharacterWorkspace() {
    var season = getSelectedSeason();
    var bagList = $('asbBagList');
    var selectedList = $('asbSelectedList');
    var bagRows = getFilteredBagCharacters();
    var assignedRows = getAssignedCharacters();
    var limit = season ? Number(season.character_limit || 35) : 0;

    $('asbCurrentSeasonLabel').textContent = season ? String(season.name || '-') : '-';
    $('asbAssignCounter').textContent = season ? (getAssignedCount() + ' / ' + limit) : '0 / 0';
    $('asbBagCounter').textContent = bagRows.length + ' visibles';
    $('asbSelectedCounter').textContent = assignedRows.length + ' asignados';

    if (!season) {
      bagList.innerHTML = '<div class="asb-empty">Selecciona una temporada para cargar personajes.</div>';
      selectedList.innerHTML = '<div class="asb-empty">No hay temporada seleccionada.</div>';
      return;
    }

    if (!bagRows.length) {
      bagList.innerHTML = '<div class="asb-empty">No quedan personajes en la bolsa con los filtros actuales.</div>';
    } else {
      bagList.innerHTML = bagRows.map(function(c){ return renderCharacterCard(c, 'bag'); }).join('');
    }

    if (!assignedRows.length) {
      selectedList.innerHTML = '<div class="asb-empty">Arrastra aqui los personajes de la temporada.</div>';
    } else {
      selectedList.innerHTML = assignedRows.map(function(c){ return renderCharacterCard(c, 'season'); }).join('');
    }
  }

  async function refreshSeasons() {
    try {
      var res = await HGAdminHttp.postAction(endpoint, 'list_seasons', {}, { loadingEl: $('asbSeasonRows') });
      state.seasons = (res && res.data && Array.isArray(res.data.rows)) ? res.data.rows : [];
      if (!state.selectedSeasonId && state.seasons.length) {
        state.selectedSeasonId = Number(state.seasons[0].id || 0);
      }
      var currentSeason = getSelectedSeason();
      if (currentSeason) {
        fillSeasonForm(currentSeason);
      }
      renderSeasons();
      renderCharacterWorkspace();
    } catch (err) {
      seasonMsg(HGAdminHttp.errorMessage(err), false);
    }
  }

  async function refreshCharacters() {
    var season = getSelectedSeason();
    if (!season) {
      state.characters = [];
      state.selectedCharacterIds = {};
      renderFilters();
      renderCharacterWorkspace();
      return;
    }
    try {
      var payload = {
        season_id: Number(season.id || 0),
        q: '',
        limit: 2000
      };
      var res = await HGAdminHttp.postAction(endpoint, 'list_characters', payload, { loadingEl: $('asbBagList') });
      state.characters = (res && res.data && Array.isArray(res.data.rows)) ? res.data.rows : [];
      state.selectedCharacterIds = {};
      for (var i = 0; i < state.characters.length; i++) {
        var c = state.characters[i];
        var cid = Number(c.id || 0);
        if (cid > 0 && Number(c.is_assigned || 0) === 1) {
          state.selectedCharacterIds[cid] = true;
        }
      }
      renderFilters();
      renderCharacterWorkspace();
    } catch (err) {
      assignMsg(HGAdminHttp.errorMessage(err), false);
    }
  }

  function selectSeason(id) {
    id = Number(id || 0);
    if (!id) return;
    var row = state.seasons.find(function(s){ return Number(s.id || 0) === id; });
    if (!row) return;
    state.selectedSeasonId = id;
    fillSeasonForm(row);
    renderSeasons();
    refreshCharacters();
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
      if (res && res.data && res.data.id) {
        state.selectedSeasonId = Number(res.data.id || 0);
        selectSeason(state.selectedSeasonId);
      }
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

  function addCharacterToSeason(characterId) {
    var season = getSelectedSeason();
    if (!season) {
      assignMsg('Selecciona una temporada.', false);
      return false;
    }
    characterId = Number(characterId || 0);
    if (!characterId || state.selectedCharacterIds[characterId]) return false;
    var limit = Number(season.character_limit || 35);
    var count = getAssignedCount();
    if (count >= limit) {
      assignMsg('Has alcanzado el limite de la temporada (' + limit + ').', false);
      return false;
    }
    state.selectedCharacterIds[characterId] = true;
    assignMsg('Personaje anadido a la temporada.', true);
    renderCharacterWorkspace();
    return true;
  }

  function removeCharacterFromSeason(characterId) {
    characterId = Number(characterId || 0);
    if (!characterId || !state.selectedCharacterIds[characterId]) return false;
    delete state.selectedCharacterIds[characterId];
    assignMsg('Personaje quitado de la temporada.', true);
    renderCharacterWorkspace();
    return true;
  }

  function addVisibleCharacters() {
    var season = getSelectedSeason();
    if (!season) {
      assignMsg('Selecciona una temporada.', false);
      return;
    }
    var limit = Number(season.character_limit || 35);
    var count = getAssignedCount();
    var added = 0;
    var visible = getFilteredBagCharacters();
    for (var i = 0; i < visible.length; i++) {
      var cid = Number(visible[i].id || 0);
      if (count >= limit) break;
      if (!state.selectedCharacterIds[cid]) {
        state.selectedCharacterIds[cid] = true;
        count++;
        added++;
      }
    }
    if (!added) {
      assignMsg('No se han anadido personajes nuevos.', false);
      return;
    }
    assignMsg('Anadidos ' + added + ' personajes a la temporada.', true);
    renderCharacterWorkspace();
  }

  function clearAssignedCharacters() {
    if (!getAssignedCount()) return;
    state.selectedCharacterIds = {};
    assignMsg('Temporada vaciada en memoria. Guarda para persistir.', true);
    renderCharacterWorkspace();
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

  async function flushCombatTables() {
    if (!confirm('Se vaciaran fact_sim_battles, fact_sim_character_scores y fact_sim_item_usage. Continuar?')) return;
    try {
      var res = await HGAdminHttp.postAction(endpoint, 'flush_combat_tables', {}, { loadingEl: $('asbFlushCombatBtn') });
      seasonMsg((res && res.message) ? res.message : 'Tablas vaciadas.', true);
    } catch (err) {
      seasonMsg(HGAdminHttp.errorMessage(err), false);
    }
  }

  function handleDragStart(ev) {
    var card = ev.target.closest('[data-char-card]');
    if (!card) return;
    state.dragCharacterId = Number(card.getAttribute('data-char-card') || 0);
    if (ev.dataTransfer) {
      ev.dataTransfer.setData('text/plain', String(state.dragCharacterId));
      ev.dataTransfer.effectAllowed = 'move';
    }
  }

  function setupDropzone(node, zone) {
    if (!node) return;
    node.addEventListener('dragover', function(ev){
      ev.preventDefault();
      node.classList.add('is-over');
    });
    node.addEventListener('dragleave', function(){
      node.classList.remove('is-over');
    });
    node.addEventListener('drop', function(ev){
      ev.preventDefault();
      node.classList.remove('is-over');
      var id = Number((ev.dataTransfer && ev.dataTransfer.getData('text/plain')) || state.dragCharacterId || 0);
      if (!id) return;
      if (zone === 'season') addCharacterToSeason(id);
      else removeCharacterFromSeason(id);
    });
  }

  function bindEvents() {
    $('asbSaveSeasonBtn').addEventListener('click', saveSeason);
    $('asbResetSeasonBtn').addEventListener('click', resetSeasonForm);
    $('asbDeleteSeasonBtn').addEventListener('click', deleteSeason);
    $('asbFlushCombatBtn').addEventListener('click', flushCombatTables);
    $('asbSaveAssignBtn').addEventListener('click', saveAssignments);
    $('asbQuickNewBtn').addEventListener('click', function(){ resetSeasonForm(); $('asbSeasonName').focus(); });
    $('asbAddVisibleBtn').addEventListener('click', addVisibleCharacters);
    $('asbClearAssignedBtn').addEventListener('click', clearAssignedCharacters);
    $('asbClearFiltersBtn').addEventListener('click', function(){
      state.filters = { q: '', clan: '', race: '', system: '', status: '' };
      $('asbCharSearch').value = '';
      $('asbFilterClan').value = '';
      $('asbFilterRace').value = '';
      $('asbFilterSystem').value = '';
      $('asbFilterStatus').value = '';
      renderCharacterWorkspace();
    });

    $('asbCharSearch').addEventListener('input', function(){
      state.filters.q = this.value || '';
      renderCharacterWorkspace();
    });
    $('asbFilterClan').addEventListener('change', function(){ state.filters.clan = this.value || ''; renderCharacterWorkspace(); });
    $('asbFilterRace').addEventListener('change', function(){ state.filters.race = this.value || ''; renderCharacterWorkspace(); });
    $('asbFilterSystem').addEventListener('change', function(){ state.filters.system = this.value || ''; renderCharacterWorkspace(); });
    $('asbFilterStatus').addEventListener('change', function(){ state.filters.status = this.value || ''; renderCharacterWorkspace(); });

    $('asbSeasonRows').addEventListener('click', function(ev){
      var editBtn = ev.target.closest('[data-edit]');
      if (editBtn) {
        selectSeason(Number(editBtn.getAttribute('data-edit') || 0));
        return;
      }

      var activeBtn = ev.target.closest('[data-active]');
      if (activeBtn) {
        activateSeason(Number(activeBtn.getAttribute('data-active') || 0));
        return;
      }

      var tr = ev.target.closest('tr[data-id]');
      if (tr) {
        selectSeason(Number(tr.getAttribute('data-id') || 0));
      }
    });

    document.addEventListener('click', function(ev){
      var actionBtn = ev.target.closest('[data-card-action]');
      if (actionBtn) {
        var id = Number(actionBtn.getAttribute('data-id') || 0);
        var action = actionBtn.getAttribute('data-card-action') || '';
        if (action === 'bag') addCharacterToSeason(id);
        else removeCharacterFromSeason(id);
        return;
      }

      var card = ev.target.closest('[data-char-card]');
      if (card && !ev.target.closest('button')) {
        var cid = Number(card.getAttribute('data-char-card') || 0);
        var zone = card.getAttribute('data-zone') || '';
        if (zone === 'bag') addCharacterToSeason(cid);
        else removeCharacterFromSeason(cid);
      }
    });

    document.addEventListener('dragstart', handleDragStart);
    setupDropzone($('asbBagZone'), 'bag');
    setupDropzone($('asbSeasonZone'), 'season');
  }

  bindEvents();
  resetSeasonForm();
  refreshSeasons().then(refreshCharacters);
})();
</script>
<?php endif; ?>
<?php if (!$isAjaxRequest) { admin_panel_close(); } ?>
