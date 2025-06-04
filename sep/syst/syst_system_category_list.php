<?php

// Aseguramos que el parámetro GET 'b' esté definido de manera segura
$systemCategory = isset($_GET['b']) ? $_GET['b'] : '';

// =========================================================== >
// Preparamos la consulta para evitar inyecciones SQL
$ordenQuery = "SELECT name, img, descripcion, formas FROM nuevo_sistema WHERE id = ? LIMIT 1";
$stmt = $link->prepare($ordenQuery);
$stmt->bind_param('s', $systemCategory);
$stmt->execute();
$result = $stmt->get_result();
$ordenQueryResult = $result->fetch_assoc();

$systemName = htmlspecialchars($ordenQueryResult["name"]);
$systemImg = htmlspecialchars($ordenQueryResult["img"]);
$systemDesc = ($ordenQueryResult["descripcion"]);
$systemForm = (int)$ordenQueryResult["formas"];

// CAMBIAR EL TITULO A LA PAGINA
if (!empty($systemName)) { 
    $pageSect = "Sistema"; 
    $pageTitle2 = $systemName; 
}

// PONER IMAGEN "NADA" SI NO TIENE IMAGEN ASIGNADA
if (empty($systemImg)) {
    $systemImg = "img/system/nada.jpg";
}

// =========================================================== >
include("sep/main/main_nav_bar.php"); // Barra Navegación
echo "<h2>$systemName</h2>";
include("sep/main/main_social_menu.php"); // Zona de Impresión y Redes Sociales
echo "<div class='renglonImagenSistema'><img class='imagenSistema' src='$systemImg' /></div>";
echo "<div class='renglonDatosSistema'>$systemDesc</div>";

// =========================================================== >
switch ($systemName) {
    case "Bastet":
        $nameAuspice = "Pryios";
        $nameTribe = "Tribus";
        break;
    case "Ananasi":
        $nameAuspice = "Facciones";
        $nameTribe = "Aspectos";
        break;
    case "Mokole":
        $nameAuspice = "Auspicios";
        $nameTribe = "Oleadas";
        $nameMisc = "Varnas";
        break;
    case "Changelling":
        $nameAuspice = "Aspectos";
        $nameTribe = "Linajes";
        $nameMisc = "Legados";
        break;
    case "Vampiro":
        $nameTribe = "Clanes";
        break;
    default:
        $nameAuspice = "Auspicios";
        $nameTribe = "Tribus";
        $nameMisc = "";
        break;
}

echo "<div class='renglonContenidoSistema'>";

// CUADRO DE FORMAS
if ($systemForm === 1) {
    $queryForms = "SELECT * FROM nuevo_formas WHERE afiliacion = ?";
    $stmtForms = $link->prepare($queryForms);
    $stmtForms->bind_param('s', $systemName);
    $stmtForms->execute();
    $resultForms = $stmtForms->get_result();
    $nRowsQueryForms = $resultForms->num_rows;

    if ($nRowsQueryForms > 0) {
        echo "<fieldset class='renglonContenidoSist'>";
        echo "<legend><b>Formas</b></legend>";

        while ($formQueryResult = $resultForms->fetch_assoc()) {
            $formId = htmlspecialchars($formQueryResult["id"]);
            $formAff = htmlspecialchars($formQueryResult["raza"]);
            $formName = htmlspecialchars($formQueryResult["forma"]);

            if ($systemName === "Bastet") {
                $formName = "$formName ($formAff)";
            }

            echo "<a href='index.php?p=verforma&amp;b=$formId'><div class='renglonSistema'>$formName</div></a>";
        }
        echo "</fieldset>";
    }

    $stmtForms->close();
}

// =========================================================== >
// CUADRO DE RAZAS
$queryRaces = "SELECT * FROM nuevo_razas WHERE sistema = ? ORDER BY id";
$stmtRaces = $link->prepare($queryRaces);
$stmtRaces->bind_param('s', $systemName);
$stmtRaces->execute();
$resultRaces = $stmtRaces->get_result();
$nRowsQueryRaces = $resultRaces->num_rows;

