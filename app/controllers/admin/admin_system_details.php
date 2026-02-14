<?php
// admin_system_details.php -- CRUD detalles de sistemas (breeds/auspices/tribes/misc)
if (!isset($link) || !$link) { die("Sin conexion BD"); }
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }

include(__DIR__ . '/../../partials/admin/admin_styles.php');
include_once(__DIR__ . '/../../partials/admin/quill_toolbar_inner.php');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function slugify_pretty(string $text): string {
    $text = trim((string)$text);
    if ($text === '') return '';
    if (function_exists('iconv')) { $text = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text; }
    $text = preg_replace('~[^\\pL\\d]+~u', '-', $text);
    $text = trim($text, '-');
    $text = strtolower($text);
    $text = preg_replace('~[^-a-z0-9]+~', '', $text);
    return $text;
}
function update_pretty_id(mysqli $link, string $table, int $id, string $source): void {
    if ($id <= 0) return;
    $slug = slugify_pretty($source);
    if ($slug === '') $slug = (string)$id;
    if ($st = $link->prepare("UPDATE `$table` SET pretty_id=? WHERE id=?")) {
        $st->bind_param("si", $slug, $id);
        $st->execute();
        $st->close();
    }
}
function sanitize_utf8_text(string $s): string {
    if (function_exists('mb_check_encoding') && !mb_check_encoding($s, 'UTF-8')) {
        if (function_exists('iconv')) {
            $s = @iconv('UTF-8', 'UTF-8//IGNORE', $s) ?: $s;
        } elseif (function_exists('mb_convert_encoding')) {
            $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8');
        }
    }
    return $s ?? '';
}

// CSRF simple
if (empty($_SESSION['csrf_admin_system_details'])) {
    $_SESSION['csrf_admin_system_details'] = bin2hex(random_bytes(16));
}
$CSRF = $_SESSION['csrf_admin_system_details'];
function csrf_ok(): bool {
    $t = $_POST['csrf'] ?? '';
    return is_string($t) && $t !== '' && isset($_SESSION['csrf_admin_system_details']) && hash_equals($_SESSION['csrf_admin_system_details'], $t);
}

$tabsAllowed = ['breeds','auspices','tribes','misc'];
$tab = $_GET['tab'] ?? 'breeds';
$tab = in_array($tab, $tabsAllowed, true) ? $tab : 'breeds';
$perPage = isset($_GET['pp']) ? max(10, min(200, (int)$_GET['pp'])) : 25;
$page    = isset($_GET['pg']) ? max(1, (int)$_GET['pg']) : 1;
$q       = trim((string)($_GET['q'] ?? ''));
$offset  = ($page-1)*$perPage;

$flash = [];

$opts_origins = [];
if ($rs = $link->query("SELECT id, name FROM dim_bibliographies ORDER BY name ASC")) {
    while ($r = $rs->fetch_assoc()) { $opts_origins[] = $r; }
    $rs->close();
}

$opts_systems = [];
if ($rs = $link->query("SELECT name FROM dim_systems ORDER BY orden ASC, name ASC")) {
    while ($r = $rs->fetch_assoc()) { $opts_systems[] = (string)$r['name']; }
    $rs->close();
}

$sys = trim((string)($_GET['sys'] ?? ''));

