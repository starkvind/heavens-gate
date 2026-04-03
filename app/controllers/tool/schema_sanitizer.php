<?php
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
include_once(__DIR__ . '/../../helpers/pretty.php');
include_once(__DIR__ . '/../../partials/admin/admin_styles.php');

if (function_exists('hg_admin_session_start')) {
    hg_admin_session_start();
} elseif (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

if (!function_exists('hg_ss_h')) {
    function hg_ss_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('hg_ss_table_exists')) {
    function hg_ss_table_exists(mysqli $link, string $table): bool
    {
        $stmt = $link->prepare("
            SELECT COUNT(*)
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
        ");
        if (!$stmt) {
            return false;
        }

        $count = 0;
        $stmt->bind_param('s', $table);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();

        return (int)$count > 0;
    }
}

if (!function_exists('hg_ss_column_exists')) {
    function hg_ss_column_exists(mysqli $link, string $table, string $column): bool
    {
        $stmt = $link->prepare("
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
        ");
        if (!$stmt) {
            return false;
        }

        $count = 0;
        $stmt->bind_param('ss', $table, $column);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();

        return (int)$count > 0;
    }
}

if (!function_exists('hg_ss_scalar')) {
    function hg_ss_scalar(mysqli $link, string $sql): int
    {
        $rs = $link->query($sql);
        if (!$rs) {
            return 0;
        }

        $row = $rs->fetch_row();
        $rs->free();
        return (int)($row[0] ?? 0);
    }
}

if (!function_exists('hg_ss_exec')) {
    function hg_ss_exec(mysqli $link, string $sql): int
    {
        $ok = $link->query($sql);
        if ($ok === false) {
            throw new RuntimeException($link->error . ' | SQL: ' . $sql);
        }
        return (int)$link->affected_rows;
    }
}

if (!function_exists('hg_ss_column_is_nullable')) {
    function hg_ss_column_is_nullable(mysqli $link, string $table, string $column): bool
    {
        $stmt = $link->prepare("
            SELECT IS_NULLABLE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
            LIMIT 1
        ");
        if (!$stmt) {
            return false;
        }

        $nullable = 'NO';
        $stmt->bind_param('ss', $table, $column);
        $stmt->execute();
        $stmt->bind_result($nullable);
        $stmt->fetch();
        $stmt->close();

        return strtoupper((string)$nullable) === 'YES';
    }
}

if (!function_exists('hg_ss_index_definitions')) {
    function hg_ss_index_definitions(mysqli $link, string $table): array
    {
        $stmt = $link->prepare("
            SELECT INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
            ORDER BY INDEX_NAME, SEQ_IN_INDEX
        ");
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('s', $table);
        $stmt->execute();
        $rs = $stmt->get_result();

        $out = [];
        while ($rs && ($row = $rs->fetch_assoc())) {
            $name = (string)($row['INDEX_NAME'] ?? '');
            if ($name === '') {
                continue;
            }
            if (!isset($out[$name])) {
                $out[$name] = [
                    'unique' => ((int)($row['NON_UNIQUE'] ?? 1) === 0),
                    'columns' => [],
                ];
            }
            $out[$name]['columns'][] = (string)($row['COLUMN_NAME'] ?? '');
        }

        $stmt->close();
        return $out;
    }
}

if (!function_exists('hg_ss_has_index')) {
    function hg_ss_has_index(
        mysqli $link,
        string $table,
        array $columns,
        bool $uniqueOnly = false,
        bool $exact = false
    ): bool {
        $columns = array_values(array_filter(array_map('strval', $columns), static function (string $value): bool {
            return $value !== '';
        }));
        if (empty($columns)) {
            return false;
        }

        foreach (hg_ss_index_definitions($link, $table) as $info) {
            if ($uniqueOnly && empty($info['unique'])) {
                continue;
            }

            $indexColumns = array_values((array)($info['columns'] ?? []));
            if (count($indexColumns) < count($columns)) {
                continue;
            }

            $prefix = array_slice($indexColumns, 0, count($columns));
            if ($prefix !== $columns) {
                continue;
            }

            if ($exact && count($indexColumns) !== count($columns)) {
                continue;
            }

            return true;
        }

        return false;
    }
}

if (!function_exists('hg_ss_fk_definitions')) {
    function hg_ss_fk_definitions(mysqli $link, string $table): array
    {
        $stmt = $link->prepare("
            SELECT
                CONSTRAINT_NAME,
                COLUMN_NAME,
                REFERENCED_TABLE_NAME,
                REFERENCED_COLUMN_NAME,
                ORDINAL_POSITION
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND REFERENCED_TABLE_NAME IS NOT NULL
            ORDER BY CONSTRAINT_NAME, ORDINAL_POSITION
        ");
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('s', $table);
        $stmt->execute();
        $rs = $stmt->get_result();

        $out = [];
        while ($rs && ($row = $rs->fetch_assoc())) {
            $name = (string)($row['CONSTRAINT_NAME'] ?? '');
            if ($name === '') {
                continue;
            }
            if (!isset($out[$name])) {
                $out[$name] = [
                    'columns' => [],
                    'ref_table' => (string)($row['REFERENCED_TABLE_NAME'] ?? ''),
                    'ref_columns' => [],
                ];
            }
            $out[$name]['columns'][] = (string)($row['COLUMN_NAME'] ?? '');
            $out[$name]['ref_columns'][] = (string)($row['REFERENCED_COLUMN_NAME'] ?? '');
        }

        $stmt->close();
        return $out;
    }
}

if (!function_exists('hg_ss_has_fk')) {
    function hg_ss_has_fk(
        mysqli $link,
        string $table,
        array $columns,
        string $refTable,
        array $refColumns
    ): bool {
        foreach (hg_ss_fk_definitions($link, $table) as $info) {
            if ((string)($info['ref_table'] ?? '') !== $refTable) {
                continue;
            }
            if (array_values((array)($info['columns'] ?? [])) !== array_values($columns)) {
                continue;
            }
            if (array_values((array)($info['ref_columns'] ?? [])) !== array_values($refColumns)) {
                continue;
            }
            return true;
        }

        return false;
    }
}

if (!function_exists('hg_ss_note')) {
    function hg_ss_note(array &$messages, string $text): void
    {
        $messages[] = trim($text);
    }
}

if (!function_exists('hg_ss_delete_exact_duplicates')) {
    function hg_ss_delete_exact_duplicates(mysqli $link, string $table, string $joinOn): int
    {
        $sql = "DELETE t1 FROM `{$table}` t1 JOIN `{$table}` t2 ON t1.id > t2.id AND {$joinOn}";
        return hg_ss_exec($link, $sql);
    }
}

if (!function_exists('hg_ss_pretty_configs')) {
    function hg_ss_pretty_configs(): array
    {
        return [
            ['table' => 'fact_characters', 'label' => 'Personajes', 'expr' => 'name'],
            ['table' => 'dim_groups', 'label' => 'Grupos', 'expr' => 'name'],
            ['table' => 'dim_organizations', 'label' => 'Organizaciones', 'expr' => 'name'],
            ['table' => 'dim_character_types', 'label' => 'Tipos de personaje', 'expr' => 'kind'],
            ['table' => 'dim_systems', 'label' => 'Sistemas', 'expr' => 'name'],
            ['table' => 'dim_forms', 'label' => 'Formas', 'expr' => "CONCAT(COALESCE((SELECT name FROM dim_systems ds WHERE ds.id = dim_forms.system_id LIMIT 1), ''), ' ', COALESCE(race, ''), ' ', COALESCE(form, ''))"],
            ['table' => 'dim_breeds', 'label' => 'Razas', 'expr' => 'name'],
            ['table' => 'dim_auspices', 'label' => 'Auspicios', 'expr' => 'name'],
            ['table' => 'dim_tribes', 'label' => 'Tribus', 'expr' => 'name'],
            ['table' => 'fact_misc_systems', 'label' => 'Detalles extra de sistema', 'expr' => 'name'],
            ['table' => 'dim_traits', 'label' => 'Traits', 'expr' => 'name'],
            ['table' => 'dim_merits_flaws', 'label' => 'Meritos y defectos', 'expr' => 'name'],
            ['table' => 'dim_archetypes', 'label' => 'Arquetipos', 'expr' => 'name'],
            ['table' => 'fact_combat_maneuvers', 'label' => 'Maniobras', 'expr' => 'name'],
            ['table' => 'dim_gift_types', 'label' => 'Tipos de don', 'expr' => 'name'],
            ['table' => 'fact_gifts', 'label' => 'Dones', 'expr' => 'name'],
            ['table' => 'dim_rite_types', 'label' => 'Tipos de rito', 'expr' => 'name'],
            ['table' => 'fact_rites', 'label' => 'Rituales', 'expr' => 'name'],
            ['table' => 'dim_totem_types', 'label' => 'Tipos de totem', 'expr' => 'name'],
            ['table' => 'dim_totems', 'label' => 'Totems', 'expr' => 'name'],
            ['table' => 'dim_discipline_types', 'label' => 'Tipos de disciplina', 'expr' => 'name'],
            ['table' => 'fact_discipline_powers', 'label' => 'Disciplinas', 'expr' => 'name'],
            ['table' => 'dim_chapters', 'label' => 'Capitulos', 'expr' => 'name'],
            ['table' => 'dim_seasons', 'label' => 'Temporadas', 'expr' => 'name'],
            ['table' => 'fact_docs', 'label' => 'Documentos', 'expr' => 'title'],
            ['table' => 'dim_item_types', 'label' => 'Tipos de objeto', 'expr' => 'name'],
            ['table' => 'fact_items', 'label' => 'Objetos', 'expr' => 'name'],
            ['table' => 'fact_map_pois', 'label' => 'POIs', 'expr' => 'name'],
            ['table' => 'dim_bibliographies', 'label' => 'Bibliografias', 'expr' => 'name'],
        ];
    }
}

if (!function_exists('hg_ss_pretty_rows')) {
    function hg_ss_pretty_rows(mysqli $link, string $table, string $expr): array
    {
        $sql = "SELECT id, {$expr} AS source, COALESCE(pretty_id, '') AS pretty_id FROM `{$table}` ORDER BY id ASC";
        $rs = $link->query($sql);
        if (!$rs) {
            return [];
        }

        $rows = [];
        while ($row = $rs->fetch_assoc()) {
            $rows[] = [
                'id' => (int)($row['id'] ?? 0),
                'source' => (string)($row['source'] ?? ''),
                'pretty_id' => trim((string)($row['pretty_id'] ?? '')),
            ];
        }
        $rs->free();

        return $rows;
    }
}

if (!function_exists('hg_ss_alias_table_sql')) {
    function hg_ss_alias_table_sql(): string
    {
        return "CREATE TABLE `fact_pretty_id_aliases` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `table_name` varchar(120) NOT NULL,
  `entity_id` int(10) unsigned NOT NULL,
  `old_pretty_id` varchar(190) NOT NULL,
  `new_pretty_id` varchar(190) NOT NULL,
  `source` varchar(50) NOT NULL DEFAULT 'schema_sanitizer',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pretty_alias_lookup` (`table_name`,`old_pretty_id`),
  KEY `idx_pretty_alias_entity` (`table_name`,`entity_id`),
  KEY `idx_pretty_alias_new` (`table_name`,`new_pretty_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    }
}

if (!function_exists('hg_ss_pretty_exists')) {
    function hg_ss_pretty_exists(mysqli $link, string $table, string $prettyId, int $excludeId = 0): bool
    {
        if ($prettyId === '' || !hg_ss_column_exists($link, $table, 'pretty_id')) {
            return false;
        }

        $stmt = $link->prepare("SELECT id FROM `{$table}` WHERE pretty_id = ? AND id <> ? LIMIT 1");
        if (!$stmt) {
            return false;
        }

        $foundId = 0;
        $stmt->bind_param('si', $prettyId, $excludeId);
        $ok = false;
        if ($stmt->execute()) {
            $stmt->bind_result($foundId);
            $ok = $stmt->fetch();
        }
        $stmt->close();

        return (bool)$ok && $foundId > 0;
    }
}

if (!function_exists('hg_ss_alias_exists')) {
    function hg_ss_alias_exists(mysqli $link, string $table, string $oldPrettyId, int $excludeId = 0): bool
    {
        if ($oldPrettyId === '' || !hg_ss_table_exists($link, 'fact_pretty_id_aliases')) {
            return false;
        }

        $stmt = $link->prepare("
            SELECT entity_id
            FROM fact_pretty_id_aliases
            WHERE table_name = ?
              AND old_pretty_id = ?
              AND entity_id <> ?
            LIMIT 1
        ");
        if (!$stmt) {
            return false;
        }

        $foundId = 0;
        $stmt->bind_param('ssi', $table, $oldPrettyId, $excludeId);
        $ok = false;
        if ($stmt->execute()) {
            $stmt->bind_result($foundId);
            $ok = $stmt->fetch();
        }
        $stmt->close();

        return (bool)$ok && $foundId > 0;
    }
}

if (!function_exists('hg_ss_next_pretty_id')) {
    function hg_ss_next_pretty_id(mysqli $link, string $table, string $baseSlug, int $id): string
    {
        $baseSlug = trim($baseSlug);
        if ($baseSlug === '') {
            $baseSlug = (string)$id;
        }

        $candidate = $baseSlug;
        if (!hg_ss_pretty_exists($link, $table, $candidate, $id) && !hg_ss_alias_exists($link, $table, $candidate, $id)) {
            return $candidate;
        }

        $candidate = $baseSlug . '-' . $id;
        if (!hg_ss_pretty_exists($link, $table, $candidate, $id) && !hg_ss_alias_exists($link, $table, $candidate, $id)) {
            return $candidate;
        }

        $suffix = 2;
        while (hg_ss_pretty_exists($link, $table, $candidate . '-' . $suffix, $id) || hg_ss_alias_exists($link, $table, $candidate . '-' . $suffix, $id)) {
            $suffix++;
        }

        return $candidate . '-' . $suffix;
    }
}

if (!function_exists('hg_ss_upsert_alias')) {
    function hg_ss_upsert_alias(mysqli $link, string $table, int $entityId, string $oldPrettyId, string $newPrettyId): bool
    {
        $oldPrettyId = trim($oldPrettyId);
        $newPrettyId = trim($newPrettyId);
        if ($oldPrettyId === '' || $newPrettyId === '' || $oldPrettyId === $newPrettyId) {
            return true;
        }

        $stmt = $link->prepare("
            INSERT INTO fact_pretty_id_aliases (table_name, entity_id, old_pretty_id, new_pretty_id, source)
            VALUES (?, ?, ?, ?, 'schema_sanitizer')
            ON DUPLICATE KEY UPDATE
                entity_id = VALUES(entity_id),
                new_pretty_id = VALUES(new_pretty_id),
                source = VALUES(source)
        ");
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('siss', $table, $entityId, $oldPrettyId, $newPrettyId);
        $ok = $stmt->execute();
        $stmt->close();

        return (bool)$ok;
    }
}

if (!function_exists('hg_ss_update_pretty')) {
    function hg_ss_update_pretty(mysqli $link, string $table, int $id, string $prettyId): bool
    {
        $stmt = $link->prepare("UPDATE `{$table}` SET pretty_id = ? WHERE id = ? LIMIT 1");
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('si', $prettyId, $id);
        $ok = $stmt->execute();
        $stmt->close();

        return (bool)$ok;
    }
}

if (!function_exists('hg_ss_analyze_chapters_zero_dates')) {
    function hg_ss_analyze_chapters_zero_dates(mysqli $link): array
    {
        $count = hg_ss_scalar($link, "SELECT COUNT(*) FROM dim_chapters WHERE played_date = '0000-00-00'");
        $nullable = hg_ss_column_is_nullable($link, 'dim_chapters', 'played_date');

        return [
            'issue_count' => $count + ($nullable ? 0 : 1),
            'status' => ($count === 0 && $nullable) ? 'clean' : 'pending',
            'details' => [
                $count . ' capitulos con played_date = 0000-00-00',
                $nullable ? 'played_date ya admite NULL' : 'played_date sigue sin admitir NULL',
            ],
        ];
    }
}

if (!function_exists('hg_ss_apply_chapters_zero_dates')) {
    function hg_ss_apply_chapters_zero_dates(mysqli $link): array
    {
        $messages = [];

        if (!hg_ss_column_is_nullable($link, 'dim_chapters', 'played_date')) {
            hg_ss_exec($link, "ALTER TABLE `dim_chapters` MODIFY `played_date` date DEFAULT NULL");
            hg_ss_note($messages, 'played_date ahora admite NULL.');
        }

        $affected = hg_ss_exec($link, "UPDATE `dim_chapters` SET `played_date` = NULL WHERE `played_date` = '0000-00-00'");
        hg_ss_note($messages, 'Fechas saneadas en dim_chapters: ' . $affected . '.');

        return ['messages' => $messages];
    }
}

if (!function_exists('hg_ss_analyze_character_comments')) {
    function hg_ss_analyze_character_comments(mysqli $link): array
    {
        $zeroDates = hg_ss_scalar($link, "SELECT COUNT(*) FROM fact_characters_comments WHERE commented_at = '0000-00-00'");
        $orphans = hg_ss_scalar($link, "
            SELECT COUNT(*)
            FROM fact_characters_comments c
            LEFT JOIN fact_characters f ON f.id = c.character_id
            WHERE f.id IS NULL
        ");
        $nullable = hg_ss_column_is_nullable($link, 'fact_characters_comments', 'commented_at');
        $hasIndex = hg_ss_has_index($link, 'fact_characters_comments', ['character_id']);
        $hasFk = hg_ss_has_fk($link, 'fact_characters_comments', ['character_id'], 'fact_characters', ['id']);

        return [
            'issue_count' => $zeroDates + $orphans + ($nullable ? 0 : 1) + ($hasIndex ? 0 : 1) + ($hasFk ? 0 : 1),
            'status' => ($zeroDates === 0 && $orphans === 0 && $nullable && $hasIndex && $hasFk) ? 'clean' : 'pending',
            'details' => [
                $zeroDates . ' comentarios con commented_at = 0000-00-00',
                $orphans . ' comentarios huerfanos',
                $hasFk ? 'FK character_id ya presente' : 'Falta FK a fact_characters',
            ],
        ];
    }
}

if (!function_exists('hg_ss_apply_character_comments')) {
    function hg_ss_apply_character_comments(mysqli $link): array
    {
        $messages = [];

        $orphans = hg_ss_exec($link, "
            DELETE c
            FROM `fact_characters_comments` c
            LEFT JOIN `fact_characters` f ON f.id = c.character_id
            WHERE f.id IS NULL
        ");
        hg_ss_note($messages, 'Comentarios huerfanos eliminados: ' . $orphans . '.');

        if (!hg_ss_column_is_nullable($link, 'fact_characters_comments', 'commented_at')) {
            hg_ss_exec($link, "ALTER TABLE `fact_characters_comments` MODIFY `commented_at` date DEFAULT NULL");
            hg_ss_note($messages, 'commented_at ahora admite NULL.');
        }

        $zeroDates = hg_ss_exec($link, "UPDATE `fact_characters_comments` SET `commented_at` = NULL WHERE `commented_at` = '0000-00-00'");
        hg_ss_note($messages, 'Fechas saneadas en comentarios: ' . $zeroDates . '.');

        if (!hg_ss_has_index($link, 'fact_characters_comments', ['character_id'])) {
            hg_ss_exec($link, "ALTER TABLE `fact_characters_comments` ADD KEY `idx_fcc_character_id` (`character_id`)");
            hg_ss_note($messages, 'Indice de character_id creado.');
        }

        if (!hg_ss_has_fk($link, 'fact_characters_comments', ['character_id'], 'fact_characters', ['id'])) {
            hg_ss_exec($link, "ALTER TABLE `fact_characters_comments` ADD CONSTRAINT `fk_fcc_character` FOREIGN KEY (`character_id`) REFERENCES `fact_characters` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");
            hg_ss_note($messages, 'FK de comentarios -> personaje creada.');
        }

        return ['messages' => $messages];
    }
}

if (!function_exists('hg_ss_analyze_groups_totem')) {
    function hg_ss_analyze_groups_totem(mysqli $link): array
    {
        $zeroTotems = hg_ss_scalar($link, "SELECT COUNT(*) FROM dim_groups WHERE totem_id = 0");
        $orphanTotems = hg_ss_scalar($link, "
            SELECT COUNT(*)
            FROM dim_groups g
            LEFT JOIN dim_totems t ON t.id = g.totem_id
            WHERE g.totem_id IS NOT NULL
              AND g.totem_id <> 0
              AND t.id IS NULL
        ");
        $orphanChronicles = hg_ss_scalar($link, "
            SELECT COUNT(*)
            FROM dim_groups g
            LEFT JOIN dim_chronicles c ON c.id = g.chronicle_id
            WHERE c.id IS NULL
        ");
        $nullable = hg_ss_column_is_nullable($link, 'dim_groups', 'totem_id');
        $hasChronicleIndex = hg_ss_has_index($link, 'dim_groups', ['chronicle_id']);
        $hasTotemIndex = hg_ss_has_index($link, 'dim_groups', ['totem_id']);
        $hasChronicleFk = hg_ss_has_fk($link, 'dim_groups', ['chronicle_id'], 'dim_chronicles', ['id']);
        $hasTotemFk = hg_ss_has_fk($link, 'dim_groups', ['totem_id'], 'dim_totems', ['id']);

        return [
            'issue_count' => $zeroTotems + $orphanTotems + $orphanChronicles + ($nullable ? 0 : 1) + ($hasChronicleIndex ? 0 : 1) + ($hasTotemIndex ? 0 : 1) + ($hasChronicleFk ? 0 : 1) + ($hasTotemFk ? 0 : 1),
            'status' => ($zeroTotems === 0 && $orphanTotems === 0 && $orphanChronicles === 0 && $nullable && $hasChronicleIndex && $hasTotemIndex && $hasChronicleFk && $hasTotemFk) ? 'clean' : 'pending',
            'details' => [
                $zeroTotems . ' grupos con totem_id = 0',
                $orphanTotems . ' grupos con totem_id huerfano',
                $orphanChronicles . ' grupos con chronicle_id huerfano',
            ],
        ];
    }
}

if (!function_exists('hg_ss_apply_groups_totem')) {
    function hg_ss_apply_groups_totem(mysqli $link): array
    {
        $messages = [];

        if (!hg_ss_column_is_nullable($link, 'dim_groups', 'totem_id')) {
            hg_ss_exec($link, "ALTER TABLE `dim_groups` MODIFY `totem_id` int(10) unsigned DEFAULT NULL");
            hg_ss_note($messages, 'totem_id en dim_groups ahora admite NULL.');
        }

        $nullified = hg_ss_exec($link, "
            UPDATE `dim_groups` g
            LEFT JOIN `dim_totems` t ON t.id = g.totem_id
            SET g.totem_id = NULL
            WHERE g.totem_id = 0
               OR (g.totem_id IS NOT NULL AND g.totem_id <> 0 AND t.id IS NULL)
        ");
        hg_ss_note($messages, 'Totems saneados en grupos: ' . $nullified . '.');

        if (!hg_ss_has_index($link, 'dim_groups', ['chronicle_id'])) {
            hg_ss_exec($link, "ALTER TABLE `dim_groups` ADD KEY `idx_dim_groups_chronicle_id` (`chronicle_id`)");
            hg_ss_note($messages, 'Indice de chronicle_id creado en grupos.');
        }

        if (!hg_ss_has_index($link, 'dim_groups', ['totem_id'])) {
            hg_ss_exec($link, "ALTER TABLE `dim_groups` ADD KEY `idx_dim_groups_totem_id` (`totem_id`)");
            hg_ss_note($messages, 'Indice de totem_id creado en grupos.');
        }

        $orphanChronicles = hg_ss_scalar($link, "
            SELECT COUNT(*)
            FROM dim_groups g
            LEFT JOIN dim_chronicles c ON c.id = g.chronicle_id
            WHERE c.id IS NULL
        ");
        if ($orphanChronicles === 0 && !hg_ss_has_fk($link, 'dim_groups', ['chronicle_id'], 'dim_chronicles', ['id'])) {
            hg_ss_exec($link, "ALTER TABLE `dim_groups` ADD CONSTRAINT `fk_dim_groups_chronicle` FOREIGN KEY (`chronicle_id`) REFERENCES `dim_chronicles` (`id`) ON UPDATE CASCADE");
            hg_ss_note($messages, 'FK groups -> chronicle creada.');
        } elseif ($orphanChronicles > 0) {
            hg_ss_note($messages, 'FK groups -> chronicle omitida: quedan ' . $orphanChronicles . ' chronicle_id huerfanos.');
        }

        if (!hg_ss_has_fk($link, 'dim_groups', ['totem_id'], 'dim_totems', ['id'])) {
            hg_ss_exec($link, "ALTER TABLE `dim_groups` ADD CONSTRAINT `fk_dim_groups_totem` FOREIGN KEY (`totem_id`) REFERENCES `dim_totems` (`id`) ON DELETE SET NULL ON UPDATE CASCADE");
            hg_ss_note($messages, 'FK groups -> totem creada.');
        }

        return ['messages' => $messages];
    }
}

if (!function_exists('hg_ss_analyze_organizations_totem')) {
    function hg_ss_analyze_organizations_totem(mysqli $link): array
    {
        $zeroTotems = hg_ss_scalar($link, "SELECT COUNT(*) FROM dim_organizations WHERE totem_id = 0");
        $orphanTotems = hg_ss_scalar($link, "
            SELECT COUNT(*)
            FROM dim_organizations o
            LEFT JOIN dim_totems t ON t.id = o.totem_id
            WHERE o.totem_id IS NOT NULL
              AND o.totem_id <> 0
              AND t.id IS NULL
        ");
        $nullable = hg_ss_column_is_nullable($link, 'dim_organizations', 'totem_id');
        $hasIndex = hg_ss_has_index($link, 'dim_organizations', ['totem_id']);
        $hasFk = hg_ss_has_fk($link, 'dim_organizations', ['totem_id'], 'dim_totems', ['id']);

        return [
            'issue_count' => $zeroTotems + $orphanTotems + ($nullable ? 0 : 1) + ($hasIndex ? 0 : 1) + ($hasFk ? 0 : 1),
            'status' => ($zeroTotems === 0 && $orphanTotems === 0 && $nullable && $hasIndex && $hasFk) ? 'clean' : 'pending',
            'details' => [
                $zeroTotems . ' organizaciones con totem_id = 0',
                $orphanTotems . ' organizaciones con totem_id huerfano',
                $hasFk ? 'FK a dim_totems ya presente' : 'Falta FK a dim_totems',
            ],
        ];
    }
}

if (!function_exists('hg_ss_apply_organizations_totem')) {
    function hg_ss_apply_organizations_totem(mysqli $link): array
    {
        $messages = [];

        if (!hg_ss_column_is_nullable($link, 'dim_organizations', 'totem_id')) {
            hg_ss_exec($link, "ALTER TABLE `dim_organizations` MODIFY `totem_id` int(10) unsigned DEFAULT NULL");
            hg_ss_note($messages, 'totem_id en dim_organizations ahora admite NULL.');
        }

        $nullified = hg_ss_exec($link, "
            UPDATE `dim_organizations` o
            LEFT JOIN `dim_totems` t ON t.id = o.totem_id
            SET o.totem_id = NULL
            WHERE o.totem_id = 0
               OR (o.totem_id IS NOT NULL AND o.totem_id <> 0 AND t.id IS NULL)
        ");
        hg_ss_note($messages, 'Totems saneados en organizaciones: ' . $nullified . '.');

        if (!hg_ss_has_index($link, 'dim_organizations', ['totem_id'])) {
            hg_ss_exec($link, "ALTER TABLE `dim_organizations` ADD KEY `idx_dim_organizations_totem_id` (`totem_id`)");
            hg_ss_note($messages, 'Indice de totem_id creado en organizaciones.');
        }

        if (!hg_ss_has_fk($link, 'dim_organizations', ['totem_id'], 'dim_totems', ['id'])) {
            hg_ss_exec($link, "ALTER TABLE `dim_organizations` ADD CONSTRAINT `fk_dim_organizations_totem` FOREIGN KEY (`totem_id`) REFERENCES `dim_totems` (`id`) ON DELETE SET NULL ON UPDATE CASCADE");
            hg_ss_note($messages, 'FK organizations -> totem creada.');
        }

        return ['messages' => $messages];
    }
}

if (!function_exists('hg_ss_analyze_bridge_chapters_characters')) {
    function hg_ss_analyze_bridge_chapters_characters(mysqli $link): array
    {
        $orphanChapters = hg_ss_scalar($link, "
            SELECT COUNT(*)
            FROM bridge_chapters_characters b
            LEFT JOIN dim_chapters c ON c.id = b.chapter_id
            WHERE c.id IS NULL
        ");
        $orphanCharacters = hg_ss_scalar($link, "
            SELECT COUNT(*)
            FROM bridge_chapters_characters b
            LEFT JOIN fact_characters f ON f.id = b.character_id
            WHERE f.id IS NULL
        ");
        $duplicates = hg_ss_scalar($link, "
            SELECT COALESCE(SUM(t.cnt - 1), 0)
            FROM (
                SELECT COUNT(*) AS cnt
                FROM bridge_chapters_characters
                GROUP BY chapter_id, character_id, participation_role
                HAVING COUNT(*) > 1
            ) t
        ");
        $hasChapterIndex = hg_ss_has_index($link, 'bridge_chapters_characters', ['chapter_id']);
        $hasCharacterIndex = hg_ss_has_index($link, 'bridge_chapters_characters', ['character_id']);
        $hasChapterFk = hg_ss_has_fk($link, 'bridge_chapters_characters', ['chapter_id'], 'dim_chapters', ['id']);
        $hasCharacterFk = hg_ss_has_fk($link, 'bridge_chapters_characters', ['character_id'], 'fact_characters', ['id']);

        return [
            'issue_count' => $orphanChapters + $orphanCharacters + $duplicates + ($hasChapterIndex ? 0 : 1) + ($hasCharacterIndex ? 0 : 1) + ($hasChapterFk ? 0 : 1) + ($hasCharacterFk ? 0 : 1),
            'status' => ($orphanChapters === 0 && $orphanCharacters === 0 && $duplicates === 0 && $hasChapterIndex && $hasCharacterIndex && $hasChapterFk && $hasCharacterFk) ? 'clean' : 'pending',
            'details' => [
                $orphanChapters . ' filas con chapter_id huerfano',
                $orphanCharacters . ' filas con character_id huerfano',
                $duplicates . ' duplicados exactos',
            ],
        ];
    }
}

if (!function_exists('hg_ss_apply_bridge_chapters_characters')) {
    function hg_ss_apply_bridge_chapters_characters(mysqli $link): array
    {
        $messages = [];

        $orphans = hg_ss_exec($link, "
            DELETE b
            FROM `bridge_chapters_characters` b
            LEFT JOIN `dim_chapters` c ON c.id = b.chapter_id
            LEFT JOIN `fact_characters` f ON f.id = b.character_id
            WHERE c.id IS NULL OR f.id IS NULL
        ");
        hg_ss_note($messages, 'Puente capitulo/personaje: huerfanos eliminados = ' . $orphans . '.');

        $duplicates = hg_ss_delete_exact_duplicates($link, 'bridge_chapters_characters', "t1.chapter_id = t2.chapter_id AND t1.character_id = t2.character_id AND t1.participation_role = t2.participation_role");
        hg_ss_note($messages, 'Duplicados exactos eliminados en bridge_chapters_characters: ' . $duplicates . '.');

        if (!hg_ss_has_index($link, 'bridge_chapters_characters', ['chapter_id'])) {
            hg_ss_exec($link, "ALTER TABLE `bridge_chapters_characters` ADD KEY `idx_bcc_chapter_id` (`chapter_id`)");
            hg_ss_note($messages, 'Indice chapter_id creado en bridge_chapters_characters.');
        }

        if (!hg_ss_has_index($link, 'bridge_chapters_characters', ['character_id'])) {
            hg_ss_exec($link, "ALTER TABLE `bridge_chapters_characters` ADD KEY `idx_bcc_character_id` (`character_id`)");
            hg_ss_note($messages, 'Indice character_id creado en bridge_chapters_characters.');
        }

        if (!hg_ss_has_fk($link, 'bridge_chapters_characters', ['chapter_id'], 'dim_chapters', ['id'])) {
            hg_ss_exec($link, "ALTER TABLE `bridge_chapters_characters` ADD CONSTRAINT `fk_bcc_chapter` FOREIGN KEY (`chapter_id`) REFERENCES `dim_chapters` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");
            hg_ss_note($messages, 'FK bridge_chapters_characters -> dim_chapters creada.');
        }

        if (!hg_ss_has_fk($link, 'bridge_chapters_characters', ['character_id'], 'fact_characters', ['id'])) {
            hg_ss_exec($link, "ALTER TABLE `bridge_chapters_characters` ADD CONSTRAINT `fk_bcc_character` FOREIGN KEY (`character_id`) REFERENCES `fact_characters` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");
            hg_ss_note($messages, 'FK bridge_chapters_characters -> fact_characters creada.');
        }

        return ['messages' => $messages];
    }
}

if (!function_exists('hg_ss_analyze_bridge_organizations_groups')) {
    function hg_ss_analyze_bridge_organizations_groups(mysqli $link): array
    {
        $orphanOrganizations = hg_ss_scalar($link, "
            SELECT COUNT(*)
            FROM bridge_organizations_groups b
            LEFT JOIN dim_organizations o ON o.id = b.organization_id
            WHERE o.id IS NULL
        ");
        $orphanGroups = hg_ss_scalar($link, "
            SELECT COUNT(*)
            FROM bridge_organizations_groups b
            LEFT JOIN dim_groups g ON g.id = b.group_id
            WHERE g.id IS NULL
        ");
        $duplicates = hg_ss_scalar($link, "
            SELECT COALESCE(SUM(t.cnt - 1), 0)
            FROM (
                SELECT COUNT(*) AS cnt
                FROM bridge_organizations_groups
                GROUP BY organization_id, group_id
                HAVING COUNT(*) > 1
            ) t
        ");
        $hasUnique = hg_ss_has_index($link, 'bridge_organizations_groups', ['organization_id', 'group_id'], true, true);
        $hasGroupIndex = hg_ss_has_index($link, 'bridge_organizations_groups', ['group_id']);
        $hasOrgFk = hg_ss_has_fk($link, 'bridge_organizations_groups', ['organization_id'], 'dim_organizations', ['id']);
        $hasGroupFk = hg_ss_has_fk($link, 'bridge_organizations_groups', ['group_id'], 'dim_groups', ['id']);

        return [
            'issue_count' => $orphanOrganizations + $orphanGroups + $duplicates + ($hasUnique ? 0 : 1) + ($hasGroupIndex ? 0 : 1) + ($hasOrgFk ? 0 : 1) + ($hasGroupFk ? 0 : 1),
            'status' => ($orphanOrganizations === 0 && $orphanGroups === 0 && $duplicates === 0 && $hasUnique && $hasGroupIndex && $hasOrgFk && $hasGroupFk) ? 'clean' : 'pending',
            'details' => [
                $orphanOrganizations . ' filas con organization_id huerfano',
                $orphanGroups . ' filas con group_id huerfano',
                $duplicates . ' duplicados exactos',
            ],
        ];
    }
}

if (!function_exists('hg_ss_apply_bridge_organizations_groups')) {
    function hg_ss_apply_bridge_organizations_groups(mysqli $link): array
    {
        $messages = [];

        $orphans = hg_ss_exec($link, "
            DELETE b
            FROM `bridge_organizations_groups` b
            LEFT JOIN `dim_organizations` o ON o.id = b.organization_id
            LEFT JOIN `dim_groups` g ON g.id = b.group_id
            WHERE o.id IS NULL OR g.id IS NULL
        ");
        hg_ss_note($messages, 'Puente organization/group: huerfanos eliminados = ' . $orphans . '.');

        $duplicates = hg_ss_delete_exact_duplicates($link, 'bridge_organizations_groups', "t1.organization_id = t2.organization_id AND t1.group_id = t2.group_id");
        hg_ss_note($messages, 'Duplicados exactos eliminados en bridge_organizations_groups: ' . $duplicates . '.');

        if (!hg_ss_has_index($link, 'bridge_organizations_groups', ['group_id'])) {
            hg_ss_exec($link, "ALTER TABLE `bridge_organizations_groups` ADD KEY `idx_bog_group_id` (`group_id`)");
            hg_ss_note($messages, 'Indice group_id creado en bridge_organizations_groups.');
        }

        if (!hg_ss_has_index($link, 'bridge_organizations_groups', ['organization_id', 'group_id'], true, true)) {
            hg_ss_exec($link, "ALTER TABLE `bridge_organizations_groups` ADD UNIQUE KEY `uq_clan_group` (`organization_id`,`group_id`)");
            hg_ss_note($messages, 'UNIQUE organization_id + group_id creado.');
        }

        if (!hg_ss_has_fk($link, 'bridge_organizations_groups', ['organization_id'], 'dim_organizations', ['id'])) {
            hg_ss_exec($link, "ALTER TABLE `bridge_organizations_groups` ADD CONSTRAINT `fk_bog_organization` FOREIGN KEY (`organization_id`) REFERENCES `dim_organizations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");
            hg_ss_note($messages, 'FK bridge_organizations_groups -> dim_organizations creada.');
        }

        if (!hg_ss_has_fk($link, 'bridge_organizations_groups', ['group_id'], 'dim_groups', ['id'])) {
            hg_ss_exec($link, "ALTER TABLE `bridge_organizations_groups` ADD CONSTRAINT `fk_bog_group` FOREIGN KEY (`group_id`) REFERENCES `dim_groups` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");
            hg_ss_note($messages, 'FK bridge_organizations_groups -> dim_groups creada.');
        }

        return ['messages' => $messages];
    }
}

if (!function_exists('hg_ss_analyze_bridge_character_relations')) {
    function hg_ss_analyze_bridge_character_relations(mysqli $link): array
    {
        $orphanSources = hg_ss_scalar($link, "
            SELECT COUNT(*)
            FROM bridge_characters_relations r
            LEFT JOIN fact_characters c ON c.id = r.source_id
            WHERE c.id IS NULL
        ");
        $orphanTargets = hg_ss_scalar($link, "
            SELECT COUNT(*)
            FROM bridge_characters_relations r
            LEFT JOIN fact_characters c ON c.id = r.target_id
            WHERE c.id IS NULL
        ");
        $duplicates = hg_ss_scalar($link, "
            SELECT COALESCE(SUM(t.cnt - 1), 0)
            FROM (
                SELECT COUNT(*) AS cnt
                FROM bridge_characters_relations
                GROUP BY source_id, target_id, relation_type, tag, importance, description, arrows
                HAVING COUNT(*) > 1
            ) t
        ");
        $hasSourceIndex = hg_ss_has_index($link, 'bridge_characters_relations', ['source_id']);
        $hasTargetIndex = hg_ss_has_index($link, 'bridge_characters_relations', ['target_id']);
        $hasSourceFk = hg_ss_has_fk($link, 'bridge_characters_relations', ['source_id'], 'fact_characters', ['id']);
        $hasTargetFk = hg_ss_has_fk($link, 'bridge_characters_relations', ['target_id'], 'fact_characters', ['id']);

        return [
            'issue_count' => $orphanSources + $orphanTargets + $duplicates + ($hasSourceIndex ? 0 : 1) + ($hasTargetIndex ? 0 : 1) + ($hasSourceFk ? 0 : 1) + ($hasTargetFk ? 0 : 1),
            'status' => ($orphanSources === 0 && $orphanTargets === 0 && $duplicates === 0 && $hasSourceIndex && $hasTargetIndex && $hasSourceFk && $hasTargetFk) ? 'clean' : 'pending',
            'details' => [
                $orphanSources . ' relaciones con source_id huerfano',
                $orphanTargets . ' relaciones con target_id huerfano',
                $duplicates . ' duplicados exactos',
            ],
        ];
    }
}

if (!function_exists('hg_ss_apply_bridge_character_relations')) {
    function hg_ss_apply_bridge_character_relations(mysqli $link): array
    {
        $messages = [];

        $orphans = hg_ss_exec($link, "
            DELETE r
            FROM `bridge_characters_relations` r
            LEFT JOIN `fact_characters` s ON s.id = r.source_id
            LEFT JOIN `fact_characters` t ON t.id = r.target_id
            WHERE s.id IS NULL OR t.id IS NULL
        ");
        hg_ss_note($messages, 'Relaciones huerfanas eliminadas: ' . $orphans . '.');

        $duplicates = hg_ss_delete_exact_duplicates($link, 'bridge_characters_relations', "t1.source_id = t2.source_id AND t1.target_id = t2.target_id AND t1.relation_type = t2.relation_type AND t1.tag <=> t2.tag AND t1.importance <=> t2.importance AND t1.description <=> t2.description AND t1.arrows = t2.arrows");
        hg_ss_note($messages, 'Duplicados exactos eliminados en bridge_characters_relations: ' . $duplicates . '.');

        if (!hg_ss_has_index($link, 'bridge_characters_relations', ['source_id'])) {
            hg_ss_exec($link, "ALTER TABLE `bridge_characters_relations` ADD KEY `idx_bcr_source_id` (`source_id`)");
            hg_ss_note($messages, 'Indice source_id creado en bridge_characters_relations.');
        }

        if (!hg_ss_has_index($link, 'bridge_characters_relations', ['target_id'])) {
            hg_ss_exec($link, "ALTER TABLE `bridge_characters_relations` ADD KEY `idx_bcr_target_id` (`target_id`)");
            hg_ss_note($messages, 'Indice target_id creado en bridge_characters_relations.');
        }

        if (!hg_ss_has_fk($link, 'bridge_characters_relations', ['source_id'], 'fact_characters', ['id'])) {
            hg_ss_exec($link, "ALTER TABLE `bridge_characters_relations` ADD CONSTRAINT `fk_bcr_source` FOREIGN KEY (`source_id`) REFERENCES `fact_characters` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");
            hg_ss_note($messages, 'FK bridge_characters_relations -> fact_characters(source) creada.');
        }

        if (!hg_ss_has_fk($link, 'bridge_characters_relations', ['target_id'], 'fact_characters', ['id'])) {
            hg_ss_exec($link, "ALTER TABLE `bridge_characters_relations` ADD CONSTRAINT `fk_bcr_target` FOREIGN KEY (`target_id`) REFERENCES `fact_characters` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");
            hg_ss_note($messages, 'FK bridge_characters_relations -> fact_characters(target) creada.');
        }

        return ['messages' => $messages];
    }
}

if (!function_exists('hg_ss_analyze_pretty_ids')) {
    function hg_ss_analyze_pretty_ids(mysqli $link): array
    {
        $totals = [
            'missing' => 0,
            'invalid' => 0,
            'non_canonical' => 0,
            'tables' => [],
        ];

        foreach (hg_ss_pretty_configs() as $config) {
            $table = (string)$config['table'];
            if (!hg_ss_table_exists($link, $table) || !hg_ss_column_exists($link, $table, 'pretty_id')) {
                continue;
            }

            $rows = hg_ss_pretty_rows($link, $table, (string)$config['expr']);
            $missing = 0;
            $invalid = 0;
            $nonCanonical = 0;

            foreach ($rows as $row) {
                $pretty = (string)$row['pretty_id'];
                $expected = hg_pretty_expected_slug($table, (string)$row['source'], (int)$row['id']);

                if ($pretty === '') {
                    $missing++;
                    continue;
                }

                if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $pretty)) {
                    $invalid++;
                }

                if ($expected !== '' && $pretty !== $expected) {
                    $nonCanonical++;
                }
            }

            if ($missing > 0 || $invalid > 0 || $nonCanonical > 0) {
                $totals['tables'][] = [
                    'label' => (string)$config['label'],
                    'table' => $table,
                    'missing' => $missing,
                    'invalid' => $invalid,
                    'non_canonical' => $nonCanonical,
                ];
            }

            $totals['missing'] += $missing;
            $totals['invalid'] += $invalid;
            $totals['non_canonical'] += $nonCanonical;
        }

        $issueCount = (int)$totals['missing'] + (int)$totals['invalid'] + (int)$totals['non_canonical'];
        $details = [
            $totals['missing'] . ' pretty_id vacios',
            $totals['invalid'] . ' pretty_id con formato roto',
            $totals['non_canonical'] . ' pretty_id fuera de la politica actual',
        ];

        foreach (array_slice((array)$totals['tables'], 0, 6) as $row) {
            $details[] = $row['label'] . ': ' . $row['non_canonical'] . ' desalineados, ' . $row['invalid'] . ' rotos, ' . $row['missing'] . ' vacios';
        }

        return [
            'issue_count' => $issueCount,
            'status' => $issueCount === 0 ? 'clean' : 'pending',
            'details' => $details,
            'tables' => (array)$totals['tables'],
        ];
    }
}

if (!function_exists('hg_ss_apply_pretty_ids')) {
    function hg_ss_apply_pretty_ids(mysqli $link): array
    {
        $messages = [];
        $updated = 0;
        $aliased = 0;
        $skipped = 0;

        if (!hg_ss_table_exists($link, 'fact_pretty_id_aliases')) {
            hg_ss_exec($link, hg_ss_alias_table_sql());
            hg_ss_note($messages, 'Tabla fact_pretty_id_aliases creada.');
        }

        foreach (hg_ss_pretty_configs() as $config) {
            $table = (string)$config['table'];
            if (!hg_ss_table_exists($link, $table) || !hg_ss_column_exists($link, $table, 'pretty_id')) {
                continue;
            }

            $rows = hg_ss_pretty_rows($link, $table, (string)$config['expr']);
            $tableUpdated = 0;

            foreach ($rows as $row) {
                $id = (int)$row['id'];
                $source = (string)$row['source'];
                $current = trim((string)$row['pretty_id']);
                $expected = hg_pretty_expected_slug($table, $source, $id);
                $target = hg_ss_next_pretty_id($link, $table, $expected, $id);

                if ($target === '' || $current === $target) {
                    continue;
                }

                if ($current !== '') {
                    if (hg_ss_alias_exists($link, $table, $current, $id)) {
                        $skipped++;
                        continue;
                    }
                    if (!hg_ss_upsert_alias($link, $table, $id, $current, $target)) {
                        $skipped++;
                        continue;
                    }
                    $aliased++;
                }

                if (!hg_ss_update_pretty($link, $table, $id, $target)) {
                    $skipped++;
                    continue;
                }

                $updated++;
                $tableUpdated++;
            }

            if ($tableUpdated > 0) {
                hg_ss_note($messages, (string)$config['label'] . ': ' . $tableUpdated . ' pretty_id ajustados.');
            }
        }

        hg_ss_note($messages, 'Pretty IDs actualizados: ' . $updated . '.');
        hg_ss_note($messages, 'Aliases registrados: ' . $aliased . '.');
        if ($skipped > 0) {
            hg_ss_note($messages, 'Filas omitidas por conflicto o error: ' . $skipped . '.');
        }

        return ['messages' => $messages];
    }
}

if (!function_exists('hg_ss_analyze_csp_posts_dates')) {
    function hg_ss_analyze_csp_posts_dates(mysqli $link): array
    {
        if (!hg_ss_table_exists($link, 'fact_csp_posts')) {
            return [
                'issue_count' => 0,
                'status' => 'clean',
                'details' => ['fact_csp_posts no existe en esta instancia.'],
            ];
        }

        $hasDate = hg_ss_column_exists($link, 'fact_csp_posts', 'posted_date');
        $hasTime = hg_ss_column_exists($link, 'fact_csp_posts', 'posted_time');
        $totalWithRaw = hg_ss_scalar($link, "SELECT COUNT(*) FROM fact_csp_posts WHERE posted_at IS NOT NULL AND TRIM(posted_at) <> ''");
        $parseable = hg_ss_scalar($link, "SELECT COUNT(*) FROM fact_csp_posts WHERE posted_at REGEXP '^[0-9]{2}:[0-9]{2}:[0-9]{2}, [0-9]{2}-[0-9]{2}-[0-9]{4}$'");
        $unparseable = max(0, $totalWithRaw - $parseable);
        $normalized = ($hasDate && $hasTime)
            ? hg_ss_scalar($link, "SELECT COUNT(*) FROM fact_csp_posts WHERE posted_date IS NOT NULL OR posted_time IS NOT NULL")
            : 0;

        return [
            'issue_count' => ($hasDate ? 0 : 1) + ($hasTime ? 0 : 1) + $unparseable + max(0, $parseable - $normalized),
            'status' => ($hasDate && $hasTime && $unparseable === 0 && $normalized >= $parseable) ? 'clean' : 'pending',
            'details' => [
                $parseable . ' filas parseables desde posted_at',
                $unparseable . ' filas no parseables',
                $normalized . ' filas ya normalizadas',
            ],
        ];
    }
}

if (!function_exists('hg_ss_apply_csp_posts_dates')) {
    function hg_ss_apply_csp_posts_dates(mysqli $link): array
    {
        $messages = [];

        if (!hg_ss_column_exists($link, 'fact_csp_posts', 'posted_date')) {
            hg_ss_exec($link, "ALTER TABLE `fact_csp_posts` ADD COLUMN `posted_date` date DEFAULT NULL AFTER `posted_at`");
            hg_ss_note($messages, 'Columna posted_date creada.');
        }

        if (!hg_ss_column_exists($link, 'fact_csp_posts', 'posted_time')) {
            hg_ss_exec($link, "ALTER TABLE `fact_csp_posts` ADD COLUMN `posted_time` time DEFAULT NULL AFTER `posted_date`");
            hg_ss_note($messages, 'Columna posted_time creada.');
        }

        $updated = hg_ss_exec($link, "
            UPDATE `fact_csp_posts`
            SET
                `posted_time` = STR_TO_DATE(SUBSTRING_INDEX(`posted_at`, ', ', 1), '%H:%i:%s'),
                `posted_date` = STR_TO_DATE(SUBSTRING_INDEX(`posted_at`, ', ', -1), '%d-%m-%Y')
            WHERE `posted_at` REGEXP '^[0-9]{2}:[0-9]{2}:[0-9]{2}, [0-9]{2}-[0-9]{2}-[0-9]{4}$'
              AND (
                    `posted_date` IS NULL
                 OR `posted_time` IS NULL
              )
        ");
        hg_ss_note($messages, 'Filas normalizadas en fact_csp_posts: ' . $updated . '.');

        if (!hg_ss_has_index($link, 'fact_csp_posts', ['posted_date'])) {
            hg_ss_exec($link, "ALTER TABLE `fact_csp_posts` ADD KEY `idx_fact_csp_posts_posted_date` (`posted_date`)");
            hg_ss_note($messages, 'Indice posted_date creado.');
        }

        $unparseable = hg_ss_scalar($link, "
            SELECT COUNT(*)
            FROM fact_csp_posts
            WHERE posted_at IS NOT NULL
              AND TRIM(posted_at) <> ''
              AND posted_at NOT REGEXP '^[0-9]{2}:[0-9]{2}:[0-9]{2}, [0-9]{2}-[0-9]{2}-[0-9]{4}$'
        ");
        if ($unparseable > 0) {
            hg_ss_note($messages, 'Quedan ' . $unparseable . ' filas de CSP con fecha legacy no parseable.');
        }

        return ['messages' => $messages];
    }
}

if (!function_exists('hg_ss_action_definitions')) {
    function hg_ss_action_definitions(): array
    {
        return [
            'chapters_zero_dates' => [
                'title' => 'Normalizar fechas imposibles en capitulos',
                'summary' => 'Convierte 0000-00-00 en NULL dentro de dim_chapters.played_date.',
                'kind' => 'sentinelas',
                'analyze' => 'hg_ss_analyze_chapters_zero_dates',
                'apply' => 'hg_ss_apply_chapters_zero_dates',
            ],
            'character_comments' => [
                'title' => 'Blindar comentarios de personaje',
                'summary' => 'Limpia fechas invalidas, borra comentarios huerfanos y crea la FK real a fact_characters.',
                'kind' => 'integridad',
                'analyze' => 'hg_ss_analyze_character_comments',
                'apply' => 'hg_ss_apply_character_comments',
            ],
            'groups_totem' => [
                'title' => 'Terminar migracion de totems en grupos',
                'summary' => 'Sustituye 0 por NULL, repara huellas legacy y anade FKs a cronica y totem cuando ya son seguras.',
                'kind' => 'integridad',
                'analyze' => 'hg_ss_analyze_groups_totem',
                'apply' => 'hg_ss_apply_groups_totem',
            ],
            'organizations_totem' => [
                'title' => 'Terminar migracion de totems en organizaciones',
                'summary' => 'Sustituye 0 por NULL, limpia referencias rotas y crea la FK opcional a dim_totems.',
                'kind' => 'integridad',
                'analyze' => 'hg_ss_analyze_organizations_totem',
                'apply' => 'hg_ss_apply_organizations_totem',
            ],
            'bridge_chapters_characters' => [
                'title' => 'Endurecer puente capitulo/personaje',
                'summary' => 'Elimina huerfanos y duplicados exactos en bridge_chapters_characters, y anade FKs reales.',
                'kind' => 'puentes',
                'analyze' => 'hg_ss_analyze_bridge_chapters_characters',
                'apply' => 'hg_ss_apply_bridge_chapters_characters',
            ],
            'bridge_organizations_groups' => [
                'title' => 'Endurecer puente organizacion/grupo',
                'summary' => 'Limpia huerfanos, deduplica, restaura la UNIQUE natural y anade FKs reales.',
                'kind' => 'puentes',
                'analyze' => 'hg_ss_analyze_bridge_organizations_groups',
                'apply' => 'hg_ss_apply_bridge_organizations_groups',
            ],
            'bridge_character_relations' => [
                'title' => 'Blindar relaciones entre personajes',
                'summary' => 'Limpia relaciones huerfanas, elimina duplicados exactos y anade FKs a source_id y target_id.',
                'kind' => 'puentes',
                'analyze' => 'hg_ss_analyze_bridge_character_relations',
                'apply' => 'hg_ss_apply_bridge_character_relations',
            ],
            'pretty_ids' => [
                'title' => 'Sanear pretty_id sin romper URLs',
                'summary' => 'Alinea slugs con la politica actual y guarda aliases en fact_pretty_id_aliases para redirigir los antiguos.',
                'kind' => 'slugs',
                'analyze' => 'hg_ss_analyze_pretty_ids',
                'apply' => 'hg_ss_apply_pretty_ids',
            ],
            'csp_posts_dates' => [
                'title' => 'Normalizar fechas legacy del tablon CSP',
                'summary' => 'Mantiene posted_at raw, pero anade posted_date y posted_time parseados para dejar de depender de varchar(50).',
                'kind' => 'legacy',
                'analyze' => 'hg_ss_analyze_csp_posts_dates',
                'apply' => 'hg_ss_apply_csp_posts_dates',
            ],
        ];
    }
}

if (!function_exists('hg_ss_collect_analysis')) {
    function hg_ss_collect_analysis(mysqli $link): array
    {
        $actions = [];
        $totals = ['pending' => 0, 'clean' => 0, 'issues' => 0];

        foreach (hg_ss_action_definitions() as $id => $def) {
            $analysis = call_user_func($def['analyze'], $link);
            $status = (string)($analysis['status'] ?? 'pending');

            if ($status === 'clean') {
                $totals['clean']++;
            } else {
                $totals['pending']++;
            }
            $totals['issues'] += (int)($analysis['issue_count'] ?? 0);

            $actions[$id] = array_merge($def, $analysis, ['id' => $id]);
        }

        return [
            'actions' => $actions,
            'totals' => $totals,
        ];
    }
}

if (!function_exists('hg_ss_manual_notes')) {
    function hg_ss_manual_notes(): array
    {
        return [
            'No toca bridge_soundtrack_links ni bridge_characters_powers porque ya estan domesticados y el riesgo de romper uso real no compensa.',
            'No convierte taxonomias editoriales de texto libre en catalogos. Eso ya exige revisar formularios, seeds y UI admin.',
            'No redisenya fact_discipline_powers.disc ni otras columnas historicas ambiguas. Eso pide una migracion funcional, no solo SQL.',
            'Los pretty_id de jugadores, cronicas y realidades se dejan fuera porque ahi hay mas componente editorial y no conviene regenerarlos en bruto.',
        ];
    }
}

if (!function_exists('hg_ss_render_page')) {
    function hg_ss_render_page(string $csrf, array $flash, array $analysis, array $execution): void
    {
        $totals = (array)($analysis['totals'] ?? []);
        $actions = (array)($analysis['actions'] ?? []);

        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }
        ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Saneador de esquema | Heaven's Gate</title>
  <link rel="stylesheet" href="/assets/css/hg-admin.css">
  <style>
    :root{--bg:#071018;--panel:#0d1822;--panel-2:#112232;--line:rgba(255,255,255,.12);--text:#f5efe3;--soft:#c7c0b3}
    *{box-sizing:border-box}
    body{margin:0;background:linear-gradient(180deg,#05090d 0%,#08131c 100%);color:var(--text);font-family:Verdana,Arial,sans-serif}
    .hg-ss-wrap{max-width:1280px;margin:0 auto;padding:24px 18px 40px}
    .hg-ss-header{display:flex;gap:14px;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;padding:18px 20px;border:1px solid var(--line);background:linear-gradient(180deg,#0d1a26 0%,#0a131b 100%);box-shadow:0 18px 42px rgba(0,0,0,.28)}
    .hg-ss-header h1{margin:0 0 8px;font-size:1.9rem}.hg-ss-header p{margin:0;color:var(--soft);line-height:1.6}
    .hg-ss-header-actions{display:flex;gap:10px;flex-wrap:wrap}.hg-ss-header-actions a,.hg-ss-btn{display:inline-flex;align-items:center;justify-content:center;padding:10px 14px;border:1px solid rgba(255,255,255,.16);background:#16304a;color:#f7f2e9;text-decoration:none;cursor:pointer}
    .hg-ss-btn-primary{background:#295d2d}.hg-ss-summary{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin:18px 0}
    .hg-ss-pill{padding:14px 16px;border:1px solid var(--line);background:var(--panel)}.hg-ss-pill strong{display:block;font-size:1.45rem}
    .hg-ss-grid{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:18px}.hg-ss-main,.hg-ss-side{display:flex;flex-direction:column;gap:18px}
    .hg-ss-card{border:1px solid var(--line);background:var(--panel);padding:18px}.hg-ss-card h2,.hg-ss-card h3{margin:0 0 10px}.hg-ss-card p{margin:0;color:var(--soft);line-height:1.6}
    .hg-ss-card ul{margin:10px 0 0;padding-left:18px}.hg-ss-card li{margin:6px 0;color:#e3dccf}
    .hg-ss-flash{margin:0 0 14px;padding:14px 16px;border:1px solid var(--line)}.hg-ss-flash.ok{background:rgba(31,122,77,.18)}.hg-ss-flash.err{background:rgba(142,45,45,.18)}.hg-ss-flash.info{background:rgba(53,94,154,.16)}
    .hg-ss-actions{display:flex;flex-direction:column;gap:14px}.hg-ss-action{border:1px solid var(--line);background:linear-gradient(180deg,#0f1c29 0%,#0b141c 100%);padding:16px}
    .hg-ss-action-head{display:flex;gap:12px;align-items:flex-start}.hg-ss-action-check{margin-top:4px;transform:scale(1.2)}.hg-ss-action-title{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
    .hg-ss-action-title h3{margin:0;font-size:1.1rem}.hg-ss-badge{display:inline-flex;align-items:center;padding:3px 8px;border:1px solid rgba(255,255,255,.18);font-size:.82rem;text-transform:uppercase;letter-spacing:.04em}
    .hg-ss-badge.pending{background:rgba(156,122,24,.18);color:#f3de8c}.hg-ss-badge.clean{background:rgba(31,122,77,.18);color:#bfe5c9}.hg-ss-badge.kind{background:rgba(53,94,154,.18);color:#cfe1ff}
    .hg-ss-action-meta{margin-top:10px;display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px}.hg-ss-mini{padding:10px 12px;border:1px solid rgba(255,255,255,.08);background:var(--panel-2)}.hg-ss-mini strong{display:block}
    .hg-ss-run table,.hg-ss-table-list table{width:100%;border-collapse:collapse}.hg-ss-run th,.hg-ss-run td,.hg-ss-table-list td,.hg-ss-table-list th{padding:10px 12px;border-bottom:1px solid rgba(255,255,255,.08);text-align:left;vertical-align:top}
    .hg-ss-notes{display:flex;flex-direction:column;gap:10px}.hg-ss-note{padding:12px 14px;border:1px solid rgba(255,255,255,.08);background:var(--panel-2);color:#ddd5c8;line-height:1.6}.hg-ss-help{font-size:.92rem;color:var(--soft)}.hg-ss-footer{margin-top:18px;color:#bfb7aa;font-size:.92rem;line-height:1.6}
    @media (max-width: 980px){.hg-ss-grid{grid-template-columns:1fr}}
  </style>
</head>
<body>
  <div class="hg-ss-wrap">
    <section class="hg-ss-header">
      <div>
        <h1>Saneador de esquema</h1>
        <p>Herramienta admin para rematar la deuda estructural sin romper la web: limpia sentinelas, endurece puentes maduros y sanea <code>pretty_id</code> con aliases y redirecciones.</p>
      </div>
      <div class="hg-ss-header-actions">
        <a href="/talim">Volver al panel</a>
        <a href="/tools/schema-sanitizer">Recargar auditoria</a>
      </div>
    </section>
    <?php foreach ($flash as $item): ?><div class="hg-ss-flash <?= hg_ss_h((string)($item['type'] ?? 'info')) ?>"><?= hg_ss_h((string)($item['msg'] ?? '')) ?></div><?php endforeach; ?>
    <section class="hg-ss-summary">
      <div class="hg-ss-pill"><strong><?= (int)($totals['pending'] ?? 0) ?></strong>bloques pendientes</div>
      <div class="hg-ss-pill"><strong><?= (int)($totals['clean'] ?? 0) ?></strong>bloques ya limpios</div>
      <div class="hg-ss-pill"><strong><?= (int)($totals['issues'] ?? 0) ?></strong>hallazgos totales</div>
    </section>
    <div class="hg-ss-grid">
      <main class="hg-ss-main">
        <section class="hg-ss-card">
          <h2>Acciones automaticas</h2>
          <p>Las acciones marcadas estan pensadas para ser idempotentes. Si una ya quedo limpia, la herramienta no deberia rehacer trabajo ni romper nada.</p>
          <form method="post">
            <input type="hidden" name="csrf" value="<?= hg_ss_h($csrf) ?>">
            <input type="hidden" name="schema_action" value="apply">
            <div class="hg-ss-actions">
              <?php foreach ($actions as $action): $isPending = (string)($action['status'] ?? 'pending') !== 'clean'; ?>
                <article class="hg-ss-action">
                  <div class="hg-ss-action-head">
                    <input class="hg-ss-action-check" type="checkbox" name="actions[]" value="<?= hg_ss_h((string)$action['id']) ?>" <?= $isPending ? 'checked' : '' ?>>
                    <div class="hg-ss-action-body">
                      <div class="hg-ss-action-title">
                        <h3><?= hg_ss_h((string)($action['title'] ?? '')) ?></h3>
                        <span class="hg-ss-badge <?= $isPending ? 'pending' : 'clean' ?>"><?= $isPending ? 'pendiente' : 'limpio' ?></span>
                        <span class="hg-ss-badge kind"><?= hg_ss_h((string)($action['kind'] ?? '')) ?></span>
                      </div>
                      <p><?= hg_ss_h((string)($action['summary'] ?? '')) ?></p>
                      <div class="hg-ss-action-meta">
                        <div class="hg-ss-mini"><strong>Hallazgos</strong><?= (int)($action['issue_count'] ?? 0) ?></div>
                        <div class="hg-ss-mini"><strong>Estado</strong><?= $isPending ? 'Requiere saneado' : 'Alineado' ?></div>
                      </div>
                      <?php if (!empty($action['details'])): ?><ul><?php foreach ((array)$action['details'] as $detail): ?><li><?= hg_ss_h((string)$detail) ?></li><?php endforeach; ?></ul><?php endif; ?>
                      <?php if ((string)$action['id'] === 'pretty_ids' && !empty($action['tables'])): ?>
                        <div class="hg-ss-table-list">
                          <table>
                            <thead><tr><th>Tabla</th><th>Desalineados</th><th>Rotos</th><th>Vacios</th></tr></thead>
                            <tbody>
                              <?php foreach (array_slice((array)$action['tables'], 0, 10) as $row): ?>
                                <tr><td><?= hg_ss_h((string)($row['label'] ?? $row['table'] ?? '')) ?></td><td><?= (int)($row['non_canonical'] ?? 0) ?></td><td><?= (int)($row['invalid'] ?? 0) ?></td><td><?= (int)($row['missing'] ?? 0) ?></td></tr>
                              <?php endforeach; ?>
                            </tbody>
                          </table>
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
            <div class="hg-ss-header-actions" style="margin-top:16px">
              <button class="hg-ss-btn" type="button" id="hg-ss-select-all">Seleccionar todo</button>
              <button class="hg-ss-btn" type="button" id="hg-ss-select-pending">Solo pendientes</button>
              <button class="hg-ss-btn hg-ss-btn-primary" type="submit" onclick="return confirm('Esto aplicara las acciones marcadas directamente sobre la base de datos real. Continuar?')">Aplicar acciones marcadas</button>
            </div>
          </form>
        </section>
        <?php if (!empty($execution)): ?>
          <section class="hg-ss-card hg-ss-run">
            <h2>Ultima ejecucion</h2>
            <table>
              <thead><tr><th>Accion</th><th>Resultado</th><th>Detalle</th></tr></thead>
              <tbody><?php foreach ($execution as $row): ?><tr><td><?= hg_ss_h((string)($row['title'] ?? '')) ?></td><td><?= hg_ss_h((string)($row['status'] ?? '')) ?></td><td><?= hg_ss_h(implode(' | ', (array)($row['messages'] ?? []))) ?></td></tr><?php endforeach; ?></tbody>
            </table>
          </section>
        <?php endif; ?>
      </main>
      <aside class="hg-ss-side">
        <section class="hg-ss-card">
          <h2>Que arregla</h2>
          <div class="hg-ss-notes">
            <div class="hg-ss-note">Sentinelas falsos como <code>0</code> y <code>0000-00-00</code>.</div>
            <div class="hg-ss-note">Puentes maduros con huerfanos o sin FK real.</div>
            <div class="hg-ss-note">Slugs legacy con mojibake o transliteracion antigua, pero sin romper URLs antiguas.</div>
            <div class="hg-ss-note">Fechas legacy de <code>fact_csp_posts</code> manteniendo el raw original.</div>
          </div>
        </section>
        <section class="hg-ss-card">
          <h2>Fuera de alcance</h2>
          <div class="hg-ss-notes"><?php foreach (hg_ss_manual_notes() as $note): ?><div class="hg-ss-note"><?= hg_ss_h($note) ?></div><?php endforeach; ?></div>
        </section>
        <section class="hg-ss-card">
          <h2>Uso recomendado</h2>
          <div class="hg-ss-help">1. Lanza primero solo los bloques de sentinelas e integridad.<br>2. Revisa que el admin y las paginas publicas siguen respondiendo bien.<br>3. Despues ejecuta el bloque de <code>pretty_id</code>, que es el mas visible a nivel editorial.</div>
          <div class="hg-ss-footer">Ruta directa: <code>/tools/schema-sanitizer</code></div>
        </section>
      </aside>
    </div>
  </div>
  <script>
    (function () {
      const all = Array.from(document.querySelectorAll('input[name="actions[]"]'));
      const selectAll = document.getElementById('hg-ss-select-all');
      const selectPending = document.getElementById('hg-ss-select-pending');
      if (selectAll) { selectAll.addEventListener('click', function () { all.forEach(function (item) { item.checked = true; }); }); }
      if (selectPending) { selectPending.addEventListener('click', function () { all.forEach(function (item) { const article = item.closest('.hg-ss-action'); const pending = article && article.querySelector('.hg-ss-badge.pending'); item.checked = !!pending; }); }); }
    })();
  </script>
</body>
</html>
        <?php
        exit;
    }
}

if (!(isset($link) && $link instanceof mysqli)) {
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=UTF-8');
        http_response_code(500);
    }
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Saneador de esquema</title></head><body style="font-family:Verdana,Arial,sans-serif;background:#071018;color:#f5efe3;padding:24px"><h1>Saneador de esquema</h1><p>No se pudo conectar a la base de datos.</p><p><a href="/talim" style="color:#9fc7ff">Volver al panel</a></p></body></html>';
    return;
}

if (method_exists($link, 'set_charset')) {
    $link->set_charset('utf8mb4');
} else {
    mysqli_set_charset($link, 'utf8mb4');
}

if (function_exists('hg_admin_is_authenticated') && !hg_admin_is_authenticated()) {
    if (function_exists('hg_admin_redirect')) {
        hg_admin_redirect('/talim');
    }
    exit;
}

$csrfKey = 'csrf_admin_schema_sanitizer';
$csrf = function_exists('hg_admin_ensure_csrf_token')
    ? hg_admin_ensure_csrf_token($csrfKey)
    : (string)($_SESSION[$csrfKey] ?? '');

$flash = [];
$execution = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['schema_action'] ?? '') === 'apply') {
    $token = function_exists('hg_admin_extract_csrf_token')
        ? hg_admin_extract_csrf_token()
        : (string)($_POST['csrf'] ?? '');

    $csrfOk = function_exists('hg_admin_csrf_valid')
        ? hg_admin_csrf_valid($token, $csrfKey)
        : ($token !== '' && isset($_SESSION[$csrfKey]) && hash_equals((string)$_SESSION[$csrfKey], $token));

    if (!$csrfOk) {
        $flash[] = ['type' => 'err', 'msg' => 'CSRF invalido. Recarga la pagina antes de reintentar.'];
    } else {
        $defs = hg_ss_action_definitions();
        $selected = array_values(array_filter((array)($_POST['actions'] ?? []), static function ($value) use ($defs): bool {
            return isset($defs[(string)$value]);
        }));

        if (empty($selected)) {
            $flash[] = ['type' => 'info', 'msg' => 'No se ha marcado ninguna accion.'];
        } else {
            foreach ($selected as $actionId) {
                $def = $defs[(string)$actionId];
                try {
                    $result = call_user_func($def['apply'], $link);
                    $execution[] = ['title' => (string)$def['title'], 'status' => 'OK', 'messages' => (array)($result['messages'] ?? [])];
                } catch (Throwable $e) {
                    $execution[] = ['title' => (string)$def['title'], 'status' => 'ERROR', 'messages' => [$e->getMessage()]];
                }
            }

            $hasErrors = false;
            foreach ($execution as $row) {
                if (($row['status'] ?? '') === 'ERROR') {
                    $hasErrors = true;
                    break;
                }
            }
            $flash[] = ['type' => $hasErrors ? 'err' : 'ok', 'msg' => $hasErrors ? 'Se han aplicado cambios, pero alguna accion ha fallado. Revisa la tabla de ultima ejecucion.' : 'Saneado completado. Revisa la auditoria actualizada debajo.'];
        }
    }
}

$analysis = hg_ss_collect_analysis($link);
hg_ss_render_page($csrf, $flash, $analysis, $execution);
