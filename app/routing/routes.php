<?php

return [
    // Principal
    'home'       => ['app/controllers/main/main_home.php', 'Inicio'],
    'news'       => ['app/controllers/main/main_news.php', 'Noticias'],
    'status'     => ['app/controllers/main/main_status.php', 'Estado'],
    'about'      => ['app/controllers/main/main_about.php', 'Acerca de...'],
    'biblio'     => ['app/controllers/main/main_biblio.php', 'Bibliografía'],
    'busq'       => ['app/controllers/main/main_search_form.php', 'Búsqueda'],
    'busk'       => ['app/controllers/main/main_search_result.php', 'Resultado de la búsqueda'],
    'talim'      => ['app/controllers/admin/admin_main.php', 'Administracion'],
    'error404'   => ['app/controllers/main/error404.php', 'Error'],

    // Temporadas
    'seasons_home'       => ['app/controllers/chapters/seasons_home.php', 'Temporadas'],
    'seasons_complete'   => ['app/controllers/chapters/seasons_home.php', 'Temporadas'],
    'seasons_interludes' => ['app/controllers/chapters/seasons_home.php', 'Temporadas'],
    'seasons_personal'   => ['app/controllers/chapters/seasons_home.php', 'Historias personales'],
    'seasons_specials'   => ['app/controllers/chapters/seasons_home.php', 'Especiales'],
    'season_order'       => ['app/controllers/chapters/season_order.php', 'Orden de temporadas'],
    'temp'               => ['app/controllers/chapters/season_archive.php', 'Temporadas'],
    'chapters_table'     => ['app/controllers/chapters/chapter_table.php', 'Capítulos'],
    'seechapter'         => ['app/controllers/chapters/chapter_page.php', 'Capítulos'],
    'temp_analisis'      => ['app/controllers/chapters/season_attendance_analysis.php', 'Análisis asistencia'],

    // Plots
    'party' => ['app/controllers/main/main_parties.php', 'Equipos activos'],

    // Biografías
    'bios'              => ['app/controllers/bio/bio_list.php', 'Biografias'],
    'biogroup'          => ['app/controllers/bio/bio_group.php', 'Biografias por Grupo'],
    'muestrabio'        => ['app/controllers/bio/bio_page.php', 'Biografia'],
    'listgroups'        => ['app/controllers/bio/bio_pack_list.php', null],
    'seegroup'          => ['app/controllers/bio/bio_pack_page.php', null],
    'chronicles'        => ['app/controllers/main/main_chronicles.php', 'Crónicas'],
    'chronicle_image'   => ['app/controllers/main/chronicle_image.php', null],
    'bio_chronicles'    => ['app/controllers/main/main_chronicles.php', 'Crónicas'],
    'bio_worlds'        => ['app/controllers/bio/bio_worlds.php', null],
    'list_table'        => ['app/controllers/bio/bio_table.php', null],
    'nebula_clan'       => ['app/controllers/bio/bio_reltree_clans.php', 'Nebulosa de relaciones'],
    'nebula_character'  => ['app/controllers/bio/bio_reltree_characters.php', 'Nebulosa de relaciones'],
    'nebula_groups'     => ['app/controllers/bio/bio_reltree_groups.php', 'Nebulosa de relaciones'],
    'org_chart'         => ['app/controllers/bio/bio_org_chart.php', 'Organigrama'],

    // Documentación
    'listadocs' => ['app/controllers/docs/docs_table.php', null],
    'verdoc'    => ['app/controllers/docs/docs_page.php', null],
    'rules'     => ['app/controllers/docs/rules_home.php', null],

    // Inventario
    'inv'      => ['app/controllers/docs/item_table.php', null],
    'inv_type' => ['app/controllers/docs/item_list.php', null],
    'seeitem'  => ['app/controllers/docs/item_page.php', null],
    'imgz'     => ['app/controllers/tool/img_board.php', null],
    'listaobj' => ['app/controllers/docs/item_table.php', null],
    'verobj'   => ['app/controllers/docs/item_page.php', null],

    // Sistemas
    'listasistemas'  => ['app/controllers/systems/systems_table.php', null],
    'sistemas'       => ['app/controllers/systems/system_overview_page.php', null],
    'versistdetalle' => ['app/controllers/systems/system_detail_page.php', null],
    'verforma'       => ['app/controllers/systems/system_form_page.php', null],

    // Rasgos
    'listarasgos'    => ['app/controllers/docs/traits_table.php', null],
    'verrasgo'       => ['app/controllers/docs/traits_page.php', null],
    'listconditions' => ['app/controllers/docs/conditions_table.php', null],
    'vercondition'   => ['app/controllers/docs/condition_page.php', null],
    'actions'        => ['app/controllers/docs/action_table.php', null],
    'veraction'      => ['app/controllers/docs/action_page.php', null],
    'maneuver'       => ['app/controllers/docs/maneuver_list.php', null],
    'vermaneu'       => ['app/controllers/docs/maneuver_page.php', null],
    'arquetip'       => ['app/controllers/docs/arche_table.php', null],
    'verarch'        => ['app/controllers/docs/arche_page.php', null],

    // Méritos y fallos
    'listamyd' => ['app/controllers/docs/merfla_table.php', null],
    'vermyd'   => ['app/controllers/docs/merfla_page.php', null],

    // Poderes
    'powers' => ['app/controllers/pwrs/powers_home.php', null],

    // Dones
    'dones'      => ['app/controllers/pwrs/don_category_list.php', null],
    'tipodon'    => ['app/controllers/pwrs/don_group_list.php', null],
    'muestradon' => ['app/controllers/pwrs/don_page.php', null],
    'listadones' => ['app/controllers/pwrs/don_table.php', null],
    'fulldon'    => ['app/controllers/pwrs/don_full_list.php', null],
    'customdon'  => ['app/controllers/pwrs/don_custom_list.php', null],

    // Rituales
    'rites'      => ['app/controllers/pwrs/rite_category_list.php', null],
    'tiporite'   => ['app/controllers/pwrs/rite_group_list.php', null],
    'seerite'    => ['app/controllers/pwrs/rite_page.php', null],
    'ritelist'   => ['app/controllers/pwrs/rite_table.php', null],
    'fullrite'   => ['app/controllers/pwrs/rite_full_list.php', null],
    'customrite' => ['app/controllers/pwrs/rite_custom_list.php', null],

    // Totems
    'totems'       => ['app/controllers/pwrs/totm_category_list.php', null],
    'tipototm'     => ['app/controllers/pwrs/totm_group_list.php', null],
    'listatotems'  => ['app/controllers/pwrs/totm_table.php', null],
    'fulltotem'    => ['app/controllers/pwrs/totm_full_list.php', null],
    'muestratotem' => ['app/controllers/pwrs/totm_page.php', null],
    'customtotem'  => ['app/controllers/pwrs/totm_custom_list.php', null],

    // Disciplinas
    'disciplinas' => ['app/controllers/pwrs/disc_table.php', null],
    'tipodisc'    => ['app/controllers/pwrs/disc_group_list.php', null],
    'muestradisc' => ['app/controllers/pwrs/disc_page.php', null],
    'fulldisc'    => ['app/controllers/pwrs/disc_full_list.php', null],
    'customdisc'  => ['app/controllers/pwrs/disc_custom_list.php', null],

    // Herramientas
    'csp'                         => ['app/controllers/tool/csp_board.php', null],
    'dados'                       => ['app/controllers/tool/dice_roller.php', 'Tiradados'],
    'dice_api'                    => ['app/controllers/tool/dice_api.php', null],
    'forum_avatar_tool'           => ['app/controllers/tool/forum_avatar_builder.php', 'Creador de mensajes foro'],
    'forum_avatar_api'            => ['app/controllers/tool/forum_avatar_api.php', null],
    'forum_topic_viewer'          => ['app/controllers/tool/forum_topic_viewer.php', 'Visor de temas foro'],
    'garou_name_gen'              => ['app/controllers/tool/garou_name_generator.php', 'Generador de nombres Garou'],
    'combat_simulator'            => ['app/controllers/tool/combat_simulator.php', 'Simulador de Combate'],
    'combat_simulator_result'     => ['app/controllers/tool/combat_simulator.php', 'Resultado del Combate'],
    'combat_simulator_logs'       => ['app/controllers/tool/combat_simulator.php', 'Registro de Combates'],
    'combat_simulator_log'        => ['app/controllers/tool/combat_simulator.php', 'Detalle del Combate'],
    'combat_simulator_scores'     => ['app/controllers/tool/combat_simulator.php', 'Puntuaciones'],
    'combat_simulator_weapons'    => ['app/controllers/tool/combat_simulator.php', 'Armas utilizadas'],
    'combat_simulator_tournament' => ['app/controllers/tool/combat_simulator.php', 'Torneo del Simulador'],
    'game_cards'                  => ['app/controllers/tool/game_cards.php', 'Archivo de mnemógeno'],
    'game_cards_collection'       => ['app/controllers/tool/game_cards.php', 'Colección de mnemógeno'],
    'game_cards_combat'           => ['app/controllers/tool/game_cards.php', 'Combate del Archivo de Mnemógeno'],
    'game_cards_mobile'           => ['app/controllers/tool/game_cards_mobile.php', 'Archivo móvil de mnemógeno'],
    'game_cards_explanation'      => ['app/controllers/tool/game_cards.php', 'Explicación del Archivo de Mnemógeno'],
    'game_cards_lab'              => ['app/controllers/tool/game_cards.php', 'Archivo de mnemÃ³geno Dev Lab'],
    'game_cards_lab_collection'   => ['app/controllers/tool/game_cards.php', 'ColecciÃ³n de mnemÃ³geno Dev Lab'],
    'game_cards_lab_combat'       => ['app/controllers/tool/game_cards.php', 'Combate del Archivo de MnemÃ³geno Dev Lab'],
    'game_cards_lab_mobile'       => ['app/controllers/tool/game_cards_mobile.php', 'Archivo mÃ³vil de mnemÃ³geno Dev Lab'],
    'game_cards_lab_explanation'  => ['app/controllers/tool/game_cards.php', 'ExplicaciÃ³n del Archivo de MnemÃ³geno Dev Lab'],

    // Legacy aliases
    'simulador'      => ['app/controllers/tool/combat_simulator.php', 'Simulador de Combate'],
    'simulador2'     => ['app/controllers/tool/combat_simulator.php', 'Resultado del Combate'],
    'combtodo'       => ['app/controllers/tool/combat_simulator.php', 'Registro de Combates'],
    'vercombat'      => ['app/controllers/tool/combat_simulator.php', 'Detalle del Combate'],
    'punts'          => ['app/controllers/tool/combat_simulator.php', 'Puntuaciones'],
    'arms'           => ['app/controllers/tool/combat_simulator.php', 'Armas utilizadas'],
    'sim_tournament' => ['app/controllers/tool/combat_simulator.php', 'Torneo del Simulador'],
    'crop'           => ['app/tools/crop.html', 'Recortador de imágenes'],

    // Banda sonora
    'ost' => ['app/controllers/ost/bso_main.php', 'Banda sonora'],

    // Línea temporal
    'timeline'       => ['app/controllers/main/events_main.php', 'Línea temporal'],
    'timeline_event' => ['app/controllers/main/events_page.php', 'Evento'],

    // Galería
    'gallery'  => ['app/controllers/main/main_gallery.php', 'Galeria de imagenes'],
    'tooltip'  => ['app/controllers/tool/tooltip.php', null],
    'mentions' => ['app/controllers/tool/mentions.php', null],

    // Mapas
    'maps'        => ['app/controllers/maps/maps_main.php', 'Mapas'],
    'maps_detail' => ['app/controllers/maps/maps_detail.php', 'Punto de interés'],
    'maps_api'    => ['app/controllers/maps/maps_api.php', null],

    // Jugadores
    'players'   => ['app/controllers/playr/playr_list.php', 'Jugadores'],
    'seeplayer' => ['app/controllers/playr/playr_page.php', 'Jugador'],

    // Snippets del foro
    'forum_message'  => ['app/partials/forum_message_snippet.php', null],
    'forum_diceroll' => ['app/partials/forum_diceroll_snippet.php', null],
    'forum_item'     => ['app/partials/forum_item_snippet.php', null],
];
