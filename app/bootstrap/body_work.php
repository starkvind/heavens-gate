<?php
// ===================== //
// ✨ Validación de entradas
// ===================== //
$routeKey = htmlspecialchars($_GET['p'] ?? '');
$routeParam = htmlspecialchars($_GET['t'] ?? '');
$pageTitle = $pageTitle ?? "Heaven's Gate";
$pageSect = $pageSect ?? null;
$metaTitle = $metaTitle ?? null;
$metaDescription = $metaDescription ?? null;
$metaImage = $metaImage ?? null;
$metaType = $metaType ?? null;

include_once("app/helpers/pretty.php");

// Normaliza parámetros pretty-id a id numérico y redirige a slug cuando aplica
function normalize_pretty_request(mysqli $link, string $route): void {
    $paramMap = [
        'muestrabio'   => ['b', 'fact_characters', '/characters'],
        'biogroup'     => ['t', 'dim_character_types', '/characters/type'],
        'seegroup'     => ['b', null, null],
        'seeplayer'    => ['b', 'dim_players', '/players'],
        'verdoc'       => ['b', 'fact_docs', '/documents'],
        'verobj'       => ['b', 'fact_items', '/inventory/items'],
        'seeitem'      => ['b', 'fact_items', '/inventory/item'],
        'muestradon'   => ['b', 'fact_gifts', '/powers/gift'],
        'tipodon'      => ['b', 'dim_gift_types', '/powers/gift/type'],
        'seerite'      => ['b', 'fact_rites', '/powers/rite'],
        'tiporite'     => ['b', 'dim_rite_types', '/powers/rite/type'],
        'muestratotem' => ['b', 'dim_totems', '/powers/totem'],
        'tipototm'     => ['b', 'dim_totem_types', '/powers/totem/type'],
        'muestradisc'  => ['b', 'fact_discipline_powers', '/powers/discipline'],
        'tipodisc'     => ['b', 'dim_discipline_types', '/powers/discipline/type'],
        'verrasgo'     => ['b', 'dim_traits', '/rules/traits'],
        'vermyd'       => ['b', 'dim_merits_flaws', '/rules/merits-flaws'],
        'verarch'      => ['b', 'dim_archetypes', '/rules/archetypes'],
        'vermaneu'     => ['b', 'fact_combat_maneuvers', '/rules/maneuvers'],
        'sistemas'     => ['b', 'dim_systems', '/systems'],
        'verforma'     => ['b', 'dim_forms', '/systems/form'],
        'versistdetalle' => ['b', 'dim_breeds', '/systems/detail'],
        'seechapter'   => ['t', 'dim_chapters', '/chapters'],
        'temp'         => ['t', 'dim_seasons', '/seasons'],
        'maps_detail'  => ['id', 'fact_map_pois', '/maps/poi'],
    ];

    if (!isset($paramMap[$route])) return;
    [$param, $table, $base] = $paramMap[$route];

    if ($route === 'seegroup' && isset($_GET['t'], $_GET['b'])) {
        $t = (string)$_GET['t'];
        if ($t === '1') {
            $table = 'dim_groups';
            $base = '/groups';
        } elseif ($t === '2') {
            $table = 'dim_organizations';
            $base = '/organizations';
        }
    }

    if ($route === 'versistdetalle' && isset($_GET['tc'], $_GET['b'])) {
        $tc = (string)$_GET['tc'];
        if ($tc === '1') $table = 'dim_breeds';
        elseif ($tc === '2') $table = 'dim_auspices';
        elseif ($tc === '3') $table = 'dim_tribes';
        elseif ($tc === '4') $table = 'fact_misc_systems';
        $base = "/systems/detail/$tc";
        $param = 'b';
    }

    if (!$table || !$base || !isset($_GET[$param])) return;

    $raw = (string)$_GET[$param];
    $resolved = resolve_pretty_id($link, $table, $raw);
    if ($resolved !== null) {
        $_GET[$param] = (string)$resolved;
    }

    if (preg_match('/^\d+$/', $raw)) {
        $pretty = get_pretty_id($link, $table, (int)$raw);
        if ($pretty) {
            $target = rtrim($base, '/') . '/' . rawurlencode($pretty);
            if ($route === 'seegroup') {
                $target = rtrim($base, '/') . '/' . rawurlencode($pretty);
            }
            if ($route === 'versistdetalle') {
                $target = rtrim($base, '/') . '/' . rawurlencode($pretty);
            }
            header("Location: $target", true, 301);
            exit;
        }
    }
}

