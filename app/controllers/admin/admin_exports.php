<?php
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
include_once(__DIR__ . '/../../partials/admin/admin_styles.php');
require_once(__DIR__ . '/../../domains/admin_exports/queries.php');

if (!hg_admin_require_db($link)) { return; }
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }

if (!function_exists('hg_admin_exports_h')) {
    function hg_admin_exports_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$ADMIN_CSRF_SESSION_KEY = 'csrf_admin_exports';
$CSRF = function_exists('hg_admin_ensure_csrf_token')
    ? hg_admin_ensure_csrf_token($ADMIN_CSRF_SESSION_KEY)
    : ($_SESSION[$ADMIN_CSRF_SESSION_KEY] ??= bin2hex(random_bytes(16)));

$allowedKinds = ['characters', 'seasons', 'chapters', 'documents', 'inventory'];

$ajaxFlag = filter_input(INPUT_GET, 'ajax', FILTER_UNSAFE_RAW);\nif ((string)$ajaxFlag === '1') {
    if (function_exists('hg_admin_require_session')) hg_admin_require_session(true);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        hg_admin_json_error('Método inválido', 405, ['method' => 'POST requerido']);
    }

    $payload = function_exists('hg_admin_read_json_payload') ? hg_admin_read_json_payload() : [];
    $csrfToken = function_exists('hg_admin_extract_csrf_token')
        ? hg_admin_extract_csrf_token($payload)
        : (string)($_POST['csrf'] ?? '');
    $csrfOk = function_exists('hg_admin_csrf_valid')
        ? hg_admin_csrf_valid($csrfToken, $ADMIN_CSRF_SESSION_KEY)
        : (is_string($csrfToken) && $csrfToken !== '' && isset($_SESSION[$ADMIN_CSRF_SESSION_KEY]) && hash_equals($_SESSION[$ADMIN_CSRF_SESSION_KEY], $csrfToken));
    if (!$csrfOk) {
        hg_admin_json_error('CSRF inválido. Recarga la página.', 403, ['csrf' => 'invalid']);
    }

    $action = trim((string)($payload['action'] ?? ''));
    $kind = trim((string)($payload['kind'] ?? ''));
    if (!in_array($kind, $allowedKinds, true)) {
        hg_admin_json_error('Tipo de exportación no soportado.', 400, ['kind' => 'unsupported']);
    }

    $chronicleIds = hg_admin_exports_normalize_ids($payload['chronicle_ids'] ?? []);
    $includeUnscoped = !empty($payload['include_unscoped']);
    if ($kind !== 'inventory' && !$chronicleIds && !$includeUnscoped) {
        hg_admin_json_error('Selecciona al menos una crónica o incluye material sin crónica.', 400, ['chronicles' => 'required']);
    }

    if ($action === 'count') {
        $total = hg_admin_exports_count($link, $kind, $chronicleIds, $includeUnscoped);
        hg_admin_json_success([
            'total' => $total,
            'batch_limit' => hg_admin_exports_batch_limit($kind),
        ], 'Conteo preparado.');
    }

    if ($action === 'chunk') {
        $afterId = max(0, (int)($payload['cursor'] ?? 0));
        $chunk = hg_admin_exports_chunk($link, $kind, $chronicleIds, $includeUnscoped, $afterId);
        hg_admin_json_success($chunk, 'Lote exportado.');
    }

    hg_admin_json_error('Acción no válida.', 400, ['action' => 'unsupported']);
}

$chronicles = hg_admin_exports_chronicles($link);
$exportCards = [
    'characters' => [
        'title' => 'Personajes',
        'description' => 'Biografías, ficha, rasgos, recursos, poderes, inventario, relaciones, participación y documentación vinculada.',
        'filename' => 'personajes',
    ],
    'seasons' => [
        'title' => 'Temporadas',
        'description' => 'Datos de temporada, sinopsis, estado, protagonistas y listado de episodios.',
        'filename' => 'temporadas',
    ],
    'chapters' => [
        'title' => 'Episodios',
        'description' => 'Temporada, fecha de juego, resumen, participantes y eventos relacionados.',
        'filename' => 'episodios',
    ],
    'documents' => [
        'title' => 'Documentos',
        'description' => 'Título, sección, origen, fuente, contenido y personajes relacionados. La crónica se infiere por sus personajes vinculados.',
        'filename' => 'documentos',
    ],
    'inventory' => [
        'title' => 'Inventario',
        'description' => 'Todos los objetos, sus datos de ficha, descripción, origen y portadores. No aplica filtro de crónica.',
        'filename' => 'inventario',
    ],
];

admin_panel_open('Exportación documental');
?>
<div class="adm-callout">
  Genera ficheros de texto plano para trabajar sobre la documentación nuclear de Heaven's Gate.
  La exportación se procesa en lotes pequeños y consecutivos para evitar consultas masivas a la base de datos.
</div>

