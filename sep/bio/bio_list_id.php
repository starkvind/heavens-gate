<?php
$pageSect 	 = "Lista por ID"; 	// Para cambiar el título a la página.
	$consulta ="SELECT * FROM pjs1 ORDER BY id ASC";
	//$consulta ="SELECT * FROM pjs1 WHERE kes LIKE 'pj' ORDER BY id ASC";
	$IdConsulta = mysql_query($consulta, $link);
	$NFilas = mysql_num_rows($IdConsulta);
	for($i=0;$i<$NFilas;$i++) {
		$ResultQuery = mysql_fetch_array($IdConsulta);
		$numregistros = mysql_num_rows ($IdConsulta);
		$nombrePJ = $ResultQuery["nombre"];
		$aliasPJ = $ResultQuery["alias"];
		$ngarouPJ = $ResultQuery["nombregarou"];
		if ($ngarouPJ != "") { $comaN = ","; } else { $comaN = ""; }
		//////////////////////////////////
		$idPJ = $ResultQuery["id"];
		$imgPJ = $ResultQuery["img"];
		$operacion = $idPJ - $anteriorID;
		//echo "<div class='listIDizq' title='$nombrePJ'><img src='$imgPJ' style='width:50px;height:50px;' /></div>";
		  $valorMostrar = "";
		  if ($operacion == 1) {
		  echo "<a href='index.php?p=muestrabio&amp;b=$idPJ' target='_blank' style='color:white;' title='$nombrePJ$comaN $ngarouPJ'>";
			echo "<div class='listIDrenglon'";
			if ($operacion != 1) { echo "style='border: 1px solid red;'"; $valorMostrar = $operacion; }
			echo ">";

				echo "<div class='listIDizq'><img src='$imgPJ' style='width:50px;height:50px;border:0.5px solid black;' /></div>";
				echo "<div class='listIDizq' style='width:26px;height:16px;border:1px solid white;margin-left:10px;background:teal;'>";
				//if ($valorMostrar != 1) { echo "$idPJ<br/>$valorMostrar"; } else { echo "$idPJ"; }
				echo "$idPJ";
				echo "</div>";
				echo "<div class='listIDizq' style='width:154px;'>";
					echo $nombrePJ;
					if ($aliasPJ != "") { echo "<br/>$aliasPJ"; }
					if ($ngarouPJ != "") { echo "<br/>$ngarouPJ"; }
				echo "</div>";
			echo "</div>";
		echo "</a>";
		$anteriorID = $idPJ;
		}
		}
?>