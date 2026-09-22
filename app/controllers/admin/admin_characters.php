<?php
$isAjaxRequest = (
    (isset($_GET['ajax']) && (string)$_GET['ajax'] === '1')
    || (
        $_SERVER['REQUEST_METHOD'] === 'POST'
        && (
            ((string)($_POST['ajax'] ?? '') === '1')
            || (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest')
        )
    )
);
if (!$isAjaxRequest):
?>
<link rel="stylesheet" href="/assets/vendor/select2/select2.min.4.1.0.css">
<script src="/assets/vendor/jquery/jquery-3.7.1.min.js"></script>
<script src="/assets/vendor/select2/select2.min.4.1.0.js"></script>
<style>
/* Override local: evita texto blanco sobre fondo blanco en Select2 */
#mb{
  --adm-s2-bg: #000033;
  --adm-s2-color: #ffffff;
  --adm-s2-border: #333333;
  --adm-s2-hover: #001199;
  --adm-s2-selected: #00105f;
}
#mb .select2-dropdown{
  background: var(--adm-s2-bg) !important;
  border: 1px solid var(--adm-s2-border) !important;
  color: var(--adm-s2-color) !important;
}
#mb .select2-results__option{
  background: transparent !important;
  color: var(--adm-s2-color) !important;
}
#mb .select2-container--default .select2-results__option--selected{
  background: var(--adm-s2-selected) !important;
  color: var(--adm-s2-color) !important;
}
#mb .select2-container--default .select2-results__option--highlighted.select2-results__option--selectable{
  background: var(--adm-s2-hover) !important;
  color: #ffffff !important;
}
#mb .select2-container--default .select2-selection--single .select2-selection__arrow b{
  border-color: #9fd8ff transparent transparent transparent !important;
}
#mb .select2-container--default.select2-container--open .select2-selection--single .select2-selection__arrow b{
  border-color: transparent transparent #9fd8ff transparent !important;
}
</style>
<?php include_once(__DIR__ . '/../../partials/admin/mentions_includes.php'); ?>
<?php endif; ?>

<?php
// admin_characters.php - CRUD Personajes (Clan/Manada + Sistema/Raza/Auspicio/Tribu + Avatar + Afiliacion + Poderes + Meritos/Defectos + Inventario + Campos complejos)

include_once(__DIR__ . '/../../helpers/admin_ajax.php');
if (!hg_admin_require_db($link)) { return; }

// [IMPORTANT] MUY IMPORTANTE: asegura que MySQLi entregue UTF-8 real (evita JSON roto)
if (method_exists($link, 'set_charset')) {
    $link->set_charset('utf8mb4');
} else {
    mysqli_set_charset($link, 'utf8mb4');
}

include_once(__DIR__ . '/../../helpers/mentions.php');

include_once(__DIR__ . '/../../helpers/pretty.php');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
include_once(__DIR__ . '/admin_characters_service.php');
include_once(__DIR__ . '/../../domains/characters/admin_queries.php');
include_once(__DIR__ . '/admin_characters_ajax.php');

// Rutas de avatar (usadas por create/update/delete en CRUD).
// Fallback a raiz de proyecto si DOCUMENT_ROOT no viene definido por el servidor.
$DOCROOT = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
if ($DOCROOT === '' || !is_dir($DOCROOT)) {
    $rootGuess = realpath(__DIR__ . '/../../../');
    $DOCROOT = $rootGuess ? rtrim((string)$rootGuess, '/') : rtrim(__DIR__, '/');
}
$AV_UPLOADDIR = $DOCROOT . '/public/img/characters';
$AV_URLBASE = '/img/characters';
if (!is_dir($AV_UPLOADDIR)) { @mkdir($AV_UPLOADDIR, 0775, true); }

$ADMIN_CSRF_SESSION_KEY = 'csrf_admin_characters';
$ADMIN_CSRF_TOKEN = function_exists('hg_admin_ensure_csrf_token')
    ? hg_admin_ensure_csrf_token($ADMIN_CSRF_SESSION_KEY)
    : '';

/* -------------------------------------------------
   Estado (catalogo + fallback legacy)
------------------------------------------------- */
$statusState = hg_characters_admin_status_state($link);
$estado_opts = $statusState['options'];
$default_status_id = (int)$statusState['default_id'];
$inactive_status_id = (int)$statusState['inactive_id'];
$has_status_id_col = !empty($statusState['has_status_id_col']);
$has_status_dim = !empty($statusState['has_status_dim']);

if (hg_admin_characters_handle_ajax($link)) {
    return;
}

// Guard defensivo: en AJAX POST, si falta crud_action (p.ej. post_max_size excedido),
// devolver JSON en lugar de HTML para evitar "Respuesta no JSON" en frontend.
$isXmlHttpRequest = (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest');
$isAjaxPostFlag = ((string)($_POST['ajax'] ?? '') === '1');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($isXmlHttpRequest || $isAjaxPostFlag) && !isset($_POST['crud_action'])) {
    $parseIniSize = static function ($value): int {
        $s = trim((string)$value);
        if ($s === '') return 0;
        if (!preg_match('/^(\d+)\s*([KMG])?$/i', $s, $m)) return (int)$s;
        $n = (int)$m[1];
        $u = strtoupper((string)($m[2] ?? ''));
        if ($u === 'G') return $n * 1024 * 1024 * 1024;
        if ($u === 'M') return $n * 1024 * 1024;
        if ($u === 'K') return $n * 1024;
        return $n;
    };

    $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    $postMaxRaw = (string)ini_get('post_max_size');
    $uploadMaxRaw = (string)ini_get('upload_max_filesize');
    $postMaxBytes = $parseIniSize($postMaxRaw);

    $errors = ['crud_action' => 'missing'];
    $data = [
        'content_length' => $contentLength,
        'post_max_size' => $postMaxRaw,
        'upload_max_filesize' => $uploadMaxRaw,
    ];

    if ($contentLength > 0 && empty($_POST) && empty($_FILES) && $postMaxBytes > 0 && $contentLength > $postMaxBytes) {
        $errors['upload'] = 'post_max_size_exceeded';
        hg_admin_json_error(
            'El archivo supera el limite permitido por el servidor (post_max_size).',
            413,
            $errors,
            $data,
            ['hint' => 'reduce_file_size_or_raise_post_max_size']
        );
    }

    hg_admin_json_error(
        'Peticion AJAX invalida: falta crud_action.',
        400,
        $errors,
        $data,
        ['hint' => 'ensure_formdata_contains_crud_action']
    );
}

/* -------------------------------------------------
   Config
------------------------------------------------- */
$perPage = isset($_GET['pp']) ? max(5, min(1000, intval($_GET['pp']))) : 25;
$page    = isset($_GET['pg']) ? max(1, intval($_GET['pg'])) : 1;
$q       = trim($_GET['q'] ?? '');
$fil_cr  = isset($_GET['fil_cr']) ? max(0, intval($_GET['fil_cr'])) : 0;
$fil_ma  = isset($_GET['fil_ma']) ? max(0, intval($_GET['fil_ma'])) : 0;
$offset  = ($page - 1) * $perPage;
$flash   = [];
$character_kind_column = pjs_table_has_column($link, 'fact_characters', 'kind') ? 'kind' : 'character_kind';
$character_kind_maxlen = pjs_column_char_maxlen($link, 'fact_characters', $character_kind_column);

