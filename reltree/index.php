<?php
	include 'config.php';
	$characters = $pdo->query("SELECT p.id, p.nombre, p.img, p.estado, nc.name AS clan_name FROM pjs1 p LEFT JOIN nuevo2_clanes nc ON p.clan = nc.id WHERE p.cronica NOT IN (2, 7) ORDER BY 2 ASC")->fetchAll();
	$relations = $pdo->query("SELECT * FROM character_relations")->fetchAll();
	
	$clanSizes = [];
	foreach ($characters as $c) {
		if (!empty($c['clan_name'])) {
			$clanSizes[$c['clan_name']] = ($clanSizes[$c['clan_name']] ?? 0) + 1;
		}
	}
	
	$pageTitle = "Nebulosa de relaciones Heaven's Gate";
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <script type="text/javascript" src="https://unpkg.com/vis-network/standalone/umd/vis-network.min.js"></script>
<style>
    body {
        font-family: 'Segoe UI', sans-serif;
        background: #f9f7f6;
        color: #333;
        margin: 0;
        padding: 20px;
    }

    h2 {
        margin: 0 0 1em 0;
        font-weight: 600;
        color: #444;
    }

    button.admin-button {
        padding: 8px 12px;
        border: none;
        background: #3498db;
        color: #fff;
        font-size: 14px;
        border-radius: 6px;
        cursor: pointer;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        transition: background 0.2s ease;
    }

    button.admin-button:hover {
        background: #2b7cb8;
    }

    .clan-toggle {
        background: #fff;
        padding: 6px 10px;
        border-radius: 6px;
        box-shadow: 0 1px 4px rgba(0,0,0,0.05);
        transition: transform 0.2s ease;
    }

    .clan-toggle:hover {
        transform: scale(1.05);
    }

    .modal {
        border-radius: 10px;
        font-family: 'Segoe UI', sans-serif;
    }

    .modal input, .modal textarea, .modal select {
        font-size: 15px;
    }

    #relationInfo {
        background-color: #ffffff;
        color: #333;
        border-color: #ddd;
        border-radius: 8px;
        font-size: 15px;
    }

    #network {
        border-radius: 8px;
        border: 2px solid #e1e1e1;
        box-shadow: 0 3px 6px rgba(0,0,0,0.05);
        background-color: #ffffff;
		height: 70vh;
    }

    select {
        background: #fff;
        border: 1px solid #ccc;
        border-radius: 6px;
    }

    input, textarea {
        border: 1px solid #ccc;
        border-radius: 6px;
        padding: 6px;
        background: #fff;
    }
</style>

</head>
<body>
<?php session_start(); ?>
<h2 style="margin-left: 1em;"><?php echo $pageTitle; ?></h2>
<div id="network"></div>
<?php
	$clanColors = [];
	$stmt = $pdo->query("SELECT name, color FROM nuevo2_clanes GROUP BY 1, 2 ORDER BY orden ASC");
	while ($row = $stmt->fetch()) {
		$clanColors[$row['name']] = $row['color'] ?: '#eeeeee';
	}
?>

