<?php
setMetaFromPage("Jugadores | Heaven's Gate", "Listado de jugadores de la campa?a.", null, 'website');
include("app/partials/main_nav_bar.php"); // Barra Navegación

echo "<h2>Jugadores</h2>";
echo "<fieldset class='grupoHabilidad'>";

// Verificar si la conexión a la base de datos ($link) está definida y es válida
if (!$link) {
    die("Error de conexión a la base de datos: " . mysqli_connect_error());
}

// Consulta para contar el número total de registros de jugadores
$conNum = "SELECT id FROM dim_players";
$idConNum = mysqli_query($link, $conNum);
if (!$idConNum) {
    die("Error en la consulta: " . mysqli_error($link));
}
$totRegNum = mysqli_num_rows($idConNum);

// Icono de jugador
$itemIcon = "img/player/sinfoto.jpg";

// Consulta para obtener detalles de los jugadores ordenados por nombre
$consulta1 = "SELECT id, pretty_id, name, surname FROM dim_players ORDER BY name ASC";
$IdConsulta1 = mysqli_query($link, $consulta1);
if (!$IdConsulta1) {
    die("Error en la consulta: " . mysqli_error($link));
}
$NFilas1 = mysqli_num_rows($IdConsulta1);

// Mostrar detalles de cada jugador
while ($ResultQuery1 = mysqli_fetch_assoc($IdConsulta1)) {
    $playerID = (int)$ResultQuery1["id"];
    $playerPretty = htmlspecialchars($ResultQuery1["pretty_id"] ?? '');
    $playerName = htmlspecialchars($ResultQuery1["name"]);
    $playerSurname = htmlspecialchars($ResultQuery1["surname"]);
    $playerHref = $playerPretty !== '' ? "/players/$playerPretty" : "/players/$playerID";

    echo "
        <a href='$playerHref'>
            <div class='renglon3col'>
                $playerName $playerSurname
            </div>
        </a>
    ";
}

echo "</fieldset>";

// Mostrar el número total de jugadores
echo "<p align='right'>Jugadores: $NFilas1</p>";

// Liberar resultados de las consultas
mysqli_free_result($idConNum);
mysqli_free_result($IdConsulta1);
?>
