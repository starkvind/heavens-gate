<?php

function hg_abs_soundtrack_exists(mysqli $link, string $title, string $artist, string $youtubeUrl, int $excludeId = 0): bool {
    $title = trim($title);
    $artist = trim($artist);
    $youtubeUrl = trim($youtubeUrl);
    if ($title === '' || $youtubeUrl === '') return false;
    $sql = "SELECT id FROM dim_soundtracks WHERE TRIM(COALESCE(title, '')) = ? AND TRIM(COALESCE(artist, '')) = ? AND TRIM(COALESCE(youtube_url, '')) = ? AND id <> ? LIMIT 1";
    $st = $link->prepare($sql);
    if (!$st) return false;
    $foundId = 0;
    $st->bind_param('sssi', $title, $artist, $youtubeUrl, $excludeId);
    $ok = false;
    if ($st->execute()) {
        $st->bind_result($foundId);
        $ok = $st->fetch();
    }
    $st->close();
    return (bool)$ok && $foundId > 0;
}

function hg_abs_bridge_total(mysqli $link, int $soundtrackId): int {
    return hg_table_has_column($link, 'bridge_soundtrack_links', 'soundtrack_id')
        ? hg_admin_catalog_count_by_id($link, 'bridge_soundtrack_links', 'soundtrack_id', $soundtrackId)
        : 0;
}

function hg_abs_type_label(string $type): string {
    if ($type === 'personaje') return 'Personaje';
    if ($type === 'temporada') return 'Temporada';
    if ($type === 'episodio') return 'Episodio';
    return ucfirst($type);
}

function hg_abs_public_url(string $type, string $prettyId, int $objectId): string {
    $slug = trim($prettyId) !== '' ? trim($prettyId) : (string)$objectId;
    if ($slug === '' || $objectId <= 0) return '';
    if ($type === 'personaje') return '/characters/' . rawurlencode($slug);
    if ($type === 'temporada') return '/seasons/' . rawurlencode($slug);
    if ($type === 'episodio') return '/chapters/' . rawurlencode($slug);
    return '';
}

function hg_abs_soundtrack_link_exists(mysqli $link, int $soundtrackId, string $type, int $objectId): bool {
    if ($soundtrackId <= 0 || $objectId <= 0 || $type === '' || !hg_table_has_column($link, 'bridge_soundtrack_links', 'soundtrack_id')) return false;
    $sql = 'SELECT id FROM bridge_soundtrack_links WHERE soundtrack_id = ? AND object_type = ? AND object_id = ? LIMIT 1';
    $st = $link->prepare($sql);
    if (!$st) return false;
    $foundId = 0;
    $ok = false;
    $st->bind_param('isi', $soundtrackId, $type, $objectId);
    if ($st->execute()) {
        $st->bind_result($foundId);
        $ok = $st->fetch();
    }
    $st->close();
    return (bool)$ok && $foundId > 0;
}

function hg_abs_link_object_exists(mysqli $link, string $type, int $objectId): bool {
    if ($objectId <= 0) return false;
    $table = $type === 'personaje' ? 'fact_characters' : ($type === 'temporada' ? 'dim_seasons' : ($type === 'episodio' ? 'dim_chapters' : ''));
    if ($table === '') return false;
    $sql = "SELECT id FROM `$table` WHERE id = ? LIMIT 1";
    $st = $link->prepare($sql);
    if (!$st) return false;
    $foundId = 0;
    $ok = false;
    $st->bind_param('i', $objectId);
    if ($st->execute()) {
        $st->bind_result($foundId);
        $ok = $st->fetch();
    }
    $st->close();
    return (bool)$ok && $foundId > 0;
}

function hg_abs_query_rows(mysqli $link, string $sql, ?string $fallbackSql = null): array {
    $rows = [];
    $rs = $link->query($sql);
    if (!$rs && $fallbackSql !== null && trim($fallbackSql) !== '') {
        if (function_exists('hg_runtime_log_error')) hg_runtime_log_error('admin_bso.query_rows.primary', $link->error . ' | SQL: ' . $sql);
        $rs = $link->query($fallbackSql);
    }
    if ($rs) {
        while ($row = $rs->fetch_assoc()) $rows[] = $row;
        $rs->close();
    } elseif (function_exists('hg_runtime_log_error')) {
        hg_runtime_log_error('admin_bso.query_rows.final', $link->error . ' | SQL: ' . ($fallbackSql !== null && trim($fallbackSql) !== '' ? $fallbackSql : $sql));
    }
    return $rows;
}

