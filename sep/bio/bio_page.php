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
	$idGetData = $_GET['b']; // Cogemos datos del GET "b"
	$orderData ="SELECT * FROM pjs1 WHERE id = ? LIMIT 1;"; // Elegimos al PJ de la Base de Datos
	$stmt = mysqli_prepare($link, $orderData);
	mysqli_stmt_bind_param($stmt, 'i', $idGetData);
	mysqli_stmt_execute($stmt);
	$result = mysqli_stmt_get_result($stmt);
	$NFilas = mysqli_num_rows($result);

	if ($NFilas > 0) { // Comenzamos chequeo de datos. Si no tenemos nada, mandamos un mensaje de error.
		while ($dataResult = mysqli_fetch_assoc($result)) {
		#$dataResult = mysql_fetch_array($queryData); // Empezamos a recolectar los datos. ~~ #SEC01
		// ================================================================== //
		// Datos básicos del personaje
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
			$bioTheme	 = $dataResult["temanombre"]; 	// Canción del personaje.
			$bioThemeUrl = $dataResult["temaurl"]; 		// Dirección de la canción del personaje.
		// ================================================================== //
			$pageSect 	 = "Biografía";						// Para cambiar el título a la página.
			$pageTitle2	 = $bioName;						// Título de la Página
			$titleInfo	 = "&nbsp;Información&nbsp;";		// Titulo de la seccion "Información"
			$titleId	 = "&nbsp;Detalles&nbsp;";			// Titulo de la seccion "Identificación"
			$titleAttr	 = "&nbsp;Atributos&nbsp;";			// Titulo de la seccion "Atributos"
			$titleSkill	 = "&nbsp;Habilidades&nbsp;";		// Titulo de la seccion "Habilidades"
			$titleBackg	 = "&nbsp;Trasfondos&nbsp;";		// Titulo de la seccion "Trasfondos"
			$titleMerits = "&nbsp;Méritos y Defectos&nbsp;";// Titulo de la seccion "Méritos y Defectos"
			$titleSocial = "&nbsp;Renombre&nbsp;"; 			// Titulo de la seccion "Social"
			$titleAdvant = "&nbsp;Ventajas&nbsp;";			// Titulo de la seccion "Ventajas"
			$titlePowers = "&nbsp;Dones&nbsp;";				// Titulo de la seccion "Poderes"
			$titleRites	 = "&nbsp;Rituales&nbsp;";			// Titulo de la seccion "Rituales"
			$titleItems	 = "&nbsp;Inventario&nbsp;";		// Titulo de la seccion "Inventario"
			$titleSameBio= "&nbsp;Relaciones&nbsp;";		// Título de la sección "Biografías similares"
			$titleNebulo = "&nbsp;Nebulosa de relaciones&nbsp;";// Título de la sección "Biografías similares"
			$titleKills	 = "&nbsp;Asesinatos&nbsp;";		// Título de la sección "Asesinatos"
			$titleComment= "&nbsp;Comentarios&nbsp;";		// Titulo de la seccion "Comentarios"		
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
			$bioPack	 = $dataResult["manada"]; 		// Manada, cuadrilla o grupo a la que pertenece el personaje.
			$bioClan	 = $dataResult["clan"]; 		// Organización a la que pertenece el personaje.
			$bioRange	 = $dataResult["rango"]; 		// Rango de importancia del personaje en su organización.
		// ================================================================== //
		// Ventajas y poderes
			$bioTotem	 = $dataResult["totem"]; 		// Tótem que guía al personaje.
			$bioPowers	 = $dataResult["poderes"]; 		// Poderes que tiene en posesión el personaje.
			$bioRites	 = $dataResult["ritos"]; 		// Rituales que conoce el personaje. 
			$bioMerFla	 = $dataResult["merydef"]; 		// Méritos y defectos que tiene en posesión el personaje. 
			$bioItems	 = $dataResult["objetos"]; 		// Méritos y defectos que tiene en posesión el personaje. 
			$bioFamily	 = $dataResult["familia"];		// Familiares del personajes
		// Comprobación de campos vacíos
			$bioEmptyPow = "dones;----------";			// Si la sección Poderes tiene estos datos, está vacía.
			$bioEmptyRit = "---------";					// Si la sección Ritos tiene estos datos, está vacía.
			$bioEmptyInv = "-----";						// Si la sección Objetos tiene estos datos, está vacía.
		// Género
			$bioGender	 = $dataResult["genero_pj"];		// Familiares del personajes
		// Icono de Biografías
			$bioSameIcon = "img/kek.gif";				// Icono para los personajes similares
		// Títulos de la sección Detalles		
			$titlePkName	= "Nombre Garou";		// Título del nombre Garou
			
		// ================================================================== //
		if ($bioSheet == "pj") { // <--- Inicio de comprobación si lleva hoja de PJ
		// ================================================================== //
		// Datos y Nombre del Sistema
		$bioFera 	= $dataResult["fera"];
		$bioSystem 	= $dataResult["sistema"];
			// ------ > Basicos
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
			// Cambiamos títulos de secciones acorde al Sistema del PJ
			include ("bio_page_section_00_system.php"); // Utilizamos "include" para no sobrecargar la página con código
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
		// Hoja de Personaje
			$bioFacebook	 = ""; //"http://www.facebook.com/sharer.php?u=$pageURL";
			$bioTwitter		 = ""; //"http://twitter.com/home?status=$pageURL";
			$bioCharSheet	 = "javascript:popUp('dsk/character_sheet.php?char=$idGetData')";
			}
	 	} // <---- Fin de comprobación si lleva hoja de PJ

		/* OTRAS PINGAS */

		// BIOGRAFIAS SIMILARES
		$consultaSameBio = "SELECT id, nombre FROM pjs1 WHERE nombre LIKE ? AND id != ? LIMIT 10";
		$stmtSameBio = $link->prepare($consultaSameBio);
		
		// Preparamos el parámetro para la búsqueda con LIKE
		$searchBioName = "%{$bioName}%";
		$stmtSameBio->bind_param('ss', $searchBioName, $idGetData);
		$stmtSameBio->execute();
		$resultSameBio = $stmtSameBio->get_result();

		// Variable para el número de resultados de biografías similares
		$numberFilasSameBio = $resultSameBio->num_rows;
		
		$sameBioId = [];
		$sameBioName = [];
		
		if ($numberFilasSameBio > 0) {
			while ($row = $resultSameBio->fetch_assoc()) {
				$sameBioId[] = htmlspecialchars($row["id"]);
				$sameBioName[] = htmlspecialchars($row["nombre"]);
				// echo "<p>#$row[id] - $row[nombre]</p>";
			}
		}
		$stmtSameBio->close();
		
		// ASESINATOS
		$consultaKills = "SELECT id, nombre FROM pjs1 WHERE causamuerte LIKE ?";
		$stmtKills = $link->prepare($consultaKills);
		
		// Preparamos el parámetro para la búsqueda con LIKE
		$searchBioName = "%{$bioName}%";
		$stmtKills->bind_param('s', $searchBioName);
		$stmtKills->execute();
		$resultKills = $stmtKills->get_result();

		// Variable para el número de resultados de asesinatos
		$numberFilasKills = $resultKills->num_rows;
		
		$killsId = [];
		$killsName = [];
		
		if ($numberFilasKills > 0) {
			while ($row = $resultKills->fetch_assoc()) {
				$killsId[] = htmlspecialchars($row["id"]);
				$killsName[] = htmlspecialchars($row["nombre"]);
				// echo "<p>#$row[id] - $row[nombre]</p>";
			}
		}
		$stmtKills->close();
		
		// Nueva preparación 2025. Tabla de relaciones.
		
		$relaciones = [];
		
		// Relaciones salientes
		$stmt1 = $link->prepare("SELECT cr.*, p2.nombre, p2.alias, p2.img, p2.genero_pj, 'outgoing' as direction
								FROM character_relations cr
								LEFT JOIN pjs1 p2 ON cr.target_id = p2.id
								WHERE cr.source_id = ?
								ORDER BY cr.relation_type");
		$stmt1->bind_param('s', $idGetData);
		$stmt1->execute();
		$stm11_results = $stmt1->get_result()->fetch_all(MYSQLI_ASSOC);
		$relaciones = array_merge($relaciones, $stm11_results);

		// Relaciones entrantes
		$stmt2 = $link->prepare("SELECT cr.*, p2.nombre, p2.alias, p2.img, p2.genero_pj, 'incoming' as direction
								FROM character_relations cr
								LEFT JOIN pjs1 p2 ON cr.source_id = p2.id
								WHERE cr.target_id = ?
								ORDER BY cr.relation_type");
		$stmt2->bind_param('s', $idGetData);
		$stmt2->execute();
		$stm12_results = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
		$relaciones = array_merge($relaciones, $stm12_results);
		// Ordenar alfabéticamente por 'relation_type'
		usort($relaciones, function($a, $b) {
			return strcasecmp($a['relation_type'], $b['relation_type']);
		});
		
		$numRelaciones = count($relaciones);
		
		// Hacemos un repaso a los datos y obtenemos los enlaces que corresponden
		// ----------------------------------------- //
		include ("bio_page_section_01_data.php"); // Utilizamos "include" para no sobrecargar la página con código
		// ----------------------------------------- //
		/* MODERNO NUEVO */
		include("sep/main/main_nav_bar.php");	// Barra Navegación
		echo "<h2>$bioName</h2>"; // Encabezado de página
		/* =========== */
		include("sep/main/main_social_menu.php");	// Zona de Impresión y Redes Sociales
		// ================================================================== //
	echo "<div class='bioBody'>"; // CUERPO PRINCIPAL DE LA FICHA DE INFORMACION
		// ================================================================== //
		echo "<div class='bioSquarePhoto'>"; // Colocamos la Fotografia del personaje ~~ #SEC02
			echo "<img class='photobio' src='$bioPhoto' alt='$bioName'/>";
		echo"</div>"; // Dejamos la Fotografía ya colocada
		// ================================================================== //
		echo "<div class='bioSquareData'>"; // Comenzamos a colocar los datos básicos ~~ #SEC03
			// ----------------------------------------- //
			include ("bio_page_section_03_details.php"); // Utilizamos "include" para no sobrecargar la página con código
			// ----------------------------------------- //
		echo "</div>"; // Finalizamos los datos básicos
		// ================================================================== //
		if ($bioText != "") { // Empezamos colocando la información de Texto
			echo "<div class='bioTextData'>"; 
				echo "<fieldset class='bioSeccion'><legend>$titleInfo</legend>$bioText</fieldset>";
			echo "</div>";
		} // Finalizamos de poner el Texto
		// ================================================================== //
		if ($bioSheet == "pj") { // Comprobamos si el personaje dispone de Hoja de Personaje
			// ----
			echo "<div class='bioSheetData'>"; // Parte Superior de la Hoja ~~ #SEC04
			echo "<fieldset class='bioSeccion'><legend>$titleId</legend>";
				// ----------------------------------------- //
				include ("bio_page_section_04_sheetup.php"); // Utilizamos "include" para no sobrecargar la página con código
				// ----------------------------------------- //
			echo "</fieldset>";
			echo "</div>"; // Cerramos Parte Superior ~~
			// ================================================================== //
			echo "<div class='bioSheetData'>"; // Atributos de la Hoja ~~ #SEC05
			echo "<fieldset class='bioSeccion'><legend>$titleAttr</legend>";
				// ----------------------------------------- //
				include ("bio_page_section_05_attributes.php"); // Utilizamos "include" para no sobrecargar la página con código
				// ----------------------------------------- //
			echo "</fieldset>";
			echo "</div>"; // Cerramos Atributos ~~
			// ================================================================== //
			echo "<div class='bioSheetData'>"; // Habilidades de la Hoja ~~ #SEC06
			echo "<fieldset class='bioSeccion'><legend>$titleSkill</legend>";
				include ("bio_page_section_06_skills.php"); // Utilizamos "include" para no sobrecargar la página con códigoç
			echo "</fieldset>";
			echo "</div>"; // Cerramos Habilidades ~~
			// ================================================================== //
			echo "<div class='bioSheetBackgrounds'>"; // Trasfondos de la Hoja ~~ #SEC07
			echo "<fieldset class='bioSeccion'><legend>$titleBackg</legend>";
				if ($bioBack1N != "") {
					echo"<div class='bioSheetBackgroundLeft'>$bioBack1N:</div>";
					echo"<div class='bioSheetBackgroundRight'>$bioSkilImg[36]</div>";
				}
				if ($bioBack2N != "") {
					echo"<div class='bioSheetBackgroundLeft'>$bioBack2N:</div>";
					echo"<div class='bioSheetBackgroundRight'>$bioSkilImg[37]</div>";
				}
				if ($bioBack3N != "") {
					echo"<div class='bioSheetBackgroundLeft'>$bioBack3N:</div>";
					echo"<div class='bioSheetBackgroundRight'>$bioSkilImg[38]</div>";
				}
				if ($bioBack4N != "") {
					echo"<div class='bioSheetBackgroundLeft'>$bioBack4N:</div>";
					echo"<div class='bioSheetBackgroundRight'>$bioSkilImg[39]</div>";
				}
				if ($bioBack5N != "") {
					echo"<div class='bioSheetBackgroundLeft'>$bioBack5N:</div>";
					echo"<div class='bioSheetBackgroundRight'>$bioSkilImg[40]</div>";
				}
			echo "</fieldset>";
			echo "</div>"; // Cerramos Trasfondos ~~
			// ================================================================== //
			echo "<div class='bioSheetMeritFlaws'>"; // Méritos y Defectos de la Hoja ~~ #SEC08
			echo "<fieldset class='bioSeccion'><legend>$titleMerits</legend>";
				if ($bioMerFla != "") {
					// ----------------------------------------- //
					include ("bio_page_section_08_merits.php"); // Utilizamos "include" para no sobrecargar la página con código
					// ----------------------------------------- //
				} else {
					echo "<p style='text-align:center;'>Este personaje no posee Méritos o Defectos</p>";
				}
			echo "</fieldset>";
			echo "</div>"; // Cerramos Méritos y Defectos ~~
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
					echo"<div class='bioSheetSocialPowerRight'>$bioRange</div>";
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
					echo"<div class='bioSheetSocialPowerRight'>$bioXP PX</div>";				
				}
			echo "</fieldset>";
			echo "</div>"; // Cerramos Fuerza de Voluntad y demás~~
		echo "</div>"; // Caja de la Seccion SOCIAL y VENTAJAS
			// ================================================================== //
			if ($bioPowers != $bioEmptyPow) { // Si el personaje no tiene Poderes, no mostramos nada.
				echo "<div class='bioSheetPowers'>"; // Poderes de la Hoja ~~ #SEC11
				echo "<fieldset class='bioSeccion'><legend>$titlePowers</legend>";
				include ("bio_page_section_11_power.php"); // Utilizamos "include" para no sobrecargar la página con código
				echo "</fieldset>";
				echo "</div>"; // Cerramos Poderes ~~
			}
			// ================================================================== //
			if ($bioRites != $bioEmptyRit) { // Si el personaje no tiene Ritos, no mostramos nada.
				echo "<div class='bioSheetPowers'>"; // Ritos de la Hoja ~~ #SEC12
				echo "<fieldset class='bioSeccion'><legend>$titleRites</legend>";
				include ("bio_page_section_12_rites.php"); // Utilizamos "include" para no sobrecargar la página con código
				echo "</fieldset>";
				echo "</div>"; // Cerramos Ritos ~~
			}
			// ================================================================== //
			if ($bioItems != $bioEmptyInv) { // Si el personaje no tiene Objetos, no mostramos nada.
				echo "<div class='bioSheetPowers'>"; // Objetos y Fetiches de la Hoja ~~ #SEC13
				echo "<fieldset class='bioSeccion'><legend>$titleItems</legend>";
				include ("bio_page_section_13_items.php"); // Utilizamos "include" para no sobrecargar la página con código
				echo "</fieldset>";
				echo "</div>"; // Cerramos Objetos y Fetiches ~~
			}
		} // Finalizamos la Hoja de Personaje
		// ================================================================== //
		#if ((isset($numberFilasSameBio) && $numberFilasSameBio > 0) OR $bioFamily != "") {
		/*
			--------------------------------------------------------------
			2025: retiramos las celdas de relaciones. 
			--------------------------------------------------------------
		*/
		/*
		if ((isset($relaciones)) && $numRelaciones > 0) {
			echo "<div class='bioTextData'>"; // Biografías similares y Familiares~~ #SEC14
			echo "<fieldset class='bioSeccion'><legend>$titleSameBio</legend>";
				include ("bio_page_section_14_family.php"); // Utilizamos "include" para no sobrecargar la página con código
			echo "</fieldset>";
			echo "</div>"; // Cerramos Biografías similares y Familiares ~~ 
		}
		
		if ((isset($relaciones)) && $numRelaciones > 0) {
			echo "<div class='bioTextData'>"; // Nebulosa de relaciones~~ #SEC17
			echo "<fieldset class='bioSeccion'><legend>$titleNebulo</legend>";
				include ("bio_page_section_17_rel_graph.php"); // Utilizamos "include" para no sobrecargar la página con código
			echo "</fieldset>";
			echo "</div>"; // Cerramos Biografías similares y Familiares ~~ 
		} */
		?>
		
		<?php if ((isset($relaciones)) && $numRelaciones > 0): ?>
	
			<div class="bioTextData">
				<fieldset class='bioSeccion'>
					<legend><?= ($titleSameBio) ?></legend>
					<button id="toggleRelaciones" class="boton2" style="float: right; margin-right:0.3em;" type="button">Cambiar vista</button>
					<div id="seccion2">
						<?php include("bio_page_section_17_rel_graph.php"); ?>
					</div>
					<div id="seccion1" style='display: none;'>
						<?php include("bio_page_section_14_family.php"); ?>
					</div>
				</fieldset>
			</div>

		<?php endif; ?>

		<?php
		// ================================================================== // numberFilasKills
		/*
		if ((isset($numberFilasKills) && $numberFilasKills > 0)) {
			echo "<div class='bioTextData'>"; // Asesinatos ~~ #SEC15
			echo "<fieldset class='bioSeccion'><legend>$titleKills</legend>";
				include ("bio_page_section_15_kills.php"); // Utilizamos "include" para no sobrecargar la página con código
			echo "</fieldset>";
			echo "</div>"; // Cerramos Asesinatos ~~ 
		}
		*/
		// ================================================================== //
		/*
			--------------------------------------------------------------
			2025: retiramos los comentarios. No aportaban nada.
			--------------------------------------------------------------
		echo "<div class='bioTextData'>"; // Comentarios de la Hoja ~~ #SEC16
		echo "<fieldset class='bioSeccion'><legend>$titleComment</legend>";
			include ("bio_page_section_16_comments.php"); // Utilizamos "include" para no sobrecargar la página con código
		echo "</fieldset>";
		echo "</div>"; // Cerramos Comentarios ~~ 
		*/
	echo "</div>"; // FIN DE CUERPO PRINCIPAL DE LA FICHA DE INFORMACION
	} else {
		echo "<p style='text-align:center;'>$mensajeDeError</p>"; // Mensaje de error en caso de introducir datos manualmente. Tomado del Cuerpo Trabajar
	}
?>


<script>
	document.addEventListener('DOMContentLoaded', () => {
		const btnToggle = document.getElementById('toggleRelaciones');
		const seccion1 = document.getElementById('seccion1');
		const seccion2 = document.getElementById('seccion2');

		btnToggle.addEventListener('click', () => {
			if (seccion1.style.display === 'none') {
				seccion1.style.display = 'block';
				seccion2.style.display = 'none';
			} else {
				seccion1.style.display = 'none';
				seccion2.style.display = 'block';
			}
		});
	});
</script>