/* -------------------------------------------------
   Cargar opciones de referencia
------------------------------------------------- */
$catalogs = hg_characters_admin_reference_catalogs($link);
$opts_cronicas = $catalogs['chronicles'];
$opts_clanes = $catalogs['organizations'];
$opts_jug = $catalogs['players'];
$opts_sist = $catalogs['systems'];
$opts_totems = $catalogs['totems'];
$opts_afili = $catalogs['character_types'];
$opts_archetypes = $catalogs['archetypes'];
$opts_manadas_flat = $catalogs['groups'];
$opts_dones = $catalogs['gifts'];
$opts_disciplinas = $catalogs['disciplines'];
$opts_rituales = $catalogs['rites'];

$complexCatalogs = hg_characters_admin_complex_catalogs($link);
$discipline_power_to_type = $complexCatalogs['disciplinePowerToType'];
$opts_myd_full = $complexCatalogs['merits'];
$opts_items_full = $complexCatalogs['items'];
$has_dim_systems_resources = $complexCatalogs['hasResources'];
$has_bridge_systems_resources = $complexCatalogs['hasSystemResources'];
$has_bridge_char_resources = $complexCatalogs['hasCharResources'];
$has_bridge_char_resources_log = $complexCatalogs['hasCharResourcesLog'];
$opts_resources_full = $complexCatalogs['resources'];
$resources_by_id = $complexCatalogs['resourcesById'];
$sys_resources_by_system = $complexCatalogs['resourcesBySystem'];
$traits_catalog = $complexCatalogs['traits'];
$valid_trait_ids = $complexCatalogs['validTraits'];
$monster_blocked_trait_ids = $complexCatalogs['blockedMonster'];
$trait_kind_order = $complexCatalogs['traitOrder'];
$trait_set_order = $complexCatalogs['traitSetOrder'];

$dimensions = hg_characters_admin_system_dimensions($link, $opts_sist);
$opts_razas = $dimensions['breed']['options'];
$razas_by_sys = $dimensions['breed']['by_system'];
$raza_id_to_sys = $dimensions['breed']['id_to_system'];
$raza_id_to_allowed_sys = $dimensions['breed']['allowed'];
$opts_ausp = $dimensions['auspice']['options'];
$ausp_by_sys = $dimensions['auspice']['by_system'];
$ausp_id_to_sys = $dimensions['auspice']['id_to_system'];
$ausp_id_to_allowed_sys = $dimensions['auspice']['allowed'];
$opts_tribus = $dimensions['tribe']['options'];
$tribus_by_sys = $dimensions['tribe']['by_system'];
$tribu_id_to_sys = $dimensions['tribe']['id_to_system'];
$tribu_id_to_allowed_sys = $dimensions['tribe']['allowed'];

$groupMaps = hg_characters_admin_group_maps($link);
$manadas_map_id_to_clan = $groupMaps['group_to_org'];
$manadas_by_clan = $groupMaps['by_org'];

