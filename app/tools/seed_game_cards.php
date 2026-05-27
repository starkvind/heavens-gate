<?php

require_once __DIR__ . '/../modules/game_cards/game_cards_catalog.php';

function hg_gc_seed_exec_schema(mysqli $link): void
{
    foreach (hg_gc_schema_sql() as $sql) {
        if (!$link->query($sql)) {
            throw new RuntimeException($link->error);
        }
    }
}

function hg_gc_seed_reset_catalog(mysqli $link): int
{
    if (!hg_gc_table_exists($link, 'fact_game_card_collection')) {
        return 0;
    }

    if (!$link->query("DELETE FROM fact_game_card_collection")) {
        throw new RuntimeException($link->error);
    }
    $deleted = max(0, $link->affected_rows);

    if (!$link->query("ALTER TABLE fact_game_card_collection AUTO_INCREMENT = 1")) {
        throw new RuntimeException($link->error);
    }

    return $deleted;
}

function hg_gc_seed_insert_card(mysqli $link, array $card): bool
{
    $rarity = (string)($card['card_rarity'] ?? 'common');
    if (!hg_gc_valid_rarity($rarity)) {
        $rarity = 'common';
    }
    [$min, $max] = hg_gc_apply_rarity_range($rarity);
    $hpMin = isset($card['hp_min']) ? (int)$card['hp_min'] : $min;
    $hpMax = isset($card['hp_max']) ? (int)$card['hp_max'] : $max;
    if ($hpMin < 0) { $hpMin = 0; }
    if ($hpMax < $hpMin) { $hpMax = $hpMin; }

    $sourceType = (string)$card['source_type'];
    $sourceTable = (string)$card['source_table'];
    if (!hg_gc_allowed_source($sourceType, $sourceTable)) {
        return false;
    }

    $sourceId = (int)$card['source_id'];
    $name = trim((string)$card['card_name']);
    if ($name === '') {
        return false;
    }

    $slug = trim((string)($card['card_slug'] ?? ''));
    $text = hg_gc_excerpt((string)($card['card_text'] ?? ''), 260);
    $image = hg_gc_normalize_image_url((string)($card['card_image_url'] ?? ''), $sourceType);

    $sql = "
        INSERT INTO fact_game_card_collection
            (source_type, source_table, source_id, card_name, card_slug, card_text, card_image_url, card_rarity, hp_min, hp_max, atk_min, atk_max, def_min, def_max, is_active)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE
            card_name = VALUES(card_name),
            card_slug = VALUES(card_slug),
            card_text = VALUES(card_text),
            card_image_url = VALUES(card_image_url),
            card_rarity = VALUES(card_rarity),
            hp_min = VALUES(hp_min),
            hp_max = VALUES(hp_max),
            atk_min = VALUES(atk_min),
            atk_max = VALUES(atk_max),
            def_min = VALUES(def_min),
            def_max = VALUES(def_max),
            is_active = 1,
            updated_at = CURRENT_TIMESTAMP
    ";

    $stmt = $link->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException($link->error);
    }

    $stmt->bind_param(
        'ssisssssiiiiii',
        $sourceType,
        $sourceTable,
        $sourceId,
        $name,
        $slug,
        $text,
        $image,
        $rarity,
        $hpMin,
        $hpMax,
        $min,
        $max,
        $min,
        $max
    );
    $ok = $stmt->execute();
    if (!$ok) {
        $err = $stmt->error;
        $stmt->close();
        throw new RuntimeException($err);
    }
    $affected = $stmt->affected_rows;
    $stmt->close();

    return $affected === 1;
}

function hg_gc_level_rarity(int $level): string
{
    if ($level >= 6) {
        return 'mythic';
    }
    if ($level >= 5) {
        return 'legendary';
    }
    if ($level >= 4) {
        return 'epic';
    }
    if ($level >= 3) {
        return 'rare';
    }
    if ($level >= 2) {
        return 'unusual';
    }
    return 'common';
}

