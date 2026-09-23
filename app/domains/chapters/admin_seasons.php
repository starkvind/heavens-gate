<?php

function hg_seasons_admin_persist_pretty_id(mysqli $link, int $id, string $name): bool {
    if ($id <= 0) return false;
    $slug = slugify_season_pretty($name);
    if ($slug === '') $slug = (string)$id;
    $st = $link->prepare("UPDATE dim_seasons SET pretty_id=? WHERE id=?");
    if (!$st) return false;
    $st->bind_param('si', $slug, $id);
    $ok = $st->execute();
    $st->close();
    return (bool)$ok;
}

function hg_seasons_admin_chronicle_options(mysqli $link): array {
    $out=[];
    if ($rs=$link->query("SELECT id, name FROM dim_chronicles ORDER BY sort_order ASC, name ASC, id ASC")) {
        while($r=$rs->fetch_assoc()) $out[(int)$r['id']] = (string)($r['name'] ?? '');
        $rs->close();
    }
    return $out;
}

function hg_seasons_admin_current_image(mysqli $link, int $id): string {
    if ($id<=0) return '';
    $st=$link->prepare('SELECT image_url FROM dim_seasons WHERE id = ? LIMIT 1');
    if(!$st)return '';
    $st->bind_param('i',$id);$st->execute();$rs=$st->get_result();$row=$rs?$rs->fetch_assoc():null;$st->close();
    return (string)($row['image_url'] ?? '');
}

function hg_seasons_admin_delete(mysqli $link, int $id): array {
    if($id<=0)return ['ok'=>false,'message'=>'ID inválido para eliminar.'];
    $st=$link->prepare("DELETE FROM dim_seasons WHERE id=?");
    if(!$st)return ['ok'=>false,'message'=>'ID inválido para eliminar.'];
    $st->bind_param('i',$id);$ok=$st->execute();$err=$st->error;$st->close();
    return $ok?['ok'=>true,'message'=>'Temporada eliminada.']:['ok'=>false,'message'=>'Error al eliminar: '.$err];
}

function hg_seasons_admin_create(mysqli $link, array $schema, array $v): array {
    $cols=['name','season_number'];$vals=[$v['name'],$v['season_number']];$types='si';$literals=[];
    if(!empty($schema['season_kind'])){$cols[]='season_kind';$vals[]=$v['season_kind'];$types.='s';}
    if(!empty($schema['chronicle_id'])){
        $cols[]='chronicle_id';
        if((int)$v['chronicle_id']>0){$vals[]=(int)$v['chronicle_id'];$types.='i';}
        else $literals['chronicle_id']='NULL';
    }
    $cols[]='description';$vals[]=$v['description'];$types.='s';
    if(!empty($schema['opening'])){$cols[]='opening';$vals[]=$v['opening'];$types.='s';}
    if(!empty($schema['main_cast'])){$cols[]='main_cast';$vals[]=$v['main_cast'];$types.='s';}
    if(!empty($schema['sort_order'])){$cols[]='sort_order';$vals[]=$v['sort_order'];$types.='i';}
    if(!empty($schema['finished'])){$cols[]='finished';$vals[]=$v['finished'];$types.='i';}
    if(!empty($schema['image_url'])){$cols[]='image_url';$vals[]=$v['image_url'];$types.='s';}
    if(!empty($schema['created_at']))$cols[]='created_at';
    if(!empty($schema['updated_at']))$cols[]='updated_at';
    $ph=[]; foreach($cols as $c){$ph[] = ($c==='created_at'||$c==='updated_at')?'NOW()':(isset($literals[$c])?$literals[$c]:'?');}
    $sql="INSERT INTO dim_seasons (`".implode('`,`',$cols)."`) VALUES (".implode(',',$ph).")";
    $st=$link->prepare($sql);
    if(!$st)return ['ok'=>false,'message'=>'Error al preparar INSERT: '.$link->error];
    $st->bind_param($types,...$vals);$ok=$st->execute();$err=$st->error;$id=(int)$link->insert_id;$st->close();
    if(!$ok)return ['ok'=>false,'message'=>'Error al crear: '.$err];
    $pretty=hg_seasons_admin_persist_pretty_id($link,$id,$v['name']);
    return ['ok'=>true,'id'=>$id,'pretty_ok'=>$pretty,'message'=>$pretty?'Temporada creada.':'Temporada creada, pero no se pudo guardar pretty_id.'];
}

