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

include("yiey.php");

include("../libreria.php");

$fechadetema = date("Y-m-d H:i:s");

/* CHEQUEAMOS EL TIPO DE IMAGEN QUE SE VA A SUBIR */

switch($_FILES['file']['type']) {
	default:
		$ext = ".jpg";
		break;
	case "image/gif":
		$ext = ".gif"; 
		break;
	case "image/png":
		$ext = ".png"; 
		break;
}


/*if ($_FILES['file']['type'] == "image/gif") { $ext = ".gif"; 
}

if ($_FILES['file']['type'] == "image/jpeg") { $ext = ".jpg"; 
}

if ($_FILES['file']['type'] == "image/pjpeg") { $ext = ".jpg";
} */
 
/* La primera ruta es para guardar en la base de datos y la segunda para colocarla en su carpeta */

$ruta = "../../../img/subidas";
$ruta2 = "img/subidas";

/* Le damos el nombre del personaje agregado para trasladarla a la carpeta */

$bunbury1 = rand(0,99999);
$bunbury2 = rand(0,99999);
$nombre2 = "$bunbury1$bunbury2";

	/* DATABASE ADD SKILLS - ARCHIVO PARA AGREGAR HABILIDADES A LA BDD */
	/* Definimos vocales con tilde y sin tilde para que PHP las quite de los nombres de archivo */ 
	$vocalesTilde 		= array("á","é","í","ó","ú","Á","É","Í","Ó","Ú","ñ","Ñ");
	$vocalesNormales 	= array("a","e","i","o","u","A","E","I","O","U","n","N");

	/* Variables para asignar el nombre de archivo */
	$charIconFile_name = strtolower($nombre);
	$charIconFile_name = str_replace($vocalesTilde, $vocalesNormales, $charIconFile_name); 
	$charIconFile_name = ereg_replace("[^A-Za-z]", "", $charIconFile_name);


$img2 = "$ruta/$charIconFile_name$ext";

move_uploaded_file( $_FILES [ 'file' ][ 'tmp_name' ], $img2);

/* Y ahora declaramos la ruta para guardar en el SQL */

$img = "$ruta2/$charIconFile_name$ext";

echo $img;

mysql_select_db("$bdd", $link);

