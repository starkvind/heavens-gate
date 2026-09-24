<?php

include_once(__DIR__ . '/admin_service.php');
include_once(__DIR__ . '/../../helpers/pretty.php');

if (!function_exists('hg_characters_admin_fetch_pairs')) {
    function hg_characters_admin_fetch_pairs(mysqli $link, string $sql): array
    {
        $out = [];
        $result = $link->query($sql);
        if (!$result) return $out;

        while ($row = $result->fetch_assoc()) {
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) continue;
            $out[$id] = (string)($row['name'] ?? '');
        }
        $result->close();

        return $out;
    }
}

if (!function_exists('hg_characters_admin_status_state')) {
    function hg_characters_admin_status_state(mysqli $link): array
    {
        $hasStatusId = pjs_table_has_column($link, 'fact_characters', 'status_id');
        $hasStatusDim = pjs_table_exists($link, 'dim_character_status');
        $options = [];
        $defaultId = 0;
        $inactiveId = 0;

        if ($hasStatusDim) {
            $result = $link->query("SELECT id, label, is_active FROM dim_character_status ORDER BY sort_order ASC, label ASC");
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $id = (int)($row['id'] ?? 0);
                    $label = (string)($row['label'] ?? '');
                    if ($id <= 0 || $label === '') continue;
                    $options[$id] = $label;
                    if ((int)($row['is_active'] ?? 0) === 1 && $defaultId <= 0) $defaultId = $id;
                }
                $result->close();
            }
            $stmt = $link->prepare("SELECT id FROM dim_character_status WHERE is_active=0 ORDER BY sort_order ASC, label ASC LIMIT 1");
            if ($stmt) {
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result && ($row = $result->fetch_assoc())) $inactiveId = (int)($row['id'] ?? 0);
                $stmt->close();
            }
        }

        if ($defaultId <= 0 && !empty($options)) $defaultId = (int)array_key_first($options);
        if ($inactiveId <= 0) {
            $preferred = ['inactivo','inactiva','desactivado','desactivada','retirado','retirada','baja','fallecido','fallecida','muerto','muerta'];
            foreach ($options as $id => $label) {
                $norm = function_exists('mb_strtolower') ? mb_strtolower((string)$label, 'UTF-8') : strtolower((string)$label);
                if (in_array($norm, $preferred, true)) { $inactiveId = (int)$id; break; }
            }
        }

        return [
            'has_status_id_col' => $hasStatusId,
            'has_status_dim' => $hasStatusDim,
            'options' => $options,
            'default_id' => $defaultId,
            'inactive_id' => $inactiveId,
        ];
    }
}

if (!function_exists('hg_characters_admin_reference_catalogs')) {
    function hg_characters_admin_reference_catalogs(mysqli $link): array
    {
        return [
            'chronicles' => hg_characters_admin_fetch_pairs($link, "SELECT id, name FROM dim_chronicles ORDER BY name"),
            'organizations' => hg_characters_admin_fetch_pairs($link, "SELECT id, name FROM dim_organizations ORDER BY name"),
            'players' => hg_characters_admin_fetch_pairs($link, "SELECT id, name FROM dim_players ORDER BY name"),
            'systems' => hg_characters_admin_fetch_pairs($link, "SELECT id, name FROM dim_systems ORDER BY name"),
            'totems' => hg_characters_admin_fetch_pairs($link, "SELECT id, name FROM dim_totems ORDER BY name"),
            'character_types' => hg_characters_admin_fetch_pairs($link, "SELECT id, kind AS name FROM dim_character_types ORDER BY sort_order, kind"),
            'archetypes' => hg_characters_admin_fetch_pairs($link, "SELECT id, name FROM dim_archetypes ORDER BY name"),
            'groups' => hg_characters_admin_fetch_pairs($link, "SELECT id, name FROM dim_groups ORDER BY name"),
            'gifts' => hg_characters_admin_fetch_pairs($link, "SELECT id, CONCAT(name, ' (', gift_group, ')') AS name FROM fact_gifts"),
            'disciplines' => hg_characters_admin_fetch_pairs($link, "SELECT id, name FROM dim_discipline_types ORDER BY name"),
            'rites' => hg_characters_admin_fetch_pairs($link, "SELECT nr.id, CONCAT(nr.name, ' (', ntr.name, ')') AS name FROM fact_rites nr LEFT JOIN dim_rite_types ntr ON nr.kind = ntr.id"),
        ];
    }
}

