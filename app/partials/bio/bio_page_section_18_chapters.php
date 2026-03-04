<div class="listaParticipacion">
<?php
if (empty($participacion) || !is_array($participacion)) {
    echo "</div>";
    return;
}

$ultimaTemporada = null;

foreach ($participacion as $rel) {
	$tempNombre = htmlspecialchars($rel["temporada_name"]);
	$tempNumero = htmlspecialchars($rel["season_number"]);
	$tempKind = trim((string)($rel["season_kind"] ?? 'temporada'));

	if ($ultimaTemporada !== $tempNumero) {
		if ($ultimaTemporada !== null) {
			echo "</div></fieldset>";
		}
		if ($tempKind === 'temporada') {
			$temporadaTitulo = $tempNombre . " (Temporada {$tempNumero})";
		} elseif ($tempKind === 'inciso') {
			$incisoNum = (int)$tempNumero;
			if ($incisoNum >= 100 && $incisoNum < 200) $incisoNum -= 100;
			$temporadaTitulo = "Inciso {$incisoNum} - {$tempNombre}";
		} elseif ($tempKind === 'historia_personal') {
			$temporadaTitulo = $tempNombre . " (Historia personal)";
		} else {
			$temporadaTitulo = "Especial - {$tempNombre}";
		}
		echo "<fieldset class='grupoBioClan bioChaptersSeasonFieldset'>";
		echo "<legend class='bioPowerTitle bioChaptersSeasonLegend'>&nbsp;{$temporadaTitulo}&nbsp;</legend>";
		echo "<div class='capitulosTemporada'>";
		$ultimaTemporada = $tempNumero;
	}

    $chapHref = pretty_url($link, 'dim_chapters', '/chapters', (int)$rel["id"]);
    $playedDateRaw = trim((string)($rel["played_date"] ?? ''));
    $playedDateFmt = $playedDateRaw;
    if ($playedDateRaw !== '' && $playedDateRaw !== '0000-00-00') {
        $tsPlayed = strtotime($playedDateRaw);
        if ($tsPlayed !== false) {
            $playedDateFmt = date('d-m-Y', $tsPlayed);
        }
    }
	echo "
		<a class='bioChapterLink hg-tooltip' href='" . htmlspecialchars($chapHref) . "' target='_blank' data-tip='chapter' data-id='" . (int)$rel["id"] . "'>
			<div class='bioSheetPower bioChapterEntry'>
				" . htmlspecialchars($rel["name"]) . "
				<div class='bioChapterDate'>" . htmlspecialchars($playedDateFmt) . "</div>
			</div>
		</a>
	";
}

if ($ultimaTemporada !== null) {
	echo "</div></fieldset>";
}
?>
</div>
