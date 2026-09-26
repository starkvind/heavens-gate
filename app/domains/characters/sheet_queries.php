<?php

require_once __DIR__ . '/detail_queries.php';

if (!function_exists('hg_characters_fetch_forms_for_system')) {
    function hg_characters_fetch_forms_for_system(mysqli $link, int $systemId): array
    {
        if ($systemId <= 0 || !hg_characters_table_exists($link, 'dim_forms')) return [];
        $sortSelect = hg_characters_has_column($link, 'dim_forms', 'sort_order')
            ? 'COALESCE(sort_order, 999) AS sort_order'
            : '999 AS sort_order';
        $stmt = $link->prepare("SELECT id, form, race, strength_bonus, dexterity_bonus, stamina_bonus, {$sortSelect}
                                FROM dim_forms
                                WHERE system_id = ? AND TRIM(COALESCE(form, '')) <> ''
                                ORDER BY sort_order ASC,
                                  CASE LOWER(form)
                                    WHEN 'hominido' THEN 10 WHEN 'glabro' THEN 20 WHEN 'glabrus' THEN 20
                                    WHEN 'sokto' THEN 20 WHEN 'crinos' THEN 30 WHEN 'hispo' THEN 40
                                    WHEN 'chatro' THEN 40 WHEN 'lupus' THEN 50 WHEN 'felino' THEN 50
                                    ELSE 500 END ASC,
                                  form ASC");
        if (!$stmt) return [];
        $stmt->bind_param('i', $systemId);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        while ($result && ($row = $result->fetch_assoc())) $rows[] = $row;
        if ($result) $result->free();
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('hg_characters_fetch_form_modifiers')) {
    function hg_characters_fetch_form_modifiers(mysqli $link, array $formIds): array
    {
        if (!hg_characters_table_exists($link, 'bridge_forms_traits')) return [];
        $ids = array_values(array_unique(array_filter(array_map('intval', $formIds), static fn(int $id): bool => $id > 0)));
        if (!$ids) return [];
        $sql = 'SELECT form_id, trait_id, modifier FROM bridge_forms_traits WHERE form_id IN (' . implode(',', $ids) . ')';
        $result = $link->query($sql);
        if (!$result) return [];
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[(int)$row['form_id']][(int)$row['trait_id']] = (int)$row['modifier'];
        }
        $result->free();
        return $rows;
    }
}

if (!function_exists('hg_characters_fetch_system_maneuvers')) {
    function hg_characters_fetch_system_maneuvers(mysqli $link, int $systemId): array
    {
        if ($systemId <= 0
            || !hg_characters_table_exists($link, 'bridge_maneuvers_systems')
            || !hg_characters_table_exists($link, 'fact_combat_maneuvers')) return [];
        $stmt = $link->prepare("SELECT m.id, m.name, m.image_url
                                FROM bridge_maneuvers_systems b
                                JOIN fact_combat_maneuvers m ON m.id = b.maneuver_id
                                WHERE b.system_id = ?
                                ORDER BY m.name ASC");
        if (!$stmt) return [];
        $stmt->bind_param('i', $systemId);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        while ($result && ($row = $result->fetch_assoc())) $rows[] = $row;
        if ($result) $result->free();
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('hg_characters_fetch_form_maneuvers')) {
    function hg_characters_fetch_form_maneuvers(mysqli $link, array $formIds): array
    {
        if (!hg_characters_table_exists($link, 'bridge_maneuvers_forms')
            || !hg_characters_table_exists($link, 'fact_combat_maneuvers')) return [];
        $ids = array_values(array_unique(array_filter(array_map('intval', $formIds), static fn(int $id): bool => $id > 0)));
        if (!$ids) return [];
        $sql = "SELECT b.form_id, m.id, m.name, m.image_url
                FROM bridge_maneuvers_forms b
                JOIN fact_combat_maneuvers m ON m.id = b.maneuver_id
                WHERE b.form_id IN (" . implode(',', $ids) . ")
                ORDER BY m.name ASC";
        $result = $link->query($sql);
        if (!$result) return [];
        $rows = [];
        while ($row = $result->fetch_assoc()) $rows[(int)$row['form_id']][] = $row;
        $result->free();
        return $rows;
    }
}

if (!function_exists('hg_characters_fetch_actions_for_sheet')) {
    function hg_characters_fetch_actions_for_sheet(mysqli $link, int $characterId): array
    {
        if ($characterId <= 0 || !hg_characters_table_exists($link, 'fact_actions')) return [];
        $stmt = $link->prepare("SELECT a.id, a.name, a.category, a.text, a.attribute_trait_id, a.skill_trait_id,
                                      a.difficulty_mode, a.fixed_difficulty, a.suggested_difficulty,
                                      a.min_difficulty, a.max_difficulty,
                                      attr.name AS attribute_name, skill.name AS skill_name,
                                      attribute_value.value AS attribute_value, skill_value.value AS skill_value
                               FROM fact_actions a
                               JOIN bridge_characters_traits attribute_value
                                 ON attribute_value.character_id = ? AND attribute_value.trait_id = a.attribute_trait_id
                               JOIN bridge_characters_traits skill_value
                                 ON skill_value.character_id = ? AND skill_value.trait_id = a.skill_trait_id
                               JOIN dim_traits attr ON attr.id = a.attribute_trait_id
                               JOIN dim_traits skill ON skill.id = a.skill_trait_id
                               WHERE attribute_value.value > 0 AND skill_value.value > 0
                               ORDER BY a.category ASC, a.name ASC");
        if (!$stmt) return [];
        $stmt->bind_param('ii', $characterId, $characterId);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        while ($result && ($row = $result->fetch_assoc())) $rows[] = $row;
        if ($result) $result->free();
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('hg_characters_fetch_maneuver_actions_for_sheet')) {
    function hg_characters_fetch_maneuver_actions_for_sheet(mysqli $link, int $characterId, int $systemId): array
    {
        if ($characterId <= 0 || $systemId <= 0
            || !hg_characters_table_exists($link, 'fact_power_rolls')
            || !hg_characters_table_exists($link, 'bridge_maneuvers_systems')
            || !hg_characters_table_exists($link, 'bridge_maneuvers_forms')
            || !hg_characters_table_exists($link, 'dim_forms')
            || !hg_characters_table_exists($link, 'fact_combat_maneuvers')) return [];
        $stmt = $link->prepare("SELECT DISTINCT m.id, m.name, 'Maniobras' AS category, m.text,
                                      pr.attribute_trait_id, pr.skill_trait_id, pr.difficulty_mode,
                                      pr.fixed_difficulty, NULL AS suggested_difficulty,
                                      pr.min_difficulty, pr.max_difficulty, pr.label AS roll_label, pr.roll_order,
                                      attr.name AS attribute_name, skill.name AS skill_name,
                                      attribute_value.value AS attribute_value, COALESCE(skill_value.value, 0) AS skill_value
                               FROM fact_combat_maneuvers m
                               JOIN fact_power_rolls pr ON pr.power_type = 'maneuver' AND pr.power_id = m.id
                               LEFT JOIN bridge_maneuvers_systems system_bridge
                                 ON system_bridge.maneuver_id = m.id AND system_bridge.system_id = ?
                               LEFT JOIN bridge_maneuvers_forms form_bridge ON form_bridge.maneuver_id = m.id
                               LEFT JOIN dim_forms maneuver_form
                                 ON maneuver_form.id = form_bridge.form_id AND maneuver_form.system_id = ?
                               JOIN bridge_characters_traits attribute_value
                                 ON attribute_value.character_id = ? AND attribute_value.trait_id = pr.attribute_trait_id
                                AND attribute_value.value > 0
                               LEFT JOIN bridge_characters_traits skill_value
                                 ON skill_value.character_id = ? AND skill_value.trait_id = pr.skill_trait_id
                               JOIN dim_traits attr ON attr.id = pr.attribute_trait_id
                               JOIN dim_traits skill ON skill.id = pr.skill_trait_id
                               WHERE (system_bridge.system_id IS NOT NULL OR maneuver_form.id IS NOT NULL)
                                 AND pr.status IN ('verified', 'manual')
                               ORDER BY m.name ASC, pr.roll_order ASC");
        if (!$stmt) return [];
        $stmt->bind_param('iiii', $systemId, $systemId, $characterId, $characterId);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        while ($result && ($row = $result->fetch_assoc())) $rows[] = $row;
        if ($result) $result->free();
        $stmt->close();
        return $rows;
    }
}
