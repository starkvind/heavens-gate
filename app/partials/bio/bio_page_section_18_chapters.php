<?php
/*
	foreach ($participacion as $rel) {
		$capituloTitle = "Capítulo " . htmlspecialchars($rel["capitulo"]) ." de " . htmlspecialchars($rel["temporada_name"]) . " (Temporada " . htmlspecialchars($rel["numero"]) . ")'";
		echo "
			<a href='/chapters/" . $rel["id"] . "' title='" . $capituloTitle . "' target='_blank'>
				<div class='bioSheetPower'>
					" . htmlspecialchars($rel["name"]) . "
					<div style='float:right;font-size:8px;padding-top:2px;'>" . htmlspecialchars($rel["fecha"]) . "</div>
				</div>
			</a>
		";
	}
*/

?>

<style>
	.listaParticipacion {
		display: flex;
		flex-direction: column;
		width: 100%;
		gap: 1em;
	}

	.capitulosTemporada {
		display: grid;
		grid-template-columns: repeat(2, 1fr);
		gap: 6px 12px;
		width: 100%;
	}

	.capitulosTemporada a {
		text-decoration: none;
		display: block;
	}

	.capitulosTemporada .bioSheetPower {
		min-height: 22px; /* Fuerza altura coherente */
		display: flex;
		justify-content: space-between;
		align-items: center;
		padding: 6px 10px;
		box-sizing: border-box;
		overflow: hidden;
	}
	
	.bioSheetPower {
		white-space: nowrap;
		text-overflow: ellipsis;
		overflow: hidden;
	}
</style>

<div class="listaParticipacion">
<?php
$ultimaTemporada = null;

foreach ($participacion as $rel) {
	$tempNombre = htmlspecialchars($rel["temporada_name"]);
	$tempNumero = htmlspecialchars($rel["numero"]);
	$capituloTitle = "Capítulo " . htmlspecialchars($rel["capitulo"]) ."";

	if ($ultimaTemporada !== $tempNumero) {
		if ($ultimaTemporada !== null) {
			echo "</div></fieldset>";
		}
		if ($tempNumero < 50) {
			$temporadaTitulo = $tempNombre . " (Temporada {$tempNumero})";
		} else {
			$temporadaTitulo = $tempNombre;
		}
		echo "<fieldset class='grupoBioClan' style='margin-left:-3em;'>";
		echo "<legend class='bioPowerTitle' style='border:0;margin:0.5em 0;'>{$temporadaTitulo}</legend>";
		echo "<div class='capitulosTemporada'>";
		$ultimaTemporada = $tempNumero;
	}

    $chapHref = pretty_url($link, 'dim_chapters', '/chapters', (int)$rel["id"]);
	echo "
		<a href='" . htmlspecialchars($chapHref) . "' title='" . $capituloTitle . "' target='_blank'>
			<div class='bioSheetPower' style='width:100%;'>
				" . htmlspecialchars($rel["name"]) . "
				<div style='float:right;font-size:8px;padding-top:2px;'>" . htmlspecialchars($rel["fecha"]) . "</div>
			</div>
		</a>
	";
}

if ($ultimaTemporada !== null) {
	echo "</div></fieldset>";
}
?>
</div>
