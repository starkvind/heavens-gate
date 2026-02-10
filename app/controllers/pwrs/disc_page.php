<?php
// Obtener y sanitizar el parámetro 'b'
$donPageID = isset($_GET['b']) ? $_GET['b'] : ''; 

// Consulta segura para obtener los datos de la disciplina
$queryDon = "SELECT * FROM fact_discipline_powers WHERE id = ? LIMIT 1";
$stmt = $link->prepare($queryDon);
$stmt->bind_param('s', $donPageID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) { // Si encontramos la disciplina en la base de datos
    $resultQueryDon = $result->fetch_assoc();

    // DATOS BÁSICOS
    $donId      = htmlspecialchars($resultQueryDon["id"]);
    $donName    = htmlspecialchars($resultQueryDon["name"]);
    $donType    = htmlspecialchars($resultQueryDon["disc"]);
    $donRank    = htmlspecialchars($resultQueryDon["nivel"]);
    $donAttr    = htmlspecialchars($resultQueryDon["atributo"]);
    $donSkill   = htmlspecialchars($resultQueryDon["habilidad"]);
    $donDesc    = $resultQueryDon["descripcion"]; // NO usar htmlspecialchars() para mantener el formato HTML
    $donSystem  = $resultQueryDon["sistema"];
    $donOrigin  = htmlspecialchars($resultQueryDon["origen"]);

    // Verificar si 'icono' existe en la base de datos
    $donImg = isset($resultQueryDon["icono"]) ? htmlspecialchars($resultQueryDon["icono"]) : "inv/no-photo.gif";

    // Ruta completa de la imagen del Don
    $itemImg = "img/$donImg";

    // Obtener el nombre del origen de la disciplina
    $queryOrigen = "SELECT name FROM dim_bibliographies WHERE id = ? LIMIT 1";
    $stmt = $link->prepare($queryOrigen);
    $stmt->bind_param('s', $donOrigin);
    $stmt->execute();
    $result = $stmt->get_result();
    $donOriginName = "-"; // Valor por defecto

    if ($rowOrigen = $result->fetch_assoc()) {
        $donOriginName = htmlspecialchars($rowOrigen["name"]);
    }

    // Obtener el nombre de la Disciplina
    $queryTipo = "SELECT name FROM dim_discipline_types WHERE id = ? LIMIT 1";
    $stmt = $link->prepare($queryTipo);
    $stmt->bind_param('s', $donType);
    $stmt->execute();
    $result = $stmt->get_result();
    $nombreTipo = "-"; // Valor por defecto

    if ($rowTipo = $result->fetch_assoc()) {
        $nombreTipo = htmlspecialchars($rowTipo["name"]);
    }

    // Guardar en sesión para breadcrumbs
    $_SESSION['punk2'] = $nombreTipo;
	
	$pageSect = "Disciplinas"; // PARA CAMBIAR EL TITULO A LA PAGINA
	$pageTitle2 = $donName; // PARA CAMBIAR EL TITULO A LA PAGINA
	setMetaFromPage($donName . " | Disciplinas | Heaven's Gate", meta_excerpt($donDesc), null, 'article');

    // Incluir barra de navegación
    include("app/partials/main_nav_bar.php");

    // Título de la página
    echo "<h2>$donName</h2>";

    echo "<fieldset class='renglonPaginaDon'>";

    echo "<div class='itemSquarePhoto' style='padding-left:4px;'>";
    echo "<img class='photobio' style='width:100px;height:100px;' src='$itemImg' alt='$donName'/>";
    echo "</div>";

    // Datos generales del Don
    echo "<div class='bioSquareData'>";

    // Nivel de la Disciplina
    if ($donRank > 0) {
        echo "<div class='bioRenglonData'>";
        echo "<div class='bioDataName'>Nivel:</div>";
        echo "<div class='bioDataText'><img class='bioAttCircle' src='img/ui/gems/pwr/gem-pwr-0$donRank.png'/></div>";
        echo "</div>";
    }

    // Nombre de la Disciplina
    echo "<div class='bioRenglonData'>";
    echo "<div class='bioDataName'>Disciplina:</div>";
    echo "<div class='bioDataText'>$nombreTipo</div>";
    echo "</div>";

    // Tirada de la Disciplina
    if (!empty($donAttr)) {
        $tiradaDon2 = !empty($donSkill) ? "$donAttr + $donSkill" : $donAttr;
        echo "<div class='bioRenglonData'>";
        echo "<div class='bioDataName'>Tirada:</div>";
        echo "<div class='bioDataText'>$tiradaDon2</div>";
        echo "</div>";
    }

    // Origen de la Disciplina
    if (!empty($donOriginName)) {
        echo "<div class='bioRenglonData'>";
        echo "<div class='bioDataName'>Origen:</div>";
        echo "<div class='bioDataText'>$donOriginName</div>";
        echo "</div>";
    }

    echo "</div>";

    // Descripción de la Disciplina (permitiendo etiquetas HTML)
    if (!empty($donDesc)) {
        echo "<div class='renglonDonData'>";
        echo "<b>Descripción:</b><p>$donDesc</p>";
        echo "</div>";
    }

    // Sistema de la Disciplina
    if (!empty($donSystem)) {
        echo "<div class='renglonDonData'>";
        echo "<b>Sistema:</b><p>$donSystem</p>";
        echo "</div>";
    }

    echo "</fieldset>";

} else {
    echo "<p>Error: Disciplina no encontrada.</p>";
}
?>
