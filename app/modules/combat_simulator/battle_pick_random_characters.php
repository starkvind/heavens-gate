<?php
	include_once("sim_character_scope.php");
	$cronicaNotInSQL = sim_chronicle_not_in_sql('c.chronicle_id');
	/* 
	// CONTAMOS LOS LUCHADORES EN LA TABLA DE CLASIFICACIÓN
	$consultaPJS = "SELECT * FROM fact_sim_character_scores";
	$IdConsultaPJS = mysql_query($consultaPJS, $link);
	$filasPJS 	= mysql_num_rows($IdConsultaPJS); // NUMERO DE LUCHADORES EN LA TABLA DE PUNKTUACION
	// CONTAMOS LOS COMBATES
	$consulta = "SELECT battles FROM fact_sim_character_scores ORDER BY battles DESC LIMIT 1";
	$IdConsulta = mysql_query($consulta, $link);
	$resultMaximosCombates = mysql_fetch_array($IdConsulta);
	$combatesTotales = $resultMaximosCombates["battles"];
		if ($filasPJS > 6) {
			$queryRandomChara = "SELECT t1.id FROM vw_sim_characters AS t1 INNER JOIN fact_sim_character_scores t2 ON t2.character_id = t1.id WHERE t2.battles < $combatesTotales AND t1.kes LIKE 'pj' ORDER BY t1.nombre"; // INTENTAMOS PILLAR AL QUE MENOS COMBATES LLEVA
			echo "MAS DE 6 PERSONAJES, BIATCH";
		} else {
			$queryRandomChara = "SELECT id FROM vw_sim_characters WHERE kes LIKE 'pj' ORDER BY nombre"; // SI NO HAY COMBATES, PILLAMOS CUALQUIER FEO DE LA BDD
			echo "AUN NO HAY SUFICIENTES PERSONAJES, BITCH ($filasPJS)";
		} */
	// VAMOS A LO RANDOM	
	$randomCharaArray = array();
	$queryRandomChara 	= "SELECT v.id FROM vw_sim_characters v INNER JOIN fact_characters c ON c.id = v.id WHERE v.kes LIKE 'pj' $cronicaNotInSQL ORDER BY v.nombre";
	$idQueryRandomChara 	= mysql_query($queryRandomChara, $link);
	$filasQueryRandomChara 	= mysql_num_rows($idQueryRandomChara);
	for($i=0;$i<$filasQueryRandomChara;$i++) {
		$resultQueryRandomchara = mysql_fetch_array($idQueryRandomChara);
		$randomCharaArray[$i] = $resultQueryRandomchara["id"];
	}
	$value = $randomCharaArray;

	if (count($value) < 2) {
		$nombre1 = "";
		$nombre2 = "";
		return;
	}

	$rand_keys= array_rand($value,2);
	
	//if ($value[$rand_keys[0]] == $value[$rand_keys[1]]) { // NO DEJAMOS QUE REPITA PJS
	//	$rand_keys= array_rand($value,2);
	//}
	
	$nombre1 = $value[$rand_keys[0]];
	$nombre2 = $value[$rand_keys[1]];	
	
?>
