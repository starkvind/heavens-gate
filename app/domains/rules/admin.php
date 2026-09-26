<?php
// Admin data ownership for rule/game-content editors.
// Controllers keep request validation, orchestration and rendering; SQL lives here.

if (!function_exists('hg_rules_admin_fetch_all')) {
    function hg_rules_admin_fetch_all(mysqli $link, string $sql): array
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
}

if (!function_exists('hg_rules_admin_fetch_pair_map')) {
    function hg_rules_admin_fetch_pair_map(mysqli $link, string $sql): array
    {
        $out = [];
        foreach (hg_rules_admin_fetch_all($link, $sql) as $row) {
            $id = (int)($row['id'] ?? 0);
            if ($id > 0) {
                $out[$id] = (string)($row['name'] ?? '');
            }
        }
        return $out;
    }
}

if (!function_exists('hg_rules_admin_bibliographies')) {
    function hg_rules_admin_bibliographies(mysqli $link): array
    {
        return hg_rules_admin_fetch_pair_map($link, 'SELECT id, name FROM dim_bibliographies ORDER BY name ASC');
    }
}

if (!function_exists('hg_rules_admin_systems')) {
    function hg_rules_admin_systems(mysqli $link): array
    {
        return hg_rules_admin_fetch_pair_map($link, 'SELECT id, name FROM dim_systems ORDER BY name ASC');
    }
}

/* Actions ---------------------------------------------------------------- */

if (!function_exists('hg_rules_admin_actions_options')) {
    function hg_rules_admin_actions_options(mysqli $link): array
    {
        return [
            'attributes' => hg_rules_admin_fetch_all($link, "SELECT id, name FROM dim_traits WHERE kind = 'Atributos' ORDER BY name"),
            'skills' => hg_rules_admin_fetch_all($link, "SELECT id, name, kind FROM dim_traits WHERE kind IN ('Talentos', 'Técnicas', 'Conocimientos') ORDER BY kind, name"),
            'bibliographies' => hg_rules_admin_fetch_all($link, 'SELECT id, name FROM dim_bibliographies ORDER BY name'),
        ];
    }
}

if (!function_exists('hg_rules_admin_actions_image')) {
    function hg_rules_admin_actions_image(mysqli $link, int $id): string
    {
        if ($id <= 0) return '';
        $st = $link->prepare('SELECT image_url FROM fact_actions WHERE id = ? LIMIT 1');
        if (!$st) return '';
        $st->bind_param('i', $id);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        return (string)($row['image_url'] ?? '');
    }
}

if (!function_exists('hg_rules_admin_actions_delete')) {
    function hg_rules_admin_actions_delete(mysqli $link, int $id): array
    {
        if ($id <= 0) return ['ok' => false, 'error' => 'invalid_id'];
        $st = $link->prepare('DELETE FROM fact_actions WHERE id = ?');
        if (!$st) return ['ok' => false, 'error' => $link->error];
        $st->bind_param('i', $id);
        $ok = $st->execute();
        $error = $st->error;
        $st->close();
        return ['ok' => $ok, 'error' => $error];
    }
}

if (!function_exists('hg_rules_admin_actions_create')) {
    function hg_rules_admin_actions_create(mysqli $link, array $d): array
    {
        $st = $link->prepare("INSERT INTO fact_actions (name, category, text, image_url, attribute_trait_id, skill_trait_id, difficulty_mode, fixed_difficulty, suggested_difficulty, min_difficulty, max_difficulty, bibliography_id) VALUES (?, ?, ?, NULLIF(?, ''), ?, ?, ?, NULLIF(?, 0), NULLIF(?, 0), NULLIF(?, 0), NULLIF(?, 0), NULLIF(?, 0))");
        if (!$st) return ['ok' => false, 'error' => $link->error, 'id' => 0];
        $st->bind_param(
            'ssssiisiiiii',
            $d['name'], $d['category'], $d['text'], $d['image_url'],
            $d['attribute_trait_id'], $d['skill_trait_id'], $d['difficulty_mode'],
            $d['fixed_difficulty'], $d['suggested_difficulty'], $d['min_difficulty'],
            $d['max_difficulty'], $d['bibliography_id']
        );
        $ok = $st->execute();
        $error = $st->error;
        $id = $ok ? (int)$link->insert_id : 0;
        $st->close();
        return ['ok' => $ok, 'error' => $error, 'id' => $id];
    }
}

