<?php include("sep/main/main_nav_bar.php");	// Barra Navegación ?>

<h2>Registro de Combates</h2>
<center>

<fieldset style="border: 1px solid #0000CC;">

<legend style="border: 1px solid #0000CC; padding: 3px; background-color:#000066">Combates</legend>

<?php

$pageSect = ":: Combates del Simulador"; // PARA CAMBIAR EL TITULO A LA PAGINA

//Limito la busqueda
$tamano_pagina = 30;

//examino la página a mostrar y el inicio del registro a mostrar
$pagina = $_GET["pagina"];
if (!$pagina) {

    $inicio = 0;
    $pagina=1;
}
else {

    $inicio = ($pagina - 1) * $tamano_pagina;

} 

/* include("heroes.php"); */

$consulta ="SELECT * FROM ultimoscombates";

$IdConsulta = mysql_query($consulta, $link);
$num_total_registros = mysql_num_rows($IdConsulta);
$total_paginas = ceil($num_total_registros / $tamano_pagina);

$consulta ="SELECT * FROM ultimoscombates ORDER BY id DESC LIMIT $inicio,$tamano_pagina";

$IdConsulta = mysql_query($consulta, $link);
$NFilas = mysql_num_rows($IdConsulta);

if ($NFilas == "") {

echo "A&uacute;n no se ha celebrado ning&uacute;n combate.";

} else {

for($i=0;$i<$NFilas;$i++) {
$ResultQuery = mysql_fetch_array($IdConsulta);

$kid = $ResultQuery["id"];

$ki1 = $ResultQuery["luchador1"];
$ki2 = $ResultQuery["luchador2"];
$kires = $ResultQuery["ganador"];
/* $resultDataCombat = $ResultQuery["turnos"];
print_r ($resultDataCombat);

$testYeah = $resultDataCombat["fraseinicio"];

echo $testYeah; */

echo "

<table style='border-bottom:1px solid #000099;' width='100%'>

<tr>

<td style='text-align:left;width:6%;'>#<a href='index.php?p=vercombat&amp;b=$kid'>$kid</a></td>
<td style='text-align:right;width:24%;'>$ki1</td>
<td style='text-align:center;width:2%;'><b>VS</b></td>
<td style='text-align:left;width:24%;'> $ki2</td>
<td style='text-align:right;width:44%;'>$kires</td>

</tr>

</table>

";

}

}

?>

</fieldset>

<p align='right'>

<?php

if ($total_paginas >= 2) { echo "P&aacute;gina: "; }

//muestro los distintos índices de las páginas, si es que hay varias páginas
if ($total_paginas > 1){
    for ($ix=1;$ix<=$total_paginas;$ix++){
       if ($pagina == $ix) {
          //si muestro el índice de la página actual, no coloco enlace
          echo $pagina . " ";
      } else
          //si el índice no corresponde con la página mostrada actualmente, coloco el enlace para ir a esa página
          echo "<a href='index.php?p=combtodo&amp;pagina=$ix'>" . $ix . "</a> ";
    }
} 

?>

</p>

<a href="index.php?p=simulador">Regresar</a>

</center>