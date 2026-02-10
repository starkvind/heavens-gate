<?php
// admin_bridges.php — Panel para ver/editar Bridges
// - PJ -> Manada (bridge_characters_groups)
// - PJ -> Clan   (bridge_characters_organizations)
// - Clan -> Manadas (bridge_organizations_groups)
//
// Requisitos:
// - Debe existir $link (mysqli) ya conectado
// - Opcional: $excludeChronicles (CSV ints) para filtrar fact_characters.cronica

if (!isset($link) || !$link) { die("Error de conexión a la base de datos."); }
if (method_exists($link, 'set_charset')) $link->set_charset('utf8mb4'); else mysqli_set_charset($link,'utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function sanitize_int_csv($csv){
  $csv = (string)$csv;
  if (trim($csv) === '') return '';
  $parts = preg_split('/\s*,\s*/', trim($csv));
  $ints = [];
  foreach($parts as $p){
    if ($p==='' ) continue;
    if (preg_match('/^\d+$/',$p)) $ints[] = (string)(int)$p;
  }
  $ints = array_values(array_unique($ints));
  return implode(',',$ints);
}

function fetchPairs(mysqli $link, string $sql): array {
  $out = [];
  if ($rs = $link->query($sql)) {
    while($r = $rs->fetch_assoc()){
      $out[(int)$r['id']] = (string)$r['name'];
    }
    $rs->close();
  }
  return $out;
}

// ============================
// Config / filtros
// ============================
$excludeChronicles = isset($excludeChronicles) ? sanitize_int_csv($excludeChronicles) : '';
$cronicaNotInSQL   = ($excludeChronicles !== '') ? " AND p.cronica NOT IN ($excludeChronicles) " : "";

$tab = $_GET['tab'] ?? 'chars'; // chars | clans
$pageTitle2 = "Bridges — Relaciones";

// IMPORTANTE: si tu tabla es hg_character_clan_bridge (singular), cambia aquí:
$T_CHAR_GROUP = "bridge_characters_groups";
$T_CHAR_CLAN  = "bridge_characters_organizations";   // <-- cambia si procede
$T_CLAN_GROUP = "bridge_organizations_groups";

// ============================
// Helpers de negocio (upsert + activar)
// ============================

/**
 * Activa UNA relación PJ->Manada (group) y desactiva el resto para ese PJ.
 * Si $groupId <= 0: desactiva todas.
 */
function set_active_character_group(mysqli $link, string $T_CHAR_GROUP, int $charId, int $groupId): void {
  if ($charId <= 0) return;

  if ($groupId > 0) {
    // Upsert (requiere UNIQUE(character_id, group_id) para ser perfecto; si no, funciona igual, pero no evita duplicados antiguos)
    if ($st = $link->prepare("SELECT id FROM {$T_CHAR_GROUP} WHERE character_id=? AND group_id=? ORDER BY id DESC LIMIT 1")) {
      $st->bind_param("ii", $charId, $groupId);
      $st->execute();
      $rs = $st->get_result();
      $idRow = 0;
      if ($rs && ($row=$rs->fetch_assoc())) $idRow = (int)$row['id'];
      $st->close();

      if ($idRow > 0) {
        if ($u = $link->prepare("UPDATE {$T_CHAR_GROUP} SET is_active=1 WHERE id=?")) {
          $u->bind_param("i",$idRow); $u->execute(); $u->close();
        }
      } else {
        if ($ins = $link->prepare("INSERT INTO {$T_CHAR_GROUP} (character_id, group_id, is_active) VALUES (?,?,1)")) {
          $ins->bind_param("ii",$charId,$groupId); $ins->execute(); $ins->close();
        }
      }
    }

    // Desactiva otras manadas del PJ
    if ($off = $link->prepare("UPDATE {$T_CHAR_GROUP} SET is_active=0 WHERE character_id=? AND group_id<>?")) {
      $off->bind_param("ii",$charId,$groupId); $off->execute(); $off->close();
    }
  } else {
    if ($off = $link->prepare("UPDATE {$T_CHAR_GROUP} SET is_active=0 WHERE character_id=?")) {
      $off->bind_param("i",$charId); $off->execute(); $off->close();
    }
  }
}

