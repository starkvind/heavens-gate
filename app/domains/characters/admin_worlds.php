<?php

if (!function_exists('hg_acw_has_table')) {
    function hg_acw_has_table(mysqli $db, string $table): bool {
        $table = str_replace(chr(96), '', $table);
        $rs = $db->query("SHOW TABLES LIKE '".$db->real_escape_string($table)."'");
        if (!$rs) return false;
        $ok = ($rs->num_rows > 0);
        $rs->close();
        return $ok;
    }
}

if (!function_exists('hg_acw_has_column')) {
    function hg_acw_has_column(mysqli $db, string $table, string $column): bool {
        $table = str_replace(chr(96), '', $table);
        $column = str_replace(chr(96), '', $column);
        $safeTable = $db->real_escape_string($table);
        $safeColumn = $db->real_escape_string($column);
        $rs = $db->query("SHOW COLUMNS FROM ".$safeTable." LIKE '".$safeColumn."'");
        if (!$rs) return false;
        $ok = ($rs->num_rows > 0);
        $rs->close();
        return $ok;
    }
}

if (!function_exists('hg_acw_save_character_world')) {
    function hg_acw_save_character_world(mysqli $link, int $characterId, int $chronicleId, int $realityId): array {
        if (!hg_acw_has_table($link, 'dim_realities') || !hg_acw_has_column($link, 'fact_characters', 'reality_id')) {
            return ['ok'=>false,'msg'=>'Falta esquema: dim_realities / fact_characters.reality_id'];
        }
        if ($characterId <= 0 || $chronicleId <= 0 || $realityId <= 0) {
            return ['ok'=>false,'msg'=>'IDs inválidos'];
        }
        foreach ([['fact_characters',$characterId,'Personaje no encontrado'],['dim_chronicles',$chronicleId,'Crónica no encontrada'],['dim_realities',$realityId,'Realidad no encontrada']] as [$table,$id,$msg]) {
            $st=$link->prepare("SELECT COUNT(*) FROM ".$table." WHERE id = ?");
            if (!$st) return ['ok'=>false,'msg'=>'Error al preparar consulta'];
            $st->bind_param('i',$id); $st->execute(); $st->bind_result($exists); $st->fetch(); $st->close();
            if ((int)$exists <= 0) return ['ok'=>false,'msg'=>$msg];
        }
        $st=$link->prepare("UPDATE fact_characters SET chronicle_id = ?, reality_id = ? WHERE id = ? LIMIT 1");
        if (!$st) return ['ok'=>false,'msg'=>'Error al preparar consulta'];
        $st->bind_param('iii',$chronicleId,$realityId,$characterId);
        $ok=$st->execute(); $st->close();
        return $ok ? ['ok'=>true,'msg'=>'Guardado'] : ['ok'=>false,'msg'=>'Error al actualizar en BDD'];
    }
}

if (!function_exists('hg_acw_load_state')) {
    function hg_acw_load_state(mysqli $link): array {
        $hasRealitySchema = hg_acw_has_table($link, 'dim_realities') && hg_acw_has_column($link, 'fact_characters', 'reality_id');
        $fetch=function(string $sql) use($link): array {
            $rows=[]; $rs=$link->query($sql); if($rs){while($r=$rs->fetch_assoc())$rows[]=$r;$rs->close();} return $rows;
        };
        $chronicles=$fetch("SELECT id, name FROM dim_chronicles ORDER BY name ASC");
        $realities=$hasRealitySchema ? $fetch("SELECT id, name FROM dim_realities ORDER BY name ASC") : [];
        $organizations=$fetch("SELECT id, name FROM dim_organizations ORDER BY name ASC");
        $characters=[];
        if ($hasRealitySchema) {
            $characters=$fetch("
                SELECT p.id,p.pretty_id,p.name,p.chronicle_id,p.reality_id,
                       COALESCE(bo.organization_id,0) AS organization_id,
                       COALESCE(ch.name,'') AS chronicle_name,
                       COALESCE(r.name,'') AS reality_name,
                       COALESCE(o.name,'') AS organization_name
                FROM fact_characters p
                LEFT JOIN (
                    SELECT character_id, MIN(organization_id) AS organization_id
                    FROM bridge_characters_organizations
                    WHERE (is_active = 1 OR is_active IS NULL)
                    GROUP BY character_id
                ) bo ON bo.character_id = p.id
                LEFT JOIN dim_organizations o ON o.id = bo.organization_id
                LEFT JOIN dim_chronicles ch ON ch.id = p.chronicle_id
                LEFT JOIN dim_realities r ON r.id = p.reality_id
                ORDER BY p.name ASC, p.id ASC
            ");
        }
        return compact('hasRealitySchema','chronicles','realities','organizations','characters');
    }
}
