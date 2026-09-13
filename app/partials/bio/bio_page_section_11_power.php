<?php
require_once(__DIR__ . '/../../domains/characters/queries.php');

$iconos = [
    'dones' => 'img/ui/icons/icon_claws.webp',
    'disciplinas' => 'img/ui/icons/icon_fangs.webp',
    'rituales' => 'img/ui/icons/icon_book.webp',
];

$listaPoderes = hg_characters_fetch_powers($link, (int)$characterId);

function build_power_url(mysqli $link, string $linkBase, string $idPoder): string {
    $idPoder = (int)$idPoder;
    switch ($linkBase) {
        case 'muestradon':
            return pretty_url($link, 'fact_gifts', '/powers/gift', $idPoder);
        case 'tipodisc':
            return pretty_url($link, 'dim_discipline_types', '/powers/discipline/type', $idPoder);
        case 'seerite':
            return pretty_url($link, 'fact_rites', '/powers/rite', $idPoder);
        default:
            return "?p=$linkBase&b=$idPoder";
    }
}

if (count($listaPoderes) > 0) {
    echo "<div class='bioSheetPowers'>";
    echo "<fieldset class='bioSeccion'><legend>$titlePowers</legend>";

    foreach ($listaPoderes as $tipo => $poderes) {
        switch ($tipo) {
            case 'dones':
                $linkBase = 'muestradon';
                break;
            case 'disciplinas':
                $linkBase = 'tipodisc';
                break;
            case 'rituales':
                $linkBase = 'seerite';
                break;
            default:
                continue 2;
        }

        $icono = $iconos[$tipo] ?? 'img/ui/icons/default.webp';

        foreach ($poderes as $poderData) {
            $idPoder = (int)($poderData['id'] ?? 0);
            if ($idPoder <= 0) continue;
            $nombre = (string)($poderData['name'] ?? '???');
            $levelFinal = $poderData['level'] ?? null;
            $href = build_power_url($link, $linkBase, (string)$idPoder);

            $tipAttr = '';
            if ($tipo === 'dones') {
                $tipAttr = " class='hg-tooltip' data-tip='don' data-id='" . $idPoder . "'";
            } elseif ($tipo === 'rituales') {
                $tipAttr = " class='hg-tooltip' data-tip='rite' data-id='" . $idPoder . "'";
            }

            echo "<a href='" . h($href) . "' target='_blank'$tipAttr>
                    <div class='bioSheetPower'>
                        <img class='valign bio-inline-icon' src='" . h($icono) . "' title='" . h(ucfirst($tipo)) . "'>
                        " . h($nombre);

            if ($tipo === 'disciplinas' || $tipo === 'rituales') {
                if ($levelFinal !== null) {
                    $levelInt = (int)$levelFinal;
                    echo "<div class='bio-gem-wrap'>
                            <img src='img/ui/gems/attr/gem-attr-0{$levelInt}.webp' class='bio-gem' />
                          </div>";
                }
            } elseif ($levelFinal !== null) {
                echo "<div class='bio-inline-level'>" . (int)$levelFinal . "</div>";
            }

            echo "  </div>
                  </a>";
        }
    }

    echo "</fieldset>";
    echo "</div>";
}
?>
