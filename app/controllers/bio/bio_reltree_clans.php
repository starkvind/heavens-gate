<?php
setMetaFromPage("Nebulosa de clanes | Heaven's Gate", "Mapa de relaciones entre clanes y organizaciones.", null, 'website');
	if (!$link) {
		die("Error de conexión a la base de datos: " . mysqli_connect_error());
	}

	// Helper: sanitiza lista tipo "1,2, 3" -> "1,2,3" (solo ints). Si queda vacío, devuelve ""
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

	$pageTitle2 = "Clanes y Manadas";

	// Excluir crónicas (si existe la variable global, la usamos; si no, no excluimos nada)
	$excludeChronicles = isset($excludeChronicles) ? sanitize_int_csv($excludeChronicles) : '';
	$cronicaNotInSQL = ($excludeChronicles !== '') ? " WHERE p.cronica NOT IN ($excludeChronicles) " : "";

	// ============================================================
	// ✅ BRIDGES
	// - Clan de PJ: bridge_characters_organizations (activo)
	// - Manada de PJ: bridge_characters_groups (activo)
	// - Clan <-> Manada: bridge_organizations_groups (activo)
	// ============================================================

	// Obtener personajes + su clan/manada desde bridge (evita usar fact_characters.clan / fact_characters.manada)
	$personajes = [];
	$sqlPjs = "
		SELECT
			p.id,
			p.nombre,
			p.img,
			cbc.clan_id,
			gbc.group_id AS manada_id
		FROM fact_characters p
			LEFT JOIN (
				SELECT character_id, MIN(clan_id) AS clan_id
				FROM bridge_characters_organizations
				WHERE (is_active = 1 OR is_active IS NULL)
				GROUP BY character_id
			) cbc ON cbc.character_id = p.id
			LEFT JOIN (
				SELECT character_id, MIN(group_id) AS group_id
				FROM bridge_characters_groups
				WHERE (is_active = 1 OR is_active IS NULL)
				GROUP BY character_id
			) gbc ON gbc.character_id = p.id
		$cronicaNotInSQL
	";
	$result = $link->query($sqlPjs);
	if ($result) {
		while ($row = $result->fetch_assoc()) {
			// normalizamos a int donde toca
			$row['clan_id']   = isset($row['clan_id']) ? (int)$row['clan_id'] : 0;
			$row['manada_id'] = isset($row['manada_id']) ? (int)$row['manada_id'] : 0;
			$personajes[] = $row;
		}
		$result->free();
	}

	// Contar referencias usadas (desde los IDs bridge)
	$manadasUsadas = [];
	$clanesUsados  = [];
	foreach ($personajes as $p) {
		if (!empty($p['manada_id'])) $manadasUsadas[(int)$p['manada_id']] = true;
		if (!empty($p['clan_id']))   $clanesUsados[(int)$p['clan_id']]   = true;
	}

	// Obtener clanes usados
	$clanes = [];
	if ($clanesUsados) {
		$in = implode(',', array_map('intval', array_keys($clanesUsados)));
		$result = $link->query("SELECT id, name FROM dim_organizations WHERE id IN ($in)");
		if ($result) {
			while ($row = $result->fetch_assoc()) {
				$clanes[(int)$row['id']] = $row['name'];
			}
			$result->free();
		}
	}

	// Obtener manadas usadas
	$manadas = [];
	if ($manadasUsadas) {
		$in = implode(',', array_map('intval', array_keys($manadasUsadas)));
		$result = $link->query("SELECT id, name FROM dim_groups WHERE id IN ($in)");
		if ($result) {
			while ($row = $result->fetch_assoc()) {
				$manadas[(int)$row['id']] = $row['name'];
			}
			$result->free();
		}
	}

	// Relaciones Clan -> Manada desde el bridge clan_group (mejor que inferirlo de pjs)
	$clanManada = [];
	if ($clanesUsados && $manadasUsadas) {
		$inClanes  = implode(',', array_map('intval', array_keys($clanesUsados)));
		$inManadas = implode(',', array_map('intval', array_keys($manadasUsadas)));

		$sqlCM = "
			SELECT clan_id, group_id
			FROM bridge_organizations_groups
			WHERE clan_id IN ($inClanes)
			  AND group_id IN ($inManadas)
			  AND (is_active = 1 OR is_active IS NULL)
		";
		$resCM = $link->query($sqlCM);
		if ($resCM) {
			while ($r = $resCM->fetch_assoc()) {
				$c = (int)$r['clan_id'];
				$m = (int)$r['group_id'];
				$key = $c . '-' . $m;
				$clanManada[$key] = ['clan' => $c, 'manada' => $m];
			}
			$resCM->free();
		}
	}
?>

<script type="text/javascript" src="assets/vendor/vis/vis-network.min.10.0.2.js"></script>

<style>
    #relationInfo {
        background-color: #ffffff;
        color: #333;
        border-color: #ddd;
        border-radius: 8px;
        font-size: 15px;
    }

    #network {
        box-shadow: 0 3px 6px rgba(0,0,0,0.05);
		height: 70vh;
		width: 100%;
		border: none;
		background-color: #05014E;
    }

	#fullscreen-btn {
		position: relative;
		z-index: 1000;
		cursor: pointer;
		float: right;
	}
</style>

<h2>Nebulosa de relaciones</h2>

