<?php

	$randomProtArray = array();
	
	$queryRandomProt 		= "SELECT id FROM nuevo3_objetos WHERE tipo LIKE 2";
	$idQueryRandomProt 		= mysql_query($queryRandomProt, $link);
	$filasQueryRandomProt 	= mysql_num_rows($idQueryRandomProt);
	
	for($i=0;$i<$filasQueryRandomProt;$i++) {
		$resultQueryRandomProt = mysql_fetch_array($idQueryRandomProt);
		$randomProtArray[$i] = $resultQueryRandomProt["id"];
	}
	$value = $randomProtArray;
	$rand_keys= array_rand($value,2);
	
	$protec1 = $value[$rand_keys[0]];
	$protec2 = $value[$rand_keys[1]];

	$protec11 = $value[$rand_keys[0]];
	$protec21 = $value[$rand_keys[1]];


?>