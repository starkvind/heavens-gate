<?php

// Obtener el parámetro 'b' de manera segura
$skillId = isset($_GET['b']) ? $_GET['b'] : '';  // ID del contenido

// Preparar la consulta para evitar inyecciones SQL
$query = "SELECT * FROM nuevo_habilidades WHERE id = ?";
$stmt = $link->prepare($query);
$stmt->bind_param('s', $skillId);
$stmt->execute();
$result = $stmt->get_result();

// Comprobamos si hay resultados
if ($result->num_rows > 0) {
    while ($ResultQuery = $result->fetch_assoc()) {
        // Datos de la habilidad
        $nameSkill      = htmlspecialchars($ResultQuery["name"]);
        $infoSkill      = ($ResultQuery["descripcion"]);
        $levlSkill      = ($ResultQuery["levels"]);
        $whoHaveSkill   = htmlspecialchars($ResultQuery["posse"]);
        $specSkill      = ($ResultQuery["special"]);
        $origSkill      = htmlspecialchars($ResultQuery["origen"]);
        $typeSkill      = htmlspecialchars($ResultQuery["tipo"]); // Para regresar
        
        $titleSkill = substr($typeSkill, 0, -1);
        $pageSect = $titleSkill; // PARA CAMBIAR EL TITULO A LA PAGINA
        $pageTitle2 = $nameSkill;

        // ================================================================== //
        // SELECCIONAR ORIGEN
        $skillOriginName = $unknownOrigin; // Valor predeterminado si no hay origen
        if ($origSkill != 0) {
            $queryOrigen = "SELECT name FROM nuevo2_bibliografia WHERE id = ? LIMIT 1;";
            $stmtOrigen = $link->prepare($queryOrigen);
            $stmtOrigen->bind_param('s', $origSkill);
            $stmtOrigen->execute();
            $resultOrigen = $stmtOrigen->get_result();
            if ($resultOrigen->num_rows > 0) {
                $resultQueryOrigen = $resultOrigen->fetch_assoc();
                $skillOriginName = htmlspecialchars($resultQueryOrigen["name"]);
            }
            $stmtOrigen->close();
        }

        // ================================================================== //
        switch ($typeSkill) {
            case "Talentos":
                $returnType = 2;
                break;
            case "Técnicas":
                $returnType = 3;
                break;
            case "Conocimientos":
                $returnType = 4;
                break;
            case "Trasfondos":
                $returnType = 5;
                break;
            default:
                $returnType = 1;
                break;
        }

        // Incluimos archivos necesarios para la navegación y contenido
        include("sep/main/main_nav_bar.php"); // Barra Navegación
        echo "<h2>$nameSkill</h2>"; // Encabezado de Página
        include("sep/main/main_social_menu.php"); // Zona de Impresión y Redes Sociales

        // ================================================================== //
        echo "<fieldset class='renglonPaginaDon'>"; // Cuerpo principal de la Ficha del Don

        // Origen de la Habilidad
        echo "<div class='renglonDonIz'>Origen:</div>"; 
        echo "<div class='renglonDonDe'>$skillOriginName</div>";

        // Descripción de la Habilidad
        if ($infoSkill != "") {
            echo "<div class='renglonDonData'>";
            echo "<b>Descripci&oacute;n:</b><p>$infoSkill</p>";
            echo "</div>";
        }

        // Niveles de la Habilidad
        if ($levlSkill != "") {
            echo "<div class='renglonDonData'>";
            echo "<b>Niveles:</b><p>$levlSkill</p>";
            echo "</div>";
        }

        // Poseído por...
        if ($whoHaveSkill != "") {
            echo "<div class='renglonDonData'>";
            echo "<b>Pose&iacute;do por:</b><p>$whoHaveSkill</p>";
            echo "</div>";
        }

        // Especialidades
        if ($specSkill != "") {
            echo "<div class='renglonDonData'>";
            echo "<b>Especialidades:</b><p>$specSkill</p>";
            echo "</div>";
        }

        echo "</fieldset>";
    }
}

// Cerramos la sentencia preparada para la consulta principal
$stmt->close();

?>
