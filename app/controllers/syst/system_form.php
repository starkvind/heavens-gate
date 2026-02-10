<?php

// Aseguramos que el parámetro GET 'b' esté definido de manera segura
$systemIdWere = isset($_GET['b']) ? $_GET['b'] : '';

// Preparamos la consulta para evitar inyecciones SQL
$consulta = "SELECT * FROM dim_forms WHERE id = ? LIMIT 1";
$stmt = $link->prepare($consulta);
$stmt->bind_param('s', $systemIdWere);
$stmt->execute();
$result = $stmt->get_result();

// Comprueba si hay resultados
if ($result->num_rows > 0) {
    // Recupera los datos del sistema
    while ($ResultQuery = $result->fetch_assoc()) {
        $returnType = htmlspecialchars($ResultQuery["afiliacion"]); // Definimos la variable para volver
        $nameWereForm = htmlspecialchars($ResultQuery["forma"]);
        $nameWereBreed = htmlspecialchars($ResultQuery["raza"]);
        $infoDesc = ($ResultQuery["desc"]);
        $imageWereForm = htmlspecialchars($ResultQuery["imagen"]);
        $bonusSTR = htmlspecialchars($ResultQuery["bonfue"]);
        $bonusDEX = htmlspecialchars($ResultQuery["bondes"]);
        $bonusRES = htmlspecialchars($ResultQuery["bonres"]);
        $useMelee = (int)$ResultQuery["armas"];
        $useGuns = (int)$ResultQuery["armasfuego"];

        $canUseMelee = $useMelee === 1
            ? "Esta forma es capaz de utilizar armas cuerpo a cuerpo."
            : "Esta forma <b><u>no</u></b> puede utilizar armas cuerpo a cuerpo.";

        $canUseGuns = $useGuns === 1
            ? "Esta forma es capaz de utilizar armas de fuego."
            : "Esta forma <b><u>no</u></b> puede utilizar armas de fuego.";

        $pageSect = "Forma"; // PARA CAMBIAR EL TITULO A LA PAGINA
        $pageTitle2 = $nameWereForm;
        setMetaFromPage($nameWereForm . " | Formas | Heaven's Gate", meta_excerpt($infoDesc), $imageWereForm, 'article'); 

        include ("app/helpers/system_category_helper.php");
        include("app/partials/main_nav_bar.php"); // Barra Navegación

        if ($returnType === "Bastet") {
            $nameWereForm = "$nameWereForm ($nameWereBreed)";
        }

        echo "<h2>$nameWereForm</h2>";

        echo "<table class='notix'>";	
        echo "
            <tr>
            <td class='imgInfoPic'>
                <center>
                    <img class='infoPic' src='$imageWereForm' title='$nameWereForm' alt='$nameWereForm'/>
                </center>
            </td>
            <td class='imgInfoPic'>
                <center>
                    <table style='width: 75%;'>
                        <tr><td colspan='2' align='center'><b><u>Modificadores de atributos</u></b></td></tr>
                        <tr><td><b>Fuerza</b>:</td><td style='text-align:right;'>$bonusSTR</td></tr>
                        <tr><td><b>Destreza</b>:</td><td style='text-align:right;'>$bonusDEX</td></tr>
                        <tr><td><b>Resistencia</b>:</td><td style='text-align:right;'>$bonusRES</td></tr>
                        <tr><td colspan='2'>&nbsp;</td></tr>
                        <tr><td colspan='2'><i>$canUseMelee</i></td></tr>
                        <tr><td colspan='2'><i>$canUseGuns</i></td></tr>
                    </table>
                </center>
            </td>
            </tr>
        ";

        echo "
            <tr><td colspan='2' class='texti'>
                <b>Descripci&oacute;n</b>:<br/>
                $infoDesc
            </td></tr>
        ";

        echo "</table>"; 
    }
}

// Cierra el statement
//$stmt->close();

?>
