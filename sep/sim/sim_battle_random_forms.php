<?php

	$arrayFormas1 = array();

	$consultaPJ1Forma = "SELECT forma FROM nuevo_formas WHERE raza LIKE '$fera1';";
	$queryPJ1Forma = mysql_query ($consultaPJ1Forma, $link);
	$rowsPJ1Forma = mysql_num_rows ($queryPJ1Forma);

	for($iForma1=0;$iForma1<$rowsPJ1Forma;$iForma1++) {
		$resultadoPJ1Forma = mysql_fetch_array($queryPJ1Forma);
		$arrayFormas1[$iForma1] = $resultadoPJ1Forma["forma"];
	}

	if ($rowsPJ1Forma != 0) {
		$randKeysForm1 = array_rand($arrayFormas1,$rowsPJ1Forma);	
		$nameForma1 = $arrayFormas1[$randKeysForm1[0]];
		$forma1 = $nameForma1;
	} else {
		$forma1 = "Homínido";
	}
	
	$arrayFormas2 = array();

	$consultaPJ2Forma = "SELECT forma FROM nuevo_formas WHERE raza LIKE '$fera2';";
	$queryPJ2Forma = mysql_query ($consultaPJ2Forma, $link);
	$rowsPJ2Forma = mysql_num_rows ($queryPJ2Forma);

	for($iForma2=0;$iForma2<$rowsPJ2Forma;$iForma2++) {
		$resultadoPJ2Forma = mysql_fetch_array($queryPJ2Forma);
		$arrayFormas2[$iForma2] = $resultadoPJ2Forma["forma"];
	}

	if ($rowsPJ2Forma != 0) {
		$randKeysForm2 = array_rand($arrayFormas2,$rowsPJ2Forma);	
		$nameForma2 = $arrayFormas2[$randKeysForm2[0]];
		$forma2 = $nameForma2;
	} else {
		$forma2 = "Homínido";
	}
?>