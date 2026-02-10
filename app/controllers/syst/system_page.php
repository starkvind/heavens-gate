<?php

// Aseguramos que el parámetro GET 'b' esté definido de manera segura
$systemCategory = isset($_GET['b']) ? $_GET['b'] : '';

// =========================================================== >
// Preparamos la consulta para evitar inyecciones SQL
$ordenQuery = "SELECT name, img, descripcion, formas FROM dim_systems WHERE id = ? LIMIT 1";
$stmt = $link->prepare($ordenQuery);
$stmt->bind_param('s', $systemCategory);
$stmt->execute();
$result = $stmt->get_result();
$ordenQueryResult = $result->fetch_assoc();

if (!$ordenQueryResult) {
    $pageSect = "Sistema";
    $pageTitle2 = "Sistema no encontrado";
    include("app/partials/main_nav_bar.php");
    echo "<h2>Sistema no encontrado</h2>";
    echo "<div class='renglonDatosSistema'>El sistema solicitado no existe.</div>";
} else {
    $systemName = htmlspecialchars($ordenQueryResult["name"]);
    $systemImg = htmlspecialchars($ordenQueryResult["img"]);
    $systemDesc = ($ordenQueryResult["descripcion"]);
    $systemForm = (int)$ordenQueryResult["formas"];

    // CAMBIAR EL TITULO A LA PAGINA
    if (!empty($systemName)) { 
        $pageSect = "Sistema"; 
        $pageTitle2 = $systemName;
		setMetaFromPage($systemName . " | Sistemas | Heaven's Gate", meta_excerpt($systemDesc), $systemImg, 'article'); 
    }

    // PONER IMAGEN "NADA" SI NO TIENE IMAGEN ASIGNADA
    if (empty($systemImg)) {
        $systemImg = "img/system/nada.jpg";
    }

    // =========================================================== >
    include("app/partials/main_nav_bar.php"); // Barra Navegación
    echo "<h2>$systemName</h2>";
    //echo "<div class='renglonImagenSistema'><img class='imagenSistema' src='$systemImg' /></div>";
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
        $queryForms = "SELECT * FROM dim_forms WHERE afiliacion = ?";
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

                echo "<a href='/systems/form/$formId'><div class='renglonSistema'>$formName</div></a>";
            }
            echo "</fieldset>";
        }

        $stmtForms->close();
    }

    // =========================================================== >
    // CUADRO DE RAZAS
    $queryRaces = "SELECT * FROM dim_breeds WHERE sistema = ? ORDER BY id";
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
            $raceHref = pretty_url($link, 'dim_breeds', '/systems/detail/1', (int)$raceId);
            echo "<a href='" . htmlspecialchars($raceHref) . "'><div class='renglonSistema'>$raceName</div></a>";
        }
        echo "</fieldset>";
    }

    $stmtRaces->close();

    // =========================================================== >
    // CUADRO DE AUSPICIOS
    $queryAuspices = "SELECT * FROM dim_auspices WHERE sistema = ? ORDER BY id";
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
            $auspiceHref = pretty_url($link, 'dim_auspices', '/systems/detail/2', (int)$auspiceId);
            echo "<a href='" . htmlspecialchars($auspiceHref) . "'><div class='renglonSistema'>$auspiceName</div></a>";
        }
        echo "</fieldset>";
    }

    $stmtAuspices->close();

    // =========================================================== >
    // CUADRO DE TRIBUS
    $queryTribes = "SELECT * FROM dim_tribes WHERE sistema = ? ORDER BY id";
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
            $tribeHref = pretty_url($link, 'dim_tribes', '/systems/detail/3', (int)$tribeId);
            echo "<a href='" . htmlspecialchars($tribeHref) . "'><div class='renglonSistema'>$tribeName</div></a>";
        }
        echo "</fieldset>";
    }

    $stmtTribes->close();

    // =========================================================== >
    // CUADRO MISCELÁNEA
    $queryMisc = "SELECT id, name, type FROM fact_misc_systems WHERE sistema = ? ORDER BY id";
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
            $miscHref = pretty_url($link, 'fact_misc_systems', '/systems/detail/4', (int)$miscId);
            echo "<a href='" . htmlspecialchars($miscHref) . "'><div class='renglonSistema'>$miscName</div></a>";
        }
        echo "</fieldset>";
    }

    $stmtMisc->close();

    // =========================================================== >
    echo "</div>";
}


?>
