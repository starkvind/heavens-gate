<?php
// admin_pjs_crud.php — CRUD Personajes (Clan→Manada + Sistema→Raza/Auspicio/Tribu + Avatar + Afiliación + Poderes)
// Escenario especial: dim_groups.clan = NOMBRE (texto) de dim_organizations.name

if (!isset($link) || !$link) { die("Error de conexión a la base de datos."); }
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

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
function sync_character_bridges(mysqli $link, int $charId, int $groupId, int $clanId): void {
    if ($groupId > 0) {
        if ($st = $link->prepare("SELECT id FROM bridge_characters_groups WHERE character_id=? AND group_id=? LIMIT 1")) {
            $st->bind_param("ii", $charId, $groupId);
            $st->execute();
            $rs = $st->get_result();
            if ($rs && ($row = $rs->fetch_assoc())) {
                $idrow = (int)$row['id'];
                if ($st2 = $link->prepare("UPDATE bridge_characters_groups SET is_active=1 WHERE id=?")) {
                    $st2->bind_param("i", $idrow); $st2->execute(); $st2->close();
                }
            } else {
                if ($st2 = $link->prepare("INSERT INTO bridge_characters_groups (character_id,group_id,is_active) VALUES (?,?,1)")) {
                    $st2->bind_param("ii", $charId, $groupId); $st2->execute(); $st2->close();
                }
            }
            $st->close();
        }
        if ($st = $link->prepare("UPDATE bridge_characters_groups SET is_active=0 WHERE character_id=? AND group_id<>?")) {
            $st->bind_param("ii", $charId, $groupId); $st->execute(); $st->close();
        }
        if ($st = $link->prepare("UPDATE hg_character_clan_bridge SET is_active=0 WHERE character_id=?")) {
            $st->bind_param("i", $charId); $st->execute(); $st->close();
        }
    } else {
        if ($st = $link->prepare("UPDATE bridge_characters_groups SET is_active=0 WHERE character_id=?")) {
            $st->bind_param("i", $charId); $st->execute(); $st->close();
        }
        if ($clanId > 0) {
            if ($st = $link->prepare("SELECT id FROM hg_character_clan_bridge WHERE character_id=? AND clan_id=? LIMIT 1")) {
                $st->bind_param("ii", $charId, $clanId);
                $st->execute();
                $rs = $st->get_result();
                if ($rs && ($row = $rs->fetch_assoc())) {
                    $idrow = (int)$row['id'];
                    if ($st2 = $link->prepare("UPDATE hg_character_clan_bridge SET is_active=1 WHERE id=?")) {
                        $st2->bind_param("i", $idrow); $st2->execute(); $st2->close();
                    }
                } else {
                    if ($st2 = $link->prepare("INSERT INTO hg_character_clan_bridge (character_id,clan_id,is_active) VALUES (?,?,1)")) {
                        $st2->bind_param("ii", $charId, $clanId); $st2->execute(); $st2->close();
                    }
                }
                $st->close();
            }
            if ($st = $link->prepare("UPDATE hg_character_clan_bridge SET is_active=0 WHERE character_id=? AND clan_id<>?")) {
                $st->bind_param("ii", $charId, $clanId); $st->execute(); $st->close();
            }
        } else {
            if ($st = $link->prepare("UPDATE hg_character_clan_bridge SET is_active=0 WHERE character_id=?")) {
                $st->bind_param("i", $charId); $st->execute(); $st->close();
            }
        }
    }
}

