<?php

function hg_realities_admin_delete(mysqli $link, int $id): array {
    if ($id <= 0) return ['ok'=>false,'message'=>'ID invalido para eliminar.'];
    $deps = hg_admin_catalog_get_reality_dependencies($link, $id);
    if (hg_admin_catalog_dependencies_total($deps) > 0) {
        return ['ok'=>false,'message'=>'No se puede borrar la realidad porque tiene dependencias: ' . hg_admin_catalog_dependencies_summary($deps) . '.'];
    }
    $st=$link->prepare('DELETE FROM dim_realities WHERE id = ?');
    if(!$st){ hg_runtime_log_error('admin_realities.delete.prepare',$link->error); return ['ok'=>false,'message'=>'No se pudo preparar el borrado de la realidad.']; }
    $st->bind_param('i',$id);$ok=$st->execute();$err=$st->error;$st->close();
    if(!$ok){ hg_runtime_log_error('admin_realities.delete',$err); return ['ok'=>false,'message'=>'No se pudo eliminar la realidad.']; }
    return ['ok'=>true,'message'=>'Realidad eliminada.'];
}

function hg_realities_admin_create(mysqli $link, array $schema, string $name, string $description, int $sortOrder, int $isActive, string $prettyId): array {
    $cols=['name','description'];$vals=[$name,$description];$types='ss';
    if(!empty($schema['sort_order'])){$cols[]='sort_order';$vals[]=$sortOrder;$types.='i';}
    if(!empty($schema['is_active'])){$cols[]='is_active';$vals[]=$isActive;$types.='i';}
    if(!empty($schema['created_at']))$cols[]='created_at';
    if(!empty($schema['updated_at']))$cols[]='updated_at';
    $ph=[];foreach($cols as $col)$ph[]=($col==='created_at'||$col==='updated_at')?'NOW()':'?';
    $sql="INSERT INTO dim_realities (`".implode('`,`',$cols)."`) VALUES (".implode(',',$ph).")";
    $st=$link->prepare($sql);
    if(!$st){hg_runtime_log_error('admin_realities.create.prepare',$link->error);return ['ok'=>false,'message'=>'No se pudo preparar el alta de la realidad.'];}
    $st->bind_param($types,...$vals);$ok=$st->execute();$err=$st->error;$id=(int)$link->insert_id;$st->close();
    if(!$ok){hg_runtime_log_error('admin_realities.create',$err);return ['ok'=>false,'message'=>'No se pudo crear la realidad.'];}
    $prettyOk=!empty($schema['pretty_id'])?hg_admin_catalog_update_pretty_id($link,'dim_realities',$id,$prettyId):true;
    return ['ok'=>true,'pretty_ok'=>$prettyOk,'message'=>$prettyOk?'Realidad creada.':'Realidad creada, pero no se pudo guardar pretty_id.'];
}

function hg_realities_admin_update(mysqli $link, array $schema, int $id, string $name, string $description, int $sortOrder, int $isActive, string $prettyId): array {
    if($id<=0)return ['ok'=>false,'message'=>'ID invalido para actualizar.'];
    $sets=['`name` = ?','`description` = ?'];$vals=[$name,$description];$types='ss';
    if(!empty($schema['sort_order'])){$sets[]='`sort_order` = ?';$vals[]=$sortOrder;$types.='i';}
    if(!empty($schema['is_active'])){$sets[]='`is_active` = ?';$vals[]=$isActive;$types.='i';}
    if(!empty($schema['updated_at']))$sets[]='`updated_at` = NOW()';
    $vals[]=$id;$types.='i';
    $st=$link->prepare("UPDATE dim_realities SET ".implode(', ',$sets)." WHERE id = ?");
    if(!$st){hg_runtime_log_error('admin_realities.update.prepare',$link->error);return ['ok'=>false,'message'=>'No se pudo preparar la actualizacion de la realidad.'];}
    $st->bind_param($types,...$vals);$ok=$st->execute();$err=$st->error;$st->close();
    if(!$ok){hg_runtime_log_error('admin_realities.update',$err);return ['ok'=>false,'message'=>'No se pudo actualizar la realidad.'];}
    $prettyOk=!empty($schema['pretty_id'])?hg_admin_catalog_update_pretty_id($link,'dim_realities',$id,$prettyId):true;
    return ['ok'=>true,'pretty_ok'=>$prettyOk,'message'=>$prettyOk?'Realidad actualizada.':'Realidad actualizada, pero no se pudo guardar pretty_id.'];
}

function hg_realities_admin_rows(mysqli $link,array $schema): array {
    $select=[
        'r.id',
        !empty($schema['pretty_id'])?"COALESCE(r.pretty_id, '') AS pretty_id":"'' AS pretty_id",
        'r.name',
        "COALESCE(r.description, '') AS description",
        !empty($schema['sort_order'])?'COALESCE(r.sort_order, 0) AS sort_order':'0 AS sort_order',
        !empty($schema['is_active'])?'COALESCE(r.is_active, 1) AS is_active':'1 AS is_active',
        !empty($schema['character_reality'])?'(SELECT COUNT(*) FROM fact_characters fc WHERE fc.reality_id = r.id) AS characters_count':'0 AS characters_count',
        !empty($schema['timeline_reality'])?'(SELECT COUNT(*) FROM bridge_timeline_events_realities bt WHERE bt.reality_id = r.id) AS timeline_count':'0 AS timeline_count'
    ];
    $orderBy=(!empty($schema['is_active'])?'COALESCE(r.is_active, 1) DESC, ':'').(!empty($schema['sort_order'])?'COALESCE(r.sort_order, 999999) ASC, ':'').'r.name ASC, r.id ASC';
    $rows=[];$rs=$link->query('SELECT '.implode(', ',$select).' FROM dim_realities r ORDER BY '.$orderBy);
    if($rs){while($row=$rs->fetch_assoc())$rows[]=$row;$rs->close();}
    return $rows;
}
