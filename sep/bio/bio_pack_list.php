<?php
// Verificar la conexión a la base de datos
if (!$link) {
    die("Error de conexión a la base de datos: " . mysqli_connect_error());
}

$nameTypePack = "Grupos y sociedades";
$iconPack = "img/kek.gif"; 
$iconSept = "img/kek2.gif"; 
$pageSect = "Biografías"; // PARA CAMBIAR EL TITULO A LA PAGINA
$pageTitle2 = $nameTypePack;

// ============================================================
include("sep/main/main_nav_bar.php");	// Barra Navegación
echo "<h2>" . htmlspecialchars($nameTypePack) . "</h2>";

// Consultar las organizaciones
$consulta = "SELECT id, name FROM nuevo2_clanes ORDER BY orden";
$result = mysqli_query($link, $consulta);

if ($result) {
    $domoarigato = [];
    $idOfSept = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $domoarigato[] = $row["name"];
        $idOfSept[] = $row["id"];
    }

    mysqli_free_result($result);

    $numeroClanesHallados = count($domoarigato);

    // Empezar a mostrar resultados
    if ($numeroClanesHallados > 0) {
        $domoarigato = array_unique($domoarigato); // Eliminar duplicados
        $numeroClanesHallados = count($domoarigato);

        $numeroDeGruposHallados = 0; // Inicializar el contador de grupos hallados

        foreach ($domoarigato as $index => $clan) {
            $consultaGrupo = "SELECT * FROM nuevo2_manadas WHERE clan = ? ORDER BY name";
            $stmtGrupo = mysqli_prepare($link, $consultaGrupo);
            if ($stmtGrupo) {
                mysqli_stmt_bind_param($stmtGrupo, 's', $clan);
                mysqli_stmt_execute($stmtGrupo);
                $resultGrupo = mysqli_stmt_get_result($stmtGrupo);

                $numeroDeGrupos = mysqli_num_rows($resultGrupo);

                if ($numeroDeGrupos > 0) {
                    print("<fieldset id='renglonArchivos'>");
                    // TITULO SECCION
                    print("<legend id='archivosLegend'>");
                    print("<a href='index.php?p=seegroup&amp;t=2&amp;b=" . htmlspecialchars($idOfSept[$index]) . "' title='" . htmlspecialchars($clan) . "'>");
                    print("&nbsp;" . htmlspecialchars($clan) . "&nbsp;");
                    print("</a>");
                    print("</legend>");
                    // FIN TITULO SECCION

                    print("<ul class='listaManadas'>");
                    // INICIO LISTA
                    while ($rowGrupo = mysqli_fetch_assoc($resultGrupo)) {
                        $enActivo = $rowGrupo["activa"];
                        $iconManada = ($enActivo == 0) ? $iconSept : $iconPack;

                        print("<a href='index.php?p=seegroup&amp;t=1&amp;b=" . htmlspecialchars($rowGrupo["id"]) . "' title='" . htmlspecialchars($rowGrupo["name"]) . "'>");
                        print("<li class='listaManadas'>");
                        print("<img src='" . htmlspecialchars($iconManada) . "' alt='" . htmlspecialchars($rowGrupo["name"]) . "' title='" . htmlspecialchars($rowGrupo["name"]) . "' class='valign'/>");
                        print(" " . htmlspecialchars($rowGrupo["name"]) . "");
                        print("</li></a>");

                        $numeroDeGruposHallados++;
                    }
                    // FIN LISTA
                    print("</ul>");
                    print("</fieldset>");
                }
                mysqli_free_result($resultGrupo);
                mysqli_stmt_close($stmtGrupo);
            }
        }
    }

    print("<p style='text-align:right;'>Organizaciones halladas: " . htmlspecialchars($numeroClanesHallados));
    print("<br/>Grupos hallados: " . htmlspecialchars($numeroDeGruposHallados) . "</p>");

} else {
    echo "Error en la consulta: " . mysqli_error($link);
}
?>
