<?php

include_once(__DIR__ . '/../../helpers/pretty.php');

if (!function_exists('hg_groups_fetch_all')) {
    function hg_groups_fetch_all(mysqli $link, string $sql, string $types = '', array $params = []): array
    {
        $stmt = $link->prepare($sql);
        if (!$stmt) return ['ok'=>false,'rows'=>[],'error'=>$link->error];
        if ($types !== '') $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            return ['ok'=>false,'rows'=>[],'error'=>$error];
        }
        $result = $stmt->get_result();
        $rows = [];
        while ($result && ($row = $result->fetch_assoc())) $rows[] = $row;
        $stmt->close();
        return ['ok'=>true,'rows'=>$rows,'error'=>''];
    }
}

if (!function_exists('hg_groups_totems')) {
    function hg_groups_totems(mysqli $link): array
    {
        $result = hg_groups_fetch_all($link, "SELECT id, name FROM dim_totems ORDER BY name ASC");
        $out = [];
        foreach ($result['rows'] as $row) $out[(int)$row['id']] = (string)$row['name'];
        return $out;
    }
}

if (!function_exists('hg_groups_clans_table')) {
    function hg_groups_clans_table(mysqli $link): array
    {
        return hg_groups_fetch_all($link, "SELECT c.id, c.name,
          (SELECT COUNT(*)
             FROM bridge_organizations_groups b
             INNER JOIN dim_groups m ON m.id = b.group_id
            WHERE b.organization_id = c.id
              AND b.is_active = 1
              AND COALESCE(m.is_active, 1) = 1) AS groups_active
          FROM dim_organizations c
          ORDER BY c.name ASC");
    }
}

if (!function_exists('hg_groups_groups_table')) {
    function hg_groups_groups_table(mysqli $link): array
    {
        return hg_groups_fetch_all($link, "SELECT m.id,m.name,m.is_active AS activa,
          (SELECT o.name
             FROM bridge_organizations_groups bog
             INNER JOIN dim_organizations o ON o.id = bog.organization_id
            WHERE bog.group_id = m.id AND bog.is_active = 1
            ORDER BY o.name ASC
            LIMIT 1) AS organization_name
          FROM dim_groups m
          ORDER BY m.name ASC");
    }
}

if (!function_exists('hg_groups_clan_detail')) {
    function hg_groups_clan_detail(mysqli $link, int $organizationId): array
    {
        $linkedResult = hg_groups_fetch_all(
            $link,
            "SELECT m.id,m.name,m.is_active AS group_is_active,b.is_active
             FROM bridge_organizations_groups b
             INNER JOIN dim_groups m ON m.id=b.group_id
             WHERE b.organization_id=?
             ORDER BY m.name ASC",
            'i',
            [$organizationId]
        );
        if (empty($linkedResult['ok'])) return ['ok'=>false,'linked'=>[],'available'=>[],'error'=>$linkedResult['error']];
        $linked = $linkedResult['rows'];
        $ids = array_values(array_filter(array_map(static fn($r)=>(int)($r['id']??0), $linked), static fn($v)=>$v>0));
        if ($ids) {
            $in = implode(',', array_map('intval',$ids));
            $available = $link->query("SELECT id,name FROM dim_groups WHERE COALESCE(is_active,1)=1 AND id NOT IN ($in) ORDER BY name ASC");
        } else {
            $available = $link->query("SELECT id,name FROM dim_groups WHERE COALESCE(is_active,1)=1 ORDER BY name ASC");
        }
        if (!$available) return ['ok'=>false,'linked'=>$linked,'available'=>[],'error'=>$link->error];
        $rows=[]; while($row=$available->fetch_assoc())$rows[]=$row; $available->close();
        return ['ok'=>true,'linked'=>$linked,'available'=>$rows,'error'=>''];
    }
}

if (!function_exists('hg_groups_group_members')) {
    function hg_groups_group_members(mysqli $link, int $groupId): array
    {
        return hg_groups_fetch_all(
            $link,
            "SELECT p.id,p.name AS nombre,p.alias,p.garou_name AS nombregarou,b.is_active,b.position
             FROM bridge_characters_groups b
             INNER JOIN fact_characters p ON p.id=b.character_id
             WHERE b.group_id=?
             ORDER BY p.name ASC",
            'i',
            [$groupId]
        );
    }
}

if (!function_exists('hg_groups_fetch_clan')) {
    function hg_groups_fetch_clan(mysqli $link, int $organizationId): ?array
    {
        $result = hg_groups_fetch_all($link,"SELECT id,name,totem_id AS totem,color,is_npc,`description` FROM dim_organizations WHERE id=? LIMIT 1",'i',[$organizationId]);
        return !empty($result['rows']) ? $result['rows'][0] : null;
    }
}

if (!function_exists('hg_groups_fetch_group')) {
    function hg_groups_fetch_group(mysqli $link, int $groupId): ?array
    {
        $result = hg_groups_fetch_all($link,"SELECT id,name,is_active AS activa,IFNULL(chronicle_id,1) AS cronica,totem_id AS totem,`description` FROM dim_groups WHERE id=? LIMIT 1",'i',[$groupId]);
        return !empty($result['rows']) ? $result['rows'][0] : null;
    }
}

if (!function_exists('hg_groups_group_org_names')) {
    function hg_groups_group_org_names(mysqli $link, int $groupId): array
    {
        $result = hg_groups_fetch_all(
            $link,
            "SELECT o.name,bog.is_active
             FROM bridge_organizations_groups bog
             INNER JOIN dim_organizations o ON o.id=bog.organization_id
             WHERE bog.group_id=?
             ORDER BY bog.is_active DESC,o.name ASC",
            'i',
            [$groupId]
        );
        $rows=[];
        foreach($result['rows'] as $row){
            $rows[] = ((int)($row['is_active']??0)===1?'[Activa] ':'[Inactiva] ') . (string)($row['name']??'');
        }
        return $rows;
    }
}

if (!function_exists('hg_groups_next_org_sort')) {
    function hg_groups_next_org_sort(mysqli $link): int
    {
        $rs=$link->query("SELECT COALESCE(MAX(sort_order),0)+1 AS next_sort FROM dim_organizations");
        if(!$rs)return 0;
        $row=$rs->fetch_assoc();$rs->close();
        return (int)($row['next_sort']??0);
    }
}

if (!function_exists('hg_groups_organizations')) {
    function hg_groups_organizations(mysqli $link): array
    {
        $result=hg_groups_fetch_all($link,"SELECT id,name FROM dim_organizations ORDER BY name ASC");
        return $result['rows'];
    }
}

if (!function_exists('hg_groups_update_clan')) {
    function hg_groups_update_clan(mysqli $link,int $id,string $name,?int $totem,string $color,int $isNpc,string $description): bool
    {
        $stmt=$link->prepare("UPDATE dim_organizations SET name=?,totem_id=?,color=?,is_npc=?,`description`=? WHERE id=?");
        if(!$stmt)return false;
        $stmt->bind_param('sisisi',$name,$totem,$color,$isNpc,$description,$id);
        $ok=$stmt->execute();$stmt->close();
        if($ok)hg_update_pretty_id_if_exists($link,'dim_organizations',$id,$name);
        return (bool)$ok;
    }
}

if (!function_exists('hg_groups_create_clan')) {
    function hg_groups_create_clan(mysqli $link,string $name,int $sortOrder,?int $totem,string $color,int $isNpc,string $description): array
    {
        $stmt=$link->prepare("INSERT INTO dim_organizations (name,sort_order,totem_id,color,is_npc,`description`) VALUES (?,?,?,?,?,?)");
        if(!$stmt)return ['ok'=>false,'id'=>0,'error'=>$link->error];
        $stmt->bind_param('sisiss',$name,$sortOrder,$totem,$color,$isNpc,$description);
        $ok=$stmt->execute();$err=$stmt->error;$id=(int)$stmt->insert_id;$stmt->close();
        if($ok)hg_update_pretty_id_if_exists($link,'dim_organizations',$id,$name);
        return ['ok'=>$ok,'id'=>$id,'error'=>$err];
    }
}

if (!function_exists('hg_groups_update_group')) {
    function hg_groups_update_group(mysqli $link,int $id,string $name,int $active,int $chronicleId,?int $totem,string $description): bool
    {
        $stmt=$link->prepare("UPDATE dim_groups SET name=?,is_active=?,chronicle_id=?,totem_id=?,`description`=? WHERE id=?");
        if(!$stmt)return false;
        $stmt->bind_param('siiisi',$name,$active,$chronicleId,$totem,$description,$id);
        $ok=$stmt->execute();$stmt->close();
        if($ok)hg_update_pretty_id_if_exists($link,'dim_groups',$id,$name);
        return (bool)$ok;
    }
}

if (!function_exists('hg_groups_set_org_group')) {
    function hg_groups_set_org_group(mysqli $link,int $organizationId,int $groupId,bool $active=true): bool
    {
        if($organizationId<=0||$groupId<=0)return false;
        if($active){
            $link->begin_transaction();
            try {
                $st=$link->prepare("UPDATE bridge_organizations_groups SET is_active=0 WHERE group_id=? AND organization_id<>?");
                if(!$st)throw new RuntimeException($link->error);
                $st->bind_param('ii',$groupId,$organizationId);$st->execute();$st->close();
                $st=$link->prepare("INSERT INTO bridge_organizations_groups (organization_id,group_id,is_active) VALUES (?,?,1) ON DUPLICATE KEY UPDATE is_active=1");
                if(!$st)throw new RuntimeException($link->error);
                $st->bind_param('ii',$organizationId,$groupId);$st->execute();$st->close();
                $link->commit();return true;
            } catch(Throwable $e){$link->rollback();return false;}
        }
        $st=$link->prepare("UPDATE bridge_organizations_groups SET is_active=0 WHERE organization_id=? AND group_id=?");
        if(!$st)return false;
        $st->bind_param('ii',$organizationId,$groupId);$ok=$st->execute();$st->close();return (bool)$ok;
    }
}

if (!function_exists('hg_groups_create_group')) {
    function hg_groups_create_group(mysqli $link,string $name,int $chronicleId,?int $totem,int $active,string $description,int $organizationId): array
    {
        $stmt=$link->prepare("INSERT INTO dim_groups (name,chronicle_id,totem_id,is_active,`description`) VALUES (?,?,?,?,?)");
        if(!$stmt)return ['ok'=>false,'id'=>0,'error'=>$link->error];
        $stmt->bind_param('siiis',$name,$chronicleId,$totem,$active,$description);
        $ok=$stmt->execute();$err=$stmt->error;$id=(int)$stmt->insert_id;$stmt->close();
        if(!$ok)return ['ok'=>false,'id'=>0,'error'=>$err];
        hg_update_pretty_id_if_exists($link,'dim_groups',$id,$name);
        if($organizationId>0 && !hg_groups_set_org_group($link,$organizationId,$id,true)){
            return ['ok'=>false,'id'=>$id,'error'=>'No se pudo vincular la manada con la organizacion.'];
        }
        return ['ok'=>true,'id'=>$id,'error'=>''];
    }
}

if (!function_exists('hg_groups_add_member')) {
    function hg_groups_add_member(mysqli $link,int $groupId,int $characterId,string $position): bool
    {
        $stmt=$link->prepare("INSERT INTO bridge_characters_groups (character_id,group_id,is_active,position) VALUES (?,?,1,?) ON DUPLICATE KEY UPDATE is_active=1,position=VALUES(position)");
        if(!$stmt)return false;
        $stmt->bind_param('iis',$characterId,$groupId,$position);$ok=$stmt->execute();$stmt->close();return (bool)$ok;
    }
}

if (!function_exists('hg_groups_remove_member')) {
    function hg_groups_remove_member(mysqli $link,int $groupId,int $characterId): bool
    {
        $stmt=$link->prepare("UPDATE bridge_characters_groups SET is_active=0 WHERE group_id=? AND character_id=?");
        if(!$stmt)return false;
        $stmt->bind_param('ii',$groupId,$characterId);$ok=$stmt->execute();$stmt->close();return (bool)$ok;
    }
}

if (!function_exists('hg_groups_save_member_position')) {
    function hg_groups_save_member_position(mysqli $link,int $groupId,int $characterId,string $position): bool
    {
        $stmt=$link->prepare("UPDATE bridge_characters_groups SET position=? WHERE group_id=? AND character_id=?");
        if(!$stmt)return false;
        $stmt->bind_param('sii',$position,$groupId,$characterId);$ok=$stmt->execute();$stmt->close();return (bool)$ok;
    }
}

if (!function_exists('hg_groups_search_characters')) {
    function hg_groups_search_characters(mysqli $link,string $query): array
    {
        $like='%'.$query.'%';
        $result=hg_groups_fetch_all($link,"SELECT id,name AS nombre,alias,garou_name AS nombregarou FROM fact_characters WHERE name LIKE ? OR alias LIKE ? OR garou_name LIKE ? ORDER BY name ASC LIMIT 30",'sss',[$like,$like,$like]);
        return $result;
    }
}
