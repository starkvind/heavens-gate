<?php
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
if (!hg_admin_require_db($link)) { return; }
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }

include(__DIR__ . '/../../partials/admin/admin_styles.php');

function ased_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function ased_table_exists(mysqli $link, string $table): bool
{
    $st = $link->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    if (!$st) return false;
    $st->bind_param('s', $table);
    $st->execute();
    $st->bind_result($count);
    $st->fetch();
    $st->close();
    return ((int)$count > 0);
}

function ased_column_exists(mysqli $link, string $table, string $column): bool
{
    $st = $link->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    if (!$st) return false;
    $st->bind_param('ss', $table, $column);
    $st->execute();
    $st->bind_result($count);
    $st->fetch();
    $st->close();
    return ((int)$count > 0);
}

function ased_csrf_ok(string $key): bool
{
    $token = (string)($_POST['csrf'] ?? '');
    return function_exists('hg_admin_csrf_valid')
        ? hg_admin_csrf_valid($token, $key)
        : (is_string($token) && $token !== '' && isset($_SESSION[$key]) && hash_equals((string)$_SESSION[$key], $token));
}

function ased_systems(mysqli $link): array
{
    $rows = [];
    if ($rs = $link->query("SELECT id, name FROM dim_systems ORDER BY sort_order ASC, name ASC, id ASC")) {
        while ($row = $rs->fetch_assoc()) {
            $rows[] = ['id' => (int)$row['id'], 'name' => (string)$row['name']];
        }
        $rs->close();
    }
    return $rows;
}

function ased_catalog(mysqli $link, string $table): array
{
    $rows = [];
    $hasSystemId = ased_column_exists($link, $table, 'system_id');
    $sql = "
        SELECT
            e.id,
            e.name,
            " . ($hasSystemId ? "COALESCE(e.system_id, 0)" : "0") . " AS system_id,
            " . ($hasSystemId ? "COALESCE(ds.name, '')" : "''") . " AS system_name
        FROM `{$table}` e
        " . ($hasSystemId ? "LEFT JOIN dim_systems ds ON ds.id = e.system_id" : "") . "
        ORDER BY " . ($hasSystemId ? "ds.name ASC, " : "") . "e.name ASC, e.id ASC
    ";
    if ($rs = $link->query($sql)) {
        while ($row = $rs->fetch_assoc()) {
            $rows[] = [
                'id' => (int)$row['id'],
                'name' => (string)$row['name'],
                'origin_system_id' => (int)($row['system_id'] ?? 0),
                'origin_system_name' => trim((string)($row['system_name'] ?? '')),
            ];
        }
        $rs->close();
    }
    return $rows;
}

function ased_existing_assignments(mysqli $link, string $bridgeTable, string $detailFk, int $systemId): array
{
    $rows = [];
    $hasActive = ased_column_exists($link, $bridgeTable, 'is_active');
    $sql = "
        SELECT {$detailFk} AS detail_id, " . ($hasActive ? 'is_active' : '1') . " AS is_active
        FROM `{$bridgeTable}`
        WHERE system_id = ?
        ORDER BY id ASC
    ";
    if ($st = $link->prepare($sql)) {
        $st->bind_param('i', $systemId);
        $st->execute();
        $rs = $st->get_result();
        while ($rs && ($row = $rs->fetch_assoc())) {
            $rows[(int)$row['detail_id']] = ['is_active' => (int)($row['is_active'] ?? 1)];
        }
        $st->close();
    }
    return $rows;
}

