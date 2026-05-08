<?php
// admin_system_details.php -- CRUD detalles de sistemas (breeds/auspices/tribes/misc)
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
if (!hg_admin_require_db($link)) { return; }
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }

include(__DIR__ . '/../../partials/admin/admin_styles.php');
include_once(__DIR__ . '/../../partials/admin/quill_toolbar_inner.php');
include_once(__DIR__ . '/../../helpers/mentions.php');
include_once(__DIR__ . '/../../helpers/pretty.php');
include_once(__DIR__ . '/../../helpers/system_energy_resource.php');
include_once(__DIR__ . '/../../helpers/admin_ajax.php');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
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
if (!function_exists('has_column')) {
    function has_column(mysqli $link, string $table, string $column): bool {
        $table = trim($table);
        $column = trim($column);
        if ($table === '' || $column === '') return false;
        $sql = "SHOW COLUMNS FROM `".$link->real_escape_string($table)."` LIKE '".$link->real_escape_string($column)."'";
        $rs = $link->query($sql);
        if (!$rs) return false;
        $ok = $rs->num_rows > 0;
        $rs->close();
        return $ok;
    }
}

// CSRF simple + helper compartido
$ADMIN_CSRF_SESSION_KEY = 'csrf_admin_system_details';
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
if ($rs = $link->query("SELECT id, name FROM dim_systems ORDER BY sort_order ASC, name ASC")) {
    while ($r = $rs->fetch_assoc()) { $opts_systems[] = ['id'=>(int)$r['id'], 'name'=>(string)$r['name']]; }
    $rs->close();
}
$systemsById = [];
foreach ($opts_systems as $sysRow) {
    $systemsById[(int)$sysRow['id']] = (string)$sysRow['name'];
}

$energyResourcesAll = hg_ser_fetch_state_resources_all($link);
$energyResourcesBySystem = hg_ser_fetch_state_resources_by_system($link);
$energyBridgeSupport = [
    'dim_breeds' => hg_ser_has_energy_bridge_table($link, 'dim_breeds') && hg_ser_has_energy_config_column($link, 'dim_breeds'),
    'dim_auspices' => hg_ser_has_energy_bridge_table($link, 'dim_auspices') && hg_ser_has_energy_config_column($link, 'dim_auspices'),
    'dim_tribes' => hg_ser_has_energy_bridge_table($link, 'dim_tribes') && hg_ser_has_energy_config_column($link, 'dim_tribes'),
    'fact_misc_systems' => hg_ser_has_energy_bridge_table($link, 'fact_misc_systems') && hg_ser_has_energy_config_column($link, 'fact_misc_systems'),
];

$sys = isset($_GET['sys']) ? (int)$_GET['sys'] : 0;

function system_detail_meta_add_energy_bridge(array $meta, string $table, array $energyBridgeSupport): array {
    $meta['list_cols'][] = ['k' => 'energy_resources_summary', 'label' => 'Recursos', 'w' => 220];
    if (empty($energyBridgeSupport[$table])) return $meta;

    $inserted = false;
    $fields = [];
    foreach ($meta['fields'] as $field) {
        $fields[] = $field;
        if (in_array((string)($field['k'] ?? ''), ['energy', 'energy_value'], true)) {
            $fields[] = ['k' => 'energy_bridge', 'label' => 'Recursos de energía', 'ui' => 'energy_bridge', 'db' => 'x', 'req' => false];
            $inserted = true;
        }
    }
    if ($inserted) {
        $meta['fields'] = $fields;
    }

    return $meta;
}

function system_detail_meta_apply_legacy_energy(mysqli $link, array $meta, string $table): array {
    $metaMap = hg_ser_energy_bridge_meta($table);
    $legacyValueColumn = (string)($metaMap['legacy_value_column'] ?? 'energy');
    $legacyNameColumn = (string)($metaMap['legacy_name_column'] ?? '');

    if (!hg_ser_has_legacy_energy_value_column($link, $table)) {
        $meta['fields'] = array_values(array_filter($meta['fields'], function($field) use ($legacyValueColumn){
            return (string)($field['k'] ?? '') !== $legacyValueColumn;
        }));
        $meta['list_cols'] = array_values(array_filter($meta['list_cols'], function($col) use ($legacyValueColumn){
            return (string)($col['k'] ?? '') !== $legacyValueColumn;
        }));
    }
    if ($legacyNameColumn !== '' && !hg_ser_has_legacy_energy_name_column($link, $table)) {
        $meta['fields'] = array_values(array_filter($meta['fields'], function($field) use ($legacyNameColumn){
            return (string)($field['k'] ?? '') !== $legacyNameColumn;
        }));
        $meta['list_cols'] = array_values(array_filter($meta['list_cols'], function($col) use ($legacyNameColumn){
            return (string)($col['k'] ?? '') !== $legacyNameColumn;
        }));
    }

    if (hg_ser_has_legacy_energy_value_column($link, $table) || ($legacyNameColumn !== '' && hg_ser_has_legacy_energy_name_column($link, $table))) {
        return $meta;
    }

    return $meta;
}

