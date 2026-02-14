<?php
setMetaFromPage("Inventario | Heaven's Gate", "Listado de objetos por tipo.", null, 'website');
header('Content-Type: text/html; charset=utf-8');
if ($link) { mysqli_set_charset($link, "utf8mb4"); }

if (!$link) {
    echo "<h2>Error</h2><p class='texti' style='text-align:center;'>Error de conexi&oacute;n.</p>";
    return;
}

// Param tipo (id o pretty_id)
$routeParam = $_GET['t'] ?? '';
$typeId = 0;
if (is_numeric($routeParam)) {
    $typeId = (int)$routeParam;
} else {
    if (function_exists('resolve_pretty_id')) {
        $typeId = (int)(resolve_pretty_id($link, 'dim_item_types', (string)$routeParam) ?? 0);
    }
}

if ($typeId <= 0) {
    echo "<h2>Inventario</h2><p class='texti' style='text-align:center;'>Tipo inv&aacute;lido.</p>";
    return;
}

// Nombre del tipo
$typeName = 'Objetos';
$typePretty = '';
$stType = $link->prepare("SELECT name, pretty_id FROM dim_item_types WHERE id = ? LIMIT 1");
$stType->bind_param('i', $typeId);
$stType->execute();
$rsType = $stType->get_result();
if ($rsType && ($row = $rsType->fetch_assoc())) {
    $typeName = (string)$row['name'];
    $typePretty = (string)($row['pretty_id'] ?? '');
}
$stType->close();
$nameTypeBack = $typeName;
$itemType = $typeId;
$typeSlug = $typePretty !== '' ? $typePretty : (string)$typeId;

include("app/partials/main_nav_bar.php");

$pageSect = "Inventario";

// Cargar items de ese tipo
$sql = "
    SELECT
        i.id AS item_id,
        i.pretty_id AS item_pretty_id,
        i.name AS item_name,
        i.img AS item_img,
        COALESCE(b.name, '') AS item_origin
    FROM fact_items i
    LEFT JOIN dim_bibliographies b ON i.origen = b.id
    WHERE i.tipo = ?
    ORDER BY b.name ASC, i.name ASC
";
$st = $link->prepare($sql);
$st->bind_param('i', $typeId);
$st->execute();
$rs = $st->get_result();
$items = [];
while ($row = $rs->fetch_assoc()) { $items[] = $row; }
$st->close();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Agrupar por origen
$groups = [];
foreach ($items as $it) {
    $origin = trim((string)($it['item_origin'] ?? ''));
    if ($origin === '') $origin = 'Sin origen';
    if (!isset($groups[$origin])) $groups[$origin] = [];
    $groups[$origin][] = $it;
}
$origins = array_keys($groups);
usort($origins, function($a, $b){
    if ($a === 'Sin origen') return 1;
    if ($b === 'Sin origen') return -1;
    return strcasecmp($a, $b);
});
?>

<style>
    .item-thumb{ width:16px; height:16px; object-fit:contain; display:inline-block; vertical-align:middle; position:relative; z-index:1; }
    .item-icon{ display:inline-flex; align-items:center; justify-content:center; width:16px; height:16px; margin-right:6px; position:relative; }
    .item-icon::before{ content:""; position:absolute; left:50%; top:50%; width:18px; height:18px; border-radius:50%; background:#001188; opacity:.65; transform:translate(-50%,-50%); }
    .item-cell{ display:inline-flex; align-items:center; gap:6px; }
</style>

<style>
	.toggleAfiliacion {
	  background: #05014e;
	  color: #fff;
	  border: 1px solid #000088;
	  padding: 6px 10px;
	  margin: 8px 0 0 0;
	  font-size: 1.1em;
	  cursor: pointer;
	  width: 85%;
	  text-align: left;
	}

	.toggleAfiliacion:hover {
	  background: #000066;
	  border: 1px solid #0000BB;
	}

	.contenidoAfiliacion {
	  display: flex;
	  flex-wrap: wrap;
	  gap: 6px;
	  padding: 8px 0 12px 0;
	}

	.oculto { display: none; }
</style>


<h2 style="text-align:right;"><?= h($typeName) ?></h2>

<?php if (empty($items)): ?>
    <p class="texti" style="text-align:center;">No hay objetos disponibles.</p>
<?php else: ?>
    <?php foreach ($origins as $origin): ?>
        <?php $fieldsetId = 'origin_' . md5($origin); ?>
        <?php echo "<h3 class='toggleAfiliacion' data-target='" . h($fieldsetId) . "'>" . h($origin) . "</h3>"; ?>
        <fieldset class="grupoBioClan" style='padding:0 1em;'>
            <?php echo "<div id='" . h($fieldsetId) . "' class='contenidoAfiliacion'>"; ?>
            <?php foreach ($groups[$origin] as $i):
                $itemSlug = $i['item_pretty_id'] ?: $i['item_id'];
                $name = (string)$i['item_name'];
                $img = $i['item_img'] ? $i['item_img'] : '/img/inv/no-photo.gif';
            ?>
                <a href="/inventory/<?= h($typeSlug) ?>/<?= h($itemSlug) ?>">
                    <div class="renglon2col">
                        <div class="renglon2colIz">
                            <span class="item-cell"><span class="item-icon"><img src="<?= h($img) ?>" alt="<?= h($name) ?>" class="item-thumb"></span><?= h($name) ?></span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
            </div>
        </fieldset>
    <?php endforeach; ?>

    <p align="right"><?= h($typeName) ?> en total: <?= count($items) ?></p>
<?php endif; ?>


<script>
	document.addEventListener('DOMContentLoaded', function(){
		var toggles = document.querySelectorAll('.toggleAfiliacion');
		for (var i = 0; i < toggles.length; i++) {
			toggles[i].addEventListener('click', function(){
				var targetId = this.getAttribute('data-target');
				var el = document.getElementById(targetId);
				if (!el) return;
				el.classList.toggle('oculto');
			});
		}
	});
</script>
