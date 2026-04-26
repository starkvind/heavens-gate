<?php
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=UTF-8');
    }
    ini_set('default_charset', 'UTF-8');
    if (function_exists('mb_internal_encoding')) {
        mb_internal_encoding('UTF-8');
    }
    if (function_exists('mb_http_output')) {
        mb_http_output('UTF-8');
    }

    function hg_strip_utf8_bom(string $content): string
    {
        return (substr($content, 0, 3) === "\xEF\xBB\xBF") ? substr($content, 3) : $content;
    }

    function hg_normalize_utf8_output(string $content): string
    {
        $content = hg_strip_utf8_bom($content);

        if (function_exists('mb_check_encoding') && !mb_check_encoding($content, 'UTF-8')) {
            $converted = @mb_convert_encoding($content, 'UTF-8', ['UTF-8', 'Windows-1252', 'ISO-8859-1']);
            if (is_string($converted) && $converted !== '') {
                $content = $converted;
            }
        }

        return $content;
    }

    $T_inicio = microtime(true);

    //include("ip.php");
    require_once(__DIR__ . "/app/helpers/db_connection.php");
    require_once(__DIR__ . "/app/bootstrap/error_reporting.php");
    require_once(__DIR__ . "/app/bootstrap/request_router.php");

    $pageTitle = "Heaven's Gate";
    $unknownOrigin = "-";

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $uri = $_SERVER['REQUEST_URI'];

    $pageURL = $scheme . '://' . $host . $uri;
    $baseURL = (!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];

    hg_request_router_bootstrap($link);

    ob_start();
    include("app/bootstrap/body_work.php");
    $pageContent = ob_get_clean();
    $pageContent = hg_normalize_utf8_output($pageContent);

    if (!empty($isBarePage)) {
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
                Página generada en <?= round(microtime(true) - $T_inicio, 5); ?> segundos.
            </p>

            <audio id="clickSound" src="sounds/ui/click.ogg" preload="auto"></audio>
            <audio id="selectSound" src="sounds/ui/hover.ogg" preload="auto"></audio>
            <audio id="confirmSound" src="sounds/ui/confirm.ogg" preload="auto"></audio>
            <audio id="closeSound" src="sounds/ui/close.ogg" preload="auto"></audio>

            <script>
                (function () {
                    const btn = document.getElementById('btnTop');
                    if (!btn) {
                        return;
                    }

                    window.addEventListener('scroll', function () {
                        btn.style.display = (window.scrollY > 300) ? 'flex' : 'none';
                    });

                    btn.addEventListener('click', function () {
                        window.scrollTo({
                            top: 0,
                            behavior: 'smooth'
                        });
                    });
                })();
            </script>
        </div>
    </body>
</html>
