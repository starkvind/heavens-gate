<?php setMetaFromPage("Temporadas | Analisis | Heaven's Gate", "Analisis de asistencia y actividad por temporada.", null, 'website'); ?>
<?php 

// 1. Obtener todos los capítulos válidos con nombre y número de temporada
$query = "SELECT 
				ac.id, ac.season_number AS num_temporada, at.name AS nombre_temporada, ac.played_date 
			FROM dim_chapters ac 
			LEFT JOIN dim_seasons at ON ac.season_number = at.season_number
			WHERE ac.played_date != '0000-00-00'";
$result = $link->query($query);
$capitulos = [];
$capitulos_por_temporada = [];
$nombre_temporadas = [];

while ($row = $result->fetch_assoc()) {
    $capitulos[$row['id']] = $row;
    $num = $row['num_temporada'];
    $capitulos_por_temporada[$num][] = $row['id'];
    $nombre_temporadas[$num] = $row['nombre_temporada']; // Map: número → nombre
}

// 2. Obtener participaciones
$query = "SELECT character_id, chapter_id FROM bridge_chapters_characters";
$result = $link->query($query);
$apariciones = [];

while ($row = $result->fetch_assoc()) {
    $pj = $row['character_id'];
    $cap = $row['chapter_id'];

    if (!isset($capitulos[$cap])) continue;
    $temporada = $capitulos[$cap]['num_temporada'];
    $apariciones[$pj][$temporada][] = $cap;
}

// 3. Obtener nombres de personajes y orden fijo
$query = "SELECT p.id, p.name
  FROM fact_characters p 
  JOIN bridge_chapters_characters acp ON p.id = acp.character_id 
  WHERE p.character_kind = 'pj' AND p.player_id > 0
  GROUP BY p.id, p.name
  ORDER BY COUNT(acp.id) DESC";

$result = $link->query($query);
$labels = [];     // ID → nombre
$pj_order = [];   // lista de IDs en orden fijo

while ($row = $result->fetch_assoc()) {
    $id = $row['id'];
    $labels[$id] = $row['name'];
    $pj_order[] = $id;
}

// 4. Preparar datos por temporada (siguiendo $pj_order)
$temporadas_totales = array_keys($capitulos_por_temporada);
sort($temporadas_totales); // orden por número

$datos_por_temporada = [];
$dataset_colors = ['#4dc9f6','#f67019','#f53794','#537bc4','#acc236','#166a8f','#00a950','#58595b'];

foreach ($temporadas_totales as $i => $temp_num) {
    $color = $dataset_colors[$i % count($dataset_colors)];
    $data_por_pj = [];

    foreach ($pj_order as $pj_id) {
        $jugados = count($apariciones[$pj_id][$temp_num] ?? []);
        $data_por_pj[$pj_id] = $jugados;
    }

    $data_ordenada = [];
    foreach ($pj_order as $pj_id) {
        $data_ordenada[] = $data_por_pj[$pj_id];
    }

    $nombre_legible = $nombre_temporadas[$temp_num] ?? "T$temp_num";

    $datos_por_temporada[] = [
        'label' => $nombre_legible,
        'data' => $data_ordenada,
        'backgroundColor' => $color,
        'borderWidth' => 1
    ];
}

// Labels finales para Chart.js, alineados con $pj_order
$labels_finales = [];
$max_jugados = 0;
foreach ($pj_order as $pj_id) {
    $labels_finales[] = $labels[$pj_id];

    $total = 0;
    foreach ($temporadas_totales as $temp) {
        $total += count($apariciones[$pj_id][$temp] ?? []);
    }
    if ($total > $max_jugados) $max_jugados = $total;
}
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<h2><?php echo $pageSect; ?></h2>
<div class="bioTextData">
    <fieldset class='bioSeccion'>
        <legend>&nbsp;Participación segmentada por temporada&nbsp;</legend>
        <canvas id="stackedChart" width="500" height="600"></canvas>
    </fieldset>
</div>

<script>
const ctx = document.getElementById('stackedChart').getContext('2d');
const chart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($labels_finales) ?>,
        datasets: <?= json_encode($datos_por_temporada) ?>
    },
    options: {
        indexAxis: 'y',
        plugins: {
            tooltip: {
                mode: 'index',
                intersect: false,
                callbacks: {
                    label: function(ctx) {
						const datasetLabel = ctx.dataset.label;
						const personaje = ctx.chart.data.labels[ctx.dataIndex]; // 🔑 aquí accedes al nombre correcto
						const valor = ctx.raw;
						return `${datasetLabel}: ${valor}`;
					}
                },
				interaction: {
					mode: 'nearest',
					axis: 'y',
					intersect: false
				}
            },
            legend: {
                position: 'top',
                labels: { color: 'white' }
            }
        },
        responsive: true,
        scales: {
            x: {
                stacked: true,
                beginAtZero: true,
                max: <?= $max_jugados ?>,
                ticks: { color: 'white', stepSize: 1 }
            },
            y: {
                stacked: true,
                ticks: { color: 'white' }
            }
        }
    }
});
</script>
