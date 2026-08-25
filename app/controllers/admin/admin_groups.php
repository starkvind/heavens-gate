<?php
/**
 * admin_groups.php — Modales + creación/renombrado + HTML server-side
 *
 * Requisitos:
 * - $link: conexión mysqli abierta (body_work.php)
 * - Tablas: dim_organizations(id,name,...) | dim_groups(id,name,chronicle_id,totem_id,is_active,`description`)
 * - Puentes: bridge_organizations_groups(organization_id,group_id,is_active)
 *            bridge_characters_groups(character_id,group_id,is_active,position)
 * - fact_characters(id,name,alias,garou_name)
 */

include_once(__DIR__ . '/../../helpers/admin_ajax.php');
if (!hg_admin_require_db($link)) { return; }

if (session_status() === PHP_SESSION_NONE) { @session_start(); }
if (method_exists($link, 'set_charset')) {
  $link->set_charset('utf8mb4');
} else {
  mysqli_set_charset($link, 'utf8mb4');
}
include_once(__DIR__ . '/../../helpers/pretty.php');
include_once(__DIR__ . '/../../partials/admin/admin_styles.php');

$isAjaxRequest = hg_admin_is_ajax_request();
if ($isAjaxRequest && $_SERVER['REQUEST_METHOD'] !== 'POST') {
  hg_admin_json_error('Método no permitido', 405, ['method' => 'POST requerido']);
}

$ADMIN_CSRF_SESSION_KEY = 'csrf_admin_groups';
$ADMIN_CSRF_TOKEN = function_exists('hg_admin_ensure_csrf_token')
  ? hg_admin_ensure_csrf_token($ADMIN_CSRF_SESSION_KEY)
  : (empty($_SESSION[$ADMIN_CSRF_SESSION_KEY]) ? ($_SESSION[$ADMIN_CSRF_SESSION_KEY] = bin2hex(random_bytes(16))) : $_SESSION[$ADMIN_CSRF_SESSION_KEY]);

/* ----------------------- helpers ----------------------- */
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function q($link,$sql,$types='',$params=[]){
  $st = mysqli_prepare($link,$sql);
  if(!$st){ return [false,mysqli_error($link),null,null]; }
  if($types!==''){ mysqli_stmt_bind_param($st,$types,...$params); }
  if(!mysqli_stmt_execute($st)){ $err=mysqli_stmt_error($st); mysqli_stmt_close($st); return [false,$err,null,null]; }
  $res = mysqli_stmt_get_result($st);
  $id  = mysqli_insert_id($link);
  mysqli_stmt_close($st);
  return [true,null,$res ?? null,$id];
}

function get_totems($link): array {
  $out = [];
  $sql = "SELECT id, name FROM dim_totems ORDER BY name ASC";
  [$ok,$err,$rs] = q($link,$sql);
  if($ok && $rs){
    while($r = mysqli_fetch_assoc($rs)){
      $out[(int)$r['id']] = (string)$r['name'];
    }
  }
  return $out;
}

function normalize_totem_id($raw): ?int {
  $value = trim((string)$raw);
  if ($value === '' || $value === '0' || $value === '-1') {
    return null;
  }
  $totemId = (int)$value;
  return $totemId > 0 ? $totemId : null;
}

function render_totem_options(array $totems, ?int $selectedId = null, string $emptyLabel = '— Sin tótem —'): void {
  echo "<option value=''>".e($emptyLabel)."</option>";
  foreach ($totems as $tid => $tname) {
    $isSelected = ((int)$tid === (int)($selectedId ?? 0));
    echo "<option value='".e($tid)."' ".($isSelected ? 'selected' : '').">".e($tname)."</option>";
  }
}

function ag_status_badge(string $text, bool $isOn = true): string {
  $cls = $isOn ? 'badge' : 'badge off';
  return "<span class='{$cls}'>".e($text)."</span>";
}

/* ----------------------- Renders (HTML) ----------------------- */
function render_clans_table($link){
  $sql = "SELECT c.id, c.name,
          (SELECT COUNT(*)
             FROM bridge_organizations_groups b
             INNER JOIN dim_groups m ON m.id = b.group_id
            WHERE b.organization_id = c.id
              AND b.is_active = 1
              AND COALESCE(m.is_active, 1) = 1) AS groups_active
          FROM dim_organizations c
          ORDER BY c.name ASC";
  [$ok,$err,$rs] = q($link,$sql);
  if(!$ok){ echo "<div class='err'>".e($err)."</div>"; return; }

  echo "<table class='table' id='clansTable'>
          <thead><tr><th>ID</th><th>Organización</th><th>Manadas activas</th><th></th></tr></thead>
          <tbody>";
  while($r = mysqli_fetch_assoc($rs)){
    echo "<tr class='row'>
            <td>".e($r['id'])."</td>
            <td><strong>".e($r['name'])."</strong></td>
            <td>".e((int)$r['groups_active'])."</td>
            <td>
              <button class='btn btn-edit-clan' data-id='".e($r['id'])."'>Gestionar</button>
            </td>
          </tr>";
  }
  echo "</tbody></table>";
}

