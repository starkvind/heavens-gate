<?php

function ac_col_exists(mysqli $link, string $table, string $column): bool {
    static $schema = [
        'dim_chapters' => ['image_url'],
        'bridge_chapters_characters' => ['participation_role', 'updated_at'],
    ];
    return isset($schema[$table]) && in_array($column, $schema[$table], true);
}

function ac_fetch_season(mysqli $link, int $seasonId): ?array {
    if ($seasonId <= 0) return null;
    if ($st = $link->prepare('SELECT id, season_number, name FROM dim_seasons WHERE id = ? LIMIT 1')) {
        $st->bind_param('i', $seasonId);
        $st->execute();
        $rs = $st->get_result();
        $row = $rs ? $rs->fetch_assoc() : null;
        $st->close();
        return $row ?: null;
    }
    return null;
}

function attach_chapter_characters(mysqli $link, int $chapterId, array $relations): int {
    if ($chapterId <= 0 || empty($relations)) {
        return 0;
    }

    $added = 0;

    $hasParticipationRole = ac_col_exists($link, 'bridge_chapters_characters', 'participation_role');
    $hasUpdatedAt = ac_col_exists($link, 'bridge_chapters_characters', 'updated_at');

    if ($hasParticipationRole) {
        if ($hasUpdatedAt) {
            $st = $link->prepare('
                INSERT INTO bridge_chapters_characters
                    (chapter_id, character_id, participation_role)
                VALUES
                    (?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    participation_role = VALUES(participation_role),
                    updated_at = CURRENT_TIMESTAMP
            ');
        } else {
            $st = $link->prepare('
                INSERT INTO bridge_chapters_characters
                    (chapter_id, character_id, participation_role)
                VALUES
                    (?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    participation_role = VALUES(participation_role)
            ');
        }
    } else {
        if ($hasUpdatedAt) {
            $st = $link->prepare('
                INSERT INTO bridge_chapters_characters
                    (chapter_id, character_id)
                VALUES
                    (?, ?)
                ON DUPLICATE KEY UPDATE
                    updated_at = CURRENT_TIMESTAMP
            ');
        } else {
            $st = $link->prepare('
                INSERT INTO bridge_chapters_characters
                    (chapter_id, character_id)
                VALUES
                    (?, ?)
                ON DUPLICATE KEY UPDATE
                    chapter_id = VALUES(chapter_id)
            ');
        }
    }

    if (!$st) {
        return 0;
    }

    foreach ($relations as $relation) {
        if (is_array($relation)) {
            $characterId = (int)($relation['character_id'] ?? 0);
            $participationRole = normalize_participation_role($relation['participation_role'] ?? 'npc');
        } else {
            $characterId = (int)$relation;
            $participationRole = 'npc';
        }

        if ($characterId <= 0) {
            continue;
        }

        if ($hasParticipationRole) {
            $st->bind_param('iis', $chapterId, $characterId, $participationRole);
        } else {
            $st->bind_param('ii', $chapterId, $characterId);
        }

        $st->execute();

        /*
         * affected_rows:
         * 1 = fila insertada
         * 2 = fila existente actualizada
         * 0 = fila existente sin cambios reales
         */
        if ($st->affected_rows === 1) {
            $added++;
        }
    }

    $st->close();

    return $added;
}

function hg_chapters_admin_get_relations(mysqli $link,int $chapterId,bool $hasBridgeId,bool $hasRole): array {
    $rows=[]; if($chapterId<=0)return $rows;
    if($hasBridgeId){
        $clean=$link->prepare("DELETE b1 FROM bridge_chapters_characters b1 INNER JOIN bridge_chapters_characters b2 ON b1.chapter_id=b2.chapter_id AND b1.character_id=b2.character_id AND b1.id>b2.id WHERE b1.chapter_id=?");
        if($clean){$clean->bind_param('i',$chapterId);$clean->execute();$clean->close();}
    }
    $roleExpr=$hasRole?"COALESCE(NULLIF(TRIM(b.participation_role), ''), 'npc')":"CASE WHEN c.character_kind = 'pj' THEN 'player' ELSE 'npc' END";
    $st=$link->prepare("SELECT b.character_id,c.name,ch.name AS chronicle_name,{$roleExpr} AS participation_role FROM bridge_chapters_characters b JOIN fact_characters c ON c.id=b.character_id LEFT JOIN dim_chronicles ch ON ch.id=c.chronicle_id WHERE b.chapter_id=? ORDER BY c.name ASC,c.id ASC");
    if($st){$st->bind_param('i',$chapterId);$st->execute();$rs=$st->get_result();while($r=$rs->fetch_assoc())$rows[]=$r;$st->close();}
    return $rows;
}
function hg_chapters_admin_add_relation(mysqli $link,int $chapterId,int $characterId,string $role,bool $hasRole): bool {
    if($chapterId<=0||$characterId<=0)return false;
    $exists=0;$chk=$link->prepare('SELECT COUNT(*) FROM bridge_chapters_characters WHERE chapter_id = ? AND character_id = ?');
    if($chk){$chk->bind_param('ii',$chapterId,$characterId);$chk->execute();$chk->bind_result($exists);$chk->fetch();$chk->close();}
    if($exists>0){
        if(!$hasRole)return true;
        $st=$link->prepare('UPDATE bridge_chapters_characters SET participation_role = ? WHERE chapter_id = ? AND character_id = ?');
        if(!$st)return false;$st->bind_param('sii',$role,$chapterId,$characterId);$ok=$st->execute();$st->close();return (bool)$ok;
    }
    $st=$hasRole
        ? $link->prepare('INSERT INTO bridge_chapters_characters (chapter_id, character_id, participation_role) VALUES (?, ?, ?)')
        : $link->prepare('INSERT INTO bridge_chapters_characters (chapter_id, character_id) VALUES (?, ?)');
    if(!$st)return false;
    if($hasRole)$st->bind_param('iis',$chapterId,$characterId,$role);else $st->bind_param('ii',$chapterId,$characterId);
    $ok=$st->execute();$st->close();return (bool)$ok;
}
function hg_chapters_admin_update_relation_role(mysqli $link,int $chapterId,int $characterId,string $role,bool $hasRole): bool {
    if($chapterId<=0||$characterId<=0)return false;
    if(!$hasRole)return true;
    $st=$link->prepare('UPDATE bridge_chapters_characters SET participation_role = ? WHERE chapter_id = ? AND character_id = ?');
    if(!$st)return false;$st->bind_param('sii',$role,$chapterId,$characterId);$ok=$st->execute();$st->close();return (bool)$ok;
}
function hg_chapters_admin_delete_relation(mysqli $link,int $chapterId,int $characterId): bool {
    if($chapterId<=0||$characterId<=0)return false;
    $st=$link->prepare('DELETE FROM bridge_chapters_characters WHERE chapter_id = ? AND character_id = ?');
    if(!$st)return false;$st->bind_param('ii',$chapterId,$characterId);$ok=$st->execute();$st->close();return (bool)$ok;
}
function hg_chapters_admin_current_image(mysqli $link,int $id): string {
    if($id<=0)return '';$st=$link->prepare('SELECT image_url FROM dim_chapters WHERE id = ? LIMIT 1');if(!$st)return '';
    $st->bind_param('i',$id);$st->execute();$rs=$st->get_result();$row=$rs?$rs->fetch_assoc():null;$st->close();return (string)($row['image_url']??'');
}
function hg_chapters_admin_delete(mysqli $link,int $id): bool {
    if($id<=0)return false;$st=$link->prepare('DELETE FROM dim_chapters WHERE id = ?');if(!$st)return false;
    $st->bind_param('i',$id);$ok=$st->execute();$st->close();return (bool)$ok;
}
function hg_chapters_admin_save(mysqli $link,int $id,string $name,int $chapterNumber,int $seasonId,?string $playedDate,string $synopsis,string $imageUrl,bool $hasImage): array {
    if($id>0){
        if($hasImage){$st=$link->prepare('UPDATE dim_chapters SET name=?, chapter_number=?, season_id=?, played_date=?, synopsis=?, image_url=?, updated_at=NOW() WHERE id=?');if($st)$st->bind_param('siisssi',$name,$chapterNumber,$seasonId,$playedDate,$synopsis,$imageUrl,$id);}
        else{$st=$link->prepare('UPDATE dim_chapters SET name=?, chapter_number=?, season_id=?, played_date=?, synopsis=?, updated_at=NOW() WHERE id=?');if($st)$st->bind_param('siissi',$name,$chapterNumber,$seasonId,$playedDate,$synopsis,$id);}
        if(!$st)return ['ok'=>false,'id'=>0];$ok=$st->execute();$st->close();return ['ok'=>(bool)$ok,'id'=>$id];
    }
    if($hasImage){$st=$link->prepare('INSERT INTO dim_chapters (name, chapter_number, season_id, played_date, synopsis, image_url, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');if($st)$st->bind_param('siisss',$name,$chapterNumber,$seasonId,$playedDate,$synopsis,$imageUrl);}
    else{$st=$link->prepare('INSERT INTO dim_chapters (name, chapter_number, season_id, played_date, synopsis, created_at) VALUES (?, ?, ?, ?, ?, NOW())');if($st)$st->bind_param('siiss',$name,$chapterNumber,$seasonId,$playedDate,$synopsis);}
    if(!$st)return ['ok'=>false,'id'=>0];$ok=$st->execute();$newId=(int)$link->insert_id;$st->close();return ['ok'=>(bool)$ok,'id'=>$newId];
}
function hg_chapters_admin_set_image(mysqli $link,int $id,string $url): bool {
    $st=$link->prepare('UPDATE dim_chapters SET image_url = ? WHERE id = ?');if(!$st)return false;$st->bind_param('si',$url,$id);$ok=$st->execute();$st->close();return (bool)$ok;
}
function hg_chapters_admin_fetch_row(mysqli $link,int $id,bool $hasImage): ?array {
    if($id<=0)return null;$image=$hasImage?'c.image_url,':"'' AS image_url,";
    $st=$link->prepare("SELECT c.id,c.name,c.chapter_number,{$image} s.season_number AS season_number,c.season_id AS season_id,c.played_date,c.synopsis,s.name AS season_name,s.sort_order AS season_sort FROM dim_chapters c LEFT JOIN dim_seasons s ON s.id=c.season_id WHERE c.id=? LIMIT 1");
    if(!$st)return null;$st->bind_param('i',$id);$st->execute();$rs=$st->get_result();$row=$rs?$rs->fetch_assoc():null;$st->close();return $row?:null;
}
function hg_chapters_admin_characters(mysqli $link): array {
    $rows=[];$rs=$link->query('SELECT p.id,p.name,COALESCE(ch.name, "") AS chronicle_name FROM fact_characters p LEFT JOIN dim_chronicles ch ON ch.id=p.chronicle_id ORDER BY p.name ASC,p.id ASC');
    if($rs){while($r=$rs->fetch_assoc())$rows[]=$r;$rs->close();}return $rows;
}
function hg_chapters_admin_seasons(mysqli $link): array {
    $rows=[];$rs=$link->query('SELECT id,season_number,name,sort_order FROM dim_seasons ORDER BY sort_order ASC,season_number ASC,id ASC');
    if($rs){while($r=$rs->fetch_assoc())$rows[]=$r;$rs->close();}return $rows;
}
function hg_chapters_admin_rows(mysqli $link,bool $hasImage): array {
    $rows=[];$image=$hasImage?'c.image_url,':"'' AS image_url,";
    $rs=$link->query("SELECT c.id,c.name,c.chapter_number,{$image} s.season_number AS season_number,c.season_id AS season_id,c.played_date,c.synopsis,s.name AS season_name,s.sort_order AS season_sort FROM dim_chapters c LEFT JOIN dim_seasons s ON s.id=c.season_id ORDER BY COALESCE(s.sort_order,9999) ASC,c.chapter_number ASC,c.id ASC");
    if($rs){while($r=$rs->fetch_assoc())$rows[]=$r;$rs->close();}return $rows;
}
