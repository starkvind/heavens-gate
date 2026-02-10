<?php
setMetaFromPage("Nebulosa de personajes | Heaven's Gate", "Mapa de relaciones entre personajes.", null, 'website');

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

// EXCLUSIONES (si existe la variable global, la usamos; si no, no excluimos nada)
$excludeChronicles = isset($excludeChronicles) ? sanitize_int_csv($excludeChronicles) : '';
$cronicaNotInSQL = ($excludeChronicles !== '') ? " AND p.cronica NOT IN ($excludeChronicles) " : "";

/*
    ============================================
    CAMBIO A BRIDGES
    - Clan: bridge_characters_organizations (activo) -> dim_organizations
    - (No tocamos relaciones: siguen en bridge_characters_relations)
    ============================================
*/

// Nota: role en bridge es NOT NULL, pero aquí solo leemos
$charactersSql = "
    SELECT
        p.id,
        p.nombre,
        p.img,
        p.estado,
        COALESCE(nc.name, '') AS clan_name
    FROM fact_characters p
        LEFT JOIN bridge_characters_organizations hccb
            ON hccb.character_id = p.id
           AND (hccb.is_active = 1 OR hccb.is_active IS NULL)
        LEFT JOIN dim_organizations nc
            ON nc.id = hccb.clan_id
    WHERE 1=1
        $cronicaNotInSQL
    ORDER BY p.nombre ASC
";

$characters = $link->query($charactersSql)->fetch_all(MYSQLI_ASSOC);

$relations = $link->query("SELECT * FROM bridge_characters_relations")->fetch_all(MYSQLI_ASSOC);
$pageTitle2 = "Personajes";
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
        border: 0;
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
        <legend>&nbsp;Relaciones entre personajes&nbsp;</legend>
        <div style="float: right;">
            <button class="boton2" id="fullscreen-btn" onclick="toggleFullScreen()">🔍 Pantalla completa</button>
            <button class="boton2" onclick="location.href='/relationship-map/organizations'">⚙️ Cambiar vista</button>
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
        <?php foreach ($characters as $c): ?>
            <?php
                $hasImage = !empty($c['img']);
                $isDead = ($c['estado'] ?? '') === 'Cadáver';
                $label = ($c['nombre'] ?? '') . ($isDead ? ' †' : '');
                $nodeColor = $isDead ? [
                    'background' => '#888',
                    'border' => '#555',
                    'highlight' => ['background' => '#888', 'border' => '#000']
                ] : null;
            ?>
            {
                id: <?= (int)$c['id'] ?>,
                label: <?= json_encode($label, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
                shape: <?= $hasImage ? "'circularImage'" : "'dot'" ?>,
                image: <?= $hasImage ? json_encode("../" . $c['img'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) : "null" ?>,
                size: 25,
                font: { color: "#fff", size: 12 },
                <?= $isDead ? "color: " . json_encode($nodeColor, JSON_UNESCAPED_UNICODE) . "," : "" ?>
            },
        <?php endforeach; ?>
    ]);

    const edges = new vis.DataSet([
        <?php foreach ($relations as $r): ?>
            <?php
                $color = '#bdc3c7';
                switch (($r['tag'] ?? '')) {
                    case 'conflicto': $color = '#e74c3c'; break;
                    case 'amistad':   $color = '#2ecc71'; break;
                    case 'alianza':   $color = '#3498db'; break;
                    case 'familia':   $color = '#f1c40f'; break;
                }
                $importance = isset($r['importance']) ? (int)$r['importance'] : 1;
                if ($importance < 1) $importance = 1;
                $arrows = ($r['arrows'] ?? '') ? $r['arrows'] : 'to';
            ?>
            {
                from: <?= (int)$r['source_id'] ?>,
                to: <?= (int)$r['target_id'] ?>,
                label: <?= json_encode((string)($r['relation_type'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
                width: <?= $importance ?>,
                arrows: <?= json_encode($arrows, JSON_UNESCAPED_UNICODE) ?>,
                color: { color: "<?= $color ?>" },
                font: { size: 0 }
            },
        <?php endforeach; ?>
    ]);

    const container = document.getElementById('network');
    const data = { nodes: nodes, edges: edges };
    const options = {
        layout: { improvedLayout: true },
        physics: {
            solver: "barnesHut",
            stabilization: {
                enabled: true,
                iterations: 300,
                updateInterval: 25,
                onlyDynamicEdges: false,
                fit: true
            },
            barnesHut: {
                gravitationalConstant: -18000,
                springLength: 120,
                springConstant: 0.01,
                avoidOverlap: 0.3
            }
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
                animation: {
                    duration: 500,
                    easingFunction: "easeInOutQuad"
                }
            });
        }
    });

    network.on("oncontext", function (params) {
        params.event.preventDefault();
    });

    network.on("doubleClick", function (params) {
        if (params.nodes.length === 1) {
            const nodeId = params.nodes[0];
            pressedNode = nodeId;
            pressTimer = setTimeout(() => {
                window.open(`/characters/${nodeId}`, '_blank');
            }, 600);
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
        network.setOptions({
            physics: { enabled: true }
        });
        network.stabilize();
    }
</script>

<div id="relationInfo" style="display:none; position:fixed; top:10px; left:50%; transform:translateX(-50%);
     background-color:#fff; border:1px solid #ccc; border-radius:6px; padding:8px 14px; font-family:sans-serif;
     font-size:14px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); z-index:2000;">
</div>