function render_groups_table($link){
  $sql = "SELECT
            m.id,
            m.name,
            m.is_active AS activa,
            (
              SELECT o.name
              FROM bridge_organizations_groups bog
              INNER JOIN dim_organizations o ON o.id = bog.organization_id
              WHERE bog.group_id = m.id AND bog.is_active = 1
              ORDER BY o.name ASC
              LIMIT 1
            ) AS organization_name
          FROM dim_groups m
          ORDER BY m.name ASC";
  [$ok,$err,$rs] = q($link,$sql);
  if(!$ok){ echo "<div class='err'>".e($err)."</div>"; return; }

  echo "<table class='table' id='groupsTable'>
          <thead><tr><th>ID</th><th>Manada</th><th>Estado</th><th>Organización activa</th><th></th></tr></thead>
          <tbody>";
  while($r = mysqli_fetch_assoc($rs)){
    $isActive = (int)$r['activa']===1;
    $organizationName = trim((string)($r['organization_name'] ?? ''));
    echo "<tr class='row'>
            <td>".e($r['id'])."</td>
            <td><strong>".e($r['name'])."</strong></td>
            <td>".ag_status_badge($isActive ? 'Activa' : 'Inactiva', $isActive)."</td>
            <td>".($organizationName !== '' ? e($organizationName) : "<span class='small'>Sin organización activa</span>")."</td>
            <td>
              <button class='btn btn-edit-group' data-id='".e($r['id'])."'>Gestionar</button>
            </td>
          </tr>";
  }
  echo "</tbody></table>";
}

/* --- fragmento: detalle clan (packs vinculados + disponibles) --- */
function render_clan_detail($link,$organization_id){
  $organization_id = (int)$organization_id;

  $sqlL = "SELECT m.id, m.name, m.is_active AS group_is_active, b.is_active
           FROM bridge_organizations_groups b
           INNER JOIN dim_groups m ON m.id=b.group_id
           WHERE b.organization_id=?
           ORDER BY m.name ASC";
  [$ok1,$err1,$rs1] = q($link,$sqlL,'i',[$organization_id]);
  if(!$ok1){ echo "<div class='err'>".e($err1)."</div>"; return; }

  $linked=[]; $ids=[];
  while($r=mysqli_fetch_assoc($rs1)){ $linked[]=$r; $ids[]=(int)$r['id']; }

  if(count($ids)){
    $in = implode(',', array_map('intval',$ids));
    $sqlA = "SELECT id,name
             FROM dim_groups
             WHERE COALESCE(is_active, 1) = 1
               AND id NOT IN ($in)
             ORDER BY name ASC";
    [$ok2,$err2,$rs2] = q($link,$sqlA);
  } else {
    $sqlA = "SELECT id,name
             FROM dim_groups
             WHERE COALESCE(is_active, 1) = 1
             ORDER BY name ASC";
    [$ok2,$err2,$rs2] = q($link,$sqlA);
  }
  if(!$ok2){ echo "<div class='err'>".e($err2)."</div>"; return; }

  $avail=[]; while($r=mysqli_fetch_assoc($rs2)){ $avail[]=$r; }
  $active = array_values(array_filter($linked, fn($x)=>(int)$x['is_active']===1 && (int)($x['group_is_active'] ?? 1)===1));
  $inactive = array_values(array_filter($linked, fn($x)=>!((int)$x['is_active']===1 && (int)($x['group_is_active'] ?? 1)===1)));

  echo "<div class='card adm-mb-10'>
          <div class='small'>Aquí gestionas el vínculo entre organización y manada. Quitar o activar en esta pantalla afecta al vínculo. Si una manada está desactivada, primero debes activarla desde su propia ficha.</div>
        </div>
        <div class='split'>
          <div>
            <h4>Manadas activas <span class='count'>".count($active)."</span></h4>
            <div class='grid' id='packsActive'>";
  foreach($active as $p){
    echo "<div class='card'>
            <h4><span>".e($p['name'])."</span>
                <span>
                  <button class='btn btn-pack-deactivate' data-gid='".e($p['id'])."' data-clan='$organization_id'>Desvincular</button>
                  <a class='btn' href='/groups/".e($p['id'])."' target='_blank'>Ver</a>
                </span>
            </h4>
            <div class='small'>".ag_status_badge('Vinculada y activa', true)."</div>
          </div>";
  }
  echo   "</div>
        </div>
        <div>
          <h4>Vincular manada activa</h4>
          <div class='toolbar'>
            <select id='packsAvailable' class='adm-input-dark-flex'>";
  foreach($avail as $p){ echo "<option value='".e($p['id'])."'>".e($p['name'])."</option>"; }
  echo     "</select>
            <button class='btn btn-ok' id='btnAddPack' data-clan='$organization_id' ".(empty($avail)?'disabled':'').">Vincular</button>
          </div>
          <div class='hr'></div>
          <h4>Manadas no activas en esta organización</h4>
          <div class='grid' id='packsInactive'>";
  foreach($inactive as $p){
    $bridgeActive = (int)($p['is_active'] ?? 0) === 1;
    $groupActive = (int)($p['group_is_active'] ?? 1) === 1;
    $notes = [];
    if(!$groupActive){ $notes[] = 'manada desactivada'; }
    if(!$bridgeActive){ $notes[] = 'vinculo inactivo'; }
    $statusHtml = empty($notes) ? '' : "<div class='small adm-mt-4'>".e(implode(' · ', $notes))."</div>";
    $actionHtml = $groupActive
      ? "<button class='btn btn-pack-activate' data-gid='".e($p['id'])."' data-clan='$organization_id'>Reactivar vínculo</button>"
      : "<span class='small'>Activa la manada desde su ficha</span>";
    echo "<div class='card'>
            <h4><span>".e($p['name'])."</span>
                <span>
                  $actionHtml
                  <a class='btn' href='/groups/".e($p['id'])."' target='_blank'>Ver</a>
                </span>
            </h4>
            $statusHtml
          </div>";
  }
  echo   "</div>
        </div>
      </div>";
}

