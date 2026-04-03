<?php
	$metaDescription = $metaDescription ?? "Heaven's Gate es una campana de rol ambientada en un Mundo de Tinieblas completamente nuevo. Descubre biografias, clanes, poderes, temporadas y una nebulosa de relaciones entre personajes.";
	$metaImage = $metaImage ?? null;
?>
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="/">

    <meta property="og:site_name" content="Heaven's Gate">

	<?php setMetaTags($routeKey ?? ($_GET['p'] ?? ''), $pageURL); ?>

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

	<link rel="shortcut icon" href="img/ui/branding/infinidice.ico" type="image/x-icon">
	<link rel="apple-touch-icon" sizes="180x180" href="img/favicon/apple-touch-icon.png">
	<link rel="icon" type="image/png" sizes="32x32" href="img/favicon/favicon-32x32.png">
	<link rel="icon" type="image/png" sizes="16x16" href="img/favicon/favicon-16x16.png">
	<link rel="manifest" href="img/favicon/site.webmanifest">

	<link rel="stylesheet" href="assets/css/hg-core.css">
	<link rel="stylesheet" href="assets/css/hg-layout.css">

	<?php
		$tooltipScriptVersion = @filemtime(__DIR__ . '/../../assets/js/hg-tooltip.js') ?: time();
	?>
	<script type="text/javascript" src="assets/js/permutloading.js"></script>
	<script type="text/javascript" src="assets/js/hg-tabs.js"></script>
	<script type="text/javascript" src="assets/js/hg-tooltip.js?v=<?= (int)$tooltipScriptVersion ?>" defer></script>
</head>
