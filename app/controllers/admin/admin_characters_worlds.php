<?php
// admin_characters_worlds.php - Asignacion masiva de cronica y realidad en personajes.

include_once(__DIR__ . '/../../helpers/admin_ajax.php');
if (!hg_admin_require_db($link)) { return; }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
include_once(__DIR__ . '/../../domains/characters/admin_worlds.php');

$isAjaxRequest = (
    (isset($_GET['ajax']) && (string)$_GET['ajax'] === '1')
    || (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest')
    || ((string)($_POST['ajax'] ?? '') === 'save_character_world')
);
$ADMIN_CSRF_SESSION_KEY = 'csrf_admin_characters_worlds';
$ADMIN_CSRF_TOKEN = function_exists('hg_admin_ensure_csrf_token')
    ? hg_admin_ensure_csrf_token($ADMIN_CSRF_SESSION_KEY)
    : (string)($_SESSION[$ADMIN_CSRF_SESSION_KEY] ?? '');

if (!function_exists('hg_acw_h')) {
    function hg_acw_h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['ajax'] ?? '') === 'save_character_world')) {
    if (ob_get_level() === 0) { ob_start(); }
    header('Content-Type: application/json; charset=UTF-8');

    $jsonExit = function(array $payload){
        $noise = '';
        if (ob_get_level() > 0) { $noise = (string)ob_get_clean(); }
        if (trim($noise) !== '') { $payload['_debug_noise'] = substr(trim($noise), 0, 1200); }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    };

    if ($isAjaxRequest && function_exists('hg_admin_require_session')) {
        hg_admin_require_session(true);
    }
    $csrfToken = function_exists('hg_admin_extract_csrf_token')
        ? hg_admin_extract_csrf_token($_POST)
        : (string)($_POST['csrf'] ?? '');
    $csrfOk = function_exists('hg_admin_csrf_valid')
        ? hg_admin_csrf_valid($csrfToken, $ADMIN_CSRF_SESSION_KEY)
        : (is_string($csrfToken) && $csrfToken !== '' && isset($_SESSION[$ADMIN_CSRF_SESSION_KEY]) && hash_equals((string)$_SESSION[$ADMIN_CSRF_SESSION_KEY], $csrfToken));
    if (!$csrfOk) {
        $jsonExit(['ok' => false, 'msg' => 'CSRF inválido']);
    }

    $result = hg_acw_save_character_world(
        $link,
        (int)($_POST['character_id'] ?? 0),
        (int)($_POST['chronicle_id'] ?? 0),
        (int)($_POST['reality_id'] ?? 0)
    );
    $jsonExit($result);

}

$state = hg_acw_load_state($link);
$hasRealitySchema = !empty($state['hasRealitySchema']);
$chronicles = $state['chronicles'];
$realities = $state['realities'];
$organizations = $state['organizations'];
$characters = $state['characters'];
?>

<div class="worlds-wrap">
  <div class="worlds-head">
    <h2>Asignacion masiva de cronica y realidad</h2>
    <div class="worlds-note">Actualizacion por fila con Ajax, sin recargar</div>
  </div>

  <?php if (!$hasRealitySchema): ?>
    <div class="warn-box">
      Falta esquema. Necesario: tabla <code>dim_realities</code> y columna <code>fact_characters.reality_id</code>.
    </div>
  <?php else: ?>
    <div class="worlds-filter">
      <select id="f-chronicle-worlds">
        <option value="0">Crónica: Todas</option>
        <?php foreach ($chronicles as $o):
            $oid = (int)($o['id'] ?? 0);
            $on = (string)($o['name'] ?? '');
        ?>
          <option value="<?= $oid ?>"><?= hg_acw_h($on) ?></option>
        <?php endforeach; ?>
      </select>
      <select id="f-org-worlds">
        <option value="0">Organización: Todas</option>
        <?php foreach ($organizations as $o):
            $oid = (int)($o['id'] ?? 0);
            $on = (string)($o['name'] ?? '');
        ?>
          <option value="<?= $oid ?>"><?= hg_acw_h($on) ?></option>
        <?php endforeach; ?>
      </select>
      <input id="f-search-worlds" type="text" placeholder="Buscar por nombre, id o pretty_id...">
    </div>

    <div class="worlds-table-wrap" tabindex="0" aria-label="Tabla de mundos de personajes">
      <table class="worlds-table adm-wide-table" id="worlds-table">
        <thead>
          <tr>
            <th class="adm-w-80">ID</th>
            <!--<th class="adm-w-190">Pretty ID</th>-->
            <th class="adm-col-name">Personaje</th>
            <th class="adm-w-220">Organización</th>
            <th class="adm-w-280">Crónica</th>
            <th class="adm-w-280">Realidad</th>
            <th class="adm-w-140">Estado</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($characters as $c):
            $id = (int)($c['id'] ?? 0);
            $pretty = (string)($c['pretty_id'] ?? '');
            $name = (string)($c['name'] ?? '');
            $chronicleId = (int)($c['chronicle_id'] ?? 0);
            $realityId = (int)($c['reality_id'] ?? 0);
            $organizationId = (int)($c['organization_id'] ?? 0);
            $organizationName = (string)($c['organization_name'] ?? '');
        ?>
          <tr
            data-character-id="<?= $id ?>"
            data-filter-chronicle="<?= $chronicleId ?>"
            data-filter-org="<?= $organizationId ?>"
            data-search="<?= hg_acw_h(strtolower($name.' '.$id.' '.$pretty)) ?>">
            <td>#<?= $id ?></td>
            <!--<td> //$pretty !== '' ? hg_acw_h($pretty) : '-' </td>-->
            <td class='adm-text-left'><?= hg_acw_h($name) ?></td>
            <td class='adm-text-left'><?= $organizationName !== '' ? hg_acw_h($organizationName) : '-' ?></td>
            <td>
              <select data-chronicle-id>
                <?php foreach ($chronicles as $o):
                    $oid = (int)($o['id'] ?? 0);
                    $on = (string)($o['name'] ?? '');
                ?>
                  <option value="<?= $oid ?>" <?= $oid === $chronicleId ? 'selected' : '' ?>><?= hg_acw_h($on) ?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <td>
              <select data-reality-id>
                <?php foreach ($realities as $o):
                    $oid = (int)($o['id'] ?? 0);
                    $on = (string)($o['name'] ?? '');
                ?>
                  <option value="<?= $oid ?>" <?= $oid === $realityId ? 'selected' : '' ?>><?= hg_acw_h($on) ?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <td><span class="row-msg" data-row-msg>-</span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php if ($hasRealitySchema): ?>