/**
 * Activa UNA relación PJ->Clan y desactiva el resto para ese PJ.
 * Si $clanId <= 0: desactiva todas.
 */
function set_active_character_clan(mysqli $link, string $T_CHAR_CLAN, int $charId, int $clanId): void {
  if ($charId <= 0) return;

  if ($clanId > 0) {
    if ($st = $link->prepare("SELECT id FROM {$T_CHAR_CLAN} WHERE character_id=? AND clan_id=? ORDER BY id DESC LIMIT 1")) {
      $st->bind_param("ii",$charId,$clanId);
      $st->execute();
      $rs = $st->get_result();
      $idRow = 0;
      if ($rs && ($row=$rs->fetch_assoc())) $idRow = (int)$row['id'];
      $st->close();

      if ($idRow > 0) {
        if ($u = $link->prepare("UPDATE {$T_CHAR_CLAN} SET is_active=1 WHERE id=?")) {
          $u->bind_param("i",$idRow); $u->execute(); $u->close();
        }
      } else {
        if ($ins = $link->prepare("INSERT INTO {$T_CHAR_CLAN} (character_id, clan_id, is_active) VALUES (?,?,1)")) {
          $ins->bind_param("ii",$charId,$clanId); $ins->execute(); $ins->close();
        }
      }
    }
    if ($off = $link->prepare("UPDATE {$T_CHAR_CLAN} SET is_active=0 WHERE character_id=? AND clan_id<>?")) {
      $off->bind_param("ii",$charId,$clanId); $off->execute(); $off->close();
    }
  } else {
    if ($off = $link->prepare("UPDATE {$T_CHAR_CLAN} SET is_active=0 WHERE character_id=?")) {
      $off->bind_param("i",$charId); $off->execute(); $off->close();
    }
  }
}

/**
 * Dado un group_id (manada), intenta resolver su clan activo via bridge_organizations_groups.
 * Devuelve clan_id o 0 si no encuentra.
 */
function resolve_clan_for_group(mysqli $link, string $T_CLAN_GROUP, int $groupId): int {
  if ($groupId <= 0) return 0;
  $cid = 0;
  $sql = "SELECT clan_id FROM {$T_CLAN_GROUP} WHERE group_id=? AND (is_active=1 OR is_active IS NULL) ORDER BY id DESC LIMIT 1";
  if ($st = $link->prepare($sql)) {
    $st->bind_param("i",$groupId);
    $st->execute();
    $rs = $st->get_result();
    if ($rs && ($r=$rs->fetch_assoc())) $cid = (int)$r['clan_id'];
    $st->close();
  }
  return $cid;
}

/**
 * Activa/desactiva una relación Clan->Manada. Aquí NO imponemos "solo una" por clan (porque un clan tiene muchas manadas),
 * pero sí puedes imponer "una manada solo puede pertenecer a un clan activo" desactivando el resto por group_id.
 */
function set_clan_group(mysqli $link, string $T_CLAN_GROUP, int $clanId, int $groupId, int $isActive, bool $enforceOneActiveOwnerPerGroup = true): void {
  if ($clanId<=0 || $groupId<=0) return;
  $isActive = $isActive ? 1 : 0;

  // Si existe, update; si no, insert
  $idRow = 0;
  if ($st = $link->prepare("SELECT id FROM {$T_CLAN_GROUP} WHERE clan_id=? AND group_id=? ORDER BY id DESC LIMIT 1")) {
    $st->bind_param("ii",$clanId,$groupId);
    $st->execute();
    $rs = $st->get_result();
    if ($rs && ($r=$rs->fetch_assoc())) $idRow = (int)$r['id'];
    $st->close();
  }

  if ($idRow > 0) {
    if ($u = $link->prepare("UPDATE {$T_CLAN_GROUP} SET is_active=? WHERE id=?")) {
      $u->bind_param("ii",$isActive,$idRow); $u->execute(); $u->close();
    }
  } else {
    if ($ins = $link->prepare("INSERT INTO {$T_CLAN_GROUP} (clan_id, group_id, is_active) VALUES (?,?,?)")) {
      $ins->bind_param("iii",$clanId,$groupId,$isActive); $ins->execute(); $ins->close();
    }
  }

  // Si activas una (clanId, groupId) y quieres que la manada tenga SOLO un clan activo a la vez:
  if ($isActive===1 && $enforceOneActiveOwnerPerGroup) {
    if ($off = $link->prepare("UPDATE {$T_CLAN_GROUP} SET is_active=0 WHERE group_id=? AND clan_id<>?")) {
      $off->bind_param("ii",$groupId,$clanId); $off->execute(); $off->close();
    }
  }
}

