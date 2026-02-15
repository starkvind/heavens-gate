<?php
/**
 * admin_groups.php — Modales + creación/renombrado + HTML server-side
 *
 * Requisitos:
 * - $link: conexión mysqli abierta (body_work.php)
 * - Tablas: dim_organizations(id,name,...) | dim_groups(id,name,activa,cronica,clan,totem,`desc`)
 * - Puentes: bridge_organizations_groups(id,clan_id,group_id,is_active)
 *            bridge_characters_groups(id,character_id,group_id,is_active,position)
 * - fact_characters(id,nombre,alias,nombregarou)
 */

if (!isset($link) || !$link) {
  echo "<div style='color:#f88'>Error: conexión DB no disponible.</div>";
  return;
include_once(__DIR__ . '/../../helpers/pretty.php');
}

/* ----------------------- helpers ----------------------- */
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function q($link,$sql,$types='',$params=[]){
  $st = mysqli_prepare($link,$sql);
  if(!$st){ return [false,mysqli_error($link),null]; }
  if($types!==''){ mysqli_stmt_bind_param($st,$types,...$params); }
  if(!mysqli_stmt_execute($st)){ $err=mysqli_stmt_error($st); mysqli_stmt_close($st); return [false,$err,null]; }
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
          (SELECT COUNT(*) FROM bridge_organizations_groups b WHERE b.clan_id=c.id AND b.is_active=1) AS groups_active
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
  $sql = "SELECT m.id, m.name, m.activa FROM dim_groups m ORDER BY m.name ASC";
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
function render_clan_detail($link,$clan_id){
  $clan_id = (int)$clan_id;

  $sqlL = "SELECT m.id, m.name, b.is_active
           FROM bridge_organizations_groups b
           INNER JOIN dim_groups m ON m.id=b.group_id
           WHERE b.clan_id=?
           ORDER BY m.name ASC";
  [$ok1,$err1,$rs1] = q($link,$sqlL,'i',[$clan_id]);
  if(!$ok1){ echo "<div class='err'>".e($err1)."</div>"; return; }

  $linked=[]; $ids=[];
  while($r=mysqli_fetch_assoc($rs1)){ $linked[]=$r; $ids[]=(int)$r['id']; }

  if(count($ids)){
    $in = implode(',', array_map('intval',$ids));
    $sqlA = "SELECT id,name FROM dim_groups WHERE id NOT IN ($in) ORDER BY name ASC";
    [$ok2,$err2,$rs2] = q($link,$sqlA);
  } else {
    $sqlA = "SELECT id,name FROM dim_groups ORDER BY name ASC";
    [$ok2,$err2,$rs2] = q($link,$sqlA);
  }
  if(!$ok2){ echo "<div class='err'>".e($err2)."</div>"; return; }

  $avail=[]; while($r=mysqli_fetch_assoc($rs2)){ $avail[]=$r; }
  $active = array_values(array_filter($linked, fn($x)=>(int)$x['is_active']===1));
  $inactive = array_values(array_filter($linked, fn($x)=>(int)$x['is_active']!==1));

  echo "<div class='split'>
          <div>
            <h4>Manadas activas <span class='count'>".count($active)."</span></h4>
            <div class='grid' id='packsActive'>";
  foreach($active as $p){
    echo "<div class='card'>
            <h4><span>".e($p['name'])."</span>
                <span>
                  <button class='btn btn-pack-deactivate' data-gid='".e($p['id'])."' data-clan='$clan_id'>Quitar</button>
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
            <select id='packsAvailable' style='flex:1; padding:8px; border-radius:8px; background:#0c1b40; border:1px solid var(--border); color:var(--text)'>";
  foreach($avail as $p){ echo "<option value='".e($p['id'])."'>".e($p['name'])."</option>"; }
  echo     "</select>
            <button class='btn btn-ok' id='btnAddPack' data-clan='$clan_id' ".(empty($avail)?'disabled':'').">Añadir</button>
          </div>
          <div class='hr'></div>
          <h4>Manadas inactivas</h4>
          <div class='grid' id='packsInactive'>";
  foreach($inactive as $p){
    echo "<div class='card'>
            <h4><span>".e($p['name'])."</span>
                <span>
                  <button class='btn btn-pack-activate' data-gid='".e($p['id'])."' data-clan='$clan_id'>Activar</button>
                  <a class='btn' href='/groups/".e($p['id'])."' target='_blank'>Ver</a>
                </span>
            </h4>
          </div>";
  }
  echo   "</div>
        </div>
      </div>";
}

/* --- fragmento: detalle manada (miembros) --- */
function render_group_detail($link,$group_id){
  $group_id = (int)$group_id;
  $sql = "SELECT p.id, p.nombre, p.alias, p.nombregarou, b.is_active, b.position
          FROM bridge_characters_groups b
          INNER JOIN fact_characters p ON p.id=b.character_id
          WHERE b.group_id=?
          ORDER BY p.nombre ASC";
  [$ok,$err,$rs] = q($link,$sql,'i',[$group_id]);
  if(!$ok){ echo "<div class='err'>".e($err)."</div>"; return; }

  $a=[];$i=[];
  while($r=mysqli_fetch_assoc($rs)){ ((int)$r['is_active']===1) ? $a[]=$r : $i[]=$r; }

  echo "<div class='toolbar'>
          <input id='searchChar' type='text' placeholder='Buscar personaje para añadir...'>
          <input id='newPosition' type='text' placeholder='Posición (opcional)'>
          <button class='btn btn-ok' id='btnAddMember' data-group='$group_id'>Añadir a la manada</button>
        </div>
        <div id='searchResults' class='grid' style='display:none'></div>

        <div class='card' style='margin-top:8px'>
          <h4>Miembros activos <span class='count'>".count($a)."</span></h4>
          <div id='membersActive' class='chips'>";
  foreach($a as $m){
    $label = $m['nombre'].( $m['alias'] ? " ({$m['alias']})" : "" );
    echo "<span class='chip' data-id='".e($m['id'])."'>
            <span>".e($label)."</span>
            <input type='text' value='".e($m['position'])."' placeholder='posición'>
            <button class='btn btn-save-position' data-id='".e($m['id'])."' data-group='$group_id'>💾</button>
            <button class='btn btn-bad btn-rem-member' data-id='".e($m['id'])."' data-group='$group_id'>✖</button>
          </span>";
  }
  echo   "</div></div>

        <div class='card' style='margin-top:8px'>
          <h4>Miembros inactivos</h4>
          <div id='membersInactive' class='chips'>";
  foreach($i as $m){
    $label = $m['nombre'].( $m['alias'] ? " ({$m['alias']})" : "" );
    echo "<span class='chip off' data-id='".e($m['id'])."'>
            <span>".e($label)."</span>
            <input type='text' value='".e($m['position'])."' placeholder='posición'>
            <button class='btn btn-ok btn-activate-member' data-id='".e($m['id'])."' data-group='$group_id'>➕</button>
          </span>";
  }
  echo   "</div></div>";
}

/* --- MODALES --- */
function render_clan_modal($link,$clan_id){
  $clan_id = (int)$clan_id;
  [$ok,$err,$rs] = q($link,"SELECT id,name,totem FROM dim_organizations WHERE id=? LIMIT 1",'i',[$clan_id]);
  if(!$ok || !$rs || !($clan=mysqli_fetch_assoc($rs))){
    echo "<div class='err'>Clan no encontrado.</div>"; return;
  }
  $totems = get_totems($link);
  $totemSel = (int)($clan['totem'] ?? 0);
  echo "<div class='modal-header'>
          <h3>Editar clan</h3>
          <button class='modal-close' aria-label='Cerrar'>&times;</button>
        </div>
        <div class='modal-body'>
          <div class='card'>
            <h4>Nombre del clan</h4>
            <div class='toolbar'>
              <input id='clanName' type='text' value='".e($clan['name'])."'>
              <select id='clanTotem' style='max-width:240px; padding:8px; border-radius:8px; background:#0c1b40; border:1px solid var(--border); color:var(--text)'>";
  foreach($totems as $tid=>$tname){
    echo "<option value='".e($tid)."' ".($tid===$totemSel?'selected':'').">".e($tname)."</option>";
  }
  echo      "</select>
              <button class='btn btn-ok' id='btnClanSave' data-id='".e($clan['id'])."'>Guardar</button>
              <button class='btn' id='btnOpenGroupCreate' data-clan='".e($clan['id'])."'>Nueva manada</button>
            </div>
          </div>
          <div class='hr'></div>
          <div id='clanModalDetail'>";
  render_clan_detail($link,$clan_id);
  echo   "</div>
        </div>";
}

function render_group_modal($link,$group_id){
  $group_id = (int)$group_id;
  [$ok,$err,$rs] = q($link,"SELECT id,name,activa,IFNULL(cronica,1) AS cronica, totem FROM dim_groups WHERE id=? LIMIT 1",'i',[$group_id]);
  if(!$ok || !$rs || !($g=mysqli_fetch_assoc($rs))){
    echo "<div class='err'>Manada no encontrada.</div>"; return;
  }
  $totems = get_totems($link);
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
              <input id='groupCronica' type='number' min='1' step='1' value='".e($g['cronica'])."' style='max-width:120px' title='Crónica'>
              <select id='groupTotem' style='max-width:240px; padding:8px; border-radius:8px; background:#0c1b40; border:1px solid var(--border); color:var(--text)'>";
  foreach($totems as $tid=>$tname){
    echo "<option value='".e($tid)."' ".($tid===$totemSel?'selected':'').">".e($tname)."</option>";
  }
  echo      "</select>
              <label style='display:flex;align-items:center;gap:6px'>
                <input id='groupActiva' type='checkbox' ".((int)$g['activa']===1?'checked':'')."> Activa
              </label>
              <button class='btn btn-ok' id='btnSaveGroupBasic' data-id='".e($g['id'])."'>Guardar</button>
              <a class='btn' href='/groups/".e($g['id'])."' target='_blank'>Ver página</a>
            </div>
          </div>
          <div class='hr'></div>
          <h4>Miembros</h4>
          <div id='groupModalDetail'>";
  render_group_detail($link,$group_id);
  echo   "</div>
        </div>";
}

function render_clan_create_form(){
  echo "<div class='modal-header'>
          <h3>Nuevo clan</h3>
          <button class='modal-close' aria-label='Cerrar'>&times;</button>
        </div>
        <div class='modal-body'>
          <div class='toolbar'>
            <input id='newClanName' type='text' placeholder='Nombre del clan'>
            <button class='btn btn-ok' id='btnCreateClan'>Crear</button>
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
                <input id='newGroupCronica' type='number' min='1' step='1' value='1' style='max-width:120px' title='Crónica'>
                <select id='newGroupTotem' style='max-width:240px; padding:8px; border-radius:8px; background:#0c1b40; border:1px solid var(--border); color:var(--text)'>";
  foreach($totems as $tid=>$tname){
    echo "<option value='".e($tid)."'>".e($tname)."</option>";
  }
  echo      "</select>
                <label style='display:flex;align-items:center;gap:6px'>
                  <input id='newGroupActiva' type='checkbox' checked> Activa
                </label>
              </div>
            </div>
            <div class='card'>
              <h4>Asignación inicial</h4>
              <div class='toolbar'>
                <select id='newGroupClan' style='flex:1; padding:8px; border-radius:8px; background:#0c1b40; border:1px solid var(--border); color:var(--text)'>
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

  // tablas básicas
  if($act==='load_clans_table'){ render_clans_table($link); exit; }
  if($act==='load_groups_table'){ render_groups_table($link); exit; }

  // modales abrir
  if($act==='clan_modal'){ $id=(int)($_POST['clan_id']??0); render_clan_modal($link,$id); exit; }
  if($act==='group_modal'){ $id=(int)($_POST['group_id']??0); render_group_modal($link,$id); exit; }
  if($act==='clan_create_form'){ render_clan_create_form(); exit; }
  if($act==='group_create_form'){ $cid=(int)($_POST['clan_id']??0); render_group_create_form($link,$cid); exit; }

  // clan update basic (name + totem)
  if($act==='clan_update_basic'){
    $id=(int)($_POST['clan_id']??0);
    $name=trim((string)($_POST['name']??''));
    $totem=(int)($_POST['totem']??0);
    if($id>0 && $name!==''){ q($link,"UPDATE dim_organizations SET name=?, totem=? WHERE id=?",'sii',[$name,$totem,$id]); }
    hg_update_pretty_id_if_exists($link, 'dim_organizations', $id, $name);
    render_clan_modal($link,$id); exit;
  }

  // crear clan
  if($act==='clan_create'){
    $name=trim((string)($_POST['name']??''));
    if($name===''){ render_clan_create_form(); echo "<div class='err'>Indica un nombre.</div>"; exit; }
    // Insert básico: si tu tabla exige más campos NOT NULL sin default, añade aquí columnas con valores por defecto.
    [$ok,$err,$rs,$newId] = q($link,"INSERT INTO dim_organizations (name) VALUES (?)",'s',[$name]);
    hg_update_pretty_id_if_exists($link, 'dim_organizations', $newId, $name);
    if(!$ok){ render_clan_create_form(); echo "<div class='err'>".e($err)."</div>"; exit; }
    render_clan_modal($link,$newId); exit;
  }

  // grupo: guardar básicos (rename, activa, crónica)
  if($act==='group_update_basic'){
    $id=(int)($_POST['group_id']??0);
    $name=trim((string)($_POST['name']??''));
    $activa = (int)($_POST['activa']??0)===1?1:0;
    $cronica = (int)($_POST['cronica']??1); if($cronica<1){ $cronica=1; }
    $totem = (int)($_POST['totem']??0);
    if($id>0 && $name!==''){
      q($link,"UPDATE dim_groups SET name=?, activa=?, cronica=?, totem=? WHERE id=?",'siiii',[$name,$activa,$cronica,$totem,$id]);
      hg_update_pretty_id_if_exists($link, 'dim_groups', $id, $name);
    }
    render_group_modal($link,$id); exit;
  }

  // crear grupo
  if($act==='group_create'){
    $name=trim((string)($_POST['name']??''));
    $cronica=(int)($_POST['cronica']??1); if($cronica<1){ $cronica=1; }
    $activa=(int)($_POST['activa']??1)===1?1:0;
    $clan_id=(int)($_POST['clan_id']??0);
    $totem=(int)($_POST['totem']??0);
    if($name===''){ render_group_create_form($link,$clan_id); echo "<div class='err'>Indica un nombre.</div>"; exit; }

    // dim_groups requiere varias columnas NOT NULL; ponemos defaults seguros
    [$ok,$err,$rs,$newId] = q($link,
      "INSERT INTO dim_groups (name, cronica, clan, totem, activa, `desc`) VALUES (?,?,?,?,?,?)",
      'sisiis', [$name, $cronica, /*clan(texto)*/'', $totem, $activa, /*desc*/'']);
    hg_update_pretty_id_if_exists($link, 'dim_groups', $newId, $name);
    if(!$ok){ render_group_create_form($link,$clan_id); echo "<div class='err'>".e($err)."</div>"; exit; }

    // Bridge (opcional) si seleccionó clan_id
    if($clan_id>0){
      // activar si existe, si no crear
      [$ok0,$err0,$rs0] = q($link,"SELECT id FROM bridge_organizations_groups WHERE clan_id=? AND group_id=? LIMIT 1",'ii',[$clan_id,$newId]);
      if($ok0 && $rs0 && ($row=mysqli_fetch_assoc($rs0))){
        q($link,"UPDATE bridge_organizations_groups SET is_active=1 WHERE id=?",'i',[(int)$row['id']]);
      } else {
        q($link,"INSERT INTO bridge_organizations_groups (clan_id,group_id,is_active) VALUES (?,?,1)",'ii',[$clan_id,$newId]);
      }
    }
    render_group_modal($link,$newId); exit;
  }

  // clan detalle (packs dentro del modal) — mismas acciones que antes
  if($act==='clan_add_group'){
    $clan_id=(int)($_POST['clan_id']??0);
    $group_id=(int)($_POST['group_id']??0);
    if($clan_id>0 && $group_id>0){
      [$ok,$err,$rs] = q($link,"SELECT id FROM bridge_organizations_groups WHERE clan_id=? AND group_id=? LIMIT 1",'ii',[$clan_id,$group_id]);
      if($ok && $rs && ($row=mysqli_fetch_assoc($rs))){
        q($link,"UPDATE bridge_organizations_groups SET is_active=1 WHERE id=?",'i',[(int)$row['id']]);
      } else {
        q($link,"INSERT INTO bridge_organizations_groups (clan_id,group_id,is_active) VALUES (?,?,1)",'ii',[$clan_id,$group_id]);
      }
    }
    render_clan_detail($link,$clan_id); exit;
  }
  if($act==='clan_remove_group'){
    $clan_id=(int)($_POST['clan_id']??0);
    $group_id=(int)($_POST['group_id']??0);
    if($clan_id>0 && $group_id>0){
      q($link,"UPDATE bridge_organizations_groups SET is_active=0 WHERE clan_id=? AND group_id=?",'ii',[$clan_id,$group_id]);
    }
    render_clan_detail($link,$clan_id); exit;
  }

  // group detalle (miembros dentro del modal)
  if($act==='group_add_member'){
    $group_id=(int)($_POST['group_id']??0);
    $character_id=(int)($_POST['character_id']??0);
    $position=trim((string)($_POST['position']??''));
    if($group_id>0 && $character_id>0){
      [$ok,$err,$rs] = q($link,"SELECT id FROM bridge_characters_groups WHERE group_id=? AND character_id=? LIMIT 1",'ii',[$group_id,$character_id]);
      if($ok && $rs && ($row=mysqli_fetch_assoc($rs))){
        q($link,"UPDATE bridge_characters_groups SET is_active=1, position=? WHERE id=?",'si',[$position,(int)$row['id']]);
      } else {
        q($link,"INSERT INTO bridge_characters_groups (character_id,group_id,is_active,position) VALUES (?,?,1,?)",'iis',[$character_id,$group_id,$position]);
      }
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
    [$ok,$err,$rs] = q($link,"SELECT id,nombre,alias,nombregarou
                              FROM fact_characters
                              WHERE nombre LIKE ? OR alias LIKE ? OR nombregarou LIKE ?
                              ORDER BY nombre ASC LIMIT 30",'sss',[$like,$like,$like]);
    if(!$ok){ echo "<div class='err'>".e($err)."</div>"; exit; }
    echo "<div class='grid'>";
    while($r=mysqli_fetch_assoc($rs)){
      $lab = $r['nombre'].( $r['alias'] ? " ({$r['alias']})" : "" );
      echo "<div class='card'>
              <div style='display:flex;justify-content:space-between;gap:8px;align-items:center'>
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
<style>
:root{ --panel:#0f1f49; --border:#27408b; --text:#e9f1ff; --muted:#a9b6db; }
.box{background:var(--panel);border:1px solid var(--border);border-radius:10px;padding:12px;margin-top:8px}
.toolbar{display:flex;gap:8px;align-items:center;margin-bottom:8px}
.toolbar input[type="text"], .toolbar input[type="number"], .toolbar select{
  flex:1; padding:8px;border-radius:8px;border:1px solid var(--border);background:#0c1b40;color:var(--text)
}
.table{width:100%;border-collapse:separate;border-spacing:0 6px}
.table th,.table td{padding:6px 8px;text-align:left}
.row{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08);border-radius:8px}
.btn{cursor:pointer;border:1px solid var(--border);background:#0c1b40;color:var(--text);padding:6px 10px;border-radius:8px}
.btn:hover{filter:brightness(1.1)} .btn-ok{border-color:#2b8a5a} .btn-bad{border-color:#8a2b2b}
.split{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:8px}
.card{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.06);border-radius:10px;padding:8px}
.chips{display:flex;flex-wrap:wrap;gap:6px}
.chip{display:inline-flex;gap:6px;align-items:center;border-radius:999px;background:#1b2f63;padding:4px 8px}
.chip.off{opacity:.7;text-decoration:line-through}
.hr{height:1px;background:rgba(255,255,255,.08);margin:8px 0}
.small{font-size:.85rem;color:var(--muted)}
.tabbar{display:flex;gap:8px;margin:4px 0;}
.tabbar .tab{padding:6px 10px;border:1px solid var(--border);border-radius:8px;background:#0c1b40;cursor:pointer;color:white;}
.tabbar .tab.active{background:#15306b}
.count{color:var(--muted);font-weight:600}
.err{color:#ff9a9a}

/* Modal */
.modal.hidden{display:none}
.modal{position:fixed;inset:0;z-index:9999}
.modal-backdrop{position:absolute;inset:0;background:rgba(0,0,0,.5)}
.modal-dialog{position:absolute;inset:auto;top:5%;left:50%;transform:translateX(-50%);
  width:min(100%, 960px); max-height:90%; overflow:auto; background:var(--panel);
  border:1px solid var(--border); border-radius:12px; box-shadow:0 20px 80px rgba(0,0,0,.5)}
.modal-header{display:flex;justify-content:space-between;align-items:center;padding:12px 12px 0 12px}
.modal-header h3{margin:0}
.modal-close{background:transparent;border:0;color:var(--text);font-size:24px;cursor:pointer}
.modal-body{padding:12px}
</style>

<?php $ADMIN_GROUPS_ENDPOINT = $_SERVER['REQUEST_URI']; ?>

<div class="tabbar">
  <button class="tab active" data-tab="clans">Clanes</button>
  <button class="tab" data-tab="groups">Manadas</button>
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

<div id="tab-groups" class="box" style="display:none">
  <h3>Manadas</h3>
  <div class="toolbar">
    <input id="filterGroups" type="text" placeholder="Filtrar manadas...">
    <button class="btn" id="btnNewGroup">Nueva manada</button>
    <button class="btn" id="reloadGroups">Recargar</button>
  </div>
  <div id="groupsTableWrap"><?php render_groups_table($link); ?></div>
</div>

<!-- Modal global -->
<div id="agModal" class="modal hidden">
  <div class="modal-backdrop"></div>
  <div class="modal-dialog">
    <div id="modalContent"></div>
  </div>
</div>

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
  Object.entries(data).forEach(([k,v])=>fd.append(k,v));
  const r = await fetch(ADMIN_GROUPS_ENDPOINT,{method:'POST', body: fd});
  return r.text();
}

/* ----- Modal helpers ----- */
function openModal(html){
  $('#modalContent').innerHTML = html;
  $('#agModal').classList.remove('hidden');
  const closeBtn = $('.modal-close', $('#modalContent'));
  $('.modal-backdrop').onclick = closeModal;
  if(closeBtn) closeBtn.onclick = closeModal;
  bindModalInside(); // enlaza eventos internos según contenido
}
function closeModal(){
  $('#agModal').classList.add('hidden');
  $('#modalContent').innerHTML = '';
}

/* ----- Tabs ----- */
$$('.tab').forEach(b=>{
  b.addEventListener('click', ()=>{
    $$('.tab').forEach(x=>x.classList.remove('active'));
    b.classList.add('active');
    const tab = b.dataset.tab;
    $('#tab-clans').style.display = tab==='clans' ? '' : 'none';
    $('#tab-groups').style.display = tab==='groups' ? '' : 'none';
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
    btn.onclick = async ()=> openModal(await htmlPost('clan_modal',{clan_id:btn.dataset.id}));
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
      openModal(await htmlPost('clan_create',{name}));
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
      const clan_id = ($('#newGroupClan', root).value||'0').trim();
      const totem = ($('#newGroupTotem', root).value||'0').trim();
      openModal(await htmlPost('group_create',{name,cronica,activa,clan_id,totem}));
      reloadGroups();
      reloadClans(); // por si asignó al clan
    };
  }

  // — Desde modal de clan: guardar nombre, abrir crear manada, gestionar packs
  const btnClanSave = $('#btnClanSave', root);
  if(btnClanSave){
    btnClanSave.onclick = async ()=>{
      const clan_id = btnClanSave.dataset.id;
      const name = ($('#clanName', root).value||'').trim();
      const totem = ($('#clanTotem', root).value||'0').trim();
      openModal(await htmlPost('clan_update_basic',{clan_id,name,totem}));
      reloadClans();
    };
  }
  const btnOpenGroupCreate = $('#btnOpenGroupCreate', root);
  if(btnOpenGroupCreate){
    btnOpenGroupCreate.onclick = async ()=>{
      const clan_id = btnOpenGroupCreate.dataset.clan;
      openModal(await htmlPost('group_create_form',{clan_id}));
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
          detailClan.innerHTML = await htmlPost('clan_add_group',{clan_id:clan, group_id:gid});
          rebindClanDetail();
          reloadClans();
        };
      });
      // desactivar
      $$('.btn-pack-deactivate', detailClan).forEach(b=>{
        b.onclick = async ()=>{
          const gid = b.dataset.gid, clan = b.dataset.clan;
          detailClan.innerHTML = await htmlPost('clan_remove_group',{clan_id:clan, group_id:gid});
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
            detailClan.innerHTML = await htmlPost('clan_add_group',{clan_id:clan, group_id:sel.value});
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
      const totem = ($('#groupTotem', root).value||'0').trim();
      openModal(await htmlPost('group_update_basic',{group_id,name,activa,cronica,totem}));
      reloadGroups();
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
          if(!q){ results.style.display='none'; results.innerHTML=''; return; }
          t=setTimeout(async ()=>{
            results.innerHTML = await htmlPost('search_characters',{q});
            results.style.display='';
            $$('.btn-pick-char', results).forEach(b=>{
              b.onclick = ()=>{ inSearch.value = b.parentElement.firstElementChild.textContent.trim(); inSearch.dataset.charId = b.dataset.id; results.style.display='none'; };
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
</script>
