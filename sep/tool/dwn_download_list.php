<center>

<h2>Descargas</h2><br/>

<?php

$pageSect = "Descargas"; // PARA CAMBIAR EL TITULO A LA PAGINA

//Limito la busqueda
$tamano_pagina = 10;

//examino la página a mostrar y el inicio del registro a mostrar
$pagina = $_GET["pag"];
if (!$pagina) {

    $inicio = 0;
    $pagina=1;
}
else {

    $inicio = ($pagina - 1) * $tamano_pagina;

} 

/* include("heroes.php"); */

$consulta ="SELECT * FROM deskrgas";

$IdConsulta = mysql_query($consulta, $link);
$num_total_registros = mysql_num_rows($IdConsulta);
$total_paginas = ceil($num_total_registros / $tamano_pagina);

/* ====================================================== */

$consulta ="SELECT * FROM deskrgas ORDER BY id DESC LIMIT $inicio,$tamano_pagina";
$IdConsulta = mysql_query($consulta, $link);
$NFilas = mysql_num_rows($IdConsulta);
for($i=0;$i<$NFilas;$i++) {
$ResultQuery = mysql_fetch_array($IdConsulta);

$yd = $ResultQuery['id'];
$nombre = $ResultQuery['nombre'];
$descripcion = $ResultQuery['descripcion'];

echo "

<fieldset class='dans'>

<table class='inventario'>

<tr>

<td class='itname'>Nombre:</td><td class='itnam2'>$nombre</td>

</tr>

<tr>

<td class='itnam2' colspan='3'><b>Descripci&oacute;n:</b>

<p align='justify'>

$descripcion

</p>

<p align='right'> <a href='descarga.php?numero=$yd' target='_blank'>Descargar</a> </p>

</td>

</tr>

</table>

</fieldset>

<br/>
";

}

echo "<table><tr>";

if ($total_paginas >= 2) { 

	if ($total_paginas != "1") { 

	$back = $pagina-1;

		if ($back != '0') {

			echo "<td align='center'><a href='index.php?p=dwn&amp;pag=$back'>&#60;&#60; Atr&aacute;s</a></td>"; }

		}

	}

	echo "<td>&nbsp;&nbsp;&nbsp;</td>";

if ($total_paginas >= 2) { 

	if ($total_paginas != "1") { 

	$adelante = $pagina+1;

		if ($adelante <= $total_paginas) {

			echo "<td align='center'><a href='index.php?p=dwn&amp;pag=$adelante'>Siguiente &#62&#62</a></td>"; }

		}

	}

echo "</tr></table></center><p align='right'>";

if ($total_paginas >= 2) { echo "P&aacute;gina: "; }

//muestro los distintos índices de las páginas, si es que hay varias páginas
if ($total_paginas > 1){
    for ($ix=1;$ix<=$total_paginas;$ix++){
       if ($pagina == $ix) {
          //si muestro el índice de la página actual, no coloco enlace
          echo $pagina . " ";
      } else
          //si el índice no corresponde con la página mostrada actualmente, coloco el enlace para ir a esa página
          echo "<a href='index.php?p=dwn&amp;pag=$ix'>" . $ix . "</a> ";
    }
} 

echo "</p>";

?>