<?php
	include_once(__DIR__ . '/../../helpers/character_avatar.php');
	/*  Ãndice de secciones
			1.- Query en Base de Datos 		[#SEC01]
			2.- Foto del Personaje 			[#SEC02]
			3.- Datos bÃ¡sicos - Detalles	[#SEC03]
			4.- Parte superior de la Hoja	[#SEC04]
			5.- Atributos					[#SEC05]
			6.- Habilidades					[#SEC06]
			7.- Trasfondos y Ventajas		[#SEC07]
			8.- MÃ©ritos y Defectos			[#SEC08]
			9.- Renombre / Virtudes			[#SEC09]
		   10.- Fuerza de Voluntad			[#SEC10]
		   11.- Poderes - Dones, Discip.	[#SEC11]
		   12.- Rituales					[#SEC12]
		   13.- Inventario del personaje	[#SEC13]
		   14.- BiografÃ­as similares		 [#SEC14]
		   15.- Comentarios					[#SEC15]
	*/

	// Helpers (escape + fetch sin depender de mysqlnd)
	function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

	function column_exists(mysqli $link, string $table, string $column): bool {
		static $cache = [];
		$key = $table . ':' . $column;
		if (isset($cache[$key])) return $cache[$key];
		$ok = false;
		if ($st = $link->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?")) {
			$st->bind_param('ss', $table, $column);
			$st->execute();
			$st->bind_result($count);
			$st->fetch();
			$st->close();
			$ok = ((int)$count > 0);
		}
		$cache[$key] = $ok;
		return $ok;
	}

	function stmt_fetch_all_assoc_compat(mysqli_stmt $stmt): array {
		$out = [];

		// Camino rÃ¡pido (mysqlnd)
		if (method_exists($stmt, 'get_result')) {
			$res = @$stmt->get_result();
			if ($res instanceof mysqli_result) {
				while ($row = $res->fetch_assoc()) { $out[] = $row; }
				$res->free();
				return $out;
			}
		}

		// Fallback sin mysqlnd
		mysqli_stmt_store_result($stmt);
		$meta = mysqli_stmt_result_metadata($stmt);
		if (!$meta) return $out;

		$fields = [];
		$row = [];
		$bind = [];

		while ($field = mysqli_fetch_field($meta)) {
			$fields[] = $field->name;
			$row[$field->name] = null;
			$bind[] = &$row[$field->name];
		}
		mysqli_free_result($meta);

		call_user_func_array([$stmt, 'bind_result'], $bind);

		while (mysqli_stmt_fetch($stmt)) {
			$r = [];
			foreach ($fields as $f) { $r[$f] = $row[$f]; }
			$out[] = $r;
		}

		return $out;
	}

	function fetch_trait_values(mysqli $link, int $characterId): array {
		$map = [];
		if ($characterId <= 0) return $map;
		$sql = "SELECT trait_id, value FROM bridge_characters_traits WHERE character_id = ?";
		if ($st = $link->prepare($sql)) {
			$st->bind_param('i', $characterId);
			$st->execute();
			$res = $st->get_result();
			if ($res) {
				while ($row = $res->fetch_assoc()) {
					$map[(int)$row['trait_id']] = (int)$row['value'];
				}
				$res->free();
			}
			$st->close();
		}
		return $map;
	}



	function fetch_traits_by_type(mysqli $link, int $characterId, string $kind, bool $onlyNonZero = true): array {
		$out = [];
		if ($characterId <= 0) return $out;
		$sql = "SELECT t.id, t.name, f.value
				FROM bridge_characters_traits f
				INNER JOIN dim_traits t ON t.id = f.trait_id
				WHERE f.character_id = ? AND t.kind = ?";
		if ($onlyNonZero) $sql .= " AND f.value > 0";
		$sql .= " ORDER BY f.value DESC, t.name";
		if ($st = $link->prepare($sql)) {
			$st->bind_param('is', $characterId, $kind);
			$st->execute();
			$res = $st->get_result();
			if ($res) {
				while ($row = $res->fetch_assoc()) {
					$out[] = ['id'=>(int)$row['id'], 'name'=>(string)$row['name'], 'value'=>(int)$row['value']];
				}
				$res->free();
			}
			$st->close();
		}
		return $out;
	}

	// CÃ¡lculo de cÃ­rculos de habilidad, atributos, etc.
	if (!function_exists('createSkillCircle')) {
		function createSkillCircle(array $array, string $prefix): array {
			$result = [];
			foreach ($array as $value) {
				$baseDir = ($prefix === 'gem-pwr') ? 'img/ui/gems/pwr' : 'img/ui/gems/attr';
				$result[] = "<img class='bioAttCircle' src='{$baseDir}/{$prefix}-0{$value}.png'/>";
			}
			return $result;
		}
	}

	function fetch_traits_for_system_type(mysqli $link, int $characterId, int $systemId, string $kind, bool $bridgeOnly = false): array {
		$out = [];
		if ($characterId <= 0) return $out;
		if ($bridgeOnly) {
			// Monster: mostrar solo lo realmente guardado en bridge_characters_traits,
			// pero respetando el orden del set del sistema cuando exista.
			if ($systemId > 0) {
				$sql = "SELECT t.id, t.name, f.value, s.sort_order, t.classification
						FROM bridge_characters_traits f
						JOIN dim_traits t ON t.id = f.trait_id AND t.kind = ?
						LEFT JOIN fact_trait_sets s
							ON s.trait_id = t.id
						   AND s.system_id = ?
						   AND s.is_active = 1
						WHERE f.character_id = ?
						ORDER BY
							CASE WHEN s.sort_order IS NULL THEN 1 ELSE 0 END,
							COALESCE(NULLIF(CAST(SUBSTRING_INDEX(TRIM(t.classification), ' ', 1) AS UNSIGNED), 0), 9999),
							s.sort_order,
							t.name";
				if ($st = $link->prepare($sql)) {
					$st->bind_param('sii', $kind, $systemId, $characterId);
					$st->execute();
					$res = $st->get_result();
					if ($res) {
						while ($row = $res->fetch_assoc()) {
							$out[] = [
								'id' => (int)$row['id'],
								'name' => (string)$row['name'],
								'value' => (int)$row['value'],
							];
						}
						$res->free();
					}
					$st->close();
				}
				if (!empty($out)) return $out;
			}
			// Fallback sin set del sistema: orden nominal.
			$sql = "SELECT t.id, t.name, f.value
					FROM bridge_characters_traits f
					JOIN dim_traits t ON t.id = f.trait_id
					WHERE f.character_id = ? AND t.kind = ?
					ORDER BY t.name";
			if ($st = $link->prepare($sql)) {
				$st->bind_param('is', $characterId, $kind);
				$st->execute();
				$res = $st->get_result();
				if ($res) {
					while ($row = $res->fetch_assoc()) {
						$out[] = ['id'=>(int)$row['id'], 'name'=>(string)$row['name'], 'value'=>(int)$row['value']];
					}
					$res->free();
				}
				$st->close();
			}
			return $out;
		}
		$hasSet = false;
		if ($systemId > 0) {
			$sql = "SELECT t.id, t.name, COALESCE(f.value,0) AS value, s.sort_order, t.classification
					FROM fact_trait_sets s
					JOIN dim_traits t ON t.id = s.trait_id AND t.kind = ?
					LEFT JOIN bridge_characters_traits f ON f.trait_id = t.id AND f.character_id = ?
					WHERE s.system_id = ? AND s.is_active = 1
					ORDER BY
						COALESCE(NULLIF(CAST(SUBSTRING_INDEX(TRIM(t.classification), ' ', 1) AS UNSIGNED), 0), 9999),
						s.sort_order,
						t.name";
			if ($st = $link->prepare($sql)) {
				$st->bind_param('sii', $kind, $characterId, $systemId);
				$st->execute();
				$res = $st->get_result();
				if ($res) {
					while ($row = $res->fetch_assoc()) {
						$out[] = ['id'=>(int)$row['id'], 'name'=>(string)$row['name'], 'value'=>(int)$row['value']];
					}
					$res->free();
				}
				$st->close();
			}
			if (!empty($out)) $hasSet = true;
		}

		if ($hasSet && $systemId > 0) {
			// Append traits with value>0 not present in the set
			$sql = "SELECT t.id, t.name, f.value
					FROM bridge_characters_traits f
					JOIN dim_traits t ON t.id = f.trait_id AND t.kind = ?
					WHERE f.character_id = ? AND f.value > 0
					AND t.id NOT IN (SELECT trait_id FROM fact_trait_sets WHERE system_id = ? AND is_active = 1)
					ORDER BY f.value DESC, t.name";
			if ($st = $link->prepare($sql)) {
				$st->bind_param('sii', $kind, $characterId, $systemId);
				$st->execute();
				$res = $st->get_result();
				if ($res) {
					while ($row = $res->fetch_assoc()) {
						$out[] = ['id'=>(int)$row['id'], 'name'=>(string)$row['name'], 'value'=>(int)$row['value']];
					}
					$res->free();
				}
				$st->close();
			}
			return $out;
		}

		// Fallback: only existing values
		return fetch_traits_by_type($link, $characterId, $kind, false);
	}

	function order_trait_list(array $list, int $baseCount = 0): array {
		if ($baseCount <= 0) return $list;
		$base = array_slice($list, 0, $baseCount);
		$extras = array_slice($list, $baseCount);
		usort($extras, function($a, $b){
			$an = strtolower((string)($a['name'] ?? ''));
			$bn = strtolower((string)($b['name'] ?? ''));
			return $an <=> $bn;
		});
		return array_merge($base, $extras);
	}

	function fetch_system_detail_labels(mysqli $link, int $systemId): array {
		$labels = [];
		if ($systemId <= 0) return $labels;

		$columns = [];
		if ($stCols = $link->prepare("SHOW COLUMNS FROM bridge_systems_detail_labels")) {
			$stCols->execute();
			$rsCols = $stCols->get_result();
			if ($rsCols) {
				while ($col = $rsCols->fetch_assoc()) {
					$name = (string)($col['Field'] ?? '');
					if ($name !== '') $columns[$name] = true;
				}
				$rsCols->free();
			}
			$stCols->close();
		}
		if (empty($columns) || !isset($columns['system_id'])) return $labels;

		$candidates = [
			'label_breed',
			'label_auspice',
			'label_pack',
			'label_tribe',
			'label_clan',
			'label_pk_name',
			'label_social',
			'label_misc',
		];

		$selectCols = [];
		foreach ($candidates as $c) {
			if (isset($columns[$c])) $selectCols[] = $c;
		}
		if (empty($selectCols)) return $labels;

		$sql = "SELECT " . implode(', ', $selectCols) . " FROM bridge_systems_detail_labels WHERE system_id = ? LIMIT 1";
		if ($st = $link->prepare($sql)) {
			$st->bind_param('i', $systemId);
			$st->execute();
			$res = $st->get_result();
			if ($res && ($row = $res->fetch_assoc())) {
				foreach ($selectCols as $c) {
					$v = trim((string)($row[$c] ?? ''));
					if ($v !== '') $labels[$c] = $v;
				}
			}
			$st->close();
		}
		return $labels;
	}

	$characterId = isset($_GET['b']) ? (int)$_GET['b'] : 0; // Cogemos datos del GET "b"
	if ($characterId <= 0) {
		echo "<p class='bio-error-msg'>$mensajeDeError</p>"; // Mensaje de error en caso de introducir datos manualmente. Tomado del Cuerpo Trabajar
		exit;
	}

	$deathTable = null;
	if ($rs = $link->query("SHOW TABLES LIKE 'fact_characters_deaths'")) {
		if ($rs->num_rows > 0) $deathTable = 'fact_characters_deaths';
		$rs->close();
	}
	if ($deathTable === null && ($rs = $link->query("SHOW TABLES LIKE 'fact_characters_death'"))) {
		if ($rs->num_rows > 0) $deathTable = 'fact_characters_death';
		$rs->close();
	}

	$deathJoin = '';
	if ($deathTable !== null) {
		$deathJoin = " LEFT JOIN `{$deathTable}` fd ON fd.character_id = p.id ";
	}
	$orderData = "SELECT p.*, s.name AS system_label, COALESCE(fd.death_description, '') AS death_description
		FROM fact_characters p
		LEFT JOIN dim_systems s ON p.system_id = s.id
		{$deathJoin}
		WHERE p.id = ? LIMIT 1;"; // Elegimos al PJ de la Base de Datos
	$stmtMain = mysqli_prepare($link, $orderData);
	if (!$stmtMain) {
		echo "<p class='bio-error-msg'>$mensajeDeError</p>"; // Mensaje de error en caso de introducir datos manualmente. Tomado del Cuerpo Trabajar
		exit;
	}

	mysqli_stmt_bind_param($stmtMain, 'i', $characterId);
	mysqli_stmt_execute($stmtMain);

	// Evitar dependencia de get_result()/mysqlnd
	$rowsMain = stmt_fetch_all_assoc_compat($stmtMain);
	$NFilas = count($rowsMain);

	if ($NFilas > 0) { // Comenzamos chequeo de datos. Si no tenemos nada, mandamos un mensaje de error.
		foreach ($rowsMain as $dataResult) {
		// Empezamos a recolectar los datos. ~~ #SEC01
		// ================================================================== //
		// Datos básicos del personaje
			$characterIdDb = $dataResult["id"];
			$bioId 		   = $characterIdDb;
 			// ID del personaje. Aunque la tengamos en el get, mejor así.
			$bioName 	 = $dataResult["name"]; 		// Nombre completo del personaje.
			$bioAlias 	 = $dataResult["alias"]; 		// Alias del personaje, como le llaman.
			$bioPackName = $dataResult["garou_name"]; 	// Nombre de manada. Como "ClÃ¡usula", "Churrasco", "Chili-ChingÃ³n", etc.
			$bioPhoto	 = hg_character_avatar_url($dataResult["image_url"] ?? '', $dataResult["gender"] ?? ''); 	// Imagen del personaje.
			$bioType	 = $dataResult["kind"] ?? $dataResult["character_type_id"] ?? 0; // Tipo de personaje.
			$bioBday	 = ''; // Se calcula desde timeline en bio_page_section_01_data.php
			$bioConcept	 = $dataResult["concept"]; 		// Concepto del personaje.
			$bioNature	 = $dataResult["nature_id"]; 	// Naturaleza del personaje.
			$bioBehavior = $dataResult["demeanor_id"]; 	// Conducta del personaje.
			$bioText	 = $dataResult["info_text"]; 	// Texto escrito que habla sobre el personaje.
		// ================================================================== //
			$pageSect 	 = "Biograf&iacute;a";						// Para cambiar el titulo a la pagina.
			$pageTitle2	 = $bioName;						// TÃ­tulo de la PÃ¡gina
			setMetaFromPage($bioName . " | Personajes | Heaven's Gate", meta_excerpt($bioText), $bioPhoto, 'article');
			$titleInfo	 = "&nbsp;Informaci&oacute;n&nbsp;";		// Titulo de la seccion "Informacion"
			$titleId	 = "&nbsp;Detalles de $bioName&nbsp;";// Titulo de la seccion "Identificacion"
			$titleAttr	 = "&nbsp;Atributos&nbsp;";			// Titulo de la seccion "Atributos"
			$titleSkill	 = "&nbsp;Habilidades&nbsp;";		// Titulo de la seccion "Habilidades"
			$titleBackg	 = "&nbsp;Trasfondos&nbsp;";		// Titulo de la seccion "Trasfondos"
			$titleMerits = "&nbsp;M&eacute;ritos y Defectos&nbsp;";// Titulo de la seccion "Meritos y Defectos"
			$titleSocial = "&nbsp;Renombre&nbsp;"; 			// Titulo de la seccion "Social"
			$titleAdvant = "&nbsp;Estado&nbsp;";			// Titulo de la seccion "Estado"
			$titlePowers = "&nbsp;Poderes&nbsp;";			// Titulo de la seccion "Poderes"
			$titleItems	 = "&nbsp;Inventario&nbsp;";		// Titulo de la seccion "Inventario"
			$titleSameBio= "&nbsp;Relaciones de $bioName&nbsp;";// TÃ­tulo de la sección "Relaciones"
			$titleNebulo = "&nbsp;Nebulosa de relaciones&nbsp;";// TÃ­tulo de la sección "Nebulosa de relaciones"	
			$titleParticp= "&nbsp;Participaci&oacute;n&nbsp;";		// Titulo de la seccion "Participacion"		
		// ================================================================== //
		// Datos de jugador y crónica
			$bioPlayer	  = $dataResult["player_id"]; 	// Jugador al que pertenece el personaje.
			$bioChronic	  = $dataResult["chronicle_id"]; // Crónica a la que pertenece el personaje.
			$bioStatus	  = $dataResult["status"] ?? ""; 	// Estado legacy; puede no venir desde fact_characters.
			$bioDethCaus  = $dataResult["death_description"] ?? ""; // Causa de la muerte.
			$bioSheetRaw  = strtolower(trim((string)($dataResult["character_kind"] ?? $dataResult["kind"] ?? "")));
			$bioSheet	  = $bioSheetRaw; // Compatibilidad con cÃ³digo legacy.
			$bioIsMonster = in_array($bioSheetRaw, ["mon", "monster"], true);
			$bioHasSheet  = in_array($bioSheetRaw, ["pj", "mon", "monster"], true);
		// ================================================================== //
		// Datos de raza y alineamientos
			$bioRace	 = $dataResult["breed_id"]; 	// Raza a la que pertenece el personaje.
			$bioAuspice	 = $dataResult["auspice_id"]; 	// Auspicio al que pertenece el personaje.
			$bioTribe	 = $dataResult["tribe_id"]; 	// Tribu a la que pertenece el personaje.
			$bioRange	 = $dataResult["rank"]; 		// Rango de importancia del personaje en su organizaciÃ³n.
		// ================================================================== //
		// Ventajas y poderes
			$bioTotem	 = ""; 		// Tótem que guí­a al personaje.
			$bioTotemId  = (int)($dataResult["totem_id"] ?? 0);
		// GÃ©nero
			$bioGender	 = $dataResult["gender"];	// Género del personaje
		// TÃ­tulos de la sección Detalles		
			$titlePkName	= "Nombre Garou";		// TÃ­tulo del nombre Garou
		// Sistema, para nombres de detalles y tal.
			$bioSystem 	= (string)($dataResult["system_label"] ?? "");
			$bioSystemId = (int)($dataResult["system_id"] ?? 0);
			$systemDetailLabels = fetch_system_detail_labels($link, $bioSystemId);
		// Nombres de conceptos
			// ================================================================== //
			// Datos y nombre del sistema
			// IDENTIFICACION
			$titleBreed		= "Raza";
			$titleAuspice	= "Auspicio";
			$titlePack 		= "Manada";
			$titleTribe 	= "Tribu";
			$titleClan 		= "Clan";
			// ================================================================== //
			// Cambiamos títulos de secciones acorde al Sistema del PJ
			include ("app/partials/bio/bio_page_section_00_system.php"); // Utilizamos "include" para no sobrecargar la pÃ¡gina con cÃ³digo
		// ================================================================== //
		if ($bioHasSheet) { // <--- Inicio de comprobación si lleva hoja
		// ================================================================== //
		// Traits normalizados (bridge_characters_traits)
			$traitValues = fetch_trait_values($link, (int)$characterId);

			// Mapa trait_id por columna legacy (mismo mapping que migraciÃ³n)
			$traitIdMap = [
				'fuerza' => 1,
				'destreza' => 33,
				'resistencia' => 34,
				'carisma' => 35,
				'manipulacion' => 36,
				'apariencia' => 37,
				'percepcion' => 38,
				'inteligencia' => 39,
				'astucia' => 40,
				'alerta' => 5,
				'atletismo' => 6,
				'callejeo' => 7,
				'empatia' => 8,
				'esquivar' => 9,
				'expresion' => 10,
				'impulsprimario' => 11,
				'intimidacion' => 12,
				'pelea' => 13,
				'subterfugio' => 14,
				'armascc' => 43,
				'armasdefuego' => 44,
				'conducir' => 45,
				'etiqueta' => 46,
				'interpretacion' => 47,
				'liderazgo' => 52,
				'reparaciones' => 18,
				'sigilo' => 49,
				'supervivencia' => 50,
				'tratoanimales' => 51,
				'ciencias' => 19,
				'enigmas' => 20,
				'informatica' => 55,
				'investigacion' => 56,
				'leyes' => 57,
				'linguistica' => 58,
				'medicina' => 59,
				'ocultismo' => 60,
				'politica' => 61,
				'rituales' => 21,
			];

			$traitVal = function(string $col) use ($traitIdMap, $traitValues, $dataResult): int {
				$tid = $traitIdMap[$col] ?? 0;
				if ($tid > 0 && isset($traitValues[$tid])) return (int)$traitValues[$tid];
				return (int)($dataResult[$col] ?? 0);
			};

		// Atributos
			$bioArrayAtt = array(
				// FISICOS
				$traitVal('fuerza'),
				$traitVal('destreza'),
				$traitVal('resistencia'),	
				// SOCIALES				
				$traitVal('carisma'),
				$traitVal('manipulacion'),
				$traitVal('apariencia'),
				// MENTALES				
				$traitVal('percepcion'),
				$traitVal('inteligencia'),
				$traitVal('astucia'),
			);
		// ================================================================== //
		// Habilidades
		$bioTraitsByType = [
			'Atributos' => fetch_traits_for_system_type($link, (int)$characterId, (int)($dataResult['system_id'] ?? 0), 'Atributos', $bioIsMonster),
			'Talentos' => fetch_traits_for_system_type($link, (int)$characterId, (int)($dataResult['system_id'] ?? 0), 'Talentos', $bioIsMonster),
			'TÃ©cnicas' => fetch_traits_for_system_type($link, (int)$characterId, (int)($dataResult['system_id'] ?? 0), 'TÃ©cnicas', $bioIsMonster),
			'Conocimientos' => fetch_traits_for_system_type($link, (int)$characterId, (int)($dataResult['system_id'] ?? 0), 'Conocimientos', $bioIsMonster),
			'Trasfondos' => fetch_traits_for_system_type($link, (int)$characterId, (int)($dataResult['system_id'] ?? 0), 'Trasfondos', $bioIsMonster),
		];

		// Orden + extras al final (alfabÃ©tico)
		$bioTraitsByType['Talentos'] = order_trait_list($bioTraitsByType['Talentos'] ?? [], 10);
		$bioTraitsByType['TÃ©cnicas'] = order_trait_list($bioTraitsByType['TÃ©cnicas'] ?? [], 10);
		$bioTraitsByType['Conocimientos'] = order_trait_list($bioTraitsByType['Conocimientos'] ?? [], 10);

		$bioTraitImgsByType = [];
		foreach ($bioTraitsByType as $tipo => $list) {
			$vals = array_map(fn($t) => (int)($t['value'] ?? 0), $list);
			$bioTraitImgsByType[$tipo] = createSkillCircle($vals, 'gem-attr');
		}

		$bioAttrList = $bioTraitsByType['Atributos'] ?? [];
		$bioDebugTraitsEnabled = isset($_GET['debug_traits']) && (string)$_GET['debug_traits'] === '1';
		if ($bioDebugTraitsEnabled && $bioIsMonster) {
			echo "<div class='bio-debug-box'>";
			echo "<strong>DEBUG TRAITS (monster)</strong><br>";
			echo "system_id=" . (int)($dataResult['system_id'] ?? 0) . "<br>";
			foreach ($bioAttrList as $ix => $t) {
				$nm = h((string)($t['name'] ?? ''));
				$tv = (int)($t['value'] ?? 0);
				echo "#" . ($ix + 1) . " {$nm} (v={$tv})<br>";
			}
			echo "</div>";
		}
		$bioAttrCols = [
			array_slice($bioAttrList, 0, 3),
			array_slice($bioAttrList, 3, 3),
			array_slice($bioAttrList, 6, 3),
		];
		$bioAttrColImgs = [
			createSkillCircle(array_map(fn($t) => (int)($t['value'] ?? 0), $bioAttrCols[0]), 'gem-attr'),
			createSkillCircle(array_map(fn($t) => (int)($t['value'] ?? 0), $bioAttrCols[1]), 'gem-attr'),
			createSkillCircle(array_map(fn($t) => (int)($t['value'] ?? 0), $bioAttrCols[2]), 'gem-attr'),
		];

		$bioSkillCols = [
			'Talentos' => $bioTraitsByType['Talentos'] ?? [],
			'TÃ©cnicas' => $bioTraitsByType['TÃ©cnicas'] ?? [],
			'Conocimientos' => $bioTraitsByType['Conocimientos'] ?? [],
		];
		$bioSkillColImgs = [
			'Talentos' => $bioTraitImgsByType['Talentos'] ?? [],
			'TÃ©cnicas' => $bioTraitImgsByType['TÃ©cnicas'] ?? [],
			'Conocimientos' => $bioTraitImgsByType['Conocimientos'] ?? [],
		];

		$bioBackgrounds = $bioTraitsByType['Trasfondos'] ?? [];
		$bioBackVals = array_map(fn($t) => (int)($t['value'] ?? 0), $bioBackgrounds);
		$bioBackImgs = createSkillCircle($bioBackVals, 'gem-attr');

		// Legacy: construir array de habilidades fijo (30) + extras por tipo
		$bioArraySkiLegacy = [
			$traitVal('alerta'),
			$traitVal('atletismo'),
			$traitVal('callejeo'),
			$traitVal('empatia'),
			$traitVal('esquivar'),
			$traitVal('expresion'),
			$traitVal('impulsprimario'),
			$traitVal('intimidacion'),
			$traitVal('pelea'),
			$traitVal('subterfugio'),
			$traitVal('armascc'),
			$traitVal('armasdefuego'),
			$traitVal('conducir'),
			$traitVal('etiqueta'),
			$traitVal('interpretacion'),
			$traitVal('liderazgo'),
			$traitVal('reparaciones'),
			$traitVal('sigilo'),
			$traitVal('supervivencia'),
			$traitVal('tratoanimales'),
			$traitVal('ciencias'),
			$traitVal('enigmas'),
			$traitVal('informatica'),
			$traitVal('investigacion'),
			$traitVal('leyes'),
			$traitVal('linguistica'),
			$traitVal('medicina'),
			$traitVal('ocultismo'),
			$traitVal('politica'),
			$traitVal('rituales'),
		];
		$bioSkilImg = createSkillCircle($bioArraySkiLegacy, 'gem-attr');

		$talentoExtras = array_slice($bioTraitsByType['Talentos'] ?? [], 10);
		$tecnicaExtras = array_slice($bioTraitsByType['TÃ©cnicas'] ?? [], 10);
		$conociExtras = array_slice($bioTraitsByType['Conocimientos'] ?? [], 10);
		$bioExtraTalImg = createSkillCircle(array_map(fn($t) => (int)($t['value'] ?? 0), $talentoExtras), 'gem-attr');
		$bioExtraTecImg = createSkillCircle(array_map(fn($t) => (int)($t['value'] ?? 0), $tecnicaExtras), 'gem-attr');
		$bioExtraConImg = createSkillCircle(array_map(fn($t) => (int)($t['value'] ?? 0), $conociExtras), 'gem-attr');
// ================================================================== //
			}
	 	} // <---- Fin de comprobaciÃ³n si lleva hoja de PJ
		
		// ======================================================================================
		// Nueva preparaciÃ³n 2025. Tabla de relaciones.
		// ======================================================================================
		$relaciones = [];
		
		// Relaciones salientes
		$stmt1 = $link->prepare("SELECT cr.*, p2.name, p2.alias, p2.image_url, p2.gender, 'outgoing' as direction
								FROM bridge_characters_relations cr
								LEFT JOIN fact_characters p2 ON cr.target_id = p2.id
								WHERE cr.source_id = ?
								ORDER BY cr.relation_type");
		$stmt1->bind_param('i', $characterId);
		$stmt1->execute();
		$stm11_results = stmt_fetch_all_assoc_compat($stmt1);
		$relaciones = array_merge($relaciones, $stm11_results);

		// Relaciones entrantes
		$stmt2 = $link->prepare("SELECT cr.*, p2.name, p2.alias, p2.image_url, p2.gender, 'incoming' as direction
								FROM bridge_characters_relations cr
								LEFT JOIN fact_characters p2 ON cr.source_id = p2.id
								WHERE cr.target_id = ?
								ORDER BY cr.relation_type");
		$stmt2->bind_param('i', $characterId);
		$stmt2->execute();
		$stm12_results = stmt_fetch_all_assoc_compat($stmt2);
		$relaciones = array_merge($relaciones, $stm12_results);
		// Ordenar alfabÃ©ticamente por 'relation_type'
		usort($relaciones, function($a, $b) {
			return strcasecmp($a['relation_type'], $b['relation_type']);
		});
		
		$numRelaciones = count($relaciones);

		$killsAsKiller = [];
		if ($deathTable !== null) {
			$sqlKills = "SELECT
						fd.id AS death_id,
						fd.character_id AS victim_id,
						fd.death_type,
						fd.death_date,
						fd.death_description,
						fd.death_timeline_event_id,
						v.name AS victim_name,
						v.alias AS victim_alias,
						v.image_url AS victim_image,
						v.gender AS victim_gender,
						e.title AS event_title,
						e.event_date AS event_date
					FROM `{$deathTable}` fd
					INNER JOIN fact_characters v ON v.id = fd.character_id
					LEFT JOIN fact_timeline_events e ON e.id = fd.death_timeline_event_id
					WHERE fd.killer_character_id = ?
					  AND fd.character_id <> ?
					ORDER BY COALESCE(fd.death_date, e.event_date) DESC, fd.id DESC";
			if ($stKills = $link->prepare($sqlKills)) {
				$stKills->bind_param('ii', $characterId, $characterId);
				$stKills->execute();
				$killsAsKiller = stmt_fetch_all_assoc_compat($stKills);
				$stKills->close();
			}
		}
		$numKillsAsKiller = count($killsAsKiller);
		
		// ======================================================================================
		// Nueva preparaciÃ³n 2025. ParticipaciÃ³n del personaje.
		// ======================================================================================		
		$participacion = [];
		$hasSeasonKindBio = column_exists($link, 'dim_seasons', 'season_kind');
		$seasonKindExprBio = $hasSeasonKindBio
			? "COALESCE(at2.season_kind, 'temporada')"
			: "'temporada'";
		$stmtP = $link->prepare("SELECT ac.id, ac.name, ac.chapter_number, at2.name AS temporada_name, at2.season_number, {$seasonKindExprBio} AS season_kind, ac.played_date FROM dim_chapters ac
								INNER JOIN bridge_chapters_characters acp ON ac.id = acp.chapter_id 
								INNER JOIN dim_seasons at2 ON at2.id = ac.season_id
								WHERE acp.character_id = ?
								ORDER BY ac.played_date, ac.chapter_number");
		$stmtP->bind_param('i', $characterId);
		$stmtP->execute();
		$stmtP_results = stmt_fetch_all_assoc_compat($stmtP);
		$participacion = array_merge($participacion, $stmtP_results);
		
		$numParticipa = count($participacion);
		$numEventosParticipa = 0;
		if ($stPartEv = $link->prepare("SELECT COUNT(*) AS c FROM bridge_timeline_events_characters WHERE character_id = ?")) {
			$stPartEv->bind_param('i', $characterId);
			$stPartEv->execute();
			$resPartEv = $stPartEv->get_result();
			if ($resPartEv && ($rowPartEv = $resPartEv->fetch_assoc())) {
				$numEventosParticipa = (int)($rowPartEv['c'] ?? 0);
			}
			$stPartEv->close();
		}

		// Flags de secciones
		$hasInfo = true;
		$hasSheet = $bioHasSheet;
		$hasRel = ((isset($relaciones) && $numRelaciones > 0) || $numKillsAsKiller > 0);
		$hasPart = ((isset($participacion) && $numParticipa > 0) || $numEventosParticipa > 0);
		$characterComments = [];
		if ($stComments = $link->prepare("SELECT id, nick, comment_time, commented_at, message, ip, created_at FROM fact_characters_comments WHERE character_id = ? ORDER BY commented_at DESC, comment_time DESC, id DESC")) {
			$stComments->bind_param('i', $characterId);
			$stComments->execute();
			$characterComments = stmt_fetch_all_assoc_compat($stComments);
			$stComments->close();
		}
		$hasComments = !empty($characterComments);
		$hasBso = false;
		if ($stBso = $link->prepare("SELECT COUNT(*) AS c FROM bridge_soundtrack_links WHERE object_type = 'personaje' AND object_id = ?")) {
			$stBso->bind_param('i', $characterId);
			$stBso->execute();
			$resBso = $stBso->get_result();
			if ($resBso && ($rowBso = $resBso->fetch_assoc())) {
				$hasBso = ((int)$rowBso['c']) > 0;
			}
			$stBso->close();
		}
		
		// Hacemos un repaso a los datos y obtenemos los enlaces que corresponden
		// ----------------------------------------- //
		include ("app/partials/bio/bio_page_section_01_data.php"); // Utilizamos "include" para no sobrecargar la pÃ¡gina con cÃ³digo
		// ----------------------------------------- //
		/* MODERNO NUEVO */
		include("app/partials/main_nav_bar.php");	// Barra NavegaciÃ³n
		// ================================================================== //
		echo "<link rel='stylesheet' href='/assets/css/hg-bio.css'>";

		echo "<div class='bioLayout'>";
		echo "<section class='bioContextHeader'>";
		echo "<div class='power-card power-card--bio'>";
		echo "  <div class='power-card__banner'><span class='power-card__title'>" . h($bioName) . "</span></div>";
		echo "  <div class='power-card__body'>";
		echo "    <div class='power-card__media'>";
		echo "      <div class='power-card__img-wrap'>";
		echo "        <img class='power-card__img' src='" . h($bioPhoto) . "' alt='" . h($bioName) . "'/>";
		echo "      </div>";
		echo "    </div>";
		echo "    <div class='power-card__stats'>";
		include ("app/partials/bio/bio_page_section_03_details.php"); // Detalles bÃ¡sicos (contexto fijo)
		echo "    </div>";
		echo "  </div>";
		echo "</div>";
		echo "</section>";

		// Config de iconos de tabs BIO (16x16).
		// Cambia solo las rutas de este bloque cuando tengas los iconos definitivos.
		$bioTabIconDefault = '/img/ui/icons/icon_character_sheet.png';
		$bioTabIcons = [
			// Keys validas: info, sheet, rel, part, bso, comments
			'default'  => $bioTabIconDefault, // Fallback global
			'info'     => '/img/ui/icons/icon_character_info.png',
			'sheet'    => '/img/ui/icons/icon_character_sheet.png',
			'rel'      => '/img/ui/icons/icon_character_relationships.png',
			'part'     => '/img/ui/icons/icon_character_participation.png',
			'bso'      => '/img/ui/icons/icon_character_music.png',
			'comments' => '/img/ui/icons/icon_character_comments.png',
		];

		// Fallback por si alguna ruta llega vacia.
		foreach (['info', 'sheet', 'rel', 'part', 'bso', 'comments'] as $k) {
			if (!isset($bioTabIcons[$k]) || trim((string)$bioTabIcons[$k]) === '') {
				$bioTabIcons[$k] = $bioTabIconDefault;
			}
		}

		$renderBioTab = function (string $tabKey, string $labelHtml) use ($bioTabIcons) {
			$icon = trim((string)($bioTabIcons[$tabKey] ?? $bioTabIcons['default'] ?? ''));
			$iconHtml = '';
			if ($icon !== '') {
				$iconHtml = "<img class='hgTabIcon' src='" . h($icon) . "' alt='' width='16' height='16' loading='lazy' decoding='async'>";
			}
			echo "<button class='hgTabBtn' data-tab='" . h($tabKey) . "'><span class='hgTabEmoji' aria-hidden='true'>" . $iconHtml . "</span><span class='hgTabLabel'>" . $labelHtml . "</span></button>";
		};

		echo "<div class='hg-tabs'>";
		if ($hasInfo) $renderBioTab('info', 'Informaci&oacute;n');
		if ($hasSheet) $renderBioTab('sheet', 'Hoja de personaje');
		if ($hasRel) $renderBioTab('rel', 'Relaciones');
		if ($hasPart) $renderBioTab('part', 'Participaci&oacute;n');
		if ($hasBso) $renderBioTab('bso', 'Banda sonora');
		if ($hasComments) $renderBioTab('comments', 'Comentarios');
		echo "</div>";

	echo "<div class='bioBody'>"; // CUERPO PRINCIPAL DE LA FICHA DE INFORMACION
		// ================================================================== //
		echo "<section id='sec-info' class='bio-tab-panel' data-tab='info'>";
		// ================================================================== //
		if ($bioText != "") { // Empezamos colocando la informaciÃ³n de Texto
			echo "<div class='bioTextData'>"; 
				echo "<fieldset class='bioSeccion'><legend>$titleInfo</legend>$bioText</fieldset>";
			echo "</div>";
		} // Finalizamos de poner el Texto

		echo "</section>";
		// ================================================================== //
		// BANDA SONORA
		if ($hasBso) {
			echo "<section id='sec-bso' class='bio-tab-panel' data-tab='bso'>";
			include("app/partials/snippet_bso_card.php");
			mostrarTarjetaBSO($link, 'personaje', $characterId);
			echo "</section>";
		}
		// ================================================================== //
		if ($bioHasSheet) { // Comprobamos si el personaje dispone de Hoja
			// ----
			echo "<section id='sec-sheet' class='bio-tab-panel' data-tab='sheet'>";
			echo "<div class='bioSheetData'>"; // Parte Superior de la Hoja ~~ #SEC04
			echo "<fieldset class='bioSeccion'><legend>$titleId</legend>";
				// ----------------------------------------- //
				include ("app/partials/bio/bio_page_section_04_sheetup.php"); // Utilizamos "include" para no sobrecargar la pÃ¡gina con cÃ³digo
				// ----------------------------------------- //
			echo "</fieldset>";
			echo "</div>"; // Cerramos Parte Superior ~~
			// ================================================================== //
			echo "<div class='bioSheetData'>"; // Atributos de la Hoja ~~ #SEC05
			echo "<fieldset class='bioSeccion'><legend>$titleAttr</legend>";
				// ----------------------------------------- //
				include ("app/partials/bio/bio_page_section_05_attributes.php"); // Utilizamos "include" para no sobrecargar la pÃ¡gina con cÃ³digo
				// ----------------------------------------- //
			echo "</fieldset>";
			echo "</div>"; // Cerramos Atributos ~~
			// ================================================================== //
			include ("app/partials/bio/bio_page_section_06_skills.php"); // Utilizamos "include" para no sobrecargar la pÃ¡gina con cÃ³digo
			// ================================================================== //
		if (!$bioIsMonster) {
			echo "<div class='bioSheetBackgrounds'>"; // Trasfondos de la Hoja ~~ #SEC07
				echo "<fieldset class='bioSeccion'><legend>$titleBackg</legend>";
					if (!empty($bioBackgrounds)) {
						foreach ($bioBackgrounds as $idx => $bg) {
							$tid = (int)($bg['id'] ?? 0);
							$nm = (string)($bg['name'] ?? '');
							$val = (int)($bg['value'] ?? 0);
							if ($nm === '' || $val <= 0) continue;
							$nameHtml = h($nm);
							if ($tid > 0 && function_exists('pretty_url')) {
								$hrefT = pretty_url($link, 'dim_traits', '/rules/traits', $tid);
								$nameHtml = "<a href='" . h($hrefT) . "' target='_blank' class='hg-tooltip' data-tip='trait' data-id='" . $tid . "'>" . h($nm) . "</a>";
							}
							echo"<div class='bioSheetBackgroundLeft'>" . $nameHtml . ":</div>";
							$img = $bioBackImgs[$idx] ?? '';
							echo"<div class='bioSheetBackgroundRight'>" . $img . "</div>";
						}
					}
				echo "</fieldset>";
			echo "</div>"; // Cerramos Trasfondos ~~
			// ================================================================== //
			// MÉRITOS Y DEFECTOS
			// ================================================================== //
			include ("app/partials/bio/bio_page_section_08_merits.php"); // Utilizamos "include" para no sobrecargar la pÃ¡gina con cÃ³digo
		}
			// ================================================================== //
			// RECURSOS DEL PERSONAJE
			// ================================================================== //
			include ("app/partials/bio/bio_page_section_07_resources.php"); // Utilizamos "include" para no sobrecargar la pÃ¡gina con cÃ³digo
			// ================================================================== //
			// PODERES, DONES, RITUALES Y DISCIPLINAS
			// ================================================================== //
			include ("app/partials/bio/bio_page_section_11_power.php"); // Utilizamos "include" para no sobrecargar la pÃ¡gina con cÃ³digo
			// ================================================================== //
			// INVENTARIO Y OBJETOS
			// ================================================================== //
			include ("app/partials/bio/bio_page_section_13_items.php"); // Utilizamos "include" para no sobrecargar la pÃ¡gina con cÃ³digo
			// ================================================================== //
			echo "</section>";
		} // Finalizamos la Hoja de Personaje
		?>
		
		<?php if ($hasRel): ?>
			<section id="sec-rel" class="bio-tab-panel" data-tab="rel">
			<div class="bioTextData">
				<fieldset class='bioSeccion'>
					<legend><?= ($titleSameBio) ?></legend>
					<button id="toggleRelaciones" class="boton2 bio-rel-toggle" type="button">Cambiar vista</button>
					<div id="seccion2">
						<?php include("app/partials/bio/bio_page_section_17_rel_graph.php"); ?>
					</div>
					<?php include("app/partials/bio/bio_page_section_20_kills.php"); ?>
					<div id="seccion1" class="bio-hidden">
						<?php include("app/partials/bio/bio_page_section_14_family.php"); ?>
					</div>
				</fieldset>
			</div>
			</section>

		<?php endif; ?>
		
		<?php if ($hasPart): ?>
			<section id="sec-part" class="bio-tab-panel" data-tab="part">
			<div class="bioTextData">
				<fieldset class='bioSeccion'>
					<legend>&nbsp;<?= ($titleParticp) ?>&nbsp;</legend>
					<?php include("app/partials/bio/bio_page_section_18_chapters.php"); ?>
					<?php include("app/partials/bio/bio_page_section_19_participation.php"); ?>
				</fieldset>
			</div>
			</section>
		<?php endif; ?>
		<?php if ($hasComments): ?>
			<section id="sec-comments" class="bio-tab-panel" data-tab="comments">
				<?php include("app/partials/bio/bio_page_section_15_comments.php"); ?>
			</section>
		<?php endif; ?>
		<?php
	echo "</div>"; // FIN DE CUERPO PRINCIPAL DE LA FICHA DE INFORMACION
		echo "</div>"; // bioLayout
	} else {
		echo "<p class='bio-error-msg'>$mensajeDeError</p>"; // Mensaje de error en caso de introducir datos manualmente. Tomado del Cuerpo Trabajar
	}

	// Limpieza del stmt principal
	if (isset($stmt) && $stmt instanceof mysqli_stmt) {
		@mysqli_stmt_close($stmt);
	}
?>

<script>
	document.addEventListener('DOMContentLoaded', () => {
		const tabs = Array.from(document.querySelectorAll('.hgTabBtn, .bioTabBtn'));
		const panels = Array.from(document.querySelectorAll('.bio-tab-panel'));
		if (typeof HGBindHoverSound === 'function') {
			HGBindHoverSound('.hgTabBtn, .bioTabBtn', '/sounds/ui/hover.ogg');
		}
		function activate(tabKey){
			panels.forEach(p => {
				p.classList.toggle('active', p.dataset.tab === tabKey);
			});
			tabs.forEach(b => {
				b.classList.toggle('active', b.dataset.tab === tabKey);
			});
			if (tabKey === 'rel') {
				setTimeout(() => {
					try {
						if (typeof window.__bioRelNetworkRefresh === 'function') {
							window.__bioRelNetworkRefresh();
						} else {
							if (window.__bioRelNetwork && typeof window.__bioRelNetwork.fit === 'function') {
								window.__bioRelNetwork.fit({ animation: { duration: 200, easingFunction: 'easeInOutQuad' } });
							}
							if (window.__bioRelNetwork && typeof window.__bioRelNetwork.redraw === 'function') {
								window.__bioRelNetwork.redraw();
							}
						}
					} catch (e) {}
				}, 60);
			}
		}
		if (tabs.length) activate(tabs[0].dataset.tab);

		tabs.forEach(b => {
			b.addEventListener('click', () => activate(b.dataset.tab));
		});

		document.querySelectorAll('.bioSideNav a[data-tab]').forEach(a => {
			a.addEventListener('click', (e) => {
				const tab = a.dataset.tab;
				if (tab) activate(tab);
			});
		});
	});

	document.addEventListener('DOMContentLoaded', () => {
		const btnToggle = document.getElementById('toggleRelaciones');
		const seccion1 = document.getElementById('seccion1');
		const seccion2 = document.getElementById('seccion2');
		const seccion3 = document.getElementById('seccion3');
		const seccion4 = document.getElementById('seccion4');

		// Si no existe el botÃ³n (porque no hay relaciones), no hacemos nada
		if (!btnToggle || !seccion1 || !seccion2) return;

		btnToggle.addEventListener('click', () => {
			const hidden = window.getComputedStyle(seccion1).display === 'none';
			if (hidden) {
				seccion1.style.display = 'block';
				seccion2.style.display = 'none';
			} else {
				seccion1.style.display = 'none';
				seccion2.style.display = 'block';
			}
			// Recalcular tamaÃ±o/redibujar vis-network si existe
			try {
				if (typeof window.__bioRelNetworkRefresh === 'function') {
					window.__bioRelNetworkRefresh();
				} else if (window.__bioRelNetwork && typeof window.__bioRelNetwork.fit === 'function') {
					window.__bioRelNetwork.fit({ animation: { duration: 300, easingFunction: 'easeInOutQuad' } });
				}
			} catch (e) {}
		});
	});
</script>
	<script>
		document.addEventListener('DOMContentLoaded', () => {
			if (window.__hgTooltipBound) return;
			const tooltip = document.createElement('div');
			tooltip.id = 'hg-tooltip';
			document.body.appendChild(tooltip);

			const cache = new Map();
			let timer = null;
			let currentKey = '';
			let lastX = 0, lastY = 0;

			function moveTip(x, y){
				const pad = 14;
				const tw = tooltip.offsetWidth || 320;
				const th = tooltip.offsetHeight || 120;
				let left = x + pad;
				let top = y + pad;
				if (left + tw > window.innerWidth) left = x - tw - pad;
				if (top + th > window.innerHeight) top = y - th - pad;
				tooltip.style.left = left + 'px';
				tooltip.style.top = top + 'px';
			}

			function hideTip(){
				tooltip.style.display = 'none';
				tooltip.innerHTML = '';
				currentKey = '';
			}

			document.querySelectorAll('.hg-tooltip').forEach(el => {
				el.addEventListener('mousemove', (e) => {
					lastX = e.clientX;
					lastY = e.clientY;
					if (tooltip.style.display === 'block') moveTip(lastX, lastY);
				});

				el.addEventListener('mouseenter', (e) => {
					lastX = e.clientX;
					lastY = e.clientY;
					const type = el.getAttribute('data-tip') || '';
					const id = el.getAttribute('data-id') || '';
					if (!type || !id) return;
					const key = type + ':' + id;
					currentKey = key;
					if (cache.has(key)) {
						tooltip.innerHTML = cache.get(key);
						tooltip.style.display = 'block';
						moveTip(lastX, lastY);
						return;
					}
					timer = setTimeout(async () => {
						if (currentKey !== key) return;
						try {
							const res = await fetch(`/ajax/tooltip?type=${encodeURIComponent(type)}&id=${encodeURIComponent(id)}`);
							const html = await res.text();
							if (currentKey !== key) return;
							cache.set(key, html);
							tooltip.innerHTML = html;
							tooltip.style.display = 'block';
							moveTip(lastX, lastY);
						} catch (err) {
							// silencioso
						}
					}, 900);
				});

				el.addEventListener('mouseleave', () => {
					if (timer) clearTimeout(timer);
					timer = null;
					hideTip();
				});
			});
		});
	</script>
	<script>
		document.addEventListener('click', async (event) => {
			const btn = event.target.closest('.js-copy-roll');
			if (!btn) return;
			const text = String(btn.getAttribute('data-copy') || '');
			if (!text) return;
			const old = btn.innerHTML;
			try {
				if (navigator.clipboard && navigator.clipboard.writeText) {
					await navigator.clipboard.writeText(text);
				} else {
					const ta = document.createElement('textarea');
					ta.value = text;
					document.body.appendChild(ta);
					ta.select();
					document.execCommand('copy');
					ta.remove();
				}
				btn.innerHTML = '&#9989;';
			} catch (e) {
				btn.innerHTML = '&#10060;';
			}
			setTimeout(() => { btn.innerHTML = old; }, 1400);
		});
	</script>
