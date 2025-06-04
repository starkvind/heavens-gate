<?php
/* Código antiguo 2013 < */
// Mostrar biografías similares
/*
if (isset($numberFilasSameBio)) {
	for ($nSameBio = 0; $nSameBio < $numberFilasSameBio; $nSameBio++) {
		echo "<a href='?p=muestrabio&amp;b=" . htmlspecialchars($sameBioId[$nSameBio]) . "' target='_blank'>
				<div class='bioSheetPower'>
					<img class='valign' style='width:13px; height:13px;' src='" . htmlspecialchars($bioSameIcon) . "'>
					" . htmlspecialchars($sameBioName[$nSameBio]) . "
					<div style='float:right;font-size:8px;padding-top:2px;'>#" . htmlspecialchars($sameBioId[$nSameBio]) . "</div>
				</div>
			</a>";
	}
}
*/

/*

$idsFamily = explode("-", $bioFamily);
$cantidadFamily = count($idsFamily);
$iconoFamilySelect = "img/kek.gif";
$tablaDeFamily = "pjs1";
$nombreFamily = "nombre";
$linkFamily = "muestrabio";

// Preparar la consulta SQL
$stmt = $link->prepare("SELECT $nombreFamily FROM $tablaDeFamily WHERE id = ? LIMIT 1");

for ($nfamily = 0; $nfamily < $cantidadFamily; $nfamily++) {
    $sacarParentesco = explode("|", $idsFamily[$nfamily]);
    $familyIdSelect = $sacarParentesco[0];
    
    // Bind del parámetro para prevenir inyecciones SQL
    $stmt->bind_param('s', $familyIdSelect);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $ResultQueryFamily = $result->fetch_assoc();
        $nombreFamilySelect = htmlspecialchars($ResultQueryFamily['nombre']);
        $parentesco = htmlspecialchars($sacarParentesco[1]);

        echo "<a href='?p=" . htmlspecialchars($linkFamily) . "&amp;b=" . htmlspecialchars($familyIdSelect) . "' target='_blank'>
                <div class='bioSheetPower'>
                    <img class='valign' style='width:13px; height:13px;' src='" . htmlspecialchars($iconoFamilySelect) . "'>
                    $nombreFamilySelect
                    <div style='float:right;font-size:9px;padding-top:0px;'>$parentesco</div>
                </div>
              </a>";
    }
}

$stmt->close();
*/


/* Código nuevo 2025 */
/* $iconoRelacion = "img/kek.gif"; // Puedes cambiar este icono según la categoría (tag) si quieres

foreach ($relaciones as $rel) {
    $relId = htmlspecialchars($rel['direction'] === 'outgoing' ? $rel['target_id'] : $rel['source_id']);
    $relNombre = htmlspecialchars($rel['nombre']);
    $relAlias = !empty($rel['alias']) ? " (" . htmlspecialchars($rel['alias']) . ")" : "";
    $relTipo = htmlspecialchars($rel['relation_type']);
    $relLink = "muestrabio"; // o el que estés usando en la bio actual

    echo "<a href='?p=" . $relLink . "&amp;b=" . $relId . "' target='_blank'>
            <div class='bioSheetPower'>
                <img class='valign' style='width:13px; height:13px;' src='" . $iconoRelacion . "'>
                $relNombre
                <div style='float:right;font-size:9px;padding-top:0px;'>$relTipo</div>
            </div>
          </a>";
} */

// $bioGender es la variable de género.

//echo $bioGender;

