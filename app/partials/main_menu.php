<?php 
	//global $link; // Asegúrate de que $link sea accesible en el ámbito global
	include("app/helpers/heroes.php"); // Archivo de la base de datos
?>


<?php
	if (!function_exists('h')) {
		function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
	}

	function hg_current_path(): string {
		$uri = $_SERVER['REQUEST_URI'] ?? '/';
		$path = strtok($uri, '?');
		if ($path === false || $path === '') return '/';

		// // Si hay rutas legacy (?p=...), usa esa info para mapear aunque el path no sea '/'
		// if (isset($_GET['p'])) {
		// 	$p = (string)$_GET['p'];
		// 	if ($p === 'temp_analisis') return '/seasons/analysis';
		// 	if ($p === 'temp') {
		// 		if (isset($_GET['t']) && $_GET['t'] !== '') return '/seasons/' . (string)$_GET['t'];
		// 		return '/seasons';
		// 	}
		// 	if ($p === 'seechapter') {
		// 		if (isset($_GET['t']) && $_GET['t'] !== '') return '/chapters/' . (string)$_GET['t'];
		// 		return '/chapters';
		// 	}
		// }

		return $path;
	}

	function hg_normalize_path(string $path): string {
		$path = strtolower($path);
		if ($path !== '/' && substr($path, -1) === '/') {
			$path = rtrim($path, '/');
		}
		return $path === '' ? '/' : $path;
	}

	function hg_starts_with(string $haystack, string $needle): bool {
		return $needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0;
	}

	function hg_menu_open_id_static(string $path, $link): ?string {
		$path = hg_normalize_path($path);

		if (hg_starts_with($path, '/news') || hg_starts_with($path, '/search') || hg_starts_with($path, '/status') || hg_starts_with($path, '/about')) {
			return 'startMenu';
		}
		if (hg_starts_with($path, '/characters') || hg_starts_with($path, '/organizations') || hg_starts_with($path, '/relationship-map')) {
			return 'bioMenu';
		}
		if (hg_starts_with($path, '/parties')) {
			return 'archivoMenu';
		}
		if (hg_starts_with($path, '/seasons/analysis')) {
			return 'toolsMenu';
		}
		if (hg_starts_with($path, '/documents') || hg_starts_with($path, '/timeline') || hg_starts_with($path, '/maps') || hg_starts_with($path, '/music') || hg_starts_with($path, '/gallery')) {
			return 'loreMenu';
		}
		if (hg_starts_with($path, '/systems') || hg_starts_with($path, '/rules') || hg_starts_with($path, '/inventory')) {
			return 'systemMenu';
		}
		if (hg_starts_with($path, '/powers')) {
			return 'powersMenu';
		}
		if (hg_starts_with($path, '/tools')) {
			return 'toolsMenu';
		}
		return null;
	}

	function hg_menu_open_id_db($link, string $path): ?string {
		if (!$link) return null;
		$path = hg_normalize_path($path);

		// Resolver temporadas/cap?tulos a su men? v?a dynamic_source (seasons_0 / seasons_1)
		$seasonFlag = null;
		if (preg_match('#^/seasons/([^/]+)#', $path, $m)) {
			$seasonRaw = (string)$m[1];
			$seasonId = 0;
			if (ctype_digit($seasonRaw)) {
				$seasonId = (int)$seasonRaw;
			} elseif (function_exists('resolve_pretty_id')) {
				$seasonId = (int)(resolve_pretty_id($link, 'dim_seasons', $seasonRaw) ?? 0);
			}
			if ($seasonId > 0 && ($stmt = mysqli_prepare($link, "SELECT season FROM dim_seasons WHERE id = ? LIMIT 1"))) {
				mysqli_stmt_bind_param($stmt, 'i', $seasonId);
				mysqli_stmt_execute($stmt);
				$res = mysqli_stmt_get_result($stmt);
				if ($row = mysqli_fetch_assoc($res)) {
					$seasonFlag = (string)($row['season'] ?? '');
				}
				mysqli_stmt_close($stmt);
			}
		} elseif (preg_match('#^/chapters/([^/]+)#', $path, $m)) {
			$chapterRaw = (string)$m[1];
			$chapterId = 0;
			if (ctype_digit($chapterRaw)) {
				$chapterId = (int)$chapterRaw;
			} elseif (function_exists('resolve_pretty_id')) {
				$chapterId = (int)(resolve_pretty_id($link, 'dim_chapters', $chapterRaw) ?? 0);
			}
			if ($chapterId > 0 && ($stmt = mysqli_prepare($link, "SELECT s.season AS season_flag FROM dim_chapters c JOIN dim_seasons s ON s.season_number = c.season_number WHERE c.id = ? LIMIT 1"))) {
				mysqli_stmt_bind_param($stmt, 'i', $chapterId);
				mysqli_stmt_execute($stmt);
				$res = mysqli_stmt_get_result($stmt);
				if ($row = mysqli_fetch_assoc($res)) {
					$seasonFlag = (string)($row['season_flag'] ?? '');
				}
				mysqli_stmt_close($stmt);
			}
		}

		if ($seasonFlag === '0' || $seasonFlag === '1') {
			$dyn = 'seasons_' . $seasonFlag;
			if ($stmt = mysqli_prepare($link, "SELECT p.menu_key FROM dim_menu_items c JOIN dim_menu_items p ON c.parent_id = p.id WHERE c.enabled = 1 AND p.enabled = 1 AND c.dynamic_source = ? LIMIT 1")) {
				mysqli_stmt_bind_param($stmt, 's', $dyn);
				mysqli_stmt_execute($stmt);
				$res = mysqli_stmt_get_result($stmt);
				if ($row = mysqli_fetch_assoc($res)) {
					$menuKey = (string)($row['menu_key'] ?? '');
					mysqli_stmt_close($stmt);
					if ($menuKey !== '') return $menuKey;
				}
				mysqli_stmt_close($stmt);
			}
		}
		$bestMenuKey = null;
		$bestLen = -1;

		$sql = "SELECT p.menu_key, c.href, c.item_type, c.dynamic_source
			FROM dim_menu_items c
			JOIN dim_menu_items p ON c.parent_id = p.id
			WHERE c.enabled = 1 AND p.enabled = 1";
		if ($res = $link->query($sql)) {
			while ($row = $res->fetch_assoc()) {
				$menuKey = (string)($row['menu_key'] ?? '');
				$href = (string)($row['href'] ?? '');
				$type = (string)($row['item_type'] ?? '');
				$dyn = (string)($row['dynamic_source'] ?? '');

				$hrefPath = $href ? parse_url($href, PHP_URL_PATH) : '';
				$hrefPath = $hrefPath ? hg_normalize_path($hrefPath) : '';

				if ($hrefPath !== '' && $hrefPath !== '#' && $hrefPath !== '/' && hg_starts_with($path, $hrefPath)) {
					$len = strlen($hrefPath);
					if ($len > $bestLen && $menuKey !== '') {
						$bestLen = $len;
						$bestMenuKey = $menuKey;
					}
				}

				if ($bestMenuKey === null && $dyn && hg_starts_with($path, '/seasons') && strpos($dyn, 'seasons_') === 0) {
					if ($menuKey !== '') {
						$bestMenuKey = $menuKey;
					}
				}
			}
			$res->free();
		}

		return $bestMenuKey;
	}
