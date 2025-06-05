<?php
    $T_inicio = microtime(true);

    include("ip.php");
    include("sep/heroes.php");
	include("error_reporting.php");

    $pageTitle = "Heaven's Gate";
	$unknownOrigin = "Desconocido";
	
	$pageURL = urlencode("https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    $linkFacebook = "http://www.facebook.com/sharer.php?u=$pageURL";
    $linkTwitter = "http://twitter.com/home?status=$pageURL";
    $linkGoogle = "https://plus.google.com/share?url=$pageURL";

    ob_start();
    include("sep/body_work.php");
    $pageContent = ob_get_clean();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Archivo de personajes y narrativa de la campaña Heaven's Gate, Mundo de Tinieblas.">
    <link rel="shortcut icon" href="img/infinidice.ico" type="image/x-icon">
    <link rel="stylesheet" href="nemesis.css">
    <title><?= htmlspecialchars(trim(($pageTitle2 ?? '') . ' - ' . ($pageSect ?? '') . ' - ' . $pageTitle, ' -')) ?></title>
	<!-- Sonidos -->
	<audio id="clickSound" src="sounds/click.ogg" preload="auto"></audio>
	<audio id="selectSound" src="sounds/hover.ogg" preload="auto"></audio>
	<audio id="confirmSound" src="sounds/confirm.ogg" preload="auto"></audio>
	<!-- Javascript -->
	<script type="text/javascript" src="pemutloading.js"></script>
</head>
<body id="mainBody">
    <div class="main-wrapper">
        <!-- CABECERA -->
		<header><img src="img/hg_header.png" alt="Heaven's Gate" /></header>
        <!-- MENÚ USUARIO -->
        <div class="userRightMenu">
            <?php include("sep/main/main_usermenu.php"); ?>
        </div>
        <!-- CONTENIDO -->
        <table class="todou">
            <tr>
                <td valign="top">
                    <?php include("sep/main/main_menu.php"); ?>
                </td>
                <td class="fcentro" valign="top">
                    <?= $pageContent ?>
                </td>
            </tr>
        </table>
        <!-- PIE DE PÁGINA -->
        <table class="todou">
            <tr>
                <td class="piepagina">
                    <?php include("sep/main/main_pie.php"); ?>
                </td>
            </tr>
        </table>
        <!-- TIEMPO DE CARGA -->
        <p style="text-align:center;">
            Página generada en <?= round(microtime(true) - $T_inicio, 5); ?> segundos.
        </p>
    </div>
</body>
</html>
