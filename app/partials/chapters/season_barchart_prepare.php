<?php
	if (!function_exists('hg_sb_col_exists')) {
		function hg_sb_col_exists(mysqli $link, string $table, string $column): bool
		{
			static $cache = [];
			$key = $table . ':' . $column;
			if (isset($cache[$key])) return $cache[$key];

			$ok = false;
			if ($st = $link->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?")) {
				$st->bind_param('ss', $table, $column);
				$st->execute();
				$st->bind_result($count);
				$st->fetch();
				$st->close();
				$ok = ((int)$count > 0);
			}

			$cache[$key] = $ok;
			return $ok;
		}
	}

	// Obtener ID de temporada ya resuelto por season_archive.php (preferido).
	$id_temporada = isset($temporadaId) ? (int)$temporadaId : 0;
	if ($id_temporada <= 0) {
		$temporadaRaw = (string)($_GET['t'] ?? '');
		if ($temporadaRaw !== '' && function_exists('resolve_pretty_id')) {
			$id_temporada = (int)(resolve_pretty_id($link, 'dim_seasons', $temporadaRaw) ?? 0);
		}
	}

	if ($id_temporada <= 0) {
		echo "<p class='chapt-error'>Temporada no valida.</p>";
		return;
	}

	// Paso 1: obtener el numero de temporada real (ej: 2, 3...) desde el ID
	$query_num_temporada = "
		SELECT season_number FROM dim_seasons WHERE id = ?
	";
	$stmt = $link->prepare($query_num_temporada);
	$stmt->bind_param("i", $id_temporada);
	$stmt->execute();
	$result = $stmt->get_result();
	$numero_temporada = $result->fetch_assoc()['season_number'] ?? null;

	if (!$numero_temporada) {
		echo "<p class='chapt-error'>No se encontro la temporada solicitada.</p>";
		return;
	}

/*
	if ($numero_temporada > 50) {
		return;
	}
*/

	// 1. Obtener total de capitulos en la temporada (con fecha valida)
	$query_total = "SELECT COUNT(*) AS total FROM dim_chapters WHERE season_id = ? AND played_date != '0000-00-00'";
	$stmt = $link->prepare($query_total);
	$stmt->bind_param("i", $id_temporada);
	$stmt->execute();
	$result = $stmt->get_result();
	$total_capitulos = $result->fetch_assoc()['total'] ?? 0;

	// Si no hay capitulos, salir
	if ($total_capitulos == 0) {
		//echo "<p>Esta temporada no tiene capitulos registrados.</p>";
		return;
	}

	// 2. Obtener participacion por personaje
	$hasParticipationRole = hg_sb_col_exists($link, 'bridge_chapters_characters', 'participation_role');
	$participationFilter = $hasParticipationRole
		? " AND acp.participation_role = 'player'"
		: " AND fact_characters.character_kind = 'pj' AND fact_characters.character_type_id = 1";

	$nombres = [];
	$jugados = [];
	$player_ids = [];
	$porcentajes = [];

	$query_participacion = "SELECT fact_characters.name, fact_characters.id AS pj_id, COUNT(DISTINCT acp.chapter_id) AS jugados
		   FROM bridge_chapters_characters acp
		   JOIN dim_chapters ac ON ac.id = acp.chapter_id
		   JOIN fact_characters ON fact_characters.id = acp.character_id
		   WHERE ac.season_id = ? AND ac.played_date != '0000-00-00'{$participationFilter}
		   GROUP BY acp.character_id, fact_characters.id, fact_characters.name
		   ORDER BY jugados DESC, fact_characters.name ASC";
	$stmt = $link->prepare($query_participacion);
	$stmt->bind_param("i", $id_temporada);
	$stmt->execute();
	$result = $stmt->get_result();

	while ($row = $result->fetch_assoc()) {
		$jugadosCount = (int)($row['jugados'] ?? 0);
		$nombres[] = (string)($row['name'] ?? '');
		$jugados[] = $jugadosCount;
		$player_ids[] = (int)($row['pj_id'] ?? 0);
		$porcentajes[] = ($total_capitulos > 0) ? round(($jugadosCount / $total_capitulos) * 100, 1) : 0;
	}

?>
