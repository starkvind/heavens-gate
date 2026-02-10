<?php 
    //include("app/partials/main_nav_bar.php"); // Barra de Navegacion
    setMetaFromPage("Reglamento | Heaven's Gate", "Resumen y acceso al reglamento del juego.", null, 'website');

    $pageSect = "Reglamento";

$rulesTypes = [
    [
        'name' => 'Rasgos',
        'href' => '/rules/traits',
        'desc' => 'Rasgos numéricos, como Atributos y Habilidades.',
    ],
    [
        'name' => 'Méritos y Defectos',
        'href' => '/rules/merits-flaws',
        'desc' => 'Rasgos que definen ventajas y debilidades del personaje.',
    ],
    [
        'name' => 'Personalidades',
        'href' => '/rules/archetypes',
        'desc' => 'Arquetipos de personalidad que ayudan a interpretar el personaje.',
    ],
    [
        'name' => 'Maniobras de pelea',
        'href' => '/rules/maneuvers',
        'desc' => 'Técnicas de combate que van más allá de la pelea simple.',
    ],
];
?>

<h2>Reglamento</h2>
<fieldset class="grupoBioClan">
    <?php
        foreach ($rulesTypes as $rule) {
            $name = htmlspecialchars($rule['name']);
            $href = htmlspecialchars($rule['href']);
            $desc = htmlspecialchars($rule['desc']);

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
