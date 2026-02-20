<?php

// Obtener parámetros de manera segura
$systemIdDocument = isset($_GET['b']) ? (string)$_GET['b'] : '';  // ID o pretty_id
$systemTypeDocument = isset($_GET['tc']) ? (int)$_GET['tc'] : 0;  // Tipo de contenido

include_once(__DIR__ . '/../../helpers/pretty.php');

// Sanitiza "1,2, 3" -> "1,2,3" (solo ints). Si queda vacío, devuelve ""
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

// EXCLUSIONES (si existe la variable global, la usamos; si no, mantenemos 2,7)
$excludeChronicles = isset($excludeChronicles) ? sanitize_int_csv($excludeChronicles) : '2,7';
$whereChron = ($excludeChronicles !== '') ? "p.chronicle_id NOT IN ($excludeChronicles)" : "1=1";

// Preparar Queries
switch ($systemTypeDocument) {
	case 1:
		$table = "dim_breeds";
		$energy = "Gnosis";
		break;
	case 2:
		$table = "dim_auspices";
		$energy = "Rabia";
		break;
	case 3:
		$table = "dim_tribes";
		$energy = "Fuerza de voluntad";
		break;
	case 4:
		$table = "fact_misc_systems";
		break;
	default:
		$table = "";
		break;
}

