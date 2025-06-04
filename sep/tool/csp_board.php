<?php include("sep/main/main_nav_bar.php");	// Barra Navegación ?>
<h2>Tablón de Mensajes</h2>

<center>
    <div class="tablax">
        <div><br/></div>
        <?php
            $pageSect = "Tablón de mensajes"; // PARA CAMBIAR EL TITULO A LA PAGINA

            global $link;

            // ORDEN GUAY

            $consulta ="SELECT autor, titulo, mensaje , fecha FROM csp ORDER BY id DESC";

            $stmt = mysqli_prepare($link, $consulta);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

                while ($ResultQuery = mysqli_fetch_assoc($result)) {
                    print("
                    <div class='klax1'>Autor:</div><div class='klax2'>".$ResultQuery["autor"]."</div>
                    <div class='klax1'>Fecha:</div><div class='klax2'>".$ResultQuery["fecha"]."</div>
                    <div class='klax1'>T&iacute;tulo:</div>
                    <div class='klax2'>".$ResultQuery["titulo"]."</div>
                    ");

                    print("
                    <div class='klax2'>
                    <p>".nl2br($ResultQuery["mensaje"])."</p>\n
                    </div>
                    ");

                    print("
                    <div>&nbsp;</div>
                    ");
                }
        ?>
    </div>
</center>