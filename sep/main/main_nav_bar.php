<div style="position:absolute;font-size:9px;">
<?php
	// <p class="navegacion_secciones">
	// include("sep/main/main_nav_bar.php");	// Barra Navegación
	switch ($daft) {
		// ========================================== //
		// Jugadores
		// ========================================== //
		case "seeplayer":	// Ver Jugador
			echo "<a href='index.php?p=players' title='Lista de Jugadores'>Jugadores</a> > $namePJ $surnamePJ";
			break;
		// ========================================== //
		// Biografías
		// ========================================== //
		case "biogroup":	// Lista de Personajes
			echo "<a href='index.php?p=bios&amp;t=$idType' title='Biografías ($nombreType)'>$nombreType</a> > $nombreClan";
			break;
		case "muestrabio":	// Ver Personaje
			echo "<a href='index.php?p=bios&amp;t=$bioType' title='Biografías ($nameTipo)'>$nameTipo</a> > <a href='index.php?p=biogroup&amp;b=$bioType&amp;t=$bioClan'>$nameClanFinal</a> > $bioName";
			break;
		case "seegroup":	// Ver Organización
			echo "<a href='index.php?p=listgroups' title='Grupos y Sociedades'>Grupos y Sociedades</a> > $packNavLinks";
			break;
		// ========================================== //
		// Archivo
		// ========================================== //
		case "seechapter":	// Ver Capitulo
			echo "<a href='index.php?p=temp&amp;t=$idTemporada' title='$nameTemporada'>$nameTemporada</a> > Capítulo $numeracionOK";
			//$nameCapi ($numeracionOK)
			break;
		// ========================================== //
		// Documentos
		// ========================================== //
		case "docx":	// Ver Documento
			echo "<a href='index.php?p=doc&amp;t=$borrasca' title='Documentos'>Documentos</a> > $titleDoc";
			break;
		// ========================================== //
		// Inventario
		// ========================================== //
		case "seeitem":	// Ver Objeto
			echo "<a href='index.php?p=inv&amp;t=$itemType' title='Inventario ($nameTypeBack)'>$nameTypeBack</a> > $itemName";
			break;
		// ========================================== //
		// Sistemas
		// ========================================== //
		case "verforma":	// Ver Forma Cambiante
			echo "<a href='index.php?p=sistemas&b=$returnTypeId'> $returnType</a> > Forma $nameWereForm";
			break;
		case "versist":		// Ver Sistema
			echo "<a href='index.php?p=sistemas&b=$returnTypeId'> $returnType</a> > $nameSyst";
			break;
		// ========================================== //
		// Habilidades
		// ========================================== //
		case "skill":		// Ver Habilidad
			echo "<a href='index.php?p=listsk&b=$returnType' title='$typeSkill'>$typeSkill</a> > $nameSkill";
			break;
		// ========================================== //
		// Méritos y Defectos
		// ========================================== //
		case "mfgroup":		// Lista de Méritos y Defectos
			echo "<a href='index.php?p=mflist&amp;b=$mafCategory' title='$mafCategoryName'>$mafCategoryName</a> > $mafTypeName";
			break;
		case "merfla":		// Ver Mérito o Defecto
			echo "<a href='index.php?p=mflist&amp;b=$returnType' title='$'>$mafType</a> > <a href='index.php?p=mfgroup&amp;t=$returnType&amp;b=$typeReturnId' title='$mafAfil'>$mafAfil</a> > $mafName";
			break;
		// ========================================== //
		// Maniobras de Combate
		// ========================================== //
		case "vermaneu":	// Ver Maniobra
			echo "<a href='index.php?p=maneuver' title='Maniobras'>Maniobras</a> > $maneName";
			break;
		// ========================================== //
		// Arquetipos de Personalidad
		// ========================================== //
		case "verarch":		// Ver Arquetipo
			echo "<a href='index.php?p=arquetip' title='Arquetipos de personalidad'>Arquetipos de personalidad</a> > $archeName";
			break;
		// ========================================== //
		// Dones
		// ========================================== //
		case "tipodon":		// Lista de Dones
			echo "<a href='index.php?p=dones' title='Dones'>Dones</a> > $punk2";
			break;
		case "muestradon":	// Ver Don
			echo "<a href='index.php?p=dones' title='Dones'>Dones</a> > <a href='index.php?p=tipodon&amp;b=$donType' title='$nombreTipo'>$nombreTipo</a> > $donName";
			break;
		// ========================================== //
		// Rituales
		// ========================================== //
		case "tiporite":	// Lista de Rituales
			echo "<a href='index.php?p=rites' title='Rituales'>Rituales</a> > $punk2";
			break;
		case "seerite":		// Ver Ritual
			echo "<a href='index.php?p=rites' title='Rituales'>Rituales</a> > <a href='index.php?p=tiporite&amp;b=$riteType' title='$nombreTipo'>$nombreTipo</a> > $riteName";
			break;
		// ========================================== //
		// Tótems
		// ========================================== //
		case "tipototm":	// Lista de Tótems
			echo "<a href='index.php?p=totems' title='Tótems'>Tótems</a> > $totemName";
			break;
		case "muestratotem":// Ver Tótem
			echo "<a href='index.php?p=totems' title='Tótems'>Tótems</a> > <a href='index.php?p=tipototm&amp;b=$totemType' title='$nombreTipo'>$nombreTipo</a> > $totemName";
			break;
		// ========================================== //
		// Disciplinas
		// ========================================== //
		case "tipodisc":	// Lista de Disciplinas
			echo "<a href='index.php?p=disciplinas' title='Disciplinas'>Disciplinas</a> > $punk2";
			break;
		case "muestradisc":	// Ver Disciplina
			echo "<a href='index.php?p=disciplinas' title='Disciplinas'>Disciplinas</a> > <a href='index.php?p=tipodisc&amp;b=$donType' title='$nombreTipo'>$nombreTipo</a> > $donName";
			break;
		// ========================================== //
		// Simulador de Combate
		// ========================================== //
		case "simulador2":	// Resultado del Combate
			echo "<a href='index.php?p=simulador' title='Simulador de Combate'>Simulador</a> > $nombreCom1 VS $nombreCom2";
			break;
		case "combtodo":	// Lista de Combates
			echo "<a href='index.php?p=simulador' title='Simulador de Combate'>Simulador</a> > Registro de Combates";
			break;
		case "vercombat":	// Ver Combate
			echo "<a href='index.php?p=simulador' title='Simulador de Combate'>Simulador</a> > <a href='index.php?p=combtodo' title='Registro de Combates'>Registro de Combates</a> > Combate #$idDelCombate";
			break;
		case "punts":		// Tabla de Puntuaciones
			echo "<a href='index.php?p=simulador' title='Simulador de Combate'>Simulador</a> > Puntuaciones";
			break;
		case "arms":		// Tabla de Armas
			echo "<a href='index.php?p=simulador' title='Simulador de Combate'>Simulador</a> > Listado de Armas";
			break;
		// ========================================== //
		default:
			//echo "&nbsp;";
			break;
	}
	//</p>
	// Mandamos un <br/> al final para que no se solape con titulos muy largos ... //
?>
</div>
<br/>