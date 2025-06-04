<?php
$numero = isset($numero) ? $numero : 0;

$consulta ="SELECT id, name, bonus, destreza FROM nuevo3_objetos WHERE tipo LIKE 2 ORDER BY bonus ASC";

$IdConsulta = mysqli_query($consulta, $link);
$NFilas = mysqli_num_rows($IdConsulta);
for($i=0;$i<$NFilas;$i++) {
$ResultQuery = mysqli_fetch_assoc($IdConsulta);

$idxx[$numero] = $ResultQuery["id"];
$nombrexx[$numero] = $ResultQuery["name"];
$bonusxx[$numero] = $ResultQuery["bonus"];
$dexx [$numero] = $ResultQuery["destreza"];


echo "<option value='$idxx[$numero]'>$nombrexx[$numero] (+$bonusxx[$numero])</option>
";
$numero = $numero+1;


}

?>