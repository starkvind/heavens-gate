<?php

if (!function_exists('hg_gc_runtime_asset_versions')) {
    function hg_gc_runtime_asset_versions(): array
    {
        return [
            'bootstrap' => '20260712-runtime-hotfix6',
            'core' => '20260712-runtime-hotfix6',
            'data' => '20260712-runtime-hotfix6',
            'packs' => '20260712-runtime-hotfix6',
            'shop' => '20260712-runtime-hotfix6',
            'collection' => '20260712-runtime-hotfix6',
            'memory' => '20260712-runtime-hotfix6',
            'cards' => '20260712-runtime-hotfix6',
            'teams' => '20260712-runtime-hotfix6',
            'combat' => '20260712-runtime-hotfix6',
            'wrapper' => '20260712-runtime-hotfix6',
            'css' => '20260712-runtime-hotfix6',
        ];
    }
}

if (!function_exists('hg_gc_runtime_script')) {
    function hg_gc_runtime_script(string $path, string $versionGroup = 'bootstrap'): string
    {
        $versions = hg_gc_runtime_asset_versions();
        $version = $versions[$versionGroup] ?? ($versions['bootstrap'] ?? '1');
        return $path . '?v=' . rawurlencode($version);
    }
}

if (!function_exists('hg_gc_runtime_boot_scripts')) {
    function hg_gc_runtime_boot_scripts(string $view = 'gacha', bool $mobile = false): array
    {
        $view = strtolower(trim($view));
        $scripts = [
            hg_gc_runtime_script('/assets/js/card-game/bootstrap/game-card-features.js'),
            hg_gc_runtime_script('/assets/js/card-game/bootstrap/game-card-loader.js'),
            hg_gc_runtime_script('/assets/js/card-game/core/game-card-utils.js', 'core'),
            hg_gc_runtime_script('/assets/js/card-game/core/game-card-storage.js', 'core'),
            hg_gc_runtime_script('/assets/js/card-game/core/game-card-state.js', 'core'),
            hg_gc_runtime_script('/assets/js/card-game/core/game-card-dom.js', 'core'),
            hg_gc_runtime_script('/assets/js/card-game/core/game-card-events.js', 'core'),
            hg_gc_runtime_script('/assets/js/card-game/data/game-card-copy-model.js', 'data'),
            hg_gc_runtime_script('/assets/js/card-game/data/game-card-governance.js', 'data'),
            hg_gc_runtime_script('/assets/js/card-game/data/game-card-rules.js', 'data'),
            hg_gc_runtime_script('/assets/js/card-game/data/game-card-migrations.js', 'data'),
            hg_gc_runtime_script('/assets/js/card-game/packs/game-card-packs.js', 'packs'),
            hg_gc_runtime_script('/assets/js/card-game/shop/game-card-shop.js', 'shop'),
        ];

        $needsCollection = $mobile || in_array($view, ['collection', 'combat'], true);
        $needsCombat = $mobile || $view === 'combat';

        if ($needsCollection) {
            $scripts[] = hg_gc_runtime_script('/assets/js/card-game/collection/game-card-collection.js', 'collection');
            $scripts[] = hg_gc_runtime_script('/assets/js/card-game/collection/game-card-collection-filters.js', 'collection');
            $scripts[] = hg_gc_runtime_script('/assets/js/card-game/collection/game-card-collection-render.js', 'collection');
            $scripts[] = hg_gc_runtime_script('/assets/js/card-game/collection/game-card-collection-import-export.js', 'collection');
            $scripts[] = hg_gc_runtime_script('/assets/js/card-game/memory/game-card-memory.js', 'memory');
            $scripts[] = hg_gc_runtime_script('/assets/js/card-game/memory/game-card-memory-render.js', 'memory');
            $scripts[] = hg_gc_runtime_script('/assets/js/card-game/cards/game-card-skills.js', 'cards');
            $scripts[] = hg_gc_runtime_script('/assets/js/card-game/cards/game-card-evolve.js', 'cards');
            $scripts[] = hg_gc_runtime_script('/assets/js/card-game/cards/game-card-improve.js', 'cards');
            $scripts[] = hg_gc_runtime_script('/assets/js/card-game/cards/game-card-recycle.js', 'cards');
            $scripts[] = hg_gc_runtime_script('/assets/js/card-game/teams/game-card-teams.js', 'teams');
            $scripts[] = hg_gc_runtime_script('/assets/js/card-game/teams/game-card-loadout.js', 'teams');
        }

        if ($needsCombat) {
            $scripts[] = hg_gc_runtime_script('/assets/js/card-game/combat/game-card-combat-state.js', 'combat');
            $scripts[] = hg_gc_runtime_script('/assets/js/card-game/combat/game-card-combat-rules.js', 'combat');
            $scripts[] = hg_gc_runtime_script('/assets/js/card-game/combat/game-card-combat-ai.js', 'combat');
            $scripts[] = hg_gc_runtime_script('/assets/js/card-game/combat/game-card-combat-animations.js', 'combat');
            $scripts[] = hg_gc_runtime_script('/assets/js/card-game/combat/game-card-combat-render.js', 'combat');
            $scripts[] = hg_gc_runtime_script('/assets/js/card-game/combat/game-card-combat.js', 'combat');
            $scripts[] = hg_gc_runtime_script('/assets/js/card-game/combat/game-card-combat-modes.js', 'combat');
            $scripts[] = hg_gc_runtime_script('/assets/js/card-game/combat/modes/game-card-combat-training.js', 'combat');
            $scripts[] = hg_gc_runtime_script('/assets/js/card-game/combat/modes/game-card-combat-daily-boss.js', 'combat');
        }

        $scripts[] = hg_gc_runtime_script('/assets/js/card-game/bootstrap/game-card-app.js');
        $scripts[] = hg_gc_runtime_script('/assets/js/card-game/bootstrap/game-card-app-runtime.js');

        return $scripts;
    }
}

if (!function_exists('hg_gc_runtime_entry_script')) {
    function hg_gc_runtime_entry_script(): string
    {
        return hg_gc_runtime_script('/assets/js/card-game/bootstrap/game-card-runtime.js', 'wrapper');
    }
}

if (!function_exists('hg_gc_runtime_wrapper_script')) {
    function hg_gc_runtime_wrapper_script(): string
    {
        return hg_gc_runtime_entry_script();
    }
}
