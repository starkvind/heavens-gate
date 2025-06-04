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
    <title><?= htmlspecialchars(trim(($pageTitle2 ?? '') . ' - ' . ($pageSect ?? '') . ' - ' . $pageTitle, ' -')) ?></title>

    <style>
        body {
            margin: 0;
            padding: 0;
            text-align: center;
        }
        .ocultable {
            display: none;
        }
        .main-wrapper {
            display: inline-block;
            text-align: left;
        }
    </style>

    <script>
        function MostrarOcultar(id) {
            const el = document.getElementById(id);
            if (el) el.style.display = (el.style.display === "block") ? "none" : "block";
        }

        function recargar(tiempo) {
            if (typeof tiempo === 'undefined') {
                location.reload();
            } else {
                setTimeout(() => location.reload(true), tiempo);
            }
        }

        function textCounter(field, countfield, maxlimit) {
            if (field.value.length > maxlimit) {
                field.value = field.value.substring(0, maxlimit);
            } else {
                countfield.value = maxlimit - field.value.length;
            }
        }

        function popUp(URL) {
            const id = Date.now();
            window.open(URL, id, 'toolbar=0,scrollbars=1,location=0,statusbar=0,menubar=0,resizable=0,width=660,height=600');
        }

        function Permut(flag, img) {
            if (document.images) {
                const image = document.images[img];
                if (image && image.permloaded) {
                    image.src = (flag === 1) ? image.perm.src : image.perm.oldsrc;
                }
            }
        }

        function preloadPermut(img, src) {
            if (document.images) {
                img.onload = null;
                img.perm = new Image();
                img.perm.oldsrc = img.src;
                img.perm.src = src;
                img.permloaded = true;
            }
        }
    </script>
</head>
<body id="mainBody">
    <div class="main-wrapper">
        <!-- CABECERA -->
        <header>
            <a href="index.php?p=news">
                <img src="img/hg_header.png" alt="Heaven's Gate" />
            </a>
        </header>

        <!-- MENÚ USUARIO -->
        <div class="userRightMenu">
            <?php include("sep/main/main_usermenu.php"); ?>
        </div>

        <!-- CONTENIDO -->
        <div class="layout">
            <nav>
                <?php include("sep/main/main_menu.php"); ?>
            </nav>
            <main class="fcentro">
                <?= $pageContent ?>
            </main>
        </div>

        <!-- PIE DE PÁGINA -->
        <footer class="piepagina">
            <?php include("sep/main/main_pie.php"); ?>
        </footer>

        <!-- TIEMPO DE CARGA -->
        <p style="text-align:center;">
            Página generada en <?= round(microtime(true) - $T_inicio, 5); ?> segundos.
        </p>
    </div>
</body>
</html>
