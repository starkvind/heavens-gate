<?php

// Obtener parámetros de manera segura
$systemIdDocument = isset($_GET['b']) ? $_GET['b'] : '';  // ID del contenido
$systemTypeDocument = isset($_GET['tc']) ? $_GET['tc'] : '';  // Tipo de contenido

// Preparar Queries

switch ($systemTypeDocument) {
	case 1:
		$query = "SELECT * FROM dim_breeds WHERE id LIKE '$systemIdDocument' LIMIT 1;";
		$energy = "Gnosis";
		break;
	case 2:
		$query = "SELECT * FROM dim_auspices WHERE id LIKE '$systemIdDocument' LIMIT 1;";
		$energy = "Rabia";
		break;
	case 3:
		$query = "SELECT * FROM dim_tribes WHERE id LIKE '$systemIdDocument' LIMIT 1;";
		$energy = "Fuerza de voluntad";
		break;
	case 4:
		$query = "SELECT * FROM fact_misc_systems WHERE id LIKE '$systemIdDocument' LIMIT 1;";
		break;
	default:
		$query = "Nada";
		break;
}

if ($query != "Nada") {

    $infoDataCheck = 0;

    // Ejecutar la consulta utilizando mysqli y sentencias preparadas
    $stmt = $link->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();
    $NFilas = $result->num_rows;

    while ($ResultQuery = $result->fetch_assoc()) {
        // Datos del Sistema
        $returnType = htmlspecialchars($ResultQuery["sistema"]);
        $typeOfSystem = $returnType;
        $nameSyst = htmlspecialchars($ResultQuery["name"]);
        $infoDesc = ($ResultQuery["desc"]);
        if (isset($ResultQuery["imagen"])) {
			$imageSyst = htmlspecialchars($ResultQuery["imagen"]);
		} else {
			$imageSyst = "";
		}

        $pageSect = $returnType; // PARA CAMBIAR EL TITULO A LA PAGINA
        $pageTitle2 = $nameSyst;
        setMetaFromPage($nameSyst . " | Sistemas | Heaven's Gate", meta_excerpt($infoDesc), $imageSyst, 'article'); 
        
        include("app/helpers/system_category_helper.php");
        include("app/partials/main_nav_bar.php"); // Barra Navegación
        echo "<h2>$nameSyst</h2>";
        echo "<table class='notix'>";

        echo "
            <tr>
            <td class='imgInfoPic'>
                <center>
                    <img class='infoPic' src='$imageSyst' title ='$nameSyst' alt='$nameSyst'/>
                </center>
            </td>
            <td class='imgInfoPic'>
                <fieldset class='systemDonList'>
        ";

        // Comprobar si los datos tienen energía para mostrarla
        if (isset($ResultQuery["energia"])) {
			$checkEnergy = htmlspecialchars($ResultQuery["energia"]);
		} else {
			$checkEnergy = 0;
		}

        if ($returnType == "Mokolé" && $systemTypeDocument == 2) {
            $energy = "Fuerza de Voluntad";
        }

        if ($checkEnergy != 0) {
            $infoDataCheck++;
            echo "<p><b>$energy:</b> $checkEnergy</p>";

        } elseif ($systemTypeDocument == 4) {
            $miscInfoData = ($ResultQuery["miscinfo"]);
            $miscNameEnergy = htmlspecialchars($ResultQuery["energianombre"]);
            $miscValuEnergy = htmlspecialchars($ResultQuery["energiavalor"]);

            if ($miscInfoData != "") { 
                echo "<p>$miscInfoData</p>"; 
                $infoDataCheck++;
            }

            if ($miscNameEnergy != "") { 
                echo "<p><b>$miscNameEnergy:</b> $miscValuEnergy</p>"; 
                $infoDataCheck++;
            }
        }

        // Don Query para obtener dones basados en el sistema
        $donGroup = $nameSyst;
        $donQuery = "SELECT id, nombre, rango FROM fact_gifts WHERE grupo = ? AND ferasistema = ? ORDER BY rango;";
        $stmtDon = $link->prepare($donQuery);
        $stmtDon->bind_param('ss', $donGroup, $typeOfSystem);
        $stmtDon->execute();
        $resultDon = $stmtDon->get_result();
        $filasDon = $resultDon->num_rows;

        if ($filasDon > 0) {
            $infoDataCheck++;
            echo "
                <p><b>Dones disponibles:</b></p>
                <ul style='list-style-type:circle;'>";
            
            while ($resultDonQuery = $resultDon->fetch_assoc()) {
                echo "
                    <li>
                        <a href='" . htmlspecialchars(pretty_url($link, 'fact_gifts', '/powers/gift', (int)$resultDonQuery['id'])) . "' title='" . htmlspecialchars($resultDonQuery['rango']) . "' target='_blank'>
                            " . htmlspecialchars($resultDonQuery['nombre']) . "
                        </a>
                    </li>
                ";
            }

            echo "</ul>";
        }

        if ($infoDataCheck == 0) {
            echo "<p align='center'>No hay datos disponibles.</p>";
        } 

        $stmtDon->close();

        echo "</fieldset></td></tr>";
        print("<tr><td colspan='2' class='texti'>$infoDesc</td></tr>");

        // Mostrar personajes que están en el clan pero que no tienen manada aún
        $charsWithoutPackQuery = "SELECT id, nombre FROM fact_characters WHERE tribu = ? ORDER BY nombre;";
        $stmtChars = $link->prepare($charsWithoutPackQuery);
        $stmtChars->bind_param('s', $systemIdDocument);
        $stmtChars->execute();
        $resultChars = $stmtChars->get_result();
        $charsWithoutPackFilas = $resultChars->num_rows;

        if ($charsWithoutPackFilas > 0 && $systemTypeDocument == 3) {
            $pjCount = 0;
            print("<tr><td colspan='2' class='texti'>");
            print("<fieldset class='grupoBioClan'><legend><b>Miembros</b>:</legend>");
            while ($charsWithoutPackResult = $resultChars->fetch_assoc()) { 
                $charWithoutPackId = htmlspecialchars($charsWithoutPackResult["id"]);
                $charWithoutPackName = htmlspecialchars($charsWithoutPackResult["nombre"]);
                $charHref = pretty_url($link, 'fact_characters', '/characters', (int)$charWithoutPackId);
                echo "<a href='" . htmlspecialchars($charHref) . "' target='_blank'><div class='renglon2col' style='text-align: center;'>$charWithoutPackName</div></a>";
                $pjCount++;
            }
            print("</fieldset>");
            echo "<p style='text-align:right;'>$nameSyst: $pjCount</p>";
            print("</td></tr>");
        }

        $stmtChars->close();

        echo "</table>";
    }

 //   $stmt->close();
}
?>
