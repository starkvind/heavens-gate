<?php
    $T_inicio = microtime(true);

    //include("ip.php");
    include(__DIR__ . "/app/helpers/heroes.php");
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

?>
<!DOCTYPE html>
<html lang="es">
	<?php include("app/bootstrap/head_work.php"); ?>
    <style>
        /* --- Botón Volver Arriba --- */
        #btnTop{
            position: fixed;
            bottom: 18px;
            right: 18px;
            width: 42px;
            height: 42px;
            border-radius: 50%;
            border: 1px solid #009;
            background: #005;
            color: #00BBFF;                 /* verde terminal */
            font-size: 18px;
            cursor: pointer;
            display: none;                  /* oculto por defecto */
            z-index: 9999;
            box-shadow: 0 6px 18px #007;
        }

        #btnTop:hover{
            background: #006;
            transform: translateY(-2px);
        }
    </style>
    <body id="mainBody">
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
            <button id="btnTop" aria-label="Volver arriba">⮝</button>
            <!-- PIE DE PÁGINA -->
            <table class="todou" style="margin: auto;">
                <tr>
                    <td class="piepagina">
                        <?php include("app/partials/main_pie.php"); ?>
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

<script>
    (function(){
    const btn = document.getElementById('btnTop');

    // Mostrar / ocultar
    window.addEventListener('scroll', function(){
        if (window.scrollY > 300) {
        btn.style.display = 'block';
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
