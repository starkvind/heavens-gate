<?php
include_once(__DIR__ . '/../../helpers/content_updates.php');

function hg_acd_has_table(mysqli $db, string $table): bool {
    $table = str_replace('`', '', $table);
    $rs = $db->query("SHOW TABLES LIKE '".$db->real_escape_string($table)."'");
    if (!$rs) return false;
    $ok = ($rs->num_rows > 0);
    $rs->close();
    return $ok;
}

function hg_acd_pick_deaths_table(mysqli $db): string {
    if (hg_acd_has_table($db, 'fact_characters_deaths')) return 'fact_characters_deaths';
    if (hg_acd_has_table($db, 'fact_characters_death')) return 'fact_characters_death';
    return '';
}

function hg_acd_col_exists(mysqli $db, string $table, string $column): bool {
    static $cache = [];
    $key = $table . ':' . $column;
    if (isset($cache[$key])) return $cache[$key];
    $ok = false;
    if ($st = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?")) {
        $st->bind_param('ss', $table, $column);
        $st->execute();
        $st->bind_result($count);
        $st->fetch();
        $st->close();
        $ok = ((int)$count > 0);
    }
    $cache[$key] = $ok;
    return $ok;
}

function hg_acd_character_name(mysqli $db, int $characterId): string {
    if ($characterId <= 0) return '';
    $name = '';
    if ($st = $db->prepare("SELECT name FROM fact_characters WHERE id = ? LIMIT 1")) {
        $st->bind_param('i', $characterId);
        $st->execute();
        $st->bind_result($name);
        $st->fetch();
        $st->close();
    }
    return trim((string)$name);
}

function hg_acd_resolve_status_id(mysqli $db, string $prettyId, string $label, int $fallback): int {
    if (!hg_acd_has_table($db, 'dim_character_status')) {
        return $fallback;
    }
    $id = 0;
    if ($st = $db->prepare("SELECT id FROM dim_character_status WHERE pretty_id = ? LIMIT 1")) {
        $st->bind_param('s', $prettyId);
        $st->execute();
        $st->bind_result($id);
        $st->fetch();
        $st->close();
    }
    if ((int)$id > 0) return (int)$id;

    if ($st = $db->prepare("SELECT id FROM dim_character_status WHERE LOWER(label) = LOWER(?) LIMIT 1")) {
        $st->bind_param('s', $label);
        $st->execute();
        $st->bind_result($id);
        $st->fetch();
        $st->close();
    }
    if ((int)$id > 0) return (int)$id;

    return $fallback;
}

function hg_acd_set_character_status_by_death(mysqli $db, int $characterId, bool $hasDeath): void {
    if ($characterId <= 0 || !hg_acd_has_table($db, 'fact_characters')) return;
    $statusId = $hasDeath
        ? hg_acd_resolve_status_id($db, 'cadaver', 'Cadáver', 3)
        : hg_acd_resolve_status_id($db, 'en_activo', 'En activo', 1);
    if ($statusId <= 0) return;

    if ($st = $db->prepare("UPDATE fact_characters SET status_id = ? WHERE id = ? LIMIT 1")) {
        $st->bind_param('ii', $statusId, $characterId);
        $st->execute();
        $st->close();
        hg_content_touch_table($db, 'fact_characters', $characterId);
    }
}

