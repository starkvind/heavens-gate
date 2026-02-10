<?php
/**
 * inspect_db.php
 * Explorador de esquema para saneado de BDD
 */

include("../helpers/heroes.php");

/* ---------- CONFIGURACIÓN ---------- */

// Tablas a excluir siempre
$EXCLUDE_TABLES = [
    'dim_web_configuration',
];

// Si NO está vacío → solo se mostrarán estas tablas
// Ejemplo: ['fact_characters', 'fact_gifts']
$ONLY_TABLES = [
    //'black_horse_for_you'
    'fact_combat_maneuvers'
    //'dim_systems',
    //'dim_forms'
    // 'dim_seasons',
    // 'dim_chapters',
    // 'bridge_chapters_characters'
    //'documentacion',
    //'docz'
    //'fact_characters',
    //'fact_items',
    //'bridge_characters_powers',
    // 'fact_discipline_powers',
    // 'dim_discipline_types',
    // 'fact_rites',
    // 'dim_merits_flaws',
    // 'dim_totems',
    // // 'fact_characters',
    // 'fact_gifts',
    // 'dim_parties',
    // 'fact_party_members_changes',
    // 'fact_party_members'
    // 'dim_groups',
    // 'dim_organizations',
    // 'bridge_organizations_groups',
    // 'fact_characters',
    // 'bridge_characters_organizations',
    // 'bridge_characters_groups',
    // 'bridge_organizations_groups'
    // 'dim_systems',
    // 'dim_forms',
    // 'dim_breeds',
    // 'dim_auspices',
    // 'dim_tribes',
    // 'fact_misc_systems'
];

if (!isset($link) || !$link) {
    die("❌ Error: conexión a BDD no disponible.<br />");
}

mysqli_set_charset($link, 'utf8mb4');

$MAX_ROWS = 5;

/* ---------- helpers PHP 7 ---------- */

function should_process_table(string $table, array $only, array $exclude): bool
{
    // Whitelist activa → solo estas
    if (!empty($only)) {
        return in_array($table, $only, true);
    }

    // Blacklist normal
    return !in_array($table, $exclude, true);
}

function ends_with($haystack, $needle) {
    $length = strlen($needle);
    if ($length === 0) return true;
    return substr($haystack, -$length) === $needle;
}

function guess_table_type(array $columns) {
    $fkCount = 0;
    $hasMeasures = false;

    foreach ($columns as $col) {
        $name = strtolower($col['Field']);

        if ($name === 'id') {
            continue;
        }

        if (ends_with($name, '_id')) {
            $fkCount++;
        } else {
            if (preg_match('/(count|total|nivel|rango|exitos|valor|amount)/', $name)) {
                $hasMeasures = true;
            }
        }
    }

    if ($fkCount >= 2 && !$hasMeasures) {
        return 'bridge';
    }

    if ($hasMeasures) {
        return 'fact';
    }

    return 'dim';
}

/* ---------- ejecución ---------- */

echo "========================================<br />";
echo " INSPECCIÓN DE BASE DE DATOS<br />";
echo "========================================<br /><br />";

$resTables = mysqli_query($link, "SHOW TABLES");

if (!$resTables) {
    die("❌ Error al listar tablas.<br />");
}

echo "Modo de inspección: ";
if (!empty($ONLY_TABLES)) {
    echo "WHITELIST → " . implode(', ', $ONLY_TABLES);
} else {
    echo "BLACKLIST → excluyendo: " . implode(', ', $EXCLUDE_TABLES);
}
echo "<br /><br />";

while ($row = mysqli_fetch_row($resTables)) {
    $table = $row[0];

    // Safeguard: saltar configuración sensible
    if (!should_process_table($table, $ONLY_TABLES, $EXCLUDE_TABLES)) {
        continue;
    }

    echo "----------------------------------------<br />";
    echo "TABLA: {$table}<br />";
    echo "----------------------------------------<br />";

    $resCols = mysqli_query($link, "DESCRIBE `$table`");

    if (!$resCols) {
        echo "⚠ No se pudo describir la tabla<br /><br />";
        continue;
    }

    $columns = [];
    while ($col = mysqli_fetch_assoc($resCols)) {
        $columns[] = $col;
    }

    $typeGuess = guess_table_type($columns);
    echo "Tipo sugerido: {$typeGuess}<br /><br />";

    echo "Columnas:<br />";
    foreach ($columns as $col) {
        echo "  - {$col['Field']} ({$col['Type']})";
        if ($col['Key'] === 'PRI') echo " [PK]";
        if ($col['Key'] === 'MUL') echo " [IDX]";
        if ($col['Null'] === 'NO') echo " [NOT NULL]";
        echo "<br />";
    }

    echo "<br />Muestra de datos (máx {$MAX_ROWS} filas):<br />";

    $resData = mysqli_query($link, "SELECT * FROM `$table` LIMIT {$MAX_ROWS}");

    if (!$resData) {
        echo "  ⚠ Error en SELECT<br /><br />";
        continue;
    }

    if (mysqli_num_rows($resData) === 0) {
        echo "  (tabla vacía)<br /><br />";
        continue;
    }

    $i = 1;
    while ($data = mysqli_fetch_assoc($resData)) {
        echo "  Fila {$i}:<br />";
        foreach ($data as $k => $v) {
            if ($v === null) {
                $v = 'NULL';
            } else {
                $v = mb_strimwidth((string)$v, 0, 80, '…');
            }
            echo "    {$k}: {$v}<br />";
        }
        $i++;
    }

    echo "<br />Observaciones:<br />";

    foreach ($columns as $c) {
        if ($c['Field'] !== 'id' && ends_with(strtolower($c['Field']), '_id')) {
            echo "  • Posible FK: {$c['Field']}<br />";
        }
    }

    echo "<br /><br />";
}

echo "========================================<br />";
echo " FIN DE INSPECCIÓN<br />";
echo "========================================<br />";
