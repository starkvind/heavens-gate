<?php
/**
 * One-off migration: expose combat maneuvers through fact_power_rolls.
 *
 * Usage:
 *   php app/tools/migrate_maneuver_rolls.php --dry-run
 *   php app/tools/migrate_maneuver_rolls.php
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only be run from the command line.\n");
    exit(1);
}

require_once __DIR__ . '/../helpers/db_connection.php';

$dryRun = in_array('--dry-run', array_slice($argv, 1), true);
$unknown = array_diff(array_slice($argv, 1), ['--dry-run']);
if ($unknown) {
    fwrite(STDERR, "Usage: php app/tools/migrate_maneuver_rolls.php [--dry-run]\n");
    exit(1);
}

function hg_mr_table_exists(mysqli $db, string $table): bool {
    $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    if (!$stmt) return false;
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return (int)$count > 0;
}

function hg_mr_trait_id(mysqli $db, string $name, bool $attribute): int {
    $kindSql = $attribute ? "kind = 'Atributos'" : "kind IN ('Talentos', 'Técnicas', 'Tecnicas', 'Conocimientos')";
    $stmt = $db->prepare("SELECT id FROM dim_traits WHERE name = ? AND {$kindSql} ORDER BY id ASC LIMIT 1");
    if (!$stmt) throw new RuntimeException('No se pudo buscar el rasgo ' . $name . '.');
    $stmt->bind_param('s', $name);
    $stmt->execute();
    $stmt->bind_result($id);
    $found = $stmt->fetch();
    $stmt->close();
    if (!$found) throw new RuntimeException("No existe el rasgo requerido: {$name}.");
    return (int)$id;
}

foreach (['fact_power_rolls', 'fact_combat_maneuvers', 'dim_traits'] as $table) {
    if (!hg_mr_table_exists($link, $table)) throw new RuntimeException("Falta la tabla {$table}.");
}

$enumResult = $link->query("SHOW COLUMNS FROM fact_power_rolls LIKE 'power_type'");
$enumRow = $enumResult ? $enumResult->fetch_assoc() : null;
if ($enumResult) $enumResult->free();
if (!$enumRow) throw new RuntimeException('No se pudo inspeccionar fact_power_rolls.power_type.');
$enumType = (string)$enumRow['Type'];
if (strpos($enumType, "'maneuver'") === false && !$dryRun) {
    if (!preg_match('/^enum\\((.*)\\)$/i', $enumType, $matches)) {
        throw new RuntimeException('fact_power_rolls.power_type ya no es un ENUM compatible.');
    }
    $enumValues = str_getcsv($matches[1], ',', "'", '\\');
    $enumValues[] = 'maneuver';
    $enumValues = array_values(array_unique($enumValues));
    $quotedValues = array_map(static fn(string $value): string => "'" . $link->real_escape_string($value) . "'", $enumValues);
    $alterSql = 'ALTER TABLE fact_power_rolls MODIFY power_type ENUM(' . implode(',', $quotedValues) . ') NOT NULL';
    if (!$link->query($alterSql)) {
        throw new RuntimeException('No se pudo ampliar fact_power_rolls.power_type: ' . $link->error);
    }
}

$traits = [
    'Fuerza' => hg_mr_trait_id($link, 'Fuerza', true),
    'Destreza' => hg_mr_trait_id($link, 'Destreza', true),
    'Manipulación' => hg_mr_trait_id($link, 'Manipulación', true),
    'Pelea' => hg_mr_trait_id($link, 'Pelea', false),
    'Atletismo' => hg_mr_trait_id($link, 'Atletismo', false),
    'Intimidación' => hg_mr_trait_id($link, 'Intimidación', false),
    'Expresión' => hg_mr_trait_id($link, 'Expresión', false),
];

$rows = [
    ['asolar', 1, 'Ataque inicial', 'Destreza + Pelea', 'Destreza', 'Pelea', 'variable', 0, 5, 6, 'Dificultad 5 si la maniobra comienza desde otra forma; en otro caso, 6.'],
    ['latigazo-de-cola', 1, 'Latigazo de cola', 'Destreza + Pelea', 'Destreza', 'Pelea', 'fixed', 7, 0, 0, ''],
    ['mordisco', 1, 'Mordisco', 'Destreza + Pelea', 'Destreza', 'Pelea', 'variable', 0, 5, 8, 'Dificultad 5 normalmente; 8 en forma Glabro.'],
    ['patada', 1, 'Patada', 'Destreza + Pelea', 'Destreza', 'Pelea', 'fixed', 7, 0, 0, ''],
    ['garrazo', 1, 'Garrazo', 'Destreza + Pelea', 'Destreza', 'Pelea', 'fixed', 6, 0, 0, ''],
    ['barrido', 1, 'Barrido', 'Destreza + Pelea', 'Destreza', 'Pelea', 'fixed', 8, 0, 0, ''],
    ['desjarretar', 1, 'Desjarretar', 'Destreza + Pelea', 'Destreza', 'Pelea', 'fixed', 8, 0, 0, ''],
    ['insultar', 1, 'Contra adversarios no Garou', 'Manipulación + Intimidación', 'Manipulación', 'Intimidación', 'special', 0, 0, 0, 'Dificultad: Astucia del enemigo + 4.'],
    ['insultar', 2, 'Contra adversarios Garou', 'Manipulación + Expresión', 'Manipulación', 'Expresión', 'special', 0, 0, 0, 'Dificultad: Astucia del enemigo + 4.'],
    ['punetazo', 1, 'Puñetazo', 'Destreza + Pelea', 'Destreza', 'Pelea', 'fixed', 6, 0, 0, ''],
    ['placaje', 1, 'Placaje', 'Destreza + Atletismo', 'Destreza', 'Atletismo', 'fixed', 6, 0, 0, 'La descripción de la maniobra fija esta combinación y dificultad para el atacante.'],
    ['presa', 1, 'Presa', 'Fuerza + Pelea', 'Fuerza', 'Pelea', 'fixed', 6, 0, 0, ''],
];

$findManeuver = $link->prepare('SELECT id, roll, difficulty FROM fact_combat_maneuvers WHERE pretty_id = ? LIMIT 1');
$upsert = $link->prepare("INSERT INTO fact_power_rolls
    (power_type, power_id, roll_order, label, pool_text, attribute_trait_id, skill_trait_id, difficulty_mode, fixed_difficulty, min_difficulty, max_difficulty, rules_note, source_excerpt, status)
    VALUES ('maneuver', ?, ?, ?, ?, ?, ?, ?, NULLIF(?, 0), NULLIF(?, 0), NULLIF(?, 0), ?, ?, 'manual')
    ON DUPLICATE KEY UPDATE label = VALUES(label), pool_text = VALUES(pool_text), attribute_trait_id = VALUES(attribute_trait_id), skill_trait_id = VALUES(skill_trait_id), difficulty_mode = VALUES(difficulty_mode), fixed_difficulty = VALUES(fixed_difficulty), min_difficulty = VALUES(min_difficulty), max_difficulty = VALUES(max_difficulty), rules_note = VALUES(rules_note), source_excerpt = VALUES(source_excerpt), status = 'manual'");
if (!$findManeuver || !$upsert) throw new RuntimeException('No se pudieron preparar las consultas de migración.');

$inserted = 0;
$updated = 0;
if (!$dryRun) $link->begin_transaction();
try {
    foreach ($rows as [$prettyId, $order, $label, $pool, $attribute, $skill, $mode, $fixed, $min, $max, $note]) {
        $findManeuver->bind_param('s', $prettyId);
        $findManeuver->execute();
        $maneuver = $findManeuver->get_result()->fetch_assoc();
        if (!$maneuver) throw new RuntimeException("No existe la maniobra {$prettyId}.");
        $maneuverId = (int)$maneuver['id'];
        $attributeId = $traits[$attribute];
        $skillId = $traits[$skill];
        $source = trim((string)$maneuver['roll'] . ' | Dificultad: ' . (string)$maneuver['difficulty']);
        if (!$dryRun) {
            $upsert->bind_param('iissiisiiiss', $maneuverId, $order, $label, $pool, $attributeId, $skillId, $mode, $fixed, $min, $max, $note, $source);
            if (!$upsert->execute()) throw new RuntimeException('No se pudo guardar ' . $prettyId . ': ' . $upsert->error);
            if ($upsert->affected_rows === 1) $inserted++;
            else $updated++;
        }
        echo ($dryRun ? '[dry-run] ' : '') . "{$prettyId} #{$order}: {$pool}\n";
    }
    if (!$dryRun) $link->commit();
} catch (Throwable $e) {
    if (!$dryRun) $link->rollback();
    throw $e;
} finally {
    $findManeuver->close();
    $upsert->close();
}

echo $dryRun
    ? "Dry-run correcto: " . count($rows) . " tiradas se migrarían.\n"
    : "Migración completada: {$inserted} insertadas, {$updated} actualizadas.\n";