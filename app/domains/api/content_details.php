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
    $tables=['objetos'=>'fact_items','merydef'=>'dim_merits_flaws','habilidades'=>'dim_traits','razas'=>'dim_breeds','auspicios'=>'dim_auspices','tribus'=>'dim_tribes','misc_sistemas'=>'fact_misc_systems','escritos'=>'fact_docs','cronicas'=>'dim_chronicles','maneuvers'=>'fact_combat_maneuvers','actions'=>'fact_actions','conditions'=>'dim_character_conditions','archetypes'=>'dim_archetypes'];
    if (!isset($tables[$resource])) return null;
    $table=$tables[$resource];
    $allowed=['id','pretty_id','name','title','description','content','mechanics_text','system_id','type','kind','classification','cost','level','rank','trait_id','item_type_id','effect','requirements','prerequisites','difficulty','image_url','bibliography_id'];
    $cols=[];
    $rs=mysqli_query($link,'SHOW COLUMNS FROM '.$table);
    if (!$rs) return null;
    while ($row=mysqli_fetch_assoc($rs)) $cols[$row['Field']]=true;
    mysqli_free_result($rs);
    $selected=array_values(array_filter($allowed,static fn($col)=>isset($cols[$col])));
    if (!isset($cols['id']) || count($selected)<2) return null;
    $sql='SELECT '.implode(',',$selected).' FROM '.$table.' WHERE id = ?';
    if (isset($cols['is_active'])) $sql.=' AND is_active = 1';
    $stmt=mysqli_prepare($link,$sql.' LIMIT 1');
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt,'i',$id);
    if (!mysqli_stmt_execute($stmt)) {mysqli_stmt_close($stmt);return null;}
    $res=mysqli_stmt_get_result($stmt);
    $row=$res?mysqli_fetch_assoc($res):null;
    mysqli_stmt_close($stmt);
    return $row?:null;
}
