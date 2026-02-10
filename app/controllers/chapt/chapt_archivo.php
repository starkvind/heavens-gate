<?php setMetaFromPage("Temporadas | Heaven's Gate", "Consulta temporadas y capitulos de la campana.", null, 'website'); ?>
<style>
	.prota-grid {
		display: flex;
		flex-wrap: wrap;
		gap: 1em;
		justify-content: center;
	}

	.prota-card {
		display: flex;
		flex-direction: column;
		align-items: center;
		text-decoration: none;
		color: #fff;
		width: 76px;
	}

	.prota-card:hover {
		color: #00ffff;
	}

	.prota-card span {
		text-align: center;
	}

	.prota-card img.photochapter {
		width: 64px;
		height: 64px;
		border-radius: 50%;
		border: 1px solid #000099;
		object-fit: cover;
		margin-bottom: 0.3em;
	}
	
	.video-wrapper {
	  display: flex;
	  justify-content: center;
	  padding: 1em;
	}
	
	.video-wrapper iframe {
		border: 1px solid #000099;
	}
</style>

<?php
// Verificar la conexión a la base de datos
if (!$link) {
    die("Error de conexión a la base de datos: " . mysqli_connect_error());
}

// Obtener el parámetro 't' (id o pretty-id)
$temporadaRaw = $_GET['t'] ?? '';
$temporadaId = resolve_pretty_id($link, 'dim_seasons', (string)$temporadaRaw) ?? 0;

// Preparar la consulta para obtener detalles de la temporada
$consulta = "SELECT * FROM dim_seasons WHERE id = ? LIMIT 1";
$stmt = mysqli_prepare($link, $consulta);
if ($temporadaId > 0 && $stmt) {
    mysqli_stmt_bind_param($stmt, 's', $temporadaId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result && mysqli_num_rows($result) > 0) {
		
        while ($ResultQuery = mysqli_fetch_assoc($result)) {
            // Asignar valores de la base de datos a las variables
            $nameTemp = $ResultQuery["name"];
            $numberTemp = $ResultQuery["numero"];
            $sinopsis = $ResultQuery["desc"];
            $linkYoutube = $ResultQuery["opening"];
            //$charas = $ResultQuery["protagonistas"];

            // Títulos para diferentes secciones
            $titleSinop = "Sinopsis";
            $titleProta = "Protagonistas";
            $titleChapt = "Capítulos";
            $titleOpeni = "Opening";

            // Determinar el título de la página basado en la temporada
            $esTempOno = $ResultQuery["season"];
            $pageSect = ($esTempOno == 0) ? "Temporadas" : "Historia personal";
            $pageTitle2 = $nameTemp;

            include("app/partials/main_nav_bar.php"); // Barra Navegación
            echo "<h2>$nameTemp</h2>";

            echo "<div class='bioBody'>"; // Cuerpo principal de la Ficha de Temporada
			
			include("app/partials/chapt/chapt_archivo_barchart_prepare.php");

            // Sección Sinopsis
            echo "<fieldset id='renglonArchivosTop'>";
				echo "<legend id='archivosLegend'>$titleSinop</legend>";
				echo "<p>$sinopsis</p>";
            echo "</fieldset>";
			
			if (isset($player_ids)) {
				$numProtagonistas = count($player_ids);
				// Sección Protagonistas (solo si hay protagonistas)
				if ($numProtagonistas > 0) {
					echo "<fieldset id='renglonArchivos'>";
						echo "<legend id='archivosLegend'>$titleProta</legend>";
						echo "<div class='prota-grid'>";
							$ids = implode(',', array_map('intval', $player_ids)); // sanitiza
							$query = "
							SELECT 
								p.id, 
								p.nombre, 
								p.img
							FROM fact_characters p
							WHERE 1=1
								AND id IN ($ids) 
								AND jugador > 0 
							ORDER BY 2
							";
							// temporadaId
							$result = $link->query($query);
							$br_count = 0;
							while ($row = $result->fetch_assoc()) {
								/* ------------------------------------------------------------------------- */
								$check_id = $row["id"];

								$index = array_search($check_id, $player_ids);
								$participaciones = $index !== false ? $jugados[$index] : 0;

								$max = max($jugados);
								$umbral = $max / 2;

								if ($participaciones >= $umbral) {
									$hrefProta = pretty_url($link, 'fact_characters', '/characters', (int)$row['id']);
									echo "<a href='" . htmlspecialchars($hrefProta) . "' class='prota-card' target='_blank' title='" . htmlspecialchars($row["nombre"]) . "'>";
										echo "<img src='" . htmlspecialchars($row["img"]) . "' class='photochapter'><span>" . htmlspecialchars($row["nombre"]) . "</span>";
									echo "</a>";
								}
								/* ------------------------------------------------------------------------- */
							}
						echo "</div>";
					echo "</fieldset>";
				}
			}
			
            // Sección Capítulos
            echo "<fieldset id='renglonArchivos' style='padding-left:46px;'>";
            echo "<legend id='archivosLegend' style='margin-left:-36px;'>$titleChapt</legend>";

            $consultaChapt = "SELECT id, name, capitulo FROM dim_chapters WHERE temporada = ? ORDER BY capitulo";
            $stmtChapt = mysqli_prepare($link, $consultaChapt);
            if ($stmtChapt) {
                mysqli_stmt_bind_param($stmtChapt, 's', $numberTemp);
                mysqli_stmt_execute($stmtChapt);
                $resultChapt = mysqli_stmt_get_result($stmtChapt);

                if ($resultChapt && mysqli_num_rows($resultChapt) > 0) {
                    while ($ResultQueryChapt = mysqli_fetch_assoc($resultChapt)) {
                        $idEpi = $ResultQueryChapt["id"];
                        $nameEpi = $ResultQueryChapt["name"];
                        $capiEpi = $ResultQueryChapt["capitulo"];

                        // Definir estilo del popup de capítulos
                        if ($esTempOno == 0) {
                            if ($capiEpi < 10 && $numberTemp < 100) { $capiEpi = "0$capiEpi"; }
                            $numeEpi = ($numberTemp < 100) ? "Capítulo $numberTemp"."x$capiEpi" : "Capítulo $capiEpi";
                        } else {
                            $numeEpi = "Capítulo $capiEpi";
                        }

                        $hrefChap = pretty_url($link, 'dim_chapters', '/chapters', (int)$idEpi);
                        echo "<a href='" . htmlspecialchars($hrefChap) . "'>";
                        echo "<div class='renglon2col' style='text-align:center;' title='$numeEpi'>$nameEpi</div>";
                        echo "</a>";
                    }
                }
                mysqli_free_result($resultChapt);
                mysqli_stmt_close($stmtChapt);
            }

            echo "</fieldset>";
			
			// Sección Opening (solo si hay enlace de YouTube)
			include("app/partials/snippet_bso_card.php");
			mostrarTarjetaBSO($link, 'temporada', $temporadaId);
			
			if (isset($player_ids)) {
				include("app/partials/chapt/chapt_archivo_barchart.php");
			}

            echo "</div>"; // Cierre del Cuerpo Principal
        }
    } else {
        echo "No se encontraron resultados para la búsqueda.";
    }

    mysqli_free_result($result);
    mysqli_stmt_close($stmt);
} elseif ($temporadaId <= 0) {
    echo "No se encontraron resultados para la búsqueda.";
} else {
    echo "Error al preparar la consulta: " . mysqli_error($link);
}
?>
