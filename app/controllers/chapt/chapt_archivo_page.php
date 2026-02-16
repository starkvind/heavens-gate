<?php

// Verificar la conexión a la base de datos
if (!$link) {
    die("Error de conexión a la base de datos: " . mysqli_connect_error());
}

// Obtener el parámetro 't' (id o pretty-id)
$chapter_numberRaw = $_GET['t'] ?? '';
$chapter_numberId = resolve_pretty_id($link, 'dim_chapters', (string)$chapter_numberRaw) ?? 0;

// Preparar la consulta para obtener detalles del capítulo
$Query = "SELECT * FROM dim_chapters WHERE id = ? LIMIT 1";
$stmt = mysqli_prepare($link, $Query);
if ($chapter_numberId > 0 && $stmt) {
    mysqli_stmt_bind_param($stmt, 's', $chapter_numberId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result && mysqli_num_rows($result) > 0) {
        $ResultQuery = mysqli_fetch_assoc($result);
        
        // Asignar valores de la base de datos a las variables
        $nameCapi 	= $ResultQuery["name"];
        $sinoCapi 	= $ResultQuery["synopsis"];
        $noSinoCapi	= "Este capítulo no dispone de información, disculpa las molestias.";
        $tempCapi 	= $ResultQuery["season_number"];
        $numeCapi	= $ResultQuery["chapter_number"];
        //$protCapi	= $ResultQuery["protagonistas"];
        $dateCapi	= $ResultQuery["played_date"];
		$dateIngame	= $ResultQuery["in_game_date"];

        // Títulos para diferentes secciones
        $titleInfo 	= "Resumen";
        $titleProta	= "Participantes";

        // Preparamos la temporada
        $tempQuery = "SELECT id, name, season_number FROM dim_seasons WHERE season_number = ? LIMIT 1";
        $stmtTemp = mysqli_prepare($link, $tempQuery);
        if ($stmtTemp) {
            mysqli_stmt_bind_param($stmtTemp, 's', $tempCapi);
            mysqli_stmt_execute($stmtTemp);
            $resultTemp = mysqli_stmt_get_result($stmtTemp);
            $resultDataTemp = mysqli_fetch_assoc($resultTemp);

            $idTemporada   = $resultDataTemp["id"];
            $nameTemporada = $resultDataTemp["name"];
            $numbTemporada = $resultDataTemp["season_number"];
        }

        // Preparamos los títulos y nombres
        $checkNumCapi = ($numeCapi < 10) ? '0' : '';
        $goodNumTemp = ($numbTemporada >= 100) ? '' : $numbTemporada;
        $numeracionOK = ($numbTemporada < 99) ? "{$goodNumTemp}x{$checkNumCapi}{$numeCapi}" : "{$checkNumCapi}{$numeCapi}";

        $goodFecha	= date("d-m-Y", strtotime($dateCapi));
		$goodIngameFecha = date("d-m-Y", strtotime($dateIngame));

        // Cambiar el título de la página
        $pageSect = "{$nameTemporada} {$numeracionOK}";
        $pageTitle2	= $nameCapi;
		setMetaFromPage($nameCapi . " | Cap?tulos | Heaven's Gate", meta_excerpt(!empty($sinoCapi) ? $sinoCapi : $noSinoCapi), null, 'article');

        include("app/partials/main_nav_bar.php");	// Barra Navegación
        echo "<h2>{$nameCapi}</h2>";

        echo "<div class='bioBody'>"; // Cuerpo principal de la Ficha de Temporada
		
        // Sección Protagonistas (solo si hay personajes)
		// Nueva sección de protagonistas, basada en la tabla bridge_chapters_characters
		$protaQuery = "
			SELECT p.id, p.name, p.img
			FROM bridge_chapters_characters acp
			INNER JOIN fact_characters p ON acp.character_id = p.id
			WHERE acp.chapter_id = ?
			ORDER BY p.name ASC
		";
		$stmtProta = mysqli_prepare($link, $protaQuery);
		if ($stmtProta) {
			mysqli_stmt_bind_param($stmtProta, 'i', $chapter_numberId);
			mysqli_stmt_execute($stmtProta);
			$resultProta = mysqli_stmt_get_result($stmtProta);

			if ($resultProta && mysqli_num_rows($resultProta) > 0) {
				echo "<fieldset id='renglonArchivos'>";
				echo "<legend id='archivosLegend'>{$titleProta}</legend>";
				echo "<center>";
				while ($pj = mysqli_fetch_assoc($resultProta)) {
					$idPJSelect = $pj["id"];
					$nombre = htmlspecialchars($pj["name"]);
					$img = htmlspecialchars($pj["img"]);
					$hrefChar = pretty_url($link, 'fact_characters', '/characters', (int)$idPJSelect);
					echo "<a href='" . htmlspecialchars($hrefChar) . "' title='{$nombre}' target='_blank'>";
					echo "<img src='{$img}' class='photochapter'>";
					echo "</a>";
				}
				echo "</center>";
				echo "</fieldset>";
			}

			mysqli_free_result($resultProta);
			mysqli_stmt_close($stmtProta);
		}

        // Sección Sinopsis
        echo "<fieldset id='renglonArchivosTop'>";
        echo "<legend id='archivosLegend'>{$titleInfo}</legend>";
		if ($dateCapi != "0000-00-00" or $dateIngame != "") {
			echo "<ul>";
		}
		if ($dateIngame != "0000-00-00" && $goodIngameFecha != "01-01-1970") {
			echo "<li><b>Fecha en ficción:</b> {$goodIngameFecha}</li>";
		}
        if ($dateCapi != "0000-00-00") {
            echo "<li><b>Fecha de juego:</b> {$goodFecha}</li>";
        }
		if ($dateCapi != "0000-00-00" or $dateIngame != "0000-00-00") {
			echo "</ul>";
		}
        echo (!empty($sinoCapi)) ? "<p>{$sinoCapi}</p>" : "<p>{$noSinoCapi}</p>";
        echo "</fieldset>";

        echo "</div>"; // Cierre del Cuerpo Principal
		
		include("app/partials/snippet_bso_card.php");
		mostrarTarjetaBSO($link, 'episodio', $chapter_numberId);
		
	// ===========================
	// ENLACES SIGUIENTE / ANTERIOR
	// ===========================
	echo "<div style='text-align:center; margin: 2em auto; width:100%;'>";

	$navQuery = "
		SELECT id, chapter_number, name FROM dim_chapters 
		WHERE season_number = ? AND chapter_number IN (?, ?)
		ORDER BY chapter_number ASC
	";
	$stmtNav = mysqli_prepare($link, $navQuery);
	if ($stmtNav) {
		$prevCap = $numeCapi - 1;
		$nextCap = $numeCapi + 1;
		mysqli_stmt_bind_param($stmtNav, 'iii', $tempCapi, $prevCap, $nextCap);
		mysqli_stmt_execute($stmtNav);
		$resultNav = mysqli_stmt_get_result($stmtNav);

		$chapter_numbersNavegacion = [];
		while ($fila = mysqli_fetch_assoc($resultNav)) {
			$chapter_numbersNavegacion[$fila['chapter_number']] = $fila;
		}

		if (isset($chapter_numbersNavegacion[$prevCap])) {
			$prevId = $chapter_numbersNavegacion[$prevCap]['id'];
			$prevName = htmlspecialchars($chapter_numbersNavegacion[$prevCap]['name']);
			$prevHref = pretty_url($link, 'dim_chapters', '/chapters', (int)$prevId);
			echo "<a class='boton2 pj-btn-pag' style='float:left;' href='" . htmlspecialchars($prevHref) . "'>&laquo; <small>{$prevName}</small></a> ";
		}

		if (isset($chapter_numbersNavegacion[$nextCap])) {
			$nextId = $chapter_numbersNavegacion[$nextCap]['id'];
			$nextName = htmlspecialchars($chapter_numbersNavegacion[$nextCap]['name']);
			$nextHref = pretty_url($link, 'dim_chapters', '/chapters', (int)$nextId);
			echo "<a class='boton2 pj-btn-pag' style='float:right;' href='" . htmlspecialchars($nextHref) . "'><small>{$nextName}</small> &raquo;</a>";
		}

		mysqli_free_result($resultNav);
		mysqli_stmt_close($stmtNav);
	}

	echo "</div>";


        mysqli_free_result($result);
        if (isset($resultTemp)) {
            mysqli_free_result($resultTemp);
        }
        mysqli_stmt_close($stmt);
        if (isset($stmtTemp)) {
            mysqli_stmt_close($stmtTemp);
        }
    } else {
        echo "No se encontraron resultados para la búsqueda.";
    }
} elseif ($chapter_numberId <= 0) {
    echo "No se encontraron resultados para la búsqueda.";
} else {
    echo "Error al preparar la consulta: " . mysqli_error($link);
}
?>


<html>

<style>

.pj-btn-pag {
    font-family: Verdana, Arial, sans-serif;
    font-size: 11px;
    padding: 0.5em 1em;
    margin: 0 10px;
    text-decoration: none;
    display: inline-block;
}
.pj-btn-pag:hover {
    cursor: pointer;
}

</style>

</html>



