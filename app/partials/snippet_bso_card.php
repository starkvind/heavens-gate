<?php
function mostrarTarjetaBSO($link, $tipo, $id) {
	if (!in_array($tipo, ['personaje', 'temporada', 'episodio'])) return;

	$queryBso = "
		SELECT bs.title_hg, bs.title AS titulo_real, bs.artist, bs.youtube AS enlace 
		FROM bridge_soundtrack_links br
		JOIN dim_soundtracks bs ON bs.id = br.soundtrack_id
		WHERE br.object_type = ? AND br.object_id = ?
		ORDER BY bs.added_at DESC
	";

	$stmt = $link->prepare($queryBso);
	
	if (!$stmt) {
		die("Error al preparar la consulta: " . $link->error);
	}
	
	$stmt->bind_param("si", $tipo, $id);
	$stmt->execute();
	$result = $stmt->get_result();

	if ($result->num_rows > 0) {
		while ($tema = $result->fetch_assoc()) {
			// Extraer ID del enlace YouTube
			$youtubeID = '';
			if (preg_match('%(?:youtu\.be/|youtube\.com/watch\?v=)([^&\n?#]+)%i', $tema['enlace'], $matches)) {
				$youtubeID = $matches[1];
			}

			if ($youtubeID) {
				echo "<div class='bioTextData'>"; 
					echo "<fieldset class='bso-card bioSeccion'>";
					echo "<legend>&nbsp;🎵 {$tema['title_hg']}&nbsp;</legend>";
					echo "<div class='video-wrapper'>";
					echo "<iframe width='550' height='315' style='clear:both;' src='https://www.youtube-nocookie.com/embed/{$youtubeID}' frameborder='0' allowfullscreen></iframe>";
					echo "</div>";
					echo "<p style='text-align:center;'><strong>{$tema['titulo_real']}</strong> — {$tema['artist']}</p>";
					echo "</fieldset>";
				echo "</div>";
			}
		}
	}
}
?>

