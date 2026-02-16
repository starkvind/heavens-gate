<?php
$character_id = $_GET['b'] ?? 1;

// 1. Obtener la primera fecha de aparición
$query_inicio = "
    SELECT MIN(ac.played_date) AS primera_fecha
    FROM dim_chapters ac
    JOIN bridge_chapters_characters acp ON ac.id = acp.chapter_id
    WHERE acp.character_id = ? AND ac.played_date != '0000-00-00'
";
$stmt = $link->prepare($query_inicio);
$stmt->bind_param("i", $character_id);
$stmt->execute();
$result = $stmt->get_result();
$inicio = $result->fetch_assoc()['primera_fecha'] ?? null;

if ($inicio && isset($finalPlayer)) {

    // 2. Obtener capítulos en los que participó
    $query_capitulos_pj = "
        SELECT ac.id, ac.played_date, ac.season_number
        FROM dim_chapters ac
        JOIN bridge_chapters_characters acp ON ac.id = acp.chapter_id
        WHERE acp.character_id = ? AND ac.played_date != '0000-00-00'
    ";
    $stmt = $link->prepare($query_capitulos_pj);
    $stmt->bind_param("i", $character_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $jugados = [];
    $temporadas_validas = [];
    $temporadas_por_mes = [];
    while ($row = $result->fetch_assoc()) {
        $mes = date('Y-m', strtotime($row['played_date']));
        $jugados[$mes] = ($jugados[$mes] ?? 0) + 1;
        $temporadas_validas[$row['season_number']] = true;
        $temporadas_por_mes[$mes] = $row['season_number']; // última temporada válida en el mes
    }

	// Obtener capítulos de todas las temporadas válidas (aunque el personaje no jugara)
	$temporadas_in = implode(',', array_keys($temporadas_validas));
	$query_mapeo_temporadas = "
		SELECT DATE_FORMAT(ac.played_date, '%Y-%m') AS mes, MAX(ac.season_number) AS season_number
		FROM dim_chapters ac
		WHERE ac.played_date >= ? AND ac.played_date != '0000-00-00' AND ac.season_number IN ($temporadas_in)
		GROUP BY mes
	";
	$stmt = $link->prepare($query_mapeo_temporadas);
	$stmt->bind_param("s", $inicio);
	$stmt->execute();
	$result = $stmt->get_result();
	$temporadas_por_mes = [];
	while ($row = $result->fetch_assoc()) {
		$temporadas_por_mes[$row['mes']] = $row['season_number'];
	}

    // 3. Obtener todos los capítulos de esas temporadas para contar “esperados”
    //$temporadas_in = implode(',', array_keys($temporadas_validas));
    $query_esperados = "
        SELECT DATE_FORMAT(played_date, '%Y-%m') AS mes, COUNT(*) AS total
        FROM dim_chapters
        WHERE played_date >= ? AND played_date != '0000-00-00' AND season_number IN ($temporadas_in)
        GROUP BY mes
    ";
    $stmt = $link->prepare($query_esperados);
    $stmt->bind_param("s", $inicio);
    $stmt->execute();
    $result = $stmt->get_result();
    $esperados = [];
    while ($row = $result->fetch_assoc()) {
        $esperados[$row['mes']] = $row['total'];
    }

    // Rango de meses desde inicio hasta último esperado
    $all_meses = [];
    $first = new DateTime($inicio);
    $last = new DateTime(max(array_keys($esperados) + array_keys($jugados)));
    $last->modify('first day of next month');
    while ($first < $last) {
        $all_meses[] = $first->format('Y-m');
        $first->modify('+1 month');
    }

    // Obtener nombres de temporadas para tooltip
    $query_nombres = "
        SELECT season_number AS id, name FROM dim_seasons
        WHERE season_number IN ($temporadas_in)
    ";
    $result = $link->query($query_nombres);
    $nombre_temporadas = [];
    while ($row = $result->fetch_assoc()) {
        $nombre_temporadas[$row['id']] = $row['name'];
    }

    // Construcción final
    $labels = $all_meses;
    $datos_jugados = [];
    $datos_esperados = [];
    $datos_temporada = [];

    foreach ($labels as $mes) {
        $datos_jugados[] = $jugados[$mes] ?? 0;
        $datos_esperados[] = $esperados[$mes] ?? 0;
        $id_temporada = $temporadas_por_mes[$mes] ?? null;
		$datos_temporada[] = $id_temporada ? ($nombre_temporadas[$id_temporada] ?? '¿?') : '';
    }
}
?>

<?php if ($inicio && isset($finalPlayer)): ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="bioTextData">
	<fieldset class='bioSeccion'>
		<legend>&nbsp;Participación de <?php echo $finalPlayer; ?>&nbsp;</legend>
		<canvas id="participacionChart" width="500" height="300"></canvas>
	</fieldset>
</div>

<script>
let labels = <?= json_encode($labels) ?>;
let temporadas = <?= json_encode($datos_temporada) ?>;
let jugados = <?= json_encode($datos_jugados) ?>;
let esperados = <?= json_encode($datos_esperados) ?>;

let ctx = document.getElementById('participacionChart').getContext('2d');
let chart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: labels,
		
        datasets: [
            {
                label: 'Participación',
                data: jugados,
                borderColor: 'rgba(86, 240, 120, 1)',
				//'rgba(75, 192, 192, 1)',
                backgroundColor: 'rgba(13, 150, 43, 0.5)',
				//'rgba(75, 192, 192, 0.2)',
                tension: 0.3
            },
            {
                label: 'Totales',
                data: esperados,
                borderColor: 'rgba(224, 0, 0, 1)',
                backgroundColor: 'rgba(143, 0, 0, 0.5)',
                tension: 0.3
            }
        ]
    },
	options: {
		interaction: {
			mode: 'index',
			intersect: false
		},
		plugins: {
			legend: {
				labels: {
					color: 'white'
				}
			},
			tooltip: {
				mode: 'index',
				intersect: false,
				callbacks: {
					title: function(tooltipItems) {
						const index = tooltipItems[0].dataIndex;
						const label = labels[index];
						const temporada = temporadas[index];
						return temporada ? `${label} – ${temporada}` : label;
					},
					label: function(context) {
						return `${context.dataset.label}: ${context.formattedValue}`;
					}
				}
			}
		},
		scales: {
			x: {
				ticks: { color: 'white' }
			},
			y: {
				beginAtZero: true,
				ticks: {
					color: 'white',
					stepSize: 1,
					precision: 0
				}
			}
		}
	}

});
</script>

<?php endif; ?>

