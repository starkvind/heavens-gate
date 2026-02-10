<?php
/**
 * admin_docs_crud.php — CRUD autocontenido (Documentos + Secciones)
 * WYSIWYG SIN CKEDITOR: Quill (CDN) — sin API key, sin carpetas de plugins.
 *
 * Tablas:
 *  - dim_doc_categories: secciones/categorías (id, tipo, orden, created_at, updated_at)
 *  - fact_docs: documentos (id, seccion, titulo, texto, source, origin, created_at, updated_at)
 *
 * Requisitos:
 *  - Debe existir $link (mysqli) ya conectado.
 */

if (!isset($link) || !$link) { die("Sin conexión BD"); }
if (session_status() === PHP_SESSION_NONE) { @session_start(); }

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function str_has($hay, $needle){ return $needle !== '' && mb_stripos((string)$hay, (string)$needle) !== false; }

function fetchPairs(mysqli $link, string $sql): array {
    $out = [];
    $q = @$link->query($sql);
    if (!$q) return $out;
    while ($r = $q->fetch_assoc()) {
        $id = isset($r['id']) ? (int)$r['id'] : (int)($r['value'] ?? 0);
        $nm = (string)($r['name'] ?? $r['tipo'] ?? '');
        $out[$id] = $nm;
    }
    $q->close();
    return $out;
}

/* -----------------------------
   CSRF (simple)
------------------------------ */
if (empty($_SESSION['csrf_admin_docs'])) {
    $_SESSION['csrf_admin_docs'] = bin2hex(random_bytes(16));
}
$CSRF = $_SESSION['csrf_admin_docs'];
function csrf_ok(): bool {
    $t = $_POST['csrf'] ?? '';
    return is_string($t) && $t !== '' && isset($_SESSION['csrf_admin_docs']) && hash_equals($_SESSION['csrf_admin_docs'], $t);
}

/* -----------------------------
   Config UI
------------------------------ */
$tabsAllowed = ['docs','sections']; // docs = fact_docs, sections = documentacion
$tab = $_GET['tab'] ?? 'docs';
$tab = in_array($tab, $tabsAllowed, true) ? $tab : 'docs';

$perPage = isset($_GET['pp']) ? max(10, min(200, (int)$_GET['pp'])) : 25;
$page    = isset($_GET['pg']) ? max(1, (int)$_GET['pg']) : 1;
$q       = trim((string)($_GET['q'] ?? ''));
$offset  = ($page-1)*$perPage;

$flash = [];

/* -----------------------------
   Opciones de referencia
------------------------------ */
$opts_sections = fetchPairs($link, "SELECT id, tipo FROM dim_doc_categories ORDER BY orden ASC, tipo ASC");
$opts_origins  = fetchPairs($link, "SELECT id, name FROM dim_bibliographies ORDER BY name ASC");

/* -----------------------------
   Metadatos CRUD
------------------------------ */
function meta_for(string $tab, array $opts_sections, array $opts_origins): array {

    if ($tab === 'sections') {
        return [
            'title' => 'Secciones',
            'table' => 'dim_doc_categories',
            'pk'    => 'id',
            'name_col' => 'tipo',
            'order_by' => 'orden ASC, tipo ASC',
            'fields' => [
                ['k'=>'tipo',  'label'=>'Nombre', 'ui'=>'text',   'db'=>'s', 'req'=>true, 'max'=>100],
                ['k'=>'orden', 'label'=>'Orden',  'ui'=>'number', 'db'=>'i', 'req'=>true, 'min'=>0, 'max'=>999],
            ],
            'list_cols' => [
                ['k'=>'id','label'=>'ID','w'=>70],
                ['k'=>'tipo','label'=>'Sección','w'=>320],
                ['k'=>'orden','label'=>'Orden','w'=>90],
            ],
            'has_timestamps' => true,
        ];
    }

    // docs
    return [
        'title' => 'Documentos',
        'table' => 'fact_docs',
        'pk'    => 'id',
        'name_col' => 'titulo',
        'order_by' => 'id DESC',
        'fields' => [
            ['k'=>'seccion','label'=>'Sección', 'ui'=>'select_int', 'db'=>'i','req'=>true,'opts'=>$opts_sections],
            ['k'=>'titulo', 'label'=>'Título',  'ui'=>'text',       'db'=>'s','req'=>true,'max'=>150],
            ['k'=>'texto',  'label'=>'Texto',   'ui'=>'wysiwyg',    'db'=>'s','req'=>true],   // Quill
            ['k'=>'source', 'label'=>'Fuente',  'ui'=>'textarea',   'db'=>'s','req'=>false],
            ['k'=>'origin', 'label'=>'Origen',  'ui'=>'select_int', 'db'=>'i','req'=>true,'opts'=>$opts_origins],
        ],
        'list_cols' => [
            ['k'=>'id','label'=>'ID','w'=>70],
            ['k'=>'titulo','label'=>'Título','w'=>420],
            ['k'=>'seccion_name','label'=>'Sección','w'=>220],
            ['k'=>'origin_name','label'=>'Origen','w'=>160],
        ],
        'has_timestamps' => true,
    ];
}