function ased_save_group(mysqli $link, int $systemId, array $group, array $selectedIds, array $activeIds): void
{
    $bridgeTable = (string)$group['bridge_table'];
    $detailFk = (string)$group['detail_fk'];
    $hasActive = (bool)$group['has_active'];

    $delete = $link->prepare("DELETE FROM `{$bridgeTable}` WHERE system_id = ?");
    if (!$delete) {
        throw new RuntimeException('No se pudo preparar el borrado de ' . $bridgeTable . '.');
    }
    $delete->bind_param('i', $systemId);
    $delete->execute();
    $delete->close();

    $selectedMap = [];
    foreach ($selectedIds as $id) {
        $detailId = (int)$id;
        if ($detailId > 0) {
            $selectedMap[$detailId] = true;
        }
    }
    if (empty($selectedMap)) {
        return;
    }

    $activeMap = [];
    foreach ($activeIds as $id) {
        $detailId = (int)$id;
        if ($detailId > 0) {
            $activeMap[$detailId] = true;
        }
    }

    $insertSql = $hasActive
        ? "INSERT INTO `{$bridgeTable}` (system_id, `{$detailFk}`, is_active) VALUES (?, ?, ?)"
        : "INSERT INTO `{$bridgeTable}` (system_id, `{$detailFk}`) VALUES (?, ?)";
    $insert = $link->prepare($insertSql);
    if (!$insert) {
        throw new RuntimeException('No se pudo preparar el alta en ' . $bridgeTable . '.');
    }

    foreach (array_keys($selectedMap) as $detailId) {
        if ($hasActive) {
            $isActive = empty($activeMap) || isset($activeMap[$detailId]) ? 1 : 0;
            $insert->bind_param('iii', $systemId, $detailId, $isActive);
        } else {
            $insert->bind_param('ii', $systemId, $detailId);
        }
        $insert->execute();
    }

    $insert->close();
}

$csrfKey = 'csrf_admin_systems_extra_details';
$csrf = function_exists('hg_admin_ensure_csrf_token') ? hg_admin_ensure_csrf_token($csrfKey) : (string)($_SESSION[$csrfKey] ?? '');

$groups = [
    'races' => [
        'title' => 'Razas vinculadas',
        'empty_label' => 'Ninguna raza',
        'select_label' => 'Seleccionar raza',
        'catalog_table' => 'dim_breeds',
        'bridge_table' => 'bridge_systems_ex_races',
        'detail_fk' => 'race_id',
        'fallback_fk' => 'breed_id',
    ],
    'auspices' => [
        'title' => 'Auspicios vinculados',
        'empty_label' => 'Ningun auspicio',
        'select_label' => 'Seleccionar auspicio',
        'catalog_table' => 'dim_auspices',
        'bridge_table' => 'bridge_systems_ex_auspices',
        'detail_fk' => 'auspice_id',
        'fallback_fk' => '',
    ],
    'tribes' => [
        'title' => 'Tribus vinculadas',
        'empty_label' => 'Ninguna tribu',
        'select_label' => 'Seleccionar tribu',
        'catalog_table' => 'dim_tribes',
        'bridge_table' => 'bridge_systems_ex_tribes',
        'detail_fk' => 'tribe_id',
        'fallback_fk' => '',
    ],
];

$missing = [];
if (!ased_table_exists($link, 'dim_systems')) {
    $missing[] = 'dim_systems';
}
foreach ($groups as &$group) {
    if (!ased_table_exists($link, (string)$group['catalog_table'])) {
        $missing[] = (string)$group['catalog_table'];
        continue;
    }
    if (!ased_table_exists($link, (string)$group['bridge_table'])) {
        $missing[] = (string)$group['bridge_table'];
        continue;
    }

    $detailFk = (string)$group['detail_fk'];
    if (!ased_column_exists($link, (string)$group['bridge_table'], $detailFk) && $group['fallback_fk'] !== '' && ased_column_exists($link, (string)$group['bridge_table'], (string)$group['fallback_fk'])) {
        $detailFk = (string)$group['fallback_fk'];
    }
    if (!ased_column_exists($link, (string)$group['bridge_table'], 'system_id') || !ased_column_exists($link, (string)$group['bridge_table'], $detailFk)) {
        $missing[] = (string)$group['bridge_table'] . '.system_id/' . $detailFk;
        continue;
    }

    $group['detail_fk'] = $detailFk;
    $group['has_active'] = ased_column_exists($link, (string)$group['bridge_table'], 'is_active');
}
unset($group);

if (!empty($missing)) {
    admin_panel_open('Extra Details to System', '');
    echo "<div class='flash'><div class='err'>Faltan tablas o columnas para este modulo: <code>" . ased_h(implode(', ', $missing)) . "</code>.</div></div>";
    admin_panel_close();
    return;
}

$systems = ased_systems($link);
$systemId = max(0, (int)($_GET['system_id'] ?? $_POST['system_id'] ?? 0));
if ($systemId <= 0 && !empty($systems)) {
    $systemId = (int)$systems[0]['id'];
}

