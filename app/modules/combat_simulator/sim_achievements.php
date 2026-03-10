<?php

if (!function_exists('sim_achievements_catalog')) {
    function sim_achievements_catalog()
    {
        // Add/edit achievements here.
        return array(
            'first_blood' => array(
                'code' => 'first_blood',
                'title' => 'Primera sangre',
                'icon' => '/img/ui/icons/achievements_001_first_blood.jpg',
                'description' => 'Consigue el primer impacto del combate.'
            ),
            'miss_streak' => array(
                'code' => 'miss_streak',
                'title' => 'Mala punteria',
                'icon' => '/img/ui/icons/achievements_002_failure_master.jpg',
                'description' => 'Acumula 5 ataques fallidos seguidos.'
            ),
            'perfect' => array(
                'code' => 'perfect',
                'title' => 'Perfecto',
                'icon' => '/img/ui/icons/achievements_003_perfect.jpg',
                'description' => 'Gana sin recibir dano.'
            ),
            'bloodied_victory' => array(
                'code' => 'bloodied_victory',
                'title' => 'Victoria ensangrentada',
                'icon' => '/img/ui/icons/achievements_001_first_blood.jpg',
                'description' => 'Gana el combate con penalizador máximo de heridas (-5).'
            ),
            'comeback' => array(
                'code' => 'comeback',
                'title' => 'Remontada',
                'icon' => '/img/ui/icons/achievements_004_comeback_kid.jpg',
                'description' => 'Remonta desde desventaja y gana al limite.'
            ),
            'beat_the_master' => array(
                'code' => 'beat_the_master',
                'title' => 'Superar al maestro',
                'icon' => '/img/ui/icons/achievements_005_beat_the_master.jpg',
                'description' => 'Un personaje de menos estrellas vence a uno de mas estrellas.'
            ),
            'efficient' => array(
                'code' => 'efficient',
                'title' => 'Eficiente',
                'icon' => '/img/ui/icons/achievements_006_efficiency.jpg',
                'description' => 'Derroto a su rival en el turno 1.'
            ),
        );
    }
}

if (!function_exists('sim_achievements_strings')) {
    function sim_achievements_strings()
    {
        // Central text dictionary for achievement descriptions.
        return array(
            'first_blood_both_turn' => 'Golpe inaugural simultáneo en el Turno %turn%.',
            'first_blood_both' => 'Golpe inaugural simultáneo.',
            'first_blood_single_turn' => 'Primer impacto del combate en Turno %turn%.',
            'first_blood_single' => 'Primer impacto del combate.',
            'perfect' => 'Victoria sin recibir daño.',
            'bloodied_victory' => 'Ganó el combate sufriendo el penalizador máximo de heridas (-5).',
            'comeback' => 'Remontó desde una posición con desventaja y ganó el combate al límite.',
            'miss_streak' => 'Acumulo %count% ataques fallidos seguidos.',
            'beat_the_master' => 'Derrotó a un rival superior en estrellas (%winner_stars% vs %loser_stars%).',
            'efficient' => 'Derrotó a su rival en el Turno 1.',
        );
    }
}

if (!function_exists('sim_achievement_text')) {
    function sim_achievement_text($key, $vars = array(), $fallback = '')
    {
        $dict = sim_achievements_strings();
        $text = isset($dict[$key]) ? (string)$dict[$key] : (string)$fallback;
        if ($text === '') {
            return '';
        }

        if (is_array($vars) && !empty($vars)) {
            foreach ($vars as $name => $value) {
                $text = str_replace('%' . $name . '%', (string)$value, $text);
            }
        }
        return $text;
    }
}

if (!function_exists('sim_achievement_template')) {
    function sim_achievement_template($code)
    {
        $catalog = sim_achievements_catalog();
        if (isset($catalog[$code]) && is_array($catalog[$code])) {
            return $catalog[$code];
        }
        return array(
            'code' => (string)$code,
            'title' => (string)$code,
            'icon' => '/img/ui/icons/achievements_001_first_blood.jpg',
            'description' => ''
        );
    }
}

