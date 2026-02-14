<?php
// admin_main.php - Menú principal de administración
	if (session_status() === PHP_SESSION_NONE) {
		session_start();
	}

	// Verificar la conexión a la base de datos
	if (!$link) {
		die("Error de conexión a la base de datos: " . mysqli_connect_error());
	}

	// Si no está logueado, incluir el login
	if (!isset($_SESSION['is_admin'])) {
		include("admin_login.php");
	} else {
		/* MODERNO NUEVO */
		include(__DIR__ . "/../../partials/main_nav_bar.php");	// Barra Navegación
		// Si hay parámetro "s", incluimos la sección correspondiente
		if (isset($_GET['s'])) {
			$seccion = htmlspecialchars($_GET['s']); // Sanear entrada

			switch ($seccion) {
				// case 'admin_pjs_crud':
				// 	include("admin_pjs_crud.php");
				// 	break;
				case 'admin_pjs':
					include("admin_pjs.php");
					break;
				// case 'admin_pjs_text':
				// 	include("admin_pjs_text.php");
				// 	break;
				case 'admin_groups':
					include("admin_groups.php");
					break;
				case 'admin_temp':
					include("admin_temporadas.php");
					break;
				case 'admin_epis':
					include("admin_episodios.php");
					break;
				case 'admin_pois':
					include("admin_pois.php");
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
				case 'admin_gallery':
					include("admin_gallery.php");
					break;
				case 'admin_plots':
					include("admin_plots_crud.php");
					break;
				case 'admin_powers':
					include("admin_powers.php");
					break;
				case 'admin_docs':
					include("admin_docs.php");
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
				case 'logout':
					include("admin_logout.php");
					break;
				default:
					echo "<p style='color:red;'>⚠ Sección no reconocida.</p>";
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
					<a href='/talim?s=admin_pjs&pp=500'>
					  <div class='bioSheetPower' style='width:47.5%;'>
						Gestionar Personajes
					  </div>
					</a>
					<a href='/talim?s=admin_groups'>
						<div class='bioSheetPower' style='width:47.5%;'>
							Gestionar Grupos
						</div>
					</a>
					<a href='/talim?s=admin_bridges'>
						<div class='bioSheetPower' style='width:47.5%;'>
							Editar Bridges
						</div>
					</a>
					<a href='/talim?s=admin_relations'>
						<div class='bioSheetPower' style='width:47.5%;'>
							Editar Relaciones
						</div>
					</a>
					<a href='/talim?s=admin_plots'>
						<div class='bioSheetPower' style='width:47.5%;'>
							Gestionar Grupos en activo
						</div>
					</a>
					";
				echo "</fieldset>";
					echo "<br />";
				// CONTENIDO
				echo "<fieldset class='bioSeccion'><legend>&nbsp;Contenido&nbsp;</legend>";
				echo "
					<a href='/talim?s=admin_news'>
						<div class='bioSheetPower' style='width:47.5%;'>
							Gestionar Noticias
						</div>
					</a>
					<a href='/talim?s=admin_temp'>
						<div class='bioSheetPower' style='width:47.5%;'>
							Gestionar Temporadas
						</div>
					</a>
					<a href='/talim?s=admin_epis'>
						<div class='bioSheetPower' style='width:47.5%;'>
							Gestionar Episodios
						</div>
					</a>
					<a href='/talim?s=admin_docs'>
						<div class='bioSheetPower' style='width:47.5%;'>
							Gestionar Documentaci&oacute;n
						</div>
					</a>
					";
				echo "</fieldset>";
					echo "<br />";
				// MUNDO
				echo "<fieldset class='bioSeccion'><legend>&nbsp;Mundo&nbsp;</legend>";
				echo "
					<a href='/talim?s=admin_pois'>
						<div class='bioSheetPower' style='width:47.5%;'>
							Gestionar Mapas
						</div>
					</a>
					<a href='/talim?s=admin_timelines'>
						<div class='bioSheetPower' style='width:47.5%;'>
							Gestionar L&iacute;nea temporal
						</div>
					</a>
					<a href='/talim?s=admin_gallery'>
						<div class='bioSheetPower' style='width:47.5%;'>
							Gestionar Galer&iacute;a
						</div>
					</a>
					<a href='/talim?s=admin_bso'>
						<div class='bioSheetPower' style='width:47.5%;'>
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
						<div class='bioSheetPower' style='width:47.5%;'>
							Gestionar Objetos
						</div>
					</a>
					<a href='/talim?s=admin_powers'>
						<div class='bioSheetPower' style='width:47.5%;'>
							Gestionar Poderes
						</div>
					</a>
					";
				echo "</fieldset>";
					echo "<br />";
				// REGLAMENTO
				echo "<fieldset class='bioSeccion'><legend>&nbsp;Reglamento&nbsp;</legend>";
				echo "
					<a href='/talim?s=admin_systems'>
						<div class='bioSheetPower' style='width:47.5%;'>
							Gestionar Sistemas
						</div>
					</a>
					<a href='/talim?s=admin_system_details'>
						<div class='bioSheetPower' style='width:47.5%;'>
							Detalles de Sistemas
						</div>
					</a>
					<a href='/talim?s=admin_forms'>
						<div class='bioSheetPower' style='width:47.5%;'>
							Gestionar Formas
						</div>
					</a>
					";
				echo "</fieldset>";
					echo "<br />";
				// SISTEMA
				echo "<fieldset class='bioSeccion'><legend>&nbsp;Sistema&nbsp;</legend>";
				echo "
					<a href='/talim?s=admin_menu'>
						<div class='bioSheetPower' style='width:47.5%;'>
							Editar Men&uacute;
						</div>
					</a>
					<a href='/talim?s=logout'>
						<div class='bioSheetPower' style='width:47.5%;'>
							Cerrar sesi&oacute;n
						</div>
					</a>
					";
				echo "</fieldset>";

				echo "</div>";
		}
	}
?>

