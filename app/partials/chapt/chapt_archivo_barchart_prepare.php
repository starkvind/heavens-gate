<?php
	// Obtener el ID de temporada desde la URL
	$id_temporada = $_GET['t'] ?? null;

	if (!$id_temporada || !is_numeric($id_temporada)) {
		echo "<p style='color:red;'>❌ Temporada no válida.</p>";
		return;
	}

	// Paso 1: obtener el número de temporada real (ej: 2, 3...) desde el ID
	$query_num_temporada = "
		SELECT season_number FROM dim_seasons WHERE id = ?
	";
	$stmt = $link->prepare($query_num_temporada);
	$stmt->bind_param("i", $id_temporada);
	$stmt->execute();
	$result = $stmt->get_result();
	$numero_temporada = $result->fetch_assoc()['season_number'] ?? null;

	if (!$numero_temporada) {
		echo "<p style='color:red;'>❌ No se encontró la temporada solicitada.</p>";
		return;
	}

/*
	if ($numero_temporada > 50) {
		return;
	}
*/

	// 1. Obtener total de capítulos en la temporada (con fecha válida)
	$query_total = "
		SELECT COUNT(*) AS total
		FROM dim_chapters
		WHERE season_number = ? AND played_date != '0000-00-00'
	";
	$stmt = $link->prepare($query_total);
	$stmt->bind_param("i", $numero_temporada);
	$stmt->execute();
	$result = $stmt->get_result();
	$total_capitulos = $result->fetch_assoc()['total'] ?? 0;

	// Si no hay capítulos, salir
	if ($total_capitulos == 0) {
		//echo "<p style='color:orange;'>⚠️ Esta temporada no tiene capítulos registrados.</p>";
		return;
	}

	// 2. Obtener participación por personaje
	$query_participacion = "
		SELECT fact_characters.name, fact_characters.id AS pj_id, COUNT(*) AS jugados
		FROM bridge_chapters_characters acp
		JOIN dim_chapters ac ON ac.id = acp.chapter_id
		JOIN fact_characters ON fact_characters.id = acp.character_id
		WHERE ac.season_number = ? AND ac.played_date != '0000-00-00' AND fact_characters.character_kind = 'pj'
		GROUP BY acp.character_id
		ORDER BY jugados DESC
	";
	$stmt = $link->prepare($query_participacion);
	$stmt->bind_param("i", $numero_temporada);
	$stmt->execute();
	$result = $stmt->get_result();

	/*
	$nombres = [];
	$jugados = [];
	$player_ids[] = [];
	$porcentajes = [];
	*/

	while ($row = $result->fetch_assoc()) {
		$nombres[] = $row['name'];
		$jugados[] = $row['jugados'];
		$player_ids[] = $row['pj_id'];
		$porcentajes[] = round(($row['jugados'] / $total_capitulos) * 100, 1);
	}

?>
