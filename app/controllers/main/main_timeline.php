<?php setMetaFromPage("Línea temporal | Heaven's Gate", "Línea temporal de eventos y sucesos.", null, 'website'); ?>
<?php
// Validamos conexión
if (!$link) {
    die("Error de conexión a la base de datos.");
}
$eventos = [];
$res = $link->query("SELECT id, event_date, title, description, kind, source, timeline FROM fact_timeline_events ORDER BY event_date ASC");
while ($row = $res->fetch_assoc()) {
    $fecha = $row['event_date'];

    // Si quieres falsificar fechas A.C., aquí puedes hacerlo:
    if ((int)substr($fecha, 0, 1) === '-') {
        // mapa ficticio
        $fecha = '0100-01-01';
    }
	
	$icon = '🌀'; // por defecto
	switch ($row['kind']) {
		case 'catastrofe':     $icon = '🔥'; break;
		case 'batalla':        $icon = 'âš”ï¸'; break;
		case 'nacimiento':     $icon = '🟢'; break;
		case 'muerte':         $icon = 'âš°ï¸'; break;
		case 'descubrimiento': $icon = '🔍'; break;
		case 'traicion':       $icon = '🩸'; break;
		case 'romance':        $icon = '💖'; break;
		case 'fundacion':      $icon = '🏛️'; break;
		case 'alianza':        $icon = '🤝'; break;
		case 'enemistad':      $icon = 'â˜ ï¸'; break;
		case 'reclutamiento':  $icon = '🧭'; break; // brújula, como símbolo de inicio de camino
		case 'otros':          $icon = '📌'; break;
	}

	$descripcion = nl2br(htmlspecialchars($row['description']));
	$fuente = htmlspecialchars($row['source']);
	$linea = htmlspecialchars($row['timeline']);
	$eventoTipo = htmlspecialchars(ucfirst($row['kind']));

	$eventos[] = [
		'id' => $row['id'],
		'content' => '<span class="evento-tooltip" ' .
			'data-id="' . htmlspecialchars($row['kind']) . htmlspecialchars($row['title']) . '"'.
			'data-fuente="' . $fuente . '" ' .
			'data-linea="' . $linea . '" ' .
			'data-tipo="' . $eventoTipo . '" ' .
			'data-desc="' . htmlspecialchars($row['description']) . '">' .
			$icon . ' ' . htmlspecialchars($row['title']) .
		'</span>',
		'className' => htmlspecialchars($row['kind']),
		'start' => $fecha,
		'linea' => $linea,
		'fuente' => $fuente,
		'eventotipo' => $eventoTipo,
		'zoom' => 'año' // o 'mes', 'detallado'
	];

}

/* 
	--AND p.kes = 'pj'
	--AND p.jugador > 0
*/

// Añadir eventos de nacimiento desde fact_characters
$res2 = $link->query("SELECT p.id, p.name, p.birthdate_text, nc.name AS nombre_cronica, p.character_kind, p.player_id 
FROM fact_characters p
LEFT JOIN dim_chronicles nc ON nc.id = p.chronicle_id 
WHERE 1=1

	AND p.chronicle_id NOT IN (2,6)
	AND p.birthdate_text REGEXP '^[0-9]{2}/[0-9]{2}/[0-9]{4}$';"
/*"
    SELECT p.id, p.nombre, p.cumple, nc.name AS nombre_cronica
    FROM fact_characters p
    LEFT JOIN dim_chronicles nc ON nc.id = p.chronicle_id 
    WHERE p.cronica NOT IN (2,6)
      AND p.birthdate_text REGEXP '^[0-9]{2}/[0-9]{2}/[0-9]{4}$';
"*/
);

while ($row = $res2->fetch_assoc()) {
    $fechaObj = DateTime::createFromFormat('d/m/Y', $row['birthdate_text']);
    if (!$fechaObj) continue; // seguridad por si acaso
    $fechaISO = $fechaObj->format('Y-m-d');

    $titulo = 'Nacimiento de ' . htmlspecialchars($row['name']);
    $fuente = 'Lista de personajes';
    $linea  = htmlspecialchars($row['nombre_cronica']);
    $icono  = '🟢';
	$eventoTipo = 'Nacimiento';

    $contenido = '<span class="evento-tooltip" ' .
		'data-id="' . $eventoTipo . $titulo . '"'.
        'data-fuente="' . htmlspecialchars($fuente) . '" ' .
        'data-linea="' . $linea . '" ' .
		'data-tipo="' . $eventoTipo . '" ' .
        'data-desc="' . htmlspecialchars($row['name']) . ' nació en esta fecha (' . $fechaISO .').">' .
        $icono . ' ' . $titulo .
    '</span>';

    $eventos[] = [
        'id' => 'pj-' . $row['id'],
        'content' => $contenido,
        'className' => 'nacimiento',
        'start' => $fechaISO,
		'linea' => $linea,
		'fuente' => $fuente,
		'zoom' => 'año' // o 'mes', 'detallado'
    ];
}

