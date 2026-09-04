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
		t.image_url,
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

<?php if (function_exists('hg_page_register_stylesheet')) { hg_page_register_stylesheet('/assets/css/pages/legacy/controllers-pwrs-totm_group_list.css'); } else { ?><link rel="stylesheet" href="/assets/css/pages/legacy/controllers-pwrs-totm_group_list.css"><?php } ?>

<?php if (function_exists('hg_page_register_stylesheet')) { hg_page_register_stylesheet('/assets/css/pages/legacy/controllers-pwrs-totm_group_list.css'); } else { ?><link rel="stylesheet" href="/assets/css/pages/legacy/controllers-pwrs-totm_group_list.css"><?php } ?>

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
				$img = (string)($row['image_url'] ?? '');
				$img = $img !== '' ? $img : 'img/ui/icons/icon_totem.webp';
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

