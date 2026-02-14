<?php
/* app/partials/bio/bio_page_section_17_rel_graph.php
   Mini grafo de relaciones para la pagina de biografia */

if (!isset($characterId) || !$characterId || !isset($relaciones) || !is_array($relaciones)) {
    echo "<div style='padding:1em;'>Error: datos insuficientes para mostrar la grafica.</div>";
    return;
}

$mainName = isset($bioName) ? (string)$bioName : '';
$mainPhoto = isset($bioPhoto) && $bioPhoto !== '' ? (string)$bioPhoto : '/img/ui/icons/default.jpg';

// Normalizar relaciones
$nodes = [];
$edges = [];
$nodes[$characterId] = [
    'id' => (int)$characterId,
    'label' => $mainName,
    'image' => $mainPhoto,
    'size' => 24,
    'fontSize' => 16,
];

foreach ($relaciones as $r) {
    $relatedId = ($r['direction'] === 'outgoing') ? (int)$r['target_id'] : (int)$r['source_id'];
    if ($relatedId <= 0) { continue; }

    if (!isset($nodes[$relatedId])) {
        $nodes[$relatedId] = [
            'id' => $relatedId,
            'label' => (string)($r['nombre'] ?? ''),
            'image' => (string)($r['img'] ?? '/img/ui/icons/default.jpg'),
            'size' => 20,
            'fontSize' => 12,
        ];
    }

    $from = ($r['direction'] === 'outgoing') ? (int)$characterId : (int)$r['source_id'];
    $to   = ($r['direction'] === 'outgoing') ? (int)$r['target_id'] : (int)$characterId;

    $tag = strtolower((string)($r['tag'] ?? ''));
    $color = '#bdc3c7';
    if ($tag === 'conflicto') $color = '#e74c3c';
    elseif ($tag === 'amistad') $color = '#2ecc71';
    elseif ($tag === 'alianza') $color = '#3498db';
    elseif ($tag === 'familia') $color = '#f1c40f';

    $edges[] = [
        'from' => $from,
        'to' => $to,
        'arrows' => (string)($r['arrows'] ?? ''),
        'label' => (string)($r['relation_type'] ?? ''),
        'color' => $color,
    ];
}
?>

<div style="position:relative; width:100%; max-width:600px; height:600px; overflow:hidden; border-radius:10px; background:#05014E;">
    <div id="mini-network" style="width:100%; height:100%;"></div>
</div>

<script src="/assets/vendor/vis/vis-network.min.10.0.2.js"></script>
<script>
(function(){
    var container = document.getElementById('mini-network');
    if (!container || typeof vis === 'undefined') return;

    var nodes = new vis.DataSet([
        <?php foreach ($nodes as $n): ?>
        {
            id: <?= json_encode((int)$n['id']) ?>,
            label: <?= json_encode((string)$n['label']) ?>,
            shape: 'circularImage',
            image: <?= json_encode((string)$n['image']) ?>,
            size: <?= (int)$n['size'] ?>,
            font: { color: '#fff', size: <?= (int)$n['fontSize'] ?>, strokeWidth: 3, strokeColor: '#000' }
        },
        <?php endforeach; ?>
    ]);

    var edges = new vis.DataSet([
        <?php foreach ($edges as $e): ?>
        {
            from: <?= json_encode((int)$e['from']) ?>,
            to: <?= json_encode((int)$e['to']) ?>,
            arrows: <?= json_encode((string)$e['arrows']) ?>,
            label: <?= json_encode((string)$e['label']) ?>,
            color: { color: <?= json_encode((string)$e['color']) ?> },
            font: { align: 'middle', color: '#fff', size: 10, strokeWidth: 3, strokeColor: '#000' }
        },
        <?php endforeach; ?>
    ]);

    var data = { nodes: nodes, edges: edges };
    var options = {
        layout: { improvedLayout: true },
        interaction: {
            dragNodes: true,
            dragView: true,
            zoomView: true,
            hover: true
        },
        nodes: {
            borderWidth: 2,
            shadow: true,
            shape: 'circularImage',
            size: 20,
            font: { color: '#fff', size: 12, strokeWidth: 3, strokeColor: '#000' },
            widthConstraint: { minimum: 60 },
            scaling: { label: { enabled: true, min: 12, max: 12 } }
        },
        edges: {
            smooth: { type: 'dynamic', roundness: 0.3 },
            arrowStrikethrough: false
        },
        physics: {
            enabled: true,
            solver: 'forceAtlas2Based',
            forceAtlas2Based: {
                gravitationalConstant: -50,
                springLength: 120,
                springConstant: 0.02
            },
            minVelocity: 0.5,
            stabilization: { iterations: 600, fit: true }
        }
    };

    var network = new vis.Network(container, data, options);
    window.__bioRelNetwork = network;

    function refreshRelGraph(forcePhysics){
        if (!container) return;
        var w = container.clientWidth || 0;
        var h = container.clientHeight || 0;
        if (!w || !h) {
            setTimeout(function(){ refreshRelGraph(forcePhysics); }, 120);
            return;
        }
        try {
            if (forcePhysics) {
                network.setOptions({ physics: { enabled: true } });
            }
            network.redraw();
            network.fit({ animation: { duration: 220, easingFunction: 'easeInOutQuad' } });
            if (forcePhysics) {
                network.stabilize();
            }
        } catch (e) {}
    }

    // Inicial
    refreshRelGraph(true);
    network.once('stabilized', function(){
        try { network.setOptions({ physics: { enabled: false } }); } catch (e) {}
        refreshRelGraph(false);
    });
    setTimeout(function(){
        try { network.setOptions({ physics: { enabled: false } }); } catch (e) {}
        refreshRelGraph(false);
    }, 1800);

    window.__bioRelNetworkRefresh = function(){
        refreshRelGraph(false);
    };

    network.on('dragStart', function(){
        try { network.setOptions({ physics: { enabled: true } }); } catch (e) {}
    });
    network.on('dragEnd', function(){
        try { network.storePositions(); } catch (e) {}
        try { network.setOptions({ physics: { enabled: false } }); } catch (e) {}
    });

    network.on('doubleClick', function(params){
        if (params.nodes.length === 1) {
            var nodeId = String(params.nodes[0]);
            if (nodeId !== <?= json_encode((string)$characterId) ?>) {
                window.open('/characters/' + nodeId, '_blank');
            }
        }
    });
})();
</script>
