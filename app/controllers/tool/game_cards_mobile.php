<?php
require_once __DIR__ . '/../../helpers/admin_auth.php';

$isBarePage = true;
$gameCardsRoute = (string)($routeKey ?? ($_GET['p'] ?? 'game_cards_mobile'));
$gameCardsLabMode = $gameCardsRoute === 'game_cards_lab_mobile';
$gameCardsBasePath = $gameCardsLabMode ? '/games/hg-cardgame-dev-lab' : '/games/card-game';
$gameCardsCatalogUrl = '/api/game_cards.php';
$gameCardsScriptSrc = '/assets/js/game-cards-v2.js?v=20260614-upgraded-guard' . ($gameCardsLabMode ? '-lab' : '');
$gameCardsStorageScope = $gameCardsLabMode ? 'dev-lab' : 'prod';
$gameCardsBootScripts = $gameCardsLabMode
    ? [
        '/assets/js/card-game/bootstrap/game-card-features.js?v=20260614-dev-lab',
        '/assets/js/card-game/bootstrap/game-card-loader.js?v=20260614-dev-lab',
        '/assets/js/card-game/bootstrap/game-card-app.js?v=20260614-dev-lab',
    ]
    : [];
$hgCardsIsAdmin = function_exists('hg_admin_is_authenticated') && hg_admin_is_authenticated();

$allowedThemes = ['classic', 'modern', 'power-save'];
$activeTheme = isset($_COOKIE['hg_theme']) ? strtolower((string)$_COOKIE['hg_theme']) : 'classic';
if (!in_array($activeTheme, $allowedThemes, true)) {
    $activeTheme = 'classic';
}
$bodyThemeClass = 'theme-' . $activeTheme;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#030713">
    <meta name="robots" content="noindex,follow">
    <title><?php echo $gameCardsLabMode ? 'Archivo de mnemogeno [Dev Lab]' : 'Archivo de mnemogeno'; ?> | Heaven's Gate</title>
    <meta name="description" content="<?php echo $gameCardsLabMode ? 'Modo movil del Dev Lab del minijuego coleccionable de cartas de Heaven&apos;s Gate con almacenamiento local aislado.' : 'Modo movil del minijuego coleccionable de cartas de Heaven&apos;s Gate.'; ?>">
    <link rel="stylesheet" href="/assets/css/hg-core.css">
    <link rel="stylesheet" href="/assets/css/game-cards.css?v=20260530-ui-texts-final-db">
</head>
<body class="hg-card-mobile-body <?= htmlspecialchars($bodyThemeClass, ENT_QUOTES, 'UTF-8') ?>">
    <section class="hg-card-game-shell hg-card-game-shell--standalone">
        <?php include dirname(__DIR__, 2) . '/modules/game_cards/game_cards_mobile_page.php'; ?>
    </section>
    <footer class="hg-mobile-footer">
        <strong>Heaven's Gate</strong>
        <span><?php echo $gameCardsLabMode ? 'Archivo de mnemogeno [Dev Lab]' : 'Archivo de mnemogeno'; ?></span>
        <a href="<?php echo htmlspecialchars($gameCardsBasePath, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">Vista clasica</a>
    </footer>
</body>
</html>
