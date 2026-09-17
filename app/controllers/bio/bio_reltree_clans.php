<?php
setMetaFromPage(
    "Nebulosa de clanes | Heaven's Gate",
    "Mapa de relaciones entre clanes y organizaciones.",
    null,
    'website'
);

include_once(__DIR__ . '/../../helpers/public_response.php');
require_once(__DIR__ . '/../../domains/relationships/queries.php');

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
$excludedChronicleIds = $excludeChronicles !== ''
    ? array_map('intval', explode(',', $excludeChronicles))
    : [];

$personajes = hg_relationships_fetch_clan_map_characters($link, $excludedChronicleIds);
if ($personajes === null) {
    hg_public_log_error('bio_reltree_clans', 'characters query failed: ' . mysqli_error($link));
    hg_public_render_error(
        'Mapa no disponible',
        'No se pudo cargar el mapa de relaciones entre clanes y manadas en este momento.'
    );
    return;
}

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

$clanes = hg_relationships_fetch_names_by_ids($link, 'dim_organizations', array_keys($clanesUsados));
if ($clanes === null) {
    hg_public_log_error('bio_reltree_clans', 'organizations query failed: ' . mysqli_error($link));
    hg_public_render_error(
        'Mapa no disponible',
        'No se pudo cargar el mapa de relaciones entre clanes y manadas en este momento.'
    );
    return;
}

$manadas = hg_relationships_fetch_names_by_ids($link, 'dim_groups', array_keys($manadasUsadas));
if ($manadas === null) {
    hg_public_log_error('bio_reltree_clans', 'groups query failed: ' . mysqli_error($link));
    hg_public_render_error(
        'Mapa no disponible',
        'No se pudo cargar el mapa de relaciones entre clanes y manadas en este momento.'
    );
    return;
}

$clanManada = hg_relationships_fetch_org_group_relations(
    $link,
    array_keys($clanesUsados),
    array_keys($manadasUsadas)
);
if ($clanManada === null) {
    hg_public_log_error('bio_reltree_clans', 'organization-group relations query failed: ' . mysqli_error($link));
    hg_public_render_error(
        'Mapa no disponible',
        'No se pudo cargar el mapa de relaciones entre clanes y manadas en este momento.'
    );
    return;
}
?>
<script type="text/javascript" src="assets/vendor/vis/vis-network.min.10.0.2.js"></script>

<?php if (function_exists('hg_page_register_stylesheet')) { hg_page_register_stylesheet('/assets/css/pages/legacy/controllers-bio-bio_reltree_clans.css'); } else { ?><link rel="stylesheet" href="/assets/css/pages/legacy/controllers-bio-bio_reltree_clans.css"><?php } ?>

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