function hg_acd_sync_event_characters(mysqli $db, int $eventId, int $characterId, ?int $killerId): void {
    if ($eventId <= 0 || $characterId <= 0) return;
    if (!hg_acd_has_table($db, 'bridge_timeline_events_characters')) return;

    $hasSortOrder = hg_acd_col_exists($db, 'bridge_timeline_events_characters', 'sort_order');
    $hasRoleLabel = hg_acd_col_exists($db, 'bridge_timeline_events_characters', 'role_label');

    if ($st = $db->prepare("DELETE FROM bridge_timeline_events_characters WHERE event_id = ?")) {
        $st->bind_param('i', $eventId);
        $st->execute();
        $st->close();
    }

    $pairs = [
        ['id' => $characterId, 'role' => 'victima', 'sort' => 0],
    ];
    if ($killerId !== null && $killerId > 0 && $killerId !== $characterId) {
        $pairs[] = ['id' => $killerId, 'role' => 'killer', 'sort' => 1];
    }

    if ($hasRoleLabel && $hasSortOrder) {
        $sql = "INSERT INTO bridge_timeline_events_characters (event_id, character_id, role_label, sort_order) VALUES (?, ?, ?, ?)";
        if ($st = $db->prepare($sql)) {
            foreach ($pairs as $p) {
                $cid = (int)$p['id'];
                $role = (string)$p['role'];
                $sort = (int)$p['sort'];
                $st->bind_param('iisi', $eventId, $cid, $role, $sort);
                $st->execute();
            }
            $st->close();
        }
        return;
    }

    if ($hasRoleLabel && !$hasSortOrder) {
        $sql = "INSERT INTO bridge_timeline_events_characters (event_id, character_id, role_label) VALUES (?, ?, ?)";
        if ($st = $db->prepare($sql)) {
            foreach ($pairs as $p) {
                $cid = (int)$p['id'];
                $role = (string)$p['role'];
                $st->bind_param('iis', $eventId, $cid, $role);
                $st->execute();
            }
            $st->close();
        }
        return;
    }

    if (!$hasRoleLabel && $hasSortOrder) {
        $sql = "INSERT INTO bridge_timeline_events_characters (event_id, character_id, sort_order) VALUES (?, ?, ?)";
        if ($st = $db->prepare($sql)) {
            foreach ($pairs as $p) {
                $cid = (int)$p['id'];
                $sort = (int)$p['sort'];
                $st->bind_param('iii', $eventId, $cid, $sort);
                $st->execute();
            }
            $st->close();
        }
        return;
    }

    $sql = "INSERT INTO bridge_timeline_events_characters (event_id, character_id) VALUES (?, ?)";
    if ($st = $db->prepare($sql)) {
        foreach ($pairs as $p) {
            $cid = (int)$p['id'];
            $st->bind_param('ii', $eventId, $cid);
            $st->execute();
        }
        $st->close();
    }
}

function hg_acd_sync_event_chronicle(mysqli $db, int $eventId, int $characterId): void {
    if ($eventId <= 0 || $characterId <= 0) return;
    if (!hg_acd_has_table($db, 'bridge_timeline_events_chronicles')) return;

    $chronicleId = 0;
    if ($st = $db->prepare("SELECT chronicle_id FROM fact_characters WHERE id = ? LIMIT 1")) {
        $st->bind_param('i', $characterId);
        $st->execute();
        $st->bind_result($chronicleId);
        $st->fetch();
        $st->close();
    }
    $chronicleId = (int)$chronicleId;

    if ($st = $db->prepare("DELETE FROM bridge_timeline_events_chronicles WHERE event_id = ?")) {
        $st->bind_param('i', $eventId);
        $st->execute();
        $st->close();
    }
    if ($chronicleId <= 0) return;

    $hasSortOrder = hg_acd_col_exists($db, 'bridge_timeline_events_chronicles', 'sort_order');
    if ($hasSortOrder) {
        if ($st = $db->prepare("INSERT INTO bridge_timeline_events_chronicles (event_id, chronicle_id, sort_order) VALUES (?, ?, 0)")) {
            $st->bind_param('ii', $eventId, $chronicleId);
            $st->execute();
            $st->close();
        }
    } else {
        if ($st = $db->prepare("INSERT INTO bridge_timeline_events_chronicles (event_id, chronicle_id) VALUES (?, ?)")) {
            $st->bind_param('ii', $eventId, $chronicleId);
            $st->execute();
            $st->close();
        }
    }
}

