<?php

function hg_gift_image_mass_fetch_prompt_gift(mysqli $link,int $giftId): ?array {
    if($giftId<=0)return null;
    $st=$link->prepare(
        'SELECT g.name, g.rank, g.kind, COALESCE(t.name, g.kind) AS kind_name, g.gift_group, g.system_name, g.description, g.mechanics_text
         FROM fact_gifts g
         LEFT JOIN dim_gift_types t ON t.id = CAST(g.kind AS UNSIGNED)
         WHERE g.id = ?
         LIMIT 1'
    );
    if(!$st)return null;
    $st->bind_param('i',$giftId);$st->execute();$rs=$st->get_result();$row=$rs?$rs->fetch_assoc():null;$st->close();
    return $row?:null;
}
function hg_gift_image_mass_fetch_image_row(mysqli $link,int $giftId): ?array {
    if($giftId<=0)return null;
    $st=$link->prepare('SELECT name, image_url FROM fact_gifts WHERE id = ? LIMIT 1');
    if(!$st)return null;
    $st->bind_param('i',$giftId);$st->execute();$rs=$st->get_result();$row=$rs?$rs->fetch_assoc():null;$st->close();
    return $row?:null;
}
function hg_gift_image_mass_update_image(mysqli $link,int $giftId,string $url): bool {
    if($giftId<=0)return false;
    $st=$link->prepare('UPDATE fact_gifts SET image_url = ? WHERE id = ?');
    if(!$st)return false;
    $st->bind_param('si',$url,$giftId);$ok=$st->execute();$st->close();return (bool)$ok;
}
function hg_gift_image_mass_fetch_gifts(mysqli $link): array {
    $sql="SELECT g.id, g.pretty_id, g.name, g.image_url, g.kind,
           COALESCE(t.name, g.kind) AS kind_name, g.gift_group, g.rank, g.system_name,
           COALESCE(owners.character_count, 0) AS character_count
    FROM fact_gifts g
    LEFT JOIN dim_gift_types t ON t.id = CAST(g.kind AS UNSIGNED)
    LEFT JOIN (
        SELECT power_id, COUNT(DISTINCT character_id) AS character_count
        FROM bridge_characters_powers
        WHERE power_kind = 'dones'
        GROUP BY power_id
    ) owners ON owners.power_id = g.id
    ORDER BY g.name ASC, g.id ASC";
    $rows=[];$rs=$link->query($sql);if($rs){while($r=$rs->fetch_assoc())$rows[]=$r;$rs->close();}return $rows;
}
