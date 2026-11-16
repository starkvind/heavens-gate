<?php
// Authenticated, read-only JSON facade for published Heaven's Gate content.
require_once __DIR__.'/../../helpers/tool_api.php';
require_once __DIR__.'/../../helpers/search_catalog.php';
require_once __DIR__.'/../../domains/search/queries.php';
require_once __DIR__.'/../../domains/api/content_details.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
$send = static function(array $data,int $status=200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
};
if (($_SERVER['REQUEST_METHOD']??'')!=='GET') {
    header('Allow: GET'); $send(['ok'=>false,'error'=>'method_not_allowed'],405); return;
}
$secret=hg_tool_api_config('HG_CONTENT_API_TOKEN');
$auth=(string)($_SERVER['HTTP_AUTHORIZATION']??'');
$token=preg_match('/^Bearer\s+([^\s]+)$/i',$auth,$match)?$match[1]:'';
if ($secret==='') { $send(['ok'=>false,'error'=>'service_unconfigured'],503); return; }
if ($token==='' || !hash_equals($secret,$token)) {
    header('WWW-Authenticate: Bearer realm="HeavensGateContent"');
    $send(['ok'=>false,'error'=>'unauthorized'],401); return;
}
if (!isset($link) || !($link instanceof mysqli)) {
    $send(['ok'=>false,'error'=>'service_unavailable'],503); return;
}
$resource=strtolower(trim((string)hg_request_query_value($hgRequest,'resource','catalog')));
$q=trim((string)hg_request_query_value($hgRequest,'q',''));
$id=filter_var(hg_request_query_value($hgRequest,'id'),FILTER_VALIDATE_INT);
$id=($id!==false && $id>0)?$id:0;
$page=max(1,min(10000,(int)hg_request_query_value($hgRequest,'page','1')));
$limit=max(1,min(25,(int)hg_request_query_value($hgRequest,'limit','10')));
$offset=($page-1)*$limit;
if (strlen($q)>160) { $send(['ok'=>false,'error'=>'query_too_long'],400); return; }
$aliases=[
 'characters'=>'biografias','episodes'=>'episodios','seasons'=>'temporadas',
 'inventory'=>'objetos','systems'=>'sistemas','attributes'=>'habilidades',
 'skills'=>'habilidades','traits'=>'habilidades','merits-flaws'=>'merydef',
 'powers'=>'dones','gifts'=>'dones','breeds'=>'razas','auspices'=>'auspicios',
 'tribes'=>'tribus','documents'=>'escritos','chronicles'=>'cronicas',
 'misc-systems'=>'misc_sistemas','merits'=>'merydef','flaws'=>'merydef'
];
$resource=$aliases[$resource]??$resource;
$catalog=hg_search_catalog($link);
$extra=[
 'maneuvers'=>'fact_combat_maneuvers','actions'=>'fact_actions',
 'conditions'=>'dim_character_conditions','rites'=>'fact_rites',
 'totems'=>'dim_totems','disciplines'=>'fact_discipline_powers',
 'archetypes'=>'dim_archetypes'
];
if ($resource==='catalog') {
 $items=[];
 foreach ($catalog as $name=>$cfg) if (empty($cfg['virtual'])) $items[]=['resource'=>$name,'label'=>(string)($cfg['label_text']??$name)];
 foreach ($extra as $name=>$table) $items[]=['resource'=>$name,'label'=>$name];
 $items[]=['resource'=>'forum','label'=>'Temas publicados del foro'];
 $send(['ok'=>true,'version'=>'1','read_only'=>true,'items'=>$items]); return;
}
if ($id && $resource !== 'forum') {
    $detail=hg_content_api_detail($link,$resource,$id);
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
   require_once __DIR__.'/../../helpers/schema_introspection.php';
   $table=hg_table_exists($link,'smf_messages')?'smf_messages':'';
   if ($table==='') {
     $check=mysqli_query($link,"SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'smf' AND TABLE_NAME = 'smf_messages' LIMIT 1");
     if ($check && mysqli_num_rows($check)>0) $table='smf.smf_messages';
     if ($check) mysqli_free_result($check);
   }
   if ($table==='') { $send(['ok'=>false,'error'=>'forum_unavailable'],503); return; }
   $stmt=mysqli_prepare($link,"SELECT id_msg AS id,id_topic AS topic_id,subject,poster_name,poster_time,body,id_member FROM {$table} WHERE id_topic = ? ORDER BY poster_time,id_msg LIMIT ? OFFSET ?");
   if (!$stmt) { $send(['ok'=>false,'error'=>'query_unavailable'],503); return; }
   mysqli_stmt_bind_param($stmt,'iii',$topicId,$limit,$offset);
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
 if ($where) $sql.=' WHERE '.implode(' AND ',$where);
 if (!empty($cfg['group_sql'])) $sql.=' GROUP BY '.$cfg['group_sql'];
 $sql.=' ORDER BY '.$cfg['order_sql'].' LIMIT ? OFFSET ?';
} else {
 // Extra domains are allowlisted, with public-display fields only.
 $table=$extra[$resource];$cols=[];
 $res=mysqli_query($link,'SHOW COLUMNS FROM '.$table);
 if (!$res) { $send(['ok'=>false,'error'=>'resource_unavailable'],503); return; }
 while ($row=mysqli_fetch_assoc($res)) $cols[$row['Field']]=true;
 mysqli_free_result($res);
 if (!isset($cols['id'],$cols['name'])) { $send(['ok'=>false,'error'=>'resource_unavailable'],503); return; }
 $fields=['id','name'];
 foreach (['description','mechanics_text','effect','system_id','kind','type','level','cost','pretty_id'] as $field)
   if (isset($cols[$field])) $fields[]=$field;
 $sql='SELECT '.implode(',',$fields).' FROM '.$table;
 if (isset($cols['is_active'])) $where[]='is_active = 1';
 if ($id) { $where[]='id = ?';$params[]=$id;$types.='i'; }
 elseif ($q!=='') { $where[]='name LIKE ?';$params[]='%'.$q.'%';$types.='s'; }
 if ($where) $sql.=' WHERE '.implode(' AND ',$where);
 $sql.=' ORDER BY name,id DESC LIMIT ? OFFSET ?';
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
