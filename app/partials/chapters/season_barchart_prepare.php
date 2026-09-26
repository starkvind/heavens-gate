<?php
	if (function_exists('hg_page_register_stylesheet')) {
		hg_page_register_stylesheet('/assets/css/hg-chapters-runtime.css');
	}

	require_once(__DIR__ . '/../../domains/chapters/queries.php');

	$id_temporada = isset($temporadaId) ? (int)$temporadaId : 0;

	if ($id_temporada <= 0) {
		echo "<p class='chapt-error'>Temporada no valida.</p>";
		return;
	}

	$attendance = hg_chapters_fetch_season_attendance($link, $id_temporada);
	if ($attendance === null) {
		echo "<p class='chapt-error'>No se encontro la temporada solicitada.</p>";
		return;
	}

	$numero_temporada = (int)($attendance['season_number'] ?? 0);
	$total_capitulos = (int)($attendance['total'] ?? 0);

	if ($total_capitulos === 0) {
		return;
	}

	$nombres = [];
	$jugados = [];
	$player_ids = [];
	$porcentajes = [];

	foreach (($attendance['players'] ?? []) as $row) {
		$jugadosCount = (int)($row['played_count'] ?? 0);
		$nombres[] = (string)($row['name'] ?? '');
		$jugados[] = $jugadosCount;
		$player_ids[] = (int)($row['pj_id'] ?? 0);
		$porcentajes[] = ($total_capitulos > 0) ? round(($jugadosCount / $total_capitulos) * 100, 1) : 0;
	}

?>