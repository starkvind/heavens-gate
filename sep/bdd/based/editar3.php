<?php

include("../hehe.php");

?>

<html>

<head>

<title>Heaven's Gate</title>

<link href="../style.css" rel="stylesheet" type="text/css">

</head>

<body>

<? if ($valido=="si")
{
?>


<?php

// Comenzamos a meter todo mecagonto 

$pj = $_POST['pj'];

include("yiey.php");

include("../libreria.php");

mysql_select_db("$bdd", $link);

if ($nombre == "") {

	echo "<center>Fallo en el proceso</center>";

} else {

$consulta = "UPDATE pjs1 SET 

`nombre` = '$nombre',
`raza` = '$raza',
`manada` = '$manada',
`jugador` = '$jugador',
`auspicio` = '$auspicio',
`totem` = '$totem',
`cronica` = '$cronica',
`tribu` = '$tribu',
`concepto` = '$concepto',
`fuerza` = '$fuerza',
`destreza` = '$destreza',
`resistencia` = '$resistencia',
`carisma` = '$carisma',
`manipulacion` = '$manipulacion',
`apariencia` = '$apariencia',
`percepcion` = '$percepcion',
`inteligencia` = '$inteligencia',
`astucia` = '$astucia',
`alerta` = '$alerta',
`atletismo` = '$atletismo',
`callejeo` = '$callejeo',
`empatia` = '$empatia',
`esquivar` = '$esquivar',
`expresion` = '$expresion',
`impulsprimario` = '$impulsprimario',
`intimidacion` = '$intimidacion',
`pelea` = '$pelea',
`subterfugio` = '$subterfugio',
`armascc` = '$armascc',
`armasdefuego` = '$armasdefuego',
`conducir` = '$conducir',
`etiqueta` = '$etiqueta',
`interpretacion` = '$interpretacion',
`liderazgo` = '$liderazgo',
`reparaciones` = '$reparaciones',
`sigilo` = '$sigilo',
`supervivencia` = '$supervivencia',
`tratoanimales` = '$tratoanimales',
`ciencias` = '$ciencias',
`enigmas` = '$enigmas',
`informatica` = '$informatica',
`investigacion` = '$investigacion',
`leyes` = '$leyes',
`linguistica` = '$linguistica',
`medicina` = '$medicina',
`ocultismo` = '$ocultismo',
`politica` = '$politica',
`rituales` = '$rituales',
`trasfondo1` = '$trasfondo1',
`trasfondo2` = '$trasfondo2',
`trasfondo3` = '$trasfondo3',
`trasfondo4` = '$trasfondo4',
`trasfondo5` = '$trasfondo5',
`trasfondo1valor` = '$trasfondo1valor',
`trasfondo2valor` = '$trasfondo2valor',
`trasfondo3valor` = '$trasfondo3valor',
`trasfondo4valor` = '$trasfondo4valor',
`trasfondo5valor` = '$trasfondo5valor',
`don1` = '$don1',
`don2` = '$don2',
`don3` = '$don3',
`don4` = '$don4',
`don5` = '$don5',
`don6` = '$don6',
`don7` = '$don7',
`don8` = '$don8',
`don9` = '$don9',
`don10` = '$don10',
`don11` = '$don11',
`don12` = '$don12',
`don13` = '$don13',
`don14` = '$don14',
`don15` = '$don15',
`don16` = '$don16',
`don17` = '$don17',
`don18` = '$don18',
`don19` = '$don19',
`don20` = '$don20',
`gloriat` = '$gloriat',
`gloriap` = '$gloriap',
`honort` = '$honort',
`honorp` = '$honorp',
`sabiduriat` = '$sabiduriat',
`sabiduriap` = '$sabiduriap',
`rabiap` = '$rabiap',
`rabiag` = '$rabiag',
`gnosisp` = '$gnosisp',
`gnosisg` = '$gnosisg',
`fvp` = '$fvp',
`fvg` = '$fvg',
`rango` = '$rango',
`estado` = '$estado',
`img` = '$img',
`tipo` = '$tipo',
`cumple` = '$cumple',
`text1` = '$text1',
`text2` = '$text2',
`kes` = '$kes'

WHERE nombre = '$pj'";

$query = mysql_query ($consulta, $link);


}


?>

<br>




<table style="empty-cells: show;">

<tr>

<td colspan="4">

<?php

if ($nombre == "") {

	echo "<h2> Error: El personaje no tiene nombre </h2>";

} else {

	echo "<h3> ¡Personaje editado correctamente! </h3>"; 

}

?>

</td>

</tr>

<tr>

<td class="datos1"> Nombre: </td> 

<td class="datos1"> Jugador: </td> 

<td class="datos1"> Estado: </td> 

<td class="datos1"> Tribu: </td> 

</tr>

<tr>

<td class="datos2"> <?php echo $nombre ?> </td>

<td class="datos2"> <?php echo $jugador ?> </td>

<td class="datos2"> <?php echo $estado ?> </td>

<td class="datos2"> <?php echo $tribu ?> </td>

</tr>

<tr>

<td colspan="2" class="datos3"><a href="../bdd1.php">Regresar</a></td>
<td colspan="2" class="datos3"><a href="editar1.php">Editar m&aacute;s</a></td>

</tr>

</table>

<br>

<? }
else { 
?>

<center>ERROR: ACCESO DENEGADO</center>

<? } ?>

</body>

</html>