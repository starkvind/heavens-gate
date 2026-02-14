<?php ob_start(); ?>

	<?php
		// <p class="navegacion_secciones">
		// include("app/partials/main_nav_bar.php");	// Barra Navegación
		$pillSeparator = "»";
		$systemSeresSobrenaturales = "<a href='/systems'> Seres sobrenaturales</a> $pillSeparator ";
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
			// Administración
			// ========================================== //
			case "talim": // 
				if (isset($_GET['s'])) {
					$seccion = htmlspecialchars($_GET['s']); // Sanear entrada
					echo "<a href='/talim' title='Administración'>Administración</a>";
					switch ($seccion) {
						case 'admin_pjs':
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
							echo " $pillSeparator Temporadas";
							break;
						case 'admin_epis':
							echo " $pillSeparator Capítulos";
							break;
						case 'admin_pois':
							echo " $pillSeparator Mapas";
							break;
						case 'admin_timelines':
							echo " $pillSeparator Línea temporal";
							break;
						case 'admin_bso':
						case 'admin_bso_link':
							echo " $pillSeparator Banda sonora";
							break;
						case 'admin_gallery':
							echo " $pillSeparator Galería";
							break;
						case 'admin_plots':
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
							echo " $pillSeparator Documentación";
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
					case 'admin_bridges':
							echo " $pillSeparator Bridges";
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
				// Antes dependía de afiliación, ahora solo mostramos clan
				$typeHref = pretty_url($link, 'dim_character_types', '/characters/type', (int)$idTipo);
				echo "<a href='/characters/types' title='Biografías'>Biografías</a> $pillSeparator $nombreTipo";
				break;
			case "muestrabio":  // Ver Personaje
				// Igual: quitamos afiliación, mostramos Clan $pillSeparator Nombre del PJ
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
			case "seechapter":	// Ver Capitulo
				$tempHref = pretty_url($link, 'dim_seasons', '/seasons', (int)$idTemporada);
				echo "<a href='" . htmlspecialchars($tempHref) . "' title='$nameTemporada'>$nameTemporada</a> $pillSeparator Capítulo $numeracionOK";
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
				echo "<a href='/rules/traits' title='Rasgos'>Rasgos</a> $pillSeparator $nameSkill";
				break;
			// ========================================== //
			// Méritos y Defectos
			// ========================================== //
			case "vermyd":
				echo "<a href='/rules/merits-flaws' title='Méritos y Defectos'>Méritos y Defectos</a> $pillSeparator $mafName";
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
			// ========================================== //
			// Simulador de Combate
			// ========================================== //
			case "simulador2":	// Resultado del Combate
				echo "<a href='?p=simulador' title='Simulador de Combate'>Simulador</a> $pillSeparator $nombreCom1 VS $nombreCom2";
				break;
			case "combtodo":	// Lista de Combates
				echo "<a href='?p=simulador' title='Simulador de Combate'>Simulador</a> $pillSeparator Registro de Combates";
				break;
			case "vercombat":	// Ver Combate
				echo "<a href='?p=simulador' title='Simulador de Combate'>Simulador</a> $pillSeparator <a href='?p=combtodo' title='Registro de Combates'>Registro de Combates</a> $pillSeparator Combate #$idDelCombate";
				break;
			case "punts":		// Tabla de Puntuaciones
				echo "<a href='?p=simulador' title='Simulador de Combate'>Simulador</a> $pillSeparator Puntuaciones";
				break;
			case "arms":		// Tabla de Armas
				echo "<a href='?p=simulador' title='Simulador de Combate'>Simulador</a> $pillSeparator Listado de Armas";
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

