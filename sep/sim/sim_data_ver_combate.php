<?php

$idDelCombate = $_GET['b']; 

$consulta ="SELECT * FROM ultimoscombates WHERE id like $idDelCombate LIMIT 1";

$IdConsulta = mysql_query($consulta, $link);
$NFilas = mysql_num_rows($IdConsulta);

if ($NFilas != "") {
	$ResultQuery = mysql_fetch_array($IdConsulta);
	$haveArrayOfTurns = $ResultQuery["turnos"];
	
	if ($haveArrayOfTurns != "") {
	
for($i=0;$i<$NFilas;$i++) {
//$ResultQuery = mysql_fetch_array($IdConsulta);

$kid = $ResultQuery["id"];

$ki1 = $ResultQuery["luchador1"];
$ki2 = $ResultQuery["luchador2"];
$kires = $ResultQuery["ganador"];

$pageSect = "Combate #$kid"; // PARA CAMBIAR EL TITULO A LA PAGINA
$pageTitle2	 = "$ki1 VS $ki2";

$resultDataCombat = $ResultQuery["turnos"];
$arrayOfTurns = unserialize($resultDataCombat);

////////////////////////////////////////////////////////

$idPj1 = $arrayOfTurns["id1"];
$consultaPj1 ="SELECT nombre,alias,img FROM pjs1 WHERE id like $idPj1";

$IdConsultaPj1 = mysql_query($consultaPj1, $link);
$NFilasPj1 = mysql_num_rows($IdConsultaPj1);
	for($ipj1=0;$ipj1<$NFilasPj1;$ipj1++) {
		$ResultQueryPj1 = mysql_fetch_array($IdConsultaPj1);
		$nombrePj1 = $ResultQueryPj1["nombre"];
		$aliasPj1 = $ResultQueryPj1["alias"];
		$imgPj1 = $ResultQueryPj1["img"];
	}
////////////////////////////////////////////////////////

$idPj2 = $arrayOfTurns["id2"];
$consultaPj2 ="SELECT nombre,alias,img FROM pjs1 WHERE id like $idPj2";

$IdConsultaPj2 = mysql_query($consultaPj2, $link);
$NFilasPj2 = mysql_num_rows($IdConsultaPj2);
	for($ipj2=0;$ipj2<$NFilasPj2;$ipj2++) {
		$ResultQueryPj2 = mysql_fetch_array($IdConsultaPj2);
		$nombrePj2 = $ResultQueryPj2["nombre"];
		$aliasPj2 = $ResultQueryPj2["alias"];
		$imgPj2 = $ResultQueryPj2["img"];
	}
/////////////////////////////////////////////////////////

$tipoComb	= $arrayOfTurns["tipocombate"];

$statsPj1 	= $arrayOfTurns["stats1"];
$umbraPj1 	= $arrayOfTurns["umbra1"];
$arma1 		= $arrayOfTurns["arma1"];
$bonus1		= $arrayOfTurns["bonusarma1"];
$protec1 	= $arrayOfTurns["prot1"];
$armor1		= $arrayOfTurns["bonusprot1"];

$statsPj2 	= $arrayOfTurns["stats2"];
$umbraPj2 	= $arrayOfTurns["umbra2"];
$arma2 		= $arrayOfTurns["arma2"];
$bonus2		= $arrayOfTurns["bonusarma2"];
$protec2 	= $arrayOfTurns["prot2"];
$armor2		= $arrayOfTurns["bonusprot2"];

$skillWep1	= $arrayOfTurns["skill1"];
$skillWep1V	= $arrayOfTurns["skill1value"];

$skillWep2	= $arrayOfTurns["skill2"];
$skillWep2V	= $arrayOfTurns["skill2value"];

/////////////////////////////////////////////////////////

$statsPj1Fin = explode(',', $statsPj1);
$statsPj2Fin = explode(',', $statsPj2);

$umbraPj1Fin = explode(',', $umbraPj1);
$umbraPj2Fin = explode(',', $umbraPj2);

include("sep/main/main_nav_bar.php");	// Barra Navegación

?>
<h2>Resumen del combate</h2>
<?php include("sep/main/main_social_menu.php");	// Zona de Impresión y Redes Sociales ?>
<table>
<tr>

<td class="ajustcelda" colspan="4" style="
	text-align:center;
	text-transform:uppercase;
	font-weight: bold;
">

<?php
	if ($tipoComb != "umbral") {
		echo "Combate a muerte";
	} else {
		echo "Combate umbral";
	}
?>

</td>

</tr>

<tr>

<td class="ajustcelda" colspan="2">

<?php 
	echo
	"<center>
	<a href='index.php?p=muestrabio&b=$idPj1' target='_blank'>
		<img class='photobio' src='$imgPj1' alt='$nombrePj1'>
	</a>
	</center>"; 
?>

</td>

<td class="ajustcelda" colspan="2">

<?php
	echo
	"<center>
	<a href='index.php?p=muestrabio&b=$idPj2' target='_blank'>
		<img class='photobio' src='$imgPj2' alt='$nombrePj2'>
	</a>
	</center>";
?>

</td>

</tr>

<tr>

<td class="ajustcelda"> Personaje: </td>

<td class="ajustcelda"> 

<?php echo"$nombrePj1"; ?>

</td>

<td class="ajustcelda"> Rival: </td>

<td class="ajustcelda"> 

<?php echo"$nombrePj2"; ?>

</td>

</tr>

<tr>

<td class="ajustcelda"> Alias: </td>

<td class="ajustcelda"> 

<?php echo"$aliasPj1"; ?>

</td>

<td class="ajustcelda"> Alias: </td>

<td class="ajustcelda"> 

<?php echo"$aliasPj2"; ?>

</td>

</tr>

<?php if ($tipoComb != "umbral") { ?>

<tr>

<td class="ajustcelda"> Arma: </td>

<td class="ajustcelda"> 

<?php if ($arma1 != "") { echo "$arma1 ($bonus1)"; } else { echo "Sin arma"; } ?>

</td>

<td class="ajustcelda"> Arma: </td>

<td class="ajustcelda"> 

<?php if ($arma2 != "") { echo "$arma2 ($bonus2)"; } else { echo "Sin arma"; } ?>

</td>

</tr>

<tr>

<td class="ajustcelda"> Protector: </td>

<td class="ajustcelda"> 

<?php if ($protec1 != "") { echo "$protec1 (+$armor1)"; } else { echo "Ninguno"; } ?>

</td>

<td class="ajustcelda"> Protector: </td>

<td class="ajustcelda"> 

<?php if ($protec2 != "") { echo "$protec2 (+$armor2)"; } else { echo "Ninguno"; } ?>

</td>

</tr>

<tr>

<td class="ajustcelda"> Forma: </td>

<td class="ajustcelda"> 

<?php echo $arrayOfTurns["forma1"]; ?>

</td>

<td class="ajustcelda"> Forma: </td>

<td class="ajustcelda"> 

<?php echo $arrayOfTurns["forma2"]; ?>

</td>

</tr>

<tr>

<td class="ajustcelda"> Fuerza: </td>

<td class="ajustcelda"> 

<?php echo $statsPj1Fin[0]; ?>

</td>

<td class="ajustcelda"> Fuerza: </td>

<td class="ajustcelda"> 

<?php echo $statsPj2Fin[0]; ?>

</td>

</tr>

<tr>

<td class="ajustcelda"> Destreza: </td>

<td class="ajustcelda"> 

<?php echo $statsPj1Fin[1]; ?>

</td>

<td class="ajustcelda"> Destreza: </td>

<td class="ajustcelda"> 

<?php echo $statsPj2Fin[1]; ?>

</td>

</tr>

<tr>

<td class="ajustcelda"> Resistencia: </td>

<td class="ajustcelda"> 

<?php echo $statsPj1Fin[2]; ?>

</td>


<td class="ajustcelda"> Resistencia: </td>

<td class="ajustcelda"> 

<?php echo $statsPj2Fin[2]; ?>

</td>

</tr>

<td class="ajustcelda"><?php echo "$skillWep1"; ?>:</td>

<td class="ajustcelda"> 

<?php echo"$skillWep1V"; ?>

</td>

<td class="ajustcelda"><?php echo "$skillWep2"; ?>:</td>

<td class="ajustcelda"> 

<?php echo"$skillWep2V"; ?>

</td>

</tr>

<?php } else { ?>

<tr>

<td class="ajustcelda"> Rabia: </td>

<td class="ajustcelda"> 

<?php echo $umbraPj1Fin[0]; ?>

</td>

<td class="ajustcelda"> Rabia: </td>

<td class="ajustcelda"> 

<?php echo $umbraPj2Fin[0]; ?>

</td>

</tr>

<tr>

<td class="ajustcelda"> Gnosis: </td>

<td class="ajustcelda"> 

<?php echo $umbraPj1Fin[1]; ?>

</td>

<td class="ajustcelda"> Gnosis: </td>

<td class="ajustcelda"> 

<?php echo $umbraPj2Fin[1]; ?>

</td>

</tr>

<tr>

<td class="ajustcelda"> Voluntad: </td>

<td class="ajustcelda"> 

<?php echo $umbraPj1Fin[2]; ?>

</td>


<td class="ajustcelda"> Voluntad: </td>

<td class="ajustcelda"> 

<?php echo $umbraPj2Fin[2]; ?>

</td>

</tr>

<?php } ?>

<?php
//echo "<table class='notix'>";
echo "<tr><td colspan='4' class='celdacombat'>";
echo $arrayOfTurns["fraseinicio"];
echo "</td></tr>";

echo "<tr><td colspan='4' class='celdacombat'>";
echo $arrayOfTurns["iniciativa"];
echo "</td></tr>";

echo "<tr><td colspan='4' class='celdacombat'>";
echo $arrayOfTurns["vitalidades"];
echo "</td></tr>";

$numeroValoresArray = $arrayOfTurns["turnos"];

for($iturnos=0;$iturnos<$numeroValoresArray;$iturnos++) {
	$numeroDeTurno = $iturnos + 1;
	$temaAtacante = $arrayOfTurns["turnoAtacante$numeroDeTurno"];
	$temaDefensor = $arrayOfTurns["turnoDefensor$numeroDeTurno"];
	$regeAtacante =	$arrayOfTurns["regenAtacante$numeroDeTurno"];
	$regeDefensor = $arrayOfTurns["regenDefensor$numeroDeTurno"];
	
	if ($regeAtacante != "") { echo "<tr><td colspan='4' class='celdacombat'>$regeAtacante</td></tr>"; }
	if ($regeDefensor != "") { echo "<tr><td colspan='4' class='celdacombat2'>$regeDefensor</td></tr>"; }
	echo "<tr><td colspan='4' class='ajustcelda'>Turno $numeroDeTurno</td></tr>";
	if ($temaAtacante != "") { echo "<tr><td colspan='4' class='celdacombat'>$temaAtacante</td></tr>"; }
	if ($temaDefensor != "") { echo "<tr><td colspan='4' class='celdacombat2'>$temaDefensor</td></tr>"; }
}

echo "<tr><td colspan='4' class='ajustcelda'>";
echo $arrayOfTurns["resultadofinal"];
echo "</td></tr>";

echo "<tr><td colspan='4' class='celdacombat'>";
echo $arrayOfTurns["ganador"];
echo "<br/>";
echo $arrayOfTurns["heridas"];
echo "</td></tr>";


echo "</td></tr>";
echo "<tr><td colspan='4' class='ajustcelda'>$kires.</td></tr>";

echo "<tr>
		<td colspan='4' class='ajustceld' align='center'>
		<table width='25%'>
			<tr>
			<td style='border:1px solid #006699;background:#000066;text-align:center;'>
			<a href='index.php?p=combtodo'>Volver</a>
			</td>
			</tr>
		</table>
		</td>
	</tr>";
echo "</table>";

}

} else {

	echo "<br/>
	<center>
		Ha ocurrido un error.
		<br/><br/>
			<table width='25%'>
			<tr>
			<td style='border:1px solid #006699;background:#000066;text-align:center;'>
			<a href='index.php?p=combtodo'>Volver</a>
			</td>
			</tr>
		</table>
	</center>";
	
}

}

?>