// ============================
// POST actions
// ============================
$flash = [];

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action'])) {
  $action = (string)$_POST['action'];

  // Nota: mejor en transacción (si algo falla, no deja la BD a medias)
  $link->begin_transaction();

  try {
    if ($action === 'save_char_links') {
      $charId  = max(0, (int)($_POST['character_id'] ?? 0));
      $groupId = max(0, (int)($_POST['group_id'] ?? 0)); // manada
      $clanId  = max(0, (int)($_POST['clan_id'] ?? 0));

      // Si eliges manada, opcionalmente forzamos clan al clan "dueño" de esa manada (bridge clan-group)
      $autoClan = 0;
      if ($groupId > 0) {
        $autoClan = resolve_clan_for_group($link, $T_CLAN_GROUP, $groupId);
      }

      // 1) Grupo
      set_active_character_group($link, $T_CHAR_GROUP, $charId, $groupId);

      // 2) Clan
      // - Si hay group y autoClan existe, usa ese.
      // - Si no, usa lo que venga del select.
      $finalClan = ($autoClan>0) ? $autoClan : $clanId;
      set_active_character_clan($link, $T_CHAR_CLAN, $charId, $finalClan);

      $flash[] = ['type'=>'ok','msg'=>"✅ Relación actualizada (PJ #{$charId})."];
    }

    if ($action === 'save_clan_group') {
      $clanId  = max(0,(int)($_POST['clan_id'] ?? 0));
      $groupId = max(0,(int)($_POST['group_id'] ?? 0));
      $isAct   = isset($_POST['is_active']) ? (int)($_POST['is_active']) : 1;

      // Si quieres permitir que una manada esté activa en MÁS de un clan, pon esto a false:
      $enforce = true;

      set_clan_group($link, $T_CLAN_GROUP, $clanId, $groupId, $isAct, $enforce);

      $flash[] = ['type'=>'ok','msg'=>"✅ Clan↔Manada actualizado (Clan #{$clanId} / Manada #{$groupId})."];
      $tab = 'clans';
    }

    if ($action === 'deactivate_row') {
      $table = (string)($_POST['table'] ?? '');
      $id    = max(0,(int)($_POST['id'] ?? 0));
      $allowed = [$T_CHAR_GROUP, $T_CHAR_CLAN, $T_CLAN_GROUP];
      if ($id>0 && in_array($table,$allowed,true)) {
        if ($st = $link->prepare("UPDATE {$table} SET is_active=0 WHERE id=?")) {
          $st->bind_param("i",$id); $st->execute(); $st->close();
        }
        $flash[] = ['type'=>'ok','msg'=>"🧊 Relación desactivada (#{$id})."];
      }
    }

    // Si quieres borrado duro (NO recomendado si quieres histórico), descomenta el botón y este handler:
    /*
    if ($action === 'delete_row') {
      $table = (string)($_POST['table'] ?? '');
      $id    = max(0,(int)($_POST['id'] ?? 0));
      $allowed = [$T_CHAR_GROUP, $T_CHAR_CLAN, $T_CLAN_GROUP];
      if ($id>0 && in_array($table,$allowed,true)) {
        if ($st = $link->prepare("DELETE FROM {$table} WHERE id=?")) {
          $st->bind_param("i",$id); $st->execute(); $st->close();
        }
        $flash[] = ['type'=>'ok','msg'=>"🗑️ Relación eliminada (#{$id})."];
      }
    }
    */

    $link->commit();
  } catch (Throwable $e) {
    $link->rollback();
    $flash[] = ['type'=>'error','msg'=>"❌ Error: ".$e->getMessage()];
  }

  // Mantener tab al volver
  if (isset($_POST['tab']) && $_POST['tab'] !== '') $tab = (string)$_POST['tab'];
}