function meta_for(mysqli $link, string $tab, array $opts_origins, array $opts_systems, array $energyBridgeSupport): array {
    if ($tab === 'breeds') {
        $meta = [
            'title' => 'Razas',
            'table' => 'dim_breeds',
            'pk' => 'id',
            'name_col' => 'name',
            'order_by' => 'system_name ASC, t.name ASC',
            'fields' => [
                ['k'=>'name', 'label'=>'Nombre', 'ui'=>'text', 'db'=>'s', 'req'=>true],
                ['k'=>'system_id', 'label'=>'Sistema', 'ui'=>'select_int', 'db'=>'i', 'req'=>true, 'opts'=>$opts_systems],
                ['k'=>'forms', 'label'=>'Formas', 'ui'=>'text', 'db'=>'s', 'req'=>false],
                ['k'=>'energy', 'label'=>'Energía', 'ui'=>'number', 'db'=>'i', 'req'=>false],
                ['k'=>'image_url', 'label'=>'Imagen', 'ui'=>'image', 'db'=>'s', 'req'=>false],
                ['k'=>'bibliography_id', 'label'=>'Origen', 'ui'=>'select_int', 'db'=>'i', 'req'=>false, 'opts'=>$opts_origins],
                ['k'=>'description', 'label'=>'Descripción', 'ui'=>'wysiwyg', 'db'=>'s', 'req'=>false],
            ],
            'list_cols' => [
                ['k'=>'id','label'=>'ID','w'=>60],
                ['k'=>'name','label'=>'Nombre','w'=>220],
                ['k'=>'system_name','label'=>'Sistema','w'=>160],
                ['k'=>'energy','label'=>'Energía','w'=>80],
            ],
            'has_timestamps' => true,
        ];
        $meta = system_detail_meta_add_energy_bridge($meta, 'dim_breeds', $energyBridgeSupport);
        return system_detail_meta_apply_legacy_energy($link, $meta, 'dim_breeds');
    }
    if ($tab === 'auspices') {
        $meta = [
            'title' => 'Auspicios',
            'table' => 'dim_auspices',
            'pk' => 'id',
            'name_col' => 'name',
            'order_by' => 'system_name ASC, t.name ASC',
            'fields' => [
                ['k'=>'name', 'label'=>'Nombre', 'ui'=>'text', 'db'=>'s', 'req'=>true],
                ['k'=>'system_id', 'label'=>'Sistema', 'ui'=>'select_int', 'db'=>'i', 'req'=>true, 'opts'=>$opts_systems],
                ['k'=>'energy', 'label'=>'Energía', 'ui'=>'number', 'db'=>'i', 'req'=>false],
                ['k'=>'image_url', 'label'=>'Imagen', 'ui'=>'image', 'db'=>'s', 'req'=>false],
                ['k'=>'bibliography_id', 'label'=>'Origen', 'ui'=>'select_int', 'db'=>'i', 'req'=>false, 'opts'=>$opts_origins],
                ['k'=>'description', 'label'=>'Descripción', 'ui'=>'wysiwyg', 'db'=>'s', 'req'=>false],
            ],
            'list_cols' => [
                ['k'=>'id','label'=>'ID','w'=>60],
                ['k'=>'name','label'=>'Nombre','w'=>220],
                ['k'=>'system_name','label'=>'Sistema','w'=>160],
                ['k'=>'energy','label'=>'Energía','w'=>80],
            ],
            'has_timestamps' => true,
        ];
        $meta = system_detail_meta_add_energy_bridge($meta, 'dim_auspices', $energyBridgeSupport);
        return system_detail_meta_apply_legacy_energy($link, $meta, 'dim_auspices');
    }
    if ($tab === 'tribes') {
        $meta = [
            'title' => 'Tribus',
            'table' => 'dim_tribes',
            'pk' => 'id',
            'name_col' => 'name',
            'order_by' => 'system_name ASC, t.name ASC',
            'fields' => [
                ['k'=>'name', 'label'=>'Nombre', 'ui'=>'text', 'db'=>'s', 'req'=>true],
                ['k'=>'system_id', 'label'=>'Sistema', 'ui'=>'select_int', 'db'=>'i', 'req'=>true, 'opts'=>$opts_systems],
                ['k'=>'affiliation', 'label'=>'Afiliación', 'ui'=>'text', 'db'=>'s', 'req'=>false],
                ['k'=>'energy', 'label'=>'Energía', 'ui'=>'number', 'db'=>'i', 'req'=>false],
                ['k'=>'image_url', 'label'=>'Imagen', 'ui'=>'image', 'db'=>'s', 'req'=>false],
                ['k'=>'bibliography_id', 'label'=>'Origen', 'ui'=>'select_int', 'db'=>'i', 'req'=>false, 'opts'=>$opts_origins],
                ['k'=>'description', 'label'=>'Descripción', 'ui'=>'wysiwyg', 'db'=>'s', 'req'=>false],
                ['k'=>'powers', 'label'=>'Poderes', 'ui'=>'textarea', 'db'=>'s', 'req'=>false],
            ],
            'list_cols' => [
                ['k'=>'id','label'=>'ID','w'=>60],
                ['k'=>'name','label'=>'Nombre','w'=>220],
                ['k'=>'system_name','label'=>'Sistema','w'=>160],
                ['k'=>'energy','label'=>'Energía','w'=>80],
            ],
            'has_timestamps' => true,
        ];
        $meta = system_detail_meta_add_energy_bridge($meta, 'dim_tribes', $energyBridgeSupport);
        return system_detail_meta_apply_legacy_energy($link, $meta, 'dim_tribes');
    }
    // misc
    $meta = [
        'title' => 'Misc Systems',
        'table' => 'fact_misc_systems',
        'pk' => 'id',
        'name_col' => 'name',
        'order_by' => 'system_name ASC, t.name ASC',
        'fields' => [
            ['k'=>'name', 'label'=>'Nombre', 'ui'=>'text', 'db'=>'s', 'req'=>true],
            ['k'=>'kind', 'label'=>'Tipo', 'ui'=>'text', 'db'=>'s', 'req'=>false],
            ['k'=>'system_id', 'label'=>'Sistema', 'ui'=>'select_int', 'db'=>'i', 'req'=>true, 'opts'=>$opts_systems],
            ['k'=>'energy_name', 'label'=>'Energía (nombre)', 'ui'=>'text', 'db'=>'s', 'req'=>false],
            ['k'=>'energy_value', 'label'=>'Energía (valor)', 'ui'=>'number', 'db'=>'i', 'req'=>false],
            ['k'=>'description', 'label'=>'Descripción', 'ui'=>'wysiwyg', 'db'=>'s', 'req'=>false],
            ['k'=>'extra_info', 'label'=>'Info extra', 'ui'=>'textarea', 'db'=>'s', 'req'=>false],
        ],
        'list_cols' => [
            ['k'=>'id','label'=>'ID','w'=>60],
            ['k'=>'name','label'=>'Nombre','w'=>220],
            ['k'=>'system_name','label'=>'Sistema','w'=>160],
            ['k'=>'kind','label'=>'Tipo','w'=>120],
        ],
        'has_timestamps' => false,
    ];
    $meta = system_detail_meta_add_energy_bridge($meta, 'fact_misc_systems', $energyBridgeSupport);
    return system_detail_meta_apply_legacy_energy($link, $meta, 'fact_misc_systems');
}