/* --- fragmento: detalle manada (miembros) --- */
function render_group_detail($link,$group_id){
  $group_id = (int)$group_id;
  $sql = "SELECT p.id, p.name AS nombre, p.alias, p.garou_name AS nombregarou, b.is_active, b.position
          FROM bridge_characters_groups b
          INNER JOIN fact_characters p ON p.id=b.character_id
          WHERE b.group_id=?
          ORDER BY p.name ASC";
  [$ok,$err,$rs] = q($link,$sql,'i',[$group_id]);
  if(!$ok){ echo "<div class='err'>".e($err)."</div>"; return; }

  $a=[];$i=[];
  while($r=mysqli_fetch_assoc($rs)){ ((int)$r['is_active']===1) ? $a[]=$r : $i[]=$r; }

  echo "<div class='toolbar'>
          <input id='searchChar' type='text' placeholder='Buscar personaje para añadir...'>
          <input id='newPosition' type='text' placeholder='Posición (opcional)'>
          <button class='btn btn-ok' id='btnAddMember' data-group='$group_id'>Añadir a la manada</button>
        </div>
        <div id='searchResults' class='grid adm-hidden'></div>

        <div class='card adm-mt-8'>
          <h4>Miembros activos <span class='count'>".count($a)."</span></h4>
          <div id='membersActive' class='chips'>";
  foreach($a as $m){
    $label = $m['nombre'].( $m['alias'] ? " ({$m['alias']})" : "" );
    echo "<span class='chip' data-id='".e($m['id'])."'>
            <span>".e($label)."</span>
            <input type='text' value='".e($m['position'])."' placeholder='posición'>
            <button class='btn btn-save-position' data-id='".e($m['id'])."' data-group='$group_id'>Guardar</button>
            <button class='btn btn-bad btn-rem-member' data-id='".e($m['id'])."' data-group='$group_id'>Quitar</button>
          </span>";
  }
  echo   "</div></div>

        <div class='card adm-mt-8'>
          <h4>Miembros inactivos</h4>
          <div id='membersInactive' class='chips'>";
  foreach($i as $m){
    $label = $m['nombre'].( $m['alias'] ? " ({$m['alias']})" : "" );
    echo "<span class='chip off' data-id='".e($m['id'])."'>
            <span>".e($label)."</span>
            <input type='text' value='".e($m['position'])."' placeholder='posición'>
            <button class='btn btn-ok btn-activate-member' data-id='".e($m['id'])."' data-group='$group_id'>Reactivar</button>
          </span>";
  }
  echo   "</div></div>";
}

