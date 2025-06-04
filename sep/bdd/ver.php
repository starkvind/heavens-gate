<html>

<head>

<title>Heaven's Gate</title>

<link href="style.css" rel="stylesheet" type="text/css">

</head>

<body>

<div class="cuerpo">

<center>

<div class="formtable">

<div> <h2> Ver </h2> </div>

<form action="bdd2.php" method="get">

<div class="ajustcelda"> Personaje: </div>

<div class="ajustcelda">

<select name="pj" onChange="cambio()">

<?php

include("libreria.php");

mysql_select_db("$bdd", $link);
$consulta ="SELECT nombre FROM pjs1 ORDER BY nombre";
$query = mysql_query ($consulta, $link);

while ($reg = mysql_fetch_row($query)) {

foreach($reg as $cambia) {
echo "<option value='$cambia'>",$cambia,"</option>";
}
}

?>


</select>

</div>

<div class="ajustcelda"> <input class="boton1" type="submit" value="Observar"> </div>

<div class="ajustcelda"> <input class="boton1" type="button" onClick="javascript: history.go(-1)" value="Regresar"> </div>

</form>

</div>

<br/>
<a href=bdd1.php> Volver </a>

</center>

</div>

</body>

</html>