<?php

function hg_topic_viewer_table_exists(mysqli $link): bool {
    $rs=$link->query("SHOW TABLES LIKE 'fact_tools_topic_viewer'");
    return $rs && $rs->num_rows>0;
}
function hg_topic_viewer_column_exists(mysqli $link,string $table,string $column): bool {
    $st=$link->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
    if(!$st)return false;
    $st->bind_param('ss',$table,$column);$st->execute();$rs=$st->get_result();$ok=$rs&&$rs->num_rows>0;$st->close();return (bool)$ok;
}
function hg_topic_viewer_delete(mysqli $link,int $id): array {
    if($id<=0)return ['ok'=>false,'message'=>'ID inválido para borrar.'];
    $st=$link->prepare("DELETE FROM fact_tools_topic_viewer WHERE id = ? LIMIT 1");
    if(!$st)return ['ok'=>false,'message'=>'Error al preparar DELETE: '.$link->error];
    $st->bind_param('i',$id);$ok=$st->execute();$err=$st->error;$st->close();
    return $ok?['ok'=>true,'message'=>'Tema eliminado.']:['ok'=>false,'message'=>'Error al borrar: '.$err];
}
function hg_topic_viewer_move(mysqli $link,int $id,string $direction,bool $supports): array {
    if($id<=0||!in_array($direction,['up','down'],true))return ['ok'=>false,'message'=>'Movimiento de orden inválido.'];
    $select=$supports?"id, COALESCE(link_scope_type, '') AS scope_type, COALESCE(link_scope_id, 0) AS scope_id":"id, '' AS scope_type, 0 AS scope_id";
    $st=$link->prepare("SELECT {$select} FROM fact_tools_topic_viewer WHERE id = ? LIMIT 1");
    if(!$st)return ['ok'=>false,'message'=>'No se pudo preparar el movimiento.'];
    $st->bind_param('i',$id);$st->execute();$current=$st->get_result()->fetch_assoc();$st->close();
    if(!$current)return ['ok'=>false,'message'=>'No se encontró el tema que quieres mover.'];
    $scopeSql=$supports?"COALESCE(link_scope_type, '') = ? AND COALESCE(link_scope_id, 0) = ?":'1 = 1';
    $st=$link->prepare("SELECT id FROM fact_tools_topic_viewer WHERE {$scopeSql} ORDER BY sort_order ASC, topic_name ASC, id ASC");
    if(!$st)return ['ok'=>false,'message'=>'No se pudo preparar el orden del grupo.'];
    if($supports){$scopeType=(string)$current['scope_type'];$scopeId=(int)$current['scope_id'];$st->bind_param('si',$scopeType,$scopeId);}
    $st->execute();$rows=$st->get_result()->fetch_all(MYSQLI_ASSOC);$st->close();
    $pos=-1;foreach($rows as $i=>$row){if((int)$row['id']===$id){$pos=$i;break;}}
    $target=$direction==='up'?$pos-1:$pos+1;
    if($pos<0||$target<0||$target>=count($rows))return ['ok'=>false,'message'=>'El tema ya está en ese extremo de su agrupación.'];
    $tmp=$rows[$pos];$rows[$pos]=$rows[$target];$rows[$target]=$tmp;
    $link->begin_transaction();
    $st=$link->prepare('UPDATE fact_tools_topic_viewer SET sort_order = ? WHERE id = ? LIMIT 1');
    $ok=(bool)$st;
    if($st){foreach($rows as $i=>$row){$order=($i+1)*10;$rowId=(int)$row['id'];$st->bind_param('ii',$order,$rowId);if(!$st->execute()){$ok=false;break;}}$st->close();}
    if($ok){$link->commit();return ['ok'=>true,'message'=>'Orden actualizado dentro de su agrupación.'];}
    $link->rollback();return ['ok'=>false,'message'=>'No se pudo actualizar el orden.'];
}
function hg_topic_viewer_save(mysqli $link,int $id,array $v,bool $supports): array {
    $chapterIdOrNull=((int)$v['chapter_id']>0)?(int)$v['chapter_id']:null;
    if($id>0){
        $sql=$supports
            ?"UPDATE fact_tools_topic_viewer SET topic_name = ?, topic_id = ?, topic_url = ?, topic_description = ?, sort_order = ?, is_active = ?, chapter_id = ?, link_scope_type = ?, link_scope_id = ? WHERE id = ? LIMIT 1"
            :"UPDATE fact_tools_topic_viewer SET topic_name = ?, topic_id = ?, topic_url = ?, topic_description = ?, sort_order = ?, is_active = ? WHERE id = ? LIMIT 1";
        $st=$link->prepare($sql); if(!$st)return ['ok'=>false,'message'=>'Error al preparar UPDATE: '.$link->error];
        if($supports){$scopeType=$v['scope_type']!==''?$v['scope_type']:null;$scopeId=($v['scope_type']!==''&&(int)$v['scope_id']>0)?(int)$v['scope_id']:null;$st->bind_param('sissiiisii',$v['topic_name'],$v['topic_id'],$v['topic_url'],$v['topic_description'],$v['sort_order'],$v['is_active'],$chapterIdOrNull,$scopeType,$scopeId,$id);}
        else{$st->bind_param('sissiii',$v['topic_name'],$v['topic_id'],$v['topic_url'],$v['topic_description'],$v['sort_order'],$v['is_active'],$id);}
        $ok=$st->execute();$code=(int)$st->errno;$err=$st->error;$st->close();
        if(!$ok)return ['ok'=>false,'message'=>$code===1062?'Ya existe un tema con ese topic_id.':'Error al actualizar: '.$err];
        return ['ok'=>true,'message'=>'Tema actualizado.'];
    }
    $sql=$supports
        ?"INSERT INTO fact_tools_topic_viewer (topic_name, topic_id, topic_url, topic_description, sort_order, is_active, chapter_id, link_scope_type, link_scope_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        :"INSERT INTO fact_tools_topic_viewer (topic_name, topic_id, topic_url, topic_description, sort_order, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())";
    $st=$link->prepare($sql);if(!$st)return ['ok'=>false,'message'=>'Error al preparar INSERT: '.$link->error];
    if($supports){$scopeType=$v['scope_type']!==''?$v['scope_type']:null;$scopeId=($v['scope_type']!==''&&(int)$v['scope_id']>0)?(int)$v['scope_id']:null;$st->bind_param('sissiiisi',$v['topic_name'],$v['topic_id'],$v['topic_url'],$v['topic_description'],$v['sort_order'],$v['is_active'],$chapterIdOrNull,$scopeType,$scopeId);}
    else{$st->bind_param('sissii',$v['topic_name'],$v['topic_id'],$v['topic_url'],$v['topic_description'],$v['sort_order'],$v['is_active']);}
    $ok=$st->execute();$code=(int)$st->errno;$err=$st->error;$st->close();
    if(!$ok)return ['ok'=>false,'message'=>$code===1062?'Ya existe un tema con ese topic_id.':'Error al crear: '.$err];
    return ['ok'=>true,'message'=>'Tema creado.'];
}
function hg_topic_viewer_fetch_row(mysqli $link,int $id): ?array {
    if($id<=0)return null;$st=$link->prepare("SELECT * FROM fact_tools_topic_viewer WHERE id = ? LIMIT 1");if(!$st)return null;
    $st->bind_param('i',$id);$st->execute();$rs=$st->get_result();$row=$rs?$rs->fetch_assoc():null;$st->close();return $row?:null;
}
function hg_topic_viewer_chapter_options(mysqli $link): array {
    $rows=[];$rs=$link->query("SELECT dc.id, dc.name, dc.chapter_number, ds.name AS season_name, ds.season_number FROM dim_chapters dc LEFT JOIN dim_seasons ds ON ds.id = dc.season_id ORDER BY COALESCE(ds.season_number, 9999) ASC, dc.chapter_number ASC, dc.id ASC");
    if($rs){while($r=$rs->fetch_assoc())$rows[]=$r;$rs->close();}return $rows;
}
function hg_topic_viewer_rows(mysqli $link,bool $supports): array {
    $sql="SELECT ftv.id,ftv.topic_name,ftv.topic_id,ftv.topic_url,ftv.topic_description,ftv.sort_order,ftv.is_active,ftv.created_at,ftv.updated_at";
    if($supports)$sql.=",ftv.chapter_id,ftv.link_scope_type,ftv.link_scope_id,dc.name AS chapter_name,dc.chapter_number,ds.name AS season_name,ds.season_number";
    $sql.=" FROM fact_tools_topic_viewer ftv";
    if($supports)$sql.=" LEFT JOIN dim_chapters dc ON dc.id = ftv.chapter_id LEFT JOIN dim_seasons ds ON ds.id = dc.season_id";
    $sql.=" ORDER BY ftv.is_active DESC, ftv.sort_order ASC, ftv.topic_name ASC, ftv.id DESC";
    $rows=[];$rs=$link->query($sql);if($rs){while($r=$rs->fetch_assoc())$rows[]=$r;$rs->close();}return $rows;
}
