<!DOCTYPE html>
<html lang="es">
    <?php include("app/bootstrap/head_work.php"); ?>
    <body id="mainBody" class="<?= htmlspecialchars($bodyThemeClass, ENT_QUOTES, 'UTF-8') ?>">
        <div class="main-wrapper">
            <!-- CABECERA -->
            <header class="main-header"><img src="img/ui/branding/hg_header.webp" alt="Heaven's Gate" /></header>
            <!-- CONTENIDO -->
            <div class="site-shell">
                <aside class="site-nav" aria-label="Navegación principal">
                    <?php include("app/partials/main_menu.php"); ?>
                </aside>
                <main class="fcentro" id="mainContent">
                    <?= $pageContent ?>
                </main>
            </div>
            <button id="btnTop" class="layout-btn-top" aria-label="Volver arriba">&#x1F845;</button>
            <!-- PIE DE PAGINA -->
            <footer class="piepagina">
                <?php include("app/partials/main_footer.php"); ?>
            </footer>
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
