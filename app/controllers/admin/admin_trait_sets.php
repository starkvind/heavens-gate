<?php
// admin_trait_sets.php  Configurar traits por sistema
include(__DIR__ . '/../../partials/admin/admin_styles.php');

if (!isset($link) || !$link) { die('DB error'); }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
if (empty($_SESSION['csrf_trait_sets'])) {
    $_SESSION['csrf_trait_sets'] = bin2hex(random_bytes(16));
}
$CSRF = $_SESSION['csrf_trait_sets'];
function csrf_ok($t){ return is_string($t) && $t !== '' && isset($_SESSION['csrf_trait_sets']) && hash_equals($_SESSION['csrf_trait_sets'], $t); }

$flash = [];

// Systems
$systems = [];
if ($rs = $link->query("SELECT id, name FROM dim_systems ORDER BY name")) {
    while ($r = $rs->fetch_assoc()) { $systems[] = ['id'=>(int)$r['id'], 'name'=>(string)$r['name']]; }
    $rs->close();
}
$system_id = isset($_GET['system_id']) ? (int)$_GET['system_id'] : 0;
if ($system_id <= 0 && !empty($systems)) $system_id = (int)$systems[0]['id'];

// Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_trait_sets'])) {
    $system_id = (int)($_POST['system_id'] ?? 0);
    if ($system_id <= 0) {
        $flash[] = ['type'=>'err','msg'=>'Sistema inválido.'];
    } elseif (!csrf_ok($_POST['csrf'] ?? '')) {
        $flash[] = ['type'=>'err','msg'=>'CSRF inválido.'];
    } else {
        $include = isset($_POST['include']) ? array_map('intval', (array)$_POST['include']) : [];
        $include = array_values(array_filter($include, fn($v)=>$v>0));
        $incSet = array_fill_keys($include, true);
        $sort = isset($_POST['sort_order']) && is_array($_POST['sort_order']) ? $_POST['sort_order'] : [];

        $link->begin_transaction();
        $ok = true;

        if (empty($include)) {
            $ok = $link->query("DELETE FROM fact_trait_sets WHERE system_id={$system_id}");
        } else {
            $idList = implode(',', $include);
            $ok = $link->query("DELETE FROM fact_trait_sets WHERE system_id={$system_id} AND trait_id NOT IN ({$idList})");
        }

        if ($ok) {
            if ($st = $link->prepare("INSERT INTO fact_trait_sets (system_id, trait_id, sort_order, is_active)
                                      VALUES (?,?,?,1)
                                      ON DUPLICATE KEY UPDATE sort_order=VALUES(sort_order), is_active=1, updated_at=NOW()")) {
                foreach ($include as $tid) {
                    $ord = isset($sort[$tid]) ? (int)$sort[$tid] : 0;
                    $st->bind_param('iii', $system_id, $tid, $ord);
                    if (!$st->execute()) { $ok = false; break; }
                }
                $st->close();
            } else {
                $ok = false;
            }
        }

        if ($ok) {
            $link->commit();
            $flash[] = ['type'=>'ok','msg'=>'Guardado.'];
        } else {
            $link->rollback();
            $flash[] = ['type'=>'err','msg'=>'Error al guardar.'];
        }
    }
}

// Traits catalog
$traits_by_type = [];
$trait_types = [];
$trait_order_fixed = ['Atributos','Talentos','Técnicas','Conocimientos','Trasfondos'];
if ($st = $link->prepare("SELECT id, name, kind AS tipo FROM dim_traits WHERE kind IS NOT NULL AND TRIM(kind) <> '' ORDER BY kind, name")) {
    $st->execute(); $rs = $st->get_result();
    while ($r = $rs->fetch_assoc()) {
        $tipo = (string)$r['tipo'];
        if (!isset($traits_by_type[$tipo])) $traits_by_type[$tipo] = [];
        $traits_by_type[$tipo][] = ['id'=>(int)$r['id'], 'name'=>(string)$r['name']];
    }
    $st->close();
}
$trait_types = $trait_order_fixed;
foreach (array_keys($traits_by_type) as $tipo) {
    if (!in_array($tipo, $trait_types, true)) $trait_types[] = $tipo;
}

// Existing set
$existing = [];
if ($system_id > 0) {
    if ($rs = $link->query("SELECT trait_id, sort_order, is_active FROM fact_trait_sets WHERE system_id={$system_id}")) {
        while ($r = $rs->fetch_assoc()) {
            $existing[(int)$r['trait_id']] = ['sort_order'=>(int)$r['sort_order'], 'is_active'=>(int)$r['is_active']];
        }
        $rs->close();
    }
}

admin_panel_open('Traits por sistema', '<span class="small-note">Configura qué traits se muestran por sistema</span>');
?>

<style>
.traits-grid{ display:grid; grid-template-columns:repeat(2, minmax(280px,1fr)); gap:12px; }
.traits-group{ background:#04023b; border:1px solid #000088; border-radius:10px; padding:10px; }
.traits-title{ font-weight:700; color:#9ff; margin-bottom:8px; }
.trait-row{ display:grid; grid-template-columns: 18px 1fr 70px; gap:8px; align-items:center; margin:4px 0; }
.trait-row input[type="number"]{ width:70px; }
</style>

<?php foreach ($flash as $f): ?>
  <div class="flash <?= $f['type']==='ok'?'ok':'err' ?>"><?= h($f['msg']) ?></div>
<?php endforeach; ?>

<form method="get" action="/talim" style="margin-bottom:10px;">
  <input type="hidden" name="s" value="admin_trait_sets">
  <label>Sistema
    <select class="select" name="system_id" onchange="this.form.submit()">
      <?php foreach ($systems as $s): ?>
        <option value="<?= (int)$s['id'] ?>" <?= $system_id===(int)$s['id']?'selected':'' ?>><?= h($s['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
</form>

<form method="post" action="/talim?s=admin_trait_sets&system_id=<?= (int)$system_id ?>">
  <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
  <input type="hidden" name="system_id" value="<?= (int)$system_id ?>">
  <input type="hidden" name="save_trait_sets" value="1">

  <div class="traits-grid">
    <?php foreach ($trait_types as $tipo): $list = $traits_by_type[$tipo] ?? []; if (!$list) continue; ?>
      <div class="traits-group">
        <div class="traits-title"><?= h($tipo) ?></div>
        <?php foreach ($list as $t):
            $tid = (int)$t['id'];
            $ex = $existing[$tid] ?? null;
            $checked = $ex && (int)$ex['is_active'] === 1;
            $ord = $ex ? (int)$ex['sort_order'] : 0;
        ?>
          <label class="trait-row">
            <input type="checkbox" name="include[]" value="<?= $tid ?>" <?= $checked?'checked':'' ?>>
            <span><?= h($t['name']) ?></span>
            <input class="inp" type="number" name="sort_order[<?= $tid ?>]" value="<?= $ord ?>">
          </label>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="modal-actions" style="margin-top:12px;">
    <button type="submit" class="btn btn-green">Guardar</button>
  </div>
</form>

<?php admin_panel_close(); ?>