/* -------------------------------------------------
   Crear / Editar (POST) + avatar + validaciones + PODERES + MÉRITOS/DEFECTOS + INVENTARIO + CAMPOS COMPLEJOS
------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crud_action'])) {
    $is_ajax_crud = ((string)($_POST['ajax'] ?? '') === '1')
        || (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest');
    if ($is_ajax_crud) {
        hg_admin_require_session(true);
        $csrf = hg_admin_extract_csrf_token($_POST);
        if (!hg_admin_csrf_valid($csrf, $ADMIN_CSRF_SESSION_KEY)) {
            hg_admin_json_error('CSRF invalido', 403, ['csrf' => 'invalid'], null, ['action' => (string)($_POST['crud_action'] ?? '')]);
        }
    }

    $action      = $_POST['crud_action'];
    $saved_character_id = 0;
    $id          = intval($_POST['id'] ?? 0);
    $nombre      = trim($_POST['nombre'] ?? '');
    $alias       = trim($_POST['alias'] ?? '');
    $nombregarou = trim($_POST['nombregarou'] ?? '');
    $gender   = trim($_POST['gender'] ?? '');
    $concept    = trim($_POST['concept'] ?? '');
    $text_color  = trim($_POST['text_color'] ?? '');
    $cronica     = max(0, intval($_POST['cronica'] ?? 0));
    $jugador     = max(0, intval($_POST['jugador'] ?? 0));
    $afili       = max(0, intval($_POST['afiliacion'] ?? 0));
    $raza        = max(0, intval($_POST['raza'] ?? 0));
    $auspice_id    = max(0, intval($_POST['auspice_id'] ?? 0));
    $tribe_id       = max(0, intval($_POST['tribe_id'] ?? 0));
    $nature_id_raw  = max(0, intval($_POST['nature_id'] ?? 0));
    $demeanor_id_raw= max(0, intval($_POST['demeanor_id'] ?? 0));
    $nature_id      = $nature_id_raw > 0 ? $nature_id_raw : null;
    $demeanor_id    = $demeanor_id_raw > 0 ? $demeanor_id_raw : null;
    $manada      = max(0, intval($_POST['manada'] ?? 0));
    $clan        = max(0, intval($_POST['clan'] ?? 0));
    $system_id   = isset($_POST['system_id']) ? (int)$_POST['system_id'] : 0;
    $totem_choice = isset($_POST['totem_id']) ? (int)$_POST['totem_id'] : 0;
    $totem_id = $totem_choice;
    $kind_raw = strtolower(trim((string)($_POST['kind'] ?? 'pnj')));
    if ($kind_raw === 'monster' || $kind_raw === 'mon') {
        $kind = 'mon';
    } elseif ($kind_raw === 'pj') {
        $kind = 'pj';
    } else {
        $kind = 'pnj';
    }
    $isMonsterKind = ($kind === 'mon');
    $isPlayableKind = ($kind !== 'pnj');
    $allowMydForKind = ($isPlayableKind && !$isMonsterKind);
    $rm_avatar   = isset($_POST['avatar_remove']) && $_POST['avatar_remove'] ? true : false;

    // Campos complejos
    $status_id   = max(0, (int)($_POST['status_id'] ?? 0));
    $rango       = trim($_POST['rango'] ?? '');
    $infotext    = trim($_POST['infotext'] ?? '');
    $infotext    = hg_mentions_convert($link, $infotext);
    $notas       = trim($_POST['notes'] ?? '');

    // PODERES
    $powers_type = isset($_POST['powers_type']) ? (array)$_POST['powers_type'] : [];
    $powers_id   = isset($_POST['powers_id'])   ? array_map('intval',(array)$_POST['powers_id']) : [];
    $powers_lvl  = isset($_POST['powers_lvl'])  ? array_map('intval',(array)$_POST['powers_lvl']) : [];

    // MÉRITOS/DEFECTOS
    $myd_id      = isset($_POST['myd_id'])  ? array_map('intval',(array)$_POST['myd_id']) : [];
    $myd_lvl_raw = isset($_POST['myd_lvl']) ? (array)$_POST['myd_lvl'] : [];

    // INVENTARIO
    $items_id    = isset($_POST['items_id']) ? array_map('intval',(array)$_POST['items_id']) : [];

    // TRAITS
    $traits_mode = trim((string)($_POST['traits_mode'] ?? ''));
    $traits_full_raw = isset($_POST['traits']) && is_array($_POST['traits']) ? $_POST['traits'] : [];
    $traits_upsert_raw = ($traits_mode === 'delta' && isset($_POST['traits_upsert']) && is_array($_POST['traits_upsert']))
        ? $_POST['traits_upsert']
        : [];
    $traits_delete_raw = ($traits_mode === 'delta' && isset($_POST['traits_delete']))
        ? (array)$_POST['traits_delete']
        : [];
    $traits_was_submitted = ($traits_mode === 'delta')
        ? (!empty($traits_upsert_raw) || !empty($traits_delete_raw))
        : !empty($traits_full_raw);
    $traits_dirty = ((string)($_POST['traits_dirty'] ?? '0') === '1');
    $should_save_traits = $traits_was_submitted && ($action === 'create' || $traits_dirty);
    $traits = [];
    $traits_delete = [];
    if ($should_save_traits) {
        if ($traits_mode === 'delta') {
            foreach ($traits_upsert_raw as $tid => $val) {
                $tid = (int)$tid;
                if ($tid <= 0 || !isset($valid_trait_ids[$tid])) continue;
                $v = is_string($val) ? trim($val) : $val;
                if ($v === '' || $v === null) $v = 0;
                $v = (int)$v;
                if ($v < 0) $v = 0;
                if ($v > 10) $v = 10;
                if ($v > 0) $traits[$tid] = $v;
                else $traits_delete[$tid] = true;
            }
            foreach ($traits_delete_raw as $tid) {
                $tid = (int)$tid;
                if ($tid <= 0 || !isset($valid_trait_ids[$tid])) continue;
                $traits_delete[$tid] = true;
            }
        } else {
            foreach ($traits_full_raw as $tid => $val) {
                $tid = (int)$tid;
                if ($tid <= 0 || !isset($valid_trait_ids[$tid])) continue;
                $v = is_string($val) ? trim($val) : $val;
                if ($v === '' || $v === null) $v = 0;
                $v = (int)$v;
                if ($v < 0) $v = 0;
                if ($v > 10) $v = 10;
                if ($v > 0) $traits[$tid] = $v;
                else $traits_delete[$tid] = true;
            }
        }
    }
    if ($isMonsterKind && !empty($traits) && !empty($monster_blocked_trait_ids)) {
        foreach (array_keys($monster_blocked_trait_ids) as $blockedTid) {
            unset($traits[(int)$blockedTid]);
        }
    }
    foreach (array_keys($traits) as $traitId) {
        unset($traits_delete[(int)$traitId]);
    }
    $traits_delete = array_map('intval', array_keys($traits_delete));

    // RECURSOS (nuevo modelo): arrays paralelos enviados desde chips del modal
    $resources_rows = [];
    $res_ids_raw  = isset($_POST['resource_ids']) ? (array)$_POST['resource_ids'] : [];
    $res_perm_raw = isset($_POST['resource_perm']) ? (array)$_POST['resource_perm'] : [];
    $res_temp_raw = isset($_POST['resource_temp']) ? (array)$_POST['resource_temp'] : [];
    $nres = min(count($res_ids_raw), count($res_perm_raw), count($res_temp_raw));
    for ($i = 0; $i < $nres; $i++) {
        $rid = (int)$res_ids_raw[$i];
        if ($rid <= 0) continue;
        if (!isset($resources_by_id[$rid])) continue; // ignora IDs no válidos
        $perm = (int)(is_string($res_perm_raw[$i]) ? trim($res_perm_raw[$i]) : $res_perm_raw[$i]);
        $temp = (int)(is_string($res_temp_raw[$i]) ? trim($res_temp_raw[$i]) : $res_temp_raw[$i]);
        if ($perm < 0) $perm = 0;
        if ($temp < 0) $temp = 0;
        $resources_rows[$rid] = ['perm'=>$perm, 'temp'=>$temp];
    }

    if ($gender === '')  $gender = 'f';
    if ($text_color === '') $text_color = 'SkyBlue';
    if ($status_id <= 0) $status_id = (int)$default_status_id;

    // Validaciones
    if ($clan <= 0) $flash[] = ['type'=>'error','msg'=>'[WARN] Debes seleccionar un Clan.'];
    if ($status_id <= 0) $flash[] = ['type'=>'error','msg'=>'El estado no es válido.'];
    if ($manada > 0) {
        $clan_of_manada = $manadas_map_id_to_clan[$manada] ?? 0;
        if ($clan_of_manada !== $clan) {
            $flash[] = ['type'=>'error','msg'=>'[WARN] La Manada seleccionada no pertenece al Clan elegido.'];
        }
    }
    if ($system_id > 0) {
        if ($raza > 0 && isset($raza_id_to_allowed_sys[$raza]) && !isset($raza_id_to_allowed_sys[$raza][(int)$system_id])) {
            $flash[]=['type'=>'error','msg'=>'[WARN] La Raza no pertenece al Sistema elegido.'];
        }
        if ($auspice_id > 0 && isset($ausp_id_to_allowed_sys[$auspice_id]) && !isset($ausp_id_to_allowed_sys[$auspice_id][(int)$system_id])) {
            $flash[]=['type'=>'error','msg'=>'[WARN] El Auspicio no pertenece al Sistema elegido.'];
        }
        if ($tribe_id > 0 && isset($tribu_id_to_allowed_sys[$tribe_id]) && !isset($tribu_id_to_allowed_sys[$tribe_id][(int)$system_id])) {
            $flash[]=['type'=>'error','msg'=>'[WARN] La Tribu no pertenece al Sistema elegido.'];
        }
    }

    // Totem:
    //   0  = heredar de Manada o Clan
    //  -1  = sin totem (guardar NULL)
    //  >0  = totem explicito
    if ($totem_choice === -1) {
        $totem_id = null;
    } elseif ($totem_id <= 0) {
        $totem_id = hg_characters_admin_inherited_totem($link, (int)$manada, (int)$clan);
    }
    if (!($totem_id > 0 && isset($opts_totems[$totem_id]))) {
        $totem_id = null; // NULL para evitar FK con 0
    }

    // Avatar actual (para update/delete) + existencia
    $current_img = '';
    $character_exists = false;
    if (($action === 'update' || $action === 'delete') && $id > 0) {
        $current = hg_characters_admin_current_image($link, $id);
        $character_exists = !empty($current['exists']);
        $current_img = (string)($current['image_url'] ?? '');
    }

    if ($action === 'create') {
        if ($nombre === '') $flash[] = ['type'=>'error','msg'=>'[WARN] El campo \"nombre\" es obligatorio.'];
        if (!array_filter($flash, fn($f)=>$f['type']==='error')) {
            $sql = "INSERT INTO fact_characters
                (name, alias, garou_name, gender, concept, chronicle_id, player_id, character_type_id, image_url, notes, text_color, `$character_kind_column`, system_id,
                 totem_id, status_id, rank, info_text, breed_id, auspice_id, tribe_id, nature_id, demeanor_id)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
            if ($stmt = $link->prepare($sql)) {
                $img='';
                $stmt->bind_param(
                    "sssssiiissssiiissiiiii",
                    $nombre, $alias, $nombregarou, $gender, $concept,
                    $cronica, $jugador, $afili,
                    $img, $notas, $text_color, $kind, $system_id,
                    $totem_id,
                    $status_id, $rango, $infotext,
                    $raza, $auspice_id, $tribe_id, $nature_id, $demeanor_id
                );
                if ($stmt->execute()) {
                    $newId = $stmt->insert_id;
                    $saved_character_id = (int)$newId;
                    hg_update_pretty_id_if_exists($link, 'fact_characters', (int)$newId, $nombre);

                    // Bridges manada/clan
                    sync_character_bridges($link, (int)$newId, (int)$manada, (int)$clan);

                    // Avatar si viene
                    if (!empty($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
                        $res = save_avatar_file($_FILES['avatar'], $newId, $nombre, $AV_UPLOADDIR, $AV_URLBASE);
                        if ($res['ok']) {
                            $avatarStored = hg_characters_admin_set_image($link, (int)$newId, (string)$res['url']);
                            if ($avatarStored) {
                                $flash[] = ['type'=>'ok','msg'=>'Avatar convertido y guardado como WebP.'];
                            } else {
                                if (!empty($res['path']) && is_file($res['path'])) @unlink($res['path']);
                                $flash[] = ['type'=>'error','msg'=>'[WARN] El avatar se convirtio, pero no se pudo guardar su ruta.'];
                            }
                        } elseif ($res['msg']!=='no_file') {
                            $flash[] = ['type'=>'error','msg'=>'[WARN] Avatar no guardado: '.$res['msg']];
                        }
                    }
                    if ($isPlayableKind) {
                    // Poderes
                    $resultPow = save_character_powers($link, (int)$newId, $powers_type, $powers_id, $powers_lvl);
                    if ($resultPow['inserted']>0) { $flash[]=['type'=>'ok','msg'=>'[OK] Poderes vinculados: '.$resultPow['inserted']]; }
                    if ($resultPow['skipped']>0)  { $flash[]=['type'=>'info','msg'=>'(Poderes omitidos: '.$resultPow['skipped'].')']; }

                    // Méritos/Defectos
                    if ($allowMydForKind) {
                        $resultMyd = save_character_merits_flaws($link, (int)$newId, $myd_id, $myd_lvl_raw);
                        if ($resultMyd['inserted']>0) { $flash[]=['type'=>'ok','msg'=>'Meritos/Defectos vinculados: '.$resultMyd['inserted']]; }
                        if ($resultMyd['skipped']>0)  { $flash[]=['type'=>'info','msg'=>'(Méritos/Defectos omitidos: '.$resultMyd['skipped']. ')']; }
                    }

                    // Inventario
                    $resultIt = save_character_items($link, (int)$newId, $items_id);
                    if ($resultIt['inserted']>0) { $flash[]=['type'=>'ok','msg'=>'Objetos vinculados: '.$resultIt['inserted']]; }
                    if ($resultIt['skipped']>0)  { $flash[]=['type'=>'info','msg'=>'(Objetos omitidos: '.$resultIt['skipped'].')']; }

                    
                    // Traits: solo si llegaron y fueron modificados en el modal (evita borrado accidental)
                    if ($should_save_traits) {
                        $resultTr = save_character_traits($link, (int)$newId, $traits, $traits_delete, 'admin', null);
                        if ($resultTr['updated']>0) {
                            $flash[]=['type'=>'ok','msg'=>'Traits guardados: '.$resultTr['updated']];
                        }
                    }

                    // Recursos (estado/permanente)
                    $resultRes = save_character_resources(
                        $link,
                        (int)$newId,
                        (int)$system_id,
                        $resources_rows,
                        $sys_resources_by_system,
                        $has_bridge_char_resources,
                        $has_bridge_char_resources_log,
                        'admin',
                        null
                    );
                    if (!empty($resultRes['error'])) {
                        $flash[]=['type'=>'error','msg'=>'[WARN] Recursos no guardados: '.$resultRes['error']];
                    } elseif (!empty($resultRes['disabled'])) {
                        $flash[]=['type'=>'info','msg'=>'(Recursos omitidos: tabla bridge_characters_system_resources no disponible)'];
                    } elseif (($resultRes['saved'] ?? 0) > 0) {
                        $msgRes = 'Recursos guardados: ' . (int)$resultRes['saved'];
                        if (($resultRes['forced'] ?? 0) > 0) $msgRes .= ' (forzados por sistema: '.(int)$resultRes['forced'].')';
                        $flash[]=['type'=>'ok','msg'=>$msgRes];
                    }
                    }
$flash[] = ['type'=>'ok','msg'=>'[OK] Personaje creado correctamente.'];
                } else {
                    $flash[] = ['type'=>'error','msg'=>'[ERROR] Error al crear: '.$stmt->error];
                }
                $stmt->close();
            } else {
                $flash[] = ['type'=>'error','msg'=>'[ERROR] Error al preparar INSERT: '.$link->error];
            }
        }
    }

    if ($action === 'update') {
    if ($id <= 0)       $flash[] = ['type'=>'error','msg'=>'[WARN] Falta el ID para editar.'];
    if ($nombre === '') $flash[] = ['type'=>'error','msg'=>'[WARN] El campo "nombre" es obligatorio.'];

    if (!array_filter($flash, fn($f)=>$f['type']==='error')) {

          // ? OJO: ya NO actualizamos p.manada ni p.clan aquí (bridges mandan)
          $sql = "UPDATE fact_characters SET
                  name=?, alias=?, garou_name=?, gender=?, concept=?,
                  chronicle_id=?, player_id=?, character_type_id=?, system_id=?, text_color=?, `$character_kind_column`=?,
                  breed_id=?, auspice_id=?, tribe_id=?, nature_id=?, demeanor_id=?,
                  totem_id=?,
                  status_id=?, rank=?, info_text=?, notes=?
                  WHERE id=?";

          if ($stmt = $link->prepare($sql)) {

              // 13 strings/ints + 5 strings + id (int)
              $stmt->bind_param(
                  "sssssiiiissiiiiiiisssi",
                  $nombre, $alias, $nombregarou, $gender, $concept,
                  $cronica, $jugador, $afili, $system_id, $text_color,
                  $kind,
                  $raza, $auspice_id, $tribe_id, $nature_id, $demeanor_id,
                  $totem_id,
                  $status_id, $rango, $infotext, $notas,
                  $id
              );

              if ($stmt->execute()) {
                  $saved_character_id = (int)$id;
                  hg_update_pretty_id_if_exists($link, 'fact_characters', $id, $nombre);
                  // Avatar: el anterior solo se elimina despues de guardar correctamente el nuevo.
                  $hasAvatarUpload = !empty($_FILES['avatar'])
                      && ($_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
                  if ($hasAvatarUpload) {
                      $res = save_avatar_file($_FILES['avatar'], $id, $nombre, $AV_UPLOADDIR, $AV_URLBASE);
                      if ($res['ok']) {
                          $avatarStored = hg_characters_admin_set_image($link, (int)$id, (string)$res['url']);
                          if ($avatarStored) {
                              if ($current_img) safe_unlink_avatar($current_img, $AV_UPLOADDIR);
                              $current_img = (string)$res['url'];
                              $flash[] = ['type'=>'ok','msg'=>'Avatar convertido y actualizado como WebP.'];
                          } else {
                              if (!empty($res['path']) && is_file($res['path'])) @unlink($res['path']);
                              $flash[] = ['type'=>'error','msg'=>'[WARN] El avatar anterior se conserva porque no se pudo actualizar la ruta del nuevo WebP.'];
                          }
                      } elseif ($res['msg'] !== 'no_file') {
                          $flash[] = ['type'=>'error','msg'=>'[WARN] Avatar no guardado: '.$res['msg']];
                      }
                  } elseif ($rm_avatar && $current_img) {
                      $avatarRemoved = hg_characters_admin_clear_image($link, (int)$id);
                      if ($avatarRemoved) {
                          safe_unlink_avatar($current_img, $AV_UPLOADDIR);
                          $current_img = '';
                          $flash[] = ['type'=>'ok','msg'=>'Avatar eliminado.'];
                      } else {
                          $flash[] = ['type'=>'error','msg'=>'[WARN] No se pudo eliminar el avatar.'];
                      }
                  }

                  // Bridges: aqui si guardas clan/manada (fuente de verdad)
                  sync_character_bridges($link, (int)$id, (int)$manada, (int)$clan);

                  if ($isPlayableKind) {
                  // Poderes
                  $resultPow = save_character_powers($link, (int)$id, $powers_type, $powers_id, $powers_lvl);
                  if ($resultPow['inserted']>0) { $flash[]=['type'=>'ok','msg'=>'[OK] Poderes vinculados: '.$resultPow['inserted']]; }
                  if ($resultPow['skipped']>0)  { $flash[]=['type'=>'info','msg'=>'(Poderes omitidos: '.$resultPow['skipped'].')']; }

                  // Méritos/Defectos
                  if ($allowMydForKind) {
                      $resultMyd = save_character_merits_flaws($link, (int)$id, $myd_id, $myd_lvl_raw);
                      if ($resultMyd['inserted']>0) { $flash[]=['type'=>'ok','msg'=>'Meritos/Defectos vinculados: '.$resultMyd['inserted']]; }
                      if ($resultMyd['skipped']>0)  { $flash[]=['type'=>'info','msg'=>'(Méritos/Defectos omitidos: '.$resultMyd['skipped']. ')']; }
                  }

                  // Inventario
                  $resultIt = save_character_items($link, (int)$id, $items_id);
                  if ($resultIt['inserted']>0) { $flash[]=['type'=>'ok','msg'=>'Objetos vinculados: '.$resultIt['inserted']]; }
                  if ($resultIt['skipped']>0)  { $flash[]=['type'=>'info','msg'=>'(Objetos omitidos: '.$resultIt['skipped'].')']; }

                  
                  // Traits: solo si llegaron y fueron modificados en el modal (evita borrado accidental)
                  if ($should_save_traits) {
                      $resultTr = save_character_traits($link, (int)$id, $traits, $traits_delete, 'admin', null);
                      if ($resultTr['updated']>0) {
                          $flash[]=['type'=>'ok','msg'=>'Traits guardados: '.$resultTr['updated']];
                      }
                  }

                  // Recursos (estado/permanente)
                  $resultRes = save_character_resources(
                      $link,
                      (int)$id,
                      (int)$system_id,
                      $resources_rows,
                      $sys_resources_by_system,
                      $has_bridge_char_resources,
                      $has_bridge_char_resources_log,
                      'admin',
                      null
                  );
                  if (!empty($resultRes['error'])) {
                      $flash[]=['type'=>'error','msg'=>'[WARN] Recursos no guardados: '.$resultRes['error']];
                  } elseif (!empty($resultRes['disabled'])) {
                      $flash[]=['type'=>'info','msg'=>'(Recursos omitidos: tabla bridge_characters_system_resources no disponible)'];
                  } elseif (($resultRes['saved'] ?? 0) > 0) {
                      $msgRes = 'Recursos guardados: ' . (int)$resultRes['saved'];
                      if (($resultRes['forced'] ?? 0) > 0) $msgRes .= ' (forzados por sistema: '.(int)$resultRes['forced'].')';
                      $flash[]=['type'=>'ok','msg'=>$msgRes];
                  }
                  }
$flash[] = ['type'=>'ok','msg'=>'[EDIT] Personaje actualizado.'];

              } else {
                  $flash[] = ['type'=>'error','msg'=>'[ERROR] Error al actualizar: '.$stmt->error];
              }

              $stmt->close();

          } else {
              $flash[] = ['type'=>'error','msg'=>'[ERROR] Error al preparar UPDATE: '.$link->error];
          }
      }
  }

  if ($action === 'delete') {
      if ($id <= 0) {
          $flash[] = ['type'=>'error','msg'=>'[WARN] Falta el ID para desactivar.'];
      } elseif (!$character_exists) {
          $flash[] = ['type'=>'error','msg'=>'[WARN] El personaje no existe o ya no esta disponible.'];
      } else {
          $okDelete = false;

          if ($has_status_id_col) {
              if ($inactive_status_id > 0) {
                  $okDelete = hg_characters_admin_soft_delete($link, (int)$id, (int)$inactive_status_id);
              }
          }

          if ($okDelete) {
              $saved_character_id = (int)$id;
              $flash[] = ['type'=>'ok','msg'=>'[OK] Personaje desactivado.'];
          } else {
              $flash[] = ['type'=>'error','msg'=>'[ERROR] No se pudo desactivar el personaje.'];
          }
      }
  }

  if ($action !== 'create' && $action !== 'update' && $action !== 'delete') {
      $flash[] = ['type'=>'error','msg'=>'[WARN] Accion CRUD no soportada.'];
  }

  if (!empty($is_ajax_crud)) {
      $okMessages = [];
      $errMessages = [];
      foreach ($flash as $m) {
          $msgText = trim((string)($m['msg'] ?? ''));
          if ($msgText === '') continue;
          if (($m['type'] ?? '') === 'error') {
              $errMessages[] = $msgText;
          } else {
              $okMessages[] = $msgText;
          }
      }

      $hasError = !empty($errMessages);
      $payloadData = [
          'id' => ($saved_character_id > 0 ? $saved_character_id : (int)$id),
          'action' => (string)$action,
          'messages' => $okMessages,
          'errors' => $errMessages,
      ];

      if ($hasError) {
          $msg = $errMessages[0] ?? 'Error al guardar personaje';
          hg_admin_json_error($msg, 400, ['form' => $errMessages], $payloadData, ['action' => (string)$action, 'id' => (int)$payloadData['id']]);
      }

      $msg = !empty($okMessages) ? end($okMessages) : 'OK';
      hg_admin_json_success($payloadData, $msg, ['action' => (string)$action, 'id' => (int)$payloadData['id']]);
  }
}

/* -------------------------------------------------
   Listado + Paginación
------------------------------------------------- */
$total = hg_characters_admin_count($link, $fil_cr, $fil_ma, $q);
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;

