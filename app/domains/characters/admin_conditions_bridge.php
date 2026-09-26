<?php

if (!function_exists('hg_accb_character_options')) {
    function hg_accb_character_options(mysqli $link): array {
        $chronicleSelect = "COALESCE(NULLIF(TRIM(ch.name), ''), 'Sin cronica')";
        $realitySelect = "COALESCE(NULLIF(TRIM(r.name), ''), 'Sin realidad')";
        $sql = "SELECT c.id,
                       COALESCE(NULLIF(TRIM(c.name), ''), CONCAT('Personaje #', c.id)) AS character_name,
                       {$chronicleSelect} AS chronicle_name,
                       {$realitySelect} AS reality_name
                FROM fact_characters c";
        $sql .= " LEFT JOIN dim_chronicles ch ON ch.id = c.chronicle_id";
        $sql .= " LEFT JOIN dim_realities r ON r.id = c.reality_id";
        $sql .= " ORDER BY c.name ASC, c.id ASC";
        $out = [];
        if ($rs = $link->query($sql)) {
            while ($r = $rs->fetch_assoc()) {
                $id = (int)($r['id'] ?? 0);
                if ($id <= 0) continue;
                $name = trim((string)($r['character_name'] ?? '')) ?: ('Personaje #' . $id);
                $chronicleName = trim((string)($r['chronicle_name'] ?? 'Sin cronica')) ?: 'Sin cronica';
                $realityName = trim((string)($r['reality_name'] ?? 'Sin realidad')) ?: 'Sin realidad';
                $out[$id] = $name . ' (#' . $id . ') [Cr: ' . $chronicleName . '] [Real: ' . $realityName . ']';
            }
            $rs->close();
        }
        return $out;
    }
}

if (!function_exists('hg_accb_condition_catalog')) {
    function hg_accb_condition_catalog(mysqli $link): array {
        $options = [];
        $meta = [];
        if ($rs = $link->query("SELECT id, name, category, max_instances FROM dim_character_conditions ORDER BY category ASC, name ASC")) {
            while ($r = $rs->fetch_assoc()) {
                $id = (int)($r['id'] ?? 0);
                if ($id <= 0) continue;
                $options[$id] = (string)($r['name'] ?? '');
                $meta[$id] = [
                    'id'=>$id,
                    'name'=>(string)($r['name'] ?? ''),
                    'category'=>(string)($r['category'] ?? ''),
                    'max_instances'=>$r['max_instances'] === null ? null : (int)$r['max_instances'],
                ];
            }
            $rs->close();
        }
        return ['options'=>$options,'meta'=>$meta];
    }
}

if (!function_exists('hg_accb_delete')) {
    function hg_accb_delete(mysqli $link, int $id): array {
        $st = $link->prepare("DELETE FROM bridge_characters_conditions WHERE id = ?");
        if (!$st) return ['ok'=>false,'message'=>'No se pudo preparar el borrado.'];
        $st->bind_param('i',$id);
        $ok = $st->execute();
        $err = $st->error;
        $st->close();
        return $ok
            ? ['ok'=>true,'message'=>'Condicion desvinculada.']
            : ['ok'=>false,'message'=>'No se pudo borrar: '.$err];
    }
}

if (!function_exists('hg_accb_save')) {
    function hg_accb_save(mysqli $link, string $action, int $id, array $v): array {
        if ($action === 'create') {
            $sql = "INSERT INTO bridge_characters_conditions
                    (character_id, condition_id, instance_no, location, notes, source, acquired_at, healed_at, is_active, created_at, updated_at)
                    VALUES (?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), ?, ?, ?, NOW(), NOW())";
            $st = $link->prepare($sql);
            if (!$st) return ['ok'=>false,'message'=>'No se pudo preparar la asignacion.'];
            $st->bind_param('iiisssssi',
                $v['character_id'],$v['condition_id'],$v['instance_no'],$v['location'],$v['notes'],$v['source'],$v['acquired_at'],$v['healed_at'],$v['is_active']
            );
            $ok=$st->execute(); $err=$st->error; $st->close();
            return $ok ? ['ok'=>true,'message'=>'Condicion asignada.'] : ['ok'=>false,'message'=>'No se pudo asignar. Revisa si esa instancia ya existe. '.$err];
        }
        if ($action === 'update') {
            if ($id <= 0) return ['ok'=>false,'message'=>'ID invalido para actualizar.'];
            $sql = "UPDATE bridge_characters_conditions
                    SET character_id = ?, condition_id = ?, instance_no = ?, location = NULLIF(?, ''), notes = NULLIF(?, ''),
                        source = NULLIF(?, ''), acquired_at = ?, healed_at = ?, is_active = ?, updated_at = NOW()
                    WHERE id = ?";
            $st = $link->prepare($sql);
            if (!$st) return ['ok'=>false,'message'=>'No se pudo preparar la actualizacion.'];
            $st->bind_param('iiisssssii',
                $v['character_id'],$v['condition_id'],$v['instance_no'],$v['location'],$v['notes'],$v['source'],$v['acquired_at'],$v['healed_at'],$v['is_active'],$id
            );
            $ok=$st->execute(); $err=$st->error; $st->close();
            return $ok ? ['ok'=>true,'message'=>'Asignacion actualizada.'] : ['ok'=>false,'message'=>'No se pudo actualizar. Revisa si esa instancia ya existe. '.$err];
        }
        return ['ok'=>false,'message'=>'Accion no valida.'];
    }
}

if (!function_exists('hg_accb_fetch_assignments')) {
    function hg_accb_fetch_assignments(mysqli $link, int $characterId = 0): array {
        $where = "WHERE 1=1";
        if ($characterId > 0) $where .= " AND bcc.character_id = " . (int)$characterId;
        $sql = "SELECT bcc.id,bcc.character_id,bcc.condition_id,bcc.instance_no,
                       COALESCE(bcc.location, '') AS location,
                       COALESCE(bcc.notes, '') AS notes,
                       COALESCE(bcc.source, '') AS source,
                       bcc.acquired_at,bcc.healed_at,bcc.is_active,
                       c.name AS condition_name,c.category,c.max_instances,
                       fc.name AS character_name
                FROM bridge_characters_conditions bcc
                JOIN dim_character_conditions c ON c.id = bcc.condition_id
                JOIN fact_characters fc ON fc.id = bcc.character_id
                {$where}
                ORDER BY fc.name ASC, bcc.is_active DESC, c.category ASC, c.name ASC, bcc.instance_no ASC";
        $rows=[];
        if ($rs=$link->query($sql)) {
            while($r=$rs->fetch_assoc()) $rows[]=$r;
            $rs->close();
        }
        return $rows;
    }
}
