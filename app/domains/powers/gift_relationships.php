<?php

require_once __DIR__ . '/../../helpers/schema_introspection.php';

/**
 * Gift-to-Gift relationships.
 *
 * `mechanics_from` is a soft inheritance relationship: the source Gift keeps
 * its own identity, description, rank, system and availability, but may inherit
 * roll fields and mechanics text from another Gift when its local fields are
 * empty.
 */

function hg_powers_gift_relations_table_exists(mysqli $link): bool
{
    return hg_table_exists($link, 'bridge_gifts_relations');
}

function hg_powers_fetch_gift_relation(mysqli $link, int $giftId, string $relationType): ?array
{
    if ($giftId <= 0 || !hg_powers_gift_relations_table_exists($link)) return null;
    if (!in_array($relationType, ['mechanics_from', 'variant_of'], true)) return null;

    $sql = "SELECT
                r.id AS relation_id,
                r.gift_id,
                r.related_gift_id,
                r.relation_type,
                r.notes AS relation_notes,
                g.pretty_id AS related_pretty_id,
                g.name AS related_name,
                g.attribute_name AS related_attribute_name,
                g.ability_name AS related_ability_name,
                g.mechanics_text AS related_mechanics_text
            FROM bridge_gifts_relations r
            INNER JOIN fact_gifts g ON g.id = r.related_gift_id
            WHERE r.gift_id = ?
              AND r.relation_type = ?
            LIMIT 1";

    $stmt = $link->prepare($sql);
    if (!$stmt) return null;

    $stmt->bind_param('is', $giftId, $relationType);
    $stmt->execute();
    $rs = $stmt->get_result();
    $row = $rs ? $rs->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

/**
 * Returns Gifts that point at the requested Gift.
 *
 * This is intentionally the reverse side of the relation: if Ishin Deshin
 * mechanics_from Habla Mental, the Habla Mental page can advertise Ishin
 * Deshin as a related Gift. The same model supports several variants pointing
 * at a shared canonical Gift such as Roce Materno.
 */
function hg_powers_fetch_gift_dependents(mysqli $link, int $giftId): array
{
    if ($giftId <= 0 || !hg_powers_gift_relations_table_exists($link)) return [];

    $sql = "SELECT
                r.id AS relation_id,
                r.gift_id AS related_gift_id,
                r.relation_type,
                r.notes AS relation_notes,
                g.pretty_id AS related_pretty_id,
                g.name AS related_name,
                g.rank AS related_rank
            FROM bridge_gifts_relations r
            INNER JOIN fact_gifts g ON g.id = r.gift_id
            WHERE r.related_gift_id = ?
              AND r.relation_type IN ('mechanics_from', 'variant_of')
            ORDER BY CAST(g.rank AS UNSIGNED), g.name, g.id";

    $stmt = $link->prepare($sql);
    if (!$stmt) return [];

    $stmt->bind_param('i', $giftId);
    $stmt->execute();
    $rs = $stmt->get_result();
    $rows = [];
    while ($rs && ($row = $rs->fetch_assoc())) $rows[] = $row;
    $stmt->close();

    return $rows;
}

function hg_powers_resolve_gift_soft_mechanics(mysqli $link, array $gift, int $maxDepth = 8): array
{
    $gift['attribute_resolved'] = (string)($gift['attribute_name'] ?? '');
    $gift['ability_resolved'] = (string)($gift['ability_name'] ?? '');
    $gift['mechanics_resolved'] = (string)($gift['mechanics_resolved'] ?? $gift['mechanics_text'] ?? '');
    $gift['mechanics_source_id'] = null;
    $gift['mechanics_source_name'] = '';
    $gift['mechanics_source_pretty_id'] = '';
    $gift['mechanics_relation_notes'] = '';
    $gift['mechanics_relation_cycle'] = false;

    $giftId = (int)($gift['id'] ?? 0);
    if ($giftId <= 0 || !hg_powers_gift_relations_table_exists($link)) return $gift;

    $visited = [$giftId => true];
    $currentId = $giftId;

    for ($depth = 0; $depth < $maxDepth; $depth++) {
        $relation = hg_powers_fetch_gift_relation($link, $currentId, 'mechanics_from');
        if (!$relation) break;

        $targetId = (int)($relation['related_gift_id'] ?? 0);
        if ($targetId <= 0) break;

        if (isset($visited[$targetId])) {
            $gift['mechanics_relation_cycle'] = true;
            break;
        }
        $visited[$targetId] = true;

        if ($gift['mechanics_source_id'] === null) {
            $gift['mechanics_source_id'] = $targetId;
            $gift['mechanics_source_name'] = (string)($relation['related_name'] ?? '');
            $gift['mechanics_source_pretty_id'] = (string)($relation['related_pretty_id'] ?? '');
            $gift['mechanics_relation_notes'] = (string)($relation['relation_notes'] ?? '');
        }

        if (trim($gift['attribute_resolved']) === '') {
            $gift['attribute_resolved'] = (string)($relation['related_attribute_name'] ?? '');
        }
        if (trim($gift['ability_resolved']) === '') {
            $gift['ability_resolved'] = (string)($relation['related_ability_name'] ?? '');
        }
        if (trim($gift['mechanics_resolved']) === '') {
            $gift['mechanics_resolved'] = (string)($relation['related_mechanics_text'] ?? '');
        }

        $currentId = $targetId;
    }

    return $gift;
}