function hg_gc_form_rarity(array $row): string
{
    $score = max(0, (int)($row['strength_bonus'] ?? 0))
        + max(0, (int)($row['dexterity_bonus'] ?? 0))
        + max(0, (int)($row['stamina_bonus'] ?? 0));
    if ((int)($row['regeneration'] ?? 0) > 0) {
        $score++;
    }
    if ((int)($row['hpregen'] ?? 0) > 0) {
        $score += min(2, max(1, (int)$row['hpregen']));
    }

    if ($score >= 11) {
        return 'mythic';
    }
    if ($score >= 8) {
        return 'legendary';
    }
    if ($score >= 6) {
        return 'epic';
    }
    if ($score >= 4) {
        return 'rare';
    }
    if ($score >= 2) {
        return 'unusual';
    }
    return 'common';
}

function hg_gc_normalized_key(string $value): string
{
    $value = strtolower(trim($value));
    $value = strtr($value, [
        'á' => 'a',
        'à' => 'a',
        'ä' => 'a',
        'â' => 'a',
        'é' => 'e',
        'è' => 'e',
        'ë' => 'e',
        'ê' => 'e',
        'í' => 'i',
        'ì' => 'i',
        'ï' => 'i',
        'î' => 'i',
        'ó' => 'o',
        'ò' => 'o',
        'ö' => 'o',
        'ô' => 'o',
        'ú' => 'u',
        'ù' => 'u',
        'ü' => 'u',
        'û' => 'u',
        'ñ' => 'n',
    ]);
    return trim((string)preg_replace('/\s+/u', ' ', $value));
}

function hg_gc_character_rank_score(string $rank): int
{
    $rank = hg_gc_normalized_key($rank);
    if ($rank === '' || $rank === '-' || $rank === 'irrelevante' || $rank === 'desconocido') {
        return 0;
    }

    if (preg_match('/^flambert\s*#?\s*(\d+)/', $rank, $m)) {
        $number = (int)$m[1];
        if ($number >= 6) {
            return 4;
        }
        if ($number >= 3) {
            return 3;
        }
        return 1;
    }

    if (preg_match('/^\d+$/', $rank)) {
        $number = (int)$rank;
        if ($number >= 6) {
            return 4;
        }
        if ($number >= 4) {
            return 3;
        }
        if ($number >= 2) {
            return 1;
        }
        return 0;
    }

    $rankScores = [
        'cachorro' => 0,
        'cachorra' => 0,
        'caido' => 0,
        'neonato' => 0,
        'iniciado' => 0,
        'iniciada' => 0,
        'yonki maestro' => 0,
        'dos' => 0,
        'cliath' => 1,
        'fostern' => 2,
        'fostern (postumo)' => 2,
        'adulto' => 2,
        'ancilla' => 2,
        'ancillae' => 2,
        'oviculum' => 2,
        'semental' => 2,
        'singing brook' => 2,
        'adren' => 3,
        'athro' => 3,
        'antiguo' => 4,
        'anciano' => 4,
        'anciana' => 4,
        'principe' => 4,
        'matriarca' => 4,
        'venerable' => 4,
        'tekhmeth' => 4,
        'tekhmet' => 4,
        'neocornix' => 4,
        'jefazo' => 4,
        'trascendente' => 5,
    ];

    if (isset($rankScores[$rank])) {
        return $rankScores[$rank];
    }

    if (strpos($rank, 'ancian') !== false || strpos($rank, 'venerable') !== false) {
        return 4;
    }
    if (strpos($rank, 'fostern') !== false) {
        return 2;
    }

    return 0;
}

function hg_gc_character_relation_score(int $relationCount): int
{
    if ($relationCount >= 25) {
        return 5;
    }
    if ($relationCount >= 15) {
        return 4;
    }
    if ($relationCount >= 9) {
        return 3;
    }
    if ($relationCount >= 5) {
        return 2;
    }
    if ($relationCount >= 2) {
        return 1;
    }
    return 0;
}

function hg_gc_character_kind_score(string $kind): int
{
    $kind = hg_gc_normalized_key($kind);
    if ($kind === 'pj') {
        return 2;
    }
    return 0;
}

function hg_gc_character_rarity(array $row): string
{
    $rankScore = hg_gc_character_rank_score((string)($row['rank'] ?? ''));
    $kindScore = hg_gc_character_kind_score((string)($row['character_kind'] ?? ''));
    $relationScore = hg_gc_character_relation_score((int)($row['relation_count'] ?? 0));
    $score = $rankScore + $kindScore + $relationScore;

    if ($score >= 10) {
        return 'mythic';
    }
    if ($score >= 8) {
        return 'legendary';
    }
    if ($score >= 6) {
        return 'epic';
    }
    if ($score >= 4) {
        return 'rare';
    }
    if ($score >= 2) {
        return 'unusual';
    }
    return 'common';
}