$flash = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_system_extra_details'])) {
    if (!ased_csrf_ok($csrfKey)) {
        $flash[] = ['type' => 'error', 'msg' => 'CSRF invalido. Recarga la pagina.'];
    } elseif ($systemId <= 0) {
        $flash[] = ['type' => 'error', 'msg' => 'Selecciona un sistema valido.'];
    } else {
        $payload = isset($_POST['map']) && is_array($_POST['map']) ? $_POST['map'] : [];
        $link->begin_transaction();
        try {
            foreach ($groups as $groupKey => $group) {
                $selectedIds = isset($payload[$groupKey]['selected']) && is_array($payload[$groupKey]['selected'])
                    ? $payload[$groupKey]['selected']
                    : [];
                $activeIds = isset($payload[$groupKey]['active']) && is_array($payload[$groupKey]['active'])
                    ? $payload[$groupKey]['active']
                    : $selectedIds;
                ased_save_group($link, $systemId, $group, $selectedIds, $activeIds);
            }
            $link->commit();
            $flash[] = ['type' => 'ok', 'msg' => 'Extra details guardados para el sistema seleccionado.'];
        } catch (Throwable $e) {
            $link->rollback();
            $flash[] = ['type' => 'error', 'msg' => 'No se pudo guardar el mapeo: ' . $e->getMessage()];
        }
    }
}

$systemName = '';
foreach ($systems as $systemRow) {
    if ((int)$systemRow['id'] === $systemId) {
        $systemName = (string)$systemRow['name'];
        break;
    }
}

$groupsState = [];
foreach ($groups as $groupKey => $group) {
    $catalog = ased_catalog($link, (string)$group['catalog_table']);
    $current = $systemId > 0 ? ased_existing_assignments($link, (string)$group['bridge_table'], (string)$group['detail_fk'], $systemId) : [];
    $items = [];
    $selected = [];
    foreach ($catalog as $row) {
        $detailId = (int)$row['id'];
        $originSystemId = (int)($row['origin_system_id'] ?? 0);
        $originSystemName = trim((string)($row['origin_system_name'] ?? ''));
        $label = (string)$row['name'];
        if ($originSystemName !== '') {
            $label .= ' (' . $originSystemName . ')';
        }
        $items[] = [
            'id' => $detailId,
            'name' => (string)$row['name'],
            'label' => $label,
            'origin_system_id' => $originSystemId,
            'origin_system_name' => $originSystemName,
        ];
        if (isset($current[$detailId])) {
            $selected[] = [
                'id' => $detailId,
                'name' => (string)$row['name'],
                'label' => $label,
                'origin_system_id' => $originSystemId,
                'origin_system_name' => $originSystemName,
            ];
        }
    }
    $groupsState[$groupKey] = [
        'title' => (string)$group['title'],
        'empty_label' => (string)$group['empty_label'],
        'select_label' => (string)$group['select_label'],
        'items' => $items,
        'selected' => $selected,
    ];
}

$actions = '<span class="adm-flex-right-8"><form method="get" class="adm-inline-form"><input type="hidden" name="p" value="talim"><input type="hidden" name="s" value="admin_systems_extra_details"><label class="adm-text-left">Sistema <select class="select" name="system_id" onchange="this.form.submit()">';
foreach ($systems as $systemRow) {
    $selected = ((int)$systemRow['id'] === $systemId) ? ' selected' : '';
    $actions .= '<option value="' . (int)$systemRow['id'] . '"' . $selected . '>' . ased_h((string)$systemRow['name']) . ' (#' . (int)$systemRow['id'] . ')</option>';
}
$actions .= '</select></label></form></span>';

