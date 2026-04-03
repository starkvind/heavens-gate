<?php
/**
 * admin_powers.php — CRUD autocontenido (Dones / Rituales / Tótems / Disciplinas)
 * Requisitos:
 *   - Debe existir $link (mysqli) ya conectado.
 *   - Tablas: fact_gifts, fact_rites, dim_totems, fact_discipline_powers
 *   - (Opcional pero recomendado) Tablas de tipos y bibliografía:
 *       dim_bibliographies(id,name)
 *       dim_gift_types(id,name)
 *       dim_rite_types(id,name,determinant?)  (si no existe, igual tirará con name)
 *       dim_totem_types(id,name)
 *       dim_discipline_types(id,name)
 * FIXES (2026-01-10):
 *  - El <select> de campos select_int (incl. Origen) ahora tiene id="f_<campo>" => se carga bien al editar.
 *  - Campos que pueden ir vacíos marcados como NO obligatorios:
 *      * Dones: atributo, sistema
 *      * Tótems: rasgos, prohib
 *      * Disciplinas: atributo, sistema
 */

include_once(__DIR__ . '/../../helpers/admin_ajax.php');
if (!hg_admin_require_db($link)) { return; }
if (session_status() === PHP_SESSION_NONE) { @session_start(); }

header('Content-Type: text/html; charset=utf-8');
if ($link) { mysqli_set_charset($link, "utf8mb4"); }
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
include_once(__DIR__ . '/../../partials/admin/quill_toolbar_inner.php');
$quillToolbarInner = admin_quill_toolbar_inner();
// Subidas de imagen (Dones)
$DOCROOT = rtrim($_SERVER['DOCUMENT_ROOT'] ?? __DIR__, '/');
$DON_IMG_UPLOAD_DIR = $DOCROOT . '/public/img/gifts';
$DON_IMG_URL_BASE   = '/img/gifts';
if (!is_dir($DON_IMG_UPLOAD_DIR)) { @mkdir($DON_IMG_UPLOAD_DIR, 0775, true); }

// Subidas de imagen (Totems)
$TOTEM_IMG_UPLOAD_DIR = $DOCROOT . '/public/img/totems';
$TOTEM_IMG_URL_BASE   = '/img/totems';
if (!is_dir($TOTEM_IMG_UPLOAD_DIR)) { @mkdir($TOTEM_IMG_UPLOAD_DIR, 0775, true); }

function save_power_image(array $file, string $uploadDir, string $urlBase, string $prefix = 'gift'): array {
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) return ['ok'=>false,'msg'=>'no_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) return ['ok'=>false,'msg'=>'Error de subida (#'.$file['error'].')'];
    if ($file['size'] > 5*1024*1024) return ['ok'=>false,'msg'=>'El archivo supera 5 MB'];
    $tmp = $file['tmp_name'];
    if (!is_uploaded_file($tmp)) return ['ok'=>false,'msg'=>'Subida no v?lida'];

    $mime = '';
    if (function_exists('finfo_open')) { $fi = finfo_open(FILEINFO_MIME_TYPE); if ($fi) { $mime = finfo_file($fi, $tmp); finfo_close($fi); } }
    if (!$mime) { $gi = @getimagesize($tmp); $mime = $gi['mime'] ?? ''; }

    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
    if (!isset($allowed[$mime])) return ['ok'=>false,'msg'=>'Formato no permitido (JPG/PNG/GIF/WebP)'];

    $ext  = $allowed[$mime];
    $name = $prefix . '-' . date('YmdHis') . '-' . substr(md5(uniqid('', true)), 0, 6) . '.' . $ext;
    $dst  = rtrim($uploadDir,'/').'/'.$name;

    if (!@move_uploaded_file($tmp, $dst)) return ['ok'=>false,'msg'=>'No se pudo mover el archivo subido'];
    @chmod($dst, 0644);
    return ['ok'=>true,'url'=>rtrim($urlBase,'/').'/'.$name, 'path'=>$dst];
}
function safe_unlink_power_image(string $relUrl, string $uploadDir): void {
    if ($relUrl === '') return;
    $docroot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? __DIR__,'/');
    $rel = '/'.ltrim($relUrl,'/');
    if (strpos($rel, '/img/') === 0) {
        $abs = $docroot . '/public' . $rel;
    } else {
        $abs = $docroot . $rel;
    }
    $base = realpath($uploadDir);
    $absr = @realpath($abs);
    if ($absr && $base && strpos($absr, $base) === 0 && is_file($absr)) { @unlink($absr); }
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function str_has($hay, $needle){ return $needle !== '' && mb_stripos((string)$hay, (string)$needle) !== false; }
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

function fetchPairs(mysqli $link, string $sql): array {
    $out = [];
    $q = @$link->query($sql);
    if (!$q) return $out;
    while ($r = $q->fetch_assoc()) {
        $id = isset($r['id']) ? (int)$r['id'] : (int)($r['value'] ?? 0);
        $nm = (string)($r['name'] ?? '');
        $out[$id] = $nm;
    }
    $q->close();
    return $out;
}
function ap_has_column(mysqli $link, string $table, string $column): bool {
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    if ($table === '' || $column === '') return false;
    $rs = @$link->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
    if (!$rs) return false;
    $ok = ($rs->num_rows > 0);
    $rs->close();
    return $ok;
}

/* -----------------------------
   CSRF (simple)
------------------------------ */
$ADMIN_CSRF_SESSION_KEY = 'csrf_admin_poderes';
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
    return is_string($t) && $t !== '' && isset($_SESSION['csrf_admin_poderes']) && hash_equals($_SESSION['csrf_admin_poderes'], $t);
}

/* -----------------------------
   Config UI
------------------------------ */
$tabsAllowed = ['dones','rituales','totems','disciplinas'];
$tab = $_GET['tab'] ?? 'dones';
$tab = in_array($tab, $tabsAllowed, true) ? $tab : 'dones';

$perPage = isset($_GET['pp']) ? max(10, min(200, (int)$_GET['pp'])) : 25;
$page    = isset($_GET['pg']) ? max(1, (int)$_GET['pg']) : 1;
$q       = trim((string)($_GET['q'] ?? ''));
$offset  = ($page-1)*$perPage;

$flash = [];

/* -----------------------------
   Opciones de referencia
------------------------------ */
$opts_origen = fetchPairs($link, "SELECT id, name FROM dim_bibliographies ORDER BY name");
$opts_systems = fetchPairs($link, "SELECT id, name FROM dim_systems ORDER BY name");