<div style="display: flex; justify-content: space-between; align-items: flex-start; margin: 20px 0; flex-wrap: wrap; gap: 40px;">
    <!-- Botones -->
    <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']) { ?>
    <div style="flex: 1; min-width: 220px;">
        <div style="display: flex; flex-direction: column; gap: 10px;">
			<button class="admin-button" onclick="location.href='admin_clanes.php'">⚙️ Administración</button>
            <button class="admin-button" onclick="openModal()">➕ Añadir relación</button>
            <!-- <button class="admin-button" onclick="openDeleteModal()">🗑️ Borrar relación</button>-->
        </div>
    </div>
    <?php } else {  ?>
    <div style="flex: 1; min-width: 220px;">
        <div style="display: flex; flex-direction: column; gap: 10px;">
			<button class="admin-button" onclick="location.href='admin_clanes.php'">⚙️ Conectar</button>
        </div>
    </div>
	<?php }; ?>
    <!-- Leyenda -->
    <div style="flex: 3; min-width: 300px;">
        <strong style="display: block; margin-bottom: 6px;">Leyenda</strong>
        <div id="legend" style="display: flex; flex-wrap: wrap; gap: 12px;">
            <?php foreach ($clanColors as $name => $color): ?>
                <div class="clan-toggle" data-clan="<?= htmlspecialchars($name) ?>" 
                     style="cursor: pointer; display: flex; align-items: center; gap: 6px;">
                    <div style="width: 18px; height: 18px; background-color: <?= $color ?>; border: 1px solid #444; border-radius: 4px;"></div>
                    <span style="font-size: 14px;"><?= htmlspecialchars($name) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div id="relationModal" style="display:none; position:fixed; top:20%; left:30%; width:40%; background:#fff; padding:20px; border:1px solid #ccc; z-index:1000;">
  <h3>Crear nueva relación</h3>
  <form id="relationForm">
    <label>Origen:</label>
    <select name="source_id" required>
      <?php foreach ($characters as $c): ?>
        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
      <?php endforeach; ?>
    </select><br><br>

    <label>Destino:</label>
    <select name="target_id" required>
      <?php foreach ($characters as $c): ?>
        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
      <?php endforeach; ?>
    </select><br><br>

	<label>Tipo de relación:</label>
	<select name="relation_type" id="relation_type_select" required>
		<option value="Amigo">Amigo</option>
		<option value="Aliado">Aliado</option>
		<option value="Mentor">Mentor</option>
		<option value="Protegido">Protegido</option>
		<option value="Salvador">Salvador</option>
		
		<option value="Amante">Amante</option>
		<option value="Pareja">Pareja</option>
	
		<option value="Rival">Rival</option>
		<option value="Traidor">Traidor</option>
		<option value="Extorsionador">Extorsionador</option>
		<option value="Enemigo">Enemigo</option>
		<option value="Asesino">Asesino</option>
		
		<option value="Padre">Padre</option>
		<option value="Madre">Madre</option>
		<option value="Hijo">Hijo</option>
		<option value="Hermano">Hermano</option>
		<option value="Abuelo">Abuelo</option>
		<option value="Tío">Tío</option>
		<option value="Primo">Primo</option>
		
		<option value="Superior">Superior</option>
		<option value="Subordinado">Subordinado</option>
		<option value="Amo">Amo</option>
		<option value="Creación">Creación</option>
		<option value="Vínculo">Vínculo</option>
	</select><br><br>

	<label>Categoría (tag):</label>
	<input type="text" name="tag" id="tag_input" value="amistad"><br><br>
	
	<label>Dirección de la relación (flechas):</label>
	<select name="arrows">
		<option value="to">➡️ Desde Origen hacia Destino</option>
		<option value="from">⬅️ Desde Destino hacia Origen</option>
		<option value="to,from">🔁 Doble dirección</option>
		<option value="">🚫 Sin flechas</option>
	</select><br><br>

    <label>Importancia (0–10):</label>
    <input type="number" name="importance" min="0" max="10" value="1"><br><br>

    <label>Descripción:</label><br>
    <textarea name="description" rows="3" cols="30"></textarea><br><br>

    <button type="submit">Guardar</button>
    <button type="button" onclick="closeModal()">Cancelar</button>
  </form>
</div>

<div id="deleteModal" style="display:none; position:fixed; top:25%; left:30%; width:40%; background:#fff; padding:20px; border:1px solid #ccc; z-index:1000;">
  <h3>Eliminar relación</h3>
  <form id="deleteForm">
    <label>Origen:</label>
    <select name="source_id" required>
      <?php foreach ($characters as $c): ?>
        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
      <?php endforeach; ?>
    </select><br><br>

    <label>Destino:</label>
    <select name="target_id" required>
      <?php foreach ($characters as $c): ?>
        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
      <?php endforeach; ?>
    </select><br><br>

    <button type="submit">Eliminar</button>
    <button type="button" onclick="closeDeleteModal()">Cancelar</button>
  </form>
</div>

<script>
const nodes = new vis.DataSet([
	// Construcción de nodos de personajes.
	<?php foreach ($characters as $c): ?>
		<?php
			$clan = $c['clan_name'] ?: 'Otros';
			$color = $clanColors[$clan] ?? '#eeeeee';
			$hasImage = !empty($c['img']);
			$isDead = $c['estado'] === 'Cadáver';
			$label = $c['nombre'] . ($isDead ? ' †' : '');
			$nodeColor = $isDead ? [
				'background' => '#888',
				'border' => '#555',
				'highlight' => ['background' => '#888', 'border' => '#000']
			] : null;

		?>
		{
			id: <?= $c['id'] ?>,
			label: <?= json_encode($label) ?>,
			shape: <?= $hasImage ? "'circularImage'" : "'dot'" ?>,
			image: <?= $hasImage ? json_encode("../" . $c['img']) : "null" ?>,
			size: 25,
			group: <?= json_encode($clan) ?>,
			<?= $isDead ? "color: " . json_encode($nodeColor) . "," : "" ?>
		},
		<?php endforeach; ?>
	// Construcción de nodos de clanes.
	// Cada clan aparece como un nodo independiente, con todos los personajes que contiene
	// unidos a él.
		<?php foreach ($clanColors as $name => $color): ?>
		{
			id: <?= json_encode("clan_" . $name) ?>,
			label: <?= json_encode($name) ?>,
			shape: "box",
			font: { color: "#333", size: 18, face: "arial" },
			size: 40,
			color: {
				background: "<?= $color ?>",
				border: "#333",
				highlight: {
					background: "<?= $color ?>",
					border: "#000"
				}
			}
		},
	<?php endforeach; ?>
]);

