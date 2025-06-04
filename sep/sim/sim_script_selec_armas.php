<?php
$numero = isset($numero) ? $numero : 0;

$consulta ="
SELECT id, name, habilidad 
FROM nuevo3_objetos 
WHERE habilidad NOT LIKE ''

ORDER BY bonus ASC";

$IdConsulta = mysqli_query($link, $consulta);
$NFilas = mysqli_num_rows($IdConsulta);
for ($i = 0; $i < $NFilas; $i++) {
    $ResultQuery = mysqli_fetch_assoc($IdConsulta);

$idxx[$numero] = $ResultQuery["id"];
$nombrexx[$numero] = $ResultQuery["name"];
$skillxx[$numero] = $ResultQuery["habilidad"];

/* CONVERTIMOS LA HABILIDAD EN PENES */

switch ($skillxx[$numero]) {

	case "Pelea";
		$skillxx[$numero] = "P";
		break;
	case "Atletismo";
	case "Arrojar";
		$skillxx[$numero] = "A";
		break;
	case "Cuerpo a Cuerpo";
		$skillxx[$numero] = "C";
		break;
	case "Tiro con Arco";
		$skillxx[$numero] = "T";
		break;
	case "Armas de Fuego";
		$skillxx[$numero] = "F";
		break;
	case "Informática";
		$skillxx[$numero] = "I";
		break;

}

/* HEMOS TERMINADO */

echo "<option value='$idxx[$numero]'>$nombrexx[$numero] ($skillxx[$numero])</option>
";
$numero = $numero+1;


}

?>