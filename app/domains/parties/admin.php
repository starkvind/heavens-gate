<?php

function hg_parties_admin_save_plot(mysqli $link,int $id,string $name,string $desc,int $active,int $order): array {
    if($id===0){
        $st=$link->prepare("INSERT INTO dim_parties (name,description,active,sort_order,created_at,updated_at) VALUES (?,?,?,?,NOW(),NOW())");
        if(!$st)return ['ok'=>false,'error'=>'Prepare failed: '.$link->error];
        $st->bind_param('ssii',$name,$desc,$active,$order);$ok=$st->execute();$err=$st->error;$newId=(int)$st->insert_id;$st->close();
        if(!$ok)return ['ok'=>false,'error'=>'Error al crear: '.$err];
        hg_update_pretty_id_if_exists($link,'dim_parties',$newId,$name);
        return ['ok'=>true,'id'=>$newId,'created'=>true];
    }
    $st=$link->prepare("UPDATE dim_parties SET name=?,description=?,active=?,sort_order=?,updated_at=NOW() WHERE id=?");
    if(!$st)return ['ok'=>false,'error'=>'Prepare failed: '.$link->error];
    $st->bind_param('ssiii',$name,$desc,$active,$order,$id);$ok=$st->execute();$err=$st->error;$st->close();
    if(!$ok)return ['ok'=>false,'error'=>'Error al actualizar: '.$err];
    hg_update_pretty_id_if_exists($link,'dim_parties',$id,$name);
    return ['ok'=>true,'id'=>$id,'created'=>false];
}
function hg_parties_admin_save_member(mysqli $link,int $id,int $plot,int $base,string $alias,array $v,string $notes,int $active): array {
    $table='fact_party_members';$fk='party_id';
    if($id===0){
        $st=$link->prepare("INSERT INTO `{$table}` (`{$fk}`,base_char_id,alias,m_hp,m_rage,m_gnosis,m_glamour,m_mana,m_blood,m_wp,notes,active,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
        if(!$st)return ['ok'=>false,'error'=>'Prepare failed: '.$link->error];
        $st->bind_param('iisiiiiiiisi',$plot,$base,$alias,$v['hp'],$v['rage'],$v['gnosis'],$v['glamour'],$v['mana'],$v['blood'],$v['wp'],$notes,$active);
        $ok=$st->execute();$err=$st->error;$newId=(int)$st->insert_id;$st->close();
        return $ok?['ok'=>true,'id'=>$newId,'created'=>true]:['ok'=>false,'error'=>'Error al insertar: '.$err];
    }
    $st=$link->prepare("UPDATE `{$table}` SET `{$fk}`=?,base_char_id=?,alias=?,m_hp=?,m_rage=?,m_gnosis=?,m_glamour=?,m_mana=?,m_blood=?,m_wp=?,notes=?,active=?,updated_at=NOW() WHERE id=?");
    if(!$st)return ['ok'=>false,'error'=>'Prepare failed: '.$link->error];
    $st->bind_param('iisiiiiiiisii',$plot,$base,$alias,$v['hp'],$v['rage'],$v['gnosis'],$v['glamour'],$v['mana'],$v['blood'],$v['wp'],$notes,$active,$id);
    $ok=$st->execute();$err=$st->error;$st->close();
    return $ok?['ok'=>true,'id'=>$id,'created'=>false]:['ok'=>false,'error'=>'Error al actualizar: '.$err];
}
function hg_parties_admin_add_change(mysqli $link,int $cid,string $resource,int $value,string $notes): array {
    $table='fact_party_members_changes';$fk='party_member_id';
    $st=$link->prepare("INSERT INTO `{$table}` (`{$fk}`,resource,value,notes,created_at) VALUES (?,?,?,?,NOW())");
    if(!$st)return ['ok'=>false,'error'=>'Prepare failed: '.$link->error];
    $st->bind_param('isis',$cid,$resource,$value,$notes);$ok=$st->execute();$err=$st->error;$id=(int)$st->insert_id;$st->close();
    return $ok?['ok'=>true,'id'=>$id]:['ok'=>false,'error'=>'Error al registrar cambio: '.$err];
}
function hg_parties_admin_load_state(mysqli $link): array {
    $plots=[];$q=$link->query("SELECT * FROM dim_parties ORDER BY sort_order DESC, created_at DESC");if($q){while($r=$q->fetch_assoc())$plots[]=$r;$q->close();}
    $sql="SELECT fc.id,fc.name AS nombre,fc.alias FROM fact_characters fc LEFT JOIN dim_character_status dcs ON dcs.id=fc.status_id WHERE COALESCE(dcs.is_active,0)=1 ORDER BY fc.name ASC";
    $baseChars=[];$q=$link->query($sql);if($q){while($r=$q->fetch_assoc())$baseChars[]=$r;$q->close();}
    $byPlot=[];$flat=[];$ids=[];
    $q=$link->query("SELECT pc.*,pc.party_id AS plot_id,p.name AS plot_name,p.sort_order AS plot_sort_order,b.name AS base_nombre,b.alias AS base_alias FROM fact_party_members pc JOIN dim_parties p ON p.id=pc.party_id LEFT JOIN fact_characters b ON b.id=pc.base_char_id ORDER BY p.sort_order DESC,p.id DESC,pc.active DESC,COALESCE(pc.alias,b.name) ASC");
    if($q){while($r=$q->fetch_assoc()){$pid=(int)$r['plot_id'];$cid=(int)$r['id'];$byPlot[$pid][]=$r;$flat[$cid]=$r;$ids[]=$cid;}$q->close();}
    $changes=[];
    if($ids){
        $in=implode(',',array_map('intval',$ids));
        $q=$link->query("SELECT id,party_member_id AS plot_char_id,resource,value,notes,created_at FROM fact_party_members_changes WHERE party_member_id IN ($in) ORDER BY created_at DESC");
        if($q){while($r=$q->fetch_assoc())$changes[(int)$r['plot_char_id']][]=$r;$q->close();}
    }
    return ['plots'=>$plots,'baseChars'=>$baseChars,'plotCharsByPlot'=>$byPlot,'plotCharsFlat'=>$flat,'changesByPlotChar'=>$changes];
}
