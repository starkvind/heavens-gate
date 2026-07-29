<?php
/**
 * Seed fact_content_updates from the newest public content records.
 *
 * Usage:
 *   php app/tools/backfill_content_updates.php
 *   php app/tools/backfill_content_updates.php 250
 *   php app/tools/backfill_content_updates.php 100 --dry-run
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only be run from the command line.\n");
    exit(1);
}

require_once(__DIR__ . '/../helpers/db_connection.php');
require_once(__DIR__ . '/../helpers/content_updates.php');

$limit = 100;
$dryRun = false;
foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--dry-run') {
        $dryRun = true;
        continue;
    }
    if (ctype_digit($argument)) {
        $limit = max(1, min(1000, (int)$argument));
        continue;
    }
    fwrite(STDERR, "Unknown argument: {$argument}\n");
    exit(1);
}

$catalog = [
    'dim_archetypes', 'dim_auspices', 'dim_breeds', 'dim_character_conditions',
    'dim_chapters', 'dim_chronicles', 'fact_characters', 'dim_forms',
    'dim_groups', 'dim_merits_flaws', 'dim_organizations', 'dim_realities',
    'dim_seasons', 'dim_soundtracks', 'dim_systems', 'dim_totems', 'dim_traits',
    'dim_tribes', 'fact_combat_maneuvers', 'fact_discipline_powers', 'fact_docs',
    'fact_gifts', 'fact_items', 'fact_misc_systems', 'fact_rites',
    'fact_timeline_events', 'dim_maps',
];

$requiredColumns = [];
$rs = $link->query("SHOW COLUMNS FROM fact_content_updates");
if (!$rs) {
    throw new RuntimeException('No existe fact_content_updates o no se puede leer.');
}
while ($column = $rs->fetch_assoc()) {
    $requiredColumns[(string)$column['Field']] = true;
}
$rs->close();
foreach (['entity_type', 'entity_id', 'updated_at'] as $column) {
    if (!isset($requiredColumns[$column])) {
        throw new RuntimeException("Falta fact_content_updates.{$column}.");
    }
}

$columnsByTable = [];
$schema = $link->query('SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()');
if (!$schema) {
    throw new RuntimeException('No se pudo leer el esquema actual.');
}
while ($column = $schema->fetch_assoc()) {
    $columnsByTable[(string)$column['TABLE_NAME']][(string)$column['COLUMN_NAME']] = true;
}
$schema->close();

$records = [];
foreach ($catalog as $table) {
    $entityType = hg_content_entity_type_for_table($table);
    $columns = $columnsByTable[$table] ?? [];
    if ($entityType === null || !isset($columns['id'])) {
        continue;
    }

    $hasUpdatedAt = isset($columns['updated_at']);
    $hasCreatedAt = isset($columns['created_at']);
    if (!$hasUpdatedAt && !$hasCreatedAt) {
        continue;
    }

    $dateColumn = $hasUpdatedAt ? 'updated_at' : 'created_at';
    $dateExpression = ($hasUpdatedAt && $hasCreatedAt)
        ? 'COALESCE(`updated_at`, `created_at`)'
        : "`{$dateColumn}`";
    $sql = "SELECT id, {$dateExpression} AS updated_at FROM `{$table}`";
    $sql .= " WHERE {$dateExpression} IS NOT NULL ORDER BY updated_at DESC, id DESC LIMIT {$limit}";

    $rows = $link->query($sql);
    if (!$rows) {
        throw new RuntimeException("No se pudo leer {$table}: " . $link->error);
    }
    while ($row = $rows->fetch_assoc()) {
        $records[] = [
            'entity_type' => $entityType,
            'entity_id' => (int)$row['id'],
            'updated_at' => (string)$row['updated_at'],
        ];
    }
    $rows->close();
}

usort($records, static function (array $a, array $b): int {
    $byDate = strcmp($b['updated_at'], $a['updated_at']);
    return $byDate !== 0
        ? $byDate
        : [$a['entity_type'], $a['entity_id']] <=> [$b['entity_type'], $b['entity_id']];
});
$records = array_slice($records, 0, $limit);

if (!$dryRun) {
    $link->begin_transaction();
    try {
        foreach ($records as $record) {
            if (!hg_content_touch_at($link, $record['entity_type'], $record['entity_id'], $record['updated_at'])) {
                throw new RuntimeException('No se pudo registrar ' . $record['entity_type'] . '#' . $record['entity_id'] . '.');
            }
        }
        $link->commit();
    } catch (Throwable $error) {
        $link->rollback();
        throw $error;
    }
}

printf(
    "%s %d registro(s).%s\n",
    $dryRun ? 'Se simularían' : 'Se indexaron',
    count($records),
    $dryRun ? ' No se escribió nada.' : ''
);