<fieldset class="bioSeccion adm-export-scope">
  <legend>&nbsp;Crónicas incluidas&nbsp;</legend>
  <div class="adm-toolbar adm-export-toolbar">
    <button class="btn" type="button" id="exportSelectAll">Seleccionar todas</button>
    <button class="btn" type="button" id="exportSelectNone">Limpiar</button>
    <span class="adm-note">El filtro se aplica a personajes, temporadas, episodios y documentos.</span>
  </div>

  <div class="adm-export-chronicles">
    <?php foreach ($chronicles as $chronicle): ?>
      <label class="adm-export-check">
        <input
          type="checkbox"
          name="export_chronicles[]"
          value="<?= (int)$chronicle['id'] ?>"
          data-name="<?= hg_admin_exports_h($chronicle['name']) ?>"
          checked
        >
        <span><?= hg_admin_exports_h($chronicle['name']) ?></span>
      </label>
    <?php endforeach; ?>
  </div>

  <label class="adm-export-check adm-export-unscoped">
    <input type="checkbox" id="exportIncludeUnscoped" checked>
    <span>Incluir material sin crónica / transversal</span>
  </label>
  <p class="adm-note">
    En Documentos, una entrada pertenece a una crónica si está vinculada a alguno de sus personajes.
    Los documentos sin personajes vinculados cuentan como material transversal.
  </p>
</fieldset>

<div class="adm-export-grid">
  <?php foreach ($exportCards as $kind => $card): ?>
    <section class="bioSheetPower adm-export-card" data-export-kind="<?= hg_admin_exports_h($kind) ?>" data-export-filename="<?= hg_admin_exports_h($card['filename']) ?>">
      <div>
        <h3><?= hg_admin_exports_h($card['title']) ?></h3>
        <p class="adm-note"><?= hg_admin_exports_h($card['description']) ?></p>
      </div>
      <div class="adm-export-progress-wrap">
        <progress class="adm-export-progress" max="1" value="0"></progress>
        <span class="adm-export-status">Preparado.</span>
      </div>
      <div class="adm-export-actions">
        <button class="btn btn-green adm-export-start" type="button">Exportar TXT</button>
        <button class="btn adm-export-cancel" type="button" hidden>Cancelar</button>
      </div>
    </section>
  <?php endforeach; ?>
</div>

