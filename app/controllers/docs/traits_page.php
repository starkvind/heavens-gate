<?php
include_once(__DIR__ . '/../../helpers/character_avatar.php');

$traitPageID = isset($_GET['b']) ? (int)$_GET['b'] : 0;
$skillId = $traitPageID; // Compatibilidad con main_nav_bar.php

$queryTrait = "SELECT * FROM dim_traits WHERE id = ? LIMIT 1;";
$stmt = $link->prepare($queryTrait);
$stmt->bind_param('i', $traitPageID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $trait = $result->fetch_assoc();

    $traitName = htmlspecialchars((string)$trait['name']);
    $nameSkill = $traitName; // Compatibilidad con breadcrumbs legacy
    $traitKind = htmlspecialchars((string)$trait['kind']);
    $traitClassRaw = (string)$trait['classification'];
    $traitClass = (strlen($traitClassRaw) >= 5) ? substr($traitClassRaw, 4) : $traitClassRaw; // Igual que SUBSTRING(..., 5) en traits_table.php
    $traitClass = htmlspecialchars($traitClass);
    $traitDescription = (string)$trait['description'];
    $traitLevels = (string)$trait['levels'];
    $traitPosse = (string)$trait['posse'];
    $traitSpecial = (string)$trait['special'];
    $traitOriginId = (int)$trait['bibliography_id'];

    $traitOriginName = "-";
    if ($traitOriginId > 0) {
        $stmtOrigin = $link->prepare("SELECT name FROM dim_bibliographies WHERE id = ? LIMIT 1;");
        $stmtOrigin->bind_param('i', $traitOriginId);
        $stmtOrigin->execute();
        $resultOrigin = $stmtOrigin->get_result();
        if ($resultOrigin->num_rows > 0) {
            $rowOrigin = $resultOrigin->fetch_assoc();
            $traitOriginName = htmlspecialchars((string)$rowOrigin['name']);
        }
        $stmtOrigin->close();
    }

    $pageSect = "Rasgo";
    $pageTitle2 = $traitName;
    setMetaFromPage($traitName . " | Rasgos | Heaven's Gate", meta_excerpt($traitDescription), null, 'article');

    include("app/partials/main_nav_bar.php");

    ob_start();

    echo "<div class='power-card power-card--trait'>";
    echo "  <div class='power-card__banner'>";
    echo "    <span class='power-card__title'>{$traitName}</span>";
    echo "  </div>";

    echo "  <div class='power-card__body'>";
    echo "    <div class='power-card__media'>";
    echo "      <img class='power-card__img' style='border:1px solid #001a55; box-shadow: 0 0 0 2px #001a55, 0 0 14px rgba(0,0,0,0.5)' src='img/inv/no-photo.gif' alt='{$traitName}'/>";
    echo "    </div>";
    echo "    <div class='power-card__stats'>";

    if ($traitKind !== "") {
        echo "      <div class='power-stat'><div class='power-stat__label'>Tipo de rasgo</div><div class='power-stat__value'>{$traitKind}</div></div>";
    }

    if ($traitClass !== "") {
        echo "      <div class='power-stat'><div class='power-stat__label'>Clasificaci&oacute;n</div><div class='power-stat__value'>{$traitClass}</div></div>";
    }

    if (trim($traitPosse) !== "") {
        echo "      <div class='power-stat'><div class='power-stat__label'>Pose&iacute;do por</div><div class='power-stat__value'>{$traitPosse}</div></div>";
    }

    if (trim($traitSpecial) !== "") {
        echo "      <div class='power-stat'><div class='power-stat__label'>Maestrías</div><div class='power-stat__value'>{$traitSpecial}</div></div>";
    }

    echo "      <div class='power-stat'><div class='power-stat__label'>Origen</div><div class='power-stat__value'>{$traitOriginName}</div></div>";
    echo "    </div>";
    echo "  </div>";

    if (trim($traitDescription) !== "") {
        echo "  <div class='power-card__desc'>";
        echo "    <div class='power-card__desc-title'>Descripci&oacute;n</div>";
        echo "    <div class='power-card__desc-body'>{$traitDescription}</div>";
        echo "  </div>";
    }

    if (trim($traitLevels) !== "") {
        echo "  <div class='power-card__desc'>";
        echo "    <div class='power-card__desc-title'>Niveles</div>";
        echo "    <div class='power-card__desc-body'>{$traitLevels}</div>";
        echo "  </div>";
    }

    echo "</div>";

    $infoHtml = ob_get_clean();

    if (!function_exists('sanitize_int_csv')) {
        function sanitize_int_csv($csv){
            $csv = (string)$csv;
            if (trim($csv) === '') return '';
            $parts = preg_split('/\s*,\s*/', trim($csv));
            $ints = [];
            foreach ($parts as $p) {
                if ($p === '') continue;
                if (preg_match('/^\d+$/', $p)) $ints[] = (string)(int)$p;
            }
            $ints = array_values(array_unique($ints));
            return implode(',', $ints);
        }
    }

    $excludeChronicles = isset($excludeChronicles) ? sanitize_int_csv($excludeChronicles) : '';
    $cronicaNotInSQL = ($excludeChronicles !== '') ? " AND c.chronicle_id NOT IN ($excludeChronicles) " : "";

    $traitOwners = [];
    $queryOwners = "
        SELECT
            c.id,
            c.name,
            c.alias,
            c.image_url,
            c.gender,
            c.status,
            b.value
        FROM bridge_characters_traits b
        JOIN fact_characters c ON c.id = b.character_id
        WHERE b.trait_id = ? AND b.value >= 1 $cronicaNotInSQL
        ORDER BY b.value ASC, c.name ASC
    ";
    if ($stOwners = $link->prepare($queryOwners)) {
        $stOwners->bind_param('i', $traitPageID);
        $stOwners->execute();
        $rsOwners = $stOwners->get_result();
        while ($r = $rsOwners->fetch_assoc()) {
            $value = (int)($r['value'] ?? 0);
            if ($value < 1) {
                continue;
            }
            if (!isset($traitOwners[$value])) {
                $traitOwners[$value] = [];
            }
            $traitOwners[$value][] = $r;
        }
        $stOwners->close();
    }
    ksort($traitOwners);
    $hasOwners = count($traitOwners) > 0;

    if ($hasOwners) {
        include_once(__DIR__ . '/../../partials/owners_tabs_styles.php');
        hg_render_owner_tabs_styles(true, 28);

        echo "<div class='hg-tabs'>";
        echo "<button class='boton2 hgTabBtn' data-tab='info'>Informaci&oacute;n</button>";
        echo "<button class='boton2 hgTabBtn' data-tab='owners'>Portadores</button>";
        echo "</div>";

        echo "<section class='hg-tab-panel' data-tab='info'>{$infoHtml}</section>";

        echo "<section class='hg-tab-panel' data-tab='owners'>";
        $mapEstado = [
            "Aún por aparecer"     => "(&#64;)",
            "Paradero desconocido" => "(&#63;)",
            "Cadáver"              => "(&#8224;)",
            "A?n por aparecer"     => "(&#64;)",
            "Cad?ver"              => "(&#8224;)"
        ];

        $totalOwners = 0;
        foreach ($traitOwners as $value => $owners) {
            $totalOwners += count($owners);
            $gemSrc = "img/ui/gems/attr/gem-attr-0{$value}.png";
            $puntos = ($value == 1) ? "1 punto" : "{$value} puntos";

            echo "<div class='power-card__desc'>";
            echo "  <div class='power-card__desc-title'><img class='bioAttCircle' src='{$gemSrc}' alt='{$puntos}' style='margin-right:6px;vertical-align:middle;'/>&nbsp;</div>"; /* Personajes con {$puntos} */
            echo "  <div class='power-card__desc-body'>";
            echo "    <div class='grupoBioClan'><div class='contenidoAfiliacion'>";

            foreach ($owners as $o) {
                $oid = (int)($o['id'] ?? 0);
                $name = htmlspecialchars((string)($o['name'] ?? ''));
                $alias = htmlspecialchars((string)($o['alias'] ?? ''));
                $img = htmlspecialchars(hg_character_avatar_url((string)($o['image_url'] ?? ''), (string)($o['gender'] ?? '')));
                $estado = (string)($o['status'] ?? '');
                $label = $alias !== '' ? $alias : $name;
                $simboloEstado = $mapEstado[$estado] ?? "";
                $href = pretty_url($link, 'fact_characters', '/characters', $oid);

                echo "<a href='" . htmlspecialchars($href) . "' target='_blank' title='{$name}'>";
                echo "  <div class='marcoFotoBio'>";
                echo "    <div class='textoDentroFotoBio'>{$label} {$simboloEstado}</div>";
                echo "    <div class='dentroFotoBio'><img class='fotoBioList' src='{$img}' alt='{$name}'></div>";
                echo "  </div>";
                echo "</a>";
            }

            echo "    </div></div>";
            echo "  </div>";
            echo "</div>";
        }

        echo "<p align='right'>Personajes: {$totalOwners}</p>";
        echo "</section>";

        echo "<script>
            document.addEventListener('DOMContentLoaded', () => {
                const tabs = Array.from(document.querySelectorAll('.hgTabBtn'));
                const panels = Array.from(document.querySelectorAll('.hg-tab-panel'));
                function activate(key){
                    panels.forEach(p => p.classList.toggle('active', p.dataset.tab === key));
                    tabs.forEach(b => b.classList.toggle('active', b.dataset.tab === key));
                }
                if (tabs.length) activate(tabs[0].dataset.tab);
                tabs.forEach(b => b.addEventListener('click', () => activate(b.dataset.tab)));
            });
        </script>";
    } else {
        echo $infoHtml;
    }
}

$stmt->close();

?>
