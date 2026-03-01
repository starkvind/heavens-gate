<?php
// admin_bridges.php - Panel para ver/editar Bridges
// - PJ -> Manada (bridge_characters_groups)
// - PJ -> Clan   (bridge_characters_organizations)
// - Clan -> Manadas (bridge_organizations_groups)
//
// Requisitos:
// - Debe existir $link (mysqli) ya conectado
// - Opcional: $excludeChronicles (CSV ints) para filtrar fact_characters.chronicle_id

if (!isset($link) || !$link) { die("Error de conexion a la base de datos."); }
if (method_exists($link, 'set_charset')) $link->set_charset('utf8mb4'); else mysqli_set_charset($link,'utf8mb4');
include_once(__DIR__ . '/../../helpers/character_avatar.php');
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
if (session_status() === PHP_SESSION_NONE) { @session_start(); }

$isAjaxRequest = (
  ((string)($_GET['ajax'] ?? '') === '1')
  || (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest')
);

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

$ADMIN_CSRF_SESSION_KEY = 'csrf_admin_bridges';
if (function_exists('hg_admin_ensure_csrf_token')) {
  $CSRF = hg_admin_ensure_csrf_token($ADMIN_CSRF_SESSION_KEY);
} else {
  if (empty($_SESSION[$ADMIN_CSRF_SESSION_KEY])) {
    $_SESSION[$ADMIN_CSRF_SESSION_KEY] = bin2hex(random_bytes(16));
  }
  $CSRF = $_SESSION[$ADMIN_CSRF_SESSION_KEY];
}
function bridges_csrf_ok(): bool {
  $payload = function_exists('hg_admin_read_json_payload') ? hg_admin_read_json_payload() : [];
  $token = function_exists('hg_admin_extract_csrf_token')
    ? hg_admin_extract_csrf_token($payload)
    : (string)($_POST['csrf'] ?? '');
  if (function_exists('hg_admin_csrf_valid')) {
    return hg_admin_csrf_valid($token, 'csrf_admin_bridges');
  }
  return is_string($token) && $token !== '' && isset($_SESSION['csrf_admin_bridges']) && hash_equals($_SESSION['csrf_admin_bridges'], $token);
}

// ============================
// Config / filtros
// ============================
$excludeChronicles = isset($excludeChronicles) ? sanitize_int_csv($excludeChronicles) : '';
$cronicaNotInSQL   = ($excludeChronicles !== '') ? " AND p.chronicle_id NOT IN ($excludeChronicles) " : "";

$tab = isset($_GET['tab']) && $_GET['tab'] !== '' ? (string)$_GET['tab'] : ((string)($_POST['tab'] ?? 'chars')); // chars | clans
if ($tab !== 'chars' && $tab !== 'clans') $tab = 'chars';
$pageTitle2 = "Bridges - Relaciones";

// IMPORTANTE: si tu tabla es hg_character_clan_bridge (singular), cambia aqui:
$T_CHAR_GROUP = "bridge_characters_groups";
$T_CHAR_CLAN  = "bridge_characters_organizations";   // <-- cambia si procede
$T_CLAN_GROUP = "bridge_organizations_groups";

// ============================
// Helpers de negocio (upsert + activar)
// ============================

/**
 * Activa UNA relacion PJ->Manada (group) y desactiva el resto para ese PJ.
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
 * Activa UNA relacion PJ->Clan y desactiva el resto para ese PJ.
 * Si $clanId <= 0: desactiva todas.
 */
function set_active_character_clan(mysqli $link, string $T_CHAR_CLAN, int $charId, int $clanId): void {
  if ($charId <= 0) return;

  if ($clanId > 0) {
    if ($st = $link->prepare("SELECT id FROM {$T_CHAR_CLAN} WHERE character_id=? AND organization_id=? ORDER BY id DESC LIMIT 1")) {
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
        if ($ins = $link->prepare("INSERT INTO {$T_CHAR_CLAN} (character_id, organization_id, is_active) VALUES (?,?,1)")) {
          $ins->bind_param("ii",$charId,$clanId); $ins->execute(); $ins->close();
        }
      }
    }
    if ($off = $link->prepare("UPDATE {$T_CHAR_CLAN} SET is_active=0 WHERE character_id=? AND organization_id<>?")) {
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
 * Devuelve organization_id o 0 si no encuentra.
 */
function resolve_clan_for_group(mysqli $link, string $T_CLAN_GROUP, int $groupId): int {
  if ($groupId <= 0) return 0;
  $cid = 0;
  $sql = "SELECT organization_id FROM {$T_CLAN_GROUP} WHERE group_id=? AND (is_active=1 OR is_active IS NULL) ORDER BY id DESC LIMIT 1";
  if ($st = $link->prepare($sql)) {
    $st->bind_param("i",$groupId);
    $st->execute();
    $rs = $st->get_result();
    if ($rs && ($r=$rs->fetch_assoc())) $cid = (int)$r['organization_id'];
    $st->close();
  }
  return $cid;
}

/**
 * Activa/desactiva una relacion Clan->Manada. Aqui NO imponemos "solo una" por clan (porque un clan tiene muchas manadas),
 * pero si puedes imponer "una manada solo puede pertenecer a un clan activo" desactivando el resto por group_id.
 */
function set_clan_group(mysqli $link, string $T_CLAN_GROUP, int $clanId, int $groupId, int $isActive, bool $enforceOneActiveOwnerPerGroup = true): void {
  if ($clanId<=0 || $groupId<=0) return;
  $isActive = $isActive ? 1 : 0;

  // Si existe, update; si no, insert
  $idRow = 0;
  if ($st = $link->prepare("SELECT id FROM {$T_CLAN_GROUP} WHERE organization_id=? AND group_id=? ORDER BY id DESC LIMIT 1")) {
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
    if ($ins = $link->prepare("INSERT INTO {$T_CLAN_GROUP} (organization_id, group_id, is_active) VALUES (?,?,?)")) {
      $ins->bind_param("iii",$clanId,$groupId,$isActive); $ins->execute(); $ins->close();
    }
  }

  // Si activas una (clanId, groupId) y quieres que la manada tenga SOLO un clan activo a la vez:
  if ($isActive===1 && $enforceOneActiveOwnerPerGroup) {
    if ($off = $link->prepare("UPDATE {$T_CLAN_GROUP} SET is_active=0 WHERE group_id=? AND organization_id<>?")) {
      $off->bind_param("ii",$groupId,$clanId); $off->execute(); $off->close();
    }
  }
}

// ============================
// POST actions
// ============================
$flash = [];
$lastMsg = '';
$hasError = false;

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action'])) {
  if ($isAjaxRequest && function_exists('hg_admin_require_session')) {
    hg_admin_require_session(true);
  }
  if (!bridges_csrf_ok()) {
    if ($isAjaxRequest && function_exists('hg_admin_json_error')) {
      hg_admin_json_error('CSRF invalido. Recarga la pagina.', 400, ['csrf' => 'invalid']);
    }
    $flash[] = ['type'=>'error','msg'=>'CSRF invalido.'];
  } else {
  $action = (string)$_POST['action'];
  $lastMsg = '';
  $hasError = false;

  // Nota: mejor en transaccion (si algo falla, no deja la BD a medias)
  $link->begin_transaction();

  try {
    if ($action === 'save_char_links') {
      $charId  = max(0, (int)($_POST['character_id'] ?? 0));
      $groupId = max(0, (int)($_POST['group_id'] ?? 0)); // manada
      $clanId  = max(0, (int)($_POST['organization_id'] ?? 0));

      // Si eliges manada, opcionalmente forzamos clan al clan "dueno" de esa manada (bridge clan-group)
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

      $lastMsg = "Relacion actualizada (PJ #{$charId}).";
      $flash[] = ['type'=>'ok','msg'=>$lastMsg];
    }

    if ($action === 'save_clan_group') {
      $clanId  = max(0,(int)($_POST['organization_id'] ?? 0));
      $groupId = max(0,(int)($_POST['group_id'] ?? 0));
      $isAct   = isset($_POST['is_active']) ? (int)($_POST['is_active']) : 1;

      // Si quieres permitir que una manada este activa en MAS de un clan, pon esto a false:
      $enforce = true;

      set_clan_group($link, $T_CLAN_GROUP, $clanId, $groupId, $isAct, $enforce);

      $lastMsg = "Clan->Manada actualizado (Clan #{$clanId} / Manada #{$groupId}).";
      $flash[] = ['type'=>'ok','msg'=>$lastMsg];
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
        $lastMsg = "Relacion desactivada (#{$id}).";
        $flash[] = ['type'=>'ok','msg'=>$lastMsg];
      }
    }

    $link->commit();
  } catch (Throwable $e) {
    $link->rollback();
    $hasError = true;
    $lastMsg = $e->getMessage();
    $flash[] = ['type'=>'error','msg'=>"[ERROR] ".$e->getMessage()];
  }

  // Mantener tab al volver
  if (isset($_POST['tab']) && $_POST['tab'] !== '') $tab = (string)$_POST['tab'];
  if ($tab !== 'chars' && $tab !== 'clans') $tab = 'chars';
  }
}

// ============================
// Cargar catalogos para selects
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
    p.name,
    p.image_url,
    p.gender,
    COALESCE(dcs.label, p.status) AS status, p.status_id,

    cg.id  AS char_group_bridge_id,
    cg.group_id AS active_group_id,
    m.name AS active_group_name,

    cc.id  AS char_clan_bridge_id,
    cc.organization_id AS active_clan_id,
    c.name AS active_clan_name
  FROM fact_characters p
    LEFT JOIN dim_character_status dcs ON dcs.id = p.status_id
  LEFT JOIN {$T_CHAR_GROUP} cg
    ON cg.character_id = p.id AND (cg.is_active=1 OR cg.is_active IS NULL)
  LEFT JOIN dim_groups m
    ON m.id = cg.group_id
  LEFT JOIN {$T_CHAR_CLAN} cc
    ON cc.character_id = p.id AND (cc.is_active=1 OR cc.is_active IS NULL)
  LEFT JOIN dim_organizations c
    ON c.id = cc.organization_id
  WHERE 1=1
  {$cronicaNotInSQL}
  ORDER BY p.name ASC
";
if ($rs = $link->query($sqlChars)) {
  while($r = $rs->fetch_assoc()) {
    $r['avatar_url'] = hg_character_avatar_url((string)($r['image_url'] ?? ''), (string)($r['gender'] ?? ''));
    $chars[] = $r;
  }
  $rs->close();
}

// ============================
// Tab 2: Clan -> Manadas (vista compacta)
// ============================
$clanGroups = []; // filas
$sqlCG = "
  SELECT
    b.id,
    b.organization_id, c.name AS clan_name,
    b.group_id, m.name AS group_name,
    COALESCE(b.is_active,0) AS is_active
  FROM {$T_CLAN_GROUP} b
  LEFT JOIN dim_organizations c ON c.id=b.organization_id
  LEFT JOIN dim_groups m ON m.id=b.group_id
  ORDER BY c.name ASC, m.name ASC, b.id DESC
";
if ($rs = $link->query($sqlCG)) {
  while($r = $rs->fetch_assoc()) $clanGroups[] = $r;
  $rs->close();
}

if ($isAjaxRequest) {
  if (function_exists('hg_admin_require_session')) {
    hg_admin_require_session(true);
  }
  $payload = [
    'tab' => $tab,
    'chars' => $chars,
    'clanGroups' => $clanGroups,
  ];

  if ((string)($_GET['ajax_mode'] ?? '') === 'state') {
    if (function_exists('hg_admin_json_success')) {
      hg_admin_json_success($payload, 'Estado');
    }
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => true, 'data' => $payload], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }

  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($hasError) {
      if (function_exists('hg_admin_json_error')) {
        hg_admin_json_error($lastMsg !== '' ? $lastMsg : 'Error al guardar.', 400, [], $payload);
      }
      header('Content-Type: application/json; charset=UTF-8');
      echo json_encode(['ok' => false, 'message' => ($lastMsg !== '' ? $lastMsg : 'Error al guardar.'), 'data' => $payload], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      exit;
    }
    if (function_exists('hg_admin_json_success')) {
      hg_admin_json_success($payload, $lastMsg !== '' ? $lastMsg : 'Guardado');
    }
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => true, 'message' => ($lastMsg !== '' ? $lastMsg : 'Guardado'), 'data' => $payload], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }
}