function meta_for(string $tab, array $opts_origins, array $opts_systems): array {
    if ($tab === 'breeds') {
        return [
            'title' => 'Razas',
            'table' => 'dim_breeds',
            'pk' => 'id',
            'name_col' => 'name',
            'order_by' => 'sistema ASC, name ASC',
            'fields' => [
                ['k'=>'name', 'label'=>'Nombre', 'ui'=>'text', 'db'=>'s', 'req'=>true],
                ['k'=>'sistema', 'label'=>'Sistema', 'ui'=>'select_text', 'db'=>'s', 'req'=>true, 'opts'=>$opts_systems],
                ['k'=>'formas', 'label'=>'Formas', 'ui'=>'text', 'db'=>'s', 'req'=>false],
                ['k'=>'energia', 'label'=>'Energia', 'ui'=>'number', 'db'=>'i', 'req'=>false],
                ['k'=>'imagen', 'label'=>'Imagen', 'ui'=>'image', 'db'=>'s', 'req'=>false],
                ['k'=>'origen', 'label'=>'Origen', 'ui'=>'select_int', 'db'=>'i', 'req'=>false, 'opts'=>$opts_origins],
                ['k'=>'desc', 'label'=>'Descripcion', 'ui'=>'wysiwyg', 'db'=>'s', 'req'=>false],
            ],
            'list_cols' => [
                ['k'=>'id','label'=>'ID','w'=>60],
                ['k'=>'name','label'=>'Nombre','w'=>220],
                ['k'=>'sistema','label'=>'Sistema','w'=>160],
                ['k'=>'energia','label'=>'Energia','w'=>80],
            ],
            'has_timestamps' => true,
        ];
    }
    if ($tab === 'auspices') {
        return [
            'title' => 'Auspicios',
            'table' => 'dim_auspices',
            'pk' => 'id',
            'name_col' => 'name',
            'order_by' => 'sistema ASC, name ASC',
            'fields' => [
                ['k'=>'name', 'label'=>'Nombre', 'ui'=>'text', 'db'=>'s', 'req'=>true],
                ['k'=>'sistema', 'label'=>'Sistema', 'ui'=>'select_text', 'db'=>'s', 'req'=>true, 'opts'=>$opts_systems],
                ['k'=>'energia', 'label'=>'Energia', 'ui'=>'number', 'db'=>'i', 'req'=>false],
                ['k'=>'imagen', 'label'=>'Imagen', 'ui'=>'image', 'db'=>'s', 'req'=>false],
                ['k'=>'origen', 'label'=>'Origen', 'ui'=>'select_int', 'db'=>'i', 'req'=>false, 'opts'=>$opts_origins],
                ['k'=>'desc', 'label'=>'Descripcion', 'ui'=>'wysiwyg', 'db'=>'s', 'req'=>false],
            ],
            'list_cols' => [
                ['k'=>'id','label'=>'ID','w'=>60],
                ['k'=>'name','label'=>'Nombre','w'=>220],
                ['k'=>'sistema','label'=>'Sistema','w'=>160],
                ['k'=>'energia','label'=>'Energia','w'=>80],
            ],
            'has_timestamps' => true,
        ];
    }
    if ($tab === 'tribes') {
        return [
            'title' => 'Tribus',
            'table' => 'dim_tribes',
            'pk' => 'id',
            'name_col' => 'name',
            'order_by' => 'sistema ASC, name ASC',
            'fields' => [
                ['k'=>'name', 'label'=>'Nombre', 'ui'=>'text', 'db'=>'s', 'req'=>true],
                ['k'=>'sistema', 'label'=>'Sistema', 'ui'=>'select_text', 'db'=>'s', 'req'=>true, 'opts'=>$opts_systems],
                ['k'=>'afiliacion', 'label'=>'Afiliacion', 'ui'=>'text', 'db'=>'s', 'req'=>false],
                ['k'=>'energia', 'label'=>'Energia', 'ui'=>'number', 'db'=>'i', 'req'=>false],
                ['k'=>'imagen', 'label'=>'Imagen', 'ui'=>'image', 'db'=>'s', 'req'=>false],
                ['k'=>'origen', 'label'=>'Origen', 'ui'=>'select_int', 'db'=>'i', 'req'=>false, 'opts'=>$opts_origins],
                ['k'=>'desc', 'label'=>'Descripcion', 'ui'=>'wysiwyg', 'db'=>'s', 'req'=>false],
                ['k'=>'poderes', 'label'=>'Poderes', 'ui'=>'textarea', 'db'=>'s', 'req'=>false],
            ],
            'list_cols' => [
                ['k'=>'id','label'=>'ID','w'=>60],
                ['k'=>'name','label'=>'Nombre','w'=>220],
                ['k'=>'sistema','label'=>'Sistema','w'=>160],
                ['k'=>'energia','label'=>'Energia','w'=>80],
            ],
            'has_timestamps' => true,
        ];
    }
    // misc
    return [
        'title' => 'Misc Systems',
        'table' => 'fact_misc_systems',
        'pk' => 'id',
        'name_col' => 'name',
        'order_by' => 'sistema ASC, name ASC',
        'fields' => [
            ['k'=>'name', 'label'=>'Nombre', 'ui'=>'text', 'db'=>'s', 'req'=>true],
            ['k'=>'type', 'label'=>'Tipo', 'ui'=>'text', 'db'=>'s', 'req'=>false],
            ['k'=>'sistema', 'label'=>'Sistema', 'ui'=>'select_text', 'db'=>'s', 'req'=>true, 'opts'=>$opts_systems],
            ['k'=>'energianombre', 'label'=>'Energia (nombre)', 'ui'=>'text', 'db'=>'s', 'req'=>false],
            ['k'=>'energiavalor', 'label'=>'Energia (valor)', 'ui'=>'number', 'db'=>'i', 'req'=>false],
            ['k'=>'desc', 'label'=>'Descripcion', 'ui'=>'wysiwyg', 'db'=>'s', 'req'=>false],
            ['k'=>'miscinfo', 'label'=>'Info extra', 'ui'=>'textarea', 'db'=>'s', 'req'=>false],
        ],
        'list_cols' => [
            ['k'=>'id','label'=>'ID','w'=>60],
            ['k'=>'name','label'=>'Nombre','w'=>220],
            ['k'=>'sistema','label'=>'Sistema','w'=>160],
            ['k'=>'type','label'=>'Tipo','w'=>120],
        ],
        'has_timestamps' => false,
    ];
}

$META = meta_for($tab, $opts_origins, $opts_systems);

$quillToolbarInner = admin_quill_toolbar_inner();

