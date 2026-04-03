<?php
setMetaFromPage(
    "Nebulosa de clanes | Heaven's Gate",
    "Mapa de relaciones entre clanes y organizaciones.",
    null,
    'website'
);

include_once(__DIR__ . '/../../helpers/public_response.php');

if (!$link) {
    hg_public_log_error('bio_reltree_clans', 'missing DB connection');
    hg_public_render_error(
        'Mapa no disponible',
        'No se pudo cargar el mapa de relaciones entre clanes y manadas en este momento.'
    );
    return;
}

if (!function_exists('hg_bio_reltree_clans_sanitize_int_csv')) {
    function hg_bio_reltree_clans_sanitize_int_csv($csv): string
    {
        $csv = (string)$csv;
        if (trim($csv) === '') {
            return '';
        }

        $parts = preg_split('/\s*,\s*/', trim($csv));
        $ints = [];

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            if (preg_match('/^\d+$/', $part)) {
                $ints[] = (string)(int)$part;
            }
        }

        $ints = array_values(array_unique($ints));
        return implode(',', $ints);
    }
}

$pageTitle2 = "Clanes y Manadas";

$excludeChronicles = isset($excludeChronicles)
    ? hg_bio_reltree_clans_sanitize_int_csv($excludeChronicles)
    : '';
$chronicleIdNotInSQL = ($excludeChronicles !== '')
    ? " WHERE p.chronicle_id NOT IN ($excludeChronicles) "
    : '';