function hg_seasons_admin_update(mysqli $link, array $schema, int $id, array $v): array {
    if($id<=0)return ['ok'=>false,'message'=>'ID inválido para actualizar.'];
    $sets=['`name`=?','`season_number`=?'];$vals=[$v['name'],$v['season_number']];$types='si';
    if(!empty($schema['season_kind'])){$sets[]='`season_kind`=?';$vals[]=$v['season_kind'];$types.='s';}
    if(!empty($schema['chronicle_id'])){
        if((int)$v['chronicle_id']>0){$sets[]='`chronicle_id`=?';$vals[]=(int)$v['chronicle_id'];$types.='i';}
        else $sets[]='`chronicle_id`=NULL';
    }
    $sets[]='`description`=?';$vals[]=$v['description'];$types.='s';
    if(!empty($schema['opening'])){$sets[]='`opening`=?';$vals[]=$v['opening'];$types.='s';}
    if(!empty($schema['main_cast'])){$sets[]='`main_cast`=?';$vals[]=$v['main_cast'];$types.='s';}
    if(!empty($schema['sort_order'])){$sets[]='`sort_order`=?';$vals[]=$v['sort_order'];$types.='i';}
    if(!empty($schema['finished'])){$sets[]='`finished`=?';$vals[]=$v['finished'];$types.='i';}
    if(!empty($schema['image_url'])){$sets[]='`image_url`=?';$vals[]=$v['image_url'];$types.='s';}
    if(!empty($schema['updated_at']))$sets[]='`updated_at`=NOW()';
    $vals[]=$id;$types.='i';
    $st=$link->prepare("UPDATE dim_seasons SET ".implode(', ',$sets)." WHERE id=?");
    if(!$st)return ['ok'=>false,'message'=>'Error al preparar UPDATE: '.$link->error];
    $st->bind_param($types,...$vals);$ok=$st->execute();$err=$st->error;$st->close();
    if(!$ok)return ['ok'=>false,'message'=>'Error al actualizar: '.$err];
    $pretty=hg_seasons_admin_persist_pretty_id($link,$id,$v['name']);
    return ['ok'=>true,'id'=>$id,'pretty_ok'=>$pretty,'message'=>$pretty?'Temporada actualizada.':'Temporada actualizada, pero no se pudo guardar pretty_id.'];
}

function hg_seasons_admin_set_image(mysqli $link,int $id,string $url): bool {
    $st=$link->prepare('UPDATE dim_seasons SET image_url = ? WHERE id = ?');
    if(!$st)return false;$st->bind_param('si',$url,$id);$ok=$st->execute();$st->close();return (bool)$ok;
}

function hg_seasons_admin_rows(mysqli $link,array $schema): array {
    $orderBy=!empty($schema['sort_order'])?'COALESCE(s.sort_order, 999999) ASC, s.season_number ASC':'s.season_number ASC';
    $cols=['s.id','s.name','s.season_number'];
    $cols[]=!empty($schema['season_kind'])?'s.season_kind':"'temporada' AS season_kind";
    if(!empty($schema['chronicle_id'])){$cols[]='s.chronicle_id';$cols[]="COALESCE(ch.name, '') AS chronicle_name";}
    $cols[]='s.description';
    if(!empty($schema['opening']))$cols[]='s.opening';
    if(!empty($schema['main_cast']))$cols[]='s.main_cast';
    if(!empty($schema['sort_order']))$cols[]='s.sort_order';
    if(!empty($schema['finished']))$cols[]='s.finished';
    if(!empty($schema['pretty_id']))$cols[]='s.pretty_id';
    if(!empty($schema['image_url']))$cols[]='s.image_url';
    $from=' FROM dim_seasons s';
    if(!empty($schema['chronicle_id']))$from.=' LEFT JOIN dim_chronicles ch ON ch.id = s.chronicle_id';
    $rows=[];$rs=$link->query('SELECT '.implode(',',$cols).$from.' ORDER BY '.$orderBy);
    if($rs){while($r=$rs->fetch_assoc())$rows[]=$r;$rs->close();}
    return $rows;
}