// Guardado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crud_action']) && isset($_POST['crud_tab'])) {
    $postTab = (string)$_POST['crud_tab'];
    if (!in_array($postTab, $tabsAllowed, true)) {
        $flash[] = ['type'=>'error','msg'=>'Pestana invalida.'];
    } elseif (!csrf_ok()) {
        $flash[] = ['type'=>'error','msg'=>'CSRF invalido. Recarga.'];
    } else {
        $opts_origins = [];
        if ($rs = $link->query("SELECT id, name FROM dim_bibliographies ORDER BY name ASC")) {
            while ($r = $rs->fetch_assoc()) { $opts_origins[] = $r; }
            $rs->close();
        }
        $opts_systems = [];
        if ($rs = $link->query("SELECT name FROM dim_systems ORDER BY orden ASC, name ASC")) {
            while ($r = $rs->fetch_assoc()) { $opts_systems[] = (string)$r['name']; }
            $rs->close();
        }
        $M = meta_for($postTab, $opts_origins, $opts_systems);
        $action = (string)$_POST['crud_action'];
        $id = (int)($_POST['id'] ?? 0);

        $vals = [];
        foreach ($M['fields'] as $f) {
            $k = $f['k'];
            if (($f['db'] ?? 's') === 'i') {
                $raw = $_POST[$k] ?? 0;
                $vals[$k] = (int)$raw;
            } else {
                $vals[$k] = (string)($_POST[$k] ?? '');
                if (($f['ui'] ?? '') !== 'textarea' && ($f['ui'] ?? '') !== 'wysiwyg') {
                    $vals[$k] = trim($vals[$k]);
                } else {
                    $vals[$k] = sanitize_utf8_text($vals[$k]);
                }
            }
        }

        if ($action !== 'delete') {
            foreach ($M['fields'] as $f) {
                if (!empty($f['req'])) {
                    $k = $f['k'];
                    if (($f['db'] ?? 's') === 'i') {
                        if ((int)$vals[$k] < 0) $flash[] = ['type'=>'error','msg'=>'Campo '.$f['label'].' invalido.'];
                    } else {
                        if (trim((string)$vals[$k]) === '') $flash[] = ['type'=>'error','msg'=>'Campo '.$f['label'].' obligatorio.'];
                    }
                }
            }
        }

        $hasErr = false;
        foreach ($flash as $m) if (($m['type'] ?? '') === 'error') { $hasErr = true; break; }

        if (!$hasErr) {
            $table = $M['table'];
            $pk = $M['pk'];
            $hasImage = false;
            foreach ($M['fields'] as $f) { if (($f['k'] ?? '') === 'imagen') { $hasImage = true; break; } }

            if ($hasImage) {
                $removeFlag = isset($_POST['remove_imagen']) && (string)$_POST['remove_imagen'] === '1';
                if ($removeFlag) {
                    $vals['imagen'] = '';
                }
                if (isset($_FILES['file_imagen']) && is_array($_FILES['file_imagen']) && $_FILES['file_imagen']['error'] === UPLOAD_ERR_OK) {
                    $tmp = $_FILES['file_imagen']['tmp_name'];
                    $orig = $_FILES['file_imagen']['name'] ?? 'img';
                    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
                    if ($ext === '') $ext = 'jpg';
                    $base = slugify_pretty(pathinfo($orig, PATHINFO_FILENAME));
                    if ($base === '') $base = 'img';
                    $name = $base . '-' . date('YmdHis') . '.' . $ext;
                    $destDir = __DIR__ . '/../../../public/img/system';
                    if (!is_dir($destDir)) @mkdir($destDir, 0775, true);
                    $dest = $destDir . '/' . $name;
                    if (@move_uploaded_file($tmp, $dest)) {
                        $vals['imagen'] = 'img/system/' . $name;
                    } else {
                        $flash[] = ['type'=>'error','msg'=>'Error al subir la imagen.'];
                        $hasErr = true;
                    }
                }
            }

            if ($action === 'delete') {
                if ($id <= 0) {
                    $flash[] = ['type'=>'error','msg'=>'Falta ID para borrar.'];
                } else {
                    $sql = "DELETE FROM `$table` WHERE `$pk`=?";
                    $st = $link->prepare($sql);
                    if ($st) {
                        $st->bind_param('i', $id);
                        if ($st->execute()) $flash[] = ['type'=>'ok','msg'=>'Eliminado correctamente.'];
                        else $flash[] = ['type'=>'error','msg'=>'Error al borrar: '.$st->error];
                        $st->close();
                    } else {
                        $flash[] = ['type'=>'error','msg'=>'Error al preparar DELETE: '.$link->error];
                    }
                }
            }

            if ($action === 'create') {
                $cols = []; $ph = []; $types = ''; $bind = [];
                foreach ($M['fields'] as $f) {
                    $cols[] = $f['k'];
                    $ph[] = '?';
                    $types .= (($f['db'] ?? 's') === 'i') ? 'i' : 's';
                    $bind[] = $vals[$f['k']];
                }
                $sql = "INSERT INTO `$table` (".implode(',', array_map(fn($c)=>"`$c`", $cols)).") VALUES (".implode(',', $ph).")";
                if (!empty($M['has_timestamps'])) {
                    $sql = "INSERT INTO `$table` (".implode(',', array_map(fn($c)=>"`$c`", $cols)).", created_at, updated_at) VALUES (".implode(',', $ph).", NOW(), NOW())";
                }
                $st = $link->prepare($sql);
                if ($st) {
                    $st->bind_param($types, ...$bind);
                    if ($st->execute()) {
                        $newId = (int)$st->insert_id;
                        $src = (string)($vals['name'] ?? '');
                        update_pretty_id($link, $table, $newId, $src);
                        $flash[] = ['type'=>'ok','msg'=>'Creado correctamente.'];
                    } else {
                        $flash[] = ['type'=>'error','msg'=>'Error al crear: '.$st->error];
                    }
                    $st->close();
                } else {
                    $flash[] = ['type'=>'error','msg'=>'Error al preparar INSERT: '.$link->error];
                }
            }

            if ($action === 'update') {
                if ($id <= 0) {
                    $flash[] = ['type'=>'error','msg'=>'Falta ID para actualizar.'];
                } else {
                    $sets = []; $types = ''; $bind = [];
                    foreach ($M['fields'] as $f) {
                        $sets[] = "`".$f['k']."`=?";
                        $types .= (($f['db'] ?? 's') === 'i') ? 'i' : 's';
                        $bind[] = $vals[$f['k']];
                    }
                    $sql = "UPDATE `$table` SET ".implode(', ', $sets);
                    if (!empty($M['has_timestamps'])) $sql .= ", updated_at=NOW()";
                    $sql .= " WHERE `$pk`=?";
                    $types .= 'i';
                    $bind[] = $id;
                    $st = $link->prepare($sql);
                    if ($st) {
                        $st->bind_param($types, ...$bind);
                        if ($st->execute()) {
                            $src = (string)($vals['name'] ?? '');
                            update_pretty_id($link, $table, $id, $src);
                            $flash[] = ['type'=>'ok','msg'=>'Actualizado.'];
                        } else {
                            $flash[] = ['type'=>'error','msg'=>'Error al actualizar: '.$st->error];
                        }
                        $st->close();
                    } else {
                        $flash[] = ['type'=>'error','msg'=>'Error al preparar UPDATE: '.$link->error];
                    }
                }
            }

            $tab = $postTab;
            $META = meta_for($tab, $opts_origins, $opts_systems);
        }
    }
}