admin_panel_open('Extra Details to System', $actions);
?>
<?php if (!empty($flash)): ?><div class="flash"><?php foreach ($flash as $m): $cl = ($m['type'] ?? '') === 'ok' ? 'ok' : 'err'; ?><div class="<?= $cl ?>"><?= ased_h($m['msg'] ?? '') ?></div><?php endforeach; ?></div><?php endif; ?>
<style>
.ased-wrap{max-width:600px;margin:0 auto}
.ased-summary{display:flex;gap:10px;flex-wrap:wrap;margin:0 0 14px 0}
.ased-pill{padding:6px 10px;border-radius:999px;border:1px solid #17366e;background:#071b4a;color:#dfefff}
.ased-card{border:1px solid #000088;border-radius:10px;padding:14px;background:rgba(0,0,40,.22)}
.ased-block + .ased-block{margin-top:18px;padding-top:18px;border-top:1px solid rgba(130,160,255,.18)}
.ased-block h3{margin:0 0 10px 0}
.ased-row{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.ased-select{min-width:260px;max-width:100%}
.ased-chiplist{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
.ased-chip{display:inline-flex;align-items:center;gap:8px;padding:6px 10px;border-radius:999px;background:#0c2454;border:1px solid #274c99;color:#eef4ff;max-width:100%}
.ased-chip-text{display:flex;flex-direction:column;min-width:0}
.ased-chip-label{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:360px}
.ased-chip-origin{font-size:10px;line-height:1.2;color:#b9cbff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:360px}
.ased-chip-remove{border:0;background:transparent;color:#ffd9d9;font-weight:700;cursor:pointer;padding:0 2px}
.ased-empty{color:#cfd8ff;font-style:italic}
</style>

<div class="ased-wrap">
  <div class="ased-summary">
    <span class="ased-pill">Sistema actual: <?= ased_h($systemName !== '' ? $systemName : ('#' . $systemId)) ?></span>
  </div>

  <form method="post" id="asedForm">
    <input type="hidden" name="csrf" value="<?= ased_h($csrf) ?>">
    <input type="hidden" name="save_system_extra_details" value="1">
    <input type="hidden" name="system_id" value="<?= (int)$systemId ?>">
    <div id="asedHiddenInputs"></div>

    <section class="ased-card">
      <?php foreach ($groups as $groupKey => $group): ?>
        <?php $state = $groupsState[$groupKey]; ?>
        <div class="ased-block" data-group="<?= ased_h($groupKey) ?>">
          <h3><?= ased_h((string)$state['title']) ?></h3>
          <div class="ased-row">
            <select class="select ased-select" id="ased-select-<?= ased_h($groupKey) ?>">
              <option value=""><?= ased_h((string)$state['select_label']) ?>...</option>
            </select>
            <button type="button" class="btn" data-add-group="<?= ased_h($groupKey) ?>">Anadir</button>
          </div>
          <div class="ased-chiplist" id="ased-list-<?= ased_h($groupKey) ?>"></div>
          <div class="ased-empty" id="ased-empty-<?= ased_h($groupKey) ?>"><?= ased_h((string)$state['empty_label']) ?></div>
        </div>
      <?php endforeach; ?>

      <div class="modal-actions adm-mt-12">
        <button type="submit" class="btn btn-green">Guardar extra details</button>
      </div>
    </section>
  </form>
</div>

<script>
const ASED_SYSTEM_ID = <?= (int)$systemId ?>;
const ASED_GROUPS = <?= json_encode($groupsState, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const asedState = {};

function asedEsc(text){
  return String(text || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

function asedInitState(){
  Object.keys(ASED_GROUPS).forEach(function(groupKey){
    const group = ASED_GROUPS[groupKey] || {};
    const selected = Array.isArray(group.selected) ? group.selected : [];
    asedState[groupKey] = selected.map(function(item){ return Object.assign({}, item); });
  });
}

function asedAvailableItems(groupKey){
  const group = ASED_GROUPS[groupKey] || {};
  const items = Array.isArray(group.items) ? group.items : [];
  const selectedIds = {};
  (asedState[groupKey] || []).forEach(function(item){ selectedIds[parseInt(item.id || 0, 10)] = true; });
  return items.filter(function(item){
    const id = parseInt(item.id || 0, 10) || 0;
    if (id <= 0 || selectedIds[id]) return false;
    const originSystemId = parseInt(item.origin_system_id || 0, 10) || 0;
    return originSystemId !== ASED_SYSTEM_ID;
  });
}

function asedRenderSelect(groupKey){
  const select = document.getElementById('ased-select-' + groupKey);
  if (!select) return;
  const group = ASED_GROUPS[groupKey] || {};
  const items = asedAvailableItems(groupKey);
  let html = '<option value="">' + asedEsc((group.select_label || 'Seleccionar')) + '...</option>';
  items.forEach(function(item){
    html += '<option value="' + (parseInt(item.id || 0, 10) || 0) + '">' + asedEsc(item.label || item.name || ('#' + item.id)) + '</option>';
  });
  select.innerHTML = html;
}

function asedRenderList(groupKey){
  const list = document.getElementById('ased-list-' + groupKey);
  const empty = document.getElementById('ased-empty-' + groupKey);
  if (!list || !empty) return;
  const rows = Array.isArray(asedState[groupKey]) ? asedState[groupKey] : [];
  if (!rows.length) {
    list.innerHTML = '';
    empty.style.display = '';
    return;
  }
  empty.style.display = 'none';
  let html = '';
  rows.forEach(function(item){
    const id = parseInt(item.id || 0, 10) || 0;
    const origin = String(item.origin_system_name || '').trim();
    const title = origin ? ((item.name || item.label || ('#' + id)) + ' | Sistema base: ' + origin) : (item.name || item.label || ('#' + id));
    html += '<span class="ased-chip" title="' + asedEsc(title) + '"><span class="ased-chip-text"><span class="ased-chip-label">' + asedEsc(item.name || item.label || ('#' + id)) + '</span>' + (origin ? ('<span class="ased-chip-origin">' + asedEsc(origin) + '</span>') : '') + '</span><button type="button" class="ased-chip-remove" data-remove-group="' + asedEsc(groupKey) + '" data-remove-id="' + id + '">x</button></span>';
  });
  list.innerHTML = html;
}

function asedSyncHiddenInputs(){
  const box = document.getElementById('asedHiddenInputs');
  if (!box) return;
  let html = '';
  Object.keys(asedState).forEach(function(groupKey){
    const rows = Array.isArray(asedState[groupKey]) ? asedState[groupKey] : [];
    rows.forEach(function(item){
      const id = parseInt(item.id || 0, 10) || 0;
      if (id <= 0) return;
      html += '<input type="hidden" name="map[' + asedEsc(groupKey) + '][selected][]" value="' + id + '">';
      html += '<input type="hidden" name="map[' + asedEsc(groupKey) + '][active][]" value="' + id + '">';
    });
  });
  box.innerHTML = html;
}

function asedRenderAll(){
  Object.keys(ASED_GROUPS).forEach(function(groupKey){
    asedRenderSelect(groupKey);
    asedRenderList(groupKey);
  });
  asedSyncHiddenInputs();
}

function asedAdd(groupKey){
  const select = document.getElementById('ased-select-' + groupKey);
  if (!select) return;
  const detailId = parseInt(select.value || '0', 10) || 0;
  if (detailId <= 0) return;
  const group = ASED_GROUPS[groupKey] || {};
  const source = (Array.isArray(group.items) ? group.items : []).find(function(item){
    return (parseInt(item.id || 0, 10) || 0) === detailId;
  });
  if (!source) return;
  if (!Array.isArray(asedState[groupKey])) asedState[groupKey] = [];
  if (asedState[groupKey].some(function(item){ return (parseInt(item.id || 0, 10) || 0) === detailId; })) return;
  asedState[groupKey].push(Object.assign({}, source));
  asedRenderAll();
}

function asedRemove(groupKey, detailId){
  if (!Array.isArray(asedState[groupKey])) return;
  asedState[groupKey] = asedState[groupKey].filter(function(item){
    return (parseInt(item.id || 0, 10) || 0) !== detailId;
  });
  asedRenderAll();
}

document.addEventListener('click', function(event){
  const addBtn = event.target.closest('[data-add-group]');
  if (addBtn) {
    asedAdd(addBtn.getAttribute('data-add-group') || '');
    return;
  }
  const removeBtn = event.target.closest('[data-remove-group]');
  if (removeBtn) {
    asedRemove(removeBtn.getAttribute('data-remove-group') || '', parseInt(removeBtn.getAttribute('data-remove-id') || '0', 10) || 0);
  }
});

document.addEventListener('change', function(event){
  const select = event.target.closest('.ased-select');
  if (!select) return;
  const groupKey = String(select.id || '').replace('ased-select-', '');
  if (groupKey) {
    asedAdd(groupKey);
  }
});

document.getElementById('asedForm').addEventListener('submit', function(){
  asedSyncHiddenInputs();
});

asedInitState();
asedRenderAll();
</script>
<?php
admin_panel_close();