<script>
(function(){
  var ADMIN_CSRF_TOKEN = <?php echo json_encode((string)$ADMIN_CSRF_TOKEN, JSON_UNESCAPED_UNICODE); ?>;
  var table = document.getElementById('worlds-table');
  var fSearch = document.getElementById('f-search-worlds');
  var fChronicle = document.getElementById('f-chronicle-worlds');
  var fOrg = document.getElementById('f-org-worlds');
  if (!table || !fSearch || !fChronicle || !fOrg) return;

  function applyFilter(){
    var q = String(fSearch.value || '').trim().toLowerCase();
    var chronicleFilter = parseInt(fChronicle.value || '0', 10) || 0;
    var orgFilter = parseInt(fOrg.value || '0', 10) || 0;
    table.querySelectorAll('tbody tr').forEach(function(tr){
      var s = String(tr.getAttribute('data-search') || '');
      var chr = parseInt(tr.getAttribute('data-filter-chronicle') || '0', 10) || 0;
      var org = parseInt(tr.getAttribute('data-filter-org') || '0', 10) || 0;

      var ok = true;
      if (q !== '' && s.indexOf(q) === -1) ok = false;
      if (chronicleFilter > 0 && chr !== chronicleFilter) ok = false;
      if (orgFilter > 0 && org !== orgFilter) ok = false;

      tr.style.display = ok ? '' : 'none';
    });
  }
  fSearch.addEventListener('input', applyFilter);
  fChronicle.addEventListener('change', applyFilter);
  fOrg.addEventListener('change', applyFilter);

  async function saveRow(tr){
    var charId = parseInt(tr.getAttribute('data-character-id') || '0', 10) || 0;
    var chronSel = tr.querySelector('[data-chronicle-id]');
    var realSel = tr.querySelector('[data-reality-id]');
    var msg = tr.querySelector('[data-row-msg]');
    if (!charId || !chronSel || !realSel || !msg) return;

    var chronicleId = parseInt(chronSel.value || '0', 10) || 0;
    var realityId = parseInt(realSel.value || '0', 10) || 0;
    if (!chronicleId || !realityId) return;

    chronSel.disabled = true;
    realSel.disabled = true;
    msg.textContent = 'Guardando...';
    msg.className = 'row-msg';

    var fd = new FormData();
    fd.append('ajax', 'save_character_world');
    fd.append('character_id', String(charId));
    fd.append('chronicle_id', String(chronicleId));
    fd.append('reality_id', String(realityId));
    if (ADMIN_CSRF_TOKEN) fd.append('csrf', ADMIN_CSRF_TOKEN);

    try {
      var endpoint = '/talim?s=admin_characters_worlds&ajax=1';
      var res = await fetch(endpoint, {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      var raw = await res.text();
      var json = null;
      try {
        json = raw ? JSON.parse(raw) : null;
      } catch (e) {
        msg.textContent = 'Respuesta invalida (' + res.status + ')';
        msg.className = 'row-msg err';
        return;
      }

      if (!res.ok || !json || !json.ok) {
        msg.textContent = (json && json.msg) ? json.msg : ('HTTP ' + res.status);
        msg.className = 'row-msg err';
      } else {
        tr.setAttribute('data-filter-chronicle', String(chronicleId));
        msg.textContent = 'Guardado';
        msg.className = 'row-msg ok';
        applyFilter();
      }
    } catch (e) {
      msg.textContent = 'Error de red';
      msg.className = 'row-msg err';
    } finally {
      chronSel.disabled = false;
      realSel.disabled = false;
    }
  }

  table.querySelectorAll('tbody tr').forEach(function(tr){
    var chronSel = tr.querySelector('[data-chronicle-id]');
    var realSel = tr.querySelector('[data-reality-id]');
    if (chronSel) chronSel.addEventListener('change', function(){ saveRow(tr); });
    if (realSel) realSel.addEventListener('change', function(){ saveRow(tr); });
  });
})();
</script>
<?php endif; ?>