if (!function_exists('hg_characters_admin_complex_catalogs')) {
    function hg_characters_admin_complex_catalogs(mysqli $link): array
    {
        $disciplinePowerToType = pjs_fetch_discipline_power_type_map($link);

        $merits = [];
        $stmt = $link->prepare("SELECT id, name, kind, cost FROM dim_merits_flaws ORDER BY kind DESC, cost, name");
        if ($stmt) {
            $stmt->execute(); $result = $stmt->get_result();
            while ($result && ($row = $result->fetch_assoc())) {
                $merits[] = ['id'=>(int)$row['id'],'name'=>(string)$row['name'],'tipo'=>(string)$row['kind'],'coste'=>(string)($row['cost'] ?? '')];
            }
            $stmt->close();
        }

        $items = [];
        $stmt = $link->prepare("SELECT id, name, item_type_id FROM fact_items ORDER BY name");
        if ($stmt) {
            $stmt->execute(); $result = $stmt->get_result();
            while ($result && ($row = $result->fetch_assoc())) {
                $items[] = ['id'=>(int)$row['id'],'name'=>(string)$row['name'],'tipo'=>(int)($row['item_type_id'] ?? 0)];
            }
            $stmt->close();
        }

        $hasResources = pjs_table_exists($link, 'dim_systems_resources');
        $hasSystemResources = pjs_table_exists($link, 'bridge_systems_resources_to_system');
        $hasCharResources = pjs_table_exists($link, 'bridge_characters_system_resources');
        $hasCharResourcesLog = pjs_table_exists($link, 'bridge_characters_system_resources_log');
        $resources = [];
        $resourcesById = [];
        $resourcesBySystem = [];

        if ($hasResources) {
            $stmt = $link->prepare("SELECT id, name, kind, sort_order FROM dim_systems_resources ORDER BY kind, sort_order, name");
            if ($stmt) {
                $stmt->execute(); $result = $stmt->get_result();
                while ($result && ($row = $result->fetch_assoc())) {
                    $v = ['id'=>(int)$row['id'],'name'=>(string)$row['name'],'kind'=>(string)$row['kind'],'sort_order'=>(int)($row['sort_order'] ?? 0)];
                    $resources[] = $v; $resourcesById[(int)$row['id']] = $v;
                }
                $stmt->close();
            }
        }

        if ($hasSystemResources && $hasResources) {
            $hasActive = pjs_table_has_column($link, 'bridge_systems_resources_to_system', 'is_active');
            $sql = "SELECT b.system_id, r.id, r.name, r.kind, r.sort_order
                    FROM bridge_systems_resources_to_system b
                    INNER JOIN dim_systems_resources r ON r.id = b.resource_id";
            if ($hasActive) $sql .= " WHERE b.is_active = 1";
            $sql .= " ORDER BY b.system_id, r.kind, r.sort_order, r.name";
            $result = $link->query($sql);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $sid = (int)$row['system_id'];
                    $resourcesBySystem[$sid][] = ['id'=>(int)$row['id'],'name'=>(string)$row['name'],'kind'=>(string)$row['kind'],'sort_order'=>(int)($row['sort_order'] ?? 0)];
                }
                $result->close();
            }
        }

        $traits = [];
        $validTraits = [];
        $blockedMonster = [];
        $kindSeen = [];
        $stmt = $link->prepare("SELECT id, name, kind, classification FROM dim_traits WHERE kind IS NOT NULL AND TRIM(kind) <> '' ORDER BY kind, name");
        if ($stmt) {
            $stmt->execute(); $result = $stmt->get_result();
            while ($result && ($row = $result->fetch_assoc())) {
                $kind = (string)$row['kind']; $classification = (string)($row['classification'] ?? ''); $id = (int)$row['id'];
                if ($id <= 0 || $kind === '') continue;
                $validTraits[$id] = true;
                $traits[] = ['id'=>$id,'name'=>(string)$row['name'],'kind'=>$kind,'classification'=>$classification];
                $kindSeen[$kind] = true;
                $kindNorm = function_exists('mb_strtolower') ? mb_strtolower($kind, 'UTF-8') : strtolower($kind);
                $classNorm = function_exists('mb_strtolower') ? mb_strtolower($classification, 'UTF-8') : strtolower($classification);
                $kindNorm = str_replace('é', 'e', $kindNorm);
                $isSec = strpos($classNorm, '002 secundarias') === 0;
                if ($kindNorm === 'trasfondos' || ($isSec && in_array($kindNorm, ['talentos','tecnicas','conocimientos'], true))) $blockedMonster[$id] = true;
            }
            $stmt->close();
        }

        $traitOrder = ['Atributos','Talentos','Técnicas','Conocimientos','Trasfondos'];
        foreach (array_keys($kindSeen) as $kind) if (!in_array($kind, $traitOrder, true)) $traitOrder[] = $kind;

        $traitSetOrder = [];
        $result = $link->query("SELECT system_id, trait_id, sort_order FROM fact_trait_sets WHERE is_active=1");
        if ($result) {
            while ($row = $result->fetch_assoc()) $traitSetOrder[(int)$row['system_id']][(int)$row['trait_id']] = (int)$row['sort_order'];
            $result->close();
        }

        return compact(
            'disciplinePowerToType','merits','items','hasResources','hasSystemResources',
            'hasCharResources','hasCharResourcesLog','resources','resourcesById','resourcesBySystem',
            'traits','validTraits','blockedMonster','traitOrder','traitSetOrder'
        );
    }
}