$pageResult = hg_characters_admin_fetch_page($link, $character_kind_column, $fil_cr, $fil_ma, $q, $offset, $perPage);
if (empty($pageResult['ok'])) {
    hg_runtime_log_error('admin_characters.list_prepare', (string)($pageResult['error'] ?? $link->error));
    hg_admin_render_error('Personajes no disponibles', 'No se pudo preparar el listado de personajes.', 500);
    return;
}
$rows = $pageResult['rows'];
$ids_page = array_map(static fn($row) => (int)($row['id'] ?? 0), $rows);

$preload = hg_characters_admin_preload($link, $ids_page);
$char_details = $preload['details'];

$char_powers = [];
foreach ($preload['powers'] as $pw) {
    $cid = (int)$pw['character_id'];
    $tp = (string)$pw['power_kind'];
    $pid = (int)$pw['power_id'];
    $rawPid = $pid;
    $lvl = (int)$pw['power_level'];
    if ($tp === 'disciplinas' && !isset($opts_disciplinas[$pid]) && isset($discipline_power_to_type[$pid])) {
        $pid = (int)$discipline_power_to_type[$pid];
    }
    if ($tp === 'dones') $nm = $opts_dones[$pid] ?? ('#'.$rawPid);
    elseif ($tp === 'disciplinas') $nm = $opts_disciplinas[$pid] ?? ('#'.$rawPid);
    else $nm = $opts_rituales[$pid] ?? ('#'.$rawPid);
    $key = $tp . ':' . $pid;
    if (!isset($char_powers[$cid])) $char_powers[$cid] = [];
    if (!isset($char_powers[$cid][$key])) {
        $char_powers[$cid][$key] = ['t'=>$tp,'id'=>$pid,'lvl'=>$lvl,'name'=>$nm];
    } elseif ($lvl > (int)$char_powers[$cid][$key]['lvl']) {
        $char_powers[$cid][$key]['lvl'] = $lvl;
    }
}
foreach ($char_powers as $cid => $rowsByKey) $char_powers[$cid] = array_values($rowsByKey);

