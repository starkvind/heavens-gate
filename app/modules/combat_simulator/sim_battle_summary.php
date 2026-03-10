<?php

if (!function_exists('sim_battle_summary_clean_legacy')) {
    function sim_battle_summary_clean_legacy($value)
    {
        $decoded = html_entity_decode((string)$value, ENT_QUOTES, 'UTF-8');
        $clean = strip_tags($decoded, '<b><strong>');
        $clean = preg_replace('/<\s*strong[^>]*>/i', '<b>', $clean);
        $clean = preg_replace('/<\s*\/\s*strong\s*>/i', '</b>', $clean);
        $clean = preg_replace('/<\s*b[^>]*>/i', '<b>', $clean);
        $clean = preg_replace('/<\s*\/\s*b\s*>/i', '</b>', $clean);
        return trim((string)$clean);
    }
}

if (!function_exists('sim_battle_winner_name_from_row')) {
    function sim_battle_winner_name_from_row($row)
    {
        if (!is_array($row)) {
            return '';
        }

        $outcome = strtolower((string)($row['outcome'] ?? ''));
        if ($outcome === 'draw') {
            return '';
        }

        $winnerId = (int)($row['winner_character_id'] ?? 0);
        $fighterOneId = (int)($row['fighter_one_character_id'] ?? 0);
        $fighterTwoId = (int)($row['fighter_two_character_id'] ?? 0);
        $fighterOneAlias = trim((string)($row['fighter_one_alias_snapshot'] ?? ''));
        $fighterTwoAlias = trim((string)($row['fighter_two_alias_snapshot'] ?? ''));

        if ($winnerId > 0) {
            if ($winnerId === $fighterOneId && $fighterOneAlias !== '') {
                return $fighterOneAlias;
            }
            if ($winnerId === $fighterTwoId && $fighterTwoAlias !== '') {
                return $fighterTwoAlias;
            }
        }

        $legacy = sim_battle_summary_clean_legacy((string)($row['winner_summary'] ?? ''));
        $legacyPlain = trim(strip_tags($legacy));
        if (stripos($legacyPlain, 'Ganador:') === 0) {
            $legacyPlain = trim(substr($legacyPlain, 8));
        }
        return $legacyPlain;
    }
}

if (!function_exists('sim_battle_summary_html_from_row')) {
    function sim_battle_summary_html_from_row($row)
    {
        if (!is_array($row)) {
            return '';
        }

        $outcome = strtolower((string)($row['outcome'] ?? ''));
        if ($outcome === 'draw') {
            return '<b>Empate</b>';
        }

        $winnerName = sim_battle_winner_name_from_row($row);
        if ($winnerName !== '') {
            return '<b>Ganador:</b> ' . htmlspecialchars($winnerName, ENT_QUOTES, 'UTF-8');
        }

        return sim_battle_summary_clean_legacy((string)($row['winner_summary'] ?? ''));
    }
}

