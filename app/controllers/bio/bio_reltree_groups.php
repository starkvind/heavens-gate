<?php
setMetaFromPage("Nebulosa de manadas | Heaven's Gate", "Mapa de relaciones entre manadas.", null, 'website');

if (!$link) {
    die("Error de conexión a la base de datos: " . mysqli_connect_error());
}

// Helper: sanitiza lista tipo "1,2, 3" -> "1,2,3" (solo ints).
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

// Excluir crónicas (si existe la variable global, la usamos; si no, no excluimos nada)
$excludeChronicles = isset($excludeChronicles) ? sanitize_int_csv($excludeChronicles) : '';
$cronicaNotInSQL = ($excludeChronicles !== '') ? " AND p.cronica NOT IN ($excludeChronicles) " : "";

// Personajes con manada activa (bridge)
$charsSql = "
    SELECT
        p.id,
        p.nombre,
        p.img,
        p.estado,
        gbc.group_id
    FROM fact_characters p
        LEFT JOIN bridge_characters_groups gbc
            ON gbc.character_id = p.id
           AND (gbc.is_active = 1 OR gbc.is_active IS NULL)
    WHERE 1=1
        $cronicaNotInSQL
";
$characters = $link->query($charsSql)->fetch_all(MYSQLI_ASSOC);

// Map character -> group
$charGroup = [];
$groupSizes = [];
foreach ($characters as $c) {
    $gid = (int)($c['group_id'] ?? 0);
    if ($gid > 0) {
        $charGroup[(int)$c['id']] = $gid;
        $groupSizes[$gid] = ($groupSizes[$gid] ?? 0) + 1;
    }
}

// Grupos usados
$groups = [];
if (!empty($groupSizes)) {
    $in = implode(',', array_map('intval', array_keys($groupSizes)));
    $rs = $link->query("SELECT id, name FROM dim_groups WHERE id IN ($in) ORDER BY name ASC");
    if ($rs) { while ($r = $rs->fetch_assoc()) { $groups[(int)$r['id']] = $r['name']; } $rs->free(); }
}

// Relaciones entre personajes -> relaciones entre grupos
$relations = [];
$resRel = $link->query("SELECT * FROM bridge_characters_relations");
if ($resRel) {
    $relations = $resRel->fetch_all(MYSQLI_ASSOC);
    $resRel->free();
}

$edges = [];
foreach ($relations as $r) {
    $sid = (int)($r['source_id'] ?? 0);
    $tid = (int)($r['target_id'] ?? 0);
    $g1 = $charGroup[$sid] ?? 0;
    $g2 = $charGroup[$tid] ?? 0;
    if ($g1 <= 0 || $g2 <= 0) continue;
    if ($g1 === $g2) continue;
    $edges[] = [
        'from' => $g1,
        'to' => $g2,
        'relation_type' => (string)($r['relation_type'] ?? ''),
        'tag' => (string)($r['tag'] ?? ''),
        'arrows' => (string)($r['arrows'] ?? 'to'),
        'importance' => (int)($r['importance'] ?? 1),
    ];
}

$pageTitle2 = "Manadas";
?>

<script type="text/javascript" src="assets/vendor/vis/vis-network.min.10.0.2.js"></script>

<style>
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
        <legend>&nbsp;Relaciones entre manadas&nbsp;</legend>
        <div style="float: right;">
            <button class="boton2" id="fullscreen-btn" onclick="toggleFullScreen()">🔍 Pantalla completa</button>
            <button class="boton2" onclick="location.href='/relationship-map/characters'">👤 Personajes</button>
            <button class="boton2" onclick="location.href='/relationship-map/organizations'">🏷️ Clanes</button>
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
<?php foreach ($groups as $id => $name): ?>
    {
        id: "group_<?= (int)$id ?>",
        label: <?= json_encode($name, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        shape: 'box',
        color: { background: '#0b1d4a', border: '#1b4aa0' },
        font: { color: "#fff", size: 12 },
        value: <?= (int)($groupSizes[(int)$id] ?? 1) ?>
    },
<?php endforeach; ?>
]);

const edges = new vis.DataSet([
<?php foreach ($edges as $e): ?>
    <?php
        $color = '#bdc3c7';
        switch (($e['tag'] ?? '')) {
            case 'conflicto': $color = '#e74c3c'; break;
            case 'amistad':   $color = '#2ecc71'; break;
            case 'alianza':   $color = '#3498db'; break;
            case 'familia':   $color = '#f1c40f'; break;
        }
        $importance = (int)$e['importance']; if ($importance < 1) $importance = 1;
        $arrows = $e['arrows'] ?: 'to';
    ?>
    {
        from: "group_<?= (int)$e['from'] ?>",
        to: "group_<?= (int)$e['to'] ?>",
        label: <?= json_encode((string)$e['relation_type'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
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
        stabilization: { enabled: true, iterations: 300, updateInterval: 25, fit: true },
        barnesHut: { gravitationalConstant: -18000, springLength: 120, springConstant: 0.01, avoidOverlap: 0.3 }
    },
    nodes: { shape: 'box', scaling: { min: 10, max: 40 } },
    edges: { smooth: { type: 'dynamic' } }
};

new vis.Network(container, data, options);
</script>
