<?php
// Shared service functions for admin_characters.php

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
if (!function_exists('pjs_fetch_id_lookup')) {
    function pjs_fetch_id_lookup(mysqli $link, string $table): array {
        $out = [];
        if (!pjs_table_exists($link, $table)) return $out;
        $t = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        if ($t === '') return $out;
        if (!$rs = $link->query("SELECT id FROM `{$t}`")) return $out;
        while ($row = $rs->fetch_assoc()) {
            $id = (int)($row['id'] ?? 0);
            if ($id > 0) $out[$id] = true;
        }
        $rs->close();
        return $out;
    }
}
if (!function_exists('pjs_fetch_discipline_power_type_map')) {
    function pjs_fetch_discipline_power_type_map(mysqli $link): array {
        $out = [];
        if (!pjs_table_exists($link, 'fact_discipline_powers')) return $out;
        if (!$rs = $link->query("SELECT id, disc FROM fact_discipline_powers")) return $out;
        while ($row = $rs->fetch_assoc()) {
            $powerId = (int)($row['id'] ?? 0);
            $typeIdRaw = trim((string)($row['disc'] ?? ''));
            if ($powerId <= 0 || $typeIdRaw === '' || !ctype_digit($typeIdRaw)) continue;
            $typeId = (int)$typeIdRaw;
            if ($typeId > 0) $out[$powerId] = $typeId;
        }
        $rs->close();
        return $out;
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
        $validByType = [
            'dones' => pjs_fetch_id_lookup($link, 'fact_gifts'),
            'disciplinas' => pjs_fetch_id_lookup($link, 'dim_discipline_types'),
            'rituales' => pjs_fetch_id_lookup($link, 'fact_rites'),
        ];
        $disciplinePowerToType = pjs_fetch_discipline_power_type_map($link);
        $n = min(count($types), count($ids), count($levels));
        $rows = [];
        for ($i = 0; $i < $n; $i++) {
            $type = strtolower(trim((string)$types[$i]));
            $pid = (int)$ids[$i];
            $lvl = (int)$levels[$i];
            if (!isset($allowed[$type]) || $pid <= 0) { $res['skipped']++; continue; }
            if ($type === 'disciplinas' && !isset($validByType['disciplinas'][$pid]) && isset($disciplinePowerToType[$pid])) {
                $pid = (int)$disciplinePowerToType[$pid];
            }
            if (!isset($validByType[$type][$pid])) { $res['skipped']++; continue; }
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
    function save_character_traits(
        mysqli $link,
        int $characterId,
        array $traits,
        array $traitsDelete = [],
        string $source = 'admin',
        ?string $createdBy = null
    ): array {
        $res = [
            'updated' => 0,
            'inserted' => 0,
            'deleted' => 0,
        ];
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
            if ($nv > 0) {
                $normalized[$tid] = $nv;
            }
        }
        $deleteMap = [];
        foreach ($traitsDelete as $tid) {
            $tid = (int)$tid;
            if ($tid > 0) $deleteMap[$tid] = true;
        }
        foreach (array_keys($normalized) as $tid) {
            unset($deleteMap[(int)$tid]);
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
                if ($ins->execute()) {
                    $res['updated']++;
                    if ($ov === null) $res['inserted']++;
                }
            }
            if ($log) {
                $reason = ($ov === null) ? 'admin initial' : 'admin update';
                $log->bind_param("iiiiisss", $characterId, $tid, $ov, $nv, $delta, $reason, $source, $createdBy);
                $log->execute();
            }
        }

        foreach (array_keys($deleteMap) as $tid) {
            if (!array_key_exists($tid, $existing)) continue;
            $ov = (int)$existing[$tid];
            if ($del) {
                $del->bind_param("ii", $characterId, $tid);
                if ($del->execute()) {
                    $res['updated']++;
                    $res['deleted']++;
                }
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