const originalNodeColors = {};
nodes.forEach(node => {
	originalNodeColors[node.id] = nodes.get(node.id).color || null;
});

const edges = new vis.DataSet([
	<?php foreach ($relations as $r): ?>
		<?php
			$color = '#bdc3c7';
			switch ($r['tag']) {
				case 'conflicto': $color = '#e74c3c'; break;
				case 'amistad':   $color = '#2ecc71'; break;
				case 'alianza':   $color = '#3498db'; break;
				case 'familia':   $color = '#f1c40f'; break;
			}
		?>
		{
			from: <?= $r['source_id'] ?>,
			to: <?= $r['target_id'] ?>,
			label: "<?= addslashes($r['relation_type']) ?>",
			width: <?= max(1, intval($r['importance'])) ?>,
			arrows: <?= json_encode($r['arrows'] ?: 'to') ?>,
			color: { color: "<?= $color ?>" },
			font: { size: 0 }
		},
	<?php endforeach; ?>
]);

<?php foreach ($characters as $c): ?>
	<?php if (!empty($c['clan_name'])): ?>
		edges.add({
			from: <?= json_encode("clan_" . $c['clan_name']) ?>,
			to: <?= $c['id'] ?>,
			arrows: '',
			width: 1,
			color: { color: '#999' },
			dashes: true
		});
	<?php endif; ?>
<?php endforeach; ?>

const container = document.getElementById('network');
const data = { nodes: nodes, edges: edges };
const options = {
    layout: { improvedLayout: true },
	physics: {
	  solver: "barnesHut",
	  stabilization: {
		enabled: true,
		iterations: 300, // Puedes ajustarlo: 200–1000 suele ir bien
		updateInterval: 25,
		onlyDynamicEdges: false,
		fit: true
	  },
	  barnesHut: {
		gravitationalConstant: -<?= count($characters) > 80 ? '12000' : '18000' ?>,
		springLength: <?= count($characters) > 80 ? '160' : '120' ?>,
		springConstant: 0.01,
		avoidOverlap: 0.3
	  }
	},
    groups: {
        <?php foreach ($clanColors as $name => $color): ?>
        <?= json_encode($name) ?>: {
            color: {
                background: "<?= $color ?>",
                border: "<?= $color ?>",
                highlight: {
                    background: "<?= $color ?>",
                    border: "#000"
                }
            },
            borderWidth: 6
        },
        <?php endforeach; ?>
    }
};

const nodeOptions = {
	<?php foreach ($clanSizes as $clan => $count): ?>
		<?= json_encode("clan_" . $clan) ?>: {
			physics: true,
			mass: <?= $count >= 25 ? 5 : 1.5 ?>,	
			font: { size: 18 },
		},
	<?php endforeach; ?>
};

const network = new vis.Network(container, data, options);

nodes.forEach(function(node) {
  if (String(node.id).startsWith("clan_")) {
    const clanName = node.label;
    const size = <?= json_encode($clanSizes) ?>;
    if (size[clanName] && size[clanName] >= 25) {
      nodes.update({
        id: node.id,
        mass: 5,
      });
    }
  }
});

network.on("click", function (params) {
    if (params.nodes.length === 1) {
        const nodeId = params.nodes[0];
        const position = network.getPositions([nodeId])[nodeId];
        network.moveTo({
            position: position,
            scale: 1.5, // o más, según lo dramático que quieras el zoom
            animation: {
                duration: 500,
                easingFunction: "easeInOutQuad"
            }
        });
    }
});

let pressTimer;
let pressedNode = null;

network.on("oncontext", function (params) {
	// Esto previene el menú contextual en escritorio
	params.event.preventDefault();
});

network.on("selectNode", function(params) {
    const selectedNodeId = params.nodes[0];
    const connectedNodeIds = network.getConnectedNodes(selectedNodeId);
    connectedNodeIds.push(selectedNodeId); // incluir el nodo seleccionado

    const updates = [];

    nodes.forEach(node => {
        const isVisible = connectedNodeIds.includes(node.id);
        if (isVisible) {
            updates.push({
                id: node.id,
                color: originalNodeColors[node.id] || undefined,
                font: { color: "#000" }
            });
        } else {
            updates.push({
                id: node.id,
                color: { background: "#eeeeee", border: "#cccccc" },
                font: { color: "#aaa" }
            });
        }
    });

    nodes.update(updates);
});

network.on("deselectNode", function() {
    const restore = [];
    nodes.forEach(node => {
        restore.push({
            id: node.id,
            color: originalNodeColors[node.id] || undefined,
            font: { color: "#000" }
        });
    });
    nodes.update(restore);
});

