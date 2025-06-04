<?php include("sep/main/main_nav_bar.php"); // Barra Navegación ?>
<h2> Dones </h2>
<fieldset class="grupoHabilidad">
<?php
    $pageSect = "Dones"; // PARA CAMBIAR EL TITULO A LA PAGINA
    $donTypePhrase = "Dones";

    // Consulta segura usando MySQLi
    $consulta = "SELECT id, name, determinante FROM nuevo2_tipo_dones ORDER BY orden";
    $stmt = $link->prepare($consulta);
    $stmt->execute();
    $result = $stmt->get_result();

    $NFilas = $result->num_rows;
    while ($ResultQuery = $result->fetch_assoc()) {
        $typeId = htmlspecialchars($ResultQuery["id"]);
        $typeName = htmlspecialchars($ResultQuery["name"]);
        $determinante = htmlspecialchars($ResultQuery["determinante"]);
        print("
            <a href='index.php?p=tipodon&amp;b=$typeId' title='$typeName'>
                <div class='renglon3col'>
                    $donTypePhrase $determinante $typeName
                </div>
            </a>
        ");
    }

    $numregistros = $NFilas;
?>
</fieldset>
<?php print ("<p align='right'>Categorías: $numregistros</p>"); ?>
