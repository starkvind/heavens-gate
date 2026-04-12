<?php 
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
        'name' => 'Condiciones',
        'href' => '/rules/conditions',
        'desc' => 'Deformidades, heridas de guerra y trastornos mentales que afectan a los personajes.',
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

<link rel="stylesheet" href="/assets/css/hg-docs.css">

<h2>Reglamento</h2>
<fieldset class="grupoBioClan">
    <?php
        foreach ($rulesTypes as $rule) {
            $name = htmlspecialchars($rule['name']);
            $href = htmlspecialchars($rule['href']);
            $desc = htmlspecialchars((string)($rule['desc'] ?? $rule['description'] ?? ''));

            echo "
                <a href='$href' title='$name'>
                    <div class='renglon2col rules-card'>
                        <strong class='rules-card-title'>$name</strong>
                        <div class='rules-card-desc'>$desc</div>
                    </div>
                </a>
            ";
        }
    ?>
</fieldset>