function hg_abs_format_season_base_label(int $seasonId, string $name, int $seasonNumber): string {
    $name = trim($name);
    if ($seasonNumber > 0 && $name !== '') return 'T' . $seasonNumber . ' - ' . $name;
    if ($seasonNumber > 0) return 'T' . $seasonNumber;
    return $name !== '' ? $name : ('Temporada #' . $seasonId);
}

function hg_abs_format_episode_base_label(int $episodeId, string $name, int $chapterNumber): string {
    $name = trim($name);
    if ($chapterNumber > 0 && $name !== '') return 'E' . $chapterNumber . ' - ' . $name;
    if ($chapterNumber > 0) return 'E' . $chapterNumber;
    return $name !== '' ? $name : ('Episodio #' . $episodeId);
}

function hg_abs_chronicle_name_map(mysqli $link): array {
    $map = [];
    if (!hg_table_has_column($link, 'dim_chronicles', 'name')) return $map;
    $rows = hg_abs_query_rows($link, "SELECT id, COALESCE(NULLIF(TRIM(name), ''), CONCAT('Cronica #', id)) AS chronicle_name FROM dim_chronicles ORDER BY name ASC, id ASC");
    foreach ($rows as $row) {
        $id = (int)($row['id'] ?? 0);
        if ($id <= 0) continue;
        $map[$id] = (string)($row['chronicle_name'] ?? ('Cronica #' . $id));
    }
    return $map;
}

function hg_abs_build_link_catalog(mysqli $link): array {
    static $cache = null;
    if ($cache !== null) return $cache;

    $chronicleMap = hg_abs_chronicle_name_map($link);
    $hasCharPretty = hg_table_has_column($link, 'fact_characters', 'pretty_id');
    $hasCharChronicleId = hg_table_has_column($link, 'fact_characters', 'chronicle_id');
    $hasSeasonPretty = hg_table_has_column($link, 'dim_seasons', 'pretty_id');
    $hasSeasonNumber = hg_table_has_column($link, 'dim_seasons', 'season_number');
    $hasSeasonChronicleId = hg_table_has_column($link, 'dim_seasons', 'chronicle_id');
    $hasChapterPretty = hg_table_has_column($link, 'dim_chapters', 'pretty_id');
    $hasChapterNumber = hg_table_has_column($link, 'dim_chapters', 'chapter_number');
    $hasChapterSeasonId = hg_table_has_column($link, 'dim_chapters', 'season_id');
    $hasChapterPlayedDate = hg_table_has_column($link, 'dim_chapters', 'played_date');

    $options = ['personaje' => [], 'temporada' => [], 'episodio' => []];
    $labels = ['personaje' => [], 'temporada' => [], 'episodio' => []];
    $pretty = ['personaje' => [], 'temporada' => [], 'episodio' => []];
    $seasonBaseById = [];

    $seasonRows = hg_abs_query_rows(
        $link,
        "SELECT s.id,
                COALESCE(NULLIF(TRIM(s.name), ''), CONCAT('Temporada #', s.id)) AS season_name,
                " . ($hasSeasonNumber ? "COALESCE(s.season_number, 0)" : "0") . " AS season_number,
                " . ($hasSeasonPretty ? "COALESCE(s.pretty_id, '')" : "''") . " AS pretty_id,
                " . ($hasSeasonChronicleId ? "COALESCE(s.chronicle_id, 0)" : "0") . " AS chronicle_id
         FROM dim_seasons s
         ORDER BY " . ($hasSeasonNumber ? "season_number ASC, " : "") . "season_name ASC, s.id ASC"
    );
    foreach ($seasonRows as $row) {
        $id = (int)($row['id'] ?? 0);
        if ($id <= 0) continue;
        $baseLabel = hg_abs_format_season_base_label($id, (string)($row['season_name'] ?? ''), (int)($row['season_number'] ?? 0));
        $chronicleId = (int)($row['chronicle_id'] ?? 0);
        $chronicleLabel = ($chronicleId > 0 && isset($chronicleMap[$chronicleId])) ? $chronicleMap[$chronicleId] : 'Sin cronica asociada';
        $label = $baseLabel . ' | ' . $chronicleLabel;
        $prettyId = (string)($row['pretty_id'] ?? '');
        $seasonBaseById[$id] = $baseLabel;
        $labels['temporada'][$id] = $label;
        $pretty['temporada'][$id] = $prettyId;
        $options['temporada'][] = ['id' => $id, 'label' => $label, 'pretty_id' => $prettyId];
    }

    $characterRows = hg_abs_query_rows(
        $link,
        "SELECT c.id,
                COALESCE(NULLIF(TRIM(c.name), ''), CONCAT('Personaje #', c.id)) AS character_name,
                " . ($hasCharPretty ? "COALESCE(c.pretty_id, '')" : "''") . " AS pretty_id,
                " . ($hasCharChronicleId ? "COALESCE(c.chronicle_id, 0)" : "0") . " AS chronicle_id
         FROM fact_characters c
         ORDER BY character_name ASC, c.id ASC"
    );
    foreach ($characterRows as $row) {
        $id = (int)($row['id'] ?? 0);
        if ($id <= 0) continue;
        $chronicleId = (int)($row['chronicle_id'] ?? 0);
        $chronicleLabel = ($chronicleId > 0 && isset($chronicleMap[$chronicleId])) ? $chronicleMap[$chronicleId] : 'Sin cronica asociada';
        $label = '#' . $id . ' | ' . (string)($row['character_name'] ?? ('Personaje #' . $id)) . ' | ' . $chronicleLabel;
        $prettyId = (string)($row['pretty_id'] ?? '');
        $labels['personaje'][$id] = $label;
        $pretty['personaje'][$id] = $prettyId;
        $options['personaje'][] = ['id' => $id, 'label' => $label, 'pretty_id' => $prettyId];
    }

    $chapterRows = hg_abs_query_rows(
        $link,
        "SELECT c.id,
                COALESCE(NULLIF(TRIM(c.name), ''), CONCAT('Episodio #', c.id)) AS chapter_name,
                " . ($hasChapterNumber ? "COALESCE(c.chapter_number, 0)" : "0") . " AS chapter_number,
                " . ($hasChapterSeasonId ? "COALESCE(c.season_id, 0)" : "0") . " AS season_id,
                " . ($hasChapterPretty ? "COALESCE(c.pretty_id, '')" : "''") . " AS pretty_id
         FROM dim_chapters c
         ORDER BY " . ($hasChapterPlayedDate ? "c.played_date DESC, " : "") . ($hasChapterNumber ? "chapter_number DESC, " : "") . "c.id DESC"
    );
    foreach ($chapterRows as $row) {
        $id = (int)($row['id'] ?? 0);
        if ($id <= 0) continue;
        $seasonId = (int)($row['season_id'] ?? 0);
        $seasonLabel = ($seasonId > 0 && isset($seasonBaseById[$seasonId])) ? $seasonBaseById[$seasonId] : 'Sin temporada asociada';
        $label = hg_abs_format_episode_base_label($id, (string)($row['chapter_name'] ?? ''), (int)($row['chapter_number'] ?? 0)) . ' | ' . $seasonLabel;
        $prettyId = (string)($row['pretty_id'] ?? '');
        $labels['episodio'][$id] = $label;
        $pretty['episodio'][$id] = $prettyId;
        $options['episodio'][] = ['id' => $id, 'label' => $label, 'pretty_id' => $prettyId];
    }

    $cache = ['options' => $options, 'labels' => $labels, 'pretty' => $pretty];
    return $cache;
}

