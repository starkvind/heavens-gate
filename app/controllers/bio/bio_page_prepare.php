<?php

// ================================================================== //
		// Datos básicos del personaje
			$characterIdDb = $dataResult["id"];
			$bioId 		   = $characterIdDb;
 			// ID del personaje. Aunque la tengamos en el get, mejor así.
			$bioName 	 = $dataResult["name"]; 		// Nombre completo del personaje.
			$bioAlias 	 = $dataResult["alias"]; 		// Alias del personaje, como le llaman.
			$bioPackName = $dataResult["garou_name"]; 	// Nombre de manada. Como "Cláusula", "Churrasco", "Chili-Chingón", etc.
			$bioPhoto	 = hg_character_avatar_url($dataResult["image_url"] ?? '', $dataResult["gender"] ?? ''); 	// Imagen del personaje.
			$bioType	 = $dataResult["kind"] ?? $dataResult["character_type_id"] ?? 0; // Tipo de personaje.
			$bioBday	 = 'Desconocido'; // Se resuelve desde timeline en bio_page_section_01_data.php
			$bioConcept	 = $dataResult["concept"]; 		// Concepto del personaje.
			$bioNature	 = $dataResult["nature_id"]; 	// Naturaleza del personaje.
			$bioBehavior = $dataResult["demeanor_id"]; 	// Conducta del personaje.
			$bioText	 = $dataResult["info_text"]; 	// Texto escrito que habla sobre el personaje.
			$bioNotes	 = (string)($dataResult["notes"] ?? ''); // Notas internas (solo admin flag).
		// ================================================================== //
			$pageSect 	 = "Biograf&iacute;a";						// Para cambiar el titulo a la pagina.
			$pageTitle2	 = $bioName;						// Título de la Página
			setMetaFromPage($bioName . " | Personajes | Heaven's Gate", meta_excerpt($bioText), $bioPhoto, 'article');
			$titleInfo	 = "&nbsp;Informaci&oacute;n&nbsp;";		// Titulo de la seccion "Informacion"
			$titleId	 = "&nbsp;Detalles de $bioName&nbsp;";// Titulo de la seccion "Identificacion"
			$titleAttr	 = "&nbsp;Atributos&nbsp;";			// Titulo de la seccion "Atributos"
			$titleForms	 = "&nbsp;Formas&nbsp;";				// Simulaci?n temporal de cambio de forma.
			$titleManeuvers = "&nbsp;Maniobras&nbsp;";
			$titleSkill	 = "&nbsp;Habilidades&nbsp;";		// Titulo de la seccion "Habilidades"
			$titleBackg	 = "&nbsp;Trasfondos&nbsp;";		// Titulo de la seccion "Trasfondos"
			$titleMerits = "&nbsp;M&eacute;ritos y Defectos&nbsp;";// Titulo de la seccion "Meritos y Defectos"
			$titleConditions = "&nbsp;Condiciones&nbsp;";		// Titulo de la seccion "Condiciones"
			$titleSocial = "&nbsp;Renombre&nbsp;"; 			// Titulo de la seccion "Social"
			$titleAdvant = "&nbsp;Estado&nbsp;";			// Titulo de la seccion "Estado"
			$titlePowers = "&nbsp;Poderes&nbsp;";			// Titulo de la seccion "Poderes"
			$titleItems	 = "&nbsp;Inventario&nbsp;";		// Titulo de la seccion "Inventario"
			$titleSameBio= "&nbsp;Relaciones de $bioName&nbsp;";// Título de la sección "Relaciones"
			$titleNebulo = "&nbsp;Nebulosa de relaciones&nbsp;";// Título de la sección "Nebulosa de relaciones"	
			$titleParticp= "&nbsp;Participaci&oacute;n&nbsp;";		// Titulo de la seccion "Participacion"		
		// ================================================================== //
		// Datos de jugador y crónica
			$bioPlayer	  = $dataResult["player_id"]; 	// Jugador al que pertenece el personaje.
			$bioChronic	  = $dataResult["chronicle_id"]; // Crónica a la que pertenece el personaje.
			$bioStatus	  = $dataResult["status"] ?? ""; 	// Estado legacy; puede no venir desde fact_characters.
			$bioDethCaus  = $dataResult["death_description"] ?? ""; // Causa de la muerte.
			$bioDeathDateRaw = (string)($dataResult["death_date"] ?? '');
			$bioDeathDisplay = '';
			$bioSheetRaw  = strtolower(trim((string)($dataResult["character_kind"] ?? $dataResult["kind"] ?? "")));
			$bioSheet	  = $bioSheetRaw; // Compatibilidad con código legacy.
			$bioIsMonster = in_array($bioSheetRaw, ["mon", "monster"], true);
			$bioHasSheet  = in_array($bioSheetRaw, ["pj", "mon", "monster"], true);
		// ================================================================== //
		// Datos de raza y alineamientos
			$bioRace	 = $dataResult["breed_id"]; 	// Raza a la que pertenece el personaje.
			$bioAuspice	 = $dataResult["auspice_id"]; 	// Auspicio del personaje.
			$bioTribe	 = $dataResult["tribe_id"]; 	// Tribu a la que pertenece el personaje.
			$bioRange	 = $dataResult["rank"]; 		// Rango de importancia del personaje en su organización.
		// ================================================================== //
		// Ventajas y poderes
			$bioTotem	 = ""; 		// Tótem que guí­a al personaje.
			$bioTotemId  = (int)($dataResult["totem_id"] ?? 0);
		// Género
			$bioGender	 = $dataResult["gender"];	// Género del personaje
		// Títulos de la sección Detalles		
			$titlePkName	= "Nombre Garou";		// Título del nombre Garou
		// Sistema, para nombres de detalles y tal.
			$bioSystem 	= (string)($dataResult["system_label"] ?? "");
			$bioSystemId = (int)($dataResult["system_id"] ?? 0);
			$systemDetailLabels = hg_characters_fetch_system_detail_labels($link, $bioSystemId);
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
			include ("app/partials/bio/bio_page_section_00_system.php"); // Utilizamos "include" para no sobrecargar la página con código
		// ================================================================== //
		$bioHasActions = false;

		if ($bioHasSheet) { // <--- Inicio de comprobación si lleva hoja
		// ================================================================== //
		// Traits normalizados (bridge_characters_traits)
			$traitValues = hg_characters_fetch_trait_values($link, (int)$characterId);

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
			'Atributos' => hg_characters_fetch_traits_for_system_type($link, (int)$characterId, (int)($dataResult['system_id'] ?? 0), 'Atributos', $bioIsMonster),
			'Talentos' => hg_characters_fetch_traits_for_system_type($link, (int)$characterId, (int)($dataResult['system_id'] ?? 0), 'Talentos', $bioIsMonster),
			'Técnicas' => hg_characters_fetch_traits_for_system_type($link, (int)$characterId, (int)($dataResult['system_id'] ?? 0), 'Técnicas', $bioIsMonster),
			'Conocimientos' => hg_characters_fetch_traits_for_system_type($link, (int)$characterId, (int)($dataResult['system_id'] ?? 0), 'Conocimientos', $bioIsMonster),
			'Trasfondos' => hg_characters_fetch_traits_for_system_type($link, (int)$characterId, (int)($dataResult['system_id'] ?? 0), 'Trasfondos', $bioIsMonster),
		];

		// Orden + extras al final (alfabético)
		$bioTraitsByType['Talentos'] = bio_order_trait_list($bioTraitsByType['Talentos'] ?? [], 10);
		$bioTraitsByType['Técnicas'] = bio_order_trait_list($bioTraitsByType['Técnicas'] ?? [], 10);
		$bioTraitsByType['Conocimientos'] = bio_order_trait_list($bioTraitsByType['Conocimientos'] ?? [], 10);

		$bioTraitImgsByType = [];
		foreach ($bioTraitsByType as $tipo => $list) {
			$vals = array_map(fn($t) => (int)($t['value'] ?? 0), $list);
			$bioTraitImgsByType[$tipo] = createSkillCircle($vals, 'gem-attr');
		}


			$bioForms = [];
			if ($bioSheetRaw === 'pj' && $bioSystemId > 0) {
				$formCandidates = hg_characters_fetch_forms_for_system($link, $bioSystemId);
				$formRaces = [];
				foreach ($formCandidates as $formRow) {
					$race = trim((string)($formRow['race'] ?? ''));
					if ($race !== '') $formRaces[$race] = true;
				}
				$bioFormRace = '';
				if (count($formRaces) > 1 && (int)$bioTribe > 0) {
					$tribeRow = hg_characters_fetch_lookup($link, 'dim_tribes', (int)$bioTribe, ['name']);
					$bioFormRace = trim((string)($tribeRow['name'] ?? ''));
				}
				$formModifiers = hg_characters_fetch_form_modifiers($link, array_column($formCandidates, 'id'));
				$formatManeuver = static function (array $maneuver) use ($link): array {
					$id = (int)($maneuver['id'] ?? 0);
					return [
						'id' => $id,
						'name' => trim((string)($maneuver['name'] ?? '')),
						'image_url' => trim((string)($maneuver['image_url'] ?? '')),
						'href' => pretty_url($link, 'fact_combat_maneuvers', '/rules/maneuvers', $id),
					];
				};
				$bioBaseManeuvers = [];
				foreach (hg_characters_fetch_system_maneuvers($link, $bioSystemId) as $maneuverRow) {
					$bioBaseManeuvers[(int)$maneuverRow['id']] = $formatManeuver($maneuverRow);
				}
				$formManeuversByForm = [];
				foreach (hg_characters_fetch_form_maneuvers($link, array_column($formCandidates, 'id')) as $formId => $maneuverRows) {
					foreach ($maneuverRows as $maneuverRow) {
						$formManeuversByForm[(int)$formId][(int)$maneuverRow['id']] = $formatManeuver($maneuverRow);
					}
				}
				$bioBaseManeuvers = array_values($bioBaseManeuvers);
				$legacyModifierColumns = [];
				foreach (($bioTraitsByType['Atributos'] ?? []) as $attribute) {
					$legacyColumn = ['Fuerza' => 'strength_bonus', 'Destreza' => 'dexterity_bonus', 'Resistencia' => 'stamina_bonus'][(string)($attribute['name'] ?? '')] ?? null;
					if ($legacyColumn !== null) $legacyModifierColumns[(int)($attribute['id'] ?? 0)] = $legacyColumn;
				}
				foreach ($formCandidates as $formRow) {
					if (count($formRaces) > 1 && ($bioFormRace === '' || (string)($formRow['race'] ?? '') !== $bioFormRace)) continue;
					$formId = (int)($formRow['id'] ?? 0);
					$formName = trim((string)($formRow['form'] ?? ''));
					$modifiers = $formModifiers[$formId] ?? [];
					foreach ($legacyModifierColumns as $traitId => $legacyColumn) {
						if (!array_key_exists($traitId, $modifiers)) $modifiers[$traitId] = (int)($formRow[$legacyColumn] ?? 0);
					}
					if (array_key_exists($formId, $formManeuversByForm)) {
						$formManeuvers = $formManeuversByForm[$formId];
					} else {
						$formManeuvers = [];
						foreach ($bioBaseManeuvers as $maneuver) {
							$maneuverId = (int)($maneuver['id'] ?? 0);
							if ($maneuverId > 0) $formManeuvers[$maneuverId] = $maneuver;
						}
					}
					$bioForms[] = ['id' => $formId, 'name' => $formName, 'modifiers' => $modifiers, 'maneuvers' => array_values($formManeuvers)];
				}
			}
		$bioActions = [];
		if ($bioSheetRaw === 'pj') {
			foreach (hg_characters_fetch_actions_for_sheet($link, $characterId) as $actionRow) {
				$actionRow['source_type'] = 'action';
				$actionRow['source_id'] = (int)$actionRow['id'];
				$actionRow['href'] = pretty_url($link, 'fact_actions', '/rules/actions', (int)$actionRow['id']);
				$bioActions[] = $actionRow;
			}
			foreach (hg_characters_fetch_maneuver_actions_for_sheet($link, $characterId, $bioSystemId) as $maneuverRow) {
				$maneuverRow['source_type'] = 'maneuver';
				$maneuverRow['source_id'] = (int)$maneuverRow['id'];
				$maneuverRow['href'] = pretty_url($link, 'fact_combat_maneuvers', '/rules/maneuvers', (int)$maneuverRow['id']);
				$bioActions[] = $maneuverRow;
			}
		}
		usort($bioActions, static function (array $left, array $right): int {
			return [(string)($left['category'] ?? ''), (string)($left['name'] ?? ''), (int)($left['roll_order'] ?? 0)]
				<=> [(string)($right['category'] ?? ''), (string)($right['name'] ?? ''), (int)($right['roll_order'] ?? 0)];
		});
		$bioHasActions = !empty($bioActions);
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
	} // <---- Fin de comprobación si lleva hoja de PJ
		
		// ======================================================================================
		// Nueva preparación 2025. Tabla de relaciones.
		// ======================================================================================
		$relaciones = hg_characters_fetch_relations($link, $characterId);
		$numRelaciones = count($relaciones);
		$killsAsKiller = hg_characters_fetch_kills($link, $characterId, $deathTable);
		$numKillsAsKiller = count($killsAsKiller);
		$participacion = hg_characters_fetch_chapter_participation($link, $characterId);
		$numParticipa = count($participacion);
		$numEventosParticipa = hg_characters_count_timeline_events($link, $characterId);
		$characterDocs = hg_characters_fetch_docs($link, $characterId);
		$characterExternalLinks = hg_characters_fetch_external_links($link, $characterId);
		$hasDocsLinks = (!empty($characterDocs) || !empty($characterExternalLinks));

		// Flags de secciones
		$hasInfo = true;
		$hasSheet = $bioHasSheet;
		$hasRel = ((isset($relaciones) && $numRelaciones > 0) || $numKillsAsKiller > 0);
		$hasPart = ((isset($participacion) && $numParticipa > 0) || $numEventosParticipa > 0);
		$characterComments = hg_characters_fetch_comments($link, $characterId);
		$hasComments = !empty($characterComments);
		$hasBso = hg_characters_has_soundtrack($link, $characterId);

		// Hacemos un repaso a los datos y obtenemos los enlaces que corresponden
		// ----------------------------------------- //
		include ("app/partials/bio/bio_page_section_01_data.php"); // Utilizamos "include" para no sobrecargar la página con código
		// ----------------------------------------- //
