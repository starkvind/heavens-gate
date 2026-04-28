<?php
/**
 * admin_groups.php — Modales + creación/renombrado + HTML server-side
 *
 * Requisitos:
 * - $link: conexión mysqli abierta (body_work.php)
 * - Tablas: dim_organizations(id,name,...) | dim_groups(id,name,chronicle_id,totem_id,is_active,`description`)
 * - Puentes: bridge_organizations_groups(organization_id,group_id,is_active)
 *            bridge_characters_groups(character_id,group_id,is_active,position)
 * - fact_characters(id,nombre,alias,nombregarou)
 */

if (!isset($link) || !$link) {
  echo "<div class='adm-color-error'>Error: conexión DB no disponible.</div>";
  return;
}
if (method_exists($link, 'set_charset')) {
  $link->set_charset('utf8mb4');
} else {
  mysqli_set_charset($link, 'utf8mb4');
}
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
if (!headers_sent()) {
  header('Content-Type: text/html; charset=UTF-8');
}
include_once(__DIR__ . '/../../helpers/pretty.php');
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
include_once(__DIR__ . '/../../partials/admin/admin_styles.php');

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
  $out = [0 => '— Sin tótem —'];
  $sql = "SELECT id, name FROM dim_totems ORDER BY name ASC";
  [$ok,$err,$rs] = q($link,$sql);
  if($ok && $rs){
    while($r = mysqli_fetch_assoc($rs)){
      $out[(int)$r['id']] = (string)$r['name'];
    }
  }
  return $out;
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
          <thead><tr><th>ID</th><th>Nombre</th><th>Grupos activos</th><th></th></tr></thead>
          <tbody>";
  while($r = mysqli_fetch_assoc($rs)){
    echo "<tr class='row'>
            <td>".e($r['id'])."</td>
            <td>".e($r['name'])."</td>
            <td>".e((int)$r['groups_active'])."</td>
            <td>
              <button class='btn btn-edit-clan' data-id='".e($r['id'])."'>Editar</button>
            </td>
          </tr>";
  }
  echo "</tbody></table>";
}

