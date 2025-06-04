<?php

$consulta ="SELECT id, tipo FROM inventario WHERE tipo LIKE '$clasifi' ORDER BY id";

$IdConsulta = mysql_query($consulta, $link);
$morir = mysql_fetch_array($IdConsulta);

$idx = $morir[0];
$tipox = $morir[1];

switch($clasifi) {

case $tipox:

$clasifi = $idx;
break;


} 

?>