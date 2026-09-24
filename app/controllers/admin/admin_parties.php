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

include_once(__DIR__ . '/../../helpers/admin_ajax.php');
if (!hg_admin_require_db($link)) { return; }
include_once(__DIR__ . '/../../helpers/pretty.php');
include_once(__DIR__ . '/../../domains/parties/admin.php');
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
include_once(__DIR__ . '/../../partials/admin/admin_styles.php');
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
if (function_exists('hg_admin_ensure_csrf_token')) {
    $_SESSION['csrf'] = hg_admin_ensure_csrf_token('csrf_admin_parties');
} elseif (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
function csrf_check(): bool {
    $payload = function_exists('hg_admin_read_json_payload') ? hg_admin_read_json_payload() : [];
    $t = function_exists('hg_admin_extract_csrf_token')
        ? hg_admin_extract_csrf_token($payload)
        : (string)($_POST['csrf'] ?? '');
    return is_string($t) && hash_equals($_SESSION['csrf'] ?? '', $t);
}

/* -----------------------------
   Compatibilidad de esquema
----------------------------- */
$partyMembersTable = '';
foreach (['fact_party_members', 'party_members'] as $t) {
    if (hg_parties_admin_has_table($link, $t)) { $partyMembersTable = $t; break; }
}
$partyChangesTable = '';
foreach (['fact_party_members_changes', 'party_members_changes'] as $t) {
    if (hg_parties_admin_has_table($link, $t)) { $partyChangesTable = $t; break; }
}
$partyFkCol = ($partyMembersTable !== '' && hg_parties_admin_has_column($link, $partyMembersTable, 'plot_id'))
    ? 'plot_id'
    : (($partyMembersTable !== '' && hg_parties_admin_has_column($link, $partyMembersTable, 'party_id')) ? 'party_id' : '');
$changesFkCol = ($partyChangesTable !== '' && hg_parties_admin_has_column($link, $partyChangesTable, 'plot_char_id'))
    ? 'plot_char_id'
    : (($partyChangesTable !== '' && hg_parties_admin_has_column($link, $partyChangesTable, 'party_member_id')) ? 'party_member_id' : '');
$hasPartiesSchema = ($partyMembersTable !== '' && $partyFkCol !== '' && $partyChangesTable !== '' && $changesFkCol !== '');

$isAjaxRequest = is_post() && (
    ((string)($_POST['ajax'] ?? '') === '1')
    || (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest')
);
function parties_fail(string $msg, array $redirectExtra = [], int $status = 400): void {
    global $isAjaxRequest;
    if ($isAjaxRequest) {
        if (function_exists('hg_admin_json_error')) hg_admin_json_error($msg, $status);
        header('Content-Type: application/json; charset=UTF-8');
        http_response_code($status);
        echo json_encode(['ok'=>false,'message'=>$msg,'error'=>$msg], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        exit;
    }
    flash_add('error', $msg);
    header("Location: ".build_redirect_url($redirectExtra));
    exit;
}
function parties_ok(string $msg, array $redirectExtra = [], array $data = []): void {
    global $isAjaxRequest;
    if ($isAjaxRequest) {
        if (function_exists('hg_admin_json_success')) hg_admin_json_success($data, $msg);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['ok'=>true,'message'=>$msg,'data'=>$data], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        exit;
    }
    flash_add('ok', $msg);
    header("Location: ".build_redirect_url($redirectExtra));
    exit;
}

/* -----------------------------
   Seguridad basica POST
----------------------------- */
$action = is_post() ? (string)($_POST['action'] ?? '') : '';
if (is_post()) {
    if ($isAjaxRequest && function_exists('hg_admin_require_session')) {
        hg_admin_require_session(true);
    }
    if (!$action) {
        parties_fail('Falta action.');
    }
    if (!csrf_check()) {
        parties_fail('CSRF invalido (recarga la pagina).', [], 403);
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
        parties_fail('El nombre de la trama es obligatorio.', ['open_plot'=>$id ?: 1]);
    }

    $result = hg_parties_admin_save_plot($link,$id,$name,$desc,$act,$ord);
    if (empty($result['ok'])) parties_fail((string)$result['error'], ['open_plot'=>$id ?: 1]);
    $savedId = (int)$result['id'];
    parties_ok(
        !empty($result['created']) ? 'Trama creada (#'.$savedId.').' : 'Trama actualizada (#'.$savedId.').',
        ['open_plot'=>null, 'focus_plot'=>$savedId],
        ['id'=>$savedId, 'focus_plot'=>$savedId]
    );
}
if ($action === 'save_plot_char') {
    if (!$hasPartiesSchema) {
        parties_fail('Esquema de tramas/personajes no compatible.');
    }
    $id     = (int)($_POST['id'] ?? 0);
    $plot   = (int)($_POST['plot_id'] ?? 0);
    $base   = (int)($_POST['base_char_id'] ?? 0);
    $alias  = trim((string)($_POST['alias'] ?? ''));
    $notes  = trim((string)($_POST['notes'] ?? ''));
    $act    = isset($_POST['active']) ? 1 : 0;

    if ($plot <= 0) { parties_fail('Debes seleccionar una trama.'); }
    if ($base <= 0) { parties_fail('Debes seleccionar un personaje base.', ['focus_plot'=>$plot, 'open_char'=>1, 'plot'=>$plot]); }

    $stats = ['hp','rage','gnosis','glamour','mana','blood','wp'];
    $vals = [];
    foreach ($stats as $s) $vals[$s] = (int)($_POST["m_$s"] ?? 0);
    if ($vals['hp'] < 0) $vals['hp'] = 0;

    $result = hg_parties_admin_save_member($link,$partyMembersTable,$partyFkCol,$id,$plot,$base,$alias,$vals,$notes,$act);
    if (empty($result['ok'])) parties_fail((string)$result['error'], ['focus_plot'=>$plot, 'open_char'=>1, 'plot'=>$plot]);
    $savedId=(int)$result['id'];
    parties_ok(
        !empty($result['created']) ? 'Personaje anadido a trama (#'.$savedId.').' : 'Personaje en trama actualizado (#'.$savedId.').',
        ['focus_plot'=>$plot, 'open_char'=>null, 'plot'=>null],
        ['id'=>$savedId, 'plot_id'=>$plot]
    );
}
if ($action === 'add_change') {
    if (!$hasPartiesSchema) {
        parties_fail('Esquema de cambios no compatible.');
    }
    $cid  = (int)($_POST['plot_char_id'] ?? 0);
    $res  = (string)($_POST['resource'] ?? '');
    $val  = (int)($_POST['value'] ?? 0);
    $note = trim((string)($_POST['notes'] ?? ''));

    $allowed = ['hp','rage','gnosis','blood','glamour','mana','wp'];
    if ($cid <= 0) {
        parties_fail('Falta plot_char_id.');
    }
    if (!in_array($res, $allowed, true)) {
        parties_fail('Recurso invalido.', ['open_changes'=>$cid]);
    }

    $result = hg_parties_admin_add_change($link,$partyChangesTable,$changesFkCol,$cid,$res,$val,$note);
    if (empty($result['ok'])) parties_fail((string)$result['error'], ['open_changes'=>$cid]);
    parties_ok('Cambio registrado.', ['open_changes'=>$cid], ['id'=>(int)$result['id'], 'plot_char_id'=>$cid]);
}
/* -----------------------------
   Cargas de datos
----------------------------- */

$state = hg_parties_admin_load_state($link,$partyMembersTable,$partyFkCol,$partyChangesTable,$changesFkCol,$hasPartiesSchema);
$plots = $state['plots'];
$baseChars = $state['baseChars'];
$plotCharsByPlot = $state['plotCharsByPlot'];
$plotCharsFlat = $state['plotCharsFlat'];
$changesByPlotChar = $state['changesByPlotChar'];

if (($_GET['ajax'] ?? '') === 'state') {
    if (function_exists('hg_admin_require_session')) {
        hg_admin_require_session(true);
    }
    $plotsMapOut = [];
    foreach ($plots as $p) {
        $plotsMapOut[(int)$p['id']] = $p;
    }
    if (function_exists('hg_admin_json_success')) {
        hg_admin_json_success([
            'plots' => $plots,
            'plotsMap' => $plotsMapOut,
            'plotCharsByPlot' => $plotCharsByPlot,
            'plotChars' => $plotCharsFlat,
            'changesByChar' => $changesByPlotChar,
        ], 'OK');
    }
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'ok' => true,
        'data' => [
            'plots' => $plots,
            'plotsMap' => $plotsMapOut,
            'plotCharsByPlot' => $plotCharsByPlot,
            'plotChars' => $plotCharsFlat,
            'changesByChar' => $changesByPlotChar,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
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

  <div class="toolbar adm-mb-8">
    <input class="inp" type="text" id="filterPlots" placeholder="Filtrar tramas (nombre, activa, orden...)">
    <input class="inp" type="text" id="filterPlotChars" placeholder="Filtrar personajes en trama (alias, base, stats...)">
  </div>

  <!-- LISTADO DE TRAMAS -->
  <div class="adm-table-scroll adm-sticky-actions" tabindex="0" aria-label="Tabla de tramas"><table class="table adm-wide-table" id="plotsTable">
    <thead>
      <tr>
        <th class="adm-w-60">ID</th>
        <th class="adm-col-name">Nombre</th>
        <th class="adm-w-90">Activa</th>
        <th class="adm-w-80">Orden</th>
        <th class="adm-th-actions" title="Acciones">Acc.</th>
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
        <td class="adm-cell-actions"><div class="adm-actions-inline">
          <button class="btn adm-icon-btn" type="button" onclick='openPlotEdit(<?= json_encode($p, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE) ?>)' aria-label="Editar trama" title="Editar trama">✏</button>
          <button class="btn btn-green adm-icon-btn" type="button" onclick="openCharCreate(<?= $pid ?>)" aria-label="Añadir personaje" title="Añadir personaje">＋</button>
          <button class="btn btn-ghost adm-icon-btn" type="button" onclick="scrollToPlot(<?= $pid ?>)" aria-label="Ver personajes" title="Ver personajes">↓</button>
          </div></td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($plots)): ?>
      <tr><td colspan="5" class="adm-color-muted">(No hay tramas aún)</td></tr>
    <?php endif; ?>
    </tbody>
  </table></div>

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

      <div class="adm-table-scroll adm-sticky-actions" tabindex="0" aria-label="Personajes de la trama"><table class="table adm-wide-table">
        <thead>
          <tr>
            <th class="adm-w-60">ID</th>
            <th class="adm-col-name">Alias</th>
            <th class="adm-col-name">Base</th>
            <th class="adm-w-80">Act.</th>
            <th class="adm-w-420">Stats (base)</th>
            <th class="adm-th-actions" title="Acciones">Acc.</th>
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
            <td class="adm-cell-actions"><div class="adm-actions-inline">
              <button class="btn adm-icon-btn" type="button" onclick='openCharEdit(<?= json_encode($pc, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE) ?>)' aria-label="Editar personaje" title="Editar personaje">✏</button>
              <button class="btn adm-icon-btn" type="button" onclick="openChanges(<?= $cid ?>)" aria-label="Cambios" title="Cambios">📜</button>
              </div></td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($pcs)): ?>
          <tr><td colspan="6" class="adm-color-muted">(No hay personajes en esta trama)</td></tr>
        <?php endif; ?>
        </tbody>
      </table></div>
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

<?php
$adminHttpJs = '/assets/js/admin/admin-http.js';
$adminHttpJsVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . $adminHttpJs) ?: time();
?>
<script>
window.ADMIN_CSRF_TOKEN = <?= json_encode($csrf, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?= h($adminHttpJs) ?>?v=<?= (int)$adminHttpJsVer ?>"></script>

<script>
var PLOTS_MAP = <?= json_encode($plotsMap, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
var PLOT_CHARS = <?= json_encode($plotCharsFlat, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
var CHANGES_BY_CHAR = <?= json_encode($changesByPlotChar, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;

function $(id){ return document.getElementById(id); }
function openModal(id){ $(id).style.display='flex'; }
function closeModal(id){ $(id).style.display='none'; }

function textNorm(s){
  var out = String(s || '').toLowerCase();
  if (typeof out.normalize === 'function') {
    out = out.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  }
  return out.trim();
}

var BASE_CHAR_OPTIONS = (function(){
  var sel = $('char_base');
  if (!sel) return [];
  return Array.prototype.map.call(sel.options, function(opt){
    return { value: String(opt.value || ''), text: String(opt.textContent || '') };
  });
})();

function syncPlotSelect(selectedId){
  var sel = $('char_plot');
  if (!sel) return;

  var plots = Object.keys(PLOTS_MAP || {}).map(function(k){ return PLOTS_MAP[k]; }).filter(Boolean);
  plots.sort(function(a, b){
    var ao = parseInt(a && a.sort_order ? a.sort_order : 0, 10) || 0;
    var bo = parseInt(b && b.sort_order ? b.sort_order : 0, 10) || 0;
    if (bo !== ao) return bo - ao;
    var aid = parseInt(a && a.id ? a.id : 0, 10) || 0;
    var bid = parseInt(b && b.id ? b.id : 0, 10) || 0;
    return bid - aid;
  });

  var html = '<option value="0">— Selecciona —</option>';
  plots.forEach(function(p){
    var id = parseInt(p.id || 0, 10) || 0;
    var name = String(p.name || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    html += '<option value="' + id + '">' + name + '</option>';
  });
  sel.innerHTML = html;

  var wanted = String(selectedId || '0');
  sel.value = wanted;
  if (sel.value !== wanted) sel.value = '0';
}

function applyBaseFilter(){
  var sel = $('char_base');
  var inFilter = $('baseFilter');
  if (!sel || !inFilter) return;

  var q = textNorm(inFilter.value);
  var selected = String(sel.value || '0');
  var html = '';

  BASE_CHAR_OPTIONS.forEach(function(opt, idx){
    if (idx === 0) {
      html += '<option value="0">— Selecciona —</option>';
      return;
    }
    var match = !q || textNorm(opt.text).indexOf(q) !== -1 || opt.value === selected;
    if (!match) return;
    var text = opt.text.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    html += '<option value="' + opt.value + '">' + text + '</option>';
  });

  sel.innerHTML = html;
  sel.value = selected;
  if (sel.value !== selected) sel.value = '0';
}

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
  syncPlotSelect(0);
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
  applyBaseFilter();
}

function openCharCreate(plotId){
  resetCharForm();
  $('charTitle').textContent = 'Añadir personaje a trama';
  $('char_id').value = 0;
  syncPlotSelect(plotId || 0);
  if (plotId) $('char_plot').value = String(plotId);
  openModal('mbChar');
}

function openCharEdit(pc){
  resetCharForm();
  $('charTitle').textContent = 'Editar personaje en trama';
  $('char_id').value = pc.id || 0;
  syncPlotSelect(pc.plot_id || 0);
  $('char_plot').value = String(pc.plot_id || 0);
  $('char_base').value = String(pc.base_char_id || 0);
  applyBaseFilter();
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

// Filtro simple del select base (sin librerias)
$('baseFilter').addEventListener('input', function(){
  applyBaseFilter();
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

<script>
(function(){
  var plotsTbody = document.querySelector('#plotsTable tbody');
  var charsSection = document.getElementById('charsSection');
  var filterPlotsInput = document.getElementById('filterPlots');
  var filterCharsInput = document.getElementById('filterPlotChars');
  var plotForm = document.getElementById('plotForm');
  var charForm = document.getElementById('charForm');
  var chgForm = document.getElementById('chgForm');
  if (!plotsTbody || !charsSection) return;

  function esc(s){
    return String(s || '')
      .replace(/&/g,'&amp;')
      .replace(/</g,'&lt;')
      .replace(/>/g,'&gt;')
      .replace(/"/g,'&quot;')
      .replace(/'/g,'&#039;');
  }
  function baseUrlWithAjax(kind){
    var u = new URL(window.location.href);
    u.searchParams.set('ajax', kind);
    u.searchParams.set('_ts', Date.now());
    return u.toString();
  }
  function req(url, opts){
    if (window.HGAdminHttp && typeof window.HGAdminHttp.request === 'function') {
      return window.HGAdminHttp.request(url, Object.assign({
        method: 'GET',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      }, opts || {}));
    }
    return fetch(url, Object.assign({
      credentials:'same-origin',
      cache: 'no-store',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }, opts || {})).then(function(r){ return r.json(); });
  }
  function renderPlots(plots){
    if (!plots || !plots.length) {
      plotsTbody.innerHTML = '<tr><td colspan="5" class="adm-color-muted">(No hay tramas aun)</td></tr>';
      return;
    }
    var html = '';
    plots.forEach(function(p){
      var id = parseInt(p.id || 0, 10) || 0;
      var active = (parseInt(p.active || 0, 10) === 1);
      html += '<tr id="plot-row-'+id+'">';
      html += '<td><b class="adm-color-accent">'+id+'</b></td>';
      html += '<td>'+esc(p.name || '')+'</td>';
      html += '<td>'+(active ? '<span class="badge">Si</span>' : '<span class="badge off">No</span>')+'</td>';
      html += '<td>'+esc(p.sort_order || 0)+'</td>';
      html += '<td class="adm-cell-actions"><div class="adm-actions-inline">';
      html += '<button class="btn adm-icon-btn js-plot-edit" type="button" data-id="'+id+'" aria-label="Editar trama" title="Editar trama">✏</button>';
      html += '<button class="btn btn-green adm-icon-btn js-plot-add-char" type="button" data-id="'+id+'" aria-label="Añadir personaje" title="Añadir personaje">＋</button>';
      html += '<button class="btn btn-ghost adm-icon-btn js-plot-scroll" type="button" data-id="'+id+'" aria-label="Ver personajes" title="Ver personajes">↓</button>';
      html += '</div></td></tr>';
    });
    plotsTbody.innerHTML = html;
  }
  function renderChars(plots, byPlot){
    var html = '<h2 class="adm-title-sm">Personajes por Trama</h2>'
      + '<div class="small">Tip: \"Cambios\" abre el log y permite registrar un cambio nuevo.</div>';
    (plots || []).forEach(function(p){
      var pid = parseInt(p.id || 0, 10) || 0;
      var pcs = (byPlot && byPlot[String(pid)]) ? byPlot[String(pid)] : [];
      var active = (parseInt(p.active || 0, 10) === 1);
      html += '<div class="plot-head" id="plot-'+pid+'">';
      html += '<h3>'+esc(p.name || '')+'</h3>';
      html += '<span class="badge">'+(active ? 'Activa' : 'Inactiva')+'</span>';
      html += '<span class="badge">Orden: '+esc(p.sort_order || 0)+'</span>';
      html += '<button class="btn btn-green js-plot-add-char" type="button" data-id="'+pid+'">Añadir personaje</button>';
      html += '</div>';
      html += '<div class="adm-table-scroll adm-sticky-actions" tabindex="0" aria-label="Personajes de la trama"><table class="table adm-wide-table"><thead><tr>'
        + '<th class="adm-w-60">ID</th><th class="adm-col-name">Alias</th><th class="adm-col-name">Base</th><th class="adm-w-80">Act.</th><th class="adm-w-420">Stats (base)</th><th class="adm-th-actions" title="Acciones">Acc.</th>'
        + '</tr></thead><tbody>';
      if (!pcs.length) {
        html += '<tr><td colspan="6" class="adm-color-muted">(No hay personajes en esta trama)</td></tr>';
      } else {
        pcs.forEach(function(pc){
          var cid = parseInt(pc.id || 0, 10) || 0;
          var alias = String(pc.alias || '').trim() || '(sin alias)';
          var baseName = String(pc.base_nombre || '');
          var baseAlias = String(pc.base_alias || '');
          var baseLabel = baseName + (baseAlias ? ' · '+baseAlias : '');
          html += '<tr id="pc-row-'+cid+'">';
          html += '<td><b class="adm-color-accent">'+cid+'</b></td>';
          html += '<td>'+esc(alias)+'</td>';
          html += '<td>'+esc(baseLabel || ('#'+(parseInt(pc.base_char_id||0,10)||0)))+'</td>';
          html += '<td>'+((parseInt(pc.active||0,10)===1) ? '<span class="badge">Si</span>' : '<span class="badge off">No</span>')+'</td>';
          html += '<td class="adm-ws-normal">'
            + '<span class="badge">HP '+(parseInt(pc.m_hp||0,10)||0)+'</span> '
            + '<span class="badge">Rabia '+(parseInt(pc.m_rage||0,10)||0)+'</span> '
            + '<span class="badge">Gnosis '+(parseInt(pc.m_gnosis||0,10)||0)+'</span> '
            + '<span class="badge">Glamour '+(parseInt(pc.m_glamour||0,10)||0)+'</span> '
            + '<span class="badge">Mana '+(parseInt(pc.m_mana||0,10)||0)+'</span> '
            + '<span class="badge">Sangre '+(parseInt(pc.m_blood||0,10)||0)+'</span> '
            + '<span class="badge">FV '+(parseInt(pc.m_wp||0,10)||0)+'</span>'
            + '</td>';
          html += '<td class="adm-cell-actions"><div class="adm-actions-inline"><button class="btn adm-icon-btn js-char-edit" type="button" data-id="'+cid+'" aria-label="Editar personaje" title="Editar personaje">✏</button><button class="btn adm-icon-btn js-char-changes" type="button" data-id="'+cid+'" aria-label="Cambios" title="Cambios">📜</button></div></td>';
          html += '</tr>';
        });
      }
      html += '</tbody></table></div>';
    });
    charsSection.innerHTML = html;
  }
  function applyFilters(){
    var qPlot = (filterPlotsInput && filterPlotsInput.value ? filterPlotsInput.value : '').toLowerCase().trim();
    var qChar = (filterCharsInput && filterCharsInput.value ? filterCharsInput.value : '').toLowerCase().trim();

    Array.prototype.forEach.call(plotsTbody.querySelectorAll('tr'), function(tr){
      if (!qPlot) {
        tr.style.display = '';
        return;
      }
      var txt = (tr.textContent || '').toLowerCase();
      tr.style.display = txt.indexOf(qPlot) !== -1 ? '' : 'none';
    });

    Array.prototype.forEach.call(charsSection.querySelectorAll('.plot-head'), function(head){
      var table = head.nextElementSibling;
      if (!table || String(table.tagName).toUpperCase() !== 'TABLE') return;
      var rows = table.querySelectorAll('tbody tr');
      var visibleCount = 0;

      Array.prototype.forEach.call(rows, function(row){
        var isEmptyRow = !!row.querySelector('td[colspan]');
        if (!qChar) {
          row.style.display = '';
          if (!isEmptyRow) visibleCount++;
          return;
        }
        if (isEmptyRow) {
          row.style.display = 'none';
          return;
        }
        var txt = (row.textContent || '').toLowerCase();
        var show = txt.indexOf(qChar) !== -1;
        row.style.display = show ? '' : 'none';
        if (show) visibleCount++;
      });

      if (!qChar) {
        head.style.display = '';
        table.style.display = '';
        return;
      }

      var plotTxt = (head.textContent || '').toLowerCase();
      var showSection = (plotTxt.indexOf(qChar) !== -1) || (visibleCount > 0);
      head.style.display = showSection ? '' : 'none';
      table.style.display = showSection ? '' : 'none';
    });
  }
  function bindDynamicClicks(){
    plotsTbody.addEventListener('click', function(ev){
      var t = ev.target;
      if (!t) return;
      if (t.classList.contains('js-plot-edit')) {
        var id = String(t.getAttribute('data-id') || '');
        if (PLOTS_MAP[id]) openPlotEdit(PLOTS_MAP[id]);
      } else if (t.classList.contains('js-plot-add-char')) {
        openCharCreate(parseInt(t.getAttribute('data-id') || '0', 10) || 0);
      } else if (t.classList.contains('js-plot-scroll')) {
        scrollToPlot(parseInt(t.getAttribute('data-id') || '0', 10) || 0);
      }
    });
    charsSection.addEventListener('click', function(ev){
      var t = ev.target;
      if (!t) return;
      if (t.classList.contains('js-plot-add-char')) {
        openCharCreate(parseInt(t.getAttribute('data-id') || '0', 10) || 0);
      } else if (t.classList.contains('js-char-edit')) {
        var id = String(t.getAttribute('data-id') || '');
        if (PLOT_CHARS[id]) openCharEdit(PLOT_CHARS[id]);
      } else if (t.classList.contains('js-char-changes')) {
        openChanges(parseInt(t.getAttribute('data-id') || '0', 10) || 0);
      }
    });
  }
  async function refreshState(){
    var payload = await req(baseUrlWithAjax('state'), { method:'GET' });
    var data = payload && payload.data ? payload.data : payload;
    if (!payload || payload.ok === false || !data) {
      throw new Error((payload && (payload.message || payload.error)) || 'No se pudo refrescar el estado');
    }
    PLOTS_MAP = data.plotsMap || {};
    PLOT_CHARS = data.plotChars || {};
    CHANGES_BY_CHAR = data.changesByChar || {};
    renderPlots(data.plots || []);
    renderChars(data.plots || [], data.plotCharsByPlot || {});
    if (document.getElementById('mbChar') && document.getElementById('mbChar').style.display === 'flex') {
      syncPlotSelect($('char_plot') ? $('char_plot').value : 0);
    } else {
      syncPlotSelect(0);
    }
    applyFilters();
  }
  function submitAjaxForm(form, onSuccess){
    form.addEventListener('submit', function(ev){
      ev.preventDefault();
      var fd = new FormData(form);
      fd.set('ajax', '1');
      req(window.location.pathname + window.location.search, { method:'POST', body: fd, loadingEl: form })
        .then(function(payload){
          if (!payload || payload.ok === false) throw payload || new Error('Error');
          return refreshState().then(function(){
            if (typeof onSuccess === 'function') onSuccess(payload);
          });
        })
        .catch(function(err){
          var msg = (window.HGAdminHttp && window.HGAdminHttp.errorMessage)
            ? window.HGAdminHttp.errorMessage(err)
            : ((err && (err.message || err.error)) || 'Error');
          alert(msg);
        });
    });
  }

  bindDynamicClicks();
  if (filterPlotsInput) filterPlotsInput.addEventListener('input', applyFilters);
  if (filterCharsInput) filterCharsInput.addEventListener('input', applyFilters);
  applyFilters();
  if (plotForm) submitAjaxForm(plotForm, function(){ closeModal('mbPlot'); });
  if (charForm) submitAjaxForm(charForm, function(){ closeModal('mbChar'); });
  if (chgForm) {
    submitAjaxForm(chgForm, function(){
      var cid = parseInt((document.getElementById('chg_char_id') || {}).value || '0', 10) || 0;
      if (cid > 0) openChanges(cid);
    });
  }
})();
</script>

<?php
// Si quieres, puedes imprimir un recordatorio de orden narrativo aquí,
// pero lo dejo fuera para que este archivo sea puramente admin.
?>