function hg_gc_fetch_rows(mysqli $link, string $sql): array
{
    $rows = [];
    if ($rs = $link->query($sql)) {
        while ($row = $rs->fetch_assoc()) {
            $rows[] = $row;
        }
        $rs->free();
    }
    return $rows;
}

function hg_gc_seed_config_value(mysqli $link, string $configName, string $default = ''): string
{
    if (!hg_gc_table_exists($link, 'dim_web_configuration')) {
        return $default;
    }

    $stmt = $link->prepare("SELECT config_value FROM dim_web_configuration WHERE config_name = ? LIMIT 1");
    if (!$stmt) {
        return $default;
    }

    $stmt->bind_param('s', $configName);
    if (!$stmt->execute()) {
        $stmt->close();
        return $default;
    }

    $stmt->bind_result($value);
    $found = $stmt->fetch();
    $stmt->close();

    return $found ? (string)$value : $default;
}

function hg_gc_seed_int_list(string $value): array
{
    if (!preg_match_all('/\d+/', $value, $matches)) {
        return [];
    }

    $ids = [];
    foreach ($matches[0] as $rawId) {
        $id = (int)$rawId;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }

    return array_values($ids);
}

function hg_gc_seed_id_list_sql(array $ids): string
{
    $cleanIds = [];
    foreach ($ids as $id) {
        $id = (int)$id;
        if ($id > 0) {
            $cleanIds[$id] = $id;
        }
    }

    return implode(',', array_values($cleanIds));
}

function hg_gc_seed_excluded_chronicle_ids(mysqli $link): array
{
    return hg_gc_seed_int_list(hg_gc_seed_config_value($link, 'exclude_chronicles', ''));
}

function hg_gc_seed_deactivate_excluded_chronicles(mysqli $link, array $excludedChronicleIds): int
{
    $excludedChroniclesSql = hg_gc_seed_id_list_sql($excludedChronicleIds);
    if ($excludedChroniclesSql === '' || !hg_gc_table_exists($link, 'fact_game_card_collection')) {
        return 0;
    }

    $updates = [];
    if (
        hg_gc_table_exists($link, 'fact_characters')
        && hg_gc_column_exists($link, 'fact_characters', 'chronicle_id')
    ) {
        $updates[] = "
            UPDATE fact_game_card_collection
            SET is_active = 0, updated_at = CURRENT_TIMESTAMP
            WHERE source_type = 'character'
              AND source_table = 'fact_characters'
              AND source_id IN (
                  SELECT id
                  FROM fact_characters
                  WHERE chronicle_id IN ({$excludedChroniclesSql})
              )
        ";
    }

    if (hg_gc_table_exists($link, 'dim_chronicles')) {
        $updates[] = "
            UPDATE fact_game_card_collection
            SET is_active = 0, updated_at = CURRENT_TIMESTAMP
            WHERE source_type = 'chronicle'
              AND source_table = 'dim_chronicles'
              AND source_id IN ({$excludedChroniclesSql})
        ";
    }

    if (
        hg_gc_table_exists($link, 'dim_seasons')
        && hg_gc_column_exists($link, 'dim_seasons', 'chronicle_id')
    ) {
        $updates[] = "
            UPDATE fact_game_card_collection
            SET is_active = 0, updated_at = CURRENT_TIMESTAMP
            WHERE source_type = 'season'
              AND source_table = 'dim_seasons'
              AND source_id IN (
                  SELECT id
                  FROM dim_seasons
                  WHERE chronicle_id IN ({$excludedChroniclesSql})
              )
        ";
    }

    if (
        hg_gc_table_exists($link, 'dim_chapters')
        && hg_gc_column_exists($link, 'dim_chapters', 'season_id')
        && hg_gc_table_exists($link, 'dim_seasons')
        && hg_gc_column_exists($link, 'dim_seasons', 'chronicle_id')
    ) {
        $updates[] = "
            UPDATE fact_game_card_collection
            SET is_active = 0, updated_at = CURRENT_TIMESTAMP
            WHERE source_type = 'episode'
              AND source_table = 'dim_chapters'
              AND source_id IN (
                  SELECT dc.id
                  FROM dim_chapters dc
                  INNER JOIN dim_seasons ds ON ds.id = dc.season_id
                  WHERE ds.chronicle_id IN ({$excludedChroniclesSql})
              )
        ";
    }

    $deactivated = 0;
    foreach ($updates as $sql) {
        if (!$link->query($sql)) {
            throw new RuntimeException($link->error);
        }
        $deactivated += max(0, $link->affected_rows);
    }

    return $deactivated;
}

