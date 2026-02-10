<?php
// ================================================================== //
// PERSONAJES QUE POSEEN ESTE OBJETO

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
$cronicaNotInSQL = ($excludeChronicles !== '') ? " AND p.cronica NOT IN ($excludeChronicles) " : "";

$queryOwners = "
    SELECT
        p.id,
        p.nombre,
        p.alias,
        p.img,
        p.estado
    FROM bridge_characters_items b
    JOIN fact_characters p ON p.id = b.personaje_id
    WHERE b.objeto_id = ? $cronicaNotInSQL
    ORDER BY p.nombre
";

$stmtOwners = $link->prepare($queryOwners);
$stmtOwners->bind_param('i', $itemPageID);
$stmtOwners->execute();
$resultOwners = $stmtOwners->get_result();

if ($resultOwners->num_rows === 0) {

    //echo "<p style='text-align:center;'>Ning&uacute;n personaje posee actualmente este objeto.</p>";

} else {

    echo "<style>.contenidoAfiliacion{ display:flex; flex-wrap:wrap; gap:6px; padding:8px 0 12px 0; }</style>";
    echo "<div class='renglonDonData'>";
    echo "<b>En posesi&oacute;n de:</b>";
    echo "</div>";
    echo "<div class='grupoBioClan'><div class='contenidoAfiliacion'>";
    $totalOwners = 0;

    while ($rowOwner = $resultOwners->fetch_assoc()) {
        $pjId     = (int)$rowOwner['id'];
        $pjName   = htmlspecialchars($rowOwner['nombre']);
        $pjAlias  = htmlspecialchars($rowOwner['alias'] ?? '');
        $pjImg    = htmlspecialchars($rowOwner['img'] ?? '');
        $pjState  = htmlspecialchars($rowOwner['estado'] ?? '');

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
                if ($pjImg !== "") {
                    echo "<div class='dentroFotoBio'><img class='fotoBioList' src='{$pjImg}' alt='{$pjName}'></div>";
                } else {
                    echo "<div class='dentroFotoBio'><span>Sin imagen</span></div>";
                }
            echo "</div>";
        echo "</a>";

        $totalOwners++;
    }

    echo "</div></div>";
    echo "<p align='right'>Personajes: {$totalOwners}</p>";
}

$stmtOwners->close();
?>
