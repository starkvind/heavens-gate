<?php
	/* 
	// CONTAMOS LOS LUCHADORES EN LA TABLA DE CLASIFICACIÓN
	$consultaPJS = "SELECT * FROM punkte";
	$IdConsultaPJS = mysqli_query($consultaPJS, $link);
	$filasPJS 	= mysqli_num_rows($IdConsultaPJS); // NUMERO DE LUCHADORES EN LA TABLA DE PUNKTUACION
	// CONTAMOS LOS COMBATES
	$consulta = "SELECT combates FROM punkte ORDER BY combates DESC LIMIT 1";
	$IdConsulta = mysqli_query($consulta, $link);
	$resultMaximosCombates = mysqli_fetch_assoc($IdConsulta);
	$combatesTotales = $resultMaximosCombates["combates"];
		if ($filasPJS > 6) {
			$queryRandomChara = "SELECT t1.id FROM pjs1 AS t1 INNER JOIN punkte t2 WHERE t2.combates < $combatesTotales AND t1.kes LIKE 'pj' ORDER BY t1.nombre"; // INTENTAMOS PILLAR AL QUE MENOS COMBATES LLEVA
			echo "MAS DE 6 PERSONAJES, BIATCH";
		} else {
			$queryRandomChara = "SELECT id FROM pjs1 WHERE kes LIKE 'pj' ORDER BY nombre"; // SI NO HAY COMBATES, PILLAMOS CUALQUIER FEO DE LA BDD
			echo "AUN NO HAY SUFICIENTES PERSONAJES, BITCH ($filasPJS)";
		} */
	// VAMOS A LO RANDOM	
	$randomCharaArray = array();
	$queryRandomChara 	= "SELECT id FROM pjs1 WHERE kes LIKE 'pj' ORDER BY nombre";
	$idQueryRandomChara 	= mysqli_query($queryRandomChara, $link);
	$filasQueryRandomChara 	= mysqli_num_rows($idQueryRandomChara);
	for($i=0;$i<$filasQueryRandomChara;$i++) {
		$resultQueryRandomchara = mysqli_fetch_assoc($idQueryRandomChara);
		$randomCharaArray[$i] = $resultQueryRandomchara["id"];
	}
	$value = $randomCharaArray;
	$rand_keys= array_rand($value,2);
	
	//if ($value[$rand_keys[0]] == $value[$rand_keys[1]]) { // NO DEJAMOS QUE REPITA PJS
	//	$rand_keys= array_rand($value,2);
	//}
	
	$nombre1 = $value[$rand_keys[0]];
	$nombre2 = $value[$rand_keys[1]];	
	
?>