// ===================== //
// ✨ Función auxiliar para metadatos SEO
// ===================== //
function setMetaTitle($custom = null) {
	global $pageTitle, $pageSect, $pageTitle2;
	$parts = [];
	if ($custom) $parts[] = $custom;
	if (!empty($pageSect)) $parts[] = $pageTitle2;
	if (!empty($pageTitle2)) $parts[] = $pageSect;
	$parts[] = $pageTitle;
	return implode(" | ", $parts);
}

function meta_excerpt($html, $maxLen = 160) {
	$txt = strip_tags((string)$html);
	$txt = html_entity_decode($txt, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	$txt = trim(preg_replace('/\s+/', ' ', $txt));
	if ($txt === '') return '';
	if (function_exists('mb_strlen') && function_exists('mb_substr')) {
		return (mb_strlen($txt, 'UTF-8') > $maxLen) ? mb_substr($txt, 0, $maxLen, 'UTF-8') : $txt;
	}
	return (strlen($txt) > $maxLen) ? substr($txt, 0, $maxLen) : $txt;
}

function setMetaFromPage($title = null, $description = null, $image = null, $type = null) {
	global $metaTitle, $metaDescription, $metaImage, $metaType;
	if (!empty($title)) $metaTitle = $title;
	if (!empty($description)) $metaDescription = $description;
	if (!empty($image)) $metaImage = $image;
	if (!empty($type)) $metaType = $type;
}

function normalize_meta_image($image, $baseURL) {
	$img = (string)$image;
	if ($img === '') return $img;
	if (preg_match('#^https?://#i', $img)) return $img;
	return rtrim($baseURL, '/') . '/' . ltrim($img, '/');
}

function setMetaTags($route, $pageURL = '', $baseURL = 'https://naufragio-heavensgate.duckdns.org') {
	global $metaTitle, $metaDescription, $metaImage, $metaType;
    $title = "Heaven's Gate";
    $description = "Heaven's Gate es una campaña de rol ambientada en el Mundo de Tinieblas. Explora biografías, poderes, clanes y más.";
	$image = $baseURL . "/img/og/og_image.jpg"; // ahora correcto
	$type = "website";

    switch ($route) {
        case 'news':
            $title = "Noticias - Heaven's Gate";
            $description = "Últimas novedades de la campaña Heaven's Gate.";
            break;
        case 'players':
            $title = "Jugadores - Heaven's Gate";
            $description = "Listado de jugadores del universo Heaven's Gate.";
            break;
		case 'temp':
		case 'seechapter':
		case 'temp_analisis':
			$title = "Temporadas - Heaven's Gate";
			$description = "Consulta las temporadas y capítulos de Heaven's Gate.";
			$image = $baseURL . "/img/og/og_image_temp.jpg"; // ahora correcto
			break;
        case 'bios':
		case 'biogroup':
		case 'muestrabio':
            $title = "Biografías - Heaven's Gate";
            $description = "Explora las biografías de los personajes clave de Heaven's Gate.";
			$image = $baseURL . "/img/og/og_image_bio.jpg"; // ahora correcto
            break;
        case 'nebula_clan':
        case 'nebula_character':
            $title = "Nebulosa de relaciones - Heaven's Gate";
            $description = "Visualiza las relaciones entre clanes, manadas y personajes en Heaven's Gate.";
            break;
        case 'doc':
            $title = "Documentación - Heaven's Gate";
            $description = "Accede a la documentación oficial y trasfondo de la campaña.";
            break;
        case 'inv':
        case 'verobj':
		case 'seeitem':
		case 'listaobj':
            $title = "Inventario - Heaven's Gate";
            $description = "Consulta los objetos y artefactos disponibles en la campana.";
			$image = $baseURL . "/img/og/og_image_monster.jpg";
            break;
        case 'sistemas':
            $title = "Sistemas de juego | Heaven's Gate";
            $description = "Explora sistemas, formas y mecánicas empleadas en la campaña.";
            break;
        case 'powers':
            $title = "Poderes | Heaven's Gate";
            $description = "Resumen y acceso a los poderes disponibles en Heaven's Gate.";
			$image = $baseURL . "/img/og/og_image_power.jpg";
            break;
        case 'dones':
		case 'tipodon':
		case 'muestradon':
		case 'listadones':
		case 'fulldon':
		//, ritos, disciplinas y poderes
            $title = "Dones | Heaven's Gate";
            $description = "Listado de dones usados en Heaven's Gate.";
			$image = $baseURL . "/img/og/og_image_power.jpg"; // ahora correcto
            break;
        case 'rites':
		case 'tiporite':
		case 'seerite':
		case 'ritelist':
		case 'fullrite':
		//, ritos, disciplinas y poderes
            $title = "Rituales | Heaven's Gate";
            $description = "Listado de ritos usados en Heaven's Gate.";
			$image = $baseURL . "/img/og/og_image_power.jpg"; // ahora correcto
            break;
        case 'totems':
		case 'tipototm':
		case 'muestratotem':
		case 'listatotems':
		//, ritos, disciplinas y poderes
            $title = "Totems | Heaven's Gate";
            $description = "Listado de totems (espiritus guia) de Heaven's Gate.";
			$image = $baseURL . "/img/og/og_image_monster.jpg";
            break;
        case 'disciplinas':
		case 'tipodisc':
		case 'muestradisc':
		//, ritos,  y poderes
            $title = "Disciplinas | Heaven's Gate";
            $description = "Listado de Disciplinas vampíricas utilizadas en Heaven's Gate.";
			$image = $baseURL . "/img/og/og_image_power.jpg"; // ahora correcto
            break;
		case 'ost':
		//, ritos, disciplinas y poderes
            $title = "Banda sonora | Heaven's Gate";
            $description = "Lista de temas musicales usados en Heaven's Gate.";
			$image = $baseURL . "/img/og/og_image_bio.jpg"; // ahora correcto
            break;
		case 'dados':
            $title = "Tiradados | Heaven's Gate";
            $description = "Cómodo tirador de dados d10 para partidas de foro.";
			$image = $baseURL . "/img/og/og_image_bio.jpg"; // ahora correcto
            break;
		case 'gallery':
            $title = "Galería de imágenes | Heaven's Gate";
            $description = "Lista de imágenes utilizadas en la campaña.";
			$image = $baseURL . "/img/og/og_image_bio.jpg"; // ahora correcto
            break;
		case 'maps':
		case 'maps_detail':
            $title = "Mapas | Heaven's Gate";
            $description = "Mapas interactivos sobre lugares de interés en la campaña.";
			$image = $baseURL . "/img/og/og_image_power.jpg"; // ahora correcto
            break;
		case 'plots':
            $title = "Equipos activos | Heaven's Gate";
            $description = "Lista de personajes en tramas abiertas y su estado, su salud y sus recursos.";
			$image = $baseURL . "/img/og/og_image_bio.jpg"; // ahora correcto
            break;
        default:
            // Fallback genérico
            break;
    }

	if (!empty($metaTitle)) $title = $metaTitle;
	if (!empty($metaDescription)) $description = $metaDescription;
	if (!empty($metaImage)) $image = normalize_meta_image($metaImage, $baseURL);
	if (!empty($metaType)) $type = $metaType;

    echo "<meta name=\"description\" content=\"{$description}\">\n";
    echo "<meta property=\"og:title\" content=\"{$title}\">\n";
    echo "<meta property=\"og:description\" content=\"{$description}\">\n";
    echo "<meta property=\"og:type\" content=\"{$type}\">\n";
//    echo "<meta property=\"og:url\" content=\"https://heavensgate.zapto.org{$_SERVER['REQUEST_URI']}\">\n";
	echo '<meta property="og:url" content="' . htmlspecialchars($pageURL) . '">';
    echo "<meta property=\"og:image\" content=\"{$image}\">\n";
    echo "<meta name=\"twitter:card\" content=\"summary_large_image\">\n";
    echo "<meta name=\"twitter:title\" content=\"{$title}\">\n";
    echo "<meta name=\"twitter:description\" content=\"{$description}\">\n";
    echo "<meta name=\"twitter:image\" content=\"{$image}\">\n";
}

// ===================== //
// ✨ Enrutador por secciones
// ===================== //
$routes = [
	// 🌍 Principal
	'news'       => ['app/controllers/main/main_news.php', 'Noticias'],
	'status'     => ['app/controllers/main/main_status.php', 'Estado'],
	'about'      => ['app/controllers/main/main_about.php', 'Acerca de...'],
	'biblio'     => ['app/controllers/main/main_biblio.php', 'Bibliografía'],
	'busq'       => ['app/controllers/main/main_search_form.php', 'Búsqueda'],
	'busk'       => ['app/controllers/main/main_search_result.php', 'Resultado de la búsqueda'],
	'talim'      => ['app/controllers/admin/admin_main.php', 'Administración'],
	'error404'   => ['app/controllers/error404.php', 'Error'],

	// 🗃️ Temporadas
	'temp'            => ['app/controllers/chapt/chapt_archivo.php', 'Temporadas'],
	'seechapter'      => ['app/controllers/chapt/chapt_archivo_page.php', 'Capítulos'],
	'temp_analisis'   => ['app/controllers/chapt/chapt_archivo_analisis.php', 'Análisis asistencia'],

	// Plots
	'party'		   => ['app/controllers/main/main_parties.php', 'Equipos activos'],

	// 🧬 Biografías
	'bios'         => ['app/controllers/bio/bio_list.php', 'Biografías'],
	'biogroup'     => ['app/controllers/bio/bio_group.php', 'Biografías por Grupo'],
	'muestrabio'   => ['app/controllers/bio/bio_page2.php', 'Biografía'],
	'listgroups'   => ['app/controllers/bio/bio_pack_list.php', null],
	'seegroup'     => ['app/controllers/bio/bio_pack_page.php', null],
	/*
	'list_by_order'=> ['app/controllers/bio/bio_list_by_order.php', null],
	'list_by_id'   => ['app/controllers/bio/bio_list_id.php', null],
	'list_avatar'  => ['app/controllers/bio/bio_list_noavatar.php', null],
	*/
	'list_table'   => ['app/controllers/bio/bio_table.php', null],
	'nebula_clan'  => ['app/controllers/bio/bio_reltree_clans.php', 'Nebulosa de relaciones'],
	'nebula_character' => ['app/controllers/bio/bio_reltree_characters.php', 'Nebulosa de relaciones'],
	'nebula_groups' => ['app/controllers/bio/bio_reltree_groups.php', 'Nebulosa de relaciones'],

	// 📚 Documentación
	'listadocs' => ['app/controllers/docs/docs_table.php', null],
	'verdoc'    => ['app/controllers/docs/docs_page.php', null],
	'rules'     => ['app/controllers/docs/rules_home.php', null],

	// 🎒 Inventario
	'inv'     => ['app/controllers/docs/item_table.php', null],
	'seeitem' => ['app/controllers/docs/item_page.php', null],
	'imgz'    => ['app/controllers/tool/img_board.php', null],
	'listaobj'=> ['app/controllers/docs/item_table.php', null],
	'verobj'  => ['app/controllers/docs/item_page.php', null],

	// ⚙️ Sistemas
	'listasistemas'  => ['app/controllers/syst/system_table.php', null],
	'sistemas'  	 => ['app/controllers/syst/system_page.php', null],
	'versistdetalle' => ['app/controllers/syst/system_page_specific.php', null],
	'verforma'  	 => ['app/controllers/syst/system_form.php', null],

	// 🧠 Rasgos
	'listarasgos' => ['app/controllers/docs/traits_table.php', null],
	'verrasgo'    => ['app/controllers/docs/traits_page.php', null],
	'maneuver'    => ['app/controllers/docs/maneuver_list.php', null],
	'vermaneu'    => ['app/controllers/docs/maneuver_page.php', null],
	'arquetip'    => ['app/controllers/docs/arche_list.php', null],
	'verarch'     => ['app/controllers/docs/arche_page.php', null],

	// 🧬 Méritos y fallos
	'listamyd' => ['app/controllers/docs/merfla_table.php', null],
	'vermyd'   => ['app/controllers/docs/merfla_page.php', null],

	// 🔮 Poderes
	'powers'       => ['app/controllers/pwrs/powers_home.php', null],
	// Dones
	'dones'        => ['app/controllers/pwrs/don_category_list.php', null],
	'tipodon'      => ['app/controllers/pwrs/don_group_list.php', null],
	'muestradon'   => ['app/controllers/pwrs/don_page.php', null],
	'listadones'   => ['app/controllers/pwrs/don_table.php', null],
	'fulldon'      => ['app/controllers/pwrs/don_full_list.php', null],
	// Rituales
	'rites'        => ['app/controllers/pwrs/rite_category_list.php', null],
	'tiporite'     => ['app/controllers/pwrs/rite_group_list.php', null],
	'seerite'      => ['app/controllers/pwrs/rite_page.php', null],
	'ritelist'	   => ['app/controllers/pwrs/rite_table.php', null],
	'fullrite'     => ['app/controllers/pwrs/rite_full_list.php', null],
	// Totems
	'totems'       => ['app/controllers/pwrs/totm_category_list.php', null],
	'tipototm'     => ['app/controllers/pwrs/totm_group_list.php', null],
	'listatotems'  => ['app/controllers/pwrs/totm_table.php', null],
	'muestratotem' => ['app/controllers/pwrs/totm_page.php', null],
	// Disciplinas
	'disciplinas'  => ['app/controllers/pwrs/disc_table.php', null],
	'tipodisc'     => ['app/controllers/pwrs/disc_group_list.php', null],
	'muestradisc'  => ['app/controllers/pwrs/disc_page.php', null],

	// 🛠️ Herramientas
	'csp'     		   => ['app/controllers/tool/csp_board.php', null],
	'dados'   		   => ['app/controllers/tool/dice_dados.php', 'Tiradados'],
	'garou_name_gen'   => ['app/controllers/tool/garou_name_gen.php', 'Generador de nombres Garou'],
	'keygen'           => ['app/tools/generador_claves.php', 'Generador de claves'],
	'crop'             => ['app/tools/crop.html', 'Recortador de imágenes'],
	
	// 🎼 Banda sonora
	'ost' => ['app/controllers/ost/bso_main.php', 'Banda sonora'],
	
	// Línea temporal
	'timeline' => ['app/controllers/main/main_timeline.php', 'Línea temporal'],
	
	// Galería
	'gallery' => ['app/controllers/main/main_gallery.php', 'Galería de imágenes'],
	'tooltip' => ['app/controllers/tool/tooltip.php', null],
	
	// Mapas
	'maps' 		  => ['app/controllers/maps/maps_main.php', 'Mapas'],
	'maps_detail' => ['app/controllers/maps/maps_detail.php', 'Punto de interés'],

	// Jugadores
	'players' 	=> ['app/controllers/playr/playr_list.php', 'Jugadores'],
	'seeplayer' => ['app/controllers/playr/playr_page.php', 'Jugador'],

	// Test

	'snippet_forum_a' => ['app/partials/snippet_forum_hg.php', null],
	'forum_message'   => ['app/partials/snippet_forum_hg.php', null],
	'forum_diceroll'  => ['app/partials/snippet_forum_hg_dice.php', null],

];

// ===================== //
// 🔁 Ejecutar inclusión
// ===================== //
normalize_pretty_request($link, $routeKey);
if (isset($routes[$routeKey])) {
	[$file, $sect] = $routes[$routeKey];
	if ($sect) $pageSect = $sect;
	if (in_array($routeKey, ['snippet_forum_a', 'forum_message', 'forum_diceroll', 'keygen', 'crop', 'tooltip'], true)) {
		$isBarePage = true;
	}
	include($file);
} else {
	$pageSect = "Noticias";
	include("app/controllers/main/main_news.php");
}

// Fallback de metatags desde tÃ­tulos de pÃ¡gina si no se han definido
if (empty($metaTitle)) {
	$metaTitle = trim(($pageTitle2 ?? '') . ' | ' . ($pageSect ?? '') . ' | ' . $pageTitle, ' |');
}
