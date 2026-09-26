<?php

if (!function_exists('hg_dice_normalize_int_csv')) {
    function hg_dice_normalize_int_csv($csv): string
    {
        $parts = preg_split('/\s*,\s*/', trim((string)$csv));
        $ids = [];
        foreach ($parts as $part) {
            if ($part !== '' && preg_match('/^\d+$/', $part)) {
                $ids[] = (string)(int)$part;
            }
        }
        return implode(',', array_values(array_unique($ids)));
    }
}

if (!function_exists('hg_dice_normalize_kind_key')) {
    function hg_dice_normalize_kind_key(string $kind): string
    {
        $normalized = function_exists('mb_strtolower')
            ? mb_strtolower(trim($kind), 'UTF-8')
            : strtolower(trim($kind));
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
            if ($converted !== false) {
                $normalized = $converted;
            }
        }
        return strtolower($normalized);
    }
}

if (!function_exists('hg_dice_roll_d10_pool')) {
    function hg_dice_roll_d10_pool(int $dicePool, int $difficulty, array $forced = []): array
    {
        $results = [];
        $rawSuccesses = 0;
        $oneDetected = false;
        for ($i = 0; $i < $dicePool; $i++) {
            $die = !empty($forced) ? (int)$forced[$i] : rand(1, 10);
            $results[] = $die;
            if ($die >= $difficulty) {
                $rawSuccesses++;
            }
            if ($die === 1) {
                $oneDetected = true;
            }
        }

        $successes = $rawSuccesses;
        if ($oneDetected) {
            $successes = max(0, $successes - 1);
        }
        $botch = ($oneDetected && $rawSuccesses === 0);
        return [$results, $successes, $botch];
    }
}

if (!function_exists('hg_dice_fetch_pj_list')) {
    function hg_dice_fetch_pj_list(mysqli $link, $excludedChronicles = '2,7'): array
    {
        $excluded = hg_dice_normalize_int_csv($excludedChronicles);
        $whereChronicle = $excluded !== '' ? " AND c.chronicle_id NOT IN ({$excluded})" : '';
        $sql = "SELECT c.id, c.name, c.chronicle_id, ch.name AS chronicle_name
                FROM fact_characters c
                LEFT JOIN dim_chronicles ch ON ch.id = c.chronicle_id
                WHERE LOWER(c.character_kind) = 'pj'{$whereChronicle}
                ORDER BY c.name ASC";
        $result = mysqli_query($link, $sql);
        if (!$result) {
            return [];
        }
        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = [
                'id' => (int)$row['id'],
                'name' => (string)$row['name'],
                'chronicle_id' => (int)($row['chronicle_id'] ?? 0),
                'chronicle_name' => (string)($row['chronicle_name'] ?? ''),
            ];
        }
        mysqli_free_result($result);
        return $rows;
    }
}

