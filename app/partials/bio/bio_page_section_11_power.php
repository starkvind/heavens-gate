<?php

/* --------------------------------------------------------------- */
// Requiere: $link = conexion mysqli y $idPersonaje definido

$iconos = [
    'dones' => 'img/ui/icons/icon_claws.png',
    'disciplinas' => 'img/ui/icons/icon_fangs.png',
    'rituales' => 'img/ui/icons/icon_book.png',
    // Puedes anadir mas tipos aqui si aparecen (como taumaturgia, etc)
];

// Consulta los poderes desde el bridge
$stmt = $link->prepare("SELECT power_kind, power_id, power_level FROM bridge_characters_powers WHERE character_id = ? ORDER BY power_kind ASC");
$stmt->bind_param('i', $characterId);
$stmt->execute();
$result = $stmt->get_result();

$listaPoderes = [];

while ($row = $result->fetch_assoc()) {
    $tipo = $row['power_kind'];
    $poderId = $row['power_id'];
	$poderLvl = $row['power_level'];
    $listaPoderes[$tipo][] = ['id' => $poderId, 'level' => $poderLvl];
}

$stmt->close();

function fetch_power_sort_rows(mysqli $link, string $table, string $valueField, array $powers): array {
    $ids = [];
    foreach ($powers as $power) {
        $id = (int)($power['id'] ?? 0);
        if ($id > 0) {
            $ids[$id] = true;
        }
    }

    if (empty($ids)) {
        return [];
    }

    $idList = implode(',', array_map('intval', array_keys($ids)));
    $map = [];
    $query = "SELECT id, name, $valueField AS sort_value FROM $table WHERE id IN ($idList)";
    if ($rs = $link->query($query)) {
        while ($row = $rs->fetch_assoc()) {
            $map[(int)$row['id']] = [
                'sort_value' => (int)($row['sort_value'] ?? 999),
                'name' => (string)($row['name'] ?? ''),
            ];
        }
        $rs->close();
    }

    return $map;
}

if (isset($listaPoderes['dones']) && is_array($listaPoderes['dones'])) {
    $giftSortRows = fetch_power_sort_rows($link, 'fact_gifts', 'rank', $listaPoderes['dones']);
    usort($listaPoderes['dones'], static function (array $a, array $b) use ($giftSortRows): int {
        $rowA = $giftSortRows[(int)($a['id'] ?? 0)] ?? ['sort_value' => 999, 'name' => ''];
        $rowB = $giftSortRows[(int)($b['id'] ?? 0)] ?? ['sort_value' => 999, 'name' => ''];
        $rankA = (int)$rowA['sort_value'];
        $rankB = (int)$rowB['sort_value'];

        if ($rankA !== $rankB) {
            return $rankA <=> $rankB;
        }

        $nameCmp = strcasecmp((string)$rowA['name'], (string)$rowB['name']);
        if ($nameCmp !== 0) {
            return $nameCmp;
        }

        return ((int)($a['id'] ?? 0)) <=> ((int)($b['id'] ?? 0));
    });
}

if (isset($listaPoderes['rituales']) && is_array($listaPoderes['rituales'])) {
    $riteSortRows = fetch_power_sort_rows($link, 'fact_rites', 'level', $listaPoderes['rituales']);
    usort($listaPoderes['rituales'], static function (array $a, array $b) use ($riteSortRows): int {
        $rowA = $riteSortRows[(int)($a['id'] ?? 0)] ?? ['sort_value' => 999, 'name' => ''];
        $rowB = $riteSortRows[(int)($b['id'] ?? 0)] ?? ['sort_value' => 999, 'name' => ''];
        $levelA = (int)$rowA['sort_value'];
        $levelB = (int)$rowB['sort_value'];

        if ($levelA !== $levelB) {
            return $levelA <=> $levelB;
        }

        $nameCmp = strcasecmp((string)$rowA['name'], (string)$rowB['name']);
        if ($nameCmp !== 0) {
            return $nameCmp;
        }

        return ((int)($a['id'] ?? 0)) <=> ((int)($b['id'] ?? 0));
    });
}


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
				$campos = "id, name, rank";
				$linkBase = "muestradon";
				break;
			/*
			case 'disciplinas':
				$tabla = "fact_discipline_powers";
				$campos = "id, name, level, disc";
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
				$campos = "id, name, level";
				$linkBase = "seerite";
				break;
			default:
				continue 2;
		}

		$icono = isset($iconos[$tipo]) ? $iconos[$tipo] : 'img/ui/icons/default.jpg';

		foreach ($poderes as $poderData) {
			$idPoder = $poderData['id'];
			$levelBridge = $poderData['level'];
			$query = "SELECT $campos FROM $tabla WHERE id = ? LIMIT 1";
			$stmt = $link->prepare($query);
			$stmt->bind_param('i', $idPoder);
			$stmt->execute();
			$result = $stmt->get_result();

			if ($result->num_rows > 0) {
				$data = $result->fetch_assoc();
				// Nombre del poder segun tipo
				$nombre = isset($data['name']) ? $data['name'] : (isset($data['name']) ? $data['name'] : '???');
				$level = isset($data['level']) ? $data['level'] : null;
				$rango = isset($data['rank']) ? $data['rank'] : (isset($data['rango']) ? $data['rango'] : null);
				
				if ($tipo == "disciplinas") {
					if ($levelBridge !== null && $levelBridge >= 0) {
						$levelFinal = intval($levelBridge);
					} elseif (isset($data['level'])) {
						$levelFinal = intval($data['level']);
					} else {
						$levelFinal = null;
					}
				} elseif ($tipo == "rituales") {
					$levelFinal = $level;
				} else {
					// Dones: siempre mostrar el rango propio del don (no el nivel del bridge)
					$levelFinal = (int)$rango;
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
							<img class='valign bio-inline-icon' src='" . htmlspecialchars($icono) . "' title='" . ucfirst(htmlspecialchars($tipo)) . "'>
							" . htmlspecialchars($nombre);

					if ($tipo == "disciplinas" or $tipo == "rituales") {
						if ($levelFinal !== null) {
							echo "<div class='bio-gem-wrap'>
									<img src='img/ui/gems/attr/gem-attr-0" . $levelFinal . ".png' class='bio-gem' />
								  </div>";
						}
					} else {
						echo "<div class='bio-inline-level'>{$levelFinal}</div>";
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



