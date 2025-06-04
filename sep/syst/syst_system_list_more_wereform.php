<?php

$consulta ="SELECT DISTINCT raza FROM nuevo_formas WHERE afiliacion LIKE '$systemCategoryName'";

$IdConsulta = mysql_query($consulta, $link);
$NFilas = mysql_num_rows($IdConsulta);

if ($NFilas != 0) {
for($i=0;$i<$NFilas;$i++) {
	$ResultQuery = mysql_fetch_array($IdConsulta);

	$razaName = $ResultQuery["raza"];
	
	/* print("<b>$razaName</b>"); */
	
	echo "<ul>";
	$moreWereConsulta ="SELECT * FROM nuevo_formas WHERE raza LIKE '$razaName' ORDER BY id";
	$idMoreWereConsulta = mysql_query($moreWereConsulta, $link);
	$moreWereNFilas = mysql_num_rows($idMoreWereConsulta);
		for($e=0;$e<$moreWereNFilas;$e++) {
			$moreWereResultQuery = mysql_fetch_array($idMoreWereConsulta);
			print("
				<li style='padding-left:0px;'>
				<a href='index.php?p=verforma&amp;b=".$moreWereResultQuery["id"]."' title='Forma de $razaName.' alt='$razaName'>
				".$moreWereResultQuery["forma"]."
				</a>
				</li>
			");
		}
	echo "</ul>";
	
print("

");

}

}
?>