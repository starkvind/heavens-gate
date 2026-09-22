<?php

include_once(__DIR__ . '/../../helpers/character_avatar.php');

if (!function_exists('hg_avatar_admin_get_character')) {
    function hg_avatar_admin_get_character(mysqli $link, int $id): ?array {
        if ($id <= 0) return null;
        $st=$link->prepare("SELECT name, image_url FROM fact_characters WHERE id=? LIMIT 1");
        if(!$st)return null;
        $st->bind_param('i',$id);$st->execute();$rs=$st->get_result();$row=$rs?$rs->fetch_assoc():null;$st->close();
        return $row ?: null;
    }
}

if (!function_exists('hg_avatar_admin_update_base')) {
    function hg_avatar_admin_update_base(mysqli $link, int $id, string $url): bool {
        $st=$link->prepare("UPDATE fact_characters SET image_url=? WHERE id=?");
        if(!$st)return false;
        $st->bind_param('si',$url,$id);$ok=$st->execute();$st->close();return (bool)$ok;
    }
}

if (!function_exists('hg_avatar_admin_upsert_variant')) {
    function hg_avatar_admin_upsert_variant(mysqli $link, int $id, string $variantCode, string $url): bool {
        $st=$link->prepare("
            INSERT INTO fact_character_avatar_variants (character_id, variant_code, image_url, is_active)
            VALUES (?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE image_url = VALUES(image_url), is_active = 1
        ");
        if(!$st)return false;
        $st->bind_param('iss',$id,$variantCode,$url);$ok=$st->execute();$st->close();return (bool)$ok;
    }
}

if (!function_exists('hg_avatar_admin_load_state')) {
    function hg_avatar_admin_load_state(mysqli $link): array {
        $characters=[];
        $sql="
        SELECT
            p.id,p.pretty_id,p.name,p.image_url,p.chronicle_id,
            COALESCE(ch.name, '') AS chronicle_name,
            COALESCE(bg.group_id, 0) AS group_id,
            COALESCE(g.name, '') AS group_name,
            COALESCE(bo.organization_id, 0) AS org_id,
            COALESCE(o.name, '') AS org_name
        FROM fact_characters p
        LEFT JOIN (
            SELECT character_id, MIN(group_id) AS group_id
            FROM bridge_characters_groups
            WHERE (is_active=1 OR is_active IS NULL)
            GROUP BY character_id
        ) bg ON bg.character_id = p.id
        LEFT JOIN dim_groups g ON g.id = bg.group_id
        LEFT JOIN (
            SELECT character_id, MIN(organization_id) AS organization_id
            FROM bridge_characters_organizations
            WHERE (is_active=1 OR is_active IS NULL)
            GROUP BY character_id
        ) bo ON bo.character_id = p.id
        LEFT JOIN dim_organizations o ON o.id = bo.organization_id
        LEFT JOIN dim_chronicles ch ON ch.id = p.chronicle_id
        ORDER BY p.name ASC";
        $rs=$link->query($sql);
        if($rs){while($r=$rs->fetch_assoc())$characters[]=$r;$rs->close();}

        $variants=[];
        if(hg_character_avatar_variants_table_exists($link,true)){
            $rs=$link->query("SELECT character_id, variant_code, image_url FROM fact_character_avatar_variants WHERE is_active = 1 ORDER BY variant_code ASC, id ASC");
            if($rs){while($r=$rs->fetch_assoc()){ $cid=(int)($r['character_id']??0); if($cid<=0)continue; $variants[$cid][]=['variant_code'=>(string)($r['variant_code']??''),'image_url'=>(string)($r['image_url']??'')]; } $rs->close();}
        }
        return ['characters'=>$characters,'variants'=>$variants];
    }
}
