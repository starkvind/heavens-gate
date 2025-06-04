<?php
// Verificar la conexión a la base de datos
if (!$link) {
    die("Error de conexión a la base de datos: " . mysqli_connect_error());
}

// Sanitizar y obtener el parámetro 'status' de la URL
$punk = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_STRING);

$queryOperator = "LIKE";
switch($punk) {
    default:
        $bioStatus 	= "En activo";
        $bioTitle	= "Personajes con vida";
        $searchFact	= "estado";
        break;
    case "dead":
        $bioStatus 	= "Cadáver";
        $bioTitle	= "Personajes muertos";
        $searchFact	= "estado";
        break;		
    case "unknown":
        $bioStatus	= "Paradero desconocido";
        $bioTitle	= "Personajes desaparecidos";
        $searchFact	= "estado";
        break;	
    case "next":
        $bioStatus 	= "Aún por aparecer";
        $bioTitle	= "Próximos personajes";
        $searchFact	= "estado";
        break;
    /* == */
    case "pb":
        $bioStatus 	= 1;
        $bioTitle	= "Personajes del Peñasco Blanco";
        $searchFact	= "clan";
        break;
    case "va":
        $bioStatus 	= 2;
        $bioTitle	= "Personajes del Viento de Acero";
        $searchFact	= "clan";
        break;	
    case "jm":
        $bioStatus 	= 3;
        $bioTitle	= "Personajes de la Justicia Metálica";
        $searchFact	= "clan";
        break;
    case "in":
        $bioStatus 	= 15;
        $bioTitle	= "Personajes independientes";
        $searchFact	= "clan";
        break;
    case "pc":
        $bioStatus 	= "pj";
        $bioTitle	= "Personajes con ficha";
        $searchFact	= "kes";
        break;
    case "forget":
        $bioStatus 	= 1;
        $bioTitle	= "Personajes abandonados";
        $searchFact	= "abandonado";
        break;
    case "hg":
        $bioStatus 	= 1;
        $bioTitle	= "Personajes de la partida Heaven's Gate";
        $searchFact	= "cronica";
        break;
    case "jv":
        $bioStatus 	= 2;
        $bioTitle	= "Personajes de la partida de Javi";
        $searchFact	= "cronica";
        break;
    case "gt":
        $bioStatus 	= 3;
        $bioTitle	= "Personajes de la partida Lobo GT";
        $searchFact	= "cronica";
        break;
    case "ot_ch":
        $queryOperator = ">";
        $bioStatus 	= 3;
        $bioTitle	= "Personajes de otras partidas";
        $searchFact	= "cronica";
        break;
}

$pageSect = "Listas organizadas"; // PARA CAMBIAR EL TITULO A LA PAGINA

// Incluir navegación principal y encabezado de la página
include("sep/main/main_nav_bar.php");
echo "<h2>Listas organizadas</h2>";

// Mostrar lista de filtros de personajes
print("<fieldset class='grupoSelBioList'>");
echo "<a href='?p=list_by_order'><div class='renglonSelBioList'>En activo</div></a>";
echo "<a href='?p=list_by_order&amp;status=dead'><div class='renglonSelBioList'>Cadáver</div></a>";
echo "<a href='?p=list_by_order&amp;status=unknown'><div class='renglonSelBioList'>Paradero desconocido</div></a>";
echo "<a href='?p=list_by_order&amp;status=next'><div class='renglonSelBioList'>Aún por aparecer</div></a>";
echo "<a href='?p=list_by_order&amp;status=pb'><div class='renglonSelBioList'>Peñasco Blanco</div></a>";
echo "<a href='?p=list_by_order&amp;status=va'><div class='renglonSelBioList'>Viento de Acero</div></a>";
echo "<a href='?p=list_by_order&amp;status=jm'><div class='renglonSelBioList'>Justicia Metálica</div></a>";
echo "<a href='?p=list_by_order&amp;status=in'><div class='renglonSelBioList'>Independientes</div></a>";
echo "<a href='?p=list_by_order&amp;status=pc'><div class='renglonSelBioList'>PJs con Ficha</div></a>";
echo "<a href='?p=list_by_order&amp;status=forget'><div class='renglonSelBioList'>PJs abandonados</div></a>";
echo "<a href='?p=list_by_order&amp;status=hg'><div class='renglonSelBioList'>Heaven's Gate</div></a>";
echo "<a href='?p=list_by_order&amp;status=jv'><div class='renglonSelBioList'>Partida de Javi</div></a>";
echo "<a href='?p=list_by_order&amp;status=gt'><div class='renglonSelBioList'>Lobo GT</div></a>";
echo "<a href='?p=list_by_order&amp;status=ot_ch'><div class='renglonSelBioList'>Otros</div></a>";
print("</fieldset>");

// Consulta para obtener personajes basados en los filtros seleccionados
$consulta = "SELECT * FROM pjs1 WHERE $searchFact $queryOperator ? ORDER BY nombre";
$stmt = mysqli_prepare($link, $consulta);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 's', $bioStatus);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    print("<fieldset class='grupoHabilidad'>"); # Inicio Fieldset
    $numeroReg = 0;
    while ($ResultQuery = mysqli_fetch_assoc($result)) {
        $clasePJ = $ResultQuery["kes"];
        $rutaIconoPJ = ($clasePJ == "pj") ? "img/kek.gif" : "img/kek2.gif";

        $estadoPJ = $ResultQuery["estado"];
        switch ($estadoPJ) {
            case "Aún por aparecer":
                $simboloEstado = "&#64;";
                break;
            case "Paradero desconocido":
                $simboloEstado = "&#63;";
                break;
            case "Cadáver":
                $simboloEstado = "&#8224;";
                break;
            default:
                $simboloEstado = "";
                break;
        }
        
        print("
            <a href='index.php?p=muestrabio&amp;b=" . htmlspecialchars($ResultQuery["id"]) . "' title='" . htmlspecialchars($estadoPJ) . "'>
                <div class='renglon2col'>
                    <div class='renglon2colIz'>
                        <img class='valign' src='" . htmlspecialchars($rutaIconoPJ) . "'> " . htmlspecialchars($ResultQuery["nombre"]) . "
                    </div>
                    <div class='renglon2colDe'>$simboloEstado</div>
                </div>
            </a>
        ");
        $numeroReg++;
    }
    print("</fieldset><br/>"); # Fin Fieldset
    print("<p align='right'>$bioTitle: $numeroReg</p>");

    mysqli_free_result($result);
    mysqli_stmt_close($stmt);
} else {
    echo "Error al preparar la consulta: " . mysqli_error($link);
}
?>