if (!function_exists('sim_achievement_make')) {
    function sim_achievement_make($code, $owner, $description, $meta = array())
    {
        $tpl = sim_achievement_template($code);
        $meta = is_array($meta) ? $meta : array();
        return array(
            'code' => (string)($tpl['code'] ?? $code),
            'title' => (string)($tpl['title'] ?? $code),
            'icon' => (string)($tpl['icon'] ?? ''),
            'owner' => (string)$owner,
            'desc' => (string)$description,
            'desc_key' => (string)($meta['desc_key'] ?? ''),
            'desc_vars' => (isset($meta['desc_vars']) && is_array($meta['desc_vars'])) ? $meta['desc_vars'] : array()
        );
    }
}

if (!function_exists('sim_achievements_evaluate')) {
    function sim_achievements_evaluate($context)
    {
        $ctx = is_array($context) ? $context : array();
        $isDraw = !empty($ctx['draw']);
        if ($isDraw) {
            return array();
        }

        $winnerKey = (string)($ctx['winner_key'] ?? '');
        $winnerLabel = (string)($ctx['winner_label'] ?? '');
        $winnerRemainingHp = (int)($ctx['winner_remaining_hp'] ?? 0);
        $winnerWoundsTaken = (int)($ctx['winner_wounds_taken'] ?? 0);
        $winnerWoundPenalty = (int)($ctx['winner_wound_penalty'] ?? 0);
        $turns = (int)($ctx['turns'] ?? 0);
        $turnData = (isset($ctx['turn_data']) && is_array($ctx['turn_data'])) ? $ctx['turn_data'] : array();
        $p1Stars = (int)($ctx['p1_stars'] ?? 0);
        $p2Stars = (int)($ctx['p2_stars'] ?? 0);

        $achievements = array();

        $firstBloodBy = (string)($turnData['first_blood_by'] ?? '');
        $firstBloodTurn = (int)($turnData['first_blood_turn'] ?? 0);
        if ($firstBloodBy === 'both') {
            if ($firstBloodTurn > 0) {
                $desc = sim_achievement_text('first_blood_both_turn', array('turn' => $firstBloodTurn), 'Golpe inaugural simultaneo.');
                $achievements[] = sim_achievement_make('first_blood', 'Ambos', $desc, array(
                    'desc_key' => 'first_blood_both_turn',
                    'desc_vars' => array('turn' => $firstBloodTurn)
                ));
            } else {
                $desc = sim_achievement_text('first_blood_both', array(), 'Golpe inaugural simultaneo.');
                $achievements[] = sim_achievement_make('first_blood', 'Ambos', $desc, array('desc_key' => 'first_blood_both'));
            }
        } elseif ($firstBloodBy === 'p1' || $firstBloodBy === 'p2') {
            $ownerLabel = ($firstBloodBy === 'p1') ? (string)($ctx['p1_label'] ?? '') : (string)($ctx['p2_label'] ?? '');
            if ($firstBloodTurn > 0) {
                $desc = sim_achievement_text('first_blood_single_turn', array('turn' => $firstBloodTurn), 'Primer impacto del combate.');
                $achievements[] = sim_achievement_make('first_blood', $ownerLabel, $desc, array(
                    'desc_key' => 'first_blood_single_turn',
                    'desc_vars' => array('turn' => $firstBloodTurn)
                ));
            } else {
                $desc = sim_achievement_text('first_blood_single', array(), 'Primer impacto del combate.');
                $achievements[] = sim_achievement_make('first_blood', $ownerLabel, $desc, array('desc_key' => 'first_blood_single'));
            }
        }

        if ($winnerWoundsTaken <= 0) {
            $desc = sim_achievement_text('perfect', array(), 'Victoria sin recibir dano.');
            $achievements[] = sim_achievement_make('perfect', $winnerLabel, $desc, array('desc_key' => 'perfect'));
        }
        if ($winnerWoundPenalty >= 5) {
            $desc = sim_achievement_text('bloodied_victory', array(), 'Gano el combate al limite con penalizador maximo de heridas (-5).');
            $achievements[] = sim_achievement_make('bloodied_victory', $winnerLabel, $desc, array('desc_key' => 'bloodied_victory'));
        }
        $hitsP1 = (int)($turnData['successful_attacks_p1'] ?? 0);
        $hitsP2 = (int)($turnData['successful_attacks_p2'] ?? 0);
        $damageTakenP1 = (int)($turnData['damage_taken_p1'] ?? 0);
        $damageTakenP2 = (int)($turnData['damage_taken_p2'] ?? 0);

        $winnerHits = 0;
        $loserHits = 0;
        $winnerDamageTaken = 0;
        $loserDamageTaken = 0;
        if ($winnerKey === 'p1') {
            $winnerHits = $hitsP1;
            $loserHits = $hitsP2;
            $winnerDamageTaken = $damageTakenP1;
            $loserDamageTaken = $damageTakenP2;
        } elseif ($winnerKey === 'p2') {
            $winnerHits = $hitsP2;
            $loserHits = $hitsP1;
            $winnerDamageTaken = $damageTakenP2;
            $loserDamageTaken = $damageTakenP1;
        }

        $hitGap = $loserHits - $winnerHits;
        $damageGap = $winnerDamageTaken - $loserDamageTaken;
        $comebackScore = $hitGap + $damageGap;
        $turnsBehind = 0;
        for ($t = 1; $t <= $turns; $t++) {
            $hp1Turn = (int)($turnData['hp1Turn' . $t] ?? 0);
            $hp2Turn = (int)($turnData['hp2Turn' . $t] ?? 0);
            if ($winnerKey === 'p1' && $hp1Turn < $hp2Turn) {
                $turnsBehind++;
            }
            if ($winnerKey === 'p2' && $hp2Turn < $hp1Turn) {
                $turnsBehind++;
            }
        }
        $requiredBehindTurns = max(2, (int)ceil($turns * 0.6));
        $sustainedBehindWin = (
            $winnerKey !== ''
            && $turns >= 4
            && $winnerHits < $loserHits
            && $hitGap >= 1
            && $turnsBehind >= $requiredBehindTurns
        );
        $isComeback = (
            $winnerKey !== ''
            && $winnerHits < $loserHits
            && $winnerDamageTaken > $loserDamageTaken
            && $hitGap >= 2
            && $damageGap >= 2
            && $comebackScore >= 4
        ) || $sustainedBehindWin;
        if ($isComeback) {
            $desc = sim_achievement_text('comeback', array(), 'Remonto desde desventaja y gano el combate al limite.');
            $achievements[] = sim_achievement_make('comeback', $winnerLabel, $desc, array('desc_key' => 'comeback'));
        }

        if ($winnerKey === 'p1' && $p1Stars > 0 && $p2Stars > 0 && $p1Stars < $p2Stars) {
            $desc = sim_achievement_text('beat_the_master', array('winner_stars' => $p1Stars, 'loser_stars' => $p2Stars), 'Derroto a un rival superior en estrellas.');
            $achievements[] = sim_achievement_make('beat_the_master', (string)($ctx['p1_label'] ?? ''), $desc, array(
                'desc_key' => 'beat_the_master',
                'desc_vars' => array('winner_stars' => $p1Stars, 'loser_stars' => $p2Stars)
            ));
        } elseif ($winnerKey === 'p2' && $p1Stars > 0 && $p2Stars > 0 && $p2Stars < $p1Stars) {
            $desc = sim_achievement_text('beat_the_master', array('winner_stars' => $p2Stars, 'loser_stars' => $p1Stars), 'Derroto a un rival superior en estrellas.');
            $achievements[] = sim_achievement_make('beat_the_master', (string)($ctx['p2_label'] ?? ''), $desc, array(
                'desc_key' => 'beat_the_master',
                'desc_vars' => array('winner_stars' => $p2Stars, 'loser_stars' => $p1Stars)
            ));
        }

        if ($turns === 1) {
            $desc = sim_achievement_text('efficient', array(), 'Derroto a su rival en el turno 1.');
            $achievements[] = sim_achievement_make('efficient', $winnerLabel, $desc, array('desc_key' => 'efficient'));
        }

        $maxMissStreakP1 = (int)($turnData['max_miss_streak_p1'] ?? 0);
        $maxMissStreakP2 = (int)($turnData['max_miss_streak_p2'] ?? 0);

        if ($maxMissStreakP1 >= 5) {
            $desc = sim_achievement_text('miss_streak', array('count' => $maxMissStreakP1), 'Acumulo ataques fallidos seguidos.');
            $achievements[] = sim_achievement_make('miss_streak', (string)($ctx['p1_label'] ?? ''), $desc, array(
                'desc_key' => 'miss_streak',
                'desc_vars' => array('count' => $maxMissStreakP1)
            ));
        }
        if ($maxMissStreakP2 >= 5) {
            $desc = sim_achievement_text('miss_streak', array('count' => $maxMissStreakP2), 'Acumulo ataques fallidos seguidos.');
            $achievements[] = sim_achievement_make('miss_streak', (string)($ctx['p2_label'] ?? ''), $desc, array(
                'desc_key' => 'miss_streak',
                'desc_vars' => array('count' => $maxMissStreakP2)
            ));
        }

        return $achievements;
    }
}