/* --- MODALES --- */
function render_clan_modal($link,$organization_id){
  $organization_id = (int)$organization_id;
  [$ok,$err,$rs] = q($link,"SELECT id,name,totem_id AS totem,color,is_npc,`description` FROM dim_organizations WHERE id=? LIMIT 1",'i',[$organization_id]);
  if(!$ok || !$rs || !($clan=mysqli_fetch_assoc($rs))){
    echo "<div class='err'>Clan no encontrado.</div>"; return;
  }
  $totems = get_totems($link);
  $totemSel = isset($clan['totem']) ? (int)$clan['totem'] : null;
  $clanColor = (string)($clan['color'] ?? '#ffffff');
  if (!preg_match('/^#[0-9a-fA-F]{6}$/', $clanColor)) $clanColor = '#ffffff';
  $clanIsNpc = ((int)($clan['is_npc'] ?? 0) === 1) ? 1 : 0;
  $clanDesc = (string)($clan['description'] ?? '');
  echo "<div class='modal-header'>
          <h3>Editar clan</h3>
          <button class='modal-close' aria-label='Cerrar'>&times;</button>
        </div>
        <div class='modal-body'>
          <div class='card'>
            <h4>Datos del clan</h4>
            <div class='toolbar'>
              <input id='clanName' type='text' value='".e($clan['name'])."'>
              <select id='clanTotem' class='adm-select-dark-240'>";
  render_totem_options($totems, $totemSel);
  echo      "</select>
              <input id='clanColor' type='color' value='".e($clanColor)."' title='Color'>
              <select id='clanIsNpc' class='adm-select-dark-140'>
                <option value='0' ".($clanIsNpc===0?'selected':'').">is_npc: 0</option>
                <option value='1' ".($clanIsNpc===1?'selected':'').">is_npc: 1</option>
              </select>
              <button class='btn btn-ok' id='btnClanSave' data-id='".e($clan['id'])."'>Guardar</button>
              <button class='btn' id='btnOpenGroupCreate' data-clan='".e($clan['id'])."'>Nueva manada</button>
            </div>
            <div class='toolbar adm-mt-8'>
              <textarea id='clanDescription' rows='4' class='adm-w-full-resize-v' placeholder='Descripción'>".e($clanDesc)."</textarea>
            </div>
          </div>
          <div class='hr'></div>
          <div id='clanModalDetail'>";
  render_clan_detail($link,$organization_id);
  echo   "</div>
        </div>";
}

