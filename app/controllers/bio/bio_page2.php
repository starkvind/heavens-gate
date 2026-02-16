<?php
	/*  Índice de secciones
			1.- Query en Base de Datos 		[#SEC01]
			2.- Foto del Personaje 			[#SEC02]
			3.- Datos básicos - Detalles	[#SEC03]
			4.- Parte superior de la Hoja	[#SEC04]
			5.- Atributos					[#SEC05]
			6.- Habilidades					[#SEC06]
			7.- Trasfondos y Ventajas		[#SEC07]
			8.- Méritos y Defectos			[#SEC08]
			9.- Renombre / Virtudes			[#SEC09]
		   10.- Fuerza de Voluntad			[#SEC10]
		   11.- Poderes - Dones, Discip.	[#SEC11]
		   12.- Rituales					[#SEC12]
		   13.- Inventario del personaje	[#SEC13]
		   14.- Biografías similares		[#SEC14]
		   15.- Comentarios					[#SEC15]
	*/

	// Helpers (escape + fetch sin depender de mysqlnd)
	function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

	function stmt_fetch_all_assoc_compat(mysqli_stmt $stmt): array {
		$out = [];

		// Camino rápido (mysqlnd)
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
		$sql = "SELECT trait_id, value FROM fact_character_traits WHERE character_id = ?";
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
				FROM fact_character_traits f
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

	// Cálculo de círculos de habilidad, atributos, etc.
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

	function fetch_traits_for_system_type(mysqli $link, int $characterId, int $systemId, string $kind): array {
		$out = [];
		if ($characterId <= 0) return $out;
		$hasSet = false;
		if ($systemId > 0) {
			$sql = "SELECT t.id, t.name, COALESCE(f.value,0) AS value, s.sort_order
					FROM fact_trait_sets s
					JOIN dim_traits t ON t.id = s.trait_id AND t.kind = ?
					LEFT JOIN fact_character_traits f ON f.trait_id = t.id AND f.character_id = ?
					WHERE s.system_id = ? AND s.is_active = 1
					ORDER BY s.sort_order, t.name";
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
					FROM fact_character_traits f
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

	$characterId = isset($_GET['b']) ? (int)$_GET['b'] : 0; // Cogemos datos del GET "b"
	if ($characterId <= 0) {
		echo "<p style='text-align:center;'>$mensajeDeError</p>"; // Mensaje de error en caso de introducir datos manualmente. Tomado del Cuerpo Trabajar
		exit;
	}

	$orderData ="SELECT p.*, s.name AS system_name, t.name AS totem_name FROM fact_characters p LEFT JOIN dim_systems s ON p.system_id = s.id LEFT JOIN dim_totems t ON p.totem_id = t.id WHERE p.id = ? LIMIT 1;"; // Elegimos al PJ de la Base de Datos
	$stmtMain = mysqli_prepare($link, $orderData);
	if (!$stmtMain) {
		echo "<p style='text-align:center;'>$mensajeDeError</p>"; // Mensaje de error en caso de introducir datos manualmente. Tomado del Cuerpo Trabajar
		exit;
	}

	mysqli_stmt_bind_param($stmtMain, 'i', $characterId);
	mysqli_stmt_execute($stmtMain);

	// Evitar dependencia de get_result()/mysqlnd
	$rowsMain = stmt_fetch_all_assoc_compat($stmtMain);
	$NFilas = count($rowsMain);

	if ($NFilas > 0) { // Comenzamos chequeo de datos. Si no tenemos nada, mandamos un mensaje de error.
		foreach ($rowsMain as $dataResult) {
		#$dataResult = mysql_fetch_array($queryData); // Empezamos a recolectar los datos. ~~ #SEC01
		// ================================================================== //
		// Datos básicos del personaje
			$characterIdDb		 = $dataResult["id"];
		$bioId = $characterIdDb;
 			// ID del personaje. Aunque la tengamos en el get, mejor así.
			$bioName 	 = $dataResult["name"]; 		// Nombre completo del personaje.
			$bioAlias 	 = $dataResult["alias"]; 		// Alias del personaje, como le llaman.
			$bioPackName = $dataResult["garou_name"]; 	// Nombre de manada. Como "Cláusula", "Churrasco", "Chili-Chingón", etc.
			$bioPhoto	 = $dataResult["img"]; 			// Imagen del personaje.
		$bioType	 = $dataResult["kind"] ?? $dataResult["character_type_id"] ?? 0; // Tipo de personaje.
			$bioBday	 = $dataResult["birthdate_text"]; // Cumpleaños del personaje.
			$bioConcept	 = $dataResult["concepto"]; 	// Concepto del personaje.
			$bioNature	 = $dataResult["nature_id"]; 	// Naturaleza del personaje.
			$bioBehavior = $dataResult["demeanor_id"]; 	// Conducta del personaje.
			$bioText	 = $dataResult["info_text"]; 	// Texto escrito que habla sobre el personaje.
			$bioTxtColor = $dataResult["colortexto"]; 	// Color de fondo para el globo de texto del personaje.
		// ================================================================== //
			$pageSect 	 = "Biografía";						// Para cambiar el título a la página.
			$pageTitle2	 = $bioName;						// Título de la Página
			setMetaFromPage($bioName . " | Personajes | Heaven's Gate", meta_excerpt($bioText), $bioPhoto, 'article');
			$titleInfo	 = "&nbsp;Información&nbsp;";		// Titulo de la seccion "Información"
			$titleId	 = "&nbsp;Detalles&nbsp;";			// Titulo de la seccion "Identificación"
			$titleAttr	 = "&nbsp;Atributos&nbsp;";			// Titulo de la seccion "Atributos"
			$titleSkill	 = "&nbsp;Habilidades&nbsp;";		// Titulo de la seccion "Habilidades"
			$titleBackg	 = "&nbsp;Trasfondos&nbsp;";		// Titulo de la seccion "Trasfondos"
			$titleMerits = "&nbsp;Méritos y Defectos&nbsp;";// Titulo de la seccion "Méritos y Defectos"
			$titleSocial = "&nbsp;Renombre&nbsp;"; 			// Titulo de la seccion "Social"
			$titleAdvant = "&nbsp;Estado&nbsp;";			// Titulo de la seccion "Estado"
			$titlePowers = "&nbsp;Poderes&nbsp;";			// Titulo de la seccion "Poderes"
			$titleItems	 = "&nbsp;Inventario&nbsp;";		// Titulo de la seccion "Inventario"
			$titleSameBio= "&nbsp;Relaciones&nbsp;";		// Título de la sección "Relaciones"
			$titleNebulo = "&nbsp;Nebulosa de relaciones&nbsp;";// Título de la sección "Nebulosa de relaciones"	
			$titleParticp= "&nbsp;Participación&nbsp;";		// Titulo de la seccion "Participación"		
		// ================================================================== //
		// Datos de jugador y crónica
			$bioPlayer	 = $dataResult["player_id"]; 	// Jugador al que pertenece el personaje.
			$bioChronic	 = $dataResult["chronicle_id"]; // Crónica a la que pertenece el personaje.
			$bioStatus	 = $dataResult["estado"]; 		// Estado del personaje. Si está "activo" o "muerto", etc.
			$bioDethCaus = $dataResult["cause_of_death"]; // Causa de la muerte.
			$bioSheet	 = $dataResult["character_kind"]; // Si el personaje posee Ficha de Personaje o no.
			$bioXP		 = $dataResult["px"]; 			// Puntos de experiencia restantes del personaje.
		// ================================================================== //
		// Datos de raza y alineamientos
			$bioRace	 = $dataResult["breed_id"]; 	// Raza a la que pertenece el personaje.
			$bioAuspice	 = $dataResult["auspicio"]; 	// Auspicio al que pertenece el personaje.
			$bioTribe	 = $dataResult["tribu"]; 		// Tribu a la que pertenece el personaje.
			$bioRange	 = $dataResult["rank"]; 		// Rango de importancia del personaje en su organización.
		// ================================================================== //
		// Ventajas y poderes
			$bioTotem	 = (string)($dataResult["totem_name"] ?? ""); 		// Tótem que guía al personaje.
		// Género
			$bioGender	 = $dataResult["genero_pj"];		// Género del personaje
		// Títulos de la sección Detalles		
			$titlePkName	= "Nombre Garou";		// Título del nombre Garou
		// Sistema, para nombres de detalles y tal.
			$bioSystem 	= (string)($dataResult["system_name"] ?? "");
		// Nombres de conceptos
			// ================================================================== //
			// Datos y Nombre del Sistema
			$bioFera = $dataResult["shifter_type"];
			// ================================================================== //
			// IDENTIFICACION
			$titleAuspice	= "Auspicio";
			$titlePack 		= "Manada";
			$titleTribe 	= "Tribu";
			$titleClan 		= "Clan";
			// SECCION SOCIAL
			$titleGlory 	= "Gloria";
			$titleHonor 	= "Honor";
			$titleWisdo 	= "Sabiduría";
			// SECCION VENTAJAS
			$titleRage 		= "Rabia";
			$titleGnosis 	= "Gnosis";
			// ================================================================== //
			// Cambiamos títulos de secciones acorde al Sistema del PJ
			include ("app/partials/bio/bio_page_section_00_system.php"); // Utilizamos "include" para no sobrecargar la página con código
		// ================================================================== //
		if ($bioSheet == "pj") { // <--- Inicio de comprobación si lleva hoja de PJ
		// ================================================================== //
		// Traits normalizados (fact_character_traits)
			$traitValues = fetch_trait_values($link, (int)$characterId);

			// Mapa trait_id por columna legacy (mismo mapping que migración)
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

		// Datos sociales
			$bioArrayPow = array(
				$dataResult["glory_points"],	// Gloria para Fêra, Conciencia para Vampiro
				$dataResult["honor_points"],	// Honor para Fêra, Autocontrol para Vampiro
				$dataResult["wisdom_points"],	// Sabiduria para Fêra, Coraje para Vampiro
				$dataResult["rage_points"],		// Rabia para Fêra
				$dataResult["gnosis_points"],	// Gnosis para Fêra, Senda para Vampiro
				$dataResult["fvp"],				// Fuerza de Voluntad
			);
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
			'Atributos' => fetch_traits_for_system_type($link, (int)$characterId, (int)($dataResult['system_id'] ?? 0), 'Atributos'),
			'Talentos' => fetch_traits_for_system_type($link, (int)$characterId, (int)($dataResult['system_id'] ?? 0), 'Talentos'),
			'Técnicas' => fetch_traits_for_system_type($link, (int)$characterId, (int)($dataResult['system_id'] ?? 0), 'Técnicas'),
			'Conocimientos' => fetch_traits_for_system_type($link, (int)$characterId, (int)($dataResult['system_id'] ?? 0), 'Conocimientos'),
			'Trasfondos' => fetch_traits_for_system_type($link, (int)$characterId, (int)($dataResult['system_id'] ?? 0), 'Trasfondos'),
		];

		// Orden + extras al final (alfab?tico)
		$bioTraitsByType['Talentos'] = order_trait_list($bioTraitsByType['Talentos'] ?? [], 10);
		$bioTraitsByType['Técnicas'] = order_trait_list($bioTraitsByType['Técnicas'] ?? [], 10);
		$bioTraitsByType['Conocimientos'] = order_trait_list($bioTraitsByType['Conocimientos'] ?? [], 10);

		$bioTraitImgsByType = [];
		foreach ($bioTraitsByType as $tipo => $list) {
			$vals = array_map(fn($t) => (int)($t['value'] ?? 0), $list);
			$bioTraitImgsByType[$tipo] = createSkillCircle($vals, 'gem-attr');
		}

		$bioAttrList = $bioTraitsByType['Atributos'] ?? [];
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
			'Técnicas' => $bioTraitsByType['Técnicas'] ?? [],
			'Conocimientos' => $bioTraitsByType['Conocimientos'] ?? [],
		];
		$bioSkillColImgs = [
			'Talentos' => $bioTraitImgsByType['Talentos'] ?? [],
			'Técnicas' => $bioTraitImgsByType['Técnicas'] ?? [],
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
		$tecnicaExtras = array_slice($bioTraitsByType['Técnicas'] ?? [], 10);
		$conociExtras = array_slice($bioTraitsByType['Conocimientos'] ?? [], 10);
		$bioExtraTalImg = createSkillCircle(array_map(fn($t) => (int)($t['value'] ?? 0), $talentoExtras), 'gem-attr');
		$bioExtraTecImg = createSkillCircle(array_map(fn($t) => (int)($t['value'] ?? 0), $tecnicaExtras), 'gem-attr');
		$bioExtraConImg = createSkillCircle(array_map(fn($t) => (int)($t['value'] ?? 0), $conociExtras), 'gem-attr');
// ================================================================== //
			}
	 	} // <---- Fin de comprobación si lleva hoja de PJ
		
		// ======================================================================================
		// Nueva preparación 2025. Tabla de relaciones.
		// ======================================================================================
		$relaciones = [];
		
		// Relaciones salientes
		$stmt1 = $link->prepare("SELECT cr.*, p2.name, p2.alias, p2.img, p2.genero_pj, 'outgoing' as direction
								FROM bridge_characters_relations cr
								LEFT JOIN fact_characters p2 ON cr.target_id = p2.id
								WHERE cr.source_id = ?
								ORDER BY cr.relation_type");
		$stmt1->bind_param('i', $characterId);
		$stmt1->execute();
		$stm11_results = stmt_fetch_all_assoc_compat($stmt1);
		$relaciones = array_merge($relaciones, $stm11_results);

		// Relaciones entrantes
		$stmt2 = $link->prepare("SELECT cr.*, p2.name, p2.alias, p2.img, p2.genero_pj, 'incoming' as direction
								FROM bridge_characters_relations cr
								LEFT JOIN fact_characters p2 ON cr.source_id = p2.id
								WHERE cr.target_id = ?
								ORDER BY cr.relation_type");
		$stmt2->bind_param('i', $characterId);
		$stmt2->execute();
		$stm12_results = stmt_fetch_all_assoc_compat($stmt2);
		$relaciones = array_merge($relaciones, $stm12_results);
		// Ordenar alfabéticamente por 'relation_type'
		usort($relaciones, function($a, $b) {
			return strcasecmp($a['relation_type'], $b['relation_type']);
		});
		
		$numRelaciones = count($relaciones);
		
		// ======================================================================================
		// Nueva preparación 2025. Participación del personaje.
		// ======================================================================================		
		$participacion = [];
		$stmtP = $link->prepare("SELECT ac.id, ac.name, ac.chapter_number, at2.name AS temporada_name, at2.season_number, ac.played_date FROM dim_chapters ac
								INNER JOIN bridge_chapters_characters acp ON ac.id = acp.chapter_id 
								INNER JOIN dim_seasons at2 ON ac.season_number = at2.season_number 
								WHERE acp.character_id = ?
								ORDER BY ac.played_date, ac.chapter_number");
		$stmtP->bind_param('i', $characterId);
		$stmtP->execute();
		$stmtP_results = stmt_fetch_all_assoc_compat($stmtP);
		$participacion = array_merge($participacion, $stmtP_results);
		
		$numParticipa = count($participacion);

		// Flags de secciones
		$hasInfo = true;
		$hasSheet = ($bioSheet == "pj");
		$hasRel = (isset($relaciones) && $numRelaciones > 0);
		$hasPart = (isset($participacion) && $numParticipa > 0);
		$hasBso = false;
		if ($stBso = $link->prepare("SELECT COUNT(*) AS c FROM bridge_soundtrack_links WHERE tipo_objeto = 'personaje' AND id_objeto = ?")) {
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
		include ("app/partials/bio/bio_page_section_01_data.php"); // Utilizamos "include" para no sobrecargar la página con código
		// ----------------------------------------- //
		/* MODERNO NUEVO */
		include("app/partials/main_nav_bar.php");	// Barra Navegación
		echo "<h2>" . h($bioName) . "</h2>"; // Encabezado de página
		// ================================================================== //
		echo "<style>
		.bioLayout{ max-width:980px; margin:0 auto; }
		.bioTabs{ display:flex; gap:8px; flex-wrap:wrap; margin:6px 0 12px; }
		.bioTabBtn{
			font-family: verdana;
			font-size: 10px;
			background-color: #000066;
			color: #fff;
			padding: 0.5em;
			border: 1px solid #000099;
			border-radius: 6px;
			cursor: pointer;
		}
		.bioTabBtn:hover{ border-color:#003399; background:#000099; color:#01b3fa; }
		.bioTabBtn.active{ background:#001199; color:#01b3fa; border-color:#003399; }
		.bio-tab-panel{ display:none; }
		.bio-tab-panel.active{ display:block; }
		#hg-tooltip{
			position: fixed;
			z-index: 9999;
			max-width: 320px;
			background: #0b0b2b;
			border: 1px solid #003399;
			color: #e6f0ff;
			padding: 8px 10px;
			border-radius: 6px;
			box-shadow: 0 6px 20px rgba(0,0,0,0.45);
			font-size: 12px;
			display: none;
			pointer-events: none;
			text-align: left;
			max-height: 60vh;
			overflow: auto;
		}
		#hg-tooltip .hg-tip-title{ font-weight: bold; margin-bottom: 4px; color:#8fd7ff; }
		#hg-tooltip .hg-tip-meta{ font-size: 11px; color:#9fb2d9; }
		#hg-tooltip .hg-tip-label{ font-weight: bold; margin-top: 6px; color:#cfd9ff; }
		#hg-tooltip .hg-tip-text{ font-size: 12px; color:#e6f0ff; }
		</style>";

		echo "<div class='bioLayout'>";
		echo "<div class='bioTabs'>";
		if ($hasInfo) echo "<button class='bioTabBtn' data-tab='info'>Información</button>";
		if ($hasBso) echo "<button class='bioTabBtn' data-tab='bso'>Banda sonora</button>";
		if ($hasSheet) echo "<button class='bioTabBtn' data-tab='sheet'>Hoja de personaje</button>";
		if ($hasRel) echo "<button class='bioTabBtn' data-tab='rel'>Relaciones</button>";
		if ($hasPart) echo "<button class='bioTabBtn' data-tab='part'>Participación</button>";
		echo "</div>";

	echo "<div class='bioBody'>"; // CUERPO PRINCIPAL DE LA FICHA DE INFORMACION
		// ================================================================== //
		echo "<section id='sec-info' class='bio-tab-panel' data-tab='info'>";
		echo "<div class='bioSquarePhoto'>"; // Colocamos la Fotografia del personaje ~~ #SEC02
			echo "<img class='photobio' src='" . h($bioPhoto) . "' alt='" . h($bioName) . "'/>";
		echo"</div>"; // Dejamos la Fotografía ya colocada
		// ================================================================== //
		echo "<div class='bioSquareData'>"; // Comenzamos a colocar los datos básicos ~~ #SEC03
			// ----------------------------------------- //
			include ("app/partials/bio/bio_page_section_03_details.php"); // Utilizamos "include" para no sobrecargar la página con código
			// ----------------------------------------- //
		echo "</div>"; // Finalizamos los datos básicos
		// ================================================================== //
		if ($bioText != "") { // Empezamos colocando la información de Texto
			echo "<div class='bioTextData'>"; 
				echo "<fieldset class='bioSeccion'><legend>$titleInfo</legend>$bioText</fieldset>";
			echo "</div>";
		} // Finalizamos de poner el Texto
		// INVENTARIO Y OBJETOS
		// ================================================================== //
		include ("app/partials/bio/bio_page_section_13_items.php"); // Utilizamos "include" para no sobrecargar la p?gina con c?digo
		// ================================================================== //
		?>
		
		<div class="bioTextData">
			<fieldset class='bioSeccion'>
				<legend>Embeber personaje en el foro</legend>
		<?php
			if ($bioTxtColor == "") $bioTxtColor = "SkyBlue";
			$html = "<pre style='background:#111; border:1px solid #444; color:#0f0; font-family:monospace; padding:0.5em; border-radius:6px; overflow:auto;'><code>[hg_avatar=" . h($characterId) . "," . h($bioTxtColor) . "]Mensaje de " . h($bioName) . "[/hg_avatar]</code></pre>";
			echo $html;
		?>
			</fieldset>
		</div>
		
		<?php

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
		if ($bioSheet == "pj") { // Comprobamos si el personaje dispone de Hoja de Personaje
			// ----
			echo "<section id='sec-sheet' class='bio-tab-panel' data-tab='sheet'>";
			echo "<div class='bioSheetData'>"; // Parte Superior de la Hoja ~~ #SEC04
			echo "<fieldset class='bioSeccion'><legend>$titleId</legend>";
				// ----------------------------------------- //
				include ("app/partials/bio/bio_page_section_04_sheetup.php"); // Utilizamos "include" para no sobrecargar la página con código
				// ----------------------------------------- //
			echo "</fieldset>";
			echo "</div>"; // Cerramos Parte Superior ~~
			// ================================================================== //
			echo "<div class='bioSheetData'>"; // Atributos de la Hoja ~~ #SEC05
			echo "<fieldset class='bioSeccion'><legend>$titleAttr</legend>";
				// ----------------------------------------- //
				include ("app/partials/bio/bio_page_section_05_attributes.php"); // Utilizamos "include" para no sobrecargar la página con código
				// ----------------------------------------- //
			echo "</fieldset>";
			echo "</div>"; // Cerramos Atributos ~~
			// ================================================================== //
			echo "<div class='bioSheetData'>"; // Habilidades de la Hoja ~~ #SEC06
			echo "<fieldset class='bioSeccion'><legend>$titleSkill</legend>";
				include ("app/partials/bio/bio_page_section_06_skills.php"); // Utilizamos "include" para no sobrecargar la página con códigoç
			echo "</fieldset>";
			echo "</div>"; // Cerramos Habilidades ~~
			// ================================================================== //
			echo "<div class='bioSheetBackgrounds'>"; // Trasfondos de la Hoja ~~ #SEC07
			echo "<fieldset class='bioSeccion'><legend>$titleBackg</legend>";
				if (!empty($bioBackgrounds)) {
					foreach ($bioBackgrounds as $idx => $bg) {
						$nm = (string)($bg['name'] ?? '');
						$val = (int)($bg['value'] ?? 0);
						if ($nm === '' || $val <= 0) continue;
						echo"<div class='bioSheetBackgroundLeft'>" . h($nm) . ":</div>";
						$img = $bioBackImgs[$idx] ?? '';
						echo"<div class='bioSheetBackgroundRight'>" . $img . "</div>";
					}
				}
			echo "</fieldset>";
			echo "</div>"; // Cerramos Trasfondos ~~
			// ================================================================== //
			// MÉRITOS Y DEFECTOS
			// ================================================================== //
			include ("app/partials/bio/bio_page_section_08_merits.php"); // Utilizamos "include" para no sobrecargar la página con código
			// ================================================================== //
		echo "<div class='bioSheetSociaWhole'>"; // Caja de la Seccion SOCIAL y VENTAJAS
			echo "<div class='bioSheetSocialPower'>"; // Datos Sociales de la Hoja ~~ #SEC09
			echo "<fieldset class='bioSeccion'><legend>$titleSocial</legend>";
				echo"<div class='bioSheetSocialPowerLeft'>$titleGlory:</div>";
				echo"<div class='bioSheetSocialPowerRight'>$bioPowrImg[0]</div>";
				echo"<div class='bioSheetSocialPowerLeft'>$titleHonor:</div>";
				echo"<div class='bioSheetSocialPowerRight'>$bioPowrImg[1]</div>";
				echo"<div class='bioSheetSocialPowerLeft'>$titleWisdo:</div>";
				echo"<div class='bioSheetSocialPowerRight'>$bioPowrImg[2]</div>";
				if ($bioRange != "") { // Rango del Personaje
					echo"<div class='bioSheetSocialPowerLeft'>Rango:</div>";
					echo"<div class='bioSheetSocialPowerRight'>" . h($bioRange) . "</div>";
				}
			echo "</fieldset>";
			echo "</div>"; // Cerramos Datos Sociales ~~
			// ================================================================== //
			echo "<div class='bioSheetSocialPower'>"; // Fuerza de Voluntad y demás de la Hoja ~~ #SEC10
			echo "<fieldset class='bioSeccion'><legend>$titleAdvant</legend>";
				if ($bioFera != "") {
					echo"<div class='bioSheetSocialPowerLeft'>$titleRage:</div>";
					echo"<div class='bioSheetSocialPowerRight'>$bioPowrImg[3]</div>";
				}
				echo"<div class='bioSheetSocialPowerLeft'>$titleGnosis:</div>";
				echo"<div class='bioSheetSocialPowerRight'>$bioPowrImg[4]</div>";
				echo"<div class='bioSheetSocialPowerLeft'>Fuerza de Voluntad:</div>";
				echo"<div class='bioSheetSocialPowerRight'>$bioPowrImg[5]</div>";
				if ($bioXP != 0) {
					echo"<div class='bioSheetSocialPowerLeft'>Experiencia:</div>";
					echo"<div class='bioSheetSocialPowerRight'>" . h($bioXP) . " PX</div>";				
				}
			echo "</fieldset>";
			echo "</div>"; // Cerramos Fuerza de Voluntad y demás~~
		echo "</div>"; // Caja de la Seccion SOCIAL y VENTAJAS
			// ================================================================== //
			// PODERES, DONES, RITUALES Y DISCIPLINAS
			// ================================================================== //
			include ("app/partials/bio/bio_page_section_11_power.php"); // Utilizamos "include" para no sobrecargar la página con código
			// ================================================================== //
			echo "</section>";
		} // Finalizamos la Hoja de Personaje
		?>
		
		<?php if ((isset($relaciones)) && $numRelaciones > 0): ?>
			<section id="sec-rel" class="bio-tab-panel" data-tab="rel">
			<div class="bioTextData">
				<fieldset class='bioSeccion'>
					<legend><?= ($titleSameBio) ?></legend>
					<button id="toggleRelaciones" class="boton2" style="float: right; margin-right:0.3em;" type="button">Cambiar vista</button>
					<div id="seccion2">
						<?php include("app/partials/bio/bio_page_section_17_rel_graph.php"); ?>
					</div>
					<div id="seccion1" style='display: none;'>
						<?php include("app/partials/bio/bio_page_section_14_family.php"); ?>
					</div>
				</fieldset>
			</div>
			</section>

		<?php endif; ?>
		
		<?php if ((isset($participacion)) && $numParticipa > 0): ?>
			<section id="sec-part" class="bio-tab-panel" data-tab="part">
			<div class="bioTextData">
				<fieldset class='bioSeccion'>
					<legend><?= ($titleParticp) ?></legend>
					<?php include("app/partials/bio/bio_page_section_18_chapters.php"); ?>
				</fieldset>
			</div>
			<?php if ($bioSheet == "pj"): ?>
				<?php include("app/partials/bio/bio_page_section_19_participation.php"); ?>
			<?php endif; ?>
			</section>
		<?php endif; ?>
		<?php
	echo "</div>"; // FIN DE CUERPO PRINCIPAL DE LA FICHA DE INFORMACION
		echo "</div>"; // bioLayout
	} else {
		echo "<p style='text-align:center;'>$mensajeDeError</p>"; // Mensaje de error en caso de introducir datos manualmente. Tomado del Cuerpo Trabajar
	}

	// Limpieza del stmt principal
	if (isset($stmt) && $stmt instanceof mysqli_stmt) {
		@mysqli_stmt_close($stmt);
	}
?>

<script>
	document.addEventListener('DOMContentLoaded', () => {
		const tabs = Array.from(document.querySelectorAll('.bioTabBtn'));
		const panels = Array.from(document.querySelectorAll('.bio-tab-panel'));
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

		// Si no existe el botón (porque no hay relaciones), no hacemos nada
		if (!btnToggle || !seccion1 || !seccion2) return;

		btnToggle.addEventListener('click', () => {
			if (seccion1.style.display === 'none') {
				seccion1.style.display = 'block';
				seccion2.style.display = 'none';
			} else {
				seccion1.style.display = 'none';
				seccion2.style.display = 'block';
			}
			// Recalcular tamaño/redibujar vis-network si existe
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


