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

if (!isset($link) || !$link) { die("Error de conexión a la base de datos."); }

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
$estado_opts = [];
$default_status_id = 0;
$has_status_id_col = false;
if ($rsChk = $link->query("SHOW COLUMNS FROM fact_characters LIKE 'status_id'")) {
    $has_status_id_col = ($rsChk->num_rows > 0);
    $rsChk->close();
}
$has_status_dim = false;
if ($rsTbl = $link->query("SHOW TABLES LIKE 'dim_character_status'")) {
    $has_status_dim = ($rsTbl->num_rows > 0);
    $rsTbl->close();
}
if ($has_status_dim) {
  if ($qst = $link->query("SELECT id, label, is_active FROM dim_character_status ORDER BY sort_order ASC, label ASC")) {
    while ($row = $qst->fetch_assoc()) {
      $sid = (int)($row['id'] ?? 0);
      $label = (string)($row['label'] ?? '');
      if ($sid <= 0 || $label === '') continue;
      $estado_opts[$sid] = $label;
      if ((int)($row['is_active'] ?? 0) === 1 && $default_status_id <= 0) {
        $default_status_id = $sid;
      }
    }
    $qst->close();
  }
}
if ($default_status_id <= 0 && !empty($estado_opts)) {
    $firstSid = (int)array_key_first($estado_opts);
    if ($firstSid > 0) {
        $default_status_id = $firstSid;
    }
}

// Estado usado para desactivar en "delete" (soft delete).
$inactive_status_id = 0;
if ($has_status_dim) {
    if ($stIn = $link->prepare("SELECT id, label FROM dim_character_status WHERE is_active=0 ORDER BY sort_order ASC, label ASC LIMIT 1")) {
        $stIn->execute();
        if ($rsIn = $stIn->get_result()) {
            if ($rIn = $rsIn->fetch_assoc()) {
                $inactive_status_id = (int)($rIn['id'] ?? 0);
            }
        }
        $stIn->close();
    }
}
if ($inactive_status_id <= 0) {
    $preferred_inactive = ['inactivo','inactiva','desactivado','desactivada','retirado','retirada','baja','fallecido','fallecida','muerto','muerta'];
    foreach ($estado_opts as $sid => $lbl) {
        $norm = function_exists('mb_strtolower') ? mb_strtolower((string)$lbl, 'UTF-8') : strtolower((string)$lbl);
        if (in_array($norm, $preferred_inactive, true)) {
            $inactive_status_id = (int)$sid;
            break;
        }
    }
}

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
$opts_cronicas = fetchPairs($link, "SELECT id, name FROM dim_chronicles ORDER BY name");
$opts_clanes   = fetchPairs($link, "SELECT id, name FROM dim_organizations ORDER BY name");
$opts_jug      = fetchPairs($link, "SELECT id, name FROM dim_players ORDER BY name");
$opts_sist     = fetchPairs($link, "SELECT id, name FROM dim_systems ORDER BY name");
$opts_totems   = fetchPairs($link, "SELECT id, name FROM dim_totems ORDER BY name");
$opts_afili    = fetchPairs($link, "SELECT id, kind AS name FROM dim_character_types ORDER BY sort_order, kind");
$opts_archetypes = fetchPairs($link, "SELECT id, name FROM dim_archetypes ORDER BY name");
$opts_manadas_flat = fetchPairs($link, "SELECT id, name FROM dim_groups ORDER BY name");

/* --- PODERES: catálogos --- */
$opts_dones        = fetchPairs($link, "SELECT id, CONCAT(name, ' (', gift_group, ')') AS name FROM fact_gifts");
$opts_disciplinas  = fetchPairs($link, "SELECT nd.id, CONCAT(nd.name, ' (', ntd.name, ')') AS name FROM fact_discipline_powers nd LEFT JOIN dim_discipline_types ntd ON nd.disc = ntd.id");
$opts_rituales     = fetchPairs($link, "SELECT nr.id, CONCAT(nr.name, ' (', ntr.name, ')') AS name FROM fact_rites nr LEFT JOIN dim_rite_types ntr ON nr.kind = ntr.id");

/* --- MÉRITOS/DEFECTOS: catálogo completo (para select + chips) --- */
$opts_myd_full = []; // [{id,name,tipo,coste}]
if ($st = $link->prepare("SELECT id, name, kind, cost FROM dim_merits_flaws ORDER BY kind DESC, cost, name")) {
    $st->execute(); $rs = $st->get_result();
    while ($r = $rs->fetch_assoc()) {
        $opts_myd_full[] = [
            'id'    => (int)$r['id'],
            'name'  => (string)$r['name'],
            'tipo'  => (string)$r['kind'],
            'coste' => (string)($r['cost'] ?? ''),
        ];
    }
    $st->close();
}

/* --- INVENTARIO: catálogo --- */
$opts_items_full = []; // [{id,name,tipo}]
if ($st = $link->prepare("SELECT id, name, item_type_id FROM fact_items ORDER BY name")) {
    $st->execute(); $rs = $st->get_result();
    while ($r = $rs->fetch_assoc()) {
        $opts_items_full[] = [
            'id'   => (int)$r['id'],
            'name' => (string)$r['name'],
            'tipo' => (int)($r['item_type_id'] ?? 0),
        ];
    }
    $st->close();
}