if (!function_exists('hg_rules_admin_actions_update')) {
    function hg_rules_admin_actions_update(mysqli $link, int $id, array $d): array
    {
        if ($id <= 0) return ['ok' => false, 'error' => 'invalid_id'];
        $st = $link->prepare("UPDATE fact_actions SET name = ?, category = ?, text = ?, image_url = NULLIF(?, ''), attribute_trait_id = ?, skill_trait_id = ?, difficulty_mode = ?, fixed_difficulty = NULLIF(?, 0), suggested_difficulty = NULLIF(?, 0), min_difficulty = NULLIF(?, 0), max_difficulty = NULLIF(?, 0), bibliography_id = NULLIF(?, 0) WHERE id = ?");
        if (!$st) return ['ok' => false, 'error' => $link->error];
        $st->bind_param(
            'ssssiisiiiiii',
            $d['name'], $d['category'], $d['text'], $d['image_url'],
            $d['attribute_trait_id'], $d['skill_trait_id'], $d['difficulty_mode'],
            $d['fixed_difficulty'], $d['suggested_difficulty'], $d['min_difficulty'],
            $d['max_difficulty'], $d['bibliography_id'], $id
        );
        $ok = $st->execute();
        $error = $st->error;
        $st->close();
        return ['ok' => $ok, 'error' => $error];
    }
}

if (!function_exists('hg_rules_admin_actions_rows')) {
    function hg_rules_admin_actions_rows(mysqli $link): array
    {
        return hg_rules_admin_fetch_all(
            $link,
            "SELECT a.*, COALESCE(attr.name, '') AS attribute_name, COALESCE(skill.name, '') AS skill_name
             FROM fact_actions a
             LEFT JOIN dim_traits attr ON attr.id = a.attribute_trait_id
             LEFT JOIN dim_traits skill ON skill.id = a.skill_trait_id
             ORDER BY a.category, a.name"
        );
    }
}

/* Traits ----------------------------------------------------------------- */

if (!function_exists('hg_rules_admin_traits_options')) {
    function hg_rules_admin_traits_options(mysqli $link): array
    {
        $kinds = [];
        foreach (hg_rules_admin_fetch_all($link, "SELECT DISTINCT kind FROM dim_traits WHERE kind IS NOT NULL AND TRIM(kind) <> '' ORDER BY kind ASC") as $row) {
            $kinds[] = (string)($row['kind'] ?? '');
        }
        $classifications = [];
        foreach (hg_rules_admin_fetch_all($link, "SELECT DISTINCT classification FROM dim_traits WHERE classification IS NOT NULL AND TRIM(classification) <> '' ORDER BY classification ASC") as $row) {
            $classifications[] = (string)($row['classification'] ?? '');
        }
        return [
            'origins' => hg_rules_admin_bibliographies($link),
            'kinds' => $kinds,
            'classifications' => $classifications,
        ];
    }
}