$char_myd = [];
foreach ($preload['myd'] as $row) {
    $cid = (int)$row['character_id'];
    $char_myd[$cid][] = [
        'id'=>(int)$row['id'],'name'=>(string)$row['name'],'tipo'=>(string)$row['kind'],
        'coste'=>(string)($row['cost'] ?? ''),'nivel'=>$row['level'] === null ? null : (int)$row['level'],
    ];
}

$char_items = [];
foreach ($preload['items'] as $row) {
    $cid = (int)$row['character_id'];
    $char_items[$cid][] = ['id'=>(int)$row['id'],'name'=>(string)$row['name'],'tipo'=>(int)($row['item_type_id'] ?? 0)];
}

$char_traits = [];
foreach ($preload['traits'] as $row) {
    $char_traits[(int)$row['character_id']][(int)$row['trait_id']] = (int)$row['value'];
}

$char_resources = [];
foreach ($preload['resources'] as $row) {
    $cid = (int)$row['character_id'];
    $char_resources[$cid][] = [
        'id'=>(int)$row['resource_id'],'name'=>(string)$row['name'],'kind'=>(string)$row['kind'],
        'sort_order'=>(int)($row['sort_order'] ?? 0),'perm'=>(int)($row['value_permanent'] ?? 0),'temp'=>(int)($row['value_temporary'] ?? 0),
    ];
}

