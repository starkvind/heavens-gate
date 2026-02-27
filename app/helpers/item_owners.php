<?php
// ================================================================== //
// PERSONAJES QUE POSEEN ESTE OBJETO
include_once(__DIR__ . '/character_avatar.php');

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
$cronicaNotInSQL = ($excludeChronicles !== '') ? " AND p.chronicle_id NOT IN ($excludeChronicles) " : "";

$queryOwners = "
    SELECT
        p.id,
        p.name,
        p.alias,
        p.image_url,
        p.gender,
        p.status
    FROM bridge_characters_items b
    JOIN fact_characters p ON p.id = b.character_id
    WHERE b.item_id = ? $cronicaNotInSQL
    ORDER BY p.name
";

$stmtOwners = $link->prepare($queryOwners);
$stmtOwners->bind_param('i', $itemPageID);
$stmtOwners->execute();
$resultOwners = $stmtOwners->get_result();

if ($resultOwners->num_rows === 0) {

    //echo "<p >Ning&uacute;n personaje posee actualmente este objeto.</p>";

} else {

    echo "<div class='renglonDonData'>";
    echo "<b>En posesi&oacute;n de:</b>";
    echo "</div>";
    echo "<div class='grupoBioClan'><div class='contenidoAfiliacion hg-affiliation-content'>";
    $totalOwners = 0;

    while ($rowOwner = $resultOwners->fetch_assoc()) {
        $pjId     = (int)$rowOwner['id'];
        $pjName   = htmlspecialchars($rowOwner['name']);
        $pjAlias  = htmlspecialchars($rowOwner['alias'] ?? '');
        $pjImg    = htmlspecialchars(hg_character_avatar_url($rowOwner['image_url'] ?? '', $rowOwner['gender'] ?? ''));
        $pjState  = htmlspecialchars($rowOwner['status'] ?? '');

        $pjLabel = $pjAlias !== '' ? $pjAlias : $pjName;
        $mapEstado = [
            "Aún por aparecer"     => "(&#64;)",
            "Paradero desconocido" => "(&#63;)",
            "Cadáver"              => "(&#8224;)"
        ];
        $simboloEstado = $mapEstado[$pjState] ?? "";

        $href = pretty_url($link, 'fact_characters', '/characters', (int)$pjId);
        echo "<a href='" . htmlspecialchars($href) . "' target='_blank' title='{$pjName}'>";
            echo "<div class='marcoFotoBio'>";
                echo "<div class='textoDentroFotoBio'>{$pjLabel} {$simboloEstado}</div>";
                echo "<div class='dentroFotoBio'><img class='fotoBioList' src='{$pjImg}' alt='{$pjName}'></div>";
            echo "</div>";
        echo "</a>";

        $totalOwners++;
    }

    echo "</div></div>";
    echo "<p align='right'>Personajes: {$totalOwners}</p>";
}

$stmtOwners->close();
?>
