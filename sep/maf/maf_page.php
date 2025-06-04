<?php
// Obtener parámetros 'b' y 'r' de manera segura
$mafPageID = isset($_GET['b']) ? $_GET['b'] : '';  // ID del Mérito Defecto
$returnID = isset($_GET['r']) ? $_GET['r'] : '';  // ID del Regreso

// Preparar la consulta para evitar inyecciones SQL
$queryMaf = "SELECT * FROM nuevo_mer_y_def WHERE id = ? LIMIT 1";

$stmtMaf = $link->prepare($queryMaf);
$stmtMaf->bind_param('s', $mafPageID);
$stmtMaf->execute();
$resultMaf = $stmtMaf->get_result();
$rowsQueryMaf = $resultMaf->num_rows;

// Comprobamos si hay resultados
if ($rowsQueryMaf > 0) {
    $resultQueryMaf = $resultMaf->fetch_assoc();

    // Datos básicos
    $mafId = htmlspecialchars($resultQueryMaf["id"]);
    $mafName = htmlspecialchars($resultQueryMaf["name"]);
    $mafType = htmlspecialchars($resultQueryMaf["tipo"]);
    $mafAfil = htmlspecialchars($resultQueryMaf["afiliacion"]);
    $mafCoste = htmlspecialchars($resultQueryMaf["coste"]);
    $mafDesc = htmlspecialchars($resultQueryMaf["descripcion"]);
    $mafSystem = htmlspecialchars($resultQueryMaf["sistema"]);
    $mafOrigin = htmlspecialchars($resultQueryMaf["origen"]);

	$meritsAndFlawsQuery = "SELECT DISTINCT afiliacion FROM nuevo_mer_y_def ORDER BY afiliacion ASC";

    // Seleccionar origen
    $mafOriginName = $unknownOrigin; // Valor predeterminado si no hay origen
    if ($mafOrigin != 0) {
        $queryOrigen = "SELECT name FROM nuevo2_bibliografia WHERE id = ? LIMIT 1";
        $stmtOrigen = $link->prepare($queryOrigen);
        $stmtOrigen->bind_param('s', $mafOrigin);
        $stmtOrigen->execute();
        $resultOrigen = $stmtOrigen->get_result();
        if ($resultOrigen->num_rows > 0) {
            $resultQueryOrigen = $resultOrigen->fetch_assoc();
            $mafOriginName = htmlspecialchars($resultQueryOrigen["name"]);
        }
        $stmtOrigen->close();
    }

    // Datos para regresar
    switch ($mafType) {
        case "Méritos":
            $returnType = 1;
            $mafNameType = "Mérito";
            break;
        case "Defectos":
            $returnType = 2;
            $mafNameType = "Defecto";
            break;
        default:
            $returnType = 1;
            $mafNameType = "Tipo desconocido";
            break;
    }

    // Crear un array para regresar
    $returnArray = array();
    $returnQuery = $meritsAndFlawsQuery; //"";
    $stmtReturn = $link->prepare($returnQuery);
    $stmtReturn->execute();
    $resultReturn = $stmtReturn->get_result();
	$i = 0;
    while ($returnQueryResult = $resultReturn->fetch_assoc()) {
        #$returnArray[$returnQueryResult["afiliacion"]] = $resultReturn->num_rows + 1;
		$returnArray[$returnQueryResult["afiliacion"]] = $i + 1;
		$i++;
    }
    $stmtReturn->close();

    #$typeReturnId = isset($returnArray[$mafAfil]) ? $returnArray[$mafAfil] : 0;

	#echo "Esto es el $mafAfil , $returnArray[$mafAfil]";

	$typeReturnId = $returnArray[$mafAfil];

    // Título e Imágenes
    $costMeritFlaw = $mafCoste; // Usamos $mafCoste para el coste
    $costeEsFijo = is_numeric($costMeritFlaw);
    $iconoCoste = "img/range-star.gif";
    $pageSect = $mafNameType; // PARA CAMBIAR EL TITULO A LA PAGINA
    $pageTitle2 = $mafName;
    $pointQty = ($costMeritFlaw >= 2) ? "puntos" : "punto"; // Numeración

    // Incluir archivos para navegación y contenido
    include("sep/main/main_nav_bar.php"); // Barra Navegación
    echo "<h2>$mafName</h2>"; // Encabezado de página
    include("sep/main/main_social_menu.php"); // Zona de Impresión y Redes Sociales

    echo "<fieldset class='renglonPaginaDon'>"; // Cuerpo principal de la Ficha del Mérito
    
    $itemImg = "img/inv/no-photo.gif";

    echo "<div class='itemSquarePhoto' style='padding-left:4px;'>"; // Colocamos la Fotografia del Don
    echo "<img class='photobio' style='width:100px;height:100px;' src='$itemImg' alt='$mafName'/>";
    echo "</div>"; // Dejamos la Fotografía ya colocada

    echo "<div class='bioSquareData'>";

    // Coste del Mérito
    echo "<div class='bioRenglonData'>";
    if ($mafCoste > 0) { 
        echo "<div class='bioDataName'>Coste:</div>"; 
        echo "<div class='bioDataText'>";
        for ($nrange = 0; $nrange < $mafCoste; $nrange++) {
            echo "<img src='$iconoCoste' alt='Coste $mafCoste'>";
        }    
        echo "</div>";
    }
    echo "</div>";

    // Tipo del Mérito
    echo "<div class='bioRenglonData'>";
    echo "<div class='bioDataName'>Tipo:</div>";
    echo "<div class='bioDataText'>$mafNameType</div>";
    echo "</div>";

    // Sistema del Mérito
    if ($mafSystem != "") {
        echo "<div class='bioRenglonData'>";
        echo "<div class='bioDataName'>Grupo:</div>";
        echo "<div class='bioDataText'>$mafSystem</div>"; 
        echo "</div>";
    }

    // Orígenes del Mérito
    echo "<div class='bioRenglonData'>";
    echo "<div class='bioDataName'>Origen:</div>";
    echo "<div class='bioDataText'>$mafOriginName</div>"; 
    echo "</div>";

    echo "</div>";

    // Descripción del Mérito
    if ($mafDesc != "") {
        echo "<div class='renglonDonData'>";
        echo "<b>Descripci&oacute;n:</b><p>$mafDesc</p>";
        echo "</div>";
    }
    echo "</fieldset>";
} // Fin comprobación

// Cerramos la sentencia preparada para la consulta principal
$stmtMaf->close();
?>
