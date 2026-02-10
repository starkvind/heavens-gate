<?php

// Verificar la conexión a la base de datos
if (!$link) {
    die("Error de conexión a la base de datos: " . mysqli_connect_error());
}

// Obtener el parámetro 't' (id o pretty-id)
$capituloRaw = $_GET['t'] ?? '';
$capituloId = resolve_pretty_id($link, 'dim_chapters', (string)$capituloRaw) ?? 0;

// Preparar la consulta para obtener detalles del capítulo
$Query = "SELECT * FROM dim_chapters WHERE id = ? LIMIT 1";
$stmt = mysqli_prepare($link, $Query);
if ($capituloId > 0 && $stmt) {
    mysqli_stmt_bind_param($stmt, 's', $capituloId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result && mysqli_num_rows($result) > 0) {
        $ResultQuery = mysqli_fetch_assoc($result);
        
        // Asignar valores de la base de datos a las variables
        $nameCapi 	= $ResultQuery["name"];
        $sinoCapi 	= $ResultQuery["sinopsis"];
        $noSinoCapi	= "Este capítulo no dispone de información, disculpa las molestias.";
        $tempCapi 	= $ResultQuery["temporada"];
        $numeCapi	= $ResultQuery["capitulo"];
        //$protCapi	= $ResultQuery["protagonistas"];
        $dateCapi	= $ResultQuery["fecha"];
		$dateIngame	= $ResultQuery["fecha_ingame"];

        // Títulos para diferentes secciones
        $titleInfo 	= "Resumen";
        $titleProta	= "Participantes";

        // Preparamos la temporada
        $tempQuery = "SELECT id, name, numero FROM dim_seasons WHERE numero = ? LIMIT 1";
        $stmtTemp = mysqli_prepare($link, $tempQuery);
        if ($stmtTemp) {
            mysqli_stmt_bind_param($stmtTemp, 's', $tempCapi);
            mysqli_stmt_execute($stmtTemp);
            $resultTemp = mysqli_stmt_get_result($stmtTemp);
            $resultDataTemp = mysqli_fetch_assoc($resultTemp);

            $idTemporada   = $resultDataTemp["id"];
            $nameTemporada = $resultDataTemp["name"];
            $numbTemporada = $resultDataTemp["numero"];
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
		setMetaFromPage($nameCapi . " | Capitulos | Heaven's Gate", meta_excerpt(!empty($sinoCapi) ? $sinoCapi : $noSinoCapi), null, 'article');

        include("app/partials/main_nav_bar.php");	// Barra Navegación
        echo "<h2>{$nameCapi}</h2>";

        echo "<div class='bioBody'>"; // Cuerpo principal de la Ficha de Temporada
		
        // Sección Protagonistas (solo si hay personajes)
		// Nueva sección de protagonistas, basada en la tabla bridge_chapters_characters
		$protaQuery = "
			SELECT p.id, p.nombre, p.img
			FROM bridge_chapters_characters acp
			INNER JOIN fact_characters p ON acp.id_personaje = p.id
			WHERE acp.id_capitulo = ?
			ORDER BY p.nombre ASC
		";
		$stmtProta = mysqli_prepare($link, $protaQuery);
		if ($stmtProta) {
			mysqli_stmt_bind_param($stmtProta, 'i', $capituloId);
			mysqli_stmt_execute($stmtProta);
			$resultProta = mysqli_stmt_get_result($stmtProta);

			if ($resultProta && mysqli_num_rows($resultProta) > 0) {
				echo "<fieldset id='renglonArchivos'>";
				echo "<legend id='archivosLegend'>{$titleProta}</legend>";
				echo "<center>";
				while ($pj = mysqli_fetch_assoc($resultProta)) {
					$idPJSelect = $pj["id"];
					$nombre = htmlspecialchars($pj["nombre"]);
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
		mostrarTarjetaBSO($link, 'episodio', $capituloId);
		
	// ===========================
	// ENLACES SIGUIENTE / ANTERIOR
	// ===========================
	echo "<div style='text-align:center; margin: 2em auto; width:100%;'>";

	$navQuery = "
		SELECT id, capitulo, name FROM dim_chapters 
		WHERE temporada = ? AND capitulo IN (?, ?)
		ORDER BY capitulo ASC
	";
	$stmtNav = mysqli_prepare($link, $navQuery);
	if ($stmtNav) {
		$prevCap = $numeCapi - 1;
		$nextCap = $numeCapi + 1;
		mysqli_stmt_bind_param($stmtNav, 'iii', $tempCapi, $prevCap, $nextCap);
		mysqli_stmt_execute($stmtNav);
		$resultNav = mysqli_stmt_get_result($stmtNav);

		$capitulosNavegacion = [];
		while ($fila = mysqli_fetch_assoc($resultNav)) {
			$capitulosNavegacion[$fila['capitulo']] = $fila;
		}

		if (isset($capitulosNavegacion[$prevCap])) {
			$prevId = $capitulosNavegacion[$prevCap]['id'];
			$prevName = htmlspecialchars($capitulosNavegacion[$prevCap]['name']);
			$prevHref = pretty_url($link, 'dim_chapters', '/chapters', (int)$prevId);
			echo "<a class='boton2 pj-btn-pag' style='float:left;' href='" . htmlspecialchars($prevHref) . "'>&laquo; <small>{$prevName}</small></a> ";
		}

		if (isset($capitulosNavegacion[$nextCap])) {
			$nextId = $capitulosNavegacion[$nextCap]['id'];
			$nextName = htmlspecialchars($capitulosNavegacion[$nextCap]['name']);
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
} elseif ($capituloId <= 0) {
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
