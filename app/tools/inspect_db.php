<?php
/**
 * inspect_db.php
 * Explorador de esquema para saneado de BDD
 */

require_once(__DIR__ . "/../helpers/runtime_response.php");
require_once(__DIR__ . "/../helpers/db_connection.php");
require_once(__DIR__ . "/../helpers/pretty.php");

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

if (!hg_runtime_require_db($link, 'inspect_db', 'bootstrap', [
    'message' => 'No se pudo conectar a la base de datos.',
])) {
    return;
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

function table_exists(mysqli $link, string $table): bool
{
    if (function_exists('hg_table_exists')) {
        return hg_table_exists($link, $table);
    }

    $stmt = $link->prepare("
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
    ");
    if (!$stmt) return false;

    $count = 0;
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    return ((int)$count > 0);
}

function inspect_scalar(mysqli $link, string $sql): int
{
    $rs = $link->query($sql);
    if (!$rs) return 0;

    $row = $rs->fetch_row();
    $rs->free();
    return (int)($row[0] ?? 0);
}

function table_has_column(mysqli $link, string $table, string $column): bool
{
    if (function_exists('hg_table_has_column')) {
        return hg_table_has_column($link, $table, $column);
    }

    $stmt = $link->prepare("
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");
    if (!$stmt) return false;

    $count = 0;
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    return ((int)$count > 0);
}

function inspect_fk_count(mysqli $link, string $table): int
{
    $stmt = $link->prepare("
        SELECT COUNT(DISTINCT CONSTRAINT_NAME)
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    if (!$stmt) return 0;

    $count = 0;
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    return (int)$count;
}

function inspect_unique_count(mysqli $link): int
{
    return inspect_scalar($link, "
        SELECT COUNT(*)
        FROM (
            SELECT DISTINCT TABLE_NAME, INDEX_NAME
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND NON_UNIQUE = 0
              AND INDEX_NAME <> 'PRIMARY'
        ) t
    ");
}

function inspect_health_summary(mysqli $link): array
{
    $summary = [
        'tables' => inspect_scalar($link, "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'"),
        'fks' => inspect_scalar($link, "
            SELECT COUNT(*)
            FROM (
                SELECT DISTINCT TABLE_NAME, CONSTRAINT_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                  AND REFERENCED_TABLE_NAME IS NOT NULL
            ) t
        "),
        'unique_indexes' => inspect_unique_count($link),
        'chapters_zero_dates' => table_exists($link, 'dim_chapters')
            ? inspect_scalar($link, "SELECT COUNT(*) FROM dim_chapters WHERE played_date = '0000-00-00'")
            : 0,
        'comments_zero_dates' => table_exists($link, 'fact_characters_comments')
            ? inspect_scalar($link, "SELECT COUNT(*) FROM fact_characters_comments WHERE commented_at = '0000-00-00'")
            : 0,
        'nature_zero' => table_exists($link, 'fact_characters')
            ? inspect_scalar($link, "SELECT COUNT(*) FROM fact_characters WHERE nature_id = 0")
            : 0,
        'demeanor_zero' => table_exists($link, 'fact_characters')
            ? inspect_scalar($link, "SELECT COUNT(*) FROM fact_characters WHERE demeanor_id = 0")
            : 0,
        'nature_null' => table_exists($link, 'fact_characters')
            ? inspect_scalar($link, "SELECT COUNT(*) FROM fact_characters WHERE nature_id IS NULL")
            : 0,
        'demeanor_null' => table_exists($link, 'fact_characters')
            ? inspect_scalar($link, "SELECT COUNT(*) FROM fact_characters WHERE demeanor_id IS NULL")
            : 0,
        'groups_zero_totem' => table_exists($link, 'dim_groups')
            ? inspect_scalar($link, "SELECT COUNT(*) FROM dim_groups WHERE totem_id = 0")
            : 0,
        'organizations_zero_totem' => table_exists($link, 'dim_organizations')
            ? inspect_scalar($link, "SELECT COUNT(*) FROM dim_organizations WHERE totem_id = 0")
            : 0,
        'bcc_orphans' => table_exists($link, 'bridge_chapters_characters')
            ? inspect_scalar($link, "
                SELECT COUNT(*)
                FROM bridge_chapters_characters b
                LEFT JOIN dim_chapters c ON c.id = b.chapter_id
                LEFT JOIN fact_characters f ON f.id = b.character_id
                WHERE c.id IS NULL OR f.id IS NULL
            ")
            : 0,
        'bog_orphans' => table_exists($link, 'bridge_organizations_groups')
            ? inspect_scalar($link, "
                SELECT COUNT(*)
                FROM bridge_organizations_groups b
                LEFT JOIN dim_organizations o ON o.id = b.organization_id
                LEFT JOIN dim_groups g ON g.id = b.group_id
                WHERE o.id IS NULL OR g.id IS NULL
            ")
            : 0,
        'relations_orphans' => table_exists($link, 'bridge_characters_relations')
            ? inspect_scalar($link, "
                SELECT COUNT(*)
                FROM bridge_characters_relations r
                LEFT JOIN fact_characters s ON s.id = r.source_id
                LEFT JOIN fact_characters t ON t.id = r.target_id
                WHERE s.id IS NULL OR t.id IS NULL
            ")
            : 0,
        'pretty_aliases' => table_exists($link, 'fact_pretty_id_aliases')
            ? inspect_scalar($link, "SELECT COUNT(*) FROM fact_pretty_id_aliases")
            : 0,
        'csp_normalized' => (
            table_exists($link, 'fact_csp_posts')
            && table_has_column($link, 'fact_csp_posts', 'posted_date')
            && table_has_column($link, 'fact_csp_posts', 'posted_time')
        )
            ? inspect_scalar($link, "SELECT COUNT(*) FROM fact_csp_posts WHERE posted_date IS NOT NULL OR posted_time IS NOT NULL")
            : 0,
        'tracked' => [],
    ];

    foreach ([
        'fact_characters_comments',
        'dim_groups',
        'dim_organizations',
        'bridge_chapters_characters',
        'bridge_organizations_groups',
        'bridge_characters_relations',
    ] as $table) {
        if (!table_exists($link, $table)) continue;
        $summary['tracked'][$table] = inspect_fk_count($link, $table);
    }

    return $summary;
}

function inspect_one_assoc(mysqli $link, string $sql): ?array
{
    $rs = $link->query($sql);
    if (!$rs) return null;

    $row = $rs->fetch_assoc();
    $rs->free();

    return $row ?: null;
}

function inspect_fake_empties_summary(mysqli $link): array
{
    $checks = [
        [
            'label' => 'bridge_characters_groups.position',
            'table' => 'bridge_characters_groups',
            'column' => 'position',
            'note' => 'Cargo o posicion editorial en la relacion personaje/grupo.',
        ],
        [
            'label' => 'bridge_characters_organizations.role',
            'table' => 'bridge_characters_organizations',
            'column' => 'role',
            'note' => 'Rol editorial en la relacion personaje/organizacion.',
        ],
        [
            'label' => 'fact_characters.image_url',
            'table' => 'fact_characters',
            'column' => 'image_url',
            'note' => 'Avatar o imagen publica del personaje.',
        ],
        [
            'label' => 'fact_characters.text_color',
            'table' => 'fact_characters',
            'column' => 'text_color',
            'note' => 'Color editorial asociado al personaje.',
        ],
        [
            'label' => 'bridge_systems_detail_labels.label_misc',
            'table' => 'bridge_systems_detail_labels',
            'column' => 'label_misc',
            'note' => 'Etiqueta editorial libre por sistema.',
        ],
        [
            'label' => 'bridge_characters_system_resources_log.source',
            'table' => 'bridge_characters_system_resources_log',
            'column' => 'source',
            'note' => 'Origen del apunte de recursos del sistema.',
        ],
    ];

    $summary = [
        'total_empty' => 0,
        'checked_fields' => 0,
        'rows' => [],
    ];

    foreach ($checks as $check) {
        if (!table_exists($link, $check['table']) || !table_has_column($link, $check['table'], $check['column'])) {
            continue;
        }

        $table = $check['table'];
        $column = $check['column'];
        $count = inspect_scalar(
            $link,
            "SELECT COUNT(*) FROM `{$table}` WHERE TRIM(COALESCE(`{$column}`, '')) = ''"
        );

        $summary['checked_fields']++;
        $summary['total_empty'] += $count;
        $summary['rows'][] = [
            'label' => $check['label'],
            'count' => $count,
            'note' => $check['note'],
        ];
    }

    return $summary;
}

function inspect_birthdate_text_summary(mysqli $link): array
{
    $summary = [
        'total' => 0,
        'empty' => 0,
        'unknown' => 0,
        'iso' => 0,
        'spanish' => 0,
        'narrative' => 0,
        'other' => 0,
    ];

    if (!table_exists($link, 'fact_characters') || !table_has_column($link, 'fact_characters', 'birthdate_text')) {
        return $summary;
    }

    $row = inspect_one_assoc($link, "
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN TRIM(COALESCE(birthdate_text, '')) = '' THEN 1 ELSE 0 END) AS empty_count,
            SUM(CASE WHEN LOWER(TRIM(COALESCE(birthdate_text, ''))) IN ('desconocido', 'unknown', 'n/a', 'no consta') THEN 1 ELSE 0 END) AS unknown_count,
            SUM(CASE WHEN TRIM(COALESCE(birthdate_text, '')) REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$' THEN 1 ELSE 0 END) AS iso_count,
            SUM(CASE WHEN TRIM(COALESCE(birthdate_text, '')) REGEXP '^[0-9]{2}/[0-9]{2}/[0-9]{4}$' THEN 1 ELSE 0 END) AS spanish_count,
            SUM(CASE
                WHEN TRIM(COALESCE(birthdate_text, '')) <> ''
                 AND LOWER(TRIM(COALESCE(birthdate_text, ''))) NOT IN ('desconocido', 'unknown', 'n/a', 'no consta')
                 AND TRIM(COALESCE(birthdate_text, '')) NOT REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'
                 AND TRIM(COALESCE(birthdate_text, '')) NOT REGEXP '^[0-9]{2}/[0-9]{2}/[0-9]{4}$'
                 AND TRIM(COALESCE(birthdate_text, '')) REGEXP '[[:alpha:]]'
                THEN 1 ELSE 0 END
            ) AS narrative_count
        FROM fact_characters
    ");

    if (!$row) {
        return $summary;
    }

    $summary['total'] = (int)($row['total'] ?? 0);
    $summary['empty'] = (int)($row['empty_count'] ?? 0);
    $summary['unknown'] = (int)($row['unknown_count'] ?? 0);
    $summary['iso'] = (int)($row['iso_count'] ?? 0);
    $summary['spanish'] = (int)($row['spanish_count'] ?? 0);
    $summary['narrative'] = (int)($row['narrative_count'] ?? 0);
    $summary['other'] = max(
        0,
        $summary['total']
        - $summary['empty']
        - $summary['unknown']
        - $summary['iso']
        - $summary['spanish']
        - $summary['narrative']
    );

    return $summary;
}

function inspect_editorial_content_summary(mysqli $link): array
{
    $summary = [
        'gift_empty_description' => 0,
        'gift_empty_mechanics' => 0,
        'gift_possible_mojibake' => 0,
        'lanza_de_hielo' => null,
    ];

    if (!table_exists($link, 'fact_gifts')) {
        return $summary;
    }

    $summary['gift_empty_description'] = inspect_scalar(
        $link,
        "SELECT COUNT(*) FROM fact_gifts WHERE TRIM(COALESCE(description, '')) = ''"
    );
    $summary['gift_empty_mechanics'] = inspect_scalar(
        $link,
        "SELECT COUNT(*) FROM fact_gifts WHERE TRIM(COALESCE(mechanics_text, '')) = ''"
    );
    $summary['gift_possible_mojibake'] = inspect_scalar(
        $link,
        "SELECT COUNT(*) FROM fact_gifts WHERE name LIKE '%Ã%' OR description LIKE '%Ã%' OR mechanics_text LIKE '%Ã%'"
    );

    $summary['lanza_de_hielo'] = inspect_one_assoc($link, "
        SELECT
            id,
            name,
            pretty_id,
            CHAR_LENGTH(TRIM(COALESCE(description, ''))) AS description_len,
            CHAR_LENGTH(TRIM(COALESCE(mechanics_text, ''))) AS mechanics_len,
            CASE WHEN TRIM(COALESCE(description, '')) = '' THEN 1 ELSE 0 END AS description_empty,
            CASE WHEN TRIM(COALESCE(mechanics_text, '')) = '' THEN 1 ELSE 0 END AS mechanics_empty,
            CASE WHEN description = mechanics_text AND TRIM(COALESCE(description, '')) <> '' THEN 1 ELSE 0 END AS duplicated_blocks,
            CASE WHEN name LIKE '%Ã%' OR description LIKE '%Ã%' OR mechanics_text LIKE '%Ã%' THEN 1 ELSE 0 END AS possible_mojibake,
            updated_at
        FROM fact_gifts
        WHERE name = 'Lanza de Hielo' OR pretty_id = 'lanza-de-hielo'
        ORDER BY id ASC
        LIMIT 1
    ");

    return $summary;
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

$health = inspect_health_summary($link);
$fakeEmpties = inspect_fake_empties_summary($link);
$birthdateSummary = inspect_birthdate_text_summary($link);
$editorialSummary = inspect_editorial_content_summary($link);
echo "========================================\n";
echo " ESTADO DE SALUD DE LA BDD\n";
echo "========================================\n";
echo "- Tablas base: " . $health['tables'] . "\n";
echo "- Foreign keys reales: " . $health['fks'] . "\n";
echo "- UNIQUEs no primarias: " . $health['unique_indexes'] . "\n";
echo "- pretty_id aliases preservados: " . $health['pretty_aliases'] . "\n";
echo "- fact_csp_posts normalizados en posted_date/posted_time: " . $health['csp_normalized'] . "\n\n";
echo "[Sentinelas saneados]\n";
echo "- dim_chapters.played_date = 0000-00-00: " . $health['chapters_zero_dates'] . "\n";
echo "- fact_characters_comments.commented_at = 0000-00-00: " . $health['comments_zero_dates'] . "\n";
echo "- fact_characters.nature_id = 0: " . $health['nature_zero'] . "\n";
echo "- fact_characters.demeanor_id = 0: " . $health['demeanor_zero'] . "\n";
echo "- dim_groups.totem_id = 0: " . $health['groups_zero_totem'] . "\n";
echo "- dim_organizations.totem_id = 0: " . $health['organizations_zero_totem'] . "\n\n";
echo "[Campos opcionales reales]\n";
echo "- fact_characters con nature_id NULL: " . $health['nature_null'] . "\n";
echo "- fact_characters con demeanor_id NULL: " . $health['demeanor_null'] . "\n\n";
echo "[Puentes e integridad]\n";
echo "- bridge_chapters_characters huerfanos: " . $health['bcc_orphans'] . "\n";
echo "- bridge_organizations_groups huerfanos: " . $health['bog_orphans'] . "\n";
echo "- bridge_characters_relations huerfanos: " . $health['relations_orphans'] . "\n";
foreach ($health['tracked'] as $table => $fkCount) {
    echo "- {$table} foreign keys activas: {$fkCount}\n";
}
echo "\n";
echo "[Vacios fingidos auditados]\n";
echo "- Campos vigilados: " . $fakeEmpties['checked_fields'] . "\n";
echo "- Filas con '' en campos NOT NULL seleccionados: " . $fakeEmpties['total_empty'] . "\n";
foreach ($fakeEmpties['rows'] as $row) {
    echo "- {$row['label']}: {$row['count']} vacios | {$row['note']}\n";
}
echo "\n";
echo "[birthdate_text en fact_characters]\n";
echo "- Total de personajes auditados: " . $birthdateSummary['total'] . "\n";
echo "- ISO yyyy-mm-dd: " . $birthdateSummary['iso'] . "\n";
echo "- Formato espanol dd/mm/yyyy: " . $birthdateSummary['spanish'] . "\n";
echo "- Narrativo o libre: " . $birthdateSummary['narrative'] . "\n";
echo "- Desconocido o equivalente: " . $birthdateSummary['unknown'] . "\n";
echo "- Vacio real: " . $birthdateSummary['empty'] . "\n";
echo "- Otros formatos mixtos: " . $birthdateSummary['other'] . "\n\n";
echo "[Revision editorial localizada]\n";
echo "- Dones con descripcion vacia: " . $editorialSummary['gift_empty_description'] . "\n";
echo "- Dones con mechanics_text vacio: " . $editorialSummary['gift_empty_mechanics'] . "\n";
echo "- Dones con posible mojibake visible: " . $editorialSummary['gift_possible_mojibake'] . "\n";
if (is_array($editorialSummary['lanza_de_hielo'])) {
    $lanza = $editorialSummary['lanza_de_hielo'];
    echo "- Lanza de Hielo (#" . (int)$lanza['id'] . "): descripcion="
        . (int)$lanza['description_len']
        . " chars, mechanics="
        . (int)$lanza['mechanics_len']
        . " chars, bloques_duplicados="
        . (int)$lanza['duplicated_blocks']
        . ", mojibake="
        . (int)$lanza['possible_mojibake']
        . ", updated_at="
        . ($lanza['updated_at'] ?? 'NULL')
        . "\n";
} else {
    echo "- Lanza de Hielo: no localizada en fact_gifts.\n";
}
echo "\n";
echo "[Lectura rapida]\n";
if (
    $health['chapters_zero_dates'] === 0
    && $health['comments_zero_dates'] === 0
    && $health['nature_zero'] === 0
    && $health['demeanor_zero'] === 0
    && $health['groups_zero_totem'] === 0
    && $health['organizations_zero_totem'] === 0
    && $health['bcc_orphans'] === 0
    && $health['bog_orphans'] === 0
    && $health['relations_orphans'] === 0
) {
    echo "- La BDD ha quedado claramente mas saneada: sin sentinelas legacy gordos en zonas clave y con puentes principales ya blindados.\n\n";
} else {
    echo "- Queda deuda localizada, pero esta seccion resume exactamente donde mirar.\n\n";
}

$resTables = mysqli_query($link, "SHOW TABLES");

if (!$resTables) {
    hg_runtime_log_error('inspect_db.tables', mysqli_error($link));
    hg_runtime_bootstrap_error('No se pudo listar las tablas de la base de datos.', 500);
    return;
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
        echo "[AVISO] No se pudo describir la tabla\n\n";
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
            echo "  [AVISO] Error en SELECT\n\n";
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
