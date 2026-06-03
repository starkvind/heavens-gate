<?php
//include("app/partials/main_nav_bar.php"); // Barra de Navegacion
setMetaFromPage("Poderes | Heaven's Gate", "Resumen y acceso a los poderes disponibles.", null, 'website');

$pageSect = "Poderes";

if (!function_exists('hg_pw_home_h')) {
    function hg_pw_home_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$powerTypes = [
    [
        'name' => 'Dones',
        'href' => '/powers/gifts',
        'description' => 'Poderes espirituales de los Garou.',
    ],
    [
        'name' => 'Rituales',
        'href' => '/powers/rites',
        'description' => 'Ritos y ceremonias con efectos místicos.',
    ],
    [
        'name' => 'Tótems',
        'href' => '/powers/totems',
        'description' => 'Espíritus guía y sus beneficios.',
    ],
    [
        'name' => 'Disciplinas',
        'href' => '/powers/disciplines',
        'description' => 'Poderes vampíricos alimentados por la sangre.',
    ],
];
?>

<h2>Poderes</h2>
<fieldset class="grupoBioClan">
    <?php foreach ($powerTypes as $power): ?>
        <?php
            $name = hg_pw_home_h($power['name'] ?? '');
            $href = hg_pw_home_h($power['href'] ?? '#');
            $desc = hg_pw_home_h($power['description'] ?? '');
        ?>
        <a href="<?= $href ?>" title="<?= $name ?>">
            <div class="renglon2col" style="height:52px;text-align:left;padding:1em;">
                <strong><?= $name ?></strong>
                <p><span style="font-size:12px; opacity:.8;"><?= $desc ?></span></p>
            </div>
        </a>
    <?php endforeach; ?>
</fieldset>