?>

<?php
	// =========================
	// Menú desde base de datos (dim_menu_items)
	// =========================
	$useDbMenu = false;
	if (isset($link) && $link) {
		if ($res = $link->query("SHOW TABLES LIKE 'dim_menu_items'")) {
			if ($res->num_rows > 0) $useDbMenu = true;
			$res->free();
		}
	}

	

	$hgCurrentPath = hg_current_path();
	$menuOpenId = $useDbMenu ? hg_menu_open_id_db($link, $hgCurrentPath) : hg_menu_open_id_static($hgCurrentPath, $link ?? null);
	if ($menuOpenId === null) {
		$menuOpenId = hg_menu_open_id_static($hgCurrentPath, $link ?? null);
	}



	if ($useDbMenu) {
		function render_seasons(mysqli $link, string $seasonFlag): void {
			$consulta = "SELECT id, name, season_number AS numero, finished FROM dim_seasons WHERE season LIKE ? ORDER BY order_n";
			if ($stmt = mysqli_prepare($link, $consulta)) {
				mysqli_stmt_bind_param($stmt, 's', $seasonFlag);
				mysqli_stmt_execute($stmt);
				$result = mysqli_stmt_get_result($stmt);
				while ($row = mysqli_fetch_assoc($result)) {
					$idTemporada = (int)$row["id"];
					$numeroTemp = (int)$row["numero"];
					$tituloTemp = (string)$row["name"];
					$tempFinalizada = (int)$row["finished"];

					$nombreTemporada = $numeroTemp . "ª Temporada";
					$claseTemporada = "";
					if ($seasonFlag === '1') {
						$nombreTemporada = $tituloTemp;
					} else {
						if ($numeroTemp < 101) {
							$nombreTemporada = $numeroTemp . "ª Temporada";
						} elseif ($numeroTemp == 999) {
							$nombreTemporada = $tituloTemp;
						} else {
							$numeroTemp -= 100;
							$nombreTemporada = "Inciso " . $numeroTemp . "º";
							$claseTemporada = "renglonMenuInciso";
						}
					}

					if ($tempFinalizada == 1) {
						$historiaCheck = '✔️';
					} elseif ($tempFinalizada == 2) {
						$historiaCheck = '❌';
					} else {
						$historiaCheck = '';
					}

					echo "<a href='/seasons/" . h($idTemporada) . "' title='" . h($tituloTemp) . "'>";
					echo "<div class='renglonMenu {$claseTemporada}'>";
					echo "<div style='float:left!important;margin-left:6px;'>" . h($nombreTemporada) . "</div>";
					echo "<div style='float:right!important;'>" . $historiaCheck . "</div>";
					echo "</div></a>";
				}
				mysqli_stmt_close($stmt);
			}
		}

		function render_menu_children(mysqli $link, int $parentId): void {
			$sql = "SELECT id, label, href, target, item_type, dynamic_source, css_class
					FROM dim_menu_items
					WHERE parent_id = ? AND enabled = 1
					ORDER BY sort_order, id";
			if ($stmt = mysqli_prepare($link, $sql)) {
				mysqli_stmt_bind_param($stmt, 'i', $parentId);
				mysqli_stmt_execute($stmt);
				$res = mysqli_stmt_get_result($stmt);
				while ($row = mysqli_fetch_assoc($res)) {
					$type = (string)($row['item_type'] ?? 'static');
					$label = (string)($row['label'] ?? '');
					$href = (string)($row['href'] ?? '#');
					$target = (string)($row['target'] ?? '_self');
					$css = (string)($row['css_class'] ?? '');
					$dyn = (string)($row['dynamic_source'] ?? '');

					if ($type === 'separator') {
						echo "<div class='renglonMenu menuSeparator'>&nbsp;</div>";
						continue;
					}

					if ($type === 'dynamic') {
						if ($dyn === 'seasons_0') render_seasons($link, '0');
						if ($dyn === 'seasons_1') render_seasons($link, '1');
						continue;
					}

					$targetAttr = ($target === '_blank') ? " target='_blank'" : "";
					echo "<a href='" . h($href) . "'{$targetAttr}><div class='renglonMenu {$css}'>" . h($label) . "</div></a>";
				}
				mysqli_stmt_close($stmt);
			}
		}

		$menuSql = "SELECT id, label, icon, icon_hover, menu_key
					FROM dim_menu_items
					WHERE parent_id IS NULL AND enabled = 1
					ORDER BY sort_order, id";
		$menuItems = [];
		if ($res = $link->query($menuSql)) {
			while ($row = $res->fetch_assoc()) { $menuItems[] = $row; }
			$res->free();
		}

		$menuOpenAttr = $menuOpenId ? " data-menu-open='" . h($menuOpenId) . "'" : "";
		echo "<table class='tmenu'{$menuOpenAttr}>";
		$idx = 0;
		foreach ($menuItems as $m) {
			$idx++;
			$menuId = (string)($m['menu_key'] ?? ('menu' . $idx));
			$label = (string)($m['label'] ?? '');
			$icon = (string)($m['icon'] ?? '');
			$iconHover = (string)($m['icon_hover'] ?? $icon);

			echo "<tr><td><br/>";
			echo "<a onclick=\"MostrarOcultar('{$menuId}')\" id=\"menu{$idx}\" onMouseover=\"Permut(1,'IMG{$idx}');\" onMouseout=\"Permut(0,'IMG{$idx}');\">";
			echo "<img src='" . h($icon) . "' class='menuIcon' align='left' NAME='IMG{$idx}' onLoad=\"preloadPermut(this,'" . h($iconHover) . "');\">";
			echo "</a></td></tr>";

			echo "<tr><td class='sekzo'>";
			$openClass = ($menuOpenId === $menuId) ? ' open' : '';
			echo "<div class='ocultable{$openClass}' id='{$menuId}'>";
			render_menu_children($link, (int)$m['id']);
			echo "</div></td></tr>";
		}
		echo "</table>";
		return;
	}
