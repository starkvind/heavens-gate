<?php
include("sep/heroes.php");
$pageSect = "Lista por ID";

$consulta = "SELECT id, nombre, alias, nombregarou, img FROM pjs1 ORDER BY id ASC";
$resultado = mysqli_query($link, $consulta);

if (!$resultado) {
    echo "<p>Error en la consulta: " . mysqli_error($link) . "</p>";
    exit;
}

$anteriorID = 0;

while ($row = mysqli_fetch_assoc($resultado)) {
    $idPJ     = (int) $row["id"];
    $nombrePJ = htmlspecialchars($row["nombre"]);
    $aliasPJ  = htmlspecialchars($row["alias"]);
    $ngarouPJ = htmlspecialchars($row["nombregarou"]);
    $imgPJ    = htmlspecialchars($row["img"]);
    $comaN = ($ngarouPJ !== "") ? "," : "";
    $operacion = $idPJ - $anteriorID;

    if ($operacion === 1) {
        echo "<a href='index.php?p=muestrabio&amp;b={$idPJ}' target='_blank' title='{$nombrePJ}{$comaN} {$ngarouPJ}' style='color:white; text-decoration:none;'>";
        echo "<div class='listIDrenglon'>";
            echo "<div class='listIDizq'>";
                echo "<img src='{$imgPJ}' style='width:50px;height:50px;border:0.5px solid black;' />";
            echo "</div>";
            echo "<div class='listIDizq' style='width:26px;height:16px;border:1px solid white;margin-left:10px;background:teal;text-align:center;font-size:10px;'>";
                echo "{$idPJ}";
            echo "</div>";
            echo "<div class='listIDizq' style='width:154px;'>";
                echo "{$nombrePJ}";
                if ($aliasPJ !== "")  echo "<br/>" . $aliasPJ;
                if ($ngarouPJ !== "") echo "<br/>" . $ngarouPJ;
            echo "</div>";
        echo "</div>";
        echo "</a>";
        $anteriorID = $idPJ;
    }
}
mysqli_free_result($resultado);
?>
