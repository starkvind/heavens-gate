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

$pj = $_POST['pj'];

include("../libreria.php");

mysql_select_db("$bdd", $link);

$consulta = "SELECT nombre,tipo,valor,poseedor,img,descri FROM items WHERE nombre LIKE '$pj'";

$query = mysql_query ($consulta, $link);

$da = mysql_fetch_array($query);

// Comenzamos a meter todo mecagonto 

$nombre = $da[0];
$tipo = $da[1];
$valor = $da[2];

$poseedor = $da[3];
$img = $da[4];
$descri = $da[5];

?>

<div class="cuerpo">

<h2> Editar Objetos </h2>

<center>

<form action="editar3.php" method="POST">

<table>

<tr>

<?php

echo "<td class=datos2>Nombre:</td><td><input type=text name=nombre size=20 maxlength=40 value='$nombre'></td></tr>"; ?>

<tr><td class=datos2>Tipo:</td><td><select name=tipo><option value=Arma>Arma</option><option value=Protector>Protector</option><option value=Fetiche>Fetiche</option><option value=Miscelaneo>Miscelaneo</option></select></td></tr>

<?php echo "
<tr><td class=datos2>Valor:</td><td><input type=text name=valor size=20 maxlength=20 value='$valor'></td></tr>

<tr><td class=datos2>Bonificaci&oacute;n</td><td> 

<select name=bonus>

<option value=0>0</option>
<option value=1>+1</option>
<option value=2>+2</option>
<option value=3>+3</option>
<option value=4>+4</option>
<option value=5>+5</option>

</select>

</td></tr>

<tr><td class=datos2>Poseedor:</td><td><input type=text name=poseedor size=20 maxlength=20 value='$poseedor'></td></tr>"; ?>

<tr><td class=datos2>Imagen:</td><td><?php echo "<input type=text name=img size=20 maxlength=1000 value='$img'>"; ?></td></tr>

</table>

<br>

<textarea name=descri rows=5 cols=30><?php echo $descri; ?></textarea>

<br><br>

<?php echo "<input type=hidden name=pj value='$pj'>"; ?>

<input class=boton1 type=submit value=Editar>

<input class=boton1 type=button onClick="javascript: history.go(-1)" value=Regresar>

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