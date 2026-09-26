<?php
require_once(__DIR__ . '/../../helpers/schema_introspection.php');

if (!function_exists('hg_cca_table')) {
    function hg_cca_table(mysqli $db, string $t): bool {
        return hg_table_exists($db, $t);
    }
}
if (!function_exists('hg_cca_col')) {
    function hg_cca_col(mysqli $db, string $t, string $c): bool {
        return hg_table_has_column($db, $t, $c);
    }
}
if (!function_exists('hg_cca_load_state')) {
    function hg_cca_load_state(mysqli $link): array {
        if (!hg_cca_table($link,'fact_characters')) return ['ok'=>false,'error'=>'No existe fact_characters.'];
        $hasPretty=hg_cca_col($link,'fact_characters','pretty_id');
        $hasAlias=hg_cca_col($link,'fact_characters','alias');
        $hasGarou=hg_cca_col($link,'fact_characters','garou_name');
        $hasChron=hg_cca_col($link,'fact_characters','chronicle_id')&&hg_cca_table($link,'dim_chronicles');
        $hasReality=hg_cca_col($link,'fact_characters','reality_id')&&hg_cca_table($link,'dim_realities');
        $hasOrgs=hg_cca_table($link,'bridge_characters_organizations')&&hg_cca_table($link,'dim_organizations');
        $hasGroups=hg_cca_table($link,'bridge_characters_groups')&&hg_cca_table($link,'dim_groups');

        $select=['c.id','c.name',
            $hasPretty?"COALESCE(c.pretty_id,'') AS pretty_id":"'' AS pretty_id",
            $hasAlias?"COALESCE(c.alias,'') AS alias":"'' AS alias",
            $hasGarou?"COALESCE(c.garou_name,'') AS garou_name":"'' AS garou_name",
            $hasChron?"COALESCE(ch.name,'') AS chronicle_name":"'' AS chronicle_name",
            $hasReality?"COALESCE(r.name,'') AS reality_name":"'' AS reality_name"];
        if($hasOrgs){
            $active=hg_cca_col($link,'bridge_characters_organizations','is_active')?' AND (b.is_active=1 OR b.is_active IS NULL)':'';
            $select[]="(SELECT GROUP_CONCAT(DISTINCT o.name ORDER BY o.name SEPARATOR ', ') FROM bridge_characters_organizations b JOIN dim_organizations o ON o.id=b.organization_id WHERE b.character_id=c.id".$active.") AS organizations";
        } else $select[]="'' AS organizations";
        if($hasGroups){
            $active=hg_cca_col($link,'bridge_characters_groups','is_active')?' AND (b.is_active=1 OR b.is_active IS NULL)':'';
            $select[]="(SELECT GROUP_CONCAT(DISTINCT g.name ORDER BY g.name SEPARATOR ', ') FROM bridge_characters_groups b JOIN dim_groups g ON g.id=b.group_id WHERE b.character_id=c.id".$active.") AS groups";
        } else $select[]="'' AS groups";
        $joins=[]; if($hasChron)$joins[]='LEFT JOIN dim_chronicles ch ON ch.id=c.chronicle_id'; if($hasReality)$joins[]='LEFT JOIN dim_realities r ON r.id=c.reality_id';
        $sql='SELECT '.implode(',',$select).' FROM fact_characters c '.implode(' ',$joins).' ORDER BY c.name,c.id';
        $rs=$link->query($sql); if(!$rs)return ['ok'=>false,'error'=>$link->error];
        $chars=[];while($row=$rs->fetch_assoc()){$row['id']=(int)$row['id'];$chars[$row['id']]=$row;}$rs->free();
        return compact('hasPretty','hasAlias','hasGarou','hasChron','hasReality','hasOrgs','hasGroups','chars')+['ok'=>true];
    }
}
