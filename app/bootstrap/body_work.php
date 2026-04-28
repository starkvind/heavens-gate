<?php
// ===================== //
// ✨ Validación de entradas
// ===================== //
$routeKey = trim((string)($_GET['p'] ?? ''));
$routeParam = trim((string)($_GET['t'] ?? ''));
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
        'chronicles'     => ['t', 'dim_chronicles', '/chronicles'],
        'bio_chronicles' => ['t', 'dim_chronicles', '/chronicles'],
        'bio_worlds'     => ['t', 'dim_realities', '/characters/worlds'],
        'seegroup'     => ['b', null, null],
        'seeplayer'    => ['b', 'dim_players', '/players'],
        'verdoc'       => ['b', 'fact_docs', '/documents'],
        'verobj'       => ['b', 'fact_items', '/inventory/items'],
        'inv_type'     => ['t', 'dim_item_types', '/inventory/type'],
        'seeitem'      => ['b', 'fact_items', '/inventory/items'],
        'muestradon'   => ['b', 'fact_gifts', '/powers/gift'],
        'tipodon'      => ['b', 'dim_gift_types', '/powers/gift/type'],
        'seerite'      => ['b', 'fact_rites', '/powers/rite'],
        'tiporite'     => ['b', 'dim_rite_types', '/powers/rite/type'],
        'muestratotem' => ['b', 'dim_totems', '/powers/totem'],
        'tipototm'     => ['b', 'dim_totem_types', '/powers/totem/type'],
        'muestradisc'  => ['b', 'fact_discipline_powers', '/powers/discipline'],
        'tipodisc'     => ['b', 'dim_discipline_types', '/powers/discipline/type'],
        'verrasgo'     => ['b', 'dim_traits', '/rules/traits'],
        'vercondition' => ['b', 'dim_character_conditions', '/rules/conditions'],
        'vermyd'       => ['b', 'dim_merits_flaws', '/rules/merits-flaws'],
        'verarch'      => ['b', 'dim_archetypes', '/rules/archetypes'],
        'vermaneu'     => ['b', 'fact_combat_maneuvers', '/rules/maneuvers'],
        'sistemas'     => ['b', 'dim_systems', '/systems'],
        'verforma'     => ['b', 'dim_forms', '/systems/form'],
        'versistdetalle' => ['b', 'dim_breeds', '/systems/detail'],
        'seechapter'   => ['t', 'dim_chapters', '/chapters'],
        'temp'         => ['t', 'dim_seasons', '/seasons'],
        'timeline_event' => ['t', 'fact_timeline_events', '/timeline/event'],
        'maps_detail'  => ['id', 'fact_map_pois', '/maps/poi'],
    ];

    if (!isset($paramMap[$route])) return;
    [$param, $table, $base] = $paramMap[$route];

    if ($route === 'seegroup' && isset($_GET['t'], $_GET['b'])) {
        $t = (string)$_GET['t'];
        if ($t === '1') {
            $table = 'dim_groups';
            $base = '/groups';
            if (isset($_GET['org']) && trim((string)$_GET['org']) !== '') {
                $orgRaw = trim((string)$_GET['org']);
                $orgResolved = resolve_pretty_id($link, 'dim_organizations', $orgRaw);
                $orgSegment = $orgRaw;
                if ($orgResolved !== null) {
                    $_GET['org'] = (string)$orgResolved;
                    $orgPretty = get_pretty_id($link, 'dim_organizations', (int)$orgResolved);
                    if ($orgPretty) {
                        $orgSegment = $orgPretty;
                    }
                }
                $base = '/groups/' . rawurlencode($orgSegment);
            }
        } elseif ($t === '2') {
            $table = 'dim_organizations';
            $base = '/organizations';
        }
    }

    if ($route === 'versistdetalle' && isset($_GET['tc'], $_GET['b'])) {
        $tc = (string)$_GET['tc'];
        if ($tc === '1') {
            $table = 'dim_breeds';
            $base = '/systems/breeds';
        } elseif ($tc === '2') {
            $table = 'dim_auspices';
            $base = '/systems/auspices';
        } elseif ($tc === '3') {
            $table = 'dim_tribes';
            $base = '/systems/tribes';
        } elseif ($tc === '4') {
            $table = 'fact_misc_systems';
            $base = '/systems/misc';
        } else {
            $base = "/systems/detail/$tc";
        }
        $param = 'b';
    }

    if (!$table || !$base || !isset($_GET[$param])) return;

    $raw = (string)$_GET[$param];
    $resolved = resolve_pretty_id($link, $table, $raw);
    if ($resolved !== null) {
        $_GET[$param] = (string)$resolved;
    }

    if (($route === 'verobj' || $route === 'seeitem') && isset($_GET['b'])) {
        $itemId = (int)$_GET['b'];
        if ($itemId > 0) {
            $itemSlug = '';
            $typeSlug = '';
            if ($stmtItem = $link->prepare("
                SELECT i.id, i.pretty_id AS item_pretty, t.id AS type_id, t.pretty_id AS type_pretty
                FROM fact_items i
                LEFT JOIN dim_item_types t ON t.id = i.item_type_id
                WHERE i.id = ?
                LIMIT 1
            ")) {
                $stmtItem->bind_param('i', $itemId);
                $stmtItem->execute();
                $rsItem = $stmtItem->get_result();
                if ($rsItem && ($rowItem = $rsItem->fetch_assoc())) {
                    $itemSlug = (string)($rowItem['item_pretty'] ?? '');
                    if ($itemSlug === '' && isset($rowItem['id'])) $itemSlug = (string)$rowItem['id'];

                    $typeSlug = (string)($rowItem['type_pretty'] ?? '');
                    if ($typeSlug === '' && isset($rowItem['type_id'])) $typeSlug = (string)$rowItem['type_id'];
                }
                $stmtItem->close();
            }

            if ($itemSlug !== '' && $typeSlug !== '') {
                $target = '/inventory/' . rawurlencode($typeSlug) . '/' . rawurlencode($itemSlug);

                $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '');
                $currentPath = (string)(parse_url($requestUri, PHP_URL_PATH) ?? '');
                $norm = static function (string $path): string {
                    $path = rtrim(rawurldecode($path), '/');
                    return $path === '' ? '/' : $path;
                };

                if ($norm($currentPath) !== $norm($target)) {
                    header("Location: $target", true, 301);
                    exit;
                }
            }
        }
    }

    if ($route === 'versistdetalle' && $resolved !== null) {
        $currentPretty = get_pretty_id($link, $table, (int)$resolved);
        $currentPath = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
        $target = rtrim($base, '/') . '/' . rawurlencode($currentPretty ?: $raw);
        if ($target !== '' && rtrim(rawurldecode($currentPath), '/') !== rtrim($target, '/')) {
            header("Location: $target", true, 301);
            exit;
        }
    }

    if ($resolved !== null && !preg_match('/^\d+$/', $raw)) {
        $currentPretty = get_pretty_id($link, $table, (int)$resolved);
        if ($currentPretty && $currentPretty !== $raw) {
            $target = rtrim($base, '/') . '/' . rawurlencode($currentPretty);
            if ($route === 'seegroup') {
                $target = rtrim($base, '/') . '/' . rawurlencode($currentPretty);
            }
            if ($route === 'versistdetalle') {
                $target = rtrim($base, '/') . '/' . rawurlencode($currentPretty);
            }
            header("Location: $target", true, 301);
            exit;
        }
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
    $description = "Heaven's Gate es una campana de rol ambientada en el Mundo de Tinieblas. Explora biografias, poderes, clanes y mas.";
	$image = $baseURL . "/img/og/og_image.jpg"; // ahora correcto
	$type = "website";

    switch ($route) {
        case 'home':
            $title = "Heaven's Gate";
            $description = "Archivo vivo de una cronica alternativa de Hombre Lobo: El Apocalipsis. Explora personajes, temporadas, eventos, mapas y material de juego.";
            break;
        case 'seasons_home':
            $title = "Temporadas | Heaven's Gate";
            $description = "Portada del archivo de temporadas e historias personales de Heaven's Gate.";
            $image = $baseURL . "/img/og/og_image_temp.jpg";
            break;
        case 'seasons_complete':
            $title = "Temporadas completas | Heaven's Gate";
            $description = "Listado de temporadas completas de Heaven's Gate.";
            $image = $baseURL . "/img/og/og_image_temp.jpg";
            break;
        case 'seasons_interludes':
            $title = "Interludes | Heaven's Gate";
            $description = "Listado de incisos e interludios narrativos de Heaven's Gate.";
            $image = $baseURL . "/img/og/og_image.jpg";
            break;
        case 'seasons_personal':
            $title = "Historias personales | Heaven's Gate";
            $description = "Listado de historias personales de Heaven's Gate.";
            $image = $baseURL . "/img/og/og_image_bio.jpg";
            break;
        case 'seasons_specials':
            $title = "Especiales | Heaven's Gate";
            $description = "Listado de especiales de Heaven's Gate.";
            $image = $baseURL . "/img/og/og_image_power.jpg";
            break;
        case 'chapters_table':
            $title = "Tabla de episodios | Heaven's Gate";
            $description = "Listado completo de episodios y capitulos de Heaven's Gate.";
            $image = $baseURL . "/img/og/og_image_temp.jpg";
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
			$image = $baseURL . "/img/og/og_image_temp.jpg"; // ahora correcto
			break;
        case 'bios':
		case 'biogroup':
		case 'chronicles':
		case 'bio_chronicles':
		case 'muestrabio':
            $title = "Biografias - Heaven's Gate";
            $description = "Explora las biografias de los personajes clave de Heaven's Gate.";
			$image = $baseURL . "/img/og/og_image_bio.jpg"; // ahora correcto
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
			$image = $baseURL . "/img/og/og_image_monster.jpg";
            break;
        case 'sistemas':
            $title = "Sistemas de juego | Heaven's Gate";
            $description = "Explora sistemas, formas y mecanicas empleadas en la campana.";
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
		case 'fulltotem':
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
			$image = $baseURL . "/img/og/og_image_power.jpg";
            break;
		case 'gallery':
            $title = "Galeria de imagenes | Heaven's Gate";
            $description = "Lista de imagenes utilizadas en la campana.";
			$image = $baseURL . "/img/og/og_image_bio.jpg"; // ahora correcto
            break;
		case 'maps':
		case 'maps_detail':
		case 'maps_api':
            $title = "Mapas | Heaven's Gate";
            $description = "Mapas interactivos sobre lugares de interes en la campana.";
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

// ===================== //
// ✨ Enrutador por secciones
// ===================== //
$routes = [
	// 🌍 Principal
	'home'       => ['app/controllers/main/main_home.php', 'Inicio'],
	'news'       => ['app/controllers/main/main_news.php', 'Noticias'],
	'status'     => ['app/controllers/main/main_status.php', 'Estado'],
	'about'      => ['app/controllers/main/main_about.php', 'Acerca de...'],
	'biblio'     => ['app/controllers/main/main_biblio.php', 'Bibliografía'],
	'busq'       => ['app/controllers/main/main_search_form.php', 'Búsqueda'],
	'busk'       => ['app/controllers/main/main_search_result.php', 'Resultado de la búsqueda'],
	'talim'      => ['app/controllers/admin/admin_main.php', 'Administracion'],
	'error404'   => ['app/controllers/main/error404.php', 'Error'],

	// 🗃️ Temporadas
	'seasons_home'    => ['app/controllers/chapters/seasons_home.php', 'Temporadas'],
	'seasons_complete' => ['app/controllers/chapters/seasons_home.php', 'Temporadas'],
	'seasons_interludes' => ['app/controllers/chapters/seasons_home.php', 'Temporadas'],
	'seasons_personal' => ['app/controllers/chapters/seasons_home.php', 'Historias personales'],
	'seasons_specials' => ['app/controllers/chapters/seasons_home.php', 'Especiales'],
	'temp'            => ['app/controllers/chapters/season_archive.php', 'Temporadas'],
	'chapters_table'  => ['app/controllers/chapters/chapter_table.php', 'Capítulos'],
	'seechapter'      => ['app/controllers/chapters/chapter_page.php', 'Capítulos'],
	'temp_analisis'   => ['app/controllers/chapters/season_attendance_analysis.php', 'Análisis asistencia'],

	// Plots
	'party'		   => ['app/controllers/main/main_parties.php', 'Equipos activos'],

	// 🧬 Biografias
	'bios'         => ['app/controllers/bio/bio_list.php', 'Biografias'],
	'biogroup'     => ['app/controllers/bio/bio_group.php', 'Biografias por Grupo'],
	'muestrabio'   => ['app/controllers/bio/bio_page2.php', 'Biografia'],
	'listgroups'   => ['app/controllers/bio/bio_pack_list.php', null],
	'seegroup'     => ['app/controllers/bio/bio_pack_page.php', null],
	'chronicles'     => ['app/controllers/main/main_chronicles.php', 'Crónicas'],
	'chronicle_image' => ['app/controllers/main/chronicle_image.php', null],
	'bio_chronicles' => ['app/controllers/main/main_chronicles.php', 'Crónicas'],
	'bio_worlds'     => ['app/controllers/bio/bio_worlds.php', null],

	'list_table'   => ['app/controllers/bio/bio_table.php', null],
	'nebula_clan'  => ['app/controllers/bio/bio_reltree_clans.php', 'Nebulosa de relaciones'],
	'nebula_character' => ['app/controllers/bio/bio_reltree_characters.php', 'Nebulosa de relaciones'],
	'nebula_groups' => ['app/controllers/bio/bio_reltree_groups.php', 'Nebulosa de relaciones'],
	'org_chart' => ['app/controllers/bio/bio_org_chart.php', 'Organigrama'],

	// 📚 Documentación
	'listadocs' => ['app/controllers/docs/docs_table.php', null],
	'verdoc'    => ['app/controllers/docs/docs_page.php', null],
	'rules'     => ['app/controllers/docs/rules_home.php', null],

	// 🎒 Inventario
	'inv'     => ['app/controllers/docs/item_table.php', null],
	'inv_type' => ['app/controllers/docs/item_list.php', null],
	'seeitem' => ['app/controllers/docs/item_page.php', null],
	'imgz'    => ['app/controllers/tool/img_board.php', null],
	'listaobj'=> ['app/controllers/docs/item_table.php', null],
	'verobj'  => ['app/controllers/docs/item_page.php', null],

	// ⚙️ Sistemas
	'listasistemas'  => ['app/controllers/systems/systems_table.php', null],
	'sistemas'  	 => ['app/controllers/systems/system_overview_page.php', null],
	'versistdetalle' => ['app/controllers/systems/system_detail_page.php', null],
	'verforma'  	 => ['app/controllers/systems/system_form_page.php', null],

	// 🧠 Rasgos
	'listarasgos' => ['app/controllers/docs/traits_table.php', null],
	'verrasgo'    => ['app/controllers/docs/traits_page.php', null],
	'listconditions' => ['app/controllers/docs/conditions_table.php', null],
	'vercondition'   => ['app/controllers/docs/condition_page.php', null],
	'maneuver'    => ['app/controllers/docs/maneuver_list.php', null],
	'vermaneu'    => ['app/controllers/docs/maneuver_page.php', null],
	'arquetip'    => ['app/controllers/docs/arche_table.php', null],
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
	'fulltotem'    => ['app/controllers/pwrs/totm_full_list.php', null],
	'muestratotem' => ['app/controllers/pwrs/totm_page.php', null],
	// Disciplinas
	'disciplinas'  => ['app/controllers/pwrs/disc_table.php', null],
	'tipodisc'     => ['app/controllers/pwrs/disc_group_list.php', null],
	'muestradisc'  => ['app/controllers/pwrs/disc_page.php', null],

	// 🛠️ Herramientas
	'csp'     		   => ['app/controllers/tool/csp_board.php', null],
	'dados'   		   => ['app/controllers/tool/dice_roller.php', 'Tiradados'],
	'forum_avatar_tool' => ['app/controllers/tool/forum_avatar_builder.php', 'Creador de mensajes foro'],
	'forum_topic_viewer' => ['app/controllers/tool/forum_topic_viewer.php', 'Visor de temas foro'],
	'garou_name_gen'   => ['app/controllers/tool/garou_name_generator.php', 'Generador de nombres Garou'],
	'combat_simulator' => ['app/controllers/tool/combat_simulator.php', 'Simulador de Combate'],
	'combat_simulator_result' => ['app/controllers/tool/combat_simulator.php', 'Resultado del Combate'],
	'combat_simulator_logs' => ['app/controllers/tool/combat_simulator.php', 'Registro de Combates'],
	'combat_simulator_log' => ['app/controllers/tool/combat_simulator.php', 'Detalle del Combate'],
	'combat_simulator_scores' => ['app/controllers/tool/combat_simulator.php', 'Puntuaciones'],
	'combat_simulator_weapons' => ['app/controllers/tool/combat_simulator.php', 'Armas utilizadas'],
	'combat_simulator_tournament' => ['app/controllers/tool/combat_simulator.php', 'Torneo del Simulador'],
	'schema_sanitizer' => ['app/controllers/tool/schema_sanitizer.php', 'Saneador de esquema'],

	// Legacy aliases
	'simulador'        => ['app/controllers/tool/combat_simulator.php', 'Simulador de Combate'],
	'simulador2'       => ['app/controllers/tool/combat_simulator.php', 'Resultado del Combate'],
	'combtodo'         => ['app/controllers/tool/combat_simulator.php', 'Registro de Combates'],
	'vercombat'        => ['app/controllers/tool/combat_simulator.php', 'Detalle del Combate'],
	'punts'            => ['app/controllers/tool/combat_simulator.php', 'Puntuaciones'],
	'arms'             => ['app/controllers/tool/combat_simulator.php', 'Armas utilizadas'],
	'sim_tournament'   => ['app/controllers/tool/combat_simulator.php', 'Torneo del Simulador'],
	'keygen'           => ['app/tools/key_generator.php', 'Generador de claves'],
	'crop'             => ['app/tools/crop.html', 'Recortador de imágenes'],
	
	// 🎼 Banda sonora
	'ost' => ['app/controllers/ost/bso_main.php', 'Banda sonora'],
	
	// Línea temporal
	'timeline' => ['app/controllers/main/events_main.php', 'Línea temporal'],
	'timeline_event' => ['app/controllers/main/events_page.php', 'Evento'],
	
	// Galeria
	'gallery' => ['app/controllers/main/main_gallery.php', 'Galeria de imagenes'],
	'tooltip' => ['app/controllers/tool/tooltip.php', null],
	'mentions' => ['app/controllers/tool/mentions.php', null],
	
	// Mapas
	'maps' 		  => ['app/controllers/maps/maps_main.php', 'Mapas'],
	'maps_detail' => ['app/controllers/maps/maps_detail.php', 'Punto de interés'],
	'maps_api'    => ['app/controllers/maps/maps_api.php', null],

	// Jugadores
	'players' 	=> ['app/controllers/playr/playr_list.php', 'Jugadores'],
	'seeplayer' => ['app/controllers/playr/playr_page.php', 'Jugador'],

	// Test
	'forum_message'   => ['app/partials/forum_message_snippet.php', null],
	'forum_diceroll'  => ['app/partials/forum_diceroll_snippet.php', null],
	'forum_item'      => ['app/partials/forum_item_snippet.php', null],

];

// ===================== //
// 🔁 Ejecutar inclusión
// ===================== //
normalize_pretty_request($link, $routeKey);
if (isset($routes[$routeKey])) {
	[$file, $sect] = $routes[$routeKey];
	if ($sect) $pageSect = $sect;
	if (in_array($routeKey, ['snippet_forum_a', 'forum_message', 'forum_diceroll', 'forum_item', 'keygen', 'crop', 'tooltip', 'mentions', 'maps_api', 'chronicle_image', 'schema_sanitizer'], true)) {
		$isBarePage = true;
	}
	include($file);
} else {
	if ($routeKey === '') {
		$pageSect = "Inicio";
		include("app/controllers/main/main_home.php");
	} else {
		$pageSect = "Noticias";
		include("app/controllers/main/main_news.php");
	}
}

// Fallback de metatags desde títulos de página si no se han definido
if (empty($metaTitle)) {
$metaTitle = trim(($pageTitle2 ?? '') . ' | ' . ($pageSect ?? '') . ' | ' . $pageTitle, ' |');
}
