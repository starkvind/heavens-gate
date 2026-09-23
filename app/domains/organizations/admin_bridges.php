<?php

function fetchPairs(mysqli $link, string $sql): array {
  $out = [];
  if ($rs = $link->query($sql)) {
    while($r = $rs->fetch_assoc()){
      $out[(int)$r['id']] = (string)$r['name'];
    }
    $rs->close();
  }
  return $out;
}

function bridges_table_has_column(mysqli $link, string $table, string $column): bool {
  $t = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
  $c = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
  if ($t === '' || $c === '') return false;
  $sql = "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . mysqli_real_escape_string($link, $t) . "' AND COLUMN_NAME = '" . mysqli_real_escape_string($link, $c) . "' LIMIT 1";
  if (!$rs = $link->query($sql)) return false;
  $ok = ($rs->num_rows > 0);
  $rs->close();
  return $ok;
}


function set_active_character_group(mysqli $link, string $T_CHAR_GROUP, int $charId, int $groupId): void {
  if ($charId <= 0) return;

  if ($groupId > 0) {
    if ($ins = $link->prepare("INSERT INTO {$T_CHAR_GROUP} (character_id, group_id, is_active) VALUES (?,?,1) ON DUPLICATE KEY UPDATE is_active=1")) {
      $ins->bind_param("ii",$charId,$groupId); $ins->execute(); $ins->close();
    }

    // Desactiva otras manadas del PJ
    if ($off = $link->prepare("UPDATE {$T_CHAR_GROUP} SET is_active=0 WHERE character_id=? AND group_id<>?")) {
      $off->bind_param("ii",$charId,$groupId); $off->execute(); $off->close();
    }
  } else {
    if ($off = $link->prepare("UPDATE {$T_CHAR_GROUP} SET is_active=0 WHERE character_id=?")) {
      $off->bind_param("i",$charId); $off->execute(); $off->close();
    }
  }
}

/**
 * Activa UNA relacion PJ->Clan y desactiva el resto para ese PJ.
 * Si $clanId <= 0: desactiva todas.
 */
function set_active_character_clan(mysqli $link, string $T_CHAR_CLAN, int $charId, int $clanId): void {
  if ($charId <= 0) return;

  if ($clanId > 0) {
    if ($ins = $link->prepare("INSERT INTO {$T_CHAR_CLAN} (character_id, organization_id, is_active) VALUES (?,?,1) ON DUPLICATE KEY UPDATE is_active=1")) {
      $ins->bind_param("ii",$charId,$clanId); $ins->execute(); $ins->close();
    }
    if ($off = $link->prepare("UPDATE {$T_CHAR_CLAN} SET is_active=0 WHERE character_id=? AND organization_id<>?")) {
      $off->bind_param("ii",$charId,$clanId); $off->execute(); $off->close();
    }
  } else {
    if ($off = $link->prepare("UPDATE {$T_CHAR_CLAN} SET is_active=0 WHERE character_id=?")) {
      $off->bind_param("i",$charId); $off->execute(); $off->close();
    }
  }
}

/**
 * Dado un group_id (manada), intenta resolver su clan activo via bridge_organizations_groups.
 * Devuelve organization_id o 0 si no encuentra.
 */
function resolve_clan_for_group(mysqli $link, string $T_CLAN_GROUP, int $groupId): int {
  if ($groupId <= 0) return 0;
  $cid = 0;
  $hasId = bridges_table_has_column($link, $T_CLAN_GROUP, 'id');
  $orderBy = $hasId ? 'ORDER BY id DESC' : 'ORDER BY updated_at DESC, created_at DESC, organization_id DESC';
  $sql = "SELECT organization_id FROM {$T_CLAN_GROUP} WHERE group_id=? AND (is_active=1 OR is_active IS NULL) {$orderBy} LIMIT 1";
  if ($st = $link->prepare($sql)) {
    $st->bind_param("i",$groupId);
    $st->execute();
    $rs = $st->get_result();
    if ($rs && ($r=$rs->fetch_assoc())) $cid = (int)$r['organization_id'];
    $st->close();
  }
  return $cid;
}

/**
 * Activa/desactiva una relacion Clan->Manada. Aqui NO imponemos "solo una" por clan (porque un clan tiene muchas manadas),
 * pero si puedes imponer "una manada solo puede pertenecer a un clan activo" desactivando el resto por group_id.
 */
function set_clan_group(mysqli $link, string $T_CLAN_GROUP, int $clanId, int $groupId, int $isActive, bool $enforceOneActiveOwnerPerGroup = true): void {
  if ($clanId<=0 || $groupId<=0) return;
  $isActive = $isActive ? 1 : 0;

  if ($ins = $link->prepare("INSERT INTO {$T_CLAN_GROUP} (organization_id, group_id, is_active) VALUES (?,?,?) ON DUPLICATE KEY UPDATE is_active=VALUES(is_active)")) {
    $ins->bind_param("iii",$clanId,$groupId,$isActive); $ins->execute(); $ins->close();
  }

  // Si activas una (clanId, groupId) y quieres que la manada tenga SOLO un clan activo a la vez:
  if ($isActive===1 && $enforceOneActiveOwnerPerGroup) {
    if ($off = $link->prepare("UPDATE {$T_CLAN_GROUP} SET is_active=0 WHERE group_id=? AND organization_id<>?")) {
      $off->bind_param("ii",$groupId,$clanId); $off->execute(); $off->close();
    }
  }
}



