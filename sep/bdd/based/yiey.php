<?php

$nombre = $_POST['nombre'];
$alias = $_POST['alias'];

if($alias == "") { $alias = $nombre; }

$raza = $_POST['raza'];
$manada = $_POST['manada'];

$jugador = $_POST['jugador'];
$auspicio = $_POST['auspicio'];
$totem = $_POST['totem'];

$cronica = $_POST['cronica'];
$tribu = $_POST['tribu'];
$concepto = $_POST['concepto'];

$naturaleza = $_POST['naturaleza'];
$conducta = $_POST['conducta'];

$nombregarou = $_POST['nombregarou'];
$temamusical = $_POST['temamusical'];
$temaurl = $_POST['temaurl'];

// Atributos

$fuerza = $_POST['fuerza'];
$destreza = $_POST['destreza'];
$resistencia = $_POST['resistencia'];

$carisma = $_POST['carisma'];
$manipulacion = $_POST['manipulacion'];
$apariencia = $_POST['apariencia'];

$percepcion = $_POST['percepcion'];
$inteligencia = $_POST['inteligencia'];
$astucia = $_POST['astucia'];

// Talentos

$alerta = $_POST['alerta'];
$atletismo = $_POST['atletismo'];
$callejeo = $_POST['callejeo'];
$empatia = $_POST['empatia'];
$esquivar = $_POST['esquivar'];
$expresion = $_POST['expresion'];
$impulsprimario = $_POST['impulsoprimario'];
$intimidacion = $_POST['intimidacion'];
$pelea = $_POST['pelea'];
$subterfugio = $_POST['subterfugio'];

$talento1extra = $_POST['talento1extra'];
$talento2extra = $_POST['talento2extra'];

$talento1valor = $_POST['talento1valor'];
$talento2valor = $_POST['talento2valor'];

// Tecnicas

$armascc = $_POST['armascc'];
$armasdefuego = $_POST['armasdefuego'];
$conducir = $_POST['conducir'];
$etiqueta = $_POST['etiqueta'];
$interpretacion = $_POST['interpretacion'];
$liderazgo = $_POST['liderazgo'];
$reparaciones = $_POST['reparaciones'];
$sigilo = $_POST['sigilo'];
$supervivencia = $_POST['supervivencia'];
$tratoanimales = $_POST['tratoanimales'];

$tecnica1extra = $_POST['tecnica1extra'];
$tecnica2extra = $_POST['tecnica2extra'];

$tecnica1valor = $_POST['tecnica1valor'];
$tecnica2valor = $_POST['tecnica2valor'];

// Conocimientos

$ciencias = $_POST['ciencias'];
$enigmas = $_POST['enigmas']; 
$informatica = $_POST['informatica'];
$investigacion = $_POST['investigacion'];
$leyes = $_POST['leyes'];
$linguistica = $_POST['linguistica'];
$medicina = $_POST['medicina'];
$ocultismo = $_POST['ocultismo'];
$politica = $_POST['politica'];
$rituales = $_POST['rituales'];

$conoci1extra = $_POST['conoci1extra'];
$conoci2extra = $_POST['conoci2extra'];

$conoci1valor = $_POST['conoci1valor'];
$conoci2valor = $_POST['conoci2valor'];

// Ventajas

$trasfondo1 = $_POST['trasfondo1'];
$trasfondo2 = $_POST['trasfondo2'];
$trasfondo3 = $_POST['trasfondo3'];
$trasfondo4 = $_POST['trasfondo4'];
$trasfondo5 = $_POST['trasfondo5'];

$trasfondo1valor = $_POST['trasfondo1valor'];
$trasfondo2valor = $_POST['trasfondo2valor'];
$trasfondo3valor = $_POST['trasfondo3valor'];
$trasfondo4valor = $_POST['trasfondo4valor'];
$trasfondo5valor = $_POST['trasfondo5valor'];

// Poder

// RENOMBRE

$gloriap = $_POST['gloriap'];
$honorp = $_POST['honorp'];
$sabiduriap = $_POST['sabiduriap'];

// COSAS

$rabiap = $_POST['rabiap'];
$gnosisp = $_POST['gnosisp'];
$fvp = $_POST['fvp'];

//
$clan = $_POST['clan'];
$rango = $_POST['rango'];
$estado = $_POST['estado'];

$img = $_POST['img'];

$tipo = $_POST['tipo'];
$cumple = $_POST['cumple'];

$text1 = $_POST['text1'];
$notas = $_POST['notas'];
if ($notas == "") { $notas = "Personaje agregado desde la web."; }
$px = $_POST['experiencia'];

////////////

$sistema = $_POST['sistema'];
$fera = $_POST['fera'];

switch($sistema) {
	default:
		$tipoDePoder = "dones";
		break;
	case "Vampiro":
		$tipoDePoder = "disciplinas";
		break;
}

$dones = "$tipoDePoder;-$_POST[don1]-$_POST[don2]-$_POST[don3]-$_POST[don4]-$_POST[don5]-$_POST[don6]-$_POST[don7]-$_POST[don8]-$_POST[don9]-$_POST[don10]";

if ($dones == "dones;----------" OR $dones == "disciplinas;----------") {
	$dones = "";
}

$numeroRituales = "$_POST[ritual1]-$_POST[ritual2]-$_POST[ritual3]-$_POST[ritual4]-$_POST[ritual5]-$_POST[ritual6]-$_POST[ritual7]-$_POST[ritual8]-$_POST[ritual9]-$_POST[ritual10]";

if ($numeroRituales == "----------") {
	$numeroRituales = "";
}

$meridef = "$_POST[merodef1]-$_POST[merodef2]-$_POST[merodef3]-$_POST[merodef4]-$_POST[merodef5]-$_POST[merodef6]-$_POST[merodef7]-$_POST[merodef8]";

if ($meridef == "-------") {
	$meridef = "";
}

//
$inventory = "$_POST[objeto1]-$_POST[objeto2]-$_POST[objeto3]-$_POST[objeto4]-$_POST[objeto5]-$_POST[objeto6]";

if ($inventory == "-----") {
	$inventory = "";
}

$kes = $_POST['kes'];


//FIN

?>