// Render
//include("sep/main/main_nav_bar.php");
?>
<link rel="stylesheet" href="/assets/vendor/select2/select2.min.4.1.0.css">
<script src="/assets/vendor/jquery/jquery-3.7.1.min.js"></script>
<script src="/assets/vendor/select2/select2.min.4.1.0.js"></script>
<?php
$adminHttpJs = '/assets/js/admin/admin-http.js';
$adminHttpJsVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . $adminHttpJs) ?: time();
?>
<script>window.ADMIN_CSRF_TOKEN = <?= json_encode($CSRF, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;</script>
<script src="<?= h($adminHttpJs) ?>?v=<?= (int)$adminHttpJsVer ?>"></script>

<div class="panel-wrap">
  <div class="hdr">
    <h2>Bridges - Relaciones</h2>
    <span class="small">Editar pertenencias activas (con historico en is_active=0)</span>
  </div>

  <div class="tabs">
    <a class="tablink <?= $tab==='chars'?'active':'' ?>" href="/talim?s=admin_bridges&tab=chars">PJ -> Clan/Manada</a>
    <a class="tablink <?= $tab==='clans'?'active':'' ?>" href="/talim?s=admin_bridges&tab=clans">Clan -> Manadas</a>
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

    <div class="adm-flex-wrap-10-mb10">
      <label class="adm-text-12-cfe">Filtro rapido
        <input class="inp" type="text" id="qChars" placeholder="Nombre del PJ...">
      </label>
      <span class="small">Doble click en el nombre abre la bio</span>
    </div>

    <table class="table" id="tblChars">
      <thead>
        <tr>
          <th class="adm-w-60">ID</th>
          <th>Personaje</th>
          <th>Estado</th>
          <th>Clan activo</th>
          <th>Manada activa</th>
          <th class="adm-w-140">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($chars as $c):
          $cid = (int)$c['id'];
          $nm  = (string)($c['name'] ?? '');
          $img = hg_character_avatar_url((string)($c['image_url'] ?? ''), (string)($c['gender'] ?? ''));
          $est = (string)($c['status'] ?? '');
          $cln = (string)($c['active_clan_name'] ?? '');
          $grp = (string)($c['active_group_name'] ?? '');
          $clanId = (int)($c['active_clan_id'] ?? 0);
          $groupId= (int)($c['active_group_id'] ?? 0);
        ?>
        <tr data-nombre="<?= strtolower(h($nm)) ?>">
          <td><strong class="adm-color-accent"><?= $cid ?></strong></td>
          <td>
            <a href="/characters/<?= $cid ?>" target="_blank" class="adm-link-white">
              <img class="avatar-mini" src="<?= h($img) ?>" alt="">
              <?= h($nm) ?>
            </a>
          </td>
          <td><?= h($est) ?></td>
          <td>
            <?= $cln ? h($cln) : "<span class='badge off'>-</span>" ?>
            <?php if ($clanId>0): ?><span class="small">#<?= $clanId ?></span><?php endif; ?>
          </td>
          <td>
            <?= $grp ? h($grp) : "<span class='badge off'>-</span>" ?>
            <?php if ($groupId>0): ?><span class="small">#<?= $groupId ?></span><?php endif; ?>
          </td>
          <td>
            <button class="btn btn-green btnEditChar"
              data-id="<?= $cid ?>"
              data-name="<?= h($nm) ?>"
              data-group="<?= $groupId ?>"
              data-clan="<?= $clanId ?>"
            >Editar</button>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($chars)): ?>
          <tr><td colspan="6" class="adm-color-muted">(Sin resultados)</td></tr>
        <?php endif; ?>
      </tbody>
    </table>

  <?php else: /* tab clans */ ?>

    <div class="adm-flex-wrap-10-mb10">
      <button class="btn btn-green" id="btnNewClanGroup">Nueva relacion Clan->Manada</button>
      <label class="adm-text-12-cfe">Filtro rapido
        <input class="inp" type="text" id="qClanGroups" placeholder="Clan o Manada...">
      </label>
      <span class="small">Activa=1 es la relacion vigente. Desactivar conserva historico.</span>
    </div>

    <table class="table" id="tblClanGroups">
      <thead>
        <tr>
          <th class="adm-w-70">ID</th>
          <th>Clan</th>
          <th>Manada</th>
          <th>Estado</th>
          <th class="adm-w-160">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($clanGroups as $r):
          $rid = (int)$r['id'];
          $cln = (string)($r['clan_name'] ?? '');
          $grp = (string)($r['group_name'] ?? '');
          $cid = (int)($r['organization_id'] ?? 0);
          $gid = (int)($r['group_id'] ?? 0);
          $act = (int)($r['is_active'] ?? 0);
        ?>
        <tr data-text="<?= strtolower(h($cln.' '.$grp)) ?>">
          <td><strong class="adm-color-accent"><?= $rid ?></strong></td>
          <td><?= $cln ? h($cln) : "<span class='badge off'>(sin clan)</span>" ?> <span class="small">#<?= $cid ?></span></td>
          <td><?= $grp ? h($grp) : "<span class='badge off'>(sin manada)</span>" ?> <span class="small">#<?= $gid ?></span></td>
          <td><?= $act ? "<span class='badge'>Activo</span>" : "<span class='badge off'>Inactivo</span>" ?></td>
          <td>
            <button class="btn btn-green btnEditClanGroup"
              data-id="<?= $rid ?>"
              data-clan="<?= $cid ?>"
              data-group="<?= $gid ?>"
              data-active="<?= $act ?>"
            >Editar</button>

            <form method="post" class="adm-inline">
              <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
              <input type="hidden" name="tab" value="clans">
              <input type="hidden" name="action" value="deactivate_row">
              <input type="hidden" name="table" value="<?= h($T_CLAN_GROUP) ?>">
              <input type="hidden" name="id" value="<?= $rid ?>">
              <button class="btn btn-gray" type="submit">Desactivar</button>
            </form>

            <!-- Borrado duro (desaconsejado): activa si lo necesitas
            <form method="post" class="adm-inline">
              <input type="hidden" name="tab" value="clans">
              <input type="hidden" name="action" value="delete_row">
              <input type="hidden" name="table" value="<?= h($T_CLAN_GROUP) ?>">
              <input type="hidden" name="id" value="<?= $rid ?>">
              <button class="btn btn-red" type="submit" onclick="return confirm('Eliminar definitivo?')">Eliminar</button>
            </form>
            -->
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($clanGroups)): ?>
          <tr><td colspan="5" class="adm-color-muted">(Sin resultados)</td></tr>
        <?php endif; ?>
      </tbody>
    </table>

  <?php endif; ?>