if (!function_exists('hg_characters_admin_system_dimensions')) {
    function hg_characters_admin_system_dimensions(mysqli $link, array $systems): array
    {
        $out = [];
        foreach ([
            'breed' => ['table'=>'dim_breeds','bridge'=>[['bridge_systems_ex_races','race_id'],['bridge_systems_ex_races','breed_id'],['bridge_systems_ex_breeds','breed_id']]],
            'auspice' => ['table'=>'dim_auspices','bridge'=>[['bridge_systems_ex_auspices','auspice_id']]],
            'tribe' => ['table'=>'dim_tribes','bridge'=>[['bridge_systems_ex_tribes','tribe_id']]],
        ] as $key => $cfg) {
            $options=[]; $bySystem=[]; $idToSystem=[]; $allowed=[]; $seen=[];
            $stmt=$link->prepare("SELECT id, name, system_id FROM {$cfg['table']} ORDER BY system_id, name");
            if ($stmt) {
                $stmt->execute(); $result=$stmt->get_result();
                while ($result && ($row=$result->fetch_assoc())) {
                    $id=(int)$row['id']; $name=(string)$row['name']; $sid=(int)($row['system_id'] ?? 0);
                    $options[$id] = ($key === 'breed' && $sid>0 && isset($systems[$sid])) ? $name.' ('.$systems[$sid].')' : $name;
                    $idToSystem[$id]=$sid;
                    if ($sid>0) $allowed[$id][$sid]=true;
                    if (!isset($seen[$sid][$id])) { $bySystem[$sid][]=['id'=>$id,'name'=>$name]; $seen[$sid][$id]=true; }
                }
                $stmt->close();
            }

            foreach ($cfg['bridge'] as [$bridge,$fk]) {
                if (!pjs_table_exists($link,$bridge) || !pjs_table_has_column($link,$bridge,'system_id') || !pjs_table_has_column($link,$bridge,$fk)) continue;
                $hasActive=pjs_table_has_column($link,$bridge,'is_active');
                $sql="SELECT b.system_id AS system_id, d.id AS id, d.name AS name FROM {$bridge} b INNER JOIN {$cfg['table']} d ON d.id=b.{$fk}";
                if ($hasActive) $sql.=" WHERE (b.is_active = 1 OR b.is_active IS NULL)";
                $sql.=" ORDER BY b.system_id, d.name";
                $stmt=$link->prepare($sql);
                if ($stmt) {
                    $stmt->execute(); $result=$stmt->get_result();
                    while ($result && ($row=$result->fetch_assoc())) {
                        $sid=(int)$row['system_id']; $id=(int)$row['id']; $name=(string)$row['name'];
                        if ($sid<=0 || $id<=0 || $name==='') continue;
                        $allowed[$id][$sid]=true;
                        if (!isset($seen[$sid][$id])) { $bySystem[$sid][]=['id'=>$id,'name'=>$name]; $seen[$sid][$id]=true; }
                    }
                    $stmt->close();
                }
                break;
            }
            $out[$key]=['options'=>$options,'by_system'=>$bySystem,'id_to_system'=>$idToSystem,'allowed'=>$allowed];
        }
        return $out;
    }
}

