<?php

$mobileEmbeddedRoutes = [
    'dados' => [
        'title' => "Tiradados | Heaven's Gate",
        'description' => 'Tiradados móvil reutilizando la herramienta publica.',
        'section' => 'Herramientas',
        'class' => 'hg-mobile-dice-tool',
        'file' => __DIR__ . '/../../controllers/tool/dice_roller.php',
    ],
    'csp' => [
        'title' => "Tablón CSP | Heaven's Gate",
        'description' => 'Tablón de mensajes CSP en vista móvil.',
        'section' => 'Herramientas',
        'class' => 'hg-mobile-csp-tool',
        'file' => __DIR__ . '/../../controllers/tool/csp_board.php',
    ],
    'combat_simulator' => [
        'title' => "Simulador de Combate | Heaven's Gate",
        'description' => 'Simulador de combate en vista móvil.',
        'section' => 'Juegos',
        'class' => 'hg-mobile-combat-tool',
        'file' => __DIR__ . '/../../controllers/tool/combat_simulator.php',
    ],
    'combat_simulator_result' => [
        'title' => "Resultado del combate | Heaven's Gate",
        'description' => 'Resultado del simulador de combate en vista móvil.',
        'section' => 'Juegos',
        'class' => 'hg-mobile-combat-tool',
        'file' => __DIR__ . '/../../controllers/tool/combat_simulator.php',
    ],
    'combat_simulator_logs' => [
        'title' => "Registro de combates | Heaven's Gate",
        'description' => 'Registro del simulador de combate en vista móvil.',
        'section' => 'Juegos',
        'class' => 'hg-mobile-combat-tool',
        'file' => __DIR__ . '/../../controllers/tool/combat_simulator.php',
    ],
    'combat_simulator_log' => [
        'title' => "Detalle de combate | Heaven's Gate",
        'description' => 'Detalle del simulador de combate en vista móvil.',
        'section' => 'Juegos',
        'class' => 'hg-mobile-combat-tool',
        'file' => __DIR__ . '/../../controllers/tool/combat_simulator.php',
    ],
    'combat_simulator_scores' => [
        'title' => "Puntuaciones | Heaven's Gate",
        'description' => 'Puntuaciones del simulador de combate en vista móvil.',
        'section' => 'Juegos',
        'class' => 'hg-mobile-combat-tool',
        'file' => __DIR__ . '/../../controllers/tool/combat_simulator.php',
    ],
    'combat_simulator_weapons' => [
        'title' => "Armas utilizadas | Heaven's Gate",
        'description' => 'Armas del simulador de combate en vista móvil.',
        'section' => 'Juegos',
        'class' => 'hg-mobile-combat-tool',
        'file' => __DIR__ . '/../../controllers/tool/combat_simulator.php',
    ],
    'combat_simulator_tournament' => [
        'title' => "Torneos | Heaven's Gate",
        'description' => 'Torneos del simulador de combate en vista móvil.',
        'section' => 'Juegos',
        'class' => 'hg-mobile-combat-tool',
        'file' => __DIR__ . '/../../controllers/tool/combat_simulator.php',
    ],
    'inv' => [
        'title' => "Inventario | Heaven's Gate",
        'description' => 'Inventario en vista móvil.',
        'section' => 'Inventario',
        'class' => 'hg-mobile-inventory-embed',
        'file' => __DIR__ . '/../../controllers/docs/item_table.php',
    ],
    'inv_type' => [
        'title' => "Inventario | Heaven's Gate",
        'description' => 'Categoría de inventario en vista móvil.',
        'section' => 'Inventario',
        'class' => 'hg-mobile-inventory-embed',
        'file' => __DIR__ . '/../../controllers/docs/item_list.php',
    ],
    'seeitem' => [
        'title' => "Objeto | Heaven's Gate",
        'description' => 'Ficha de objeto en vista móvil.',
        'section' => 'Inventario',
        'class' => 'hg-mobile-inventory-embed',
        'file' => __DIR__ . '/../../controllers/docs/item_page.php',
    ],
    'listasistemas' => [
        'title' => "Sistemas | Heaven's Gate",
        'description' => 'Sistemas en vista móvil.',
        'section' => 'Sistemas',
        'class' => 'hg-mobile-systems-embed',
        'file' => __DIR__ . '/../../controllers/systems/systems_table.php',
    ],
    'sistemas' => [
        'title' => "Sistema | Heaven's Gate",
        'description' => 'Sistema en vista móvil.',
        'section' => 'Sistemas',
        'class' => 'hg-mobile-systems-embed',
        'file' => __DIR__ . '/../../controllers/systems/system_overview_page.php',
    ],
    'versistdetalle' => [
        'title' => "Detalle de sistema | Heaven's Gate",
        'description' => 'Detalle de sistema en vista móvil.',
        'section' => 'Sistemas',
        'class' => 'hg-mobile-systems-embed',
        'file' => __DIR__ . '/../../controllers/systems/system_detail_page.php',
    ],
    'verforma' => [
        'title' => "Forma | Heaven's Gate",
        'description' => 'Forma en vista móvil.',
        'section' => 'Sistemas',
        'class' => 'hg-mobile-systems-embed',
        'file' => __DIR__ . '/../../controllers/systems/system_form_page.php',
    ],
];
$mobileEmbeddedAliases = [
    'listaobj' => 'inv',
    'verobj' => 'seeitem',
    'simulador' => 'combat_simulator',
    'simulador2' => 'combat_simulator_result',
    'combtodo' => 'combat_simulator_logs',
    'vercombat' => 'combat_simulator_log',
    'punts' => 'combat_simulator_scores',
    'arms' => 'combat_simulator_weapons',
    'sim_tournament' => 'combat_simulator_tournament',
];
$route = (string)($_GET['p'] ?? '');
$route = $mobileEmbeddedAliases[$route] ?? $route;
$config = $mobileEmbeddedRoutes[$route] ?? null;
if (!$config || !is_file((string)$config['file'])) {
    include(__DIR__ . '/fallback.php');
    return;
}

$metaTitle = (string)$config['title'];
$metaDescription = (string)$config['description'];
$pageSect = (string)$config['section'];

if (!defined('HG_MOBILE_TOOL_EMBED')) define('HG_MOBILE_TOOL_EMBED', true);
if (!defined('HG_MOBILE_DESKTOP_EMBED')) define('HG_MOBILE_DESKTOP_EMBED', true);
?>
<section class="hg-mobile-section hg-mobile-embed <?= htmlspecialchars((string)$config['class'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
<?php include((string)$config['file']); ?>
</section>