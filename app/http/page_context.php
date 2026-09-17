<?php
// ===================== //
// Page metadata context
// ===================== //
$routeKey = (isset($hgRequest) && is_array($hgRequest)) ? hg_request_route($hgRequest) : '';
$routeParam = (isset($hgRequest) && is_array($hgRequest)) ? hg_request_query_param($hgRequest, 't') : '';
$pageTitle = $pageTitle ?? "Heaven's Gate";
$pageSect = $pageSect ?? null;
$metaTitle = $metaTitle ?? null;
$metaDescription = $metaDescription ?? null;
$metaImage = $metaImage ?? null;
$metaType = $metaType ?? null;

include_once("app/helpers/pretty.php");

// ===================== //
// ✨ Función auxiliar para metadatos SEO
// ===================== //
function setMetaTitle($custom = null) {
	global $pageTitle, $pageSect, $pageTitle2;
	$parts = [];
	if ($custom) $parts[] = $custom;
	if (!empty($pageTitle2)) $parts[] = $pageTitle2;
	if (!empty($pageSect)) $parts[] = $pageSect;
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

function hg_meta_attr($value): string {
	return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function setMetaTags($route, $pageURL = '', $baseURL = 'https://naufragio-heavensgate.duckdns.org') {
	global $metaTitle, $metaDescription, $metaImage, $metaType;
    $title = "Heaven's Gate";
    $description = "Heaven's Gate es una campaña de rol ambientada en el Mundo de Tinieblas. Explora biografías, poderes, clanes y más.";
	$image = $baseURL . "/img/og/og_image.webp"; // ahora correcto
	$type = "website";

    switch ($route) {
        case 'home':
            $title = "Heaven's Gate";
            $description = "Archivo vivo de una crónica alternativa de Hombre Lobo: El Apocalipsis. Explora personajes, temporadas, eventos, mapas y material de juego.";
            break;
        case 'seasons_home':
            $title = "Temporadas | Heaven's Gate";
            $description = "Portada del archivo de temporadas e historias personales de Heaven's Gate.";
            $image = $baseURL . "/img/og/og_image_temp.webp";
            break;
        case 'seasons_complete':
            $title = "Temporadas completas | Heaven's Gate";
            $description = "Listado de temporadas completas de Heaven's Gate.";
            $image = $baseURL . "/img/og/og_image_temp.webp";
            break;
        case 'seasons_interludes':
            $title = "Interludes | Heaven's Gate";
            $description = "Listado de incisos e interludios narrativos de Heaven's Gate.";
            $image = $baseURL . "/img/og/og_image.webp";
            break;
        case 'seasons_personal':
            $title = "Historias personales | Heaven's Gate";
            $description = "Listado de historias personales de Heaven's Gate.";
            $image = $baseURL . "/img/og/og_image_bio.webp";
            break;
        case 'seasons_specials':
            $title = "Especiales | Heaven's Gate";
            $description = "Listado de especiales de Heaven's Gate.";
            $image = $baseURL . "/img/og/og_image_power.webp";
            break;
        case 'season_order':
            $title = "Orden de temporadas | Heaven's Gate";
            $description = "Consulta Heaven's Gate por orden jugado, cronologico u otros recorridos narrativos.";
            $image = $baseURL . "/img/og/og_image_temp.webp";
            break;
        case 'chapters_table':
            $title = "Tabla de episodios | Heaven's Gate";
            $description = "Listado completo de episodios y capitulos de Heaven's Gate.";
            $image = $baseURL . "/img/og/og_image_temp.webp";
            break;
        case 'news':
            $title = "Noticias - Heaven's Gate";
            $description = "Ultimas novedades de la campana Heaven's Gate.";
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
			$image = $baseURL . "/img/og/og_image_temp.webp"; // ahora correcto
			break;
        case 'bios':
		case 'biogroup':
		case 'chronicles':
		case 'bio_chronicles':
		case 'muestrabio':
            $title = "Biografias - Heaven's Gate";
            $description = "Explora las biografias de los personajes clave de Heaven's Gate.";
			$image = $baseURL . "/img/og/og_image_bio.webp"; // ahora correcto
            break;
        case 'nebula_clan':
        case 'nebula_character':
            $title = "Nebulosa de relaciones - Heaven's Gate";
            $description = "Visualiza las relaciones entre clanes, manadas y personajes en Heaven's Gate.";
            break;
        case 'doc':
            $title = "Documentación - Heaven's Gate";
            $description = "Accede a la documentacion oficial y trasfondo de la campana.";
            break;
        case 'inv':
        case 'verobj':
		case 'seeitem':
		case 'listaobj':
            $title = "Inventario - Heaven's Gate";
            $description = "Consulta los objetos y artefactos disponibles en la campana.";
			$image = $baseURL . "/img/og/og_image_monster.webp";
            break;
        case 'sistemas':
            $title = "Sistemas de juego | Heaven's Gate";
            $description = "Explora sistemas, formas y mecanicas empleadas en la campana.";
            break;
        case 'powers':
            $title = "Poderes | Heaven's Gate";
            $description = "Resumen y acceso a los poderes disponibles en Heaven's Gate.";
			$image = $baseURL . "/img/og/og_image_power.webp";
            break;
        case 'dones':
		case 'tipodon':
		case 'muestradon':
		case 'listadones':
		case 'fulldon':
		case 'customdon':
		//, ritos, disciplinas y poderes
            $title = "Dones | Heaven's Gate";
            $description = "Listado de dones usados en Heaven's Gate.";
			$image = $baseURL . "/img/og/og_image_power.webp"; // ahora correcto
            break;
        case 'rites':
		case 'tiporite':
		case 'seerite':
		case 'ritelist':
		case 'fullrite':
		case 'customrite':
		//, ritos, disciplinas y poderes
            $title = "Rituales | Heaven's Gate";
            $description = "Listado de ritos usados en Heaven's Gate.";
			$image = $baseURL . "/img/og/og_image_power.webp"; // ahora correcto
            break;
        case 'totems':
		case 'tipototm':
		case 'muestratotem':
		case 'listatotems':
		case 'fulltotem':
		case 'customtotem':
		//, ritos, disciplinas y poderes
            $title = "Totems | Heaven's Gate";
            $description = "Listado de totems (espiritus guia) de Heaven's Gate.";
			$image = $baseURL . "/img/og/og_image_monster.webp";
            break;
        case 'disciplinas':
		case 'tipodisc':
		case 'muestradisc':
		case 'fulldisc':
		case 'customdisc':
		//, ritos,  y poderes
            $title = "Disciplinas | Heaven's Gate";
            $description = "Listado de Disciplinas vampíricas utilizadas en Heaven's Gate.";
			$image = $baseURL . "/img/og/og_image_power.webp"; // ahora correcto
            break;
		case 'ost':
		//, ritos, disciplinas y poderes
            $title = "Banda sonora | Heaven's Gate";
            $description = "Lista de temas musicales usados en Heaven's Gate.";
			$image = $baseURL . "/img/og/og_image_bio.webp"; // ahora correcto
            break;
		case 'dados':
            $title = "Tiradados | Heaven's Gate";
            $description = "Cómodo tirador de dados d10 para partidas de foro.";
			$image = $baseURL . "/img/og/og_image_bio.webp"; // ahora correcto
            break;
		case 'combat_simulator':
		case 'combat_simulator_result':
		case 'combat_simulator_logs':
		case 'combat_simulator_log':
		case 'combat_simulator_scores':
		case 'combat_simulator_weapons':
		case 'combat_simulator_tournament':
		case 'simulador':
		case 'simulador2':
		case 'combtodo':
		case 'vercombat':
		case 'punts':
		case 'arms':
		case 'sim_tournament':
            $title = "Simulador de combate | Heaven's Gate";
            $description = "Simulador de combate de personajes usando datos reales de la web.";
			$image = $baseURL . "/img/og/og_image_power.webp";
            break;
		case 'game_cards':
		case 'game_cards_collection':
		case 'game_cards_combat':
		case 'game_cards_mobile':
		case 'game_cards_explanation':
		case 'game_cards_lab':
		case 'game_cards_lab_collection':
		case 'game_cards_lab_combat':
		case 'game_cards_lab_mobile':
		case 'game_cards_lab_explanation':
            $title = "Archivo de mnemógeno | Heaven's Gate";
            $description = "Minijuego coleccionable de cartas de Heaven's Gate con colección guardada en el navegador.";
			$image = $baseURL . "/img/og/og_image_power.webp";
            break;
		case 'gallery':
            $title = "Galeria de imagenes | Heaven's Gate";
            $description = "Lista de imagenes utilizadas en la campana.";
			$image = $baseURL . "/img/og/og_image_bio.webp"; // ahora correcto
            break;
		case 'maps':
		case 'maps_detail':
		case 'maps_api':
            $title = "Mapas | Heaven's Gate";
            $description = "Mapas interactivos sobre lugares de interes en la campana.";
			$image = $baseURL . "/img/og/og_image_power.webp"; // ahora correcto
            break;
		case 'plots':
            $title = "Equipos activos | Heaven's Gate";
            $description = "Lista de personajes en tramas abiertas y su estado, su salud y sus recursos.";
			$image = $baseURL . "/img/og/og_image_bio.webp"; // ahora correcto
            break;
        default:
            // Fallback genérico
            break;
    }

	if (!empty($metaTitle)) $title = $metaTitle;
	if (!empty($metaDescription)) $description = $metaDescription;
	if (!empty($metaImage)) $image = normalize_meta_image($metaImage, $baseURL);
	if (!empty($metaType)) $type = $metaType;

	$titleAttr = hg_meta_attr($title);
	$descriptionAttr = hg_meta_attr($description);
	$typeAttr = hg_meta_attr($type);
	$pageUrlAttr = hg_meta_attr($pageURL);
	$imageAttr = hg_meta_attr($image);

    echo '<meta name="description" content="' . $descriptionAttr . '">' . "\n";
    echo '<meta property="og:title" content="' . $titleAttr . '">' . "\n";
    echo '<meta property="og:description" content="' . $descriptionAttr . '">' . "\n";
    echo '<meta property="og:type" content="' . $typeAttr . '">' . "\n";
	echo '<meta property="og:url" content="' . $pageUrlAttr . '">' . "\n";
    echo '<meta property="og:image" content="' . $imageAttr . '">' . "\n";
    echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
    echo '<meta name="twitter:title" content="' . $titleAttr . '">' . "\n";
    echo '<meta name="twitter:description" content="' . $descriptionAttr . '">' . "\n";
    echo '<meta name="twitter:image" content="' . $imageAttr . '">' . "\n";
}
