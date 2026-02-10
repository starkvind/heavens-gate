<?php
// Título y descripción dinámicos (puedes definirlos antes de llamar a head.php)
$metaDescription = $metaDescription ?? "Heaven's Gate es una campaña de rol ambientada en un Mundo de Tinieblas completamente nuevo. Descubre biografías, clanes, poderes, temporadas y una nebulosa de relaciones entre personajes.";
$metaImage = $metaImage ?? null; // Deja que setMetaTags use su imagen por defecto
$metaURL = "https://heavensgate.zapto.org" . $_SERVER['REQUEST_URI'];
?>
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="/">
    
    <!--<title> //htmlspecialchars(setMetaTitle()) ?></title>-->
    <meta name="description" content="<?= htmlspecialchars($metaDescription) ?>">
    <meta property="og:site_name" content="Heaven's Gate">

	<?= setMetaTags($_GET['p'] ?? '', $pageURL); ?>
	
    <title><?= htmlspecialchars(trim(($pageTitle2 ?? '') . ' | ' . ($pageSect ?? '') . ' | ' . $pageTitle, ' |')) ?></title>

    <!-- Favicon y estilos -->
	<link rel="shortcut icon" href="img/ui/branding/infinidice.ico" type="image/x-icon">
	
	<link rel="apple-touch-icon" sizes="180x180" href="img/favicon/apple-touch-icon.png">
	<link rel="icon" type="image/png" sizes="32x32" href="img/favicon//favicon-32x32.png">
	<link rel="icon" type="image/png" sizes="16x16" href="img/favicon//favicon-16x16.png">
	<link rel="manifest" href="img/favicon/site.webmanifest">
	
	<link rel="stylesheet" href="assets/css/nemesis.css">
	
	<!-- Sonidos -->
	<audio id="clickSound" src="sounds/ui/click.ogg" preload="auto"></audio>
	<audio id="selectSound" src="sounds/ui/hover.ogg" preload="auto"></audio>
	<audio id="confirmSound" src="sounds/ui/confirm.ogg" preload="auto"></audio>
	<audio id="closeSound" src="sounds/ui/close.ogg" preload="auto"></audio>
	<!-- Javascript -->
	<script type="text/javascript" src="assets/js/permutloading.js"></script>
</head>
