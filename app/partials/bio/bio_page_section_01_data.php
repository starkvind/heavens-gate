<?php
// Aseguramos que $link ya esté definido y sea una conexión válida de mysqli.

// Función para obtener el nombre y otros detalles basados en el ID
function getSingleRecord($link, $table, $id, $fields = ['name']) {
    $fieldList = implode(', ', $fields);
    $stmt = $link->prepare("SELECT $fieldList FROM $table WHERE id = ? LIMIT 1");
    $stmt->bind_param('s', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Función para crear enlaces seguros
function createLink($href, $text, $target = '_blank', $title = '') {
    $titleAttr = $title ? "title='$title'" : '';
    return "<a href='$href' target='$target' $titleAttr>$text</a>";
}

// JUGADOR
$idJugador = $bioPlayer;
if ($idJugador != "PNJ") {
    $resultCheckNPla = getSingleRecord($link, 'dim_players', $idJugador);
    $finalPlayer = ($resultCheckNPla['name']);
    $namePlayerOfChara = $finalPlayer; #createLink("/players/$idJugador", $finalPlayer);
} else {
    $namePlayerOfChara = htmlspecialchars($bioPlayer);
}

// CRONICA
$idCronica = $bioChronic;
$resultCronica = getSingleRecord($link, 'dim_chronicles', $idCronica, ['name', 'description']);
if ($resultCronica) {
    $nameCronica = ($resultCronica['name']);
    $descCronica = htmlspecialchars($resultCronica['description']);
    $nameCronicaFinal = $nameCronica; #createLink('#', $nameCronica, '', $descCronica);
} else {
    $nameCronicaFinal = htmlspecialchars($bioChronic);
}

// RAZA
$idRace = $bioRace;
$resultRace = getSingleRecord($link, 'dim_breeds', $idRace);
if ($resultRace) {
    $nameRaceFinal = htmlspecialchars($resultRace['name']);
    $raceLink = $nameRaceFinal; #createLink("?p=versist&amp;tc=1&amp;b=$idRace", $nameRaceFinal);
} else {
    $raceLink = htmlspecialchars($idRace);
}

// AUSPICIO
$idAuspice = $bioAuspice;
$resultAuspice = getSingleRecord($link, 'dim_auspices', $idAuspice);
if ($resultAuspice) {
    $nameAuspiceFinal = htmlspecialchars($resultAuspice['name']);
    $auspiceLink = $nameAuspiceFinal; #createLink("?p=versist&amp;tc=2&amp;b=$idAuspice", $nameAuspiceFinal);
} else {
    $auspiceLink = htmlspecialchars($idAuspice);
}

// TRIBU
$idTribe = $bioTribe;
$resultTribe = getSingleRecord($link, 'dim_tribes', $idTribe);
if ($resultTribe) {
    $nameTribeFinal = htmlspecialchars($resultTribe['name']);
    $tribeLink = $nameTribeFinal; #createLink("?p=versist&amp;tc=3&amp;b=$idTribe", $nameTribeFinal);
} else {
    $tribeLink = htmlspecialchars($idTribe);
}

/* Cambio septiembre 2025 */

/* $characterId = id del personaje (int) */
$characterId = isset($characterId) ? (int)$characterId : (int)($_GET['b'] ?? 0);

$bioPack = 0;  // dim_groups.id
$bioClan = 0;  // dim_organizations.id

/* 1) PACK activo (bridge personaje⇄manada) */
$sql = "
  SELECT cgb.group_id
  FROM bridge_characters_groups AS cgb
  WHERE cgb.character_id = ? AND cgb.is_active = 1
  ORDER BY cgb.id DESC
  LIMIT 1
";
if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, 'i', $characterId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $packId);
    if (mysqli_stmt_fetch($stmt)) { $bioPack = (int)$packId; }
    mysqli_stmt_close($stmt);
}

/* Fallback pack legacy */
if ($bioPack === 0) {
    $res = mysqli_query($link, "SELECT manada FROM fact_characters WHERE id = {$characterId} LIMIT 1");
    if ($res && ($row = mysqli_fetch_assoc($res))) $bioPack = (int)$row['manada'];
}

/* 2) CLAN: prioridad por pack→clan, si no hay pack mirar vínculo directo personaje→clan */
if ($bioPack > 0) {
    // clan vía manada activa
    $sql = "
      SELECT cgb2.clan_id
      FROM bridge_organizations_groups AS cgb2
      WHERE cgb2.group_id = ? AND cgb2.is_active = 1
      ORDER BY cgb2.id DESC
      LIMIT 1
    ";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, 'i', $bioPack);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $clanId);
        if (mysqli_stmt_fetch($stmt)) { $bioClan = (int)$clanId; }
        mysqli_stmt_close($stmt);
    }
}

if ($bioClan === 0) {
    // clan directo (personaje sin manada)
    $sql = "
      SELECT h.clan_id
      FROM bridge_characters_organizations h
      WHERE h.character_id = ? AND h.is_active = 1
      ORDER BY h.id DESC
      LIMIT 1
    ";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, 'i', $characterId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $clanId);
        if (mysqli_stmt_fetch($stmt)) { $bioClan = (int)$clanId; }
        mysqli_stmt_close($stmt);
    }
}

