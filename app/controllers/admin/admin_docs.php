<?php
if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}
/**
 * admin_docs.php — CRUD autocontenido (Documentos + Secciones)
 * WYSIWYG SIN CKEDITOR: Quill (CDN) — sin API key, sin carpetas de plugins.
 *
 * Tablas:
 *  - dim_doc_categories: secciones/categorías (id, kind, sort_order, created_at, updated_at)
 *  - fact_docs: documentos (id, section_id, title, content, source, bibliography_id, created_at, updated_at)
 *
 * Requisitos:
 *  - Debe existir $link (mysqli) ya conectado.
 */

if (!isset($link) || !$link) { die("Sin conexión BD"); }
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
include_once(__DIR__ . '/../../partials/admin/quill_toolbar_inner.php');
include_once(__DIR__ . '/../../helpers/mentions.php');
include_once(__DIR__ . '/../../helpers/pretty.php');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function str_has($hay, $needle){ return $needle !== '' && mb_stripos((string)$hay, (string)$needle) !== false; }

function fetchPairs(mysqli $link, string $sql): array {
    $out = [];
    $q = @$link->query($sql);
    if (!$q) return $out;
    while ($r = $q->fetch_assoc()) {
        $id = isset($r['id']) ? (int)$r['id'] : (int)($r['value'] ?? 0);
        $nm = (string)($r['name'] ?? $r['kind'] ?? $r['tipo'] ?? '');
        $out[$id] = $nm;
    }
    $q->close();
    return $out;
}

/* -----------------------------
   CSRF (simple)
------------------------------ */
$ADMIN_CSRF_SESSION_KEY = 'csrf_admin_docs';
if (function_exists('hg_admin_ensure_csrf_token')) {
    $CSRF = hg_admin_ensure_csrf_token($ADMIN_CSRF_SESSION_KEY);
} else {
    if (empty($_SESSION[$ADMIN_CSRF_SESSION_KEY])) {
        $_SESSION[$ADMIN_CSRF_SESSION_KEY] = bin2hex(random_bytes(16));
    }
    $CSRF = $_SESSION[$ADMIN_CSRF_SESSION_KEY];
}
function csrf_ok(): bool {
    $payload = function_exists('hg_admin_read_json_payload') ? hg_admin_read_json_payload() : [];
    $t = function_exists('hg_admin_extract_csrf_token')
        ? hg_admin_extract_csrf_token($payload)
        : (string)($_POST['csrf'] ?? '');
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
$opts_sections = fetchPairs($link, "SELECT id, kind FROM dim_doc_categories ORDER BY sort_order ASC, kind ASC");
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
            'name_col' => 'kind',
            'order_by' => 'sort_order ASC, kind ASC',
            'fields' => [
                ['k'=>'kind',  'label'=>'Nombre', 'ui'=>'text',   'db'=>'s', 'req'=>true, 'max'=>100],
                ['k'=>'sort_order', 'label'=>'Orden',  'ui'=>'number', 'db'=>'i', 'req'=>true, 'min'=>0, 'max'=>999],
            ],
            'list_cols' => [
                ['k'=>'id','label'=>'ID','w'=>70],
                ['k'=>'kind','label'=>'Sección','w'=>320],
                ['k'=>'sort_order','label'=>'Orden','w'=>90],
            ],
            'has_timestamps' => true,
        ];
    }

    // docs
    return [
        'title' => 'Documentos',
        'table' => 'fact_docs',
        'pk'    => 'id',
        'name_col' => 'title',
        'order_by' => 'id DESC',
        'fields' => [
            ['k'=>'section_id','label'=>'Sección', 'ui'=>'select_int', 'db'=>'i','req'=>true,'opts'=>$opts_sections],
            ['k'=>'title',  'label'=>'Título',  'ui'=>'text',       'db'=>'s','req'=>true,'max'=>150],
            ['k'=>'content',  'label'=>'Texto',   'ui'=>'wysiwyg',    'db'=>'s','req'=>true],   // Quill
            ['k'=>'source', 'label'=>'Fuente',  'ui'=>'textarea',   'db'=>'s','req'=>false],
            ['k'=>'bibliography_id', 'label'=>'Origen',  'ui'=>'select_int', 'db'=>'i','req'=>true,'opts'=>$opts_origins],
        ],
        'list_cols' => [
            ['k'=>'id','label'=>'ID','w'=>70],
            ['k'=>'title','label'=>'Título','w'=>420],
            ['k'=>'seccion_name','label'=>'Sección','w'=>220],
            ['k'=>'origin_name','label'=>'Origen','w'=>160],
        ],
        'has_timestamps' => true,
    ];
}