if (!function_exists('sim_achievements_hydrate_from_payload')) {
    function sim_achievements_hydrate_from_payload($payloadAchievements)
    {
        if (!is_array($payloadAchievements)) {
            return array();
        }

        $hydrated = array();
        foreach ($payloadAchievements as $item) {
            if (!is_array($item)) {
                continue;
            }
            $code = (string)($item['code'] ?? '');
            if ($code === '') {
                continue;
            }
            $tpl = sim_achievement_template($code);
            $descKey = (string)($item['desc_key'] ?? '');
            $descVars = (isset($item['desc_vars']) && is_array($item['desc_vars'])) ? $item['desc_vars'] : array();
            $descFromDict = ($descKey !== '') ? sim_achievement_text($descKey, $descVars, '') : '';
            $rawDesc = (string)($item['desc'] ?? '');
            $catalogDesc = (string)($tpl['description'] ?? '');
            $finalDesc = ($descFromDict !== '')
                ? $descFromDict
                : ($catalogDesc !== '' ? $catalogDesc : $rawDesc);

            $hydrated[] = array(
                'code' => $code,
                'title' => (string)($item['title'] ?? ($tpl['title'] ?? $code)),
                'icon' => (string)($item['icon'] ?? ($tpl['icon'] ?? '')),
                'owner' => (string)($item['owner'] ?? ''),
                'desc' => $finalDesc,
                'desc_key' => $descKey,
                'desc_vars' => $descVars
            );
        }
        return $hydrated;
    }
}