$fechas = array_column($eventos, 'start');
sort($fechas);
$start = $fechas[0];
$end   = end($fechas);

// Convertir a objetos DateTime para manipular años
$start_dt = new DateTime($start);
$end_dt   = new DateTime($end);

// Restar y sumar 10 años
$start_dt->modify('-10 years');
$end_dt->modify('+5 years');

// Exportar en formato compatible con JS
$start = $start_dt->format('Y-m-d');
$end   = $end_dt->format('Y-m-d');

// Agrupación automática por década para crear resúmenes visibles solo en zoom amplio
$eventosPorDecada = [];

foreach ($eventos as $ev) {
	if (!isset($ev['start'])) continue;
	$ano = (int)substr($ev['start'], 0, 4);
	if ($ano < 1000) continue; // Ignorar fechas artificiales o A.C.

	$decada = floor($ano / 10) * 10;

	if (!isset($eventosPorDecada[$decada])) {
		$eventosPorDecada[$decada] = 0;
	}
	$eventosPorDecada[$decada]++;
}

/*
foreach ($eventosPorDecada as $decada => $cantidad) {
	$eventos[] = [
		'id' => 'resumen-' . $decada,
		'content' => '<span class="evento-tooltip" ' .
			'data-id="resumen-' . $decada . '" ' .
			'data-fuente="Sistema" ' .
			'data-linea="Resumen" ' .
			'data-tipo="Resumen" ' .
			'data-desc="Hay ' . $cantidad . ' eventos registrados en esta década. Usa el zoom para explorarlos.">' .
			'📅 Eventos en los ' . $decada . '</span>',
		'className' => 'resumen',
		'start' => $decada . '-01-01',
		'zoom' => 'época' // 👈 solo visible cuando el zoom es amplio
	];
}*/

?>

<script src="/assets/vendor/vis/vis-timeline-graph2d.min.js"></script>

<link href="/assets/vendor/vis/vis-timeline-graph2d.min.css" rel="stylesheet" />
<link rel="stylesheet" href="/assets/css/hg-main.css">
<?php include_once("app/partials/datatable_assets.php"); ?>

<h2 class="main-right-title">Línea temporal</h2>

<!--<p>Página aún en construcción.</p>

<div class="main-search-center">
	<button id="btn-fullscreen" class="boton2">â›¶ Pantalla completa</button>
</div>

<div id="timeline-container">
	<div id="timeline"></div>
</div>

<div id="timeline-tooltip"></div> -->

