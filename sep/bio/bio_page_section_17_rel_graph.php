<?php
/* bio_page_section_17_rel_graph.php
   Mini grafo de relaciones para la página de biografía */

/* RelGraph */ 
	$idGetData = $_GET['b'] ?? null;

	if (!$idGetData || !$link) {
		echo "<div style='padding:1em;'>Error: Datos insuficientes para mostrar la gráfica.</div>";
		return;
	}

	// Obtener datos del personaje principal
	$stmtChar = $link->prepare("SELECT id, nombre, img, estado FROM pjs1 WHERE id = ? LIMIT 1");
	$stmtChar->bind_param('s', $idGetData);
	$stmtChar->execute();
	$mainChar = $stmtChar->get_result()->fetch_assoc();
	$stmtChar->close();

	$id = intval($_GET['b'] ?? 0);

	// Obtener datos del personaje
	$queryPJ = "SELECT id, nombre, img, estado FROM pjs1 WHERE id = ?";
	$stmt = $link->prepare($queryPJ);
	$stmt->bind_param('i', $id);
	$stmt->execute();
	$pjResult = $stmt->get_result();
	$pj = $pjResult->fetch_assoc();
	$stmt->close();

	if (!$pj) {
		echo "<div style='color:red;'>Personaje no encontrado.</div>";
		return;
	}

	// Obtener relaciones
	$queryRel = "SELECT * FROM character_relations WHERE source_id = ? OR target_id = ?";
	$stmt = $link->prepare($queryRel);
	$stmt->bind_param('ii', $id, $id);
	$stmt->execute();
	$relResult = $stmt->get_result();
	$rels = $relResult->fetch_all(MYSQLI_ASSOC);
	$stmt->close();

	// Recolectar todos los IDs relacionados
	$relatedIds = [$id];
	foreach ($rels as $r) {
		$relatedIds[] = $r['source_id'];
		$relatedIds[] = $r['target_id'];
	}
	$relatedIds = array_unique($relatedIds);

	// Consulta para obtener datos de todos los implicados
	$placeholders = implode(',', array_fill(0, count($relatedIds), '?'));
	$types = str_repeat('i', count($relatedIds));
	$stmt = $link->prepare("SELECT id, nombre, img, estado FROM pjs1 WHERE id IN ($placeholders)");
	$stmt->bind_param($types, ...$relatedIds);
	$stmt->execute();
	$charResult = $stmt->get_result();
	$characters = [];
	while ($row = $charResult->fetch_assoc()) {
		$characters[$row['id']] = $row;
	}
	$stmt->close();
?>

<div style="position:relative; width:100%; max-width:600px; height:600px; overflow:hidden; border-radius:10px; background:#05014E;">
    <div id="mini-network" style="width:100%; height:100%;"></div>
</div>

<script type="text/javascript" src="https://unpkg.com/vis-network/standalone/umd/vis-network.min.js"></script>
<script>
	const nodes = new vis.DataSet([
		{
			id: <?= json_encode($idGetData) ?>,
			label: <?= json_encode($bioName) ?>,
			shape: 'circularImage',
			image: <?= json_encode($bioPhoto) ?>,
			size: 24,
			font: { color: "#fff", size: 16 }
		},
		<?php
		$addedNodes = [$idGetData];
		foreach ($relaciones as $r):
			$relatedId = $r['direction'] === 'outgoing' ? $r['target_id'] : $r['source_id'];
			if (in_array($relatedId, $addedNodes)) continue;
			$addedNodes[] = $relatedId;
			$nombre = $r['nombre'];
			$img = $r['img'] ?: 'img/default.jpg';
		?>
		{
			id: <?= json_encode($relatedId) ?>,
			label: <?= json_encode($nombre) ?>,
			shape: 'circularImage',
			image: <?= json_encode($img) ?>,
			size: 20,
			font: { color: "#fff", size: 12 }
		},
		<?php endforeach; ?>
	]);

	const edges = new vis.DataSet([
		<?php foreach ($relaciones as $r):
			$from = $r['direction'] === 'outgoing' ? $idGetData : $r['source_id'];
			$to   = $r['direction'] === 'outgoing' ? $r['target_id'] : $idGetData;

			$color = '#bdc3c7';
			switch (strtolower($r['tag'])) {
				case 'conflicto': $color = '#e74c3c'; break;
				case 'amistad':   $color = '#2ecc71'; break;
				case 'alianza':   $color = '#3498db'; break;
				case 'familia':   $color = '#f1c40f'; break;
			}
		?>
		{
			from: <?= json_encode($from) ?>,
			to: <?= json_encode($to) ?>,
			arrows: <?= json_encode($r['arrows']) ?>,
			label: <?= json_encode($r['relation_type']) ?>,
			color: { color: "<?= $color ?>" },
			font: {
			  align: 'middle',
			  color: "#fff",
			  size: 10,
			  strokeWidth: 3,
			  strokeColor: "#000"
			}

		},
		<?php endforeach; ?>
	]);

	const container = document.getElementById("mini-network");
	const data = { nodes, edges };
	const options = {
		layout: { improvedLayout: true },
		nodes: {
			borderWidth: 2,
			shadow: true,
			shape: 'circularImage',
			size: 20,
			font: {
				color: '#fff',
				size: 12,
				strokeWidth: 3,
				strokeColor: '#000'
			},
			widthConstraint: {
				minimum: 60
			},
			scaling: {
				label: {
					enabled: true,
					min: 12,
					max: 12
				}
			}
		},
		edges: {
			smooth: {
				type: "dynamic",
				roundness: 0.3
			},
			arrowStrikethrough: false
		},
		physics: {
			enabled: true,
			solver: "forceAtlas2Based",
			forceAtlas2Based: {
				gravitationalConstant: -50,
				springLength: 120,
				springConstant: 0.02
			},
			minVelocity: 0.5
		}
	};

	const network = new vis.Network(container, data, options);

	// Abrir biografía con doble clic si no es el nodo principal
	network.on("doubleClick", function (params) {
		if (params.nodes.length === 1) {
			const nodeId = params.nodes[0];
			if (String(nodeId) !== <?= json_encode((string)$idGetData) ?>) {
				window.open("?p=muestrabio&b=" + nodeId, "_blank");
			}
		}
	});

</script>

