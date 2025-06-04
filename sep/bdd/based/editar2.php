<?php

include("../hehe.php");

?>

<html>

<head>

<title>Heaven's Gate</title>

<script languaje="Javascript">
<!--
document.write('<style type="text/css">div.ocultable{display: none;}</style>');
function MostrarOcultar(capa,enlace)
{
    if (document.getElementById)
    {
        var aux = document.getElementById(capa).style;
        aux.display = aux.display? "":"block";
    }
}

/* CODIGO OBTENIDO EN:
 http://foro.noticias3d.com/vbulletin/showthread.php?t=171184
*/


//-->
</script>

<link href="../style.css" rel="stylesheet" type="text/css">

</head>

<body>

<? if ($valido=="si")
{
?>

<?php

$pj = $_POST['pj'];

include("../libreria.php");

mysql_select_db("$bdd", $link);

$consulta = "SELECT nombre,raza,manada,jugador,auspicio,totem,cronica,tribu,concepto,fuerza,destreza,resistencia,carisma,manipulacion,apariencia,percepcion,inteligencia,astucia,alerta,armascc,ciencias,atletismo,armasdefuego,enigmas,callejeo,conducir,informatica,empatia,etiqueta,investigacion,esquivar,interpretacion,leyes,expresion,liderazgo,linguistica,impulsprimario,reparaciones,medicina,intimidacion,sigilo,ocultismo,pelea,supervivencia,politica,subterfugio,tratoanimales,rituales,trasfondo1,trasfondo2,trasfondo3,trasfondo4,trasfondo5,trasfondo1valor,trasfondo2valor,trasfondo3valor,trasfondo4valor,trasfondo5valor,don1,don2,don3,don4,don5,don6,don7,don8,don9,don10,don11,don12,don13,don14,don15,don16,don17,don18,don19,don20,gloriat,gloriap,honort,honorp,sabiduriat,sabiduriap,rabiap,rabiag,gnosisp,gnosisg,fvp,fvg,rango,estado,img,tipo,cumple,text1,text2,kes FROM pjs1 WHERE nombre LIKE '$pj'";

$query = mysql_query ($consulta, $link);

$da = mysql_fetch_array($query);

// Comenzamos a meter todo mecagonto 

$nombre = $da[0];
$raza = $da[1];
$manada = $da[2];

$jugador = $da[3];
$auspicio = $da[4];
$totem = $da[5];

$cronica = $da[6];
$tribu = $da[7];
$concepto = $da[8];

// Atributos

$fuerza = $da[9];
$destreza = $da[10];
$resistencia = $da[11];

$carisma = $da[12];
$manipulacion = $da[13];
$apariencia = $da[14];

$percepcion = $da[15];
$inteligencia = $da[16];
$astucia = $da[17];

// Talentos

$alerta = $da[18];
$atletismo = $da[21];
$callejeo = $da[24];
$empatia = $da[27];
$esquivar = $da[30];
$expresion = $da[33];
$impulsprimario = $da[36];
$intimidacion = $da[39];
$pelea = $da[42];
$subterfugio = $da[45];

// Tecnicas

$armascc = $da[19];
$armasdefuego = $da[22];
$conducir = $da[25];
$etiqueta = $da[28];
$interpretacion = $da[31];
$liderazgo = $da[34];
$reparaciones = $da[37];
$sigilo = $da[40];
$supervivencia = $da[43];
$tratoanimales = $da[46];

// Conocimientos

$ciencias = $da[20];
$enigmas = $da[23];
$informatica = $da[26];
$investigacion = $da[29];
$leyes = $da[32];
$linguistica = $da[35];
$medicina = $da[38];
$ocultismo = $da[41];
$politica = $da[44];
$rituales = $da[47];

// Ventajas

$trasfondo1 = $da[48];
$trasfondo2 = $da[49];
$trasfondo3 = $da[50];
$trasfondo4 = $da[51];
$trasfondo5 = $da[52];

$trasfondo1valor = $da[53];
$trasfondo2valor = $da[54];
$trasfondo3valor = $da[55];
$trasfondo4valor = $da[56];
$trasfondo5valor = $da[57];

$don1 = $da[58];
$don2 = $da[59];
$don3 = $da[60];
$don4 = $da[61];
$don5 = $da[62];
$don6 = $da[63];
$don7 = $da[64];
$don8 = $da[65];
$don9 = $da[66];
$don10 = $da[67];
$don11 = $da[68];
$don12 = $da[69];
$don13 = $da[70];
$don14 = $da[71];
$don15 = $da[72];
$don16 = $da[73];
$don17 = $da[74];
$don18 = $da[75];
$don19 = $da[76];
$don20 = $da[77];

// Poder

// RENOMBRE

$gloriat = $da[78];
$gloriap = $da[79];

$honort = $da[80];
$honorp = $da[81];

$sabiduriat = $da[82];
$sabiduriap = $da[83];

// COSAS

$rabiap = $da[84];
$rabiag = $da[85];

$gnosisp = $da[86];
$gnosisg = $da[87];

$fvp = $da[88];
$fvg = $da[89];

$rango = $da[90];
$estado = $da[91];
$img = $da[92];

$tipo = $da[93];
$cumple = $da[94];
$text1 = $da[95];
$text2 = $da[96];
$kes = $da[97];

//FIN

?>

<div class="cuerpo">

<h2> Editar Personajes </h2>

<center>

<form action="editar3.php" method="POST">

<table>

<tr>

<?php

echo "<td class=datos2>Nombre:</td><td><input type=text name=nombre size=20 maxlength=20 value='$nombre'></td></tr>

<tr><td class=datos2>Jugador:</td><td><input type=text name=jugador size=20 maxlength=20 value='$jugador'></td></tr>
<tr><td class=datos2>Cr&oacute;nica:</td><td><input type=text name=cronica value='$cronica' size=20 maxlength=20 ></td></tr>

<tr><td class=datos2>Raza:</td><td><input type=text name=raza size=20 maxlength=20 value='$raza'></td></tr>

<tr><td class=datos2>Auspicio:</td><td><input type=text name=auspicio size=20maxlength=20 value='$auspicio'></td></tr>

<tr><td class=datos2>Tribu:</td><td><input type=text name=tribu size=20 maxlength=30 value='$tribu'></td></tr>

<tr>

<td class=datos2>Manada:</td><td><input type=text name=manada size=20 maxlength=30 value='$manada'></td></tr>

<tr><td class=datos2>T&oacute;tem:</td><td><input type=text name=totem size=20 maxlength=20 value='$totem'></td></tr>

<tr><td class=datos2>Concepto:</td><td><input type=text name=concepto size=20 maxlength=20 value='$concepto'></td></tr>"; ?>

<tr><td class=datos2><a href="javascript:MostrarOcultar('texto1');" id="enlace1">F&iacute;sicos</a>:</td>

<td>

<div class="ocultable" id="texto1">

<table>

<tr>

<?php echo

"<td>Fuerza:</td>

<td>

<input type=text name=fuerza value='$fuerza' size=1 maxlength=1>

</td>

</tr>

<tr>

<td>Destreza:</td>

<td>

<input type=text name=destreza value='$destreza' size=1 maxlength=1>

</td>

</tr>

<tr>

<td>Resistencia:</td>

<td>

<input type=text name=resistencia value='$resistencia' size=1 maxlength=1>

</td></tr></table>

</div>

</td></tr>

<tr><td class=datos2><a href=javascript:MostrarOcultar('texto2'); id=enlace2>Sociales</a>:</td>

<td>

<div class=ocultable id=texto2>

<table>

<tr>

<td>Carisma:</td>

<td>
<input type=text name=carisma value='$carisma' size=1 maxlength=1>

</td>

</tr>

<tr>

<td>Manipulaci&oacute;n:</td>

<td>

<input type=text name=manipulacion value='$manipulacion' size=1 maxlength=1>

</td>

</tr>

<tr>

<td>Apariencia:</td>

<td>

<input type=text name=apariencia value='$apariencia' size=1 maxlength=1>

</td></tr></table>

</div>

</td></tr>

<tr><td class=datos2><a href=javascript:MostrarOcultar('texto3'); id=enlace3>Mentales</a>:</td>

<td>

<div class=ocultable id=texto3>

<table>

<tr>

<td>Percepci&oacute;n:</td>

<td>

<input type=text name=percepcion value='$percepcion' size=1 maxlength=1>

</td>

</tr>

<tr>

<td>Inteligencia:</td>

<td>

<input type=text name=inteligencia value='$inteligencia' size=1 maxlength=1>

</td>

</tr>

<tr>

<td>Astucia:</td>

<td>

<input type=text name=astucia value='$astucia' size=1 maxlength=1>

</td></tr></table>

</div>


</td></tr>

<tr><td class=datos2><a href=javascript:MostrarOcultar('texto4'); id=enlace4>Talentos</a>:</td>

<td>

<div class=ocultable id=texto4>

	<table>

	<tr>

	<td >    Alerta: </td>

	<td >  

	<input type=text name=alerta value='$alerta' size=1 maxlength=1>

	</td>

	</tr>

	<tr>

	<td >    Atletismo: </td>

	<td >  

	<input type=text name=atletismo value='$atletismo' size=1 maxlength=1>

	</td>

	</tr>

	<tr>

	<td >    Callejeo: </td>

	<td >  

	<input type=text name=callejeo value='$callejeo' size=1 maxlength=1>

	</td>

	</tr>

	<tr>

	<td >    Empat&iacute;a: </td>

	<td >  

	<input type=text name=empatia value='$empatia' size=1 maxlength=1>

	</td>

	</tr>

	<tr>

	<td >    Esquivar: </td>

	<td >  

	<input type=text name=esquivar value='$esquivar' size=1 maxlength=1>

	</td>

	</tr>

	<tr>

	<td >    Expresi&oacute;n: </td>

	<td >  

	<input type=text name=expresion value='$expresion' size=1 maxlength=1>

	</td>

	</tr>

	<tr>

	<td >    Impulso Primario: </td>

	<td >  

	<input type=text name=impulsprimario value='$impulsprimario' size=1 maxlength=1>

	</td>

	</tr>

	<tr>

	<td >    Intimidaci&oacute;n: </td>

	<td >  

	<input type=text name=intimidacion value='$intimidacion' size=1 maxlength=1>

	</td>

	</tr>

	<tr>

	<td >    Pelea: </td>

	<td >  

	<input type=text name=pelea value='$pelea' size=1 maxlength=1>

	</td>

	</tr>

	<tr>

	<td >    Subterfugio: </td>

	<td >  

	<input type=text name=subterfugio value='$subterfugio' size=1 maxlength=1>

	</td>

	</tr>

	</table>

</div>

</td></tr>

<tr><td class=datos2><a href=javascript:MostrarOcultar('texto5'); id=enlace5>T&eacute;cnicas</a>:</td>

<td>

<div class=ocultable id=texto5>

	<table>

	<tr>

	<td >    Armas C.C.: </td>

	<td >  

	<input type=text name=armascc value='$armascc' size=1 maxlength=1>

	</td>

	</tr>

	<tr>

	<td >    Armas de Fuego: </td>

	<td >  

	<input type=text name=armasdefuego value='$armasdefuego' size=1 maxlength=1>

	</td>

	</tr>

	<tr>

	<td >    Conducir: </td>

	<td > 
 
	<input type=text name=conducir value='$conducir' size=1 maxlength=1>

	</td>

	</tr>

	<tr>

	<td >    Etiqueta: </td>

	<td >  

	<input type=text name=etiqueta value='$etiqueta' size=1 maxlength=1>

	</td>

	</tr>

	<tr>

	<td >    Interpretaci&oacute;n: </td>

	<td >  

	<input type=text name=interpretacion value='$interpretacion' size=1 maxlength=1>

	</td>

	</tr>

	<tr>

	<td >    Liderazgo: </td>

	<td >  

	<input type=text name=liderazgo value='$liderazgo' size=1 maxlength=1>

	</td>

	</tr>

	<tr>

	<td >    Reparaciones: </td>

	<td >  

	<input type=text name=reparaciones value='$reparaciones' size=1 maxlength=1>

	</td>

	</tr>

	<tr>

	<td >    Sigilo: </td>

	<td >  

	<input type=text name=sigilo value='$sigilo' size=1 maxlength=1>

	</td>

	</tr>

	<tr>

	<td >    Supervivencia: </td>

	<td >  

	<input type=text name=supervivencia value='$supervivencia' size=1 maxlength=1>

	</td>

	</tr>

	<tr>

	<td >    Trato con Animales: </td>

	<td >  

	<input type=text name=tratoanimales value='$tratoanimales' size=1 maxlength=1>

	</td>

	</tr>

	</table>

</div>

</td></tr>

<tr><td class=datos2><a href=javascript:MostrarOcultar('texto6'); id=enlace6>Conocimientos</a>:</td>

<td>

<div class=ocultable id=texto6>

	<table>

	<tr>

	<td >    Ciencias: </td>

	<td >  

	<input type=text name=ciencias value='$ciencias' size=1 maxlength=1>

	</td>

	</tr>

	<tr>

	<td >    Enigmas: </td>

	<td >  

	<input type=text name=enigmas value='$enigmas' size=1 maxlength=1>
	</td>

	</tr>

	<tr>

	<td >    Inform&aacute;tica: </td>

	<td >  

	<input type=text name=informatica value='$informatica' size=1 maxlength=1>

	</td>

	</tr>

	<tr>

	<td >    Investigaci&oacute;n: </td>

	<td >  

	<input type=text name=investigacion value='$investigacion' size=1 maxlength=1>

	</td>

	</tr>

	<tr>

	<td >    Leyes: </td>

	<td >  

	<input type=text name=leyes value='$leyes' size=1 maxlength=1>
	</td>

	</tr>

	<tr>

	<td >    Ling&uuml;&iacute;stica: </td>

	<td >  

	<input type=text name=linguistica value='$linguistica' size=1 maxlength=1>

	</td>

	</tr>

	<tr>

	<td >    Medicina: </td>

	<td >  

	<input type=text name=medicina value='$medicina' size=1 maxlength=1>

	</td>

	</tr>
	
	<tr>

	<td >    Ocultismo: </td>

	<td >  

	<input type=text name=ocultismo value='$ocultismo' size=1 maxlength=1>

	</td>

	</tr>

	<tr>

	<td >    Pol&iacute;tica: </td>

	<td >  

	<input type=text name=politica value='$politica' size=1 maxlength=1>

	</td>

	</tr>

	<tr>

	<td >    Rituales: </td>

	<td >  

	<input type=text name=rituales value='$rituales' size=1 maxlength=1>

	</td>

	</tr>

	</table>

</div>

</td></tr>";?>

<tr><td class=datos2><a href=javascript:MostrarOcultar('texto7'); id=enlace7>Trasfondos</a>:</td>

<td>

<div class=ocultable id=texto7>

<table>

	<?php echo "<td> <input name=trasfondo1 type=text size=15 maxlength=15 value='$trasfondo1'> </td>

	<td >  

	<input type=text name=trasfondo1valor value='$trasfondo1valor' size=1 maxlength=1>

	</td>

	</tr>

	<tr>

	<td> <input name=trasfondo2 type=text size=15 maxlength=15 value='$trasfondo2'> </td>

	<td >  

	<input type=text name=trasfondo2valor value='$trasfondo2valor' size=1 maxlength=1>

	</td>

	</tr>

	<tr>

	<td> <input name=trasfondo3 type=text size=15 maxlength=15 value='$trasfondo3'> </td>

	<td >  

	<input type=text name=trasfondo3valor value='$trasfondo3valor' size=1 maxlength=1>

	</td>

	</tr>

	<tr>

	<td> <input name=trasfondo4 type=text size=15 maxlength=15 value='$trasfondo4'> </td>

	<td >  

	<input type=text name=trasfondo4valor value='$trasfondo4valor' size=1 maxlength=1>

	</td>

	</tr>

	<tr>

	<td> <input name=trasfondo5 type=text size=15 maxlength=15 value='$trasfondo5'> </td>

	<td >  

	<input type=text name=trasfondo5valor value='$trasfondo5valor' size=1 maxlength=1>

	</td>"; ?>

</table>

</div>

</td></tr>

<tr><td class=datos2><a href=javascript:MostrarOcultar('texto8'); id=enlace8>Dones</a>:</td>

<td>

<div class=ocultable id=texto8>

	<table> 

	<?php echo "
	<tr><td><input name=don1 type=text size=15 maxlength=30 value='$don1'></td></tr>
	<tr><td><input name=don2 type=text size=15 maxlength=30 value='$don2'></td></tr>
	<tr><td><input name=don3 type=text size=15 maxlength=30 value='$don3'></td></tr>
	<tr><td><input name=don4 type=text size=15 maxlength=30 value='$don4'></td></tr>
	<tr><td><input name=don5 type=text size=15 maxlength=30 value='$don5'></td></tr>
	<tr><td><input name=don6 type=text size=15 maxlength=30 value='$don6'></td></tr>
	<tr><td><input name=don7 type=text size=15 maxlength=30 value='$don7'></td></tr>
	<tr><td><input name=don8 type=text size=15 maxlength=30 value='$don8'></td></tr>
	<tr><td><input name=don9 type=text size=15 maxlength=30 value='$don9'></td></tr>
	<tr><td><input name=don10 type=text size=15 maxlength=30 value='$don10'></td></tr>
	<tr><td><input name=don10 type=text size=15 maxlength=30 value='$don11'></td></tr> 
	<tr><td><input name=don10 type=text size=15 maxlength=30 value='$don12'></td></tr> 
	<tr><td><input name=don10 type=text size=15 maxlength=30 value='$don13'></td></tr> 
	<tr><td><input name=don10 type=text size=15 maxlength=30 value='$don14'></td></tr> 
	<tr><td><input name=don10 type=text size=15 maxlength=30 value='$don15'></td></tr> 
	<tr><td><input name=don10 type=text size=15 maxlength=30 value='$don16'></td></tr> 
	<tr><td><input name=don10 type=text size=15 maxlength=30 value='$don17'></td></tr> 
	<tr><td><input name=don10 type=text size=15 maxlength=30 value='$don18'></td></tr> 	<tr><td><input name=don10 type=text size=15 maxlength=30 value='$don19'></td></tr>
	<tr><td><input name=don10 type=text size=15 maxlength=30 value='$don20'></td></tr>"; ?>

	</table>
 
</div>

</td></tr>

<tr><td class=datos2><a href="javascript:MostrarOcultar('texto9');" id="enlace1">Renombre</a>:</td>

<td>

<div class="ocultable" id="texto9">

	<table>

	<tr>

	<td > <a title='Temporal / Permanente'>Gloria</a>: </td>

	<td>

	<?php echo "

	<input type=text name=gloriat value='$gloriat' size=1 maxlength=1>

	/

	<input type=text name=gloriap value='$gloriap' size=1 maxlength=1>
	</td>

	</tr>

	<tr>

	<td > <a title='Temporal / Permanente'>Honor</a>: </td>

	<td>

	<input type=text name=honort value='$honort' size=1 maxlength=1>

	/

	<input type=text name=honorp value='$honorp' size=1 maxlength=1>

</td>

	</tr>

	<tr>

	<td > <a title='Temporal / Permanente'> Sabidur&iacute;a</a>: </td>

	<td>

	<input type=text name=sabiduriat value='$sabiduriat' size=1 maxlength=1>

	 / 

	<input type=text name=sabiduriap value='$sabiduriap' size=1 maxlength=1>" ?>

	</td>

	</tr>

	</table>

</div>

</td></tr>

<tr><td class=datos2><a href="javascript:MostrarOcultar('texto10');" id="enlace10">Poder</a>:</td>

<td>

<div class="ocultable" id="texto10">

	<table>

	<tr>

	<?php echo "

	<td > <a title='Permanente / Gastado'>Rabia</a>: </td>

	<td >

	<input type=text name=rabiap value='$rabiap' size=1 maxlength=1>

	/

	<input type=text name=rabiag value='$rabiag' size=1 maxlength=1>

	</td>

	</tr>

	<tr>

	<td > <a title='Permanente / Gastado'>Gnosis</a>: </td>


	<td >

	<input type=text name=gnosisp value='$gnosisp' size=1 maxlength=1>

	/

	<input type=text name=gnosisg value='$gnosisg' size=1 maxlength=1>

	</td>

	</tr>

	<tr>

	<td > <a title='Permanente / Gastado'>Voluntad</a>: </td>

	<td >

	<input type=text name=fvp value='$fvp' size=1 maxlength=1>

	/

	<input type=text name=fvg value='$fvg' size=1 maxlength=1>

	</td>

	</tr>"?>

	</table>

</div>

</td></tr>

<tr><td class=datos2>Rango:</td><td><?php echo "<input type=text name=rango size=20 maxlength=20 value='$rango'>"; ?></td></tr>

<tr><td class=datos2>Estado:</td><td><select name="estado">
<option value="En activo">Activo/a</option>
<option value="Paradero desconocido">Desaparecido/a</option>
<option value="Cad&aacute;ver">Muerto/a</option>
<option value="A&uacute;n por aparecer">A&uacute;n por aparecer</option>
</select></td></tr>

<tr><td class=datos2>Imagen:</td><td><?php echo "<input type=text name=img size=20 maxlength=1000 value='$img'>"; ?></td></tr>

<tr><td class=datos2>Afiliaci&oacute;n:</td><td>

<?php

include("../libreria.php");

mysql_select_db("$bdd", $link);
$consulta ="SELECT tipo FROM afiliacion ORDER BY id";
$query = mysql_query ($consulta, $link);

echo "<select name=tipo>";

while ($reg = mysql_fetch_row($query)) {

foreach($reg as $cambia) {
echo "<option value='$cambia'>",$cambia,"</option>";
}
}

echo "</select>";

?></td></tr>

<tr><td class=datos2>Cumplea&ntilde;os:</td><td><?php echo "<input type=text name=cumple size=20 maxlength=20 value='$cumple'>"; ?></td></tr>

<tr><td class=datos2>Texto 1:</td><td><textarea name="text1"><?php echo $text1; ?></textarea></td></tr>
<tr><td class=datos2>Texto 2:</td><td><textarea name="text2"><?php echo $text2; ?></textarea></td></tr>

<tr><td class=datos2>¿PJ?:</td><td><select name="kes">
<option value="pj">Personaje Jugador</option>
<option value="pnj">Personaje NO Jugador</option>
</select></td></tr>

</table>

<br>

<?php echo "<input type=hidden name=pj value='$pj'>"; ?>

<input class="boton1" type="submit" value="Editar">


<input class="boton1" type="button" onClick="javascript: history.go(-1)" value="Regresar">

</form>

</center>

</div>

<? }
else { 
?>

<center>ERROR: ACCESO DENEGADO</center>

<? } ?>

</body>

</html>