// Listado
$table = $META['table'];
$pk = $META['pk'];
$nameCol = $META['name_col'];
$where = "WHERE 1=1";
$params = []; $types = '';
if ($q !== '') {
    $where .= " AND `$nameCol` LIKE ?";
    $types .= 's';
    $params[] = "%".$q."%";
}
if ($sys !== '') {
    $where .= " AND `sistema` = ?";
    $types .= 's';
    $params[] = $sys;
}

$sqlCnt = "SELECT COUNT(*) AS c FROM `$table` $where";
$stC = $link->prepare($sqlCnt);
if ($types) $stC->bind_param($types, ...$params);
$stC->execute();
$rsC = $stC->get_result();
$total = ($rsC && ($rowC=$rsC->fetch_assoc())) ? (int)$rowC['c'] : 0;
$stC->close();

$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page-1)*$perPage;

$colsAll = array_map(fn($f)=>"`".$f['k']."`", $META['fields']);
$colsAll[] = "`$pk`";
$colsAll = array_values(array_unique($colsAll));

$sqlList = "SELECT ".implode(',', $colsAll)." FROM `$table` $where ORDER BY ".$META['order_by']." LIMIT ?, ?";
$types2 = $types.'ii';
$params2 = $params; $params2[] = $offset; $params2[] = $perPage;
$stL = $link->prepare($sqlList);
$stL->bind_param($types2, ...$params2);
$stL->execute();
$rsL = $stL->get_result();
$rows = []; $rowMap = [];
while ($r = $rsL->fetch_assoc()) {
    $idv = (int)$r[$pk];
    $rows[] = $r;
    $rowMap[$idv] = $r;
}
$stL->close();

function ui_title(string $tab): string {
    switch ($tab) {
        case 'breeds': return 'Razas';
        case 'auspices': return 'Auspicios';
        case 'tribes': return 'Tribus';
        case 'misc': return 'Misc Systems';
        default: return 'Detalles';
    }
}
function ui_short(string $s, int $n=120): string {
    $s = trim((string)$s);
    $s = preg_replace('/\s+/u',' ', $s);
    if (function_exists('mb_strlen') && mb_strlen($s, 'UTF-8') <= $n) return $s;
    return function_exists('mb_substr') ? mb_substr($s,0,$n,'UTF-8').'...' : substr($s,0,$n).'...';
}

$sysOptions = '<option value="">-- Todos --</option>';
foreach ($opts_systems as $sname) {
    $sel = ($sname === $sys) ? ' selected' : '';
    $sysOptions .= '<option value="'.h($sname).'"'.$sel.'>'.h($sname).'</option>';
}
$actions = '<span style="margin-left:auto; display:flex; gap:8px; align-items:center;">'
    . '<label style="text-align:left;">Sistema '
    . '<select class="select" id="filterSystemDetails">'.$sysOptions.'</select></label>'
    . '<button class="btn btn-green" id="btnNew" type="button">+ Nuevo</button>'
    . '<label style="text-align:left;">Filtro rapido '
    . '<input class="inp" type="text" id="quickFilterSystemDetails" placeholder="En esta pagina..."></label>'
    . '</span>';
admin_panel_open('Detalles de sistemas', $actions);
?>

<?php if (!empty($flash)): ?>
    <div class="flash">
        <?php foreach ($flash as $m):
            $cl = $m['type']==='ok'?'ok':($m['type']==='error'?'err':'info'); ?>
            <div class="<?= $cl ?>"><?= h($m['msg']) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<style>