<script>
	/* ------------------------ */
	/* JS para el Timeline      */
	/* ------------------------ */
	function getZoomLevel(start, end) {
		const range = end - start;
		const oneDay = 1000 * 60 * 60 * 24;
		const days = range / oneDay;

		if (days > 365 * 20) return 'época';
		if (days > 365 * 5) return 'año';
		if (days > 180) return 'mes';
		return 'detallado';
	}

	function filtrarItemsPorZoom(nivel) {
		return rawItems.filter(item => {
			switch (item.zoom) {
				case 'detallado': return nivel === 'detallado';
				case 'mes': return nivel === 'mes' || nivel === 'detallado';
				case 'año': return ['año', 'mes', 'detallado'].includes(nivel);
				case 'época': return nivel === 'época';
				default: return true;
			}
		});
	}

	function updateVisibleItems(start, end) {
		const zoom = getZoomLevel(start, end);
		const filtered = allItems.filter(item => {
			if (!item.zoom) return true;
			if (item.zoom === 'época') return zoom === 'época';
			if (item.zoom === 'año') return ['año', 'mes', 'detallado'].includes(zoom);
			if (item.zoom === 'mes') return ['mes', 'detallado'].includes(zoom);
			if (item.zoom === 'detallado') return zoom === 'detallado';
			return false;
		});
		timeline.setItems(filtered);
	}

	const rawItems = <?= json_encode($eventos) ?>;
	const allItems = new vis.DataSet(rawItems);
	
	/* ------------------------------ */
	/* Rellenar Tabla de eventos      */
	/* ------------------------------ */
	$(document).ready(function () {
		const tablaBody = document.querySelector('#tabla-eventos tbody');

		rawItems.forEach(item => {
			const div = document.createElement('div');
			div.innerHTML = item.content;
			const icono = div.textContent.trim().substring(0, 2);
			const titulo = div.textContent.trim().substring(2).trim();

			const fila = document.createElement('tr');
			fila.innerHTML = `
				<td>${titulo}</td>
				<td>${item.start}</td>
				<td>${item.eventotipo || 'Nacimiento'}</td>
				<td>${item.linea || '-'}</td>
				<td>${item.fuente || '-'}</td>
			`;

			fila.addEventListener('click', () => {
				timeline.focus(item.id, { animation: true });
			});

			tablaBody.appendChild(fila);
		});

		$('#tabla-eventos').DataTable({
			pageLength: 10,
			lengthMenu: [10, 25, 50, 100],
			order: [[1, "asc"]],
			language: {
				search: "&#128269; Buscar:&nbsp;",
				lengthMenu: "Mostrar _MENU_ eventos",
				info: "Mostrando _START_ a _END_ de _TOTAL_ eventos",
				infoEmpty: "No hay eventos disponibles",
				emptyTable: "No hay datos en la tabla",
				paginate: {
					first: "Primero",
					last: "&Uacute;ltimo",
					next: "&#9654;",
					previous: "&#9664;"
				}
			}
		});
	});

	const container = document.getElementById('timeline');
	const dataset = new vis.DataSet(); // inicialmente vacío

	const timeline = new vis.Timeline(container, dataset, {
		start: new Date('<?= $start ?>'),
		end: new Date('<?= $end ?>'),
		min: new Date('<?= $start ?>'),
		max: new Date('<?= $end ?>'),
		stack: true,
		locale: 'es',
		editable: false,
		margin: { item: 10, axis: 200 },
		orientation: 'bottom',
		type: 'box',
		template: item => {
			const div = document.createElement('div');
			div.innerHTML = item.content;
			return div;
		}
	});

	// Inicializa según zoom inicial
	const initialRange = timeline.getWindow();
	const initialZoom = getZoomLevel(initialRange.start, initialRange.end);
	dataset.clear();
	dataset.add(filtrarItemsPorZoom(initialZoom));
	
	document.getElementById('btn-fullscreen').addEventListener('click', () => {
		const cont = document.getElementById('timeline-container');
		if (document.fullscreenElement) {
			document.exitFullscreen();
		} else {
			cont.requestFullscreen();
		}
	});
	
	document.addEventListener('fullscreenchange', () => {
		timeline.redraw();
	});

	// Actualiza al hacer zoom
	timeline.on('rangechange', function (props) {
		const zoomLevel = getZoomLevel(props.start, props.end);
		dataset.clear();
		dataset.add(filtrarItemsPorZoom(zoomLevel));
	});
		
	document.addEventListener('mousemove', function(e) {
		const tooltip = document.getElementById('timeline-tooltip');
		const target = e.target.closest('.evento-tooltip');

		if (target) {
			const fuente = target.dataset.fuente;
			const linea = target.dataset.linea;
			const tipo = target.dataset.tipo;
			const desc = target.dataset.description;

			tooltip.innerHTML = `
				<div class='tooltip-timeline-event-container'>
				<div class='tooltip-timeline-event-row'><div class='tooltip-timeline-event-icon'>🔷</div> <div class='tooltip-timeline-event-label'>Tipo:</div> ${tipo}</div>
				<div class='tooltip-timeline-event-row'><div class='tooltip-timeline-event-icon'>📄</div> <div class='tooltip-timeline-event-label'>Fuente:</div> ${fuente}</div>
				<div class='tooltip-timeline-event-row'><div class='tooltip-timeline-event-icon'>🧭</div> <div class='tooltip-timeline-event-label'>Línea:</div> ${linea}</div>
				</div>
				<div class='tooltip-timeline-event-description'>
					${desc.replace(/\n/g, "<br>")}
				</div>
			`;
			tooltip.style.display = 'block';
			tooltip.style.left = (e.pageX + 15) + 'px';
			tooltip.style.top = (e.pageY - 10) + 'px';
		} else {
			tooltip.style.display = 'none';
		}
	});

</script>

<br/>

<!-- <h3 class="main-right-title">📑 Lista completa de eventos</h3>-->
<table id="tabla-eventos" class="display">
	<thead>
		<tr>
			<th>Evento</th>
			<th>Fecha</th>
			<th>Tipo</th>
			<th>Línea</th>
			<th>Fuente</th>
		</tr>
	</thead>
	<tbody></tbody>
</table>






