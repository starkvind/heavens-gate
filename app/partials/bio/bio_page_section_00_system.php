<?php
	/* =============================================================================================== //
	|	Tomamos el dato de la variable $bioSystem para saber qué sistema utiliza el PJ seleccionado		|		
	|	y cambiamos los títulos de las diferentes secciones para acomodarlas a la Raza Cambiante,		|
	|	Sistema u otro del personaje.																	|
	*/ // ============================================================================================ //
	switch ($bioSystem) {
		// ------ > Bastet
		case "Bastet":
			// SECCION SOCIAL
			$titleGlory = "Ferocidad";
			$titleWisdo = "Sagacidad";
			break;
		// ------ > Ananasi
		case "Ananasi":
			// SECCION SOCIAL
			$titleGlory = "Obediencia";
			$titleHonor = "Astucia";
			$titleWisdo = "Sabiduría";
			break;
		// ------ > Gurahl
		case "Gurahl":
			// SECCION SOCIAL
			$titleGlory = "Socorro";
			break;
		// ------ > Ratkin
		case "Ratkin":
			// SECCION SOCIAL
			$titleGlory = "Infamia";
			$titleHonor = "Obligación";
			$titleWisdo = "Astucia";
		// ------ > Nuwisha
		case "Nuwisha":
			// SECCION SOCIAL
			$titleGlory = "Ferocidad";
			$titleHonor = "Humor";
			$titleWisdo = "Astucia";
			break;
		// ------ > Hidrianos
		case "Hidrianos":
			// SECCION SOCIAL
			$titleTribe = "Comunidad";
			break;
		// ------ > Changeling
		case "Changeling":
			// SECCION SOCIAL
			$titlePkName = "Identidad humana";
			break;
		// ------ > Vampiro
		case "Vampiro":
			// TITULOS
			//$titlePowers = "&nbsp;Disciplinas&nbsp;";	// Titulo de la seccion "Poderes"
			// IDENTIFICACION
			$titlePack = "Cuadrilla";
			$titleTribe = "Clan";
			$titleClan = "Organización";
			// SECCION SOCIAL
			$titleSocial = "&nbsp;Virtudes&nbsp;"; // Titulo de la seccion "Social"
			$titleGlory = "Conciencia";
			$titleHonor = "Autocontrol";
			$titleWisdo = "Coraje";
			// SECCION VENTAJAS
			$titleRage = "Sangre";
			$titleGnosis = "Humanidad / Senda";
			break;
		// ------ > Ícaros
		case "Ícaros":
			// TITULOS
			//$titlePowers = "&nbsp;Disciplinas&nbsp;";	// Titulo de la seccion "Poderes"
			$titlePkName = "Otra identidad";
			// IDENTIFICACION
			$titleAuspice = "Aspecto";
			$titlePack = "Grupo";
			$titleTribe = "Especie";	
			// SECCION VENTAJAS
			$titleRage = "Sangre";
			$titleGnosis = "Humanidad";
			break;
	}
?>