<?php
// Obtener y sanitizar el parámetro 'b'
$punk = isset($_GET['b']) ? $_GET['b'] : ''; 

// Consulta segura para obtener la información del tipo de ritual
$consulta = "SELECT name, determinante, `desc` FROM nuevo2_tipo_rituales WHERE id = ? LIMIT 1";
$stmt = $link->prepare($consulta);
$stmt->bind_param('s', $punk);
$stmt->execute();
$result = $stmt->get_result();
$ResultQuery = $result->fetch_assoc();

// Definir variables con valores por defecto
$punk2 = $ResultQuery ? htmlspecialchars($ResultQuery["name"]) : "Desconocido";
$determinante = $ResultQuery ? htmlspecialchars($ResultQuery["determinante"]) : "";
$descRituales = $ResultQuery ? $ResultQuery["desc"] : "<p>Descripción no disponible</p>"; // NO usar htmlspecialchars()
$donTypePhrase = "Ritos";
$pageSect = "$donTypePhrase $determinante $punk2"; // Para cambiar el título de la página

// Guardar en sesión para breadcrumbs
$_SESSION['punk2'] = $punk2;

// Incluir la barra de navegación
include("sep/main/main_nav_bar.php");

echo "<h2>$donTypePhrase $determinante $punk2</h2>";
echo "<fieldset class='descripcionGrupo'>$descRituales</fieldset>";

// Obtener los niveles de los rituales
$consulta = "SELECT DISTINCT nivel FROM nuevo_rituales WHERE tipo = ? ORDER BY nivel";
$stmt = $link->prepare($consulta);
$stmt->bind_param('s', $punk);
$stmt->execute();
$result = $stmt->get_result();

$domoarigato = [];
while ($row = $result->fetch_assoc()) {
    $domoarigato[] = htmlspecialchars($row["nivel"]);
}

// Si hay niveles de rituales, los mostramos
$misterroboto = count($domoarigato);

if ($misterroboto > 0) {
    foreach ($domoarigato as $nivel) {
        // Consulta para obtener los rituales de cada nivel
        $consulta = "SELECT id, name FROM nuevo_rituales WHERE nivel = ? AND tipo = ? ORDER BY name";
        $stmt = $link->prepare($consulta);
        $stmt->bind_param('ss', $nivel, $punk);
        $stmt->execute();
        $result = $stmt->get_result();

        $riteClasificacion = ($punk2 !== "Menores") ? "Nivel $nivel" : "Sin nivel";

        echo "<fieldset class='grupoHabilidad'>";
        echo "<legend><b><a name='$riteClasificacion'></a> $riteClasificacion</b></legend>";

        while ($row = $result->fetch_assoc()) {
            echo "
                <a href='index.php?p=seerite&amp;b=" . htmlspecialchars($row["id"]) . "'
                    title='" . htmlspecialchars($row["name"]) . "'>
                    <div class='renglon2col'>
                        <div class='renglon2colIz'>
                            <img class='valign' src='img/rite.gif'> " . htmlspecialchars($row["name"]) . "
                        </div>
                    </div>
                </a>
            ";
        }

        echo "</fieldset>";
    }
}

echo "<p align='right'>Rituales hallados: $misterroboto</p>";
?>
