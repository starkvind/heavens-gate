<?php
// admin_character_deaths.php - Gestion de muertes de personajes con guardado Ajax por fila.

if (!isset($link) || !$link) { die("Error de conexion a la base de datos."); }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }

if (!function_exists('hg_acd_h')) {
    function hg_acd_h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

function hg_acd_has_table(mysqli $db, string $table): bool {
    $table = str_replace('`', '', $table);
    $rs = $db->query("SHOW TABLES LIKE '".$db->real_escape_string($table)."'");
    if (!$rs) return false;
    $ok = ($rs->num_rows > 0);
    $rs->close();
    return $ok;
}

function hg_acd_pick_deaths_table(mysqli $db): string {
    if (hg_acd_has_table($db, 'fact_characters_deaths')) return 'fact_characters_deaths';
    if (hg_acd_has_table($db, 'fact_characters_death')) return 'fact_characters_death';
    return '';
}

$deathsTable = hg_acd_pick_deaths_table($link);
$hasSchema = ($deathsTable !== '')
    && hg_acd_has_table($link, 'fact_characters')
    && hg_acd_has_table($link, 'fact_timeline_events');

$deathTypes = ['asesinato','catastrofe','suicidio','sacrificio','radiacion','absorcion','destruccion','desconexion','ritual','accidente','sobredosis','otros'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    if (ob_get_level() === 0) { ob_start(); }
    header('Content-Type: application/json; charset=UTF-8');

    $jsonExit = function(array $payload){
        $noise = '';
        if (ob_get_level() > 0) { $noise = (string)ob_get_clean(); }
        if (trim($noise) !== '') { $payload['_debug_noise'] = substr(trim($noise), 0, 1200); }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    };

    if (!$hasSchema) {
        $jsonExit(['ok' => false, 'msg' => 'Falta esquema para muertes de personajes']);
    }

    $ajaxMode = (string)($_POST['ajax'] ?? '');

    if ($ajaxMode === 'save_character_death') {
        $characterId = isset($_POST['character_id']) ? (int)$_POST['character_id'] : 0;
        $killerId = isset($_POST['killer_character_id']) ? (int)$_POST['killer_character_id'] : 0;
        $timelineEventId = isset($_POST['death_timeline_event_id']) ? (int)$_POST['death_timeline_event_id'] : 0;
        $deathType = trim((string)($_POST['death_type'] ?? ''));
        $deathDate = trim((string)($_POST['death_date'] ?? ''));
        $deathDescription = trim((string)($_POST['death_description'] ?? ''));
        $weight = isset($_POST['narrative_weight']) ? (int)$_POST['narrative_weight'] : 1;

        if ($characterId <= 0) $jsonExit(['ok' => false, 'msg' => 'Personaje invalido']);
        if ($killerId < 0 || $timelineEventId < 0) $jsonExit(['ok' => false, 'msg' => 'IDs invalidos']);
        if ($deathType === '') $deathType = 'otros';
        if (!in_array($deathType, $deathTypes, true)) $jsonExit(['ok' => false, 'msg' => 'Tipo de muerte invalido']);

        $killerId = $killerId > 0 ? $killerId : null;
        $timelineEventId = $timelineEventId > 0 ? $timelineEventId : null;
        $deathDate = ($deathDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $deathDate)) ? $deathDate : null;
        $deathDescription = ($deathDescription !== '') ? $deathDescription : null;
        if ($weight < 1) $weight = 1;
        if ($weight > 10) $weight = 10;

        $existsCharacter = 0;
        if ($st = $link->prepare("SELECT COUNT(*) FROM fact_characters WHERE id=?")) {
            $st->bind_param('i', $characterId);
            $st->execute();
            $st->bind_result($existsCharacter);
            $st->fetch();
            $st->close();
        }
        if ($existsCharacter <= 0) $jsonExit(['ok' => false, 'msg' => 'Personaje no encontrado']);

        if ($killerId !== null) {
            $existsKiller = 0;
            if ($st = $link->prepare("SELECT COUNT(*) FROM fact_characters WHERE id=?")) {
                $st->bind_param('i', $killerId);
                $st->execute();
                $st->bind_result($existsKiller);
                $st->fetch();
                $st->close();
            }
            if ($existsKiller <= 0) $jsonExit(['ok' => false, 'msg' => 'Responsable no encontrado']);
        }

        if ($timelineEventId !== null) {
            $existsEvent = 0;
            if ($st = $link->prepare("SELECT COUNT(*) FROM fact_timeline_events WHERE id=?")) {
                $st->bind_param('i', $timelineEventId);
                $st->execute();
                $st->bind_result($existsEvent);
                $st->fetch();
                $st->close();
            }
            if ($existsEvent <= 0) $jsonExit(['ok' => false, 'msg' => 'Evento no encontrado']);
        }

        $existingId = 0;
        if ($st = $link->prepare("SELECT id FROM `{$deathsTable}` WHERE character_id=? LIMIT 1")) {
            $st->bind_param('i', $characterId);
            $st->execute();
            $st->bind_result($existingId);
            $st->fetch();
            $st->close();
        }

        if ($existingId > 0) {
            if ($st = $link->prepare("
                UPDATE `{$deathsTable}`
                SET killer_character_id=?, death_timeline_event_id=?, death_type=?, death_date=?, death_description=?, narrative_weight=?
                WHERE id=?
                LIMIT 1
            ")) {
                $st->bind_param('iisssii', $killerId, $timelineEventId, $deathType, $deathDate, $deathDescription, $weight, $existingId);
                if (!$st->execute()) {
                    $st->close();
                    $jsonExit(['ok' => false, 'msg' => 'Error al actualizar la muerte']);
                }
                $st->close();
                $jsonExit(['ok' => true, 'msg' => 'Guardado', 'mode' => 'update']);
            }
            $jsonExit(['ok' => false, 'msg' => 'Error al preparar UPDATE']);
        }

        if ($st = $link->prepare("
            INSERT INTO `{$deathsTable}`
            (character_id, killer_character_id, death_timeline_event_id, death_type, death_date, death_description, narrative_weight)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ")) {
            $st->bind_param('iiisssi', $characterId, $killerId, $timelineEventId, $deathType, $deathDate, $deathDescription, $weight);
            if (!$st->execute()) {
                $st->close();
                $jsonExit(['ok' => false, 'msg' => 'Error al insertar la muerte']);
            }
            $st->close();
            $jsonExit(['ok' => true, 'msg' => 'Guardado', 'mode' => 'insert']);
        }

        $jsonExit(['ok' => false, 'msg' => 'Error al preparar INSERT']);
    }

    if ($ajaxMode === 'delete_character_death') {
        $characterId = isset($_POST['character_id']) ? (int)$_POST['character_id'] : 0;
        if ($characterId <= 0) $jsonExit(['ok' => false, 'msg' => 'Personaje invalido']);
        if ($st = $link->prepare("DELETE FROM `{$deathsTable}` WHERE character_id=? LIMIT 1")) {
            $st->bind_param('i', $characterId);
            if (!$st->execute()) {
                $st->close();
                $jsonExit(['ok' => false, 'msg' => 'Error al borrar']);
            }
            $st->close();
            $jsonExit(['ok' => true, 'msg' => 'Muerte eliminada']);
        }
        $jsonExit(['ok' => false, 'msg' => 'Error al preparar DELETE']);
    }

    $jsonExit(['ok' => false, 'msg' => 'Modo AJAX no soportado']);
}

$characters = [];
$killers = [];
$events = [];
$chronicles = [];
$organizations = [];

if ($hasSchema) {
    if ($rs = $link->query("SELECT id, name FROM fact_characters ORDER BY name ASC")) {
        while ($r = $rs->fetch_assoc()) { $killers[] = $r; }
        $rs->close();
    }

    if ($rs = $link->query("SELECT id, event_date, title FROM fact_timeline_events ORDER BY event_date DESC, id DESC")) {
        while ($r = $rs->fetch_assoc()) { $events[] = $r; }
        $rs->close();
    }

    if ($rs = $link->query("SELECT id, name FROM dim_chronicles ORDER BY name ASC")) {
        while ($r = $rs->fetch_assoc()) { $chronicles[] = $r; }
        $rs->close();
    }

    if ($rs = $link->query("SELECT id, name FROM dim_organizations ORDER BY name ASC")) {
        while ($r = $rs->fetch_assoc()) { $organizations[] = $r; }
        $rs->close();
    }

    $sql = "
    SELECT
      p.id,
      p.pretty_id,
      p.name,
      COALESCE(dcs.label, p.status) AS status, p.status_id,
      p.chronicle_id,
      COALESCE(bo.organization_id, 0) AS organization_id,
      d.id AS death_id,
      d.killer_character_id,
      d.death_timeline_event_id,
      d.death_type,
      d.death_date,
      d.death_description,
      d.narrative_weight
    FROM fact_characters p
    LEFT JOIN dim_character_status dcs ON dcs.id = p.status_id
    LEFT JOIN (
      SELECT character_id, MIN(organization_id) AS organization_id
      FROM bridge_characters_organizations
      WHERE (is_active = 1 OR is_active IS NULL)
      GROUP BY character_id
    ) bo ON bo.character_id = p.id
    LEFT JOIN `{$deathsTable}` d ON d.character_id = p.id
    ORDER BY p.name ASC, p.id ASC
    ";
    if ($rs = $link->query($sql)) {
        while ($r = $rs->fetch_assoc()) { $characters[] = $r; }
        $rs->close();
    }
}
?>

<div class="worlds-wrap">
  <div class="worlds-head">
    <h2>Muertes de personajes</h2>
    <div class="worlds-note">Edicion por fila con Ajax, sin recargar</div>
  </div>

  <?php if (!$hasSchema): ?>
    <div class="warn-box">
      Falta esquema. Necesario: <code>fact_characters_deaths</code> (o <code>fact_characters_death</code>), <code>fact_characters</code> y <code>fact_timeline_events</code>.
    </div>
  <?php else: ?>
    <div class="worlds-filter">
      <select id="f-chronicle-deaths">
        <option value="0">Cronica: Todas</option>
        <?php foreach ($chronicles as $o): ?>
          <option value="<?= (int)($o['id'] ?? 0) ?>"><?= hg_acd_h((string)($o['name'] ?? '')) ?></option>
        <?php endforeach; ?>
      </select>
      <select id="f-org-deaths">
        <option value="0">Organizacion: Todas</option>
        <?php foreach ($organizations as $o): ?>
          <option value="<?= (int)($o['id'] ?? 0) ?>"><?= hg_acd_h((string)($o['name'] ?? '')) ?></option>
        <?php endforeach; ?>
      </select>
      <select id="f-type-deaths">
        <option value="">Tipo: Todos</option>
        <?php foreach ($deathTypes as $t): ?>
          <option value="<?= hg_acd_h($t) ?>"><?= hg_acd_h(ucfirst($t)) ?></option>
        <?php endforeach; ?>
      </select>
      <label class="adm-flex-8-center"><input type="checkbox" id="f-only-with-death"> Solo con muerte</label>
      <input id="f-search-deaths" type="text" placeholder="Buscar por nombre, id o pretty_id...">
    </div>

    <?php
      $killerNameById = [];
      foreach ($killers as $k) {
          $killerNameById[(int)($k['id'] ?? 0)] = (string)($k['name'] ?? '');
      }
      $eventTitleById = [];
      foreach ($events as $e) {
          $eventTitleById[(int)($e['id'] ?? 0)] = (string)($e['title'] ?? '');
      }
    ?>

    <div class="worlds-table-wrap">
      <table class="worlds-table" id="deaths-table">
        <thead>
          <tr>
            <th class="adm-w-80">ID</th>
            <th>Personaje</th>
            <th class="adm-w-140">Estado PJ</th>
            <th class="adm-w-120">Muerte</th>
            <th>Resumen</th>
            <th class="adm-w-140">Fecha</th>
            <th class="adm-w-100">Peso</th>
            <th class="adm-w-160">Estado</th>
            <th class="adm-w-120">Accion</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($characters as $c):
            $id = (int)($c['id'] ?? 0);
            $pretty = (string)($c['pretty_id'] ?? '');
            $name = (string)($c['name'] ?? '');
            $status = (string)($c['status'] ?? '');
            $chronicleId = (int)($c['chronicle_id'] ?? 0);
            $organizationId = (int)($c['organization_id'] ?? 0);
            $deathId = (int)($c['death_id'] ?? 0);
            $deathType = (string)($c['death_type'] ?? '');
            $killerId = (int)($c['killer_character_id'] ?? 0);
            $eventId = (int)($c['death_timeline_event_id'] ?? 0);
            $deathDate = (string)($c['death_date'] ?? '');
            $description = (string)($c['death_description'] ?? '');
            $weight = (int)($c['narrative_weight'] ?? 1);
            if ($deathId <= 0) $deathType = '';
            if ($weight < 1) $weight = 1;
            if ($weight > 10) $weight = 10;
            $killerName = $killerId > 0 ? (string)($killerNameById[$killerId] ?? ('#'.$killerId)) : '-';
            $eventTitle = $eventId > 0 ? (string)($eventTitleById[$eventId] ?? ('#'.$eventId)) : '-';
            $summary = [];
            if ($deathType !== '') $summary[] = ucfirst($deathType);
            if ($killerId > 0) $summary[] = 'Por: '.$killerName;
            if ($eventId > 0) $summary[] = 'Evento: '.$eventTitle;
            $summaryText = !empty($summary) ? implode(' | ', $summary) : 'Sin muerte registrada';
        ?>
          <tr
            data-main-row="1"
            data-character-id="<?= $id ?>"
            data-has-death="<?= $deathId > 0 ? '1' : '0' ?>"
            data-filter-chronicle="<?= $chronicleId ?>"
            data-filter-org="<?= $organizationId ?>"
            data-filter-type="<?= hg_acd_h($deathType) ?>"
            data-search="<?= hg_acd_h(strtolower($name.' '.$id.' '.$pretty)) ?>">
            <td>#<?= $id ?></td>
            <td class="adm-text-left"><?= hg_acd_h($name) ?></td>
            <td class="adm-text-left"><?= $status !== '' ? hg_acd_h($status) : '-' ?></td>
            <td><?= $deathId > 0 ? 'Si' : 'No' ?></td>
            <td class="adm-text-left" data-summary-text><?= hg_acd_h($summaryText) ?></td>
            <td><input type="date" data-death-date value="<?= hg_acd_h($deathDate) ?>"></td>
            <td><input type="number" min="1" max="10" data-weight value="<?= $weight ?>"></td>
            <td>
              <span class="row-msg" data-row-msg>-</span>
            </td>
            <td>
              <button class="btn btn-small" type="button" data-delete-death>Quitar</button>
            </td>
          </tr>
          <tr data-edit-row="1" data-character-id="<?= $id ?>">
            <td colspan="9">
              <div class="grid adm-grid-2-auto">
                <label>Tipo
                  <select data-death-type>
                    <option value="">- Sin muerte -</option>
                    <?php foreach ($deathTypes as $t): ?>
                      <option value="<?= hg_acd_h($t) ?>" <?= $deathType === $t ? 'selected' : '' ?>><?= hg_acd_h(ucfirst($t)) ?></option>
                    <?php endforeach; ?>
                  </select>
                </label>
                <label>Responsable
                  <select data-killer-id>
                    <option value="0">- Sin responsable -</option>
                    <?php foreach ($killers as $k):
                        $kid = (int)($k['id'] ?? 0);
                        $kn = (string)($k['name'] ?? '');
                    ?>
                      <option value="<?= $kid ?>" <?= $kid === $killerId ? 'selected' : '' ?>>#<?= $kid ?> - <?= hg_acd_h($kn) ?></option>
                    <?php endforeach; ?>
                  </select>
                </label>
              </div>
              <div class="grid adm-grid-2-auto">
                <label>Evento
                  <select data-event-id>
                    <option value="0">- Sin evento -</option>
                    <?php foreach ($events as $e):
                        $eid = (int)($e['id'] ?? 0);
                        $etitle = (string)($e['title'] ?? '');
                        $edate = (string)($e['event_date'] ?? '');
                    ?>
                      <option value="<?= $eid ?>" <?= $eid === $eventId ? 'selected' : '' ?>>
                        #<?= $eid ?> <?= $edate !== '' ? '['.hg_acd_h($edate).'] ' : '' ?><?= hg_acd_h($etitle) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </label>
              </div>
              <label class="adm-text-left">Descripcion
                <textarea data-description rows="2" placeholder="Descripcion..."><?= hg_acd_h($description) ?></textarea>
              </label>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php if ($hasSchema): ?>
<script>
(function(){
  var table = document.getElementById('deaths-table');
  var fSearch = document.getElementById('f-search-deaths');
  var fChronicle = document.getElementById('f-chronicle-deaths');
  var fOrg = document.getElementById('f-org-deaths');
  var fType = document.getElementById('f-type-deaths');
  var fOnlyWithDeath = document.getElementById('f-only-with-death');
  if (!table || !fSearch || !fChronicle || !fOrg || !fType || !fOnlyWithDeath) return;

  function getEditRow(mainTr){
    var nx = mainTr && mainTr.nextElementSibling ? mainTr.nextElementSibling : null;
    if (!nx) return null;
    if (nx.getAttribute('data-edit-row') !== '1') return null;
    if ((nx.getAttribute('data-character-id') || '') !== (mainTr.getAttribute('data-character-id') || '')) return null;
    return nx;
  }

  function applyFilter(){
    var q = String(fSearch.value || '').trim().toLowerCase();
    var chronicleFilter = parseInt(fChronicle.value || '0', 10) || 0;
    var orgFilter = parseInt(fOrg.value || '0', 10) || 0;
    var typeFilter = String(fType.value || '');
    var onlyWithDeath = !!fOnlyWithDeath.checked;

    table.querySelectorAll('tbody tr[data-main-row="1"]').forEach(function(mainTr){
      var s = String(mainTr.getAttribute('data-search') || '');
      var chr = parseInt(mainTr.getAttribute('data-filter-chronicle') || '0', 10) || 0;
      var org = parseInt(mainTr.getAttribute('data-filter-org') || '0', 10) || 0;
      var typ = String(mainTr.getAttribute('data-filter-type') || '');
      var hasDeath = String(mainTr.getAttribute('data-has-death') || '0') === '1';
      var ok = true;
      if (q !== '' && s.indexOf(q) === -1) ok = false;
      if (chronicleFilter > 0 && chr !== chronicleFilter) ok = false;
      if (orgFilter > 0 && org !== orgFilter) ok = false;
      if (typeFilter !== '' && typ !== typeFilter) ok = false;
      if (onlyWithDeath && !hasDeath) ok = false;
      var editTr = getEditRow(mainTr);
      mainTr.style.display = ok ? '' : 'none';
      if (editTr) editTr.style.display = ok ? '' : 'none';
    });
  }

  fSearch.addEventListener('input', applyFilter);
  fChronicle.addEventListener('change', applyFilter);
  fOrg.addEventListener('change', applyFilter);
  fType.addEventListener('change', applyFilter);
  fOnlyWithDeath.addEventListener('change', applyFilter);

  async function saveRow(mainTr){
    var editTr = getEditRow(mainTr);
    var charId = parseInt(mainTr.getAttribute('data-character-id') || '0', 10) || 0;
    var typeSel = editTr ? editTr.querySelector('[data-death-type]') : null;
    var killerSel = editTr ? editTr.querySelector('[data-killer-id]') : null;
    var eventSel = editTr ? editTr.querySelector('[data-event-id]') : null;
    var dateInp = mainTr.querySelector('[data-death-date]');
    var weightInp = mainTr.querySelector('[data-weight]');
    var desc = editTr ? editTr.querySelector('[data-description]') : null;
    var msg = mainTr.querySelector('[data-row-msg]');
    var summary = mainTr.querySelector('[data-summary-text]');
    if (!charId || !typeSel || !killerSel || !eventSel || !dateInp || !weightInp || !desc || !msg) return;

    var deathType = String(typeSel.value || 'otros');
    var killerId = parseInt(killerSel.value || '0', 10) || 0;
    var eventId = parseInt(eventSel.value || '0', 10) || 0;
    var date = String(dateInp.value || '');
    var weight = parseInt(weightInp.value || '1', 10) || 1;
    if (weight < 1) weight = 1;
    if (weight > 10) weight = 10;

    typeSel.disabled = true;
    killerSel.disabled = true;
    eventSel.disabled = true;
    dateInp.disabled = true;
    weightInp.disabled = true;
    desc.disabled = true;
    msg.textContent = 'Guardando...';
    msg.className = 'row-msg';

    var fd = new FormData();
    fd.append('ajax', 'save_character_death');
    fd.append('character_id', String(charId));
    fd.append('death_type', deathType);
    fd.append('killer_character_id', String(killerId));
    fd.append('death_timeline_event_id', String(eventId));
    fd.append('death_date', date);
    fd.append('narrative_weight', String(weight));
    fd.append('death_description', String(desc.value || ''));

    try {
      var endpoint = '/talim?s=admin_character_deaths&ajax=1';
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
        typeSel.value = deathType;
        mainTr.setAttribute('data-has-death', '1');
        mainTr.setAttribute('data-filter-type', deathType);
        if (summary) {
          var t = deathType ? (deathType.charAt(0).toUpperCase() + deathType.slice(1)) : '';
          var killerTxt = killerSel.value && killerSel.value !== '0' ? ('Por: ' + killerSel.options[killerSel.selectedIndex].text.replace(/^#\d+\s-\s/, '')) : '';
          var eventTxt = eventSel.value && eventSel.value !== '0' ? ('Evento: ' + eventSel.options[eventSel.selectedIndex].text.replace(/^#\d+\s/, '')) : '';
          var parts = [];
          if (t) parts.push(t);
          if (killerTxt) parts.push(killerTxt);
          if (eventTxt) parts.push(eventTxt);
          summary.textContent = parts.length ? parts.join(' | ') : 'Sin muerte registrada';
        }
        msg.textContent = 'Guardado';
        msg.className = 'row-msg ok';
        applyFilter();
      }
    } catch (e) {
      msg.textContent = 'Error de red';
      msg.className = 'row-msg err';
    } finally {
      typeSel.disabled = false;
      killerSel.disabled = false;
      eventSel.disabled = false;
      dateInp.disabled = false;
      weightInp.disabled = false;
      desc.disabled = false;
    }
  }

  async function deleteRowDeath(mainTr){
    var editTr = getEditRow(mainTr);
    var charId = parseInt(mainTr.getAttribute('data-character-id') || '0', 10) || 0;
    var msg = mainTr.querySelector('[data-row-msg]');
    var summary = mainTr.querySelector('[data-summary-text]');
    if (!charId || !msg) return;
    if (!confirm('Se eliminara la muerte registrada de este personaje. Continuar?')) return;

    msg.textContent = 'Eliminando...';
    msg.className = 'row-msg';

    var fd = new FormData();
    fd.append('ajax', 'delete_character_death');
    fd.append('character_id', String(charId));

    try {
      var endpoint = '/talim?s=admin_character_deaths&ajax=1';
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
        return;
      }

      var typeSel = editTr ? editTr.querySelector('[data-death-type]') : null;
      var killerSel = editTr ? editTr.querySelector('[data-killer-id]') : null;
      var eventSel = editTr ? editTr.querySelector('[data-event-id]') : null;
      var dateInp = mainTr.querySelector('[data-death-date]');
      var weightInp = mainTr.querySelector('[data-weight]');
      var desc = editTr ? editTr.querySelector('[data-description]') : null;
      if (typeSel) typeSel.value = '';
      if (killerSel) killerSel.value = '0';
      if (eventSel) eventSel.value = '0';
      if (dateInp) dateInp.value = '';
      if (weightInp) weightInp.value = '1';
      if (desc) desc.value = '';
      if (summary) summary.textContent = 'Sin muerte registrada';

      mainTr.setAttribute('data-has-death', '0');
      mainTr.setAttribute('data-filter-type', '');
      msg.textContent = 'Sin muerte';
      msg.className = 'row-msg ok';
      applyFilter();
    } catch (e) {
      msg.textContent = 'Error de red';
      msg.className = 'row-msg err';
    }
  }

  table.querySelectorAll('tbody tr[data-main-row="1"]').forEach(function(mainTr){
    var editTr = getEditRow(mainTr);
    var typeSel = editTr ? editTr.querySelector('[data-death-type]') : null;
    var killerSel = editTr ? editTr.querySelector('[data-killer-id]') : null;
    var eventSel = editTr ? editTr.querySelector('[data-event-id]') : null;
    var dateInp = mainTr.querySelector('[data-death-date]');
    var weightInp = mainTr.querySelector('[data-weight]');
    var desc = editTr ? editTr.querySelector('[data-description]') : null;
    var delBtn = mainTr.querySelector('[data-delete-death]');

    if (typeSel) typeSel.addEventListener('change', function(){ saveRow(mainTr); });
    if (killerSel) killerSel.addEventListener('change', function(){ saveRow(mainTr); });
    if (eventSel) eventSel.addEventListener('change', function(){ saveRow(mainTr); });
    if (dateInp) dateInp.addEventListener('change', function(){ saveRow(mainTr); });
    if (weightInp) weightInp.addEventListener('change', function(){ saveRow(mainTr); });
    if (desc) desc.addEventListener('blur', function(){ saveRow(mainTr); });
    if (delBtn) delBtn.addEventListener('click', function(){ deleteRowDeath(mainTr); });
  });
})();
</script>
<?php endif; ?>