function hg_abs_fetch_link_options(mysqli $link): array {
    $catalog = hg_abs_build_link_catalog($link);
    return (array)($catalog['options'] ?? ['personaje' => [], 'temporada' => [], 'episodio' => []]);
}

function hg_abs_fetch_links_by_soundtrack(mysqli $link): array {
    if (!hg_table_has_column($link, 'bridge_soundtrack_links', 'soundtrack_id')) return [];
    $catalog = hg_abs_build_link_catalog($link);
    $labels = (array)($catalog['labels'] ?? []);
    $pretty = (array)($catalog['pretty'] ?? []);
    $rows = hg_abs_query_rows($link, "SELECT id, soundtrack_id, object_type, object_id FROM bridge_soundtrack_links ORDER BY soundtrack_id ASC, object_type ASC, object_id ASC, id ASC");
    $map = [];
    foreach ($rows as $row) {
        $sid = (int)($row['soundtrack_id'] ?? 0);
        $type = (string)($row['object_type'] ?? '');
        $objectId = (int)($row['object_id'] ?? 0);
        if ($sid <= 0 || $type === '' || $objectId <= 0) continue;
        $row['object_label'] = (string)($labels[$type][$objectId] ?? ('#' . $objectId));
        $row['object_pretty_id'] = (string)($pretty[$type][$objectId] ?? '');
        $row['type_label'] = hg_abs_type_label($type);
        $row['public_url'] = hg_abs_public_url($type, (string)($row['object_pretty_id'] ?? ''), $objectId);
        if (!isset($map[$sid])) $map[$sid] = [];
        $map[$sid][] = $row;
    }
    return $map;
}