$opts_tipo_dones = fetchPairs($link, "SELECT id, name FROM dim_gift_types ORDER BY id");
$opts_tipo_rit   = fetchPairs($link, "SELECT id, CONCAT(name, IFNULL(CONCAT(' ', determinant),'')) AS name FROM dim_rite_types ORDER BY id");
if (!$opts_tipo_rit) { // por si no existe determinant
    $opts_tipo_rit = fetchPairs($link, "SELECT id, name FROM dim_rite_types ORDER BY id");
}
$opts_tipo_tot   = fetchPairs($link, "SELECT id, name FROM dim_totem_types ORDER BY id");
$opts_tipo_disc  = fetchPairs($link, "SELECT id, name FROM dim_discipline_types ORDER BY id");
$giftMechanicsCol = ap_has_column($link, 'fact_gifts', 'mechanics_text') ? 'mechanics_text' : 'system_name';
$giftSystemLabelCol = ap_has_column($link, 'fact_gifts', 'shifter_system_name') ? 'shifter_system_name' : 'system_name';

/* -----------------------------
   Metadatos CRUD
------------------------------ */
function meta_for(string $tab, array $opts_origen, array $opts_systems, array $opts_tipo_dones, array $opts_tipo_rit, array $opts_tipo_tot, array $opts_tipo_disc, string $giftMechanicsCol, string $giftSystemLabelCol): array {
    if ($tab === 'dones') {
        return [
            'title' => 'Dones',
            'table' => 'fact_gifts',
            'pk'    => 'id',
            'name_col' => 'name',
            'order_by' => 'id DESC',
            'fields' => [
                ['k'=>'name',        'label'=>'Nombre',       'ui'=>'text',     'db'=>'s', 'req'=>true,  'max'=>100],
                ['k'=>'kind',        'label'=>'Tipo',         'ui'=>'select_or_text', 'db'=>'s', 'req'=>true,  'opts'=>$opts_tipo_dones, 'placeholder'=>'(ID tipo)'],
                ['k'=>'gift_group',       'label'=>'Grupo',        'ui'=>'text',     'db'=>'s', 'req'=>true,  'max'=>55],
                ['k'=>'rank',        'label'=>'Rango',        'ui'=>'text',     'db'=>'s', 'req'=>true,  'max'=>25],
                // FIX: ahora pueden ir vacíos
                ['k'=>'attribute_name',    'label'=>'Atributo',     'ui'=>'text',     'db'=>'s', 'req'=>false, 'max'=>50],
                ['k'=>'ability_name',   'label'=>'Habilidad',    'ui'=>'text',     'db'=>'s', 'req'=>false, 'max'=>50],
                ['k'=>'description', 'label'=>'Descripción',  'ui'=>'textarea', 'db'=>'s', 'req'=>true],
                // FIX: ahora puede ir vacío
                ['k'=>$giftMechanicsCol, 'label'=>'Sistema (texto)',      'ui'=>'textarea', 'db'=>'s', 'req'=>false],
                ['k'=>'system_id',   'label'=>'Sistema',     'ui'=>'select_int','db'=>'i','req'=>true, 'opts'=>$opts_systems],
                ['k'=>$giftSystemLabelCol, 'label'=>'Sistema (clasificacion)', 'ui'=>'text',     'db'=>'s', 'req'=>false,  'max'=>100],
                ['k'=>'image_url',   'label'=>'Imagen',      'ui'=>'image_upload', 'db'=>'s', 'req'=>false],
                ['k'=>'bibliography_id', 'label'=>'Origen',   'ui'=>'select_int','db'=>'i','req'=>true,  'opts'=>$opts_origen],
            ],
            'list_cols' => [
                ['k'=>'id','label'=>'ID','w'=>70],
                ['k'=>'name','label'=>'Nombre','w'=>260],
                ['k'=>'gift_group','label'=>'Grupo','w'=>140],
                ['k'=>'rank','label'=>'Rango','w'=>90],
                ['k'=>'system_label','label'=>'Sistema','w'=>140],
                ['k'=>'origen_name','label'=>'Origen','w'=>180],
            ],
        ];
    }
    if ($tab === 'rituales') {
        return [
            'title' => 'Rituales',
            'table' => 'fact_rites',
            'pk'    => 'id',
            'name_col' => 'name',
            'order_by' => 'id DESC',
            'fields' => [
                ['k'=>'name',   'label'=>'Nombre',      'ui'=>'text',     'db'=>'s', 'req'=>true, 'max'=>100],
                ['k'=>'kind',   'label'=>'Tipo',        'ui'=>'select_or_text', 'db'=>'s','req'=>true,'opts'=>$opts_tipo_rit,'placeholder'=>'(ID tipo)'],
                ['k'=>'level',  'label'=>'Nivel',       'ui'=>'number',   'db'=>'i', 'req'=>true, 'min'=>0, 'max'=>20],
                ['k'=>'race',   'label'=>'Raza',        'ui'=>'text',     'db'=>'s', 'req'=>false,'max'=>100],
                ['k'=>'description', 'label'=>'Descripción', 'ui'=>'textarea', 'db'=>'s', 'req'=>true],
                ['k'=>'system_text',   'label'=>'Sistema (texto largo)', 'ui'=>'textarea', 'db'=>'s', 'req'=>true],
                ['k'=>'system_id','label'=>'Sistema',     'ui'=>'select_int','db'=>'i', 'req'=>true, 'opts'=>$opts_systems],
                ['k'=>'system_name','label'=>'Sistema (legacy)',     'ui'=>'text',     'db'=>'s', 'req'=>false,'max'=>100],
                ['k'=>'bibliography_id', 'label'=>'Origen', 'ui'=>'select_int','db'=>'i','req'=>true,'opts'=>$opts_origen],
            ],
            'list_cols' => [
                ['k'=>'id','label'=>'ID','w'=>70],
                ['k'=>'name','label'=>'Nombre','w'=>320],
                ['k'=>'level','label'=>'Nivel','w'=>70],
                ['k'=>'system_label','label'=>'Sistema','w'=>140],
                ['k'=>'origen_name','label'=>'Origen','w'=>180],
            ],
        ];
    }
    if ($tab === 'totems') {
        return [
            'title' => 'Tótems',
            'table' => 'dim_totems',
            'pk'    => 'id',
            'name_col' => 'name',
            'order_by' => 'id DESC',
            'fields' => [
                ['k'=>'name',   'label'=>'Nombre',      'ui'=>'text',     'db'=>'s', 'req'=>true, 'max'=>100],
                ['k'=>'totem_type_id',   'label'=>'Tipo',        'ui'=>'select_int_or_text','db'=>'i','req'=>true,'opts'=>$opts_tipo_tot,'placeholder'=>'(ID tipo)'],
                ['k'=>'cost',  'label'=>'Coste',       'ui'=>'number',   'db'=>'i', 'req'=>true, 'min'=>0, 'max'=>30],
                ['k'=>'description', 'label'=>'Descripción', 'ui'=>'textarea', 'db'=>'s', 'req'=>true],
                // FIX: ahora pueden ir vacíos
                ['k'=>'traits', 'label'=>'Rasgos',      'ui'=>'textarea', 'db'=>'s', 'req'=>false],
                ['k'=>'prohibited', 'label'=>'Prohibición', 'ui'=>'textarea', 'db'=>'s', 'req'=>false],
                ['k'=>'image_url', 'label'=>'Imagen', 'ui'=>'image_upload', 'db'=>'s', 'req'=>false],
                ['k'=>'bibliography_id', 'label'=>'Origen', 'ui'=>'select_int','db'=>'i','req'=>true,'opts'=>$opts_origen],
            ],
            'list_cols' => [
                ['k'=>'id','label'=>'ID','w'=>70],
                ['k'=>'name','label'=>'Nombre','w'=>260],
                ['k'=>'cost','label'=>'Coste','w'=>70],
                ['k'=>'tipo_name','label'=>'Tipo','w'=>180],
                ['k'=>'origen_name','label'=>'Origen','w'=>180],
            ],
            'has_timestamps' => true,
        ];
    }
    // disciplinas
    return [
        'title' => 'Disciplinas',
        'table' => 'fact_discipline_powers',
        'pk'    => 'id',
        'name_col' => 'name',
        'order_by' => 'id DESC',
        'fields' => [
            ['k'=>'name',       'label'=>'Nombre',      'ui'=>'text',     'db'=>'s', 'req'=>true, 'max'=>100],
            ['k'=>'disc',       'label'=>'Disciplina',  'ui'=>'select_or_text', 'db'=>'s','req'=>true,'opts'=>$opts_tipo_disc,'placeholder'=>'(ID disciplina)'],
            ['k'=>'level',      'label'=>'Nivel',       'ui'=>'number',   'db'=>'i', 'req'=>true, 'min'=>0, 'max'=>20],
            ['k'=>'description','label'=>'Descripción', 'ui'=>'textarea', 'db'=>'s', 'req'=>true],
            // FIX: ahora puede ir vacío
            ['k'=>'system_name',    'label'=>'Sistema',     'ui'=>'textarea', 'db'=>'s', 'req'=>false],
            // FIX: ahora puede ir vacío
            ['k'=>'attribute',  'label'=>'Atributo',    'ui'=>'text',     'db'=>'s', 'req'=>false,'max'=>100],
            ['k'=>'skill',      'label'=>'Habilidad',   'ui'=>'text',     'db'=>'s', 'req'=>false,'max'=>100],
            ['k'=>'image_url',  'label'=>'Imagen', 'ui'=>'image_upload', 'db'=>'s', 'req'=>false],
            ['k'=>'bibliography_id', 'label'=>'Origen', 'ui'=>'select_int','db'=>'i','req'=>true,'opts'=>$opts_origen],
        ],
        'list_cols' => [
            ['k'=>'id','label'=>'ID','w'=>70],
            ['k'=>'name','label'=>'Nombre','w'=>320],
            ['k'=>'level','label'=>'Nivel','w'=>70],
            ['k'=>'disc_name','label'=>'Disciplina','w'=>180],
            ['k'=>'origen_name','label'=>'Origen','w'=>180],
        ],
    ];
}

