<?php

function fetchPairs(mysqli $link, string $sql): array {
    $out = [];
    $q = @$link->query($sql);
    if (!$q) return $out;
    while ($r = $q->fetch_assoc()) {
        $id = isset($r['id']) ? (int)$r['id'] : (int)($r['value'] ?? 0);
        $nm = (string)($r['name'] ?? $r['kind'] ?? $r['tipo'] ?? '');
        $out[$id] = $nm;
    }
    $q->close();
    return $out;
}

function hg_docs_admin_delete(mysqli $link,array $meta,string $tab,int $id): array {
    if($id<=0)return ['ok'=>false,'message'=>'Falta ID para borrar.'];
    if($tab==='sections'){
        $st=$link->prepare("SELECT COUNT(*) AS c FROM fact_docs WHERE section_id=?");
        if(!$st)return ['ok'=>false,'message'=>'Error al preparar comprobacion: '.$link->error];
        $st->bind_param('i',$id);$st->execute();$rs=$st->get_result();$cnt=($rs&&($r=$rs->fetch_assoc()))?(int)$r['c']:0;$st->close();
        if($cnt>0)return ['ok'=>false,'message'=>'No se puede borrar: hay documentos en esa sección ('.$cnt.').'];
    }
    $table=preg_replace('/[^a-zA-Z0-9_]/','',(string)$meta['table']);
    $pk=preg_replace('/[^a-zA-Z0-9_]/','',(string)$meta['pk']);
    $st=$link->prepare("DELETE FROM `{$table}` WHERE `{$pk}`=?");
    if(!$st)return ['ok'=>false,'message'=>'Error al preparar DELETE: '.$link->error];
    $st->bind_param('i',$id);$ok=$st->execute();$err=$st->error;$st->close();
    return $ok?['ok'=>true,'message'=>'Eliminado correctamente.']:['ok'=>false,'message'=>'Error al borrar: '.$err];
}

function hg_docs_admin_create(mysqli $link,array $meta,array $vals): array {
    $table=preg_replace('/[^a-zA-Z0-9_]/','',(string)$meta['table']);
    $cols=[];$ph=[];$types='';$bind=[];
    foreach($meta['fields'] as $f){
        $k=preg_replace('/[^a-zA-Z0-9_]/','',(string)$f['k']);
        $cols[]=$k;$ph[]='?';$types.=(($f['db']??'s')==='i')?'i':'s';$bind[]=$vals[$f['k']];
    }
    $quoted=array_map(static fn($c)=>"`{$c}`",$cols);
    $sql="INSERT INTO `{$table}` (".implode(',',$quoted).") VALUES (".implode(',',$ph).")";
    if(!empty($meta['has_timestamps'])){
        $sqlTry="INSERT INTO `{$table}` (".implode(',',$quoted).", `created_at`, `updated_at`) VALUES (".implode(',',$ph).", NOW(), NOW())";
        $probe=@$link->prepare($sqlTry);
        if($probe){$probe->close();$sql=$sqlTry;}
    }
    $st=$link->prepare($sql);
    if(!$st)return ['ok'=>false,'message'=>'Error al preparar INSERT: '.$link->error];
    $st->bind_param($types,...$bind);$ok=$st->execute();$err=$st->error;$id=(int)$link->insert_id;$st->close();
    if(!$ok)return ['ok'=>false,'message'=>'Error al crear: '.$err];
    $src=(string)($vals[$meta['name_col']]??'');
    hg_update_pretty_id_if_exists($link,$table,$id,$src);
    hg_content_touch_table($link,$table,$id);
    return ['ok'=>true,'id'=>$id,'message'=>'OK '.$meta['title'].' creado correctamente.'];
}

