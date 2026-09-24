<?php

function hg_admin_col_exists(mysqli $link, string $table, string $column): bool {
    static $schema = [
        'dim_timeline_events_types' => ['sort_order'],
        'dim_chronicles' => ['sort_order'],
        'dim_realities' => [],
        'bridge_timeline_events_chronicles' => ['sort_order'],
        'bridge_timeline_events_chapters' => ['sort_order'],
        'bridge_timeline_events_realities' => ['sort_order'],
        'bridge_timeline_events_characters' => ['sort_order', 'role_label'],
    ];
    return isset($schema[$table]) && in_array($column, $schema[$table], true);
}

function hg_admin_has_table(mysqli $link, string $table): bool {
    $table = str_replace('`', '', $table);
    $rs = $link->query("SHOW TABLES LIKE '" . $link->real_escape_string($table) . "'");
    if (!$rs) return false;
    $ok = ($rs->num_rows > 0);
    $rs->close();
    return $ok;
}

function hg_admin_pick_deaths_table(mysqli $link): string {
    if (hg_admin_has_table($link, 'fact_characters_deaths')) return 'fact_characters_deaths';
    if (hg_admin_has_table($link, 'fact_characters_death')) return 'fact_characters_death';
    return '';
}

function hg_admin_sync_death_ground_truth(mysqli $link, string $deathsTable, int $eventId, ?string $deathDate): void {
    if ($deathsTable === '' || $eventId <= 0) return;

    $linkedDeaths = 0;
    if ($stCount = $link->prepare("SELECT COUNT(*) FROM `{$deathsTable}` WHERE death_timeline_event_id = ?")) {
        $stCount->bind_param('i', $eventId);
        $stCount->execute();
        $stCount->bind_result($linkedDeaths);
        $stCount->fetch();
        $stCount->close();
    }
    if ((int)$linkedDeaths <= 0) return;

    if ($stDeaths = $link->prepare("UPDATE `{$deathsTable}` SET death_date = ? WHERE death_timeline_event_id = ?")) {
        $stDeaths->bind_param('si', $deathDate, $eventId);
        $stDeaths->execute();
        $stDeaths->close();
    }

    $eventDate = ($deathDate !== null && $deathDate !== '') ? $deathDate : '1000-01-01';
    $precision = ($deathDate !== null && $deathDate !== '') ? 'day' : 'unknown';
    $dateNote = ($deathDate !== null && $deathDate !== '')
        ? null
        : 'Fecha de muerte no especificada (sincronizado desde muertes).';

    if ($stEv = $link->prepare("
        UPDATE fact_timeline_events
        SET
            event_type_id = 5,
            event_date = ?,
            sort_date = ?,
            date_precision = ?,
            date_note = ?,
            updated_at = NOW()
        WHERE id = ?
        LIMIT 1
    ")) {
        $stEv->bind_param('ssssi', $eventDate, $eventDate, $precision, $dateNote, $eventId);
        $stEv->execute();
        $stEv->close();
    }
}

function hg_admin_sync_bridge_ids(mysqli $link, string $table, string $refCol, int $eventId, array $ids): void {
    if ($eventId <= 0) return;
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $refCol = preg_replace('/[^a-zA-Z0-9_]/', '', $refCol);
    if ($table === '' || $refCol === '') return;
    $hasSortOrder = hg_admin_col_exists($link, $table, 'sort_order');

    if ($stDel = $link->prepare("DELETE FROM {$table} WHERE event_id = ?")) {
        $stDel->bind_param('i', $eventId);
        $stDel->execute();
        $stDel->close();
    }

    if (empty($ids)) return;

    if ($hasSortOrder) {
        $sqlIns = "INSERT INTO {$table} (event_id, {$refCol}, sort_order) VALUES (?, ?, ?)";
        if ($stIns = $link->prepare($sqlIns)) {
            foreach (array_values($ids) as $i => $refId) {
                $sortOrder = (int)$i;
                $stIns->bind_param('iii', $eventId, $refId, $sortOrder);
                $stIns->execute();
            }
            $stIns->close();
        }
    } else {
        $sqlIns = "INSERT INTO {$table} (event_id, {$refCol}) VALUES (?, ?)";
        if ($stIns = $link->prepare($sqlIns)) {
            foreach (array_values($ids) as $refId) {
                $stIns->bind_param('ii', $eventId, $refId);
                $stIns->execute();
            }
            $stIns->close();
        }
    }
}

function hg_admin_sync_bridge_characters(mysqli $link, int $eventId, array $characterIds): void {
    if ($eventId <= 0) return;
    $hasSortOrder = hg_admin_col_exists($link, 'bridge_timeline_events_characters', 'sort_order');
    $hasRoleLabel = hg_admin_col_exists($link, 'bridge_timeline_events_characters', 'role_label');

    if ($stDel = $link->prepare("DELETE FROM bridge_timeline_events_characters WHERE event_id = ?")) {
        $stDel->bind_param('i', $eventId);
        $stDel->execute();
        $stDel->close();
    }

    if (empty($characterIds)) return;

    if ($hasRoleLabel && $hasSortOrder) {
        $sqlIns = "INSERT INTO bridge_timeline_events_characters (event_id, character_id, role_label, sort_order) VALUES (?, ?, NULL, ?)";
        if ($stIns = $link->prepare($sqlIns)) {
            foreach (array_values($characterIds) as $i => $characterId) {
                $sortOrder = (int)$i;
                $stIns->bind_param('iii', $eventId, $characterId, $sortOrder);
                $stIns->execute();
            }
            $stIns->close();
        }
        return;
    }

    if ($hasRoleLabel && !$hasSortOrder) {
        $sqlIns = "INSERT INTO bridge_timeline_events_characters (event_id, character_id, role_label) VALUES (?, ?, NULL)";
        if ($stIns = $link->prepare($sqlIns)) {
            foreach (array_values($characterIds) as $characterId) {
                $stIns->bind_param('ii', $eventId, $characterId);
                $stIns->execute();
            }
            $stIns->close();
        }
        return;
    }

    if (!$hasRoleLabel && $hasSortOrder) {
        $sqlIns = "INSERT INTO bridge_timeline_events_characters (event_id, character_id, sort_order) VALUES (?, ?, ?)";
        if ($stIns = $link->prepare($sqlIns)) {
            foreach (array_values($characterIds) as $i => $characterId) {
                $sortOrder = (int)$i;
                $stIns->bind_param('iii', $eventId, $characterId, $sortOrder);
                $stIns->execute();
            }
            $stIns->close();
        }
        return;
    }

    $sqlIns = "INSERT INTO bridge_timeline_events_characters (event_id, character_id) VALUES (?, ?)";
    if ($stIns = $link->prepare($sqlIns)) {
        foreach (array_values($characterIds) as $characterId) {
            $stIns->bind_param('ii', $eventId, $characterId);
            $stIns->execute();
        }
        $stIns->close();
    }
}

function hg_admin_get_bridge_ids(mysqli $link, string $table, string $refCol, int $eventId): array {
    $ids = [];
    if ($eventId <= 0) return $ids;
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $refCol = preg_replace('/[^a-zA-Z0-9_]/', '', $refCol);
    if ($table === '' || $refCol === '') return $ids;
    $orderBy = hg_admin_col_exists($link, $table, 'sort_order') ? 'sort_order ASC, id ASC' : 'id ASC';
    $sql = "SELECT {$refCol} AS ref_id FROM {$table} WHERE event_id = ? ORDER BY {$orderBy}";
    if ($st = $link->prepare($sql)) {
        $st->bind_param('i', $eventId);
        $st->execute();
        $rs = $st->get_result();
        while ($rs && ($row = $rs->fetch_assoc())) {
            $rid = (int)($row['ref_id'] ?? 0);
            if ($rid > 0) $ids[] = $rid;
        }
        $st->close();
    }
    return $ids;
}

function hg_timeline_admin_event_types(mysqli $link,bool $hasSort): array {
    $sortSelect=$hasSort?'sort_order':'0';$order=$hasSort?'sort_order ASC, name ASC':'name ASC, id ASC';
    $rows=[];$rs=$link->query("SELECT id, pretty_id, name, {$sortSelect} AS sort_order, is_active FROM dim_timeline_events_types ORDER BY {$order}");
    if($rs){while($r=$rs->fetch_assoc())$rows[]=$r;$rs->close();}return $rows;
}
function hg_timeline_admin_chronicles(mysqli $link,bool $hasSort): array {
    $order=$hasSort?'sort_order ASC, name ASC':'name ASC, id ASC';$rows=[];$rs=$link->query("SELECT id, name FROM dim_chronicles ORDER BY {$order}");
    if($rs){while($r=$rs->fetch_assoc())$rows[]=$r;$rs->close();}return $rows;
}
function hg_timeline_admin_realities(mysqli $link,bool $hasSort): array {
    $order=$hasSort?'sort_order ASC, name ASC':'name ASC, id ASC';$rows=[];$rs=$link->query("SELECT id, name FROM dim_realities ORDER BY {$order}");
    if($rs){while($r=$rs->fetch_assoc())$rows[]=$r;$rs->close();}return $rows;
}
function hg_timeline_admin_chapters(mysqli $link): array {
    $rows=[];$rs=$link->query("SELECT c.id,c.name,s.season_number,s.season_kind,c.chapter_number FROM dim_chapters c LEFT JOIN dim_seasons s ON s.id=c.season_id ORDER BY COALESCE(s.sort_order,9999) ASC,c.chapter_number ASC,c.id ASC");
    if($rs){while($r=$rs->fetch_assoc())$rows[]=$r;$rs->close();}return $rows;
}
function hg_timeline_admin_characters(mysqli $link): array {
    $rows=[];$rs=$link->query("SELECT p.id,p.name,COALESCE(ch.name,'') AS chronicle_name FROM fact_characters p LEFT JOIN dim_chronicles ch ON ch.id=p.chronicle_id ORDER BY p.name ASC,p.id ASC");
    if($rs){while($r=$rs->fetch_assoc())$rows[]=$r;$rs->close();}return $rows;
}
function hg_timeline_admin_event_links(mysqli $link,int $eventId): array {
    return [
      'chronicle_ids'=>hg_admin_get_bridge_ids($link,'bridge_timeline_events_chronicles','chronicle_id',$eventId),
      'character_ids'=>hg_admin_get_bridge_ids($link,'bridge_timeline_events_characters','character_id',$eventId),
      'chapter_ids'=>hg_admin_get_bridge_ids($link,'bridge_timeline_events_chapters','chapter_id',$eventId),
      'reality_ids'=>hg_admin_get_bridge_ids($link,'bridge_timeline_events_realities','reality_id',$eventId),
    ];
}
function hg_timeline_admin_event_row(mysqli $link,int $eventId,string $chronicleConcatOrderSql,string $primaryChronicleExpr): ?array {
    if($eventId<=0)return null;
    $sql="SELECT e.id,e.pretty_id,e.title,e.event_date,e.date_precision,e.date_note,e.location,e.source,e.description,e.is_active,e.event_type_id,
      COALESCE(t.name,'Evento') AS type_name,COALESCE(t.pretty_id,'evento') AS type_slug,
      {$primaryChronicleExpr} AS primary_chronicle_id,
      COALESCE(NULLIF(GROUP_CONCAT(DISTINCT c.name ORDER BY {$chronicleConcatOrderSql} SEPARATOR ' | '), ''),e.timeline,'') AS chronicle_line
      FROM fact_timeline_events e
      LEFT JOIN dim_timeline_events_types t ON t.id=e.event_type_id
      LEFT JOIN bridge_timeline_events_chronicles bec ON bec.event_id=e.id
      LEFT JOIN dim_chronicles c ON c.id=bec.chronicle_id
      WHERE e.id=?
      GROUP BY e.id,e.pretty_id,e.title,e.event_date,e.date_precision,e.date_note,e.location,e.source,e.description,e.is_active,e.event_type_id,t.name,t.pretty_id
      LIMIT 1";
    $st=$link->prepare($sql);if(!$st)return null;$st->bind_param('i',$eventId);$st->execute();$rs=$st->get_result();$row=$rs?$rs->fetch_assoc():null;$st->close();
    if(!$row)return null;$links=hg_timeline_admin_event_links($link,$eventId);
    foreach(['chronicle','character','chapter','reality'] as $k){$ids=$links[$k.'_ids'];$row[$k.'_ids']=implode(',',$ids);$row[$k.'s_count']=count($ids);}
    return $row;
}
function hg_timeline_admin_delete_event(mysqli $link,int $eventId): bool {
    if($eventId<=0)return false;$st=$link->prepare('DELETE FROM fact_timeline_events WHERE id = ?');if(!$st)return false;
    $st->bind_param('i',$eventId);$ok=$st->execute();$st->close();return (bool)$ok;
}
function hg_timeline_admin_save_event(mysqli $link,array $v,string $deathsTable): array {
    $id=(int)$v['id'];$savedId=0;
    try{
      $link->begin_transaction();
      if($id>0){
        $st=$link->prepare("UPDATE fact_timeline_events SET title=?,event_date=?,date_precision=?,date_note=?,sort_date=?,description=?,location=?,source=?,event_type_id=?,timeline=?,is_active=?,updated_at=NOW() WHERE id=?");
        if(!$st)throw new RuntimeException('No se pudo actualizar el evento.');
        $st->bind_param('ssssssssisii',$v['title'],$v['event_date'],$v['date_precision'],$v['date_note_sql'],$v['sort_date'],$v['description'],$v['location'],$v['source'],$v['event_type_id'],$v['timeline_legacy_sql'],$v['is_active'],$id);
        $ok=$st->execute();$st->close();if(!$ok)throw new RuntimeException('No se pudo actualizar el evento.');$savedId=$id;
      }else{
        $st=$link->prepare("INSERT INTO fact_timeline_events (title,event_date,date_precision,date_note,sort_date,description,location,source,event_type_id,timeline,is_active) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        if(!$st)throw new RuntimeException('No se pudo crear el evento.');
        $st->bind_param('ssssssssisi',$v['title'],$v['event_date'],$v['date_precision'],$v['date_note_sql'],$v['sort_date'],$v['description'],$v['location'],$v['source'],$v['event_type_id'],$v['timeline_legacy_sql'],$v['is_active']);
        $ok=$st->execute();$savedId=(int)$link->insert_id;$st->close();if(!$ok||$savedId<=0)throw new RuntimeException('No se pudo crear el evento.');
        hg_update_pretty_id_if_exists($link,'fact_timeline_events',$savedId,$v['title']);
      }
      hg_admin_sync_bridge_ids($link,'bridge_timeline_events_chronicles','chronicle_id',$savedId,$v['chronicle_ids']);
      hg_admin_sync_bridge_characters($link,$savedId,$v['character_ids']);
      hg_admin_sync_bridge_ids($link,'bridge_timeline_events_chapters','chapter_id',$savedId,$v['chapter_ids']);
      hg_admin_sync_bridge_ids($link,'bridge_timeline_events_realities','reality_id',$savedId,$v['reality_ids']);
      $syncDeathDate=($v['date_precision']==='unknown'||$v['event_date']==='1000-01-01')?null:$v['event_date'];
      hg_admin_sync_death_ground_truth($link,$deathsTable,$savedId,$syncDeathDate);
      $link->commit();
      return ['ok'=>true,'id'=>$savedId];
    }catch(Throwable $e){$link->rollback();return ['ok'=>false,'error'=>$e->getMessage()];}
}
function hg_timeline_admin_events(mysqli $link,string $chronicleConcatOrderSql,string $primaryChronicleExpr): array {
    $sql="SELECT e.id,e.pretty_id,e.title,e.event_date,e.date_precision,e.date_note,e.location,e.source,e.description,e.is_active,e.event_type_id,
      COALESCE(t.name,'Evento') AS type_name,COALESCE(t.pretty_id,'evento') AS type_slug,
      {$primaryChronicleExpr} AS primary_chronicle_id,
      COALESCE(NULLIF(GROUP_CONCAT(DISTINCT c.name ORDER BY {$chronicleConcatOrderSql} SEPARATOR ' | '), ''),e.timeline,'') AS chronicle_line
      FROM fact_timeline_events e
      LEFT JOIN dim_timeline_events_types t ON t.id=e.event_type_id
      LEFT JOIN bridge_timeline_events_chronicles bec ON bec.event_id=e.id
      LEFT JOIN dim_chronicles c ON c.id=bec.chronicle_id
      GROUP BY e.id,e.pretty_id,e.title,e.event_date,e.date_precision,e.date_note,e.location,e.source,e.description,e.is_active,e.event_type_id,t.name,t.pretty_id
      ORDER BY COALESCE(e.sort_date,e.event_date) DESC,e.id DESC";
    $rows=[];$rs=$link->query($sql);if($rs){while($r=$rs->fetch_assoc())$rows[]=$r;$rs->close();}
    foreach($rows as &$row){$id=(int)($row['id']??0);$links=$id>0?hg_timeline_admin_event_links($link,$id):['chronicle_ids'=>[],'character_ids'=>[],'chapter_ids'=>[],'reality_ids'=>[]];
      foreach(['chronicle','character','chapter','reality'] as $k){$ids=$links[$k.'_ids'];$row[$k.'_ids']=implode(',',$ids);$row[$k.'s_count']=count($ids);}
    }unset($row);return $rows;
}
