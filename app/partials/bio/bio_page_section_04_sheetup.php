<?php
	if ($bioPlayer != 0) {	// Jugador del Personaje
		echo"<div class='bioSheetSectionLeft'>Jugador:</div>";
		echo"<div class='bioSheetSectionRight'>$namePlayerOfChara</div>"; // Variable obtenida de #bio_page_section_01_data
	}
	if ($bioChronic != 0) { // Crónica del Personaje
		echo"<div class='bioSheetSectionLeft'>Crónica:</div>";
		echo"<div class='bioSheetSectionRight'>$nameCronicaFinal</div>"; // Variable obtenida de #bio_page_section_01_data
	}
	if ($bioPack != 0) { // Manada del Personaje
		echo"<div class='bioSheetSectionLeft'>$titlePack:</div>";
		echo"<div class='bioSheetSectionRight'>$packLink</div>"; // Variable obtenida de #bio_page_section_01_data
	}
	if ($bioClan != 0) { // Clan del Personaje
		echo"<div class='bioSheetSectionLeft'>$titleClan:</div>";
		echo"<div class='bioSheetSectionRight'>$clanLink</div>"; // Variable obtenida de #bio_page_section_01_data
	}		
	if ($bioRace != 0) { // Raza del Personaje
		echo"<div class='bioSheetSectionLeft'>Raza:</div>";
		echo"<div class='bioSheetSectionRight'>$raceLink</div>"; // Variable obtenida de #bio_page_section_01_data
	}
	if ($bioAuspice != 0) { // Auspicio del Personaje
		echo"<div class='bioSheetSectionLeft'>$titleAuspice:</div>";
		echo"<div class='bioSheetSectionRight'>$auspiceLink</div>"; // Variable obtenida de #bio_page_section_01_data
	}
	if ($bioTribe != 0) { // Tribu del Personaje
		echo"<div class='bioSheetSectionLeft'>$titleTribe:</div>";
		echo"<div class='bioSheetSectionRight'>$tribeLink</div>"; // Variable obtenida de #bio_page_section_01_data
	}
	if ($bioTotem != "") { // Tótem del Personaje
		echo"<div class='bioSheetSectionLeft'>Tótem:</div>";
		echo"<div class='bioSheetSectionRight'>$bioTotem</div>";				
	}
	if ($bioNature != 0) {		// Naturaleza del Personaje
		echo"<div class='bioSheetSectionLeft'>Naturaleza:</div>";
		echo"<div class='bioSheetSectionRight'>$natureLink</div>"; // Variable obtenida de #bio_page_section_01_data
	}
	if ($bioBehavior != 0) {	// Conducta del Personaje
		echo"<div class='bioSheetSectionLeft'>Conducta:</div>";
		echo"<div class='bioSheetSectionRight'>$demeanorLink</div>"; // Variable obtenida de #bio_page_section_01_data
	}	
?>