<style>
.adm-export-scope { margin: 18px 0 22px; }
.adm-export-toolbar { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
.adm-export-chronicles { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:8px 14px; margin:14px 0; }
.adm-export-check { display:flex; gap:8px; align-items:center; min-width:0; }
.adm-export-check span { min-width:0; overflow-wrap:anywhere; }
.adm-export-unscoped { margin-top:10px; }
.adm-export-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px 16px; }
.adm-export-card { width:auto !important; min-height:170px; box-sizing:border-box; margin:0 !important; padding:14px; display:flex; flex-direction:column; justify-content:space-between; }
.adm-export-card h3 { margin-top:0; }
.adm-export-progress-wrap { display:grid; gap:6px; margin:14px 0; }
.adm-export-progress { width:100%; height:14px; }
.adm-export-status { color:#9dd; font-size:12px; }
.adm-export-actions { display:flex; gap:8px; flex-wrap:wrap; }
@media (max-width: 900px) {
  .adm-export-chronicles { grid-template-columns:repeat(2,minmax(0,1fr)); }
}
@media (max-width: 700px) {
  .adm-export-grid, .adm-export-chronicles { grid-template-columns:1fr; }
}
</style>

<script src="/assets/js/admin/admin-http.js"></script>
<script>
(function () {
  window.ADMIN_CSRF_TOKEN = <?= json_encode($CSRF, JSON_UNESCAPED_UNICODE) ?>;

  var endpoint = '/talim?s=admin_exports&ajax=1';
  var cancelled = Object.create(null);
  var labels = {
    characters: 'Personajes',
    seasons: 'Temporadas',
    chapters: 'Episodios',
    documents: 'Documentos',
    inventory: 'Inventario'
  };

  function selectedChronicles() {
    return Array.prototype.map.call(
      document.querySelectorAll('input[name="export_chronicles[]"]:checked'),
      function (el) { return Number(el.value || 0); }
    ).filter(Boolean);
  }

  function selectedChronicleNames() {
    return Array.prototype.map.call(
      document.querySelectorAll('input[name="export_chronicles[]"]:checked'),
      function (el) { return String(el.getAttribute('data-name') || '').trim(); }
    ).filter(Boolean);
  }

  function includeUnscoped() {
    var el = document.getElementById('exportIncludeUnscoped');
    return !!(el && el.checked);
  }

  function dateStamp() {
    var d = new Date();
    return [
      d.getFullYear(),
      String(d.getMonth() + 1).padStart(2, '0'),
      String(d.getDate()).padStart(2, '0')
    ].join('-');
  }

  function makeHeader(kind, names, unscoped) {
    var scope = kind === 'inventory'
      ? 'Global (inventario sin crónica)'
      : (names.length ? names.join(', ') : 'Ninguna crónica seleccionada');
    if (kind !== 'inventory' && unscoped) scope += ' + material sin crónica / transversal';

    return [
      "HEAVEN'S GATE — EXPORTACIÓN DOCUMENTAL",
      'Tipo: ' + (labels[kind] || kind),
      'Ámbito: ' + scope,
      'Generado: ' + new Date().toLocaleString('es-ES'),
      '',
      ''
    ].join('\n');
  }

  function downloadTxt(filename, chunks) {
    var blob = new Blob(['\uFEFF'].concat(chunks), { type: 'text/plain;charset=utf-8' });
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
  }

  function setCardState(card, state) {
    var start = card.querySelector('.adm-export-start');
    var cancel = card.querySelector('.adm-export-cancel');
    if (start) start.disabled = state === 'running';
    if (cancel) cancel.hidden = state !== 'running';
    if (state === 'running') card.classList.add('adm-loading');
    else card.classList.remove('adm-loading');
  }

  async function runExport(card) {
    var kind = String(card.getAttribute('data-export-kind') || '');
    var filenameBase = String(card.getAttribute('data-export-filename') || kind);
    var progress = card.querySelector('.adm-export-progress');
    var status = card.querySelector('.adm-export-status');
    var chronicleIds = kind === 'inventory' ? [] : selectedChronicles();
    var chronicleNames = kind === 'inventory' ? [] : selectedChronicleNames();
    var unscoped = kind === 'inventory' ? false : includeUnscoped();

    if (kind !== 'inventory' && !chronicleIds.length && !unscoped) {
      HGAdminHttp.notify('Selecciona al menos una crónica o incluye material transversal.', 'err', 3200);
      return;
    }

    cancelled[kind] = false;
    setCardState(card, 'running');
    if (progress) { progress.max = 1; progress.value = 0; }
    if (status) status.textContent = 'Calculando registros...';

    try {
      var countPayload = await HGAdminHttp.postAction(endpoint, 'count', {
        kind: kind,
        chronicle_ids: chronicleIds,
        include_unscoped: unscoped
      });
      var total = Number(countPayload && countPayload.data ? countPayload.data.total : 0);

      if (!total) {
        if (status) status.textContent = 'No hay registros para este ámbito.';
        HGAdminHttp.notify('No hay datos que exportar.', 'info');
        return;
      }

      if (progress) { progress.max = total; progress.value = 0; }
      var chunks = [makeHeader(kind, chronicleNames, unscoped)];
      var cursor = 0;
      var processed = 0;

      while (!cancelled[kind]) {
        var chunkPayload = await HGAdminHttp.postAction(endpoint, 'chunk', {
          kind: kind,
          chronicle_ids: chronicleIds,
          include_unscoped: unscoped,
          cursor: cursor
        });
        var data = (chunkPayload && chunkPayload.data) ? chunkPayload.data : {};
        var count = Number(data.count || 0);
        var text = String(data.text || '');
        if (text) chunks.push(text);

        processed += count;
        cursor = Number(data.next_cursor || cursor);
        if (progress) progress.value = Math.min(processed, total);
        if (status) status.textContent = 'Procesados ' + Math.min(processed, total) + ' de ' + total + ' registros...';

        if (data.done || count === 0) break;
      }

      if (cancelled[kind]) {
        if (status) status.textContent = 'Exportación cancelada.';
        return;
      }

      downloadTxt('heavens-gate-' + filenameBase + '-' + dateStamp() + '.txt', chunks);
      if (progress) progress.value = total;
      if (status) status.textContent = 'Completado: ' + total + ' registros.';
      HGAdminHttp.notify((labels[kind] || kind) + ': exportación completada.', 'ok', 3000);
    } catch (e) {
      if (status) status.textContent = 'Error durante la exportación.';
      HGAdminHttp.notify(HGAdminHttp.errorMessage(e), 'err', 4200);
    } finally {
      setCardState(card, 'idle');
    }
  }

  document.getElementById('exportSelectAll')?.addEventListener('click', function () {
    document.querySelectorAll('input[name="export_chronicles[]"]').forEach(function (el) { el.checked = true; });
  });

  document.getElementById('exportSelectNone')?.addEventListener('click', function () {
    document.querySelectorAll('input[name="export_chronicles[]"]').forEach(function (el) { el.checked = false; });
  });

  document.querySelectorAll('[data-export-kind]').forEach(function (card) {
    var kind = String(card.getAttribute('data-export-kind') || '');
    card.querySelector('.adm-export-start')?.addEventListener('click', function () { runExport(card); });
    card.querySelector('.adm-export-cancel')?.addEventListener('click', function () {
      cancelled[kind] = true;
      var status = card.querySelector('.adm-export-status');
      if (status) status.textContent = 'Cancelando al terminar el lote actual...';
    });
  });
}());
</script>
<?php admin_panel_close(); ?>
