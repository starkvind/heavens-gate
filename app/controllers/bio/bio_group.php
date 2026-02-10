<?php setMetaFromPage("Biografias por grupo | Heaven's Gate", "Listado de biografias agrupadas por clan o tipo.", null, 'website'); ?>
<style>
	.toggleAfiliacion {
	  background: #05014e;
	  color: #fff;
	  border: 1px solid #000088;
	  padding: 6px 10px;
	  margin: 8px 0 0 0;
	  font-size: 1.1em;
	  cursor: pointer;
	  width: 85%;
	}

	.toggleAfiliacion:hover {
	  background: #000066;
	  border: 1px solid #0000BB;
	}

	.contenidoAfiliacion {
	  display: flex;
	  flex-wrap: wrap;
	  gap: 6px;
	  padding: 8px 0 12px 0;
	}

	.oculto { display: none; }
</style>

<?php
	if (!$link) {
		die("Error de conexión a la base de datos: " . mysqli_connect_error());
	}

	// Helper escape
	function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

	// Sanitiza "1,2, 3" -> "1,2,3" (solo ints). Si queda vacío, devuelve ""
	function sanitize_int_csv($csv){
		$csv = (string)$csv;
		if (trim($csv) === '') return '';
		$parts = preg_split('/\s*,\s*/', trim($csv));
		$ints = [];
		foreach ($parts as $p) {
			if ($p === '') continue;
			if (preg_match('/^\d+$/', $p)) $ints[] = (string)(int)$p;
		}
		$ints = array_values(array_unique($ints));
		return implode(',', $ints);
	}

	$idTipo = isset($_GET['t']) ? (int)$_GET['t'] : 0;
	if ($idTipo <= 0) {
		include("app/partials/main_nav_bar.php");
		echo "<h2>Error</h2><p class='texti'>Tipo inválido.</p>";
		exit;
	}

	$valuePJ = "p.id, p.nombre, p.alias, p.estado, p.img, p.kes, p.tipo";
	$howMuch = 0;

	// ======================================== //
	// EXCLUSIONES DE CRÓNICAS (lista de ints, segura)
	$excludeChronicles = isset($excludeChronicles) ? sanitize_int_csv($excludeChronicles) : '';
	$cronicaNotInSQL = ($excludeChronicles !== '') ? " AND p.cronica NOT IN ($excludeChronicles) " : "";

	// ======================================== //
	// Nombre del Tipo
	$typeQuery = "SELECT tipo FROM dim_character_types WHERE id = ? LIMIT 1";
	$stmtType = mysqli_prepare($link, $typeQuery);
	if (!$stmtType) die("Error preparando typeQuery: " . mysqli_error($link));

	mysqli_stmt_bind_param($stmtType, 'i', $idTipo);
	mysqli_stmt_execute($stmtType);
	$resultTypeQuery = mysqli_stmt_get_result($stmtType);

	if ($rowType = mysqli_fetch_assoc($resultTypeQuery)) {

		$nombreTipo = h($rowType["tipo"]);
		$pageSect   = "$nombreTipo | Biografías";

		include("app/partials/main_nav_bar.php");
		echo "<h2>$nombreTipo</h2>";

		// ============================================================
		// Personajes por tipo
		// ============================================================
		$queryPJ = "
			SELECT $valuePJ
			FROM fact_characters p
			WHERE p.tipo = ?
			  $cronicaNotInSQL
			ORDER BY
				CASE p.estado
					WHEN 'Paradero desconocido' THEN 1
					WHEN 'Cadáver' THEN 2
					WHEN 'Aún por aparecer' THEN 9999
					ELSE 0
				END,
				p.nombre ASC
		";

		$stmtPJ = mysqli_prepare($link, $queryPJ);
		if ($stmtPJ) {
			mysqli_stmt_bind_param($stmtPJ, 'i', $idTipo);
			mysqli_stmt_execute($stmtPJ);
			$resultPJ = mysqli_stmt_get_result($stmtPJ);

			if ($resultPJ && mysqli_num_rows($resultPJ) > 0) {
				$howMuch = mysqli_num_rows($resultPJ);

				echo "<div class='grupoBioClan'>";
				echo "<div class='contenidoAfiliacion'>";

				while ($rowPJ = mysqli_fetch_assoc($resultPJ)) {
					$idPJ     = (int)$rowPJ["id"];
					$nombrePJ = h($rowPJ["nombre"] ?? '');
					$aliasPJ  = h($rowPJ["alias"] ?? '');
					$imgPJ    = h($rowPJ["img"] ?? '');
					$clasePJ  = h($rowPJ["kes"] ?? '');
					$estadoPJ = h($rowPJ["estado"] ?? '');

					if ($aliasPJ === "") { $aliasPJ = $nombrePJ; }

					$fondoFoto = "";
					$estiloLink = "";
					if ($clasePJ !== "pj" && $clasePJ !== "") {
						$fondoFoto = "NoSheet";
						$estiloLink = "color: #EE0000;";
					}

					$mapEstado = [
						"Aún por aparecer"     => "(&#64;)",
						"Paradero desconocido" => "(&#63;)",
						"Cadáver"              => "(&#8224;)"
					];
					$simboloEstado = $mapEstado[$estadoPJ] ?? "";

					$hrefPJ = pretty_url($link, 'fact_characters', '/characters', $idPJ);
					echo "<a href='" . h($hrefPJ) . "' title='" . $nombrePJ . "' style='" . h($estiloLink) . "'>";
						echo "<div class='marcoFotoBio" . h($fondoFoto) . "'>";
							echo "<div class='textoDentroFotoBio" . h($fondoFoto) . "'>$aliasPJ $simboloEstado</div>";

							if ($imgPJ !== "") {
								echo "<div class='dentroFotoBio'><img class='fotoBioList' src='$imgPJ' alt='$nombrePJ'></div>";
							} else {
								echo "<div class='dentroFotoBio'><span>Sin imagen</span></div>";
							}
						echo "</div>";
					echo "</a>";
				}

				echo "</div>"; // contenidoAfiliacion
				echo "</div>"; // grupoBioClan
			}

			if ($resultPJ) mysqli_free_result($resultPJ);
			mysqli_stmt_close($stmtPJ);
		}

		echo "<p align='right'>Personajes: " . h($howMuch) . "</p>";

	} else {
		include("app/partials/main_nav_bar.php");
		echo "<h2>Tipo</h2><p class='texti' style='text-align:center;'>No se encontró el tipo especificado.</p>";
	}

	mysqli_free_result($resultTypeQuery);
	mysqli_stmt_close($stmtType);

	// OJO: yo NO cerraría $link aquí si lo reutilizas en la misma request con includes.
	// mysqli_close($link);
?>