/* --- RECURSOS: catálogo + defaults por sistema --- */
$has_dim_systems_resources = pjs_table_exists($link, 'dim_systems_resources');
$has_bridge_systems_resources = pjs_table_exists($link, 'bridge_systems_resources_to_system');
$has_bridge_char_resources = pjs_table_exists($link, 'bridge_characters_system_resources');
$has_bridge_char_resources_log = pjs_table_exists($link, 'bridge_characters_system_resources_log');

$opts_resources_full = [];      // [{id,name,kind,sort_order}]
$resources_by_id = [];          // [id => row]
$sys_resources_by_system = [];  // [system_id => [{id,name,kind,sort_order}]]

if ($has_dim_systems_resources) {
    if ($st = $link->prepare("SELECT id, name, kind, sort_order FROM dim_systems_resources ORDER BY kind, sort_order, name")) {
        $st->execute(); $rs = $st->get_result();
        while ($r = $rs->fetch_assoc()) {
            $row = [
                'id' => (int)$r['id'],
                'name' => (string)$r['name'],
                'kind' => (string)$r['kind'],
                'sort_order' => (int)($r['sort_order'] ?? 0),
            ];
            $opts_resources_full[] = $row;
            $resources_by_id[(int)$r['id']] = $row;
        }
        $st->close();
    }
}

if ($has_bridge_systems_resources && $has_dim_systems_resources) {
    $hasActiveCol = pjs_table_has_column($link, 'bridge_systems_resources_to_system', 'is_active');
    $sqlSysRes = "
        SELECT b.system_id, r.id, r.name, r.kind, r.sort_order
        FROM bridge_systems_resources_to_system b
        INNER JOIN dim_systems_resources r ON r.id = b.resource_id
    ";
    if ($hasActiveCol) $sqlSysRes .= " WHERE b.is_active = 1";
    $sqlSysRes .= " ORDER BY b.system_id, r.kind, r.sort_order, r.name";

    if ($rs = $link->query($sqlSysRes)) {
        while ($r = $rs->fetch_assoc()) {
            $sid = (int)$r['system_id'];
            if (!isset($sys_resources_by_system[$sid])) $sys_resources_by_system[$sid] = [];
            $sys_resources_by_system[$sid][] = [
                'id' => (int)$r['id'],
                'name' => (string)$r['name'],
                'kind' => (string)$r['kind'],
                'sort_order' => (int)($r['sort_order'] ?? 0),
            ];
        }
        $rs->close();
    }
}

