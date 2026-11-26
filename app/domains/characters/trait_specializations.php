<?php
require_once __DIR__ . '/../../helpers/schema_introspection.php';
require_once __DIR__ . '/detail_queries.php';

function hg_trait_specializations_available(mysqli $link): bool {
    return hg_table_has_column($link, 'bridge_characters_traits', 'specializations_json');
}
function hg_trait_specializations_kind(string $kind): bool {
    return in_array($kind, ['Atributos','Talentos','Técnicas','Tecnicas','Conocimientos','Habilidades'], true);
}
function hg_trait_specializations_normalize(string $text): array {
    if (!mb_check_encoding($text, 'UTF-8') || strlen($text)>10000) throw new InvalidArgumentException('Texto de especializaciones inválido.');
    $out=[];
    foreach (preg_split('/\R/u',$text) as $line) {
        $line=trim($line);
        if ($line==='') continue;
        if (mb_strlen($line,'UTF-8')>120 || preg_match('/\p{Cc}/u',$line)) throw new InvalidArgumentException('Especialización inválida: máximo 120 caracteres sin controles.');
        if (!in_array($line,$out,true)) $out[]=$line;
        if (count($out)>20) throw new InvalidArgumentException('Máximo 20 especializaciones por Rasgo.');
    }
    return $out;
}
function hg_trait_specializations_decode(?string $json): array {
    $items=json_decode($json ?? '',true);
    if (!is_array($items) || !array_is_list($items)) return [];
    foreach ($items as $item) if (!is_string($item) || preg_match('/\R/u',$item)) return [];
    try { return hg_trait_specializations_normalize(implode("\n",$items)); }
    catch (InvalidArgumentException $e) { return []; }
}
function hg_trait_specializations_fetch(mysqli $link,array $characterIds): array {
    $ids=array_values(array_unique(array_filter(array_map('intval',$characterIds),static fn($id)=>$id>0)));
    if (!$ids || !hg_trait_specializations_available($link)) return [];
    $in=implode(',',$ids);
    $result=$link->query("SELECT b.character_id,b.trait_id,b.specializations_json,t.kind FROM bridge_characters_traits b JOIN dim_traits t ON t.id=b.trait_id WHERE b.character_id IN ({$in}) AND b.value>0 AND b.specializations_json IS NOT NULL");
    if (!$result) return [];
    $out=[];
    while ($row=$result->fetch_assoc()) {
        if (!hg_trait_specializations_kind((string)$row['kind'])) continue;
        $items=hg_trait_specializations_decode($row['specializations_json']);
        if ($items) $out[(int)$row['character_id']][(int)$row['trait_id']]=$items;
    }
    $result->free();
    return $out;
}
function hg_trait_specializations_validate($raw,array $catalog,array $values): array {
    if (!is_string($raw) || strlen($raw)>65536) throw new InvalidArgumentException('Especializaciones inválidas.');
    $data=json_decode($raw);
    if (!$data instanceof stdClass) throw new InvalidArgumentException('Especializaciones inválidas.');
    $allowed=[];
    foreach ($catalog as $trait) if (hg_trait_specializations_kind((string)$trait['kind'])) $allowed[(int)$trait['id']]=true;
    $out=[];
    foreach ($data as $id=>$text) {
        $tid=(int)$id;
        if ((string)$tid!==(string)$id || !isset($allowed[$tid]) || ($values[$tid]??0)<=0 || !is_string($text)) throw new InvalidArgumentException('Las especializaciones requieren un Atributo o Habilidad con valor positivo.');
        $out[$tid]=hg_trait_specializations_normalize($text);
    }
    return $out;
}
function hg_trait_specializations_save(mysqli $link,int $characterId,array $edits): void {
    if (!$edits) return;
    if (!hg_trait_specializations_available($link)) throw new RuntimeException('Migración de especializaciones pendiente.');
    if (!$link->begin_transaction()) throw new RuntimeException('No se pudo iniciar la transacción.');
    try {
        $check=$link->prepare('SELECT value FROM bridge_characters_traits WHERE character_id=? AND trait_id=? FOR UPDATE');
        $update=$link->prepare('UPDATE bridge_characters_traits SET specializations_json=? WHERE character_id=? AND trait_id=? AND value>0');
        if (!$check || !$update) throw new RuntimeException('No se pudieron preparar las especializaciones.');
        foreach ($edits as $tid=>$items) {
            $check->bind_param('ii',$characterId,$tid);
            if (!$check->execute()) throw new RuntimeException('No se pudo verificar el Rasgo.');
            $result=$check->get_result();
            $row=$result->fetch_assoc();
            $result->free();
            if (!$row || (int)$row['value']<=0) throw new RuntimeException('El Rasgo ya no está disponible.');
            $json=$items?json_encode($items,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR):null;
            $update->bind_param('sii',$json,$characterId,$tid);
            if (!$update->execute()) throw new RuntimeException('No se pudieron guardar las especializaciones.');
        }
        $check->close();
        $update->close();
        if (!$link->commit()) throw new RuntimeException('No se pudieron confirmar las especializaciones.');
    } catch (Throwable $e) { $link->rollback(); throw $e; }
}