function traducirRelacion($tipo, $direccion, $generoRelacionado, $generoBio) {
	if (!function_exists('g')) {
		function g($genero, $masc, $fem, $neutro) {
			if ($genero === 'f') return $fem;
			if ($genero === 'i') return $neutro;
			return $masc;
		}
	}

    if ($direccion === 'outgoing') {
        switch ($tipo) {
            case "Asesino":        return "Víctima";			
            case "Amo":            return "Dueño";
            case "Padre":
            case "Madre":          return g($generoRelacionado, "Hijo", "Hija", "Hije");
            case "Hermano":        return g($generoRelacionado, "Hermano", "Hermana", "Hermane");
			case "Tío":            return g($generoRelacionado, "Sobrino", "Sobrina", "Sobrine");
            case "Primo":          return g($generoRelacionado, "Primo", "Prima", "Prime");
            case "Abuelo":         return g($generoRelacionado, "Abuelo", "Abuela", "Abuele");
			case "Aliado":         return g($generoBio, "Aliado", "Aliada", "Aliade");
			case "Creación":       return g($generoRelacionado, "Creador", "Creadora", "Creadore");
			case "Amigo":          return g($generoBio, "Amigo", "Amiga", "Amigue");
			case "Enemigo":        return g($generoBio, "Enemigo", "Enemiga", "Enemigue");
			case "Extorsionador":  return g($generoRelacionado, "Extorsionado", "Extorsionada", "Extorsionade");
			case "Traidor":		   return g($generoRelacionado, "Traicionado", "Traicionada", "Traicionade");
            default:               return $tipo;
        }
    } else {
        switch ($tipo) {
            case "Asesino":        return g($generoRelacionado, "Asesino", "Asesina", "Asesine");
            case "Traidor":        return g($generoRelacionado, "Traidor", "Traidora", "Traidore");
            case "Superior":       return g($generoRelacionado, "Subordinado", "Subordinada", "Subordinade");
            case "Mentor":         return g($generoRelacionado, "Alumno", "Alumna", "Alumne");
            case "Amo":            return g($generoRelacionado, "Esclavo", "Esclava", "Esclave");
            case "Hijo":           return g($generoBio, "Padre", "Madre", "Progenitor");
            case "Hermano":        return g($generoBio, "Hermano", "Hermana", "Hermane");
			case "Tío":            return g($generoRelacionado, "Tío", "Tía", "Tíe");
			case "Primo":          return g($generoRelacionado, "Primo", "Prima", "Prime");
            case "Creación":	   return g($generoRelacionado, "Creador", "Creadora", "Creadore");
            case "Protegido":      return g($generoRelacionado, "Protector", "Protectora", "Protectore");
            case "Subordinado":    return g($generoRelacionado, "Jefe", "Jefa", "Jefe");
            case "Salvador":       return g($generoRelacionado, "Héroe", "Heroína", "Heroe");
            case "Abuelo":         return g($generoBio, "Nieto", "Nieta", "Niete");
			case "Aliado":         return g($generoRelacionado, "Aliado", "Aliada", "Aliade");
			case "Amigo":          return g($generoRelacionado, "Amigo", "Amiga", "Amigue");
			case "Enemigo":        return g($generoRelacionado, "Enemigo", "Enemiga", "Enemigue");
            default:               return $tipo;
        }
    }
}

$grupos = [
    'amistad' => [],
    'conflicto' => [],
    'familia' => [],
    'alianza' => [],
    'otro' => []
];

foreach ($relaciones as $rel) {
    $tag = strtolower($rel['tag'] ?? 'otro');
    if (!isset($grupos[$tag])) {
        $grupos['otro'][] = $rel;
    } else {
        $grupos[$tag][] = $rel;
    }
}

$iconoRelacion = "img/kek.gif"; // genérico, cámbialo si quieres por tipo

$etiquetas = [
    'amistad' => 'Amistades y vínculos afectivos',
    'conflicto' => 'Enemistades y agresiones',
    'familia' => 'Vínculos familiares',
    'alianza' => 'Alianzas, lealtades o pactos',
    'otro' => 'Otras relaciones'
];

// Imprimir cada grupo
foreach ($grupos as $categoria => $lista) {
    if (count($lista) === 0) continue;
    echo "<h4 style='margin: 1em 0.2em; font-weight: bold;'>{$etiquetas[$categoria]}</h4>";
		foreach ($lista as $rel) {
			$relId     = htmlspecialchars($rel['direction'] === 'outgoing' ? $rel['target_id'] : $rel['source_id']);
			$relNombre = htmlspecialchars($rel['nombre']);
			$relImg    = htmlspecialchars($rel['img']);
			$relGender  = htmlspecialchars($rel['genero_pj']);
			$relAlias  = !empty($rel['alias']) ? " (" . htmlspecialchars($rel['alias']) . ")" : "";
			$relTipo   = htmlspecialchars($rel['relation_type']);
			$relLink   = "muestrabio";

			$tipoLegible = traducirRelacion($relTipo, $rel['direction'], $relGender, $bioGender);
			echo "<a href='?p=$relLink&amp;b=$relId' target='_blank'>
					<div class='bioSheetPower'>
						<img class='valign' style='width:13px; height:13px;' src='$relImg'>
						$relNombre
						<div style='float:right;font-size:9px;padding-top:0px;'>$tipoLegible</div>
					</div>
				  </a>";
		}
    echo "<br style='width: 100%; display: block;' />";

}
	
/*

foreach ($grupos as $categoria => $lista) {
    if (count($lista) === 0) continue;

    echo "<h4 style='margin: 0.8em; font-weight: bold;'>{$etiquetas[$categoria]}</h4>";

    foreach ($lista as $rel) {
        $relId = htmlspecialchars($rel['direction'] === 'outgoing' ? $rel['target_id'] : $rel['source_id']);
        $relNombre = htmlspecialchars($rel['nombre']);
		$relImg = htmlspecialchars($rel['img']);
        $relAlias = !empty($rel['alias']) ? " (" . htmlspecialchars($rel['alias']) . ")" : "";
        $relTipo = htmlspecialchars($rel['relation_type']);
        $relLink = "muestrabio";

        echo "<a href='?p=$relLink&amp;b=$relId' target='_blank'>
                <div class='bioSheetPower'>
                    <img class='valign' style='width:13px; height:13px;' src='$relImg'>
                    $relNombre
                    <div style='float:right;font-size:9px;padding-top:0px;'>$relTipo</div>
                </div>
              </a>";
    }
	
	echo "<br style='width: 100%; display: block;' />";

}

*/

?>
