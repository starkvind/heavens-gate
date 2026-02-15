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
			$bioName 	 = $dataResult["nombre"]; 		// Nombre completo del personaje.
			$bioAlias 	 = $dataResult["alias"]; 		// Alias del personaje, como le llaman.
			$bioPackName = $dataResult["nombregarou"]; 	// Nombre de manada. Como "Cláusula", "Churrasco", "Chili-Chingón", etc.
			$bioPhoto	 = $dataResult["img"]; 			// Imagen del personaje.
			$bioType	 = $dataResult["tipo"]; 		// Tipo de personaje. Si es Protagonista o pertenece al grupo de PNJs.
			$bioBday	 = $dataResult["cumple"]; 		// Cumpleaños del personaje.
			$bioConcept	 = $dataResult["concepto"]; 	// Concepto del personaje.
			$bioNature	 = $dataResult["naturaleza"]; 	// Naturaleza del personaje, como es en realidad.
			$bioBehavior = $dataResult["conducta"]; 	// Conducta del personaje, como se comporta.
			$bioText	 = $dataResult["infotext"]; 	// Texto escrito que habla sobre el personaje.
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
			$bioPlayer	 = $dataResult["jugador"]; 		// Jugador al que pertenece el personaje.
			$bioChronic	 = $dataResult["cronica"]; 		// Crónica a la que pertenece el personaje.
			$bioStatus	 = $dataResult["estado"]; 		// Estado del personaje. Si está "activo" o "muerto", etc.
			$bioDethCaus = $dataResult["causamuerte"]; 	// Causa de la muerte. Si está vivo o no, no lo usaremos.
			$bioSheet	 = $dataResult["kes"]; 			// Si el personaje posee Ficha de Personaje o no. Los que no tienen, muestran menos datos.
			$bioXP		 = $dataResult["px"]; 			// Puntos de experiencia restantes del personaje.
		// ================================================================== //
		// Datos de raza y alineamientos
			$bioRace	 = $dataResult["raza"]; 		// Raza a la que pertenece el personaje.
			$bioAuspice	 = $dataResult["auspicio"]; 	// Auspicio al que pertenece el personaje.
			$bioTribe	 = $dataResult["tribu"]; 		// Tribu a la que pertenece el personaje.
			$bioRange	 = $dataResult["rango"]; 		// Rango de importancia del personaje en su organización.
		// ================================================================== //
		// Ventajas y poderes
			$bioTotem	 = (string)($dataResult["totem_name"] ?? $dataResult["totem"] ?? ""); 		// Tótem que guía al personaje.
		// Género
			$bioGender	 = $dataResult["genero_pj"];		// Género del personaje
		// Títulos de la sección Detalles		
			$titlePkName	= "Nombre Garou";		// Título del nombre Garou
		// Sistema, para nombres de detalles y tal.
			$bioSystem 	= (string)($dataResult["system_name"] ?? $dataResult["sistema"] ?? "");
		// Nombres de conceptos
			// ================================================================== //
			// Datos y Nombre del Sistema
			$bioFera = $dataResult["fera"];
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
		// Datos sociales
			$bioArrayPow = array(
				$dataResult["gloriap"],		// Gloria para Fêra, Conciencia para Vampiro
				$dataResult["honorp"],		// Honor para Fêra, Autocontrol para Vampiro
				$dataResult["sabiduriap"],	// Sabiduria para Fêra, Coraje para Vampiro
				$dataResult["rabiap"],		// Rabia para Fêra
				$dataResult["gnosisp"],		// Gnosis para Fêra, Senda para Vampiro
				$dataResult["fvp"],			// Fuerza de Voluntad
			);
		// Atributos
			$bioArrayAtt = array(
				// FISICOS
				$dataResult["fuerza"],
				$dataResult["destreza"],
				$dataResult["resistencia"],	
				// SOCIALES				
				$dataResult["carisma"],
				$dataResult["manipulacion"],
				$dataResult["apariencia"],
				// MENTALES				
				$dataResult["percepcion"],
				$dataResult["inteligencia"],
				$dataResult["astucia"],
			);
		// ================================================================== //
		// Habilidades
			$bioArraySki = array(
				// TALENTOS
				$dataResult["alerta"],			 
				$dataResult["atletismo"],		 
				$dataResult["callejeo"], 		
				$dataResult["empatia"], 			 
				$dataResult["esquivar"], 		 
				$dataResult["expresion"], 		 
				$dataResult["impulsprimario"], 	 
				$dataResult["intimidacion"],	 
				$dataResult["pelea"],	
				$dataResult["subterfugio"],
				// TECNICAS
				$dataResult["armascc"],
				$dataResult["armasdefuego"],
				$dataResult["conducir"],
				$dataResult["etiqueta"],
				$dataResult["interpretacion"],
				$dataResult["liderazgo"],
				$dataResult["reparaciones"],
				$dataResult["sigilo"],
				$dataResult["supervivencia"],
				$dataResult["tratoanimales"],
				// CONOCIMIENTOS
				$dataResult["ciencias"],
				$dataResult["enigmas"],
				$dataResult["informatica"],
				$dataResult["investigacion"],
				$dataResult["leyes"],
				$dataResult["linguistica"],
				$dataResult["medicina"],
				$dataResult["ocultismo"],
				$dataResult["politica"],
				$dataResult["rituales"],
				// HABILIDADES SECUNDARIAS
				$dataResult["talento1valor"],
				$dataResult["talento2valor"],
				$dataResult["tecnica1valor"],
				$dataResult["tecnica2valor"],
				$dataResult["conoci1valor"],
				$dataResult["conoci2valor"],
				// TRASFONDOS
				$dataResult["trasfondo1valor"],
				$dataResult["trasfondo2valor"],
				$dataResult["trasfondo3valor"],
				$dataResult["trasfondo4valor"],
				$dataResult["trasfondo5valor"],
			);
		// Habilidades Secundarias
			$bioTale1N		 = $dataResult["talento1extra"]; 	// Talento 1 extra del PJ.
			$bioTale2N		 = $dataResult["talento2extra"]; 	// Talento 2 extra del PJ.
			$bioTecn1N		 = $dataResult["tecnica1extra"]; 	// Técnica 1 extra del PJ.
			$bioTecn2N		 = $dataResult["tecnica2extra"]; 	// Técnica 2 extra del PJ.
			$bioCono1N		 = $dataResult["conoci1extra"]; 	// Conocimiento 1 extra del PJ.
			$bioCono2N		 = $dataResult["conoci2extra"]; 	// Conocimiento 2 extra del PJ.
		// ================================================================== //
		// Trasfondos
			$bioBack1N		 = $dataResult["trasfondo1"]; 	// Trasfondo 1 del PJ.
			$bioBack2N		 = $dataResult["trasfondo2"]; 	// Trasfondo 2 del PJ.
			$bioBack3N		 = $dataResult["trasfondo3"]; 	// Trasfondo 3 del PJ.
			$bioBack4N		 = $dataResult["trasfondo4"]; 	// Trasfondo 4 del PJ.
			$bioBack5N		 = $dataResult["trasfondo5"]; 	// Trasfondo 5 del PJ.
		// ================================================================== //
			}
	 	} // <---- Fin de comprobación si lleva hoja de PJ
		
		// ======================================================================================
		// Nueva preparación 2025. Tabla de relaciones.
		// ======================================================================================
		$relaciones = [];
		
		// Relaciones salientes
		$stmt1 = $link->prepare("SELECT cr.*, p2.nombre, p2.alias, p2.img, p2.genero_pj, 'outgoing' as direction
								FROM bridge_characters_relations cr
								LEFT JOIN fact_characters p2 ON cr.target_id = p2.id
								WHERE cr.source_id = ?
								ORDER BY cr.relation_type");
		$stmt1->bind_param('i', $characterId);
		$stmt1->execute();
		$stm11_results = stmt_fetch_all_assoc_compat($stmt1);
		$relaciones = array_merge($relaciones, $stm11_results);

		// Relaciones entrantes
		$stmt2 = $link->prepare("SELECT cr.*, p2.nombre, p2.alias, p2.img, p2.genero_pj, 'incoming' as direction
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
		$stmtP = $link->prepare("SELECT ac.id, ac.name, ac.capitulo, at2.name AS temporada_name, at2.numero, ac.fecha FROM dim_chapters ac
								INNER JOIN bridge_chapters_characters acp ON ac.id = acp.id_capitulo 
								INNER JOIN dim_seasons at2 ON ac.temporada = at2.numero 
								WHERE acp.id_personaje = ?
								ORDER BY ac.fecha, ac.capitulo");
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
				if ($bioBack1N != "") {
					echo"<div class='bioSheetBackgroundLeft'>" . h($bioBack1N) . ":</div>";
					echo"<div class='bioSheetBackgroundRight'>$bioSkilImg[36]</div>";
				}
				if ($bioBack2N != "") {
					echo"<div class='bioSheetBackgroundLeft'>" . h($bioBack2N) . ":</div>";
					echo"<div class='bioSheetBackgroundRight'>$bioSkilImg[37]</div>";
				}
				if ($bioBack3N != "") {
					echo"<div class='bioSheetBackgroundLeft'>" . h($bioBack3N) . ":</div>";
					echo"<div class='bioSheetBackgroundRight'>$bioSkilImg[38]</div>";
				}
				if ($bioBack4N != "") {
					echo"<div class='bioSheetBackgroundLeft'>" . h($bioBack4N) . ":</div>";
					echo"<div class='bioSheetBackgroundRight'>$bioSkilImg[39]</div>";
				}
				if ($bioBack5N != "") {
					echo"<div class='bioSheetBackgroundLeft'>" . h($bioBack5N) . ":</div>";
					echo"<div class='bioSheetBackgroundRight'>$bioSkilImg[40]</div>";
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
