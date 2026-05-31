<?php
if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}

ini_set('default_charset', 'UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/app/helpers/admin_auth.php';

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
    <title>Archivo de mnemógeno | Heaven's Gate</title>
    <meta name="description" content="Modo móvil del minijuego coleccionable de cartas de Heaven's Gate.">
    <link rel="stylesheet" href="/assets/css/hg-core.css">
    <link rel="stylesheet" href="/assets/css/game-cards.css?v=20260530-ui-texts-final-db">
</head>
<body class="hg-card-mobile-body <?= htmlspecialchars($bodyThemeClass, ENT_QUOTES, 'UTF-8') ?>">
    <section class="hg-card-game-shell hg-card-game-shell--standalone">
        <?php include __DIR__ . '/app/modules/game_cards/game_cards_mobile_page.php'; ?>
    </section>
    <footer class="hg-mobile-footer">
        <strong>Heaven's Gate</strong>
        <span>Archivo de mnemógeno</span>
        <a href="/games/card-game">Vista clásica</a>
    </footer>
</body>
</html>
