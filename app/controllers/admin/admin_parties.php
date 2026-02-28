<?php
/**
 * admin_parties.php - GUI autocontenida para:
 *  - dim_parties
 *  - fact_party_members
 *  - fact_party_members_changes (log, solo INSERT + LIST)
 *
 * Requisitos:
 *  - Debe existir $link (mysqli) ya conectado (como en vuestro panel).
 *  - Tabla base de personajes: fact_characters (id, nombre, alias) -> para base_char_id.
 *
 * Integracion:
 *  - Incluyelo en tu zona admin (por ejemplo /talim?s=admin_parties).
 */

if (!isset($link) || !$link) die("Sin conexion BD");
include_once(__DIR__ . '/../../helpers/pretty.php');
include_once(__DIR__ . '/../../partials/admin/admin_styles.php');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function is_post(){ return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'; }
function has_table(mysqli $db, string $table): bool {
    $table = str_replace('`', '', $table);
    $rs = $db->query("SHOW TABLES LIKE '".$db->real_escape_string($table)."'");
    if (!$rs) return false;
    $ok = ($rs->num_rows > 0);
    $rs->close();
    return $ok;
}
function has_column(mysqli $db, string $table, string $column): bool {
    $table = str_replace('`', '', $table);
    $column = str_replace('`', '', $column);
    $rs = $db->query("SHOW COLUMNS FROM `".$db->real_escape_string($table)."` LIKE '".$db->real_escape_string($column)."'");
    if (!$rs) return false;
    $ok = ($rs->num_rows > 0);
    $rs->close();
    return $ok;
}
function cur_url(): string {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    return $uri ?: '';
}
function build_redirect_url(array $extra = []): string {
    // Mantiene el query actual y añade/actualiza claves.
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $parts = parse_url($uri);
    $path = $parts['path'] ?? '';
    $qs = [];
    if (!empty($parts['query'])) parse_str($parts['query'], $qs);
    foreach ($extra as $k=>$v) {
        if ($v === null) unset($qs[$k]);
        else $qs[$k] = $v;
    }
    $q = http_build_query($qs);
    return $path . ($q ? ('?'.$q) : '');
}
function flash_add(string $type, string $msg): void {
    $_SESSION['flash'][] = ['type'=>$type, 'msg'=>$msg];
}
function flash_take(): array {
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return is_array($f) ? $f : [];
}

/* -----------------------------
   CSRF
----------------------------- */
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
function csrf_check(): bool {
    $t = $_POST['csrf'] ?? '';
    return is_string($t) && hash_equals($_SESSION['csrf'] ?? '', $t);
}

/* -----------------------------
   Compatibilidad de esquema
----------------------------- */
$partyMembersTable = '';
foreach (['fact_party_members', 'party_members'] as $t) {
    if (has_table($link, $t)) { $partyMembersTable = $t; break; }
}
$partyChangesTable = '';
foreach (['fact_party_members_changes', 'party_members_changes'] as $t) {
    if (has_table($link, $t)) { $partyChangesTable = $t; break; }
}
$partyFkCol = ($partyMembersTable !== '' && has_column($link, $partyMembersTable, 'plot_id'))
    ? 'plot_id'
    : (($partyMembersTable !== '' && has_column($link, $partyMembersTable, 'party_id')) ? 'party_id' : '');
$changesFkCol = ($partyChangesTable !== '' && has_column($link, $partyChangesTable, 'plot_char_id'))
    ? 'plot_char_id'
    : (($partyChangesTable !== '' && has_column($link, $partyChangesTable, 'party_member_id')) ? 'party_member_id' : '');
$hasPartiesSchema = ($partyMembersTable !== '' && $partyFkCol !== '' && $partyChangesTable !== '' && $changesFkCol !== '');

/* -----------------------------
   Seguridad básica POST
----------------------------- */
$action = is_post() ? (string)($_POST['action'] ?? '') : '';
if (is_post()) {
    if (!$action) {
        flash_add('error', '⚠ Falta action.');
        header("Location: ".build_redirect_url()); exit;
    }
    if (!csrf_check()) {
        flash_add('error', '⚠ CSRF inválido (recarga la página).');
        header("Location: ".build_redirect_url()); exit;
    }
}

/* -----------------------------
   POST handlers
----------------------------- */
if ($action === 'save_plot') {
    $id   = (int)($_POST['id'] ?? 0);
    $name = trim((string)($_POST['name'] ?? ''));
    $desc = trim((string)($_POST['description'] ?? ''));
    $ord  = (int)($_POST['order'] ?? 0);
    $act  = isset($_POST['active']) ? 1 : 0;

    if ($name === '') {
        flash_add('error', '⚠ El nombre de la trama es obligatorio.');
        header("Location: ".build_redirect_url(['open_plot'=>$id ?: 1])); exit;
    }

    if ($id === 0) {
        $st = $link->prepare("
            INSERT INTO dim_parties (name, description, active, sort_order, created_at, updated_at)
            VALUES (?, ?, ?, ?, NOW(), NOW())
        ");
        if (!$st) { flash_add('error', '❌ Prepare failed: '.$link->error); header("Location: ".build_redirect_url()); exit; }
        $st->bind_param("ssii", $name, $desc, $act, $ord);
        if ($st->execute()) {
            $newId = (int)$st->insert_id;
            hg_update_pretty_id_if_exists($link, 'dim_parties', $newId, $name);
            flash_add('ok', '✅ Trama creada (#'.$newId.').');
            $st->close();
            header("Location: ".build_redirect_url(['open_plot'=>null, 'focus_plot'=>$newId])); exit;
        } else {
            flash_add('error', '❌ Error al crear: '.$st->error);
            $st->close();
            header("Location: ".build_redirect_url(['open_plot'=>1])); exit;
        }
    } else {
        $st = $link->prepare("
            UPDATE dim_parties
               SET name=?, description=?, active=?, sort_order=?, updated_at=NOW()
            WHERE id=?
        ");
        if (!$st) { flash_add('error', '❌ Prepare failed: '.$link->error); header("Location: ".build_redirect_url()); exit; }
        $st->bind_param("ssiii", $name, $desc, $act, $ord, $id);
        if ($st->execute()) {
            hg_update_pretty_id_if_exists($link, 'dim_parties', $id, $name);
            flash_add('ok', '✏ Trama actualizada (#'.$id.').');
        } else {
            flash_add('error', '❌ Error al actualizar: '.$st->error);
        }
        $st->close();
        header("Location: ".build_redirect_url(['open_plot'=>null, 'focus_plot'=>$id])); exit;
    }
}

if ($action === 'save_plot_char') {
    if (!$hasPartiesSchema) {
        flash_add('error', '❌ Esquema de tramas/personajes no compatible.');
        header("Location: ".build_redirect_url()); exit;
    }
    $id     = (int)($_POST['id'] ?? 0);
    $plot   = (int)($_POST['plot_id'] ?? 0);
    $base   = (int)($_POST['base_char_id'] ?? 0);
    $alias  = trim((string)($_POST['alias'] ?? ''));
    $notes  = trim((string)($_POST['notes'] ?? ''));
    $act    = isset($_POST['active']) ? 1 : 0;

    if ($plot <= 0) { flash_add('error', '⚠ Debes seleccionar una trama.'); header("Location: ".build_redirect_url()); exit; }
    if ($base <= 0) { flash_add('error', '⚠ Debes seleccionar un personaje base.'); header("Location: ".build_redirect_url(['focus_plot'=>$plot, 'open_char'=>1, 'plot'=>$plot])); exit; }

    // Stats (m_hp NOT NULL). El resto, si tu DB permite NULL, aquí lo dejamos como int (0 por defecto).
    $stats = ['hp','rage','gnosis','glamour','mana','blood','wp'];
    $vals = [];
    foreach ($stats as $s) $vals[$s] = (int)($_POST["m_$s"] ?? 0);
    if ($vals['hp'] < 0) $vals['hp'] = 0;

    if ($id === 0) {
        $st = $link->prepare("
            INSERT INTO `{$partyMembersTable}`
            (`{$partyFkCol}`, base_char_id, alias, m_hp, m_rage, m_gnosis, m_glamour, m_mana, m_blood, m_wp, notes, active, updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?, ?, NOW())
        ");
        if (!$st) { flash_add('error', '❌ Prepare failed: '.$link->error); header("Location: ".build_redirect_url()); exit; }

        $st->bind_param(
            "iisiiiiiiisi",
            $plot,$base,$alias,
            $vals['hp'],$vals['rage'],$vals['gnosis'],$vals['glamour'],$vals['mana'],$vals['blood'],$vals['wp'],
            $notes,$act
        );

        if ($st->execute()) {
            $newId = (int)$st->insert_id;
            flash_add('ok', '✅ Personaje añadido a trama (#'.$newId.').');
            $st->close();
            header("Location: ".build_redirect_url(['focus_plot'=>$plot, 'open_char'=>null, 'plot'=>null])); exit;
        } else {
            flash_add('error', '❌ Error al insertar: '.$st->error);
            $st->close();
            header("Location: ".build_redirect_url(['focus_plot'=>$plot, 'open_char'=>1, 'plot'=>$plot])); exit;
        }
    } else {
        $st = $link->prepare("
            UPDATE `{$partyMembersTable}` SET
            `{$partyFkCol}`=?, base_char_id=?, alias=?,
            m_hp=?, m_rage=?, m_gnosis=?, m_glamour=?, m_mana=?, m_blood=?, m_wp=?,
            notes=?, active=?, updated_at=NOW()
            WHERE id=?
        ");
        if (!$st) { flash_add('error', '❌ Prepare failed: '.$link->error); header("Location: ".build_redirect_url()); exit; }

        $st->bind_param(
            "iisiiiiiiisii",
            $plot,$base,$alias,
            $vals['hp'],$vals['rage'],$vals['gnosis'],$vals['glamour'],$vals['mana'],$vals['blood'],$vals['wp'],
            $notes,$act,$id
        );

        if ($st->execute()) {
            flash_add('ok', '✏ Personaje en trama actualizado (#'.$id.').');
        } else {
            flash_add('error', '❌ Error al actualizar: '.$st->error);
        }
        $st->close();
        header("Location: ".build_redirect_url(['focus_plot'=>$plot, 'open_char'=>null, 'plot'=>null])); exit;
    }
}

if ($action === 'add_change') {
    if (!$hasPartiesSchema) {
        flash_add('error', '❌ Esquema de cambios no compatible.');
        header("Location: ".build_redirect_url()); exit;
    }
    $cid  = (int)($_POST['plot_char_id'] ?? 0);
    $res  = (string)($_POST['resource'] ?? '');
    $val  = (int)($_POST['value'] ?? 0);
    $note = trim((string)($_POST['notes'] ?? ''));

    $allowed = ['hp','rage','gnosis','blood','glamour','mana','wp'];
    if ($cid <= 0) {
        flash_add('error', '⚠ Falta plot_char_id.');
        header("Location: ".build_redirect_url()); exit;
    }
    if (!in_array($res, $allowed, true)) {
        flash_add('error', '⚠ Recurso inválido.');
        header("Location: ".build_redirect_url(['open_changes'=>$cid])); exit;
    }

    $st = $link->prepare("
        INSERT INTO `{$partyChangesTable}`
        (`{$changesFkCol}`, resource, value, notes, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    if (!$st) { flash_add('error', '❌ Prepare failed: '.$link->error); header("Location: ".build_redirect_url()); exit; }
    $st->bind_param("isis", $cid, $res, $val, $note);
    if ($st->execute()) {
        flash_add('ok', '🩸 Cambio registrado.');
    } else {
        flash_add('error', '❌ Error al registrar cambio: '.$st->error);
    }
    $st->close();
    header("Location: ".build_redirect_url(['open_changes'=>$cid])); exit;
}

/* -----------------------------
   Cargas de datos
----------------------------- */

// Plots
$plots = [];
$q = $link->query("SELECT * FROM dim_parties ORDER BY sort_order DESC, created_at DESC");
if ($q) { while ($r = $q->fetch_assoc()) $plots[] = $r; $q->close(); }

// Personajes base (fact_characters)
$baseChars = [];
$statusCol = has_column($link, 'fact_characters', 'status') ? 'status' : (has_column($link, 'fact_characters', 'character_status') ? 'character_status' : '');
$hasStatusId = has_column($link, 'fact_characters', 'status_id');
$hasStatusDim = has_table($link, 'dim_character_status');
$baseCharsSql = "SELECT fc.id, fc.name AS nombre, fc.alias FROM fact_characters fc";
if ($hasStatusId && $hasStatusDim) {
    $legacyActiveExpr = ($statusCol !== '')
        ? "IF(LOWER(TRIM(COALESCE(fc.`{$statusCol}`, '')))='en activo',1,0)"
        : "0";
    $baseCharsSql .= " LEFT JOIN dim_character_status dcs ON dcs.id = fc.status_id";
    $baseCharsSql .= " WHERE COALESCE(dcs.is_active, {$legacyActiveExpr}) = 1";
} elseif ($statusCol !== '') {
    $baseCharsSql .= " WHERE LOWER(TRIM(COALESCE(fc.`{$statusCol}`, ''))) = 'en activo' ";
}
$baseCharsSql .= " ORDER BY fc.name ASC";
$q = $link->query($baseCharsSql);
if ($q) { while ($r = $q->fetch_assoc()) $baseChars[] = $r; $q->close(); }

// Plot characters (bridge)
$plotCharsByPlot = [];
$plotCharsFlat   = []; // por id
$q = null;
if ($hasPartiesSchema) {
    $q = $link->query("
        SELECT pc.*,
               pc.`{$partyFkCol}` AS plot_id,
               p.name AS plot_name,
               p.sort_order AS plot_sort_order,
               b.name AS base_nombre,
               b.alias  AS base_alias
        FROM `{$partyMembersTable}` pc
        JOIN dim_parties p ON p.id = pc.`{$partyFkCol}`
        LEFT JOIN fact_characters b ON b.id = pc.base_char_id
        ORDER BY p.sort_order DESC, p.id DESC, pc.active DESC, COALESCE(pc.alias,b.name) ASC
    ");
}
$plotCharIds = [];
if ($q) {
    while ($r = $q->fetch_assoc()) {
        $pid = (int)$r['plot_id'];
        $cid = (int)$r['id'];
        $plotCharsByPlot[$pid][] = $r;
        $plotCharsFlat[$cid] = $r;
        $plotCharIds[] = $cid;
    }
    $q->close();
}

// Cambios agrupados para todos los plot_char en la página
$changesByPlotChar = [];
if (!empty($plotCharIds)) {
    $in = implode(',', array_map('intval', $plotCharIds));
    $q = $link->query("
        SELECT id, `{$changesFkCol}` AS plot_char_id, resource, value, notes, created_at
        FROM `{$partyChangesTable}`
        WHERE `{$changesFkCol}` IN ($in)
        ORDER BY created_at DESC
    ");
    if ($q) {
        while ($r = $q->fetch_assoc()) {
            $cid = (int)$r['plot_char_id'];
            $changesByPlotChar[$cid][] = $r;
        }
        $q->close();
    }
}

// Helpers de totales (base + suma cambios)
function compute_totals(array $pcRow, array $changes): array {
    $map = [
        'hp'      => (int)($pcRow['m_hp'] ?? 0),
        'rage'    => (int)($pcRow['m_rage'] ?? 0),
        'gnosis'  => (int)($pcRow['m_gnosis'] ?? 0),
        'glamour' => (int)($pcRow['m_glamour'] ?? 0),
        'mana'    => (int)($pcRow['m_mana'] ?? 0),
        'blood'   => (int)($pcRow['m_blood'] ?? 0),
        'wp'      => (int)($pcRow['m_wp'] ?? 0),
    ];
    foreach ($changes as $c) {
        $res = (string)($c['resource'] ?? '');
        $val = (int)($c['value'] ?? 0);
        if (isset($map[$res])) $map[$res] += $val;
    }
    return $map;
}

$flash = flash_take();

// Estado UI por GET
$focusPlot   = (int)($_GET['focus_plot'] ?? 0);
$openPlot    = isset($_GET['open_plot']) ? 1 : 0;
$openChar    = isset($_GET['open_char']) ? 1 : 0;
$openChanges = (int)($_GET['open_changes'] ?? 0);
$prePlotId   = (int)($_GET['plot'] ?? 0);

// Para JS: diccionarios
$plotsMap = [];
foreach ($plots as $p) $plotsMap[(int)$p['id']] = $p;

$csrf = $_SESSION['csrf'];
?>
<div class="hg-wrap">
  <div class="hg-hdr">
    <div>
      <h2>📚 Tramas & Personajes en Trama</h2>
      <div class="hg-sub">dim_parties · fact_party_members · fact_party_members_changes</div>
    </div>
    <div class="adm-row-right">
      <button class="btn btn-green" type="button" id="btnNewPlot">➕ Nueva trama</button>
      <button class="btn" type="button" onclick="window.location.href='<?= h(build_redirect_url(['open_plot'=>null,'open_char'=>null,'open_changes'=>null,'plot'=>null])) ?>'">🔄 Limpiar modales</button>
    </div>
  </div>

  <?php if (!empty($flash)): ?>
    <div class="flash">
      <?php foreach ($flash as $m):
        $cl = $m['type']==='ok'?'ok':($m['type']==='error'?'err':'info'); ?>
        <div class="<?= $cl ?>"><?= h($m['msg']) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (!$hasPartiesSchema): ?>
    <div class="flash">
      <div class="err">
        ❌ Esquema no compatible para miembros/cambios de trama.
        Se esperaba `fact_party_members.plot_id` o `party_members.party_id`,
        y `fact_party_members_changes.plot_char_id` o `party_members_changes.party_member_id`.
      </div>
    </div>
  <?php endif; ?>

  <!-- LISTADO DE TRAMAS -->
  <table class="table" id="plotsTable">
    <thead>
      <tr>
        <th class="adm-w-60">ID</th>
        <th>Nombre</th>
        <th class="adm-w-90">Activa</th>
        <th class="adm-w-80">Orden</th>
        <th class="adm-w-260">Acciones</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($plots as $p): ?>
      <?php $pid=(int)$p['id']; ?>
      <tr id="plot-row-<?= $pid ?>">
        <td><b class="adm-color-accent"><?= $pid ?></b></td>
        <td><?= h($p['name']) ?></td>
        <td><?= ((int)$p['active']===1) ? '<span class="badge">Sí</span>' : '<span class="badge off">No</span>' ?></td>
        <td><?= (int)($p['sort_order'] ?? 0) ?></td>
        <td>
          <button class="btn" type="button" onclick='openPlotEdit(<?= json_encode($p, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE) ?>)'>✏ Editar</button>
          <button class="btn btn-green" type="button" onclick="openCharCreate(<?= $pid ?>)">➕ Añadir personaje</button>
          <button class="btn btn-ghost" type="button" onclick="scrollToPlot(<?= $pid ?>)">⬇ Ver personajes</button>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($plots)): ?>
      <tr><td colspan="5" class="adm-color-muted">(No hay tramas aún)</td></tr>
    <?php endif; ?>
    </tbody>
  </table>

  <!-- PERSONAJES POR TRAMA -->
  <div class="section" id="charsSection">
    <h2 class="adm-title-sm">🎭 Personajes por Trama</h2>
    <div class="small">Tip: “📜 Cambios” abre el log y permite registrar un cambio nuevo.</div>

    <?php foreach ($plots as $p): ?>
      <?php $pid=(int)$p['id']; $pcs = $plotCharsByPlot[$pid] ?? []; ?>
      <div class="plot-head" id="plot-<?= $pid ?>">
        <h3><?= h($p['name']) ?></h3>
        <span class="badge"><?= ((int)$p['active']===1) ? 'Activa' : 'Inactiva' ?></span>
        <span class="badge">Orden: <?= (int)($p['sort_order'] ?? 0) ?></span>
        <button class="btn btn-green" type="button" onclick="openCharCreate(<?= $pid ?>)">➕ Añadir personaje</button>
      </div>

      <table class="table">
        <thead>
          <tr>
            <th class="adm-w-60">ID</th>
            <th>Alias</th>
            <th>Base</th>
            <th class="adm-w-80">Act.</th>
            <th class="adm-w-420">Stats (base)</th>
            <th class="adm-w-220">Acciones</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($pcs as $pc): ?>
          <?php
            $cid = (int)$pc['id'];
            $alias = trim((string)($pc['alias'] ?? ''));
            $baseName = (string)($pc['base_nombre'] ?? '');
            $baseAlias = (string)($pc['base_alias'] ?? '');
            $baseLabel = $baseName . ($baseAlias ? ' · '.$baseAlias : '');
          ?>
          <tr id="pc-row-<?= $cid ?>">
            <td><b class="adm-color-accent"><?= $cid ?></b></td>
            <td><?= h($alias !== '' ? $alias : '(sin alias)') ?></td>
            <td><?= h($baseLabel !== '' ? $baseLabel : ('#'.(int)$pc['base_char_id'])) ?></td>
            <td><?= ((int)$pc['active']===1) ? '<span class="badge">Sí</span>' : '<span class="badge off">No</span>' ?></td>
            <td class="adm-ws-normal">
              <span class="badge">HP <?= (int)$pc['m_hp'] ?></span>
              <span class="badge">Rabia <?= (int)$pc['m_rage'] ?></span>
              <span class="badge">Gnosis <?= (int)$pc['m_gnosis'] ?></span>
              <span class="badge">Glamour <?= (int)$pc['m_glamour'] ?></span>
              <span class="badge">Mana <?= (int)$pc['m_mana'] ?></span>
              <span class="badge">Sangre <?= (int)$pc['m_blood'] ?></span>
              <span class="badge">FV <?= (int)$pc['m_wp'] ?></span>
            </td>
            <td>
              <button class="btn" type="button" onclick='openCharEdit(<?= json_encode($pc, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE) ?>)'>✏ Editar</button>
              <button class="btn" type="button" onclick="openChanges(<?= $cid ?>)">📜 Cambios</button>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($pcs)): ?>
          <tr><td colspan="6" class="adm-color-muted">(No hay personajes en esta trama)</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    <?php endforeach; ?>
  </div>
</div>

<!-- MODAL: TRAMA -->
<div class="modal-back" id="mbPlot">
  <div class="modal" role="dialog" aria-modal="true">
    <h3 id="plotTitle">Nueva trama</h3>
    <form method="post" id="plotForm" class="adm-m-0">
      <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
      <input type="hidden" name="action" value="save_plot">
      <input type="hidden" name="id" id="plot_id" value="0">

      <div class="grid">
        <div class="full">
          <label>Nombre
            <input class="inp" name="name" id="plot_name" maxlength="255" required>
          </label>
        </div>

        <div class="full">
          <label>Descripción
            <textarea name="description" id="plot_desc"></textarea>
          </label>
        </div>

        <div>
          <label>Orden
            <input class="inp" type="number" name="order" id="plot_order" value="0">
          </label>
        </div>

        <div>
          <label class="adm-pt-6">
            <input type="checkbox" name="active" id="plot_active" value="1"> Activa
          </label>
        </div>
      </div>

      <div class="modal-actions">
        <button class="btn btn-red" type="button" onclick="closeModal('mbPlot')">Cancelar</button>
        <button class="btn btn-green" type="submit">Guardar</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL: PLOT CHARACTER -->
<div class="modal-back" id="mbChar">
  <div class="modal" role="dialog" aria-modal="true">
    <h3 id="charTitle">Añadir personaje a trama</h3>
    <form method="post" id="charForm" class="adm-m-0">
      <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
      <input type="hidden" name="action" value="save_plot_char">
      <input type="hidden" name="id" id="char_id" value="0">

      <div class="grid">
        <div>
          <label>Trama
            <select class="sel" name="plot_id" id="char_plot" required>
              <option value="0">— Selecciona —</option>
              <?php foreach ($plots as $p): ?>
                <option value="<?= (int)$p['id'] ?>"><?= h($p['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>

        <div>
          <label>Filtrar base (opcional)
            <input class="inp" type="text" id="baseFilter" placeholder="Escribe para filtrar…">
          </label>
        </div>

        <div>
          <label>Personaje base
            <select class="sel" name="base_char_id" id="char_base" required>
              <option value="0">— Selecciona —</option>
              <?php foreach ($baseChars as $b):
                $lbl = (string)$b['nombre'] . ((string)$b['alias'] ? ' · '.$b['alias'] : '') . ' (#'.(int)$b['id'].')';
              ?>
                <option value="<?= (int)$b['id'] ?>"><?= h($lbl) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>

        <div class="full">
          <label>Alias (en la trama)
            <input class="inp" name="alias" id="char_alias" maxlength="255" placeholder="Si lo dejas vacío, puedes usar el del base">
          </label>
        </div>

        <div>
          <label>HP
            <input class="inp" type="number" name="m_hp" id="m_hp" value="7">
          </label>
        </div>
        <div>
          <label>Rabia
            <input class="inp" type="number" name="m_rage" id="m_rage" value="0">
          </label>
        </div>
        <div>
          <label>Gnosis
            <input class="inp" type="number" name="m_gnosis" id="m_gnosis" value="0">
          </label>
        </div>

        <div>
          <label>Glamour
            <input class="inp" type="number" name="m_glamour" id="m_glamour" value="0">
          </label>
        </div>
        <div>
          <label>Mana
            <input class="inp" type="number" name="m_mana" id="m_mana" value="0">
          </label>
        </div>
        <div>
          <label>Sangre
            <input class="inp" type="number" name="m_blood" id="m_blood" value="0">
          </label>
        </div>

        <div>
          <label>FV
            <input class="inp" type="number" name="m_wp" id="m_wp" value="0">
          </label>
        </div>

        <div class="full">
          <label>Notas
            <textarea name="notes" id="char_notes"></textarea>
          </label>
        </div>

        <div>
          <label class="adm-pt-6">
            <input type="checkbox" name="active" id="char_active" value="1" checked> Activo en trama
          </label>
        </div>
      </div>

      <div class="modal-actions">
        <button class="btn btn-red" type="button" onclick="closeModal('mbChar')">Cancelar</button>
        <button class="btn btn-green" type="submit">Guardar</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL: CHANGES -->
<div class="modal-back" id="mbChg">
  <div class="modal" role="dialog" aria-modal="true">
    <h3 id="chgTitle">📜 Cambios</h3>

    <div id="chgMeta" class="small"></div>

    <div class="kpi" id="chgTotals"></div>

    <div class="adm-top-sep">
      <h3 class="adm-mb-8">Registrar cambio</h3>
      <form method="post" id="chgForm" class="adm-m-0">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <input type="hidden" name="action" value="add_change">
        <input type="hidden" name="plot_char_id" id="chg_char_id" value="0">

        <div class="grid adm-grid-112">
          <div>
            <label>Recurso
              <select class="sel" name="resource" id="chg_res">
                <option value="hp">HP</option>
                <option value="rage">Rabia</option>
                <option value="gnosis">Gnosis</option>
                <option value="wp">FV</option>
                <option value="blood">Sangre</option>
                <option value="glamour">Glamour</option>
                <option value="mana">Mana</option>
              </select>
            </label>
          </div>
          <div>
            <label>Valor (+/-)
              <input class="inp" type="number" name="value" id="chg_val" value="0">
            </label>
          </div>
          <div>
            <label>Notas
              <input class="inp" type="text" name="notes" id="chg_notes" placeholder="Motivo narrativo / mecánico…">
            </label>
          </div>
        </div>

        <div class="modal-actions adm-mt-8">
          <button class="btn btn-red" type="button" onclick="closeModal('mbChg')">Cerrar</button>
          <button class="btn btn-green" type="submit">Registrar</button>
        </div>
      </form>
    </div>

    <div class="adm-top-sep">
      <h3 class="adm-mb-8">Historial</h3>
      <div id="chgHistory" class="adm-scrollbox"></div>
    </div>

  </div>
</div>

<script>
var PLOTS_MAP = <?= json_encode($plotsMap, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
var PLOT_CHARS = <?= json_encode($plotCharsFlat, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
var CHANGES_BY_CHAR = <?= json_encode($changesByPlotChar, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;

function $(id){ return document.getElementById(id); }
function openModal(id){ $(id).style.display='flex'; }
function closeModal(id){ $(id).style.display='none'; }

function scrollToPlot(plotId){
  var el = document.getElementById('plot-'+plotId);
  if (el) el.scrollIntoView({behavior:'smooth', block:'start'});
}

/* -------------------------
   Modal Plot
------------------------- */
document.getElementById('btnNewPlot').addEventListener('click', function(){
  $('plotTitle').textContent = 'Nueva trama';
  $('plot_id').value = 0;
  $('plot_name').value = '';
  $('plot_desc').value = '';
  $('plot_order').value = 0;
  $('plot_active').checked = true;
  openModal('mbPlot');
});

function openPlotEdit(p){
  $('plotTitle').textContent = 'Editar trama';
  $('plot_id').value = p.id || 0;
  $('plot_name').value = p.name || '';
  $('plot_desc').value = p.description || '';
  $('plot_order').value = p.sort_order || p.order || 0;
  $('plot_active').checked = (String(p.active) === '1');
  openModal('mbPlot');
}

/* -------------------------
   Modal Plot Character
------------------------- */
function resetCharForm(){
  $('char_id').value = 0;
  $('char_plot').value = '0';
  $('char_base').value = '0';
  $('char_alias').value = '';
  $('m_hp').value = 7;
  $('m_rage').value = 0;
  $('m_gnosis').value = 0;
  $('m_glamour').value = 0;
  $('m_mana').value = 0;
  $('m_blood').value = 0;
  $('m_wp').value = 0;
  $('char_notes').value = '';
  $('char_active').checked = true;
  $('baseFilter').value = '';
  // Restablece visibilidad de todas las opciones del selector base.
  var sel = $('char_base');
  if (sel) {
    for (var i = 0; i < sel.options.length; i++) {
      sel.options[i].hidden = false;
    }
  }
}

function openCharCreate(plotId){
  resetCharForm();
  $('charTitle').textContent = 'Añadir personaje a trama';
  $('char_id').value = 0;
  if (plotId) $('char_plot').value = String(plotId);
  openModal('mbChar');
}

function openCharEdit(pc){
  resetCharForm();
  $('charTitle').textContent = 'Editar personaje en trama';
  $('char_id').value = pc.id || 0;
  $('char_plot').value = String(pc.plot_id || 0);
  $('char_base').value = String(pc.base_char_id || 0);
  $('char_alias').value = pc.alias || '';
  $('m_hp').value = pc.m_hp || 0;
  $('m_rage').value = pc.m_rage || 0;
  $('m_gnosis').value = pc.m_gnosis || 0;
  $('m_glamour').value = pc.m_glamour || 0;
  $('m_mana').value = pc.m_mana || 0;
  $('m_blood').value = pc.m_blood || 0;
  $('m_wp').value = pc.m_wp || 0;
  $('char_notes').value = pc.notes || '';
  $('char_active').checked = (String(pc.active) === '1');
  openModal('mbChar');
}

// Filtro simple del select base (sin librerías)
$('baseFilter').addEventListener('input', function(){
  var q = this.value.toLowerCase().trim();
  var sel = $('char_base');
  for (var i=0; i<sel.options.length; i++){
    var opt = sel.options[i];
    if (i===0){ opt.hidden = false; continue; }
    var t = (opt.textContent || '').toLowerCase();
    opt.hidden = (q && t.indexOf(q) === -1);
  }
});

/* -------------------------
   Modal Changes
------------------------- */
function fmtDate(s){
  if (!s) return '';
  // Si viene "YYYY-MM-DD HH:MM:SS", lo dejamos legible.
  return s;
}
function totalsFor(pc, list){
  var t = {
    hp: Number(pc.m_hp||0),
    rage: Number(pc.m_rage||0),
    gnosis: Number(pc.m_gnosis||0),
    glamour: Number(pc.m_glamour||0),
    mana: Number(pc.m_mana||0),
    blood: Number(pc.m_blood||0),
    wp: Number(pc.m_wp||0)
  };
  (list||[]).forEach(function(c){
    var r = c.resource;
    if (t.hasOwnProperty(r)) t[r] += Number(c.value||0);
  });
  return t;
}
function openChanges(plotCharId){
  var pc = PLOT_CHARS[String(plotCharId)];
  if (!pc) { alert('No encuentro ese plot_char_id en la página.'); return; }

  var alias = (pc.alias && pc.alias.trim()) ? pc.alias : '(sin alias)';
  var base = (pc.base_nombre || '') + (pc.base_alias ? (' · '+pc.base_alias) : '');
  if (!base) base = '#'+pc.base_char_id;

  $('chgTitle').textContent = '📜 Cambios — ' + alias;
  $('chgMeta').textContent =
    'Trama: ' + (pc.plot_name || ('#'+pc.plot_id)) +
    ' · Base: ' + base +
    ' · Estado: ' + (String(pc.active)==='1' ? 'Activo' : 'Inactivo') +
    ' · plot_char_id: ' + plotCharId;

  $('chg_char_id').value = String(plotCharId);
  $('chg_val').value = 0;
  $('chg_notes').value = '';

  var list = CHANGES_BY_CHAR[String(plotCharId)] || [];

  // Totales
  var tot = totalsFor(pc, list);
  var chips = '';
  Object.keys(tot).forEach(function(k){
    var label = (k==='hp'?'HP':k==='wp'?'FV':k.charAt(0).toUpperCase()+k.slice(1));
    chips += '<span class="chip"><b>'+label+'</b> '+tot[k]+'</span>';
  });
  $('chgTotals').innerHTML = chips;

  // Historial
  if (!list.length){
    $('chgHistory').innerHTML = '<div class="small">(Sin cambios registrados)</div>';
  } else {
    var html = '<table class="table adm-m-0">' +
      '<tr><th class="adm-w-140">Fecha</th><th class="adm-w-90">Recurso</th><th class="adm-w-70">Valor</th><th>Notas</th></tr>';
    list.forEach(function(c){
      html += '<tr>' +
        '<td>'+ (fmtDate(c.created_at) || '') +'</td>' +
        '<td>'+ (c.resource || '') +'</td>' +
        '<td><b class="adm-color-accent">'+ (c.value || 0) +'</b></td>' +
        '<td class="adm-ws-normal">'+ (c.notes ? String(c.notes).replace(/</g,'&lt;').replace(/>/g,'&gt;') : '<span class="small">—</span>') +'</td>' +
      '</tr>';
    });
    html += '</table>';
    $('chgHistory').innerHTML = html;
  }

  openModal('mbChg');
}

/* -------------------------
   Cierre modales al click en fondo
------------------------- */
['mbPlot','mbChar','mbChg'].forEach(function(id){
  $(id).addEventListener('click', function(e){
    if (e.target === $(id)) closeModal(id);
  });
});

/* -------------------------
   Auto-open desde GET (PRG)
------------------------- */
(function(){
  // Focus plot scroll
  var focusPlot = <?= (int)$focusPlot ?>;
  if (focusPlot) scrollToPlot(focusPlot);

  // Abrir modal trama si se pidió
  var openPlot = <?= (int)$openPlot ?>;
  if (openPlot) openModal('mbPlot');

  // Abrir modal personaje si se pidió
  var openChar = <?= (int)$openChar ?>;
  var prePlot = <?= (int)$prePlotId ?>;
  if (openChar){
    openCharCreate(prePlot || 0);
  }

  // Abrir modal cambios si se pidió
  var openChangesId = <?= (int)$openChanges ?>;
  if (openChangesId) openChanges(openChangesId);
})();
</script>

<?php
// Si quieres, puedes imprimir un recordatorio de orden narrativo aquí,
// pero lo dejo fuera para que este archivo sea puramente admin.
?>
