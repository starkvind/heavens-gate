<?php

	$randomWeaponArray = array();
	
	$queryRandomWeap 		= "SELECT id FROM nuevo3_objetos WHERE habilidad NOT LIKE ''";
	$idQueryRandomWeap 		= mysqli_query($queryRandomWeap, $link);
	$filasQueryRandomWeap 	= mysqli_num_rows($idQueryRandomWeap);
	
	for($i=0;$i<$filasQueryRandomWeap;$i++) {
		$resultQueryRandomWeap = mysqli_fetch_assoc($idQueryRandomWeap);
		$randomWeapArray[$i] = $resultQueryRandomWeap["id"];
	}
	$value = $randomWeapArray;
	$rand_keys= array_rand($value,2);
	
	$arma1 = $value[$rand_keys[0]];
	$arma2 = $value[$rand_keys[1]];
	
	$arma11 = $value[$rand_keys[0]];
	$arma21 = $value[$rand_keys[1]];


?>