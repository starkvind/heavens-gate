<div class="listaParticipacion">
<?php
$ultimaTemporada = null;

foreach ($participacion as $rel) {
	$tempNombre = htmlspecialchars($rel["temporada_name"]);
	$tempNumero = htmlspecialchars($rel["season_number"]);
	$chapter_numberTitle = "Capítulo " . htmlspecialchars($rel["chapter_number"]);

	if ($ultimaTemporada !== $tempNumero) {
		if ($ultimaTemporada !== null) {
			echo "</div></fieldset>";
		}
		if ($tempNumero < 50) {
			$temporadaTitulo = $tempNombre . " (Temporada {$tempNumero})";
		} else {
			$temporadaTitulo = $tempNombre;
		}
		echo "<fieldset class='grupoBioClan bioChaptersSeasonFieldset'>";
		echo "<legend class='bioPowerTitle bioChaptersSeasonLegend'>&nbsp;{$temporadaTitulo}&nbsp;</legend>";
		echo "<div class='capitulosTemporada'>";
		$ultimaTemporada = $tempNumero;
	}

    $chapHref = pretty_url($link, 'dim_chapters', '/chapters', (int)$rel["id"]);
	echo "
		<a class='bioChapterLink' href='" . htmlspecialchars($chapHref) . "' title='" . $chapter_numberTitle . "' target='_blank'>
			<div class='bioSheetPower bioChapterEntry'>
				" . htmlspecialchars($rel["name"]) . "
				<div class='bioChapterDate'>" . htmlspecialchars($rel["played_date"]) . "</div>
			</div>
		</a>
	";
}

if ($ultimaTemporada !== null) {
	echo "</div></fieldset>";
}
?>
</div>
