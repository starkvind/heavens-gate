<?php
	if ($bioRace != 0) { // Raza del Personaje
		echo"<div class='bioSheetSectionLeft'>$titleBreed:</div>";
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
	if (!empty($bioMiscLinksByKind) && is_array($bioMiscLinksByKind)) {
		foreach ($bioMiscLinksByKind as $miscKind => $miscLinks) {
			$miscKindLabel = htmlspecialchars((string)$miscKind, ENT_QUOTES, 'UTF-8');
			$miscLinksHtml = implode(', ', array_values((array)$miscLinks));
			if ($miscLinksHtml === '') continue;
			echo"<div class='bioSheetSectionLeft'>{$miscKindLabel}:</div>";
			echo"<div class='bioSheetSectionRight'>{$miscLinksHtml}</div>";
		}
	}
	if (($bioTotemId ?? 0) > 0 || ($totemLink ?? '') !== '' || $bioTotem != "") { // T&oacute;tem del Personaje
		echo"<div class='bioSheetSectionLeft'>T&oacute;tem:</div>";
		echo"<div class='bioSheetSectionRight'>".($totemLink !== '' ? $totemLink : $bioTotem)."</div>";
	}
	if ((int)($bioNature ?? 0) > 0) {		// Naturaleza del Personaje
		echo"<div class='bioSheetSectionLeft'>Naturaleza:</div>";
		echo"<div class='bioSheetSectionRight'>$natureLink</div>"; // Variable obtenida de #bio_page_section_01_data
	}
	if ((int)($bioBehavior ?? 0) > 0) {	// Conducta del Personaje
		echo"<div class='bioSheetSectionLeft'>Conducta:</div>";
		echo"<div class='bioSheetSectionRight'>$demeanorLink</div>"; // Variable obtenida de #bio_page_section_01_data
	}
	if ($bioPack != 0) { // Manada del Personaje
		echo"<div class='bioSheetSectionLeft'>$titlePack:</div>";
		echo"<div class='bioSheetSectionRight'>$packLink</div>"; // Variable obtenida de #bio_page_section_01_data
	}
	if ($bioClan != 0) { // Clan del Personaje
		echo"<div class='bioSheetSectionLeft'>$titleClan:</div>";
		echo"<div class='bioSheetSectionRight'>$clanLink</div>"; // Variable obtenida de #bio_page_section_01_data
	}
	if ($bioPlayer != 0) {	// Jugador del Personaje
		$playerDisplay = (isset($playerLinkOfChara) && $playerLinkOfChara !== '') ? $playerLinkOfChara : $namePlayerOfChara;
		echo"<div class='bioSheetSectionLeft'>Jugador:</div>";
		echo"<div class='bioSheetSectionRight'>$playerDisplay</div>"; // Variable obtenida de #bio_page_section_01_data
	}
	if ($bioChronic != 0) { // Cr&oacute;nica del Personaje
		echo"<div class='bioSheetSectionLeft'>Cr&oacute;nica:</div>";
		echo"<div class='bioSheetSectionRight'>$nameCronicaFinal</div>"; // Variable obtenida de #bio_page_section_01_data
	}
?>