// ============================
// Cargar catálogos para selects
// ============================
$opts_clanes = fetchPairs($link, "SELECT id, name FROM dim_organizations ORDER BY name");
$opts_manadas= fetchPairs($link, "SELECT id, name FROM dim_groups ORDER BY name");

// ============================
// Tab 1: Personajes + relaciones activas
// ============================
$chars = [];
$sqlChars = "
  SELECT
    p.id,
    p.nombre,
    p.img,
    p.estado,

    cg.id  AS char_group_bridge_id,
    cg.group_id AS active_group_id,
    m.name AS active_group_name,

    cc.id  AS char_clan_bridge_id,
    cc.clan_id AS active_clan_id,
    c.name AS active_clan_name
  FROM fact_characters p
  LEFT JOIN {$T_CHAR_GROUP} cg
    ON cg.character_id = p.id AND (cg.is_active=1 OR cg.is_active IS NULL)
  LEFT JOIN dim_groups m
    ON m.id = cg.group_id
  LEFT JOIN {$T_CHAR_CLAN} cc
    ON cc.character_id = p.id AND (cc.is_active=1 OR cc.is_active IS NULL)
  LEFT JOIN dim_organizations c
    ON c.id = cc.clan_id
  WHERE 1=1
  {$cronicaNotInSQL}
  ORDER BY p.nombre ASC
";
if ($rs = $link->query($sqlChars)) {
  while($r = $rs->fetch_assoc()) $chars[] = $r;
  $rs->close();
}

// ============================
// Tab 2: Clan -> Manadas (vista compacta)
// ============================
$clanGroups = []; // filas
$sqlCG = "
  SELECT
    b.id,
    b.clan_id, c.name AS clan_name,
    b.group_id, m.name AS group_name,
    COALESCE(b.is_active,0) AS is_active
  FROM {$T_CLAN_GROUP} b
  LEFT JOIN dim_organizations c ON c.id=b.clan_id
  LEFT JOIN dim_groups m ON m.id=b.group_id
  ORDER BY c.name ASC, m.name ASC, b.id DESC
";
if ($rs = $link->query($sqlCG)) {
  while($r = $rs->fetch_assoc()) $clanGroups[] = $r;
  $rs->close();
}

