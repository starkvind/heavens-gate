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

    $themeOptions = [
        'heavens-blue' => 'Heavens-Blue',
        'ember-red' => 'Ember-Red',
        'sky-white' => 'Sky-White',
    ];
    $themeAliases = [
        // Compatibilidad con cookies/links legacy
        'classic' => 'heavens-blue',
        'modern' => 'heavens-blue',
        'power-save' => 'ember-red',
    ];
    $normalizeTheme = static function (string $theme) use ($themeOptions, $themeAliases): string {
        $theme = strtolower(trim($theme));
        if (isset($themeAliases[$theme])) {
            return $themeAliases[$theme];
        }
        if (isset($themeOptions[$theme])) {
            return $theme;
        }
        return 'heavens-blue';
    };

    $requestedTheme = isset($_GET['theme']) ? $normalizeTheme((string)$_GET['theme']) : '';
    if ($requestedTheme !== '') {
        setcookie('hg_theme', $requestedTheme, time() + 31536000, '/');
        $_COOKIE['hg_theme'] = $requestedTheme;
    }
    $activeTheme = $normalizeTheme((string)($_COOKIE['hg_theme'] ?? 'heavens-blue'));
    $bodyThemeClass = 'theme-' . $activeTheme;

    $themeLinks = [];
    $currentQuery = $_GET;
    foreach ($themeOptions as $themeKey => $themeLabel) {
        $q = $currentQuery;
        $q['theme'] = $themeKey;
        $themeLinks[] = [
            'label' => $themeLabel,
            'href' => '?' . http_build_query($q),
            'active' => ($themeKey === $activeTheme),
        ];
    }
?>
<!DOCTYPE html>
<html lang="es">
    <?php include("app/bootstrap/head_work.php"); ?>
    <body id="mainBody" class="<?= htmlspecialchars($bodyThemeClass, ENT_QUOTES, 'UTF-8') ?>">
        <div class="main-wrapper app-shell" id="appShell">
            <!-- CABECERA -->
            <header class="app-header">
                <button
                    id="menuToggleBtn"
                    class="app-menu-toggle"
                    type="button"
                    aria-controls="appSidebar"
                    aria-expanded="false"
                    aria-label="Abrir menú"
                >☰</button>
                <img src="img/ui/branding/hg_header.png" alt="Heaven's Gate" />
            </header>
            <div class="app-layout">
                <aside id="appSidebar" class="app-sidebar" aria-label="Menú principal">
                    <?php include("app/partials/main_menu.php"); ?>
                </aside>
                <main class="app-content fcentro">
                    <?= $pageContent ?>
                </main>
            </div>
            <div id="appSidebarOverlay" class="app-sidebar-overlay" aria-hidden="true"></div>
            <button id="btnTop" class="layout-btn-top" aria-label="Volver arriba">&#x1F845;</button>
            <!-- PIE DE PAGINA -->
            <footer class="layout-footer-table piepagina">
                <?php include("app/partials/main_footer.php"); ?>
            </footer>
            <!-- TIEMPO DE CARGA -->
            <p class="layout-render-time">
                Pagina generada en <?= round(microtime(true) - $T_inicio, 5); ?> segundos.
            </p>
            <div class="layout-theme-switcher" aria-label="Selector de tema">
                <span class="layout-theme-switcher__label">Tema:</span>
                <?php foreach ($themeLinks as $themeLink): ?>
                    <a
                        class="layout-theme-switcher__link<?= $themeLink['active'] ? ' is-active' : '' ?>"
                        href="<?= htmlspecialchars($themeLink['href'], ENT_QUOTES, 'UTF-8') ?>"
                    ><?= htmlspecialchars($themeLink['label'], ENT_QUOTES, 'UTF-8') ?></a>
                <?php endforeach; ?>
            </div>
        </div>
    </body>
</html>

<script>
    (function(){
    const btn = document.getElementById('btnTop');
    const shell = document.getElementById('appShell');
    const menuBtn = document.getElementById('menuToggleBtn');
    const overlay = document.getElementById('appSidebarOverlay');
    const mobileMq = window.matchMedia('(max-width: 980px)');

    function closeMenu() {
        if (!shell) return;
        shell.classList.remove('menu-open');
        if (menuBtn) {
            menuBtn.setAttribute('aria-expanded', 'false');
            menuBtn.setAttribute('aria-label', 'Abrir menú');
        }
    }

    function toggleMenu() {
        if (!shell) return;
        const open = shell.classList.toggle('menu-open');
        if (menuBtn) {
            menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
            menuBtn.setAttribute('aria-label', open ? 'Cerrar menú' : 'Abrir menú');
        }
    }

    if (menuBtn) menuBtn.addEventListener('click', toggleMenu);
    if (overlay) overlay.addEventListener('click', closeMenu);
    window.addEventListener('keydown', function(e){
        if (e.key === 'Escape') closeMenu();
    });
    if (mobileMq && mobileMq.addEventListener) {
        mobileMq.addEventListener('change', function(e){
            if (!e.matches) closeMenu();
        });
    }

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
