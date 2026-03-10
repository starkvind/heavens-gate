<?php

	$arrayFormas1 = array();

	$consultaPJ1Forma = "SELECT forma FROM vw_sim_forms WHERE raza LIKE '$fera1';";
	$queryPJ1Forma = mysql_query ($consultaPJ1Forma, $link);
	$rowsPJ1Forma = mysql_num_rows ($queryPJ1Forma);

	for($iForma1=0;$iForma1<$rowsPJ1Forma;$iForma1++) {
		$resultadoPJ1Forma = mysql_fetch_array($queryPJ1Forma);
		$arrayFormas1[$iForma1] = $resultadoPJ1Forma["forma"];
	}

	if ($rowsPJ1Forma != 0) {
		$randKeyForm1 = array_rand($arrayFormas1);	
		$nameForma1 = $arrayFormas1[$randKeyForm1];
		$forma1 = $nameForma1;
	} else {
		$forma1 = "Hominido";
	}
	
	$arrayFormas2 = array();

	$consultaPJ2Forma = "SELECT forma FROM vw_sim_forms WHERE raza LIKE '$fera2';";
	$queryPJ2Forma = mysql_query ($consultaPJ2Forma, $link);
	$rowsPJ2Forma = mysql_num_rows ($queryPJ2Forma);

	for($iForma2=0;$iForma2<$rowsPJ2Forma;$iForma2++) {
		$resultadoPJ2Forma = mysql_fetch_array($queryPJ2Forma);
		$arrayFormas2[$iForma2] = $resultadoPJ2Forma["forma"];
	}

	if ($rowsPJ2Forma != 0) {
		$randKeyForm2 = array_rand($arrayFormas2);	
		$nameForma2 = $arrayFormas2[$randKeyForm2];
		$forma2 = $nameForma2;
	} else {
		$forma2 = "Hominido";
	}
?>