function hg_gc_seed_rows(mysqli $link): array
{
    $cards = [];
    $excludedChroniclesSql = hg_gc_seed_id_list_sql(hg_gc_seed_excluded_chronicle_ids($link));

    if (hg_gc_table_exists($link, 'fact_characters')) {
        $relationJoin = '';
        $relationColumns = '0 AS relation_count';
        $chronicleWhere = '';
        if (
            $excludedChroniclesSql !== ''
            && hg_gc_column_exists($link, 'fact_characters', 'chronicle_id')
        ) {
            $chronicleWhere = " AND fc.chronicle_id NOT IN ({$excludedChroniclesSql})";
        }

        if (hg_gc_table_exists($link, 'bridge_characters_relations')) {
            $relationColumns = 'COALESCE(rel.relation_count, 0) AS relation_count';
            $relationJoin = "
                LEFT JOIN (
                    SELECT character_id, COUNT(*) AS relation_count
                    FROM (
                        SELECT source_id AS character_id
                        FROM bridge_characters_relations
                        UNION ALL
                        SELECT target_id AS character_id
                        FROM bridge_characters_relations
                    ) rel_src
                    GROUP BY character_id
                ) rel ON rel.character_id = fc.id
            ";
        }

        foreach (hg_gc_fetch_rows($link, "
            SELECT
                fc.id,
                fc.name AS card_name,
                fc.pretty_id,
                fc.info_text,
                fc.image_url,
                fc.character_kind,
                fc.rank,
                {$relationColumns}
            FROM fact_characters fc
            {$relationJoin}
            WHERE TRIM(COALESCE(fc.name, '')) <> ''
            {$chronicleWhere}
            ORDER BY fc.id ASC
        ") as $row) {
            $cards[] = [
                'source_type' => 'character',
                'source_table' => 'fact_characters',
                'source_id' => (int)$row['id'],
                'card_name' => (string)$row['card_name'],
                'card_slug' => (string)($row['pretty_id'] ?? ''),
                'card_text' => (string)($row['info_text'] ?? ''),
                'card_image_url' => (string)($row['image_url'] ?? ''),
                'card_rarity' => hg_gc_character_rarity($row),
            ];
        }
    }

    if (hg_gc_table_exists($link, 'dim_chapters')) {
        $chronicleWhere = '';
        $hasChapterSeasonJoin = hg_gc_column_exists($link, 'dim_chapters', 'season_id')
            && hg_gc_table_exists($link, 'dim_seasons');
        $chapterSeasonJoin = $hasChapterSeasonJoin
            ? "LEFT JOIN dim_seasons gc_seed_s ON gc_seed_s.id = dc.season_id"
            : '';
        $hasChapterSeasonSort = $hasChapterSeasonJoin
            && hg_gc_column_exists($link, 'dim_seasons', 'sort_order');
        $hasChapterSeasonNumber = $hasChapterSeasonJoin
            && hg_gc_column_exists($link, 'dim_seasons', 'season_number');
        $chapterOrderBy = $hasChapterSeasonSort
            ? 'COALESCE(gc_seed_s.sort_order, 9999) ASC, dc.chapter_number ASC, dc.id ASC'
            : ($hasChapterSeasonNumber
                ? 'COALESCE(gc_seed_s.season_number, 9999) ASC, dc.chapter_number ASC, dc.id ASC'
                : 'dc.chapter_number ASC, dc.id ASC');
        $chapterImageSelect = hg_gc_column_exists($link, 'dim_chapters', 'image_url')
            ? 'dc.image_url'
            : "'' AS image_url";
        if (
            $excludedChroniclesSql !== ''
            && $hasChapterSeasonJoin
            && hg_gc_column_exists($link, 'dim_seasons', 'chronicle_id')
        ) {
            $chronicleWhere = " AND (gc_seed_s.chronicle_id IS NULL OR gc_seed_s.chronicle_id NOT IN ({$excludedChroniclesSql}))";
        }

        foreach (hg_gc_fetch_rows($link, "
            SELECT dc.id, dc.name, dc.pretty_id, dc.synopsis, dc.chapter_number, {$chapterImageSelect}
            FROM dim_chapters dc
            {$chapterSeasonJoin}
            WHERE TRIM(COALESCE(dc.name, '')) <> ''
            {$chronicleWhere}
            ORDER BY {$chapterOrderBy}
        ") as $row) {
            $chapterNumber = (int)($row['chapter_number'] ?? 0);
            $cards[] = [
                'source_type' => 'episode',
                'source_table' => 'dim_chapters',
                'source_id' => (int)$row['id'],
                'card_name' => (string)$row['name'],
                'card_slug' => (string)($row['pretty_id'] ?? ''),
                'card_text' => (string)($row['synopsis'] ?? ''),
                'card_image_url' => (string)($row['image_url'] ?? ''),
                'card_rarity' => ($chapterNumber > 0 && $chapterNumber % 10 === 0) ? 'unusual' : 'common',
            ];
        }
    }

    if (hg_gc_table_exists($link, 'dim_seasons')) {
        $chronicleWhere = '';
        $seasonImageSelect = hg_gc_column_exists($link, 'dim_seasons', 'image_url')
            ? 'image_url'
            : "'' AS image_url";
        $seasonOrderParts = [];
        if (hg_gc_column_exists($link, 'dim_seasons', 'sort_order')) {
            $seasonOrderParts[] = 'COALESCE(sort_order, 999999) ASC';
        }
        if (hg_gc_column_exists($link, 'dim_seasons', 'season_number')) {
            $seasonOrderParts[] = 'season_number ASC';
        }
        $seasonOrderParts[] = 'id ASC';
        $seasonOrderBy = implode(', ', $seasonOrderParts);
        if (
            $excludedChroniclesSql !== ''
            && hg_gc_column_exists($link, 'dim_seasons', 'chronicle_id')
        ) {
            $chronicleWhere = " AND (chronicle_id IS NULL OR chronicle_id NOT IN ({$excludedChroniclesSql}))";
        }

        foreach (hg_gc_fetch_rows($link, "
            SELECT id, name, pretty_id, description, season_kind, finished, {$seasonImageSelect}
            FROM dim_seasons
            WHERE TRIM(COALESCE(name, '')) <> ''
            {$chronicleWhere}
            ORDER BY {$seasonOrderBy}
        ") as $row) {
            $kind = (string)($row['season_kind'] ?? '');
            $cards[] = [
                'source_type' => 'season',
                'source_table' => 'dim_seasons',
                'source_id' => (int)$row['id'],
                'card_name' => (string)$row['name'],
                'card_slug' => (string)($row['pretty_id'] ?? ''),
                'card_text' => (string)($row['description'] ?? ''),
                'card_image_url' => (string)($row['image_url'] ?? ''),
                'card_rarity' => ($kind === 'temporada') ? 'rare' : 'unusual',
            ];
        }
    }

    if (hg_gc_table_exists($link, 'dim_chronicles')) {
        $chronicleWhere = '';
        if ($excludedChroniclesSql !== '') {
            $chronicleWhere = " AND id NOT IN ({$excludedChroniclesSql})";
        }

        foreach (hg_gc_fetch_rows($link, "
            SELECT id, name, pretty_id, description, image_url
            FROM dim_chronicles
            WHERE TRIM(COALESCE(name, '')) <> ''
            {$chronicleWhere}
            ORDER BY id ASC
        ") as $row) {
            $cards[] = [
                'source_type' => 'chronicle',
                'source_table' => 'dim_chronicles',
                'source_id' => (int)$row['id'],
                'card_name' => (string)$row['name'],
                'card_slug' => (string)($row['pretty_id'] ?? ''),
                'card_text' => (string)($row['description'] ?? ''),
                'card_image_url' => (string)($row['image_url'] ?? ''),
                'card_rarity' => 'rare',
            ];
        }
    }

    if (hg_gc_table_exists($link, 'dim_systems')) {
        foreach (hg_gc_fetch_rows($link, "
            SELECT id, name, pretty_id, description, image_url, forms
            FROM dim_systems
            WHERE TRIM(COALESCE(name, '')) <> ''
            ORDER BY sort_order ASC, name ASC, id ASC
        ") as $row) {
            $cards[] = [
                'source_type' => 'system',
                'source_table' => 'dim_systems',
                'source_id' => (int)$row['id'],
                'card_name' => (string)$row['name'],
                'card_slug' => (string)($row['pretty_id'] ?? ''),
                'card_text' => (string)($row['description'] ?? ''),
                'card_image_url' => (string)($row['image_url'] ?? ''),
                'card_rarity' => ((int)($row['forms'] ?? 0) > 0) ? 'rare' : 'unusual',
            ];
        }
    }

    if (hg_gc_table_exists($link, 'dim_tribes')) {
        foreach (hg_gc_fetch_rows($link, "
            SELECT id, name, pretty_id, description, powers, image_url
            FROM dim_tribes
            WHERE TRIM(COALESCE(name, '')) <> ''
            ORDER BY system_id ASC, name ASC, id ASC
        ") as $row) {
            $text = (string)($row['description'] ?? '');
            if (trim((string)($row['powers'] ?? '')) !== '') {
                $text .= "\n\n" . (string)$row['powers'];
            }
            $cards[] = [
                'source_type' => 'tribe',
                'source_table' => 'dim_tribes',
                'source_id' => (int)$row['id'],
                'card_name' => (string)$row['name'],
                'card_slug' => (string)($row['pretty_id'] ?? ''),
                'card_text' => $text,
                'card_image_url' => (string)($row['image_url'] ?? ''),
                'card_rarity' => (trim((string)($row['powers'] ?? '')) !== '') ? 'rare' : 'unusual',
            ];
        }
    }

    if (hg_gc_table_exists($link, 'dim_auspices')) {
        foreach (hg_gc_fetch_rows($link, "
            SELECT id, name, pretty_id, description, image_url, energy_resources_configured
            FROM dim_auspices
            WHERE TRIM(COALESCE(name, '')) <> ''
            ORDER BY system_id ASC, name ASC, id ASC
        ") as $row) {
            $cards[] = [
                'source_type' => 'auspice',
                'source_table' => 'dim_auspices',
                'source_id' => (int)$row['id'],
                'card_name' => (string)$row['name'],
                'card_slug' => (string)($row['pretty_id'] ?? ''),
                'card_text' => (string)($row['description'] ?? ''),
                'card_image_url' => (string)($row['image_url'] ?? ''),
                'card_rarity' => ((int)($row['energy_resources_configured'] ?? 0) > 0) ? 'rare' : 'unusual',
            ];
        }
    }

    if (hg_gc_table_exists($link, 'dim_forms')) {
        foreach (hg_gc_fetch_rows($link, "
            SELECT id, pretty_id, affiliation, race, form, description, image_url, strength_bonus, dexterity_bonus, stamina_bonus, regeneration, hpregen
            FROM dim_forms
            WHERE TRIM(COALESCE(form, '')) <> ''
              AND LOWER(TRIM(form)) <> 'hominido'
            ORDER BY system_id ASC, race ASC, form ASC, id ASC
        ") as $row) {
            $race = trim((string)($row['race'] ?? ''));
            $form = trim((string)($row['form'] ?? ''));
            $name = ($race !== '' && stripos($form, $race) === false) ? "{$race}: {$form}" : $form;
            $cards[] = [
                'source_type' => 'form',
                'source_table' => 'dim_forms',
                'source_id' => (int)$row['id'],
                'card_name' => $name,
                'card_slug' => (string)($row['pretty_id'] ?? ''),
                'card_text' => (string)($row['description'] ?? ''),
                'card_image_url' => (string)($row['image_url'] ?? ''),
                'card_rarity' => hg_gc_form_rarity($row),
            ];
        }
    }

    if (hg_gc_table_exists($link, 'fact_items')) {
        foreach (hg_gc_fetch_rows($link, "
            SELECT id, name, pretty_id, description, image_url, GREATEST(COALESCE(level, 0), COALESCE(rating, 0), COALESCE(bonus, 0)) AS item_level
            FROM fact_items
            WHERE TRIM(COALESCE(name, '')) <> ''
            ORDER BY id ASC
        ") as $row) {
            $cards[] = [
                'source_type' => 'object',
                'source_table' => 'fact_items',
                'source_id' => (int)$row['id'],
                'card_name' => (string)$row['name'],
                'card_slug' => (string)($row['pretty_id'] ?? ''),
                'card_text' => (string)($row['description'] ?? ''),
                'card_image_url' => (string)($row['image_url'] ?? ''),
                'card_rarity' => hg_gc_level_rarity((int)($row['item_level'] ?? 0)),
            ];
        }
    }

    if (hg_gc_table_exists($link, 'fact_docs')) {
        foreach (hg_gc_fetch_rows($link, "
            SELECT id, title, pretty_id, content
            FROM fact_docs
            WHERE TRIM(COALESCE(title, '')) <> ''
            ORDER BY id ASC
        ") as $row) {
            $cards[] = [
                'source_type' => 'document',
                'source_table' => 'fact_docs',
                'source_id' => (int)$row['id'],
                'card_name' => (string)$row['title'],
                'card_slug' => (string)($row['pretty_id'] ?? ''),
                'card_text' => (string)($row['content'] ?? ''),
                'card_image_url' => hg_gc_fallback_image_url('document'),
                'card_rarity' => 'common',
            ];
        }
    }

    if (hg_gc_table_exists($link, 'fact_gifts')) {
        foreach (hg_gc_fetch_rows($link, "
            SELECT id, name, pretty_id, description, image_url, rank
            FROM fact_gifts
            WHERE TRIM(COALESCE(name, '')) <> ''
            ORDER BY id ASC
        ") as $row) {
            $cards[] = [
                'source_type' => 'gift',
                'source_table' => 'fact_gifts',
                'source_id' => (int)$row['id'],
                'card_name' => (string)$row['name'],
                'card_slug' => (string)($row['pretty_id'] ?? ''),
                'card_text' => (string)($row['description'] ?? ''),
                'card_image_url' => (string)($row['image_url'] ?? ''),
                'card_rarity' => hg_gc_level_rarity((int)preg_replace('/\D+/', '', (string)($row['rank'] ?? '1'))),
            ];
        }
    }

    if (hg_gc_table_exists($link, 'fact_rites')) {
        foreach (hg_gc_fetch_rows($link, "
            SELECT id, name, pretty_id, description, image_url, level
            FROM fact_rites
            WHERE TRIM(COALESCE(name, '')) <> ''
            ORDER BY id ASC
        ") as $row) {
            $cards[] = [
                'source_type' => 'rite',
                'source_table' => 'fact_rites',
                'source_id' => (int)$row['id'],
                'card_name' => (string)$row['name'],
                'card_slug' => (string)($row['pretty_id'] ?? ''),
                'card_text' => (string)($row['description'] ?? ''),
                'card_image_url' => (string)($row['image_url'] ?? ''),
                'card_rarity' => hg_gc_level_rarity((int)($row['level'] ?? 1)),
            ];
        }
    }

    if (hg_gc_table_exists($link, 'dim_totems')) {
        foreach (hg_gc_fetch_rows($link, "
            SELECT id, name, pretty_id, description, image_url, cost
            FROM dim_totems
            WHERE TRIM(COALESCE(name, '')) <> ''
            ORDER BY id ASC
        ") as $row) {
            $cost = (int)($row['cost'] ?? 0);
            $rarity = ($cost >= 10) ? 'mythic' : (($cost >= 8) ? 'legendary' : (($cost >= 6) ? 'epic' : (($cost >= 5) ? 'rare' : (($cost >= 3) ? 'unusual' : 'common'))));
            $cards[] = [
                'source_type' => 'totem',
                'source_table' => 'dim_totems',
                'source_id' => (int)$row['id'],
                'card_name' => (string)$row['name'],
                'card_slug' => (string)($row['pretty_id'] ?? ''),
                'card_text' => (string)($row['description'] ?? ''),
                'card_image_url' => (string)($row['image_url'] ?? ''),
                'card_rarity' => $rarity,
            ];
        }
    }

    if (hg_gc_table_exists($link, 'fact_discipline_powers')) {
        foreach (hg_gc_fetch_rows($link, "
            SELECT id, name, pretty_id, description, image_url, level
            FROM fact_discipline_powers
            WHERE TRIM(COALESCE(name, '')) <> ''
            ORDER BY id ASC
        ") as $row) {
            $cards[] = [
                'source_type' => 'discipline',
                'source_table' => 'fact_discipline_powers',
                'source_id' => (int)$row['id'],
                'card_name' => (string)$row['name'],
                'card_slug' => (string)($row['pretty_id'] ?? ''),
                'card_text' => (string)($row['description'] ?? ''),
                'card_image_url' => (string)($row['image_url'] ?? ''),
                'card_rarity' => hg_gc_level_rarity((int)($row['level'] ?? 1)),
            ];
        }
    }

    return $cards;
}

function hg_gc_generic_seed_cards(): array
{
    return [
        ['source_type' => 'character', 'source_table' => 'fact_characters', 'source_id' => 0, 'card_name' => 'Carta de personaje', 'card_text' => 'Entrada generica para comprobar el album.', 'card_rarity' => 'common'],
        ['source_type' => 'episode', 'source_table' => 'dim_chapters', 'source_id' => 0, 'card_name' => 'Carta de episodio', 'card_text' => 'Entrada generica para comprobar los sobres.', 'card_rarity' => 'common'],
        ['source_type' => 'object', 'source_table' => 'fact_items', 'source_id' => 0, 'card_name' => 'Carta de objeto', 'card_text' => 'Entrada generica para comprobar la coleccion.', 'card_rarity' => 'unusual'],
        ['source_type' => 'gift', 'source_table' => 'fact_gifts', 'source_id' => 0, 'card_name' => 'Carta de don', 'card_text' => 'Entrada generica para comprobar rarezas.', 'card_rarity' => 'rare'],
        ['source_type' => 'rite', 'source_table' => 'fact_rites', 'source_id' => 0, 'card_name' => 'Carta de rito', 'card_text' => 'Entrada generica para comprobar rareza epica.', 'card_rarity' => 'epic'],
        ['source_type' => 'chronicle', 'source_table' => 'dim_chronicles', 'source_id' => 0, 'card_name' => 'Carta de cronica', 'card_text' => 'Entrada generica para comprobar el archivo.', 'card_rarity' => 'legendary'],
    ];
}

function hg_gc_seed_run(mysqli $link, bool $resetCatalog = false): array
{
    hg_gc_seed_exec_schema($link);
    $deleted = $resetCatalog ? hg_gc_seed_reset_catalog($link) : 0;
    $deactivatedExcluded = hg_gc_seed_deactivate_excluded_chronicles($link, hg_gc_seed_excluded_chronicle_ids($link));
    $cards = hg_gc_seed_rows($link);
    if (count($cards) === 0) {
        $cards = hg_gc_generic_seed_cards();
    }

    $inserted = 0;
    foreach ($cards as $card) {
        if (hg_gc_seed_insert_card($link, $card)) {
            $inserted++;
        }
    }

    $total = 0;
    if ($rs = $link->query("SELECT COUNT(*) AS total FROM fact_game_card_collection")) {
        $row = $rs->fetch_assoc();
        $total = (int)($row['total'] ?? 0);
        $rs->free();
    }

    return [
        'schema_ready' => true,
        'reset' => $resetCatalog,
        'deleted' => $deleted,
        'excluded_deactivated' => $deactivatedExcluded,
        'inserted' => $inserted,
        'total' => $total,
    ];
}

function hg_gc_seed_cli_main(array $argv): int
{
    try {
        require __DIR__ . '/../helpers/db_connection.php';
    } catch (Throwable $e) {
        fwrite(STDERR, "Database connection unavailable: " . $e->getMessage() . "\n");
        return 1;
    }

    if (!isset($link) || !($link instanceof mysqli)) {
        fwrite(STDERR, "Database connection unavailable.\n");
        return 1;
    }

    try {
        $stats = hg_gc_seed_run($link, in_array('--reset', $argv, true));
    } catch (Throwable $e) {
        fwrite(STDERR, "Seed failed: " . $e->getMessage() . "\n");
        return 1;
    }

    echo "Schema ready.\n";
    if (!empty($stats['reset'])) {
        echo "Catalog reset: " . (int)$stats['deleted'] . " cards deleted.\n";
    }
    echo "Excluded chronicle cards deactivated: " . (int)$stats['excluded_deactivated'] . "\n";
    echo "New cards inserted: " . (int)$stats['inserted'] . "\n";
    echo "Catalog total: " . (int)$stats['total'] . "\n";
    return 0;
}

if (PHP_SAPI === 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    exit(hg_gc_seed_cli_main($argv ?? []));
}

if (PHP_SAPI !== 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    http_response_code(403);
    echo "This tool is not directly accessible.\n";
    exit;
}
