<?php
// admin_main.php - Menú principal de administración
	include_once(__DIR__ . '/../../helpers/admin_auth.php');
	hg_admin_session_start();
	hg_admin_send_security_headers();

	// Verificar la conexión a la base de datos
	include_once(__DIR__ . '/../../helpers/admin_ajax.php');
if (!hg_admin_require_db($link)) { return; }

if (!function_exists('hg_admin_menu_attr')) {
	function hg_admin_menu_attr($value) {
		return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
	}
}

if (!function_exists('hg_admin_render_menu_tile')) {
	function hg_admin_render_menu_tile(array $item, string $sectionTitle) {
		$keywords = isset($item['keywords']) && is_array($item['keywords']) ? implode(' ', $item['keywords']) : '';
		$search = trim($sectionTitle . ' ' . $item['label'] . ' ' . $keywords);
		$target = !empty($item['new_tab']) ? " target='_blank' rel='noopener'" : '';

		echo "<a class='adm-admin-menu-link' href='" . hg_admin_menu_attr($item['href']) . "'{$target} data-admin-search='" . hg_admin_menu_attr($search) . "'>";
		echo "<div class='bioSheetPower adm-admin-tile'>";
		echo "<span class='adm-admin-tile-title'>" . hg_admin_menu_attr($item['label']) . "</span>";
		if (!empty($item['hint'])) {
			echo "<span class='adm-admin-tile-hint'>" . hg_admin_menu_attr($item['hint']) . "</span>";
		}
		echo "</div>";
		echo "</a>";
	}
}

