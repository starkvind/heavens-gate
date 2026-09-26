<?php
	if (!isset($link) || !($link instanceof mysqli)) {
		include(__DIR__ . '/../helpers/db_connection.php');
	}
	require_once __DIR__ . '/../domains/navigation/queries.php';
?>


<?php
	if (!function_exists('h')) {
		function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
	}

	function hg_current_path(): string {
		$uri = $_SERVER['REQUEST_URI'] ?? '/';
		$path = strtok($uri, '?');
		if ($path === false || $path === '') return '/';


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

		if ($path === '/' || hg_starts_with($path, '/home') || hg_starts_with($path, '/news') || hg_starts_with($path, '/search') || hg_starts_with($path, '/status') || hg_starts_with($path, '/about')) {
			return 'startMenu';
		}
		if (hg_starts_with($path, '/characters') || hg_starts_with($path, '/organizations') || hg_starts_with($path, '/groups') || hg_starts_with($path, '/relationship-map')) {
			return 'bioMenu';
		}
		if (hg_starts_with($path, '/parties') || hg_starts_with($path, '/seasons') || hg_starts_with($path, '/chapters')) {
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

		if ($path === '/' || hg_starts_with($path, '/home')) {
			return 'startMenu';
		}

		$bestMenuKey = null;
		$bestLen = -1;
		foreach (hg_navigation_fetch_route_candidates($link) as $row) {
			$menuKey = (string)($row['menu_key'] ?? '');
			$href = (string)($row['href'] ?? '');
			$hrefPath = $href ? (string)parse_url($href, PHP_URL_PATH) : '';
			$hrefPath = $hrefPath ? hg_normalize_path($hrefPath) : '';

			if ($hrefPath !== '' && $hrefPath !== '#' && $hrefPath !== '/' && hg_starts_with($path, $hrefPath)) {
				$len = strlen($hrefPath);
				if ($len > $bestLen && $menuKey !== '') {
					$bestLen = $len;
					$bestMenuKey = $menuKey;
				}
			}
		}

		return $bestMenuKey;
	}?>

<?php
	// =========================
	// MenÃº desde base de datos (dim_menu_items)
	// =========================
	$useDbMenu = isset($link) && ($link instanceof mysqli);

	

	$hgCurrentPath = hg_current_path();
	$menuOpenId = $useDbMenu ? hg_menu_open_id_db($link, $hgCurrentPath) : hg_menu_open_id_static($hgCurrentPath, $link ?? null);
	if ($menuOpenId === null) {
		$menuOpenId = hg_menu_open_id_static($hgCurrentPath, $link ?? null);
	}



	if ($useDbMenu) {
		function hg_menu_get_children(mysqli $link, int $parentId): array {
			return hg_navigation_fetch_children($link, $parentId);
		}

		function render_seasons(mysqli $link, string $seasonFlag): void {
			$lastGroup = '';
			foreach (hg_navigation_season_items($link, $seasonFlag === '1') as $row) {
				$tituloTemp = (string)($row['name'] ?? '');
				$seasonKind = (string)($row['season_kind'] ?? 'temporada');

				if ($seasonFlag === '0' && $seasonKind !== $lastGroup) {
					if ($lastGroup !== '') {
						echo "<div class='renglonMenu menuSeparator'>&nbsp;</div>";
					}
					$lastGroup = $seasonKind;
				}

				$claseTemporada = $seasonKind === 'inciso' ? 'renglonMenuInciso' : '';
				$tempFinalizada = (int)($row['finished'] ?? 0);
				$historiaCheck = $tempFinalizada === 1 ? '&#10004;' : ($tempFinalizada === 2 ? '&#10006;' : '');
				$statusClass = $historiaCheck === '' ? ' menu-season-row--nostatus' : '';

				echo "<a href='" . h($row['href'] ?? '/seasons') . "' title='" . h($tituloTemp) . "'>";
				echo "<div class='renglonMenu menu-season-row {$claseTemporada}{$statusClass}'>";
				echo "<div class='menu-season-label'>" . h($row['label'] ?? $tituloTemp) . "</div>";
				if ($historiaCheck !== '') {
					echo "<div class='menu-season-status'>{$historiaCheck}</div>";
				}
				echo "</div></a>";
			}
		}
		function render_menu_children(mysqli $link, int $parentId, string $parentMenuKey = '', ?array $rows = null): void {
			$rows = $rows ?? hg_menu_get_children($link, $parentId);
			if (!empty($rows)) {
				if ($parentMenuKey === 'startMenu') {
					$hasHomeLink = false;
					foreach ($rows as $row) {
						$href = (string)($row['href'] ?? '');
						$hrefPath = $href ? (string)parse_url($href, PHP_URL_PATH) : '';
						$hrefPath = $hrefPath ? hg_normalize_path($hrefPath) : '';
						if ($hrefPath === '/' || $hrefPath === '/home') {
							$hasHomeLink = true;
							break;
						}
					}

					if (!$hasHomeLink) {
						echo "<a href='/'><div class='renglonMenu'>Inicio</div></a>";
					}
				}

				foreach ($rows as $row) {
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
						elseif ($dyn === 'seasons_1') render_seasons($link, '1');
						continue;
					}

					$targetAttr = ($target === '_blank') ? " target='_blank'" : "";
					echo "<a href='" . h($href) . "'{$targetAttr}><div class='renglonMenu {$css}'>" . h($label) . "</div></a>";
				}
			}
		}

		$menuItems = hg_navigation_fetch_parents($link);
		$menuOpenAttr = $menuOpenId ? " data-menu-open='" . h($menuOpenId) . "'" : "";
		echo "<table class='tmenu'{$menuOpenAttr}>";
		$idx = 0;
		foreach ($menuItems as $m) {
			$idx++;
			$menuId = (string)($m['menu_key'] ?? ('menu' . $idx));
			$label = (string)($m['label'] ?? '');
			$icon = (string)($m['icon'] ?? '');
			$iconHover = (string)($m['icon_hover'] ?? $icon);
			$childRows = hg_menu_get_children($link, (int)$m['id']);

			echo "<tr><td><br/>";
			echo "<a onclick=\"MostrarOcultar('{$menuId}')\" id=\"menu{$idx}\" onMouseover=\"Permut(1,'IMG{$idx}');\" onMouseout=\"Permut(0,'IMG{$idx}');\">";
			echo "<img src='" . h($icon) . "' class='menuIcon' align='left' NAME='IMG{$idx}' onLoad=\"preloadPermut(this,'" . h($iconHover) . "');\">";
			echo "</a></td></tr>";

			echo "<tr><td class='sekzo'>";
			$openClass = ($menuOpenId === $menuId) ? ' open' : '';
			echo "<div class='ocultable{$openClass}' id='{$menuId}'>";
			render_menu_children($link, (int)$m['id'], $menuId, $childRows);
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
			<img src="img/menu/index_icon.webp" class="menuIcon" align="left" NAME="IMG0" onLoad="preloadPermut(this,'img/menu/index_icon_hover.webp');">
		</a>
		</td>
	</tr>
	<tr>
		<td class="sekzo">
			<div class="ocultable<?= ($menuOpenId === 'startMenu') ? ' open' : '' ?>" id="startMenu">
				<a href="/"><div class="renglonMenu">Inicio</div></a>
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
            <img src="img/menu/bio_icon.webp" class="menuIcon" align="left" name="IMG1" onload="preloadPermut(this,'img/menu/bio_icon_hover.webp');">
        </a>
        </td>
    </tr>
    <tr>
        <td class='sekzo'>
        <div class="ocultable<?= ($menuOpenId === 'bioMenu') ? ' open' : '' ?>" id="bioMenu">
            <?php
				echo "<a href='/characters'><div class='renglonMenu'>Lista de personajes</div></a>";
				echo "<a href='/characters/types'><div class='renglonMenu'>BiografÃ­as por tipo</div></a>";
				echo "<a href='/organizations'><div class='renglonMenu'>Grupos y sociedades</div></a>";
				echo "<a href='/relationship-map/characters'><div class='renglonMenu'>Nebulosa relaciones</div></a>";
            ?>
        </div>
        </td>
    </tr> <!-- BIOGRAFIAS -->
    <!-- ============================================================================ -->
    <tr> <!-- ARCHIVO -->
        <td>
        <br/>
        <a onclick="MostrarOcultar('archivoMenu')" id="menu2" onMouseover="Permut(1,'IMG2');" onMouseout="Permut(0,'IMG2');">
            <img src="img/menu/archive_icon.webp" class="menuIcon" align="left" name="IMG2" onload="preloadPermut(this,'img/menu/archive_icon_hover.webp');">
        </a>
        </td>
    </tr>
    <tr>    
        <td class='sekzo'>
            <div class="ocultable<?= ($menuOpenId === 'archivoMenu') ? ' open' : '' ?>" id="archivoMenu">
				<a href="/parties"><div class="renglonMenu">Tramas en curso</div></a>
				<div class='renglonMenu menuSeparator'>&nbsp;</div>
                <?php
                    // ConexiÃ³n a la base de datos usando MySQLi
                    $consulta = "SELECT id, name, season_number AS numero, finished, season_kind FROM dim_seasons WHERE season_kind IN ('temporada','inciso','especial') ORDER BY FIELD(season_kind, 'temporada','inciso','especial'), sort_order";
                    $stmt = mysqli_prepare($link, $consulta);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);

                    while ($ResultQuery = mysqli_fetch_assoc($result)) {
                        $idTemporada = htmlspecialchars($ResultQuery["id"]);
                        $numeroTemp = htmlspecialchars($ResultQuery["numero"]);
						$tituloTemp = htmlspecialchars($ResultQuery["name"]);
						$tempFinalizada = $ResultQuery["finished"];
                        $nombreTemporada = $numeroTemp . "Âª Temporada";
						$claseTemporada = "";
                        if ($numeroTemp < 101) {
                            $nombreTemporada = $numeroTemp . "Âª Temporada";
							//$nombreTemporada = $tituloTemp;
                        } elseif ($numeroTemp == 999) {
                            $nombreTemporada = $tituloTemp;
                        } else {
                            $numeroTemp -= 100;
                            $nombreTemporada = "Inciso " . $numeroTemp . "Âº";
							//$nombreTemporada = $tituloTemp;
							$claseTemporada = "renglonMenuInciso";
							//"i" . $numeroTemp . "";
                        }
						// Check de marcas
						# Completado
						if ($tempFinalizada == 1) {
							$historiaCheck = '&#10004;';
						# Abandonado
						} elseif ($tempFinalizada == 2) {
							$historiaCheck = '&#10006;';
						# En curso
						} else {
							$historiaCheck = '';
						}
						// nombreTemporada
						$hrefTemporada = function_exists('pretty_url')
							? pretty_url($link, 'dim_seasons', '/seasons', (int)$idTemporada)
							: ('/seasons/' . (int)$idTemporada);
						$statusClass = ($historiaCheck === '') ? ' menu-season-row--nostatus' : '';
                        echo "<a href='" . h($hrefTemporada) . "' title='{$tituloTemp}'>
							<div class='renglonMenu menu-season-row {$claseTemporada}{$statusClass}'>
								<div class='menu-season-label'>{$nombreTemporada}</div>";
						if ($historiaCheck !== '') {
							echo "<div class='menu-season-status'>{$historiaCheck}</div>";
						}
						echo "	</div>
						</a>";
                    }

                    mysqli_stmt_close($stmt);
                ?>
            </div>
        </td>
    </tr> <!-- ARCHIVO -->
    <!-- ============================================================================ -->
	<tr> <!-- TRASFONDO !-->
		<td>
		<br/>
		<a onclick="MostrarOcultar('loreMenu')" id="menu4" onMouseover="Permut(1,'IMG4');" onMouseout="Permut(0,'IMG4');">
			<img src="img/menu/lore_icon.webp" class="menuIcon" align="left" NAME="IMG4" onLoad="preloadPermut(this,'img/menu/lore_icon_hover.webp');">
		</a>
		</td>
	</tr>
	<tr>
		<td class="sekzo">
			<div class="ocultable<?= ($menuOpenId === 'loreMenu') ? ' open' : '' ?>" id="loreMenu">
				<a href="/documents"><div class="renglonMenu">Lista de Documentos</div></a>
				<a href="/timeline"><div class="renglonMenu">LÃ­nea Temporal</div></a>
				<a href="/maps"><div class="renglonMenu">Mapas</div></a>
				<a href="/music"><div class="renglonMenu">Banda sonora</div></a>
				<a href="/gallery"><div class="renglonMenu">GalerÃ­a de ImÃ¡genes</div></a>
			</div>
		</td>
	</tr> <!-- TRASFONDO !-->
	<!-- ============================================================================ -->
	 <!-- Sigue con el mismo patrÃ³n para el resto de secciones -->
	<tr> <!-- MECÃNICAS !-->
		<td>
		<br/>
		<a onclick="MostrarOcultar('systemMenu')" id="menu5" onMouseover="Permut(1,'IMG5');" onMouseout="Permut(0,'IMG5');">
			<img src="img/menu/system_icon.webp" class="menuIcon" align="left" NAME="IMG5" onLoad="preloadPermut(this,'img/menu/system_icon_hover.webp');">
		</a>
		</td>
	</tr>
	<tr>
		<td class='sekzo'>
		<div class="ocultable<?= ($menuOpenId === 'systemMenu') ? ' open' : '' ?>" id="systemMenu">
			<a href="/systems"><div class="renglonMenu">Seres sobrenaturales</div></a>
			<a href="/rules/traits"><div class="renglonMenu">Lista de Rasgos</div></a>
			<a href="/rules/actions"><div class="renglonMenu">Acciones</div></a>
			<a href="/rules/merits-flaws"><div class="renglonMenu">MÃ©ritos y Defectos</div></a>
			<a href="/inventory"><div class="renglonMenu">Inventario</div></a>
			<a href="/rules/archetypes"><div class="renglonMenu">Personalidades</div></a>
			<a href="/rules/maneuvers"><div class="renglonMenu">Maniobras de pelea</div></a>
		</div>
		</td>
	</tr> <!-- MECÃNICAS !-->
	<!-- ============================================================================ !-->
	<tr> <!-- PODERES !-->
		<td>
		<br/>
		<a onclick="MostrarOcultar('powersMenu')" id="menu6" onMouseover="Permut(1,'IMG6');" onMouseout="Permut(0,'IMG6');">
			<img src="img/menu/powers_icon.webp" class="menuIcon" align="left" NAME="IMG6" onLoad="preloadPermut(this,'img/menu/powers_icon_hover.webp');">
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
			<img src="img/menu/tools_icon.webp" class="menuIcon" align="left" NAME="IMG7" onLoad="preloadPermut(this,'img/menu/tools_icon_hover.webp');">
		</a>
		</td>
	</tr>
	<tr>
		<td class="sekzo">
			<div class="ocultable<?= ($menuOpenId === 'toolsMenu') ? ' open' : '' ?>" id="toolsMenu">
				<a href="/tools/dice"><div class="renglonMenu">Tiradados</div></a>
				<a href="/tools/forum-avatar"><div class="renglonMenu">Creador Mensajes Foro</div></a>
				<a href="/tools/forum-topic-viewer"><div class="renglonMenu">Visor de temas foro</div></a>
				<a href="/tools/csp"><div class="renglonMenu">TablÃ³n de Mensajes</div></a>
				<a href="/seasons/analysis"><div class="renglonMenu ">AnÃ¡lisis temporadas</div></a>
				<a href="/tools/garou-name-generator?n=20"><div class="renglonMenu">Generador Nombres</div></a>
				<a href="crop.html" target="_blank"><div class="renglonMenu">Recortador imÃ¡genes</div></a>
			</div>
		</td>
	</tr> <!-- HERRAMIENTAS !-->
	<!-- ============================================================================ !-->
    <!-- Pie del menÃº -->
</table>