function hg_acd_sync_timeline_from_death(mysqli $db, string $deathsTable, ?int $eventId, int $characterId, ?int $killerId, string $deathType, ?string $deathDate, ?string $deathDescription): ?int {
    if ($eventId !== null && $eventId > 0) {
        $eventId = (int)$eventId;
    } else {
        $eventId = null;
    }

    $charName = hg_acd_character_name($db, $characterId);
    if ($charName === '') {
        return $eventId;
    }
    $killerName = ($killerId !== null && $killerId > 0) ? hg_acd_character_name($db, $killerId) : '';

    $eventDate = ($deathDate !== null && $deathDate !== '') ? $deathDate : '1000-01-01';
    $precision = ($deathDate !== null && $deathDate !== '') ? 'day' : 'unknown';
    $dateNote = ($deathDate !== null && $deathDate !== '')
        ? null
        : "Fecha de muerte no especificada (sincronizado desde {$deathsTable}).";
    $title = 'Muerte de ' . $charName;
    $description = trim((string)$deathDescription);
    if ($description === '') {
        $description = $charName . ' muere';
        if ($killerName !== '') {
            $description .= ' a manos de ' . $killerName;
        }
        $description .= '.';
    }
    if ($deathType !== '') {
        $description .= ' [tipo: ' . $deathType . ']';
    }
    $source = $deathsTable . '.character#' . $characterId;
    $isActive = 1;

    if ($eventId === null) {
        if ($st = $db->prepare("
            INSERT INTO fact_timeline_events
                (title, event_date, date_precision, date_note, sort_date, description, source, event_type_id, is_active)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, 5, ?)
        ")) {
            $st->bind_param('sssssssi', $title, $eventDate, $precision, $dateNote, $eventDate, $description, $source, $isActive);
            $ok = $st->execute();
            if ($ok) {
                $eventId = (int)$db->insert_id;
            }
            $st->close();
        }
    } else if ($st = $db->prepare("
        UPDATE fact_timeline_events
        SET
            title = ?,
            description = ?,
            source = ?,
            event_type_id = 5,
            event_date = ?,
            sort_date = ?,
            date_precision = ?,
            date_note = ?,
            is_active = 1,
            updated_at = NOW()
        WHERE id = ?
        LIMIT 1
    ")) {
        $st->bind_param('sssssssi', $title, $description, $source, $eventDate, $eventDate, $precision, $dateNote, $eventId);
        $st->execute();
        $st->close();
    }

    if ($eventId !== null && $eventId > 0) {
        hg_acd_sync_event_characters($db, $eventId, $characterId, $killerId);
        hg_acd_sync_event_chronicle($db, $eventId, $characterId);
    }

    return $eventId;
}


function hg_acd_fetch_deaths_rows(mysqli $link, string $deathsTable, string $q = '', int $limit = 0, int $offset = 0, ?int &$totalOut = null): array {
    $rows = [];
    $q = trim($q);
    $hasQ = ($q !== '');
    $needle = '%' . $q . '%';

    $fromSql = "
        FROM `{$deathsTable}` d
        INNER JOIN fact_characters p ON p.id = d.character_id
        LEFT JOIN dim_character_status dcs ON dcs.id = p.status_id
        LEFT JOIN fact_characters k ON k.id = d.killer_character_id
        LEFT JOIN fact_timeline_events e ON e.id = d.death_timeline_event_id
    ";
    $whereSql = $hasQ
        ? " WHERE (
                p.name LIKE ? OR
                p.pretty_id LIKE ? OR
                COALESCE(d.death_type, '') LIKE ? OR
                COALESCE(k.name, '') LIKE ? OR
                COALESCE(e.title, '') LIKE ?
            ) "
        : "";

    if ($totalOut !== null) {
        $totalOut = 0;
        if ($hasQ) {
            $sqlCnt = "SELECT COUNT(*) " . $fromSql . $whereSql;
            if ($stCnt = $link->prepare($sqlCnt)) {
                $stCnt->bind_param('sssss', $needle, $needle, $needle, $needle, $needle);
                $stCnt->execute();
                $stCnt->bind_result($totalOut);
                $stCnt->fetch();
                $stCnt->close();
            }
        } else if ($rsCnt = $link->query("SELECT COUNT(*) AS c " . $fromSql)) {
            if ($rCnt = $rsCnt->fetch_assoc()) {
                $totalOut = (int)($rCnt['c'] ?? 0);
            }
            $rsCnt->close();
        }
    }

    $selectSql = "
        SELECT
            d.id AS death_id,
            d.character_id,
            p.pretty_id,
            p.name AS character_name,
            COALESCE(dcs.label, '') AS status_label,
            d.killer_character_id,
            d.death_timeline_event_id,
            d.death_type,
            d.death_date,
            d.death_description,
            d.narrative_weight,
            COALESCE(k.name, '') AS killer_name,
            COALESCE(e.title, '') AS event_title,
            COALESCE(e.event_date, '') AS event_date
        " . $fromSql . $whereSql . "
        ORDER BY
            COALESCE(d.death_date, '1000-01-01') DESC,
            d.id DESC
    ";
    if ($limit > 0) {
        $selectSql .= " LIMIT ?, ?";
    }

    if ($st = $link->prepare($selectSql)) {
        if ($hasQ && $limit > 0) {
            $st->bind_param('sssssii', $needle, $needle, $needle, $needle, $needle, $offset, $limit);
        } elseif ($hasQ) {
            $st->bind_param('sssss', $needle, $needle, $needle, $needle, $needle);
        } elseif ($limit > 0) {
            $st->bind_param('ii', $offset, $limit);
        }
        $st->execute();
        $rs = $st->get_result();
        while ($r = $rs->fetch_assoc()) {
            $r['character_id'] = (int)($r['character_id'] ?? 0);
            $r['death_id'] = (int)($r['death_id'] ?? 0);
            $r['killer_character_id'] = (int)($r['killer_character_id'] ?? 0);
            $r['death_timeline_event_id'] = (int)($r['death_timeline_event_id'] ?? 0);
            $r['narrative_weight'] = (int)($r['narrative_weight'] ?? 1);
            if ($r['narrative_weight'] < 1) $r['narrative_weight'] = 1;
            if ($r['narrative_weight'] > 10) $r['narrative_weight'] = 10;

            $summary = [];
            $deathType = trim((string)($r['death_type'] ?? ''));
            if ($deathType !== '') $summary[] = ucfirst($deathType);
            if ((int)$r['killer_character_id'] > 0) {
                $summary[] = 'Por: ' . (trim((string)$r['killer_name']) !== '' ? (string)$r['killer_name'] : ('#' . (int)$r['killer_character_id']));
            }
            if ((int)$r['death_timeline_event_id'] > 0) {
                $summary[] = 'Evento: ' . (trim((string)$r['event_title']) !== '' ? (string)$r['event_title'] : ('#' . (int)$r['death_timeline_event_id']));
            }
            $r['summary_text'] = !empty($summary) ? implode(' | ', $summary) : 'Sin resumen';
            $rows[] = $r;
        }
        $st->close();
    }

    return $rows;
}



