<?php
// Lightweight, current-state activity index for public content.

if (!function_exists('hg_content_touch')) {
    /**
     * Mark a public entity as recently updated.
     *
     * This intentionally stores one row per entity, not an edit history. The
     * helper is best-effort: a failure to update the home feed must never turn
     * a successful admin save into a failed request.
     */
    function hg_content_touch(mysqli $link, string $entityType, int $entityId): bool
    {
        static $statement = null;

        $entityType = strtolower(trim($entityType));
        if ($entityId <= 0 || !preg_match('/^[a-z][a-z0-9_]{0,49}$/', $entityType)) {
            return false;
        }

        if ($statement === null) {
            $statement = $link->prepare(
                'INSERT INTO fact_content_updates (entity_type, entity_id, updated_at) '
                . 'VALUES (?, ?, CURRENT_TIMESTAMP) '
                . 'ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP'
            );
        }

        if (!$statement) {
            return false;
        }

        $statement->bind_param('si', $entityType, $entityId);
        return (bool)@$statement->execute();
    }
}

if (!function_exists('hg_content_touch_at')) {
    /** Preserve a content item's original update time while backfilling. */
    function hg_content_touch_at(mysqli $link, string $entityType, int $entityId, string $updatedAt): bool
    {
        static $statement = null;

        $entityType = strtolower(trim($entityType));
        $updatedAt = trim($updatedAt);
        if ($entityId <= 0 || $updatedAt === '' || !preg_match('/^[a-z][a-z0-9_]{0,49}$/', $entityType)) {
            return false;
        }

        if ($statement === null) {
            $statement = $link->prepare(
                'INSERT INTO fact_content_updates (entity_type, entity_id, updated_at) '
                . 'VALUES (?, ?, ?) '
                . 'ON DUPLICATE KEY UPDATE updated_at = GREATEST(updated_at, VALUES(updated_at))'
            );
        }

        if (!$statement) {
            return false;
        }

        $statement->bind_param('sis', $entityType, $entityId, $updatedAt);
        return (bool)@$statement->execute();
    }
}
if (!function_exists('hg_content_touch_many')) {
    function hg_content_touch_many(mysqli $link, string $entityType, array $entityIds): void
    {
        foreach (array_unique(array_map('intval', $entityIds)) as $entityId) {
            hg_content_touch($link, $entityType, $entityId);
        }
    }
}

if (!function_exists('hg_content_entity_type_for_table')) {
    function hg_content_entity_type_for_table(string $table): ?string
    {
        static $types = [
            'dim_archetypes' => 'archetype',
            'dim_auspices' => 'auspice',
            'dim_breeds' => 'breed',
            'dim_character_conditions' => 'condition',
            'dim_forms' => 'form',
            'dim_merits_flaws' => 'merit_flaw',
            'dim_traits' => 'trait',
            'fact_combat_maneuvers' => 'maneuver',
            'fact_misc_systems' => 'system_detail',
            'dim_chapters' => 'chapter',
            'dim_chronicles' => 'chronicle',
            'fact_characters' => 'character',
            'dim_organizations' => 'organization',
            'dim_groups' => 'group',
            'dim_realities' => 'reality',
            'dim_systems' => 'system',
            'dim_tribes' => 'tribe',
            'dim_totems' => 'totem',
            'dim_maps' => 'map',
            'fact_docs' => 'document',
            'fact_gifts' => 'gift',
            'fact_items' => 'item',
            'fact_rites' => 'rite',
            'fact_discipline_powers' => 'discipline',
            'fact_timeline_events' => 'timeline_event',
            'dim_soundtracks' => 'soundtrack',
            'dim_seasons' => 'season',
        ];

        return $types[$table] ?? null;
    }
}
if (!function_exists('hg_content_touch_table')) {
    /** Map catalog table names used by generic CRUD controllers to feed types. */
    function hg_content_touch_table(mysqli $link, string $table, int $entityId): bool
    {
        $entityType = hg_content_entity_type_for_table($table);


        return $entityType !== null
            ? hg_content_touch($link, $entityType, $entityId)
            : false;
    }
}