$META = meta_for($tab, $opts_sections, $opts_origins);
$isAjaxCrudRequest = (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['crud_action'], $_POST['crud_tab'])
    && (
        ((string)($_POST['ajax'] ?? '') === '1')
        || (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest')
    )
);

$quillToolbarInner = admin_quill_toolbar_inner();

/* -----------------------------
   Guardado (POST)
------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crud_action']) && isset($_POST['crud_tab'])) {
    if ($isAjaxCrudRequest && function_exists('hg_admin_require_session')) {
        hg_admin_require_session(true);
    }
    $postTab = (string)$_POST['crud_tab'];
    if (!in_array($postTab, $tabsAllowed, true)) {
        $flash[] = ['type'=>'error','msg'=>'❌ Pestaña inválida.'];
    } elseif (!csrf_ok()) {
        $flash[] = ['type'=>'error','msg'=>'❌ CSRF inválido. Recarga la página.'];
    } else {
        // refrescar secciones por si se editaron
        $opts_sections = fetchPairs($link, "SELECT id, kind FROM dim_doc_categories ORDER BY sort_order ASC, kind ASC");
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
        // bibliography_id ya viene del formulario

        // normalizaciones
        if ($postTab === 'docs' && isset($vals['content'])) {
            $vals['content'] = hg_mentions_convert($link, $vals['content']);
        }

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
                            if ($plain === '') $flash[] = ['type'=>'error','msg'=>'âš  '.$f['label'].' es obligatorio.'];
                        } else {
                            if (trim($v) === '') $flash[] = ['type'=>'error','msg'=>'âš  '.$f['label'].' es obligatorio.'];
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
                    $flash[] = ['type'=>'error','msg'=>'âš  Falta ID para borrar.'];
                } else {
                    $canDelete = true;
                    if ($postTab === 'sections') {
                        $stChk = $link->prepare("SELECT COUNT(*) AS c FROM fact_docs WHERE section_id=?");
                        $stChk->bind_param("i", $id);
                        $stChk->execute();
                        $rs = $stChk->get_result();
                        $cnt = ($rs && ($r=$rs->fetch_assoc())) ? (int)$r['c'] : 0;
                        $stChk->close();
                        if ($cnt > 0) {
                            $canDelete = false;
                            $flash[] = ['type'=>'error','msg'=>'❌ No se puede borrar: hay documentos en esa sección ('.$cnt.').'];
                            // evita borrar secciones con documentos vinculados
                        }
                    }

                    if ($canDelete) {
                    $sql = "DELETE FROM `$table` WHERE `$pk`=?";
                    $st = $link->prepare($sql);
                    if (!$st) {
                        $flash[] = ['type'=>'error','msg'=>'âŒ Error al preparar DELETE: '.$link->error];
                    } else {
                        $st->bind_param("i", $id);
                        if ($st->execute()) $flash[] = ['type'=>'ok','msg'=>'🗑 Eliminado correctamente.'];
                        else $flash[] = ['type'=>'error','msg'=>'âŒ Error al borrar: '.$st->error];
                        $st->close();
                    }
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
                    $flash[] = ['type'=>'error','msg'=>'âŒ Error al preparar INSERT: '.$link->error];
                } else {
                    $st->bind_param($types, ...$bind);
                    if ($st->execute()) {
                        $flash[] = ['type'=>'ok','msg'=>'OK '.$M['title'].' creado correctamente.'];
                        $newId = (int)$link->insert_id;
                        $src = (string)($vals[$M['name_col']] ?? '');
                        hg_update_pretty_id_if_exists($link, $table, $newId, $src);
                    } else {
                        $flash[] = ['type'=>'error','msg'=>'Error al crear: '.$st->error];
                    }
                    $st->close();
                }
            }

            // UPDATE
            if ($action === 'update') {
                if ($id <= 0) {
                    $flash[] = ['type'=>'error','msg'=>'âš  Falta ID para actualizar.'];
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
                        $flash[] = ['type'=>'error','msg'=>'âŒ Error al preparar UPDATE: '.$link->error];
                    } else {
                        $st->bind_param($types, ...$bind);
                        if ($st->execute()) {
                            $flash[] = ['type'=>'ok','msg'=>'OK '.$M['title'].' actualizado.'];
                            $src = (string)($vals[$M['name_col']] ?? '');
                            hg_update_pretty_id_if_exists($link, $table, $id, $src);
                        } else {
                            $flash[] = ['type'=>'error','msg'=>'Error al actualizar: '.$st->error];
                        }
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

if ($isAjaxCrudRequest) {
    $errors = [];
    $messages = [];
    foreach ($flash as $m) {
        $type = (string)($m['type'] ?? '');
        $msg = (string)($m['msg'] ?? '');
        if ($msg === '') continue;
        if ($type === 'error') $errors[] = $msg;
        else $messages[] = $msg;
    }
    if (!empty($errors)) {
        if (function_exists('hg_admin_json_error')) {
            hg_admin_json_error($errors[0], 400, ['flash' => $errors], ['messages' => $messages]);
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'message' => $errors[0],
            'error' => $errors[0],
            'errors' => $errors,
            'data' => ['messages' => $messages],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    $okMsg = !empty($messages) ? $messages[count($messages)-1] : 'Guardado';
    if (function_exists('hg_admin_json_success')) {
        hg_admin_json_success(['messages' => $messages], $okMsg);
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'message' => $okMsg,
        'msg' => $okMsg,
        'data' => ['messages' => $messages],
        'errors' => [],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

$ajaxMode = (string)($_GET['ajax_mode'] ?? ($_GET['ajax'] ?? ''));
if ($ajaxMode === 'list') {
    if (function_exists('hg_admin_require_session')) {
        hg_admin_require_session(true);
    }
    $tabAjax = (string)($_GET['tab'] ?? $tab);
    $tabAjax = in_array($tabAjax, $tabsAllowed, true) ? $tabAjax : 'docs';
    $qAjax = trim((string)($_GET['q'] ?? ''));
    $MAjax = meta_for($tabAjax, $opts_sections, $opts_origins);

    $tableAjax = $MAjax['table'];
    $pkAjax = $MAjax['pk'];
    $nameColAjax = $MAjax['name_col'];

    $whereAjax = "WHERE 1=1";
    $paramsAjax = [];
    $typesAjax = '';
    if ($qAjax !== '') {
        $whereAjax .= " AND `$nameColAjax` LIKE ?";
        $typesAjax .= "s";
        $paramsAjax[] = "%".$qAjax."%";
    }

    $colsAllAjax = array_map(fn($f)=>"`".$f['k']."`", $MAjax['fields']);
    $colsAllAjax[] = "`$pkAjax`";
    $colsAllAjax = array_values(array_unique($colsAllAjax));

    $sqlAjax = "SELECT ".implode(',', $colsAllAjax)." FROM `$tableAjax` $whereAjax ORDER BY ".$MAjax['order_by'];
    $stAjax = $link->prepare($sqlAjax);
    if (!$stAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok'=>false,'message'=>'Error al preparar listado AJAX','error'=>$link->error], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
    if ($typesAjax !== '') $stAjax->bind_param($typesAjax, ...$paramsAjax);
    if (!$stAjax->execute()) {
        $stAjax->close();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok'=>false,'message'=>'Error al ejecutar listado AJAX','error'=>$link->error], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
    $rsAjax = $stAjax->get_result();
    $rowsAjax = [];
    $rowMapAjax = [];
    while ($r = $rsAjax->fetch_assoc()) {
        $idv = (int)$r[$pkAjax];
        if ($tabAjax === 'docs') {
            $r['seccion_name'] = ($opts_sections[(int)($r['section_id'] ?? 0)] ?? '');
            $r['origin_name'] = ($opts_origins[(int)($r['bibliography_id'] ?? 0)] ?? '');
        }
        $rowsAjax[] = $r;
        $rowMapAjax[$idv] = $r;
    }
    $stAjax->close();

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'tab' => $tabAjax,
        'rows' => $rowsAjax,
        'rowMap' => $rowMapAjax,
        'total' => count($rowsAjax),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

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
        $r['seccion_name'] = ($opts_sections[(int)($r['section_id'] ?? 0)] ?? '');
        $r['origin_name'] = ($opts_origins[(int)($r['bibliography_id'] ?? 0)] ?? '');
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
<div class="panel-wrap">
  <div class="hdr">
    <h2>🧩 CRUD — <?= h(ui_title($tab)) ?></h2>

    <div class="tabs">
      <?php
        $baseTabs = "?p=".urlencode($_GET['p'] ?? 'talim')."&s=".urlencode($_GET['s'] ?? 'admin_docs');
        $baseTabs .= "&pp=".$perPage."&q=".urlencode($q);
      ?>
      <a class="tablnk <?= $tab==='docs'?'active':'' ?>" href="<?= $baseTabs ?>&tab=docs">Documentos</a>
      <a class="tablnk <?= $tab==='sections'?'active':'' ?>" href="<?= $baseTabs ?>&tab=sections">Secciones</a>
    </div>

    <button class="btn btn-green" id="btnNew">➕ Nuevo</button>

    <form method="get" class="adm-flex-right-8" id="docsFilterForm">
      <input type="hidden" name="p" value="<?= h($_GET['p'] ?? 'talim') ?>">
      <input type="hidden" name="s" value="<?= h($_GET['s'] ?? 'admin_docs') ?>">
      <input type="hidden" name="tab" value="<?= h($tab) ?>">
      <label class="small">Búsqueda
        <input class="inp" type="text" id="quickSearchDocs" name="q" value="<?= h($q) ?>" placeholder="<?= $tab==='docs'?'Título…':'Sección…' ?>">
      </label>
      <label class="small adm-ml-auto-left">Filtro rápido
        <input class="inp" type="text" id="quickFilterDocs" placeholder="En esta página...">
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
          <th width="<?= (int)($c['w'] ?? 120) ?>"><?= h($c['label']) ?></th>
        <?php endforeach; ?>
        <th class="adm-w-190">Acciones</th>
      </tr>
    </thead>
    <tbody id="docsTbody">
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
                <strong class="adm-color-accent"><?= (int)$r[$pk] ?></strong>
              <?php elseif (str_has($k,'_name')): ?>
                <?= $val !== '' ? h($val) : '<span class="small">(—)</span>' ?>
              <?php else: ?>
                <?= h(ui_short($val, 140)) ?>
              <?php endif; ?>
            </td>
          <?php endforeach; ?>
          <td>
            <button class="btn" type="button" data-edit="<?= (int)$r[$pk] ?>">📝 Editar</button>
            <button class="btn btn-red" type="button" data-del="<?= (int)$r[$pk] ?>">🗑️ Borrar</button>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($rows)): ?>
        <tr><td colspan="<?= count($META['list_cols'])+1 ?>" class="adm-color-muted">(Sin resultados)</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <div class="pager">
    <?php
      $base = "?p=".urlencode($_GET['p'] ?? 'talim')."&s=".urlencode($_GET['s'] ?? 'admin_docs');
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

  <div class="small adm-mt-8">
    Nota: el campo Texto (Documentos) guarda HTML. El listado recorta para no romper la tabla.
  </div>
</div>

<!-- Modal Crear/Editar -->
<div class="modal-back" id="mb">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <h3 id="modalTitle">Nuevo</h3>

    <form method="post" id="formCrud" class="adm-m-0">
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
  <div class="modal adm-modal-sm">
    <h3>Confirmar borrado</h3>
    <div class="adm-help-text">
      Esto eliminará el registro definitivamente.
      <?php if ($tab==='sections'): ?>
      <div class="small">Si la sección contiene documentos, el sistema lo impedirá.</div>
      <?php endif; ?>
    </div>
    <form method="post" id="formDel" class="adm-m-0">
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
<link href="/assets/vendor/quill/1.3.7/quill.snow.css" rel="stylesheet">
<script src="/assets/vendor/quill/1.3.7/quill.min.js"></script>
<?php include_once(__DIR__ . '/../../partials/admin/mentions_includes.php'); ?>
<?php
$adminHttpJs = '/assets/js/admin/admin-http.js';
$adminHttpJsVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . $adminHttpJs) ?: time();
?>
<script src="<?= h($adminHttpJs) ?>?v=<?= (int)$adminHttpJsVer ?>"></script>

<script>
window.ADMIN_CSRF_TOKEN = <?= json_encode($CSRF, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
var TAB = <?= json_encode($tab, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
var META = <?= json_encode($META, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
var HG_MENTION_TYPES = ['character','season','episode','organization','group','gift','rite','totem','discipline','item','trait','background','merit','flaw','merydef','doc','system','breed','auspice','tribe'];
var QUILL_TOOLBAR_INNER = <?= json_encode($quillToolbarInner, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;

var ROWMAP = <?= json_encode($rowMap, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;

var OPTS_SECTIONS = <?= json_encode(array_map(fn($id,$name)=>['id'=>$id,'name'=>$name], array_keys($opts_sections), array_values($opts_sections)), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
var OPTS_ORIGINS = <?= json_encode(array_map(fn($id,$name)=>['id'=>$id,'name'=>$name], array_keys($opts_origins), array_values($opts_origins)), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;

function pickOptsForField(fieldKey){
  if (TAB==='docs' && fieldKey==='section_id') return OPTS_SECTIONS;
  if (TAB==='docs' && fieldKey==='bibliography_id') return OPTS_ORIGINS;
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

    if (window.hgMentions && HG_MENTION_TYPES) {
      window.hgMentions.attachQuill(q, { types: HG_MENTION_TYPES });
    }

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
  var formCrud = document.getElementById('formCrud');
  var formDel = document.getElementById('formDel');
  var tbody = document.getElementById('docsTbody') || document.querySelector('#tablaDocs tbody');
  var pagerCur = document.querySelector('.pager .cur');
  var filterForm = document.getElementById('docsFilterForm');
  var quickSearch = document.getElementById('quickSearchDocs');
  var quickFilter = document.getElementById('quickFilterDocs');
  var typingTimer = 0;

  function adminUrl(){
    var url = new URL(window.location.href);
    url.searchParams.set('s', 'admin_docs');
    url.searchParams.set('ajax', '1');
    return url;
  }

  function listUrl(){
    var url = adminUrl();
    url.searchParams.set('ajax_mode', 'list');
    url.searchParams.set('tab', TAB);
    url.searchParams.set('_ts', Date.now());
    if (quickSearch) {
      var qv = String(quickSearch.value || '').trim();
      if (qv) url.searchParams.set('q', qv);
      else url.searchParams.delete('q');
    }
    return url;
  }

  function request(url, opts){
    if (window.HGAdminHttp && typeof window.HGAdminHttp.request === 'function') {
      return window.HGAdminHttp.request(url, opts || {});
    }
    var cfg = Object.assign({
      method: 'GET',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }, opts || {});
    return fetch(url, cfg).then(function(resp){
      return resp.text().then(function(text){
        var payload = {};
        if (text) {
          try {
            payload = JSON.parse(text);
          } catch (e) {
            payload = { ok: false, message: 'Respuesta no JSON', raw: text };
          }
        }
        if (!resp.ok || (payload && payload.ok === false)) {
          var err = new Error((payload && (payload.message || payload.error || payload.msg)) || ('HTTP ' + resp.status));
          err.status = resp.status;
          err.payload = payload;
          throw err;
        }
        return payload;
      });
    });
  }

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

  function shortText(str, n){
    var s = String(str || '').replace(/\s+/g, ' ').trim();
    return s.length <= n ? s : (s.slice(0, n) + '...');
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
      wrap.className = 'field field-full'; // âœ… Texto ocupa toda la fila

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
      wysWrap.className = 'wys-wrap'; // âœ… para aplicar estilos de scroll interno

      // Toolbar “sencilla” y estable
      var tb = el('div', {id:toolbarId, class:'ql-toolbar ql-snow'}, QUILL_TOOLBAR_INNER);

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

  function bindRowButtons(root){
    Array.prototype.forEach.call((root || document).querySelectorAll('button[data-edit]'), function(b){
      b.onclick = function(){
        var id = parseInt(b.getAttribute('data-edit') || '0', 10) || 0;
        openEdit(id);
      };
    });
    Array.prototype.forEach.call((root || document).querySelectorAll('button[data-del]'), function(b){
      b.onclick = function(){
        var id = parseInt(b.getAttribute('data-del') || '0', 10) || 0;
        openDelete(id);
      };
    });
  }

  function applyLocalQuickFilter(){
    if (!tbody || !quickFilter) return;
    var q = String(quickFilter.value || '').toLowerCase();
    Array.prototype.forEach.call(tbody.querySelectorAll('tr'), function(tr){
      var hay = String(tr.getAttribute('data-search') || tr.textContent || '').toLowerCase();
      tr.style.display = hay.indexOf(q) !== -1 ? '' : 'none';
    });
  }

  function renderRows(rows, rowMap){
    if (!tbody) return;
    ROWMAP = rowMap || {};
    if (!rows || !rows.length) {
      tbody.innerHTML = '<tr><td colspan="' + (META.list_cols.length + 1) + '" class="adm-color-muted">(Sin resultados)</td></tr>';
      if (pagerCur) pagerCur.textContent = 'Total 0 (vista AJAX)';
      return;
    }

    var html = '';
    rows.forEach(function(r){
      var searchParts = [];
      (META.list_cols || []).forEach(function(c){
        searchParts.push(String(r[c.k] || ''));
      });
      var search = searchParts.join(' ').toLowerCase();
      html += '<tr data-search="' + escapeHtml(search) + '">';
      (META.list_cols || []).forEach(function(c){
        var k = c.k;
        var val = (r[k] === null || r[k] === undefined) ? '' : String(r[k]);
        if (k === 'id') {
          html += '<td><strong class="adm-color-accent">' + (parseInt(r.id || 0, 10) || 0) + '</strong></td>';
          return;
        }
        if (/_name$/.test(k)) {
          html += '<td>' + (val ? escapeHtml(val) : '<span class="small">(—)</span>') + '</td>';
          return;
        }
        html += '<td>' + escapeHtml(shortText(val, 140)) + '</td>';
      });
      var rid = (parseInt(r.id || 0, 10) || 0);
      html += '<td><button class="btn" type="button" data-edit="' + rid + '">Editar</button> ';
      html += '<button class="btn btn-red" type="button" data-del="' + rid + '">Borrar</button></td>';
      html += '</tr>';
    });

    tbody.innerHTML = html;
    bindRowButtons(tbody);
    applyLocalQuickFilter();
    if (pagerCur) pagerCur.textContent = 'Total ' + rows.length + ' (vista AJAX)';
  }

  function currentValidationErrors(){
    var errs = [];
    syncEditorsToTextarea();
    (META.fields || []).forEach(function(f){
      if (!f.req) return;
      var k = f.k;
      var e = document.getElementById('f_' + k);
      var v = e ? e.value : '';
      if ((f.ui || '') === 'wysiwyg') {
        var plain = String(v || '').replace(/<[^>]*>/g, '').replace(/\s+/g, ' ').trim();
        if (!plain) errs.push(f.label + ' es obligatorio');
        return;
      }
      if (String(v).trim() === '') errs.push(f.label + ' es obligatorio');
    });
    return errs;
  }

  async function reloadList(updateUrl, replaceHistory){
    var url = listUrl();
    var payload = await request(url.toString(), { method: 'GET' });
    var data = payload && payload.data ? payload.data : payload;
    if (!payload || payload.ok === false || !data) {
      throw new Error((payload && (payload.message || payload.error || payload.msg)) || 'Error al cargar listado');
    }
    renderRows(data.rows || [], data.rowMap || {});
    if (updateUrl) {
      var targetUrl = new URL(window.location.href);
      targetUrl.searchParams.set('s', 'admin_docs');
      targetUrl.searchParams.set('tab', TAB);
      if (quickSearch) {
        var qv = String(quickSearch.value || '').trim();
        if (qv) targetUrl.searchParams.set('q', qv);
        else targetUrl.searchParams.delete('q');
      }
      targetUrl.searchParams.delete('ajax');
      targetUrl.searchParams.delete('ajax_mode');
      targetUrl.searchParams.delete('_ts');
      if (replaceHistory) history.replaceState({ q: quickSearch ? quickSearch.value : '' }, '', targetUrl.pathname + '?' + targetUrl.searchParams.toString());
      else history.pushState({ q: quickSearch ? quickSearch.value : '' }, '', targetUrl.pathname + '?' + targetUrl.searchParams.toString());
    }
  }

  function handleError(err, fallback){
    var msg = fallback || 'Error';
    if (window.HGAdminHttp && typeof window.HGAdminHttp.errorMessage === 'function') {
      msg = window.HGAdminHttp.errorMessage(err);
    } else if (err && (err.message || err.error)) {
      msg = err.message || err.error;
    }
    alert(msg);
  }

  btnNew.addEventListener('click', openCreate);
  btnCancel.addEventListener('click', closeModal);
  mb.addEventListener('click', function(e){ if (e.target === mb) closeModal(); });
  btnDelCancel.addEventListener('click', closeDelete);
  mbDel.addEventListener('click', function(e){ if (e.target === mbDel) closeDelete(); });
  bindRowButtons(document);

  if (quickFilter) {
    quickFilter.addEventListener('input', applyLocalQuickFilter);
  }

  if (filterForm) {
    filterForm.addEventListener('submit', function(ev){
      ev.preventDefault();
      reloadList(true, false).catch(function(err){ handleError(err, 'Error aplicando filtro'); });
    });
  }

  if (quickSearch) {
    quickSearch.addEventListener('input', function(){
      clearTimeout(typingTimer);
      typingTimer = setTimeout(function(){
        reloadList(true, true).catch(function(err){ handleError(err, 'Error en búsqueda'); });
      }, 220);
    });
  }

  if (formCrud) {
    formCrud.addEventListener('submit', function(ev){
      ev.preventDefault();
      var errs = currentValidationErrors();
      if (errs.length) {
        alert(errs.join('\n'));
        return;
      }

      var fd = new FormData(formCrud);
      fd.set('ajax', '1');
      request(adminUrl().toString(), {
        method: 'POST',
        body: fd,
        loadingEl: document.getElementById('btnSave') || formCrud
      }).then(function(payload){
        closeModal();
        if (window.HGAdminHttp && typeof window.HGAdminHttp.notify === 'function') {
          window.HGAdminHttp.notify((payload && (payload.message || payload.msg)) || 'Guardado', 'ok');
        }
        return reloadList(false, true);
      }).catch(function(err){
        handleError(err, 'Error al guardar');
      });
    });
  }

  if (formDel) {
    formDel.addEventListener('submit', function(ev){
      ev.preventDefault();
      var fd = new FormData(formDel);
      fd.set('ajax', '1');
      request(adminUrl().toString(), {
        method: 'POST',
        body: fd,
        loadingEl: formDel
      }).then(function(payload){
        closeDelete();
        if (window.HGAdminHttp && typeof window.HGAdminHttp.notify === 'function') {
          window.HGAdminHttp.notify((payload && (payload.message || payload.msg)) || 'Eliminado', 'ok');
        }
        return reloadList(false, true);
      }).catch(function(err){
        handleError(err, 'Error al borrar');
      });
    });
  }

  window.addEventListener('popstate', function(){
    var url = new URL(window.location.href);
    if (quickSearch) quickSearch.value = url.searchParams.get('q') || '';
    reloadList(false, true).catch(function(){});
  });

})();
</script>














