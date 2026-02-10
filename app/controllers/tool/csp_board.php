<?php include("app/partials/main_nav_bar.php");	// Barra Navegación ?>
<h2>Tablón de Mensajes</h2>

<center>
    <table class="tablax">
        <tr><td colspan="6">
        <br/>
        </td></tr>
        <?php
            $pageSect = "Tablón de mensajes"; // PARA CAMBIAR EL TITULO A LA PAGINA

            global $link;

            // ORDEN GUAY

            $consulta ="SELECT autor, titulo, mensaje, fecha FROM fact_csp_posts ORDER BY id DESC";

            $stmt = mysqli_prepare($link, $consulta);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

                while ($ResultQuery = mysqli_fetch_assoc($result)) {
                    print("
                    <tr>
                    <td class='klax1'>Autor:</td><td class='klax2'>".$ResultQuery["autor"]."</td>
                    <td class='klax1'>Fecha:</td><td class='klax2'>".$ResultQuery["fecha"]."</td>
                    </tr><tr><td class='klax1'>T&iacute;tulo:</td>
                    <td colspan='3' class='klax2'>".$ResultQuery["titulo"]."</td>
                    </tr>
                    ");

                    print("
                    <tr>
                    <td colspan='6' class='klax2'>
                    <p>".nl2br($ResultQuery["mensaje"])."</p>\n
                    </td>
                    </tr>
                    ");

                    print("
                    <tr>
                    <td colspan='6'>&nbsp;</td>
                    </tr>
                    ");
                }
        ?>
    </table>
</center>