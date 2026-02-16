<?php
setMetaFromPage("Tótems | Heaven's Gate", "Listado de tótems por categoría.", null, 'website');
header('Content-Type: text/html; charset=utf-8');
if ($link) { mysqli_set_charset($link, "utf8mb4"); }

if (!$link) {
	echo "<h2>Error</h2><p class='texti' style='text-align:center;'>Error de conexi&oacute;n.</p>";
	return;
}

// Parametro 'b' (tipo de totem)
$routeParam = isset($_GET['b']) ? $_GET['b'] : '';
$typeId = is_numeric($routeParam) ? (int)$routeParam : 0;

// Consulta segura para obtener la informacion del tipo de totem
$consulta = "SELECT name, determinant AS determinante FROM dim_totem_types WHERE id = ? LIMIT 1";
$stmt = $link->prepare($consulta);
$stmt->bind_param('i', $typeId);
$stmt->execute();
$result = $stmt->get_result();
$ResultQuery = $result->fetch_assoc();

// Definir variables con valores por defecto
$totemName = $ResultQuery ? htmlspecialchars($ResultQuery["name"]) : "Desconocido";
$totemDett = $ResultQuery ? htmlspecialchars($ResultQuery["determinante"]) : "";
$pageSect = "Tótems $totemDett $totemName";

include("app/partials/main_nav_bar.php");

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Consulta segura para obtener los totems de esta categoria
$consulta = "
	SELECT
		t.id,
		t.pretty_id,
		t.name,
		t.cost,
		t.img,
		COALESCE(b.name, '') AS origen
	FROM dim_totems t
	LEFT JOIN dim_bibliographies b ON t.bibliography_id = b.id
	WHERE t.totem_type_id = ?
	ORDER BY b.name ASC, t.name ASC
";
$stmt = $link->prepare($consulta);
$stmt->bind_param('i', $typeId);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) { $items[] = $row; }

// Agrupar por origen
$groups = [];
foreach ($items as $it) {
	$origin = trim((string)($it['origen'] ?? ''));
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
	.item-thumb{ width:18px; height:18px; object-fit:contain; display:inline-block; vertical-align:middle; position:relative; z-index:1; }
	.item-icon{ display:inline-flex; align-items:center; justify-content:center; width:18px; height:18px; margin-right:6px; position:relative; }
	.item-icon::before{ content:""; position:absolute; left:50%; top:50%; width:20px; height:20px; border-radius:50%; background:#001188; opacity:.65; transform:translate(-50%,-50%); }
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

<h2 style="text-align:right;">T&oacute;tems <?= h($totemName) ?></h2>

<?php if (empty($items)): ?>
	<p class="texti" style="text-align:center;">No hay t&oacute;tems disponibles.</p>
<?php else: ?>
	<?php foreach ($origins as $origin): ?>
		<?php $fieldsetId = 'origin_' . md5($origin); ?>
		<?php echo "<h3 class='toggleAfiliacion' data-target='" . h($fieldsetId) . "'>" . h($origin) . "</h3>"; ?>
		<fieldset class="grupoHabilidad">
			<?php echo "<div id='" . h($fieldsetId) . "' class='contenidoAfiliacion'>"; ?>
			<?php foreach ($groups[$origin] as $row):
				$img = (string)($row['img'] ?? '');
				$img = $img !== '' ? $img : 'img/ui/powers/totem.gif';
				$name = (string)($row['name'] ?? '');
				$href = pretty_url($link, 'dim_totems', '/powers/totem', (int)$row["id"]);
			?>
				<a href="<?= h($href) ?>">
					<div class="renglon2col">
						<div class="renglon2colIz">
							<span class="item-cell"><span class="item-icon"><img class="item-thumb" src="<?= h($img) ?>" alt="<?= h($name) ?>"></span><?= h($name) ?></span>
						</div>
						<div class="renglon2colDe"><?= h($row["cost"]) ?></div>
					</div>
				</a>
			<?php endforeach; ?>
			</div>
		</fieldset>
	<?php endforeach; ?>

	<p align="right">T&oacute;tems hallados: <?= count($items) ?></p>
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
