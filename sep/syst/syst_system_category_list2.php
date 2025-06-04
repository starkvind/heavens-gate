<?php 
	$systemCategory = $_GET['b']; 
	include ("syst_system_category_convert.php");
?>

<?php 
	if ($systemCategoryName != "Nada") { 
		$pageSect = "Sistema"; // PARA CAMBIAR EL TITULO A LA PAGINA
		$pageTitle2	= $systemCategoryName; 
?>
	
<table width="100%">

<tr> <td colspan="3"> <h2> <?php echo $systemCategoryName; ?>  </h2> </td> </tr>

<tr><td>&nbsp;</td></tr>

<tr><td style="width:75%;">
<fieldset style="border: 1px solid #0000CC;">
<legend style="border: 1px solid #0000CC; padding: 3px; background-color:#000066">Informaci&oacute;n</legend>
<br/>

<?php

/* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */

$consulta ="SELECT * FROM nuevo_sistema WHERE name LIKE '$systemCategoryName' LIMIT 1";

$IdConsulta = mysql_query($consulta, $link);
$NFilas = mysql_num_rows($IdConsulta);

for($i=0;$i<$NFilas;$i++) {
$ResultQuery = mysql_fetch_array($IdConsulta);
print("
		".$ResultQuery["descripcion"]."
");

$haveForms = $ResultQuery["formas"];
}

/* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~*/
?>
</fieldset>
</td>

<?php if ($systemCategoryName != "Vampiro") { /* INICIO IF SI ES VAMPIRO */ ?>

<?php if ($haveForms >= 1) { ?>

<td style="width: 25%;">
<fieldset style="border: 1px solid #0000CC;">
<legend style="border: 1px solid #0000CC; padding: 3px; background-color:#000066">Formas</legend>
<?php

/* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */

if ($haveForms == 2) { 

	include ("syst_system_list_more_wereform.php");

} else {

$consulta ="SELECT * FROM nuevo_formas WHERE raza LIKE '$systemCategoryName' LIMIT 100";

$IdConsulta = mysql_query($consulta, $link);
$NFilas = mysql_num_rows($IdConsulta);

if ($NFilas != 0) {
	echo "<ul>";
for($i=0;$i<$NFilas;$i++) {
$ResultQuery = mysql_fetch_array($IdConsulta);
print("
		<li>
		<a href='index.php?p=verforma&amp;b=".$ResultQuery["id"]."' title='Forma de $systemCategoryName.' alt='$systemCategoryName'>
		".$ResultQuery["forma"]."
		</a>
		</li>
");

}
	echo "</ul>";
}

}

/* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~*/
?>
</fieldset>
</td></tr>

<?php } ?>

<?php

$numregistros = 0;

$consulta ="SELECT id, name FROM nuevo_razas WHERE sistema LIKE '$systemCategoryName' ORDER BY id";

$IdConsulta = mysql_query($consulta, $link);
$NFilas = mysql_num_rows($IdConsulta);

if ($NFilas != 0) {
	echo "
		<tr>
			<td colspan='2'><h4>Razas</h3></td>
		</tr>
		";
	$numregistros = $numregistros + mysql_num_rows ($IdConsulta);
	}
	
for($i=0;$i<$NFilas;$i++) {
$ResultQuery = mysql_fetch_array($IdConsulta);
/* tc=1  > Razas */
print("
	<tr>
		<td class='lefay' colspan='2'>
		<a href='index.php?p=versist&amp;tc=1&amp;b=".$ResultQuery["id"]."'> 
		<img src='img/system-race.gif' alt='".$ResultQuery["name"]."' title='".$ResultQuery["name"]."'/>
		".$ResultQuery["name"]."
		</a>
		</td>
	</tr>
");

}

$consulta ="SELECT id, name FROM nuevo_auspicios WHERE sistema LIKE '$systemCategoryName' ORDER BY id";

switch($systemCategoryName) {
	case "Bastet":
		$auspiceName = "Pryios";
		break;
	case "Ananasi":
		$auspiceName = "Facciones";
		break;
	case "Changelling":
		$auspiceName = "Aspectos";
		break;
	default:
		$auspiceName = "Auspicios";
		break;
		
}

$IdConsulta = mysql_query($consulta, $link);
$NFilas = mysql_num_rows($IdConsulta);

if ($NFilas != 0) {
	echo "
		<tr>
			<td colspan='2'><h4>$auspiceName</h3></td>
		</tr>
		";
	$numregistros = $numregistros + mysql_num_rows ($IdConsulta);
	}

for($i=0;$i<$NFilas;$i++) {
$ResultQuery = mysql_fetch_array($IdConsulta);
/* tc=2  > Auspicios */
print("
	<tr>
		<td class='lefay' colspan='2'>
		<a href='index.php?p=versist&amp;tc=2&amp;b=".$ResultQuery["id"]."'>
		<img src='img/system-auspice.gif' alt='".$ResultQuery["name"]."' title='".$ResultQuery["name"]."'/>
		".$ResultQuery["name"]."
		</a>
		</td>
	</tr>
");

}

$consulta ="SELECT id, name, afiliacion FROM nuevo_tribus WHERE sistema LIKE '$systemCategoryName' ORDER BY id";

switch($systemCategoryName) {
	case "Ananasi":
		$tribeName = "Aspectos";
		break;
	case "Mokolé":
		$tribeName = "Oleadas";
		break;
	case "Changelling":
		$tribeName = "Linajes";
		break;
	default:
		$tribeName = "Tribus";
		break;
		
}

$IdConsulta = mysql_query($consulta, $link);
$NFilas = mysql_num_rows($IdConsulta);
/* tc=3  > Tribus */
if ($NFilas != 0) {
	echo "
		<tr>
			<td colspan='2'><h4>$tribeName</h4></td>
		</tr>
		";
	$numregistros = $numregistros + mysql_num_rows ($IdConsulta);
	}

for($i=0;$i<$NFilas;$i++) {
$ResultQuery = mysql_fetch_array($IdConsulta);

print("
	<tr>
		<td class='lefay' colspan='2'>
		<a href='index.php?p=versist&amp;tc=3&amp;b=".$ResultQuery["id"]."'>
		<img src='img/system-tribe.gif' alt='".$ResultQuery["name"]."' title='".$ResultQuery["name"]."'/>
		".$ResultQuery["name"]."
		</a>
		</td>
	</tr>
");

}
// Añadimos la linea Zhong Lung si somos Mokole porque somos asi de guays
if ($systemCategoryName == "Mokolé") {
print("
	<tr>
		<td class='lefay' colspan='2'>
		<a href='index.php?p=versist&amp;tc=3&amp;b=47'>
		<img src='img/system-tribe.gif' alt='Zhong Lung' title='Zhong Lung'/>
		Zhong Lung
		</a>
		</td>
	</tr>
");
}

$consultaNameMisc ="SELECT type FROM nuevo_miscsistemas WHERE sistema LIKE '$systemCategoryName' LIMIT 1";
$idConsultaNamemMisc = mysql_query($consultaNameMisc, $link);
$nameMisc = mysql_fetch_array($idConsultaNamemMisc);
$nameMisc2 = $nameMisc["type"];
//////
$consulta ="SELECT id, name, type FROM nuevo_miscsistemas WHERE sistema LIKE '$systemCategoryName' ORDER BY id";

$IdConsulta = mysql_query($consulta, $link);
$NFilas = mysql_num_rows($IdConsulta);
/* tc=3  > Tribus */
if ($NFilas != 0) {

	echo "
		<tr>
			<td colspan='2'><h4>$nameMisc2</h4></td>
		</tr>
		";
	$numregistros = $numregistros + mysql_num_rows ($IdConsulta);
	}

for($i=0;$i<$NFilas;$i++) {
$ResultQuery = mysql_fetch_array($IdConsulta);
print("
	<tr>
		<td class='lefay' colspan='2'>
		<a href='index.php?p=versist&amp;tc=4&amp;b=".$ResultQuery["id"]."'>
		<img src='img/system-clan.gif' alt='".$ResultQuery["name"]."' title='".$ResultQuery["name"]."'/>
		".$ResultQuery["name"]."
		</a>
		</td>
	</tr>
");

}
//////////////////////////////////////////////////////////////////////////

} else { /* FIN IF SI ES VAMPIRO */ 

	include("syst_system_list_vampire.php");

}

?>

<tr><td>&nbsp;</td></tr>

</table>

<?php 
print ("<p align='right'>Categor&iacute;as:".""." $numregistros</p>");
?>
<?php } ?>