if (!function_exists('hg_dice_fetch_pj')) {
    function hg_dice_fetch_pj(mysqli $link, int $characterId): ?array
    {
        if ($characterId <= 0) {
            return null;
        }
        $stmt = mysqli_prepare(
            $link,
            "SELECT c.id, c.name, c.chronicle_id, ch.name AS chronicle_name
             FROM fact_characters c
             LEFT JOIN dim_chronicles ch ON ch.id = c.chronicle_id
             WHERE c.id = ? AND LOWER(c.character_kind) = 'pj'
             LIMIT 1"
        );
        if (!$stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, 'i', $characterId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        if ($result) {
            mysqli_free_result($result);
        }
        mysqli_stmt_close($stmt);
        return $row ?: null;
    }
}

if (!function_exists('hg_dice_fetch_pj_roll_profiles')) {
    function hg_dice_fetch_pj_roll_profiles(mysqli $link, array $pjs): array
    {
        $profiles = [];
        $ids = [];
        foreach ($pjs as $pj) {
            $id = (int)($pj['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $ids[$id] = true;
            $profiles[$id] = [
                'id' => $id,
                'name' => (string)($pj['name'] ?? ''),
                'chronicle' => (string)($pj['chronicle_name'] ?? ''),
                'attributes' => [],
                'skills' => [],
                'resources' => [],
                'attribute_map' => [],
                'attribute_labels' => [],
                'skill_map' => [],
                'skill_labels' => [],
                'skill_kind_map' => [],
                'resource_map' => [],
                'resource_labels' => [],
            ];
        }
        if (empty($ids)) {
            return $profiles;
        }

        $idSql = implode(',', array_keys($ids));
        $result = mysqli_query(
            $link,
            "SELECT b.character_id, t.id AS trait_id, t.name, t.kind, b.value
             FROM bridge_characters_traits b
             JOIN dim_traits t ON t.id = b.trait_id
             WHERE b.character_id IN ({$idSql})
               AND t.kind IN ('Atributos','Talentos','Técnicas','Tecnicas','Conocimientos','Trasfondos')
             ORDER BY t.name"
        );
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $characterId = (int)$row['character_id'];
                if (!isset($profiles[$characterId])) {
                    continue;
                }
                $traitId = (int)$row['trait_id'];
                $value = (int)$row['value'];
                if ($traitId <= 0 || $value <= 0) {
                    continue;
                }
                $name = (string)$row['name'];
                $item = ['id' => $traitId, 'name' => $name, 'value' => $value];
                $kind = hg_dice_normalize_kind_key((string)$row['kind']);
                if ($kind === 'atributos') {
                    $profiles[$characterId]['attributes'][] = $item;
                    $profiles[$characterId]['attribute_map'][$traitId] = $value;
                    $profiles[$characterId]['attribute_labels'][$traitId] = $name;
                } elseif (in_array($kind, ['talentos', 'tecnicas', 'conocimientos', 'trasfondos'], true)) {
                    $skillKind = $kind === 'trasfondos' ? 'trasfondo' : 'habilidad';
                    $item['skill_kind'] = $skillKind;
                    $profiles[$characterId]['skills'][] = $item;
                    $profiles[$characterId]['skill_map'][$traitId] = $value;
                    $profiles[$characterId]['skill_labels'][$traitId] = $name;
                    $profiles[$characterId]['skill_kind_map'][$traitId] = $skillKind;
                }
            }
            mysqli_free_result($result);
        }

        $result = mysqli_query(
            $link,
            "SELECT r.character_id, d.id AS resource_id, d.name, r.value_permanent, r.value_temporary
             FROM bridge_characters_system_resources r
             JOIN dim_systems_resources d ON d.id = r.resource_id
             WHERE r.character_id IN ({$idSql})
               AND LOWER(d.kind) = 'estado'
             ORDER BY d.sort_order, d.name"
        );
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $characterId = (int)$row['character_id'];
                if (!isset($profiles[$characterId])) {
                    continue;
                }
                $resourceId = (int)$row['resource_id'];
                $permanent = (int)$row['value_permanent'];
                $temporary = (int)$row['value_temporary'];
                $value = $temporary > 0 ? $temporary : $permanent;
                if ($resourceId <= 0 || $value <= 0) {
                    continue;
                }
                $name = (string)$row['name'];
                $profiles[$characterId]['resources'][] = ['id' => $resourceId, 'name' => $name, 'value' => $value];
                $profiles[$characterId]['resource_map'][$resourceId] = $value;
                $profiles[$characterId]['resource_labels'][$resourceId] = $name;
            }
            mysqli_free_result($result);
        }

        return $profiles;
    }
}

if (!function_exists('hg_dice_fetch_roll_profile')) {
    function hg_dice_fetch_roll_profile(mysqli $link, int $characterId): ?array
    {
        $pj = hg_dice_fetch_pj($link, $characterId);
        if (!$pj) {
            return null;
        }
        $profiles = hg_dice_fetch_pj_roll_profiles($link, [$pj]);
        return $profiles[$characterId] ?? null;
    }
}

if (!function_exists('hg_dice_fetch_roll')) {
    function hg_dice_fetch_roll(mysqli $link, int $rollId): ?array
    {
        if ($rollId <= 0) {
            return null;
        }
        $stmt = mysqli_prepare(
            $link,
            'SELECT id, name, roll_name, dice_pool, difficulty, roll_results, successes, botch, willpower_spent, ip, rolled_at
             FROM fact_dice_rolls WHERE id = ? LIMIT 1'
        );
        if (!$stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, 'i', $rollId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        if ($result) {
            mysqli_free_result($result);
        }
        mysqli_stmt_close($stmt);
        return $row ?: null;
    }
}

if (!function_exists('hg_dice_last_roll_at_for_ip')) {
    function hg_dice_last_roll_at_for_ip(mysqli $link, string $ip): ?string
    {
        $stmt = mysqli_prepare($link, 'SELECT rolled_at FROM fact_dice_rolls WHERE ip = ? ORDER BY rolled_at DESC LIMIT 1');
        if (!$stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, 's', $ip);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        if ($result) {
            mysqli_free_result($result);
        }
        mysqli_stmt_close($stmt);
        return $row ? (string)($row['rolled_at'] ?? '') : null;
    }
}

if (!function_exists('hg_dice_roll_name_exists')) {
    function hg_dice_roll_name_exists(mysqli $link, string $rollName): bool
    {
        $stmt = mysqli_prepare($link, 'SELECT COUNT(*) AS total FROM fact_dice_rolls WHERE roll_name = ?');
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 's', $rollName);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        if ($result) {
            mysqli_free_result($result);
        }
        mysqli_stmt_close($stmt);
        return (int)($row['total'] ?? 0) > 0;
    }
}