.tabs { display:flex; gap:8px; flex-wrap:wrap; }
.tablnk{ display:inline-block; padding:6px 10px; border:1px solid #000088; background:#050b36; color:#cfe; border-radius:999px; text-decoration:none; font-size:12px; }
.tablnk.active{ background:#001199; color:#33FFFF; }
.modal-back{ position:fixed; inset:0; background:rgba(0,0,0,.6); display:none; align-items:center; justify-content:center; z-index:9999; padding:14px; box-sizing:border-box; }
.modal{ width:min(1100px,96vw); max-height:92vh; overflow:hidden; background:#05014E; border:1px solid #000088; border-radius:12px; padding:12px; position:relative; display:flex; flex-direction:column; }
.modal h3{ margin:0 0 8px; color:#33FFFF; }
.modal-body{ flex:1; overflow:auto; padding-right:6px; min-height:0; }
.modal-actions{ position:sticky; bottom:0; display:flex; gap:10px; justify-content:flex-end; padding:10px 0 0; margin-top:10px; background:linear-gradient(to top, rgba(5,1,78,1), rgba(5,1,78,0)); border-top:1px solid #000088; }
.grid{ display:grid; grid-template-columns:repeat(2, minmax(280px, 1fr)); gap:10px 12px; }
.grid .field-full{ grid-column:1 / -1; }
.grid label{ font-size:12px; color:#cfe; display:block; text-align:left; }
.grid input, .grid select, .grid textarea { width:100%; box-sizing:border-box; }
textarea.inp { min-height:120px; resize:vertical; white-space:pre-wrap; }
.img-row{ display:flex; gap:12px; align-items:center; }
.img-preview{ width:32px; height:32px; border-radius:50%; background:#001188; border:1px solid #000088; display:flex; align-items:center; justify-content:center; overflow:hidden; flex:0 0 32px; }
.img-preview img{ width:100%; height:100%; object-fit:cover; display:none; }
.img-preview .ph{ font-size:9px; color:#88a; }
.img-controls{ flex:1; display:grid; gap:6px; }
.img-controls input[type="file"]{ font-size:12px; color:#cfe; }
.img-controls .row{ display:flex; align-items:center; gap:10px; }
.img-controls .row label{ margin:0; font-size:11px; color:#cfe; }
.img-controls .row input[type="checkbox"]{ transform:scale(1.05); }
.ql-toolbar.ql-snow{ border:1px solid #000088 !important; background:#050b36 !important; border-radius:8px 8px 0 0; }
.ql-container.ql-snow{ border:1px solid #000088 !important; border-top:none !important; background:#000033 !important; color:#fff !important; border-radius:0 0 8px 8px; }
.ql-editor{ min-height:220px; font-size:12px; }
.ql-snow .ql-stroke{ stroke:#cfe !important; }
.ql-snow .ql-fill{ fill:#cfe !important; }
.ql-snow .ql-picker{ color:#cfe !important; }
.ql-snow .ql-picker-options{
  background:#050b36 !important;
  border:1px solid #000088 !important;
}
.ql-snow .ql-picker-item{
  color:#cfe !important;
}
</style>

<div class="tabs" style="margin:6px 0 10px;">
  <?php
    $baseTabs = "?p=".urlencode($_GET['p'] ?? 'talim')."&s=".urlencode($_GET['s'] ?? 'admin_system_details');
    $baseTabs .= "&pp=".$perPage."&q=".urlencode($q)."&sys=".urlencode($sys);
  ?>
  <a class="tablnk <?= $tab==='breeds'?'active':'' ?>" href="<?= $baseTabs ?>&tab=breeds">Razas</a>
  <a class="tablnk <?= $tab==='auspices'?'active':'' ?>" href="<?= $baseTabs ?>&tab=auspices">Auspicios</a>
  <a class="tablnk <?= $tab==='tribes'?'active':'' ?>" href="<?= $baseTabs ?>&tab=tribes">Tribus</a>
  <a class="tablnk <?= $tab==='misc'?'active':'' ?>" href="<?= $baseTabs ?>&tab=misc">Misc</a>
</div>

<table class="table" id="tablaDetails">
    <thead>
      <tr>
        <?php foreach ($META['list_cols'] as $c): ?>
          <th style="width:<?= (int)($c['w'] ?? 120) ?>px;"><?= h($c['label']) ?></th>
        <?php endforeach; ?>
        <th style="width:190px;">Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <?php
          $searchParts = [];
          foreach ($META['list_cols'] as $c) { $k = $c['k']; $searchParts[] = (string)($r[$k] ?? ''); }
          $search = trim(implode(' ', $searchParts));
          if (function_exists('mb_strtolower')) { $search = mb_strtolower($search, 'UTF-8'); }
          else { $search = strtolower($search); }
        ?>
        <tr data-search="<?= h($search) ?>">
          <?php foreach ($META['list_cols'] as $c):
              $k = $c['k'];
              $val = (string)($r[$k] ?? '');
              if ($k === 'id') $val = (string)(int)$r[$pk];
          ?>
            <td>
              <?php if ($k === 'id'): ?>
                <strong style="color:#33FFFF;"><?= (int)$r[$pk] ?></strong>
              <?php else: ?>
                <?= h(ui_short($val, 140)) ?>
              <?php endif; ?>
            </td>
          <?php endforeach; ?>
          <td>
            <button class="btn" type="button" data-edit="<?= (int)$r[$pk] ?>">Editar</button>
            <button class="btn btn-red" type="button" data-del="<?= (int)$r[$pk] ?>">Borrar</button>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($rows)): ?>
        <tr><td colspan="<?= count($META['list_cols'])+1 ?>" style="color:#bbb;">(Sin resultados)</td></tr>
      <?php endif; ?>
    </tbody>
</table>

<div class="pager">
  <?php
    $base = "?p=".urlencode($_GET['p'] ?? 'talim')."&s=".urlencode($_GET['s'] ?? 'admin_system_details');
    $base .= "&tab=".urlencode($tab)."&pp=".$perPage."&q=".urlencode($q)."&sys=".urlencode($sys);
    $prev = max(1, $page-1);
    $next = min($pages, $page+1);
  ?>
  <a href="<?= $base ?>&pg=1">&laquo; Primero</a>
  <a href="<?= $base ?>&pg=<?= $prev ?>">&lsaquo; Anterior</a>
  <span class="cur">Pag <?= $page ?>/<?= $pages ?> ? Total <?= (int)$total ?></span>
  <a href="<?= $base ?>&pg=<?= $next ?>">Siguiente &rsaquo;</a>
  <a href="<?= $base ?>&pg=<?= $pages ?>">Ultimo &raquo;</a>
</div>

<div class="modal-back" id="mb">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <h3 id="modalTitle">Nuevo</h3>
    <form method="post" id="formCrud" enctype="multipart/form-data" style="margin:0;">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
      <input type="hidden" name="crud_tab" id="crud_tab" value="<?= h($tab) ?>">
      <input type="hidden" name="crud_action" id="crud_action" value="create">
      <input type="hidden" name="id" id="f_id" value="0">
      <div class="modal-body">
        <div class="grid" id="formGrid"></div>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-red" id="btnCancel">Cancelar</button>
        <button type="submit" class="btn btn-green" id="btnSave">Guardar</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-back" id="mbDel">
  <div class="modal" style="width:min(560px,96vw);">
    <h3>Confirmar borrado</h3>
    <div style="color:#cfe; font-size:12px; line-height:1.4; margin-bottom:10px;">
      Esto eliminara el registro definitivamente.
    </div>
    <form method="post" id="formDel" style="margin:0;">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
      <input type="hidden" name="crud_tab" value="<?= h($tab) ?>">
      <input type="hidden" name="crud_action" value="delete">
      <input type="hidden" name="id" id="del_id" value="0">
      <div class="modal-actions">
        <button type="button" class="btn" id="btnDelCancel">Cancelar</button>
        <button type="submit" class="btn btn-red">Borrar</button>
      </div>
    </form>
  </div>
</div>

<link href="/assets/vendor/quill/1.3.7/quill.snow.css" rel="stylesheet">
<script src="/assets/vendor/quill/1.3.7/quill.min.js"></script>

<script>
var TAB = <?= json_encode($tab, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
var META = <?= json_encode($META, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
var QUILL_TOOLBAR_INNER = <?= json_encode($quillToolbarInner, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;

var ROWMAP = <?= json_encode($rowMap, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
var OPTS_ORIGINS = <?= json_encode(array_map(fn($r)=>['id'=>(int)$r['id'], 'name'=>$r['name']], $opts_origins), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
var OPTS_SYSTEMS = <?= json_encode(array_values($opts_systems), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;

function pickOptsForField(fieldKey){
  if (TAB !== 'misc' && fieldKey === 'origen') return OPTS_ORIGINS;
  if (fieldKey === 'sistema') return OPTS_SYSTEMS;
  return [];
}

var QUILL_MAP = {};

function initEditors(){
  if (typeof Quill === 'undefined') return;
  document.querySelectorAll('[data-wys="1"]').forEach(function(wrap){
    var taId = wrap.getAttribute('data-ta');
    var toolbarId = wrap.getAttribute('data-toolbar');
    var editorId  = wrap.getAttribute('data-editor');
    if (!taId || QUILL_MAP[taId]) return;
    var textarea = document.getElementById(taId);
    var toolbar  = document.getElementById(toolbarId);
    var editor   = document.getElementById(editorId);
    if (!textarea || !toolbar || !editor) return;
    var q = new Quill(editor, { theme: 'snow', modules: { toolbar: toolbar, clipboard: { matchVisual: false } } });
    var html = textarea.value || '';
    q.root.innerHTML = html;
    QUILL_MAP[taId] = q;
  });
}

function syncEditorsToTextarea(){
  Object.keys(QUILL_MAP).forEach(function(taId){
    var q = QUILL_MAP[taId];
    var textarea = document.getElementById(taId);
    if (!q || !textarea) return;
    var html = q.root.innerHTML || '';
    var plain = (q.getText() || '').replace(/\s+/g,' ').trim();
    textarea.value = plain ? html : '';
  });
}

(function(){
  var mb = document.getElementById('mb');
  var btnNew = document.getElementById('btnNew');
  var btnCancel = document.getElementById('btnCancel');
  var grid = document.getElementById('formGrid');

  var mbDel = document.getElementById('mbDel');
  var btnDelCancel = document.getElementById('btnDelCancel');
  var delId = document.getElementById('del_id');

  function el(tag, attrs, html){
    var e = document.createElement(tag);
    if (attrs) Object.keys(attrs).forEach(function(k){
      if (k==='class') e.className = attrs[k];
      else if (k==='for') e.htmlFor = attrs[k];
      else e.setAttribute(k, attrs[k]);
    });
    if (html !== undefined) e.innerHTML = html;
    return e;
  }

  function escapeHtml(str){
    return String(str||'')
      .replace(/&/g,'&amp;')
      .replace(/</g,'&lt;')
      .replace(/>/g,'&gt;')
      .replace(/"/g,'&quot;')
      .replace(/'/g,'&#039;');
  }

  function buildSelect(name, opts, includeZero, id){
    var s = el('select', {name:name, class:'select'});
    if (id) s.id = id;
    if (includeZero) s.appendChild(el('option', {value:'0'}, '--'));
    else s.appendChild(el('option', {value:''}, '--'));
    (opts||[]).forEach(function(it){
      s.appendChild(el('option', {value:String(it.id)}, escapeHtml(it.name||('ID '+it.id))));
    });
    return s;
  }

  function buildSelectText(name, opts, id){
    var s = el('select', {name:name, class:'select'});
    if (id) s.id = id;
    s.appendChild(el('option', {value:''}, '--'));
    (opts||[]).forEach(function(txt){
      var val = String(txt ?? '');
      if (!val) return;
      s.appendChild(el('option', {value: val}, escapeHtml(val)));
    });
    return s;
  }

  function ensureOption(select, value){
    if (!select || value === undefined || value === null) return;
    var v = String(value);
    var exists = false;
    Array.prototype.forEach.call(select.options, function(o){
      if (String(o.value) === v) exists = true;
    });
    if (!exists && v !== '') {
      select.appendChild(el('option', {value:v}, escapeHtml(v)));
    }
  }

  function buildField(f){
    var wrap = el('div');
    wrap.className = 'field';

    var label = el('label');
    var req = f.req ? ' <span class="badge">oblig.</span>' : '';
    label.innerHTML = '<span>'+escapeHtml(f.label)+'</span>'+req;

    var k = f.k;
    var ui = f.ui || 'text';
    var input;

    if (ui === 'textarea') {
      if (k === 'poderes' || k === 'miscinfo') wrap.className = 'field field-full';
      input = el('textarea', {name:k, id:'f_'+k, class:'inp'});
      label.appendChild(input);
      wrap.appendChild(label);
      return wrap;
    }

    if (ui === 'wysiwyg') {
      wrap.className = 'field field-full';
      var taId = 'f_'+k;
      var toolbarId = 'qt_'+k;
      var editorId  = 'qe_'+k;
      input = el('textarea', {name:k, id:taId, class:'inp', style:'display:none;'});
      var wysWrap = el('div', {
        'data-wys':'1',
        'data-ta': taId,
        'data-toolbar': toolbarId,
        'data-editor': editorId
      });
      wysWrap.className = 'wys-wrap';

      var tb = el('div', {id:toolbarId, class:'ql-toolbar ql-snow'}, QUILL_TOOLBAR_INNER);
      var ed = el('div', {id:editorId, class:'ql-container ql-snow'});
      wysWrap.appendChild(tb);
      wysWrap.appendChild(ed);

      label.appendChild(input);
      wrap.appendChild(label);
      wrap.appendChild(wysWrap);
      return wrap;
    }

    if (ui === 'select_int') {
      input = buildSelect(k, pickOptsForField(k), true, 'f_'+k);
      label.appendChild(input);
      wrap.appendChild(label);
      return wrap;
    }

    if (ui === 'select_text') {
      input = buildSelectText(k, pickOptsForField(k), 'f_'+k);
      label.appendChild(input);
      wrap.appendChild(label);
      return wrap;
    }

    if (ui === 'image') {
      wrap.className = 'field field-full';
      var row = el('div', {class:'img-row'});
      var prev = el('div', {class:'img-preview'});
      var img = el('img', {id:'img_'+k, alt:'preview'});
      var ph = el('div', {class:'ph', id:'ph_'+k}, 'Sin');
      prev.appendChild(img);
      prev.appendChild(ph);

      var controls = el('div', {class:'img-controls'});
      input = el('input', {type:'text', name:k, id:'f_'+k, class:'inp', placeholder:'img/system/archivo.jpg'});
      var file = el('input', {type:'file', name:'file_'+k, id:'file_'+k, accept:'image/*'});
      var row2 = el('div', {class:'row'});
      var chk = el('input', {type:'checkbox', id:'remove_'+k, name:'remove_'+k, value:'1'});
      var chkLbl = el('label', {for:'remove_'+k}, 'Quitar imagen');
      row2.appendChild(chk);
      row2.appendChild(chkLbl);

      controls.appendChild(input);
      controls.appendChild(file);
      controls.appendChild(row2);

      row.appendChild(prev);
      row.appendChild(controls);
      label.appendChild(row);
      wrap.appendChild(label);
      return wrap;
    }

    if (ui === 'number') {
      input = el('input', {type:'number', name:k, id:'f_'+k, class:'inp'});
      label.appendChild(input);
      wrap.appendChild(label);
      return wrap;
    }

    input = el('input', {type:'text', name:k, id:'f_'+k, class:'inp'});
    label.appendChild(input);
    wrap.appendChild(label);
    return wrap;
  }

  function openModal(action, id){
    grid.innerHTML = '';
    document.getElementById('crud_action').value = action;
    document.getElementById('crud_tab').value = TAB;
    document.getElementById('f_id').value = id || 0;

    (META.fields||[]).forEach(function(f){
      var field = buildField(f);
      grid.appendChild(field);
    });

    if (action === 'update' && id && ROWMAP[id]) {
      var row = ROWMAP[id];
      (META.fields||[]).forEach(function(f){
        var k = f.k;
        var ui = f.ui || 'text';
        var e = document.getElementById('f_'+k);
        if (!e) return;
        var val = row[k] ?? '';
        if (ui === 'number' || ui === 'select_int') {
          e.value = String(val ?? 0);
        } else if (ui === 'select_text') {
          ensureOption(e, val);
          e.value = String(val ?? '');
        } else {
          e.value = String(val ?? '');
        }
      });
    }

    (META.fields||[]).forEach(function(f){
      if ((f.ui || '') === 'image') {
        var k = f.k;
        var path = '';
        if (action === 'update' && id && ROWMAP[id]) {
          path = String(ROWMAP[id][k] ?? '');
        }
        updateImagePreview(k, path);
      }
    });

    initEditors();
    document.getElementById('modalTitle').textContent = (action==='create'?'Nuevo - ':'Editar - ') + (META.title||'');
    mb.style.display = 'flex';
  }

  function closeModal(){ mb.style.display = 'none'; grid.innerHTML=''; }
  function openCreate(){ openModal('create', 0); }
  function openEdit(id){ openModal('update', id); }
  function openDelete(id){ delId.value = String(id||0); mbDel.style.display='flex'; }
  function closeDelete(){ mbDel.style.display='none'; delId.value='0'; }

  btnNew.addEventListener('click', openCreate);
  btnCancel.addEventListener('click', closeModal);
  mb.addEventListener('click', function(e){ if (e.target === mb) closeModal(); });

  btnDelCancel.addEventListener('click', closeDelete);
  mbDel.addEventListener('click', function(e){ if (e.target === mbDel) closeDelete(); });

  Array.prototype.forEach.call(document.querySelectorAll('button[data-edit]'), function(b){
    b.addEventListener('click', function(){
      var id = parseInt(b.getAttribute('data-edit')||'0',10)||0;
      openEdit(id);
    });
  });
  Array.prototype.forEach.call(document.querySelectorAll('button[data-del]'), function(b){
    b.addEventListener('click', function(){
      var id = parseInt(b.getAttribute('data-del')||'0',10)||0;
      openDelete(id);
    });
  });

  document.getElementById('formCrud').addEventListener('submit', function(ev){
    syncEditorsToTextarea();
  });

  function normalizeImgPath(p){
    var v = String(p || '');
    if (!v) return '';
    if (v.startsWith('http://') || v.startsWith('https://') || v.startsWith('//')) return v;
    if (v.startsWith('/')) return v;
    return '/' + v;
  }

  function updateImagePreview(k, path){
    var img = document.getElementById('img_'+k);
    var ph = document.getElementById('ph_'+k);
    if (!img || !ph) return;
    var src = normalizeImgPath(path);
    if (src) {
      img.src = src;
      img.style.display = 'block';
      ph.style.display = 'none';
    } else {
      img.removeAttribute('src');
      img.style.display = 'none';
      ph.style.display = 'block';
    }
  }

  document.addEventListener('change', function(e){
    var t = e.target;
    if (!t) return;
    if (t.id && t.id.startsWith('file_')) {
      var k = t.id.replace('file_','');
      if (t.files && t.files[0]) {
        var reader = new FileReader();
        reader.onload = function(ev2){
          updateImagePreview(k, ev2.target.result || '');
        };
        reader.readAsDataURL(t.files[0]);
      }
    }
    if (t.id && t.id.startsWith('remove_')) {
      var k2 = t.id.replace('remove_','');
      if (t.checked) {
        var input = document.getElementById('f_'+k2);
        if (input) input.value = '';
        updateImagePreview(k2, '');
      }
    }
  });
})();
</script>

<script>
(function(){
  var input = document.getElementById('quickFilterSystemDetails');
  if (!input) return;
  input.addEventListener('input', function(){
    var q = (this.value || '').toLowerCase();
    document.querySelectorAll('#tablaDetails tbody tr').forEach(function(tr){
      var hay = (tr.getAttribute('data-search') || tr.textContent || '').toLowerCase();
      tr.style.display = hay.indexOf(q) !== -1 ? '' : 'none';
    });
  });
})();
</script>

<script>
(function(){
  var sel = document.getElementById('filterSystemDetails');
  if (!sel) return;
  sel.addEventListener('change', function(){
    var url = new URL(window.location.href);
    var v = this.value || '';
    if (v) url.searchParams.set('sys', v);
    else url.searchParams.delete('sys');
    var qs = url.searchParams.toString();
    window.location.href = url.pathname + (qs ? ('?'+qs) : '');
  });
})();
</script>

<?php admin_panel_close(); ?>
