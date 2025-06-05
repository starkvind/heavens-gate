<?php
    $T_inicio = microtime(true);

    include("ip.php");
    include("sep/heroes.php");

    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    $pageTitle = "Heaven's Gate";

    ob_start();
    include("sep/body_work.php");
    $pageContent = ob_get_clean();

    $pageURL = urlencode("https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    $linkFacebook = "http://www.facebook.com/sharer.php?u=$pageURL";
    $linkTwitter = "http://twitter.com/home?status=$pageURL";
    $linkGoogle = "https://plus.google.com/share?url=$pageURL";
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
	<link rel="stylesheet" href="nemesis-modern.css">
    <script type="text/javascript" src="js_hover.js"></script>
    <title><?= htmlspecialchars(trim(($pageTitle2 ?? '') . ' - ' . ($pageSect ?? '') . ' - ' . $pageTitle, ' -')) ?></title>
</head>
<body>
    <div class="main-wrapper">
        <!-- CABECERA -->
        <header>
            <a href="index.php?p=news">
                <img src="img/hg_header.png" alt="Heaven's Gate" />
            </a>
        </header>
        <!-- CONTENIDO PRINCIPAL -->
        <div class="main-content">
            <aside class="main-menu">
                <?php include("sep/main/main_menu.php"); ?>
            </aside>
            <section class="content-body">
                <?= $pageContent ?>
            </section>
        </div>
        <!-- PIE DE PÁGINA -->
        <footer>
            <?php include("sep/main/main_pie.php"); ?>
            <p>Página generada en <?= round(microtime(true) - $T_inicio, 5); ?> segundos.</p>
        </footer>
    </div>
</body>
</html>