function render_group_modal($link,$group_id){
  $group_id = (int)$group_id;
  [$ok,$err,$rs] = q($link,"SELECT id,name,is_active AS activa,IFNULL(chronicle_id,1) AS cronica, totem_id AS totem, `description` FROM dim_groups WHERE id=? LIMIT 1",'i',[$group_id]);
  if(!$ok || !$rs || !($g=mysqli_fetch_assoc($rs))){
    echo "<div class='err'>Manada no encontrada.</div>"; return;
  }
  $totems = get_totems($link);
  $groupDesc = (string)($g['description'] ?? '');
  $totemSel = isset($g['totem']) ? (int)$g['totem'] : null;
  $orgNames = [];
  [$okOrg,$errOrg,$rsOrg] = q($link, "SELECT o.name, bog.is_active
    FROM bridge_organizations_groups bog
    INNER JOIN dim_organizations o ON o.id = bog.organization_id
    WHERE bog.group_id = ?
    ORDER BY bog.is_active DESC, o.name ASC", 'i', [$group_id]);
  if ($okOrg && $rsOrg) {
    while($orgRow = mysqli_fetch_assoc($rsOrg)){
      $orgNames[] = ((int)($orgRow['is_active'] ?? 0) === 1 ? '[Activa] ' : '[Inactiva] ') . (string)($orgRow['name'] ?? '');
    }
  }
  $groupIsActive = ((int)$g['activa']===1);
  echo "<div class='modal-header'>
          <h3>Editar manada</h3>
          <button class='modal-close' aria-label='Cerrar'>&times;</button>
        </div>
        <div class='modal-body'>
          <div class='card'>
            <div class='toolbar'>
              <div class='card adm-w-full'>
                <div class='small'>Estado de la manada</div>
                <div>".ag_status_badge($groupIsActive ? 'Activa' : 'Inactiva', $groupIsActive)."</div>
              </div>
              <div class='card adm-w-full'>
                <div class='small'>Organización vinculada</div>
                <div>".(!empty($orgNames) ? e($orgNames[0]) : "<span class='small'>Sin vínculo activo</span>")."</div>
              </div>
            </div>
          </div>
          <div class='card adm-mt-8'>
            <h4>Datos básicos</h4>
            <div class='toolbar'>
              <input id='groupName' type='text' value='".e($g['name'])."' placeholder='Nombre'>
              <input id='groupCronica' type='number' min='1' step='1' value='".e($g['cronica'])."' class='adm-maxw-120' title='Crónica'>
              <select id='groupTotem' class='adm-select-dark-240'>";
  render_totem_options($totems, $totemSel);
  echo      "</select>
              <label class='adm-flex-6-center'>
                <input id='groupActiva' type='checkbox' ".($groupIsActive?'checked':'')."> Manada activa
              </label>
              <button class='btn btn-ok' id='btnSaveGroupBasic' data-id='".e($g['id'])."'>Guardar</button>
              <a class='btn' href='/groups/".e($g['id'])."' target='_blank'>Ver página</a>
            </div>
            <div class='small adm-mt-4'>Este interruptor activa o desactiva la manada completa. El vínculo con una organización se gestiona desde la ficha de la organización.</div>
            <div class='toolbar adm-mt-8'>
              <textarea id='groupDescription' rows='4' class='adm-w-full-resize-v' placeholder='Descripción'>".e($groupDesc)."</textarea>
            </div>
          </div>
          <div class='hr'></div>
          <h4>Miembros</h4>
          <div id='groupModalDetail'>";
  render_group_detail($link,$group_id);
  echo   "</div>
        </div>";
}

function render_clan_create_form($link){
  $nextSort = 0;
  $totems = get_totems($link);
  [$ok,$err,$rs] = q($link, "SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_sort FROM dim_organizations");
  if ($ok && $rs && ($row = mysqli_fetch_assoc($rs))) {
    $nextSort = (int)($row['next_sort'] ?? 0);
  }
  echo "<div class='modal-header'>
          <h3>Nuevo clan</h3>
          <button class='modal-close' aria-label='Cerrar'>&times;</button>
        </div>
        <div class='modal-body'>
          <div class='toolbar'>
            <input id='newClanName' type='text' placeholder='Nombre del clan'>
            <input id='newClanSortOrder' type='number' min='0' step='1' value='".e($nextSort)."' placeholder='Orden'>
            <select id='newClanTotem' class='adm-select-dark-240'>";
  render_totem_options($totems, null);
  echo    "</select>
            <input id='newClanColor' type='color' value='#ffffff' title='Color'>
            <select id='newClanIsNpc' class='adm-select-dark-140'>
              <option value='0' selected>is_npc: 0</option>
              <option value='1'>is_npc: 1</option>
            </select>
            <button class='btn btn-ok' id='btnCreateClan'>Crear</button>
          </div>
          <div class='toolbar adm-mt-8'>
            <textarea id='newClanDescription' rows='4' class='adm-w-full-resize-v' placeholder='Descripción'></textarea>
          </div>
          <div class='small'>Se creará con valores por defecto. Podrás completar más campos en otras pantallas si es necesario.</div>
        </div>";
}

function render_group_create_form($link,$prefill_clan_id=0){
  $prefill_clan_id=(int)$prefill_clan_id;
  [$ok,$err,$rs] = q($link,"SELECT id,name FROM dim_organizations ORDER BY name ASC");
  $totems = get_totems($link);
  echo "<div class='modal-header'>
          <h3>Nueva manada</h3>
          <button class='modal-close' aria-label='Cerrar'>&times;</button>
        </div>
        <div class='modal-body'>
          <div class='grid'>
            <div class='card'>
              <h4>Datos básicos</h4>
              <div class='toolbar'>
                <input id='newGroupName' type='text' placeholder='Nombre de la manada' class='adm-w-full'>
              </div>
              <div class='toolbar adm-mt-8'>
                <input id='newGroupCronica' type='number' min='1' step='1' value='1' class='adm-maxw-120' title='Crónica' placeholder='Crónica'>
                <select id='newGroupTotem' class='adm-select-dark-240'>";
  render_totem_options($totems, null);
  echo      "</select>
                <label class='adm-flex-6-center'>
                  <input id='newGroupActiva' type='checkbox' checked> Activa
                </label>
              </div>
              <div class='toolbar adm-mt-8'>
                <textarea id='newGroupDescription' rows='4' class='adm-w-full-resize-v' placeholder='Descripción'></textarea>
              </div>
              <div class='small adm-mt-4'>Indica el nombre, la crónica base y, si quieres, un tótem inicial para la manada.</div>
            </div>
            <div class='card'>
              <h4>Asignación inicial</h4>
              <div class='toolbar'>
                <select id='newGroupClan' class='adm-input-dark-flex'>
                  <option value='0' ".($prefill_clan_id===0?'selected':'').">— Sin asignar —</option>";
  if($ok){ while($c=mysqli_fetch_assoc($rs)){
    echo "<option value='".e($c['id'])."' ".($prefill_clan_id===(int)$c['id']?'selected':'').">".e($c['name'])."</option>";
  }}
  echo        "</select>
              </div>
              <div class='toolbar adm-mt-8'>
                <button class='btn btn-ok' id='btnCreateGroup'>Crear</button>
              </div>
              <div class='small'>Si eliges una organización, la manada quedará vinculada y activa dentro de ella desde el momento de crearla.</div>
            </div>
          </div>
        </div>";
}

/* ----------------------- Acciones AJAX (HTML) ----------------------- */
if(!empty($_POST['action'])){
  $act = $_POST['action'];
  header('Content-Type: text/html; charset=utf-8');
  if (function_exists('hg_admin_require_session') && !hg_admin_require_session(false)) {
    echo "<div class='err'>No autorizado.</div>";
    exit;
  }
  $readOnlyActions = [
    'load_clans_table','load_groups_table',
    'clan_modal','group_modal',
    'clan_create_form','group_create_form',
    'search_characters'
  ];
  $requiresCsrf = !in_array($act, $readOnlyActions, true);
  if ($requiresCsrf && function_exists('hg_admin_csrf_valid')) {
    $payload = function_exists('hg_admin_read_json_payload') ? hg_admin_read_json_payload() : [];
    $csrf = function_exists('hg_admin_extract_csrf_token')
      ? hg_admin_extract_csrf_token($payload)
      : trim((string)($_POST['csrf'] ?? ''));
    if (!hg_admin_csrf_valid($csrf, $ADMIN_CSRF_SESSION_KEY)) {
      echo "<div class='err'>CSRF invalido. Recarga la pagina.</div>";
      exit;
    }
  }

  // tablas básicas
  if($act==='load_clans_table'){ render_clans_table($link); exit; }
  if($act==='load_groups_table'){ render_groups_table($link); exit; }

  // modales abrir
  if($act==='clan_modal'){ $id=(int)($_POST['organization_id']??0); render_clan_modal($link,$id); exit; }
  if($act==='group_modal'){ $id=(int)($_POST['group_id']??0); render_group_modal($link,$id); exit; }
  if($act==='clan_create_form'){ render_clan_create_form($link); exit; }
  if($act==='group_create_form'){ $cid=(int)($_POST['organization_id']??0); render_group_create_form($link,$cid); exit; }

  // clan update basic (name + totem + color + is_npc + description)
  if($act==='clan_update_basic'){
    $id=(int)($_POST['organization_id']??0);
    $name=trim((string)($_POST['name']??''));
    $totem = normalize_totem_id($_POST['totem'] ?? null);
    $color=trim((string)($_POST['color']??'#ffffff'));
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = '#ffffff';
    $is_npc=((int)($_POST['is_npc']??0)===1)?1:0;
    $description=(string)($_POST['description']??'');
    if($id>0 && $name!==''){ q($link,"UPDATE dim_organizations SET name=?, totem_id=?, color=?, is_npc=?, `description`=? WHERE id=?",'sisisi',[$name,$totem,$color,$is_npc,$description,$id]); }
    hg_update_pretty_id_if_exists($link, 'dim_organizations', $id, $name);
    render_clan_modal($link,$id); exit;
  }

  // crear clan
  if($act==='clan_create'){
    $name=trim((string)($_POST['name']??''));
    $sort_order=(int)($_POST['sort_order']??0);
    if($sort_order < 0){ $sort_order = 0; }
    $totem = normalize_totem_id($_POST['totem'] ?? null);
    $color=trim((string)($_POST['color']??'#ffffff'));
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = '#ffffff';
    $is_npc=((int)($_POST['is_npc']??0)===1)?1:0;
    $description=(string)($_POST['description']??'');
    if($name===''){ render_clan_create_form($link); echo "<div class='err'>Indica un nombre.</div>"; exit; }
    // Insert básico: si tu tabla exige más campos NOT NULL sin default, añade aquí columnas con valores por defecto.
    [$ok,$err,$rs,$newId] = q($link,"INSERT INTO dim_organizations (name, sort_order, totem_id, color, is_npc, `description`) VALUES (?,?,?,?,?,?)",'sisiss',[$name, $sort_order, $totem, $color, $is_npc, $description]);
    if(!$ok){ render_clan_create_form($link); echo "<div class='err'>".e($err)."</div>"; exit; }
    hg_update_pretty_id_if_exists($link, 'dim_organizations', (int)$newId, $name);
    render_clan_modal($link,$newId); exit;
  }

  // grupo: guardar básicos (rename, activa, crónica)
  if($act==='group_update_basic'){
    $id=(int)($_POST['group_id']??0);
    $name=trim((string)($_POST['name']??''));
    $activa = (int)($_POST['activa']??0)===1?1:0;
    $cronica = (int)($_POST['cronica']??1); if($cronica<1){ $cronica=1; }
    $totem = normalize_totem_id($_POST['totem'] ?? null);
    $description=(string)($_POST['description']??'');
    if($id>0 && $name!==''){
      q($link,"UPDATE dim_groups SET name=?, is_active=?, chronicle_id=?, totem_id=?, `description`=? WHERE id=?",'siiisi',[$name,$activa,$cronica,$totem,$description,$id]);
      hg_update_pretty_id_if_exists($link, 'dim_groups', $id, $name);
    }
    render_group_modal($link,$id); exit;
  }

  // crear grupo
  if($act==='group_create'){
    $name=trim((string)($_POST['name']??''));
    $cronica=(int)($_POST['cronica']??1); if($cronica<1){ $cronica=1; }
    $activa=(int)($_POST['activa']??1)===1?1:0;
    $organization_id=(int)($_POST['organization_id']??0);
    $totem = normalize_totem_id($_POST['totem'] ?? null);
    $description=(string)($_POST['description']??'');
    if($name===''){ render_group_create_form($link,$organization_id); echo "<div class='err'>Indica un nombre.</div>"; exit; }

    // dim_groups: name, chronicle_id, totem_id, is_active, description (NOT NULL)
    [$ok,$err,$rs,$newId] = q($link,
      "INSERT INTO dim_groups (name, chronicle_id, totem_id, is_active, `description`) VALUES (?,?,?,?,?)",
      'siiis', [$name, $cronica, $totem, $activa, $description]);
    if(!$ok){ render_group_create_form($link,$organization_id); echo "<div class='err'>".e($err)."</div>"; exit; }
    hg_update_pretty_id_if_exists($link, 'dim_groups', (int)$newId, $name);

    // Bridge (opcional) si seleccionó organization_id
    if($organization_id>0){
      q(
        $link,
        "UPDATE bridge_organizations_groups
         SET is_active=0
         WHERE group_id=? AND organization_id<>?",
        'ii',
        [$newId,$organization_id]
      );
      q(
        $link,
        "INSERT INTO bridge_organizations_groups (organization_id,group_id,is_active) VALUES (?,?,1)
         ON DUPLICATE KEY UPDATE is_active=1",
        'ii',
        [$organization_id,$newId]
      );
    }
    render_group_modal($link,$newId); exit;
  }

  // clan detalle (packs dentro del modal) — mismas acciones que antes
  if($act==='clan_add_group'){
    $organization_id=(int)($_POST['organization_id']??0);
    $group_id=(int)($_POST['group_id']??0);
    if($organization_id>0 && $group_id>0){
      q(
        $link,
        "UPDATE bridge_organizations_groups
         SET is_active=0
         WHERE group_id=? AND organization_id<>?",
        'ii',
        [$group_id,$organization_id]
      );
      q(
        $link,
        "INSERT INTO bridge_organizations_groups (organization_id,group_id,is_active) VALUES (?,?,1)
         ON DUPLICATE KEY UPDATE is_active=1",
        'ii',
        [$organization_id,$group_id]
      );
    }
    render_clan_detail($link,$organization_id); exit;
  }
  if($act==='clan_remove_group'){
    $organization_id=(int)($_POST['organization_id']??0);
    $group_id=(int)($_POST['group_id']??0);
    if($organization_id>0 && $group_id>0){
      q($link,"UPDATE bridge_organizations_groups SET is_active=0 WHERE organization_id=? AND group_id=?",'ii',[$organization_id,$group_id]);
    }
    render_clan_detail($link,$organization_id); exit;
  }

  // group detalle (miembros dentro del modal)
  if($act==='group_add_member'){
    $group_id=(int)($_POST['group_id']??0);
    $character_id=(int)($_POST['character_id']??0);
    $position=trim((string)($_POST['position']??''));
    if($group_id>0 && $character_id>0){
      q(
        $link,
        "INSERT INTO bridge_characters_groups (character_id,group_id,is_active,position) VALUES (?,?,1,?)
         ON DUPLICATE KEY UPDATE is_active=1, position=VALUES(position)",
        'iis',
        [$character_id,$group_id,$position]
      );
    }
    render_group_detail($link,$group_id); exit;
  }
  if($act==='group_remove_member'){
    $group_id=(int)($_POST['group_id']??0);
    $character_id=(int)($_POST['character_id']??0);
    if($group_id>0 && $character_id>0){
      q($link,"UPDATE bridge_characters_groups SET is_active=0 WHERE group_id=? AND character_id=?",'ii',[$group_id,$character_id]);
    }
    render_group_detail($link,$group_id); exit;
  }
  if($act==='group_save_position'){
    $group_id=(int)($_POST['group_id']??0);
    $character_id=(int)($_POST['character_id']??0);
    $position=trim((string)($_POST['position']??''));
    if($group_id>0 && $character_id>0){
      q($link,"UPDATE bridge_characters_groups SET position=? WHERE group_id=? AND character_id=?", 'sii', [$position,$group_id,$character_id]);
    }
    render_group_detail($link,$group_id); exit;
  }

  // búsqueda de personajes
  if($act==='search_characters'){
    $qtxt = trim((string)($_POST['q']??''));
    if($qtxt===''){ echo ""; exit; }
    $like="%{$qtxt}%";
    [$ok,$err,$rs] = q($link,"SELECT id,name AS nombre,alias,garou_name AS nombregarou
                              FROM fact_characters
                              WHERE name LIKE ? OR alias LIKE ? OR garou_name LIKE ?
                              ORDER BY name ASC LIMIT 30",'sss',[$like,$like,$like]);
    if(!$ok){ echo "<div class='err'>".e($err)."</div>"; exit; }
    echo "<div class='grid'>";
    while($r=mysqli_fetch_assoc($rs)){
      $lab = $r['nombre'].( $r['alias'] ? " ({$r['alias']})" : "" );
      echo "<div class='card'>
              <div class='adm-flex-between-8'>
                <div>".e($lab)."</div>
                <button class='btn btn-pick-char' data-id='".e($r['id'])."'>Añadir</button>
              </div>
            </div>";
    }
    echo "</div>"; exit;
  }

  echo "<div class='err'>Acción no reconocida.</div>"; exit;
}

/* ----------------------- UI ----------------------- */
$ADMIN_GROUPS_ENDPOINT = '/talim?s=admin_groups&ajax=1';
admin_panel_open('Grupos (Manadas y Clanes)');
?>
<link rel="stylesheet" href="/assets/vendor/select2/select2.min.4.1.0.css">
<style>
#agModal {
  --adm-s2-bg: #000033;
  --adm-s2-color: #ffffff;
  --adm-s2-border: #333333;
  --adm-s2-hover: #001199;
  --adm-s2-selected: #00105f;
}
#adminGroupsApp,
#adminGroupsApp h3,
#adminGroupsApp .small,
#adminGroupsApp th,
#adminGroupsApp td,
#agModal .modal-body,
#agModal .modal-body h3,
#agModal .modal-body h4,
#agModal .modal-body .small,
#agModal .modal-body label {
  text-align: left;
}
#adminGroupsApp .btn,
#agModal .modal-body .btn {
  text-align: center;
}
#agModal .select2-dropdown { background: var(--adm-s2-bg) !important; border: 1px solid var(--adm-s2-border) !important; color: var(--adm-s2-color) !important; }
#agModal .select2-results__option { background: transparent !important; color: var(--adm-s2-color) !important; }
#agModal .select2-container--default .select2-results__option--selected { background: var(--adm-s2-selected) !important; color: var(--adm-s2-color) !important; }
#agModal .select2-container--default .select2-results__option--highlighted.select2-results__option--selectable { background: var(--adm-s2-hover) !important; color: #ffffff !important; }
#agModal .select2-container--default .select2-selection--single { background: var(--adm-s2-bg) !important; border-color: var(--adm-s2-border) !important; }
#agModal .select2-container--default .select2-selection--single .select2-selection__rendered { color: var(--adm-s2-color) !important; }
#agModal .select2-container--default .select2-selection--single .select2-selection__placeholder { color: rgba(255, 255, 255, .78) !important; }
#agModal .select2-container--default .select2-selection--single .select2-selection__arrow b { border-color: #9fd8ff transparent transparent !important; }
#agModal .select2-container--default.select2-container--open .select2-selection--single .select2-selection__arrow b { border-color: transparent transparent #9fd8ff !important; }
</style>
<div class="adm-crud-wrap" id="adminGroupsApp">
  <div class="tabs">
    <a href="#" class="tablink active" data-tab="clans">Clanes</a>
    <a href="#" class="tablink" data-tab="groups">Manadas</a>
  </div>

  <div id="tab-clans" class="box">
    <h3>Clanes</h3>
    <div class="small adm-mb-10">Gestiona aquí las organizaciones y qué manadas están vinculadas a cada una.</div>
    <div class="toolbar">
      <input id="filterClans" type="text" placeholder="Filtrar clanes...">
      <button class="btn" id="btnNewClan">Nuevo clan</button>
      <button class="btn" id="reloadClans">Recargar</button>
    </div>
    <div id="clansTableWrap"><?php render_clans_table($link); ?></div>
  </div>

  <div id="tab-groups" class="box" style="display:none;">
    <h3>Manadas</h3>
    <div class="small adm-mb-10">Gestiona aquí el estado propio de cada manada, sus datos básicos y sus miembros.</div>
    <div class="toolbar">
      <input id="filterGroups" type="text" placeholder="Filtrar manadas...">
      <button class="btn" id="btnNewGroup">Nueva manada</button>
      <button class="btn" id="reloadGroups">Recargar</button>
    </div>
    <div id="groupsTableWrap"><?php render_groups_table($link); ?></div>
  </div>
</div>

<!-- Modal global (mismo patron que admin_relations) -->
<div id="agModal" class="modal-back" style="display:none;">
  <div class="modal adm-u-070">
    <div id="modalContent" class="modal-body"></div>
  </div>
</div>
<?php
$adminHttpJs = '/assets/js/admin/admin-http.js';
$adminHttpJsVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . $adminHttpJs) ?: time();
?>
<script>
window.ADMIN_CSRF_TOKEN = <?= json_encode($ADMIN_CSRF_TOKEN, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
window.HG_ADMIN_GROUPS_BOOT = <?= json_encode([
  'endpoint' => $ADMIN_GROUPS_ENDPOINT,
], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="/assets/vendor/jquery/jquery-3.7.1.min.js"></script>
<script src="/assets/vendor/select2/select2.min.4.1.0.js"></script>
<script src="<?= e($adminHttpJs) ?>?v=<?= (int)$adminHttpJsVer ?>"></script>
<?php
$adminGroupsJs = '/assets/js/admin/admin-groups.js';
$adminGroupsJsVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . $adminGroupsJs) ?: time();
?>
<script src="<?= e($adminGroupsJs) ?>?v=<?= (int)$adminGroupsJsVer ?>"></script>
<?php admin_panel_close(); ?>