$personajes = [];
$sqlPjs = "
    SELECT
        p.id,
        p.name,
        p.image_url,
        cbc.organization_id,
        gbc.group_id AS manada_id
    FROM fact_characters p
        LEFT JOIN (
            SELECT character_id, MIN(organization_id) AS organization_id
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
    $chronicleIdNotInSQL
";

$charactersResult = $link->query($sqlPjs);
if (!$charactersResult) {
    hg_public_log_error('bio_reltree_clans', 'characters query failed: ' . mysqli_error($link));
    hg_public_render_error(
        'Mapa no disponible',
        'No se pudo cargar el mapa de relaciones entre clanes y manadas en este momento.'
    );
    return;
}

while ($row = $charactersResult->fetch_assoc()) {
    $row['organization_id'] = isset($row['organization_id']) ? (int)$row['organization_id'] : 0;
    $row['manada_id'] = isset($row['manada_id']) ? (int)$row['manada_id'] : 0;
    $personajes[] = $row;
}
$charactersResult->free();

$manadasUsadas = [];
$clanesUsados = [];
foreach ($personajes as $personaje) {
    if (!empty($personaje['manada_id'])) {
        $manadasUsadas[(int)$personaje['manada_id']] = true;
    }
    if (!empty($personaje['organization_id'])) {
        $clanesUsados[(int)$personaje['organization_id']] = true;
    }
}

$clanes = [];
if ($clanesUsados) {
    $in = implode(',', array_map('intval', array_keys($clanesUsados)));
    $clanesResult = $link->query("SELECT id, name FROM dim_organizations WHERE id IN ($in)");
    if (!$clanesResult) {
        hg_public_log_error('bio_reltree_clans', 'organizations query failed: ' . mysqli_error($link));
        hg_public_render_error(
            'Mapa no disponible',
            'No se pudo cargar el mapa de relaciones entre clanes y manadas en este momento.'
        );
        return;
    }

    while ($row = $clanesResult->fetch_assoc()) {
        $clanes[(int)$row['id']] = $row['name'];
    }
    $clanesResult->free();
}

$manadas = [];
if ($manadasUsadas) {
    $in = implode(',', array_map('intval', array_keys($manadasUsadas)));
    $manadasResult = $link->query("SELECT id, name FROM dim_groups WHERE id IN ($in)");
    if (!$manadasResult) {
        hg_public_log_error('bio_reltree_clans', 'groups query failed: ' . mysqli_error($link));
        hg_public_render_error(
            'Mapa no disponible',
            'No se pudo cargar el mapa de relaciones entre clanes y manadas en este momento.'
        );
        return;
    }

    while ($row = $manadasResult->fetch_assoc()) {
        $manadas[(int)$row['id']] = $row['name'];
    }
    $manadasResult->free();
}

$clanManada = [];
if ($clanesUsados && $manadasUsadas) {
    $inClanes = implode(',', array_map('intval', array_keys($clanesUsados)));
    $inManadas = implode(',', array_map('intval', array_keys($manadasUsadas)));

    $sqlCM = "
        SELECT organization_id, group_id
        FROM bridge_organizations_groups
        WHERE organization_id IN ($inClanes)
          AND group_id IN ($inManadas)
          AND (is_active = 1 OR is_active IS NULL)
    ";
    $resCM = $link->query($sqlCM);

    if (!$resCM) {
        hg_public_log_error('bio_reltree_clans', 'organization-group relations query failed: ' . mysqli_error($link));
        hg_public_render_error(
            'Mapa no disponible',
            'No se pudo cargar el mapa de relaciones entre clanes y manadas en este momento.'
        );
        return;
    }

    while ($row = $resCM->fetch_assoc()) {
        $clanId = (int)$row['organization_id'];
        $manadaId = (int)$row['group_id'];
        $key = $clanId . '-' . $manadaId;
        $clanManada[$key] = ['clan' => $clanId, 'manada' => $manadaId];
    }
    $resCM->free();
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
        box-shadow: 0 3px 6px rgba(0, 0, 0, 0.05);
        height: 70vh;
        width: 100%;
        border: none;
        background-color: #05014e;
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
    <fieldset class="bioSeccion">
        <legend>&nbsp;Relaciones entre clanes y manadas&nbsp;</legend>
        <div style="float: right;">
            <button class="boton2" id="fullscreen-btn" onclick="toggleFullScreen()">Pantalla completa</button>
            <button class="boton2" onclick="location.href='/relationship-map/characters'">Cambiar vista</button>
            <button class="boton2" id="btnDetenerFisica" onclick="detenerFisica()">Detener fisica</button>
            <button class="boton2" id="btnActivarFisica" onclick="activarFisica()" style="display:none;">Activar fisica</button>
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
    {
        id: "clan_<?= (int)$id ?>",
        label: <?= json_encode((string)$name, JSON_UNESCAPED_UNICODE) ?>,
        shape: 'box',
        color: '#f39c12',
        link: '/organizations/<?= (int)$id ?>'
    },
<?php endforeach; ?>
<?php foreach ($manadas as $id => $name): ?>
    {
        id: "manada_<?= (int)$id ?>",
        label: <?= json_encode((string)$name, JSON_UNESCAPED_UNICODE) ?>,
        shape: 'ellipse',
        color: '#2980b9',
        link: '/groups/<?= (int)$id ?>'
    },
<?php endforeach; ?>
<?php foreach ($personajes as $personaje): ?>
    {
        id: "pj_<?= (int)$personaje['id'] ?>",
        label: <?= json_encode((string)$personaje['name'], JSON_UNESCAPED_UNICODE) ?>,
        shape: <?= !empty($personaje['image_url']) ? "'circularImage'" : "'dot'" ?>,
<?php if (!empty($personaje['image_url'])): ?>
        image: <?= json_encode("../" . $personaje['image_url'], JSON_UNESCAPED_UNICODE) ?>,
<?php endif; ?>
        size: 25,
        color: '#27ae60',
        font: { color: "#fff", size: 12 },
        link: '/characters/<?= (int)$personaje['id'] ?>'
    },
<?php endforeach; ?>
]);

const edges = new vis.DataSet([
<?php foreach ($clanManada as $relation): ?>
    {
        from: "clan_<?= (int)$relation['clan'] ?>",
        to: "manada_<?= (int)$relation['manada'] ?>",
        arrows: 'to',
        color: '#555'
    },
<?php endforeach; ?>
<?php foreach ($personajes as $personaje): ?>
<?php if (!empty($personaje['manada_id'])): ?>
    {
        from: "manada_<?= (int)$personaje['manada_id'] ?>",
        to: "pj_<?= (int)$personaje['id'] ?>",
        arrows: 'to',
        color: '#888'
    },
<?php elseif (!empty($personaje['organization_id'])): ?>
    {
        from: "clan_<?= (int)$personaje['organization_id'] ?>",
        to: "pj_<?= (int)$personaje['id'] ?>",
        arrows: 'to',
        color: '#aaa',
        dashes: true
    },
<?php endif; ?>
<?php endforeach; ?>
]);

const container = document.getElementById('network');
const data = { nodes: nodes, edges: edges };
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
        const node = nodes.get(params.nodes[0]);
        if (node && node.link) {
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