// Base AJAX (misma página)
$AJAX_BASE = "/talim?s=admin_characters&ajax=1";
?>

<br />
<div class="panel-wrap">
  <div class="hdr">
    <h2>Personajes - Lista y CRUD</h2>
    <button class="btn btn-green" id="btnNew">+ Nuevo personaje</button>
    <a class="btn" href="/talim?s=admin_character_conditions_bridge">Condiciones</a>
    <a class="btn" href="/talim?s=admin_character_misc_bridge">Misc Systems</a>
    <a class="btn" href="/talim?s=admin_character_deaths">Muertes</a>
    <a class="btn" href="/talim?s=admin_birthdays_quick">Cumplea&ntilde;os</a>

    <form method="get" id="charactersFilterForm" action="/talim" class="adm-flex-8-center-spaced">
      <input type="hidden" name="p" value="talim">
      <input type="hidden" name="s" value="admin_characters">
      <label>Crónica
        <select class="select" name="fil_cr">
          <option value="0">Todas</option>
          <?php foreach($opts_cronicas as $id=>$name): ?>
            <option value="<?= (int)$id ?>" <?= $fil_cr==$id?'selected':'' ?>><?= h($name) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Manada
        <select class="select" name="fil_ma">
          <option value="0">Todas</option>
          <?php foreach($opts_manadas_flat as $id=>$name): ?>
            <option value="<?= (int)$id ?>" <?= $fil_ma==$id?'selected':'' ?>><?= h($name) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="adm-ml-auto-left">Buscar
        <input class="inp" type="text" name="q" id="quickFilter" value="<?= h($q) ?>" placeholder="Nombre...">
      </label>
      <label>Pág
        <select class="select" name="pp">
          <?php foreach([25,50,100,250,500,1000] as $pp): ?>
            <option value="<?= $pp ?>" <?= $perPage==$pp?'selected':'' ?>><?= $pp ?></option>
          <?php endforeach; ?>
        </select>
      </label>
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

  <table class="table" id="tablaPjs">
    <thead>
      <tr>
        <th class="adm-w-60">ID</th>
        <th>Nombre</th>
        <th>Jugador</th>
        <th>Crónica</th>
        <th>Sistema</th>
        <th class="adm-w-170">Acciones</th>
      </tr>
    </thead>
    <tbody id="tablaPjsBody">
      <?php foreach ($rows as $r): ?>
        <tr data-nombre="<?= strtolower(h($r['name'])) ?>">
          <td><strong class="adm-color-accent"><?= (int)$r['id'] ?></strong></td>
          <td><?= h($r['name']) ?></td>
          <td><?= h($r['jugador_'] ?? $r['player_id']) ?></td>
          <td><?= h($r['cronica_'] ?? $r['chronicle_id']) ?></td>
          <td><?= h($r['sistema_n'] ?? '') ?></td>
          <td>
            <button class="btn btn-small" data-edit='1'
              data-id="<?= (int)$r['id'] ?>"
              data-nombre="<?= h($r['name']) ?>"
              data-alias="<?= h($r['alias']) ?>"
              data-nombregarou="<?= h($r['garou_name']) ?>"
              data-gender="<?= h($r['gender']) ?>"
              data-concept="<?= h($r['concept']) ?>"
              data-cronica="<?= (int)$r['chronicle_id'] ?>"
              data-jugador="<?= (int)$r['player_id'] ?>"
              data-system_id="<?= (int)($r['system_id'] ?? 0) ?>"
              data-totem_id="<?= (int)($r['totem_id'] ?? 0) ?>"
              data-totem_mode="<?= ((int)($r['totem_id'] ?? 0) > 0 ? 'direct' : 'none') ?>"
              data-text_color="<?= h($r['text_color']) ?>"
              data-raza="<?= (int)$r['breed_id'] ?>"
              data-auspice_id="<?= (int)$r['auspice_id'] ?>"
              data-tribe_id="<?= (int)$r['tribe_id'] ?>"
              data-nature_id="<?= (int)($r['nature_id'] ?? 0) ?>"
              data-demeanor_id="<?= (int)($r['demeanor_id'] ?? 0) ?>"
              data-manada="<?= (int)$r['manada'] ?>"
              data-clan="<?= (int)$r['clan'] ?>"
              data-img="<?= h($r['image_url']) ?>"
              data-afiliacion="<?= (int)$r['character_type_id'] ?>"
              data-kind="<?= h((string)($r['kind'] ?? 'pnj')) ?>"
            >Editar</button>
            <button class="btn btn-small btn-red" type="button"
              data-delete="1"
              data-id="<?= (int)$r['id'] ?>"
              data-nombre="<?= h($r['name']) ?>"
            >Desactivar</button>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($rows)): ?>
        <tr><td colspan="6" class="adm-color-muted">(Sin resultados)</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <div class="pager" id="charactersPager">
    <?php
      $base = "/talim?s=admin_characters&pp=".$perPage."&fil_cr=".$fil_cr."&fil_ma=".$fil_ma."&q=".urlencode($q);
      $prev = max(1, $page-1);
      $next = min($pages, $page+1);
    ?>
    <a href="<?= $base ?>&pg=1">« Primero</a>
    <a href="<?= $base ?>&pg=<?= $prev ?>">‹ Anterior</a>
    <span class="cur">Pág <?= $page ?>/<?= $pages ?> · Total <?= $total ?></span>
    <a href="<?= $base ?>&pg=<?= $next ?>">Siguiente ›</a>
    <a href="<?= $base ?>&pg=<?= $pages ?>">Último »</a>
  </div>
