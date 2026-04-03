<?php
// admin_main.php - Menú principal de administración
	include_once(__DIR__ . '/../../helpers/admin_auth.php');
	hg_admin_session_start();

	// Verificar la conexión a la base de datos
	include_once(__DIR__ . '/../../helpers/admin_ajax.php');
if (!hg_admin_require_db($link)) { return; }

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
				case 'admin_menu':
					include("admin_menu.php");
					break;
				case 'admin_groups':
					include("admin_groups.php");
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
				case 'admin_traits':
					include("admin_traits.php");
					break;
				case 'admin_merits_flaws':
					include("admin_merits_flaws.php");
					break;
				case 'admin_powers':
					include("admin_powers.php");
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
					echo json_encode(['ok' => false, 'error' => 'Seccion AJAX no soportada']);
					break;
			}
			return;
		}

		/* MODERNO NUEVO */
		include(__DIR__ . "/../../partials/main_nav_bar.php");	// Barra Navegacion
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
				case 'admin_temp': // legacy alias
				case 'admin_seasons':
					include("admin_seasons.php");
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
				case 'admin_system_details':
					include("admin_system_details.php");
					break;
				case 'admin_systems_extra_details':
					include("admin_systems_extra_details.php");
					break;
				case 'admin_trait_sets':
					include("admin_trait_sets.php");
					break;
				case 'admin_traits':
					include("admin_traits.php");
					break;
				case 'admin_merits_flaws':
					include("admin_merits_flaws.php");
					break;
				case 'admin_systems_resources':
					include("admin_systems_resources.php");
					break;
				case 'admin_resources':
					include("admin_resources.php");
					break;
				case 'admin_resources_migration':
					include("admin_resources_migration.php");
					break;
				case 'admin_inspect_db':
					include(__DIR__ . "/../../tools/inspect_db.php");
					break;
				case 'admin_mentions_help':
					include("mentions_help.html");
					break;
				case 'admin_schema_initializer':
					include("admin_schema_initializer.php");
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
			echo "<h2>Panel de Administraci&oacute;n</h2>";
				echo "<div class='bioSheetPowers'>";

				// PERSONAJES
				echo "<fieldset class='bioSeccion'><legend>&nbsp;Personajes&nbsp;</legend>";
				echo "
					<a href='/talim?s=admin_characters'>
					  <div class='bioSheetPower adm-admin-tile'>
						Gestionar Personajes
					  </div>
					</a>
					<a href='/talim?s=admin_groups'>
						<div class='bioSheetPower adm-admin-tile'>
							Gestionar Grupos
						</div>
					</a>
					<a href='/talim?s=admin_players'>
						<div class='bioSheetPower adm-admin-tile'>
							Gestionar Jugadores
						</div>
					</a>
					<a href='/talim?s=admin_parties'>
						<div class='bioSheetPower adm-admin-tile'>
							Gestionar Grupos en activo
						</div>
					</a>
					";
				echo "</fieldset>";
					echo "<br />";
				// RELACIONES & BRIDGES
				echo "<fieldset class='bioSeccion'><legend>&nbsp;Relaciones & Puentes&nbsp;</legend>";
					echo "
					<a href='/talim?s=admin_avatar_mass'>
					  <div class='bioSheetPower adm-admin-tile'>
						Editar avatares de forma masiva
					  </div>
					</a>
					<a href='/talim?s=admin_character_deaths'>
					  <div class='bioSheetPower adm-admin-tile'>
						Editar muertes de personajes
					  </div>
					</a>
					<a href='/talim?s=admin_bridges'>
						<div class='bioSheetPower adm-admin-tile'>
							Editar Bridges
						</div>
					</a>
					<a href='/talim?s=admin_relations'>
						<div class='bioSheetPower adm-admin-tile'>
							Editar Relaciones
						</div>
					</a>
					<a href='/talim?s=admin_birthdays_quick'>
						<div class='bioSheetPower adm-admin-tile'>
							Editar Cumplea&ntilde;os
						</div>
					</a>
					<a href='/talim?s=admin_characters_worlds'>
					  <div class='bioSheetPower adm-admin-tile'>
						Asignar Crónicas y Realidades
					  </div>
					</a>
					<a href='/talim?s=admin_characters_clone'>
					  <div class='bioSheetPower adm-admin-tile'>
						Copiar Personajes
					  </div>
					</a>
					";
				echo "</fieldset>";
					echo "<br />";
				// TEMPORADAS Y EPISODIOS
				echo "<fieldset class='bioSeccion'><legend>&nbsp;Temporadas & Episodios&nbsp;</legend>";
				echo "
					<a href='/talim?s=admin_news'>
						<div class='bioSheetPower adm-admin-tile'>
							Gestionar Noticias
						</div>
					</a>
					<a href='/talim?s=admin_topic_viewer'>
						<div class='bioSheetPower adm-admin-tile'>
							Gestionar Topics del Foro
						</div>
					</a>
					<a href='/talim?s=admin_seasons'>
						<div class='bioSheetPower adm-admin-tile'>
							Gestionar Temporadas
						</div>
					</a>
					<a href='/talim?s=admin_chapters'>
						<div class='bioSheetPower adm-admin-tile'>
							Gestionar Episodios
						</div>
					</a>
					";
				echo "</fieldset>";
					echo "<br />";
				// DOCUMENTOS
				echo "<fieldset class='bioSeccion'><legend>&nbsp;Documentaci&oacute;n&nbsp;</legend>";
				echo "
					<a href='/talim?s=admin_docs'>
						<div class='bioSheetPower adm-admin-tile'>
							Gestionar Documentaci&oacute;n
						</div>
					</a>
					<a href='/talim?s=admin_external_links'>
						<div class='bioSheetPower adm-admin-tile'>
							Gestionar Documentos Externos
						</div>
					</a>
					<a href='/talim?s=admin_character_links'>
						<div class='bioSheetPower adm-admin-tile'>
							Vincular Docs y Enlaces a PJ
						</div>
					</a>
					<a href='/talim?s=admin_doc_links'>
						<div class='bioSheetPower adm-admin-tile'>
							Vincular Documento a PJs
						</div>
					</a>				
				";
				echo "</fieldset>";
					echo "<br />";
				// AMBIENTACION
				echo "<fieldset class='bioSeccion'><legend>&nbsp;Ambientaci&oacute;n&nbsp;</legend>";
				echo "
					<a href='/talim?s=admin_chronicles'>
						<div class='bioSheetPower adm-admin-tile'>
							Gestionar Cr&oacute;nicas
						</div>
					</a>
					<a href='/talim?s=admin_realities'>
						<div class='bioSheetPower adm-admin-tile'>
							Gestionar Realidades
						</div>
					</a>
					<a href='/talim?s=admin_pois'>
						<div class='bioSheetPower adm-admin-tile'>
							Gestionar Mapas
						</div>
					</a>
					<a href='/talim?s=admin_timelines'>
						<div class='bioSheetPower adm-admin-tile'>
							Gestionar L&iacute;nea temporal
						</div>
					</a>
					<a href='/talim?s=admin_gallery'>
						<div class='bioSheetPower adm-admin-tile'>
							Gestionar Galer&iacute;a
						</div>
					</a>
					<a href='/talim?s=admin_bso'>
						<div class='bioSheetPower adm-admin-tile'>
							Gestionar Banda Sonora
						</div>
					</a>
					";
				echo "</fieldset>";
					echo "<br />";
				// PODERES
				echo "<fieldset class='bioSeccion'><legend>&nbsp;Objetos y Poderes&nbsp;</legend>";
				echo "
					<a href='/talim?s=admin_items'>
						<div class='bioSheetPower adm-admin-tile'>
							Gestionar Objetos
						</div>
					</a>
					<a href='/talim?s=admin_powers'>
						<div class='bioSheetPower adm-admin-tile'>
							Gestionar Poderes
						</div>
					</a>
					<a href='/talim?s=admin_merits_flaws'>
						<div class='bioSheetPower adm-admin-tile'>
							Gestionar M&eacute;ritos y Defectos
						</div>
					</a>
					";
				echo "</fieldset>";
					echo "<br />";
				// REGLAMENTO
				echo "<fieldset class='bioSeccion'><legend>&nbsp;Reglamento&nbsp;</legend>";
				echo "
					<a href='/talim?s=admin_systems'>
						<div class='bioSheetPower adm-admin-tile'>
							Gestionar Sistemas
						</div>
					</a>
					<a href='/talim?s=admin_traits'>
						<div class='bioSheetPower adm-admin-tile'>
							Gestionar Rasgos
						</div>
					</a>
					<a href='/talim?s=admin_trait_sets'>
						<div class='bioSheetPower adm-admin-tile'>
							Asignar Rasgos por Sistema
						</div>
					</a>
					<a href='/talim?s=admin_systems_resources'>
						<div class='bioSheetPower adm-admin-tile'>
							Asginar Recursos por Sistema
						</div>
					</a>
					<a href='/talim?s=admin_resources'>
						<div class='bioSheetPower adm-admin-tile'>
							Gestionar Recursos (Catalogo)
						</div>
					</a>
					<a href='/talim?s=admin_system_details'>
						<div class='bioSheetPower adm-admin-tile'>
							Gestionar Razas / Auspicios / Tribus
						</div>
					</a>
					<a href='/talim?s=admin_systems_extra_details'>
						<div class='bioSheetPower adm-admin-tile'>
							Extra Details to System
						</div>
					</a>
					<a href='/talim?s=admin_forms'>
						<div class='bioSheetPower adm-admin-tile'>
							Gestionar Formas
						</div>
					</a>
					";
				echo "</fieldset>";
					echo "<br />";
				// SIMULADOR
				echo "<fieldset class='bioSeccion'><legend>&nbsp;Simulador de Combate&nbsp;</legend>";
				echo "
					<a href='/talim?s=admin_sim_browser'>
						<div class='bioSheetPower adm-admin-tile'>
							Temporadas Simulador
						</div>
					</a>
					<a href='/talim?s=admin_sim_character_talk'>
						<div class='bioSheetPower adm-admin-tile'>
							Frases PJs Simulador
						</div>
					</a>
				";
				echo "</fieldset>";
					echo "<br />";
				// SISTEMA
				echo "<fieldset class='bioSeccion'><legend>&nbsp;Sistema&nbsp;</legend>";
				echo "
					<a href='/talim?s=admin_menu'>
						<div class='bioSheetPower adm-admin-tile'>
							Editar Men&uacute;
						</div>
					</a>
					<a href='/talim?s=admin_schema_initializer'>
						<div class='bioSheetPower adm-admin-tile'>
							Inicializar Esquema
						</div>
					</a>
					<a href='/talim?s=admin_inspect_db'>
						<div class='bioSheetPower adm-admin-tile'>
							Inspeccionar BDD
						</div>
					</a>
					<a href='/talim?s=admin_mentions_help'>
						<div class='bioSheetPower adm-admin-tile'>
							Ayuda Mentions
						</div>
					</a>
					<a href='/talim?s=logout'>
						<div class='bioSheetPower adm-admin-tile'>
							Cerrar sesi&oacute;n
						</div>
					</a>
					";
				echo "</fieldset>";

				echo "</div>";
		}
	}
?>
