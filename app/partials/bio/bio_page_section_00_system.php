<?php
	/* =============================================================================================== //
	|	Aplicamos etiquetas de detalle definidas en bridge_systems_detail_labels para el sistema del PJ.|
	|	Si alguna etiqueta no existe, se mantienen los títulos por defecto definidos en bio_page2.php. |
	*/ // ============================================================================================ //
	$systemDetailLabels = (isset($systemDetailLabels) && is_array($systemDetailLabels)) ? $systemDetailLabels : [];

	if (!empty($systemDetailLabels['label_breed'])) {
		$titleBreed = $systemDetailLabels['label_breed'];
	}
	if (!empty($systemDetailLabels['label_auspice'])) {
		$titleAuspice = $systemDetailLabels['label_auspice'];
	}
	if (!empty($systemDetailLabels['label_pack'])) {
		$titlePack = $systemDetailLabels['label_pack'];
	}
	if (!empty($systemDetailLabels['label_tribe'])) {
		$titleTribe = $systemDetailLabels['label_tribe'];
	}
	if (!empty($systemDetailLabels['label_clan'])) {
		$titleClan = $systemDetailLabels['label_clan'];
	}
	if (!empty($systemDetailLabels['label_pk_name'])) {
		$titlePkName = $systemDetailLabels['label_pk_name'];
	}
	if (!empty($systemDetailLabels['label_social'])) {
		$titleSocial = "&nbsp;" . $systemDetailLabels['label_social'] . "&nbsp;";
	}
?>
