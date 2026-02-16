<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<?php include_once(__DIR__ . '/../../partials/admin/mentions_includes.php'); ?>

<style>
/* Ajustes Select2 para tu tema oscuro */
.select2-container{ width:100% !important; font-size:12px; }
.select2-container--default .select2-selection--single{
  background:#000033; border:1px solid #333; color:#fff;
  height:28px; border-radius:6px;
}
.select2-container--default .select2-selection--single .select2-selection__rendered{
  color:#fff; line-height:26px;
}
.select2-container--default .select2-selection--single .select2-selection__placeholder{ color:#9dd; }
.select2-container--default .select2-selection--single .select2-selection__arrow{ height:26px; }

.select2-dropdown{
  background:#000033; border:1px solid #333; color:#fff;
  box-sizing:border-box;
}
.select2-results__option{ color:#fff; }
.select2-container--default .select2-results__option--highlighted.select2-results__option--selectable{
  background:#001199;
}
.select2-results>.select2-results__options{ max-height:260px; } /* evita dropdown infinito */
.select2-container--open{ z-index: 20000; } /* por encima de tu modal-back (9999) */
</style>

<?php
// admin_pjs.php — CRUD Personajes (Clan→Manada + Sistema→Raza/Auspicio/Tribu + Avatar + Afiliación + Poderes + Méritos/Defectos + Inventario + Campos complejos)

if (!isset($link) || !$link) { die("Error de conexión a la base de datos."); }

// ⚙️ MUY IMPORTANTE: asegura que MySQLi entregue UTF-8 real (evita JSON roto)
if (method_exists($link, 'set_charset')) {
    $link->set_charset('utf8mb4');
} else {
    mysqli_set_charset($link, 'utf8mb4');
}

include_once(__DIR__ . '/../../helpers/mentions.php');

include_once(__DIR__ . '/../../helpers/pretty.php');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* -------------------------------------------------
   Helpers (texto “complejo”)
------------------------------------------------- */
function preview_text(string $s, int $len = 180): string {
  $s = strip_tags($s);
  $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
  $s = preg_replace('/\s+/u', ' ', $s);
  $s = trim($s);
  if (function_exists('mb_strlen') && mb_strlen($s, 'UTF-8') > $len) $s = mb_substr($s, 0, $len - 1, 'UTF-8') . '…';
  return $s;
}

/* -------------------------------------------------
   Subidas (avatar): rutas y utilidades
------------------------------------------------- */
$DOCROOT      = rtrim($_SERVER['DOCUMENT_ROOT'] ?? __DIR__, '/');
$AV_UPLOADDIR = $DOCROOT . '/img/characters';
$AV_URLBASE   = '/img/characters';
if (!is_dir($AV_UPLOADDIR)) { @mkdir($AV_UPLOADDIR, 0775, true); }

function slugify($text){
    $text = trim((string)$text);
    if (function_exists('iconv')) { $text = @iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$text); }
    $text = preg_replace('~[^\\pL\\d]+~u','-',$text);
    $text = trim($text,'-');
    $text = strtolower($text);
    $text = preg_replace('~[^-a-z0-9]+~','',$text);
    return $text ?: 'pj';
}
function save_avatar_file(array $file, int $pjId, string $displayName, string $uploadDir, string $urlBase){
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) return ['ok'=>false,'msg'=>'no_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) return ['ok'=>false,'msg'=>'Error de subida (#'.$file['error'].')'];
    if ($file['size'] > 5*1024*1024)     return ['ok'=>false,'msg'=>'El archivo supera 5 MB'];
    $tmp = $file['tmp_name'];
    if (!is_uploaded_file($tmp))         return ['ok'=>false,'msg'=>'Subida no válida'];

    $mime = '';
    if (function_exists('finfo_open')) { $fi = finfo_open(FILEINFO_MIME_TYPE); if ($fi) { $mime = finfo_file($fi, $tmp); finfo_close($fi); } }
    if (!$mime) { $gi = @getimagesize($tmp); $mime = $gi['mime'] ?? ''; }

    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
    if (!isset($allowed[$mime])) return ['ok'=>false,'msg'=>'Formato no permitido (JPG/PNG/GIF/WebP)'];

    $ext  = $allowed[$mime];
    $slug = slugify($displayName ?: 'pj');
    $name = sprintf('pj-%d-%s-%s.%s', $pjId, $slug, date('YmdHis'), $ext);
    $dst  = rtrim($uploadDir,'/').'/'.$name;

    if (!@move_uploaded_file($tmp, $dst)) return ['ok'=>false,'msg'=>'No se pudo mover el archivo subido'];
    @chmod($dst, 0644);
    return ['ok'=>true,'url'=>rtrim($urlBase,'/').'/'.$name, 'path'=>$dst];
}
function safe_unlink_avatar(string $relUrl, string $uploadDir){
    if ($relUrl==='') return;
    $abs = rtrim($_SERVER['DOCUMENT_ROOT'] ?? __DIR__,'/').'/'.ltrim($relUrl,'/');
    $base= realpath($uploadDir);
    $absr= @realpath($abs);
    if ($absr && $base && strpos($absr,$base)===0 && is_file($absr)) { @unlink($absr); }
}

/**
 * Sincroniza bridges de pertenencia al guardar un PJ (manada/clan).
 */
/**
 * Sincroniza bridges de pertenencia al guardar un PJ (manada/clan).
 * - Siempre deja activo el clan (si clanId > 0)
 * - Siempre deja activa la manada (si groupId > 0)
 * - Desactiva el resto de registros del personaje en cada bridge
 */
function sync_character_bridges(mysqli $link, int $charId, int $groupId, int $clanId): void {

    // -------------------------
    // 1) GROUP BRIDGE (manada)
    // -------------------------
    if ($groupId > 0) {
        // upsert "activo" para (charId, groupId)
        if ($st = $link->prepare("SELECT id FROM bridge_characters_groups WHERE character_id=? AND group_id=? LIMIT 1")) {
            $st->bind_param("ii", $charId, $groupId);
            $st->execute();
            $rs = $st->get_result();

            if ($rs && ($row = $rs->fetch_assoc())) {
                $idrow = (int)$row['id'];
                if ($st2 = $link->prepare("UPDATE bridge_characters_groups SET is_active=1 WHERE id=?")) {
                    $st2->bind_param("i", $idrow);
                    $st2->execute();
                    $st2->close();
                }
            } else {
                if ($st2 = $link->prepare("INSERT INTO bridge_characters_groups (character_id,group_id,is_active) VALUES (?,?,1)")) {
                    $st2->bind_param("ii", $charId, $groupId);
                    $st2->execute();
                    $st2->close();
                }
            }
            $st->close();
        }

        // desactivar otras manadas del PJ
        if ($st = $link->prepare("UPDATE bridge_characters_groups SET is_active=0 WHERE character_id=? AND group_id<>?")) {
            $st->bind_param("ii", $charId, $groupId);
            $st->execute();
            $st->close();
        }
    } else {
        // si no hay manada, desactiva todas
        if ($st = $link->prepare("UPDATE bridge_characters_groups SET is_active=0 WHERE character_id=?")) {
            $st->bind_param("i", $charId);
            $st->execute();
            $st->close();
        }
    }

    // -------------------------
    // 2) CLAN BRIDGE
    // -------------------------
    if ($clanId > 0) {
        // upsert "activo" para (charId, clanId)
        if ($st = $link->prepare("SELECT id FROM bridge_characters_organizations WHERE character_id=? AND clan_id=? LIMIT 1")) {
            $st->bind_param("ii", $charId, $clanId);
            $st->execute();
            $rs = $st->get_result();

            if ($rs && ($row = $rs->fetch_assoc())) {
                $idrow = (int)$row['id'];
                if ($st2 = $link->prepare("UPDATE bridge_characters_organizations SET is_active=1 WHERE id=?")) {
                    $st2->bind_param("i", $idrow);
                    $st2->execute();
                    $st2->close();
                }
            } else {
                if ($st2 = $link->prepare("INSERT INTO bridge_characters_organizations (character_id,clan_id,is_active) VALUES (?,?,1)")) {
                    $st2->bind_param("ii", $charId, $clanId);
                    $st2->execute();
                    $st2->close();
                }
            }
            $st->close();
        }

        // desactivar otros clanes del PJ
        if ($st = $link->prepare("UPDATE bridge_characters_organizations SET is_active=0 WHERE character_id=? AND clan_id<>?")) {
            $st->bind_param("ii", $charId, $clanId);
            $st->execute();
            $st->close();
        }
    } else {
        // si no hay clan, desactiva todos
        if ($st = $link->prepare("UPDATE bridge_characters_organizations SET is_active=0 WHERE character_id=?")) {
            $st->bind_param("i", $charId);
            $st->execute();
            $st->close();
        }
    }
}

/* --- PODERES: helper para guardar poderes del formulario --- */
function save_character_powers(mysqli $link, int $charId, array $types, array $ids, array $lvls): array {
    $n = min(count($types), count($ids), count($lvls));
    $inserted = 0; $skipped = 0;

    if ($st = $link->prepare("DELETE FROM bridge_characters_powers WHERE character_id=?")) {
        $st->bind_param("i", $charId); $st->execute(); $st->close();
    }
    if ($n<=0) return ['inserted'=>0,'skipped'=>0];

    $seen = [];
    if ($ins = $link->prepare("INSERT INTO bridge_characters_powers (character_id, power_kind, power_id, power_level) VALUES (?,?,?,?)")) {
        for ($i=0; $i<$n; $i++){
            $t = (string)$types[$i];
            $id = (int)$ids[$i];
            $lvl = max(0, min(9, (int)$lvls[$i]));

            if (!in_array($t, ['dones','disciplinas','rituales'], true)) { $skipped++; continue; }
            if ($id <= 0) { $skipped++; continue; }

            $key = $t.':'.$id;
            if (isset($seen[$key])) { $skipped++; continue; }
            $seen[$key] = true;

            $ins->bind_param("isii", $charId, $t, $id, $lvl);
            if ($ins->execute()) { $inserted++; } else { $skipped++; }
        }
        $ins->close();
    }
    return ['inserted'=>$inserted,'skipped'=>$skipped];
}

/* --- MÉRITOS/DEFECTOS: helper guardar bridge (nivel NULL o int) --- */
function save_character_merits_flaws(mysqli $link, int $charId, array $ids, array $lvls_raw): array {
    $n = min(count($ids), count($lvls_raw));
    $inserted = 0; $skipped = 0;

    if ($st = $link->prepare("DELETE FROM bridge_characters_merits_flaws WHERE character_id=?")) {
        $st->bind_param("i", $charId); $st->execute(); $st->close();
    }
    if ($n<=0) return ['inserted'=>0,'skipped'=>0];

    $seen = [];
    // 3er parámetro lo bindeamos como string para que NULL viaje como NULL sin convertirse a 0
    if ($ins = $link->prepare("INSERT INTO bridge_characters_merits_flaws (character_id, merit_flaw_id, level) VALUES (?,?,?)")) {
        for ($i=0; $i<$n; $i++){
            $mid = (int)$ids[$i];
            if ($mid <= 0) { $skipped++; continue; }

            $key = (string)$mid;
            if (isset($seen[$key])) { $skipped++; continue; }
            $seen[$key] = true;

            $raw = $lvls_raw[$i];
            $raw = is_string($raw) ? trim($raw) : $raw;
            if ($raw === '' || $raw === null) {
                $lvl = null; // NULL real
            } else {
                $lvl = (string)max(-99, min(999, (int)$raw)); // tope razonable
            }

            $ins->bind_param("iis", $charId, $mid, $lvl);
            if ($ins->execute()) { $inserted++; } else { $skipped++; }
        }
        $ins->close();
    }
    return ['inserted'=>$inserted,'skipped'=>$skipped];
}

/* --- INVENTARIO: helper guardar bridge --- */
function save_character_items(mysqli $link, int $charId, array $itemIds): array {
    $inserted = 0; $skipped = 0;

    if ($st = $link->prepare("DELETE FROM bridge_characters_items WHERE character_id=?")) {
        $st->bind_param("i", $charId); $st->execute(); $st->close();
    }
    if (empty($itemIds)) return ['inserted'=>0,'skipped'=>0];

    $seen = [];
    if ($ins = $link->prepare("INSERT INTO bridge_characters_items (character_id, item_id) VALUES (?,?)")) {
        foreach ($itemIds as $iid){
            $iid = (int)$iid;
            if ($iid <= 0) { $skipped++; continue; }
            if (isset($seen[$iid])) { $skipped++; continue; }
            $seen[$iid] = true;

            $ins->bind_param("ii", $charId, $iid);
            if ($ins->execute()) { $inserted++; } else { $skipped++; }
        }
        $ins->close();
    }
    return ['inserted'=>$inserted,'skipped'=>$skipped];
}

/* --- TRAITS: helper guardar valores + log --- */
function save_character_traits(mysqli $link, int $charId, array $traits, string $source = 'admin', ?string $createdBy = null): array {
    if (empty($traits)) return ['updated'=>0,'logged'=>0,'skipped'=>0];

    $old = [];
    if ($st = $link->prepare("SELECT trait_id, value FROM fact_character_traits WHERE character_id=?")) {
        $st->bind_param("i", $charId);
        $st->execute();
        $rs = $st->get_result();
        while ($rs && ($row = $rs->fetch_assoc())) {
            $old[(int)$row['trait_id']] = (int)$row['value'];
        }
        $st->close();
    }

    $updated = 0; $logged = 0; $skipped = 0;

    $ins = $link->prepare("INSERT INTO fact_character_traits (character_id, trait_id, value)
                           VALUES (?,?,?)
                           ON DUPLICATE KEY UPDATE value=VALUES(value), updated_at=NOW()");
    $log = $link->prepare("INSERT INTO fact_character_traits_log
                           (character_id, trait_id, old_value, new_value, delta, reason, source, created_at, created_by)
                           VALUES (?,?,?,?,?,?,?,NOW(),?)");

    foreach ($traits as $tid => $val) {
        $tid = (int)$tid;
        if ($tid <= 0) { $skipped++; continue; }
        $v = (int)$val;
        if ($v < 0) $v = 0;
        if ($v > 10) $v = 10;

        if ($ins) {
            $ins->bind_param("iii", $charId, $tid, $v);
            if ($ins->execute()) { $updated++; } else { $skipped++; }
        }

        $hadOld = array_key_exists($tid, $old);
        $oldVal = $hadOld ? (int)$old[$tid] : null;
        if (!$hadOld && $v === 0) {
            continue; // no log for implicit zero
        }
        if ($hadOld && $oldVal === $v) {
            continue; // no changes
        }

        $oldStr = $hadOld ? (string)$oldVal : null;
        $newStr = (string)$v;
        $delta  = $hadOld ? (string)($v - $oldVal) : $newStr;
        $reason = $hadOld ? 'admin update' : 'admin initial';
        $src    = $source;
        $cb     = $createdBy;

        if ($log) {
            $log->bind_param("iissssss", $charId, $tid, $oldStr, $newStr, $delta, $reason, $src, $cb);
            if ($log->execute()) { $logged++; } else { $skipped++; }
        }
    }

    if ($ins) $ins->close();
    if ($log) $log->close();

    return ['updated'=>$updated,'logged'=>$logged,'skipped'=>$skipped];
}

/* -------------------------------------------------
   Helpers generales
------------------------------------------------- */
function fetchPairs($link, $sql, $bindTypes = "", $bindValues = []) {
    $out = [];
    $stmt = $link->prepare($sql);
    if(!$stmt){ return $out; }
    if ($bindTypes && $bindValues) { $stmt->bind_param($bindTypes, ...$bindValues); }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $key = isset($r['id']) ? (int)$r['id'] : (string)$r['name'];
        $out[$key] = $r['name'];
    }
    $stmt->close();
    return $out;
}

/* -------------------------------------------------
   Estado (lista válida desde BD)
------------------------------------------------- */
$estado_opts = [];
if ($rs = $link->query("SELECT estado FROM fact_characters GROUP BY 1 ORDER BY 1")) {
  while ($row = $rs->fetch_assoc()) {
    $val = (string)($row['estado'] ?? '');
    $estado_opts[$val] = $val;
  }
  $rs->close();
}
if (!isset($estado_opts['En activo'])) $estado_opts['En activo'] = 'En activo';
$estado_set = array_fill_keys(array_keys($estado_opts), true);

/* -------------------------------------------------
   Endpoint AJAX (se mantiene)
------------------------------------------------- */
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json; charset=UTF-8');

    $jsonFlags = JSON_UNESCAPED_UNICODE;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $jsonFlags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }

    $mode = $_GET['mode'] ?? '';
    if ($mode === 'details') {
        $id = max(0, (int)($_GET['id'] ?? 0));
        if ($id <= 0) { echo json_encode(['ok'=>false,'msg'=>'bad_id'], $jsonFlags); exit; }

        if ($st = $link->prepare("SELECT estado, cause_of_death, birthdate_text, rank, info_text FROM fact_characters WHERE id=? LIMIT 1")) {
            $st->bind_param("i", $id);
            $st->execute();
            $rs = $st->get_result();

            if ($rs && ($row = $rs->fetch_assoc())) {
                echo json_encode([
                    'ok'          => true,
                    'estado'      => (string)($row['estado'] ?? ''),
                    'causamuerte' => (string)($row['cause_of_death'] ?? ''),
                    'cumple'      => (string)($row['birthdate_text'] ?? ''),
                    'rango'       => (string)($row['rank'] ?? ''),
                    'infotext'    => (string)($row['info_text'] ?? ''),
                ], $jsonFlags);
            } else {
                echo json_encode(['ok'=>false,'msg'=>'not_found'], $jsonFlags);
            }

            $st->close();
            exit;
        }

        echo json_encode(['ok'=>false,'msg'=>'prep_fail'], $jsonFlags);
        exit;
    }

    echo json_encode(['ok'=>false,'msg'=>'bad_mode'], $jsonFlags);
    exit;
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

/* -------------------------------------------------
   Cargar opciones de referencia
------------------------------------------------- */
$opts_cronicas = fetchPairs($link, "SELECT id, name FROM dim_chronicles ORDER BY name");
$opts_clanes   = fetchPairs($link, "SELECT id, name FROM dim_organizations ORDER BY name");
$opts_jug      = fetchPairs($link, "SELECT id, name FROM dim_players ORDER BY name");
$opts_sist     = fetchPairs($link, "SELECT id, name FROM dim_systems ORDER BY name");
$opts_totems   = fetchPairs($link, "SELECT id, name FROM dim_totems ORDER BY name");
$opts_afili    = fetchPairs($link, "SELECT id, kind AS name FROM dim_character_types ORDER BY sort_order, kind");
$opts_manadas_flat = fetchPairs($link, "SELECT id, name FROM dim_groups ORDER BY name");

/* --- PODERES: catálogos --- */
$opts_dones        = fetchPairs($link, "SELECT id, CONCAT(name, ' (', grupo, ')') AS name FROM fact_gifts");
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

/* --- TRAITS: catálogo (todos los tipos) --- */
$traits_by_type = [];
$trait_types = [];
$trait_order_fixed = ['Atributos','Talentos','Técnicas','Conocimientos','Trasfondos'];
if ($st = $link->prepare("
    SELECT id, name, kind
    FROM dim_traits
    WHERE kind IS NOT NULL AND TRIM(kind) <> ''
    ORDER BY kind, name
")) {
    $st->execute(); $rs = $st->get_result();
    while ($r = $rs->fetch_assoc()) {
        $kind = (string)$r['kind'];
        if (!isset($traits_by_type[$kind])) {
            $traits_by_type[$kind] = [];
        }
        $traits_by_type[$kind][] = ['id'=>(int)$r['id'], 'name'=>(string)$r['name']];
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
   Sistema → (Raza, Auspicio, Tribu)
------------------------------------------------- */
// RAZAS
$opts_razas = [];
$razas_by_sys = []; $raza_id_to_sys = [];
if ($st = $link->prepare("SELECT id, name, system_id FROM dim_breeds ORDER BY system_id, name")) {
    $st->execute(); $rs = $st->get_result();
    while ($r = $rs->fetch_assoc()) {
        $id = (int)$r['id']; $nm = (string)$r['name']; $sys = (int)($r['system_id'] ?? 0);
        $opts_razas[$id] = $nm . ($sys>0 && isset($opts_sist[$sys]) ? ' ('.$opts_sist[$sys].')' : '');
        $raza_id_to_sys[$id] = $sys;
        $razas_by_sys[$sys][] = ['id'=>$id,'name'=>$nm];
    }
    $st->close();
}
// AUSPICIOS
$opts_ausp = []; $ausp_by_sys = []; $ausp_id_to_sys = [];
if ($st = $link->prepare("SELECT id, name, system_id FROM dim_auspices ORDER BY system_id, name")) {
    $st->execute(); $rs = $st->get_result();
    while ($r = $rs->fetch_assoc()) {
        $id = (int)$r['id']; $nm = (string)$r['name']; $sys = (int)($r['system_id'] ?? 0);
        $opts_ausp[$id] = $nm;
        $ausp_id_to_sys[$id] = $sys;
        $ausp_by_sys[$sys][] = ['id'=>$id,'name'=>$nm];
    }
    $st->close();
} else {
    $opts_ausp = fetchPairs($link, "SELECT id, name FROM dim_auspices ORDER BY name");
}
// TRIBUS
$opts_tribus = []; $tribus_by_sys = []; $tribu_id_to_sys = [];
if ($st = $link->prepare("SELECT id, name, system_id FROM dim_tribes ORDER BY system_id, name")) {
    $st->execute(); $rs = $st->get_result();
    while ($r = $rs->fetch_assoc()) {
        $id = (int)$r['id']; $nm = (string)$r['name']; $sys = (int)($r['system_id'] ?? 0);
        $opts_tribus[$id] = $nm;
        $tribu_id_to_sys[$id] = $sys;
        $tribus_by_sys[$sys][] = ['id'=>$id,'name'=>$nm];
    }
    $st->close();
} else {
    $opts_tribus = fetchPairs($link, "SELECT id, name FROM dim_tribes ORDER BY name");
}

/* -------------------------------------------------
   MAPAS Clan→Manadas (por BRIDGE bridge_organizations_groups)
------------------------------------------------- */
$manadas_map_id_to_clan = [];
$manadas_by_clan        = [];

$sqlMap = "
    SELECT
        b.group_id AS manada_id,
        m.name     AS manada_name,
        b.clan_id  AS clan_id
    FROM bridge_organizations_groups b
    INNER JOIN dim_groups m ON m.id = b.group_id
    INNER JOIN dim_organizations  c ON c.id = b.clan_id
    WHERE (b.is_active = 1 OR b.is_active IS NULL)
    ORDER BY b.clan_id, m.name
";
if ($stmtM = $link->prepare($sqlMap)) {
    $stmtM->execute();
    $resM = $stmtM->get_result();
    while ($row = $resM->fetch_assoc()) {
        $mid = (int)$row['manada_id'];
        $cid = (int)$row['clan_id'];
        $manadas_map_id_to_clan[$mid] = $cid;
        $manadas_by_clan[$cid][] = ['id'=>$mid, 'name'=>$row['manada_name']];
    }
    $stmtM->close();
}

/* -------------------------------------------------
   Crear / Editar (POST) + avatar + validaciones + PODERES + MÉRITOS/DEFECTOS + INVENTARIO + CAMPOS COMPLEJOS
------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crud_action'])) {
    $action      = $_POST['crud_action'];
    $id          = intval($_POST['id'] ?? 0);
    $nombre      = trim($_POST['nombre'] ?? '');
    $alias       = trim($_POST['alias'] ?? '');
    $nombregarou = trim($_POST['nombregarou'] ?? '');
    $genero_pj   = trim($_POST['genero_pj'] ?? '');
    $concepto    = trim($_POST['concepto'] ?? '');
    $colortexto  = trim($_POST['colortexto'] ?? '');
    $cronica     = max(0, intval($_POST['cronica'] ?? 0));
    $jugador     = max(0, intval($_POST['jugador'] ?? 0));
    $afili       = max(0, intval($_POST['afiliacion'] ?? 0));
    $raza        = max(0, intval($_POST['raza'] ?? 0));
    $auspicio    = max(0, intval($_POST['auspicio'] ?? 0));
    $tribu       = max(0, intval($_POST['tribu'] ?? 0));
    $manada      = max(0, intval($_POST['manada'] ?? 0));
    $clan        = max(0, intval($_POST['clan'] ?? 0));
    $system_id   = isset($_POST['system_id']) ? (int)$_POST['system_id'] : 0;
    $sistema_legacy = trim($_POST['sistema_legacy'] ?? '');
    $totem_id = isset($_POST['totem_id']) ? (int)$_POST['totem_id'] : 0;
    $rm_avatar   = isset($_POST['avatar_remove']) && $_POST['avatar_remove'] ? true : false;

    // Campos complejos
    $estado      = (string)($_POST['estado'] ?? '');
    $causamuerte = trim($_POST['causamuerte'] ?? '');
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

    if ($genero_pj === '')  $genero_pj = 'f';
    if ($colortexto === '') $colortexto = 'SkyBlue';
    if ($estado === '')     $estado = 'En activo';

    // Validaciones
    if ($clan <= 0) $flash[] = ['type'=>'error','msg'=>'⚠ Debes seleccionar un Clan.'];
    if (!isset($estado_set[$estado])) $flash[] = ['type'=>'error','msg'=>'⚠ El estado no es válido.'];
    if ($manada > 0) {
        $clan_of_manada = $manadas_map_id_to_clan[$manada] ?? 0;
        if ($clan_of_manada !== $clan) {
            $flash[] = ['type'=>'error','msg'=>'⚠ La Manada seleccionada no pertenece al Clan elegido.'];
        }
    }
    if ($system_id > 0) {
        if ($raza     > 0 && isset($raza_id_to_sys[$raza])     && (int)$raza_id_to_sys[$raza]     !== (int)$system_id) $flash[]=['type'=>'error','msg'=>'⚠ La Raza no pertenece al Sistema elegido.'];
        if ($auspicio > 0 && isset($ausp_id_to_sys[$auspicio]) && (int)$ausp_id_to_sys[$auspicio] !== (int)$system_id) $flash[]=['type'=>'error','msg'=>'⚠ El Auspicio no pertenece al Sistema elegido.'];
        if ($tribu    > 0 && isset($tribu_id_to_sys[$tribu])   && (int)$tribu_id_to_sys[$tribu]   !== (int)$system_id) $flash[]=['type'=>'error','msg'=>'⚠ La Tribu no pertenece al Sistema elegido.'];
    }

    if ($system_id > 0 && isset($opts_sist[$system_id])) {
        $sistema_legacy = (string)$opts_sist[$system_id];
    }
    if ($sistema_legacy === '') {
        $sistema_legacy = 'Otros';
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
    $totem_legacy = '';
    if ($totem_id > 0 && isset($opts_totems[$totem_id])) {
        $totem_legacy = (string)$opts_totems[$totem_id];
    } else {
        $totem_id = null; // NULL para evitar FK con 0
    }

    // Avatar actual (para update)
    $current_img = '';
    if ($action === 'update' && $id > 0) {
        if ($st = $link->prepare("SELECT img FROM fact_characters WHERE id=?")) {
            $st->bind_param("i",$id); $st->execute();
            $rs = $st->get_result(); if ($row=$rs->fetch_assoc()) $current_img = (string)($row['img'] ?? '');
            $st->close();
        }
    }

    if ($action === 'create') {
        if ($nombre === '') $flash[] = ['type'=>'error','msg'=>'⚠ El campo \"nombre\" es obligatorio.'];
        if (!array_filter($flash, fn($f)=>$f['type']==='error')) {
            $sql = "INSERT INTO fact_characters
                (name, alias, garou_name, genero_pj, concepto, chronicle_id, player_id, kind, img, notes, colortexto, character_kind, system_name, system_id,
                 shifter_type, totem_name, totem_id, estado, cause_of_death, birthdate_text, rank, info_text, breed_id, auspicio, tribu)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
            if ($stmt = $link->prepare($sql)) {
                $img=''; $kes='pnj'; $fera=''; $totem='';
                $stmt->bind_param(
                    "sssssiiisssssississsssiii",
                    $nombre, $alias, $nombregarou, $genero_pj, $concepto,
                    $cronica, $jugador, $afili,
                    $img, $notas, $colortexto, $kes, $sistema_legacy, $system_id, $fera,
                    $totem_legacy, $totem_id,
                    $estado, $causamuerte, $cumple, $rango, $infotext,
                    $raza, $auspicio, $tribu
                );
                if ($stmt->execute()) {
                    $newId = $stmt->insert_id;
                    hg_update_pretty_id_if_exists($link, 'fact_characters', (int)$newId, $nombre);

                    // Bridges manada/clan
                    sync_character_bridges($link, (int)$newId, (int)$manada, (int)$clan);

                    // Avatar si viene
                    if (!empty($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
                        $res = save_avatar_file($_FILES['avatar'], $newId, $nombre, $AV_UPLOADDIR, $AV_URLBASE);
                        if ($res['ok']) {
                            if ($st2 = $link->prepare("UPDATE fact_characters SET img=? WHERE id=?")) {
                                $st2->bind_param("si", $res['url'], $newId);
                                $st2->execute(); $st2->close();
                            }
                            $flash[] = ['type'=>'ok','msg'=>'🖼 Avatar subido.'];
                        } elseif ($res['msg']!=='no_file') {
                            $flash[] = ['type'=>'error','msg'=>'⚠ Avatar no guardado: '.$res['msg']];
                        }
                    }

                    // Poderes
                    $resultPow = save_character_powers($link, (int)$newId, $powers_type, $powers_id, $powers_lvl);
                    if ($resultPow['inserted']>0) { $flash[]=['type'=>'ok','msg'=>'✨ Poderes vinculados: '.$resultPow['inserted']]; }
                    if ($resultPow['skipped']>0)  { $flash[]=['type'=>'info','msg'=>'(Poderes omitidos: '.$resultPow['skipped'].')']; }

                    // Méritos/Defectos
                    $resultMyd = save_character_merits_flaws($link, (int)$newId, $myd_id, $myd_lvl_raw);
                    if ($resultMyd['inserted']>0) { $flash[]=['type'=>'ok','msg'=>'🏷️ Méritos/Defectos vinculados: '.$resultMyd['inserted']]; }
                    if ($resultMyd['skipped']>0)  { $flash[]=['type'=>'info','msg'=>'(Méritos/Defectos omitidos: '.$resultMyd['skipped'].')']; }

                    // Inventario
                    $resultIt = save_character_items($link, (int)$newId, $items_id);
                    if ($resultIt['inserted']>0) { $flash[]=['type'=>'ok','msg'=>'🎒 Objetos vinculados: '.$resultIt['inserted']]; }
                    if ($resultIt['skipped']>0)  { $flash[]=['type'=>'info','msg'=>'(Objetos omitidos: '.$resultIt['skipped'].')']; }

                    
                    // Traits
                    $resultTr = save_character_traits($link, (int)$newId, $traits, 'admin', null);
                    if ($resultTr['updated']>0) { $flash[]=['type'=>'ok','msg'=>'Traits guardados: '.$resultTr['updated']]; }
$flash[] = ['type'=>'ok','msg'=>'✅ Personaje creado correctamente.'];
                } else {
                    $flash[] = ['type'=>'error','msg'=>'❌ Error al crear: '.$stmt->error];
                }
                $stmt->close();
            } else {
                $flash[] = ['type'=>'error','msg'=>'❌ Error al preparar INSERT: '.$link->error];
            }
        }
    }

    if ($action === 'update') {
    if ($id <= 0)       $flash[] = ['type'=>'error','msg'=>'⚠ Falta el ID para editar.'];
    if ($nombre === '') $flash[] = ['type'=>'error','msg'=>'⚠ El campo "nombre" es obligatorio.'];

    if (!array_filter($flash, fn($f)=>$f['type']==='error')) {

          // ✅ OJO: ya NO actualizamos p.manada ni p.clan aquí (bridges mandan)
          $sql = "UPDATE fact_characters SET
                  name=?, alias=?, garou_name=?, genero_pj=?, concepto=?,
                  chronicle_id=?, player_id=?, kind=?, system_name=?, system_id=?, colortexto=?,
                  breed_id=?, auspicio=?, tribu=?,
                  totem_name=?, totem_id=?,
                  estado=?, cause_of_death=?, birthdate_text=?, rank=?, info_text=?
                  WHERE id=?";

          if ($stmt = $link->prepare($sql)) {

              // 13 strings/ints + 5 strings + id (int)
              $stmt->bind_param(
                  "sssssiiisisiiisisssssi",
                  $nombre, $alias, $nombregarou, $genero_pj, $concepto,
                  $cronica, $jugador, $afili, $sistema_legacy, $system_id, $colortexto,
                  $raza, $auspicio, $tribu,
                  $totem_legacy, $totem_id,
                  $estado, $causamuerte, $cumple, $rango, $infotext,
                  $id
              );

              if ($stmt->execute()) {
                  hg_update_pretty_id_if_exists($link, 'fact_characters', $id, $nombre);

                  // Avatar
                  if ($rm_avatar && $current_img) {
                      safe_unlink_avatar($current_img, $AV_UPLOADDIR);
                      if ($st2 = $link->prepare("UPDATE fact_characters SET img='' WHERE id=?")) {
                          $st2->bind_param("i",$id);
                          $st2->execute();
                          $st2->close();
                      }
                      $flash[] = ['type'=>'ok','msg'=>'🗑 Avatar eliminado.'];
                      $current_img = '';
                  }

                  if (!empty($_FILES['avatar']) && ($_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                      $res = save_avatar_file($_FILES['avatar'], $id, $nombre, $AV_UPLOADDIR, $AV_URLBASE);
                      if ($res['ok']) {
                          if ($current_img) safe_unlink_avatar($current_img, $AV_UPLOADDIR);
                          if ($st3 = $link->prepare("UPDATE fact_characters SET img=? WHERE id=?")) {
                              $st3->bind_param("si", $res['url'], $id);
                              $st3->execute();
                              $st3->close();
                          }
                          $flash[] = ['type'=>'ok','msg'=>'🖼 Avatar actualizado.'];
                      } elseif ($res['msg']!=='no_file') {
                          $flash[] = ['type'=>'error','msg'=>'⚠ Avatar no guardado: '.$res['msg']];
                      }
                  }

                  // ✅ Bridges: aquí sí guardas clan/manada (fuente de verdad)
                  sync_character_bridges($link, (int)$id, (int)$manada, (int)$clan);

                  // Poderes
                  $resultPow = save_character_powers($link, (int)$id, $powers_type, $powers_id, $powers_lvl);
                  if ($resultPow['inserted']>0) { $flash[]=['type'=>'ok','msg'=>'✨ Poderes vinculados: '.$resultPow['inserted']]; }
                  if ($resultPow['skipped']>0)  { $flash[]=['type'=>'info','msg'=>'(Poderes omitidos: '.$resultPow['skipped'].')']; }

                  // Méritos/Defectos
                  $resultMyd = save_character_merits_flaws($link, (int)$id, $myd_id, $myd_lvl_raw);
                  if ($resultMyd['inserted']>0) { $flash[]=['type'=>'ok','msg'=>'🏷️ Méritos/Defectos vinculados: '.$resultMyd['inserted']]; }
                  if ($resultMyd['skipped']>0)  { $flash[]=['type'=>'info','msg'=>'(Méritos/Defectos omitidos: '.$resultMyd['skipped'].')']; }

                  // Inventario
                  $resultIt = save_character_items($link, (int)$id, $items_id);
                  if ($resultIt['inserted']>0) { $flash[]=['type'=>'ok','msg'=>'🎒 Objetos vinculados: '.$resultIt['inserted']]; }
                  if ($resultIt['skipped']>0)  { $flash[]=['type'=>'info','msg'=>'(Objetos omitidos: '.$resultIt['skipped'].')']; }

                  
                  // Traits
                  $resultTr = save_character_traits($link, (int)$id, $traits, 'admin', null);
                  if ($resultTr['updated']>0) { $flash[]=['type'=>'ok','msg'=>'Traits guardados: '.$resultTr['updated']]; }
$flash[] = ['type'=>'ok','msg'=>'✏ Personaje actualizado.'];

              } else {
                  $flash[] = ['type'=>'error','msg'=>'❌ Error al actualizar: '.$stmt->error];
              }

              $stmt->close();

          } else {
              $flash[] = ['type'=>'error','msg'=>'❌ Error al preparar UPDATE: '.$link->error];
          }
      }
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
      SELECT character_id, MIN(clan_id) AS clan_id
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
  p.id, p.name, p.alias, p.garou_name, p.genero_pj, p.concepto,
  p.chronicle_id, p.player_id, p.system_id, p.system_name, p.colortexto,
  p.breed_id, p.auspicio, p.tribu,
  -- ✅ IDs desde bridge (para el modal y coherencia)
  COALESCE(pgb.group_id, 0) AS manada,
  COALESCE(pcb.clan_id, 0)  AS clan,
  p.img, p.kind AS character_type_id, p.totem_id, p.totem_name,

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
    SELECT character_id, MIN(clan_id) AS clan_id
    FROM bridge_characters_organizations
    WHERE (is_active=1 OR is_active IS NULL)
    GROUP BY character_id
) pcb ON pcb.character_id = p.id

LEFT JOIN dim_players  nj ON p.player_id = nj.id
LEFT JOIN dim_chronicles  nc ON p.chronicle_id = nc.id
LEFT JOIN dim_systems     ds ON p.system_id = ds.id
LEFT JOIN dim_totems      dt ON p.totem_id = dt.id
LEFT JOIN dim_breeds      nr ON p.breed_id    = nr.id
LEFT JOIN dim_auspices  na ON p.auspicio= na.id
LEFT JOIN dim_tribes     nt ON p.tribu   = nt.id

-- ✅ Nombres desde ids bridge
LEFT JOIN dim_groups   nm  ON nm.id  = pgb.group_id
LEFT JOIN dim_organizations    nc2 ON nc2.id = pcb.clan_id

LEFT JOIN dim_character_types af ON p.kind  = af.id

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
    $qdet = $link->query("SELECT id, estado, cause_of_death, birthdate_text, rank, info_text FROM fact_characters WHERE id IN ($in)");
    if ($qdet) {
        while ($d = $qdet->fetch_assoc()) {
            $cid = (int)($d['id'] ?? 0);
            if ($cid <= 0) continue;
            $char_details[$cid] = [
                'estado'      => (string)($d['estado'] ?? ''),
                'causamuerte' => (string)($d['cause_of_death'] ?? ''),
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
        FROM fact_character_traits
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

// Base AJAX (misma página)
$AJAX_BASE = "/talim?s=admin_pjs&ajax=1";
?>

<style>
.panel-wrap { background:#05014E; border:1px solid #000088; border-radius:12px; padding:12px; }
.hdr { display:flex; gap:12px; align-items:center; flex-wrap:wrap; margin-bottom:10px; }
.hdr h2 { margin:0; color:#33FFFF; font-size:16px; }
.btn { background:#0d3a7a; color:#fff; border:1px solid #1b4aa0; border-radius:8px; padding:6px 10px; cursor:pointer; font-size:12px; }
.btn:hover { filter:brightness(1.1); }
.btn-green { background:#0d5d37; border-color:#168f59; }
.btn-red { background:#6b1c1c; border-color:#993333; }
.inp { background:#000033; color:#fff; border:1px solid #333; padding:4px 6px; font-size:12px; }
.select { background:#000033; color:#fff; border:1px solid #333; padding:4px 6px; font-size:12px; }
.select:disabled { opacity:0.6; cursor:not-allowed; }
.ta { background:#000033; color:#fff; border:1px solid #333; padding:6px 8px; font-size:12px; width:100%; box-sizing:border-box; }
.table { width:100%; border-collapse:collapse; font-size:11px; font-family:Verdana,Arial,sans-serif; }
.table th, .table td { border:1px solid #000088; padding:6px 8px; background:#05014E; white-space:nowrap; }
.table th { background:#050b36; color:#33CCCC; text-align:left; }
.table tr:hover td { background:#000066; color:#33FFFF; }
.pager{ display:flex; gap:6px; align-items:center; margin-top:10px; flex-wrap:wrap; }
.pager a, .pager span { display:inline-block; padding:4px 8px; border:1px solid #000088; background:#05014E; color:#eee; text-decoration:none; border-radius:6px; }
.pager .cur { background:#001199; }
.flash { margin:6px 0; }
.flash .ok{ color:#7CFC00; } .flash .err{ color:#FF6B6B; } .flash .info{ color:#33FFFF; }
.modal h3{ margin:0 0 8px; color:#33FFFF; }
.grid { display:grid; grid-template-columns:repeat(3, minmax(220px,1fr)); gap:8px 12px; }
.grid label{ font-size:12px; color:#cfe; display:block; }
.grid input, .grid select, .grid textarea { width:100%; box-sizing:border-box; }
.avatar-wrap{ display:flex; gap:10px; align-items:flex-start; }
.avatar-wrap img{ width:96px; height:96px; object-fit:cover; border-radius:10px; border:1px solid #1b4aa0; background:#000022; }
.small-note{ font-size:10px; color:#9dd; display:block; margin-top:4px; }
.traits-grid{ display:grid; grid-template-columns:repeat(2, minmax(260px,1fr)); gap:10px; }
.traits-group{ background:#04023b; border:1px solid #000088; border-radius:10px; padding:8px; }
.traits-title{ font-weight:700; color:#9ff; margin-bottom:6px; }
.traits-items{ display:grid; grid-template-columns:repeat(2, minmax(120px,1fr)); gap:6px 8px; }
.trait-item{ display:flex; align-items:center; justify-content:space-between; gap:6px; }
.trait-item span{ font-size:11px; color:#cfe; }
.trait-item input{ width:64px; }

.modal-back{
  position:fixed; inset:0;
  background:rgba(0,0,0,.6);
  display:none; align-items:center; justify-content:center;
  z-index:9999;
  padding:14px;
  box-sizing:border-box;
}

/* Modal: altura máxima y scroll interno */
.modal{
  width:min(1000px, 96vw);
  max-height:92vh;
  overflow:hidden;
  background:#05014E;
  border:1px solid #000088;
  border-radius:12px;
  padding:12px;
  display:flex;
  flex-direction:column;
}

/* Contenido scrolleable */
#formCrud{
  flex:1;
  overflow:auto;
  padding-right:6px;
}

/* Acciones sticky */
.modal-actions{
  position:sticky;
  bottom:0;
  display:flex;
  gap:10px;
  justify-content:flex-end;
  padding:10px 0 0;
  margin-top:10px;
  background:linear-gradient(to top, rgba(5,1,78,1), rgba(5,1,78,0));
  border-top:1px solid #000088;
}

@media (max-width:750px){
  .modal{ max-height:94vh; padding:10px; }
  .btn{ padding:8px 10px; }
}

/* Chips */
.chips{ display:flex; flex-wrap:wrap; gap:6px; margin-top:6px; }
.chip{ display:inline-flex; align-items:center; gap:6px; background:#00135a; border:1px solid #1b4aa0; border-radius:999px; padding:4px 8px; }
.chip .tag{ font-weight:bold; color:#9dd; }
.chip .pname{ color:#fff; }
.chip input.power-lvl{ width:48px; text-align:center; }
.chip input.myd-lvl{ width:70px; text-align:center; }

@media (max-width:1100px){ .grid{ grid-template-columns:repeat(2, minmax(240px,1fr)); } }
@media (max-width:750px){ .grid{ grid-template-columns:1fr; } }
</style>

<br />
<div class="panel-wrap">
  <div class="hdr">
    <h2>👤 Personajes — Lista & CRUD</h2>
    <button class="btn btn-green" id="btnNew">➕ Nuevo personaje</button>

    <form method="get" style="display:flex; gap:8px; align-items:center;">
      <input type="hidden" name="p" value="talim">
      <input type="hidden" name="s" value="admin_pjs">
      <label>Crónica
        <select class="select" name="fil_cr" onchange="this.form.submit()">
          <option value="0">Todas</option>
          <?php foreach($opts_cronicas as $id=>$name): ?>
            <option value="<?= (int)$id ?>" <?= $fil_cr==$id?'selected':'' ?>><?= h($name) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Manada
        <select class="select" name="fil_ma" onchange="this.form.submit()">
          <option value="0">Todas</option>
          <?php foreach($opts_manadas_flat as $id=>$name): ?>
            <option value="<?= (int)$id ?>" <?= $fil_ma==$id?'selected':'' ?>><?= h($name) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label style="margin-left:auto;text-align:left;">Filtro rápido
        <input class="inp" type="text" id="quickFilter" placeholder="En esta página…">
      </label>
      <label>Pág
        <select class="select" name="pp" onchange="this.form.submit()">
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
        <th style="width:60px;">ID</th>
        <th>Nombre</th>
        <th>Jugador</th>
        <th>Crónica</th>
        <th>Sistema</th>
        <th style="width:120px;">Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr data-nombre="<?= strtolower(h($r['name'])) ?>">
          <td><strong style="color:#33FFFF;"><?= (int)$r['id'] ?></strong></td>
          <td><?= h($r['name']) ?></td>
          <td><?= h($r['jugador_'] ?? $r['player_id']) ?></td>
          <td><?= h($r['cronica_'] ?? $r['chronicle_id']) ?></td>
          <td><?= h($r['sistema_n'] ?? $r['system_name']) ?></td>
          <td>
            <button class="btn btn-small" data-edit='1'
              data-id="<?= (int)$r['id'] ?>"
              data-nombre="<?= h($r['name']) ?>"
              data-alias="<?= h($r['alias']) ?>"
              data-nombregarou="<?= h($r['garou_name']) ?>"
              data-genero_pj="<?= h($r['genero_pj']) ?>"
              data-concepto="<?= h($r['concepto']) ?>"
              data-cronica="<?= (int)$r['chronicle_id'] ?>"
              data-jugador="<?= (int)$r['player_id'] ?>"
              data-system_id="<?= (int)($r['system_id'] ?? 0) ?>"
              data-sistema_legacy="<?= h($r['system_name']) ?>"
              data-totem_id="<?= (int)($r['totem_id'] ?? 0) ?>"
              data-colortexto="<?= h($r['colortexto']) ?>"
              data-raza="<?= (int)$r['breed_id'] ?>"
              data-auspicio="<?= (int)$r['auspicio'] ?>"
              data-tribu="<?= (int)$r['tribu'] ?>"
              data-manada="<?= (int)$r['manada'] ?>"
              data-clan="<?= (int)$r['clan'] ?>"
              data-img="<?= h($r['img']) ?>"
              data-afiliacion="<?= (int)$r['character_type_id'] ?>"
            >✏ Editar</button>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($rows)): ?>
        <tr><td colspan="6" style="color:#bbb;">(Sin resultados)</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <div class="pager">
    <?php
      $base = "/talim?s=admin_pjs&pp=".$perPage."&fil_cr=".$fil_cr."&fil_ma=".$fil_ma."&q=".urlencode($q);
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
    <form method="post" id="formCrud" enctype="multipart/form-data" style="margin:0;">
      <input type="hidden" name="crud_action" id="crud_action" value="create">
      <input type="hidden" name="id" id="f_id" value="0">

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
          <label style="text-align:left;">Nombre Garou
            <input class="inp" type="text" name="nombregarou" id="f_nombregarou" maxlength="100">
          </label>
        </div>

        <div>
          <label>Género (f/m/…)
            <input class="inp" type="text" name="genero_pj" id="f_genero_pj" maxlength="1" placeholder="f">
          </label>
        </div>
        <div>
          <label>Concepto
            <input class="inp" type="text" name="concepto" id="f_concepto" maxlength="50">
          </label>
        </div>
        <div>
          <label style="text-align:left;">Color texto
            <input class="inp" type="text" name="colortexto" id="f_colortexto" placeholder="SkyBlue">
          </label>
        </div>

        <div>
          <label>Estado
            <select class="select" name="estado" id="f_estado" required>
              <option value="">— Selecciona —</option>
              <?php foreach ($estado_opts as $val=>$label): ?>
                <option value="<?= h($val) ?>"><?= h($label==='' ? '(vacío)' : $label) ?></option>
              <?php endforeach; ?>
            </select>
            <span class="small-note">Lista desde: SELECT estado FROM fact_characters GROUP BY 1</span>
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
          <label style="text-align:left;">¿Qué es?
            <select class="select" name="afiliacion" id="f_afiliacion">
              <option value="0">—</option>
              <?php foreach($opts_afili as $id=>$name): ?>
                <option value="<?= (int)$id ?>"><?= h($name) ?></option>
              <?php endforeach; ?>
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
            <input type="hidden" name="sistema_legacy" id="f_sistema_legacy" value="">
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
            <select class="select" name="auspicio" id="f_auspicio" disabled>
              <option value="0">— Elige un Sistema —</option>
              <?php foreach($opts_ausp as $id=>$name): ?>
                <option value="<?= (int)$id ?>"><?= h($name) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
        <div>
          <label>Tribu
            <select class="select" name="tribu" id="f_tribu" disabled>
              <option value="0">— Elige un Sistema —</option>
              <?php foreach($opts_tribus as $id=>$name): ?>
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
              <img id="f_avatar_preview" src="" alt="avatar" style="display:none;">
              <div>
                <input class="inp" type="file" name="avatar" id="f_avatar" accept="image/*">
                <label class="small-note"><input type="checkbox" name="avatar_remove" id="f_avatar_remove" value="1"> Quitar avatar</label>
                <span class="small-note">JPG/PNG/GIF/WebP · máx. 5 MB</span>
              </div>
            </div>
          </label>
        </div>

        <div style="grid-column:1/-1;">
          <label style="text-align:left;">Causa de muerte
            <textarea class="ta" name="causamuerte" id="f_causamuerte" rows="3" placeholder="Texto libre…"></textarea>
          </label>
        </div>

        <div style="grid-column:1/-1;">
          <label style="text-align:left;">Información sobre el personaje
            <textarea class="ta hg-mention-input" data-mentions="character,season,episode,organization,group,gift,rite,totem,discipline,item,trait,background,merit,flaw,merydef,doc" name="infotext" id="f_infotext" rows="6" placeholder="Texto largo…"></textarea>
          </label>
        </div>

        <!-- TRAITS -->
        <div style="grid-column:1/-1;">
          <label><strong>Traits</strong></label>
          <div class="traits-grid">
            <?php foreach ($trait_types as $tipo): $list = $traits_by_type[$tipo] ?? []; if (!$list) continue; ?>
              <div class="traits-group">
                <div class="traits-title"><?= h($tipo) ?></div>
                <div class="traits-items">
                  <?php foreach ($list as $t): ?>
                    <label class="trait-item" data-trait-name="<?= h($t['name']) ?>">
                      <span><?= h($t['name']) ?></span>
                      <input class="inp trait-input" type="number" min="0" max="10" name="traits[<?= (int)$t['id'] ?>]" data-trait-id="<?= (int)$t['id'] ?>" value="0">
                    </label>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <span class="small-note">Se guardan en fact_character_traits.</span>
        </div>

        <!-- PODERES -->
        <div style="grid-column:1/-1;">
          <label><strong>Poderes</strong></label>
          <div class="grid" style="grid-template-columns: 1fr 2fr 120px auto; gap:8px;">
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
        <div style="grid-column:1/-1;">
          <label><strong>Méritos &amp; Defectos</strong></label>
          <div class="grid" style="grid-template-columns: 2fr 140px auto; gap:8px;">
            <select class="select" id="myd_sel"></select>
            <input class="inp" id="myd_lvl" type="number" min="-99" max="999" placeholder="nivel (opcional)">
            <button class="btn" type="button" id="myd_add">Añadir</button>
          </div>
          <div class="chips" id="mydList"></div>
          <span class="small-note">Nivel vacío = NULL (se usará el coste del mérito/defecto en la hoja).</span>
        </div>

        <!-- INVENTARIO -->
        <div style="grid-column:1/-1;">
          <label><strong>Inventario</strong></label>
          <div class="grid" style="grid-template-columns: 2fr auto; gap:8px;">
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

<script>
/* ------------ Select2 init (dentro del modal) ------------ */
function initSelect2Modal(){
  if (!window.jQuery || !jQuery.fn || !jQuery.fn.select2) return;

  var $parent = jQuery('#mb');
  // Sólo selects del modal
  $parent.find('select').each(function(){
  if (window.hgMentions) { window.hgMentions.attachAuto(); }
    var $s = jQuery(this);
    if ($s.data('select2')) $s.select2('destroy');

    $s.select2({
      width: 'style',
      dropdownParent: $parent,
      minimumResultsForSearch: 0
    });
  });
}

// Reinit individual (cuando se cambian options por JS)
function reinitSelect2(el){
  if (!window.jQuery || !jQuery.fn || !jQuery.fn.select2) return;
  if (!el) return;
  var $s = jQuery(el);
  if ($s.data('select2')) $s.select2('destroy');
  $s.select2({
    width: 'style',
    dropdownParent: jQuery('#mb'),
    minimumResultsForSearch: 0
  });
}

// Bind change que funciona con Select2 (jQuery) y sin él
function onSelectChange(el, handler){
  if (!el) return;
  if (window.jQuery) {
    jQuery(el).off('change.hg').on('change.hg', handler);
  } else {
    el.addEventListener('change', handler);
  }
}

var AJAX_BASE = <?= json_encode($AJAX_BASE, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;

// Dependencias
var MANADAS_BY_CLAN   = <?= json_encode($manadas_by_clan, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
var MANADA_ID_TO_CLAN = <?= json_encode($manadas_map_id_to_clan, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;

var RAZAS_BY_SYS      = <?= json_encode($razas_by_sys, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
var RAZA_ID_TO_SYS    = <?= json_encode($raza_id_to_sys, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;

var AUSP_BY_SYS       = <?= json_encode($ausp_by_sys, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
var AUSP_ID_TO_SYS    = <?= json_encode($ausp_id_to_sys, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;

var TRIBUS_BY_SYS     = <?= json_encode($tribus_by_sys, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
var TRIBU_ID_TO_SYS   = <?= json_encode($tribu_id_to_sys, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;

// PODERES
var DONES_OPTS       = <?= json_encode(array_map(fn($id,$name)=>['id'=>$id,'name'=>$name], array_keys($opts_dones), array_values($opts_dones)), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
var DISC_OPTS        = <?= json_encode(array_map(fn($id,$name)=>['id'=>$id,'name'=>$name], array_keys($opts_disciplinas), array_values($opts_disciplinas)), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
var RITU_OPTS        = <?= json_encode(array_map(fn($id,$name)=>['id'=>$id,'name'=>$name], array_keys($opts_rituales), array_values($opts_rituales)), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
var CHAR_POWERS      = <?= json_encode($char_powers, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;

// MÉRITOS/DEFECTOS
var MYD_OPTS         = <?= json_encode($opts_myd_full, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
var CHAR_MYD         = <?= json_encode($char_myd, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;

// INVENTARIO
var ITEMS_OPTS       = <?= json_encode($opts_items_full, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
var CHAR_ITEMS       = <?= json_encode($char_items, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;

// TRAITS
var CHAR_TRAITS      = <?= json_encode(
    $char_traits,
    JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE
    | (defined('JSON_INVALID_UTF8_SUBSTITUTE') ? JSON_INVALID_UTF8_SUBSTITUTE : 0)
); ?>;
var TRAIT_SET_ORDER  = <?= json_encode(
    $trait_set_order,
    JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE
    | (defined('JSON_INVALID_UTF8_SUBSTITUTE') ? JSON_INVALID_UTF8_SUBSTITUTE : 0)
); ?>;

var CHAR_DETAILS     = <?= json_encode(
    $char_details,
    JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE
    | (defined('JSON_INVALID_UTF8_SUBSTITUTE') ? JSON_INVALID_UTF8_SUBSTITUTE : 0)
); ?>;

(function(){
  // Filtro rápido (cliente)
  var quick = document.getElementById('quickFilter');
  if (quick) {
    quick.addEventListener('input', function(){
      var q = (this.value || '').toLowerCase();
      document.querySelectorAll('#tablaPjs tbody tr').forEach(function(tr){
        var nom = tr.getAttribute('data-nombre') || '';
        tr.style.display = nom.indexOf(q) !== -1 ? '' : 'none';
      });
    });
  }

  var mb = document.getElementById('mb');
  var btnNew = document.getElementById('btnNew');
  var btnCancel = document.getElementById('btnCancel');

  var selSistema = document.getElementById('f_system_id');
  var selRaza    = document.getElementById('f_raza');
  var selAusp    = document.getElementById('f_auspicio');
  var selTribu   = document.getElementById('f_tribu');

  var selClan    = document.getElementById('f_clan');
  var selManada  = document.getElementById('f_manada');
  var selTotem   = document.getElementById('f_totem_id');

  var selAfili   = document.getElementById('f_afiliacion');

  var avatar      = document.getElementById('f_avatar');
  var avatarPrev  = document.getElementById('f_avatar_preview');
  var avatarRm    = document.getElementById('f_avatar_remove');

  // Campos complejos
  var fEstado     = document.getElementById('f_estado');
  var fCumple     = document.getElementById('f_cumple');
  var fRango      = document.getElementById('f_rango');
  var fCausa      = document.getElementById('f_causamuerte');
  var fInfo       = document.getElementById('f_infotext');

  // PODERES
  var powTipo  = document.getElementById('pow_tipo');
  var powPoder = document.getElementById('pow_poder');
  var powLvl   = document.getElementById('pow_lvl');
  var powAdd   = document.getElementById('pow_add');
  var powList  = document.getElementById('powersList');

  // MYD
  var mydSel   = document.getElementById('myd_sel');
  var mydLvl   = document.getElementById('myd_lvl');
  var mydAdd   = document.getElementById('myd_add');
  var mydList  = document.getElementById('mydList');

  // INVENTARIO
  var invSel   = document.getElementById('inv_sel');
  var invAdd   = document.getElementById('inv_add');
  var invList  = document.getElementById('invList');
  var traitInputs = document.querySelectorAll('.trait-input');

  function clearSelect(sel, keepFirst){
    while (sel.options.length > (keepFirst?1:0)) sel.remove(keepFirst?1:0);
  }

  function fillSelectFrom(list, sel, placeholder, preselect){
    clearSelect(sel,false);

    if (!list || !list.length){
      sel.disabled = true;
      var o=document.createElement('option'); o.value='0'; o.textContent=placeholder;
      sel.appendChild(o);
      sel.value='0';
      reinitSelect2(sel);
      return false;
    }

    sel.disabled = false;
    var ph=document.createElement('option'); ph.value='0'; ph.textContent='— Elige —';
    sel.appendChild(ph);

    var found=false;
    list.forEach(function(it){
      var o=document.createElement('option'); o.value=String(it.id); o.textContent=it.name;
      sel.appendChild(o);
      if (preselect && String(preselect)===String(it.id)) found=true;
    });

    sel.value = found ? String(preselect) : '0';
    reinitSelect2(sel);
    return found;
  }

  function updateManadas(clanId, preselect){
    var list = MANADAS_BY_CLAN[String(clanId||0)] || [];
    fillSelectFrom(list, selManada, '— Sin manadas en este Clan —', preselect);
  }

  function updateSistemaSets(sys, preRaza, preAusp, preTribu){
    if (!sys){
      clearSelect(selRaza,false); var a1=document.createElement('option'); a1.value='0'; a1.textContent='— Elige un Sistema —'; selRaza.appendChild(a1); selRaza.disabled=true; reinitSelect2(selRaza);
      clearSelect(selAusp,false); var a2=document.createElement('option'); a2.value='0'; a2.textContent='— Elige un Sistema —'; selAusp.appendChild(a2); selAusp.disabled=true; reinitSelect2(selAusp);
      clearSelect(selTribu,false); var a3=document.createElement('option'); a3.value='0'; a3.textContent='— Elige un Sistema —'; selTribu.appendChild(a3); selTribu.disabled=true; reinitSelect2(selTribu);
      return;
    }

    var okR = fillSelectFrom(RAZAS_BY_SYS[sys]||[], selRaza, '— Sin razas para este Sistema —', preRaza);
    var okA = fillSelectFrom(AUSP_BY_SYS[sys]||[],  selAusp, '— Sin auspicios para este Sistema —', preAusp);
    var okT = fillSelectFrom(TRIBUS_BY_SYS[sys]||[], selTribu,'— Sin tribus para este Sistema —', preTribu);

    if (preRaza && !okR){
      var w=document.createElement('option'); w.value=String(preRaza); w.textContent='⚠ (Fuera del Sistema) ID '+preRaza;
      selRaza.appendChild(w); selRaza.value=String(preRaza); selRaza.disabled=false;
      reinitSelect2(selRaza);
    }
    if (preAusp && !okA){
      var w2=document.createElement('option'); w2.value=String(preAusp); w2.textContent='⚠ (Fuera del Sistema) ID '+preAusp;
      selAusp.appendChild(w2); selAusp.value=String(preAusp); selAusp.disabled=false;
      reinitSelect2(selAusp);
    }
    if (preTribu && !okT){
      var w3=document.createElement('option'); w3.value=String(preTribu); w3.textContent='⚠ (Fuera del Sistema) ID '+preTribu;
      selTribu.appendChild(w3); selTribu.value=String(preTribu); selTribu.disabled=false;
      reinitSelect2(selTribu);
    }
  }

  function resetAvatarUI(){
    avatar.value = '';
    avatarRm.checked = false;
    avatarPrev.src = '';
    avatarPrev.style.display = 'none';
  }

  function resetTraits(){
    if (!traitInputs) return;
    traitInputs.forEach(function(inp){ inp.value = '0'; });
  }

  function fillTraits(map){
    resetTraits();
    if (!map) return;
    traitInputs.forEach(function(inp){
      var tid = inp.getAttribute('data-trait-id');
      if (tid && map[tid] !== undefined) {
        inp.value = String(map[tid]);
      }
    });
  }

  function applyTraitOrder(systemId){
    var orderMap = (TRAIT_SET_ORDER && systemId && TRAIT_SET_ORDER[String(systemId)]) ? TRAIT_SET_ORDER[String(systemId)] : {};
    document.querySelectorAll('.traits-group').forEach(function(group){
      var items = Array.prototype.slice.call(group.querySelectorAll('.trait-item'));
      items.sort(function(a,b){
        var aid = a.querySelector('[data-trait-id]')?.getAttribute('data-trait-id') || '';
        var bid = b.querySelector('[data-trait-id]')?.getAttribute('data-trait-id') || '';
        var ao = orderMap[aid] !== undefined ? parseInt(orderMap[aid],10) : 9999;
        var bo = orderMap[bid] !== undefined ? parseInt(orderMap[bid],10) : 9999;
        if (ao !== bo) return ao - bo;
        var an = (a.getAttribute('data-trait-name') || '').toLowerCase();
        var bn = (b.getAttribute('data-trait-name') || '').toLowerCase();
        return an.localeCompare(bn);
      });
      items.forEach(function(it){ group.appendChild(it); });
    });
  }

  // PODERES
  function powersCatalogFor(type){
    if (type==='dones') return DONES_OPTS;
    if (type==='disciplinas') return DISC_OPTS;
    return RITU_OPTS;
  }
  function refreshPowerSelect(){
    var t = powTipo.value;
    fillSelectFrom(powersCatalogFor(t), powPoder, '— Sin poderes —', 0);
  }
  function addPowerChip(type, id, name, lvl){
    var exists = Array.prototype.some.call(powList.querySelectorAll('.power-chip'), function(c){
      return c.dataset.type===type && c.dataset.id===String(id);
    });
    if (exists) return;

    var chip = document.createElement('span');
    chip.className = 'chip power-chip';
    chip.dataset.type = type;
    chip.dataset.id = String(id);
    chip.innerHTML =
      '<span class="tag">'+(type.charAt(0).toUpperCase()+type.slice(1))+'</span>' +
      '<span class="pname">'+name+'</span>' +
      '<input class="inp power-lvl" type="number" name="powers_lvl[]" min="0" max="9" value="'+(lvl||0)+'">' +
      '<input type="hidden" name="powers_type[]" value="'+type+'">' +
      '<input type="hidden" name="powers_id[]" value="'+id+'">' +
      '<button type="button" class="btn btn-red btn-del-power">✖</button>';
    powList.appendChild(chip);
    chip.querySelector('.btn-del-power').addEventListener('click', function(){ chip.remove(); });
  }

  // MYD
  function refreshMydSelect(){
    // Construimos un name amigable: "Nombre — Tipo (Coste)"
    var list = (MYD_OPTS||[]).map(function(it){
      var extra = '';
      if (it.tipo) extra += ' — ' + it.tipo;
      if (it.coste!==undefined && it.coste!==null && String(it.coste)!=='') extra += ' ('+it.coste+')';
      return { id: it.id, name: (it.name || ('#'+it.id)) + extra, tipo: it.tipo, coste: it.coste };
    });
    fillSelectFrom(list, mydSel, '— Sin méritos/defectos —', 0);
  }

  function addMydChip(id, baseName, tipo, coste, nivel){
    var exists = Array.prototype.some.call(mydList.querySelectorAll('.myd-chip'), function(c){
      return c.dataset.id===String(id);
    });
    if (exists) return;

    var tag = (tipo || 'MYD');
    var name = baseName || ('#'+id);

    var chip = document.createElement('span');
    chip.className = 'chip myd-chip';
    chip.dataset.id = String(id);
    chip.dataset.tipo = tag;

    var lvlVal = (nivel===null || nivel===undefined) ? '' : String(nivel);

    chip.innerHTML =
      '<span class="tag">'+tag+'</span>' +
      '<span class="pname">'+name+'</span>' +
      '<input type="hidden" name="myd_id[]" value="'+id+'">' +
      '<input class="inp myd-lvl" type="number" name="myd_lvl[]" min="-99" max="999" placeholder="nivel" value="'+lvlVal+'">' +
      '<button type="button" class="btn btn-red btn-del-myd">✖</button>';

    mydList.appendChild(chip);
    chip.querySelector('.btn-del-myd').addEventListener('click', function(){ chip.remove(); });
  }

  // INVENTARIO
  function refreshInvSelect(){
    var list = (ITEMS_OPTS||[]).map(function(it){
      return { id: it.id, name: (it.name || ('#'+it.id)), tipo: it.tipo };
    });
    fillSelectFrom(list, invSel, '— Sin objetos —', 0);
  }

  function addInvChip(id, name, tipo){
    var exists = Array.prototype.some.call(invList.querySelectorAll('.inv-chip'), function(c){
      return c.dataset.id===String(id);
    });
    if (exists) return;

    var chip = document.createElement('span');
    chip.className = 'chip inv-chip';
    chip.dataset.id = String(id);
    chip.innerHTML =
      '<span class="tag">OBJ</span>' +
      '<span class="pname">'+(name || ('#'+id))+'</span>' +
      '<input type="hidden" name="items_id[]" value="'+id+'">' +
      '<button type="button" class="btn btn-red btn-del-inv">✖</button>';

    invList.appendChild(chip);
    chip.querySelector('.btn-del-inv').addEventListener('click', function(){ chip.remove(); });
  }

  function ensureEstadoOption(val){
    if (!val) return;
    var sel = fEstado;
    var ok = Array.prototype.some.call(sel.options, function(o){ return o.value === val; });
    if (!ok) {
      var opt = document.createElement('option');
      opt.value = val;
      opt.textContent = '⚠ ' + val + ' (no en lista)';
      sel.appendChild(opt);
      reinitSelect2(sel);
    }
    sel.value = val;
    reinitSelect2(sel);
  }

  function openCreate(){
    document.getElementById('modalTitle').textContent = 'Nuevo personaje';
    document.getElementById('crud_action').value = 'create';
    document.getElementById('f_id').value = '0';

    ['nombre','alias','nombregarou','genero_pj','concepto','colortexto','cumple','rango'].forEach(function(k){
      var el=document.getElementById('f_'+k); if(el) el.value='';
    });
    ['cronica','jugador','system_id'].forEach(function(k){
      var el=document.getElementById('f_'+k); if(el) el.value='0';
    });
    var sistLegacy = document.getElementById('f_sistema_legacy');
    if (sistLegacy) sistLegacy.value = '';
    if (selTotem) selTotem.value = '0';
    selAfili.value = '0';

    ensureEstadoOption('En activo');

    fCausa.value = '';
    fInfo.value  = '';

    updateSistemaSets('', 0,0,0);

    selClan.value='0';
    clearSelect(selManada,false);
    var o=document.createElement('option'); o.value='0'; o.textContent='— Selecciona primero un Clan —';
    selManada.appendChild(o); selManada.disabled=true;
    reinitSelect2(selManada);

    resetAvatarUI();

    // reset poderes
    powList.innerHTML = '';
    powTipo.value = 'dones';
    refreshPowerSelect();

    // reset myd
    mydList.innerHTML = '';
    mydLvl.value = '';
    refreshMydSelect();

    // reset inv
    invList.innerHTML = '';
    refreshInvSelect();

    // reset traits
    resetTraits();
    applyTraitOrder(0);

    mb.style.display='flex';
    initSelect2Modal();

    document.getElementById('f_nombre').focus();
  }

  function openEdit(btn){
    document.getElementById('modalTitle').textContent = 'Editar personaje';
    document.getElementById('crud_action').value = 'update';
    var cid = btn.getAttribute('data-id') || '0';
    document.getElementById('f_id').value = cid;

    document.getElementById('f_nombre').value      = btn.getAttribute('data-nombre') || '';
    document.getElementById('f_alias').value       = btn.getAttribute('data-alias') || '';
    document.getElementById('f_nombregarou').value = btn.getAttribute('data-nombregarou') || '';
    document.getElementById('f_genero_pj').value   = btn.getAttribute('data-genero_pj') || '';
    document.getElementById('f_concepto').value    = btn.getAttribute('data-concepto') || '';
    document.getElementById('f_colortexto').value  = btn.getAttribute('data-colortexto') || '';

    document.getElementById('f_cronica').value     = btn.getAttribute('data-cronica') || '0';
    document.getElementById('f_jugador').value     = btn.getAttribute('data-jugador') || '0';
    document.getElementById('f_afiliacion').value  = btn.getAttribute('data-afiliacion') || '0';

    var sistId = parseInt(btn.getAttribute('data-system_id')||'0',10)||0;
    var selS = document.getElementById('f_system_id');
    if (selS) selS.value = String(sistId||0);
    var sistLegacy = document.getElementById('f_sistema_legacy');
    if (sistLegacy) sistLegacy.value = btn.getAttribute('data-sistema_legacy') || '';

    if (selTotem) {
      var tId = parseInt(btn.getAttribute('data-totem_id')||'0',10)||0;
      selTotem.value = String(tId||0);
    }

    var razaId = parseInt(btn.getAttribute('data-raza')||'0',10)||0;
    var ausId  = parseInt(btn.getAttribute('data-auspicio')||'0',10)||0;
    var triId  = parseInt(btn.getAttribute('data-tribu')||'0',10)||0;
    updateSistemaSets(sistId, razaId, ausId, triId);
    applyTraitOrder(sistId);

    var clanId   = parseInt(btn.getAttribute('data-clan') || '0',10) || 0;
    var manadaId = parseInt(btn.getAttribute('data-manada') || '0',10) || 0;
    selClan.value = String(clanId||0);
    updateManadas(clanId, manadaId);

    resetAvatarUI();
    var img = btn.getAttribute('data-img') || '';
    if (img) { avatarPrev.src = img; avatarPrev.style.display='block'; }

    // Poderes: cargar
    powList.innerHTML = '';
    var list = CHAR_POWERS[cid] || [];
    list.forEach(function(p){ addPowerChip(p.t, p.id, p.name, p.lvl); });
    powTipo.value = 'dones';
    refreshPowerSelect();

    // MYD: cargar
    mydList.innerHTML = '';
    mydLvl.value = '';
    refreshMydSelect();
    var ml = CHAR_MYD[cid] || [];
    ml.forEach(function(m){
      addMydChip(m.id, m.name, m.tipo, m.coste, m.nivel);
    });

    // INV: cargar
    invList.innerHTML = '';
    refreshInvSelect();
    var il = CHAR_ITEMS[cid] || [];
    il.forEach(function(it){
      addInvChip(it.id, it.name, it.tipo);
    });

    // Traits: cargar
    fillTraits(CHAR_TRAITS[cid] || {});

    fCausa.value  = '';
    fInfo.value   = '';
    fCumple.value = '';
    fRango.value  = '';
    ensureEstadoOption('En activo');

    var d = CHAR_DETAILS[cid];
    if (d) {
      ensureEstadoOption(d.estado || 'En activo');
      fCausa.value  = d.causamuerte || '';
      fCumple.value = d.cumple || '';
      fRango.value  = d.rango || '';
      fInfo.value   = d.infotext || '';
    }

    mb.style.display='flex';
    initSelect2Modal();

    document.getElementById('f_nombre').focus();
  }

  // Modal binds
  btnNew.addEventListener('click', openCreate);
  btnCancel.addEventListener('click', function(){ mb.style.display='none'; });
  mb.addEventListener('click', function(e){ if (e.target === mb) mb.style.display='none'; });
  Array.prototype.forEach.call(document.querySelectorAll('button[data-edit="1"]'), function(b){
    b.addEventListener('click', function(){ openEdit(b); });
  });

  // Sistema change
  onSelectChange(selSistema, function(){
    var sys = parseInt(selSistema.value,10)||0;
    updateSistemaSets(sys, 0,0,0);
    applyTraitOrder(sys);
  });

  // Clan → manadas
  onSelectChange(selClan, function(){
    var c = parseInt(selClan.value,10)||0;
    if (!c){
      clearSelect(selManada,false);
      var o=document.createElement('option'); o.value='0'; o.textContent='— Selecciona primero un Clan —';
      selManada.appendChild(o); selManada.disabled=true;
      reinitSelect2(selManada);
      return;
    }
    updateManadas(c, 0);
  });

  // Avatar preview / remove
  avatar.addEventListener('change', function(){
    if (avatar.files && avatar.files[0]) {
      avatarPrev.src = URL.createObjectURL(avatar.files[0]);
      avatarPrev.style.display = 'block';
      avatarRm.checked = false;
    } else if (!avatarRm.checked && !avatarPrev.src) {
      avatarPrev.style.display = 'none';
    }
  });
  avatarRm.addEventListener('change', function(){
    if (avatarRm.checked) {
      avatar.value = '';
      avatarPrev.src = '';
      avatarPrev.style.display = 'none';
    }
  });

  // Validación rápida cliente
  document.getElementById('formCrud').addEventListener('submit', function(ev){
    var c = parseInt(selClan.value,10)||0;
    var m = parseInt(selManada.value,10)||0;
    if (!c) { alert('Debes seleccionar un Clan.'); ev.preventDefault(); return; }
    if (m && MANADA_ID_TO_CLAN[String(m)] && parseInt(MANADA_ID_TO_CLAN[String(m)],10)!==c) {
      alert('La Manada seleccionada no pertenece al Clan elegido.');
      ev.preventDefault(); return;
    }
    var sys = parseInt(selSistema.value,10)||0;
    var rz = parseInt(selRaza.value,10)||0;
    var au = parseInt(selAusp.value,10)||0;
    var tr = parseInt(selTribu.value,10)||0;
    if (sys){
      if (rz && RAZA_ID_TO_SYS[String(rz)]   && parseInt(RAZA_ID_TO_SYS[String(rz)],10)   !== sys){ alert('La Raza no pertenece al Sistema elegido.'); ev.preventDefault(); return; }
      if (au && AUSP_ID_TO_SYS[String(au)]   && parseInt(AUSP_ID_TO_SYS[String(au)],10)   !== sys){ alert('El Auspicio no pertenece al Sistema elegido.'); ev.preventDefault(); return; }
      if (tr && TRIBU_ID_TO_SYS[String(tr)]  && parseInt(TRIBU_ID_TO_SYS[String(tr)],10)  !== sys){ alert('La Tribu no pertenece al Sistema elegido.'); ev.preventDefault(); return; }
    }
    if (!fEstado.value) { alert('Debes seleccionar un Estado.'); ev.preventDefault(); return; }
  });

  // PODERES UI
  onSelectChange(powTipo, function(){ refreshPowerSelect(); });
  refreshPowerSelect();
  reinitSelect2(powTipo);
  reinitSelect2(powPoder);

  powAdd.addEventListener('click', function(){
    var t = powTipo.value;
    var pid = parseInt(powPoder.value,10)||0;
    if (!pid){ alert('Elige un poder.'); return; }
    var nm = powPoder.options[powPoder.selectedIndex].textContent;
    var lvl = parseInt(powLvl.value,10); if (isNaN(lvl)) lvl=0; lvl=Math.max(0,Math.min(9,lvl));
    addPowerChip(t, pid, nm, lvl);
  });

  // MYD UI
  refreshMydSelect();
  reinitSelect2(mydSel);

  mydAdd.addEventListener('click', function(){
    var mid = parseInt(mydSel.value,10)||0;
    if (!mid){ alert('Elige un Mérito o Defecto.'); return; }

    var base = null, tipo=null, coste=null;
    for (var i=0;i<MYD_OPTS.length;i++){
      if (parseInt(MYD_OPTS[i].id,10)===mid){
        base = MYD_OPTS[i].name;
        tipo = MYD_OPTS[i].tipo;
        coste= MYD_OPTS[i].coste;
        break;
      }
    }

    var raw = (mydLvl.value||'').trim();
    var nivel = (raw==='') ? null : parseInt(raw,10);
    if (raw!=='' && isNaN(nivel)) nivel = null;

    addMydChip(mid, base, tipo, coste, nivel);
    mydLvl.value = '';
  });

  // INVENTARIO UI
  refreshInvSelect();
  reinitSelect2(invSel);

  invAdd.addEventListener('click', function(){
    var iid = parseInt(invSel.value,10)||0;
    if (!iid){ alert('Elige un objeto.'); return; }

    var nm=null, tp=0;
    for (var i=0;i<ITEMS_OPTS.length;i++){
      if (parseInt(ITEMS_OPTS[i].id,10)===iid){
        nm = ITEMS_OPTS[i].name;
        tp = ITEMS_OPTS[i].tipo || 0;
        break;
      }
    }
    addInvChip(iid, nm, tp);
  });

})();
</script>