if (!function_exists('bridges_deactivate_row')) {
  function bridges_deactivate_row(mysqli $link, string $table, int $id, int $characterId, int $organizationId, int $groupId, array $allowed): bool {
    if (!in_array($table,$allowed,true)) return false;
    if ($table === $allowed[0] && $characterId > 0 && $groupId > 0) {
      $st=$link->prepare("UPDATE {$table} SET is_active=0 WHERE character_id=? AND group_id=?");
      if(!$st)return false; $st->bind_param("ii",$characterId,$groupId);
    } elseif ($table === $allowed[1] && $characterId > 0 && $organizationId > 0) {
      $st=$link->prepare("UPDATE {$table} SET is_active=0 WHERE character_id=? AND organization_id=?");
      if(!$st)return false; $st->bind_param("ii",$characterId,$organizationId);
    } elseif ($table === $allowed[2] && $organizationId > 0 && $groupId > 0) {
      $st=$link->prepare("UPDATE {$table} SET is_active=0 WHERE organization_id=? AND group_id=?");
      if(!$st)return false; $st->bind_param("ii",$organizationId,$groupId);
    } elseif ($id>0 && bridges_table_has_column($link,$table,'id')) {
      $st=$link->prepare("UPDATE {$table} SET is_active=0 WHERE id=?");
      if(!$st)return false; $st->bind_param("i",$id);
    } else return false;
    $ok=$st->execute();$st->close();return (bool)$ok;
  }
}

if (!function_exists('bridges_load_state')) {
  function bridges_load_state(mysqli $link, string $T_CHAR_GROUP, string $T_CHAR_CLAN, string $T_CLAN_GROUP, string $excludeChronicles): array {
    $opts_clanes = fetchPairs($link, "SELECT id, name FROM dim_organizations ORDER BY name");
    $opts_manadas = fetchPairs($link, "SELECT id, name FROM dim_groups ORDER BY name");
    $charGroupHasId = bridges_table_has_column($link,$T_CHAR_GROUP,'id');
    $charClanHasId = bridges_table_has_column($link,$T_CHAR_CLAN,'id');
    $clanGroupHasId = bridges_table_has_column($link,$T_CLAN_GROUP,'id');
    $cronicaNotInSQL = ($excludeChronicles !== '') ? " AND p.chronicle_id NOT IN ($excludeChronicles) " : "";

    $chars=[];
    $sqlChars="SELECT p.id,p.name,p.image_url,p.gender,COALESCE(dcs.label,'') AS status,p.status_id,
      ".($charGroupHasId?"cg.id":"NULL")." AS char_group_bridge_id,
      cg.group_id AS active_group_id,m.name AS active_group_name,
      ".($charClanHasId?"cc.id":"NULL")." AS char_clan_bridge_id,
      cc.organization_id AS active_clan_id,c.name AS active_clan_name
      FROM fact_characters p
      LEFT JOIN dim_character_status dcs ON dcs.id=p.status_id
      LEFT JOIN {$T_CHAR_GROUP} cg ON cg.character_id=p.id AND (cg.is_active=1 OR cg.is_active IS NULL)
      LEFT JOIN dim_groups m ON m.id=cg.group_id
      LEFT JOIN {$T_CHAR_CLAN} cc ON cc.character_id=p.id AND (cc.is_active=1 OR cc.is_active IS NULL)
      LEFT JOIN dim_organizations c ON c.id=cc.organization_id
      WHERE 1=1 {$cronicaNotInSQL}
      ORDER BY p.name ASC";
    if($rs=$link->query($sqlChars)){while($row=$rs->fetch_assoc())$chars[]=$row;$rs->close();}

    $clanGroups=[];
    $sqlCG="SELECT ".($clanGroupHasId?"b.id":"NULL")." AS id,
      b.organization_id,c.name AS clan_name,b.group_id,m.name AS group_name,COALESCE(b.is_active,0) AS is_active
      FROM {$T_CLAN_GROUP} b
      LEFT JOIN dim_organizations c ON c.id=b.organization_id
      LEFT JOIN dim_groups m ON m.id=b.group_id
      ORDER BY c.name ASC,m.name ASC,b.organization_id DESC,b.group_id DESC";
    if($rs=$link->query($sqlCG)){while($row=$rs->fetch_assoc())$clanGroups[]=$row;$rs->close();}

    return compact('opts_clanes','opts_manadas','charGroupHasId','charClanHasId','clanGroupHasId','chars','clanGroups');
  }
}
