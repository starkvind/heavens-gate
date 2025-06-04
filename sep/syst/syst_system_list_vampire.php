<?php

/* TODO EL TEMA A CONTINUACION ES SI SOMOS VAMPIROS, MIERDA */
$consulta ="SELECT DISTINCT afiliacion FROM nuevo_tribus WHERE sistema LIKE '$systemCategoryName' ORDER BY id";

$IdConsulta = mysql_query($consulta, $link);
$NFilas = mysql_num_rows($IdConsulta);

/* COGEMOS LA AFILIACION DEL ZURUCK Y LE APLICAMOS EL AMOR */
for($i=0;$i<$NFilas;$i++) {
$ResultQuery = mysql_fetch_array($IdConsulta);

	$afiliationName = $ResultQuery["afiliacion"];

	print("<tr><td colspan='2'><h4>$afiliationName</h4></td></tr>");
	
	$afiliationConsulta ="SELECT id, name FROM nuevo_tribus WHERE afiliacion LIKE '$afiliationName' ORDER BY id";
	$idAfiliationConsulta = mysql_query($afiliationConsulta, $link);
	$afiliationNFilas = mysql_num_rows($idAfiliationConsulta);
		for($e=0;$e<$afiliationNFilas;$e++) {
			$afiliationResultQuery = mysql_fetch_array($idAfiliationConsulta);
			print("
				<tr>
				<td class='lefay' colspan='2'>
				<a href='index.php?p=versist&amp;tc=3&amp;b=".$afiliationResultQuery["id"]."'>
				<img src='img/system-clan.gif' alt='".$afiliationResultQuery["name"]."' title='".$afiliationResultQuery["name"]."'/>
				".$afiliationResultQuery["name"]."
				</a>
				</td>
				</tr>
			");
		}
	$numregistros = $numregistros + mysql_num_rows ($idAfiliationConsulta);
}

?>