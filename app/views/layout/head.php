<?php
    $metaDescription = $metaDescription ?? "Heaven's Gate es una campana de rol ambientada en un Mundo de Tinieblas completamente nuevo. Descubre biografias, clanes, poderes, temporadas y una nebulosa de relaciones entre personajes.";
    $metaImage = $metaImage ?? null;
    $hgAssetRoot = dirname(__DIR__, 3) . '/assets';
?>
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="/">

    <meta property="og:site_name" content="Heaven's Gate">

    <?php setMetaTags($routeKey ?? '', $pageURL); ?>

    <?php
        if (!empty($metaTitle)) {
            $fullTitle = (string)$metaTitle;
        } else {
            $titleParts = [];
            if (!empty($pageTitle2)) {
                $titleParts[] = $pageTitle2;
            }
            if (!empty($pageSect)) {
                $titleParts[] = $pageSect;
            }
            if (!empty($pageTitle)) {
                $titleParts[] = $pageTitle;
            }
            $fullTitle = implode(' | ', $titleParts);
        }
    ?>
    <title><?= htmlspecialchars($fullTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></title>

    <meta name="theme-color" content="#050150">
    <link rel="shortcut icon" href="/img/ui/branding/infinidice.ico" type="image/x-icon">
    <link rel="apple-touch-icon" sizes="180x180" href="/img/favicon/apple-touch-icon.webp">
    <link rel="icon" type="image/webp" sizes="32x32" href="/img/favicon/favicon-32x32.webp">
    <link rel="icon" type="image/webp" sizes="16x16" href="/img/favicon/favicon-16x16.webp">
    <link rel="manifest" href="/manifest.webmanifest">

    <?php
        $tokensCssVersion = @filemtime($hgAssetRoot . '/css/hg-tokens.css');
        $baseCssVersion = @filemtime($hgAssetRoot . '/css/hg-base.css');
        $coreCssVersion = @filemtime($hgAssetRoot . '/css/hg-core.css');
        $legacyComponentsCssVersion = @filemtime($hgAssetRoot . '/css/hg-legacy-components.css');
        $componentsCssVersion = @filemtime($hgAssetRoot . '/css/hg-components.css');
        $layoutCssVersion = @filemtime($hgAssetRoot . '/css/hg-layout.css');
        $menuCssVersion = @filemtime($hgAssetRoot . '/css/hg-menu.css');
        $permutScriptVersion = @filemtime($hgAssetRoot . '/js/permutloading.js');
        $tabsScriptVersion = @filemtime($hgAssetRoot . '/js/hg-tabs.js');
        $tooltipScriptVersion = @filemtime($hgAssetRoot . '/js/hg-tooltip.js');
        $pwaScriptVersion = @filemtime($hgAssetRoot . '/js/hg-pwa.js');

        $tokensCssVersion = ($tokensCssVersion !== false) ? (int)$tokensCssVersion : 1;
        $baseCssVersion = ($baseCssVersion !== false) ? (int)$baseCssVersion : 1;
        $coreCssVersion = ($coreCssVersion !== false) ? (int)$coreCssVersion : 1;
        $legacyComponentsCssVersion = ($legacyComponentsCssVersion !== false) ? (int)$legacyComponentsCssVersion : 1;
        $componentsCssVersion = ($componentsCssVersion !== false) ? (int)$componentsCssVersion : 1;
        $layoutCssVersion = ($layoutCssVersion !== false) ? (int)$layoutCssVersion : 1;
        $menuCssVersion = ($menuCssVersion !== false) ? (int)$menuCssVersion : 1;
        $permutScriptVersion = ($permutScriptVersion !== false) ? (int)$permutScriptVersion : 1;
        $tabsScriptVersion = ($tabsScriptVersion !== false) ? (int)$tabsScriptVersion : 1;
        $tooltipScriptVersion = ($tooltipScriptVersion !== false) ? (int)$tooltipScriptVersion : 1;
        $pwaScriptVersion = ($pwaScriptVersion !== false) ? (int)$pwaScriptVersion : 1;
    ?>

    <link rel="stylesheet" href="assets/css/hg-tokens.css?v=<?= $tokensCssVersion ?>">
    <link rel="stylesheet" href="assets/css/hg-base.css?v=<?= $baseCssVersion ?>">
    <link rel="stylesheet" href="assets/css/hg-core.css?v=<?= $coreCssVersion ?>">
    <link rel="stylesheet" href="assets/css/hg-legacy-components.css?v=<?= $legacyComponentsCssVersion ?>">
    <link rel="stylesheet" href="assets/css/hg-components.css?v=<?= $componentsCssVersion ?>">
    <link rel="stylesheet" href="assets/css/hg-layout.css?v=<?= $layoutCssVersion ?>">
    <link rel="stylesheet" href="assets/css/hg-menu.css?v=<?= $menuCssVersion ?>">

    <?php
        // Route/domain styles are registered while the controller is buffered and
        // emitted here once, after the global shell styles and before scripts.
        if (function_exists('hg_page_render_registered_styles')) {
            hg_page_render_registered_styles();
        }
    ?>

    <script type="text/javascript" src="assets/js/permutloading.js?v=<?= $permutScriptVersion ?>"></script>
    <script type="text/javascript" src="assets/js/hg-tabs.js?v=<?= $tabsScriptVersion ?>"></script>
    <script type="text/javascript" src="assets/js/hg-tooltip.js?v=<?= $tooltipScriptVersion ?>" defer></script>
    <script type="text/javascript" src="assets/js/hg-pwa.js?v=<?= $pwaScriptVersion ?>" defer></script>
</head>