network.on("doubleClick", function (params) {
	if (params.nodes.length === 1) {
		const nodeId = params.nodes[0];
		pressedNode = nodeId;
		pressTimer = setTimeout(() => {
			//window.open(`../index.php?p=muestrabio&b=${nodeId}`, '_blank');
			window.open(`view_character_relationship.php?id=${nodeId}`, '_blank');
		}, 600); // 600ms hold
	}
});

network.on("selectEdge", function (params) {
    if (params.edges.length === 1) {
        const edgeId = params.edges[0];
        const edge = edges.get(edgeId);
        const label = edge.label || "";
        const infoBox = document.getElementById("relationInfo");
        infoBox.textContent = `${label}`;
        infoBox.style.display = "block";
    }
});

network.on("deselectEdge", function () {
    document.getElementById("relationInfo").style.display = "none";
});


/* network.once('stabilizationIterationsDone', function () {
  network.setOptions({ physics: false });
  network.fit({ animation: { duration: 500, easingFunction: 'easeInOutQuad' } });
}); */

const hiddenClans = new Set();

document.querySelectorAll('.clan-toggle').forEach(el => {
    el.addEventListener('click', () => {
        const clan = el.dataset.clan;

        if (hiddenClans.has(clan)) {
            // Mostrar nodos del clan
            hiddenClans.delete(clan);
            const toShow = nodes.get({ filter: (n) => n.group === clan });
            nodes.update(toShow.map(n => ({ ...n, hidden: false })));
            el.style.opacity = 1;
            el.style.textDecoration = "none";
        } else {
            // Ocultar nodos del clan
            hiddenClans.add(clan);
            const toHide = nodes.get({ filter: (n) => n.group === clan });
            nodes.update(toHide.map(n => ({ ...n, hidden: true })));
            el.style.opacity = 0.4;
            el.style.textDecoration = "line-through";
        }
    });
});

</script>

<script>
function openModal() {
    document.getElementById('relationModal').style.display = 'block';
}
function closeModal() {
    document.getElementById('relationModal').style.display = 'none';
}
function openDeleteModal() {
    document.getElementById('deleteModal').style.display = 'block';
}
function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}

// Enviar relación nueva
document.getElementById('relationForm').addEventListener('submit', function(e) {
    e.preventDefault();
    fetch('add_relation_ajax.php', {
        method: 'POST',
        body: new FormData(this)
    })
    .then(res => res.text())
    .then(result => {
        alert(result);
        closeModal();
        location.reload(); // O actualizar el grafo dinámicamente
    });
});

// Eliminar relación
document.getElementById('deleteForm').addEventListener('submit', function(e) {
    e.preventDefault();
    fetch('delete_relation_ajax.php', {
        method: 'POST',
        body: new FormData(this)
    })
    .then(res => res.text())
    .then(result => {
        alert(result);
        closeDeleteModal();
        location.reload();
    });
});
</script>

<script>
	document.getElementById("relation_type_select").addEventListener("change", function () {
	  const value = this.value.toLowerCase();
	  const tagField = document.getElementById("tag_input");
	  const arrowsField = document.querySelector("select[name='arrows']");

	  let tag = "";
	  let arrow = "to"; // valor por defecto

	  if (["amigo", "aliado", "mentor", "protegido", "salvador", "pareja", "amante"].includes(value)) {
		tag = "amistad";
	  } else if (["enemigo", "traidor", "rival", "asesino", "extorsionador"].includes(value)) {
		tag = "conflicto";
	  } else if (["superior", "subordinado", "amo", "creacion", "vinculo"].includes(value)) {
		tag = "alianza";
	  } else if (["padre", "madre", "hijo", "abuelo", "tío", "primo", "hermano"].includes(value)) {
		tag = "familia";
	  }

	  // Dirección sugerida de flechas
	  switch (value) {
		case "padre":
		case "madre":
		case "abuelo":
		case "tío":
		case "primo":
		case "superior":
		case "amo":
		case "creacion":
		case "salvador":
		  arrow = "to"; break; // origen → destino

		case "hijo":
		case "subordinado":
		case "protegido":
		  arrow = "from"; break; // destino → origen

		case "pareja":
		case "amante":
		case "hermano":
		case "aliado":
		  arrow = "to,from"; break; // bidireccional

		default:
		  arrow = "to";
	  }

	  tagField.value = tag;
	  arrowsField.value = arrow;
	});
</script>

<div id="relationInfo" style="display:none; position:fixed; top:10px; left:50%; transform:translateX(-50%);
     background-color:#fff; border:1px solid #ccc; border-radius:6px; padding:8px 14px; font-family:sans-serif;
     font-size:14px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); z-index:2000;">
</div>

</body>
</html>