$META = meta_for($tab, $opts_sections, $opts_origins);

/* -----------------------------
   Guardado (POST)
------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crud_action']) && isset($_POST['crud_tab'])) {
    $postTab = (string)$_POST['crud_tab'];
    if (!in_array($postTab, $tabsAllowed, true)) {
        $flash[] = ['type'=>'error','msg'=>'❌ Pestaña inválida.'];
    } elseif (!csrf_ok()) {
        $flash[] = ['type'=>'error','msg'=>'❌ CSRF inválido. Recarga la página.'];
    } else {
        // refrescar secciones por si se editaron
        $opts_sections = fetchPairs($link, "SELECT id, tipo FROM dim_doc_categories ORDER BY orden ASC, tipo ASC");
$opts_origins  = fetchPairs($link, "SELECT id, name FROM dim_bibliographies ORDER BY name ASC");

        $M = meta_for($postTab, $opts_sections, $opts_origins);
        $action = (string)$_POST['crud_action'];
        $id = (int)($_POST['id'] ?? 0);

        // recoger valores
        $vals = [];
            foreach ($M['fields'] as $f) {
                $k = $f['k'];
                if (($f['db'] ?? 's') === 'i') {
                    $raw = $_POST[$k] ?? 0;
                    $vals[$k] = (int)$raw;
                } else {
                    // NO trim para textarea/wysiwyg (respetar HTML)
                    $vals[$k] = (string)($_POST[$k] ?? '');
                    if (($f['ui'] ?? '') !== 'textarea' && ($f['ui'] ?? '') !== 'wysiwyg') {
                        $vals[$k] = trim($vals[$k]);
                    }
                }
            }

        // normalizaciones
        foreach ($M['fields'] as $f) {
            $k = $f['k'];
            if (($f['db'] ?? 's') === 's') {
                if (!isset($vals[$k]) || $vals[$k] === null) $vals[$k] = '';
            }
        }

        // validaciones mínimas
        if ($action !== 'delete') {
            foreach ($M['fields'] as $f) {
                if (!empty($f['req'])) {
                    $k = $f['k'];
                    if (($f['db'] ?? 's') === 'i') {
                        if ((int)$vals[$k] < 0) $flash[] = ['type'=>'error','msg'=>'⚠ '.$f['label'].' inválido.'];
                    } else {
                        $v = (string)$vals[$k];
                        $plain = trim(strip_tags($v));
                        if (($f['ui'] ?? '') === 'wysiwyg') {
                            if ($plain === '') $flash[] = ['type'=>'error','msg'=>'⚠ '.$f['label'].' es obligatorio.'];
                        } else {
                            if (trim($v) === '') $flash[] = ['type'=>'error','msg'=>'⚠ '.$f['label'].' es obligatorio.'];
                        }
                    }
                }
            }
        }

        $hasErr = false;
        foreach ($flash as $m) if (($m['type'] ?? '') === 'error') { $hasErr = true; break; }

        if (!$hasErr) {
            $table = $M['table'];
            $pk    = $M['pk'];

            // DELETE (safeguard para secciones con docs)
            if ($action === 'delete') {
                if ($id <= 0) {
                    $flash[] = ['type'=>'error','msg'=>'⚠ Falta ID para borrar.'];
                } else {
                    if ($postTab === 'sections') {
                        $stChk = $link->prepare("SELECT COUNT(*) AS c FROM fact_docs WHERE seccion=?");
                        $stChk->bind_param("i", $id);
                        $stChk->execute();
                        $rs = $stChk->get_result();
                        $cnt = ($rs && ($r=$rs->fetch_assoc())) ? (int)$r['c'] : 0;
                        $stChk->close();
                        if ($cnt > 0) {
                            $flash[] = ['type'=>'error','msg'=>'❌ No se puede borrar: hay documentos en esa sección ('.$cnt.').'];
                            $tab = $postTab;
                            $META = meta_for($tab, $opts_sections, $opts_origins);
                            goto RENDER;
                        }
                    }

                    $sql = "DELETE FROM `$table` WHERE `$pk`=?";
                    $st = $link->prepare($sql);
                    if (!$st) {
                        $flash[] = ['type'=>'error','msg'=>'❌ Error al preparar DELETE: '.$link->error];
                    } else {
                        $st->bind_param("i", $id);
                        if ($st->execute()) $flash[] = ['type'=>'ok','msg'=>'🗑 Eliminado correctamente.'];
                        else $flash[] = ['type'=>'error','msg'=>'❌ Error al borrar: '.$st->error];
                        $st->close();
                    }
                }
            }

            // CREATE
            if ($action === 'create') {
                $cols = [];
                $ph   = [];
                $types= '';
                $bind = [];

                foreach ($M['fields'] as $f) {
                    $cols[] = $f['k'];
                    $ph[]   = '?';
                    $types .= (($f['db'] ?? 's') === 'i') ? 'i' : 's';
                    $bind[] = $vals[$f['k']];
                }

                $sql = "INSERT INTO `$table` (".implode(',', array_map(fn($c)=>"`$c`",$cols)).") VALUES (".implode(',',$ph).")";
                if (!empty($M['has_timestamps'])) {
                    $sqlTry = "INSERT INTO `$table` (".implode(',', array_map(fn($c)=>"`$c`",$cols)).", `created_at`, `updated_at`) VALUES (".implode(',',$ph).", NOW(), NOW())";
                    $st = @$link->prepare($sqlTry);
                    if ($st) { $st->close(); $sql = $sqlTry; }
                }

                $st = $link->prepare($sql);
                if (!$st) {
                    $flash[] = ['type'=>'error','msg'=>'❌ Error al preparar INSERT: '.$link->error];
                } else {
                    $st->bind_param($types, ...$bind);
                    if ($st->execute()) $flash[] = ['type'=>'ok','msg'=>'✅ '.$M['title'].' creado correctamente.'];
                    else $flash[] = ['type'=>'error','msg'=>'❌ Error al crear: '.$st->error];
                    $st->close();
                }
            }

            // UPDATE
            if ($action === 'update') {
                if ($id <= 0) {
                    $flash[] = ['type'=>'error','msg'=>'⚠ Falta ID para actualizar.'];
                } else {
                    $sets = [];
                    $types= '';
                    $bind = [];

                    foreach ($M['fields'] as $f) {
                        $sets[] = "`".$f['k']."`=?";
                        $types .= (($f['db'] ?? 's') === 'i') ? 'i' : 's';
                        $bind[] = $vals[$f['k']];
                    }

                    $sql = "UPDATE `$table` SET ".implode(', ', $sets);
                    if (!empty($M['has_timestamps'])) $sql .= ", `updated_at`=NOW()";
                    $sql .= " WHERE `$pk`=?";

                    $types .= "i";
                    $bind[] = $id;

                    $st = $link->prepare($sql);
                    if (!$st) {
                        $flash[] = ['type'=>'error','msg'=>'❌ Error al preparar UPDATE: '.$link->error];
                    } else {
                        $st->bind_param($types, ...$bind);
                        if ($st->execute()) $flash[] = ['type'=>'ok','msg'=>'✏ '.$M['title'].' actualizado.'];
                        else $flash[] = ['type'=>'error','msg'=>'❌ Error al actualizar: '.$st->error];
                        $st->close();
                    }
                }
            }

            // Mantener tab actual tras POST
            $tab = $postTab;
            $META = meta_for($tab, $opts_sections, $opts_origins);
        }
    }
}

RENDER:

/* -----------------------------
   Listado + paginación
------------------------------ */
$table   = $META['table'];
$pk      = $META['pk'];
$nameCol = $META['name_col'];

