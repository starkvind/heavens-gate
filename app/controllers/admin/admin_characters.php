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
if (!function_exists('fetchPairs')) {
    function fetchPairs(mysqli $link, string $sql): array {
        $out = [];
        if (!$rs = $link->query($sql)) return $out;
        while ($row = $rs->fetch_assoc()) {
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) continue;
            $out[$id] = (string)($row['name'] ?? '');
        }
        $rs->close();
        return $out;
    }
}
if (!function_exists('pjs_table_exists')) {
    function pjs_table_exists(mysqli $link, string $table): bool {
        $t = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        if ($t === '') return false;
        $sql = "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . mysqli_real_escape_string($link, $t) . "' LIMIT 1";
        $rs = $link->query($sql);
        if (!$rs) return false;
        $ok = ($rs->num_rows > 0);
        $rs->close();
        return $ok;
    }
}
if (!function_exists('pjs_table_has_column')) {
    function pjs_table_has_column(mysqli $link, string $table, string $column): bool {
        $t = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $c = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        if ($t === '' || $c === '') return false;
        $sql = "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . mysqli_real_escape_string($link, $t) . "' AND COLUMN_NAME = '" . mysqli_real_escape_string($link, $c) . "' LIMIT 1";
        $rs = $link->query($sql);
        if (!$rs) return false;
        $ok = ($rs->num_rows > 0);
        $rs->close();
        return $ok;
    }
}
if (!function_exists('pjs_column_char_maxlen')) {
    function pjs_column_char_maxlen(mysqli $link, string $table, string $column): int {
        $t = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $c = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        if ($t === '' || $c === '') return 0;
        $sql = "SELECT COALESCE(CHARACTER_MAXIMUM_LENGTH, 0) AS m FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . mysqli_real_escape_string($link, $t) . "' AND COLUMN_NAME = '" . mysqli_real_escape_string($link, $c) . "' LIMIT 1";
        $rs = $link->query($sql);
        if (!$rs) return 0;
        $row = $rs->fetch_assoc();
        $rs->close();
        return (int)($row['m'] ?? 0);
    }
}
if (!function_exists('sync_character_bridges')) {
    function sync_character_bridges(mysqli $link, int $characterId, int $groupId, int $organizationId): void {
        if ($characterId <= 0) return;

        $hasGroups = pjs_table_exists($link, 'bridge_characters_groups');
        $hasOrgs = pjs_table_exists($link, 'bridge_characters_organizations');
        $groupsHasActive = $hasGroups && pjs_table_has_column($link, 'bridge_characters_groups', 'is_active');
        $orgsHasActive = $hasOrgs && pjs_table_has_column($link, 'bridge_characters_organizations', 'is_active');

        if ($hasGroups) {
            if ($groupsHasActive) {
                if ($groupId > 0) {
                    if ($st = $link->prepare("UPDATE bridge_characters_groups SET is_active=0 WHERE character_id=? AND group_id<>?")) {
                        $st->bind_param("ii", $characterId, $groupId);
                        $st->execute();
                        $st->close();
                    }
                    if ($st = $link->prepare("INSERT INTO bridge_characters_groups (character_id, group_id, is_active, position) VALUES (?, ?, 1, '') ON DUPLICATE KEY UPDATE is_active=1")) {
                        $st->bind_param("ii", $characterId, $groupId);
                        $st->execute();
                        $st->close();
                    }
                } else {
                    if ($st = $link->prepare("UPDATE bridge_characters_groups SET is_active=0 WHERE character_id=?")) {
                        $st->bind_param("i", $characterId);
                        $st->execute();
                        $st->close();
                    }
                }
            } else {
                if ($st = $link->prepare("DELETE FROM bridge_characters_groups WHERE character_id=?")) {
                    $st->bind_param("i", $characterId);
                    $st->execute();
                    $st->close();
                }
                if ($groupId > 0) {
                    if ($st = $link->prepare("INSERT INTO bridge_characters_groups (character_id, group_id, position) VALUES (?, ?, '')")) {
                        $st->bind_param("ii", $characterId, $groupId);
                        $st->execute();
                        $st->close();
                    }
                }
            }
        }

        if ($hasOrgs) {
            if ($orgsHasActive) {
                if ($organizationId > 0) {
                    if ($st = $link->prepare("UPDATE bridge_characters_organizations SET is_active=0 WHERE character_id=? AND organization_id<>?")) {
                        $st->bind_param("ii", $characterId, $organizationId);
                        $st->execute();
                        $st->close();
                    }
                    if ($st = $link->prepare("INSERT INTO bridge_characters_organizations (character_id, organization_id, is_active, role) VALUES (?, ?, 1, '') ON DUPLICATE KEY UPDATE is_active=1")) {
                        $st->bind_param("ii", $characterId, $organizationId);
                        $st->execute();
                        $st->close();
                    }
                } else {
                    if ($st = $link->prepare("UPDATE bridge_characters_organizations SET is_active=0 WHERE character_id=?")) {
                        $st->bind_param("i", $characterId);
                        $st->execute();
                        $st->close();
                    }
                }
            } else {
                if ($st = $link->prepare("DELETE FROM bridge_characters_organizations WHERE character_id=?")) {
                    $st->bind_param("i", $characterId);
                    $st->execute();
                    $st->close();
                }
                if ($organizationId > 0) {
                    if ($st = $link->prepare("INSERT INTO bridge_characters_organizations (character_id, organization_id, role) VALUES (?, ?, '')")) {
                        $st->bind_param("ii", $characterId, $organizationId);
                        $st->execute();
                        $st->close();
                    }
                }
            }
        }
    }
}
if (!function_exists('save_character_powers')) {
    function save_character_powers(mysqli $link, int $characterId, array $types, array $ids, array $levels): array {
        $res = ['inserted' => 0, 'skipped' => 0];
        if ($characterId <= 0 || !pjs_table_exists($link, 'bridge_characters_powers')) return $res;

        $allowed = ['dones' => true, 'disciplinas' => true, 'rituales' => true];
        $n = min(count($types), count($ids), count($levels));
        $rows = [];
        for ($i = 0; $i < $n; $i++) {
            $type = strtolower(trim((string)$types[$i]));
            $pid = (int)$ids[$i];
            $lvl = (int)$levels[$i];
            if (!isset($allowed[$type]) || $pid <= 0) { $res['skipped']++; continue; }
            if ($lvl < 0) $lvl = 0;
            if ($lvl > 9) $lvl = 9;
            $rows[$type . ':' . $pid] = ['type' => $type, 'id' => $pid, 'lvl' => $lvl];
        }

        if ($st = $link->prepare("DELETE FROM bridge_characters_powers WHERE character_id=?")) {
            $st->bind_param("i", $characterId);
            $st->execute();
            $st->close();
        }

        if (!empty($rows) && ($st = $link->prepare("INSERT INTO bridge_characters_powers (character_id, power_kind, power_id, power_level) VALUES (?,?,?,?)"))) {
            foreach ($rows as $r) {
                $type = $r['type'];
                $pid = $r['id'];
                $lvl = $r['lvl'];
                $st->bind_param("isii", $characterId, $type, $pid, $lvl);
                if ($st->execute()) $res['inserted']++; else $res['skipped']++;
            }
            $st->close();
        }
        return $res;
    }
}
if (!function_exists('save_character_merits_flaws')) {
    function save_character_merits_flaws(mysqli $link, int $characterId, array $ids, array $levelsRaw): array {
        $res = ['inserted' => 0, 'skipped' => 0];
        if ($characterId <= 0 || !pjs_table_exists($link, 'bridge_characters_merits_flaws')) return $res;

        $n = max(count($ids), count($levelsRaw));
        $rows = [];
        for ($i = 0; $i < $n; $i++) {
            $mid = isset($ids[$i]) ? (int)$ids[$i] : 0;
            if ($mid <= 0) { $res['skipped']++; continue; }
            $lvlRaw = $levelsRaw[$i] ?? '';
            $lvlRaw = is_string($lvlRaw) ? trim($lvlRaw) : $lvlRaw;
            $lvl = null;
            if ($lvlRaw !== '' && $lvlRaw !== null) {
                $lvl = (int)$lvlRaw;
                if ($lvl < -99) $lvl = -99;
                if ($lvl > 99) $lvl = 99;
            }
            $rows[$mid] = $lvl;
        }

        if ($st = $link->prepare("DELETE FROM bridge_characters_merits_flaws WHERE character_id=?")) {
            $st->bind_param("i", $characterId);
            $st->execute();
            $st->close();
        }

        $stLvl = $link->prepare("INSERT INTO bridge_characters_merits_flaws (character_id, merit_flaw_id, level) VALUES (?,?,?)");
        $stNull = $link->prepare("INSERT INTO bridge_characters_merits_flaws (character_id, merit_flaw_id, level) VALUES (?, ?, NULL)");
        if (!empty($rows) && ($stLvl || $stNull)) {
            foreach ($rows as $mid => $lvl) {
                if ($lvl === null) {
                    if ($stNull) {
                        $stNull->bind_param("ii", $characterId, $mid);
                        if ($stNull->execute()) $res['inserted']++; else $res['skipped']++;
                    } else {
                        $res['skipped']++;
                    }
                } else {
                    if ($stLvl) {
                        $stLvl->bind_param("iii", $characterId, $mid, $lvl);
                        if ($stLvl->execute()) $res['inserted']++; else $res['skipped']++;
                    } else {
                        $res['skipped']++;
                    }
                }
            }
            if ($stLvl) $stLvl->close();
            if ($stNull) $stNull->close();
        }
        return $res;
    }
}
if (!function_exists('save_character_items')) {
    function save_character_items(mysqli $link, int $characterId, array $ids): array {
        $res = ['inserted' => 0, 'skipped' => 0];
        if ($characterId <= 0 || !pjs_table_exists($link, 'bridge_characters_items')) return $res;

        $rows = [];
        foreach ($ids as $id) {
            $iid = (int)$id;
            if ($iid <= 0) { $res['skipped']++; continue; }
            $rows[$iid] = true;
        }

        if ($st = $link->prepare("DELETE FROM bridge_characters_items WHERE character_id=?")) {
            $st->bind_param("i", $characterId);
            $st->execute();
            $st->close();
        }

        if (!empty($rows) && ($st = $link->prepare("INSERT INTO bridge_characters_items (character_id, item_id) VALUES (?,?)"))) {
            foreach (array_keys($rows) as $iid) {
                $st->bind_param("ii", $characterId, $iid);
                if ($st->execute()) $res['inserted']++; else $res['skipped']++;
            }
            $st->close();
        }
        return $res;
    }
}
if (!function_exists('save_character_traits')) {
    function save_character_traits(mysqli $link, int $characterId, array $traits, string $source = 'admin', ?string $createdBy = null): array {
        $res = ['updated' => 0];
        if ($characterId <= 0 || !pjs_table_exists($link, 'bridge_characters_traits')) return $res;

        $hasLog = pjs_table_exists($link, 'bridge_characters_traits_log');
        $existing = [];
        if ($st = $link->prepare("SELECT trait_id, value FROM bridge_characters_traits WHERE character_id=?")) {
            $st->bind_param("i", $characterId);
            $st->execute();
            if ($rs = $st->get_result()) {
                while ($r = $rs->fetch_assoc()) $existing[(int)$r['trait_id']] = (int)$r['value'];
            }
            $st->close();
        }

        $normalized = [];
        foreach ($traits as $tid => $v) {
            $tid = (int)$tid;
            if ($tid <= 0) continue;
            $nv = (int)$v;
            if ($nv < 0) $nv = 0;
            if ($nv > 10) $nv = 10;
            $normalized[$tid] = $nv;
        }

        $ins = $link->prepare("INSERT INTO bridge_characters_traits (character_id, trait_id, value) VALUES (?,?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)");
        $del = $link->prepare("DELETE FROM bridge_characters_traits WHERE character_id=? AND trait_id=?");
        $log = null;
        if ($hasLog) {
            $log = $link->prepare("INSERT INTO bridge_characters_traits_log (character_id, trait_id, old_value, new_value, delta, reason, source, created_by) VALUES (?,?,?,?,?,?,?,?)");
        }

        foreach ($normalized as $tid => $nv) {
            $ov = array_key_exists($tid, $existing) ? (int)$existing[$tid] : null;
            $delta = ($ov === null) ? $nv : ($nv - $ov);
            if ($ov !== null && $ov === $nv) continue;

            if ($ins) {
                $ins->bind_param("iii", $characterId, $tid, $nv);
                if ($ins->execute()) $res['updated']++;
            }
            if ($log) {
                $reason = ($ov === null) ? 'admin initial' : 'admin update';
                $log->bind_param("iiiiisss", $characterId, $tid, $ov, $nv, $delta, $reason, $source, $createdBy);
                $log->execute();
            }
            unset($existing[$tid]);
        }

        foreach ($existing as $tid => $ov) {
            if ($del) {
                $del->bind_param("ii", $characterId, $tid);
                if ($del->execute()) $res['updated']++;
            }
            if ($log) {
                $nv = null;
                $delta = -((int)$ov);
                $reason = 'admin update';
                $log->bind_param("iiiiisss", $characterId, $tid, $ov, $nv, $delta, $reason, $source, $createdBy);
                $log->execute();
            }
        }

        if ($ins) $ins->close();
        if ($del) $del->close();
        if ($log) $log->close();
        return $res;
    }
}
if (!function_exists('save_character_resources')) {
    function save_character_resources(
        mysqli $link,
        int $characterId,
        int $systemId,
        array $rows,
        array $resourcesBySystem,
        bool $hasBridge,
        bool $hasLog,
        string $source = 'admin',
        ?string $createdBy = null
    ): array {
        $res = ['saved' => 0, 'forced' => 0, 'disabled' => false, 'error' => null];
        if ($characterId <= 0 || !$hasBridge || !pjs_table_exists($link, 'bridge_characters_system_resources')) {
            $res['disabled'] = true;
            return $res;
        }

        $target = [];
        foreach ($rows as $rid => $vals) {
            $rid = (int)$rid;
            if ($rid <= 0) continue;
            $perm = (int)($vals['perm'] ?? 0);
            $temp = (int)($vals['temp'] ?? 0);
            if ($perm < 0) $perm = 0;
            if ($temp < 0) $temp = 0;
            $target[$rid] = ['perm' => $perm, 'temp' => $temp];
        }

        if ($systemId > 0 && !empty($resourcesBySystem[$systemId])) {
            foreach ($resourcesBySystem[$systemId] as $r) {
                $rid = (int)($r['id'] ?? 0);
                if ($rid <= 0) continue;
                if (!isset($target[$rid])) {
                    $target[$rid] = ['perm' => 0, 'temp' => 0];
                    $res['forced']++;
                }
            }
        }

        $existing = [];
        if ($st = $link->prepare("SELECT resource_id, value_permanent, value_temporary FROM bridge_characters_system_resources WHERE character_id=?")) {
            $st->bind_param("i", $characterId);
            $st->execute();
            if ($rs = $st->get_result()) {
                while ($r = $rs->fetch_assoc()) {
                    $rid = (int)$r['resource_id'];
                    $existing[$rid] = ['perm' => (int)$r['value_permanent'], 'temp' => (int)$r['value_temporary']];
                }
            }
            $st->close();
        }

        $up = $link->prepare("INSERT INTO bridge_characters_system_resources (character_id, resource_id, value_permanent, value_temporary) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE value_permanent=VALUES(value_permanent), value_temporary=VALUES(value_temporary)");
        $del = $link->prepare("DELETE FROM bridge_characters_system_resources WHERE character_id=? AND resource_id=?");
        $lg = null;
        if ($hasLog && pjs_table_exists($link, 'bridge_characters_system_resources_log')) {
            $lg = $link->prepare("INSERT INTO bridge_characters_system_resources_log (character_id, resource_id, old_permanent, new_permanent, old_temporary, new_temporary, delta_permanent, delta_temporary, reason, source, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        }

        foreach ($target as $rid => $vals) {
            $np = (int)$vals['perm'];
            $nt = (int)$vals['temp'];
            $ep = $existing[$rid]['perm'] ?? null;
            $et = $existing[$rid]['temp'] ?? null;
            if ($ep !== null && $ep === $np && $et === $nt) { unset($existing[$rid]); continue; }

            if ($up) {
                $up->bind_param("iiii", $characterId, $rid, $np, $nt);
                if ($up->execute()) $res['saved']++;
            }
            if ($lg) {
                $dp = ($ep === null) ? $np : ($np - $ep);
                $dt = ($et === null) ? $nt : ($nt - $et);
                $reason = ($ep === null && $et === null) ? 'admin initial' : 'admin update';
                $lg->bind_param("iiiiiiiisss", $characterId, $rid, $ep, $np, $et, $nt, $dp, $dt, $reason, $source, $createdBy);
                $lg->execute();
            }
            unset($existing[$rid]);
        }

        foreach ($existing as $rid => $old) {
            if ($del) {
                $del->bind_param("ii", $characterId, $rid);
                if ($del->execute()) $res['saved']++;
            }
            if ($lg) {
                $ep = (int)$old['perm']; $et = (int)$old['temp'];
                $np = null; $nt = null; $dp = -$ep; $dt = -$et;
                $reason = 'admin update';
                $lg->bind_param("iiiiiiiisss", $characterId, $rid, $ep, $np, $et, $nt, $dp, $dt, $reason, $source, $createdBy);
                $lg->execute();
            }
        }

        if ($up) $up->close();
        if ($del) $del->close();
        if ($lg) $lg->close();
        return $res;
    }
}
if (!function_exists('slugify')) {
    function slugify($text): string {
        $text = trim((string)$text);
        if (function_exists('iconv')) {
            $tmp = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if ($tmp !== false) $text = (string)$tmp;
        }
        $text = preg_replace('~[^\\pL\\d]+~u', '-', $text);
        $text = trim((string)$text, '-');
        $text = strtolower((string)$text);
        $text = preg_replace('~[^-a-z0-9]+~', '', (string)$text);
        return $text !== '' ? $text : 'pj';
    }
}
if (!function_exists('save_avatar_file')) {
    function save_avatar_file(array $file, int $pjId, string $displayName, string $uploadDir, string $urlBase): array {
        if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) return ['ok'=>false,'msg'=>'no_file'];
        if ((int)$file['error'] !== UPLOAD_ERR_OK) return ['ok'=>false,'msg'=>'Upload error (#'.(int)$file['error'].')'];
        if ((int)($file['size'] ?? 0) > 5 * 1024 * 1024) return ['ok'=>false,'msg'=>'File exceeds 5 MB'];

        $tmp = (string)($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) return ['ok'=>false,'msg'=>'Invalid upload'];

        $mime = '';
        if (function_exists('finfo_open')) {
            $fi = finfo_open(FILEINFO_MIME_TYPE);
            if ($fi) {
                $mime = (string)finfo_file($fi, $tmp);
                finfo_close($fi);
            }
        }
        if ($mime === '') {
            $gi = @getimagesize($tmp);
            $mime = (string)($gi['mime'] ?? '');
        }

        $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
        if (!isset($allowed[$mime])) return ['ok'=>false,'msg'=>'Unsupported format (JPG/PNG/GIF/WebP only)'];

        if (!is_dir($uploadDir)) @mkdir($uploadDir, 0775, true);
        $ext = $allowed[$mime];
        $slug = slugify($displayName !== '' ? $displayName : 'pj');
        $name = sprintf('pj-%d-%s-%s.%s', $pjId, $slug, date('YmdHis'), $ext);
        $dst = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $name;
        if (!@move_uploaded_file($tmp, $dst)) return ['ok'=>false,'msg'=>'Could not move uploaded file'];
        @chmod($dst, 0644);

        return ['ok'=>true,'url'=>rtrim($urlBase, '/').'/'.$name,'path'=>$dst];
    }
}
if (!function_exists('safe_unlink_avatar')) {
    function safe_unlink_avatar(string $relUrl, string $uploadDir): void {
        if ($relUrl === '') return;
        $docroot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? __DIR__, '/');
        $rel = '/' . ltrim($relUrl, '/');
        $abs = (strpos($rel, '/img/') === 0) ? ($docroot . '/public' . $rel) : ($docroot . $rel);
        $base = realpath($uploadDir);
        $real = @realpath($abs);
        if ($base && $real && strpos($real, $base) === 0 && is_file($real)) {
            @unlink($real);
        }
    }
}

