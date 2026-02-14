<?php
// Generate pretty_id for selected tables.
// Usage: /sep/tools/generate_pretty_ids.php?force=1

include(__DIR__ . "/../helpers/heroes.php");
include(__DIR__ . "/../helpers/pretty.php");

if (!$link) {
    die("Error de conexión a la base de datos: " . mysqli_connect_error());
}

$force = isset($_GET['force']) && $_GET['force'] == '1';

$tables = [
    ['table' => 'fact_characters', 'id' => 'id', 'expr' => 'nombre'],
    ['table' => 'dim_groups', 'id' => 'id', 'expr' => 'name'],
    ['table' => 'dim_organizations', 'id' => 'id', 'expr' => 'name'],
    ['table' => 'dim_players', 'id' => 'id', 'expr' => "CONCAT(name, ' ', surname)"],
    ['table' => 'dim_character_types', 'id' => 'id', 'expr' => 'tipo'],
    ['table' => 'dim_systems', 'id' => 'id', 'expr' => 'name'],
    ['table' => 'dim_forms', 'id' => 'id', 'expr' => "CONCAT(afiliacion, ' ', raza, ' ', forma)"],
    ['table' => 'dim_breeds', 'id' => 'id', 'expr' => 'name'],
    ['table' => 'dim_auspices', 'id' => 'id', 'expr' => 'name'],
    ['table' => 'dim_tribes', 'id' => 'id', 'expr' => 'name'],
    ['table' => 'fact_misc_systems', 'id' => 'id', 'expr' => 'name'],
    ['table' => 'dim_traits', 'id' => 'id', 'expr' => 'name'],
    ['table' => 'dim_merits_flaws', 'id' => 'id', 'expr' => 'name'],
    ['table' => 'dim_archetypes', 'id' => 'id', 'expr' => 'name'],
    ['table' => 'fact_combat_maneuvers', 'id' => 'id', 'expr' => 'name'],
    ['table' => 'dim_gift_types', 'id' => 'id', 'expr' => 'name'],
    ['table' => 'fact_gifts', 'id' => 'id', 'expr' => 'nombre'],
    ['table' => 'dim_rite_types', 'id' => 'id', 'expr' => 'name'],
    ['table' => 'fact_rites', 'id' => 'id', 'expr' => 'name'],
    ['table' => 'dim_totem_types', 'id' => 'id', 'expr' => 'name'],
    ['table' => 'dim_totems', 'id' => 'id', 'expr' => 'name'],
    ['table' => 'dim_discipline_types', 'id' => 'id', 'expr' => 'name'],
    ['table' => 'fact_discipline_powers', 'id' => 'id', 'expr' => 'name'],
    ['table' => 'dim_chapters', 'id' => 'id', 'expr' => 'name'],
    ['table' => 'dim_seasons', 'id' => 'id', 'expr' => 'name'],
    ['table' => 'fact_docs', 'id' => 'id', 'expr' => 'titulo'],
    ['table' => 'dim_item_types', 'id' => 'id', 'expr' => 'name'],
    ['table' => 'fact_items', 'id' => 'id', 'expr' => 'name'],
    ['table' => 'fact_map_pois', 'id' => 'id', 'expr' => 'name'],
];

function column_exists(mysqli $link, string $table, string $column): bool {
    $stmt = $link->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    $stmt->close();
    return $exists;
}

function index_exists(mysqli $link, string $table, string $index): bool {
    $stmt = $link->prepare("SHOW INDEX FROM $table WHERE Key_name = ?");
    $stmt->bind_param('s', $index);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    $stmt->close();
    return $exists;
}

echo "<pre>";
echo "GENERADOR DE PRETTY_ID\n";
echo "force = " . ($force ? "1" : "0") . "\n\n";

foreach ($tables as $t) {
    $table = $t['table'];
    $idCol = $t['id'];
    $expr  = $t['expr'];
    $indexName = "uniq_pretty_id";

    echo "Tabla: $table\n";

    if (!column_exists($link, $table, 'pretty_id')) {
        $alter = "ALTER TABLE $table ADD COLUMN pretty_id VARCHAR(190) NULL";
        if (!$link->query($alter)) {
            echo "  ERROR adding column: " . $link->error . "\n";
            continue;
        }
        echo "  + Columna pretty_id creada\n";
    }

    $used = [];
    $resUsed = $link->query("SELECT pretty_id FROM $table WHERE pretty_id IS NOT NULL AND pretty_id != ''");
    if ($resUsed) {
        while ($row = $resUsed->fetch_assoc()) {
            $used[$row['pretty_id']] = true;
        }
        $resUsed->free();
    }

    $sql = "SELECT $idCol AS id, $expr AS name, pretty_id FROM $table";
    $res = $link->query($sql);
    if (!$res) {
        echo "  ERROR selecting data: " . $link->error . "\n";
        continue;
    }

    $updates = 0;
    while ($row = $res->fetch_assoc()) {
        $id = (int)$row['id'];
        $name = (string)$row['name'];
        $current = (string)($row['pretty_id'] ?? '');

        if (!$force && $current !== '') continue;

        $base = slugify_pretty_id($name);
        if ($base === '') $base = (string)$id;

        $slug = $base;
        $i = 2;
        while (isset($used[$slug])) {
            $slug = $base . '-' . $i;
            $i++;
        }
        $used[$slug] = true;

        $stmt = $link->prepare("UPDATE $table SET pretty_id = ? WHERE $idCol = ? LIMIT 1");
        $stmt->bind_param('si', $slug, $id);
        if ($stmt->execute()) {
            $updates++;
        }
        $stmt->close();
    }
    $res->free();

    echo "  + Updated: $updates\n";

    if (!index_exists($link, $table, $indexName)) {
        if ($link->query("CREATE UNIQUE INDEX $indexName ON $table (pretty_id)")) {
            echo "  + Unique index created\n";
        } else {
            echo "  ! Unique index not created: " . $link->error . "\n";
        }
    }

    echo "\n";
}

echo "DONE\n";
echo "</pre>";
