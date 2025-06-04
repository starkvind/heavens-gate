<?php

$consulta ="SELECT id, name, bonus, destreza FROM nuevo3_objetos WHERE tipo LIKE 2 ORDER BY bonus ASC";

$IdConsulta = mysql_query($consulta, $link);
$NFilas = mysql_num_rows($IdConsulta);
for($i=0;$i<$NFilas;$i++) {
$ResultQuery = mysql_fetch_array($IdConsulta);

$idxx[$numero] = $ResultQuery["id"];
$nombrexx[$numero] = $ResultQuery["name"];
$bonusxx[$numero] = $ResultQuery["bonus"];
$dexx [$numero] = $ResultQuery["destreza"];

$numerito = $numerito+1;

echo "<option value='$idxx[$numero]'>$nombrexx[$numero] (+$bonusxx[$numero])</option>
";
$numero = $numero+1;


}

?>