if (!function_exists('hg_dice_insert_roll')) {
    function hg_dice_insert_roll(
        mysqli $link,
        string $name,
        string $rollName,
        int $dicePool,
        int $difficulty,
        string $rollResults,
        int $successes,
        bool $botch,
        int $willpowerSpent,
        string $ip,
        ?string &$error = null
    ): int {
        $error = null;
        $botchValue = $botch ? 1 : 0;
        $stmt = mysqli_prepare(
            $link,
            'INSERT INTO fact_dice_rolls
             (name, roll_name, dice_pool, difficulty, roll_results, successes, botch, willpower_spent, ip)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$stmt) {
            $error = mysqli_error($link);
            return 0;
        }
        mysqli_stmt_bind_param(
            $stmt,
            'ssiisiiis',
            $name,
            $rollName,
            $dicePool,
            $difficulty,
            $rollResults,
            $successes,
            $botchValue,
            $willpowerSpent,
            $ip
        );
        if (!mysqli_stmt_execute($stmt)) {
            $error = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            return 0;
        }
        $id = (int)mysqli_insert_id($link);
        mysqli_stmt_close($stmt);
        return $id;
    }
}

if (!function_exists('hg_dice_form_attribute_modifier')) {
    function hg_dice_form_attribute_modifier(mysqli $db, int $characterId, int $formId, int $traitId): int
    {
        if ($characterId <= 0 || $formId <= 0 || $traitId <= 0) {
            return 0;
        }

        $sql = "SELECT b.modifier, t.name AS trait_name, f.strength_bonus, f.dexterity_bonus, f.stamina_bonus
                FROM dim_forms f
                JOIN fact_characters c ON c.id = ? AND c.system_id = f.system_id
                JOIN dim_traits t ON t.id = ?
                LEFT JOIN bridge_forms_traits b ON b.form_id = f.id AND b.trait_id = t.id
                WHERE f.id = ? LIMIT 1";
        $stmt = mysqli_prepare($db, $sql);
        if (!$stmt) {
            return 0;
        }

        mysqli_stmt_bind_param($stmt, 'iii', $characterId, $traitId, $formId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        if ($result) {
            mysqli_free_result($result);
        }
        mysqli_stmt_close($stmt);

        if (!$row) {
            return 0;
        }
        if ($row['modifier'] !== null) {
            return (int)$row['modifier'];
        }

        switch ((string)($row['trait_name'] ?? '')) {
            case 'Fuerza':
                return (int)($row['strength_bonus'] ?? 0);
            case 'Destreza':
                return (int)($row['dexterity_bonus'] ?? 0);
            case 'Resistencia':
                return (int)($row['stamina_bonus'] ?? 0);
            default:
                return 0;
        }
    }
}

if (!function_exists('hg_dice_fetch_roll_history')) {
    function hg_dice_fetch_roll_history(mysqli $link): array
    {
        $result = mysqli_query(
            $link,
            'SELECT id, roll_name, name, successes, botch, willpower_spent, rolled_at
             FROM fact_dice_rolls
             ORDER BY rolled_at DESC'
        );
        if (!$result) {
            return [];
        }

        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
        mysqli_free_result($result);
        return $rows;
    }
}

if (!function_exists('hg_dice_fetch_recent_rolls')) {
    function hg_dice_fetch_recent_rolls(mysqli $link, int $limit = 10): array
    {
        $limit = max(1, min(100, $limit));
        $result = mysqli_query(
            $link,
            "SELECT id, roll_name, name
             FROM fact_dice_rolls
             ORDER BY rolled_at DESC
             LIMIT {$limit}"
        );
        if (!$result) {
            return [];
        }

        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
        mysqli_free_result($result);
        return $rows;
    }
}