if (!function_exists('sim_achievements_render_html')) {
    function sim_achievements_render_html($achievements)
    {
        if (!is_array($achievements) || empty($achievements)) {
            return '';
        }

        $html = "<div class='sim-achievement-list'>";
        foreach ($achievements as $ach) {
            if (!is_array($ach)) {
                continue;
            }
            $title = htmlspecialchars((string)($ach['title'] ?? ''), ENT_QUOTES, 'UTF-8');
            if ($title === '') {
                continue;
            }

            $owner = trim((string)($ach['owner'] ?? ''));
            $desc = trim((string)($ach['desc'] ?? ''));
            $ownerHtml = htmlspecialchars($owner, ENT_QUOTES, 'UTF-8');
            $descHtml = htmlspecialchars($desc, ENT_QUOTES, 'UTF-8');
            $text = ($owner !== '')
                ? ('<strong>' . $ownerHtml . '</strong>' . ($desc !== '' ? ': ' . $descHtml : ''))
                : $descHtml;

            $icon = htmlspecialchars((string)($ach['icon'] ?? ''), ENT_QUOTES, 'UTF-8');
            if ($icon === '') {
                $icon = '/img/ui/icons/achievements_001_first_blood.jpg';
            }

            $html .= "<div class='sim-achievement-item'>"
                . "<div class='sim-achievement-icon-wrap'><img class='sim-achievement-icon' src='{$icon}' alt=''></div>"
                . "<div class='sim-achievement-copy'>"
                . "<div class='sim-achievement-title'>{$title}</div>"
                . "<div class='sim-achievement-desc'>{$text}</div>"
                . "</div>"
                . "</div>";
        }
        $html .= '</div><br/>';
        return $html;
    }
}