<div class="bioTextData">
    <fieldset class='bioSeccion'>
	<legend>&nbsp;Relaciones entre clanes y manadas&nbsp;</legend>
		<div style="float: right;">
			<button class="boton2" id='fullscreen-btn' onclick="toggleFullScreen()">🔍 Pantalla completa</button>
			<button class="boton2" onclick="location.href='/relationship-map/characters'">⚙️ Cambiar vista</button>
			<button class="boton2" id="btnDetenerFisica" onclick="detenerFisica()">🛑 Detener física</button>
			<button class="boton2" id="btnActivarFisica" onclick="activarFisica()" style="display:none;">🔄 Activar física</button>
		</div>
        <div style="position:relative; width:100%; max-width:600px; height:600px; overflow:hidden; border-radius:10px; background:#05014E;">
			<div id="network" style="width:100%; height:100%;"></div>
		</div>
    </fieldset>
</div>

<script>
	function toggleFullScreen() {
		const el = document.getElementById('network');
		if (!document.fullscreenElement) {
			el.requestFullscreen();
		} else {
			document.exitFullscreen();
		}
	}

	const nodes = new vis.DataSet([
		<?php foreach ($clanes as $id => $name): ?>
		{ id: "clan_<?= (int)$id ?>", label: <?= json_encode("" . $name, JSON_UNESCAPED_UNICODE) ?>, shape: 'box', color: '#f39c12', link: '/organizations/<?= (int)$id ?>' },
		<?php endforeach; ?>

		<?php foreach ($manadas as $id => $name): ?>
		{ id: "manada_<?= (int)$id ?>", label: <?= json_encode("" . $name, JSON_UNESCAPED_UNICODE) ?>, shape: 'ellipse', color: '#2980b9', link: '/groups/<?= (int)$id ?>' },
		<?php endforeach; ?>

		<?php foreach ($personajes as $p): ?>
		{
			id: "pj_<?= (int)$p['id'] ?>",
			label: <?= json_encode($p['nombre'], JSON_UNESCAPED_UNICODE) ?>,
			shape: <?= !empty($p['img']) ? "'circularImage'" : "'dot'" ?>,
			image: <?= !empty($p['img']) ? json_encode("../" . $p['img'], JSON_UNESCAPED_UNICODE) : 'null' ?>,
			size: 25,
			color: '#27ae60',
			font: { color: "#fff", size: 12 },
			link: '/characters/<?= (int)$p['id'] ?>'
		},
		<?php endforeach; ?>
	]);

	const edges = new vis.DataSet([
		<?php foreach ($clanManada as $rel): ?>
		{ from: "clan_<?= (int)$rel['clan'] ?>", to: "manada_<?= (int)$rel['manada'] ?>", arrows: 'to', color: '#555' },
		<?php endforeach; ?>

		<?php foreach ($personajes as $p): ?>
		<?php if (!empty($p['manada_id'])): ?>
		{ from: "manada_<?= (int)$p['manada_id'] ?>", to: "pj_<?= (int)$p['id'] ?>", arrows: 'to', color: '#888' },
		<?php elseif (!empty($p['clan_id'])): ?>
		{ from: "clan_<?= (int)$p['clan_id'] ?>", to: "pj_<?= (int)$p['id'] ?>", arrows: 'to', color: '#aaa', dashes: true },
		<?php endif; ?>
		<?php endforeach; ?>
	]);

	const container = document.getElementById('network');
	const data = { nodes, edges };
	const options = {
		layout: { improvedLayout: true },
		physics: {
			enabled: true,
			solver: "barnesHut",
			stabilization: { iterations: 200 },
			barnesHut: {
				gravitationalConstant: -8000,
				centralGravity: 0.2,
				springLength: 150,
				springConstant: 0.04,
				avoidOverlap: 0.5
			}
		},
		nodes: {
			font: { size: 14 },
			shape: 'dot',
			scaling: { label: true }
		},
		edges: {
			smooth: true,
			arrows: { to: { enabled: true, scaleFactor: 1 } }
		},
		interaction: {
			dragNodes: true,
			dragView: true
		}
	};

	const network = new vis.Network(container, data, options);

	network.on("click", function (params) {
		if (params.nodes.length === 1) {
			const nodeId = params.nodes[0];
			const position = network.getPositions([nodeId])[nodeId];
			network.moveTo({
				position: position,
				scale: 1.5,
				animation: { duration: 500, easingFunction: "easeInOutQuad" }
			});
		}
	});

	network.on("oncontext", function (params) {
		params.event.preventDefault();
	});

	network.on("doubleClick", function (params) {
		if (params.nodes.length === 1) {
			const nodeId = params.nodes[0];
			const node = nodes.get(nodeId);
			if (node.link) {
				window.open(node.link, '_blank');
			}
		}
	});

	function detenerFisica() {
		network.setOptions({ physics: false });
		document.getElementById("btnDetenerFisica").style.display = "none";
		document.getElementById("btnActivarFisica").style.display = "inline-block";
	}

	function activarFisica() {
		network.setOptions({ physics: true });
		network.stabilize();
		document.getElementById("btnDetenerFisica").style.display = "inline-block";
		document.getElementById("btnActivarFisica").style.display = "none";
		network.setOptions({ physics: { enabled: true } });
		network.stabilize();
	}
</script>

<div id="relationInfo" style="display:none; position:fixed; top:10px; left:50%; transform:translateX(-50%);
     background-color:#fff; border:1px solid #ccc; border-radius:6px; padding:8px 14px; font-family:sans-serif;
     font-size:14px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); z-index:2000;">
</div>
