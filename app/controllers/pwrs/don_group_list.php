<?php
setMetaFromPage("Dones | Heaven's Gate", "Listado de dones por categoria.", null, 'website');

    // Obtener y sanitizar el parámetro 'b'
    $routeParam = isset($_GET['b']) ? $_GET['b'] : ''; 

    // Preparar la consulta para obtener información sobre el tipo de don
    $consulta = "SELECT name, determinant AS determinante, description FROM dim_gift_types WHERE id = ? LIMIT 1";
    $stmt = $link->prepare($consulta);
    $stmt->bind_param('s', $routeParam);
    $stmt->execute();
    $result = $stmt->get_result();
    $ResultQuery = $result->fetch_assoc();

    if ($ResultQuery) {
        $routeLabel = htmlspecialchars($ResultQuery["name"]);
        $determinante = htmlspecialchars($ResultQuery["determinante"]);
        $descDones = htmlspecialchars($ResultQuery["description"] ?? $ResultQuery["desc"] ?? '');
        $donTypePhrase = "Dones";
        $pageSect = "$donTypePhrase $determinante $routeLabel"; // Para cambiar el título de la página
		
		include("app/partials/main_nav_bar.php"); // Barra de navegación

        echo "<h2>$donTypePhrase $determinante $routeLabel</h2>";
        echo "<fieldset class='descripcionGrupo'>$descDones</fieldset>";

        // Preparar la consulta para obtener los grupos de dones
        $consulta = "SELECT DISTINCT grupo FROM fact_gifts WHERE kind = ? ORDER BY grupo";
        $stmt = $link->prepare($consulta);
        $stmt->bind_param('s', $routeParam);
        $stmt->execute();
        $result = $stmt->get_result();

        $domoarigato = [];
        while ($row = $result->fetch_assoc()) {
            $domoarigato[] = htmlspecialchars($row["grupo"]);
        }

        $misterroboto = count($domoarigato);

        if ($misterroboto > 0) {
            foreach ($domoarigato as $grupo) {
                // Preparar la consulta para obtener los dones dentro de cada grupo
                $consulta = "SELECT id, pretty_id, name, rank FROM fact_gifts WHERE grupo = ? AND kind = ? ORDER BY rank";
                $stmt = $link->prepare($consulta);
                $stmt->bind_param('ss', $grupo, $routeParam);
                $stmt->execute();
                $result = $stmt->get_result();

                $riteClasificacion = ($routeLabel !== "Menores") ? $grupo : "Sin nivel";

                echo "<fieldset class='grupoHabilidad'>";
                echo "<legend><b><a name='$riteClasificacion'></a> $riteClasificacion</b></legend>";

                while ($row = $result->fetch_assoc()) {
                    echo "
                        <a href='" . htmlspecialchars(pretty_url($link, 'fact_gifts', '/powers/gift', (int)$row["id"])) . "' 
                            title='" . htmlspecialchars($row["name"]) . ", Rango " . htmlspecialchars($row["rank"]) . "'>
                            <div class='renglon2col'>
                                <div class='renglon2colIz'>
                                    <img class='valign' src='img/ui/powers/don.gif'> " . htmlspecialchars($row["name"]) . "
                                </div>
                                <div class='renglon2colDe'>" . htmlspecialchars($row["rank"]) . "</div>
                            </div>
                        </a>
                    ";
                }
                echo "</fieldset>";
            }
        }
        
        echo "<p align='right'>Dones hallados: $misterroboto</p>";
    } else {
        echo "<p>Error: No se encontró el tipo de don especificado.</p>";
    }
?>