function render_groups_table($link){
  $sql = "SELECT m.id, m.name, m.is_active AS activa FROM dim_groups m ORDER BY m.name ASC";
  [$ok,$err,$rs] = q($link,$sql);
  if(!$ok){ echo "<div class='err'>".e($err)."</div>"; return; }

  echo "<table class='table' id='groupsTable'>
          <thead><tr><th>ID</th><th>Nombre</th><th>Activa</th><th></th></tr></thead>
          <tbody>";
  while($r = mysqli_fetch_assoc($rs)){
    echo "<tr class='row'>
            <td>".e($r['id'])."</td>
            <td>".e($r['name'])."</td>
            <td>".( (int)$r['activa']===1 ? 'Sí' : 'No' )."</td>
            <td>
              <button class='btn btn-edit-group' data-id='".e($r['id'])."'>Editar</button>
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

  echo "<div class='split'>
          <div>
            <h4>Manadas activas <span class='count'>".count($active)."</span></h4>
            <div class='grid' id='packsActive'>";
  foreach($active as $p){
    echo "<div class='card'>
            <h4><span>".e($p['name'])."</span>
                <span>
                  <button class='btn btn-pack-deactivate' data-gid='".e($p['id'])."' data-clan='$organization_id'>Quitar</button>
                  <a class='btn' href='/groups/".e($p['id'])."' target='_blank'>Ver</a>
                </span>
            </h4>
          </div>";
  }
  echo   "</div>
        </div>
        <div>
          <h4>Añadir manada</h4>
          <div class='toolbar'>
            <select id='packsAvailable' class='adm-input-dark-flex'>";
  foreach($avail as $p){ echo "<option value='".e($p['id'])."'>".e($p['name'])."</option>"; }
  echo     "</select>
            <button class='btn btn-ok' id='btnAddPack' data-clan='$organization_id' ".(empty($avail)?'disabled':'').">Añadir</button>
          </div>
          <div class='hr'></div>
          <h4>Manadas inactivas</h4>
          <div class='grid' id='packsInactive'>";
  foreach($inactive as $p){
    $bridgeActive = (int)($p['is_active'] ?? 0) === 1;
    $groupActive = (int)($p['group_is_active'] ?? 1) === 1;
    $notes = [];
    if(!$groupActive){ $notes[] = 'manada desactivada'; }
    if(!$bridgeActive){ $notes[] = 'vinculo inactivo'; }
    $statusHtml = empty($notes) ? '' : "<div class='small adm-mt-4'>".e(implode(' · ', $notes))."</div>";
    $actionHtml = $groupActive
      ? "<button class='btn btn-pack-activate' data-gid='".e($p['id'])."' data-clan='$organization_id'>Activar</button>"
      : "<span class='small'>Actívala desde la propia manada</span>";
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
  $totemSel = (int)($clan['totem'] ?? 0);
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
  foreach($totems as $tid=>$tname){
    echo "<option value='".e($tid)."' ".($tid===$totemSel?'selected':'').">".e($tname)."</option>";
  }
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
  $totemSel = (int)($g['totem'] ?? 0);
  echo "<div class='modal-header'>
          <h3>Editar manada</h3>
          <button class='modal-close' aria-label='Cerrar'>&times;</button>
        </div>
        <div class='modal-body'>
          <div class='card'>
            <h4>Datos básicos</h4>
            <div class='toolbar'>
              <input id='groupName' type='text' value='".e($g['name'])."' placeholder='Nombre'>
              <input id='groupCronica' type='number' min='1' step='1' value='".e($g['cronica'])."' class='adm-maxw-120' title='Crónica'>
              <select id='groupTotem' class='adm-select-dark-240'>";
  foreach($totems as $tid=>$tname){
    echo "<option value='".e($tid)."' ".($tid===$totemSel?'selected':'').">".e($tname)."</option>";
  }
  echo      "</select>
              <label class='adm-flex-6-center'>
                <input id='groupActiva' type='checkbox' ".((int)$g['activa']===1?'checked':'')."> Activa
              </label>
              <button class='btn btn-ok' id='btnSaveGroupBasic' data-id='".e($g['id'])."'>Guardar</button>
              <a class='btn' href='/groups/".e($g['id'])."' target='_blank'>Ver p?gina</a>
            </div>
            <div class='toolbar adm-mt-8'>
              <textarea id='groupDescription' rows='4' class='adm-w-full-resize-v' placeholder='Descripci?n'>".e($groupDesc)."</textarea>
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
                <input id='newGroupName' type='text' placeholder='Nombre de la manada'>
                <input id='newGroupCronica' type='number' min='1' step='1' value='1' class='adm-maxw-120' title='Crónica'>
                <select id='newGroupTotem' class='adm-select-dark-240'>";
  foreach($totems as $tid=>$tname){
    echo "<option value='".e($tid)."'>".e($tname)."</option>";
  }
  echo      "</select>
                <label class='adm-flex-6-center'>
                  <input id='newGroupActiva' type='checkbox' checked> Activa
                </label>
              <div class='toolbar adm-mt-8'>
                <textarea id='newGroupDescription' rows='4' class='adm-w-full-resize-v' placeholder='Descripci?n'></textarea>
              </div>
            </div>
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
                <button class='btn btn-ok' id='btnCreateGroup'>Crear</button>
              </div>
              <div class='small'>Si eliges un clan, se creará también el vínculo activo en el bridge.</div>
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
    $totem=(int)($_POST['totem']??0);
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
    $color=trim((string)($_POST['color']??'#ffffff'));
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = '#ffffff';
    $is_npc=((int)($_POST['is_npc']??0)===1)?1:0;
    $description=(string)($_POST['description']??'');
    if($name===''){ render_clan_create_form($link); echo "<div class='err'>Indica un nombre.</div>"; exit; }
    // Insert básico: si tu tabla exige más campos NOT NULL sin default, añade aquí columnas con valores por defecto.
    [$ok,$err,$rs,$newId] = q($link,"INSERT INTO dim_organizations (name, sort_order, totem_id, color, is_npc, `description`) VALUES (?,?,0,?,?,?)",'sisis',[$name, $sort_order, $color, $is_npc, $description]);
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
    $totem = (int)($_POST['totem']??0);
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
    $totem=(int)($_POST['totem']??0);
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

/* ----------------------- Estilos + UI ----------------------- */ ?>
<?php
$ADMIN_GROUPS_ENDPOINT = '/talim?s=admin_groups&ajax=1';
admin_panel_open('Grupos (Manadas y Clanes)');
?>

<div class="tabs">
  <a href="#" class="tablink active" data-tab="clans">Clanes</a>
  <a href="#" class="tablink" data-tab="groups">Manadas</a>
</div>

<div id="tab-clans" class="box">
  <h3>Clanes</h3>
  <div class="toolbar">
    <input id="filterClans" type="text" placeholder="Filtrar clanes...">
    <button class="btn" id="btnNewClan">Nuevo clan</button>
    <button class="btn" id="reloadClans">Recargar</button>
  </div>
  <div id="clansTableWrap"><?php render_clans_table($link); ?></div>
</div>

<div id="tab-groups" class="box" style="display:none;">
  <h3>Manadas</h3>
  <div class="toolbar">
    <input id="filterGroups" type="text" placeholder="Filtrar manadas...">
    <button class="btn" id="btnNewGroup">Nueva manada</button>
    <button class="btn" id="reloadGroups">Recargar</button>
  </div>
  <div id="groupsTableWrap"><?php render_groups_table($link); ?></div>
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
</script>
<script src="<?= e($adminHttpJs) ?>?v=<?= (int)$adminHttpJsVer ?>"></script>

<script>
const ADMIN_GROUPS_ENDPOINT = <?php echo json_encode($ADMIN_GROUPS_ENDPOINT); ?>;
const $ = (s,ctx=document)=>ctx.querySelector(s);
const $$ = (s,ctx=document)=>Array.from(ctx.querySelectorAll(s));

function filterTable(input, tbodySel){
  const q = (input.value||'').trim().toLowerCase();
  $$(tbodySel+" tr").forEach(tr=> tr.style.display = tr.textContent.toLowerCase().includes(q) ? "" : "none");
}
async function htmlPost(action, data={}){
  const fd = new FormData(); fd.append('action', action);
  if (window.ADMIN_CSRF_TOKEN) fd.append('csrf', window.ADMIN_CSRF_TOKEN);
  Object.entries(data).forEach(([k,v])=>fd.append(k,v));
  const r = await fetch(ADMIN_GROUPS_ENDPOINT,{
    method:'POST',
    headers: {'X-Requested-With':'XMLHttpRequest'},
    credentials: 'same-origin',
    body: fd
  });
  return r.text();
}

/* ----- Modal helpers ----- */
function openModal(html){
  const modal = $('#agModal');
  const content = $('#modalContent');
  content.innerHTML = html;
  modal.style.display = 'flex';
  const closeBtn = $('.modal-close', content);
  if (closeBtn) closeBtn.onclick = closeModal;
  bindModalInside(); // enlaza eventos internos según contenido
}
function closeModal(){
  const modal = $('#agModal');
  const content = $('#modalContent');
  modal.style.display = 'none';
  content.innerHTML = '';
}

/* ----- Tabs ----- */
$$('.tablink').forEach(b=>{
  b.addEventListener('click', (ev)=>{
    ev.preventDefault();
    $$('.tablink').forEach(x=>x.classList.remove('active'));
    b.classList.add('active');
    const tab = b.dataset.tab;
    const clans = $('#tab-clans');
    const groups = $('#tab-groups');
    if (clans) clans.style.display = (tab==='clans') ? '' : 'none';
    if (groups) groups.style.display = (tab==='groups') ? '' : 'none';
  });
});

/* ----- Filtros ----- */
$('#filterClans').addEventListener('input', ()=> filterTable($('#filterClans'), '#clansTable tbody'));
$('#filterGroups').addEventListener('input', ()=> filterTable($('#filterGroups'), '#groupsTable tbody'));

/* ----- Recargas ----- */
async function reloadClans(){
  $('#clansTableWrap').innerHTML = await htmlPost('load_clans_table');
  bindRowButtons();
  $('#filterClans').dispatchEvent(new Event('input'));
}
async function reloadGroups(){
  $('#groupsTableWrap').innerHTML = await htmlPost('load_groups_table');
  bindRowButtons();
  $('#filterGroups').dispatchEvent(new Event('input'));
}
$('#reloadClans').onclick = reloadClans;
$('#reloadGroups').onclick = reloadGroups;

/* ----- Botones nuevo ----- */
$('#btnNewClan').onclick = async ()=>{
  openModal(await htmlPost('clan_create_form'));
};
$('#btnNewGroup').onclick = async ()=>{
  openModal(await htmlPost('group_create_form'));
};

/* ----- Vincular botones de filas (Editar) ----- */
function bindRowButtons(){
  $$('#clansTableWrap .btn-edit-clan').forEach(btn=>{
    btn.onclick = async ()=> openModal(await htmlPost('clan_modal',{organization_id:btn.dataset.id}));
  });
  $$('#groupsTableWrap .btn-edit-group').forEach(btn=>{
    btn.onclick = async ()=> openModal(await htmlPost('group_modal',{group_id:btn.dataset.id}));
  });
}
bindRowButtons();

/* ----- Enlazar lo que haya dentro del modal en cada carga ----- */
function bindModalInside(){
  const root = $('#modalContent');

  // — Crear clan
  const btnCreateClan = $('#btnCreateClan', root);
  if(btnCreateClan){
    btnCreateClan.onclick = async ()=>{
      const name = ($('#newClanName', root).value||'').trim();
      const sort_order = ($('#newClanSortOrder', root).value||'0').trim();
      const color = ($('#newClanColor', root).value||'#ffffff').trim();
      const is_npc = ($('#newClanIsNpc', root).value||'0').trim();
      const description = ($('#newClanDescription', root).value||'');
      openModal(await htmlPost('clan_create',{name,sort_order,color,is_npc,description}));
      reloadClans();
    };
  }

  // — Crear manada
  const btnCreateGroup = $('#btnCreateGroup', root);
  if(btnCreateGroup){
    btnCreateGroup.onclick = async ()=>{
      const name = ($('#newGroupName', root).value||'').trim();
      const cronica = ($('#newGroupCronica', root).value||'1').trim();
      const activa = $('#newGroupActiva', root).checked ? 1 : 0;
      const organization_id = ($('#newGroupClan', root).value||'0').trim();
      const totem = ($("#newGroupTotem", root).value||'0').trim();
      const description = ($("#newGroupDescription", root).value||'');
      openModal(await htmlPost('group_create',{name,cronica,activa,organization_id,totem,description}));
      reloadGroups();
      reloadClans(); // por si asignó al clan
    };
  }

  // — Desde modal de clan: guardar nombre, abrir crear manada, gestionar packs
  const btnClanSave = $('#btnClanSave', root);
  if(btnClanSave){
    btnClanSave.onclick = async ()=>{
      const organization_id = btnClanSave.dataset.id;
      const name = ($('#clanName', root).value||'').trim();
      const totem = ($('#clanTotem', root).value||'0').trim();
      const color = ($('#clanColor', root).value||'#ffffff').trim();
      const is_npc = ($('#clanIsNpc', root).value||'0').trim();
      const description = ($('#clanDescription', root).value||'');
      openModal(await htmlPost('clan_update_basic',{organization_id,name,totem,color,is_npc,description}));
      reloadClans();
    };
  }
  const btnOpenGroupCreate = $('#btnOpenGroupCreate', root);
  if(btnOpenGroupCreate){
    btnOpenGroupCreate.onclick = async ()=>{
      const organization_id = btnOpenGroupCreate.dataset.clan;
      openModal(await htmlPost('group_create_form',{organization_id}));
    };
  }
  // packs dentro del modal de clan
  const detailClan = $('#clanModalDetail', root);
  if(detailClan){
    const rebindClanDetail = ()=>{
      // activar
      $$('.btn-pack-activate', detailClan).forEach(b=>{
        b.onclick = async ()=>{
          const gid = b.dataset.gid, clan = b.dataset.clan;
          detailClan.innerHTML = await htmlPost('clan_add_group',{organization_id:clan, group_id:gid});
          rebindClanDetail();
          reloadClans();
        };
      });
      // desactivar
      $$('.btn-pack-deactivate', detailClan).forEach(b=>{
        b.onclick = async ()=>{
          const gid = b.dataset.gid, clan = b.dataset.clan;
          detailClan.innerHTML = await htmlPost('clan_remove_group',{organization_id:clan, group_id:gid});
          rebindClanDetail();
          reloadClans();
        };
      });
      // añadir desde select
      const btnAddPack = $('#btnAddPack', detailClan);
      if(btnAddPack){
        btnAddPack.onclick = async ()=>{
          const clan = btnAddPack.dataset.clan;
          const sel = $('#packsAvailable', detailClan);
          if(sel && sel.value){
            detailClan.innerHTML = await htmlPost('clan_add_group',{organization_id:clan, group_id:sel.value});
            rebindClanDetail();
            reloadClans();
          }
        };
      }
    };
    rebindClanDetail();
  }

  // — Modal de manada: guardar básicos (nombre/activa/cronica)
  const btnSaveGroupBasic = $('#btnSaveGroupBasic', root);
  if(btnSaveGroupBasic){
    btnSaveGroupBasic.onclick = async ()=>{
      const group_id = btnSaveGroupBasic.dataset.id;
      const name = ($('#groupName', root).value||'').trim();
      const activa = $('#groupActiva', root).checked ? 1 : 0;
      const cronica = ($('#groupCronica', root).value||'1').trim();
      const totem = ($("#groupTotem", root).value||'0').trim();
      const description = ($("#groupDescription", root).value||'');
      openModal(await htmlPost('group_update_basic',{group_id,name,activa,cronica,totem,description}));
      reloadGroups();
      reloadClans();
    };
  }

  // — Miembros dentro del modal de manada
  const groupDetail = $('#groupModalDetail', root);
  if(groupDetail){
    const rebindGroupDetail = ()=>{
      // búsqueda
      const inSearch = $('#searchChar', groupDetail);
      const results = $('#searchResults', root); // fuera del detalle pero dentro del modal
      if(inSearch){
        let t=null;
        inSearch.oninput = ()=>{
          clearTimeout(t);
          const q = inSearch.value.trim();
          if(!q){
            results.classList.add('adm-hidden');
            results.innerHTML='';
            return;
          }
          t=setTimeout(async ()=>{
            results.innerHTML = await htmlPost('search_characters',{q});
            results.classList.remove('adm-hidden');
            $$('.btn-pick-char', results).forEach(b=>{
              b.onclick = ()=>{
                inSearch.value = b.parentElement.firstElementChild.textContent.trim();
                inSearch.dataset.charId = b.dataset.id;
                results.classList.add('adm-hidden');
              };
            });
          },300);
        };
      }
      // añadir miembro
      const btnAdd = $('#btnAddMember', groupDetail);
      if(btnAdd){
        btnAdd.onclick = async ()=>{
          const gid = btnAdd.dataset.group;
          const pos = ($('#newPosition', groupDetail).value||'').trim();
          const cid = ($('#searchChar', groupDetail).dataset.charId||'').trim();
          if(!cid){ alert('Selecciona un personaje de la búsqueda.'); return; }
          groupDetail.innerHTML = await htmlPost('group_add_member',{group_id:gid, character_id:cid, position:pos});
          rebindGroupDetail();
        };
      }
      // guardar posición
      $$('.btn-save-position', groupDetail).forEach(b=>{
        b.onclick = async ()=>{
          const gid = b.dataset.group, cid = b.dataset.id;
          const chip = b.closest('.chip'); const pos = chip.querySelector('input').value.trim();
          groupDetail.innerHTML = await htmlPost('group_save_position',{group_id:gid, character_id:cid, position:pos});
          rebindGroupDetail();
        };
      });
      // quitar miembro
      $$('.btn-rem-member', groupDetail).forEach(b=>{
        b.onclick = async ()=>{
          const gid = b.dataset.group, cid = b.dataset.id;
          groupDetail.innerHTML = await htmlPost('group_remove_member',{group_id:gid, character_id:cid});
          rebindGroupDetail();
        };
      });
      // reactivar miembro
      $$('.btn-activate-member', groupDetail).forEach(b=>{
        b.onclick = async ()=>{
          const gid = b.dataset.group, cid = b.dataset.id;
          const chip = b.closest('.chip'); const pos = chip.querySelector('input').value.trim();
          groupDetail.innerHTML = await htmlPost('group_add_member',{group_id:gid, character_id:cid, position:pos});
          rebindGroupDetail();
        };
      });
    };
    rebindGroupDetail();
  }
}

/* Bind inicial de filas tras carga */
bindRowButtons();

// Cerrar modal al pulsar fuera / ESC (como en admin_relations)
const agDialog = $('#agModal .modal');
if (agDialog) {
  agDialog.addEventListener('click', function(e){ e.stopPropagation(); });
}
$('#agModal').addEventListener('click', function(e){
  if (e.target === this) closeModal();
});
document.addEventListener('keydown', function(e){
  if (e.key === 'Escape') closeModal();
});
</script>
<?php admin_panel_close(); ?>