if (!function_exists('hg_rules_admin_traits_create')) {
    function hg_rules_admin_traits_create(mysqli $link, array $d): array
    {
        $st = $link->prepare("INSERT INTO dim_traits (name, kind, classification, description, levels, posse, special, bibliography_id, created_at, updated_at)
                             VALUES (?, ?, ?, ?, ?, ?, ?, NULLIF(?, 0), NOW(), NOW())");
        if (!$st) return ['ok' => false, 'error' => $link->error, 'id' => 0];
        $st->bind_param('sssssssi', $d['name'], $d['kind'], $d['classification'], $d['description'], $d['levels'], $d['posse'], $d['special'], $d['bibliography_id']);
        $ok = $st->execute();
        $error = $st->error;
        $id = $ok ? (int)$link->insert_id : 0;
        $st->close();
        return ['ok' => $ok, 'error' => $error, 'id' => $id];
    }
}

if (!function_exists('hg_rules_admin_traits_update')) {
    function hg_rules_admin_traits_update(mysqli $link, int $id, array $d): array
    {
        if ($id <= 0) return ['ok' => false, 'error' => 'invalid_id'];
        $st = $link->prepare("UPDATE dim_traits
                             SET name=?, kind=?, classification=?, description=?, levels=?, posse=?, special=?, bibliography_id=NULLIF(?, 0), updated_at=NOW()
                             WHERE id=?");
        if (!$st) return ['ok' => false, 'error' => $link->error];
        $st->bind_param('sssssssii', $d['name'], $d['kind'], $d['classification'], $d['description'], $d['levels'], $d['posse'], $d['special'], $d['bibliography_id'], $id);
        $ok = $st->execute();
        $error = $st->error;
        $st->close();
        return ['ok' => $ok, 'error' => $error];
    }
}

if (!function_exists('hg_rules_admin_traits_delete')) {
    function hg_rules_admin_traits_delete(mysqli $link, int $id): array
    {
        if ($id <= 0) return ['ok' => false, 'error' => 'invalid_id'];
        $st = $link->prepare('DELETE FROM dim_traits WHERE id=?');
        if (!$st) return ['ok' => false, 'error' => $link->error];
        $st->bind_param('i', $id);
        $ok = $st->execute();
        $error = $st->error;
        $st->close();
        return ['ok' => $ok, 'error' => $error];
    }
}

if (!function_exists('hg_rules_admin_traits_filter_parts')) {
    function hg_rules_admin_traits_filter_parts(string $q): array
    {
        if ($q === '') return ['', '', []];
        $needle = '%' . $q . '%';
        return [' WHERE (name LIKE ? OR kind LIKE ? OR classification LIKE ?)', 'sss', [$needle, $needle, $needle]];
    }
}

if (!function_exists('hg_rules_admin_traits_search')) {
    function hg_rules_admin_traits_search(mysqli $link, string $q): array
    {
        [$where, $types, $params] = hg_rules_admin_traits_filter_parts($q);
        $sql = "SELECT id, name, kind, classification, description, levels, posse, special, bibliography_id, pretty_id
                FROM dim_traits{$where}
                ORDER BY id DESC";
        $st = $link->prepare($sql);
        if (!$st) return [];
        if ($types !== '') $st->bind_param($types, ...$params);
        $st->execute();
        $rs = $st->get_result();
        $rows = [];
        while ($rs && ($row = $rs->fetch_assoc())) $rows[] = $row;
        $st->close();
        return $rows;
    }
}

if (!function_exists('hg_rules_admin_traits_count')) {
    function hg_rules_admin_traits_count(mysqli $link, string $q): int
    {
        [$where, $types, $params] = hg_rules_admin_traits_filter_parts($q);
        $st = $link->prepare("SELECT COUNT(*) AS c FROM dim_traits{$where}");
        if (!$st) return 0;
        if ($types !== '') $st->bind_param($types, ...$params);
        $st->execute();
        $rs = $st->get_result();
        $row = $rs ? $rs->fetch_assoc() : null;
        $st->close();
        return (int)($row['c'] ?? 0);
    }
}

if (!function_exists('hg_rules_admin_traits_page')) {
    function hg_rules_admin_traits_page(mysqli $link, string $q, int $offset, int $limit): array
    {
        [$where, $types, $params] = hg_rules_admin_traits_filter_parts($q);
        $sql = "SELECT id, name, kind, classification, description, levels, posse, special, bibliography_id, pretty_id
                FROM dim_traits{$where}
                ORDER BY id DESC
                LIMIT ?, ?";
        $st = $link->prepare($sql);
        if (!$st) return [];
        $types .= 'ii';
        $params[] = $offset;
        $params[] = $limit;
        $st->bind_param($types, ...$params);
        $st->execute();
        $rs = $st->get_result();
        $rows = [];
        while ($rs && ($row = $rs->fetch_assoc())) $rows[] = $row;
        $st->close();
        return $rows;
    }
}

/* Character condition catalog ------------------------------------------- */

if (!function_exists('hg_rules_admin_conditions_origins')) {
    function hg_rules_admin_conditions_origins(mysqli $link): array
    {
        return hg_rules_admin_bibliographies($link);
    }
}

if (!function_exists('hg_rules_admin_conditions_create')) {
    function hg_rules_admin_conditions_create(mysqli $link, array $d): array
    {
        $st = $link->prepare("INSERT INTO dim_character_conditions
                             (name, category, description, max_instances, bibliography_id, created_at, updated_at)
                             VALUES (?, ?, ?, NULLIF(?, 0), NULLIF(?, 0), NOW(), NOW())");
        if (!$st) return ['ok' => false, 'error' => $link->error, 'id' => 0];
        $st->bind_param('sssii', $d['name'], $d['category'], $d['description'], $d['max_instances'], $d['bibliography_id']);
        $ok = $st->execute();
        $error = $st->error;
        $id = $ok ? (int)$link->insert_id : 0;
        $st->close();
        return ['ok' => $ok, 'error' => $error, 'id' => $id];
    }
}

if (!function_exists('hg_rules_admin_conditions_update')) {
    function hg_rules_admin_conditions_update(mysqli $link, int $id, array $d): array
    {
        if ($id <= 0) return ['ok' => false, 'error' => 'invalid_id'];
        $st = $link->prepare("UPDATE dim_character_conditions
                             SET name=?, category=?, description=?, max_instances=NULLIF(?, 0), bibliography_id=NULLIF(?, 0), updated_at=NOW()
                             WHERE id=?");
        if (!$st) return ['ok' => false, 'error' => $link->error];
        $st->bind_param('sssiii', $d['name'], $d['category'], $d['description'], $d['max_instances'], $d['bibliography_id'], $id);
        $ok = $st->execute();
        $error = $st->error;
        $st->close();
        return ['ok' => $ok, 'error' => $error];
    }
}

if (!function_exists('hg_rules_admin_conditions_delete')) {
    function hg_rules_admin_conditions_delete(mysqli $link, int $id): array
    {
        if ($id <= 0) return ['ok' => false, 'error' => 'invalid_id'];
        $st = $link->prepare('DELETE FROM dim_character_conditions WHERE id=?');
        if (!$st) return ['ok' => false, 'error' => $link->error];
        $st->bind_param('i', $id);
        $ok = $st->execute();
        $error = $st->error;
        $st->close();
        return ['ok' => $ok, 'error' => $error];
    }
}

if (!function_exists('hg_rules_admin_conditions_filter_parts')) {
    function hg_rules_admin_conditions_filter_parts(string $q): array
    {
        if ($q === '') return ['', '', []];
        $needle = '%' . $q . '%';
        return [" WHERE (cc.name LIKE ? OR cc.category LIKE ? OR COALESCE(b.name, '') LIKE ? OR cc.description LIKE ?)", 'ssss', [$needle, $needle, $needle, $needle]];
    }
}

if (!function_exists('hg_rules_admin_conditions_search')) {
    function hg_rules_admin_conditions_search(mysqli $link, string $q): array
    {
        [$where, $types, $params] = hg_rules_admin_conditions_filter_parts($q);
        $sql = "SELECT cc.id, cc.pretty_id, cc.name, cc.category, cc.description, cc.max_instances, cc.bibliography_id,
                       COALESCE(b.name, '') AS origin_name
                FROM dim_character_conditions cc
                LEFT JOIN dim_bibliographies b ON b.id = cc.bibliography_id{$where}
                ORDER BY cc.id DESC";
        $st = $link->prepare($sql);
        if (!$st) return [];
        if ($types !== '') $st->bind_param($types, ...$params);
        $st->execute();
        $rs = $st->get_result();
        $rows = [];
        while ($rs && ($row = $rs->fetch_assoc())) $rows[] = $row;
        $st->close();
        return $rows;
    }
}

if (!function_exists('hg_rules_admin_conditions_count')) {
    function hg_rules_admin_conditions_count(mysqli $link, string $q): int
    {
        [$where, $types, $params] = hg_rules_admin_conditions_filter_parts($q);
        $sql = "SELECT COUNT(*) AS c
                FROM dim_character_conditions cc
                LEFT JOIN dim_bibliographies b ON b.id = cc.bibliography_id{$where}";
        $st = $link->prepare($sql);
        if (!$st) return 0;
        if ($types !== '') $st->bind_param($types, ...$params);
        $st->execute();
        $rs = $st->get_result();
        $row = $rs ? $rs->fetch_assoc() : null;
        $st->close();
        return (int)($row['c'] ?? 0);
    }
}

if (!function_exists('hg_rules_admin_conditions_page')) {
    function hg_rules_admin_conditions_page(mysqli $link, string $q, int $offset, int $limit): array
    {
        [$where, $types, $params] = hg_rules_admin_conditions_filter_parts($q);
        $sql = "SELECT cc.id, cc.pretty_id, cc.name, cc.category, cc.description, cc.max_instances, cc.bibliography_id,
                       COALESCE(b.name, '') AS origin_name
                FROM dim_character_conditions cc
                LEFT JOIN dim_bibliographies b ON b.id = cc.bibliography_id{$where}
                ORDER BY cc.id DESC
                LIMIT ?, ?";
        $st = $link->prepare($sql);
        if (!$st) return [];
        $types .= 'ii';
        $params[] = $offset;
        $params[] = $limit;
        $st->bind_param($types, ...$params);
        $st->execute();
        $rs = $st->get_result();
        $rows = [];
        while ($rs && ($row = $rs->fetch_assoc())) $rows[] = $row;
        $st->close();
        return $rows;
    }
}

/* Merits / flaws --------------------------------------------------------- */

if (!function_exists('hg_rules_admin_merits_options')) {
    function hg_rules_admin_merits_options(mysqli $link): array
    {
        $kinds = [];
        foreach (hg_rules_admin_fetch_all($link, "SELECT DISTINCT kind FROM dim_merits_flaws WHERE kind IS NOT NULL AND TRIM(kind) <> '' ORDER BY kind ASC") as $row) {
            $kinds[] = (string)($row['kind'] ?? '');
        }
        $affiliations = [];
        foreach (hg_rules_admin_fetch_all($link, "SELECT DISTINCT affiliation FROM dim_merits_flaws WHERE affiliation IS NOT NULL AND TRIM(affiliation) <> '' ORDER BY affiliation ASC") as $row) {
            $affiliations[] = (string)($row['affiliation'] ?? '');
        }
        return [
            'origins' => hg_rules_admin_bibliographies($link),
            'systems' => hg_rules_admin_systems($link),
            'kinds' => $kinds,
            'affiliations' => $affiliations,
        ];
    }
}

if (!function_exists('hg_rules_admin_merits_create')) {
    function hg_rules_admin_merits_create(mysqli $link, array $d): array
    {
        $st = $link->prepare("INSERT INTO dim_merits_flaws
                             (name, kind, affiliation, cost, description, system_name, system_id, bibliography_id, created_at, updated_at)
                             VALUES (?, ?, ?, ?, ?, ?, NULLIF(?, 0), NULLIF(?, 0), NOW(), NOW())");
        if (!$st) return ['ok' => false, 'error' => $link->error, 'id' => 0];
        $st->bind_param('ssssssii', $d['name'], $d['kind'], $d['affiliation'], $d['cost'], $d['description'], $d['system_name'], $d['system_id'], $d['bibliography_id']);
        $ok = $st->execute();
        $error = $st->error;
        $id = $ok ? (int)$link->insert_id : 0;
        $st->close();
        return ['ok' => $ok, 'error' => $error, 'id' => $id];
    }
}

if (!function_exists('hg_rules_admin_merits_update')) {
    function hg_rules_admin_merits_update(mysqli $link, int $id, array $d): array
    {
        if ($id <= 0) return ['ok' => false, 'error' => 'invalid_id'];
        $st = $link->prepare("UPDATE dim_merits_flaws
                             SET name=?, kind=?, affiliation=?, cost=?, description=?, system_name=?, system_id=NULLIF(?, 0), bibliography_id=NULLIF(?, 0), updated_at=NOW()
                             WHERE id=?");
        if (!$st) return ['ok' => false, 'error' => $link->error];
        $st->bind_param('ssssssiii', $d['name'], $d['kind'], $d['affiliation'], $d['cost'], $d['description'], $d['system_name'], $d['system_id'], $d['bibliography_id'], $id);
        $ok = $st->execute();
        $error = $st->error;
        $st->close();
        return ['ok' => $ok, 'error' => $error];
    }
}

if (!function_exists('hg_rules_admin_merits_delete')) {
    function hg_rules_admin_merits_delete(mysqli $link, int $id): array
    {
        if ($id <= 0) return ['ok' => false, 'error' => 'invalid_id'];
        $st = $link->prepare('DELETE FROM dim_merits_flaws WHERE id=?');
        if (!$st) return ['ok' => false, 'error' => $link->error];
        $st->bind_param('i', $id);
        $ok = $st->execute();
        $error = $st->error;
        $st->close();
        return ['ok' => $ok, 'error' => $error];
    }
}

if (!function_exists('hg_rules_admin_merits_filter_parts')) {
    function hg_rules_admin_merits_filter_parts(string $q): array
    {
        if ($q === '') return ['', '', []];
        $needle = '%' . $q . '%';
        return [' WHERE (name LIKE ? OR kind LIKE ? OR affiliation LIKE ? OR cost LIKE ? OR system_name LIKE ?)', 'sssss', [$needle, $needle, $needle, $needle, $needle]];
    }
}

if (!function_exists('hg_rules_admin_merits_search')) {
    function hg_rules_admin_merits_search(mysqli $link, string $q): array
    {
        [$where, $types, $params] = hg_rules_admin_merits_filter_parts($q);
        $sql = "SELECT id, name, kind, affiliation, cost, description, system_name, system_id, bibliography_id, pretty_id
                FROM dim_merits_flaws{$where}
                ORDER BY id DESC";
        $st = $link->prepare($sql);
        if (!$st) return [];
        if ($types !== '') $st->bind_param($types, ...$params);
        $st->execute();
        $rs = $st->get_result();
        $rows = [];
        while ($rs && ($row = $rs->fetch_assoc())) $rows[] = $row;
        $st->close();
        return $rows;
    }
}

if (!function_exists('hg_rules_admin_merits_count')) {
    function hg_rules_admin_merits_count(mysqli $link, string $q): int
    {
        [$where, $types, $params] = hg_rules_admin_merits_filter_parts($q);
        $st = $link->prepare("SELECT COUNT(*) AS c FROM dim_merits_flaws{$where}");
        if (!$st) return 0;
        if ($types !== '') $st->bind_param($types, ...$params);
        $st->execute();
        $rs = $st->get_result();
        $row = $rs ? $rs->fetch_assoc() : null;
        $st->close();
        return (int)($row['c'] ?? 0);
    }
}

if (!function_exists('hg_rules_admin_merits_page')) {
    function hg_rules_admin_merits_page(mysqli $link, string $q, int $offset, int $limit): array
    {
        [$where, $types, $params] = hg_rules_admin_merits_filter_parts($q);
        $sql = "SELECT id, name, kind, affiliation, cost, description, system_name, system_id, bibliography_id, pretty_id
                FROM dim_merits_flaws{$where}
                ORDER BY id DESC
                LIMIT ?, ?";
        $st = $link->prepare($sql);
        if (!$st) return [];
        $types .= 'ii';
        $params[] = $offset;
        $params[] = $limit;
        $st->bind_param($types, ...$params);
        $st->execute();
        $rs = $st->get_result();
        $rows = [];
        while ($rs && ($row = $rs->fetch_assoc())) $rows[] = $row;
        $st->close();
        return $rows;
    }
}

/* Maneuver assignments --------------------------------------------------- */

if (!function_exists('hg_rules_admin_maneuver_save_links')) {
    function hg_rules_admin_maneuver_save_links(mysqli $link, int $maneuverId, array $systems, array $forms): array
    {
        $systems = array_values(array_unique(array_filter(array_map('intval', $systems))));
        $forms = array_values(array_unique(array_filter(array_map('intval', $forms))));
        $link->begin_transaction();
        try {
            $deleteSystem = $link->prepare('DELETE FROM bridge_maneuvers_systems WHERE maneuver_id = ?');
            $deleteForm = $link->prepare('DELETE FROM bridge_maneuvers_forms WHERE maneuver_id = ?');
            if (!$deleteSystem || !$deleteForm) throw new RuntimeException($link->error);
            $deleteSystem->bind_param('i', $maneuverId);
            $deleteForm->bind_param('i', $maneuverId);
            if (!$deleteSystem->execute() || !$deleteForm->execute()) throw new RuntimeException($link->error);
            $deleteSystem->close();
            $deleteForm->close();

            $insertSystem = $link->prepare('INSERT INTO bridge_maneuvers_systems (maneuver_id, system_id) VALUES (?, ?)');
            if (!$insertSystem) throw new RuntimeException($link->error);
            foreach ($systems as $systemId) {
                $insertSystem->bind_param('ii', $maneuverId, $systemId);
                if (!$insertSystem->execute()) throw new RuntimeException($insertSystem->error);
            }
            $insertSystem->close();

            $insertForm = $link->prepare('INSERT INTO bridge_maneuvers_forms (maneuver_id, form_id) VALUES (?, ?)');
            if (!$insertForm) throw new RuntimeException($link->error);
            foreach ($forms as $formId) {
                $insertForm->bind_param('ii', $maneuverId, $formId);
                if (!$insertForm->execute()) throw new RuntimeException($insertForm->error);
            }
            $insertForm->close();

            $link->commit();
            return ['ok' => true, 'error' => ''];
        } catch (Throwable $error) {
            $link->rollback();
            return ['ok' => false, 'error' => $error->getMessage()];
        }
    }
}

if (!function_exists('hg_rules_admin_maneuver_state')) {
    function hg_rules_admin_maneuver_state(mysqli $link, int $selectedId, bool $bridgesReady): array
    {
        $maneuvers = hg_rules_admin_fetch_all($link, 'SELECT id, name, system_name, user FROM fact_combat_maneuvers ORDER BY system_name, name');
        if ($selectedId <= 0 && !empty($maneuvers)) $selectedId = (int)$maneuvers[0]['id'];

        $systems = hg_rules_admin_fetch_all($link, 'SELECT id, name FROM dim_systems ORDER BY sort_order, name');
        $forms = hg_rules_admin_fetch_all(
            $link,
            'SELECT f.id, f.form, f.race, s.name AS system_name
             FROM dim_forms f
             JOIN dim_systems s ON s.id=f.system_id
             ORDER BY s.sort_order, s.name, f.race, f.form'
        );

        $selectedSystems = [];
        $selectedForms = [];
        $maneuverLinkMap = [];

        if ($bridgesReady) {
            foreach (hg_rules_admin_fetch_all($link, 'SELECT maneuver_id, system_id FROM bridge_maneuvers_systems') as $row) {
                $maneuverLinkMap[(int)$row['maneuver_id']]['systems'][] = (int)$row['system_id'];
            }
            foreach (hg_rules_admin_fetch_all($link, 'SELECT maneuver_id, form_id FROM bridge_maneuvers_forms') as $row) {
                $maneuverLinkMap[(int)$row['maneuver_id']]['forms'][] = (int)$row['form_id'];
            }

            if ($selectedId > 0) {
                $st = $link->prepare('SELECT system_id FROM bridge_maneuvers_systems WHERE maneuver_id = ?');
                if ($st) {
                    $st->bind_param('i', $selectedId);
                    $st->execute();
                    $rs = $st->get_result();
                    while ($rs && ($row = $rs->fetch_assoc())) $selectedSystems[(int)$row['system_id']] = true;
                    $st->close();
                }
                $st = $link->prepare('SELECT form_id FROM bridge_maneuvers_forms WHERE maneuver_id = ?');
                if ($st) {
                    $st->bind_param('i', $selectedId);
                    $st->execute();
                    $rs = $st->get_result();
                    while ($rs && ($row = $rs->fetch_assoc())) $selectedForms[(int)$row['form_id']] = true;
                    $st->close();
                }
            }
        }

        return compact('maneuvers', 'selectedId', 'systems', 'forms', 'selectedSystems', 'selectedForms', 'maneuverLinkMap');
    }
}

/* Trait sets ------------------------------------------------------------- */

if (!function_exists('hg_rules_admin_trait_sets_systems')) {
    function hg_rules_admin_trait_sets_systems(mysqli $link): array
    {
        return hg_rules_admin_fetch_all($link, 'SELECT id, name FROM dim_systems ORDER BY name');
    }
}

if (!function_exists('hg_rules_admin_trait_sets_state')) {
    function hg_rules_admin_trait_sets_state(mysqli $link, int $systemId): array
    {
        $traitsByType = [];
        $traitOrderFixed = ['Atributos', 'Talentos', 'Tecnicas', 'Conocimientos', 'Trasfondos'];

        $st = $link->prepare("SELECT id, name, kind AS tipo FROM dim_traits WHERE kind IS NOT NULL AND TRIM(kind) <> '' ORDER BY kind, name");
        if ($st) {
            $st->execute();
            $rs = $st->get_result();
            while ($rs && ($row = $rs->fetch_assoc())) {
                $type = (string)$row['tipo'];
                if (!isset($traitsByType[$type])) $traitsByType[$type] = [];
                $traitsByType[$type][] = ['id' => (int)$row['id'], 'name' => (string)$row['name']];
            }
            $st->close();
        }

        $traitTypes = $traitOrderFixed;
        foreach (array_keys($traitsByType) as $type) {
            if (!in_array($type, $traitTypes, true)) $traitTypes[] = $type;
        }

        $existing = [];
        if ($systemId > 0) {
            $st = $link->prepare('SELECT trait_id, sort_order, is_active FROM fact_trait_sets WHERE system_id = ?');
            if ($st) {
                $st->bind_param('i', $systemId);
                $st->execute();
                $rs = $st->get_result();
                while ($rs && ($row = $rs->fetch_assoc())) {
                    $existing[(int)$row['trait_id']] = [
                        'sort_order' => (int)$row['sort_order'],
                        'is_active' => (int)$row['is_active'],
                    ];
                }
                $st->close();
            }
        }

        $groups = [];
        foreach ($traitTypes as $type) {
            $list = $traitsByType[$type] ?? [];
            if (!$list) continue;
            $traits = [];
            foreach ($list as $trait) {
                $tid = (int)$trait['id'];
                $ex = $existing[$tid] ?? null;
                $traits[] = [
                    'id' => $tid,
                    'name' => (string)$trait['name'],
                    'checked' => ($ex && (int)$ex['is_active'] === 1),
                    'sort_order' => $ex ? (int)$ex['sort_order'] : 0,
                ];
            }
            $groups[] = ['type' => $type, 'traits' => $traits];
        }

        return ['system_id' => $systemId, 'groups' => $groups];
    }
}

if (!function_exists('hg_rules_admin_trait_sets_save')) {
    function hg_rules_admin_trait_sets_save(mysqli $link, int $systemId, array $includeRaw, array $sortRaw): array
    {
        if ($systemId <= 0) return ['ok' => false, 'message' => 'Sistema invalido.'];

        $include = array_values(array_filter(array_map('intval', $includeRaw), static fn($v) => $v > 0));
        $link->begin_transaction();
        $ok = true;

        if (!$include) {
            $st = $link->prepare('DELETE FROM fact_trait_sets WHERE system_id = ?');
            if ($st) {
                $st->bind_param('i', $systemId);
                $ok = $st->execute();
                $st->close();
            } else {
                $ok = false;
            }
        } else {
            $placeholders = implode(',', array_fill(0, count($include), '?'));
            $sql = "DELETE FROM fact_trait_sets WHERE system_id = ? AND trait_id NOT IN ({$placeholders})";
            $st = $link->prepare($sql);
            if ($st) {
                $types = str_repeat('i', count($include) + 1);
                $params = array_merge([$systemId], $include);
                $st->bind_param($types, ...$params);
                $ok = $st->execute();
                $st->close();
            } else {
                $ok = false;
            }
        }

        if ($ok) {
            $st = $link->prepare(
                'INSERT INTO fact_trait_sets (system_id, trait_id, sort_order, is_active) VALUES (?,?,?,1) '
                . 'ON DUPLICATE KEY UPDATE sort_order=VALUES(sort_order), is_active=1, updated_at=NOW()'
            );
            if ($st) {
                foreach ($include as $tid) {
                    $ord = isset($sortRaw[$tid]) ? (int)$sortRaw[$tid] : 0;
                    $st->bind_param('iii', $systemId, $tid, $ord);
                    if (!$st->execute()) {
                        $ok = false;
                        break;
                    }
                }
                $st->close();
            } else {
                $ok = false;
            }
        }

        if ($ok) {
            $link->commit();
            return ['ok' => true, 'message' => 'Guardado.'];
        }

        $link->rollback();
        return ['ok' => false, 'message' => 'Error al guardar.'];
    }
}