if (!function_exists('hg_characters_admin_group_maps')) {
    function hg_characters_admin_group_maps(mysqli $link): array
    {
        $byId=[]; $byOrg=[];
        $stmt=$link->prepare("SELECT b.group_id AS group_id, m.name AS group_name, b.organization_id AS organization_id
                              FROM bridge_organizations_groups b
                              INNER JOIN dim_groups m ON m.id=b.group_id
                              INNER JOIN dim_organizations o ON o.id=b.organization_id
                              WHERE (b.is_active=1 OR b.is_active IS NULL)
                              ORDER BY b.group_id ASC, b.updated_at DESC, b.created_at DESC, b.organization_id DESC, m.name ASC");
        if ($stmt) {
            $stmt->execute(); $result=$stmt->get_result(); $seen=[];
            while ($result && ($row=$result->fetch_assoc())) {
                $gid=(int)$row['group_id']; if ($gid<=0 || isset($seen[$gid])) continue;
                $seen[$gid]=true; $oid=(int)$row['organization_id'];
                $byId[$gid]=$oid; $byOrg[$oid][]=['id'=>$gid,'name'=>(string)$row['group_name']];
            }
            $stmt->close();
        }
        return ['group_to_org'=>$byId,'by_org'=>$byOrg];
    }
}

if (!function_exists('hg_characters_admin_inherited_totem')) {
    function hg_characters_admin_inherited_totem(mysqli $link, int $groupId, int $organizationId): int
    {
        $totem=0;
        if ($groupId>0) {
            $stmt=$link->prepare("SELECT totem_id FROM dim_groups WHERE id=? LIMIT 1");
            if ($stmt) { $stmt->bind_param('i',$groupId); $stmt->execute(); $result=$stmt->get_result(); if($result&&($row=$result->fetch_assoc())) $totem=(int)($row['totem_id']??0); $stmt->close(); }
        }
        if ($totem<=0 && $organizationId>0) {
            $stmt=$link->prepare("SELECT totem_id FROM dim_organizations WHERE id=? LIMIT 1");
            if ($stmt) { $stmt->bind_param('i',$organizationId); $stmt->execute(); $result=$stmt->get_result(); if($result&&($row=$result->fetch_assoc())) $totem=(int)($row['totem_id']??0); $stmt->close(); }
        }
        return $totem;
    }
}

if (!function_exists('hg_characters_admin_current_image')) {
    function hg_characters_admin_current_image(mysqli $link, int $id): array
    {
        $stmt=$link->prepare("SELECT image_url FROM fact_characters WHERE id=?");
        if(!$stmt) return ['exists'=>false,'image_url'=>''];
        $stmt->bind_param('i',$id); $stmt->execute(); $result=$stmt->get_result(); $row=$result?$result->fetch_assoc():null; $stmt->close();
        return ['exists'=>is_array($row),'image_url'=>(string)($row['image_url']??'')];
    }
}

if (!function_exists('hg_characters_admin_create')) {
    function hg_characters_admin_create(mysqli $link, string $kindColumn, array $v): array
    {
        $sql="INSERT INTO fact_characters
              (name,alias,garou_name,gender,concept,chronicle_id,player_id,character_type_id,image_url,notes,text_color,`{$kindColumn}`,system_id,totem_id,status_id,rank,info_text,breed_id,auspice_id,tribe_id,nature_id,demeanor_id)
              VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $stmt=$link->prepare($sql);
        if(!$stmt) return ['ok'=>false,'error'=>'prepare','message'=>$link->error];
        $img='';
        $stmt->bind_param("sssssiiissssiiissiiiii",
            $v['name'],$v['alias'],$v['garou_name'],$v['gender'],$v['concept'],$v['chronicle_id'],$v['player_id'],$v['character_type_id'],
            $img,$v['notes'],$v['text_color'],$v['kind'],$v['system_id'],$v['totem_id'],$v['status_id'],$v['rank'],$v['info_text'],
            $v['breed_id'],$v['auspice_id'],$v['tribe_id'],$v['nature_id'],$v['demeanor_id']
        );
        if(!$stmt->execute()){ $m=$stmt->error; $stmt->close(); return ['ok'=>false,'error'=>'execute','message'=>$m]; }
        $id=(int)$stmt->insert_id; $stmt->close();
        hg_update_pretty_id_if_exists($link,'fact_characters',$id,(string)$v['name']);
        return ['ok'=>true,'id'=>$id];
    }
}

if (!function_exists('hg_characters_admin_update')) {
    function hg_characters_admin_update(mysqli $link, string $kindColumn, int $id, array $v): array
    {
        $sql="UPDATE fact_characters SET name=?,alias=?,garou_name=?,gender=?,concept=?,chronicle_id=?,player_id=?,character_type_id=?,system_id=?,text_color=?,`{$kindColumn}`=?,breed_id=?,auspice_id=?,tribe_id=?,nature_id=?,demeanor_id=?,totem_id=?,status_id=?,rank=?,info_text=?,notes=? WHERE id=?";
        $stmt=$link->prepare($sql);
        if(!$stmt) return ['ok'=>false,'error'=>'prepare','message'=>$link->error];
        $stmt->bind_param("sssssiiiissiiiiiiisssi",
            $v['name'],$v['alias'],$v['garou_name'],$v['gender'],$v['concept'],$v['chronicle_id'],$v['player_id'],$v['character_type_id'],$v['system_id'],$v['text_color'],$v['kind'],
            $v['breed_id'],$v['auspice_id'],$v['tribe_id'],$v['nature_id'],$v['demeanor_id'],$v['totem_id'],$v['status_id'],$v['rank'],$v['info_text'],$v['notes'],$id
        );
        if(!$stmt->execute()){ $m=$stmt->error; $stmt->close(); return ['ok'=>false,'error'=>'execute','message'=>$m]; }
        $stmt->close(); hg_update_pretty_id_if_exists($link,'fact_characters',$id,(string)$v['name']);
        return ['ok'=>true];
    }
}

if (!function_exists('hg_characters_admin_set_image')) {
    function hg_characters_admin_set_image(mysqli $link, int $id, string $url): bool
    {
        $stmt=$link->prepare("UPDATE fact_characters SET image_url=? WHERE id=?");
        if(!$stmt) return false;
        $stmt->bind_param('si',$url,$id); $ok=$stmt->execute(); $stmt->close(); return (bool)$ok;
    }
}

if (!function_exists('hg_characters_admin_clear_image')) {
    function hg_characters_admin_clear_image(mysqli $link, int $id): bool
    {
        $stmt=$link->prepare("UPDATE fact_characters SET image_url='' WHERE id=?");
        if(!$stmt) return false;
        $stmt->bind_param('i',$id); $ok=$stmt->execute(); $stmt->close(); return (bool)$ok;
    }
}

if (!function_exists('hg_characters_admin_soft_delete')) {
    function hg_characters_admin_soft_delete(mysqli $link, int $id, int $inactiveStatusId): bool
    {
        $stmt=$link->prepare("UPDATE fact_characters SET status_id=? WHERE id=?");
        if(!$stmt) return false;
        $stmt->bind_param('ii',$inactiveStatusId,$id); $ok=$stmt->execute(); $stmt->close(); return (bool)$ok;
    }
}

if (!function_exists('hg_characters_admin_count')) {
    function hg_characters_admin_count(mysqli $link, int $chronicleId, int $groupId, string $q): int
    {
        $where="WHERE 1=1"; $types=''; $params=[];
        if($chronicleId>0){$where.=" AND p.chronicle_id=?";$types.='i';$params[]=$chronicleId;}
        if($groupId>0){$where.=" AND pgb.group_id=?";$types.='i';$params[]=$groupId;}
        if($q!==''){$where.=" AND p.name LIKE ?";$types.='s';$params[]='%'.$q.'%';}
        $sql="SELECT COUNT(*) AS c FROM fact_characters p
              LEFT JOIN (SELECT character_id,MIN(group_id) AS group_id FROM bridge_characters_groups WHERE (is_active=1 OR is_active IS NULL) GROUP BY character_id) pgb ON pgb.character_id=p.id
              LEFT JOIN (SELECT character_id,MIN(organization_id) AS organization_id FROM bridge_characters_organizations WHERE (is_active=1 OR is_active IS NULL) GROUP BY character_id) pcb ON pcb.character_id=p.id
              {$where}";
        $stmt=$link->prepare($sql); if(!$stmt) return 0; if($types!=='')$stmt->bind_param($types,...$params); $stmt->execute(); $result=$stmt->get_result(); $count=$result&&($row=$result->fetch_assoc())?(int)$row['c']:0; $stmt->close(); return $count;
    }
}

if (!function_exists('hg_characters_admin_fetch_page')) {
    function hg_characters_admin_fetch_page(mysqli $link, string $kindColumn, int $chronicleId, int $groupId, string $q, int $offset, int $limit): array
    {
        $where="WHERE 1=1"; $types=''; $params=[];
        if($chronicleId>0){$where.=" AND p.chronicle_id=?";$types.='i';$params[]=$chronicleId;}
        if($groupId>0){$where.=" AND pgb.group_id=?";$types.='i';$params[]=$groupId;}
        if($q!==''){$where.=" AND p.name LIKE ?";$types.='s';$params[]='%'.$q.'%';}
        $sql="SELECT p.id,p.name,p.alias,p.garou_name,p.gender,p.concept,p.chronicle_id,p.player_id,p.system_id,p.text_color,p.breed_id,p.auspice_id,p.tribe_id,p.nature_id,p.demeanor_id,
              COALESCE(pgb.group_id,0) AS manada,COALESCE(pcb.organization_id,0) AS clan,p.image_url,p.character_type_id,p.totem_id,p.`{$kindColumn}` AS kind,
              nj.name AS jugador_,nc.name AS cronica_,nr.name AS raza_n,na.name AS auspicio_n,nt.name AS tribu_n,ds.name AS sistema_n,dt.name AS totem_n,nm.name AS manada_n,nc2.name AS clan_n,af.kind AS tipo_n
              FROM fact_characters p
              LEFT JOIN (SELECT character_id,MIN(group_id) AS group_id FROM bridge_characters_groups WHERE (is_active=1 OR is_active IS NULL) GROUP BY character_id) pgb ON pgb.character_id=p.id
              LEFT JOIN (SELECT character_id,MIN(organization_id) AS organization_id FROM bridge_characters_organizations WHERE (is_active=1 OR is_active IS NULL) GROUP BY character_id) pcb ON pcb.character_id=p.id
              LEFT JOIN dim_players nj ON p.player_id=nj.id LEFT JOIN dim_chronicles nc ON p.chronicle_id=nc.id LEFT JOIN dim_systems ds ON p.system_id=ds.id
              LEFT JOIN dim_totems dt ON p.totem_id=dt.id LEFT JOIN dim_breeds nr ON p.breed_id=nr.id LEFT JOIN dim_auspices na ON p.auspice_id=na.id LEFT JOIN dim_tribes nt ON p.tribe_id=nt.id
              LEFT JOIN dim_groups nm ON nm.id=pgb.group_id LEFT JOIN dim_organizations nc2 ON nc2.id=pcb.organization_id LEFT JOIN dim_character_types af ON p.character_type_id=af.id
              {$where} ORDER BY p.name ASC LIMIT ?,?";
        $types.='ii'; $params[]=$offset; $params[]=$limit;
        $stmt=$link->prepare($sql); if(!$stmt) return ['ok'=>false,'rows'=>[],'error'=>$link->error];
        $stmt->bind_param($types,...$params); if(!$stmt->execute()){ $m=$stmt->error;$stmt->close();return ['ok'=>false,'rows'=>[],'error'=>$m]; }
        $result=$stmt->get_result();$rows=[];while($result&&($row=$result->fetch_assoc()))$rows[]=$row;$stmt->close();return ['ok'=>true,'rows'=>$rows];
    }
}

if (!function_exists('hg_characters_admin_preload')) {
    function hg_characters_admin_preload(mysqli $link, array $ids): array
    {
        $ids=array_values(array_unique(array_filter(array_map('intval',$ids),static fn($v)=>$v>0)));
        $out=['details'=>[],'powers'=>[],'myd'=>[],'items'=>[],'traits'=>[],'resources'=>[]];
        if(empty($ids)) return $out;
        $in=implode(',',$ids);

        $result=$link->query("SELECT fc.id,COALESCE(dcs.label,'') AS status,fc.status_id,fc.rank,fc.info_text,fc.notes FROM fact_characters fc LEFT JOIN dim_character_status dcs ON dcs.id=fc.status_id WHERE fc.id IN ({$in})");
        if($result){while($r=$result->fetch_assoc()){$cid=(int)$r['id'];$out['details'][$cid]=['status'=>(string)$r['status'],'status_id'=>(int)$r['status_id'],'causamuerte'=>'','rango'=>(string)$r['rank'],'infotext'=>(string)$r['info_text'],'notes'=>(string)$r['notes']];}$result->close();}

        $result=$link->query("SELECT character_id,power_kind,power_id,power_level FROM bridge_characters_powers WHERE character_id IN ({$in}) ORDER BY power_kind,power_id");
        if($result){while($r=$result->fetch_assoc())$out['powers'][]=$r;$result->close();}

        $result=$link->query("SELECT b.character_id,nmd.id,nmd.name,nmd.kind,nmd.cost,b.level FROM bridge_characters_merits_flaws b JOIN dim_merits_flaws nmd ON nmd.id=b.merit_flaw_id WHERE b.character_id IN ({$in}) ORDER BY nmd.kind DESC,nmd.cost,nmd.name");
        if($result){while($r=$result->fetch_assoc())$out['myd'][]=$r;$result->close();}

        $result=$link->query("SELECT b.character_id,o.id,o.name,o.item_type_id FROM bridge_characters_items b JOIN fact_items o ON o.id=b.item_id WHERE b.character_id IN ({$in}) ORDER BY o.name");
        if($result){while($r=$result->fetch_assoc())$out['items'][]=$r;$result->close();}

        $result=$link->query("SELECT character_id,trait_id,value FROM bridge_characters_traits WHERE character_id IN ({$in}) ORDER BY character_id,trait_id");
        if($result){while($r=$result->fetch_assoc())$out['traits'][]=$r;$result->close();}

        if(pjs_table_exists($link,'bridge_characters_system_resources') && pjs_table_exists($link,'dim_systems_resources')){
            $result=$link->query("SELECT b.character_id,b.resource_id,b.value_permanent,b.value_temporary,r.name,r.kind,r.sort_order FROM bridge_characters_system_resources b INNER JOIN dim_systems_resources r ON r.id=b.resource_id WHERE b.character_id IN ({$in}) ORDER BY b.character_id,r.kind,r.sort_order,r.name");
            if($result){while($r=$result->fetch_assoc())$out['resources'][]=$r;$result->close();}
        }
        return $out;
    }
}

if (!function_exists('hg_characters_admin_fetch_ajax_details')) {
    function hg_characters_admin_fetch_ajax_details(mysqli $link, int $id): ?array
    {
        $stmt=$link->prepare("SELECT COALESCE(dcs.label,'') AS status,fc.status_id,fc.rank,fc.info_text FROM fact_characters fc LEFT JOIN dim_character_status dcs ON dcs.id=fc.status_id WHERE fc.id=? LIMIT 1");
        if(!$stmt) return null;
        $stmt->bind_param('i',$id);$stmt->execute();$result=$stmt->get_result();$row=$result?$result->fetch_assoc():null;$stmt->close();return $row?:null;
    }
}