</div>

<!-- Modal PJ -->
<div class="modal-back" id="mbChar">
  <div class="modal" role="dialog" aria-modal="true">
    <h3>Editar relaciones del PJ</h3>
    <form method="post" id="formChar" class="adm-m-0">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
      <input type="hidden" name="tab" value="chars">
      <input type="hidden" name="action" value="save_char_links">
      <input type="hidden" name="character_id" id="f_char_id" value="0">

      <div class="modal-body">
        <div class="grid">
          <div>
            <label>Personaje
              <input class="inp" type="text" id="f_char_name" value="" disabled>
            </label>
            <span class="small">Si seleccionas Manada, el Clan puede autoajustarse por Clan->Manada activo.</span>
          </div>
          <div></div>

          <div>
            <label>Clan activo
              <select class="select" name="organization_id" id="f_char_clan">
                <option value="0">- (ninguno) -</option>
                <?php foreach($opts_clanes as $id=>$name): ?>
                  <option value="<?= (int)$id ?>"><?= h($name) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>
          <div>
            <label>Manada activa
              <select class="select" name="group_id" id="f_char_group">
                <option value="0">- (ninguna) -</option>
                <?php foreach($opts_manadas as $id=>$name): ?>
                  <option value="<?= (int)$id ?>"><?= h($name) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>
        </div>

        <div class="adm-mt-10">
          <span class="small">
            Regla aplicada al guardar: 1) activa la relacion elegida; 2) desactiva el resto del mismo PJ.
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

