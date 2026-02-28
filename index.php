<?php
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=UTF-8');
    }
    ini_set('default_charset', 'UTF-8');
    if (function_exists('mb_internal_encoding')) {
        mb_internal_encoding('UTF-8');
    }

    $T_inicio = microtime(true);

    //include("ip.php");
    include(__DIR__ . "/app/helpers/db_connection.php");
    include(__DIR__ . "/app/bootstrap/error_reporting.php");

    $pageTitle = "Heaven's Gate";
    $unknownOrigin = "-";

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $uri = $_SERVER['REQUEST_URI'];

    $pageURL = $scheme . '://' . $host . $uri;
    $baseURL = (!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];

    ob_start();
    include("app/bootstrap/body_work.php");
    $pageContent = ob_get_clean();

    if (!empty($isBarePage)) {
        // Strip UTF-8 BOM if present (breaks JSON parsing for AJAX endpoints)
        if (substr($pageContent, 0, 3) === "\xEF\xBB\xBF") {
            $pageContent = substr($pageContent, 3);
        }
        echo $pageContent;
        exit;
    }

    $allowedThemes = ['classic', 'modern', 'power-save'];
    $requestedTheme = isset($_GET['theme']) ? strtolower(trim((string)$_GET['theme'])) : '';
    if ($requestedTheme !== '' && in_array($requestedTheme, $allowedThemes, true)) {
        setcookie('hg_theme', $requestedTheme, time() + 31536000, '/');
        $_COOKIE['hg_theme'] = $requestedTheme;
    }
    $activeTheme = isset($_COOKIE['hg_theme']) ? strtolower((string)$_COOKIE['hg_theme']) : 'classic';
    if (!in_array($activeTheme, $allowedThemes, true)) {
        $activeTheme = 'classic';
    }
    $bodyThemeClass = 'theme-' . $activeTheme;
?>
<!DOCTYPE html>
<html lang="es">
    <?php include("app/bootstrap/head_work.php"); ?>
    <body id="mainBody" class="<?= htmlspecialchars($bodyThemeClass, ENT_QUOTES, 'UTF-8') ?>">
        <div class="main-wrapper">
            <!-- CABECERA -->
            <header><img src="img/ui/branding/hg_header.png" alt="Heaven's Gate" /></header>
            <!-- CONTENIDO -->
            <table class="todou">
                <tr>
                    <td valign="top">
                        <?php include("app/partials/main_menu.php"); ?>
                    </td>
                    <td class="fcentro" valign="top">
                        <?= $pageContent ?>
                    </td>
                </tr>
            </table>
            <button id="btnTop" class="layout-btn-top" aria-label="Volver arriba">&#x1F845;</button>
            <!-- PIE DE PAGINA -->
            <table class="todou layout-footer-table">
                <tr>
                    <td class="piepagina">
                        <?php include("app/partials/main_footer.php"); ?>
                    </td>
                </tr>
            </table>
            <!-- TIEMPO DE CARGA -->
            <p class="layout-render-time">
                Pagina generada en <?= round(microtime(true) - $T_inicio, 5); ?> segundos.
            </p>
        </div>
    </body>
</html>

<script>
    (function(){
    const btn = document.getElementById('btnTop');

    // Mostrar / ocultar
    window.addEventListener('scroll', function(){
        if (window.scrollY > 300) {
        btn.style.display = 'flex';
        } else {
        btn.style.display = 'none';
        }
    });

    // Scroll suave hacia arriba
    btn.addEventListener('click', function(){
        window.scrollTo({
        top: 0,
        behavior: 'smooth'
        });
    });
    })();
</script>