function hg_abs_delete_soundtrack(mysqli $link,int $id): array {
    if($id<=0)return ['ok'=>false,'message'=>'ID invalido para eliminar.'];
    $deps=[['key'=>'links','label'=>'Vinculos BSO','count'=>hg_abs_bridge_total($link,$id)]];
    if(hg_admin_catalog_dependencies_total($deps)>0)return ['ok'=>false,'message'=>'No se puede borrar el tema porque tiene vinculos activos: '.hg_admin_catalog_dependencies_summary($deps).'.'];
    $st=$link->prepare('DELETE FROM dim_soundtracks WHERE id = ?');
    if(!$st){hg_runtime_log_error('admin_bso.delete.prepare',$link->error);return ['ok'=>false,'message'=>'No se pudo preparar el borrado del tema.'];}
    $st->bind_param('i',$id);$ok=$st->execute();$err=$st->error;$st->close();
    if(!$ok){hg_runtime_log_error('admin_bso.delete',$err);return ['ok'=>false,'message'=>'No se pudo eliminar el tema.'];}
    return ['ok'=>true,'message'=>'Tema eliminado.'];
}
function hg_abs_add_link(mysqli $link,int $soundtrackId,string $objectType,int $objectId): array {
    $hasCreated=hg_table_has_column($link,'bridge_soundtrack_links','created_at');
    $hasUpdated=hg_table_has_column($link,'bridge_soundtrack_links','updated_at');
    $cols=['soundtrack_id','object_type','object_id'];$vals=[$soundtrackId,$objectType,$objectId];$types='isi';
    if($hasCreated)$cols[]='created_at'; if($hasUpdated)$cols[]='updated_at';
    $ph=[];foreach($cols as $col)$ph[]=($col==='created_at'||$col==='updated_at')?'NOW()':'?';
    $st=$link->prepare("INSERT INTO bridge_soundtrack_links (`".implode('`,`',$cols)."`) VALUES (".implode(',',$ph).")");
    if(!$st){hg_runtime_log_error('admin_bso.add_link.prepare',$link->error);return ['ok'=>false,'message'=>'No se pudo preparar el alta del vinculo.'];}
    $st->bind_param($types,...$vals);$ok=$st->execute();$err=$st->error;$st->close();
    if(!$ok){hg_runtime_log_error('admin_bso.add_link',$err);return ['ok'=>false,'message'=>'No se pudo anadir el vinculo al tema.'];}
    return ['ok'=>true,'message'=>'Vinculo anadido al tema.'];
}
function hg_abs_delete_link(mysqli $link,int $id): array {
    if($id<=0)return ['ok'=>false,'message'=>'ID de vinculo invalido.'];
    $st=$link->prepare('DELETE FROM bridge_soundtrack_links WHERE id = ?');
    if(!$st){hg_runtime_log_error('admin_bso.delete_link.prepare',$link->error);return ['ok'=>false,'message'=>'No se pudo preparar el borrado del vinculo.'];}
    $st->bind_param('i',$id);$ok=$st->execute();$err=$st->error;$st->close();
    if(!$ok){hg_runtime_log_error('admin_bso.delete_link',$err);return ['ok'=>false,'message'=>'No se pudo eliminar el vinculo del tema.'];}
    return ['ok'=>true,'message'=>'Vinculo eliminado del tema.'];
}
function hg_abs_save_soundtrack(mysqli $link,string $action,int $id,array $schema,array $v): array {
    if($action==='create'){
        $cols=['title'];$vals=[$v['title']];$types='s';
        if(!empty($schema['artist'])){$cols[]='artist';$vals[]=$v['artist'];$types.='s';}
        if(!empty($schema['youtube_url'])){$cols[]='youtube_url';$vals[]=$v['youtube_url'];$types.='s';}
        if(!empty($schema['context_title'])){$cols[]='context_title';$vals[]=$v['context_title'];$types.='s';}
        if(!empty($schema['added_at'])&&preg_match('/^\d{4}-\d{2}-\d{2}$/',$v['added_at'])){$cols[]='added_at';$vals[]=$v['added_at'];$types.='s';}
        if(!empty($schema['created_at']))$cols[]='created_at'; if(!empty($schema['updated_at']))$cols[]='updated_at';
        $ph=[];foreach($cols as $col)$ph[]=($col==='created_at'||$col==='updated_at')?'NOW()':'?';
        $st=$link->prepare("INSERT INTO dim_soundtracks (`".implode('`,`',$cols)."`) VALUES (".implode(',',$ph).")");
        if(!$st){hg_runtime_log_error('admin_bso.create.prepare',$link->error);return ['ok'=>false,'message'=>'No se pudo preparar el alta del tema.'];}
        $st->bind_param($types,...$vals);$ok=$st->execute();$err=$st->error;$newId=(int)$link->insert_id;$st->close();
        if(!$ok){hg_runtime_log_error('admin_bso.create',$err);return ['ok'=>false,'message'=>'No se pudo crear el tema.'];}
        hg_update_pretty_id_if_exists($link,'dim_soundtracks',$newId,$v['title']);hg_content_touch_table($link,'dim_soundtracks',$newId);
        return ['ok'=>true,'id'=>$newId,'message'=>'Tema creado.'];
    }
    if($id<=0)return ['ok'=>false,'message'=>'ID invalido para actualizar.'];
    $sets=['`title` = ?'];$vals=[$v['title']];$types='s';
    if(!empty($schema['artist'])){$sets[]='`artist` = ?';$vals[]=$v['artist'];$types.='s';}
    if(!empty($schema['youtube_url'])){$sets[]='`youtube_url` = ?';$vals[]=$v['youtube_url'];$types.='s';}
    if(!empty($schema['context_title'])){$sets[]='`context_title` = ?';$vals[]=$v['context_title'];$types.='s';}
    if(!empty($schema['added_at'])&&preg_match('/^\d{4}-\d{2}-\d{2}$/',$v['added_at'])){$sets[]='`added_at` = ?';$vals[]=$v['added_at'];$types.='s';}
    if(!empty($schema['updated_at']))$sets[]='`updated_at` = NOW()';
    $vals[]=$id;$types.='i';
    $st=$link->prepare("UPDATE dim_soundtracks SET ".implode(', ',$sets)." WHERE id = ?");
    if(!$st){hg_runtime_log_error('admin_bso.update.prepare',$link->error);return ['ok'=>false,'message'=>'No se pudo preparar la actualizacion del tema.'];}
    $st->bind_param($types,...$vals);$ok=$st->execute();$err=$st->error;$st->close();
    if(!$ok){hg_runtime_log_error('admin_bso.update',$err);return ['ok'=>false,'message'=>'No se pudo actualizar el tema.'];}
    hg_update_pretty_id_if_exists($link,'dim_soundtracks',$id,$v['title']);hg_content_touch_table($link,'dim_soundtracks',$id);
    return ['ok'=>true,'id'=>$id,'message'=>'Tema actualizado.'];
}
function hg_abs_fetch_soundtrack_rows(mysqli $link,array $schema): array {
    $select=[
      's.id',"COALESCE(s.title, '') AS title",
      !empty($schema['artist'])?"COALESCE(s.artist, '') AS artist":"'' AS artist",
      !empty($schema['context_title'])?"COALESCE(s.context_title, '') AS context_title":"'' AS context_title",
      !empty($schema['youtube_url'])?"COALESCE(s.youtube_url, '') AS youtube_url":"'' AS youtube_url",
      !empty($schema['added_at'])?"COALESCE(CAST(s.added_at AS CHAR), '') AS added_at":"'' AS added_at",
      (!empty($schema['bridge'])&&!empty($schema['bridge_object_type']))?"(SELECT COUNT(*) FROM bridge_soundtrack_links b WHERE b.soundtrack_id=s.id AND b.object_type='personaje') AS characters_count":'0 AS characters_count',
      (!empty($schema['bridge'])&&!empty($schema['bridge_object_type']))?"(SELECT COUNT(*) FROM bridge_soundtrack_links b WHERE b.soundtrack_id=s.id AND b.object_type='temporada') AS seasons_count":'0 AS seasons_count',
      (!empty($schema['bridge'])&&!empty($schema['bridge_object_type']))?"(SELECT COUNT(*) FROM bridge_soundtrack_links b WHERE b.soundtrack_id=s.id AND b.object_type='episodio') AS chapters_count":'0 AS chapters_count',
      !empty($schema['bridge'])?"(SELECT COUNT(*) FROM bridge_soundtrack_links b WHERE b.soundtrack_id=s.id) AS links_count":'0 AS links_count'
    ];
    $rows=[];$rs=$link->query('SELECT '.implode(', ',$select).' FROM dim_soundtracks s ORDER BY '.(!empty($schema['added_at'])?'s.added_at DESC, ':'').'s.id DESC');
    if($rs){while($r=$rs->fetch_assoc())$rows[]=$r;$rs->close();}return $rows;
}
