<?php

function acl_table_exists(mysqli $db, string $table): bool {
    static $tables = [
        'fact_characters' => true,
        'fact_docs' => true,
        'bridge_characters_docs' => true,
        'fact_external_links' => true,
        'bridge_characters_external_links' => true,
        'dim_chronicles' => true,
        'dim_realities' => true,
    ];
    return isset($tables[$table]);
}

function acl_column_exists(mysqli $db, string $table, string $column): bool {
    return $table === 'fact_characters' && $column === 'reality_id';
}

function acl_doc_link_mutation(mysqli $link,string $action,int $characterId,int $docId,string $label,int $sort): array {
    if($docId<=0)return ['ok'=>false,'message'=>$action==='link_doc'?'Selecciona un documento vÃ¡lido.':'Fila de documento invalida.'];
    if($action==='link_doc'){
        $st=$link->prepare("INSERT INTO bridge_characters_docs (character_id, doc_id, relation_label, sort_order) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE relation_label=VALUES(relation_label), sort_order=VALUES(sort_order), updated_at=NOW()");
        if(!$st)return ['ok'=>false,'message'=>'Error al preparar vinculo de documento.'];
        $st->bind_param('iisi',$characterId,$docId,$label,$sort);$ok=$st->execute();$err=$st->error;$st->close();
        return $ok?['ok'=>true,'message'=>'Documento vinculado al personaje.']:['ok'=>false,'message'=>'Error al vincular documento: '.$err];
    }
    if($action==='update_doc_link'){
        $st=$link->prepare("UPDATE bridge_characters_docs SET relation_label=?, sort_order=?, updated_at=NOW() WHERE character_id=? AND doc_id=?");
        if(!$st)return ['ok'=>false,'message'=>'Error al preparar actualizacion de documento: '.$link->error];
        $st->bind_param('siii',$label,$sort,$characterId,$docId);$ok=$st->execute();$err=$st->error;$st->close();
        return $ok?['ok'=>true,'message'=>'Vinculo de documento actualizado.']:['ok'=>false,'message'=>'Error al actualizar vinculo de documento: '.$err];
    }
    $st=$link->prepare("DELETE FROM bridge_characters_docs WHERE character_id=? AND doc_id=?");
    if(!$st)return ['ok'=>false,'message'=>'Error al preparar borrado de documento: '.$link->error];
    $st->bind_param('ii',$characterId,$docId);$ok=$st->execute();$err=$st->error;$st->close();
    return $ok?['ok'=>true,'message'=>'Vinculo de documento eliminado.']:['ok'=>false,'message'=>'Error al eliminar vinculo de documento: '.$err];
}
function acl_external_link_mutation(mysqli $link,string $action,int $characterId,int $externalId,string $label,int $sort): array {
    if($externalId<=0)return ['ok'=>false,'message'=>$action==='link_external'?'Selecciona un enlace externo vÃ¡lido.':'Fila de enlace externo invalida.'];
    if($action==='link_external'){
        $st=$link->prepare("INSERT INTO bridge_characters_external_links (character_id, external_link_id, relation_label, sort_order) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE relation_label=VALUES(relation_label), sort_order=VALUES(sort_order), updated_at=NOW()");
        if(!$st)return ['ok'=>false,'message'=>'Error al preparar vinculo de enlace externo.'];
        $st->bind_param('iisi',$characterId,$externalId,$label,$sort);$ok=$st->execute();$err=$st->error;$st->close();
        return $ok?['ok'=>true,'message'=>'Enlace externo vinculado al personaje.']:['ok'=>false,'message'=>'Error al vincular enlace externo: '.$err];
    }
    if($action==='update_external_link'){
        $st=$link->prepare("UPDATE bridge_characters_external_links SET relation_label=?, sort_order=?, updated_at=NOW() WHERE character_id=? AND external_link_id=?");
        if(!$st)return ['ok'=>false,'message'=>'Error al preparar actualizacion de enlace externo: '.$link->error];
        $st->bind_param('siii',$label,$sort,$characterId,$externalId);$ok=$st->execute();$err=$st->error;$st->close();
        return $ok?['ok'=>true,'message'=>'Vinculo de enlace externo actualizado.']:['ok'=>false,'message'=>'Error al actualizar vinculo externo: '.$err];
    }
    $st=$link->prepare("DELETE FROM bridge_characters_external_links WHERE character_id=? AND external_link_id=?");
    if(!$st)return ['ok'=>false,'message'=>'Error al preparar borrado de enlace externo: '.$link->error];
    $st->bind_param('ii',$characterId,$externalId);$ok=$st->execute();$err=$st->error;$st->close();
    return $ok?['ok'=>true,'message'=>'Vinculo de enlace externo eliminado.']:['ok'=>false,'message'=>'Error al eliminar vinculo externo: '.$err];
}
function acl_fetch_characters(mysqli $link,int $chronicleId,int $realityId,bool $hasChronicles,bool $hasReality): array {
    $chrName=$hasChronicles?"COALESCE(ch.name, '') AS chronicle_name":"'' AS chronicle_name";
    $reality=$hasReality?", c.reality_id, COALESCE(r.name, '') AS reality_name":", 0 AS reality_id, '' AS reality_name";
    $sql="SELECT c.id,c.name,c.alias,c.chronicle_id,{$chrName}{$reality} FROM fact_characters c";
    if($hasChronicles)$sql.=" LEFT JOIN dim_chronicles ch ON ch.id=c.chronicle_id";
    if($hasReality)$sql.=" LEFT JOIN dim_realities r ON r.id=c.reality_id";
    $sql.=" WHERE 1=1";$types='';$params=[];
    if($chronicleId>0){$sql.=" AND c.chronicle_id=?";$types.='i';$params[]=$chronicleId;}
    if($realityId>0&&$hasReality){$sql.=" AND c.reality_id=?";$types.='i';$params[]=$realityId;}
    $sql.=" ORDER BY c.name ASC";
    $st=$link->prepare($sql);if(!$st)return [];
    if($types!=='')$st->bind_param($types,...$params);$st->execute();$rs=$st->get_result();$rows=[];while($rs&&($r=$rs->fetch_assoc()))$rows[]=$r;$st->close();return $rows;
}
function acl_fetch_pairs(mysqli $link,string $sql): array {
    $rows=[];$rs=$link->query($sql);if($rs){while($r=$rs->fetch_assoc())$rows[]=$r;$rs->close();}return $rows;
}
function acl_fetch_linked_docs(mysqli $link,int $characterId): array {
    if($characterId<=0)return [];
    $sql="SELECT b.doc_id,COALESCE(b.relation_label,'') AS relation_label,COALESCE(b.sort_order,0) AS sort_order,d.title,d.pretty_id,COALESCE(c.kind,'') AS category_name FROM bridge_characters_docs b INNER JOIN fact_docs d ON d.id=b.doc_id LEFT JOIN dim_doc_categories c ON c.id=d.section_id WHERE b.character_id=? ORDER BY b.sort_order ASC,d.title ASC";
    $st=$link->prepare($sql);if(!$st)return [];$st->bind_param('i',$characterId);$st->execute();$rs=$st->get_result();$rows=[];while($rs&&($r=$rs->fetch_assoc()))$rows[]=$r;$st->close();return $rows;
}
function acl_fetch_linked_external(mysqli $link,int $characterId): array {
    if($characterId<=0)return [];
    $sql="SELECT b.external_link_id,COALESCE(b.relation_label,'') AS relation_label,COALESCE(b.sort_order,0) AS sort_order,l.title,l.url,l.kind,l.is_active FROM bridge_characters_external_links b INNER JOIN fact_external_links l ON l.id=b.external_link_id WHERE b.character_id=? ORDER BY b.sort_order ASC,l.title ASC";
    $st=$link->prepare($sql);if(!$st)return [];$st->bind_param('i',$characterId);$st->execute();$rs=$st->get_result();$rows=[];while($rs&&($r=$rs->fetch_assoc()))$rows[]=$r;$st->close();return $rows;
}
function acl_fetch_current_character(mysqli $link,int $id,bool $hasChronicles,bool $hasReality): array {
    if($id<=0)return ['name'=>'','chronicle_name'=>'','reality_name'=>''];
    $chr=$hasChronicles?"COALESCE(ch.name, '') AS chronicle_name":"'' AS chronicle_name";
    $real=$hasReality?", COALESCE(r.name, '') AS reality_name":", '' AS reality_name";
    $sql="SELECT c.name,{$chr}{$real} FROM fact_characters c";
    if($hasChronicles)$sql.=" LEFT JOIN dim_chronicles ch ON ch.id=c.chronicle_id";
    if($hasReality)$sql.=" LEFT JOIN dim_realities r ON r.id=c.reality_id";
    $sql.=" WHERE c.id=? LIMIT 1";$st=$link->prepare($sql);if(!$st)return ['name'=>'','chronicle_name'=>'','reality_name'=>''];
    $st->bind_param('i',$id);$st->execute();$rs=$st->get_result();$row=$rs?$rs->fetch_assoc():null;$st->close();
    return $row?:['name'=>'','chronicle_name'=>'','reality_name'=>''];
}