if (!function_exists('hg_acd_record_exists')) {
    function hg_acd_record_exists(mysqli $link, string $table, int $id): bool {
        if ($id <= 0) return false;
        $st=$link->prepare("SELECT COUNT(*) FROM ".$table." WHERE id=?");
        if(!$st)return false;
        $st->bind_param('i',$id);$st->execute();$st->bind_result($n);$st->fetch();$st->close();
        return (int)$n>0;
    }
}

if (!function_exists('hg_acd_event_label')) {
    function hg_acd_event_label(mysqli $link, ?int $eventId): string {
        if ($eventId === null || $eventId <= 0) return '';
        $eventTitle='';$eventDate='';
        if($st=$link->prepare("SELECT event_date, title FROM fact_timeline_events WHERE id = ? LIMIT 1")){
            $st->bind_param('i',$eventId);$st->execute();$st->bind_result($eventDate,$eventTitle);$st->fetch();$st->close();
        }
        return '#'.$eventId.($eventDate!==''?(' ['.$eventDate.'] '):' ').trim((string)$eventTitle);
    }
}

if (!function_exists('hg_acd_save_death')) {
    function hg_acd_save_death(
        mysqli $link,
        string $deathsTable,
        int $characterId,
        ?int $killerId,
        ?int $timelineEventId,
        string $deathType,
        ?string $deathDate,
        ?string $deathDescription,
        int $weight
    ): array {
        if (!hg_acd_record_exists($link,'fact_characters',$characterId)) return ['ok'=>false,'msg'=>'Personaje no encontrado'];
        if ($killerId !== null && !hg_acd_record_exists($link,'fact_characters',$killerId)) return ['ok'=>false,'msg'=>'Responsable no encontrado'];
        if ($timelineEventId !== null && !hg_acd_record_exists($link,'fact_timeline_events',$timelineEventId)) return ['ok'=>false,'msg'=>'Evento no encontrado'];

        $existingId=0;
        if($st=$link->prepare("SELECT id FROM ".$deathsTable." WHERE character_id=? LIMIT 1")){
            $st->bind_param('i',$characterId);$st->execute();$st->bind_result($existingId);$st->fetch();$st->close();
        }

        if($existingId>0){
            $st=$link->prepare("UPDATE ".$deathsTable." SET killer_character_id=?, death_timeline_event_id=?, death_type=?, death_date=?, death_description=?, narrative_weight=? WHERE id=? LIMIT 1");
            if(!$st)return ['ok'=>false,'msg'=>'Error al preparar UPDATE'];
            $st->bind_param('iisssii',$killerId,$timelineEventId,$deathType,$deathDate,$deathDescription,$weight,$existingId);
            if(!$st->execute()){$st->close();return ['ok'=>false,'msg'=>'Error al actualizar la muerte'];}
            $st->close();

            $timelineEventId=hg_acd_sync_timeline_from_death($link,$deathsTable,$timelineEventId,$characterId,$killerId,$deathType,$deathDate,$deathDescription);
            if($timelineEventId!==null&&$timelineEventId>0){
                if($st=$link->prepare("UPDATE ".$deathsTable." SET death_timeline_event_id = ? WHERE id = ? LIMIT 1")){
                    $st->bind_param('ii',$timelineEventId,$existingId);$st->execute();$st->close();
                }
            }
            hg_acd_set_character_status_by_death($link,$characterId,true);
            return ['ok'=>true,'msg'=>'Guardado','mode'=>'update','event_id'=>$timelineEventId?(int)$timelineEventId:0,'event_label'=>hg_acd_event_label($link,$timelineEventId)];
        }

        $st=$link->prepare("INSERT INTO ".$deathsTable." (character_id, killer_character_id, death_timeline_event_id, death_type, death_date, death_description, narrative_weight) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if(!$st)return ['ok'=>false,'msg'=>'Error al preparar INSERT'];
        $st->bind_param('iiisssi',$characterId,$killerId,$timelineEventId,$deathType,$deathDate,$deathDescription,$weight);
        if(!$st->execute()){$st->close();return ['ok'=>false,'msg'=>'Error al insertar la muerte'];}
        $st->close();
        $newDeathId=(int)$link->insert_id;

        $timelineEventId=hg_acd_sync_timeline_from_death($link,$deathsTable,$timelineEventId,$characterId,$killerId,$deathType,$deathDate,$deathDescription);
        if($timelineEventId!==null&&$timelineEventId>0){
            if($st=$link->prepare("UPDATE ".$deathsTable." SET death_timeline_event_id = ? WHERE id = ? LIMIT 1")){
                $st->bind_param('ii',$timelineEventId,$newDeathId);$st->execute();$st->close();
            }
        }
        hg_acd_set_character_status_by_death($link,$characterId,true);
        return ['ok'=>true,'msg'=>'Guardado','mode'=>'insert','event_id'=>$timelineEventId?(int)$timelineEventId:0,'event_label'=>hg_acd_event_label($link,$timelineEventId)];
    }
}

if (!function_exists('hg_acd_delete_death')) {
    function hg_acd_delete_death(mysqli $link, string $deathsTable, int $characterId): array {
        if($characterId<=0)return ['ok'=>false,'msg'=>'Personaje inválido'];
        $st=$link->prepare("DELETE FROM ".$deathsTable." WHERE character_id=? LIMIT 1");
        if(!$st)return ['ok'=>false,'msg'=>'Error al preparar DELETE'];
        $st->bind_param('i',$characterId);
        if(!$st->execute()){$st->close();return ['ok'=>false,'msg'=>'Error al borrar'];}
        $st->close();
        hg_acd_set_character_status_by_death($link,$characterId,false);
        return ['ok'=>true,'msg'=>'Muerte eliminada'];
    }
}

if (!function_exists('hg_acd_load_state')) {
    function hg_acd_load_state(mysqli $link, string $deathsTable, string $q, int $page, int $perPage): array {
        $characters=[];$events=[];
        if($rs=$link->query("SELECT id, name FROM fact_characters ORDER BY name ASC")){
            while($row=$rs->fetch_assoc()){
                $cid=(int)($row['id']??0);if($cid<=0)continue;
                $characters[]=['id'=>$cid,'name'=>(string)($row['name']??'')];
            }
            $rs->close();
        }
        if($rs=$link->query("SELECT id, event_date, title FROM fact_timeline_events ORDER BY event_date DESC, id DESC")){
            while($row=$rs->fetch_assoc())$events[]=$row;
            $rs->close();
        }
        $total=0;
        $offset=($page-1)*$perPage;
        $rows=hg_acd_fetch_deaths_rows($link,$deathsTable,$q,$perPage,$offset,$total);
        $pages=max(1,(int)ceil($total/$perPage));
        if($page>$pages)$page=$pages;
        $rowMap=[];
        foreach($rows as $row)$rowMap[(string)((int)($row['character_id']??0))]=$row;
        return ['characters'=>$characters,'killers'=>$characters,'events'=>$events,'rows'=>$rows,'rowMap'=>$rowMap,'total'=>$total,'pages'=>$pages,'page'=>$page];
    }
}
