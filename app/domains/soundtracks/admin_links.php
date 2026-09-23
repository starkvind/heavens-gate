<?php

function hg_abl_type_label(string $type): string {
    if ($type === 'personaje') return 'Personaje';
    if ($type === 'temporada') return 'Temporada';
    if ($type === 'episodio') return 'Episodio';
    return ucfirst($type);
}

function hg_abl_type_order(string $type): int {
    if ($type === 'personaje') return 1;
    if ($type === 'temporada') return 2;
    if ($type === 'episodio') return 3;
    return 9;
}

function hg_abl_query_rows(mysqli $link, string $sql): array {
    $rows = [];
    $rs = $link->query($sql);
    if ($rs) {
        while ($row = $rs->fetch_assoc()) $rows[] = $row;
        $rs->close();
    }
    return $rows;
}

function hg_abl_soundtrack_exists(mysqli $link, int $soundtrackId): bool {
    if ($soundtrackId <= 0) return false;
    if ($st = $link->prepare('SELECT id FROM dim_soundtracks WHERE id = ? LIMIT 1')) {
        $found = 0; $ok = false;
        $st->bind_param('i', $soundtrackId);
        if ($st->execute()) { $st->bind_result($found); $ok = $st->fetch(); }
        $st->close();
        return (bool)$ok && $found > 0;
    }
    return false;
}

function hg_abl_object_exists(mysqli $link, string $type, int $objectId): bool {
    if ($objectId <= 0) return false;
    $table = $type === 'personaje' ? 'fact_characters' : ($type === 'temporada' ? 'dim_seasons' : ($type === 'episodio' ? 'dim_chapters' : ''));
    if ($table === '') return false;
    if ($st = $link->prepare("SELECT id FROM `$table` WHERE id = ? LIMIT 1")) {
        $found = 0; $ok = false;
        $st->bind_param('i', $objectId);
        if ($st->execute()) { $st->bind_result($found); $ok = $st->fetch(); }
        $st->close();
        return (bool)$ok && $found > 0;
    }
    return false;
}

function hg_abl_link_exists(mysqli $link, int $soundtrackId, string $type, int $objectId): bool {
    if ($soundtrackId <= 0 || $objectId <= 0 || $type === '') return false;
    if ($st = $link->prepare('SELECT id FROM bridge_soundtrack_links WHERE soundtrack_id = ? AND object_type = ? AND object_id = ? LIMIT 1')) {
        $found = 0; $ok = false;
        $st->bind_param('isi', $soundtrackId, $type, $objectId);
        if ($st->execute()) { $st->bind_result($found); $ok = $st->fetch(); }
        $st->close();
        return (bool)$ok && $found > 0;
    }
    return false;
}

function hg_abl_public_url(string $type, string $prettyId, int $objectId): string {
    $slug = trim($prettyId) !== '' ? trim($prettyId) : (string)$objectId;
    if ($slug === '' || $objectId <= 0) return '';
    if ($type === 'personaje') return '/characters/' . rawurlencode($slug);
    if ($type === 'temporada') return '/seasons/' . rawurlencode($slug);
    if ($type === 'episodio') return '/chapters/' . rawurlencode($slug);
    return '';
}

function hg_abl_state_label(array $row): string {
    $parts = [];
    if ((int)($row['soundtrack_exists'] ?? 0) <= 0) $parts[] = 'Tema huerfano';
    if ((int)($row['object_exists'] ?? 0) <= 0) $parts[] = 'Destino huerfano';
    $dup = (int)($row['duplicate_count'] ?? 0);
    if ($dup > 1) $parts[] = 'Duplicado x' . $dup;
    return !empty($parts) ? implode(' | ', $parts) : 'OK';
}