// Render
//include("sep/main/main_nav_bar.php");
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<style>
.panel-wrap { background:#05014E; border:1px solid #000088; border-radius:12px; padding:12px; }
.hdr { display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:10px; }
.hdr h2 { margin:0; color:#33FFFF; font-size:16px; }
.btn { background:#0d3a7a; color:#fff; border:1px solid #1b4aa0; border-radius:8px; padding:6px 10px; cursor:pointer; font-size:12px; }
.btn:hover { filter:brightness(1.1); }
.btn-green { background:#0d5d37; border-color:#168f59; }
.btn-red { background:#6b1c1c; border-color:#993333; }
.btn-gray { background:#3b3b3b; border-color:#666; }
.inp { background:#000033; color:#fff; border:1px solid #333; padding:4px 6px; font-size:12px; }
.select { background:#000033; color:#fff; border:1px solid #333; padding:4px 6px; font-size:12px; }
.table { width:100%; border-collapse:collapse; font-size:11px; font-family:Verdana,Arial,sans-serif; }
.table th, .table td { border:1px solid #000088; padding:6px 8px; background:#05014E; white-space:nowrap; vertical-align:middle; }
.table th { background:#050b36; color:#33CCCC; text-align:left; }
.table tr:hover td { background:#000066; color:#33FFFF; }
.flash { margin:8px 0; }
.flash .ok{ color:#7CFC00; } .flash .err{ color:#FF6B6B; } .flash .info{ color:#33FFFF; }

.tabs { display:flex; gap:8px; margin:10px 0; flex-wrap:wrap; }
.tablink { padding:6px 10px; border:1px solid #000088; border-radius:999px; text-decoration:none; background:#05014E; color:#eee; }
.tablink.active { background:#001199; color:#fff; }

.small { font-size:10px; color:#9dd; }
.badge { display:inline-block; padding:2px 6px; border-radius:999px; border:1px solid #1b4aa0; background:#00135a; color:#fff; font-size:10px; }
.badge.off { background:#2b2b2b; border-color:#666; color:#ddd; }

.modal-back{
  position:fixed; inset:0;
  background:rgba(0,0,0,.6);
  display:none; align-items:center; justify-content:center;
  z-index:9999;
  padding:14px;
}
.modal{
  width:min(900px, 96vw);
  max-height:92vh;
  overflow:hidden;
  background:#05014E;
  border:1px solid #000088;
  border-radius:12px;
  padding:12px;
  display:flex;
  flex-direction:column;
}
.modal h3{ margin:0 0 8px; color:#33FFFF; }
.modal-body{ flex:1; overflow:auto; padding-right:6px; }
.modal-actions{
  position:sticky;
  bottom:0;
  display:flex;
  gap:10px;
  justify-content:flex-end;
  padding:10px 0 0;
  margin-top:10px;
  background:linear-gradient(to top, rgba(5,1,78,1), rgba(5,1,78,0));
  border-top:1px solid #000088;
}
.grid { display:grid; grid-template-columns:repeat(2, minmax(240px,1fr)); gap:10px 12px; }
.grid label{ font-size:12px; color:#cfe; display:block; }
.avatar-mini{ width:18px; height:18px; object-fit:cover; border-radius:50%; border:1px solid #1b4aa0; vertical-align:middle; margin-right:6px; background:#000022; }

.select2-container{ width:100% !important; font-size:12px; }
.select2-container--default .select2-selection--single{
  background:#000033; border:1px solid #333; color:#fff;
  height:28px; border-radius:6px;
}
.select2-container--default .select2-selection--single .select2-selection__rendered{
  color:#fff; line-height:26px;
}
.select2-dropdown{
  background:#000033; border:1px solid #333; color:#fff;
}
.select2-results__option{ color:#fff; }
.select2-container--open{ z-index: 20000; }
</style>

<div class="panel-wrap">
  <div class="hdr">
    <h2>🔗 Bridges — Relaciones</h2>
    <span class="small">Editar pertenencias activas (con histórico en is_active=0)</span>
  </div>

  <div class="tabs">
    <a class="tablink <?= $tab==='chars'?'active':'' ?>" href="/talim?s=admin_bridges&tab=chars">👤 PJ → Clan/Manada</a>
    <a class="tablink <?= $tab==='clans'?'active':'' ?>" href="/talim?s=admin_bridges&tab=clans">🏛️ Clan → Manadas</a>
  </div>

  <?php if (!empty($flash)): ?>
    <div class="flash">
      <?php foreach($flash as $m):
        $cl = $m['type']==='ok'?'ok':($m['type']==='error'?'err':'info'); ?>
        <div class="<?= $cl ?>"><?= h($m['msg']) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($tab === 'chars'): ?>

    <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:10px;">
      <label style="color:#cfe; font-size:12px;">Filtro rápido
        <input class="inp" type="text" id="qChars" placeholder="Nombre del PJ…">
      </label>
      <span class="small">Doble click en el nombre abre la bio</span>
    </div>

    <table class="table" id="tblChars">
      <thead>
        <tr>
          <th style="width:60px;">ID</th>
          <th>Personaje</th>
          <th>Estado</th>
          <th>Clan activo</th>
          <th>Manada activa</th>
          <th style="width:140px;">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($chars as $c):
          $cid = (int)$c['id'];
          $nm  = (string)($c['nombre'] ?? '');
          $img = (string)($c['img'] ?? '');
          $est = (string)($c['estado'] ?? '');
          $cln = (string)($c['active_clan_name'] ?? '');
          $grp = (string)($c['active_group_name'] ?? '');
          $clanId = (int)($c['active_clan_id'] ?? 0);
          $groupId= (int)($c['active_group_id'] ?? 0);
        ?>
        <tr data-nombre="<?= strtolower(h($nm)) ?>">
          <td><strong style="color:#33FFFF;"><?= $cid ?></strong></td>
          <td>
            <a href="/characters/<?= $cid ?>" target="_blank" style="color:#fff;text-decoration:none;">
              <?php if ($img): ?><img class="avatar-mini" src="<?= h($img) ?>" alt=""><?php endif; ?>
              <?= h($nm) ?>
            </a>
          </td>
          <td><?= h($est) ?></td>
          <td>
            <?= $cln ? h($cln) : "<span class='badge off'>—</span>" ?>
            <?php if ($clanId>0): ?><span class="small">#<?= $clanId ?></span><?php endif; ?>
          </td>
          <td>
            <?= $grp ? h($grp) : "<span class='badge off'>—</span>" ?>
            <?php if ($groupId>0): ?><span class="small">#<?= $groupId ?></span><?php endif; ?>
          </td>
          <td>
            <button class="btn btn-green btnEditChar"
              data-id="<?= $cid ?>"
              data-name="<?= h($nm) ?>"
              data-group="<?= $groupId ?>"
              data-clan="<?= $clanId ?>"
            >✏ Editar</button>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($chars)): ?>
          <tr><td colspan="6" style="color:#bbb;">(Sin resultados)</td></tr>
        <?php endif; ?>
      </tbody>
    </table>

  <?php else: /* tab clans */ ?>

    <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:10px;">
      <button class="btn btn-green" id="btnNewClanGroup">➕ Nueva relación Clan↔Manada</button>
      <label style="color:#cfe; font-size:12px;">Filtro rápido
        <input class="inp" type="text" id="qClanGroups" placeholder="Clan o Manada…">
      </label>
      <span class="small">Activa=1 es la relación vigente. Desactivar conserva histórico.</span>
    </div>

    <table class="table" id="tblClanGroups">
      <thead>
        <tr>
          <th style="width:70px;">ID</th>
          <th>Clan</th>
          <th>Manada</th>
          <th>Estado</th>
          <th style="width:160px;">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($clanGroups as $r):
          $rid = (int)$r['id'];
          $cln = (string)($r['clan_name'] ?? '');
          $grp = (string)($r['group_name'] ?? '');
          $cid = (int)($r['clan_id'] ?? 0);
          $gid = (int)($r['group_id'] ?? 0);
          $act = (int)($r['is_active'] ?? 0);
        ?>
        <tr data-text="<?= strtolower(h($cln.' '.$grp)) ?>">
          <td><strong style="color:#33FFFF;"><?= $rid ?></strong></td>
          <td><?= $cln ? h($cln) : "<span class='badge off'>(sin clan)</span>" ?> <span class="small">#<?= $cid ?></span></td>
          <td><?= $grp ? h($grp) : "<span class='badge off'>(sin manada)</span>" ?> <span class="small">#<?= $gid ?></span></td>
          <td><?= $act ? "<span class='badge'>Activo</span>" : "<span class='badge off'>Inactivo</span>" ?></td>
          <td>
            <button class="btn btn-green btnEditClanGroup"
              data-id="<?= $rid ?>"
              data-clan="<?= $cid ?>"
              data-group="<?= $gid ?>"
              data-active="<?= $act ?>"
            >✏ Editar</button>

            <form method="post" style="display:inline;">
              <input type="hidden" name="tab" value="clans">
              <input type="hidden" name="action" value="deactivate_row">
              <input type="hidden" name="table" value="<?= h($T_CLAN_GROUP) ?>">
              <input type="hidden" name="id" value="<?= $rid ?>">
              <button class="btn btn-gray" type="submit">🧊 Desactivar</button>
            </form>

            <!-- Borrado duro (desaconsejado): activa si lo necesitas
            <form method="post" style="display:inline;">
              <input type="hidden" name="tab" value="clans">
              <input type="hidden" name="action" value="delete_row">
              <input type="hidden" name="table" value="<?= h($T_CLAN_GROUP) ?>">
              <input type="hidden" name="id" value="<?= $rid ?>">
              <button class="btn btn-red" type="submit" onclick="return confirm('¿Eliminar definitivo?')">🗑</button>
            </form>
            -->
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($clanGroups)): ?>
          <tr><td colspan="5" style="color:#bbb;">(Sin resultados)</td></tr>
        <?php endif; ?>
      </tbody>
    </table>

  <?php endif; ?>
</div>

<!-- Modal PJ -->
<div class="modal-back" id="mbChar">
  <div class="modal" role="dialog" aria-modal="true">
    <h3>Editar relaciones del PJ</h3>
    <form method="post" id="formChar" style="margin:0;">
      <input type="hidden" name="tab" value="chars">
      <input type="hidden" name="action" value="save_char_links">
      <input type="hidden" name="character_id" id="f_char_id" value="0">

      <div class="modal-body">
        <div class="grid">
          <div>
            <label>Personaje
              <input class="inp" type="text" id="f_char_name" value="" disabled>
            </label>
            <span class="small">Si seleccionas Manada, el Clan puede autoajustarse por Clan→Manada activo.</span>
          </div>
          <div></div>

          <div>
            <label>Clan activo
              <select class="select" name="clan_id" id="f_char_clan">
                <option value="0">— (ninguno) —</option>
                <?php foreach($opts_clanes as $id=>$name): ?>
                  <option value="<?= (int)$id ?>"><?= h($name) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>
          <div>
            <label>Manada activa
              <select class="select" name="group_id" id="f_char_group">
                <option value="0">— (ninguna) —</option>
                <?php foreach($opts_manadas as $id=>$name): ?>
                  <option value="<?= (int)$id ?>"><?= h($name) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>
        </div>

        <div style="margin-top:10px;">
          <span class="small">
            Regla aplicada al guardar: 1) activa la relación elegida; 2) desactiva el resto del mismo PJ.
          </span>
        </div>
      </div>

      <div class="modal-actions">
        <button type="button" class="btn btn-red" id="btnCharCancel">Cancelar</button>
        <button type="submit" class="btn btn-green">Guardar</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Clan↔Manada -->
<div class="modal-back" id="mbCG">
  <div class="modal" role="dialog" aria-modal="true">
    <h3>Editar Clan ↔ Manada</h3>

    <form method="post" id="formCG" style="margin:0;">
      <input type="hidden" name="tab" value="clans">
      <input type="hidden" name="action" value="save_clan_group">

      <div class="modal-body">
        <div class="grid">
          <div>
            <label>Clan
              <select class="select" name="clan_id" id="f_cg_clan" required>
                <option value="0">— Selecciona —</option>
                <?php foreach($opts_clanes as $id=>$name): ?>
                  <option value="<?= (int)$id ?>"><?= h($name) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>
          <div>
            <label>Manada
              <select class="select" name="group_id" id="f_cg_group" required>
                <option value="0">— Selecciona —</option>
                <?php foreach($opts_manadas as $id=>$name): ?>
                  <option value="<?= (int)$id ?>"><?= h($name) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>

          <div>
            <label>Estado
              <select class="select" name="is_active" id="f_cg_active">
                <option value="1">Activa</option>
                <option value="0">Inactiva</option>
              </select>
            </label>
          </div>
          <div>
            <span class="small">
              Si marcas Activa, por defecto se desactivan otras relaciones activas de ESA manada con otros clanes.
              (Eso se puede cambiar en el código: <code>$enforce=true</code>).
            </span>
          </div>
        </div>
      </div>

      <div class="modal-actions">
        <button type="button" class="btn btn-red" id="btnCGCancel">Cancelar</button>
        <button type="submit" class="btn btn-green">Guardar</button>
      </div>
    </form>
  </div>
</div>

<script>
(function(){
  // Select2 init
  function initSelect2($parent){
    if (!window.jQuery || !jQuery.fn || !jQuery.fn.select2) return;
    $parent.find('select').each(function(){
      var $s = jQuery(this);
      if ($s.data('select2')) $s.select2('destroy');
      $s.select2({ width:'style', dropdownParent: $parent, minimumResultsForSearch: 0 });
    });
  }

  // Filtro rápido
  var qChars = document.getElementById('qChars');
  if (qChars) {
    qChars.addEventListener('input', function(){
      var q = (this.value||'').toLowerCase();
      document.querySelectorAll('#tblChars tbody tr').forEach(function(tr){
        var nom = tr.getAttribute('data-nombre') || '';
        tr.style.display = nom.indexOf(q)!==-1 ? '' : 'none';
      });
    });
  }
  var qCG = document.getElementById('qClanGroups');
  if (qCG) {
    qCG.addEventListener('input', function(){
      var q = (this.value||'').toLowerCase();
      document.querySelectorAll('#tblClanGroups tbody tr').forEach(function(tr){
        var txt = tr.getAttribute('data-text') || '';
        tr.style.display = txt.indexOf(q)!==-1 ? '' : 'none';
      });
    });
  }

  // Modal PJ
  var mbChar = document.getElementById('mbChar');
  var btnCharCancel = document.getElementById('btnCharCancel');
  var fCharId = document.getElementById('f_char_id');
  var fCharName = document.getElementById('f_char_name');
  var fCharClan = document.getElementById('f_char_clan');
  var fCharGroup= document.getElementById('f_char_group');

  Array.prototype.forEach.call(document.querySelectorAll('.btnEditChar'), function(btn){
    btn.addEventListener('click', function(){
      var id = btn.getAttribute('data-id') || '0';
      var nm = btn.getAttribute('data-name') || '';
      var cl = btn.getAttribute('data-clan') || '0';
      var gr = btn.getAttribute('data-group') || '0';

      fCharId.value = id;
      fCharName.value = nm;
      fCharClan.value = cl;
      fCharGroup.value = gr;

      mbChar.style.display = 'flex';
      initSelect2(jQuery('#mbChar'));
    });
  });

  btnCharCancel && btnCharCancel.addEventListener('click', function(){ mbChar.style.display='none'; });
  mbChar && mbChar.addEventListener('click', function(e){ if (e.target === mbChar) mbChar.style.display='none'; });

  // Modal Clan↔Manada
  var mbCG = document.getElementById('mbCG');
  var btnCGCancel = document.getElementById('btnCGCancel');
  var btnNewCG = document.getElementById('btnNewClanGroup');

  var fCGClan = document.getElementById('f_cg_clan');
  var fCGGroup= document.getElementById('f_cg_group');
  var fCGAct  = document.getElementById('f_cg_active');

  btnNewCG && btnNewCG.addEventListener('click', function(){
    fCGClan.value = '0';
    fCGGroup.value= '0';
    fCGAct.value  = '1';
    mbCG.style.display='flex';
    initSelect2(jQuery('#mbCG'));
  });

  Array.prototype.forEach.call(document.querySelectorAll('.btnEditClanGroup'), function(btn){
    btn.addEventListener('click', function(){
      fCGClan.value = btn.getAttribute('data-clan') || '0';
      fCGGroup.value= btn.getAttribute('data-group')|| '0';
      fCGAct.value  = btn.getAttribute('data-active')|| '0';
      mbCG.style.display='flex';
      initSelect2(jQuery('#mbCG'));
    });
  });

  btnCGCancel && btnCGCancel.addEventListener('click', function(){ mbCG.style.display='none'; });
  mbCG && mbCG.addEventListener('click', function(e){ if (e.target === mbCG) mbCG.style.display='none'; });

})();
</script>
