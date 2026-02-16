<?php include("app/partials/main_nav_bar.php"); // Barra Navegación ?>
setMetaFromPage("Dones | Heaven's Gate", "Categorias de dones.", null, 'website');
<h2> Dones </h2>
<fieldset class="grupoHabilidad">
<?php
    $pageSect = "Dones"; // PARA CAMBIAR EL TITULO A LA PAGINA
    $donTypePhrase = "Dones";

    // Consulta segura usando MySQLi
    $consulta = "SELECT id, name, determinant FROM dim_gift_types ORDER BY sort_order";
    $stmt = $link->prepare($consulta);
    $stmt->execute();
    $result = $stmt->get_result();

    $NFilas = $result->num_rows;
    while ($ResultQuery = $result->fetch_assoc()) {
        $typeId = (int)$ResultQuery["id"];
        $typeName = htmlspecialchars($ResultQuery["name"]);
        $determinant = htmlspecialchars($ResultQuery["determinant"]);
        print("
            <a href='" . htmlspecialchars(pretty_url($link, 'dim_gift_types', '/powers/gift/type', $typeId)) . "' title='$typeName'>
                <div class='renglon3col'>
                    $donTypePhrase $determinant $typeName
                </div>
            </a>
        ");
    }

    $numregistros = $NFilas;
?>
</fieldset>
<?php print ("<p align='right'>Categorías: $numregistros</p>"); ?>

