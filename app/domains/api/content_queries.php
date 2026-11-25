<?php
/** Data retrieval logic for the read-only content API; never called before authentication. */
function hg_content_api_handle(mysqli $link,array $hgRequest,callable $send): void {
$resource=strtolower(trim((string)hg_request_query_value($hgRequest,'resource','catalog')));
$requestedResource=$resource;
$q=trim((string)hg_request_query_value($hgRequest,'q',''));
$id=filter_var(hg_request_query_value($hgRequest,'id'),FILTER_VALIDATE_INT);
$id=($id!==false && $id>0)?$id:0;
$page=max(1,min(10000,(int)hg_request_query_value($hgRequest,'page','1')));
$limit=max(1,min(25,(int)hg_request_query_value($hgRequest,'limit','10')));
$offset=($page-1)*$limit;
$organizationParam=hg_request_query_value($hgRequest,'organization_id','');
$groupParam=hg_request_query_value($hgRequest,'group_id','');
$organizationId=$organizationParam===''?0:filter_var($organizationParam,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
$groupId=$groupParam===''?0:filter_var($groupParam,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if ($organizationId===false || $groupId===false) { $send(['ok'=>false,'error'=>'invalid_membership_filter'],400);return; }
$organizationId=(int)$organizationId;
$groupId=(int)$groupId;
if (($organizationId>0 || $groupId>0) && ($resource!=='biografias' && $resource!=='characters' || $id>0)) {
 $send(['ok'=>false,'error'=>'unsupported_membership_filter'],400);return;
}

if (strlen($q)>160) { $send(['ok'=>false,'error'=>'query_too_long'],400); return; }
$aliases=[
 'characters'=>'biografias','episodes'=>'episodios','seasons'=>'temporadas',
 'inventory'=>'objetos','systems'=>'sistemas','attributes'=>'habilidades',
 'skills'=>'habilidades','traits'=>'habilidades','merits-flaws'=>'merydef',
 'powers'=>'dones','gifts'=>'dones','breeds'=>'razas','auspices'=>'auspicios',
 'tribes'=>'tribus','documents'=>'escritos','chronicles'=>'cronicas',
 'misc-systems'=>'misc_sistemas','merits'=>'merydef','flaws'=>'merydef','backgrounds'=>'habilidades',
 'clans'=>'organizations','clanes'=>'organizations','organizaciones'=>'organizations',
 'packs'=>'groups','manadas'=>'groups','rolls'=>'tiradas','dice'=>'tiradas'
];
$resource=$aliases[$resource]??$resource;
$catalog=hg_search_catalog($link);
$extra=[
 'maneuvers'=>'fact_combat_maneuvers','actions'=>'fact_actions',
 'conditions'=>'dim_character_conditions','rites'=>'fact_rites',
 'totems'=>'dim_totems','disciplines'=>'fact_discipline_powers',
 'archetypes'=>'dim_archetypes','forms'=>'dim_forms','resources'=>'dim_systems_resources'
];
if ($resource==='catalog') {
 $items=[];
 foreach ($catalog as $name=>$cfg) if (empty($cfg['virtual'])) $items[]=['resource'=>$name,'label'=>(string)($cfg['label_text']??$name)];
 foreach ($extra as $name=>$table) $items[]=['resource'=>$name,'label'=>$name];
 $items[]=['resource'=>'organizations','label'=>'Organizaciones y clanes'];
 $items[]=['resource'=>'groups','label'=>'Manadas y grupos activos'];
 $items[]=['resource'=>'forum','label'=>'Temas publicados del foro'];
 $items[]=['resource'=>'tiradas','label'=>'Tiradas publicadas en el foro'];
 $send(['ok'=>true,'version'=>'1','read_only'=>true,'items'=>$items]); return;
}
if (in_array($resource, ['organizations', 'groups'], true)) {
    require_once __DIR__ . '/content_organizations.php';
    hg_content_api_structure_handle($link,$resource,$id,$q,$limit,$offset,$page,$send);
    return;
}
if ($resource === 'tiradas') {
    // No broad roll enumeration: an ID is mandatory, and it is visible only
    // through an approved SMF post in an enabled topic.
    if ($id <= 0 || $q !== '' || hg_request_query_value($hgRequest, 'topic_id', '') !== '') {
        $send(['ok'=>false,'error'=>'invalid_roll_request'],400);
        return;
    }
    require_once __DIR__ . '/content_rolls.php';
    hg_content_api_roll_handle($link, $id, $send);
    return;
}
if ($id && $resource !== 'forum') {
    $detail=hg_content_api_detail($link,$resource,$id);
    if ($detail && $resource==='habilidades' && isset($detail['rule_kind'])) {
       $kind=$detail['rule_kind'];
       if ($requestedResource==='attributes' && $kind!=='Atributos') $detail=null;
       if ($requestedResource==='skills' && !in_array($kind,['Talentos','Técnicas','Tecnicas','Conocimientos'],true)) $detail=null;
       if ($requestedResource==='backgrounds' && $kind!=='Trasfondos') $detail=null;
    }
    if (!$detail) { $send(['ok'=>false,'error'=>'not_found'],404); return; }
    $send(['ok'=>true,'resource'=>$resource,'item'=>$detail]); return;
}
if ($resource==='forum') {
 $topicId=filter_var(hg_request_query_value($hgRequest,'topic_id'),FILTER_VALIDATE_INT);
 $topicId=($topicId!==false && $topicId>0)?$topicId:0;
 $meta=null;
 if ($topicId===0) {
   $stmt=mysqli_prepare($link,'SELECT topic_id, topic_name, topic_description, topic_url FROM fact_tools_topic_viewer WHERE is_active = 1 ORDER BY sort_order, topic_name LIMIT ? OFFSET ?');
   if (!$stmt) { $send(['ok'=>false,'error'=>'query_unavailable'],503); return; }
   mysqli_stmt_bind_param($stmt,'ii',$limit,$offset);
 } else {
   $gate=mysqli_prepare($link,'SELECT topic_id, topic_name, topic_url FROM fact_tools_topic_viewer WHERE topic_id = ? AND is_active = 1 LIMIT 1');
   if (!$gate) { $send(['ok'=>false,'error'=>'query_unavailable'],503); return; }
   mysqli_stmt_bind_param($gate,'i',$topicId); mysqli_stmt_execute($gate);
   $meta=mysqli_fetch_assoc(mysqli_stmt_get_result($gate)); mysqli_stmt_close($gate);
   if (!$meta) { $send(['ok'=>false,'error'=>'not_found'],404); return; }
   require_once __DIR__ . '/content_forum.php';
   try {
     $items=hg_content_api_forum_fetch($link,$topicId,$limit,$offset,$q);
   } catch (mysqli_sql_exception $e) {
     error_log('content_api.forum_fetch: '.$e->getMessage());
     $items=null;
   }
   if ($items===null) { $send(['ok'=>false,'error'=>'forum_unavailable'],503);return; }
   $send(['ok'=>true,'resource'=>'forum','topic'=>$meta,'items'=>$items,'page'=>$page,'limit'=>$limit,'has_more'=>count($items)===$limit]); return;
 }
 if (!mysqli_stmt_execute($stmt)) { mysqli_stmt_close($stmt);$send(['ok'=>false,'error'=>'query_unavailable'],503);return; }
 $res=mysqli_stmt_get_result($stmt);$items=[];
 while ($row=mysqli_fetch_assoc($res)) $items[]=$row;
 mysqli_stmt_close($stmt);
 $send(['ok'=>true,'resource'=>'forum','topic'=>$meta,'items'=>$items,'page'=>$page,'limit'=>$limit,'has_more'=>count($items)===$limit]); return;
}
$cfg=$catalog[$resource]??null;
if ($cfg && !empty($cfg['virtual'])) $cfg=null;
if (!$cfg && !isset($extra[$resource])) { $send(['ok'=>false,'error'=>'unknown_resource'],404); return; }
$params=[];$types='';$where=[];
if ($cfg) {
 $sql='SELECT '.$cfg['id_expr'].' AS id,'.$cfg['title_expr'].' AS title,'.$cfg['excerpt_expr'].' AS description,'.$cfg['secondary_expr'].' AS secondary FROM '.$cfg['from_sql'];
 if ($id) { $where[]=$cfg['id_expr'].' = ?';$params[]=$id;$types.='i'; }
 elseif ($q!=='') $where[]=hg_search_query_build_where($cfg['search_fields']??[],[$q],$params,$types);
 if ($resource==='biografias') {
    // Explicit active membership bridges only; EXISTS avoids duplicate biographies.
    if ($organizationId>0) {
        $where[]='EXISTS (SELECT 1 FROM bridge_characters_organizations bco WHERE bco.character_id = src.id AND bco.organization_id = ? AND (bco.is_active = 1 OR bco.is_active IS NULL))';
        $params[]=$organizationId;
        $types.='i';
    }
    if ($groupId>0) {
        $where[]='EXISTS (SELECT 1 FROM bridge_characters_groups bcg INNER JOIN dim_groups dg ON dg.id = bcg.group_id AND dg.is_active = 1 WHERE bcg.character_id = src.id AND bcg.group_id = ? AND (bcg.is_active = 1 OR bcg.is_active IS NULL))';
        $params[]=$groupId;
        $types.='i';
    }
 }
 if ($resource==='habilidades' && in_array($requestedResource,['attributes','skills','backgrounds'],true)) {
    if ($requestedResource==='attributes') $where[]="src.kind = 'Atributos'";
    elseif ($requestedResource==='backgrounds') $where[]="src.kind = 'Trasfondos'";
    else $where[]="src.kind IN ('Talentos','Técnicas','Tecnicas','Conocimientos')";
 }
 if ($where) $sql.=' WHERE '.implode(' AND ',$where);
 if (!empty($cfg['group_sql'])) $sql.=' GROUP BY '.$cfg['group_sql'];
 $sql.=' ORDER BY '.$cfg['order_sql'].' LIMIT ? OFFSET ?';
} else {
 // Extra domains are allowlisted, with public-display fields only.
 $table=$extra[$resource];
 $titleField=$resource==='forms'?'form':'name';
 $sql='SELECT id,'.$titleField.' AS name FROM '.$table;
 if ($id) { $where[]='id = ?';$params[]=$id;$types.='i'; }
 elseif ($q!=='') { $where[]=$titleField.' LIKE ?';$params[]='%'.$q.'%';$types.='s'; }
 if ($where) $sql.=' WHERE '.implode(' AND ',$where);
 $sql.=' ORDER BY '.$titleField.',id DESC LIMIT ? OFFSET ?';
}
$params[]=$id?1:$limit;$params[]=$id?0:$offset;$types.='ii';
$stmt=mysqli_prepare($link,$sql);
if (!$stmt) { $send(['ok'=>false,'error'=>'query_unavailable'],503); return; }
mysqli_stmt_bind_param($stmt,$types,...$params);
if (!mysqli_stmt_execute($stmt)) { mysqli_stmt_close($stmt);$send(['ok'=>false,'error'=>'query_unavailable'],503);return; }
$res=mysqli_stmt_get_result($stmt);$items=[];
while ($row=mysqli_fetch_assoc($res)) $items[]=$row;
mysqli_stmt_close($stmt);
if ($id && !$items) { $send(['ok'=>false,'error'=>'not_found'],404);return; }
$send(['ok'=>true,'resource'=>$resource,'items'=>$items,'page'=>$page,'limit'=>$id?1:$limit,'has_more'=>!$id && count($items)===$limit]);

}
