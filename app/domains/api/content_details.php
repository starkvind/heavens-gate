<?php
/** Public detail projections; reuse the web's existing domain queries. */
function hg_content_api_pick(array $row,array $fields): array {
    return array_intersect_key($row,array_flip($fields));
}
function hg_content_api_detail(mysqli $link,string $resource,int $id): ?array {
    if ($id <= 0) return null;
    if ($resource === 'biografias') {
        require_once __DIR__.'/../characters/detail_queries.php';
        $result=hg_characters_fetch_detail_row($link,$id);
        $row=$result['row']??null;
        if (!$row) return null;
        $data=hg_content_api_pick($row,['id','pretty_id','name','alias','concept','info_text','image_url','chronicle_id','chronicle_name','system_id','system_name','type_name','breed_name','auspice_name','tribe_name','totem_name','nature_name','demeanor_name','status_label','gender']);
        $data['chapters']=array_slice(hg_characters_fetch_chapter_participation($link,$id),0,100);
        $stmt=mysqli_prepare($link,'SELECT t.id,t.name,t.kind,t.description,ct.value FROM bridge_characters_traits ct INNER JOIN dim_traits t ON t.id = ct.trait_id WHERE ct.character_id = ? ORDER BY t.kind,t.name LIMIT 200');
        $data['traits']=[];
        if ($stmt) {
            mysqli_stmt_bind_param($stmt,'i',$id);
            if (mysqli_stmt_execute($stmt)) {
                $rs=mysqli_stmt_get_result($stmt);
                while ($rs && ($r=mysqli_fetch_assoc($rs))) $data['traits'][]=$r;
            }
            mysqli_stmt_close($stmt);
        }
        return $data;
    }
    if ($resource === 'episodios' || $resource === 'temporadas') {
        require_once __DIR__.'/../chapters/queries.php';
        if ($resource === 'episodios') {
            $row=hg_chapters_fetch_chapter_detail($link,$id);
            if (!$row) return null;
            $row['participants']=array_slice(hg_chapters_fetch_chapter_participants($link,$id)??[],0,200);
            $row['events']=hg_chapters_fetch_chapter_events($link,$id,100)??[];
        } else {
            $row=hg_chapters_fetch_season_detail($link,$id);
            if (!$row) return null;
            $row['episodes']=array_slice(hg_chapters_fetch_season_chapters($link,$id)??[],0,200);
        }
        return $row;
    }
    if ($resource === 'sistemas') {
        require_once __DIR__.'/../systems/queries.php';
        $row=hg_systems_fetch_system($link,$id);
        if (!$row) return null;
        $data=hg_content_api_pick($row,['id','pretty_id','name','description','image_url','bibliography_id']);
        $data['forms']=array_slice(hg_systems_fetch_forms($link,$id)?:[],0,100);
        return $data;
    }
    if (in_array($resource,['dones','rites','totems','disciplines'],true)) {
        require_once __DIR__.'/../powers/queries.php';
        $fetch=['dones'=>'hg_powers_fetch_gift','rites'=>'hg_powers_fetch_rite','totems'=>'hg_powers_fetch_totem','disciplines'=>'hg_powers_fetch_discipline'][$resource];
        $row=$fetch($link,$id);
        return $row?hg_content_api_pick($row,['id','pretty_id','name','description','mechanics_text','mechanics_resolved','system_name','resolved_system_name','system_id','type_name','kind','rank','level','cost','gnosis','rage','willpower','origin_name','bibliography_id','type','disc','totem_type_id','image_url']):null;
    }
    if (in_array($resource,['habilidades','merydef','maneuvers','actions','conditions','archetypes'],true)) {
        require_once __DIR__.'/../rules/queries.php';
        $func=['habilidades'=>'hg_rules_fetch_trait','merydef'=>'hg_rules_fetch_merit',
            'maneuvers'=>'hg_rules_fetch_maneuver','actions'=>'hg_rules_fetch_action',
            'conditions'=>'hg_rules_fetch_condition','archetypes'=>'hg_rules_fetch_archetype'][$resource];
        $row=$func($link,$id);
        if (!$row) return null;
        $data=hg_content_api_pick($row,['id','pretty_id','name','description','text','levels','roll',
            'difficulty','damage','actions','effect','attribute_name','skill_name','system_id',
            'system_name','kind','rule_kind','type','tipo','cost','coste','affiliation','afiliacion',
            'origin_name','category','max_instances','image_url','bibliography_id']);
        if ($resource==='maneuvers') $data['availability']=hg_rules_fetch_maneuver_availability($link,$id);
        if ($resource==='conditions') $data['effects']=hg_rules_fetch_condition_effects($link,$id);
        return $data;
    }
    return hg_content_api_basic_detail($link,$resource,$id);
}
function hg_content_api_basic_detail(mysqli $link,string $resource,int $id): ?array {
    // Explicit public-content columns only; never expose arbitrary row fields.
    $queries=[
        'objetos'=>'SELECT id,pretty_id,name,description FROM fact_items WHERE id = ?',
        'razas'=>'SELECT id,pretty_id,name,description,system_id FROM dim_breeds WHERE id = ?',
        'auspicios'=>'SELECT id,pretty_id,name,description,system_id FROM dim_auspices WHERE id = ?',
        'tribus'=>'SELECT id,pretty_id,name,description,system_id FROM dim_tribes WHERE id = ?',
        'misc_sistemas'=>'SELECT id,pretty_id,name,description,kind,system_name FROM fact_misc_systems WHERE id = ?',
        'escritos'=>'SELECT id,pretty_id,title,content FROM fact_docs WHERE id = ?',
        'cronicas'=>'SELECT id,pretty_id,name,description FROM dim_chronicles WHERE id = ?'
    ];
    if (!isset($queries[$resource])) return null;
    $stmt=mysqli_prepare($link,$queries[$resource].' LIMIT 1');
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt,'i',$id);
    if (!mysqli_stmt_execute($stmt)) {mysqli_stmt_close($stmt);return null;}
    $res=mysqli_stmt_get_result($stmt);
    $row=$res?mysqli_fetch_assoc($res):null;
    mysqli_stmt_close($stmt);
    return $row?:null;
}
