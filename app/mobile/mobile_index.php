<?php
    $routeKey = trim((string)($_GET['p'] ?? ''));
    $mobileTitle = "Heaven's Gate";

    include_once(__DIR__ . '/helpers/chronicle_scope.php');
    include(__DIR__ . '/mobile_routes.php');

    $mobileRouteKey = trim((string)($_GET['p'] ?? ''));
    $mobileIsHome = in_array($mobileRouteKey, ['', 'home'], true);
    $mobileController = $hgMobileRoutes[$mobileRouteKey] ?? __DIR__ . '/controllers/fallback.php';

    ob_start();
    include($mobileController);
    $mobilePageContent = ob_get_clean();
    if (function_exists('hg_normalize_utf8_output')) {
        $mobilePageContent = hg_normalize_utf8_output($mobilePageContent);
    }

    if (!empty($isBarePage)) {
        echo $mobilePageContent;
        exit;
    }

    if (!empty($metaTitle)) {
        $mobileTitle = (string)$metaTitle;
    } else {
        $titleParts = [];
        if (!empty($pageTitle2)) $titleParts[] = $pageTitle2;
        if (!empty($pageSect)) $titleParts[] = $pageSect;
        if (!empty($pageTitle)) $titleParts[] = $pageTitle;
        $mobileTitle = implode(' | ', $titleParts) ?: "Heaven's Gate";
    }

    $allowedThemes = ['classic', 'violet', 'violet-pearl', 'light', 'modern', 'power-save'];
    $activeTheme = isset($_COOKIE['hg_mobile_theme']) ? strtolower((string)$_COOKIE['hg_mobile_theme']) : 'classic';
    if (!in_array($activeTheme, $allowedThemes, true)) {
        $activeTheme = 'classic';
    }
    if ($activeTheme === 'modern') {
        $activeTheme = 'light';
    }
    $bodyThemeClass = 'theme-' . $activeTheme;
    $themeColors = [
        'classic' => '#050150',
        'violet' => '#21113d',
        'violet-pearl' => '#f3eef9',
        'light' => '#f6f7fb',
        'power-save' => '#000000',
    ];
    $activeThemeColor = $themeColors[$activeTheme] ?? $themeColors['classic'];

    if (!function_exists('hg_mobile_h')) {
        function hg_mobile_h($value): string
        {
            return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
    }

    if (!function_exists('hg_mobile_view_url')) {
        function hg_mobile_view_url(string $view): string
        {
            $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
            $path = (string)(parse_url($uri, PHP_URL_PATH) ?? '/');
            $rawQuery = (string)(parse_url($uri, PHP_URL_QUERY) ?? '');
            $query = [];
            if ($rawQuery !== '') {
                parse_str($rawQuery, $query);
            }
            $query['view'] = $view;
            if ($view === 'desktop') {
                $query['theme'] = 'classic';
            }
            $qs = http_build_query($query);
            return $path . ($qs === '' ? '' : ('?' . $qs));
        }
    }

    $mobileCssPath = __DIR__ . '/../../assets/css/hg-mobile.css';
    $mobileJsPath = __DIR__ . '/../../assets/js/hg-mobile.js';
    $mobileCssVersion = is_file($mobileCssPath) ? (string)filemtime($mobileCssPath) : '1';
    $mobileJsVersion = is_file($mobileJsPath) ? (string)filemtime($mobileJsPath) : '1';

    include(__DIR__ . '/mobile_menu.php');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="<?= hg_mobile_h($activeThemeColor) ?>" data-mobile-theme-color>
    <base href="/">
    <title><?= hg_mobile_h($mobileTitle) ?></title>
    <link rel="shortcut icon" href="img/ui/branding/infinidice.ico" type="image/x-icon">
    <link rel="stylesheet" href="assets/css/hg-mobile.css?v=<?= hg_mobile_h($mobileCssVersion) ?>">
    <?php
        // Mobile controllers are buffered before <head>, so embedded tools can
        // register their page-scoped styles without falling back to body links.
        if (function_exists('hg_page_render_registered_styles')) {
            hg_page_render_registered_styles();
        }
    ?>
    <script src="assets/js/hg-mobile.js?v=<?= hg_mobile_h($mobileJsVersion) ?>" defer></script>
</head>
<body class="hg-mobile-body <?= hg_mobile_h($bodyThemeClass) ?>">
    <div class="hg-mobile-shell">
        <header class="hg-mobile-topbar">
            <a class="hg-mobile-brand" href="/home" aria-label="Heaven's Gate">Heaven's Gate</a>
            <div class="hg-mobile-topbar-actions">
                <?php if (!$mobileIsHome): ?>
                    <a class="hg-mobile-home-button" href="/home" aria-label="Volver a inicio">&#8962;<span>Inicio</span></a>
                <?php endif; ?>
                <button class="hg-mobile-menu-button" type="button" data-mobile-menu-toggle aria-expanded="false" aria-controls="hgMobileMenu">Menú</button>
            </div>
        </header>

        <nav class="hg-mobile-menu" id="hgMobileMenu" hidden>
            <?php foreach ($hgMobileMenuGroups as $group): ?>
                <section class="hg-mobile-menu-group">
                    <h2><?= hg_mobile_h($group['label'] ?? '') ?></h2>
                    <div class="hg-mobile-menu-links">
                        <?php foreach (($group['items'] ?? []) as $item): ?>
                            <?php $targetAttr = (($item['target'] ?? '_self') === '_blank') ? ' target="_blank" rel="noopener"' : ''; ?>
                            <a href="<?= hg_mobile_h($item['href'] ?? '#') ?>"<?= $targetAttr ?>><?= hg_mobile_h($item['label'] ?? '') ?></a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>

            <section class="hg-mobile-menu-group">
                <h2>Apariencia</h2>
                <div class="hg-mobile-menu-links hg-mobile-theme-links">
                    <button type="button" data-mobile-theme="classic"<?= $activeTheme === 'classic' ? ' class="is-active" aria-current="true"' : '' ?>>Clásico</button>
                    <button type="button" data-mobile-theme="violet"<?= $activeTheme === 'violet' ? ' class="is-active" aria-current="true"' : '' ?>>Violeta</button>
                    <button type="button" data-mobile-theme="violet-pearl"<?= $activeTheme === 'violet-pearl' ? ' class="is-active" aria-current="true"' : '' ?>>Violeta perla</button>
                    <button type="button" data-mobile-theme="light"<?= $activeTheme === 'light' ? ' class="is-active" aria-current="true"' : '' ?>>Claro</button>
                    <button type="button" data-mobile-theme="power-save"<?= $activeTheme === 'power-save' ? ' class="is-active" aria-current="true"' : '' ?>>Ahorro energía</button>
                </div>
            </section>
        </nav>

        <main class="hg-mobile-content" data-route="<?= hg_mobile_h($routeKey) ?>" data-mobile-native="<?= isset($hgMobileRoutes[$mobileRouteKey]) ? '1' : '0' ?>">
            <?= $mobilePageContent ?>
        </main>
        <button class="hg-mobile-back-top" type="button" data-mobile-back-top aria-label="Volver arriba">&uarr;</button>

        <footer class="hg-mobile-footer">
            <span>Vista móvil</span>
            <a href="<?= hg_mobile_h(hg_mobile_view_url('desktop')) ?>">Desktop</a>
        </footer>
    </div>
</body>
</html>