if ($nRowsQueryRaces > 0) {
    echo "<fieldset class='renglonContenidoSist'>";
    echo "<legend><b>Razas</b></legend>";
    while ($raceQueryResult = $resultRaces->fetch_assoc()) {
        $raceId = htmlspecialchars($raceQueryResult["id"]);
        $raceName = htmlspecialchars($raceQueryResult["name"]);
        echo "<a href='index.php?p=versist&amp;tc=1&amp;b=$raceId'><div class='renglonSistema'>$raceName</div></a>";
    }
    echo "</fieldset>";
}

$stmtRaces->close();

// =========================================================== >
// CUADRO DE AUSPICIOS
$queryAuspices = "SELECT * FROM nuevo_auspicios WHERE sistema = ? ORDER BY id";
$stmtAuspices = $link->prepare($queryAuspices);
$stmtAuspices->bind_param('s', $systemName);
$stmtAuspices->execute();
$resultAuspices = $stmtAuspices->get_result();
$nRowsQueryAuspices = $resultAuspices->num_rows;

if ($nRowsQueryAuspices > 0) {
    echo "<fieldset class='renglonContenidoSist'>";
    echo "<legend><b>$nameAuspice</b></legend>";
    while ($auspiceQueryResult = $resultAuspices->fetch_assoc()) {
        $auspiceId = htmlspecialchars($auspiceQueryResult["id"]);
        $auspiceName = htmlspecialchars($auspiceQueryResult["name"]);
        echo "<a href='index.php?p=versist&amp;tc=2&amp;b=$auspiceId'><div class='renglonSistema'>$auspiceName</div></a>";
    }
    echo "</fieldset>";
}

$stmtAuspices->close();

// =========================================================== >
// CUADRO DE TRIBUS
$queryTribes = "SELECT * FROM nuevo_tribus WHERE sistema = ? ORDER BY id";
$stmtTribes = $link->prepare($queryTribes);
$stmtTribes->bind_param('s', $systemName);
$stmtTribes->execute();
$resultTribes = $stmtTribes->get_result();
$nRowsQueryTribes = $resultTribes->num_rows;

if ($nRowsQueryTribes > 0) {
    echo "<fieldset class='renglonContenidoSist'>";
    echo "<legend><b>$nameTribe</b></legend>";
    while ($tribeQueryResult = $resultTribes->fetch_assoc()) {
        $tribeId = htmlspecialchars($tribeQueryResult["id"]);
        $tribeName = htmlspecialchars($tribeQueryResult["name"]);
        echo "<a href='index.php?p=versist&amp;tc=3&amp;b=$tribeId'><div class='renglonSistema'>$tribeName</div></a>";
    }
    echo "</fieldset>";
}

$stmtTribes->close();

// =========================================================== >
// CUADRO MISCELÁNEA
$queryMisc = "SELECT id, name, type FROM nuevo_miscsistemas WHERE sistema = ? ORDER BY id";
$stmtMisc = $link->prepare($queryMisc);
$stmtMisc->bind_param('s', $systemName);
$stmtMisc->execute();
$resultMisc = $stmtMisc->get_result();
$nRowsQueryMisc = $resultMisc->num_rows;

if ($nRowsQueryMisc > 0) {
    echo "<fieldset class='renglonContenidoSist'>";
    echo "<legend><b>$nameMisc</b></legend>";
    while ($miscQueryResult = $resultMisc->fetch_assoc()) {
        $miscId = htmlspecialchars($miscQueryResult["id"]);
        $miscName = htmlspecialchars($miscQueryResult["name"]);
        echo "<a href='index.php?p=versist&amp;tc=4&amp;b=$miscId'><div class='renglonSistema'>$miscName</div></a>";
    }
    echo "</fieldset>";
}

$stmtMisc->close();

// =========================================================== >
echo "</div>";
?>