if (!function_exists('hg_admin_render_menu_section')) {
	function hg_admin_render_menu_section(array $section) {
		$title = $section['title'];
		$items = $section['items'];
		$itemCount = count($items);

		echo "<fieldset class='bioSeccion adm-admin-menu-section' data-admin-section='" . hg_admin_menu_attr($title) . "'>";
		echo "<legend>&nbsp;" . hg_admin_menu_attr($title) . "&nbsp;</legend>";
		if (!empty($section['summary'])) {
			echo "<p class='adm-admin-menu-summary'>" . hg_admin_menu_attr($section['summary']) . "</p>";
		}
		echo "<div class='bioSheetPowers adm-admin-menu-grid'>";
		foreach ($items as $item) {
			hg_admin_render_menu_tile($item, $title);
		}
		echo "</div>";
		echo "<p class='adm-admin-menu-empty' hidden>No hay accesos visibles en esta seccion.</p>";
		echo "<p class='adm-admin-menu-count'><span data-admin-count>{$itemCount}</span> accesos</p>";
		echo "</fieldset>";
	}
}

	// Si no está logueado, incluir el login
	$isAjaxAdminRequest = isset($_GET['ajax'], $_GET['s']) && $_GET['ajax'] === '1';
	if (!hg_admin_is_authenticated()) {
		if ($isAjaxAdminRequest) {
			http_response_code(403);
			header('Content-Type: application/json; charset=UTF-8');
			echo json_encode(['ok' => false, 'error' => 'No autorizado', 'message' => 'No autorizado']);
			return;
		}
		include("admin_login.php");
	} else {
		// Modo AJAX: responder sin navbar/layout para no romper JSON.
		if ($isAjaxAdminRequest) {
			$seccionAjax = htmlspecialchars($_GET['s']);
			switch ($seccionAjax) {
				case 'admin_pjs': // legacy alias
				case 'admin_characters':
					include("admin_characters.php");
					break;
				case 'admin_epis': // legacy alias
				case 'admin_chapters':
					include("admin_chapters.php");
					break;
				case 'admin_temp': // legacy alias
				case 'admin_seasons':
					include("admin_seasons.php");
					break;
				case 'admin_season_order':
					include("admin_season_order.php");
					break;
				case 'admin_season_order_schema':
					include("admin_season_order_schema.php");
					break;
				case 'admin_menu':
					include("admin_menu.php");
					break;
				case 'admin_groups':
					include("admin_groups.php");
					break;
				case 'admin_organizations':
					include("admin_organizations.php");
					break;
				case 'admin_pois':
					include("admin_pois.php");
					break;
				case 'admin_players':
					include("admin_players.php");
					break;
				case 'admin_chronicles':
					include("admin_chronicles.php");
					break;
				case 'admin_realities':
					include("admin_realities.php");
					break;
				case 'admin_plots': // legacy alias
				case 'admin_parties':
					include("admin_parties.php");
					break;
				case 'admin_system_details':
					include("admin_system_details.php");
					break;
				case 'admin_systems_extra_details':
					include("admin_systems_extra_details.php");
					break;
				case 'admin_systems_energy':
					include("admin_systems_energy.php");
					break;
				case 'admin_traits':
					include("admin_traits.php");
					break;
                case 'admin_actions':
                    include('admin_actions.php');
                    break;
				case 'admin_merits_flaws':
					include("admin_merits_flaws.php");
					break;
				case 'admin_character_conditions':
					include("admin_character_conditions.php");
					break;
				case 'admin_character_conditions_bridge':
				case 'admin_characters_conditions_brige':
					include("admin_character_conditions_bridge.php");
					break;
				case 'admin_character_misc_bridge':
					include("admin_character_misc_bridge.php");
					break;
				case 'admin_character_affiliations_canonical':
					include("admin_character_affiliations_canonical.php");
					break;
				case 'admin_powers':
					include("admin_powers.php");
					break;
				case 'admin_gift_image_mass':
					include("admin_gift_image_mass.php");
					break;
				case 'admin_game_cards':
					include("admin_game_cards.php");
					break;
				case 'admin_docs':
					include("admin_docs.php");
					break;
				case 'admin_external_links':
					include("admin_external_links.php");
					break;
				case 'admin_character_links':
					include("admin_character_links.php");
					break;
				case 'admin_doc_links':
					include("admin_doc_links.php");
					break;
				case 'admin_topic_viewer':
					include("admin_topic_viewer.php");
					break;
				case 'admin_gallery':
					include("admin_gallery.php");
					break;
				case 'admin_items':
					include("admin_items.php");
					break;
				case 'admin_news':
					include("admin_news.php");
					break;
				case 'admin_systems':
					include("admin_systems.php");
					break;
				case 'admin_resources':
					include("admin_resources.php");
					break;
				case 'admin_forms':
					include("admin_forms.php");
					break;
				case 'admin_maneuvers':
					include("admin_maneuvers.php");
					break;
				case 'admin_timelines':
					include("admin_timelines.php");
					break;
				case 'admin_birthdays_quick':
					include("admin_birthdays_quick.php");
					break;
				case 'admin_bso':
					include("admin_bso.php");
					break;
				case 'admin_bso_link':
					include("admin_bso_link.php");
					break;
				case 'admin_bridges':
					include("admin_bridges.php");
					break;
				case 'admin_trait_sets':
					include("admin_trait_sets.php");
					break;
				case 'admin_systems_resources':
					include("admin_systems_resources.php");
					break;
				case 'admin_avatar_mass':
					include("admin_avatar_mass.php");
					break;
				case 'admin_characters_worlds':
					include("admin_characters_worlds.php");
					break;
				case 'admin_character_deaths':
					include("admin_character_deaths.php");
					break;
				case 'admin_characters_clone':
					include("admin_characters_clone.php");
					break;
				case 'admin_sim_character_talk':
					include("admin_sim_character_talk.php");
					break;
				case 'admin_sim_browser':
					include("admin_sim_browser.php");
					break;
				default:
					http_response_code(400);
					header('Content-Type: application/json; charset=UTF-8');
					echo json_encode(['ok' => false, 'error' => 'Sección AJAX no soportada']);
					break;
			}
			return;
		}

		/* MODERNO NUEVO */
		include(__DIR__ . "/../../partials/main_nav_bar.php");	// Barra Navegación
		echo '<link rel="stylesheet" href="/assets/css/hg-admin.css">';
		// Si hay parámetro "s", incluimos la sección correspondiente
		if (isset($_GET['s'])) {
			$seccion = htmlspecialchars($_GET['s']); // Sanear entrada

			switch ($seccion) {
				case 'admin_pjs': // legacy alias
				case 'admin_characters':
					include("admin_characters.php");
					break;
				case 'admin_avatar_mass':
					include("admin_avatar_mass.php");
					break;
				case 'admin_characters_worlds':
					include("admin_characters_worlds.php");
					break;
				case 'admin_character_collision_audit':
					include("admin_character_collision_audit.php");
					break;
				case 'admin_character_deaths':
					include("admin_character_deaths.php");
					break;
				case 'admin_characters_clone':
					include("admin_characters_clone.php");
					break;
				case 'admin_sim_character_talk':
					include("admin_sim_character_talk.php");
					break;
				case 'admin_sim_browser':
					include("admin_sim_browser.php");
					break;
				case 'admin_groups':
					include("admin_groups.php");
					break;
				case 'admin_organizations':
					include("admin_organizations.php");
					break;
				case 'admin_temp': // legacy alias
				case 'admin_seasons':
					include("admin_seasons.php");
					break;
				case 'admin_season_order':
					include("admin_season_order.php");
					break;
				case 'admin_season_order_schema':
					include("admin_season_order_schema.php");
					break;
				case 'admin_epis': // legacy alias
				case 'admin_chapters':
					include("admin_chapters.php");
					break;
				case 'admin_pois':
					include("admin_pois.php");
					break;
				case 'admin_players':
					include("admin_players.php");
					break;
				case 'admin_chronicles':
					include("admin_chronicles.php");
					break;
				case 'admin_realities':
					include("admin_realities.php");
					break;
				case 'admin_bso':
					include("admin_bso.php");
					break;
				case 'admin_bso_link':
					include("admin_bso_link.php");
					break;
				case 'admin_timelines':
					include("admin_timelines.php");
					break;
				case 'admin_birthdays_quick':
					include("admin_birthdays_quick.php");
					break;
				case 'admin_gallery':
					include("admin_gallery.php");
					break;
				case 'admin_plots': // legacy alias
				case 'admin_parties':
					include("admin_parties.php");
					break;
				case 'admin_powers':
					include("admin_powers.php");
					break;
				case 'admin_gift_image_mass':
					include("admin_gift_image_mass.php");
					break;
				case 'admin_game_cards':
					include("admin_game_cards.php");
					break;
				case 'admin_docs':
					include("admin_docs.php");
					break;
				case 'admin_external_links':
					include("admin_external_links.php");
					break;
				case 'admin_character_links':
					include("admin_character_links.php");
					break;
				case 'admin_doc_links':
					include("admin_doc_links.php");
					break;
				case 'admin_topic_viewer':
					include("admin_topic_viewer.php");
					break;
				case 'admin_bridges':
					include("admin_bridges.php");
					break;
				case 'admin_items':
					include("admin_items.php");
					break;
				case 'admin_menu':
					include("admin_menu.php");
					break;
				case 'admin_relations':
					include("admin_relations.php");
					break;
				case 'admin_news':
					include("admin_news.php");
					break;
				case 'admin_systems':
					include("admin_systems.php");
					break;
				case 'admin_forms':
					include("admin_forms.php");
					break;
				case 'admin_maneuvers':
					include("admin_maneuvers.php");
					break;
				case 'admin_system_details':
					include("admin_system_details.php");
					break;
				case 'admin_systems_extra_details':
					include("admin_systems_extra_details.php");
					break;
				case 'admin_systems_energy':
					include("admin_systems_energy.php");
					break;
				case 'admin_trait_sets':
					include("admin_trait_sets.php");
					break;
				case 'admin_traits':
					include("admin_traits.php");
					break;
                case 'admin_actions':
                    include('admin_actions.php');
                    break;
				case 'admin_merits_flaws':
					include("admin_merits_flaws.php");
					break;
				case 'admin_character_conditions':
					include("admin_character_conditions.php");
					break;
				case 'admin_character_conditions_bridge':
				case 'admin_characters_conditions_brige':
					include("admin_character_conditions_bridge.php");
					break;
				case 'admin_character_misc_bridge':
					include("admin_character_misc_bridge.php");
					break;
				case 'admin_character_affiliations_canonical':
					include("admin_character_affiliations_canonical.php");
					break;
				case 'admin_systems_resources':
					include("admin_systems_resources.php");
					break;
				case 'admin_resources':
					include("admin_resources.php");
					break;
				case 'admin_inspect_db':
					include(__DIR__ . "/../../tools/inspect_db.php");
					break;
				case 'admin_mentions_help':
					include("mentions_help.html");
					break;
				case 'admin_org_chart_schema':
					include("admin_org_chart_schema.php");
					break;
				case 'logout':
					include("admin_logout.php");
					break;
				default:
					echo "<p class='adm-admin-error'>Sección no reconocida.</p>";
					break;
			}

		} else {
			// Menú principal si no hay sección específica
			$pageSect = "Panel de Administración";
			$adminMenuSections = [
				[
					'title' => 'Personajes',
					'summary' => 'Altas, cambios masivos y mantenimiento directo de personajes y jugadores.',
					'items' => [
						['href' => '/talim?s=admin_characters', 'label' => 'Gestionar Personajes', 'keywords' => ['pj', 'bio']],
						['href' => '/talim?s=admin_players', 'label' => 'Gestionar Jugadores', 'keywords' => ['usuarios', 'player']],
						['href' => '/talim?s=admin_avatar_mass', 'label' => 'Editar avatares de forma masiva', 'keywords' => ['imagenes', 'avatar']],
						['href' => '/talim?s=admin_character_deaths', 'label' => 'Editar muertes de personajes', 'keywords' => ['estado', 'fallecidos']],
						['href' => '/talim?s=admin_birthdays_quick', 'label' => 'Editar Cumpleaños', 'keywords' => ['fechas', 'birthday']],
						['href' => '/talim?s=admin_characters_clone', 'label' => 'Copiar Personajes', 'keywords' => ['duplicar', 'clonar']],
					],
				],
				[
					'title' => 'Afiliaciones',
					'summary' => 'Relaciones, grupos y asignaciones transversales entre personajes.',
					'items' => [
						['href' => '/talim?s=admin_groups', 'label' => 'Gestionar Grupos y manadas', 'keywords' => ['packs', 'afiliaciones']],
						['href' => '/talim?s=admin_organizations', 'label' => 'Gestionar Organizaciones', 'keywords' => ['facciones']],
						['href' => '/talim?s=admin_characters_worlds', 'label' => 'Asignar Crónicas y Realidades', 'keywords' => ['cronicas', 'realidades']],
						['href' => '/talim?s=admin_character_collision_audit', 'label' => 'Auditar Colisiones de Personajes', 'keywords' => ['duplicados', 'multiverso', 'identidad', 'migracion']],
						['href' => '/talim?s=admin_character_conditions_bridge', 'label' => 'Asignar Condiciones a PJs', 'keywords' => ['bridge', 'condiciones']],
						['href' => '/talim?s=admin_character_misc_bridge', 'label' => 'Asignar Datos misceláneos a PJs', 'keywords' => ['bridge', 'misc']],
						['href' => '/talim?s=admin_bridges', 'label' => 'Editar Bridges', 'keywords' => ['vinculos']],
						['href' => '/talim?s=admin_character_affiliations_canonical', 'label' => 'Canonizar Afiliaciones', 'keywords' => ['normalizar']],
						['href' => '/talim?s=admin_relations', 'label' => 'Editar Relaciones', 'keywords' => ['vinculos', 'network']],
					],
				],
				[
					'title' => 'Narrativa',
					'summary' => 'Temporadas, episodios, crónicas y seguimiento narrativo.',
					'items' => [
						['href' => '/talim?s=admin_chronicles', 'label' => 'Gestionar Crónicas', 'keywords' => ['historias']],
						['href' => '/talim?s=admin_parties', 'label' => 'Gestionar Grupos en activo', 'keywords' => ['parties', 'activo']],
						['href' => '/talim?s=admin_news', 'label' => 'Gestionar Noticias', 'keywords' => ['anuncios']],
						['href' => '/talim?s=admin_topic_viewer', 'label' => 'Gestionar Topics del Foro', 'keywords' => ['foro', 'topics']],
						['href' => '/talim?s=admin_seasons', 'label' => 'Gestionar Temporadas', 'keywords' => ['season']],
						['href' => '/talim?s=admin_chapters', 'label' => 'Gestionar Episodios', 'keywords' => ['chapter']],
						['href' => '/talim?s=admin_season_order', 'label' => 'Orden de temporadas', 'keywords' => ['orden', 'season']],
						['href' => '/talim?s=admin_timelines', 'label' => 'Gestionar Línea temporal', 'keywords' => ['timeline']],
					],
				],
				[
					'title' => 'Ambientación',
					'summary' => 'Realidades, mapas, galería y recursos de atmósfera.',
					'items' => [
						['href' => '/talim?s=admin_realities', 'label' => 'Gestionar Realidades', 'keywords' => ['worldbuilding']],
						['href' => '/talim?s=admin_pois', 'label' => 'Gestionar Mapas', 'keywords' => ['poi', 'mapas']],
						['href' => '/talim?s=admin_gallery', 'label' => 'Gestionar Galería', 'keywords' => ['imagenes']],
						['href' => '/talim?s=admin_bso', 'label' => 'Gestionar Banda Sonora', 'keywords' => ['musica', 'audio']],
					],
				],
				[
					'title' => 'Documentación',
					'summary' => 'Documentos, enlaces y vinculaciones de soporte.',
					'items' => [
						['href' => '/talim?s=admin_docs', 'label' => 'Gestionar Documentación', 'keywords' => ['docs']],
						['href' => '/talim?s=admin_external_links', 'label' => 'Gestionar Documentos Externos', 'keywords' => ['enlaces']],
						['href' => '/talim?s=admin_character_links', 'label' => 'Vincular Docs y Enlaces a PJ', 'keywords' => ['docs', 'personajes']],
						['href' => '/talim?s=admin_doc_links', 'label' => 'Vincular Documento a PJs', 'keywords' => ['docs', 'bridge']],
					],
				],
				[
					'title' => 'Reglamento',
					'summary' => 'Sistemas, rasgos, recursos y estructura mecánica del juego.',
					'items' => [
						['href' => '/talim?s=admin_systems', 'label' => 'Gestionar Sistemas', 'keywords' => ['systems']],
						['href' => '/talim?s=admin_system_details', 'label' => 'Gestionar Razas / Auspicios / Tribus', 'keywords' => ['detalles', 'razas']],
						['href' => '/talim?s=admin_systems_extra_details', 'label' => 'Extra Details to System', 'keywords' => ['detalles extra']],
						['href' => '/talim?s=admin_systems_energy', 'label' => 'Vincular Energía a Recursos', 'keywords' => ['energia', 'resources']],
						['href' => '/talim?s=admin_traits', 'label' => 'Gestionar Rasgos', 'keywords' => ['traits']],
						['href' => '/talim?s=admin_trait_sets', 'label' => 'Asignar Rasgos por Sistema', 'keywords' => ['trait sets']],
						['href' => '/talim?s=admin_systems_resources', 'label' => 'Asignar Recursos por Sistema', 'keywords' => ['resources']],
						['href' => '/talim?s=admin_resources', 'label' => 'Gestionar Recursos (Catálogo)', 'keywords' => ['catalogo']],
						['href' => '/talim?s=admin_forms', 'label' => 'Gestionar Formas', 'keywords' => ['formas']],
                        ['href' => '/talim?s=admin_maneuvers', 'label' => 'Asignar Maniobras a Sistemas y Formas', 'keywords' => ['maniobras', 'forms', 'bridges']],
                        ['href' => '/talim?s=admin_actions', 'label' => 'Gestionar Acciones', 'keywords' => ['acciones', 'tiradas', 'dificultad']],
					],
				],
				[
					'title' => 'Contenido de juego',
					'summary' => 'Poderes, objetos, condiciones y catálogos jugables generales.',
					'items' => [
						['href' => '/talim?s=admin_powers', 'label' => 'Gestionar Poderes', 'keywords' => ['dones', 'powers']],
						['href' => '/talim?s=admin_gift_image_mass', 'label' => 'Imagen dones masivos', 'keywords' => ['dones', 'imagenes']],
						['href' => '/talim?s=admin_items', 'label' => 'Gestionar Objetos', 'keywords' => ['items', 'inventario']],
						['href' => '/talim?s=admin_merits_flaws', 'label' => 'Gestionar Méritos y Defectos', 'keywords' => ['merits', 'flaws']],
						['href' => '/talim?s=admin_character_conditions', 'label' => 'Gestionar Condiciones', 'keywords' => ['states', 'conditions']],
					],
				],
				[
					'title' => 'Juego de cartas',
					'summary' => 'Todo lo relacionado con el gacha queda aislado aquí para encontrarlo rápido.',
					'items' => [
						['href' => '/talim?s=admin_game_cards', 'label' => 'Gestionar Cartas del Gacha', 'keywords' => ['cartas', 'gacha', 'deck', 'cards']],
						['href' => '/admin/game-cards/seed', 'label' => 'Sembrar Cartas del Gacha', 'keywords' => ['seed', 'cartas', 'gacha']],
					],
				],
				[
					'title' => 'Simulador',
					'summary' => 'Paneles propios del simulador, separados del resto de contenido.',
					'items' => [
						['href' => '/talim?s=admin_sim_browser', 'label' => 'Temporadas Simulador', 'keywords' => ['sim', 'browser', 'temporadas']],
						['href' => '/talim?s=admin_sim_character_talk', 'label' => 'Frases PJs Simulador', 'keywords' => ['sim', 'talk', 'dialogos']],
					],
				],
				[
					'title' => 'Sistema',
					'summary' => 'Herramientas internas, auditorías y utilidades de administración.',
					'items' => [
						['href' => '/talim?s=admin_menu', 'label' => 'Editar Menú', 'keywords' => ['menu']],
						['href' => '/talim?s=admin_inspect_db', 'label' => 'Inspeccionar BDD', 'keywords' => ['db', 'bdd']],
						['href' => '/talim?s=admin_mentions_help', 'label' => 'Ayuda Mentions', 'keywords' => ['mentions', 'ayuda']],
						['href' => '/talim?s=admin_season_order_schema', 'label' => 'Schema orden temporadas', 'keywords' => ['schema', 'temporadas']],
						['href' => '/talim?s=logout', 'label' => 'Cerrar sesión', 'keywords' => ['logout', 'salir']],
					],
				],
			];

			echo "<h2>Panel de Administración</h2>";
			echo "<div class='adm-admin-menu-toolbar'>";
			echo "<label class='adm-admin-menu-search-label' for='adm-admin-menu-search'>Buscar acceso</label>";
			echo "<input class='inp adm-admin-menu-search' id='adm-admin-menu-search' type='search' placeholder='Busca por nombre, categoría o palabra clave' autocomplete='off'>";
			echo "<p class='adm-admin-menu-search-help'>El filtro busca dentro del nombre del acceso y su sección.</p>";
			echo "<p class='adm-admin-menu-empty-global' id='adm-admin-menu-empty-global' hidden>No hay coincidencias con ese filtro.</p>";
			echo "</div>";
			echo "<div class='adm-admin-menu-sections'>";
			foreach ($adminMenuSections as $section) {
				hg_admin_render_menu_section($section);
			}
			echo "</div>";
			echo <<<HTML
<script>
(function () {
	var input = document.getElementById('adm-admin-menu-search');
	if (!input) { return; }

	var sectionNodes = Array.prototype.slice.call(document.querySelectorAll('[data-admin-section]'));
	var globalEmpty = document.getElementById('adm-admin-menu-empty-global');

	function normalizeText(value) {
		return (value || '')
			.toLowerCase()
			.normalize('NFD')
			.replace(/[\u0300-\u036f]/g, '')
			.trim();
	}

	function applyFilter() {
		var query = normalizeText(input.value);
		var visibleSections = 0;

		sectionNodes.forEach(function (section) {
			var links = Array.prototype.slice.call(section.querySelectorAll('[data-admin-search]'));
			var visibleItems = 0;

			links.forEach(function (link) {
				var haystack = normalizeText(link.getAttribute('data-admin-search'));
				var isVisible = query === '' || haystack.indexOf(query) !== -1;
				link.hidden = !isVisible;
				if (isVisible) {
					visibleItems += 1;
				}
			});

			section.hidden = visibleItems === 0;
			var countNode = section.querySelector('[data-admin-count]');
			var emptyNode = section.querySelector('.adm-admin-menu-empty');
			if (countNode) {
				countNode.textContent = String(visibleItems);
			}
			if (emptyNode) {
				emptyNode.hidden = visibleItems !== 0;
			}
			if (visibleItems > 0) {
				visibleSections += 1;
			}
		});

		if (globalEmpty) {
			globalEmpty.hidden = visibleSections !== 0;
		}
	}

	input.addEventListener('input', applyFilter);
	applyFilter();
}());
</script>
HTML;
		}
	}
?>