$where = "WHERE 1=1";
$params = [];
$types  = "";

if ($q !== '') {
    $where .= " AND `$nameCol` LIKE ?";
    $types .= "s";
    $params[] = "%".$q."%";
}

// COUNT
$sqlCnt = "SELECT COUNT(*) AS c FROM `$table` $where";
$stC = $link->prepare($sqlCnt);
if ($types) $stC->bind_param($types, ...$params);
$stC->execute();
$rsC = $stC->get_result();
$total = ($rsC && ($rowC=$rsC->fetch_assoc())) ? (int)$rowC['c'] : 0;
$stC->close();

$pages = max(1, (int)ceil($total / $perPage));
$page  = min($page, $pages);
$offset= ($page-1)*$perPage;

// SELECT rows completos
$colsAll = array_map(fn($f)=>"`".$f['k']."`", $META['fields']);
$colsAll[] = "`$pk`";
$colsAll = array_values(array_unique($colsAll));

$sqlList = "SELECT ".implode(',', $colsAll)." FROM `$table` $where ORDER BY ".$META['order_by']." LIMIT ?, ?";
$types2 = $types."ii";
$params2 = $params;
$params2[] = $offset;
$params2[] = $perPage;

$stL = $link->prepare($sqlList);
$stL->bind_param($types2, ...$params2);
$stL->execute();
$rsL = $stL->get_result();