$META = meta_for($tab, $opts_origen, $opts_systems, $opts_tipo_dones, $opts_tipo_rit, $opts_tipo_tot, $opts_tipo_disc, $giftMechanicsCol, $giftSystemLabelCol);
$isAjaxCrudRequest = (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['crud_action'], $_POST['crud_tab'])
    && (
        ((string)($_POST['ajax'] ?? '') === '1')
        || (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest')
    )
);

/* -----------------------------
   Guardado (POST)
------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crud_action']) && isset($_POST['crud_tab'])) {
    if ($isAjaxCrudRequest && function_exists('hg_admin_require_session')) {
        hg_admin_require_session(true);
    }
    $postTab = (string)$_POST['crud_tab'];
    if (!in_array($postTab, $tabsAllowed, true)) {
        $flash[] = ['type'=>'error','msg'=>'Pestaña inválida.'];
    } elseif (!csrf_ok()) {
        $flash[] = ['type'=>'error','msg'=>'CSRF inválido. Recarga la página.'];
    } else {
        $M = meta_for($postTab, $opts_origen, $opts_systems, $opts_tipo_dones, $opts_tipo_rit, $opts_tipo_tot, $opts_tipo_disc, $giftMechanicsCol, $giftSystemLabelCol);
        $action = (string)$_POST['crud_action'];
        $id = (int)($_POST['id'] ?? 0);
        $isDeleteAction = ($action === 'delete');

        // recoger valores (solo create/update)
        $vals = [];
        if (!$isDeleteAction) {
            foreach ($M['fields'] as $f) {
                $k = $f['k'];
                if (($f['db'] ?? 's') === 'i') {
                    $raw = $_POST[$k] ?? 0;
                    $vals[$k] = (int)$raw;
                } else {
                    $vals[$k] = (string)($_POST[$k] ?? '');
                    if (($f['ui'] ?? '') !== 'textarea') $vals[$k] = trim($vals[$k]);
                }
            }
        }

        // Subida de imagen (Dones y Totems)
        if (!$isDeleteAction && ($postTab === 'dones' || $postTab === 'totems')) {
            $currentImg = trim((string)($_POST['current_img'] ?? ''));
            $uploadDir = ($postTab === 'dones') ? $DON_IMG_UPLOAD_DIR : $TOTEM_IMG_UPLOAD_DIR;
            $urlBase   = ($postTab === 'dones') ? $DON_IMG_URL_BASE   : $TOTEM_IMG_URL_BASE;
            $prefix    = ($postTab === 'dones') ? 'gift' : 'totem';
            $upload = save_power_image($_FILES['img_file'] ?? [], $uploadDir, $urlBase, $prefix);
            if (($upload['ok'] ?? false) === true) {
                if ($currentImg !== '') safe_unlink_power_image($currentImg, $uploadDir);
                $vals['image_url'] = $upload['url'];
            } else {
                if (($vals['image_url'] ?? '') === '' && $currentImg !== '') $vals['image_url'] = $currentImg;
            }
        }

        // normalizaciones
        if (!$isDeleteAction) {
            foreach ($M['fields'] as $f) {
                $k = $f['k'];
                if (($f['db'] ?? 's') === 's') {
                    if (!isset($vals[$k]) || $vals[$k] === null) $vals[$k] = '';
                }
            }
        }

        // Relleno legacy desde system_id (si aplica)
        if (!$isDeleteAction && isset($vals['system_id']) && (int)$vals['system_id'] > 0) {
            $sysName = $opts_systems[(int)$vals['system_id']] ?? '';
            if ($sysName !== '') {
                if ($postTab === 'dones') {
                    if (array_key_exists($giftSystemLabelCol, $vals) && trim((string)$vals[$giftSystemLabelCol]) === '') {
                        $vals[$giftSystemLabelCol] = $sysName;
                    }
                } else {
                    if (array_key_exists('system_name', $vals) && trim((string)$vals['system_name']) === '') {
                        $vals['system_name'] = $sysName;
                    }
                }
            }
        }

        // validaciones m?nimas (solo create/update)
        if (!$isDeleteAction) {
            foreach ($M['fields'] as $f) {
                if (!empty($f['req'])) {
                    $k = $f['k'];
                    if (($f['db'] ?? 's') === 'i') {
                        if ((int)$vals[$k] < 0) $flash[] = ['type'=>'error','msg'=>'? '.$f['label'].' inv?lido.'];
                    } else {
                        if (trim((string)$vals[$k]) === '') $flash[] = ['type'=>'error','msg'=>'? '.$f['label'].' es obligatorio.'];
                    }
                }
            }
        }
        // bibliography_id ya viene del formulario

        $hasErr = false;
        foreach ($flash as $m) if (($m['type'] ?? '') === 'error') { $hasErr = true; break; }

        if (!$hasErr) {
            $table = $M['table'];
            $pk    = $M['pk'];

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
                    $flash[] = ['type'=>'error','msg'=>'? Error al preparar INSERT: '.$link->error];
                } else {
                    $st->bind_param($types, ...$bind);
                    if ($st->execute()) {
                        $newId = (int)$link->insert_id;
                        $src = (string)($vals[$M['name_col']] ?? '');
                        update_pretty_id($link, $table, $newId, $src);
                        $flash[] = ['type'=>'ok','msg'=>'? '.$M['title'].' creado correctamente.'];
                    } else {
                        $flash[] = ['type'=>'error','msg'=>'? Error al crear: '.$st->error];
                    }
                    $st->close();
                }
            } elseif ($action === 'update') {
                if ($id <= 0) {
                    $flash[] = ['type'=>'error','msg'=>'? Falta ID para actualizar.'];
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
                    if (!empty($M['has_timestamps'])) {
                        $sql .= ", `updated_at`=NOW()";
                    }
                    $sql .= " WHERE `$pk`=?";

                    $types .= "i";
                    $bind[] = $id;

                    $st = $link->prepare($sql);
                    if (!$st) {
                        $flash[] = ['type'=>'error','msg'=>'? Error al preparar UPDATE: '.$link->error];
                    } else {
                        $st->bind_param($types, ...$bind);
                        if ($st->execute()) {
                            $src = (string)($vals[$M['name_col']] ?? '');
                            update_pretty_id($link, $table, $id, $src);
                            $flash[] = ['type'=>'ok','msg'=>'? '.$M['title'].' actualizado.'];
                        } else {
                            $flash[] = ['type'=>'error','msg'=>'? Error al actualizar: '.$st->error];
                        }
                        $st->close();
                    }
                }
            } elseif ($action === 'delete') {
                if ($id <= 0) {
                    $flash[] = ['type'=>'error','msg'=>'? Falta ID para borrar.'];
                } else {
                    $imgToDelete = '';
                    $uploadDirForDelete = '';

                    if ($postTab === 'dones' || $postTab === 'totems') {
                        $uploadDirForDelete = ($postTab === 'dones') ? $DON_IMG_UPLOAD_DIR : $TOTEM_IMG_UPLOAD_DIR;
                        $stImg = $link->prepare("SELECT `image_url` FROM `$table` WHERE `$pk`=? LIMIT 1");
                        if ($stImg) {
                            $stImg->bind_param('i', $id);
                            if ($stImg->execute()) {
                                $rsImg = $stImg->get_result();
                                if ($rsImg && ($rwImg = $rsImg->fetch_assoc())) {
                                    $imgToDelete = (string)($rwImg['image_url'] ?? '');
                                }
                            }
                            $stImg->close();
                        }
                    }

                    $st = $link->prepare("DELETE FROM `$table` WHERE `$pk`=?");
                    if (!$st) {
                        $flash[] = ['type'=>'error','msg'=>'? Error al preparar DELETE: '.$link->error];
                    } else {
                        $st->bind_param('i', $id);
                        if ($st->execute()) {
                            if ($st->affected_rows > 0) {
                                if ($imgToDelete !== '' && $uploadDirForDelete !== '') {
                                    safe_unlink_power_image($imgToDelete, $uploadDirForDelete);
                                }
                                $flash[] = ['type'=>'ok','msg'=>'? '.$M['title'].' eliminado.'];
                            } else {
                                $flash[] = ['type'=>'error','msg'=>'? No existe el registro a borrar.'];
                            }
                        } else {
                            $flash[] = ['type'=>'error','msg'=>'? Error al borrar: '.$st->error];
                        }
                        $st->close();
                    }
                }
            } else {
                $flash[] = ['type'=>'error','msg'=>'Acción inválida.'];
            }

            // Mantener tab actual tras POST
            $tab = $postTab;
            $META = meta_for($tab, $opts_origen, $opts_systems, $opts_tipo_dones, $opts_tipo_rit, $opts_tipo_tot, $opts_tipo_disc, $giftMechanicsCol, $giftSystemLabelCol);
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
}

/* -----------------------------
   Listado + paginación
------------------------------ */
$ajaxMode = (string)($_GET['ajax_mode'] ?? ($_GET['ajax'] ?? ''));
if ($ajaxMode === 'search') {
    $tabAjax = (string)($_GET['tab'] ?? $tab);
    $tabAjax = in_array($tabAjax, $tabsAllowed, true) ? $tabAjax : 'dones';
    $qAjax   = trim((string)($_GET['q'] ?? ''));
    $MAjax   = meta_for($tabAjax, $opts_origen, $opts_systems, $opts_tipo_dones, $opts_tipo_rit, $opts_tipo_tot, $opts_tipo_disc, $giftMechanicsCol, $giftSystemLabelCol);

    $tableAjax   = $MAjax['table'];
    $pkAjax      = $MAjax['pk'];
    $nameColAjax = $MAjax['name_col'];

    $whereAjax = "WHERE 1=1";
    $paramsAjax = [];
    $typesAjax  = "";
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
        echo json_encode([
            'ok' => false,
            'tab' => $tabAjax,
            'rows' => [],
            'rowMap' => [],
            'total' => 0,
            'error' => 'Error al preparar búsqueda: '.$link->error,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
    if ($typesAjax !== '') $stAjax->bind_param($typesAjax, ...$paramsAjax);
    if (!$stAjax->execute()) {
        $stAjax->close();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'tab' => $tabAjax,
            'rows' => [],
            'rowMap' => [],
            'total' => 0,
            'error' => 'Error al ejecutar búsqueda: '.$link->error,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
    $rsAjax = $stAjax->get_result();
    if (!$rsAjax) {
        $stAjax->close();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'tab' => $tabAjax,
            'rows' => [],
            'rowMap' => [],
            'total' => 0,
            'error' => 'No se pudo leer el resultado de búsqueda: '.$link->error,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    $rowsAjax = [];
    $rowMapAjax = [];
    while ($r = $rsAjax->fetch_assoc()) {
        $idv = (int)$r[$pkAjax];

        $r['origen_name'] = ($opts_origen[(int)($r['bibliography_id'] ?? 0)] ?? '');
        if (isset($r['system_id'])) {
            $r['system_label'] = ($opts_systems[(int)($r['system_id'] ?? 0)] ?? '');
        }

        if ($tabAjax === 'dones') {
            $t = (int)($r['kind'] ?? 0);
            $r['tipo_name'] = $opts_tipo_dones[$t] ?? '';
        } elseif ($tabAjax === 'rituales') {
            $t = (int)($r['kind'] ?? 0);
            $r['tipo_name'] = $opts_tipo_rit[$t] ?? '';
        } elseif ($tabAjax === 'totems') {
            $t = (int)($r['totem_type_id'] ?? 0);
            $r['tipo_name'] = $opts_tipo_tot[$t] ?? '';
        } else {
            $t = (int)($r['disc'] ?? 0);
            $r['disc_name'] = $opts_tipo_disc[$t] ?? '';
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
$total = 0;
$sqlCnt = "SELECT COUNT(*) AS c FROM `$table` $where";
$stC = $link->prepare($sqlCnt);
if (!$stC) {
    $flash[] = ['type'=>'error','msg'=>'Error al preparar el conteo: '.$link->error];
} else {
    if ($types) $stC->bind_param($types, ...$params);
    if (!$stC->execute()) {
        $flash[] = ['type'=>'error','msg'=>'Error al ejecutar el conteo: '.$link->error];
    } else {
        $rsC = $stC->get_result();
        $total = ($rsC && ($rowC=$rsC->fetch_assoc())) ? (int)$rowC['c'] : 0;
    }
    $stC->close();
}

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
$rows = [];
$rowMap = [];
if (!$stL) {
    $flash[] = ['type'=>'error','msg'=>'Error al preparar el listado: '.$link->error];
} else {
    $stL->bind_param($types2, ...$params2);
    if (!$stL->execute()) {
        $flash[] = ['type'=>'error','msg'=>'Error al cargar el listado: '.$link->error];
    } else {
        $rsL = $stL->get_result();
        if (!$rsL) {
            $flash[] = ['type'=>'error','msg'=>'No se pudo leer el listado: '.$link->error];
        } else {
            while ($r = $rsL->fetch_assoc()) {
                $idv = (int)$r[$pk];

                $r['origen_name'] = ($opts_origen[(int)($r['bibliography_id'] ?? 0)] ?? '');
                if (isset($r['system_id'])) {
                    $r['system_label'] = ($opts_systems[(int)($r['system_id'] ?? 0)] ?? '');
                }

                if ($tab === 'dones') {
                    $t = (int)($r['kind'] ?? 0);
                    $r['tipo_name'] = $opts_tipo_dones[$t] ?? '';
                } elseif ($tab === 'rituales') {
                    $t = (int)($r['kind'] ?? 0);
                    $r['tipo_name'] = $opts_tipo_rit[$t] ?? '';
                } elseif ($tab === 'totems') {
                    $t = (int)($r['totem_type_id'] ?? 0);
                    $r['tipo_name'] = $opts_tipo_tot[$t] ?? '';
                } else {
                    $t = (int)($r['disc'] ?? 0);
                    $r['disc_name'] = $opts_tipo_disc[$t] ?? '';
                }

                $rows[] = $r;
                $rowMap[$idv] = $r;
            }
        }
    }
    $stL->close();
}

/* -----------------------------
   Helpers UI
------------------------------ */
function ui_title(string $tab): string {
    return $tab==='dones' ? 'Dones'
        : ($tab==='rituales' ? 'Rituales'
        : ($tab==='totems' ? 'Tótems' : 'Disciplinas'));
}
function ui_short(string $s, int $n=120): string {
    $s = trim((string)$s);
    $s = preg_replace('/\s+/u',' ', $s);
    if (mb_strlen($s) <= $n) return $s;
    return mb_substr($s,0,$n).'…';
}

?>
<div class="panel-wrap">
  <div class="hdr">
    <h2>&#x1F9E9; CRUD &#8212; <?= h(ui_title($tab)) ?></h2>

    <div class="tabs">
      <?php
        $baseTabs = "?p=".urlencode($_GET['p'] ?? 'talim')."&s=".urlencode($_GET['s'] ?? 'admin_powers');
        $baseTabs .= "&pp=".$perPage."&q=".urlencode($q);
      ?>
      <a class="tablnk <?= $tab==='dones'?'active':'' ?>" href="<?= $baseTabs ?>&tab=dones">Dones</a>
      <a class="tablnk <?= $tab==='rituales'?'active':'' ?>" href="<?= $baseTabs ?>&tab=rituales">Rituales</a>
      <a class="tablnk <?= $tab==='totems'?'active':'' ?>" href="<?= $baseTabs ?>&tab=totems">Tótems</a>
      <a class="tablnk <?= $tab==='disciplinas'?'active':'' ?>" href="<?= $baseTabs ?>&tab=disciplinas">Disciplinas</a>
    </div>

    <button class="btn btn-green" id="btnNew">&#x2795; Nuevo</button>

    <form method="get" id="powersFilterForm" class="adm-flex-right-8">
      <input type="hidden" name="p" value="<?= h($_GET['p'] ?? 'talim') ?>">
      <input type="hidden" name="s" value="<?= h($_GET['s'] ?? 'admin_powers') ?>">
      <input type="hidden" name="tab" value="<?= h($tab) ?>">
      <label class="small">Búsqueda
        <input class="inp" type="text" name="q" id="quickSearchPowers" value="<?= h($q) ?>" placeholder="Nombre (realtime en todo el set)…">
      </label>
      <label class="small">Por pág
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

  <table class="table" id="powersTable">
    <thead>
      <tr>
        <?php foreach ($META['list_cols'] as $c): ?>
          <th width="<?= (int)($c['w'] ?? 120) ?>"><?= h($c['label']) ?></th>
        <?php endforeach; ?>
        <th class="adm-w-120">Acciones</th>
      </tr>
    </thead>
    <tbody id="powersTbody">
      <?php foreach ($rows as $r): ?>
        <tr>
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
                <?= h(ui_short($val, 120)) ?>
              <?php endif; ?>
            </td>
          <?php endforeach; ?>
          <td>
            <button class="btn" type="button" data-edit="<?= (int)$r[$pk] ?>">&#x270F; Editar</button>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($rows)): ?>
        <tr><td colspan="<?= count($META['list_cols'])+1 ?>" class="adm-color-muted">(Sin resultados)</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <div class="pager" id="powersPager">
    <?php
      $base = "?p=".urlencode($_GET['p'] ?? 'talim')."&s=".urlencode($_GET['s'] ?? 'admin_powers');
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
    Consejo rápido: si pegáis HTML en descripciones/sistemas, aquí se guarda tal cual (el listado solo recorta para no romper la tabla).
  </div>
</div>

<!-- Modal Crear/Editar -->
<div class="modal-back" id="mb">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <h3 id="modalTitle">Nuevo</h3>

    <form method="post" id="formCrud" class="adm-m-0" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
      <input type="hidden" name="crud_tab" id="crud_tab" value="<?= h($tab) ?>">
      <input type="hidden" name="crud_action" id="crud_action" value="create">
      <input type="hidden" name="id" id="f_id" value="0">
      <input type="hidden" name="current_img" id="f_current_img" value="">

      <div class="grid" id="formGrid"></div>

      <div class="modal-actions">
        <button type="button" class="btn btn-red" id="btnCancel">Cancelar</button>
        <button type="button" class="btn btn-red" id="btnDelete" style="display:none;">Borrar</button>
        <button type="submit" class="btn btn-green" id="btnSave">Guardar</button>
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
<script>
window.ADMIN_CSRF_TOKEN = <?= json_encode($CSRF, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?= h($adminHttpJs) ?>?v=<?= (int)$adminHttpJsVer ?>"></script>

<script>
var HG_MENTION_TYPES = ['character','season','episode','organization','group','gift','rite','totem','discipline','item','trait','background','merit','flaw','merydef','doc','system','breed','auspice','tribe'];
var QUILL_TOOLBAR_INNER = <?= json_encode($quillToolbarInner, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
var TAB = <?= json_encode($tab, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE); ?>;
var META = <?= json_encode($META, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE); ?>;
var ROWMAP = <?= json_encode($rowMap, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE); ?>;

var OPTS_ORIGEN = <?= json_encode(array_map(fn($id,$name)=>['id'=>$id,'name'=>$name], array_keys($opts_origen), array_values($opts_origen)), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE); ?>;
var OPTS_SYSTEMS = <?= json_encode(array_map(fn($id,$name)=>['id'=>$id,'name'=>$name], array_keys($opts_systems), array_values($opts_systems)), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE); ?>;
var OPTS_TIPO_DONES = <?= json_encode(array_map(fn($id,$name)=>['id'=>$id,'name'=>$name], array_keys($opts_tipo_dones), array_values($opts_tipo_dones)), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE); ?>;
var OPTS_TIPO_RIT   = <?= json_encode(array_map(fn($id,$name)=>['id'=>$id,'name'=>$name], array_keys($opts_tipo_rit), array_values($opts_tipo_rit)), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE); ?>;
var OPTS_TIPO_TOT   = <?= json_encode(array_map(fn($id,$name)=>['id'=>$id,'name'=>$name], array_keys($opts_tipo_tot), array_values($opts_tipo_tot)), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE); ?>;
var OPTS_TIPO_DISC  = <?= json_encode(array_map(fn($id,$name)=>['id'=>$id,'name'=>$name], array_keys($opts_tipo_disc), array_values($opts_tipo_disc)), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE); ?>;

function pickOptsForField(fieldKey){
  if (TAB==='dones' && fieldKey==='kind') return OPTS_TIPO_DONES;
  if (TAB==='rituales' && fieldKey==='kind') return OPTS_TIPO_RIT;
  if (TAB==='totems' && fieldKey==='totem_type_id') return OPTS_TIPO_TOT;
  if (TAB==='disciplinas' && fieldKey==='disc') return OPTS_TIPO_DISC;
  if (TAB==='disciplinas' && fieldKey==='disc') return OPTS_TIPO_DISC;
  if (fieldKey==='system_id') return OPTS_SYSTEMS;
  if (fieldKey==='bibliography_id') return OPTS_ORIGEN;
  return [];
}

// ---- Quill helpers ----
var QUILL_MAP = {}; // textareaId -> quill instance

function destroyEditors(){
  QUILL_MAP = {};
}

function initEditors(){
  if (typeof Quill === 'undefined') return;
  document.querySelectorAll('[data-wys=\"1\"]').forEach(function(wrap){
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
  var btnDelete = document.getElementById('btnDelete');
  var formCrud = document.getElementById('formCrud');
  var grid = document.getElementById('formGrid');
  var currentEditRow = null;

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
    if (id) s.id = id; // FIX: IDs reales para que openEdit pueda setear valores
    if (includeZero) {
      s.appendChild(el('option', {value:'0'}, '—'));
    } else {
      s.appendChild(el('option', {value:''}, '— Selecciona —'));
    }
    (opts||[]).forEach(function(it){
      s.appendChild(el('option', {value:String(it.id)}, escapeHtml(it.name||('ID '+it.id))));
    });
    return s;
  }

  function buildField(f){
    var wrap = el('div');
    var label = el('label');
    var req = f.req ? ' <span class="badge">oblig.</span>' : '';
    label.innerHTML = '<span>'+escapeHtml(f.label)+'</span>'+req;

    var input;
    var k = f.k;
    var ui = f.ui || 'text';

    if (ui === 'textarea') {
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
      var ed = el('div', {id:editorId, class:'ql-container ql-snow'}, '');

      wysWrap.appendChild(tb);
      wysWrap.appendChild(ed);

      label.appendChild(input);
      label.appendChild(wysWrap);
      wrap.appendChild(label);
      return wrap;
    } else if (ui === 'number') {
      input = el('input', {type:'number', name:k, id:'f_'+k, class:'inp'});
      if (f.min !== undefined) input.setAttribute('min', String(f.min));
      if (f.max !== undefined) input.setAttribute('max', String(f.max));
    } else if (ui === 'select_int') {
      // FIX: id="f_<campo>" (aquí estaba el bug del Origen al editar)
      input = buildSelect(k, pickOptsForField(k), true, 'f_'+k);
    } else if (ui === 'select_or_text') {
      var box = el('div');

      var sel = buildSelect(k+'_sel', pickOptsForField(k), false, 'f_'+k+'_sel');
      sel.style.marginBottom = '6px';

      var txt = el('input', {type:'text', name:k, id:'f_'+k, class:'inp', placeholder:(f.placeholder||'')});
      sel.addEventListener('change', function(){ var v = sel.value || ''; if (v) txt.value = v; });

      box.appendChild(sel);
      box.appendChild(txt);
      label.appendChild(box);
      wrap.appendChild(label);
      return wrap;

    } else if (ui === 'image_upload') {
      var box3 = el('div');
      var txt3 = el('input', {type:'text', name:k, id:'f_'+k, class:'inp', placeholder:'img/gifts/archivo.jpg o URL'});
      txt3.style.marginBottom = '6px';
      var file3 = el('input', {type:'file', name:'img_file', id:'f_'+k+'_file', class:'inp', accept:'image/*'});
      file3.style.marginBottom = '6px';
      var prev = el('img', {id:'f_'+k+'_preview', class:'img-preview'});
      box3.appendChild(txt3);
      box3.appendChild(file3);
      box3.appendChild(prev);
      label.appendChild(box3);
      wrap.appendChild(label);
      return wrap;

    } else if (ui === 'select_int_or_text') {
      var box2 = el('div');

      var sel2 = buildSelect(k+'_sel', pickOptsForField(k), false, 'f_'+k+'_sel');
      sel2.style.marginBottom = '6px';

      var txt2 = el('input', {type:'number', name:k, id:'f_'+k, class:'inp', placeholder:(f.placeholder||'')});
      sel2.addEventListener('change', function(){ var v = sel2.value || ''; if (v) txt2.value = v; });

      box2.appendChild(sel2);
      box2.appendChild(txt2);
      label.appendChild(box2);
      wrap.appendChild(label);
      return wrap;

    } else {
      input = el('input', {type:'text', name:k, id:'f_'+k, class:'inp'});
      if (f.max) input.setAttribute('maxlength', String(f.max));
    }

    label.appendChild(input);
    wrap.appendChild(label);
    return wrap;
  }

  function renderForm(){
    destroyEditors();
    grid.innerHTML = '';
    (META.fields||[]).forEach(function(f){ grid.appendChild(buildField(f)); });
  }

  function wireImageUpload(){
    (META.fields||[]).forEach(function(f){
      if ((f.ui || '') !== 'image_upload') return;
      var k = f.k;
      var file = document.getElementById('f_'+k+'_file');
      var prev = document.getElementById('f_'+k+'_preview');
      if (!file || !prev) return;
      file.addEventListener('change', function(){
        var fl = this.files && this.files[0];
        if (!fl) return;
        var url = URL.createObjectURL(fl);
        prev.src = url;
      });
    });
  }

  function openCreate(){
    document.getElementById('modalTitle').textContent = 'Nuevo — '+(META.title||'');
    document.getElementById('crud_action').value = 'create';
    document.getElementById('f_id').value = '0';
    currentEditRow = null;
    if (btnDelete) {
      btnDelete.style.display = 'none';
      btnDelete.disabled = true;
    }

    renderForm();
    wireImageUpload();

    (META.fields||[]).forEach(function(f){
      var k = f.k;
      var ui = f.ui || 'text';

      if (ui === 'image_upload') {
        var t3 = document.getElementById('f_'+k);
        var p3 = document.getElementById('f_'+k+'_preview');
        var c3 = document.getElementById('f_current_img');
        if (t3) t3.value = '';
        if (p3) p3.src = '';
        if (c3) c3.value = '';
      } else if (ui === 'select_or_text' || ui === 'select_int_or_text') {
        var t = document.getElementById('f_'+k);
        var s = document.getElementById('f_'+k+'_sel');
        if (s) s.value = '';
        if (t) t.value = (ui==='select_int_or_text' ? '0' : '');
      } else {
        var e = document.getElementById('f_'+k);
        if (!e) return;
        if (ui === 'select_int') e.value = '0';
        else if (ui === 'number') e.value = '0';
        else e.value = '';
      }
    });

    initEditors();
    mb.style.display = 'flex';
    setTimeout(function(){
      var first = grid.querySelector('input,textarea,select');
      if (first) first.focus();
    }, 0);
  }

  function openEdit(id){
    var row = ROWMAP[String(id)];
    if (!row) return;
    currentEditRow = row;

    document.getElementById('modalTitle').textContent = 'Editar — '+(META.title||'');
    document.getElementById('crud_action').value = 'update';
    document.getElementById('f_id').value = String(id);
    if (btnDelete) {
      btnDelete.style.display = '';
      btnDelete.disabled = false;
    }

    renderForm();
    wireImageUpload();

    (META.fields||[]).forEach(function(f){
      var k = f.k;
      var ui = f.ui || 'text';
      var v = row[k];

      if (ui === 'image_upload') {
        var t4 = document.getElementById('f_'+k);
        var p4 = document.getElementById('f_'+k+'_preview');
        var c4 = document.getElementById('f_current_img');
        if (t4) t4.value = (v===null || v===undefined) ? '' : String(v);
        if (p4) p4.src = (v===null || v===undefined) ? '' : String(v);
        if (c4) c4.value = (v===null || v===undefined) ? '' : String(v);
      } else if (ui === 'select_or_text' || ui === 'select_int_or_text') {
        var t = document.getElementById('f_'+k);
        var s = document.getElementById('f_'+k+'_sel');
        if (t) t.value = (v===null || v===undefined) ? '' : String(v);
        if (s) s.value = (v===null || v===undefined) ? '' : String(v);
      } else {
        var e = document.getElementById('f_'+k);
        if (!e) return;
        if (f.db === 'i') e.value = String(parseInt(v||0,10)||0);
        else e.value = (v===null || v===undefined) ? '' : String(v);
      }
    });

    initEditors();
    mb.style.display = 'flex';
    setTimeout(function(){
      var first = grid.querySelector('input,textarea,select');
      if (first) first.focus();
    }, 0);
  }

  btnNew.addEventListener('click', openCreate);
  btnCancel.addEventListener('click', function(){ mb.style.display='none'; });
  mb.addEventListener('click', function(e){ if (e.target === mb) mb.style.display='none'; });

  function runDeleteCurrent(){
    if (!currentEditRow) {
      alert('No hay registro seleccionado para borrar.');
      return;
    }
    var id = parseInt(currentEditRow.id || document.getElementById('f_id').value || '0', 10) || 0;
    if (id <= 0) {
      alert('ID inválido para borrar.');
      return;
    }

    var powerName = String(currentEditRow.name || '').trim();
    if (powerName === '') {
      alert('No se puede verificar el nombre del poder. Recarga y prueba de nuevo.');
      return;
    }

    var typed = window.prompt(
      'Para borrar definitivamente este poder, escribe su nombre exacto:\\n\\n' + powerName,
      ''
    );
    if (typed === null) return;
    if (typed.trim() !== powerName) {
      alert('El nombre no coincide. No se ha borrado.');
      return;
    }

    var fd = new FormData();
    var csrfEl = formCrud ? formCrud.querySelector('input[name=\"csrf\"]') : null;
    fd.set('csrf', (csrfEl && csrfEl.value) ? csrfEl.value : (window.ADMIN_CSRF_TOKEN || ''));
    fd.set('crud_tab', TAB);
    fd.set('crud_action', 'delete');
    fd.set('id', String(id));
    fd.set('ajax', '1');

    requestCrudAjax(fd).then(function(json){
      if (!json || json.ok === false) {
        var msg = (json && (json.message || json.msg || json.error)) || 'Error al borrar';
        alert(msg);
        return;
      }
      mb.style.display='none';
      if (window.HGAdminHttp && typeof window.HGAdminHttp.notify === 'function') {
        window.HGAdminHttp.notify(json.message || 'Eliminado', 'ok');
      }
      if (window.runPowerSearch) window.runPowerSearch(true);
    }).catch(function(err){
      var msg = (window.HGAdminHttp && window.HGAdminHttp.errorMessage)
        ? window.HGAdminHttp.errorMessage(err)
        : (err && err.message ? err.message : 'Error al borrar');
      alert(msg);
    });
  }
  if (btnDelete) {
    btnDelete.addEventListener('click', runDeleteCurrent);
  }

  function bindEditButtons(){
    Array.prototype.forEach.call(document.querySelectorAll('#powersTbody button[data-edit]'), function(b){
      b.addEventListener('click', function(){
        var id = parseInt(b.getAttribute('data-edit')||'0',10)||0;
        openEdit(id);
      });
    });
  }
  bindEditButtons();
  window.bindPowerEditButtons = bindEditButtons;
  window.openPowerEdit = openEdit;

  function requestCrudAjax(formData){
    var postUrl = window.location.pathname + window.location.search;
    var btnSave = document.getElementById('btnSave');
    if (window.HGAdminHttp && typeof window.HGAdminHttp.request === 'function') {
      return window.HGAdminHttp.request(postUrl, {
        method: 'POST',
        body: formData,
        loadingEl: btnSave || formCrud
      });
    }
    return fetch(postUrl, { method: 'POST', credentials: 'same-origin', body: formData })
      .then(function(res){ return res.json(); });
  }

  formCrud.addEventListener('submit', function(ev){
    ev.preventDefault();
    syncEditorsToTextarea();
    var errs = [];
    (META.fields||[]).forEach(function(f){
      if (!f.req) return;
      var k = f.k;
      var ui = f.ui || 'text';
      var v = '';

      if (ui === 'select_or_text' || ui === 'select_int_or_text') {
        var t = document.getElementById('f_'+k);
        v = t ? t.value : '';
      } else {
        var e = document.getElementById('f_'+k);
        v = e ? e.value : '';
      }

      if (String(v).trim() === '') errs.push(f.label+' es obligatorio');
    });

    if (errs.length){
      alert(errs.join("\n"));
      return;
    }

    var fd = new FormData(formCrud);
    fd.set('ajax', '1');
    requestCrudAjax(fd).then(function(json){
      if (!json || json.ok === false) {
        var msg = (json && (json.message || json.msg || json.error)) || 'Error al guardar';
        alert(msg);
        return;
      }
      mb.style.display='none';
      if (window.HGAdminHttp && typeof window.HGAdminHttp.notify === 'function') {
        window.HGAdminHttp.notify(json.message || 'Guardado', 'ok');
      }
      if (window.runPowerSearch) window.runPowerSearch(true);
    }).catch(function(err){
      var msg = (window.HGAdminHttp && window.HGAdminHttp.errorMessage)
        ? window.HGAdminHttp.errorMessage(err)
        : (err && err.message ? err.message : 'Error al guardar');
      alert(msg);
    });
  });

})();
</script>

<script>
(function(){
  var input = document.getElementById('quickSearchPowers');
  var tbody = document.getElementById('powersTbody');
  var pager = document.getElementById('powersPager');
  var searchForm = document.getElementById('powersFilterForm');
  if (!input || !tbody) return;

  var initialHtml = tbody.innerHTML;
  var initialMap = ROWMAP;
  var reqSeq = 0;
  var timer = null;

  function esc(str){
    return String(str||'')
      .replace(/&/g,'&amp;')
      .replace(/</g,'&lt;')
      .replace(/>/g,'&gt;')
      .replace(/"/g,'&quot;')
      .replace(/'/g,'&#039;');
  }
  function shortText(str, n){
    var s = String(str||'').replace(/\s+/g,' ').trim();
    if (s.length <= n) return s;
    return s.slice(0, n) + '...';
  }

  function renderRows(rows, rowMap){
    ROWMAP = rowMap || {};
    if (!rows || !rows.length){
      tbody.innerHTML = '<tr><td colspan="'+(META.list_cols.length+1)+'" class="adm-color-muted">(Sin resultados)</td></tr>';
      return;
    }

    var html = '';
    rows.forEach(function(r){
      html += '<tr>';
      (META.list_cols || []).forEach(function(c){
        var k = c.k;
        var val = (r[k]===null || r[k]===undefined) ? '' : String(r[k]);
        if (k === 'id') {
          html += '<td><strong class="adm-color-accent">'+(parseInt(r.id||0,10)||0)+'</strong></td>';
        } else if (k.indexOf('_name') !== -1) {
          html += '<td>'+(val !== '' ? esc(val) : '<span class="small">(-)</span>')+'</td>';
        } else {
          html += '<td>'+esc(shortText(val, 120))+'</td>';
        }
      });
      html += '<td><button class="btn" type="button" data-edit="'+(parseInt(r.id||0,10)||0)+'">&#x270F; Editar</button></td>';
      html += '</tr>';
    });
    tbody.innerHTML = html;
    if (window.bindPowerEditButtons) window.bindPowerEditButtons();
  }

  function runSearch(forceFetch){
    var term = (input.value || '').trim();
    if (term === '' && !forceFetch){
      ROWMAP = initialMap;
      tbody.innerHTML = initialHtml;
      if (window.bindPowerEditButtons) window.bindPowerEditButtons();
      if (pager) pager.style.display = '';
      return;
    }

    if (pager) pager.style.display = 'none';
    var mySeq = ++reqSeq;
    var pVal = 'talim';
    try {
      var usp = new URLSearchParams(window.location.search || '');
      pVal = usp.get('p') || 'talim';
    } catch(e){}

    var url = '?p=' + encodeURIComponent(pVal)
      + '&s=admin_powers'
      + '&tab=' + encodeURIComponent(TAB)
      + '&ajax=1'
      + '&ajax_mode=search'
      + '&q=' + encodeURIComponent(term)
      + '&_ts=' + Date.now();

    fetch(url, {
      credentials:'same-origin',
      cache: 'no-store'
    })
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (mySeq !== reqSeq) return;
        if (!data || data.ok !== true) {
          console.warn('[admin_powers] busqueda AJAX no valida:', data);
          if (forceFetch && searchForm) {
            if (typeof searchForm.requestSubmit === 'function') searchForm.requestSubmit();
            else searchForm.submit();
          }
          return;
        }
        renderRows(data.rows || [], data.rowMap || {});
        if (term === '') {
          initialMap = ROWMAP;
          initialHtml = tbody.innerHTML;
          if (pager) pager.style.display = '';
        }
      })
      .catch(function(err){
        if (mySeq !== reqSeq) return;
        console.warn('[admin_powers] fallo en busqueda AJAX:', err);
        if (forceFetch && searchForm) {
          if (typeof searchForm.requestSubmit === 'function') searchForm.requestSubmit();
          else searchForm.submit();
        }
      });
  }

  input.addEventListener('input', function(){
    clearTimeout(timer);
    timer = setTimeout(runSearch, 180);
  });
  input.addEventListener('keydown', function(ev){
    if (ev.key === 'Enter') {
      ev.preventDefault();
      runSearch(true);
    }
  });
  if (searchForm) {
    searchForm.addEventListener('submit', function(ev){
      var active = document.activeElement;
      if (active === input) {
        ev.preventDefault();
        runSearch(true);
      }
    });
  }
  window.runPowerSearch = runSearch;
})();
</script>





