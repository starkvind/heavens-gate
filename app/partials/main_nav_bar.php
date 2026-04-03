<?php ob_start(); ?>

	<?php
		// <p class="navegacion_secciones">
		// include("app/partials/main_nav_bar.php");	// Barra Navegacion
		$routeKey = isset($routeKey)
			? (string)$routeKey
			: (isset($GLOBALS['routeKey']) ? (string)$GLOBALS['routeKey'] : trim((string)($_GET['p'] ?? '')));
		$pillSeparator = "&raquo;";
		$systemSeresSobrenaturales = "<a href='/systems'> Seres sobrenaturales</a> $pillSeparator ";
		$namePJ = isset($namePJ) ? (string)$namePJ : '';
		$surnamePJ = isset($surnamePJ) ? (string)$surnamePJ : '';
		$nombreTipo = isset($nombreTipo) ? (string)$nombreTipo : '';
		$nameTipo = isset($nameTipo) ? (string)$nameTipo : '';
		$bioName = isset($bioName) ? (string)$bioName : '';
		$packNavLinks = isset($packNavLinks) ? (string)$packNavLinks : '';
		$pageTitle2 = isset($pageTitle2) ? (string)$pageTitle2 : '';
		$nameTemporada = isset($nameTemporada) ? (string)$nameTemporada : '';
		$numeracionOK = isset($numeracionOK) ? (string)$numeracionOK : '';
		$title = isset($title) ? (string)$title : '';
		$titleDoc = isset($titleDoc) ? (string)$titleDoc : '';
		$nameTypeBack = isset($nameTypeBack) ? (string)$nameTypeBack : '';
		$itemName = isset($itemName) ? (string)$itemName : '';
		$systemName = isset($systemName) ? (string)$systemName : '';
		$returnType = isset($returnType) ? (string)$returnType : '';
		$nameWereForm = isset($nameWereForm) ? (string)$nameWereForm : '';
		$nameSyst = isset($nameSyst) ? (string)$nameSyst : '';
		$nameSkill = isset($nameSkill) ? (string)$nameSkill : '';
		$mafName = isset($mafName) ? (string)$mafName : '';
		$maneName = isset($maneName) ? (string)$maneName : '';
		$archeName = isset($archeName) ? (string)$archeName : '';
		$routeLabel = isset($routeLabel) ? (string)$routeLabel : '';
		$donName = isset($donName) ? (string)$donName : '';
		$riteName = isset($riteName) ? (string)$riteName : '';
		$totemName = isset($totemName) ? (string)$totemName : '';
		$playerIdNav = isset($pjId) ? (int)$pjId : (isset($idPlayer) ? (int)$idPlayer : 0);
		$bioIdNav = isset($bioId) ? (int)$bioId : (isset($characterId) ? (int)$characterId : 0);
		$docIdNav = isset($docId) ? (int)$docId : (isset($idDoc) ? (int)$idDoc : 0);
		$itemIdNav = isset($itemId) ? (int)$itemId : (isset($idItem) ? (int)$idItem : 0);
		$mafIdNav = isset($mafId) ? (int)$mafId : 0;
		$skillIdNav = isset($skillId) ? (int)$skillId : 0;
		$maneIdNav = isset($maneId) ? (int)$maneId : 0;
		$archeIdNav = isset($archeId) ? (int)$archeId : 0;
		$donIdNav = isset($donId) ? (int)$donId : 0;
		$donTypeNav = isset($donType) ? (int)$donType : (isset($routeParam) ? (int)$routeParam : 0);
		$riteIdNav = isset($riteId) ? (int)$riteId : 0;
		$riteTypeNav = isset($riteType) ? (int)$riteType : (isset($routeParam) ? (int)$routeParam : 0);
		$totemIdNav = isset($totemId) ? (int)$totemId : 0;
		$totemTypeNav = isset($totemType) ? (int)$totemType : (isset($routeParam) ? (int)$routeParam : 0);
		$formIdNav = isset($systemIdWere) ? (int)$systemIdWere : (isset($formId) ? (int)$formId : 0);

		switch ($routeKey) {
			// ========================================== //
			// Administracion
			// ========================================== //
			case "talim": // 
				if (isset($_GET['s'])) {
					$seccion = htmlspecialchars($_GET['s']); // Sanear entrada
					echo "<a href='/talim' title='Administraci&oacute;n'>Administraci&oacute;n</a>";
					switch ($seccion) {
						case 'admin_pjs':
						case 'admin_characters':
							echo " $pillSeparator Personajes";
							break;
						case 'admin_pjs_text':
							echo " $pillSeparator Personajes (TEXT)";
							break;
						case 'admin_pjs_crud':
							echo " $pillSeparator Personajes (CRUD)";
							break;
						case 'admin_groups':
							echo " $pillSeparator Grupos (Manadas & Clanes)";
							break;
						case 'admin_temp':
						case 'admin_seasons':
							echo " $pillSeparator Temporadas";
							break;
						case 'admin_epis':
						case 'admin_chapters':
							echo " $pillSeparator Capítulos";
							break;
						case 'admin_pois':
							echo " $pillSeparator Mapas";
							break;
						case 'admin_players':
							echo " $pillSeparator Jugadores";
							break;
						case 'admin_chronicles':
							echo " $pillSeparator Cr&oacute;nicas";
							break;
						case 'admin_realities':
							echo " $pillSeparator Realidades";
							break;
						case 'admin_timelines':
							echo " $pillSeparator L&iacute;nea temporal";
							break;
						case 'admin_birthdays_quick':
							echo " $pillSeparator Cumplea&ntilde;os r&aacute;pidos";
							break;
						case 'admin_bso':
						case 'admin_bso_link':
							echo " $pillSeparator Banda sonora";
							break;
						case 'admin_gallery':
							echo " $pillSeparator Galeria";
							break;
						case 'admin_plots':
						case 'admin_parties':
							echo " $pillSeparator Tramas";
							break;
						case 'admin_powers':
							echo " $pillSeparator Poderes";
							break;
						case 'admin_items':
							echo " $pillSeparator Objetos";
							break;
						case 'admin_menu':
							echo " $pillSeparator Menu";
							break;
						case 'admin_relations':
							echo " $pillSeparator Relaciones";
							break;
						case 'admin_docs':
							echo " $pillSeparator Documentacion";
							break;
						case 'admin_topic_viewer':
							echo " $pillSeparator Temas Visor Foro";
							break;
					case 'admin_news':
						echo " $pillSeparator Noticias";
						break;
						case 'admin_systems':
						echo " $pillSeparator Sistemas";
						break;
					case 'admin_forms':
						echo " $pillSeparator Formas";
						break;
					case 'admin_system_details':
						echo " $pillSeparator Detalles de sistemas";
						break;
					case 'admin_systems_extra_details':
						echo " $pillSeparator Extra details to system";
						break;
					case 'admin_bridges':
							echo " $pillSeparator Bridges";
							break;
					case 'admin_trait_sets':
						echo " $pillSeparator Asignar rasgos por sistema";
						break;
					case 'admin_traits':
						echo " $pillSeparator Gestionar rasgos";
						break;
					case 'admin_systems_resources':
						echo " $pillSeparator Asignar recursos por sistema";
						break;
					case 'admin_resources':
						echo " $pillSeparator Gestionar recursos (catálogo)";
						break;
					case 'admin_resources_migration':
						echo " $pillSeparator Migracion de recursos";
						break;
					case 'admin_inspect_db':
						echo " $pillSeparator Inspeccionar BDD";
						break;
					case 'admin_avatar_mass':
						echo " $pillSeparator Avatares masivos";
						break;
					case 'admin_characters_worlds':
						echo " $pillSeparator Asignación crónicas y realidades";
						break;
					case 'admin_character_deaths':
						echo " $pillSeparator Muertes de personajes";
						break;
					case 'admin_characters_clone':
						echo " $pillSeparator Copiar personajes";
						break;
					case 'admin_external_links':
						echo " $pillSeparator Enlaces externos";
						break;
					case 'admin_character_links':
						echo " $pillSeparator Enlaces y documentos a personajes";
						break;
					case 'admin_doc_links':
						echo " $pillSeparator Documentos vinculados a personajes";
						break;
					case 'admin_sim_browser':
						echo " $pillSeparator Temporadas simulador";
						break;
					case 'admin_sim_character_talk':
						echo " $pillSeparator Frases de simulador";
						break;
					case 'admin_schema_initializer':
						echo " $pillSeparator Inicializador de esquema";
						break;
					case 'admin_mentions_help':
						echo " $pillSeparator Ayuda mentions";
						break;
					case 'logout':
						echo " $pillSeparator Cerrar sesión";
						break;
					}
					echo "<br />";
				}
				break;
			// ========================================== //
			// Jugadores
			// ========================================== //
			case "seeplayer":	// Ver Jugador
				echo "<a href='/players' title='Lista de Jugadores'>Jugadores</a> $pillSeparator $namePJ $surnamePJ";
				break;
			// ========================================== //
			// Biografías
			// ========================================== //
			case "biogroup":    // Lista de Personajes
				// Antes dependia de afiliacion, ahora solo mostramos clan
				$typeHref = pretty_url($link, 'dim_character_types', '/characters/type', (int)$idTipo);
				echo "<a href='/characters/types' title='Biografías'>Biografías</a> $pillSeparator $nombreTipo";
				break;
			case "chronicles":
			case "bio_chronicles":
				if (isset($_GET['t']) && (int)$_GET['t'] > 0) {
					$chronNavId = (int)$_GET['t'];
					$chronNavName = '';
					if ($stChronNav = $link->prepare("SELECT name FROM dim_chronicles WHERE id = ? LIMIT 1")) {
						$stChronNav->bind_param('i', $chronNavId);
						$stChronNav->execute();
						$rsChronNav = $stChronNav->get_result();
						if ($rowChronNav = $rsChronNav->fetch_assoc()) {
							$chronNavName = (string)($rowChronNav['name'] ?? '');
						}
						$stChronNav->close();
					}
					echo "<a href='/chronicles' title='Crónicas'>Crónicas</a>";
					if ($chronNavName !== '') echo " $pillSeparator " . htmlspecialchars($chronNavName, ENT_QUOTES, 'UTF-8');
				}
				break;
			case "bio_worlds":
				echo "<a href='/characters/types' title='Biografías'>Biografías</a> $pillSeparator Realidades";
				break;
			case "muestrabio":  // Ver Personaje
				// Igual: quitamos afiliacion, mostramos Clan $pillSeparator Nombre del PJ
				$typeHref = pretty_url($link, 'dim_character_types', '/characters/type', (int)$bioType);
				echo "<a href='/characters/types' title='Biografías'>Biografías</a> $pillSeparator 
					<a href='" . htmlspecialchars($typeHref) . "'>$nameTipo</a> $pillSeparator $bioName";
				break;
			case "seegroup":	// Ver Organización
				echo "<a href='/organizations' title='Grupos y Sociedades'>Grupos y Sociedades</a> $pillSeparator $packNavLinks";
				break;
			// ========================================== //
			// Archivo
			// ========================================== //
			//case "seasons_home":
				//echo "<a href='/seasons' title='Temporadas'>Temporadas</a>";
				//break;
			case "seasons_complete":
				echo "<a href='/seasons' title='Temporadas'>Temporadas</a> $pillSeparator Temporadas completas";
				break;
			case "seasons_interludes":
				echo "<a href='/seasons' title='Temporadas'>Temporadas</a> $pillSeparator Incisos";
				break;
			case "seasons_personal":
				echo "<a href='/seasons' title='Temporadas'>Temporadas</a> $pillSeparator Historias personales";
				break;
			case "seasons_specials":
				echo "<a href='/seasons' title='Temporadas'>Temporadas</a> $pillSeparator Especiales";
				break;
			case "chapters_table":
				echo "<a href='/seasons' title='Temporadas'>Temporadas</a> $pillSeparator Capítulos";
				break;
			case "seechapter":	// Ver Capítulo
				$tempHref = pretty_url($link, 'dim_seasons', '/seasons', (int)$idTemporada);
				$chapterBreadcrumbTitle = isset($pageTitle2) ? (string)$pageTitle2 : ('Capítulo ' . $numeracionOK);
				echo "<a href='/seasons' title='Temporadas'>Temporadas</a> $pillSeparator <a href='" . htmlspecialchars($tempHref) . "' title='$nameTemporada'>$nameTemporada</a> $pillSeparator " . htmlspecialchars($chapterBreadcrumbTitle, ENT_QUOTES, 'UTF-8');
				break;
			// ========================================== //
			// Linea temporal
			// ========================================== //
			case "timeline":
				// En la portada de timeline no mostramos breadcrumb.
				break;
			case "timeline_event":
				$eventNavTitle = isset($title) ? (string)$title : (isset($pageTitle2) ? (string)$pageTitle2 : '');
				echo "<a href='/timeline' title='Linea temporal'>L&iacute;nea temporal</a>";
				if (trim($eventNavTitle) !== '') {
					echo " $pillSeparator " . htmlspecialchars($eventNavTitle, ENT_QUOTES, 'UTF-8');
				}
				break;
			// ========================================== //
			// Documentos
			// ========================================== //
			case "verdoc":	// Ver Documento
				echo "<a href='/documents' title='Documentos'>Documentos</a> $pillSeparator $titleDoc";
				break;
			// ========================================== //
			// Inventario
			// ========================================== //
			case "verobj":
				$typeHref = pretty_url($link, 'dim_item_types', '/inventory', (int)$itemType);
				echo "<a href='/inventory' title='Inventario'>Inventario</a> $pillSeparator <a href='" . htmlspecialchars($typeHref) . "' title='Inventario ($nameTypeBack)'>$nameTypeBack</a> $pillSeparator $itemName";
				break;
			case "seeitem":	// Ver Objeto
							$typeHref = pretty_url($link, 'dim_item_types', '/inventory', (int)$itemType);
			echo "<a href='" . htmlspecialchars($typeHref) . "' title='Inventario ($nameTypeBack)'>$nameTypeBack</a> $pillSeparator $itemName";
				break;
			case "inv_type":
				echo "<a href='/inventory' title='Inventario'>Inventario</a> $pillSeparator $nameTypeBack";
				break;
						// ========================================== //
			// Sistemas
			// ========================================== //
			case "sistemas":
				$systemIdNav = isset($systemId) ? (int)$systemId : (isset($returnTypeId) ? (int)$returnTypeId : 0);
				$sysHref = pretty_url($link, 'dim_systems', '/systems', $systemIdNav);
				echo "{$systemSeresSobrenaturales}$systemName";
				break;
			case "verforma":	// Ver Forma Cambiante
				$sysHref = pretty_url($link, 'dim_systems', '/systems', (int)$returnTypeId);
				$formHref = pretty_url($link, 'dim_forms', '/systems/form', $formIdNav);
				echo "{$systemSeresSobrenaturales}<a href='" . htmlspecialchars($sysHref) . "'> $returnType</a> $pillSeparator Forma $nameWereForm";
				break;
			case "versistdetalle":
				$sysHref = pretty_url($link, 'dim_systems', '/systems', (int)$returnTypeId);
				echo "{$systemSeresSobrenaturales}<a href='" . htmlspecialchars($sysHref) . "'> $returnType</a> $pillSeparator $nameSyst";
				break;
			/*
			case "verforma":	// Ver Forma Cambiante
				echo "<a href='/systems/$returnTypeId'> $returnType</a> > Forma $nameWereForm";
				break;
			case "versist":		// Ver Sistema
				echo "<a href='/systems/$returnTypeId'> $returnType</a> > $nameSyst";
				break;
			*/
			// ========================================== //
			// Habilidades
			// ========================================== //
			case "verrasgo":
				$traitNavName = isset($nameSkill) ? $nameSkill : (isset($pageTitle2) ? $pageTitle2 : '');
				echo "<a href='/rules/traits' title='Rasgos'>Rasgos</a> $pillSeparator $traitNavName";
				break;
			// ========================================== //
			// Meritos y Defectos
			// ========================================== //
			case "vermyd":
				echo "<a href='/rules/merits-flaws' title='Meritos y Defectos'>Meritos y Defectos</a> $pillSeparator $mafName";
				break;
			// ========================================== //
			// Maniobras de Combate
			// ========================================== //
			case "vermaneu":	// Ver Maniobra
				echo "<a href='/rules/maneuvers' title='Maniobras'>Maniobras</a> $pillSeparator $maneName";
				break;
			// ========================================== //
			// Arquetipos de Personalidad
			// ========================================== //
			case "verarch":		// Ver Arquetipo
				echo "<a href='/rules/archetypes' title='Arquetipos de personalidad'>Arquetipos de personalidad</a> $pillSeparator $archeName";
				break;
			// ========================================== //
			// Dones
			// ========================================== //
			case "powers":
				echo "<a href='/powers' title='Poderes'>Poderes</a>";
				break;
			case "tipodon":		// Lista de Dones
				echo "<a href='/powers/gifts' title='Dones'>Dones</a> $pillSeparator $routeLabel";
				break;
			case "muestradon":	// Ver Don
				$typeHref = pretty_url($link, 'dim_gift_types', '/powers/gift/type', $donTypeNav);
				echo "<a href='/powers/gifts' title='Dones'>Dones</a> $pillSeparator <a href='" . htmlspecialchars($typeHref) . "' title='$nombreTipo'>$nombreTipo</a> $pillSeparator $donName";
				break;
			// ========================================== //
			// Rituales
			// ========================================== //
			case "tiporite":	// Lista de Rituales
				echo "<a href='/powers/rites' title='Rituales'>Rituales</a> $pillSeparator $routeLabel";
				break;
			case "seerite":		// Ver Ritual
				$typeHref = pretty_url($link, 'dim_rite_types', '/powers/rite/type', $riteTypeNav);
				echo "<a href='/powers/rites' title='Rituales'>Rituales</a> $pillSeparator <a href='" . htmlspecialchars($typeHref) . "' title='$nombreTipo'>$nombreTipo</a> $pillSeparator $riteName";
				break;
			// ========================================== //
			// Tótems
			// ========================================== //
			case "tipototm":	// Lista de Tótems
				echo "<a href='/powers/totems' title='Tótems'>Tótems</a> $pillSeparator $totemName";
				break;
			case "muestratotem":// Ver Tótem
				$typeHref = pretty_url($link, 'dim_totem_types', '/powers/totem/type', $totemTypeNav);
				echo "<a href='/powers/totems' title='Tótems'>Tótems</a> $pillSeparator <a href='" . htmlspecialchars($typeHref) . "' title='$nombreTipo'>$nombreTipo</a> $pillSeparator $totemName";
				break;
			// ========================================== //
			// Disciplinas
			// ========================================== //
			case "tipodisc":	// Lista de Disciplinas
				echo "<a href='/powers/disciplines' title='Disciplinas'>Disciplinas</a> $pillSeparator $routeLabel";
				break;
			case "muestradisc":	// Ver Disciplina
				$typeHref = pretty_url($link, 'dim_discipline_types', '/powers/discipline/type', $donTypeNav);
				echo "<a href='/powers/disciplines' title='Disciplinas'>Disciplinas</a> $pillSeparator <a href='" . htmlspecialchars($typeHref) . "' title='$nombreTipo'>$nombreTipo</a> $pillSeparator $donName";
				break;
			case "dados":		// Tiradados
				if (isset($_GET['see']) && (int)$_GET['see'] > 0) {
					$rollIdNav = (int)$_GET['see'];
					$stmt = mysqli_prepare($link, "SELECT roll_name FROM fact_dice_rolls WHERE id = ? LIMIT 1");
					if ($stmt) {
						mysqli_stmt_bind_param($stmt, "i", $rollIdNav);
						mysqli_stmt_execute($stmt);
						$res = mysqli_stmt_get_result($stmt);
						if ($rowRoll = mysqli_fetch_assoc($res)) {
							$rollTitleNav = htmlspecialchars((string)$rowRoll['roll_name'], ENT_QUOTES, 'UTF-8');
							echo "<a href='/tools/dice' title='Tiradados'>Tiradados</a> $pillSeparator {$rollTitleNav}";
						}
						mysqli_stmt_close($stmt);
					}
				}
				break;
			case "forum_topic_viewer":
				echo "<a href='/tools/forum-topic-viewer' title='Visor de temas foro'>Visor de temas foro</a>";
				break;
			// ========================================== //
			default:
				//echo "&nbsp;";
				break;
		}
		//</p>
		// Mandamos un <br/> al final para que no se solape con titulos muy largos ... //
	?>
<?php
$nav = trim(ob_get_clean());
if ($nav !== '') {
    echo '<div class="nav-breadcrumb">';
    echo $nav;
    echo '</div><br/>';
}
?>