if ($table !== "") {

    $infoDataCheck = 0;

    $resolvedId = 0;
    if ($systemIdDocument !== '') {
        if (preg_match('/^\d+$/', $systemIdDocument)) {
            $resolvedId = (int)$systemIdDocument;
        } else {
            $resolvedId = (int)resolve_pretty_id($link, $table, $systemIdDocument);
        }
    }

    if ($resolvedId <= 0) {
        $pageSect = "Sistema";
        $pageTitle2 = "Elemento no encontrado";
        include("app/partials/main_nav_bar.php");
        echo "<h2>Elemento no encontrado</h2>";
        echo "<div class='renglonDatosSistema'>El contenido solicitado no existe.</div>";
        return;
    }

    // Ejecutar la consulta utilizando mysqli y sentencias preparadas
    $stmt = $link->prepare("SELECT * FROM `$table` WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $resolvedId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($ResultQuery = $result->fetch_assoc()) {
        // Datos del Sistema
        $returnType = htmlspecialchars($ResultQuery["system_name"]);
        $typeOfSystem = $returnType;
        $nameSyst = htmlspecialchars($ResultQuery["name"]);
        $infoDesc = ($ResultQuery["description"] ?? $ResultQuery["description"] ?? "");
        $systemId = (int)($ResultQuery["system_id"] ?? 0);
        if (isset($ResultQuery["image_url"])) {
			$imageSyst = htmlspecialchars($ResultQuery["image_url"]);
		} else {
			$imageSyst = "";
		}

        $pageSect = $returnType; // PARA CAMBIAR EL TITULO A LA PAGINA
        $pageTitle2 = $nameSyst;
        setMetaFromPage($nameSyst . " | Sistemas | Heaven's Gate", meta_excerpt($infoDesc), $imageSyst, 'article'); 
        
        include("app/helpers/system_category_helper.php");
        include("app/partials/main_nav_bar.php"); // Barra Navegación

        // Comprobar si los datos tienen energía para mostrarla
        if (isset($ResultQuery["energy"])) {
			$checkEnergy = htmlspecialchars($ResultQuery["energy"]);
		} else {
			$checkEnergy = 0;
		}

        if ($returnType == "Ícaros") {
            $energy = "Fuerza de Voluntad";
        }

        if ($returnType == "Mokolé" && $systemTypeDocument == 2) {
            $energy = "Fuerza de Voluntad";
        }
?>
<style>
.syst-detail { display:grid; gap:12px; }
.syst-banner { position:relative; background:#000033; border:1px solid #000088; border-radius:12px; overflow:hidden; min-height:140px; margin-top:1em; }
.syst-banner::before { content:''; display:block; padding-top:28%; }
.syst-banner img { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; filter:saturate(1.05); }
.syst-banner-title { position:absolute; top:10px; left:12px; color:#33FFFF; background:rgba(0,0,0,0.55); border:1px solid #1b4aa0; padding:6px 10px; border-radius:8px; font-weight:bold; text-align:left; }
.syst-box { background:#05014E; border:1px solid #000088; border-radius:12px; padding:12px; }
.syst-box h3 { margin-top:0; color:#33FFFF; }
.syst-meta { color:#cfe; }
.syst-detail, .syst-box, .syst-box h3, .syst-box p, .syst-box div, .renglon2col, .renglon2colIz, .renglon2colDe { text-align:left !important; }
.syst-box fieldset, .syst-box fieldset * { text-align:left !important; }
.syst-box a, .syst-box a * { text-align:left !important; }
</style>
<div class="syst-detail">
  <div class="syst-banner">
    <?php if ($imageSyst !== ''): ?>
      <img src="<?= htmlspecialchars($imageSyst) ?>" alt="<?= htmlspecialchars($nameSyst) ?>">
    <?php endif; ?>
    <h2 class="syst-banner-title"><?= $nameSyst ?></h2>
  </div>

<?php
        $metaHtml = '';
        if ($checkEnergy != 0) {
            $infoDataCheck++;
            $metaHtml .= "<p><b>$energy inicial:</b> $checkEnergy</p>";

        } elseif ($systemTypeDocument == 4) {
            $miscInfoData = ($ResultQuery["extra_info"]);
            $miscNameEnergy = htmlspecialchars($ResultQuery["energy_name"]);
            $miscValuEnergy = htmlspecialchars($ResultQuery["energy_value"]);

            if ($miscInfoData != "") { 
                $metaHtml .= "<p>$miscInfoData</p>"; 
                $infoDataCheck++;
            }

            if ($miscNameEnergy != "") { 
                $metaHtml .= "<p><b>$miscNameEnergy:</b> $miscValuEnergy</p>"; 
                $infoDataCheck++;
            }
        }

        if ($metaHtml !== '') {
            echo "<div class=\"syst-box syst-meta\">$metaHtml</div>";
        }
?>

  <div class="syst-box">
    <h3>Descripción</h3>
    <div><?= $infoDesc ?></div>
  </div>

<?php
        // Don Query para obtener dones basados en el sistema
        $donGroup = $nameSyst;
        $donQuery = "SELECT id, name, rank FROM fact_gifts WHERE gift_group = ? AND system_id = ? ORDER BY rank;";
        $stmtDon = $link->prepare($donQuery);
        $stmtDon->bind_param('si', $donGroup, $systemId);
        $stmtDon->execute();
        $resultDon = $stmtDon->get_result();
        $filasDon = $resultDon->num_rows;

        if ($filasDon > 0) {
            $infoDataCheck++;
            echo "<div class=\"syst-box\">";
            echo "<h3>Dones disponibles</h3>";
            echo "<fieldset class='grupoHabilidad'>";
            while ($resultDonQuery = $resultDon->fetch_assoc()) {
                echo "
                    <a href='" . htmlspecialchars(pretty_url($link, 'fact_gifts', '/powers/gift', (int)$resultDonQuery['id'])) . "'
                        class='hg-tooltip'
                        data-tip='don'
                        data-id='" . (int)$resultDonQuery['id'] . "'
                        target='_blank'>
                        <div class='renglon2col'>
                            <div class='renglon2colIz'>
                                <img class='valign' src='img/ui/powers/don.gif'> " . htmlspecialchars($resultDonQuery['name']) . "
                            </div>
                            <div class='renglon2colDe'>" . htmlspecialchars($resultDonQuery['rank']) . "</div>
                        </div>
                    </a>
                ";
            }
            echo "</fieldset>";
            echo "</div>";
        }

        $stmtDon->close();
?>

<?php
        // Mostrar personajes asociados (raza / auspicio / tribu)
        $charField = '';
        if ($systemTypeDocument === 1) $charField = 'breed_id';
        elseif ($systemTypeDocument === 2) $charField = 'auspice_id';
        elseif ($systemTypeDocument === 3) $charField = 'tribe_id';

        if ($charField !== '') {
            $charsWithoutPackQuery = "
                SELECT 
                    p.id,
                    p.name,
                    GROUP_CONCAT(DISTINCT g.name ORDER BY g.name SEPARATOR ', ') AS grupos,
                    GROUP_CONCAT(DISTINCT o.name ORDER BY o.name SEPARATOR ', ') AS organizaciones
                FROM fact_characters p
                LEFT JOIN bridge_characters_groups bcg ON bcg.character_id = p.id
                LEFT JOIN dim_groups g ON g.id = bcg.group_id
                LEFT JOIN bridge_characters_organizations bco ON bco.character_id = p.id
                LEFT JOIN dim_organizations o ON o.id = bco.clan_id
                WHERE p.`$charField` = ?
                  AND $whereChron
                GROUP BY p.id
                ORDER BY p.name
            ";
            $stmtChars = $link->prepare($charsWithoutPackQuery);
            $stmtChars->bind_param('i', $resolvedId);
            $stmtChars->execute();
            $resultChars = $stmtChars->get_result();
            $charsWithoutPackFilas = $resultChars->num_rows;
        } else {
            $charsWithoutPackFilas = 0;
        }

        if ($charsWithoutPackFilas > 0 && $charField !== '') {
            $members = [];
            while ($charsWithoutPackResult = $resultChars->fetch_assoc()) { 
                $members[] = [
                    'id' => (int)$charsWithoutPackResult["id"],
                    'nombre' => (string)$charsWithoutPackResult["name"],
                    'grupos' => (string)($charsWithoutPackResult["grupos"] ?? ''),
                    'organizaciones' => (string)($charsWithoutPackResult["organizaciones"] ?? ''),
                ];
            }

            $pjCount = count($members);
            echo "<div class='syst-box'>";
            echo "<h3>Miembros</h3>";
            echo "<table id='tabla-miembros' class='display' style='width:100%'>";
            echo "<thead><tr><th>Nombre</th><th>Grupo</th><th>Organización</th></tr></thead><tbody>";
            foreach ($members as $m) {
                $charHref = pretty_url($link, 'fact_characters', '/characters', (int)$m['id']);
                $charName = htmlspecialchars($m['nombre']);
                $charGroup = htmlspecialchars($m['grupos'] !== '' ? $m['grupos'] : '-');
                $charOrg = htmlspecialchars($m['organizaciones'] !== '' ? $m['organizaciones'] : '-');
                echo "<tr><td><a href='" . htmlspecialchars($charHref) . "' target='_blank'>$charName</a></td><td>$charGroup</td><td>$charOrg</td></tr>";
            }
            echo "</tbody></table>";
            echo "<p style='text-align:left;'>$nameSyst: $pjCount</p>";
            echo "</div>";

            echo "<link rel='stylesheet' href='/assets/vendor/datatables/jquery.dataTables.min.css'>";
            echo "<script src='/assets/vendor/jquery/jquery-3.7.1.min.js'></script>";
            echo "<script src='/assets/vendor/datatables/jquery.dataTables.min.js'></script>";
            echo "<script>
            (function(){
              if (typeof jQuery === 'undefined' || !jQuery.fn || !jQuery.fn.DataTable) return;
              jQuery(function($){
                $('#tabla-miembros').DataTable({
                  pageLength: 10,
                  lengthMenu: [10, 20, 50, 100],
                  order: [[0, 'asc']],
                  language: {
                    search: 'Buscar:&nbsp; ',
                    lengthMenu: 'Mostrar _MENU_ personajes',
                    info: 'Mostrando _START_ a _END_ de _TOTAL_ personajes',
                    infoEmpty: 'No hay personajes disponibles',
                    emptyTable: 'No hay datos en la tabla',
                    paginate: { first: 'Primero', last: 'Último', next: '▶', previous: '◀' }
                  }
                });
              });
            })();
            </script>";
        }

        if (isset($stmtChars) && $stmtChars) $stmtChars->close();
?>
</div>
<?php
    }
}
?>
<style>
#hg-tooltip{
	position: fixed;
	z-index: 999999;
	max-width: 380px;
	background: #050b36;
	border: 1px solid #0b3a7a;
	color: #e6f0ff;
	border-radius: 8px;
	padding: 10px 12px;
	box-shadow: 0 8px 24px rgba(0,0,0,.45);
	display: none;
	pointer-events: none;
	font-size: 12px;
	line-height: 1.35;
	text-align: left !important;
}
#hg-tooltip *{ text-align: left !important; }
#hg-tooltip .hg-tip-title{ font-weight: bold; margin-bottom: 4px; color:#8fd7ff; }
#hg-tooltip .hg-tip-meta{ font-size: 11px; color:#9fb2d9; }
#hg-tooltip .hg-tip-label{ font-weight: bold; margin-top: 6px; color:#cfd9ff; }
#hg-tooltip .hg-tip-text{ font-size: 12px; color:#e6f0ff; }
</style>
<script>
(function(){
	const nodes = document.querySelectorAll('.hg-tooltip[data-tip="don"]');
	if (!nodes.length) return;

	let tooltip = document.getElementById('hg-tooltip');
	if (!tooltip) {
		tooltip = document.createElement('div');
		tooltip.id = 'hg-tooltip';
		document.body.appendChild(tooltip);
	}

	let lastX = 0, lastY = 0;
	const cache = new Map();
	let timer = null;
	let currentKey = '';

	function moveTip(x, y){
		const pad = 14;
		const vw = window.innerWidth;
		const vh = window.innerHeight;
		const tw = tooltip.offsetWidth || 320;
		const th = tooltip.offsetHeight || 120;
		let left = x + pad;
		let top = y + pad;
		if (left + tw > vw - 8) left = x - tw - pad;
		if (top + th > vh - 8) top = y - th - pad;
		if (left < 8) left = 8;
		if (top < 8) top = 8;
		tooltip.style.left = left + 'px';
		tooltip.style.top = top + 'px';
	}

	function hideTip(){
		tooltip.style.display = 'none';
		tooltip.innerHTML = '';
		currentKey = '';
	}

	nodes.forEach(el => {
		el.addEventListener('mousemove', (ev) => {
			lastX = ev.clientX;
			lastY = ev.clientY;
			if (tooltip.style.display === 'block') moveTip(lastX, lastY);
		});

		el.addEventListener('mouseenter', (ev) => {
			lastX = ev.clientX;
			lastY = ev.clientY;
			const type = el.getAttribute('data-tip') || '';
			const id = el.getAttribute('data-id') || '';
			if (!type || !id) return;
			const key = type + ':' + id;
			currentKey = key;
			if (timer) clearTimeout(timer);

			timer = setTimeout(async () => {
				if (currentKey !== key) return;
				if (cache.has(key)) {
					tooltip.innerHTML = cache.get(key);
					tooltip.style.display = 'block';
					moveTip(lastX, lastY);
					return;
				}

				try {
					const res = await fetch('/ajax/tooltip?type=' + encodeURIComponent(type) + '&id=' + encodeURIComponent(id));
					const html = await res.text();
					if (currentKey !== key) return;
					cache.set(key, html);
					tooltip.innerHTML = html;
					tooltip.style.display = 'block';
					moveTip(lastX, lastY);
				} catch (_e) {}
			}, 2000);
		});

		el.addEventListener('mouseleave', () => {
			if (timer) clearTimeout(timer);
			timer = null;
			hideTip();
		});
	});
})();
</script>