/* Fallbacks de legado */
if ($bioClan === 0 && $bioPack > 0) {
    // pack→clan por nombre (solo mientras conviva nm.clan texto)
    $sql = "
      SELECT c.id
      FROM dim_organizations c
      JOIN dim_groups m ON m.clan = c.name
      WHERE m.id = ? LIMIT 1
    ";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, 'i', $bioPack);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $clanId2);
        if (mysqli_stmt_fetch($stmt)) { $bioClan = (int)$clanId2; }
        mysqli_stmt_close($stmt);
    }
}
if ($bioClan === 0) {
    $res = mysqli_query($link, "SELECT clan FROM fact_characters WHERE id = {$characterId} LIMIT 1");
    if ($res && ($row = mysqli_fetch_assoc($res))) $bioClan = (int)$row['clan'];
}

/* Enlaces finales como ya tenías */
$idPack = $bioPack;
$resultPack = $idPack ? getSingleRecord($link, 'dim_groups', $idPack) : null;
$packLink = $resultPack
    ? createLink(pretty_url($link, 'dim_groups', '/groups', (int)$idPack), htmlspecialchars($resultPack['name']))
    : htmlspecialchars($idPack);

$idClan = $bioClan;
$resultClan = $idClan ? getSingleRecord($link, 'dim_organizations', $idClan) : null;
$clanLink = $resultClan
    ? createLink(pretty_url($link, 'dim_organizations', '/organizations', (int)$idClan), htmlspecialchars($resultClan['name']))
    : htmlspecialchars($idClan);
$nameClanFinal = $resultClan ? htmlspecialchars($resultClan['name']) : '';
	
/*
// MANADA
$idPack = $bioPack;
$resultPack = getSingleRecord($link, 'dim_groups', $idPack);
if ($resultPack) {
    $namePackFinal = htmlspecialchars($resultPack['name']);
    $packLink = createLink("/groups/$idPack", $namePackFinal);
} else {
    $packLink = htmlspecialchars($idPack);
}

// CLAN
$idClan = $bioClan;
$resultClan = getSingleRecord($link, 'dim_organizations', $idClan);
if ($resultClan) {
    $nameClanFinal = htmlspecialchars($resultClan['name']);
    $clanLink = createLink("/organizations/$idClan", $nameClanFinal);
} else {
    $clanLink = htmlspecialchars($idClan);
}
*/

// TIPO
$idTipo = $bioType;
$resultTipo = getSingleRecord($link, 'dim_character_types', $idTipo, ['kind']);
$nameTipo = $resultTipo ? htmlspecialchars($resultTipo['kind']) : '';

// NATURALEZA
$idNature = $bioNature;
$resultNature = getSingleRecord($link, 'dim_archetypes', $idNature);
if ($resultNature) {
    $nameNatureFinal = htmlspecialchars($resultNature['name']);
    $natureLink = createLink(pretty_url($link, 'dim_archetypes', '/rules/archetypes', (int)$idNature), $nameNatureFinal);
} else {
    $natureLink = htmlspecialchars($idNature ? $idNature : 'Sin especificar');
}

// CONDUCTA
$idDemeanor = $bioBehavior;
$resultDemeanor = getSingleRecord($link, 'dim_archetypes', $idDemeanor);
if ($resultDemeanor) {
    $nameDemeanorFinal = htmlspecialchars($resultDemeanor['name']);
    $demeanorLink = createLink(pretty_url($link, 'dim_archetypes', '/rules/archetypes', (int)$idDemeanor), $nameDemeanorFinal);
} else {
    $demeanorLink = htmlspecialchars($idDemeanor ? $idDemeanor : 'Sin especificar');
}

// BIOGRAFIAS SIMILARES
$stmt = $link->prepare("SELECT id, name FROM fact_characters WHERE name LIKE ? AND id != ? LIMIT 10");
$stmt->bind_param('ss', $bioName, $characterId);
$stmt->execute();
$resultSameBio = $stmt->get_result();
while ($row = $resultSameBio->fetch_assoc()) {
    $sameBioId[] = htmlspecialchars($row['id']);
    $sameBioName[] = htmlspecialchars($row['name']);
}

// ASESINATOS
$stmt = $link->prepare("SELECT id, name FROM fact_characters WHERE cause_of_death LIKE ?");
$stmt->bind_param('s', $bioName);
$stmt->execute();
$resultKills = $stmt->get_result();
while ($row = $resultKills->fetch_assoc()) {
    $killsId[] = htmlspecialchars($row['id']);
    $killsName[] = htmlspecialchars($row['name']);
}

// Cálculo de círculos de habilidad, atributos, etc.
if (!function_exists('createSkillCircle')) {
    function createSkillCircle($array, $prefix) {
        $result = [];
        foreach ($array as $value) {
            $baseDir = ($prefix === 'gem-pwr') ? 'img/ui/gems/pwr' : 'img/ui/gems/attr';
            $result[] = "<img class='bioAttCircle' src='{$baseDir}/{$prefix}-0$value.png'/>";
        }
        return $result;
    }
}

if (isset($bioArrayPow)) $bioPowrImg = createSkillCircle($bioArrayPow, 'gem-pwr');
if (isset($bioArrayAtt)) $bioAttrImg = createSkillCircle($bioArrayAtt, 'gem-attr');
if (isset($bioArraySki)) $bioSkilImg = createSkillCircle($bioArraySki, 'gem-attr');

?>