?>

<?php $menuOpenAttr = $menuOpenId ? " data-menu-open='" . h($menuOpenId) . "'" : ""; ?>
<table class="tmenu"<?= $menuOpenAttr ?>>
    <!-- TEMA -->
	<tr> <!-- INICIO !-->
		<td>
		<br/>
		<a onclick="MostrarOcultar('startMenu')" id="menu0" onMouseover="Permut(1,'IMG0');" onMouseout="Permut(0,'IMG0');">
			<img src="img/menu/index_icon.png" class="menuIcon" align="left" NAME="IMG0" onLoad="preloadPermut(this,'img/menu/index_icon_hover.png');">
		</a>
		</td>
	</tr>
	<tr>
		<td class="sekzo">
			<div class="ocultable<?= ($menuOpenId === 'startMenu') ? ' open' : '' ?>" id="startMenu">
				<a href="/news"><div class="renglonMenu">Noticias</div></a>
				<a href="/search"><div class="renglonMenu">Buscar</div></a>
				<a href="/status"><div class="renglonMenu">Estado</div></a>
				<a href="/about"><div class="renglonMenu">Sobre la web</div></a>
				<a href="https://naufragio-foros.duckdns.org/" target="_blank"><div class="renglonMenu">Foros</div></a>
			</div>
		</td>
	</tr> <!-- INICIO !-->
    <!-- ============================================================================ -->
    <tr> <!-- BIOGRAFIAS -->
        <td>
        <br/>
        <a onclick="MostrarOcultar('bioMenu')" id="menu1" onMouseover="Permut(1,'IMG1');" onMouseout="Permut(0,'IMG1');">  
            <img src="img/menu/bio_icon.png" class="menuIcon" align="left" name="IMG1" onload="preloadPermut(this,'img/menu/bio_icon_hover.png');">
        </a>
        </td>
    </tr>
    <tr>
        <td class='sekzo'>
        <div class="ocultable<?= ($menuOpenId === 'bioMenu') ? ' open' : '' ?>" id="bioMenu">
            <?php
				echo "<a href='/characters'><div class='renglonMenu'>Lista de personajes</div></a>";
				echo "<a href='/characters/types'><div class='renglonMenu'>Biografías por tipo</div></a>";
				echo "<a href='/organizations'><div class='renglonMenu'>Grupos y sociedades</div></a>";
				echo "<a href='/relationship-map/characters'><div class='renglonMenu'>Nebulosa relaciones</div></a>";
				//echo "<a href='?p=list_by_id'><div class='renglonMenu'>Lista por ID</div></a>";
                //echo "<a href='?p=list_by_order'><div class='renglonMenu'>Listas organizadas</div></a>";
				//echo "<a href='?p=list_avatar'><div class='renglonMenu'>Personajes sin avatar</div></a>";
            ?>
        </div>
        </td>
    </tr> <!-- BIOGRAFIAS -->
    <!-- ============================================================================ -->
    <tr> <!-- ARCHIVO -->
        <td>
        <br/>
        <a onclick="MostrarOcultar('archivoMenu')" id="menu2" onMouseover="Permut(1,'IMG2');" onMouseout="Permut(0,'IMG2');">
            <img src="img/menu/archive_icon.png" class="menuIcon" align="left" name="IMG2" onload="preloadPermut(this,'img/menu/archive_icon_hover.png');">
        </a>
        </td>
    </tr>
    <tr>    
        <td class='sekzo'>
            <div class="ocultable<?= ($menuOpenId === 'archivoMenu') ? ' open' : '' ?>" id="archivoMenu">
				<a href="/parties"><div class="renglonMenu">Tramas en curso</div></a>
				<div class='renglonMenu menuSeparator'>&nbsp;</div>
                <?php
                    // Conexión a la base de datos usando MySQLi
                    $consulta = "SELECT id, name, season_number AS numero, finished FROM dim_seasons WHERE season LIKE '0' ORDER BY order_n";
                    $stmt = mysqli_prepare($link, $consulta);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);

                    while ($ResultQuery = mysqli_fetch_assoc($result)) {
                        $idTemporada = htmlspecialchars($ResultQuery["id"]);
                        $numeroTemp = htmlspecialchars($ResultQuery["numero"]);
						$tituloTemp = htmlspecialchars($ResultQuery["name"]);
						$tempFinalizada = $ResultQuery["finished"];
                        $nombreTemporada = $numeroTemp . "ª Temporada";
						$claseTemporada = "";
                        if ($numeroTemp < 101) {
                            $nombreTemporada = $numeroTemp . "ª Temporada";
							//$nombreTemporada = $tituloTemp;
                        } elseif ($numeroTemp == 999) {
                            $nombreTemporada = $tituloTemp;
                        } else {
                            $numeroTemp -= 100;
                            $nombreTemporada = "Inciso " . $numeroTemp . "º";
							//$nombreTemporada = $tituloTemp;
							$claseTemporada = "renglonMenuInciso";
							//"i" . $numeroTemp . "";
                        }
						// Check de marcas
						# Completado
						if ($tempFinalizada == 1) {
							$historiaCheck = '✔️';
						# Abandonado
						} elseif ($tempFinalizada == 2) {
							$historiaCheck = '❌';
						# En curso
						} else {
							$historiaCheck = '';
						}
						// nombreTemporada
                        echo "<a href='/seasons/$idTemporada' title='{$tituloTemp}'>
							<div class='renglonMenu {$claseTemporada}'>
								<div style='float:left!important;margin-left:6px;'>{$nombreTemporada}</div>
								<div style='float:right!important;'>{$historiaCheck}</div>
							</div>
						</a>";
                    }

                    mysqli_stmt_close($stmt);
                ?>
            </div>
        </td>
    </tr> <!-- ARCHIVO -->
    <!-- ============================================================================ -->
    <tr> <!-- HISTORIAS PERSONALES -->
        <td>
        <br/>
        <a onclick="MostrarOcultar('personalesMenu')" id="menu3" onMouseover="Permut(1,'IMG3');" onMouseout="Permut(0,'IMG3');">
            <img src="img/menu/persona_icon.png" class="menuIcon" align="left" name="IMG3" onload="preloadPermut(this,'img/menu/persona_icon_hover.png');">
        </a>
        </td>
    </tr>
    <tr>
        <td class='sekzo'>
            <div class="ocultable<?= ($menuOpenId === 'personalesMenu') ? ' open' : '' ?>" id="personalesMenu">
                <?php
                    $consulta = "SELECT id, name, season_number AS numero, finished FROM dim_seasons WHERE season LIKE '1' ORDER BY order_n";
                    $stmt = mysqli_prepare($link, $consulta);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);

                    while ($ResultQuery = mysqli_fetch_assoc($result)) {
                        $idHistoria = htmlspecialchars($ResultQuery["id"]);
                        $nombreHistoria = htmlspecialchars($ResultQuery["name"]);
						$historiaFinalizada = $ResultQuery["finished"];
						# Completado
						if ($historiaFinalizada == 1) {
							$historiaCheck = '✔️';
						# Abandonado
						} elseif ($historiaFinalizada == 2) {
							$historiaCheck = '❌';
						# En curso
						} else {
							$historiaCheck = '';
						}
                        echo "<a href='/seasons/{$idHistoria}'><div class='renglonMenu'>
							<div style='float:left!important;margin-left:6px;'>{$nombreHistoria}</div>
							<div style='float:right!important;'>{$historiaCheck}</div>
						</div></a>";
                    }

                    mysqli_stmt_close($stmt);
                ?>
            </div>
        </td>
    </tr> <!-- HISTORIAS PERSONALES -->
    <!-- ============================================================================ -->
	<tr> <!-- TRASFONDO !-->
		<td>
		<br/>
		<a onclick="MostrarOcultar('loreMenu')" id="menu4" onMouseover="Permut(1,'IMG4');" onMouseout="Permut(0,'IMG4');">
			<img src="img/menu/lore_icon.png" class="menuIcon" align="left" NAME="IMG4" onLoad="preloadPermut(this,'img/menu/lore_icon_hover.png');">
		</a>
		</td>
	</tr>
	<tr>
		<td class="sekzo">
			<div class="ocultable<?= ($menuOpenId === 'loreMenu') ? ' open' : '' ?>" id="loreMenu">
				<a href="/documents"><div class="renglonMenu">Lista de Documentos</div></a>
				<a href="/timeline"><div class="renglonMenu">Línea Temporal</div></a>
				<a href="/maps"><div class="renglonMenu">Mapas</div></a>
				<a href="/music"><div class="renglonMenu">Banda sonora</div></a>
				<a href="/gallery"><div class="renglonMenu">Galería de Imágenes</div></a>
			</div>
		</td>
	</tr> <!-- TRASFONDO !-->
	<!-- ============================================================================ -->
	 <!-- Sigue con el mismo patrón para el resto de secciones -->
	<tr> <!-- MECÁNICAS !-->
		<td>
		<br/>
		<a onclick="MostrarOcultar('systemMenu')" id="menu5" onMouseover="Permut(1,'IMG5');" onMouseout="Permut(0,'IMG5');">
			<img src="img/menu/system_icon.png" class="menuIcon" align="left" NAME="IMG5" onLoad="preloadPermut(this,'img/menu/system_icon_hover.png');">
		</a>
		</td>
	</tr>
	<tr>
		<td class='sekzo'>
		<div class="ocultable<?= ($menuOpenId === 'systemMenu') ? ' open' : '' ?>" id="systemMenu">
			<a href="/systems"><div class="renglonMenu">Seres sobrenaturales</div></a>
			<a href="/rules/traits"><div class="renglonMenu">Lista de Rasgos</div></a>
			<a href="/rules/merits-flaws"><div class="renglonMenu">Méritos y Defectos</div></a>
			<a href="/inventory"><div class="renglonMenu">Inventario</div></a>
			<a href="/rules/archetypes"><div class="renglonMenu">Personalidades</div></a>
			<a href="/rules/maneuvers"><div class="renglonMenu">Maniobras de pelea</div></a>
		</div>
		</td>
	</tr> <!-- MECÁNICAS !-->
	<!-- ============================================================================ !-->
	<tr> <!-- PODERES !-->
		<td>
		<br/>
		<a onclick="MostrarOcultar('powersMenu')" id="menu6" onMouseover="Permut(1,'IMG6');" onMouseout="Permut(0,'IMG6');">
			<img src="img/menu/powers_icon.png" class="menuIcon" align="left" NAME="IMG6" onLoad="preloadPermut(this,'img/menu/powers_icon_hover.png');">
		</a>
		</td>
	</tr>
	<tr>
		<td class="sekzo">
			<div class="ocultable<?= ($menuOpenId === 'powersMenu') ? ' open' : '' ?>" id="powersMenu">
				<a href="/powers/gifts"><div class="renglonMenu">Dones</div></a>
				<a href="/powers/rites"><div class="renglonMenu">Rituales</div></a>
				<a href="/powers/totems"><div class="renglonMenu">T&oacute;tems</div></a>
				<a href="/powers/disciplines"><div class="renglonMenu">Disciplinas</a></div>
			</div>
		</td>
	</tr> <!-- PODERES !-->
	<tr> <!-- HERRAMIENTAS !-->
		<td>
		<br/>
		<a onclick="MostrarOcultar('toolsMenu')" id="menu7" onMouseover="Permut(1,'IMG7');" onMouseout="Permut(0,'IMG7');">
			<img src="img/menu/tools_icon.png" class="menuIcon" align="left" NAME="IMG7" onLoad="preloadPermut(this,'img/menu/tools_icon_hover.png');">
		</a>
		</td>
	</tr>
	<tr>
		<td class="sekzo">
			<div class="ocultable<?= ($menuOpenId === 'toolsMenu') ? ' open' : '' ?>" id="toolsMenu">
				<a href="/tools/dice"><div class="renglonMenu">Tiradados</div></a>
				<a href="/tools/csp"><div class="renglonMenu">Tablón de Mensajes</div></a>
				<a href="/seasons/analysis"><div class="renglonMenu ">Análisis temporadas</div></a>
				<a href="/tools/garou-name-generator?n=20"><div class="renglonMenu">Generador Nombres</div></a>
				<a href="crop.html" target="_blank"><div class="renglonMenu">Recortador imágenes</div></a>
			</div>
		</td>
	</tr> <!-- HERRAMIENTAS !-->
	<!-- ============================================================================ !-->
    <!-- Pie del menú -->
</table>