</div>

<!-- Modal Crear/Editar -->
<div class="modal-back" id="mb">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <h3 id="modalTitle">Nuevo personaje</h3>
    <form method="post" id="formCrud" enctype="multipart/form-data" class="adm-m-0">
      <input type="hidden" name="crud_action" id="crud_action" value="create">
      <input type="hidden" name="id" id="f_id" value="0">
      <input type="hidden" name="csrf" id="f_csrf" value="<?= h($ADMIN_CSRF_TOKEN) ?>">
      <input type="hidden" name="traits_dirty" id="f_traits_dirty" value="0">
      <div class="grid">
        <div>
          <label>Nombre
            <input class="inp" type="text" name="nombre" id="f_nombre" maxlength="50" required>
          </label>
        </div>
        <div>
          <label>Alias
            <input class="inp" type="text" name="alias" id="f_alias" maxlength="20">
          </label>
        </div>
        <div>
          <label class="adm-text-left">Nombre Garou
            <input class="inp" type="text" name="nombregarou" id="f_nombregarou" maxlength="100">
          </label>
        </div>

        <div>
          <label>Género (f/m/…)
            <input class="inp" type="text" name="gender" id="f_genero_pj" maxlength="1" placeholder="f">
          </label>
        </div>
        <div>
          <label>Concepto
            <input class="inp" type="text" name="concept" id="f_concepto" maxlength="50">
          </label>
        </div>
        <div>
          <label class="adm-text-left">Color texto
            <input class="inp" type="text" name="text_color" id="f_colortexto" placeholder="SkyBlue">
          </label>
        </div>

        <div>
          <label>Estado
            <select class="select" name="status_id" id="f_estado" required>
              <option value="">— Selecciona —</option>
              <?php foreach ($estado_opts as $sid=>$label): ?>
                <option value="<?= (int)$sid ?>"><?= h($label==='' ? '(vacío)' : $label) ?></option>
              <?php endforeach; ?>
            </select>
            <span class="small-note">Lista desde: dim_character_status</span>
          </label>
        </div>
        <div>
          <label>Rango
            <input class="inp" type="text" name="rango" id="f_rango" maxlength="100">
          </label>
        </div>

        <div>
          <label>Crónica
            <select class="select" name="cronica" id="f_cronica">
              <option value="0">—</option>
              <?php foreach($opts_cronicas as $id=>$name): ?>
                <option value="<?= (int)$id ?>"><?= h($name) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
        <div>
          <label>Jugador
            <select class="select" name="jugador" id="f_jugador">
              <option value="0">—</option>
              <?php foreach($opts_jug as $id=>$name): ?>
                <option value="<?= (int)$id ?>"><?= h($name) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
        <div>
          <label class="adm-text-left">¿Qué es?
            <select class="select" name="afiliacion" id="f_afiliacion">
              <option value="0">—</option>
              <?php foreach($opts_afili as $id=>$name): ?>
                <option value="<?= (int)$id ?>"><?= h($name) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
        <div>
          <label class="adm-text-left">kind
            <select class="select" name="kind" id="f_kind">
              <option value="pj">pj</option>
              <option value="pnj" selected>pnj</option>
              <option value="mon">mon</option>
            </select>
          </label>
        </div>

        <div>
          <label>Sistema
            <select class="select" name="system_id" id="f_system_id">
              <option value="0">—</option>
              <?php foreach($opts_sist as $id=>$name): ?>
                <option value="<?= (int)$id ?>"><?= h($name) ?></option>
              <?php endforeach; ?>
            </select>
            <span class="small-note">Filtra Raza, Auspicio y Tribu</span>
          </label>
        </div>

        <div>
          <label>Raza
            <select class="select" name="raza" id="f_raza" disabled>
              <option value="0">— Elige un Sistema —</option>
              <?php foreach($opts_razas as $id=>$label): ?>
                <option value="<?= (int)$id ?>"><?= h($label) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
        <div>
          <label>Auspicio
            <select class="select" name="auspice_id" id="f_auspicio" disabled>
              <option value="0">— Elige un Sistema —</option>
              <?php foreach($opts_ausp as $id=>$name): ?>
                <option value="<?= (int)$id ?>"><?= h($name) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
        <div>
          <label>Tribu
            <select class="select" name="tribe_id" id="f_tribu" disabled>
              <option value="0">— Elige un Sistema —</option>
              <?php foreach($opts_tribus as $id=>$name): ?>
                <option value="<?= (int)$id ?>"><?= h($name) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
        <div>
          <label>Naturaleza
            <select class="select" name="nature_id" id="f_nature_id">
              <option value="0">— Sin naturaleza —</option>
              <?php foreach($opts_archetypes as $id=>$name): ?>
                <option value="<?= (int)$id ?>"><?= h($name) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
        <div>
          <label>Conducta
            <select class="select" name="demeanor_id" id="f_demeanor_id">
              <option value="0">— Sin conducta —</option>
              <?php foreach($opts_archetypes as $id=>$name): ?>
                <option value="<?= (int)$id ?>"><?= h($name) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>

        <div>
          <label>Clan
            <select class="select" name="clan" id="f_clan" required>
              <option value="0">— Selecciona —</option>
              <?php foreach($opts_clanes as $id=>$name): ?>
                <option value="<?= (int)$id ?>"><?= h($name) ?></option>
              <?php endforeach; ?>
            </select>
            <span class="small-note">Un PJ debe tener Clan</span>
          </label>
        </div>

        <div>
          <label>Tótem (opcional)
            <select class="select" name="totem_id" id="f_totem_id">
              <option value="0">— Heredar de Manada / Clan —</option>
              <option value="-1">— Sin Tótem —</option>
              <?php foreach($opts_totems as $id=>$name): ?>
                <option value="<?= (int)$id ?>"><?= h($name) ?></option>
              <?php endforeach; ?>
            </select>
            <span class="small-note">Puedes heredar el tótem de la Manada o del Clan, o dejar el personaje sin tótem.</span>
          </label>
        </div>

        <div>
          <label>Manada
            <select class="select" name="manada" id="f_manada" disabled>
              <option value="0">— Selecciona primero un Clan —</option>
            </select>
            <span class="small-note">Sólo se muestran las manadas del Clan elegido</span>
          </label>
        </div>

        <div>
          <label>Avatar
            <div class="avatar-wrap">
              <img id="f_avatar_preview" src="" alt="avatar" class="adm-hidden">
              <div>
                <input class="inp" type="file" name="avatar" id="f_avatar" accept="image/jpeg,image/png,image/webp">
                <label class="small-note"><input type="checkbox" name="avatar_remove" id="f_avatar_remove" value="1"> Quitar avatar</label>
                <span class="small-note">JPG/PNG/WebP &middot; m&aacute;x. 5 MB &middot; guardado autom&aacute;tico como WebP</span>
              </div>
            </div>
          </label>
        </div>

        <div class="adm-grid-full">
          <label class="adm-text-left">Información sobre el personaje
            <textarea class="ta hg-mention-input" data-mentions="character,season,episode,organization,group,gift,rite,totem,discipline,item,trait,background,merit,flaw,merydef,doc" name="infotext" id="f_infotext" rows="6" placeholder="Texto largo…"></textarea>
          </label>
        </div>

        <div class="adm-grid-full">
          <label class="adm-text-left">Notas internas
            <textarea class="ta" name="notes" id="f_notes" rows="4" placeholder="Notas internas del personaje…"></textarea>
          </label>
        </div>

        <!-- TRAITS -->
        <div class="kind-pj-only adm-grid-full">
          <label><strong>Traits</strong></label>
          <div class="traits-grid" id="traitsDefaultList"></div>
          <div class="grid adm-grid-2-auto">
            <select class="select" id="trait_sel"></select>
            <button class="btn" type="button" id="trait_add">Añadir</button>
          </div>
          <div class="chips" id="traitExtraList"></div>
          <span class="small-note">Se cargan los traits base del sistema; el resto se añaden manualmente. Se guardan en bridge_characters_traits.</span>
        </div>

        <!-- RECURSOS -->
        <div class="kind-pj-only adm-grid-full">
          <label><strong>Recursos</strong></label>
          <div class="grid adm-grid-2-auto">
            <select class="select" id="res_sel"></select>
            <button class="btn" type="button" id="res_add">Añadir</button>
          </div>
          <div class="chips" id="resourceList"></div>
          <span class="small-note">Se guardan en bridge_characters_system_resources. Los recursos por defecto del sistema se vinculan automáticamente.</span>
        </div>

        <!-- PODERES -->
        <div class="kind-pj-only adm-grid-full">
          <label><strong>Poderes</strong></label>
          <div class="grid adm-grid-1-2-120-auto">
            <select class="select" id="pow_tipo">
              <option value="dones">Dones</option>
              <option value="disciplinas">Disciplinas</option>
              <option value="rituales">Rituales</option>
            </select>
            <select class="select" id="pow_poder"></select>
            <input class="inp" id="pow_lvl" type="number" min="0" max="9" value="0" title="Nivel">
            <button class="btn" type="button" id="pow_add">Añadir</button>
          </div>
          <div class="chips" id="powersList"></div>
          <span class="small-note">Las Disciplinas se guardan como tipo de Disciplina + nivel; dones y rituales siguen guardándose como poderes concretos.</span>
        </div>

        <!-- MÉRITOS Y DEFECTOS -->
        <div class="kind-pj-only kind-no-monster adm-grid-full">
          <label><strong>Méritos &amp; Defectos</strong></label>
          <div class="grid adm-grid-2-140-auto">
            <select class="select" id="myd_sel"></select>
            <input class="inp" id="myd_lvl" type="number" min="-99" max="999" placeholder="nivel (opcional)">
            <button class="btn" type="button" id="myd_add">Añadir</button>
          </div>
          <div class="chips" id="mydList"></div>
          <span class="small-note">Nivel vacío = NULL (se usará el coste del mérito/defecto en la hoja).</span>
        </div>

        <!-- INVENTARIO -->
        <div class="kind-pj-only adm-grid-full">
          <label><strong>Inventario</strong></label>
          <div class="grid adm-grid-2-auto">
            <select class="select" id="inv_sel"></select>
            <button class="btn" type="button" id="inv_add">Añadir</button>
          </div>
          <div class="chips" id="invList"></div>
          <span class="small-note">Los objetos listados aquí se guardarán con el personaje.</span>
        </div>

      </div>

      <div class="modal-actions">
        <button type="button" class="btn btn-red" id="btnCancel">Cancelar</button>
        <button type="submit" class="btn btn-green" id="btnSave">Guardar</button>
      </div>
    </form>
  </div>