<!-- Modal Clan <-> Manada -->
<div class="modal-back" id="mbCG">
  <div class="modal" role="dialog" aria-modal="true">
    <h3>Editar Clan <-> Manada</h3>

    <form method="post" id="formCG" class="adm-m-0">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
      <input type="hidden" name="tab" value="clans">
      <input type="hidden" name="action" value="save_clan_group">

      <div class="modal-body">
        <div class="grid">
          <div>
            <label>Clan
              <select class="select" name="organization_id" id="f_cg_clan" required>
                <option value="0">- Selecciona -</option>
                <?php foreach($opts_clanes as $id=>$name): ?>
                  <option value="<?= (int)$id ?>"><?= h($name) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>
          <div>
            <label>Manada
              <select class="select" name="group_id" id="f_cg_group" required>
                <option value="0">- Selecciona -</option>
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
              (Eso se puede cambiar en el codigo: <code>$enforce=true</code>).
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
  function esc(s){
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
  }
  var TABLE_CLAN_GROUP = <?= json_encode($T_CLAN_GROUP, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>;
  var ACTIVE_TAB = <?= json_encode($tab, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>;

  function endpointUrl(mode, tab){
    var url = new URL(window.location.href);
    url.searchParams.set('s', 'admin_bridges');
    url.searchParams.set('ajax', '1');
    if (mode) url.searchParams.set('ajax_mode', mode);
    else url.searchParams.delete('ajax_mode');
    if (tab) url.searchParams.set('tab', tab);
    else url.searchParams.delete('tab');
    url.searchParams.set('_ts', Date.now());
    return url.toString();
  }
  function request(url, opts){
    if (window.HGAdminHttp && typeof window.HGAdminHttp.request === 'function') {
      return window.HGAdminHttp.request(url, opts || {});
    }
    var cfg = Object.assign({
      method:'GET',
      credentials:'same-origin',
      headers:{'X-Requested-With':'XMLHttpRequest'}
    }, opts || {});
    return fetch(url, cfg).then(async function(resp){
      var text = await resp.text();
      var payload = {};
      if (text) {
        try { payload = JSON.parse(text); } catch(e){ payload = { ok:false, message:'Respuesta no JSON', raw:text }; }
      }
      if (!resp.ok || (payload && payload.ok === false)) {
        var err = new Error((payload && (payload.message || payload.error || payload.msg)) || ('HTTP ' + resp.status));
        err.status = resp.status;
        err.payload = payload;
        throw err;
      }
      return payload;
    });
  }
  function notifyOk(msg){
    if (window.HGAdminHttp && window.HGAdminHttp.notify) window.HGAdminHttp.notify(msg || 'Guardado', 'ok');
  }
  function notifyErr(err){
    var msg = (window.HGAdminHttp && window.HGAdminHttp.errorMessage) ? window.HGAdminHttp.errorMessage(err) : (err && err.message ? err.message : 'Error');
    alert(msg);
  }

  function renderChars(rows){
    var tbody = document.querySelector('#tblChars tbody');
    if (!tbody) return;
    if (!rows || !rows.length) {
      tbody.innerHTML = '<tr><td colspan="6" class="adm-color-muted">(Sin resultados)</td></tr>';
      bindRows();
      return;
    }
    var html = '';
    rows.forEach(function(c){
      var cid = parseInt(c.id || 0, 10) || 0;
      var nm = String(c.name || '');
      var est = String(c.status || '');
      var cln = String(c.active_clan_name || '');
      var grp = String(c.active_group_name || '');
      var clanId = parseInt(c.active_clan_id || 0, 10) || 0;
      var groupId = parseInt(c.active_group_id || 0, 10) || 0;
      var img = String(c.avatar_url || '');

      html += '<tr data-nombre="' + esc(nm.toLowerCase()) + '">';
      html += '<td><strong class="adm-color-accent">' + cid + '</strong></td>';
      html += '<td><a href="/characters/' + cid + '" target="_blank" class="adm-link-white"><img class="avatar-mini" src="' + esc(img) + '" alt="">' + esc(nm) + '</a></td>';
      html += '<td>' + esc(est) + '</td>';
      html += '<td>' + (cln ? esc(cln) : "<span class=\"badge off\">-</span>") + (clanId > 0 ? ' <span class="small">#' + clanId + '</span>' : '') + '</td>';
      html += '<td>' + (grp ? esc(grp) : "<span class=\"badge off\">-</span>") + (groupId > 0 ? ' <span class="small">#' + groupId + '</span>' : '') + '</td>';
      html += '<td><button class="btn btn-green btnEditChar" data-id="' + cid + '" data-name="' + esc(nm) + '" data-group="' + groupId + '" data-clan="' + clanId + '" type="button">Editar</button></td>';
      html += '</tr>';
    });
    tbody.innerHTML = html;
    bindRows();
    applyQuickFilters();
  }

  function renderClanGroups(rows){
    var tbody = document.querySelector('#tblClanGroups tbody');
    if (!tbody) return;
    if (!rows || !rows.length) {
      tbody.innerHTML = '<tr><td colspan="5" class="adm-color-muted">(Sin resultados)</td></tr>';
      bindRows();
      return;
    }
    var html = '';
    rows.forEach(function(r){
      var rid = parseInt(r.id || 0, 10) || 0;
      var cln = String(r.clan_name || '');
      var grp = String(r.group_name || '');
      var cid = parseInt(r.organization_id || 0, 10) || 0;
      var gid = parseInt(r.group_id || 0, 10) || 0;
      var act = parseInt(r.is_active || 0, 10) || 0;
      html += '<tr data-text="' + esc((cln + ' ' + grp).toLowerCase()) + '">';
      html += '<td><strong class="adm-color-accent">' + rid + '</strong></td>';
      html += '<td>' + (cln ? esc(cln) : "<span class=\"badge off\">(sin clan)</span>") + ' <span class="small">#' + cid + '</span></td>';
      html += '<td>' + (grp ? esc(grp) : "<span class=\"badge off\">(sin manada)</span>") + ' <span class="small">#' + gid + '</span></td>';
      html += '<td>' + (act ? "<span class=\"badge\">Activo</span>" : "<span class=\"badge off\">Inactivo</span>") + '</td>';
      html += '<td>';
      html += '<button class="btn btn-green btnEditClanGroup" data-id="' + rid + '" data-clan="' + cid + '" data-group="' + gid + '" data-active="' + act + '" type="button">Editar</button> ';
      html += '<button class="btn btn-gray btnDeactivateBridge" data-id="' + rid + '" type="button">Desactivar</button>';
      html += '</td></tr>';
    });
    tbody.innerHTML = html;
    bindRows();
    applyQuickFilters();
  }

  function reloadState(tab){
    request(endpointUrl('state', tab || ACTIVE_TAB), { method:'GET' }).then(function(payload){
      var data = payload && payload.data ? payload.data : {};
      if (Array.isArray(data.chars)) renderChars(data.chars);
      if (Array.isArray(data.clanGroups)) renderClanGroups(data.clanGroups);
    }).catch(notifyErr);
  }

  function applyQuickFilters(){
    var qChars = document.getElementById('qChars');
    if (qChars) {
      var q = (qChars.value||'').toLowerCase();
      document.querySelectorAll('#tblChars tbody tr').forEach(function(tr){
        var nom = tr.getAttribute('data-nombre') || '';
        tr.style.display = nom.indexOf(q)!==-1 ? '' : 'none';
      });
    }
    var qCG = document.getElementById('qClanGroups');
    if (qCG) {
      var q = (qCG.value||'').toLowerCase();
      document.querySelectorAll('#tblClanGroups tbody tr').forEach(function(tr){
        var txt = tr.getAttribute('data-text') || '';
        tr.style.display = txt.indexOf(q)!==-1 ? '' : 'none';
      });
    }
  }

  // Modal PJ
  var mbChar = document.getElementById('mbChar');
  var btnCharCancel = document.getElementById('btnCharCancel');
  var fCharId = document.getElementById('f_char_id');
  var fCharName = document.getElementById('f_char_name');
  var fCharClan = document.getElementById('f_char_clan');
  var fCharGroup= document.getElementById('f_char_group');

  function bindRows(){
    Array.prototype.forEach.call(document.querySelectorAll('.btnEditChar'), function(btn){
      btn.onclick = function(){
      var id = btn.getAttribute('data-id') || '0';
      var nm = btn.getAttribute('data-name') || '';
      var cl = btn.getAttribute('data-clan') || '0';
      var gr = btn.getAttribute('data-group') || '0';

      fCharId.value = id;
      fCharName.value = nm;
      fCharClan.value = cl;
      fCharGroup.value = gr;

      mbChar.style.display = 'flex';
      };
    });

    Array.prototype.forEach.call(document.querySelectorAll('.btnEditClanGroup'), function(btn){
      btn.onclick = function(){
        fCGClan.value = btn.getAttribute('data-clan') || '0';
        fCGGroup.value= btn.getAttribute('data-group')|| '0';
        fCGAct.value  = btn.getAttribute('data-active')|| '0';
        mbCG.style.display='flex';
      };
    });

    Array.prototype.forEach.call(document.querySelectorAll('.btnDeactivateBridge'), function(btn){
      btn.onclick = function(){
        var id = parseInt(btn.getAttribute('data-id') || '0', 10) || 0;
        if (!id) return;
        if (!confirm('Desactivar relacion?')) return;
        var fd = new FormData();
        fd.set('ajax', '1');
        fd.set('csrf', window.ADMIN_CSRF_TOKEN || '');
        fd.set('tab', 'clans');
        fd.set('action', 'deactivate_row');
        fd.set('table', TABLE_CLAN_GROUP);
        fd.set('id', String(id));
        request(endpointUrl('', 'clans'), { method:'POST', body: fd, loadingEl: btn }).then(function(payload){
          var data = payload && payload.data ? payload.data : {};
          if (Array.isArray(data.clanGroups)) renderClanGroups(data.clanGroups);
          notifyOk((payload && (payload.message || payload.msg)) || 'Desactivado');
        }).catch(notifyErr);
      };
    });

    Array.prototype.forEach.call(document.querySelectorAll('form.adm-inline'), function(frm){
      frm.onsubmit = function(ev){
        ev.preventDefault();
        var fd = new FormData(frm);
        fd.set('ajax', '1');
        request(endpointUrl('', 'clans'), { method:'POST', body: fd, loadingEl: frm }).then(function(payload){
          var data = payload && payload.data ? payload.data : {};
          if (Array.isArray(data.clanGroups)) renderClanGroups(data.clanGroups);
          notifyOk((payload && (payload.message || payload.msg)) || 'Actualizado');
        }).catch(notifyErr);
      };
    });
  }

  // Modal Clan <-> Manada
  var mbCG = document.getElementById('mbCG');
  var btnCGCancel = document.getElementById('btnCGCancel');
  var btnNewCG = document.getElementById('btnNewClanGroup');
  var fCGClan = document.getElementById('f_cg_clan');
  var fCGGroup= document.getElementById('f_cg_group');
  var fCGAct  = document.getElementById('f_cg_active');

  btnCharCancel && btnCharCancel.addEventListener('click', function(){ mbChar.style.display='none'; });
  mbChar && mbChar.addEventListener('click', function(e){ if (e.target === mbChar) mbChar.style.display='none'; });
  btnCGCancel && btnCGCancel.addEventListener('click', function(){ mbCG.style.display='none'; });
  mbCG && mbCG.addEventListener('click', function(e){ if (e.target === mbCG) mbCG.style.display='none'; });

  btnNewCG && btnNewCG.addEventListener('click', function(){
    fCGClan.value = '0';
    fCGGroup.value= '0';
    fCGAct.value  = '1';
    mbCG.style.display='flex';
  });

  var formChar = document.getElementById('formChar');
  if (formChar) {
    formChar.addEventListener('submit', function(ev){
      ev.preventDefault();
      var fd = new FormData(formChar);
      fd.set('ajax', '1');
      request(endpointUrl('', 'chars'), { method:'POST', body: fd, loadingEl: formChar }).then(function(payload){
        var data = payload && payload.data ? payload.data : {};
        if (Array.isArray(data.chars)) renderChars(data.chars);
        mbChar.style.display = 'none';
        notifyOk((payload && (payload.message || payload.msg)) || 'Guardado');
      }).catch(notifyErr);
    });
  }
  var formCG = document.getElementById('formCG');
  if (formCG) {
    formCG.addEventListener('submit', function(ev){
      ev.preventDefault();
      var fd = new FormData(formCG);
      fd.set('ajax', '1');
      request(endpointUrl('', 'clans'), { method:'POST', body: fd, loadingEl: formCG }).then(function(payload){
        var data = payload && payload.data ? payload.data : {};
        if (Array.isArray(data.clanGroups)) renderClanGroups(data.clanGroups);
        mbCG.style.display = 'none';
        notifyOk((payload && (payload.message || payload.msg)) || 'Guardado');
      }).catch(notifyErr);
    });
  }

  var qChars = document.getElementById('qChars');
  if (qChars) qChars.addEventListener('input', applyQuickFilters);
  var qCG = document.getElementById('qClanGroups');
  if (qCG) qCG.addEventListener('input', applyQuickFilters);

  bindRows();
  applyQuickFilters();

})();
</script>