$META = meta_for($link, $tab, $opts_origins, $opts_systems, $energyBridgeSupport);
$isAjaxCrudRequest = (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['crud_action'], $_POST['crud_tab'])
    && (
        ((string)($_POST['ajax'] ?? '') === '1')
        || (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest')
    )
);

$quillToolbarInner = admin_quill_toolbar_inner();

// Guardado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crud_action']) && isset($_POST['crud_tab'])) {
    if ($isAjaxCrudRequest && function_exists('hg_admin_require_session')) {
        hg_admin_require_session(true);
    }
    $postTab = (string)$_POST['crud_tab'];
    if (!in_array($postTab, $tabsAllowed, true)) {
        $flash[] = ['type'=>'error','msg'=>'Pestaña inválida.'];
    } elseif (!csrf_ok()) {
        $flash[] = ['type'=>'error','msg'=>'CSRF inválido. Recarga.'];
    } else {
        $opts_origins = [];
        if ($rs = $link->query("SELECT id, name FROM dim_bibliographies ORDER BY name ASC")) {
            while ($r = $rs->fetch_assoc()) { $opts_origins[] = $r; }
            $rs->close();
        }
        $opts_systems = [];
        if ($rs = $link->query("SELECT id, name FROM dim_systems ORDER BY sort_order ASC, name ASC")) {
            while ($r = $rs->fetch_assoc()) { $opts_systems[] = ['id'=>(int)$r['id'], 'name'=>(string)$r['name']]; }
            $rs->close();
        }
        $systemsById = [];
        foreach ($opts_systems as $sysRow) {
            $systemsById[(int)$sysRow['id']] = (string)$sysRow['name'];
        }
        $M = meta_for($link, $postTab, $opts_origins, $opts_systems, $energyBridgeSupport);
        $action = (string)$_POST['crud_action'];
        $id = (int)($_POST['id'] ?? 0);

        $vals = [];
        $energyAssignments = [];
        foreach ($M['fields'] as $f) {
            $k = $f['k'];
            if (($f['db'] ?? 's') === 'x') continue;
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
        // bibliography_id ya viene del formulario

        foreach ($M['fields'] as $f) {
            $k = $f['k'];
            $ui = $f['ui'] ?? '';
            if (($ui === 'wysiwyg' || $ui === 'textarea') && isset($vals[$k])) {
                $vals[$k] = hg_mentions_convert($link, $vals[$k]);
            }
        }

        if (!empty($energyBridgeSupport[$M['table'] ?? ''])) {
            $energyAssignments = hg_ser_normalize_posted_energy_assignments($_POST['energy_bridge'] ?? []);
            $energySystemId = (int)($vals['system_id'] ?? 0);
            $allowAllStateResources = ((int)($_POST['allow_all_state_resources'] ?? 0) === 1);
            $energyError = hg_ser_validate_energy_assignments($energyAssignments, $energySystemId, $energyResourcesBySystem, $energyResourcesAll, $allowAllStateResources);
            if ($energyError !== null) {
                $flash[] = ['type'=>'error','msg'=>$energyError];
            }
        }

        if ($action !== 'delete') {
            foreach ($M['fields'] as $f) {
                if (!empty($f['req'])) {
                    $k = $f['k'];
                    if (($f['db'] ?? 's') === 'x') {
                        continue;
                    }
                    if (($f['db'] ?? 's') === 'i') {
                        if ((int)$vals[$k] <= 0) $flash[] = ['type'=>'error','msg'=>'Campo '.$f['label'].' obligatorio.'];
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
            $fieldKeys = array_map(fn($f)=>(string)$f['k'], array_values(array_filter($M['fields'], fn($f)=>(($f['db'] ?? 's') !== 'x'))));
            $extraWrite = [];
            if (has_column($link, $table, 'system_name') && !in_array('system_name', $fieldKeys, true)) {
                $sid = (int)($vals['system_id'] ?? 0);
                $extraWrite['system_name'] = (string)($systemsById[$sid] ?? '');
            }
            $hasImage = false;
            foreach ($M['fields'] as $f) { if (($f['k'] ?? '') === 'image_url') { $hasImage = true; break; } }

            if ($hasImage) {
                $removeFlag = isset($_POST['remove_image_url']) && (string)$_POST['remove_image_url'] === '1';
                if ($removeFlag) {
                    $vals['image_url'] = '';
                }
                if (isset($_FILES['file_image_url']) && is_array($_FILES['file_image_url']) && $_FILES['file_image_url']['error'] === UPLOAD_ERR_OK) {
                    $tmp = $_FILES['file_image_url']['tmp_name'];
                    $orig = $_FILES['file_image_url']['name'] ?? 'img';
                    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
                    if ($ext === '') $ext = 'jpg';
                    $base = slugify_pretty_id(pathinfo($orig, PATHINFO_FILENAME));
                    if ($base === '') $base = 'img';
                    $name = $base . '-' . date('YmdHis') . '.' . $ext;
                    $destDir = __DIR__ . '/../../../public/img/system';
                    if (!is_dir($destDir)) @mkdir($destDir, 0775, true);
                    $dest = $destDir . '/' . $name;
                    if (@move_uploaded_file($tmp, $dest)) {
                        $vals['image_url'] = 'img/system/' . $name;
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
                    if (($f['db'] ?? 's') === 'x') continue;
                    $isNullableSelectInt = (($f['ui'] ?? '') === 'select_int') && empty($f['req']) && (($f['db'] ?? 's') === 'i');
                    $cols[] = $f['k'];
                    $ph[] = $isNullableSelectInt ? 'NULLIF(?, 0)' : '?';
                    $types .= (($f['db'] ?? 's') === 'i') ? 'i' : 's';
                    $bind[] = $vals[$f['k']];
                }
                foreach ($extraWrite as $k => $v) {
                    $cols[] = $k;
                    $ph[] = '?';
                    $types .= 's';
                    $bind[] = (string)$v;
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
                        hg_update_pretty_id_if_exists($link, $table, $newId, $src);
                        if (!empty($energyBridgeSupport[$table])) {
                            $energySave = hg_ser_save_energy_assignments($link, $table, $newId, $energyAssignments);
                            if (empty($energySave['ok'])) {
                                $flash[] = ['type'=>'error','msg'=>(string)($energySave['message'] ?? 'No se pudieron guardar los recursos de energia.')];
                            }
                        }
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
                        if (($f['db'] ?? 's') === 'x') continue;
                        $isNullableSelectInt = (($f['ui'] ?? '') === 'select_int') && empty($f['req']) && (($f['db'] ?? 's') === 'i');
                        $sets[] = "`".$f['k']."`=" . ($isNullableSelectInt ? 'NULLIF(?, 0)' : '?');
                        $types .= (($f['db'] ?? 's') === 'i') ? 'i' : 's';
                        $bind[] = $vals[$f['k']];
                    }
                    foreach ($extraWrite as $k => $v) {
                        $sets[] = "`".$k."`=?";
                        $types .= 's';
                        $bind[] = (string)$v;
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
                            hg_update_pretty_id_if_exists($link, $table, $id, $src);
                            if (!empty($energyBridgeSupport[$table])) {
                                $energySave = hg_ser_save_energy_assignments($link, $table, $id, $energyAssignments);
                                if (empty($energySave['ok'])) {
                                    $flash[] = ['type'=>'error','msg'=>(string)($energySave['message'] ?? 'No se pudieron guardar los recursos de energia.')];
                                }
                            }
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
            $META = meta_for($link, $tab, $opts_origins, $opts_systems, $energyBridgeSupport);
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

if (($_GET['ajax'] ?? '') === 'list') {
    if (function_exists('hg_admin_require_session')) {
        hg_admin_require_session(true);
    }
    $tabAjax = (string)($_GET['tab'] ?? $tab);
    $tabAjax = in_array($tabAjax, $tabsAllowed, true) ? $tabAjax : 'breeds';
    $qAjax = trim((string)($_GET['q'] ?? ''));
    $sysAjax = isset($_GET['sys']) ? (int)$_GET['sys'] : 0;
    $MAjax = meta_for($link, $tabAjax, $opts_origins, $opts_systems, $energyBridgeSupport);

    $tableAjax = $MAjax['table'];
    $pkAjax = $MAjax['pk'];
    $nameColAjax = $MAjax['name_col'];

    $whereAjax = "WHERE 1=1";
    $paramsAjax = [];
    $typesAjax = '';
    if ($qAjax !== '') {
        $whereAjax .= " AND t.`$nameColAjax` LIKE ?";
        $typesAjax .= 's';
        $paramsAjax[] = "%".$qAjax."%";
    }
    if ($sysAjax > 0) {
        $whereAjax .= " AND t.`system_id` = ?";
        $typesAjax .= 'i';
        $paramsAjax[] = $sysAjax;
    }

    $sqlFieldsAjax = array_values(array_filter($MAjax['fields'], fn($f)=>(($f['db'] ?? 's') !== 'x')));
    $energyAjaxSql = hg_ser_energy_sql_parts($link, $tableAjax, 't', 'er_ajax');
    $fromAjax = "`$tableAjax` t LEFT JOIN dim_systems s ON s.id = t.system_id{$energyAjaxSql['join']}";
    $colsAjax = array_map(fn($f)=>"t.`".$f['k']."`", $sqlFieldsAjax);
    $colsAjax[] = "t.`$pkAjax`";
    $colsAjax[] = "s.name AS system_name";
    if ($energyAjaxSql['select'] !== '') {
        $colsAjax[] = "COALESCE(er_ajax.name, '') AS energy_resource_name";
    }
    $colsAjax = array_values(array_unique($colsAjax));
    $sqlAjax = "SELECT ".implode(',', $colsAjax)." FROM $fromAjax $whereAjax ORDER BY ".$MAjax['order_by'];

    $stAjax = $link->prepare($sqlAjax);
    if (!$stAjax) {
        if (function_exists('hg_admin_json_error')) {
            hg_admin_json_error('Error al preparar listado: '.$link->error, 500);
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok'=>false,'error'=>'Error al preparar listado: '.$link->error], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        exit;
    }
    if ($typesAjax !== '') $stAjax->bind_param($typesAjax, ...$paramsAjax);
    if (!$stAjax->execute()) {
        $errMsg = 'Error al ejecutar listado: '.$stAjax->error;
        $stAjax->close();
        if (function_exists('hg_admin_json_error')) hg_admin_json_error($errMsg, 500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok'=>false,'error'=>$errMsg], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        exit;
    }
    $rsAjax = $stAjax->get_result();
    $rowsAjax = [];
    $rowMapAjax = [];
    while ($r = $rsAjax->fetch_assoc()) {
        $rowsAjax[] = $r;
    }
    $stAjax->close();
    $rowsAjax = hg_ser_attach_energy_summary($link, $tableAjax, $rowsAjax);
    foreach ($rowsAjax as $r) {
        $idv = (int)($r[$pkAjax] ?? 0);
        $rowMapAjax[$idv] = $r;
    }

    if (function_exists('hg_admin_json_success')) {
        hg_admin_json_success([
            'rows' => $rowsAjax,
            'rowMap' => $rowMapAjax,
            'total' => count($rowsAjax),
            'tab' => $tabAjax,
        ], 'OK');
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'rows' => $rowsAjax,
        'rowMap' => $rowMapAjax,
        'total' => count($rowsAjax),
        'tab' => $tabAjax,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
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
$energyListSql = hg_ser_energy_sql_parts($link, $table, 't', 'er_list');
$from = "`$table` t LEFT JOIN dim_systems s ON s.id = t.system_id{$energyListSql['join']}";
if ($sys > 0) {
    $where .= " AND t.`system_id` = ?";
    $types .= 'i';
    $params[] = $sys;
}

$sqlCnt = "SELECT COUNT(*) AS c FROM $from $where";
$stC = $link->prepare($sqlCnt);
if ($types) $stC->bind_param($types, ...$params);
$stC->execute();
$rsC = $stC->get_result();
$total = ($rsC && ($rowC=$rsC->fetch_assoc())) ? (int)$rowC['c'] : 0;
$stC->close();

$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page-1)*$perPage;

$sqlFields = array_values(array_filter($META['fields'], fn($f)=>(($f['db'] ?? 's') !== 'x')));
$colsAll = array_map(fn($f)=>"t.`".$f['k']."`", $sqlFields);
$colsAll[] = "t.`$pk`";
$colsAll[] = "s.name AS system_name";
if ($energyListSql['select'] !== '') {
    $colsAll[] = "COALESCE(er_list.name, '') AS energy_resource_name";
}
$colsAll = array_values(array_unique($colsAll));

$sqlList = "SELECT ".implode(',', $colsAll)." FROM $from $where ORDER BY ".$META['order_by']." LIMIT ?, ?";
$types2 = $types.'ii';
$params2 = $params; $params2[] = $offset; $params2[] = $perPage;
$stL = $link->prepare($sqlList);
$stL->bind_param($types2, ...$params2);
$stL->execute();
$rsL = $stL->get_result();
$rows = []; $rowMap = [];
while ($r = $rsL->fetch_assoc()) {
    $rows[] = $r;
}
$stL->close();
$rows = hg_ser_attach_energy_summary($link, $table, $rows);
foreach ($rows as $r) {
    $idv = (int)$r[$pk];
    $rowMap[$idv] = $r;
}

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
foreach ($opts_systems as $srow) {
    $sid = (int)$srow['id']; $sname = (string)$srow['name'];
    $sel = ($sid === $sys) ? ' selected' : '';
    $sysOptions .= '<option value="'.$sid.'"'.$sel.'>'.h($sname).'</option>';
}
$actions = '<span class="adm-flex-right-8">'
    . '<label class="adm-text-left">Sistema '
    . '<select class="select" id="filterSystemDetails">'.$sysOptions.'</select></label>'
    . '<button class="btn btn-green" id="btnNew" type="button">+ Nuevo</button>'
    . '<label class="adm-text-left">Filtro rápido '
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

<div class="tabs adm-m-6-0-10">
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
          <th width="<?= (int)($c['w'] ?? 120) ?>"><?= h($c['label']) ?></th>
        <?php endforeach; ?>
        <th class="adm-w-190">Acciones</th>
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
                <strong class="adm-color-accent"><?= (int)$r[$pk] ?></strong>
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
        <tr><td colspan="<?= count($META['list_cols'])+1 ?>" class="adm-color-muted">(Sin resultados)</td></tr>
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
    <form method="post" id="formCrud" enctype="multipart/form-data" class="adm-m-0">
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
  <div class="modal adm-modal-sm">
    <h3>Confirmar borrado</h3>
    <div class="adm-help-text">
      Esto eliminara el registro definitivamente.
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

<style>
.energy-bridge-editor{display:flex;flex-direction:column;gap:8px;margin-top:6px}
.energy-bridge-grid{display:grid;grid-template-columns:minmax(240px,2fr) 110px 90px 90px;gap:8px;align-items:center}
.energy-bridge-grid-head{font-size:12px;color:#8ea7cf}
.energy-bridge-list{display:flex;flex-direction:column;gap:8px}
.energy-bridge-row{display:grid;grid-template-columns:minmax(240px,2fr) 110px 90px 90px;gap:8px;align-items:center}
.energy-bridge-actions{display:flex;justify-content:flex-start}
.energy-bridge-head{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
@media (max-width: 900px){.energy-bridge-grid,.energy-bridge-row{grid-template-columns:1fr}}
</style>

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
var TAB = <?= json_encode($tab, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
var META = <?= json_encode($META, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
var HG_MENTION_TYPES = ['character','season','episode','organization','group','gift','rite','totem','discipline','item','trait','background','merit','flaw','merydef','doc','system','breed','auspice','tribe'];
var QUILL_TOOLBAR_INNER = <?= json_encode($quillToolbarInner, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;

var ROWMAP = <?= json_encode($rowMap, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
var OPTS_ORIGINS = <?= json_encode(array_map(fn($r)=>['id'=>(int)$r['id'], 'name'=>$r['name']], $opts_origins), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
var OPTS_SYSTEMS = <?= json_encode(array_values($opts_systems), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
var ENERGY_RESOURCES_ALL = <?= json_encode(array_values($energyResourcesAll), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
var ENERGY_RESOURCES_BY_SYSTEM = <?= json_encode($energyResourcesBySystem, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;

function shouldAllowAllStateResources(){
  var chk = document.getElementById('f_allow_all_state_resources');
  return !!(chk && chk.checked);
}

function energyResourcesForSystem(systemId){
  if (shouldAllowAllStateResources()) {
    return Array.isArray(ENERGY_RESOURCES_ALL) ? ENERGY_RESOURCES_ALL : [];
  }
  var key = String(parseInt(systemId || '0', 10) || 0);
  var rows = (ENERGY_RESOURCES_BY_SYSTEM && (ENERGY_RESOURCES_BY_SYSTEM[key] || ENERGY_RESOURCES_BY_SYSTEM[parseInt(key, 10)])) || [];
  if (Array.isArray(rows) && rows.length) return rows;
  return Array.isArray(ENERGY_RESOURCES_ALL) ? ENERGY_RESOURCES_ALL : [];
}

function systemSpecificEnergyResources(systemId){
  var key = String(parseInt(systemId || '0', 10) || 0);
  return (ENERGY_RESOURCES_BY_SYSTEM && (ENERGY_RESOURCES_BY_SYSTEM[key] || ENERGY_RESOURCES_BY_SYSTEM[parseInt(key, 10)])) || [];
}

function energyResourceBelongsToSystem(resourceId, systemId){
  var rid = parseInt(resourceId || '0', 10) || 0;
  if (!rid) return true;
  var rows = systemSpecificEnergyResources(systemId);
  if (!Array.isArray(rows) || !rows.length) return true;
  return rows.some(function(row){ return (parseInt(row.id || 0, 10) || 0) === rid; });
}

function pickOptsForField(fieldKey){
  if (TAB !== 'misc' && fieldKey === 'bibliography_id') return OPTS_ORIGINS;
  if (fieldKey === 'system_id') return OPTS_SYSTEMS;
  if (fieldKey === 'energy_resource_id') return ENERGY_RESOURCES_ALL;
  return [];
}

var QUILL_MAP = {};

function resetEditors(){
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
    var q = new Quill(editor, { theme: 'snow', modules: { toolbar: toolbar, clipboard: { matchVisual: false } } });
    var html = textarea.value || '';
    q.root.innerHTML = html;
    QUILL_MAP[taId] = q;
    if (window.hgMentions && HG_MENTION_TYPES) {
      window.hgMentions.attachQuill(q, { types: HG_MENTION_TYPES });
    }
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

  function rebuildSelect(select, opts, includeZero, selectedValue, selectedLabel){
    if (!select) return;
    var target = String(selectedValue ?? '');
    select.innerHTML = '';
    if (includeZero) select.appendChild(el('option', {value:'0'}, '--'));
    else select.appendChild(el('option', {value:''}, '--'));
    (opts || []).forEach(function(it){
      select.appendChild(el('option', {value:String(it.id)}, escapeHtml(it.name || ('ID ' + it.id))));
    });
    if (selectedLabel && target !== '' && target !== '0') {
      ensureOption(select, target);
      var opt = Array.prototype.find.call(select.options, function(o){ return String(o.value) === target; });
      if (opt && (!opt.textContent || opt.textContent === target)) opt.textContent = selectedLabel;
    }
    select.value = target !== '' ? target : (includeZero ? '0' : '');
  }

  function bindEnergyResourceSelect(row){
    var systemSelect = document.getElementById('f_system_id');
    var resourceSelect = document.getElementById('f_energy_resource_id');
    if (!systemSelect || !resourceSelect) return;

    function applyOptions(preferredValue, preferredLabel){
      var currentSystemId = parseInt(systemSelect.value || '0', 10) || 0;
      var valueToKeep = preferredValue;
      if (valueToKeep === undefined || valueToKeep === null) {
        valueToKeep = resourceSelect.value || '0';
      }
      rebuildSelect(resourceSelect, energyResourcesForSystem(currentSystemId), true, String(valueToKeep || '0'), preferredLabel || '');
    }

    applyOptions(String((row && row.energy_resource_id) || resourceSelect.value || '0'), String((row && row.energy_resource_name) || ''));
    systemSelect.addEventListener('change', function(){
      applyOptions(resourceSelect.value || '0', '');
    });
  }


  function createEnergyBridgeRow(index, entry, options){
    var row = el('div', {class:'energy-bridge-row'});
    var resourceWrap = el('div', {class:'energy-bridge-col energy-bridge-col-resource'});
    var valueWrap = el('div', {class:'energy-bridge-col energy-bridge-col-value'});
    var sortWrap = el('div', {class:'energy-bridge-col energy-bridge-col-sort'});
    var actionWrap = el('div', {class:'energy-bridge-col energy-bridge-col-action'});

    var resource = buildSelect('energy_bridge[' + index + '][resource_id]', options || [], true);
    resource.className = 'select energy-bridge-resource';
    rebuildSelect(resource, options || [], true, String((entry && entry.resource_id) || 0), String((entry && entry.resource_name) || ''));

    var value = el('input', {
      type:'number',
      min:'0',
      name:'energy_bridge[' + index + '][energy_value]',
      class:'inp energy-bridge-value',
      value:String((entry && entry.energy_value) || '')
    });

    var sort = el('input', {
      type:'number',
      name:'energy_bridge[' + index + '][sort_order]',
      class:'inp energy-bridge-sort',
      value:String((entry && entry.sort_order) || 0)
    });

    var active = el('input', {
      type:'hidden',
      name:'energy_bridge[' + index + '][is_active]',
      value:String(entry && entry.is_active === 0 ? 0 : 1)
    });

    var removeBtn = el('button', {type:'button', class:'btn btn-red', 'data-energy-remove':'1'}, 'Quitar');

    resourceWrap.appendChild(resource);
    valueWrap.appendChild(value);
    sortWrap.appendChild(sort);
    actionWrap.appendChild(removeBtn);
    row.appendChild(resourceWrap);
    row.appendChild(valueWrap);
    row.appendChild(sortWrap);
    row.appendChild(actionWrap);
    row.appendChild(active);
    return row;
  }

  function bindEnergyBridgeEditor(row){
    var systemSelect = document.getElementById('f_system_id');
    var host = document.getElementById('energyBridgeEditor');
    var allowAll = document.getElementById('f_allow_all_state_resources');
    if (!host) return;
    var list = host.querySelector('.energy-bridge-list');
    var addBtn = host.querySelector('[data-energy-add]');
    if (!list || !addBtn) return;

    var seq = 0;
    function currentOptions(){
      return energyResourcesForSystem(parseInt((systemSelect && systemSelect.value) || '0', 10) || 0);
    }
    function refreshAllOptions(){
      Array.prototype.forEach.call(list.querySelectorAll('select.energy-bridge-resource'), function(select){
        var currentValue = select.value || '0';
        var currentLabel = '';
        if (select.selectedOptions && select.selectedOptions[0]) currentLabel = select.selectedOptions[0].textContent || '';
        rebuildSelect(select, currentOptions(), true, currentValue, currentLabel);
      });
    }
    function addRow(entry){
      list.appendChild(createEnergyBridgeRow(seq++, entry || {}, currentOptions()));
    }

    list.innerHTML = '';
    var initialRows = (row && Array.isArray(row.energy_resources_rows)) ? row.energy_resources_rows : [];
    if (allowAll) {
      var currentSystemId = parseInt((systemSelect && systemSelect.value) || ((row && row.system_id) || 0), 10) || 0;
      allowAll.checked = initialRows.some(function(entry){
        return !energyResourceBelongsToSystem(entry && entry.resource_id, currentSystemId);
      });
    }
    initialRows.forEach(function(entry){ addRow(entry); });

    addBtn.onclick = function(){ addRow({}); };
    host.onclick = function(ev){
      var btn = ev.target && ev.target.closest ? ev.target.closest('[data-energy-remove]') : null;
      if (!btn) return;
      ev.preventDefault();
      var energyRow = btn.closest('.energy-bridge-row');
      if (energyRow) energyRow.remove();
    };
    if (systemSelect) {
      systemSelect.onchange = (function(prev){
        return function(ev){
          if (typeof prev === 'function') prev.call(this, ev);
          refreshAllOptions();
        };
      })(systemSelect.onchange);
    }
    if (allowAll) {
      allowAll.onchange = function(){
        refreshAllOptions();
      };
    }
    refreshAllOptions();
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
      if (k === 'poderes' || k === 'extra_info') wrap.className = 'field field-full';
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

    if (ui === 'energy_bridge') {
      wrap.className = 'field field-full';
      var eb = el('div', {class:'energy-bridge-editor', id:'energyBridgeEditor'});
      eb.appendChild(el('div', {class:'energy-bridge-head'}, '<strong>Recursos</strong><span class="adm-color-muted"> Anade una o varias filas para esta raza, auspicio o tribu.</span>'));
      eb.appendChild(el('label', {class:'adm-text-left'}, '<input type="checkbox" id="f_allow_all_state_resources" name="allow_all_state_resources" value="1"> Mostrar todos los recursos de estado'));
      eb.appendChild(el('div', {class:'energy-bridge-grid energy-bridge-grid-head'}, '<div>Recurso</div><div>Valor</div><div>Orden</div><div></div>'));
      eb.appendChild(el('div', {class:'energy-bridge-list'}));
      eb.appendChild(el('div', {class:'energy-bridge-actions'}, '<button type="button" class="btn" data-energy-add="1">+ Recurso</button>'));
      label.appendChild(eb);
      wrap.appendChild(label);
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
    resetEditors();
    grid.innerHTML = '';
    document.getElementById('crud_action').value = action;
    document.getElementById('crud_tab').value = TAB;
    document.getElementById('f_id').value = id || 0;
    var activeRow = (action === 'update' && id && ROWMAP[String(id)]) ? ROWMAP[String(id)] : null;

    (META.fields||[]).forEach(function(f){
      var field = buildField(f);
      grid.appendChild(field);
    });

    if (activeRow) {
      var row = activeRow;
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
    } else if (action === 'create') {
      var systemField = document.getElementById('f_system_id');
      var currentFilter = document.getElementById('filterSystemDetails');
      if (systemField && currentFilter && currentFilter.value) {
        systemField.value = String(currentFilter.value);
      }
    }

    (META.fields||[]).forEach(function(f){
      if ((f.ui || '') === 'image') {
        var k = f.k;
        var path = '';
        if (action === 'update' && id && ROWMAP[String(id)]) {
          path = String(ROWMAP[String(id)][k] ?? '');
        }
        updateImagePreview(k, path);
      }
    });

    bindEnergyBridgeEditor(activeRow);
    initEditors();
    document.getElementById('modalTitle').textContent = (action==='create'?'Nuevo - ':'Editar - ') + (META.title||'');
    mb.style.display = 'flex';
  }

  function closeModal(){
    resetEditors();
    mb.style.display = 'none';
    grid.innerHTML='';
  }
  function openCreate(){ openModal('create', 0); }
  function openEdit(id){ openModal('update', id); }
  function openDelete(id){ delId.value = String(id||0); mbDel.style.display='flex'; }
  function closeDelete(){ mbDel.style.display='none'; delId.value='0'; }

  btnNew.addEventListener('click', openCreate);
  btnCancel.addEventListener('click', closeModal);
  mb.addEventListener('click', function(e){ if (e.target === mb) closeModal(); });

  btnDelCancel.addEventListener('click', closeDelete);
  mbDel.addEventListener('click', function(e){ if (e.target === mbDel) closeDelete(); });

  function bindRowButtons(scope){
    var root = scope || document;
    Array.prototype.forEach.call(root.querySelectorAll('button[data-edit]'), function(b){
      b.addEventListener('click', function(){
        var id = parseInt(b.getAttribute('data-edit')||'0',10)||0;
        openEdit(id);
      });
    });
    Array.prototype.forEach.call(root.querySelectorAll('button[data-del]'), function(b){
      b.addEventListener('click', function(){
        var id = parseInt(b.getAttribute('data-del')||'0',10)||0;
        openDelete(id);
      });
    });
  }
  bindRowButtons(document);
  window.bindSystemDetailsRowButtons = bindRowButtons;
  window.openSystemDetailsEdit = openEdit;
  window.openSystemDetailsDelete = openDelete;
  window.closeSystemDetailsModal = closeModal;
  window.closeSystemDetailsDelete = closeDelete;

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
  var formCrud = document.getElementById('formCrud');
  var formDel = document.getElementById('formDel');
  var tbody = document.querySelector('#tablaDetails tbody');
  var pagerCur = document.querySelector('.pager .cur');
  if (!tbody) return;

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
    return s.length <= n ? s : (s.slice(0, n) + '...');
  }
  function currentSystemFilterValue(){
    return sel ? String(sel.value || '') : '';
  }
  function syncTabLinks(){
    Array.prototype.forEach.call(document.querySelectorAll('.tabs .tablnk'), function(link){
      var href = link.getAttribute('href');
      if (!href) return;
      var url = new URL(href, window.location.origin);
      var sysValue = currentSystemFilterValue();
      if (sysValue) url.searchParams.set('sys', sysValue);
      else url.searchParams.delete('sys');
      url.searchParams.delete('_ts');
      link.setAttribute('href', url.pathname + '?' + url.searchParams.toString());
    });
  }
  function currentListUrl(){
    var url = new URL(window.location.href);
    url.searchParams.set('ajax', 'list');
    url.searchParams.set('tab', TAB);
    url.searchParams.set('_ts', Date.now());
    var v = currentSystemFilterValue();
    if (v) url.searchParams.set('sys', v);
    else url.searchParams.delete('sys');
    return url;
  }
  function currentActionUrl(){
    var url = new URL(window.location.href);
    url.searchParams.set('tab', TAB);
    url.searchParams.delete('ajax');
    url.searchParams.delete('_ts');
    var v = currentSystemFilterValue();
    if (v) url.searchParams.set('sys', v);
    else url.searchParams.delete('sys');
    return url;
  }
  function request(url, opts){
    if (window.HGAdminHttp && typeof window.HGAdminHttp.request === 'function') {
      return window.HGAdminHttp.request(url, Object.assign({ method: 'GET' }, opts || {}));
    }
    return fetch(url, Object.assign({
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }, opts || {})).then(function(r){ return r.json(); });
  }
  function renderRows(rows, rowMap){
    ROWMAP = rowMap || {};
    if (!rows || !rows.length) {
      tbody.innerHTML = '<tr><td colspan="'+(META.list_cols.length+1)+'" class="adm-color-muted">(Sin resultados)</td></tr>';
      if (pagerCur) pagerCur.textContent = 'Total 0';
      return;
    }
    var html = '';
    rows.forEach(function(r){
      var searchParts = [];
      (META.list_cols||[]).forEach(function(c){ searchParts.push(String(r[c.k] || '')); });
      var search = searchParts.join(' ').toLowerCase();
      html += '<tr data-search="'+esc(search)+'">';
      (META.list_cols || []).forEach(function(c){
        var k = c.k;
        var val = (r[k]===null || r[k]===undefined) ? '' : String(r[k]);
        if (k === 'id') {
          html += '<td><strong class="adm-color-accent">'+(parseInt(r.id||0,10)||0)+'</strong></td>';
        } else {
          html += '<td>'+esc(shortText(val, 140))+'</td>';
        }
      });
      var rid = (parseInt(r.id||0,10)||0);
      html += '<td><button class="btn" type="button" data-edit="'+rid+'">Editar</button> <button class="btn btn-red" type="button" data-del="'+rid+'">Borrar</button></td>';
      html += '</tr>';
    });
    tbody.innerHTML = html;
    if (pagerCur) pagerCur.textContent = 'Total ' + rows.length + ' (vista AJAX)';
    if (window.bindSystemDetailsRowButtons) window.bindSystemDetailsRowButtons(document);
  }
  async function reloadList(pushState){
    var listUrl = currentListUrl();
    var payload = await request(listUrl.toString(), { method: 'GET' });
    var data = payload && payload.data ? payload.data : payload;
    if (!payload || payload.ok === false || !data) {
      throw new Error((payload && (payload.message || payload.error)) || 'Error cargando listado');
    }
    renderRows(data.rows || [], data.rowMap || {});
    if (pushState) {
      listUrl.searchParams.delete('ajax');
      listUrl.searchParams.delete('_ts');
      history.pushState({ sys: sel ? (sel.value || '') : '' }, '', listUrl.pathname + '?' + listUrl.searchParams.toString());
    }
    syncTabLinks();
  }

  if (sel) {
    sel.addEventListener('change', function(){
      reloadList(true).catch(function(err){
        alert((window.HGAdminHttp && window.HGAdminHttp.errorMessage) ? window.HGAdminHttp.errorMessage(err) : (err.message || 'Error'));
      });
    });
  }

  if (formCrud) {
    formCrud.addEventListener('submit', function(ev){
      ev.preventDefault();
      var fd = new FormData(formCrud);
      fd.set('ajax', '1');
      request(currentActionUrl().toString(), {
        method: 'POST',
        body: fd,
        loadingEl: document.getElementById('btnSave') || formCrud
      }).then(function(payload){
        if (!payload || payload.ok === false) throw payload || new Error('Error al guardar');
        if (window.closeSystemDetailsModal) window.closeSystemDetailsModal();
        return reloadList(false).then(function(){
          if (window.HGAdminHttp && window.HGAdminHttp.notify) {
            window.HGAdminHttp.notify(payload.message || 'Guardado', 'ok');
          }
        });
      }).catch(function(err){
        var msg = (window.HGAdminHttp && window.HGAdminHttp.errorMessage)
          ? window.HGAdminHttp.errorMessage(err)
          : ((err && (err.message || err.error)) || 'Error al guardar');
        alert(msg);
      });
    });
  }

  if (formDel) {
    formDel.addEventListener('submit', function(ev){
      ev.preventDefault();
      var fd = new FormData(formDel);
      fd.set('ajax', '1');
      request(currentActionUrl().toString(), {
        method: 'POST',
        body: fd,
        loadingEl: formDel
      }).then(function(payload){
        if (!payload || payload.ok === false) throw payload || new Error('Error al borrar');
        if (window.closeSystemDetailsDelete) window.closeSystemDetailsDelete();
        return reloadList(false).then(function(){
          if (window.HGAdminHttp && window.HGAdminHttp.notify) {
            window.HGAdminHttp.notify(payload.message || 'Eliminado', 'ok');
          }
        });
      }).catch(function(err){
        var msg = (window.HGAdminHttp && window.HGAdminHttp.errorMessage)
          ? window.HGAdminHttp.errorMessage(err)
          : ((err && (err.message || err.error)) || 'Error al borrar');
        alert(msg);
      });
    });
  }

  syncTabLinks();
})();
</script>

<?php admin_panel_close(); ?>





