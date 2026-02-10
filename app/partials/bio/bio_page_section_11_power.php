<?php

/* --------------------------------------------------------------- */
// Requiere: $link = conexión mysqli y $idPersonaje definido

$iconos = [
    'dones' => 'img/ui/powers/don.gif',
    'disciplinas' => 'img/ui/powers/disc.gif',
    'rituales' => 'img/ui/powers/rite.gif',
    // Puedes añadir más tipos aquí si aparecen (como taumaturgia, etc)
];

// Consulta los poderes desde el bridge
$stmt = $link->prepare("SELECT tipo_poder, poder_id, poder_lvl FROM bridge_characters_powers WHERE personaje_id = ? ORDER BY tipo_poder, poder_lvl ASC");
$stmt->bind_param('i', $characterId);
$stmt->execute();
$result = $stmt->get_result();

$listaPoderes = [];

while ($row = $result->fetch_assoc()) {
    $tipo = $row['tipo_poder'];
    $poderId = $row['poder_id'];
	$poderLvl = $row['poder_lvl'];
    $listaPoderes[$tipo][] = ['id' => $poderId, 'nivel' => $poderLvl];
}

$stmt->close();


function build_power_url(mysqli $link, string $linkBase, string $idPoder): string {
    $idPoder = (int)$idPoder;
    switch ($linkBase) {
        case 'muestradon':
            return pretty_url($link, 'fact_gifts', '/powers/gift', $idPoder);
        case 'tipodisc':
            return pretty_url($link, 'dim_discipline_types', '/powers/discipline/type', $idPoder);
        case 'seerite':
            return pretty_url($link, 'fact_rites', '/powers/rite', $idPoder);
        default:
            return "?p=$linkBase&b=$idPoder";
    }
}

if (count($listaPoderes) > 0) {
	echo "<div class='bioSheetPowers'>"; // Poderes de la Hoja ~~ #SEC11
	echo "<fieldset class='bioSeccion'><legend>$titlePowers</legend>";
	// Mostrar por tipo
	foreach ($listaPoderes as $tipo => $poderes) {
		
		//echo "<h4 class='characterPowerSection'>".ucfirst($tipo)."</h4>";

		switch ($tipo) {
			case 'dones':
				$tabla = "fact_gifts";
				$campos = "id, nombre, rango";
				$linkBase = "muestradon";
				break;
			/*
			case 'disciplinas':
				$tabla = "fact_discipline_powers";
				$campos = "id, name, nivel, disc";
				$linkBase = "disciplina";
				break;
			*/
			case 'disciplinas':
				$tabla = "dim_discipline_types";
				$campos = "id, name";
				$linkBase = "tipodisc";
				break;
			case 'rituales':
				$tabla = "fact_rites";
				$campos = "id, name, nivel";
				$linkBase = "seerite";
				break;
			default:
				continue 2;
		}

		$icono = isset($iconos[$tipo]) ? $iconos[$tipo] : 'img/ui/icons/default.jpg';

		foreach ($poderes as $poderData) {
			$idPoder = $poderData['id'];
			$nivelBridge = $poderData['nivel'];
			$query = "SELECT $campos FROM $tabla WHERE id = ? LIMIT 1";
			$stmt = $link->prepare($query);
			$stmt->bind_param('i', $idPoder);
			$stmt->execute();
			$result = $stmt->get_result();

			if ($result->num_rows > 0) {
				$data = $result->fetch_assoc();
				// Nombre del poder según tipo
				$nombre = isset($data['nombre']) ? $data['nombre'] : (isset($data['name']) ? $data['name'] : '???');
				$nivel = isset($data['nivel']) ? $data['nivel'] : null;
				$rango = isset($data['rango']) ? $data['rango'] : null;
				
				if ($tipo == "disciplinas") {
					if ($nivelBridge !== null && $nivelBridge >= 0) {
						$nivelFinal = intval($nivelBridge);
					} elseif (isset($data['nivel'])) {
						$nivelFinal = intval($data['nivel']);
					} else {
						$nivelFinal = null;
					}
				} elseif ($tipo == "rituales") {
					$nivelFinal = $nivel;
				} else {
					$nivelFinal = (int)$rango;
				}

				// Enlace e icono
                $href = build_power_url($link, $linkBase, (string)$idPoder);
				$tipAttr = "";
				if ($tipo === "dones") {
					$tipAttr = " class='hg-tooltip' data-tip='don' data-id='" . (int)$idPoder . "'";
				} elseif ($tipo === "rituales") {
					$tipAttr = " class='hg-tooltip' data-tip='rite' data-id='" . (int)$idPoder . "'";
				}
				echo "<a href='$href' target='_blank'$tipAttr>
						<div class='bioSheetPower'>
							<img class='valign' style='width:13px; height:13px;' src='" . htmlspecialchars($icono) . "' title='" . ucfirst(htmlspecialchars($tipo)) . "'>
							" . htmlspecialchars($nombre);

					if ($tipo == "disciplinas" or $tipo == "rituales") {
						if ($nivelFinal !== null) {
							echo "<div style='float:right'>
									<img src='img/ui/gems/attr/gem-attr-0" . $nivelFinal . ".png' style='padding-top: 2px;' />
								  </div>";
						}
					} else {
						echo "<div style='float:right;font-size: 8px;padding-top: 2px;'>{$nivelFinal}</div>";
					}
				echo "  </div>
					  </a>";
			}

			$stmt->close();
		}
	}
	echo "</fieldset>";
	echo "</div>"; // Cerramos Poderes ~~
}
?>
