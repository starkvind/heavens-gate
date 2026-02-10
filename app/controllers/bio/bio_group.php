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
	  text-align: left;
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
		die("Error de conexiÃ³n a la base de datos: " . mysqli_connect_error());
	}

	// Helper escape
	function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

	// Sanitiza "1,2, 3" -> "1,2,3" (solo ints). Si queda vacÃ­o, devuelve ""
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
		echo "<h2>Error</h2><p class='texti'>Tipo invÃ¡lido.</p>";
		exit;
	}

	$valuePJ = "p.id, p.nombre, p.alias, p.estado, p.img, p.kes, p.tipo,
					COALESCE(nc2.id, nc_from_pack.id, 0) AS clan_id,
					COALESCE(nc2.pretty_id, nc_from_pack.pretty_id) AS clan_pretty_id,
					COALESCE(nc2.name, nc_from_pack.name, 'Sin clan') AS clan_name";
	$howMuch = 0;

	// ======================================== //
	// EXCLUSIONES DE CRÃ“NICAS (lista de ints, segura)
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
		$pageSect   = "$nombreTipo | BiografÃ­as";

		include("app/partials/main_nav_bar.php");
		echo "<h2>$nombreTipo</h2>";

		// ============================================================
		// Personajes por tipo + clan
		// ============================================================
		$queryPJ = "
			SELECT $valuePJ
			FROM fact_characters p

				-- Bridge: personaje -> manada
				LEFT JOIN bridge_characters_groups hcg
					ON hcg.character_id = p.id
					AND (hcg.is_active = 1 OR hcg.is_active IS NULL)
				LEFT JOIN dim_groups nm2
					ON nm2.id = hcg.group_id

				-- Bridge: personaje -> clan
				LEFT JOIN bridge_characters_organizations hcc
					ON hcc.character_id = p.id
					AND (hcc.is_active = 1 OR hcc.is_active IS NULL)
				LEFT JOIN dim_organizations nc2
					ON nc2.id = hcc.clan_id

				-- Bridge: manada -> clan (fallback / coherencia)
				LEFT JOIN bridge_organizations_groups hcg2
					ON hcg2.group_id = nm2.id
					AND (hcg2.is_active = 1 OR hcg2.is_active IS NULL)
				LEFT JOIN dim_organizations nc_from_pack
					ON nc_from_pack.id = hcg2.clan_id

			WHERE p.tipo = ?
			  $cronicaNotInSQL
			ORDER BY clan_id ASC, p.nombre ASC
		";

		$stmtPJ = mysqli_prepare($link, $queryPJ);
		if ($stmtPJ) {
			mysqli_stmt_bind_param($stmtPJ, 'i', $idTipo);
			mysqli_stmt_execute($stmtPJ);
			$resultPJ = mysqli_stmt_get_result($stmtPJ);

			if ($resultPJ && mysqli_num_rows($resultPJ) > 0) {
				$howMuch = mysqli_num_rows($resultPJ);

				$grupos = [];
				while ($rowPJ = mysqli_fetch_assoc($resultPJ)) {
					$clanId = (int)($rowPJ['clan_id'] ?? 0);
					$clanName = (string)($rowPJ['clan_name'] ?? 'Sin clan');
					$clanPretty = (string)($rowPJ['clan_pretty_id'] ?? '');

					$key = $clanId > 0 ? (string)$clanId : 'none';
					if (!isset($grupos[$key])) {
						$grupos[$key] = [
							'id' => $clanId,
							'name' => $clanName,
							'pretty_id' => $clanPretty,
							'items' => [],
						];
					}
					$grupos[$key]['items'][] = $rowPJ;
				}

				// Ordenar por ID asc, dejando "none" al final
				$keys = array_keys($grupos);
				usort($keys, function($a, $b){
					if ($a === 'none') return 1;
					if ($b === 'none') return -1;
					return (int)$a <=> (int)$b;
				});

				foreach ($keys as $k) {
					$grp = $grupos[$k];
					$clanId = (int)$grp['id'];
					$clanName = (string)$grp['name'];
					$fieldsetId = 'clan_' . ($clanId > 0 ? $clanId : 'none');

					echo "<h3 class='toggleAfiliacion' data-target='" . h($fieldsetId) . "'>" . h($clanName) . "</h3>";
					echo "<fieldset class='grupoBioClan' style='padding:0 1em;'>";
					echo "<div id='" . h($fieldsetId) . "' class='contenidoAfiliacion'>";

					foreach ($grp['items'] as $rowPJ) {
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
							"AÃºn por aparecer"     => "(&#64;)",
							"Paradero desconocido" => "(&#63;)",
							"CadÃ¡ver"              => "(&#8224;)"
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
					echo "</fieldset>";
				}
			}

			if ($resultPJ) mysqli_free_result($resultPJ);
			mysqli_stmt_close($stmtPJ);
		}

		echo "<p align='right'>Personajes: " . h($howMuch) . "</p>";

	} else {
		include("app/partials/main_nav_bar.php");
		echo "<h2>Tipo</h2><p class='texti' style='text-align:center;'>No se encontrÃ³ el tipo especificado.</p>";
	}

	mysqli_free_result($resultTypeQuery);
	mysqli_stmt_close($stmtType);

	// OJO: yo NO cerrarÃ­a $link aquÃ­ si lo reutilizas en la misma request con includes.
	// mysqli_close($link);
?>

<script>
	document.addEventListener('DOMContentLoaded', function(){
		var toggles = document.querySelectorAll('.toggleAfiliacion');
		for (var i = 0; i < toggles.length; i++) {
			toggles[i].addEventListener('click', function(){
				var targetId = this.getAttribute('data-target');
				var el = document.getElementById(targetId);
				if (!el) return;
				el.classList.toggle('oculto');
			});
		}
	});
</script>