/* --- TRAITS: catálogo (todos los tipos) --- */
$traits_by_type = [];
$trait_types = [];
$monster_blocked_trait_ids = [];
$trait_order_fixed = ['Atributos','Talentos','Técnicas','Conocimientos','Trasfondos'];
if ($st = $link->prepare("
    SELECT id, name, kind, classification
    FROM dim_traits
    WHERE kind IS NOT NULL AND TRIM(kind) <> ''
    ORDER BY kind, name
")) {
    $st->execute(); $rs = $st->get_result();
    while ($r = $rs->fetch_assoc()) {
        $kindTrait = (string)$r['kind'];
        $classification = (string)($r['classification'] ?? '');
        if (!isset($traits_by_type[$kindTrait])) {
            $traits_by_type[$kindTrait] = [];
        }
        $traits_by_type[$kindTrait][] = [
            'id'=>(int)$r['id'],
            'name'=>(string)$r['name'],
            'classification'=>$classification,
        ];

        $kindNorm = function_exists('mb_strtolower') ? mb_strtolower($kindTrait, 'UTF-8') : strtolower($kindTrait);
        $classNorm = function_exists('mb_strtolower') ? mb_strtolower($classification, 'UTF-8') : strtolower($classification);
        $kindNorm = str_replace('é', 'e', $kindNorm);
        $isSec = (strpos($classNorm, '002 secundarias') === 0);
        $isBlockedForMonster = ($kindNorm === 'trasfondos')
            || ($isSec && in_array($kindNorm, ['talentos','tecnicas','conocimientos'], true));
        if ($isBlockedForMonster) {
            $monster_blocked_trait_ids[(int)$r['id']] = true;
        }
    }
    $st->close();
}
// Orden fijo + resto al final (alfabético)
$trait_types = $trait_order_fixed;
foreach (array_keys($traits_by_type) as $kind) {
    if (!in_array($kind, $trait_types, true)) $trait_types[] = $kind;
}

/* --- TRAIT SETS: orden por sistema --- */
$trait_set_order = [];
if ($rs = $link->query("SELECT system_id, trait_id, sort_order FROM fact_trait_sets WHERE is_active=1")) {
    while ($r = $rs->fetch_assoc()) {
        $sid = (int)$r['system_id'];
        $tid = (int)$r['trait_id'];
        $ord = (int)$r['sort_order'];
        $trait_set_order[$sid][$tid] = $ord;
    }
    $rs->close();
}

/* -------------------------------------------------
   Sistema -> (Raza, Auspicio, Tribu)
------------------------------------------------- */
// RAZAS
$opts_razas = [];
$razas_by_sys = []; $raza_id_to_sys = []; $raza_id_to_allowed_sys = []; $razas_seen_by_sys = [];
if ($st = $link->prepare("SELECT id, name, system_id FROM dim_breeds ORDER BY system_id, name")) {
    $st->execute(); $rs = $st->get_result();
    while ($r = $rs->fetch_assoc()) {
        $id = (int)$r['id']; $nm = (string)$r['name']; $sys = (int)($r['system_id'] ?? 0);
        $opts_razas[$id] = $nm . ($sys>0 && isset($opts_sist[$sys]) ? ' ('.$opts_sist[$sys].')' : '');
        $raza_id_to_sys[$id] = $sys;
        if ($sys > 0) {
            if (!isset($raza_id_to_allowed_sys[$id])) $raza_id_to_allowed_sys[$id] = [];
            $raza_id_to_allowed_sys[$id][$sys] = true;
        }
        if (!isset($razas_seen_by_sys[$sys][$id])) {
            $razas_by_sys[$sys][] = ['id'=>$id,'name'=>$nm];
            $razas_seen_by_sys[$sys][$id] = true;
        }
    }
    $st->close();
}
$bridgeExtraRazaTable = '';
$bridgeExtraRazaFk = '';
$bridgeExtraRazaCandidates = [
    ['table' => 'bridge_systems_ex_races', 'fk' => 'race_id'],
    ['table' => 'bridge_systems_ex_races', 'fk' => 'breed_id'],
    ['table' => 'bridge_systems_ex_breeds', 'fk' => 'breed_id'],
];
foreach ($bridgeExtraRazaCandidates as $candidate) {
    $table = (string)$candidate['table'];
    $fk = (string)$candidate['fk'];
    if (!pjs_table_exists($link, $table)) continue;
    if (!pjs_table_has_column($link, $table, 'system_id')) continue;
    if (!pjs_table_has_column($link, $table, $fk)) continue;
    $bridgeExtraRazaTable = $table;
    $bridgeExtraRazaFk = $fk;
    break;
}
if ($bridgeExtraRazaTable !== '' && $bridgeExtraRazaFk !== '') {
    $hasActiveCol = pjs_table_has_column($link, $bridgeExtraRazaTable, 'is_active');
    $sqlExtraRaza = "
        SELECT b.system_id AS system_id, d.id AS id, d.name AS name
        FROM {$bridgeExtraRazaTable} b
        INNER JOIN dim_breeds d ON d.id = b.{$bridgeExtraRazaFk}
    ";
    if ($hasActiveCol) $sqlExtraRaza .= " WHERE (b.is_active = 1 OR b.is_active IS NULL)";
    $sqlExtraRaza .= " ORDER BY b.system_id, d.name";
    if ($st = $link->prepare($sqlExtraRaza)) {
        $st->execute(); $rs = $st->get_result();
        while ($r = $rs->fetch_assoc()) {
            $sys = (int)($r['system_id'] ?? 0);
            $id = (int)($r['id'] ?? 0);
            $nm = (string)($r['name'] ?? '');
            if ($sys <= 0 || $id <= 0 || $nm === '') continue;
            if (!isset($raza_id_to_allowed_sys[$id])) $raza_id_to_allowed_sys[$id] = [];
            $raza_id_to_allowed_sys[$id][$sys] = true;
            if (!isset($razas_seen_by_sys[$sys][$id])) {
                $razas_by_sys[$sys][] = ['id'=>$id,'name'=>$nm];
                $razas_seen_by_sys[$sys][$id] = true;
            }
        }
        $st->close();
    }
}
// AUSPICIOS
$opts_ausp = []; $ausp_by_sys = []; $ausp_id_to_sys = []; $ausp_id_to_allowed_sys = []; $ausp_seen_by_sys = [];
if ($st = $link->prepare("SELECT id, name, system_id FROM dim_auspices ORDER BY system_id, name")) {
    $st->execute(); $rs = $st->get_result();
    while ($r = $rs->fetch_assoc()) {
        $id = (int)$r['id']; $nm = (string)$r['name']; $sys = (int)($r['system_id'] ?? 0);
        $opts_ausp[$id] = $nm;
        $ausp_id_to_sys[$id] = $sys;
        if ($sys > 0) {
            if (!isset($ausp_id_to_allowed_sys[$id])) $ausp_id_to_allowed_sys[$id] = [];
            $ausp_id_to_allowed_sys[$id][$sys] = true;
        }
        if (!isset($ausp_seen_by_sys[$sys][$id])) {
            $ausp_by_sys[$sys][] = ['id'=>$id,'name'=>$nm];
            $ausp_seen_by_sys[$sys][$id] = true;
        }
    }
    $st->close();
} else {
    $opts_ausp = fetchPairs($link, "SELECT id, name FROM dim_auspices ORDER BY name");
}
$bridgeExtraAuspTable = '';
$bridgeExtraAuspFk = '';
$bridgeExtraAuspCandidates = [
    ['table' => 'bridge_systems_ex_auspices', 'fk' => 'auspice_id'],
];
foreach ($bridgeExtraAuspCandidates as $candidate) {
    $table = (string)$candidate['table'];
    $fk = (string)$candidate['fk'];
    if (!pjs_table_exists($link, $table)) continue;
    if (!pjs_table_has_column($link, $table, 'system_id')) continue;
    if (!pjs_table_has_column($link, $table, $fk)) continue;
    $bridgeExtraAuspTable = $table;
    $bridgeExtraAuspFk = $fk;
    break;
}
if ($bridgeExtraAuspTable !== '' && $bridgeExtraAuspFk !== '') {
    $hasActiveCol = pjs_table_has_column($link, $bridgeExtraAuspTable, 'is_active');
    $sqlExtraAusp = "
        SELECT b.system_id AS system_id, d.id AS id, d.name AS name
        FROM {$bridgeExtraAuspTable} b
        INNER JOIN dim_auspices d ON d.id = b.{$bridgeExtraAuspFk}
    ";
    if ($hasActiveCol) $sqlExtraAusp .= " WHERE (b.is_active = 1 OR b.is_active IS NULL)";
    $sqlExtraAusp .= " ORDER BY b.system_id, d.name";
    if ($st = $link->prepare($sqlExtraAusp)) {
        $st->execute(); $rs = $st->get_result();
        while ($r = $rs->fetch_assoc()) {
            $sys = (int)($r['system_id'] ?? 0);
            $id = (int)($r['id'] ?? 0);
            $nm = (string)($r['name'] ?? '');
            if ($sys <= 0 || $id <= 0 || $nm === '') continue;
            if (!isset($ausp_id_to_allowed_sys[$id])) $ausp_id_to_allowed_sys[$id] = [];
            $ausp_id_to_allowed_sys[$id][$sys] = true;
            if (!isset($ausp_seen_by_sys[$sys][$id])) {
                $ausp_by_sys[$sys][] = ['id'=>$id,'name'=>$nm];
                $ausp_seen_by_sys[$sys][$id] = true;
            }
        }
        $st->close();
    }
}
// TRIBUS
$opts_tribus = []; $tribus_by_sys = []; $tribu_id_to_sys = []; $tribu_id_to_allowed_sys = []; $tribus_seen_by_sys = [];
if ($st = $link->prepare("SELECT id, name, system_id FROM dim_tribes ORDER BY system_id, name")) {
    $st->execute(); $rs = $st->get_result();
    while ($r = $rs->fetch_assoc()) {
        $id = (int)$r['id']; $nm = (string)$r['name']; $sys = (int)($r['system_id'] ?? 0);
        $opts_tribus[$id] = $nm;
        $tribu_id_to_sys[$id] = $sys;
        if ($sys > 0) {
            if (!isset($tribu_id_to_allowed_sys[$id])) $tribu_id_to_allowed_sys[$id] = [];
            $tribu_id_to_allowed_sys[$id][$sys] = true;
        }
        if (!isset($tribus_seen_by_sys[$sys][$id])) {
            $tribus_by_sys[$sys][] = ['id'=>$id,'name'=>$nm];
            $tribus_seen_by_sys[$sys][$id] = true;
        }
    }
    $st->close();
} else {
    $opts_tribus = fetchPairs($link, "SELECT id, name FROM dim_tribes ORDER BY name");
}
$bridgeExtraTribuTable = '';
$bridgeExtraTribuFk = '';
$bridgeExtraTribuCandidates = [
    ['table' => 'bridge_systems_ex_tribes', 'fk' => 'tribe_id'],
];
foreach ($bridgeExtraTribuCandidates as $candidate) {
    $table = (string)$candidate['table'];
    $fk = (string)$candidate['fk'];
    if (!pjs_table_exists($link, $table)) continue;
    if (!pjs_table_has_column($link, $table, 'system_id')) continue;
    if (!pjs_table_has_column($link, $table, $fk)) continue;
    $bridgeExtraTribuTable = $table;
    $bridgeExtraTribuFk = $fk;
    break;
}
if ($bridgeExtraTribuTable !== '' && $bridgeExtraTribuFk !== '') {
    $hasActiveCol = pjs_table_has_column($link, $bridgeExtraTribuTable, 'is_active');
    $sqlExtraTribu = "
        SELECT b.system_id AS system_id, d.id AS id, d.name AS name
        FROM {$bridgeExtraTribuTable} b
        INNER JOIN dim_tribes d ON d.id = b.{$bridgeExtraTribuFk}
    ";
    if ($hasActiveCol) $sqlExtraTribu .= " WHERE (b.is_active = 1 OR b.is_active IS NULL)";
    $sqlExtraTribu .= " ORDER BY b.system_id, d.name";
    if ($st = $link->prepare($sqlExtraTribu)) {
        $st->execute(); $rs = $st->get_result();
        while ($r = $rs->fetch_assoc()) {
            $sys = (int)($r['system_id'] ?? 0);
            $id = (int)($r['id'] ?? 0);
            $nm = (string)($r['name'] ?? '');
            if ($sys <= 0 || $id <= 0 || $nm === '') continue;
            if (!isset($tribu_id_to_allowed_sys[$id])) $tribu_id_to_allowed_sys[$id] = [];
            $tribu_id_to_allowed_sys[$id][$sys] = true;
            if (!isset($tribus_seen_by_sys[$sys][$id])) {
                $tribus_by_sys[$sys][] = ['id'=>$id,'name'=>$nm];
                $tribus_seen_by_sys[$sys][$id] = true;
            }
        }
        $st->close();
    }
}

/* -------------------------------------------------
   MAPAS Clan->Manadas (por BRIDGE bridge_organizations_groups)
------------------------------------------------- */
$manadas_map_id_to_clan = [];
$manadas_by_clan        = [];

$sqlMap = "
    SELECT
        b.group_id AS manada_id,
        m.name     AS manada_name,
        b.organization_id  AS organization_id
    FROM bridge_organizations_groups b
    INNER JOIN dim_groups m ON m.id = b.group_id
    INNER JOIN dim_organizations  c ON c.id = b.organization_id
    WHERE (b.is_active = 1 OR b.is_active IS NULL)
    ORDER BY b.organization_id, m.name
";
if ($stmtM = $link->prepare($sqlMap)) {
    $stmtM->execute();
    $resM = $stmtM->get_result();
    while ($row = $resM->fetch_assoc()) {
        $mid = (int)$row['manada_id'];
        $cid = (int)$row['organization_id'];
        $manadas_map_id_to_clan[$mid] = $cid;
        $manadas_by_clan[$cid][] = ['id'=>$mid, 'name'=>$row['manada_name']];
    }
    $stmtM->close();
}

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
    $nature_id      = max(0, intval($_POST['nature_id'] ?? 0));
    $demeanor_id    = max(0, intval($_POST['demeanor_id'] ?? 0));
    $manada      = max(0, intval($_POST['manada'] ?? 0));
    $clan        = max(0, intval($_POST['clan'] ?? 0));
    $system_id   = isset($_POST['system_id']) ? (int)$_POST['system_id'] : 0;
    $totem_id = isset($_POST['totem_id']) ? (int)$_POST['totem_id'] : 0;
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
    $cumple      = trim($_POST['cumple'] ?? '');
    $rango       = trim($_POST['rango'] ?? '');
    $infotext    = trim($_POST['infotext'] ?? '');
    $infotext    = hg_mentions_convert($link, $infotext);

    $notas       = '';

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
    $traits_raw = isset($_POST['traits']) && is_array($_POST['traits']) ? $_POST['traits'] : [];
    $traits = [];
    foreach ($traits_raw as $tid => $val) {
        $tid = (int)$tid;
        if ($tid <= 0) continue;
        $v = is_string($val) ? trim($val) : $val;
        if ($v === '' || $v === null) { $v = 0; }
        $v = (int)$v;
        if ($v < 0) $v = 0;
        if ($v > 10) $v = 10;
        $traits[$tid] = $v;
    }
    // Filtrar: solo traits por defecto del sistema + traits con valor > 0
    $default_trait_ids = [];
    if ($system_id > 0 && isset($trait_set_order[$system_id])) {
        $default_trait_ids = array_keys($trait_set_order[$system_id]);
    }
    if (!empty($traits)) {
        $filtered = [];
        foreach ($traits as $tid => $v) {
            if ($v > 0 || in_array($tid, $default_trait_ids, true)) {
                $filtered[$tid] = $v;
            }
        }
        $traits = $filtered;
    }
    if ($isMonsterKind && !empty($traits) && !empty($monster_blocked_trait_ids)) {
        foreach (array_keys($monster_blocked_trait_ids) as $blockedTid) {
            unset($traits[(int)$blockedTid]);
        }
    }

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
    if ($status_id <= 0) $flash[] = ['type'=>'error','msg'=>'? El status no es válido.'];
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

    // Totem: si no elige, hereda de manada o clan
    if ($totem_id <= 0) {
        $totem_from_group = 0;
        if ($manada > 0) {
            if ($st = $link->prepare("SELECT totem_id FROM dim_groups WHERE id=? LIMIT 1")) {
                $st->bind_param("i", $manada);
                $st->execute();
                if ($rs = $st->get_result()) { if ($row = $rs->fetch_assoc()) { $totem_from_group = (int)($row['totem_id'] ?? 0); } }
                $st->close();
            }
        }
        $totem_from_clan = 0;
        if ($totem_from_group <= 0 && $clan > 0) {
            if ($st = $link->prepare("SELECT totem_id FROM dim_organizations WHERE id=? LIMIT 1")) {
                $st->bind_param("i", $clan);
                $st->execute();
                if ($rs = $st->get_result()) { if ($row = $rs->fetch_assoc()) { $totem_from_clan = (int)($row['totem_id'] ?? 0); } }
                $st->close();
            }
        }
        $totem_id = $totem_from_group > 0 ? $totem_from_group : $totem_from_clan;
    }
    if (!($totem_id > 0 && isset($opts_totems[$totem_id]))) {
        $totem_id = null; // NULL para evitar FK con 0
    }

    // Avatar actual (para update/delete) + existencia
    $current_img = '';
    $character_exists = false;
    if (($action === 'update' || $action === 'delete') && $id > 0) {
        if ($st = $link->prepare("SELECT image_url FROM fact_characters WHERE id=?")) {
            $st->bind_param("i",$id); $st->execute();
            $rs = $st->get_result();
            if ($row = $rs->fetch_assoc()) {
                $character_exists = true;
                $current_img = (string)($row['image_url'] ?? '');
            }
            $st->close();
        }
    }

    if ($action === 'create') {
        if ($nombre === '') $flash[] = ['type'=>'error','msg'=>'[WARN] El campo \"nombre\" es obligatorio.'];
        if (!array_filter($flash, fn($f)=>$f['type']==='error')) {
            $sql = "INSERT INTO fact_characters
                (name, alias, garou_name, gender, concept, chronicle_id, player_id, character_type_id, image_url, notes, text_color, `$character_kind_column`, system_id,
                 totem_id, status_id, birthdate_text, rank, info_text, breed_id, auspice_id, tribe_id, nature_id, demeanor_id)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
            if ($stmt = $link->prepare($sql)) {
                $img='';
                $stmt->bind_param(
                    "sssssiiissssiiisssiiiii",
                    $nombre, $alias, $nombregarou, $gender, $concept,
                    $cronica, $jugador, $afili,
                    $img, $notas, $text_color, $kind, $system_id,
                    $totem_id,
                    $status_id, $cumple, $rango, $infotext,
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
                            if ($st2 = $link->prepare("UPDATE fact_characters SET image_url=? WHERE id=?")) {
                                $st2->bind_param("si", $res['url'], $newId);
                                $st2->execute(); $st2->close();
                            }
                            $flash[] = ['type'=>'ok','msg'=>'Avatar subido.'];
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

                    
                    // Traits
                    $resultTr = save_character_traits($link, (int)$newId, $traits, 'admin', null);
                    if ($resultTr['updated']>0) { $flash[]=['type'=>'ok','msg'=>'Traits guardados: '.$resultTr['updated']]; }

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
                  status_id=?, birthdate_text=?, rank=?, info_text=?
                  WHERE id=?";

          if ($stmt = $link->prepare($sql)) {

              // 13 strings/ints + 5 strings + id (int)
              $stmt->bind_param(
                  "sssssiiiissiiiiiissssi",
                  $nombre, $alias, $nombregarou, $gender, $concept,
                  $cronica, $jugador, $afili, $system_id, $text_color,
                  $kind,
                  $raza, $auspice_id, $tribe_id, $nature_id, $demeanor_id,
                  $totem_id,
                  $status_id, $cumple, $rango, $infotext,
                  $id
              );

              if ($stmt->execute()) {
                  $saved_character_id = (int)$id;
                  hg_update_pretty_id_if_exists($link, 'fact_characters', $id, $nombre);

                  // Avatar
                  if ($rm_avatar && $current_img) {
                      safe_unlink_avatar($current_img, $AV_UPLOADDIR);
                      if ($st2 = $link->prepare("UPDATE fact_characters SET image_url='' WHERE id=?")) {
                          $st2->bind_param("i",$id);
                          $st2->execute();
                          $st2->close();
                      }
                      $flash[] = ['type'=>'ok','msg'=>'Avatar eliminado.'];
                      $current_img = '';
                  }

                  if (!empty($_FILES['avatar']) && ($_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                      $res = save_avatar_file($_FILES['avatar'], $id, $nombre, $AV_UPLOADDIR, $AV_URLBASE);
                      if ($res['ok']) {
                          if ($current_img) safe_unlink_avatar($current_img, $AV_UPLOADDIR);
                          if ($st3 = $link->prepare("UPDATE fact_characters SET image_url=? WHERE id=?")) {
                              $st3->bind_param("si", $res['url'], $id);
                              $st3->execute();
                              $st3->close();
                          }
                          $flash[] = ['type'=>'ok','msg'=>'Avatar actualizado.'];
                      } elseif ($res['msg']!=='no_file') {
                          $flash[] = ['type'=>'error','msg'=>'[WARN] Avatar no guardado: '.$res['msg']];
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

                  
                  // Traits
                  $resultTr = save_character_traits($link, (int)$id, $traits, 'admin', null);
                  if ($resultTr['updated']>0) { $flash[]=['type'=>'ok','msg'=>'Traits guardados: '.$resultTr['updated']]; }

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
                  if ($stmt = $link->prepare("UPDATE fact_characters SET status_id=? WHERE id=?")) {
                      $status_id_to_set = (int)$inactive_status_id;
                      $stmt->bind_param("ii", $status_id_to_set, $id);
                      $okDelete = (bool)$stmt->execute();
                      $stmt->close();
                  }
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
$where = "WHERE 1=1"; $params = []; $types = "";
if ($fil_cr > 0) { $where .= " AND p.chronicle_id = ?"; $types .= "i"; $params[] = $fil_cr; }
if ($fil_ma > 0) { $where .= " AND pgb.group_id = ?"; $types .= "i"; $params[] = $fil_ma; }
if ($q !== '')   { $where .= " AND p.name LIKE ?"; $types .= "s"; $params[] = "%".$q."%"; }

$sqlCnt = "
  SELECT COUNT(*) AS c
  FROM fact_characters p
  LEFT JOIN (
      SELECT character_id, MIN(group_id) AS group_id
      FROM bridge_characters_groups
      WHERE (is_active=1 OR is_active IS NULL)
      GROUP BY character_id
  ) pgb ON pgb.character_id = p.id
  LEFT JOIN (
      SELECT character_id, MIN(organization_id) AS organization_id
      FROM bridge_characters_organizations
      WHERE (is_active=1 OR is_active IS NULL)
      GROUP BY character_id
  ) pcb ON pcb.character_id = p.id
  $where
";

$stmtC = $link->prepare($sqlCnt);
if ($types) { $stmtC->bind_param($types, ...$params); }
$stmtC->execute();
$resC = $stmtC->get_result();
$total = ($resC && ($rowC = $resC->fetch_assoc())) ? intval($rowC['c']) : 0;
$stmtC->close();

$pages = max(1, (int)ceil($total / $perPage));
$page  = min($page, $pages);
$offset= ($page - 1) * $perPage;

$sql = "
SELECT
  p.id, p.name, p.alias, p.garou_name, p.gender, p.concept,
  p.chronicle_id, p.player_id, p.system_id, p.text_color,
  p.breed_id, p.auspice_id, p.tribe_id, p.nature_id, p.demeanor_id,
  -- [OK] IDs desde bridge (para el modal y coherencia)
  COALESCE(pgb.group_id, 0) AS manada,
  COALESCE(pcb.organization_id, 0)  AS clan,
  p.image_url, p.character_type_id, p.totem_id, p.`$character_kind_column` AS kind,

  nj.name AS jugador_,
  nc.name AS cronica_,
  nr.name AS raza_n,
  na.name AS auspicio_n,
  nt.name AS tribu_n,
  ds.name AS sistema_n,
  dt.name AS totem_n,

  nm.name AS manada_n,
  nc2.name AS clan_n,
  af.kind AS tipo_n

FROM fact_characters p

LEFT JOIN (
    SELECT character_id, MIN(group_id) AS group_id
    FROM bridge_characters_groups
    WHERE (is_active=1 OR is_active IS NULL)
    GROUP BY character_id
) pgb ON pgb.character_id = p.id

LEFT JOIN (
    SELECT character_id, MIN(organization_id) AS organization_id
    FROM bridge_characters_organizations
    WHERE (is_active=1 OR is_active IS NULL)
    GROUP BY character_id
) pcb ON pcb.character_id = p.id

LEFT JOIN dim_players  nj ON p.player_id = nj.id
LEFT JOIN dim_chronicles  nc ON p.chronicle_id = nc.id
LEFT JOIN dim_systems     ds ON p.system_id = ds.id
LEFT JOIN dim_totems      dt ON p.totem_id = dt.id
LEFT JOIN dim_breeds      nr ON p.breed_id    = nr.id
LEFT JOIN dim_auspices  na ON p.auspice_id= na.id
LEFT JOIN dim_tribes     nt ON p.tribe_id   = nt.id

-- [OK] Nombres desde ids bridge
LEFT JOIN dim_groups   nm  ON nm.id  = pgb.group_id
LEFT JOIN dim_organizations    nc2 ON nc2.id = pcb.organization_id

LEFT JOIN dim_character_types af ON p.character_type_id  = af.id

$where
ORDER BY p.name ASC
LIMIT ?, ?";

$typesPage = $types."ii";
$paramsPage = $params; $paramsPage[] = $offset; $paramsPage[] = $perPage;
$stmt = $link->prepare($sql);

if ($stmt === false) {
    die(
        "<pre>SQL PREPARE ERROR:\n" .
        $link->errno . " — " . $link->error .
        "\n\nSQL:\n" . $sql .
        "</pre>"
    );
}

$stmt->bind_param($typesPage, ...$paramsPage);
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
$ids_page = [];
while ($r = $res->fetch_assoc()) { $rows[] = $r; $ids_page[] = (int)$r['id']; }
$stmt->close();

/* --- CAMPOS COMPLEJOS: precarga (SIN AJAX) --- */
$char_details = [];
if (!empty($ids_page)) {
    $in = implode(',', array_map('intval', $ids_page));
    $qdet = $link->query("SELECT fc.id, COALESCE(dcs.label, '') AS status, fc.status_id, fc.birthdate_text, fc.rank, fc.info_text FROM fact_characters fc LEFT JOIN dim_character_status dcs ON dcs.id = fc.status_id WHERE fc.id IN ($in)");
    if ($qdet) {
        while ($d = $qdet->fetch_assoc()) {
            $cid = (int)($d['id'] ?? 0);
            if ($cid <= 0) continue;
            $char_details[$cid] = [
                'status'      => (string)($d['status'] ?? ''),
                'status_id'   => (int)($d['status_id'] ?? 0),
                'causamuerte' => '',
                'cumple'      => (string)($d['birthdate_text'] ?? ''),
                'rango'       => (string)($d['rank'] ?? ''),
                'infotext'    => (string)($d['info_text'] ?? ''),
            ];
        }
        $qdet->close();
    }
}

/* --- PODERES: precarga poderes --- */
$char_powers = [];
if (!empty($ids_page)) {
    $in = implode(',', array_map('intval',$ids_page));
    $qpow = $link->query("SELECT character_id, power_kind, power_id, power_level FROM bridge_characters_powers WHERE character_id IN ($in) ORDER BY power_kind, power_id");
    if ($qpow) {
        while($pw = $qpow->fetch_assoc()){
            $cid = (int)$pw['character_id'];
            $tp  = (string)$pw['power_kind'];
            $pid = (int)$pw['power_id'];
            $lvl = (int)$pw['power_level'];
            if ($tp==='dones')          { $nm = $opts_dones[$pid]        ?? ('#'.$pid); }
            elseif ($tp==='disciplinas'){ $nm = $opts_disciplinas[$pid]  ?? ('#'.$pid); }
            else                        { $nm = $opts_rituales[$pid]     ?? ('#'.$pid); }
            $char_powers[$cid][] = ['t'=>$tp,'id'=>$pid,'lvl'=>$lvl,'name'=>$nm];
        }
        $qpow->close();
    }
}

/* --- MÉRITOS/DEFECTOS: precarga --- */
$char_myd = [];
if (!empty($ids_page)) {
    $in = implode(',', array_map('intval',$ids_page));
    $qmyd = $link->query("
        SELECT b.character_id, nmd.id, nmd.name, nmd.kind, nmd.cost, b.level
        FROM bridge_characters_merits_flaws b
        JOIN dim_merits_flaws nmd ON nmd.id = b.merit_flaw_id
        WHERE b.character_id IN ($in)
        ORDER BY nmd.kind DESC, nmd.cost, nmd.name
    ");
    if ($qmyd) {
        while($r = $qmyd->fetch_assoc()){
            $cid = (int)$r['character_id'];
            $char_myd[$cid][] = [
                'id'    => (int)$r['id'],
                'name'  => (string)$r['name'],
                'tipo'  => (string)$r['kind'],
                'coste' => (string)($r['cost'] ?? ''),
                'nivel' => $r['level'] === null ? null : (int)$r['level'],
            ];
        }
        $qmyd->close();
    }
}

/* --- INVENTARIO: precarga --- */
$char_items = [];
if (!empty($ids_page)) {
    $in = implode(',', array_map('intval',$ids_page));
    $qit = $link->query("
        SELECT b.character_id, o.id, o.name, o.item_type_id
        FROM bridge_characters_items b
        JOIN fact_items o ON o.id = b.item_id
        WHERE b.character_id IN ($in)
        ORDER BY o.name
    ");
    if ($qit) {
        while($r = $qit->fetch_assoc()){
            $cid = (int)$r['character_id'];
            $char_items[$cid][] = [
                'id'   => (int)$r['id'],
                'name' => (string)$r['name'],
                'tipo' => (int)($r['item_type_id'] ?? 0),
            ];
        }
        $qit->close();
    }
}

/* --- TRAITS: precarga --- */
$char_traits = [];
if (!empty($ids_page)) {
    $in = implode(',', array_map('intval',$ids_page));
    $qtr = $link->query("
        SELECT character_id, trait_id, value
        FROM bridge_characters_traits
        WHERE character_id IN ($in)
        ORDER BY character_id, trait_id
    ");
    if ($qtr) {
        while ($r = $qtr->fetch_assoc()) {
            $cid = (int)$r['character_id'];
            $tid = (int)$r['trait_id'];
            $val = (int)$r['value'];
            $char_traits[$cid][$tid] = $val;
        }
        $qtr->close();
    }
}

/* --- RECURSOS: precarga --- */
$char_resources = [];
if ($has_bridge_char_resources && $has_dim_systems_resources && !empty($ids_page)) {
    $in = implode(',', array_map('intval', $ids_page));
    $qrs = $link->query("
        SELECT b.character_id, b.resource_id, b.value_permanent, b.value_temporary, r.name, r.kind, r.sort_order
        FROM bridge_characters_system_resources b
        INNER JOIN dim_systems_resources r ON r.id = b.resource_id
        WHERE b.character_id IN ($in)
        ORDER BY b.character_id, r.kind, r.sort_order, r.name
    ");
    if ($qrs) {
        while ($r = $qrs->fetch_assoc()) {
            $cid = (int)$r['character_id'];
            $char_resources[$cid][] = [
                'id' => (int)$r['resource_id'],
                'name' => (string)$r['name'],
                'kind' => (string)$r['kind'],
                'sort_order' => (int)($r['sort_order'] ?? 0),
                'perm' => (int)($r['value_permanent'] ?? 0),
                'temp' => (int)($r['value_temporary'] ?? 0),
            ];
        }
        $qrs->close();
    }
}

// Base AJAX (misma página)
$AJAX_BASE = "/talim?s=admin_characters&ajax=1";
?>

<br />
<div class="panel-wrap">
  <div class="hdr">
    <h2>Personajes - Lista y CRUD</h2>
    <button class="btn btn-green" id="btnNew">+ Nuevo personaje</button>

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
          <label>Cumpleaños <span class="small-note">(ej: 1990-05-21)</span>
            <input class="inp" type="text" name="cumple" id="f_cumple" placeholder="YYYY-MM-DD">
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
              <option value="0">— Sin tótem —</option>
              <?php foreach($opts_totems as $id=>$name): ?>
                <option value="<?= (int)$id ?>"><?= h($name) ?></option>
              <?php endforeach; ?>
            </select>
            <span class="small-note">Si no eliges, se usa el tótem de la Manada o del Clan</span>
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
                <input class="inp" type="file" name="avatar" id="f_avatar" accept="image/*">
                <label class="small-note"><input type="checkbox" name="avatar_remove" id="f_avatar_remove" value="1"> Quitar avatar</label>
                <span class="small-note">JPG/PNG/GIF/WebP · máx. 5 MB</span>
              </div>
            </div>
          </label>
        </div>

        <div class="adm-grid-full">
          <label class="adm-text-left">Información sobre el personaje
            <textarea class="ta hg-mention-input" data-mentions="character,season,episode,organization,group,gift,rite,totem,discipline,item,trait,background,merit,flaw,merydef,doc" name="infotext" id="f_infotext" rows="6" placeholder="Texto largo…"></textarea>
          </label>
        </div>

        <!-- TRAITS -->
        <div class="kind-pj-only adm-grid-full">
          <label><strong>Traits</strong></label>
          <div class="traits-grid">
            <?php foreach ($trait_types as $tipo): $list = $traits_by_type[$tipo] ?? []; if (!$list) continue; ?>
              <div class="traits-group">
                <div class="traits-title"><?= h($tipo) ?></div>
                <div class="traits-items">
                  <?php foreach ($list as $t): ?>
                    <label class="trait-item"
                           data-trait-name="<?= h($t['name']) ?>"
                           data-trait-kind="<?= h($tipo) ?>"
                           data-trait-classification="<?= h((string)($t['classification'] ?? '')) ?>">
                      <span><?= h($t['name']) ?></span>
                      <input class="inp trait-input" type="number" min="0" max="10" name="traits[<?= (int)$t['id'] ?>]" data-trait-id="<?= (int)$t['id'] ?>" value="0">
                    </label>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <span class="small-note">Se guardan en bridge_characters_traits.</span>
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
          <span class="small-note">Los poderes listados aquí se guardarán con el personaje.</span>
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
  'CHAR_TRAITS' => $char_traits,
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