/* -------------------------------------------------
   Estado (catalogo + fallback legacy)
------------------------------------------------- */
$estado_opts = [];
$estado_set = [];
$status_label_to_id = [];
$default_status_label = 'En activo';
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
      if ($label === '') continue;
      $estado_opts[$label] = $label;
      $estado_set[$label] = true;
      $status_label_to_id[$label] = $sid;
      if ((int)($row['is_active'] ?? 0) === 1 && $default_status_label === 'En activo') {
        $default_status_label = $label;
      }
    }
    $qst->close();
  }
}
if (empty($estado_opts)) {
  if ($rs = $link->query("SELECT status FROM fact_characters GROUP BY 1 ORDER BY 1")) {
    while ($row = $rs->fetch_assoc()) {
      $val = (string)($row['status'] ?? '');
      if ($val === '') continue;
      $estado_opts[$val] = $val;
      $estado_set[$val] = true;
    }
    $rs->close();
  }
}
if (!isset($estado_opts[$default_status_label])) $estado_opts[$default_status_label] = $default_status_label;
if (!isset($estado_set[$default_status_label])) $estado_set[$default_status_label] = true;

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

        if ($st = $link->prepare("SELECT COALESCE(dcs.label, fc.status) AS status, fc.status_id, fc.birthdate_text, fc.rank, fc.info_text FROM fact_characters fc LEFT JOIN dim_character_status dcs ON dcs.id = fc.status_id WHERE fc.id=? LIMIT 1")) {
            $st->bind_param("i", $id);
            $st->execute();
            $rs = $st->get_result();

            if ($rs && ($row = $rs->fetch_assoc())) {
                echo json_encode([
                    'ok'          => true,
                    'status'      => (string)($row['status'] ?? ''),
                    'status_id'   => (int)($row['status_id'] ?? 0),
                    'causamuerte' => '',
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
    $action      = $_POST['crud_action'];
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
    $status      = (string)($_POST['status'] ?? '');
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
    if ($status === '') $status = $default_status_label;
    $status_id = isset($status_label_to_id[$status]) ? (int)$status_label_to_id[$status] : 0;

    // Validaciones
    if ($clan <= 0) $flash[] = ['type'=>'error','msg'=>'[WARN] Debes seleccionar un Clan.'];
    if (!isset($estado_set[$status]) && $status_id <= 0) $flash[] = ['type'=>'error','msg'=>'? El status no es válido.'];
    if ($manada > 0) {
        $clan_of_manada = $manadas_map_id_to_clan[$manada] ?? 0;
        if ($clan_of_manada !== $clan) {
            $flash[] = ['type'=>'error','msg'=>'[WARN] La Manada seleccionada no pertenece al Clan elegido.'];
        }
    }
    if ($system_id > 0) {
        if ($raza     > 0 && isset($raza_id_to_sys[$raza])     && (int)$raza_id_to_sys[$raza]     !== (int)$system_id) $flash[]=['type'=>'error','msg'=>'[WARN] La Raza no pertenece al Sistema elegido.'];
        if ($auspice_id > 0 && isset($ausp_id_to_sys[$auspice_id]) && (int)$ausp_id_to_sys[$auspice_id] !== (int)$system_id) $flash[]=['type'=>'error','msg'=>'[WARN] El Auspicio no pertenece al Sistema elegido.'];
        if ($tribe_id    > 0 && isset($tribu_id_to_sys[$tribe_id])   && (int)$tribu_id_to_sys[$tribe_id]   !== (int)$system_id) $flash[]=['type'=>'error','msg'=>'[WARN] La Tribu no pertenece al Sistema elegido.'];
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

    // Avatar actual (para update)
    $current_img = '';
    if ($action === 'update' && $id > 0) {
        if ($st = $link->prepare("SELECT image_url FROM fact_characters WHERE id=?")) {
            $st->bind_param("i",$id); $st->execute();
            $rs = $st->get_result(); if ($row=$rs->fetch_assoc()) $current_img = (string)($row['image_url'] ?? '');
            $st->close();
        }
    }

    if ($action === 'create') {
        if ($nombre === '') $flash[] = ['type'=>'error','msg'=>'[WARN] El campo \"nombre\" es obligatorio.'];
        if (!array_filter($flash, fn($f)=>$f['type']==='error')) {
            $sql = "INSERT INTO fact_characters
                (name, alias, garou_name, gender, concept, chronicle_id, player_id, character_type_id, image_url, notes, text_color, `$character_kind_column`, system_id,
                 totem_id, status, status_id, birthdate_text, rank, info_text, breed_id, auspice_id, tribe_id, nature_id, demeanor_id)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
            if ($stmt = $link->prepare($sql)) {
                $img='';
                $stmt->bind_param(
                    "sssssiiissssiisisssiiiii",
                    $nombre, $alias, $nombregarou, $gender, $concept,
                    $cronica, $jugador, $afili,
                    $img, $notas, $text_color, $kind, $system_id,
                    $totem_id,
                    $status, $status_id, $cumple, $rango, $infotext,
                    $raza, $auspice_id, $tribe_id, $nature_id, $demeanor_id
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
                  status=?, status_id=?, birthdate_text=?, rank=?, info_text=?
                  WHERE id=?";

          if ($stmt = $link->prepare($sql)) {

              // 13 strings/ints + 5 strings + id (int)
              $stmt->bind_param(
                  "sssssiiiissiiiiiisisssi",
                  $nombre, $alias, $nombregarou, $gender, $concept,
                  $cronica, $jugador, $afili, $system_id, $text_color,
                  $kind,
                  $raza, $auspice_id, $tribe_id, $nature_id, $demeanor_id,
                  $totem_id,
                  $status, $status_id, $cumple, $rango, $infotext,
                  $id
              );

              if ($stmt->execute()) {
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
    $qdet = $link->query("SELECT fc.id, COALESCE(dcs.label, fc.status) AS status, fc.status_id, fc.birthdate_text, fc.rank, fc.info_text FROM fact_characters fc LEFT JOIN dim_character_status dcs ON dcs.id = fc.status_id WHERE fc.id IN ($in)");
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

    <form method="get" class="adm-flex-8-center-spaced">
      <input type="hidden" name="p" value="talim">
      <input type="hidden" name="s" value="admin_characters">
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
      <label class="adm-ml-auto-left">Filtro rápido
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
        <th class="adm-w-60">ID</th>
        <th>Nombre</th>
        <th>Jugador</th>
        <th>Crónica</th>
        <th>Sistema</th>
        <th class="adm-w-120">Acciones</th>
      </tr>
    </thead>
    <tbody>
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
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($rows)): ?>
        <tr><td colspan="6" class="adm-color-muted">(Sin resultados)</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <div class="pager">
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
            <select class="select" name="status" id="f_estado" required>
              <option value="">— Selecciona —</option>
              <?php foreach ($estado_opts as $val=>$label): ?>
                <option value="<?= h($val) ?>"><?= h($label==='' ? '(vacío)' : $label) ?></option>
              <?php endforeach; ?>
            </select>
            <span class="small-note">Lista desde: dim_character_status (fallback: fact_characters.status)</span>
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

<script>
function syncSelect2Palette(){
  var mbEl = document.getElementById('mb');
  if (!mbEl) return;
  var probe = mbEl.querySelector('select.select, select');
  if (!probe) return;
  var cs = window.getComputedStyle(probe);
  var bg = (cs.backgroundColor || '').trim() || '#000033';
  var fg = (cs.color || '').trim() || '#ffffff';
  var bd = (cs.borderColor || '').trim() || '#333333';
  mbEl.style.setProperty('--adm-s2-bg', bg);
  mbEl.style.setProperty('--adm-s2-color', fg);
  mbEl.style.setProperty('--adm-s2-border', bd);
}

/* ------------ Select2 init (dentro del modal) ------------ */
function initSelect2Modal(){
  if (!window.jQuery || !jQuery.fn || !jQuery.fn.select2) return;
  syncSelect2Palette();

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

// RECURSOS
var RESOURCE_OPTS    = <?= json_encode($opts_resources_full, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
var SYS_RESOURCES_BY_SYS = <?= json_encode($sys_resources_by_system, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
var CHAR_RESOURCES   = <?= json_encode($char_resources, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;

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
	  var selNature  = document.getElementById('f_nature_id');
	  var selDemeanor= document.getElementById('f_demeanor_id');

  var selClan    = document.getElementById('f_clan');
  var selManada  = document.getElementById('f_manada');
  var selTotem   = document.getElementById('f_totem_id');

  var selAfili   = document.getElementById('f_afiliacion');
  var selKind    = document.getElementById('f_kind');

  var avatar      = document.getElementById('f_avatar');
  var avatarPrev  = document.getElementById('f_avatar_preview');
  var avatarRm    = document.getElementById('f_avatar_remove');

  // Campos complejos
  var fEstado     = document.getElementById('f_estado');
  var fCumple     = document.getElementById('f_cumple');
  var fRango      = document.getElementById('f_rango');
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

  // RECURSOS
  var resSel   = document.getElementById('res_sel');
  var resAdd   = document.getElementById('res_add');
  var resList  = document.getElementById('resourceList');
  var traitInputs = document.querySelectorAll('.trait-input');
  var pjOnlyBlocks = document.querySelectorAll('.kind-pj-only');
  var noMonsterBlocks = document.querySelectorAll('.kind-no-monster');

  function normalizeText(v){
    v = String(v || '').toLowerCase();
    if (v.normalize) {
      v = v.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }
    return v;
  }

  function applyMonsterTraitFilter(kind){
    var isMonster = (normalizeText(kind) === 'monster' || normalizeText(kind) === 'mon');
    document.querySelectorAll('.trait-item').forEach(function(item){
      var k = normalizeText(item.getAttribute('data-trait-kind') || '');
      var c = normalizeText(item.getAttribute('data-trait-classification') || '');
      var hide = false;
      if (isMonster) {
        hide = (k === 'trasfondos') || (
          (k === 'talentos' || k === 'tecnicas' || k === 'conocimientos') &&
          c.indexOf('002 secundarias') === 0
        );
      }
      item.style.display = hide ? 'none' : '';
      if (hide) {
        var inp = item.querySelector('.trait-input');
        if (inp) inp.value = '0';
      }
    });
  }

  function applyKindVisibility(kind){
    var k = String(kind || '').toLowerCase();
    var isPj = (k !== 'pnj');
    var isMonster = (normalizeText(k) === 'monster' || normalizeText(k) === 'mon');
    pjOnlyBlocks.forEach(function(block){
      block.style.display = isPj ? '' : 'none';
    });
    noMonsterBlocks.forEach(function(block){
      block.style.display = (isPj && !isMonster) ? '' : 'none';
    });
    applyMonsterTraitFilter(kind);
  }

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
      var w=document.createElement('option'); w.value=String(preRaza); w.textContent='[WARN] (Fuera del Sistema) ID '+preRaza;
      selRaza.appendChild(w); selRaza.value=String(preRaza); selRaza.disabled=false;
      reinitSelect2(selRaza);
    }
    if (preAusp && !okA){
      var w2=document.createElement('option'); w2.value=String(preAusp); w2.textContent='[WARN] (Fuera del Sistema) ID '+preAusp;
      selAusp.appendChild(w2); selAusp.value=String(preAusp); selAusp.disabled=false;
      reinitSelect2(selAusp);
    }
    if (preTribu && !okT){
      var w3=document.createElement('option'); w3.value=String(preTribu); w3.textContent='[WARN] (Fuera del Sistema) ID '+preTribu;
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
      '<button type="button" class="btn btn-red btn-del-power">X</button>';
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
      '<button type="button" class="btn btn-red btn-del-myd">X</button>';

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
      '<button type="button" class="btn btn-red btn-del-inv">X</button>';

    invList.appendChild(chip);
    chip.querySelector('.btn-del-inv').addEventListener('click', function(){ chip.remove(); });
  }

  // RECURSOS
  function refreshResourceSelect(){
    var list = (RESOURCE_OPTS||[]).map(function(it){
      return { id: it.id, name: (it.name || ('#'+it.id)) + ' [' + (it.kind || '') + ']' };
    });
    fillSelectFrom(list, resSel, '— Sin recursos —', 0);
  }

  function getResourceMeta(rid){
    rid = parseInt(rid, 10) || 0;
    for (var i=0; i<(RESOURCE_OPTS||[]).length; i++) {
      var r = RESOURCE_OPTS[i];
      if ((parseInt(r.id,10)||0) === rid) return r;
    }
    return null;
  }

  function setResourceChipDefault(chip, isDefault){
    if (!chip) return;
    chip.dataset.sysDefault = isDefault ? '1' : '0';
    var badge = chip.querySelector('.res-default-badge');
    var btnDel = chip.querySelector('.btn-del-res');
    if (badge) badge.style.display = isDefault ? '' : 'none';
    if (btnDel) btnDel.style.display = isDefault ? 'none' : '';
  }

  function addResourceChip(id, name, kind, perm, temp, isSystemDefault){
    id = parseInt(id,10)||0;
    if (!id) return;
    var exists = Array.prototype.find.call(resList.querySelectorAll('.res-chip'), function(c){
      return c.dataset.id === String(id);
    });
    if (exists) {
      if (isSystemDefault) setResourceChipDefault(exists, true);
      return;
    }

    perm = parseInt(perm,10); if (isNaN(perm) || perm < 0) perm = 0;
    temp = parseInt(temp,10); if (isNaN(temp) || temp < 0) temp = 0;

    var chip = document.createElement('span');
    chip.className = 'chip res-chip';
    chip.dataset.id = String(id);
    chip.innerHTML =
      '<span class="tag">'+(kind || 'res')+'</span>' +
      '<span class="pname">'+(name || ('#'+id))+'</span>' +
      '<span class="res-default-badge adm-hidden-sys-badge">SYS</span>' +
      '<input type="hidden" name="resource_ids[]" value="'+id+'">' +
      '<input class="inp adm-w-90" type="number" min="0" name="resource_perm[]" value="'+perm+'" title="Permanente">' +
      '<input class="inp adm-w-90" type="number" min="0" name="resource_temp[]" value="'+temp+'" title="Temporal">' +
      '<button type="button" class="btn btn-red btn-del-res">X</button>';

    resList.appendChild(chip);
    chip.querySelector('.btn-del-res').addEventListener('click', function(){ chip.remove(); });
    setResourceChipDefault(chip, !!isSystemDefault);
  }

  function ensureSystemResources(systemId){
    systemId = parseInt(systemId,10)||0;
    var defaults = SYS_RESOURCES_BY_SYS[String(systemId)] || [];
    defaults.forEach(function(r){
      addResourceChip(r.id, r.name, r.kind, 0, 0, true);
    });
    // Re-marca defaults de este sistema y desmarca el resto
    var defaultMap = {};
    defaults.forEach(function(r){ defaultMap[String(r.id)] = true; });
    Array.prototype.forEach.call(resList.querySelectorAll('.res-chip'), function(ch){
      setResourceChipDefault(ch, !!defaultMap[ch.dataset.id]);
    });
  }

  function ensureEstadoOption(val){
    if (!val) return;
    var sel = fEstado;
    var ok = Array.prototype.some.call(sel.options, function(o){ return o.value === val; });
    if (!ok) {
      var opt = document.createElement('option');
      opt.value = val;
      opt.textContent = '[WARN] ' + val + ' (no en lista)';
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

    ['nombre','alias','nombregarou','gender','concept','text_color','cumple','rango'].forEach(function(k){
      var el=document.getElementById('f_'+k); if(el) el.value='';
    });
    ['cronica','jugador','system_id'].forEach(function(k){
      var el=document.getElementById('f_'+k); if(el) el.value='0';
    });
    if (selTotem) selTotem.value = '0';
    selAfili.value = '0';
    if (selKind) selKind.value = 'pnj';

    ensureEstadoOption('En activo');

    fInfo.value  = '';

	    updateSistemaSets('', 0,0,0);
	    if (selNature) selNature.value = '0';
	    if (selDemeanor) selDemeanor.value = '0';

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

    // reset recursos
    resList.innerHTML = '';
    refreshResourceSelect();
    ensureSystemResources(parseInt(selSistema.value,10)||0);

    // reset traits
    resetTraits();
    applyTraitOrder(0);
    applyKindVisibility(selKind ? selKind.value : 'pnj');

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
    document.getElementById('f_genero_pj').value   = btn.getAttribute('data-gender') || '';
    document.getElementById('f_concepto').value    = btn.getAttribute('data-concept') || '';
    document.getElementById('f_colortexto').value  = btn.getAttribute('data-text_color') || '';

    document.getElementById('f_cronica').value     = btn.getAttribute('data-cronica') || '0';
    document.getElementById('f_jugador').value     = btn.getAttribute('data-jugador') || '0';
    document.getElementById('f_afiliacion').value  = btn.getAttribute('data-afiliacion') || '0';
    if (selKind) {
      var k = (btn.getAttribute('data-kind') || 'pnj').toLowerCase();
      if (k === 'monster' || k === 'mon') selKind.value = 'mon';
      else selKind.value = (k === 'pj') ? 'pj' : 'pnj';
    }

    var sistId = parseInt(btn.getAttribute('data-system_id')||'0',10)||0;
    var selS = document.getElementById('f_system_id');
    if (selS) selS.value = String(sistId||0);

    if (selTotem) {
      var tId = parseInt(btn.getAttribute('data-totem_id')||'0',10)||0;
      selTotem.value = String(tId||0);
    }

	    var razaId = parseInt(btn.getAttribute('data-raza')||'0',10)||0;
	    var ausId  = parseInt(btn.getAttribute('data-auspice_id')||'0',10)||0;
	    var triId  = parseInt(btn.getAttribute('data-tribe_id')||'0',10)||0;
	    var natId  = parseInt(btn.getAttribute('data-nature_id')||'0',10)||0;
	    var demId  = parseInt(btn.getAttribute('data-demeanor_id')||'0',10)||0;
	    updateSistemaSets(sistId, razaId, ausId, triId);
	    applyTraitOrder(sistId);
	    if (selNature) selNature.value = String(natId||0);
	    if (selDemeanor) selDemeanor.value = String(demId||0);

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

    // RECURSOS: cargar (existentes) + defaults del sistema
    resList.innerHTML = '';
    refreshResourceSelect();
    var rl = CHAR_RESOURCES[cid] || [];
    rl.forEach(function(rr){
      addResourceChip(rr.id, rr.name, rr.kind, rr.perm, rr.temp, false);
    });
    ensureSystemResources(sistId);

    // Traits: cargar
    fillTraits(CHAR_TRAITS[cid] || {});
    applyKindVisibility(selKind ? selKind.value : 'pnj');

    fInfo.value   = '';
    fCumple.value = '';
    fRango.value  = '';
    ensureEstadoOption('En activo');

    var d = CHAR_DETAILS[cid];
    if (d) {
      ensureEstadoOption(d.status || 'En activo');
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
    ensureSystemResources(sys);
  });

  // Clan -> manadas
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

  onSelectChange(selKind, function(){
    applyKindVisibility(selKind ? selKind.value : 'pnj');
  });
  applyKindVisibility(selKind ? selKind.value : 'pnj');

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

  // RECURSOS UI
  refreshResourceSelect();
  reinitSelect2(resSel);
  resAdd.addEventListener('click', function(){
    var rid = parseInt(resSel.value,10)||0;
    if (!rid){ alert('Elige un recurso.'); return; }
    var meta = getResourceMeta(rid);
    addResourceChip(rid, meta ? meta.name : ('#'+rid), meta ? meta.kind : 'res', 0, 0, false);
    ensureSystemResources(parseInt(selSistema.value,10)||0);
  });

})();
</script>