$rows = [];
$rowMap = [];
while ($r = $rsL->fetch_assoc()) {
    $idv = (int)$r[$pk];
    if ($tab === 'docs') {
        $r['seccion_name'] = ($opts_sections[(int)($r['seccion'] ?? 0)] ?? '');
        $r['origin_name'] = ($opts_origins[(int)($r['origin'] ?? 0)] ?? '');
    }
    $rows[] = $r;
    $rowMap[$idv] = $r;
}
$stL->close();

function ui_title(string $tab): string { return $tab==='docs' ? 'Documentos' : 'Secciones'; }
function ui_short(string $s, int $n=120): string {
    $s = trim((string)$s);
    $s = preg_replace('/\s+/u',' ', $s);
    if (mb_strlen($s) <= $n) return $s;
    return mb_substr($s,0,$n).'…';
}
?>
<style>
.panel-wrap { background:#05014E; border:1px solid #000088; border-radius:12px; padding:12px; }
.hdr { display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:10px; }
.hdr h2 { margin:0; color:#33FFFF; font-size:16px; }
.tabs { display:flex; gap:8px; flex-wrap:wrap; }
.tablnk{ display:inline-block; padding:6px 10px; border:1px solid #000088; background:#050b36; color:#cfe; border-radius:999px; text-decoration:none; font-size:12px; }
.tablnk.active{ background:#001199; color:#33FFFF; }
.btn { background:#0d3a7a; color:#fff; border:1px solid #1b4aa0; border-radius:8px; padding:6px 10px; cursor:pointer; font-size:12px; }
.btn:hover { filter:brightness(1.1); }
.btn-green { background:#0d5d37; border-color:#168f59; }
.btn-red { background:#6b1c1c; border-color:#993333; }
.inp { background:#000033; color:#fff; border:1px solid #333; padding:4px 6px; font-size:12px; border-radius:6px; }
.select { background:#000033; color:#fff; border:1px solid #333; padding:4px 6px; font-size:12px; border-radius:6px; }
.table { width:100%; border-collapse:collapse; font-size:11px; font-family:Verdana,Arial,sans-serif; }
.table th, .table td { border:1px solid #000088; padding:6px 8px; background:#05014E; white-space:nowrap; vertical-align:top; }
.table th { background:#050b36; color:#33CCCC; text-align:left; }
.table tr:hover td { background:#000066; color:#33FFFF; }
.flash { margin:6px 0; }
.flash .ok{ color:#7CFC00; } .flash .err{ color:#FF6B6B; } .flash .info{ color:#33FFFF; }
.pager{ display:flex; gap:6px; align-items:center; margin-top:10px; flex-wrap:wrap; }
.pager a, .pager span { display:inline-block; padding:4px 8px; border:1px solid #000088; background:#05014E; color:#eee; text-decoration:none; border-radius:6px; }
.pager .cur { background:#001199; }
.small{ font-size:10px; color:#9dd; }
.badge{ display:inline-block; padding:2px 8px; border:1px solid #1b4aa0; background:#00135a; color:#cfe; border-radius:999px; font-size:10px; }
.modal-back { position:fixed; inset:0; background:rgba(0,0,0,.6); display:none; align-items:center; justify-content:center; z-index:9999; padding:14px; box-sizing:border-box; }
.modal { width:min(1100px,96vw); max-height:92vh; overflow:hidden; background:#05014E; border:1px solid #000088; border-radius:12px; padding:12px; display:flex; flex-direction:column; }
.modal h3{ margin:0 0 8px; color:#33FFFF; }

.modal-body{ flex:1; overflow:auto; padding-right:6px; min-height:0; }
#formCrud{ display:flex; flex-direction:column; flex:1; min-height:0; }

/* ✅ Grid del modal: 2 columnas + campo "full width" */
.grid{
  display:grid;
  grid-template-columns:repeat(2, minmax(280px, 1fr));
  gap:10px 12px;
}
.grid .field-full{ grid-column:1 / -1; }

.grid label{ font-size:12px; color:#cfe; display:block; text-align:left; }
.grid input, .grid select, .grid textarea { width:100%; box-sizing:border-box; }
textarea.inp { min-height:140px; resize:vertical; white-space:pre-wrap; }
.ta-source{ min-height:48px; }
.modal-actions{ position:sticky; bottom:0; display:flex; gap:10px; justify-content:flex-end; padding:10px 0 0; margin-top:10px; background:linear-gradient(to top, rgba(5,1,78,1), rgba(5,1,78,0)); border-top:1px solid #000088; }

@media (max-width:750px){ .grid{ grid-template-columns:1fr; } }

/* ---- Quill (adaptado a tu tema oscuro) ---- */
.ql-toolbar.ql-snow{
  border:1px solid #000088 !important;
  background:#050b36 !important;
  border-radius:8px 8px 0 0;
}
.ql-container.ql-snow{
  border:1px solid #000088 !important;
  border-top:none !important;
  background:#000033 !important;
  color:#fff !important;
  border-radius:0 0 8px 8px;
}
.ql-editor{ min-height:260px; font-size:12px; }
.ql-snow .ql-stroke{ stroke:#cfe !important; }
.ql-snow .ql-fill{ fill:#cfe !important; }
.ql-snow .ql-picker{ color:#cfe !important; }

/* ✅ Toolbar útil: scroll dentro del editor (no dependemos del scroll del modal) */
.wys-wrap { width:100%; }
.wys-wrap .ql-toolbar.ql-snow{
  position: static !important;
  top: auto !important;
  z-index: 1;
}
.wys-wrap .ql-container.ql-snow{
  height: min(60vh, 560px);
  overflow: hidden;
}
.wys-wrap .ql-editor{
  height:100%;
  overflow-y:auto;
  padding-bottom:80px;
}
</style>

<div class="panel-wrap">
  <div class="hdr">
    <h2>🧩 CRUD — <?= h(ui_title($tab)) ?></h2>

    <div class="tabs">
      <?php
        $baseTabs = "?p=".urlencode($_GET['p'] ?? 'talim')."&s=".urlencode($_GET['s'] ?? 'admin_docs_crud');
        $baseTabs .= "&pp=".$perPage."&q=".urlencode($q);
      ?>
      <a class="tablnk <?= $tab==='docs'?'active':'' ?>" href="<?= $baseTabs ?>&tab=docs">Documentos</a>
      <a class="tablnk <?= $tab==='sections'?'active':'' ?>" href="<?= $baseTabs ?>&tab=sections">Secciones</a>
    </div>

    <button class="btn btn-green" id="btnNew">➕ Nuevo</button>

    <form method="get" style="display:flex; gap:8px; align-items:center; margin-left:auto;">
      <input type="hidden" name="p" value="<?= h($_GET['p'] ?? 'talim') ?>">
      <input type="hidden" name="s" value="<?= h($_GET['s'] ?? 'admin_docs_crud') ?>">
      <input type="hidden" name="tab" value="<?= h($tab) ?>">
      <label class="small">Búsqueda
        <input class="inp" type="text" name="q" value="<?= h($q) ?>" placeholder="<?= $tab==='docs'?'Título…':'Sección…' ?>">
      </label>
      <label class="small" style="margin-left:auto; text-align:left;">Filtro r&aacute;pido
        <input class="inp" type="text" id="quickFilterDocs" placeholder="En esta p&aacute;gina...">
      </label>
      <label class="small">Por p&aacute;g
        <select class="select" name="pp" onchange="this.form.submit()">
          <?php foreach([25,50,100,200] as $pp): ?>
            <option value="<?= $pp ?>" <?= $perPage==$pp?'selected':'' ?>><?= $pp ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <button class="btn" type="submit">Aplicar</button>
    </form>
  </div>

  <?php if (!empty($flash)): ?>
    <div class="flash">
      <?php foreach ($flash as $m):
        $cl = $m['type']==='ok'?'ok':($m['type']==='error'?'err':'info'); ?>
        <div class="<?= $cl ?>"><?= h($m['msg']) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <table class="table" id="tablaDocs">
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
              <?php elseif (str_has($k,'_name')): ?>
                <?= $val !== '' ? h($val) : '<span class="small">(—)</span>' ?>
              <?php else: ?>
                <?= h(ui_short($val, 140)) ?>
              <?php endif; ?>
            </td>
          <?php endforeach; ?>
          <td>
            <button class="btn" type="button" data-edit="<?= (int)$r[$pk] ?>">✏ Editar</button>
            <button class="btn btn-red" type="button" data-del="<?= (int)$r[$pk] ?>">🗑 Borrar</button>
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
      $base = "?p=".urlencode($_GET['p'] ?? 'talim')."&s=".urlencode($_GET['s'] ?? 'admin_docs_crud');
      $base .= "&tab=".urlencode($tab)."&pp=".$perPage."&q=".urlencode($q);
      $prev = max(1, $page-1);
      $next = min($pages, $page+1);
    ?>
    <a href="<?= $base ?>&pg=1">« Primero</a>
    <a href="<?= $base ?>&pg=<?= $prev ?>">‹ Anterior</a>
    <span class="cur">Pág <?= $page ?>/<?= $pages ?> · Total <?= (int)$total ?></span>
    <a href="<?= $base ?>&pg=<?= $next ?>">Siguiente ›</a>
    <a href="<?= $base ?>&pg=<?= $pages ?>">Último »</a>
  </div>

  <div class="small" style="margin-top:8px;">
    Nota: el campo Texto (Documentos) guarda HTML. El listado recorta para no romper la tabla.
  </div>
</div>

<!-- Modal Crear/Editar -->
<div class="modal-back" id="mb">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <h3 id="modalTitle">Nuevo</h3>

    <form method="post" id="formCrud" style="margin:0;">
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

<!-- Modal Delete -->
<div class="modal-back" id="mbDel">
  <div class="modal" style="width:min(560px,96vw);">
    <h3>Confirmar borrado</h3>
    <div style="color:#cfe; font-size:12px; line-height:1.4; margin-bottom:10px;">
      Esto eliminará el registro definitivamente.
      <?php if ($tab==='sections'): ?>
      <div class="small">Si la sección contiene documentos, el sistema lo impedirá.</div>
      <?php endif; ?>
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

<!-- Quill (CDN, sin API key, sin carpetas) -->
<link href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js"></script>

<script>
var TAB = <?= json_encode($tab, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
var META = <?= json_encode($META, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
var ROWMAP = <?= json_encode($rowMap, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;

var OPTS_SECTIONS = <?= json_encode(array_map(fn($id,$name)=>['id'=>$id,'name'=>$name], array_keys($opts_sections), array_values($opts_sections)), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
var OPTS_ORIGINS = <?= json_encode(array_map(fn($id,$name)=>['id'=>$id,'name'=>$name], array_keys($opts_origins), array_values($opts_origins)), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;

function pickOptsForField(fieldKey){
  if (TAB==='docs' && fieldKey==='seccion') return OPTS_SECTIONS;
  if (TAB==='docs' && fieldKey==='origin') return OPTS_ORIGINS;
  return [];
}

// ---- Quill helpers ----
var QUILL_MAP = {}; // textareaId -> quill instance

function destroyEditors(){
  // Quill no necesita destroy formal; basta con limpiar mapa y DOM
  QUILL_MAP = {};
}

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

    var q = new Quill(editor, {
      theme: 'snow',
      modules: {
        toolbar: toolbar,
        clipboard: { matchVisual: false }
      }
    });

    // Cargar HTML inicial desde textarea
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
    // Normaliza "vacío" de Quill
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
    if (includeZero) s.appendChild(el('option', {value:'0'}, '—'));
    else s.appendChild(el('option', {value:''}, '— Selecciona —'));

    (opts||[]).forEach(function(it){
      s.appendChild(el('option', {value:String(it.id)}, escapeHtml(it.name||('ID '+it.id))));
    });
    return s;
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
      if (k === 'source') wrap.className = 'field field-full'; // fuente suele ser larga
      var cls = k === 'source' ? 'inp ta-source' : 'inp';
      input = el('textarea', {name:k, id:'f_'+k, class:cls, rows:(k==='source'?'2':'')});
      label.appendChild(input);
      wrap.appendChild(label);
      return wrap;
    }

    if (ui === 'wysiwyg') {
      wrap.className = 'field field-full'; // ✅ Texto ocupa toda la fila

      // Hidden textarea (lo que viaja al POST) + Quill toolbar + editor
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
      wysWrap.className = 'wys-wrap'; // ✅ para aplicar estilos de scroll interno

      // Toolbar “sencilla” y estable
      var tb = el('div', {id:toolbarId, class:'ql-toolbar ql-snow'}, `
        <span class="ql-formats">
          <select class="ql-header">
            <option value="1"></option>
            <option value="2"></option>
            <option selected></option>
          </select>
          <select class="ql-size"></select>
        </span>
        <span class="ql-formats">
          <button class="ql-bold"></button>
          <button class="ql-italic"></button>
          <button class="ql-underline"></button>
          <button class="ql-strike"></button>
        </span>
        <span class="ql-formats">
          <button class="ql-blockquote"></button>
          <button class="ql-code-block"></button>
        </span>
        <span class="ql-formats">
          <button class="ql-list" value="ordered"></button>
          <button class="ql-list" value="bullet"></button>
          <button class="ql-indent" value="-1"></button>
          <button class="ql-indent" value="+1"></button>
        </span>
        <span class="ql-formats">
          <select class="ql-align"></select>
        </span>
        <span class="ql-formats">
          <button class="ql-link"></button>
          <button class="ql-clean"></button>
        </span>
      `);

      var ed = el('div', {id:editorId, class:'ql-container ql-snow'}, '');

      wysWrap.appendChild(tb);
      wysWrap.appendChild(ed);

      label.appendChild(input);
      label.appendChild(wysWrap);
      wrap.appendChild(label);
      return wrap;
    }

    if (ui === 'number') {
      input = el('input', {type:'number', name:k, id:'f_'+k, class:'inp'});
      if (f.min !== undefined) input.setAttribute('min', String(f.min));
      if (f.max !== undefined) input.setAttribute('max', String(f.max));
      label.appendChild(input);
      wrap.appendChild(label);
      return wrap;
    }

    if (ui === 'select_int') {
      input = buildSelect(k, pickOptsForField(k), true, 'f_'+k);
      label.appendChild(input);
      wrap.appendChild(label);
      return wrap;
    }

    input = el('input', {type:'text', name:k, id:'f_'+k, class:'inp'});
    if (f.max) input.setAttribute('maxlength', String(f.max));
    label.appendChild(input);
    wrap.appendChild(label);
    return wrap;
  }

  function renderForm(){
    destroyEditors();
    grid.innerHTML = '';
    (META.fields||[]).forEach(function(f){ grid.appendChild(buildField(f)); });
  }

  function openCreate(){
    document.getElementById('modalTitle').textContent = 'Nuevo — '+(META.title||'');
    document.getElementById('crud_action').value = 'create';
    document.getElementById('crud_tab').value = TAB;
    document.getElementById('f_id').value = '0';

    renderForm();

    (META.fields||[]).forEach(function(f){
      var k = f.k;
      var ui = f.ui || 'text';
      var e = document.getElementById('f_'+k);
      if (!e) return;

      if (ui === 'select_int') e.value = '0';
      else if (ui === 'number') e.value = '0';
      else e.value = '';
    });

    mb.style.display = 'flex';
    setTimeout(function(){
      initEditors();
      var first = grid.querySelector('input,textarea,select');
      if (first) first.focus();
    }, 0);
  }

  function openEdit(id){
    var row = ROWMAP[String(id)];
    if (!row) return;

    document.getElementById('modalTitle').textContent = 'Editar — '+(META.title||'');
    document.getElementById('crud_action').value = 'update';
    document.getElementById('crud_tab').value = TAB;
    document.getElementById('f_id').value = String(id);

    renderForm();

    (META.fields||[]).forEach(function(f){
      var k = f.k;
      var v = row[k];
      var e = document.getElementById('f_'+k);
      if (!e) return;

      if (f.db === 'i') e.value = String(parseInt(v||0,10)||0);
      else e.value = (v===null || v===undefined) ? '' : String(v);
    });

    mb.style.display = 'flex';
    setTimeout(function(){
      initEditors();
      var first = grid.querySelector('input,textarea,select');
      if (first) first.focus();
    }, 0);
  }

  function closeModal(){
    mb.style.display='none';
    destroyEditors();
  }

  function openDelete(id){
    delId.value = String(id||0);
    mbDel.style.display = 'flex';
  }
  function closeDelete(){
    mbDel.style.display = 'none';
    delId.value = '0';
  }

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

        var action = document.getElementById('crud_action').value;

        // 🔄 sincroniza Quill -> textarea
        syncEditorsToTextarea();

        var errs = [];
        (META.fields||[]).forEach(function(f){
            if (!f.req) return;

            var k = f.k;
            var e = document.getElementById('f_'+k);
            var v = e ? e.value : '';

            if ((f.ui||'') === 'wysiwyg') {
            var plain = String(v||'')
                .replace(/<[^>]*>/g,'')
                .replace(/\s+/g,' ')
                .trim();
            if (!plain) errs.push(f.label+' es obligatorio');
            } else {
            if (String(v).trim() === '') errs.push(f.label+' es obligatorio');
            }
        });

        if (errs.length){
            alert(errs.join("\n"));
            ev.preventDefault();
        }
    });

})();
</script>

<script>
(function(){
  var quick = document.getElementById('quickFilterDocs');
  if (!quick) return;
  quick.addEventListener('input', function(){
    var q = (this.value || '').toLowerCase();
    document.querySelectorAll('#tablaDocs tbody tr').forEach(function(tr){
      var hay = (tr.getAttribute('data-search') || tr.textContent || '').toLowerCase();
      tr.style.display = hay.indexOf(q) !== -1 ? '' : 'none';
    });
  });
})();
</script>
