<?php

// Validación y saneamiento de entradas
$daft = isset($_GET['p']) ? htmlspecialchars($_GET['p']) : '';
$punk = isset($_GET['t']) ? htmlspecialchars($_GET['t']) : '';

$mensajeDeError = "Ha ocurrido un error, inténtalo de nuevo.";

if (!isset($pageTitle)) $pageTitle = "Heaven's Gate"; // Valor por defecto si no está definido

// Determinamos la sección y cargamos el archivo correspondiente
switch ($daft) {
    // ============================================================== //
    //  SECCION PRINCIPAL                                             //
    // ============================================================== //
    case "news":
        $pageSect = "Noticias";
        include("main/main_news.php");
        break;
    case "status":
        $pageSect = "Estado";
        include("main/main_status.php");
        break;
    case "about":
        $pageSect = "Acerca de...";
        include("main/main_about.php");
        break;
    case "links":
        $pageSect = "Enlaces";
        include("main/main_links.php");
        break;
    case "biblio":
        $pageSect = "Bibliografía";
        include("main/main_biblio.php");
        break;
    case "players":
        $pageSect = "Jugadores";
        include("pjs/pjs_list.php");
        break;
    case "seeplayer":
        include("pjs/pjs_page.php");
        break;
    case "busq":
        $pageSect = "Búsqueda";
        include("main/main_search_form.php");
        break;
    case "busk":
        $pageSect = "Resultado de la búsqueda";
        include("main/main_search_result.php");
        break;
    case "taki":
        $pageSect = "Acceso";
        include("main/main_bdd_access.php");
        break;
    case "error404":
        $pageSect = "Error";
        include("error404.php");
        break;
    // ============================================================== //
    //  SECCION TEMPORADAS                                            //
    // ============================================================== //
    case "temp":
        include("docs/docs_archivo.php");
        break;
    case "seechapter":
        include("docs/docs_archivo_page.php");
        break;
    // ============================================================== //
    //  SECCION BIOGRAFIAS                                            //
    // ============================================================== //
    case "bios":
        include("bio/bio_list.php");
        break;
    case "biogroup":
        include("bio/bio_group.php");
        break;
    case "muestrabio":
        include("bio/bio_page.php");
        break;
    case "listgroups":
        include("bio/bio_pack_list.php");
        break;
    case "seegroup":
        include("bio/bio_pack_page.php");
        break;
    case "list_by_order":
        include("bio/bio_list_by_order.php");
        break;
    case "list_by_id":
        include("bio/bio_list_id.php");
        break;
    case "list_avatar":
        include("bio/bio_list_noavatar.php");
        break;
    // ============================================================== //
    //  SECCION DOCUMENTACION                                         //
    // ============================================================== //
    case "doc":
        include("docs/docs_document_list.php");
        break;
    case "docx":
        include("docs/docs_document_page.php");
        break;
    // ============================================================== //
    //  SECCION INVENTARIO                                            //
    // ============================================================== //
    case "inv":
        include("inv/inv_inventory_list.php");
        break;
    case "seeitem":
        include("inv/inv_inventory_page.php");
        break;
    case "imgz":
        include("tool/img_board.php");
        break;
    // ============================================================== //
    //  SECCION SISTEMAS                                              //
    // ============================================================== //
    case "sistemas":
        include("syst/syst_system_category_list.php");
        break;
    case "versist":
        include("syst/syst_system_page.php");
        break;
    case "verforma":
        include("syst/syst_system_wereform.php");
        break;
    // ============================================================== //
    //  SECCION HABILIDADES                                           //
    // ============================================================== //
    case "listsk":
        include("skill/skill_list.php");
        break;
    case "skill":
        include("skill/skill_page.php");
        break;
    case "maneuver":
        include("skill/skill_maneuver_list.php");
        break;
    case "vermaneu":
        include("skill/skill_maneuver_page.php");
        break;
    case "arquetip":
        include("skill/skill_arche_list.php");
        break;
    case "verarch":
        include("skill/skill_arche_page.php");
        break;
    case "mflist":
        include("maf/maf_list.php");
        break;
    case "mfgroup":
        include("maf/maf_group.php");
        break;
    case "merfla":
        include("maf/maf_page.php");
        break;
    // ============================================================== //
    //  SECCION PODERES                                               //
    // ============================================================== //
    case "dones":
        include("don/don_category_list.php");
        break;
    case "tipodon":
        include("don/don_group_list.php");
        break;
    case "muestradon":
        include("don/don_page.php");
        break;
    case "rites":
        include("rite/rite_category_list.php");
        break;
    case "tiporite":
        include("rite/rite_group_list.php");
        break;
    case "seerite":
        include("rite/rite_page.php");
        break;
    case "totems":
        include("totm/totm_category_list.php");
        break;
    case "tipototm":
        include("totm/totm_group_list.php");
        break;
    case "muestratotem":
        include("totm/totm_page.php");
        break;
    case "disciplinas":
        include("disc/disc_category_list.php");
        break;
    case "tipodisc":
        include("disc/disc_group_list.php");
        break;
    case "muestradisc":
        include("disc/disc_page.php");
        break;
    // ============================================================== //
    //  SECCION SIMULADOR                                             //
    // ============================================================== //
    case "simulador":
        include("sim/sim_main_simulador.php");
        break;
    case "simulador2":
        include("sim/sim_main_result.php");
        break;
    case "punts":
        include("sim/sim_data_puntuaciones.php");
        break;
    case "arms":
        include("sim/sim_data_armas.php");
        break;
    case "combtodo":
        include("sim/sim_data_combates.php");
        break;
    case "vercombat":
        include("sim/sim_data_ver_combate.php");
        break;
    // ============================================================== //
    //  SECCION HERRAMIENTAS                                          //
    // ============================================================== //
    case "dwn":
        include("tool/dwn_download_list.php");
        break;
    case "csp":
        include("tool/csp_board.php");
        break;
    case "dados":
        include("tool/dice_dados.php");
        break;
    case "faqpj":
        include("tool/info_faqpj_form.php");
        break;
    // ============================================================== //
    //  Página por defecto
    // ============================================================== //
    default:
        $pageSect = "Noticias";
        include("main/main_news.php");
        break;
}

/* // Mostrar el título de la página
echo "<title>";
if (!empty($pageTitle2)) { echo htmlspecialchars($pageTitle2) . " - "; }
if (!empty($pageSect)) { echo htmlspecialchars($pageSect) . " - "; }
echo htmlspecialchars($pageTitle);
echo "</title>"; */

?>