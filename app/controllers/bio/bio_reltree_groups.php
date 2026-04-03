<?php
setMetaFromPage(
    "Nebulosa de manadas | Heaven's Gate",
    "Mapa de relaciones entre manadas.",
    null,
    'website'
);

include_once(__DIR__ . '/../../helpers/public_response.php');

if (!$link) {
    hg_public_log_error('bio_reltree_groups', 'missing DB connection');
    hg_public_render_error(
        'Mapa no disponible',
        'No se pudo cargar el mapa de relaciones entre manadas en este momento.'
    );
    return;
}

if (!function_exists('hg_bio_reltree_groups_sanitize_int_csv')) {
    function hg_bio_reltree_groups_sanitize_int_csv($csv): string
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

$excludeChronicles = isset($excludeChronicles)
    ? hg_bio_reltree_groups_sanitize_int_csv($excludeChronicles)
    : '';
$chronicleIdNotInSQL = ($excludeChronicles !== '')
    ? " AND p.chronicle_id NOT IN ($excludeChronicles) "
    : "";

$charsSql = "
    SELECT
        p.id,
        p.name,
        p.image_url,
        COALESCE(dcs.label, '') AS status,
        p.status_id,
        gbc.group_id
    FROM fact_characters p
        LEFT JOIN dim_character_status dcs
            ON dcs.id = p.status_id
        LEFT JOIN bridge_characters_groups gbc
            ON gbc.character_id = p.id
           AND (gbc.is_active = 1 OR gbc.is_active IS NULL)
    WHERE 1=1
        $chronicleIdNotInSQL
";

$charactersResult = $link->query($charsSql);
if (!$charactersResult) {
    hg_public_log_error('bio_reltree_groups', 'characters query failed: ' . mysqli_error($link));
    hg_public_render_error(
        'Mapa no disponible',
        'No se pudo cargar el mapa de relaciones entre manadas en este momento.'
    );
    return;
}

$characters = $charactersResult->fetch_all(MYSQLI_ASSOC);
$charactersResult->free();

$charGroup = [];
$groupSizes = [];
foreach ($characters as $character) {
    $groupId = (int)($character['group_id'] ?? 0);
    if ($groupId > 0) {
        $charGroup[(int)$character['id']] = $groupId;
        $groupSizes[$groupId] = ($groupSizes[$groupId] ?? 0) + 1;
    }
}

$groups = [];
if (!empty($groupSizes)) {
    $in = implode(',', array_map('intval', array_keys($groupSizes)));
    $groupsResult = $link->query("SELECT id, name FROM dim_groups WHERE id IN ($in) ORDER BY name ASC");
    if (!$groupsResult) {
        hg_public_log_error('bio_reltree_groups', 'groups query failed: ' . mysqli_error($link));
        hg_public_render_error(
            'Mapa no disponible',
            'No se pudo cargar el mapa de relaciones entre manadas en este momento.'
        );
        return;
    }

    while ($row = $groupsResult->fetch_assoc()) {
        $groups[(int)$row['id']] = $row['name'];
    }
    $groupsResult->free();
}

$relationsResult = $link->query("SELECT * FROM bridge_characters_relations");
if (!$relationsResult) {
    hg_public_log_error('bio_reltree_groups', 'relations query failed: ' . mysqli_error($link));
    hg_public_render_error(
        'Mapa no disponible',
        'No se pudo cargar el mapa de relaciones entre manadas en este momento.'
    );
    return;
}

$relations = $relationsResult->fetch_all(MYSQLI_ASSOC);
$relationsResult->free();

$edges = [];
foreach ($relations as $relation) {
    $sourceId = (int)($relation['source_id'] ?? 0);
    $targetId = (int)($relation['target_id'] ?? 0);
    $groupSource = $charGroup[$sourceId] ?? 0;
    $groupTarget = $charGroup[$targetId] ?? 0;

    if ($groupSource <= 0 || $groupTarget <= 0 || $groupSource === $groupTarget) {
        continue;
    }

    $edges[] = [
        'from' => $groupSource,
        'to' => $groupTarget,
        'relation_type' => (string)($relation['relation_type'] ?? ''),
        'tag' => (string)($relation['tag'] ?? ''),
        'arrows' => (string)($relation['arrows'] ?? 'to'),
        'importance' => (int)($relation['importance'] ?? 1),
    ];
}

$pageTitle2 = "Manadas";
?>
<script type="text/javascript" src="assets/vendor/vis/vis-network.min.10.0.2.js"></script>

<style>
    #network {
        border: 0;
        box-shadow: 0 3px 6px rgba(0, 0, 0, 0.05);
        height: 70vh;
        width: 100%;
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
        <legend>&nbsp;Relaciones entre manadas&nbsp;</legend>
        <div style="float: right;">
            <button class="boton2" id="fullscreen-btn" onclick="toggleFullScreen()">Pantalla completa</button>
            <button class="boton2" onclick="location.href='/relationship-map/characters'">Personajes</button>
            <button class="boton2" onclick="location.href='/relationship-map/organizations'">Clanes</button>
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
        label: <?= json_encode((string)$name, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        shape: 'box',
        color: { background: '#0b1d4a', border: '#1b4aa0' },
        font: { color: "#fff", size: 12 },
        value: <?= (int)($groupSizes[(int)$id] ?? 1) ?>
    },
<?php endforeach; ?>
]);

const edges = new vis.DataSet([
<?php foreach ($edges as $edge): ?>
    <?php
        $color = '#bdc3c7';
        switch ((string)($edge['tag'] ?? '')) {
            case 'conflicto':
                $color = '#e74c3c';
                break;
            case 'amistad':
                $color = '#2ecc71';
                break;
            case 'alianza':
                $color = '#3498db';
                break;
            case 'familia':
                $color = '#f1c40f';
                break;
        }

        $importance = (int)$edge['importance'];
        if ($importance < 1) {
            $importance = 1;
        }

        $arrows = (string)$edge['arrows'];
        if ($arrows === '') {
            $arrows = 'to';
        }
    ?>
    {
        from: "group_<?= (int)$edge['from'] ?>",
        to: "group_<?= (int)$edge['to'] ?>",
        label: <?= json_encode((string)$edge['relation_type'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
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