if ($nombre == "") {

	echo "<center>Fallo en el proceso</center>";

} else {

//mysql_query("insert into pjs1 (nombre,raza,manada,jugador,auspicio,totem,cronica,tribu,concepto,fuerza,destreza,resistencia,carisma,manipulacion,apariencia,percepcion,inteligencia,astucia,alerta,armascc,ciencias,atletismo,armasdefuego,enigmas,callejeo,conducir,informatica,empatia,etiqueta,investigacion,esquivar,interpretacion,leyes,expresion,liderazgo,linguistica,impulsprimario,reparaciones,medicina,intimidacion,sigilo,ocultismo,pelea,supervivencia,politica,subterfugio,tratoanimales,rituales,trasfondo1,trasfondo2,trasfondo3,trasfondo4,trasfondo5,trasfondo1valor,trasfondo2valor,trasfondo3valor,trasfondo4valor,trasfondo5valor,don1,don2,don3,don4,don5,don6,don7,don8,don9,don10,don11,don12,don13,don14,don15,don16,don17,don18,don19,don20,gloriat,gloriap,honort,honorp,sabiduriat,sabiduriap,rabiap,rabiag,gnosisp,gnosisg,fvp,fvg,rango,estado,img,tipo,cumple,text1,text2,fera,kes,naturaleza,conducta,merodef1,merodef2,merodef3,merodef4,merodef5,merodef6,merodef7,clan,talento1extra,talento2extra,tecnica1extra,tecnica2extra,conoci1extra,conoci2extra,talento1valor,talento2valor,tecnica1valor,tecnica2valor,conoci1valor,conoci2valor) values ('$nombre', '$raza', '$manada', '$jugador', '$auspicio', '$totem', '$cronica', '$tribu', '$concepto', '$fuerza', '$destreza', '$resistencia', '$carisma', '$manipulacion', '$apariencia', '$percepcion', '$inteligencia', '$astucia', '$alerta', '$armascc', '$ciencias', '$atletismo', '$armasdefuego', '$enigmas', '$callejeo', '$conducir', '$informatica', '$empatia', '$etiqueta', '$investigacion', '$esquivar', '$interpretacion', '$leyes', '$expresion', '$liderazgo', '$linguistica', '$impulsprimario', '$reparaciones', '$medicina', '$intimidacion', '$sigilo', '$ocultismo', '$pelea', '$supervivencia', '$politica', '$subterfugio', '$tratoanimales', '$rituales', '$trasfondo1', '$trasfondo2', '$trasfondo3', '$trasfondo4', '$trasfondo5', '$trasfondo1valor', '$trasfondo2valor', '$trasfondo3valor', '$trasfondo4valor', '$trasfondo5valor', '$don1', '$don2', '$don3', '$don4', '$don5', '$don6', '$don7', '$don8', '$don9', '$don10', '$don11', '$don12', '$don13', '$don14', '$don15', '$don16', '$don17', '$don18', '$don19', '$don20', '$gloriat', '$gloriap', '$honort', '$honorp', '$sabiduriat', '$sabiduriap', '$rabiap', '$rabiag', '$gnosisp', '$gnosisg', '$fvp', '$fvg', '$rango', '$estado', '$img', '$tipo', '$cumple', '$text1', '$text2', '$fera', '$kes', '$naturaleza', '$conducta', '$merodef1', '$merodef2', '$merodef3', '$merodef4', '$merodef5', '$merodef6', '$merodef7', '$clan', '$talento1extra', '$talento2extra', '$tecnica1extra', '$tecnica2extra', '$conoci1extra', '$conoci2extra', '$talento1valor', '$talento2valor', '$tecnica1valor', '$tecnica2valor', '$conoci1valor', '$conoci2valor')",$link);

$consultaTemaSQL = "INSERT INTO pjs1 VALUES ('','$nombre','$alias','$nombregarou','$raza','$manada','$jugador','$auspicio','$totem','$cronica','$tribu','$concepto','$naturaleza','$conducta','$fuerza','$destreza','$resistencia','$carisma','$manipulacion','$apariencia','$percepcion','$inteligencia','$astucia','$alerta','$atletismo','$callejeo','$empatia','$esquivar','$expresion','$impulsprimario','$intimidacion','$pelea','$subterfugio','$armascc','$armasdefuego','$conducir','$etiqueta','$interpretacion','$liderazgo','$reparaciones','$sigilo','$supervivencia','$tratoanimales','$ciencias','$enigmas','$informatica','$investigacion','$leyes','$linguistica','$medicina','$ocultismo','$politica','$rituales','$talento1extra','$talento2extra','$tecnica1extra','$tecnica2extra','$conoci1extra','$conoci2extra','$talento1valor','$talento2valor','$tecnica1valor','$tecnica2valor','$conoci1valor','$conoci2valor','$trasfondo1','$trasfondo2','$trasfondo3','$trasfondo4','$trasfondo5','$trasfondo1valor','$trasfondo2valor','$trasfondo3valor','$trasfondo4valor','$trasfondo5valor','$dones','$numeroRituales','$meridef','$inventory','$gloriap','$honorp','$sabiduriap','$rabiap','$gnosisp','$fvp','$rango','$estado','','$img','$tipo','$clan','$cumple','$text1','','$kes','','$temamusical','$temaurl','$px','$notas','$fera','$sistema','$fechadetema')";
	
if (!mysql_query($consultaTemaSQL,$link))
  {
  echo $consultaTemaSQL;
  echo "<br/><br/>";
  die('Error: ' . mysql_error());
  }
//echo "1 record added";

}

//echo $nombre;

?>

<br><center>

<table style="empty-cells: show;">

<tr>

<td colspan="4">

<?php

if ($nombre == "") {

	echo "<h2> Error: El personaje no tiene nombre </h2>";

} else {

	echo "<h3> ¡Personaje agregado correctamente! </h3>"; 

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
<td colspan="2" class="datos3"><a href="agregar1.php">Agregar m&aacute;s</a></td>

</tr>

</table></center>

<br>

<? }
else { 
?>

<center>ERROR: ACCESO DENEGADO</center>

<? } ?>

</body>

</html>