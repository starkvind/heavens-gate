<?PHP
// Función que obtiene el nombre de un mes
   function nombreMes ($mes)
   {
      $meses = array ("enero", "febrero", "marzo", "abril", "mayo",
                      "junio", "julio", "agosto", "septiembre",
                      "octubre", "noviembre", "diciembre");
      $i=0;
      $enc=false;
      while ($i<12 and !$enc)
      {
         if ($i == $mes-1)
            $enc = true;
         else
            $i++;
      }
      return ($meses[$i]);
   }

//Pasar el dia de la semana a castellano

$diasemana = date("D");
$diames = date("d");
$mes = date("m");
$year = date("Y");

switch($diasemana) {

	case "Mon" : $diasemana="Lunes"; break;
	case "Tue" : $diasemana="Martes"; break;
	case "Wed" : $diasemana="Mi&eacute;rcoles"; break;
	case "Thu" : $diasemana="Jueves"; break;
	case "Fri" : $diasemana="Viernes"; break;
	case "Sat" : $diasemana="S&aacute;bado"; break;
	case "Sun" : $diasemana="Domingo"; break;

}


?>

<?php echo $diasemana ?>

<?PHP
   $dia  = date ("j");
   $mes  = date ("n");
   $anyo = date ("Y");
   print ("" . $dia . " de " . nombreMes($mes) . " de " . $anyo . "");
?>