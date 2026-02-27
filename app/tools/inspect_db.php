<?php
/**
 * inspect_db.php
 * Explorador de esquema para saneado de BDD
 */

include_once(__DIR__ . "/../helpers/db_connection.php");

/* ---------- CONFIGURACIÓN ---------- */

// Tablas a excluir siempre
$EXCLUDE_TABLES = [
    'dim_web_configuration',
];

// Si NO está vacío → solo se mostrarán estas tablas
// Ejemplo: ['fact_characters', 'fact_gifts']
$ONLY_TABLES = [
    //'black_horse_for_you'
    // 'fact_combat_maneuvers',
    // 'fact_gifts'
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

/* ---------- ejecución ---------- */

// Modo: full (tablas + columnas + muestra) / schema (solo tablas + columnas)
$mode = isset($_GET['mode']) ? strtolower(trim((string)$_GET['mode'])) : 'full';
if (!in_array($mode, ['full','schema'], true)) $mode = 'full';

// Codificación (conexión + BD)
$encConn = '';
$encDb = '';
if ($rsEnc = $link->query("SELECT @@character_set_connection AS cs_conn, @@collation_connection AS col_conn, @@character_set_database AS cs_db, @@collation_database AS col_db")) {
    if ($row = $rsEnc->fetch_assoc()) {
        $encConn = ($row['cs_conn'] ?? '').' / '.($row['col_conn'] ?? '');
        $encDb   = ($row['cs_db'] ?? '').' / '.($row['col_db'] ?? '');
    }
    $rsEnc->close();
}

echo "<div class='inspect-db-wrap'>";
echo "<div class='inspect-db-actions'>
  <a class='inspect-db-btn ".($mode==='full'?'active':'')."' href='?p=talim&s=admin_inspect_db&mode=full'>Modo completo</a>
  <a class='inspect-db-btn ".($mode==='schema'?'active':'')."' href='?p=talim&s=admin_inspect_db&mode=schema'>Solo tablas/columnas</a>
  <button type='button' class='inspect-db-btn' id='inspectDbCopy'>Copiar</button>
  <span class='inspect-db-hint' id='inspectDbCopyHint'></span>
</div>";
echo "<pre class='inspect-db-output' id='inspectDbOutput'>";
echo "========================================\n";
echo " INSPECCIÓN DE BASE DE DATOS\n";
echo "========================================\n\n";
if ($encConn !== '' || $encDb !== '') {
    echo "Codificación conexión: " . $encConn . "\n";
    echo "Codificación BDD: " . $encDb . "\n\n";
}

$resTables = mysqli_query($link, "SHOW TABLES");

if (!$resTables) {
    die("❌ Error al listar tablas.\n</pre>");
}

echo "Modo de inspección: ";
if (!empty($ONLY_TABLES)) {
    echo "WHITELIST → " . implode(', ', $ONLY_TABLES);
} else {
    echo "BLACKLIST → excluyendo: " . implode(', ', $EXCLUDE_TABLES);
}
echo "\n\n";

while ($row = mysqli_fetch_row($resTables)) {
    $table = $row[0];

    // Safeguard: saltar configuración sensible
    if (!should_process_table($table, $ONLY_TABLES, $EXCLUDE_TABLES)) {
        continue;
    }

    echo "----------------------------------------\n";
    echo "TABLA: {$table}\n";
    echo "----------------------------------------\n";

    $resCols = mysqli_query($link, "DESCRIBE `$table`");

    if (!$resCols) {
        echo "⚠ No se pudo describir la tabla\n\n";
        continue;
    }

    $columns = [];
    while ($col = mysqli_fetch_assoc($resCols)) {
        $columns[] = $col;
    }

    echo "Columnas:\n";
    foreach ($columns as $col) {
        echo "  - {$col['Field']} ({$col['Type']})";
        if ($col['Key'] === 'PRI') echo " [PK]";
        if ($col['Key'] === 'MUL') echo " [IDX]";
        if ($col['Null'] === 'NO') echo " [NOT NULL]";
        echo "\n";
    }

    if ($mode !== 'schema') {
        echo "\nMuestra de datos (máx {$MAX_ROWS} filas):\n";

        $resData = mysqli_query($link, "SELECT * FROM `$table` LIMIT {$MAX_ROWS}");

        if (!$resData) {
            echo "  ⚠ Error en SELECT\n\n";
            continue;
        }

        if (mysqli_num_rows($resData) === 0) {
            echo "  (tabla vacía)\n\n";
            continue;
        }

        $i = 1;
        while ($data = mysqli_fetch_assoc($resData)) {
            echo "  Fila {$i}:\n";
            foreach ($data as $k => $v) {
                if ($v === null) {
                    $v = 'NULL';
                } else {
                    $v = mb_strimwidth((string)$v, 0, 80, '…');
                }
                echo "    {$k}: {$v}\n";
            }
            $i++;
        }
    }

    echo "\n\n";
}

echo "========================================\n";
echo " FIN DE INSPECCIÓN\n";
echo "========================================\n";
echo "</pre>";
echo "</div>";
echo "<script>
  (function(){
    var btn = document.getElementById('inspectDbCopy');
    var out = document.getElementById('inspectDbOutput');
    var hint = document.getElementById('inspectDbCopyHint');
    if (!btn || !out) return;
    btn.addEventListener('click', async function(){
      try {
        var text = out.innerText || out.textContent || '';
        if (navigator.clipboard && navigator.clipboard.writeText) {
          await navigator.clipboard.writeText(text);
        } else {
          var ta = document.createElement('textarea');
          ta.value = text;
          ta.style.position = 'fixed';
          ta.style.left = '-9999px';
          document.body.appendChild(ta);
          ta.select();
          document.execCommand('copy');
          document.body.removeChild(ta);
        }
        if (hint) { hint.textContent = 'Copiado.'; setTimeout(function(){ hint.textContent=''; }, 1200); }
      } catch(e){
        if (hint) { hint.textContent = 'No se pudo copiar.'; setTimeout(function(){ hint.textContent=''; }, 1600); }
      }
    });
  })();
</script>";