function hg_docs_admin_update(mysqli $link,array $meta,array $vals,int $id): array {
    if($id<=0)return ['ok'=>false,'message'=>'Falta ID para actualizar.'];
    $table=preg_replace('/[^a-zA-Z0-9_]/','',(string)$meta['table']);
    $pk=preg_replace('/[^a-zA-Z0-9_]/','',(string)$meta['pk']);
    $sets=[];$types='';$bind=[];
    foreach($meta['fields'] as $f){
        $k=preg_replace('/[^a-zA-Z0-9_]/','',(string)$f['k']);
        $sets[]="`{$k}`=?";$types.=(($f['db']??'s')==='i')?'i':'s';$bind[]=$vals[$f['k']];
    }
    $sql="UPDATE `{$table}` SET ".implode(', ',$sets);
    if(!empty($meta['has_timestamps']))$sql.=", `updated_at`=NOW()";
    $sql.=" WHERE `{$pk}`=?";$types.='i';$bind[]=$id;
    $st=$link->prepare($sql);
    if(!$st)return ['ok'=>false,'message'=>'Error al preparar UPDATE: '.$link->error];
    $st->bind_param($types,...$bind);$ok=$st->execute();$err=$st->error;$st->close();
    if(!$ok)return ['ok'=>false,'message'=>'Error al actualizar: '.$err];
    $src=(string)($vals[$meta['name_col']]??'');
    hg_update_pretty_id_if_exists($link,$table,$id,$src);
    hg_content_touch_table($link,$table,$id);
    return ['ok'=>true,'id'=>$id,'message'=>'OK '.$meta['title'].' actualizado.'];
}

function hg_docs_admin_count(mysqli $link,array $meta,string $q): int {
    $table=preg_replace('/[^a-zA-Z0-9_]/','',(string)$meta['table']);
    $nameCol=preg_replace('/[^a-zA-Z0-9_]/','',(string)$meta['name_col']);
    $sql="SELECT COUNT(*) AS c FROM `{$table}` WHERE 1=1";$types='';$params=[];
    if($q!==''){$sql.=" AND `{$nameCol}` LIKE ?";$types='s';$params[]='%'.$q.'%';}
    $st=$link->prepare($sql);if(!$st)return 0;if($types!=='')$st->bind_param($types,...$params);$st->execute();$rs=$st->get_result();$row=$rs?$rs->fetch_assoc():null;$st->close();return (int)($row['c']??0);
}

function hg_docs_admin_rows(mysqli $link,array $meta,string $q,?int $offset=null,?int $limit=null): array {
    $table=preg_replace('/[^a-zA-Z0-9_]/','',(string)$meta['table']);
    $pk=preg_replace('/[^a-zA-Z0-9_]/','',(string)$meta['pk']);
    $nameCol=preg_replace('/[^a-zA-Z0-9_]/','',(string)$meta['name_col']);
    $cols=[];foreach($meta['fields'] as $f)$cols[]='`'.preg_replace('/[^a-zA-Z0-9_]/','',(string)$f['k']).'`';$cols[]="`{$pk}`";$cols=array_values(array_unique($cols));
    $sql="SELECT ".implode(',',$cols)." FROM `{$table}` WHERE 1=1";$types='';$params=[];
    if($q!==''){$sql.=" AND `{$nameCol}` LIKE ?";$types.='s';$params[]='%'.$q.'%';}
    $sql.=" ORDER BY ".$meta['order_by'];
    if($offset!==null&&$limit!==null){$sql.=" LIMIT ?, ?";$types.='ii';$params[]=$offset;$params[]=$limit;}
    $st=$link->prepare($sql);if(!$st)return ['ok'=>false,'rows'=>[],'error'=>$link->error];
    if($types!=='')$st->bind_param($types,...$params);
    if(!$st->execute()){$err=$st->error;$st->close();return ['ok'=>false,'rows'=>[],'error'=>$err];}
    $rs=$st->get_result();$rows=[];while($rs&&($r=$rs->fetch_assoc()))$rows[]=$r;$st->close();return ['ok'=>true,'rows'=>$rows,'error'=>''];
}