function hg_abl_fetch_payload(mysqli $link): array {
    $hasContextTitle = hg_table_has_column($link, 'dim_soundtracks', 'context_title');
    $hasTitle = hg_table_has_column($link, 'dim_soundtracks', 'title');
    $hasArtist = hg_table_has_column($link, 'dim_soundtracks', 'artist');
    $hasAddedAt = hg_table_has_column($link, 'dim_soundtracks', 'added_at');
    $hasCharPretty = hg_table_has_column($link, 'fact_characters', 'pretty_id');
    $hasSeasonPretty = hg_table_has_column($link, 'dim_seasons', 'pretty_id');
    $hasSeasonNumber = hg_table_has_column($link, 'dim_seasons', 'season_number');
    $hasChapterPretty = hg_table_has_column($link, 'dim_chapters', 'pretty_id');
    $hasChapterPlayedDate = hg_table_has_column($link, 'dim_chapters', 'played_date');
    $hasBridgeCreatedAt = hg_table_has_column($link, 'bridge_soundtrack_links', 'created_at');

    $soundtrackLabelExpr = $hasContextTitle
        ? "COALESCE(NULLIF(TRIM(s.context_title), ''), " . ($hasTitle ? "NULLIF(TRIM(s.title), '')" : "''") . ", CONCAT('Tema #', s.id))"
        : ($hasTitle ? "COALESCE(NULLIF(TRIM(s.title), ''), CONCAT('Tema #', s.id))" : "CONCAT('Tema #', s.id)");
    $seasonLabelExpr = $hasSeasonNumber
        ? "CASE WHEN COALESCE(s.season_number, 0) > 0 AND COALESCE(NULLIF(TRIM(s.name), ''), '') <> '' THEN CONCAT('T', s.season_number, ' - ', s.name) WHEN COALESCE(s.season_number, 0) > 0 THEN CONCAT('T', s.season_number) ELSE COALESCE(NULLIF(TRIM(s.name), ''), CONCAT('Temporada #', s.id)) END"
        : "COALESCE(NULLIF(TRIM(s.name), ''), CONCAT('Temporada #', s.id))";
    $seasonLabelExprLink = str_replace(['s.season_number', 's.name', 's.id'], ['ds.season_number', 'ds.name', 'ds.id'], $seasonLabelExpr);

    $soundtracks = hg_abl_query_rows($link, "SELECT s.id, {$soundtrackLabelExpr} AS label, " . ($hasTitle ? "COALESCE(s.title, '')" : "''") . " AS title, " . ($hasArtist ? "COALESCE(s.artist, '')" : "''") . " AS artist FROM dim_soundtracks s ORDER BY " . ($hasAddedAt ? "s.added_at DESC, " : "") . "s.id DESC");
    $characters = hg_abl_query_rows($link, "SELECT c.id, COALESCE(NULLIF(TRIM(c.name), ''), CONCAT('Personaje #', c.id)) AS label, " . ($hasCharPretty ? "COALESCE(c.pretty_id, '')" : "''") . " AS pretty_id FROM fact_characters c ORDER BY c.name ASC, c.id ASC");
    $seasons = hg_abl_query_rows($link, "SELECT s.id, {$seasonLabelExpr} AS label, " . ($hasSeasonPretty ? "COALESCE(s.pretty_id, '')" : "''") . " AS pretty_id FROM dim_seasons s ORDER BY " . ($hasSeasonNumber ? "s.season_number ASC, " : "") . "s.name ASC, s.id ASC");
    $chapters = hg_abl_query_rows($link, "SELECT c.id, COALESCE(NULLIF(TRIM(c.name), ''), CONCAT('Episodio #', c.id)) AS label, " . ($hasChapterPretty ? "COALESCE(c.pretty_id, '')" : "''") . " AS pretty_id FROM dim_chapters c ORDER BY " . ($hasChapterPlayedDate ? "c.played_date DESC, " : "") . "c.id DESC");

    $rows = hg_abl_query_rows($link, "SELECT l.id, l.soundtrack_id, l.object_type, l.object_id,
        COALESCE({$soundtrackLabelExpr}, CONCAT('Tema #', l.soundtrack_id)) AS soundtrack_label,
        " . ($hasTitle ? "COALESCE(s.title, '')" : "''") . " AS soundtrack_title,
        " . ($hasArtist ? "COALESCE(s.artist, '')" : "''") . " AS soundtrack_artist,
        CASE WHEN l.object_type = 'personaje' THEN COALESCE(NULLIF(TRIM(ch.name), ''), CONCAT('Personaje #', l.object_id))
             WHEN l.object_type = 'temporada' THEN {$seasonLabelExprLink}
             WHEN l.object_type = 'episodio' THEN COALESCE(NULLIF(TRIM(cp.name), ''), CONCAT('Episodio #', l.object_id))
             ELSE CONCAT('Objeto #', l.object_id) END AS object_label,
        CASE WHEN l.object_type = 'personaje' THEN " . ($hasCharPretty ? "COALESCE(ch.pretty_id, '')" : "''") . "
             WHEN l.object_type = 'temporada' THEN " . ($hasSeasonPretty ? "COALESCE(ds.pretty_id, '')" : "''") . "
             WHEN l.object_type = 'episodio' THEN " . ($hasChapterPretty ? "COALESCE(cp.pretty_id, '')" : "''") . "
             ELSE '' END AS object_pretty_id,
        CASE WHEN s.id IS NULL THEN 0 ELSE 1 END AS soundtrack_exists,
        CASE WHEN l.object_type = 'personaje' AND ch.id IS NOT NULL THEN 1
             WHEN l.object_type = 'temporada' AND ds.id IS NOT NULL THEN 1
             WHEN l.object_type = 'episodio' AND cp.id IS NOT NULL THEN 1
             ELSE 0 END AS object_exists,
        (SELECT COUNT(*) FROM bridge_soundtrack_links d WHERE d.soundtrack_id = l.soundtrack_id AND d.object_type = l.object_type AND d.object_id = l.object_id) AS duplicate_count,
        " . ($hasBridgeCreatedAt ? "COALESCE(CAST(l.created_at AS CHAR), '')" : "''") . " AS created_at
        FROM bridge_soundtrack_links l
        LEFT JOIN dim_soundtracks s ON s.id = l.soundtrack_id
        LEFT JOIN fact_characters ch ON l.object_type = 'personaje' AND ch.id = l.object_id
        LEFT JOIN dim_seasons ds ON l.object_type = 'temporada' AND ds.id = l.object_id
        LEFT JOIN dim_chapters cp ON l.object_type = 'episodio' AND cp.id = l.object_id
        ORDER BY {$soundtrackLabelExpr} ASC, l.object_type ASC, object_label ASC, l.id DESC");

    foreach ($rows as &$row) {
        $row['type_label'] = hg_abl_type_label((string)($row['object_type'] ?? ''));
        $row['type_order'] = hg_abl_type_order((string)($row['object_type'] ?? ''));
        $row['state_label'] = hg_abl_state_label($row);
        $row['public_url'] = ((int)($row['object_exists'] ?? 0) > 0)
            ? hg_abl_public_url((string)($row['object_type'] ?? ''), (string)($row['object_pretty_id'] ?? ''), (int)($row['object_id'] ?? 0))
            : '';
    }
    unset($row);

    return ['soundtracks' => $soundtracks, 'characters' => $characters, 'seasons' => $seasons, 'chapters' => $chapters, 'rows' => $rows, 'has_created_at' => $hasBridgeCreatedAt];
}


function hg_abl_create_link(mysqli $link,int $soundtrackId,string $objectType,int $objectId): array {
    $hasCreatedAt=hg_table_has_column($link,'bridge_soundtrack_links','created_at');
    $hasUpdatedAt=hg_table_has_column($link,'bridge_soundtrack_links','updated_at');
    $cols=['soundtrack_id','object_type','object_id'];$vals=[$soundtrackId,$objectType,$objectId];$types='isi';
    if($hasCreatedAt)$cols[]='created_at';if($hasUpdatedAt)$cols[]='updated_at';
    $ph=[];foreach($cols as $col)$ph[]=($col==='created_at'||$col==='updated_at')?'NOW()':'?';
    $st=$link->prepare("INSERT INTO bridge_soundtrack_links (`".implode('`,`',$cols)."`) VALUES (".implode(',',$ph).")");
    if(!$st){hg_runtime_log_error('admin_bso_link.create.prepare',$link->error);return ['ok'=>false,'message'=>'No se pudo preparar el alta del vinculo.'];}
    $st->bind_param($types,...$vals);$ok=$st->execute();$err=$st->error;$st->close();
    if(!$ok){hg_runtime_log_error('admin_bso_link.create',$err);return ['ok'=>false,'message'=>'No se pudo crear el vinculo.'];}
    return ['ok'=>true,'message'=>'Vinculo creado.'];
}
function hg_abl_delete_link(mysqli $link,int $id): array {
    if($id<=0)return ['ok'=>false,'message'=>'ID invalido para borrar.'];
    $st=$link->prepare('DELETE FROM bridge_soundtrack_links WHERE id = ?');
    if(!$st){hg_runtime_log_error('admin_bso_link.delete.prepare',$link->error);return ['ok'=>false,'message'=>'No se pudo preparar el borrado del vinculo.'];}
    $st->bind_param('i',$id);$ok=$st->execute();$err=$st->error;$st->close();
    if(!$ok){hg_runtime_log_error('admin_bso_link.delete',$err);return ['ok'=>false,'message'=>'No se pudo borrar el vinculo.'];}
    return ['ok'=>true,'message'=>'Vinculo eliminado.'];
}
function hg_abl_dedupe(mysqli $link): array {
    $sql="DELETE l1 FROM bridge_soundtrack_links l1 INNER JOIN bridge_soundtrack_links l2 ON l1.soundtrack_id=l2.soundtrack_id AND l1.object_type=l2.object_type AND l1.object_id=l2.object_id AND l1.id>l2.id";
    if($link->query($sql)===true){
        $n=(int)$link->affected_rows;
        return ['ok'=>true,'message'=>$n>0?'Duplicados eliminados: '.$n.'.':'No habia duplicados exactos.'];
    }
    hg_runtime_log_error('admin_bso_link.dedupe',$link->error);
    return ['ok'=>false,'message'=>'No se pudieron eliminar los duplicados.'];
}
