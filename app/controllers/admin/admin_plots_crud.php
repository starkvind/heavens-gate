<?php
/**
 * admin_plots_crud.php — GUI autocontenida para:
 *  - dim_parties
 *  - fact_party_members
 *  - fact_party_members_changes (log, solo INSERT + LIST)
 *
 * Requisitos:
 *  - Debe existir $link (mysqli) ya conectado (como en vuestro panel).
 *  - Tabla base de personajes: fact_characters (id, nombre, alias) -> para base_char_id.
 *
 * Integración:
 *  - Inclúyelo en tu zona admin (por ejemplo /talim?s=admin_plots_crud).
 */

if (!isset($link) || !$link) die("Sin conexión BD");
include_once(__DIR__ . '/../../helpers/pretty.php');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function is_post(){ return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'; }
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
            INSERT INTO dim_parties (name, description, active, `order`, created_at, updated_at)
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
            SET name=?, description=?, active=?, `order`=?, updated_at=NOW()
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
            INSERT INTO fact_party_members
            (plot_id, base_char_id, alias, m_hp, m_rage, m_gnosis, m_glamour, m_mana, m_blood, m_wp, notes, active, updated_at)
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
            UPDATE fact_party_members SET
            plot_id=?, base_char_id=?, alias=?,
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
        INSERT INTO fact_party_members_changes
        (plot_char_id, resource, value, notes, created_at)
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
$q = $link->query("SELECT * FROM dim_parties ORDER BY `order` DESC, created_at DESC");
if ($q) { while ($r = $q->fetch_assoc()) $plots[] = $r; $q->close(); }

// Personajes base (fact_characters)
$baseChars = [];
$q = $link->query("SELECT id, nombre, alias FROM fact_characters ORDER BY nombre ASC");
if ($q) { while ($r = $q->fetch_assoc()) $baseChars[] = $r; $q->close(); }

// Plot characters (bridge)
$plotCharsByPlot = [];
$plotCharsFlat   = []; // por id
$q = $link->query("
    SELECT pc.*,
           p.name AS plot_name,
           p.`order` AS plot_order,
           b.nombre AS base_nombre,
           b.alias  AS base_alias
    FROM fact_party_members pc
    JOIN dim_parties p ON p.id = pc.plot_id
    LEFT JOIN fact_characters b ON b.id = pc.base_char_id
    ORDER BY p.`order` DESC, p.id DESC, pc.active DESC, COALESCE(pc.alias,b.nombre) ASC
");
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
        SELECT id, plot_char_id, resource, value, notes, created_at
        FROM fact_party_members_changes
        WHERE plot_char_id IN ($in)
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
<style>
/* Estética Heaven’s Gate-ish (oscuro, legible, sin dependencias) */
.hg-wrap{ background:#05014E; border:1px solid #000088; border-radius:12px; padding:12px; font-family:Verdana,Arial,sans-serif; }
.hg-hdr{ display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:10px; }
.hg-hdr h2{ margin:0; color:#33FFFF; font-size:16px; }
.hg-sub{ color:#9dd; font-size:11px; margin-top:2px; }
.btn{ background:#0d3a7a; color:#fff; border:1px solid #1b4aa0; border-radius:8px; padding:6px 10px; cursor:pointer; font-size:12px; }
.btn:hover{ filter:brightness(1.1); }
.btn-green{ background:#0d5d37; border-color:#168f59; }
.btn-red{ background:#6b1c1c; border-color:#993333; }
.btn-ghost{ background:#05014E; }
.inp, .sel, textarea{ background:#000033; color:#fff; border:1px solid #333; padding:6px 8px; font-size:12px; border-radius:8px; }
textarea{ width:100%; min-height:90px; resize:vertical; box-sizing:border-box; }
.table{ width:100%; border-collapse:collapse; font-size:11px; }
.table th, .table td{ border:1px solid #000088; padding:6px 8px; background:#05014E; white-space:nowrap; vertical-align:top; }
.table th{ background:#050b36; color:#33CCCC; text-align:left; }
.table tr:hover td{ background:#000066; color:#33FFFF; }
.badge{ display:inline-block; padding:2px 8px; border-radius:999px; border:1px solid #1b4aa0; background:#00135a; color:#cfe; font-size:10px; }
.badge.off{ opacity:.6; }
.flash{ margin:8px 0; }
.flash .ok{ color:#7CFC00; }
.flash .err{ color:#FF6B6B; }
.flash .info{ color:#33FFFF; }

.section{ margin-top:14px; border-top:1px solid #000088; padding-top:12px; }
.plot-head{ display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin:10px 0 6px; }
.plot-head h3{ margin:0; color:#33FFFF; font-size:14px; }
.small{ font-size:10px; color:#9dd; }

.modal-back{ position:fixed; inset:0; background:rgba(0,0,0,.6); display:none; align-items:center; justify-content:center; z-index:9999; }
.modal{ width:min(980px,96vw); background:#05014E; border:1px solid #000088; border-radius:12px; padding:12px; }
.modal h3{ margin:0 0 10px; color:#33FFFF; font-size:14px; }
.grid{ display:grid; grid-template-columns:repeat(3, minmax(220px,1fr)); gap:10px; }
.grid label{ display:block; color:#cfe; font-size:12px; }
.grid .full{ grid-column:1/-1; }
.modal-actions{ display:flex; gap:10px; justify-content:flex-end; margin-top:10px; }

.kpi{ display:flex; flex-wrap:wrap; gap:6px; margin:8px 0; }
.kpi .chip{ display:inline-flex; align-items:center; gap:6px; padding:4px 8px; border-radius:999px; border:1px solid #1b4aa0; background:#00135a; color:#fff; font-size:11px; }
.kpi .chip b{ color:#9dd; }

@media (max-width:900px){ .grid{ grid-template-columns:repeat(2, minmax(240px,1fr)); } }
@media (max-width:700px){ .grid{ grid-template-columns:1fr; } .table th, .table td{ white-space:normal; } }
</style>

<div class="hg-wrap">
  <div class="hg-hdr">
    <div>
      <h2>📚 Tramas & Personajes en Trama</h2>
      <div class="hg-sub">dim_parties · fact_party_members · fact_party_members_changes</div>
    </div>
    <div style="margin-left:auto; display:flex; gap:8px; flex-wrap:wrap;">
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

  <!-- LISTADO DE TRAMAS -->
  <table class="table" id="plotsTable">
    <thead>
      <tr>
        <th style="width:60px;">ID</th>
        <th>Nombre</th>
        <th style="width:90px;">Activa</th>
        <th style="width:80px;">Orden</th>
        <th style="width:260px;">Acciones</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($plots as $p): ?>
      <?php $pid=(int)$p['id']; ?>
      <tr id="plot-row-<?= $pid ?>">
        <td><b style="color:#33FFFF;"><?= $pid ?></b></td>
        <td><?= h($p['name']) ?></td>
        <td><?= ((int)$p['active']===1) ? '<span class="badge">Sí</span>' : '<span class="badge off">No</span>' ?></td>
        <td><?= (int)($p['order'] ?? 0) ?></td>
        <td>
          <button class="btn" type="button" onclick='openPlotEdit(<?= json_encode($p, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE) ?>)'>✏ Editar</button>
          <button class="btn btn-green" type="button" onclick="openCharCreate(<?= $pid ?>)">➕ Añadir personaje</button>
          <button class="btn btn-ghost" type="button" onclick="scrollToPlot(<?= $pid ?>)">⬇ Ver personajes</button>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($plots)): ?>
      <tr><td colspan="5" style="color:#bbb;">(No hay tramas aún)</td></tr>
    <?php endif; ?>
    </tbody>
  </table>

  <!-- PERSONAJES POR TRAMA -->
  <div class="section" id="charsSection">
    <h2 style="margin:0 0 8px; color:#33FFFF; font-size:14px;">🎭 Personajes por Trama</h2>
    <div class="small">Tip: “📜 Cambios” abre el log y permite registrar un cambio nuevo.</div>

    <?php foreach ($plots as $p): ?>
      <?php $pid=(int)$p['id']; $pcs = $plotCharsByPlot[$pid] ?? []; ?>
      <div class="plot-head" id="plot-<?= $pid ?>">
        <h3><?= h($p['name']) ?></h3>
        <span class="badge"><?= ((int)$p['active']===1) ? 'Activa' : 'Inactiva' ?></span>
        <span class="badge">Orden: <?= (int)($p['order'] ?? 0) ?></span>
        <button class="btn btn-green" type="button" onclick="openCharCreate(<?= $pid ?>)">➕ Añadir personaje</button>
      </div>

      <table class="table">
        <thead>
          <tr>
            <th style="width:60px;">ID</th>
            <th>Alias</th>
            <th>Base</th>
            <th style="width:80px;">Act.</th>
            <th style="width:420px;">Stats (base)</th>
            <th style="width:220px;">Acciones</th>
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
            <td><b style="color:#33FFFF;"><?= $cid ?></b></td>
            <td><?= h($alias !== '' ? $alias : '(sin alias)') ?></td>
            <td><?= h($baseLabel !== '' ? $baseLabel : ('#'.(int)$pc['base_char_id'])) ?></td>
            <td><?= ((int)$pc['active']===1) ? '<span class="badge">Sí</span>' : '<span class="badge off">No</span>' ?></td>
            <td style="white-space:normal;">
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
          <tr><td colspan="6" style="color:#bbb;">(No hay personajes en esta trama)</td></tr>
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
    <form method="post" id="plotForm" style="margin:0;">
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
          <label style="padding-top:6px;">
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
    <form method="post" id="charForm" style="margin:0;">
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
          <label style="padding-top:6px;">
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

    <div style="border-top:1px solid #000088; padding-top:10px; margin-top:10px;">
      <h3 style="margin:0 0 8px;">Registrar cambio</h3>
      <form method="post" id="chgForm" style="margin:0;">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <input type="hidden" name="action" value="add_change">
        <input type="hidden" name="plot_char_id" id="chg_char_id" value="0">

        <div class="grid" style="grid-template-columns: 1fr 1fr 2fr;">
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

        <div class="modal-actions" style="margin-top:8px;">
          <button class="btn btn-red" type="button" onclick="closeModal('mbChg')">Cerrar</button>
          <button class="btn btn-green" type="submit">Registrar</button>
        </div>
      </form>
    </div>

    <div style="border-top:1px solid #000088; padding-top:10px; margin-top:10px;">
      <h3 style="margin:0 0 8px;">Historial</h3>
      <div id="chgHistory" style="max-height:320px; overflow:auto; border:1px solid #000088; border-radius:10px; padding:8px;"></div>
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
  $('plot_order').value = p.order || 0;
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
    var html = '<table class="table" style="margin:0;">' +
      '<tr><th style="width:140px;">Fecha</th><th style="width:90px;">Recurso</th><th style="width:70px;">Valor</th><th>Notas</th></tr>';
    list.forEach(function(c){
      html += '<tr>' +
        '<td>'+ (fmtDate(c.created_at) || '') +'</td>' +
        '<td>'+ (c.resource || '') +'</td>' +
        '<td><b style="color:#33FFFF;">'+ (c.value || 0) +'</b></td>' +
        '<td style="white-space:normal;">'+ (c.notes ? String(c.notes).replace(/</g,'&lt;').replace(/>/g,'&gt;') : '<span class="small">—</span>') +'</td>' +
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
