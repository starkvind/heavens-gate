<?php
// admin_characters_worlds.php - Asignacion masiva de cronica y realidad en personajes.

if (!isset($link) || !$link) { die("Error de conexion a la base de datos."); }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }

if (!function_exists('hg_acw_h')) {
    function hg_acw_h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

function hg_acw_has_table(mysqli $db, string $table): bool {
    $table = str_replace('`', '', $table);
    $rs = $db->query("SHOW TABLES LIKE '".$db->real_escape_string($table)."'");
    if (!$rs) return false;
    $ok = ($rs->num_rows > 0);
    $rs->close();
    return $ok;
}

function hg_acw_has_column(mysqli $db, string $table, string $column): bool {
    $table = str_replace('`', '', $table);
    $column = str_replace('`', '', $column);
    $rs = $db->query("SHOW COLUMNS FROM `".$db->real_escape_string($table)."` LIKE '".$db->real_escape_string($column)."'");
    if (!$rs) return false;
    $ok = ($rs->num_rows > 0);
    $rs->close();
    return $ok;
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

    if (!hg_acw_has_table($link, 'dim_realities') || !hg_acw_has_column($link, 'fact_characters', 'reality_id')) {
        $jsonExit(['ok' => false, 'msg' => 'Falta esquema: dim_realities / fact_characters.reality_id']);
    }

    $characterId = isset($_POST['character_id']) ? (int)$_POST['character_id'] : 0;
    $chronicleId = isset($_POST['chronicle_id']) ? (int)$_POST['chronicle_id'] : 0;
    $realityId = isset($_POST['reality_id']) ? (int)$_POST['reality_id'] : 0;
    if ($characterId <= 0 || $chronicleId <= 0 || $realityId <= 0) {
        $jsonExit(['ok' => false, 'msg' => 'IDs invalidos']);
    }

    $exists = 0;
    if ($st = $link->prepare("SELECT COUNT(*) FROM fact_characters WHERE id = ?")) {
        $st->bind_param('i', $characterId);
        $st->execute();
        $st->bind_result($exists);
        $st->fetch();
        $st->close();
    }
    if ($exists <= 0) { $jsonExit(['ok' => false, 'msg' => 'Personaje no encontrado']); }

    $existsChron = 0;
    if ($st = $link->prepare("SELECT COUNT(*) FROM dim_chronicles WHERE id = ?")) {
        $st->bind_param('i', $chronicleId);
        $st->execute();
        $st->bind_result($existsChron);
        $st->fetch();
        $st->close();
    }
    if ($existsChron <= 0) { $jsonExit(['ok' => false, 'msg' => 'Cronica no encontrada']); }

    $existsReality = 0;
    if ($st = $link->prepare("SELECT COUNT(*) FROM dim_realities WHERE id = ?")) {
        $st->bind_param('i', $realityId);
        $st->execute();
        $st->bind_result($existsReality);
        $st->fetch();
        $st->close();
    }
    if ($existsReality <= 0) { $jsonExit(['ok' => false, 'msg' => 'Realidad no encontrada']); }

    if ($st = $link->prepare("UPDATE fact_characters SET chronicle_id = ?, reality_id = ? WHERE id = ? LIMIT 1")) {
        $st->bind_param('iii', $chronicleId, $realityId, $characterId);
        if (!$st->execute()) {
            $st->close();
            $jsonExit(['ok' => false, 'msg' => 'Error al actualizar en BDD']);
        }
        $st->close();
        $jsonExit(['ok' => true, 'msg' => 'Guardado']);
    }

    $jsonExit(['ok' => false, 'msg' => 'Error al preparar consulta']);
}

$hasRealitySchema = hg_acw_has_table($link, 'dim_realities') && hg_acw_has_column($link, 'fact_characters', 'reality_id');

$chronicles = [];
if ($rs = $link->query("SELECT id, name FROM dim_chronicles ORDER BY name ASC")) {
    while ($r = $rs->fetch_assoc()) { $chronicles[] = $r; }
    $rs->close();
}

$realities = [];
if ($hasRealitySchema && ($rs = $link->query("SELECT id, name FROM dim_realities ORDER BY name ASC"))) {
    while ($r = $rs->fetch_assoc()) { $realities[] = $r; }
    $rs->close();
}

$organizations = [];
if ($rs = $link->query("SELECT id, name FROM dim_organizations ORDER BY name ASC")) {
    while ($r = $rs->fetch_assoc()) { $organizations[] = $r; }
    $rs->close();
}

$characters = [];
if ($hasRealitySchema) {
    $sql = "
    SELECT
        p.id,
        p.pretty_id,
        p.name,
        p.chronicle_id,
        p.reality_id,
        COALESCE(bo.organization_id, 0) AS organization_id,
        COALESCE(ch.name, '') AS chronicle_name,
        COALESCE(r.name, '') AS reality_name,
        COALESCE(o.name, '') AS organization_name
    FROM fact_characters p
    LEFT JOIN (
        SELECT character_id, MIN(organization_id) AS organization_id
        FROM bridge_characters_organizations
        WHERE (is_active = 1 OR is_active IS NULL)
        GROUP BY character_id
    ) bo ON bo.character_id = p.id
    LEFT JOIN dim_organizations o ON o.id = bo.organization_id
    LEFT JOIN dim_chronicles ch ON ch.id = p.chronicle_id
    LEFT JOIN dim_realities r ON r.id = p.reality_id
    ORDER BY p.name ASC, p.id ASC
    ";
    if ($rs = $link->query($sql)) {
        while ($r = $rs->fetch_assoc()) { $characters[] = $r; }
        $rs->close();
    }
}
?>

<style>
.worlds-wrap{ background:#05014E; border:1px solid #000088; border-radius:12px; padding:12px; }
.worlds-head{ display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; }
.worlds-head h2{ margin:0; color:#33FFFF; font-size:16px; }
.worlds-note{ color:#9dd; font-size:11px; }
.worlds-filter{ margin:10px 0; display:grid; grid-template-columns: repeat(3, minmax(180px,1fr)); gap:8px; }
.worlds-filter input{
  width:100%; background:#000033; color:#fff; border:1px solid #333; border-radius:6px; padding:6px 8px; font-size:12px;
}
.worlds-filter select{
  width:100%; background:#000033; color:#fff; border:1px solid #333; border-radius:6px; padding:6px 8px; font-size:12px;
}
.worlds-table-wrap{ max-height:72vh; overflow:auto; border:1px solid #000088; border-radius:8px; }
.worlds-table{ width:100%; border-collapse:collapse; font-size:11px; }
.worlds-table th,.worlds-table td{ border:1px solid #000088; padding:6px 8px; vertical-align:middle; background:#05014E; }
.worlds-table th{ background:#050b36; color:#33CCCC; text-align:left; position:sticky; top:0; z-index:2; }
.worlds-table tr:hover td{ background:#000066; }
.worlds-table select{
  width:100%; background:#000033; color:#fff; border:1px solid #333; border-radius:6px; padding:4px 6px; font-size:12px;
}
.row-msg{ font-size:11px; color:#9dd; }
.row-msg.ok{ color:#7CFC00; }
.row-msg.err{ color:#FF6B6B; }
.warn-box{
  background:#2c1f00;
  border:1px solid #8a5a00;
  color:#ffd58a;
  padding:10px;
  border-radius:8px;
  font-size:12px;
}
@media (max-width:980px){
  .worlds-filter{ grid-template-columns: 1fr; }
}
</style>

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
        <option value="0">Cronica: Todas</option>
        <?php foreach ($chronicles as $o):
            $oid = (int)($o['id'] ?? 0);
            $on = (string)($o['name'] ?? '');
        ?>
          <option value="<?= $oid ?>"><?= hg_acw_h($on) ?></option>
        <?php endforeach; ?>
      </select>
      <select id="f-org-worlds">
        <option value="0">Organizacion: Todas</option>
        <?php foreach ($organizations as $o):
            $oid = (int)($o['id'] ?? 0);
            $on = (string)($o['name'] ?? '');
        ?>
          <option value="<?= $oid ?>"><?= hg_acw_h($on) ?></option>
        <?php endforeach; ?>
      </select>
      <input id="f-search-worlds" type="text" placeholder="Buscar por nombre, id o pretty_id...">
    </div>

    <div class="worlds-table-wrap">
      <table class="worlds-table" id="worlds-table">
        <thead>
          <tr>
            <th style="width:80px;">ID</th>
            <!--<th style="width:190px;">Pretty ID</th>-->
            <th>Personaje</th>
            <th style="width:220px;">Organizacion</th>
            <th style="width:280px;">Cronica</th>
            <th style="width:280px;">Realidad</th>
            <th style="width:140px;">Estado</th>
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
            <td style='text-align:left;'><?= hg_acw_h($name) ?></td>
            <td style='text-align:left;'><?= $organizationName !== '' ? hg_acw_h($organizationName) : '-' ?></td>
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
