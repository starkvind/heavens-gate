<?php

/* LIMITE DE LOS COJONES */

$hora = $horax;

/* HORA ACTUAL */

$kualki = date("H:i");
$kualki2 = date("d-m-Y");

$diasmes = date("t");

$minutosMaximos = 2;

/*  CALCULAMOS EL LIMITE Y SEPARAMOS LA HORA EN CACHITOS */

$wowx = explode(":", $hora);

$limiteh = $wowx[0];
$limitem = $wowx[1]+$minutosMaximos;
$limited = $wowx[2];
$limites = $wowx[3];
$limitea = $wowx[4];

/* SI LOS MINUTOS SON MAYORES QUE 60, LOS VOLVEMOS A 0 O ALGO */

if ($limitem >= 60) {

	$limitem = $limitem-60;

	if ($limitem < 10) {

		$limitem = "0$limitem";

	}

	$limiteh = $limiteh+1;

	if ($limiteh >= 24) {

	$limiteh = 00;

	$limited = $limited+1;

		if ($limited > $diasmes) {

			$limited = 01;
			$limites = $limitemes+1;

				if ($limites > 12) {

					$limitea = $limitea+1;

				 }

		}

	}

}

$limiteg = "$limiteh:$limitem";
$fechalimite = "$limited-$limites-$limitea";


$hora1 = strtotime( $kualki );
$hora2 = strtotime( $limiteg ); 

/* echo "<br/>Hora actual: $kualki, $kualki2<br/>Limite: $limiteg, $fechalimite<br/><br/><br/>$hora1<br/>$hora2<br/><br/>"; */

if ($hora1 > $hora2) {

	/* echo "Puedes luchar porque es una hora mayor a la del limite"; */
	$podesluchar = 1;


} elseif ($fechalimite != $kualki2) {

		/* echo "Puedes luchar. Es una hora menor a la hora del limite pero es diferente fecha"; */
		$podesluchar = 1;

} else {

		/* echo "No puedes, es una hora menor a la hora del limite en la misma fecha"; */
		$podesluchar = 0;


}




?>