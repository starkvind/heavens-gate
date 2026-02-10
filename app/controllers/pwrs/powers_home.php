<?php 
    //include("app/partials/main_nav_bar.php"); // Barra de Navegacion
    setMetaFromPage("Poderes | Heaven's Gate", "Resumen y acceso a los poderes disponibles.", null, 'website');

    $pageSect = "Poderes";

$powerTypes = [
    [
        'name' => 'Dones',
        'href' => '/powers/gifts',
        'desc' => 'Poderes espirituales de los Garou.',
    ],
    [
        'name' => 'Rituales',
        'href' => '/powers/rites',
        'desc' => 'Ritos y ceremonias con efectos místicos.',
    ],
    [
        'name' => 'Tótems',
        'href' => '/powers/totems',
        'desc' => 'Espiritus guía y sus beneficios.',
    ],
    [
        'name' => 'Disciplinas',
        'href' => '/powers/disciplines',
        'desc' => 'Poderes vampíricos alimentados por la sangre.',
    ],
];
?>

<h2>Poderes</h2>
<fieldset class="grupoBioClan">
    <?php
        foreach ($powerTypes as $power) {
            $name = htmlspecialchars($power['name']);
            $href = htmlspecialchars($power['href']);
            $desc = htmlspecialchars($power['desc']);

            echo "
                <a href='$href' title='$name'>
                    <div class='renglon2col' style='height:52px;text-align:left;padding:1em;'>
                        <strong>$name</strong>
                        <p><span style='font-size:12px; opacity:.8;'>$desc</span></p>
                    </div>
                </a>
            ";
        }
    ?>
</fieldset>