</div>

<?php
$adminHttpJs = '/assets/js/admin/admin-http.js';
$adminHttpJsVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . $adminHttpJs) ?: time();
$adminCharactersJs = '/assets/js/admin/admin-characters.js';
$adminCharactersJsVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . $adminCharactersJs) ?: time();
$jsBoot = [
  'AJAX_BASE' => $AJAX_BASE,
  'CSRF_TOKEN' => $ADMIN_CSRF_TOKEN,
  'MANADAS_BY_CLAN' => $manadas_by_clan,
  'MANADA_ID_TO_CLAN' => $manadas_map_id_to_clan,
  'RAZAS_BY_SYS' => $razas_by_sys,
  'RAZA_ID_TO_SYS' => $raza_id_to_sys,
  'RAZA_ID_TO_ALLOWED_SYS' => $raza_id_to_allowed_sys,
  'AUSP_BY_SYS' => $ausp_by_sys,
  'AUSP_ID_TO_SYS' => $ausp_id_to_sys,
  'AUSP_ID_TO_ALLOWED_SYS' => $ausp_id_to_allowed_sys,
  'TRIBUS_BY_SYS' => $tribus_by_sys,
  'TRIBU_ID_TO_SYS' => $tribu_id_to_sys,
  'TRIBU_ID_TO_ALLOWED_SYS' => $tribu_id_to_allowed_sys,
  'DONES_OPTS' => array_map(fn($id,$name)=>['id'=>$id,'name'=>$name], array_keys($opts_dones), array_values($opts_dones)),
  'DISC_OPTS' => array_map(fn($id,$name)=>['id'=>$id,'name'=>$name], array_keys($opts_disciplinas), array_values($opts_disciplinas)),
  'RITU_OPTS' => array_map(fn($id,$name)=>['id'=>$id,'name'=>$name], array_keys($opts_rituales), array_values($opts_rituales)),
  'CHAR_POWERS' => $char_powers,
  'MYD_OPTS' => $opts_myd_full,
  'CHAR_MYD' => $char_myd,
  'ITEMS_OPTS' => $opts_items_full,
  'CHAR_ITEMS' => $char_items,
  'RESOURCE_OPTS' => $opts_resources_full,
  'SYS_RESOURCES_BY_SYS' => $sys_resources_by_system,
  'CHAR_RESOURCES' => $char_resources,
  'TRAITS_OPTS' => $traits_catalog,
  'CHAR_TRAITS' => $char_traits,
  'TRAIT_KIND_ORDER' => $trait_kind_order,
  'TRAIT_SET_ORDER' => $trait_set_order,
  'CHAR_DETAILS' => $char_details,
  'DEFAULT_STATUS_ID' => (int)$default_status_id,
];
$jsBootFlags = JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE;
if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
    $jsBootFlags |= JSON_INVALID_UTF8_SUBSTITUTE;
}
?>
<script>
window.ADMIN_CSRF_TOKEN = <?= json_encode($ADMIN_CSRF_TOKEN, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
window.HG_ADMIN_CHARACTERS_BOOT = <?= json_encode($jsBoot, $jsBootFlags); ?>;
</script>
<script src="<?= h($adminHttpJs) ?>?v=<?= (int)$adminHttpJsVer ?>"></script>
<script src="<?= h($adminCharactersJs) ?>?v=<?= (int)$adminCharactersJsVer ?>"></script>
