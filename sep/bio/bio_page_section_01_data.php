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
    $resultCheckNPla = getSingleRecord($link, 'nuevo_jugadores', $idJugador);
    $finalPlayer = ($resultCheckNPla['name']);
    $namePlayerOfChara = createLink("index.php?p=seeplayer&amp;b=$idJugador", $finalPlayer);
} else {
    $namePlayerOfChara = htmlspecialchars($bioPlayer);
}

// CRONICA
$idCronica = $bioChronic;
$resultCronica = getSingleRecord($link, 'nuevo2_cronicas', $idCronica, ['name', 'descripcion']);
if ($resultCronica) {
    $nameCronica = ($resultCronica['name']);
    $descCronica = htmlspecialchars($resultCronica['descripcion']);
    $nameCronicaFinal = createLink('#', $nameCronica, '', $descCronica);
} else {
    $nameCronicaFinal = htmlspecialchars($bioChronic);
}

// RAZA
$idRace = $bioRace;
$resultRace = getSingleRecord($link, 'nuevo_razas', $idRace);
if ($resultRace) {
    $nameRaceFinal = htmlspecialchars($resultRace['name']);
    $raceLink = createLink("index.php?p=versist&amp;tc=1&amp;b=$idRace", $nameRaceFinal);
} else {
    $raceLink = htmlspecialchars($idRace);
}

// AUSPICIO
$idAuspice = $bioAuspice;
$resultAuspice = getSingleRecord($link, 'nuevo_auspicios', $idAuspice);
if ($resultAuspice) {
    $nameAuspiceFinal = htmlspecialchars($resultAuspice['name']);
    $auspiceLink = createLink("index.php?p=versist&amp;tc=2&amp;b=$idAuspice", $nameAuspiceFinal);
} else {
    $auspiceLink = htmlspecialchars($idAuspice);
}

// TRIBU
$idTribe = $bioTribe;
$resultTribe = getSingleRecord($link, 'nuevo_tribus', $idTribe);
if ($resultTribe) {
    $nameTribeFinal = htmlspecialchars($resultTribe['name']);
    $tribeLink = createLink("index.php?p=versist&amp;tc=3&amp;b=$idTribe", $nameTribeFinal);
} else {
    $tribeLink = htmlspecialchars($idTribe);
}

// MANADA
$idPack = $bioPack;
$resultPack = getSingleRecord($link, 'nuevo2_manadas', $idPack);
if ($resultPack) {
    $namePackFinal = htmlspecialchars($resultPack['name']);
    $packLink = createLink("index.php?p=seegroup&amp;t=1&amp;b=$idPack", $namePackFinal);
} else {
    $packLink = htmlspecialchars($idPack);
}

// CLAN
$idClan = $bioClan;
$resultClan = getSingleRecord($link, 'nuevo2_clanes', $idClan);
if ($resultClan) {
    $nameClanFinal = htmlspecialchars($resultClan['name']);
    $clanLink = createLink("index.php?p=seegroup&amp;t=2&amp;b=$idClan", $nameClanFinal);
} else {
    $clanLink = htmlspecialchars($idClan);
}

// TIPO
$idTipo = $bioType;
$resultTipo = getSingleRecord($link, 'afiliacion', $idTipo, ['tipo']);
$nameTipo = $resultTipo ? htmlspecialchars($resultTipo['tipo']) : '';

// NATURALEZA
$idNature = $bioNature;
$resultNature = getSingleRecord($link, 'nuevo_personalidad', $idNature);
if ($resultNature) {
    $nameNatureFinal = htmlspecialchars($resultNature['name']);
    $natureLink = createLink("index.php?p=verarch&amp;b=$idNature", $nameNatureFinal);
} else {
    $natureLink = htmlspecialchars($idNature ? $idNature : 'Sin especificar');
}

// CONDUCTA
$idDemeanor = $bioBehavior;
$resultDemeanor = getSingleRecord($link, 'nuevo_personalidad', $idDemeanor);
if ($resultDemeanor) {
    $nameDemeanorFinal = htmlspecialchars($resultDemeanor['name']);
    $demeanorLink = createLink("index.php?p=verarch&amp;b=$idDemeanor", $nameDemeanorFinal);
} else {
    $demeanorLink = htmlspecialchars($idDemeanor ? $idDemeanor : 'Sin especificar');
}

// BIOGRAFIAS SIMILARES
$stmt = $link->prepare("SELECT id, nombre FROM pjs1 WHERE nombre LIKE ? AND id != ? LIMIT 10");
$stmt->bind_param('ss', $bioName, $idGetData);
$stmt->execute();
$resultSameBio = $stmt->get_result();
while ($row = $resultSameBio->fetch_assoc()) {
    $sameBioId[] = htmlspecialchars($row['id']);
    $sameBioName[] = htmlspecialchars($row['nombre']);
}

// ASESINATOS
$stmt = $link->prepare("SELECT id, nombre FROM pjs1 WHERE causamuerte LIKE ?");
$stmt->bind_param('s', $bioName);
$stmt->execute();
$resultKills = $stmt->get_result();
while ($row = $resultKills->fetch_assoc()) {
    $killsId[] = htmlspecialchars($row['id']);
    $killsName[] = htmlspecialchars($row['nombre']);
}

// Cálculo de círculos de habilidad, atributos, etc.
function createSkillCircle($array, $prefix) {
    $result = [];
    foreach ($array as $value) {
        $result[] = "<img class='bioAttCircle' src='img/{$prefix}-0$value.png'/>";
    }
    return $result;
}

if (isset($bioArrayPow)) $bioPowrImg = createSkillCircle($bioArrayPow, 'gem-pwr');
if (isset($bioArrayAtt)) $bioAttrImg = createSkillCircle($bioArrayAtt, 'gem-attr');
if (isset($bioArraySki)) $bioSkilImg = createSkillCircle($bioArraySki, 'gem-attr');

?>