/* --- NUEVO PODERES: helper para guardar poderes del formulario --- */
function save_character_powers(mysqli $link, int $charId, array $types, array $ids, array $lvls): array {
    // Normaliza arrays (mismo tamaño)
    $n = min(count($types), count($ids), count($lvls));
    $inserted = 0; $skipped = 0;

    // Borrado simple para reescribir set completo
    if ($st = $link->prepare("DELETE FROM bridge_characters_powers WHERE personaje_id=?")) {
        $st->bind_param("i", $charId); $st->execute(); $st->close();
    }

    if ($n<=0) return ['inserted'=>0,'skipped'=>0];

    // Evitar duplicados por (tipo, id)
    $seen = [];

    if ($ins = $link->prepare("INSERT INTO bridge_characters_powers (personaje_id, tipo_poder, poder_id, poder_lvl) VALUES (?,?,?,?)")) {
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
   Config
------------------------------------------------- */
$perPage = isset($_GET['pp']) ? max(5, min(100, intval($_GET['pp']))) : 25;
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
$opts_sist     = fetchPairs($link, "SELECT name FROM dim_systems ORDER BY name");
$opts_afili    = fetchPairs($link, "SELECT id, tipo AS name FROM dim_character_types ORDER BY tipo");

// Para filtros del listado (todas las manadas)
$opts_manadas_flat = fetchPairs($link, "SELECT id, name FROM dim_groups ORDER BY name");

/* --- NUEVO PODERES: catálogos de poderes --- */
#$opts_dones        = fetchPairs($link, "SELECT id, nombre AS name FROM fact_gifts ORDER BY nombre");
$opts_dones        = fetchPairs($link, "SELECT id, CONCAT(nombre, ' (', grupo, ')') AS name FROM fact_gifts");
#$opts_disciplinas  = fetchPairs($link, "SELECT id, name AS name FROM fact_discipline_powers ORDER BY name");
$opts_disciplinas  = fetchPairs($link, "SELECT nd.id, CONCAT(nd.name, ' (', ntd.name, ')') AS name FROM fact_discipline_powers nd LEFT JOIN dim_discipline_types ntd ON nd.disc = ntd.id");
#$opts_rituales     = fetchPairs($link, "SELECT id, name AS name FROM fact_rites ORDER BY name");
$opts_rituales     = fetchPairs($link, "SELECT nr.id, CONCAT(nr.name, ' (', ntr.name, ')') AS name FROM fact_rites nr LEFT JOIN dim_rite_types ntr ON nr.tipo = ntr.id");

/* -------------------------------------------------
   Sistema → (Raza, Auspicio, Tribu)
------------------------------------------------- */
// RAZAS
$opts_razas = [];
$razas_by_sys = []; $raza_id_to_sys = [];
if ($st = $link->prepare("SELECT id, name, sistema FROM dim_breeds ORDER BY sistema, name")) {
    $st->execute(); $rs = $st->get_result();
    while ($r = $rs->fetch_assoc()) {
        $id = (int)$r['id']; $nm = (string)$r['name']; $sys = (string)($r['sistema'] ?? '');
        $opts_razas[$id] = $nm . ($sys!=='' ? ' ('.$sys.')' : '');
        $raza_id_to_sys[$id] = $sys;
        $razas_by_sys[$sys][] = ['id'=>$id,'name'=>$nm];
    }
    $st->close();
}
// AUSPICIOS
$opts_ausp = []; $ausp_by_sys = []; $ausp_id_to_sys = [];
if ($st = $link->prepare("SELECT id, name, sistema FROM dim_auspices ORDER BY sistema, name")) {
    $st->execute(); $rs = $st->get_result();
    while ($r = $rs->fetch_assoc()) {
        $id = (int)$r['id']; $nm = (string)$r['name']; $sys = (string)($r['sistema'] ?? '');
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
if ($st = $link->prepare("SELECT id, name, sistema FROM dim_tribes ORDER BY sistema, name")) {
    $st->execute(); $rs = $st->get_result();
    while ($r = $rs->fetch_assoc()) {
        $id = (int)$r['id']; $nm = (string)$r['name']; $sys = (string)($r['sistema'] ?? '');
        $opts_tribus[$id] = $nm;
        $tribu_id_to_sys[$id] = $sys;
        $tribus_by_sys[$sys][] = ['id'=>$id,'name'=>$nm];
    }
    $st->close();
} else {
    $opts_tribus = fetchPairs($link, "SELECT id, name FROM dim_tribes ORDER BY name");
}

/* -------------------------------------------------
   MAPAS Clan→Manadas (uniendo por NOMBRE)
------------------------------------------------- */
$manadas_map_id_to_clan = [];
$manadas_by_clan        = [];
$sqlMap = "
    SELECT m.id   AS manada_id,
           m.name AS manada_name,
           c.id   AS clan_id
    FROM dim_groups m
    JOIN dim_organizations  c
      ON TRIM(m.clan) = TRIM(c.name)
    ORDER BY c.id, m.name
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
   Crear / Editar (POST) + avatar + validaciones + PODERES
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
    $sistema     = trim($_POST['sistema'] ?? '');
    $rm_avatar   = isset($_POST['avatar_remove']) && $_POST['avatar_remove'] ? true : false;
    $notas       = '';

    // --- NUEVO PODERES: recoger arrays del formulario
    $powers_type = isset($_POST['powers_type']) ? (array)$_POST['powers_type'] : [];
    $powers_id   = isset($_POST['powers_id'])   ? array_map('intval',(array)$_POST['powers_id']) : [];
    $powers_lvl  = isset($_POST['powers_lvl'])  ? array_map('intval',(array)$_POST['powers_lvl']) : [];

    if ($genero_pj === '')  $genero_pj = 'f';
    if ($colortexto === '') $colortexto = 'SkyBlue';

    // Validaciones
    if ($clan <= 0) $flash[] = ['type'=>'error','msg'=>'⚠ Debes seleccionar un Clan.'];
    if ($manada > 0) {
        $clan_of_manada = $manadas_map_id_to_clan[$manada] ?? 0;
        if ($clan_of_manada !== $clan) {
            $flash[] = ['type'=>'error','msg'=>'⚠ La Manada seleccionada no pertenece al Clan elegido.'];
        }
    }
    if ($sistema !== '') {
        if ($raza     > 0 && isset($raza_id_to_sys[$raza])     && $raza_id_to_sys[$raza]     !== $sistema) $flash[]=['type'=>'error','msg'=>'⚠ La Raza no pertenece al Sistema elegido.'];
        if ($auspicio > 0 && isset($ausp_id_to_sys[$auspicio]) && $ausp_id_to_sys[$auspicio] !== $sistema) $flash[]=['type'=>'error','msg'=>'⚠ El Auspicio no pertenece al Sistema elegido.'];
        if ($tribu    > 0 && isset($tribu_id_to_sys[$tribu])   && $tribu_id_to_sys[$tribu]   !== $sistema) $flash[]=['type'=>'error','msg'=>'⚠ La Tribu no pertenece al Sistema elegido.'];
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
                (nombre, alias, nombregarou, genero_pj, concepto, cronica, jugador, tipo, img, notas, colortexto, kes, sistema,
                 fera, manada, clan, totem, estado, raza, auspicio, tribu)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
            if ($stmt = $link->prepare($sql)) {
                $img=''; $kes='pnj'; $fera=''; $totem=''; $estado='En activo';
                $stmt->bind_param(
                    "sssssiiissssssiissiii",
                    $nombre, $alias, $nombregarou, $genero_pj, $concepto,
                    $cronica, $jugador, $afili, $img, $notas, $colortexto, $kes, $sistema,
                    $fera, $manada, $clan, $totem, $estado, $raza, $auspicio, $tribu
                );
                if ($stmt->execute()) {
                    $newId = $stmt->insert_id;

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

                    // --- NUEVO PODERES: guardar set enviado
                    $resultPow = save_character_powers($link, (int)$newId, $powers_type, $powers_id, $powers_lvl);
                    if ($resultPow['inserted']>0) { $flash[]=['type'=>'ok','msg'=>'✨ Poderes vinculados: '.$resultPow['inserted']]; }
                    if ($resultPow['skipped']>0)  { $flash[]=['type'=>'info','msg'=>'(Omitidos: '.$resultPow['skipped'].')']; }

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
        if ($nombre === '') $flash[] = ['type'=>'error','msg'=>'⚠ El campo \"nombre\" es obligatorio.'];
        if (!array_filter($flash, fn($f)=>$f['type']==='error')) {
            $sql = "UPDATE fact_characters SET
                    nombre=?, alias=?, nombregarou=?, genero_pj=?, concepto=?,
                    cronica=?, jugador=?, tipo=?, sistema=?, colortexto=?,
                    raza=?, auspicio=?, tribu=?, manada=?, clan=?
                    WHERE id=?";
            if ($stmt = $link->prepare($sql)) {
                $stmt->bind_param(
                    "sssssiiissiiiiii",
                    $nombre, $alias, $nombregarou, $genero_pj, $concepto,
                    $cronica, $jugador, $afili, $sistema, $colortexto,
                    $raza, $auspicio, $tribu, $manada, $clan,
                    $id
                );
                if ($stmt->execute()) {
                    // Avatar
                    if ($rm_avatar && $current_img) {
                        safe_unlink_avatar($current_img, $AV_UPLOADDIR);
                        if ($st2 = $link->prepare("UPDATE fact_characters SET img='' WHERE id=?")) {
                            $st2->bind_param("i",$id); $st2->execute(); $st2->close();
                        }
                        $flash[] = ['type'=>'ok','msg'=>'🗑 Avatar eliminado.'];
                        $current_img = '';
                    }
                    if (!empty($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
                        $res = save_avatar_file($_FILES['avatar'], $id, $nombre, $AV_UPLOADDIR, $AV_URLBASE);
                        if ($res['ok']) {
                            if ($current_img) safe_unlink_avatar($current_img, $AV_UPLOADDIR);
                            if ($st3 = $link->prepare("UPDATE fact_characters SET img=? WHERE id=?")) {
                                $st3->bind_param("si", $res['url'], $id);
                                $st3->execute(); $st3->close();
                            }
                            $flash[] = ['type'=>'ok','msg'=>'🖼 Avatar actualizado.'];
                        } elseif ($res['msg']!=='no_file') {
                            $flash[] = ['type'=>'error','msg'=>'⚠ Avatar no guardado: '.$res['msg']];
                        }
                    }

                    // Bridges manada/clan
                    sync_character_bridges($link, (int)$id, (int)$manada, (int)$clan);

                    // --- NUEVO PODERES: reescribir set enviado
                    $resultPow = save_character_powers($link, (int)$id, $powers_type, $powers_id, $powers_lvl);
                    if ($resultPow['inserted']>0) { $flash[]=['type'=>'ok','msg'=>'✨ Poderes vinculados: '.$resultPow['inserted']]; }
                    if ($resultPow['skipped']>0)  { $flash[]=['type'=>'info','msg'=>'(Omitidos: '.$resultPow['skipped'].')']; }

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
if ($fil_cr > 0) { $where .= " AND p.cronica = ?"; $types .= "i"; $params[] = $fil_cr; }
if ($fil_ma > 0) { $where .= " AND p.manada  = ?"; $types .= "i"; $params[] = $fil_ma; }
if ($q !== '')   { $where .= " AND p.nombre LIKE ?"; $types .= "s"; $params[] = "%".$q."%"; }

$sqlCnt = "SELECT COUNT(*) AS c FROM fact_characters p $where";
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
  p.id, p.nombre, p.alias, p.nombregarou, p.genero_pj, p.concepto,
  p.cronica, p.jugador, p.sistema, p.colortexto,
  p.raza, p.auspicio, p.tribu, p.manada, p.clan, p.img, p.tipo,
  nj.name AS jugador_,
  nc.name AS cronica_,
  nr.name AS raza_n,
  na.name AS auspicio_n,
  nt.name AS tribu_n,
  nm.name AS manada_n,
  nc2.name AS clan_n,
  af.tipo AS tipo_n
FROM fact_characters p
LEFT JOIN dim_players  nj ON p.jugador = nj.id
LEFT JOIN dim_chronicles  nc ON p.cronica = nc.id
LEFT JOIN dim_breeds      nr ON p.raza    = nr.id
LEFT JOIN dim_auspices  na ON p.auspicio= na.id
LEFT JOIN dim_tribes     nt ON p.tribu   = nt.id
LEFT JOIN dim_groups   nm ON p.manada  = nm.id
LEFT JOIN dim_organizations    nc2 ON p.clan   = nc2.id
LEFT JOIN dim_character_types af ON p.tipo    = af.id
$where
ORDER BY p.nombre ASC
LIMIT ?, ?";
$typesPage = $types."ii";
$paramsPage = $params; $paramsPage[] = $offset; $paramsPage[] = $perPage;
$stmt = $link->prepare($sql);
$stmt->bind_param($typesPage, ...$paramsPage);
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
$ids_page = [];
while ($r = $res->fetch_assoc()) { $rows[] = $r; $ids_page[] = (int)$r['id']; }
$stmt->close();

/* --- NUEVO PODERES: precarga poderes de los personajes listados (para prellenar modal de edición) --- */
$char_powers = [];
if (!empty($ids_page)) {
    $in = implode(',', array_map('intval',$ids_page));
    $qpow = $link->query("SELECT personaje_id, tipo_poder, poder_id, poder_lvl FROM bridge_characters_powers WHERE personaje_id IN ($in) ORDER BY tipo_poder, poder_id");
    if ($qpow) {
        while($pw = $qpow->fetch_assoc()){
            $cid = (int)$pw['personaje_id'];
            $tp  = (string)$pw['tipo_poder'];
            $pid = (int)$pw['poder_id'];
            $lvl = (int)$pw['poder_lvl'];
            // nombre para mostrar
            if ($tp==='dones')        { $nm = $opts_dones[$pid]       ?? ('#'.$pid); }
            elseif ($tp==='disciplinas'){ $nm = $opts_disciplinas[$pid] ?? ('#'.$pid); }
            else/*rituales*/            { $nm = $opts_rituales[$pid]    ?? ('#'.$pid); }
            $char_powers[$cid][] = ['t'=>$tp,'id'=>$pid,'lvl'=>$lvl,'name'=>$nm];
        }
        $qpow->close();
    }
}

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
.table { width:100%; border-collapse:collapse; font-size:11px; font-family:Verdana,Arial,sans-serif; }
.table th, .table td { border:1px solid #000088; padding:6px 8px; background:#05014E; white-space:nowrap; }
.table th { background:#050b36; color:#33CCCC; text-align:left; }
.table tr:hover td { background:#000066; color:#33FFFF; }
.pager{ display:flex; gap:6px; align-items:center; margin-top:10px; flex-wrap:wrap; }
.pager a, .pager span { display:inline-block; padding:4px 8px; border:1px solid #000088; background:#05014E; color:#eee; text-decoration:none; border-radius:6px; }
.pager .cur { background:#001199; }
.flash { margin:6px 0; }
.flash .ok{ color:#7CFC00; } .flash .err{ color:#FF6B6B; } .flash .info{ color:#33FFFF; }
.modal-back { position:fixed; inset:0; background:rgba(0,0,0,.6); display:none; align-items:center; justify-content:center; z-index:9999; }
.modal { width:min(1000px,96vw); background:#05014E; border:1px solid #000088; border-radius:12px; padding:12px; }
.modal h3{ margin:0 0 8px; color:#33FFFF; }
.grid { display:grid; grid-template-columns:repeat(3, minmax(220px,1fr)); gap:8px 12px; }
.grid label{ font-size:12px; color:#cfe; display:block; }
.grid input, .grid select { width:100%; box-sizing:border-box; }
.modal-actions{ display:flex; gap:10px; justify-content:flex-end; margin-top:10px; }
.avatar-wrap{ display:flex; gap:10px; align-items:flex-start; }
.avatar-wrap img{ width:96px; height:96px; object-fit:cover; border-radius:10px; border:1px solid #1b4aa0; background:#000022; }
.small-note{ font-size:10px; color:#9dd; display:block; margin-top:4px; }

/* --- NUEVO PODERES: estilos chips --- */
.chips{ display:flex; flex-wrap:wrap; gap:6px; margin-top:6px; }
.chip{ display:inline-flex; align-items:center; gap:6px; background:#00135a; border:1px solid #1b4aa0; border-radius:999px; padding:4px 8px; }
.chip .tag{ font-weight:bold; color:#9dd; }
.chip .pname{ color:#fff; }
.chip input.power-lvl{ width:48px; text-align:center; }
@media (max-width:1100px){ .grid{ grid-template-columns:repeat(2, minmax(240px,1fr)); } }
@media (max-width:750px){ .grid{ grid-template-columns:1fr; } }
</style>

<br />
<div class="panel-wrap">
  <div class="hdr">
    <h2>👤 Personajes — Lista & CRUD</h2>
    <button class="btn btn-green" id="btnNew">➕ Nuevo personaje</button>

    <form method="get" style="display:flex; gap:8px; align-items:center; margin-left:auto;">
      <input type="hidden" name="p" value="talim">
      <input type="hidden" name="s" value="admin_pjs_crud">
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
      <label>Búsqueda
        <input class="inp" type="text" name="q" value="<?= h($q) ?>" placeholder="Nombre…">
      </label>
      <label>Pág
        <select class="select" name="pp" onchange="this.form.submit()">
          <?php foreach([25,50,100] as $pp): ?>
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

  <table class="table">
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
        <tr>
          <td><strong style="color:#33FFFF;"><?= (int)$r['id'] ?></strong></td>
          <td><?= h($r['nombre']) ?></td>
          <td><?= h($r['jugador_'] ?? $r['jugador']) ?></td>
          <td><?= h($r['cronica_'] ?? $r['cronica']) ?></td>
          <td><?= h($r['sistema']) ?></td>
          <td>
            <button class="btn btn-small" data-edit='1'
              data-id="<?= (int)$r['id'] ?>"
              data-nombre="<?= h($r['nombre']) ?>"
              data-alias="<?= h($r['alias']) ?>"
              data-nombregarou="<?= h($r['nombregarou']) ?>"
              data-genero_pj="<?= h($r['genero_pj']) ?>"
              data-concepto="<?= h($r['concepto']) ?>"
              data-cronica="<?= (int)$r['cronica'] ?>"
              data-jugador="<?= (int)$r['jugador'] ?>"
              data-sistema="<?= h($r['sistema']) ?>"
              data-colortexto="<?= h($r['colortexto']) ?>"
              data-raza="<?= (int)$r['raza'] ?>"
              data-auspicio="<?= (int)$r['auspicio'] ?>"
              data-tribu="<?= (int)$r['tribu'] ?>"
              data-manada="<?= (int)$r['manada'] ?>"
              data-clan="<?= (int)$r['clan'] ?>"
              data-img="<?= h($r['img']) ?>"
              data-afiliacion="<?= (int)$r['tipo'] ?>"
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
      $base = "/talim?s=admin_pjs_crud&pp=".$perPage."&fil_cr=".$fil_cr."&fil_ma=".$fil_ma."&q=".urlencode($q);
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
            <select class="select" name="sistema" id="f_sistema">
              <option value="">—</option>
              <?php foreach($opts_sist as $name=>$name2): ?>
                <option value="<?= h($name) ?>"><?= h($name2) ?></option>
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

        <!-- --- NUEVO PODERES: bloque UI --- -->
        <div style="grid-column:1/-1;">
          <label><strong>Poderes</strong></label>
          <div class="grid" style="grid-template-columns: 1fr 2fr 120px auto; gap:8px;">
            <select class="select" id="pow_tipo">
              <option value="dones">Dones</option>
              <option value="disciplinas">Disciplinas</option>
              <option value="rituales">Rituales</option>
            </select>
            <select class="select" id="pow_poder">
              <!-- opciones dinámicas según tipo -->
            </select>
            <input class="inp" id="pow_lvl" type="number" min="0" max="9" value="0" title="Nivel">
            <button class="btn" type="button" id="pow_add">Añadir</button>
          </div>
          <div class="chips" id="powersList"></div>
          <span class="small-note">Los poderes listados aquí se guardarán con el personaje.</span>
        </div>
        <!-- --- FIN NUEVO PODERES --- -->

      </div>

      <div class="modal-actions">
        <button type="button" class="btn btn-red" id="btnCancel">Cancelar</button>
        <button type="submit" class="btn btn-green" id="btnSave">Guardar</button>
      </div>
    </form>
  </div>
</div>

<script>
// --- Datos para dependencias ---
var MANADAS_BY_CLAN   = <?= json_encode($manadas_by_clan, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
var MANADA_ID_TO_CLAN = <?= json_encode($manadas_map_id_to_clan, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;

var RAZAS_BY_SYS      = <?= json_encode($razas_by_sys, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
var RAZA_ID_TO_SYS    = <?= json_encode($raza_id_to_sys, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;

var AUSP_BY_SYS       = <?= json_encode($ausp_by_sys, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
var AUSP_ID_TO_SYS    = <?= json_encode($ausp_id_to_sys, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;

var TRIBUS_BY_SYS     = <?= json_encode($tribus_by_sys, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
var TRIBU_ID_TO_SYS   = <?= json_encode($tribu_id_to_sys, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;

/* --- NUEVO PODERES: catálogos y poderes por personaje de la página --- */
var DONES_OPTS       = <?= json_encode(array_map(fn($id,$name)=>['id'=>$id,'name'=>$name], array_keys($opts_dones), array_values($opts_dones)), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
var DISC_OPTS        = <?= json_encode(array_map(fn($id,$name)=>['id'=>$id,'name'=>$name], array_keys($opts_disciplinas), array_values($opts_disciplinas)), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
var RITU_OPTS        = <?= json_encode(array_map(fn($id,$name)=>['id'=>$id,'name'=>$name], array_keys($opts_rituales), array_values($opts_rituales)), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
var CHAR_POWERS      = <?= json_encode($char_powers, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;

(function(){
  var mb = document.getElementById('mb');
  var btnNew = document.getElementById('btnNew');
  var btnCancel = document.getElementById('btnCancel');

  var selSistema = document.getElementById('f_sistema');
  var selRaza    = document.getElementById('f_raza');
  var selAusp    = document.getElementById('f_auspicio');
  var selTribu   = document.getElementById('f_tribu');

  var selClan    = document.getElementById('f_clan');
  var selManada  = document.getElementById('f_manada');

  var selAfili   = document.getElementById('f_afiliacion');

  var avatar      = document.getElementById('f_avatar');
  var avatarPrev  = document.getElementById('f_avatar_preview');
  var avatarRm    = document.getElementById('f_avatar_remove');

  // --- NUEVO PODERES: refs
  var powTipo  = document.getElementById('pow_tipo');
  var powPoder = document.getElementById('pow_poder');
  var powLvl   = document.getElementById('pow_lvl');
  var powAdd   = document.getElementById('pow_add');
  var powList  = document.getElementById('powersList');

  function clearSelect(sel, keepFirst){
    while (sel.options.length > (keepFirst?1:0)) sel.remove(keepFirst?1:0);
  }
  function fillSelectFrom(list, sel, placeholder, preselect){
    clearSelect(sel,false);
    if (!list || !list.length){
      sel.disabled = true;
      var o=document.createElement('option'); o.value='0'; o.textContent=placeholder;
      sel.appendChild(o); sel.value='0'; return false;
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
    return found;
  }

  function updateManadas(clanId, preselect){
    var list = MANADAS_BY_CLAN[String(clanId||0)] || [];
    fillSelectFrom(list, selManada, '— Sin manadas en este Clan —', preselect);
  }

  function updateSistemaSets(sys, preRaza, preAusp, preTribu){
    if (!sys){
      clearSelect(selRaza,false); var a1=document.createElement('option'); a1.value='0'; a1.textContent='— Elige un Sistema —'; selRaza.appendChild(a1); selRaza.disabled=true;
      clearSelect(selAusp,false); var a2=document.createElement('option'); a2.value='0'; a2.textContent='— Elige un Sistema —'; selAusp.appendChild(a2); selAusp.disabled=true;
      clearSelect(selTribu,false); var a3=document.createElement('option'); a3.value='0'; a3.textContent='— Elige un Sistema —'; selTribu.appendChild(a3); selTribu.disabled=true;
      return;
    }
    var okR = fillSelectFrom(RAZAS_BY_SYS[sys]||[], selRaza, '— Sin razas para este Sistema —', preRaza);
    var okA = fillSelectFrom(AUSP_BY_SYS[sys]||[],  selAusp, '— Sin auspicios para este Sistema —', preAusp);
    var okT = fillSelectFrom(TRIBUS_BY_SYS[sys]||[], selTribu,'— Sin tribus para este Sistema —', preTribu);
    if (preRaza && !okR){ var w=document.createElement('option'); w.value=String(preRaza); w.textContent='⚠ (Fuera del Sistema) ID '+preRaza; selRaza.appendChild(w); selRaza.value=String(preRaza); selRaza.disabled=false; }
    if (preAusp && !okA){ var w2=document.createElement('option'); w2.value=String(preAusp); w2.textContent='⚠ (Fuera del Sistema) ID '+preAusp; selAusp.appendChild(w2); selAusp.value=String(preAusp); selAusp.disabled=false; }
    if (preTribu && !okT){ var w3=document.createElement('option'); w3.value=String(preTribu); w3.textContent='⚠ (Fuera del Sistema) ID '+preTribu; selTribu.appendChild(w3); selTribu.value=String(preTribu); selTribu.disabled=false; }
  }

  function resetAvatarUI(){
    avatar.value = '';
    avatarRm.checked = false;
    avatarPrev.src = '';
    avatarPrev.style.display = 'none';
  }

  // --- NUEVO PODERES: helpers UI ---
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
    // Evitar duplicados exactos
    var exists = Array.prototype.some.call(powList.querySelectorAll('.power-chip'), function(c){ return c.dataset.type===type && c.dataset.id===String(id); });
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

  function openCreate(){
    document.getElementById('modalTitle').textContent = 'Nuevo personaje';
    document.getElementById('crud_action').value = 'create';
    document.getElementById('f_id').value = '0';
    ['nombre','alias','nombregarou','genero_pj','concepto','colortexto'].forEach(function(k){
      var el=document.getElementById('f_'+k); if(el) el.value='';
    });
    ['cronica','jugador','sistema'].forEach(function(k){
      var el=document.getElementById('f_'+k); if(el) el.value=(k==='sistema'?'':'0');
    });
    selAfili.value = '0';

    // Sistema dependencias
    updateSistemaSets('', 0,0,0);

    // Clan→Manada
    selClan.value='0';
    clearSelect(selManada,false);
    var o=document.createElement('option'); o.value='0'; o.textContent='— Selecciona primero un Clan —';
    selManada.appendChild(o); selManada.disabled=true;

    // Avatar & poderes
    resetAvatarUI();
    powList.innerHTML = '';
    powTipo.value = 'dones';
    refreshPowerSelect();

    mb.style.display='flex';
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

    // Sistema (texto)
    var sist = btn.getAttribute('data-sistema') || '';
    var selS = document.getElementById('f_sistema');
    if (sist && selS && !Array.prototype.some.call(selS.options, function(o){return o.value===sist;})) {
      var opt=document.createElement('option'); opt.value=sist; opt.textContent=sist; selS.appendChild(opt);
    }
    if (selS) selS.value = sist;

    // Raza/Auspicio/Tribu
    var razaId = parseInt(btn.getAttribute('data-raza')||'0',10)||0;
    var ausId  = parseInt(btn.getAttribute('data-auspicio')||'0',10)||0;
    var triId  = parseInt(btn.getAttribute('data-tribu')||'0',10)||0;
    updateSistemaSets(sist, razaId, ausId, triId);

    // Clan→Manada
    var clanId   = parseInt(btn.getAttribute('data-clan') || '0',10) || 0;
    var manadaId = parseInt(btn.getAttribute('data-manada') || '0',10) || 0;
    selClan.value = String(clanId||0);
    updateManadas(clanId, manadaId);

    // Avatar
    resetAvatarUI();
    var img = btn.getAttribute('data-img') || '';
    if (img) { avatarPrev.src = img; avatarPrev.style.display='block'; }

    // Poderes existentes
    powList.innerHTML = '';
    var list = CHAR_POWERS[cid] || [];
    list.forEach(function(p){ addPowerChip(p.t, p.id, p.name, p.lvl); });

    // Selector de poder preparado
    powTipo.value = 'dones';
    refreshPowerSelect();

    mb.style.display='flex';
    document.getElementById('f_nombre').focus();
  }

  // Bind básico modal
  btnNew.addEventListener('click', openCreate);
  btnCancel.addEventListener('click', function(){ mb.style.display='none'; });
  mb.addEventListener('click', function(e){ if (e.target === mb) mb.style.display='none'; });
  Array.prototype.forEach.call(document.querySelectorAll('button[data-edit="1"]'), function(b){
    b.addEventListener('click', function(){ openEdit(b); });
  });

  // Cambio de Sistema
  selSistema.addEventListener('change', function(){
    var sys = selSistema.value || '';
    updateSistemaSets(sys, 0,0,0);
  });

  // Cambio de Clan → refrescar manadas
  selClan.addEventListener('change', function(){
    var c = parseInt(selClan.value,10)||0;
    if (!c){
      clearSelect(selManada,false);
      var o=document.createElement('option'); o.value='0'; o.textContent='— Selecciona primero un Clan —';
      selManada.appendChild(o); selManada.disabled=true; return;
    }
    updateManadas(c, 0);
  });

  // Avatar: vista previa y bloqueo al marcar "Quitar"
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
    var sys = selSistema.value || '';
    var rz = parseInt(selRaza.value,10)||0;
    var au = parseInt(selAusp.value,10)||0;
    var tr = parseInt(selTribu.value,10)||0;
    if (sys){
      if (rz && RAZA_ID_TO_SYS[String(rz)]   && RAZA_ID_TO_SYS[String(rz)]   !== sys){ alert('La Raza no pertenece al Sistema elegido.'); ev.preventDefault(); return; }
      if (au && AUSP_ID_TO_SYS[String(au)]   && AUSP_ID_TO_SYS[String(au)]   !== sys){ alert('El Auspicio no pertenece al Sistema elegido.'); ev.preventDefault(); return; }
      if (tr && TRIBU_ID_TO_SYS[String(tr)]  && TRIBU_ID_TO_SYS[String(tr)]  !== sys){ alert('La Tribu no pertenece al Sistema elegido.'); ev.preventDefault(); return; }
    }
  });

  // --- NUEVO PODERES: comportamiento UI
  powTipo.addEventListener('change', refreshPowerSelect);
  refreshPowerSelect();

  powAdd.addEventListener('click', function(){
    var t = powTipo.value;
    var pid = parseInt(powPoder.value,10)||0;
    if (!pid){ alert('Elige un poder.'); return; }
    var nm = powPoder.options[powPoder.selectedIndex].textContent;
    var lvl = parseInt(powLvl.value,10); if (isNaN(lvl)) lvl=0; lvl=Math.max(0,Math.min(9,lvl));
    addPowerChip(t, pid, nm, lvl);
  });

})();
</script>
