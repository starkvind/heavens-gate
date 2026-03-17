<?php
include_once('sim_character_scope.php');
include_once('app/helpers/character_avatar.php');

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

if (!function_exists('sim_tournament_h')) {
    function sim_tournament_h($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('sim_tournament_is_admin')) {
    function sim_tournament_is_admin()
    {
        $sessionAdmin = (!empty($_SESSION) && is_array($_SESSION) && !empty($_SESSION['is_admin']));
        if ($sessionAdmin) {
            return true;
        }
        $cookieValue = isset($_COOKIE['is_admin']) ? strtoupper(trim((string)$_COOKIE['is_admin'])) : '';
        return in_array($cookieValue, array('1', 'TRUE', 'YES', 'ON'), true);
    }
}

if (!function_exists('sim_tournament_table_exists')) {
    function sim_tournament_table_exists($link, $tableName)
    {
        if (function_exists('sim_table_exists')) {
            return sim_table_exists($link, $tableName);
        }
        $safe = mysql_real_escape_string((string)$tableName, $link);
        $rs = mysql_query("SHOW TABLES LIKE '$safe'", $link);
        return ($rs && mysql_num_rows($rs) > 0);
    }
}

if (!function_exists('sim_tournament_star_count')) {
    function sim_tournament_star_count($score)
    {
        $stars = (int)ceil(((int)$score) / 7);
        if ($stars < 1) { $stars = 1; }
        if ($stars > 5) { $stars = 5; }
        return $stars;
    }
}

if (!function_exists('sim_tournament_seed_positions')) {
    function sim_tournament_seed_positions($size)
    {
        $size = (int)$size;
        if ($size < 2 || ($size & ($size - 1)) !== 0) {
            return array(1, 2);
        }
        $positions = array(1, 2);
        for ($n = 4; $n <= $size; $n *= 2) {
            $next = array();
            foreach ($positions as $p) {
                $next[] = $p;
                $next[] = $n + 1 - $p;
            }
            $positions = $next;
        }
        return $positions;
    }
}

if (!function_exists('sim_tournament_active_season')) {
    function sim_tournament_active_season($link)
    {
        $out = array('id' => 0, 'name' => '', 'description' => '', 'character_limit' => 35);
        if (!sim_tournament_table_exists($link, 'fact_sim_seasons')) {
            return $out;
        }

        $query = "SELECT id, COALESCE(name, '') AS name, COALESCE(description, '') AS description, COALESCE(character_limit, 35) AS character_limit
                  FROM fact_sim_seasons
                  WHERE is_active = 1
                  ORDER BY updated_at DESC, id DESC
                  LIMIT 1";
        $rs = mysql_query($query, $link);
        if (!$rs || mysql_num_rows($rs) === 0) {
            return $out;
        }

        $row = mysql_fetch_array($rs);
        $out['id'] = (int)($row['id'] ?? 0);
        $out['name'] = (string)($row['name'] ?? '');
        $out['description'] = (string)($row['description'] ?? '');
        $limit = (int)($row['character_limit'] ?? 35);
        if ($limit < 1) { $limit = 1; }
        if ($limit > 200) { $limit = 200; }
        $out['character_limit'] = $limit;
        return $out;
    }
}

if (!function_exists('sim_tournament_roster')) {
    function sim_tournament_roster($link, $activeSeasonId, $rosterLimit)
    {
        $activeSeasonId = (int)$activeSeasonId;
        $rosterLimit = (int)$rosterLimit;
        if ($rosterLimit < 2) { $rosterLimit = 2; }
        if ($rosterLimit > 400) { $rosterLimit = 400; }

        $cronicaNotInSQL = sim_chronicle_not_in_sql('c.chronicle_id');
        $seasonJoin = '';
        if ($activeSeasonId > 0 && sim_tournament_table_exists($link, 'bridge_battle_sim_characters_seasons')) {
            $seasonJoin = "INNER JOIN bridge_battle_sim_characters_seasons sbs ON sbs.character_id = v.id AND sbs.season_id = $activeSeasonId";
        }

        $query = "
            SELECT
                v.id,
                v.nombre,
                v.alias,
                v.img,
                COALESCE(v.fuerza, 0) AS fuerza,
                COALESCE(v.destreza, 0) AS destreza,
                COALESCE(v.pelea, 0) AS pelea,
                COALESCE(v.armascc, 0) AS armascc,
                COALESCE(v.armasdefuego, 0) AS armasdefuego,
                COALESCE(v.esquivar, 0) AS esquivar,
                COALESCE(v.resistencia, 0) AS resistencia
            FROM vw_sim_characters v
            INNER JOIN fact_characters c ON c.id = v.id
            $seasonJoin
            WHERE v.kes LIKE 'pj' $cronicaNotInSQL
            ORDER BY v.alias ASC
            LIMIT $rosterLimit
        ";

        $rows = array();
        $rs = mysql_query($query, $link);
        if (!$rs) {
            return $rows;
        }

        while ($row = mysql_fetch_array($rs)) {
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $rankScore = (int)($row['fuerza'] ?? 0)
                + (int)($row['destreza'] ?? 0)
                + (int)($row['pelea'] ?? 0)
                + (int)($row['armascc'] ?? 0)
                + (int)($row['armasdefuego'] ?? 0)
                + (int)($row['esquivar'] ?? 0)
                + (int)($row['resistencia'] ?? 0);

            $imgRaw = (string)($row['img'] ?? '');
            $img = function_exists('hg_character_avatar_url') ? hg_character_avatar_url($imgRaw, '') : $imgRaw;

            $rows[] = array(
                'character_id' => $id,
                'name' => (string)($row['nombre'] ?? ''),
                'alias' => (string)($row['alias'] ?? ''),
                'img' => (string)$img,
                'rank_score' => $rankScore,
                'rank_stars' => sim_tournament_star_count($rankScore),
            );
        }
        mysql_free_result($rs);
        return $rows;
    }
}

if (!function_exists('sim_tournament_create')) {
    function sim_tournament_create($roster, $size, $seedMode, $seasonId = 0, $tournamentName = '')
    {
        $size = (int)$size;
        if ($size < 4) { $size = 4; }
        if ($size > 32) { $size = 32; }
        $seedMode = ($seedMode === 'random') ? 'random' : 'rank';
        $seasonId = (int)$seasonId;
        $tournamentName = trim((string)$tournamentName);

        $entries = array_values($roster);
        if ($seedMode === 'rank') {
            usort($entries, function ($a, $b) {
                $sa = (int)($a['rank_score'] ?? 0);
                $sb = (int)($b['rank_score'] ?? 0);
                if ($sa === $sb) {
                    return strcmp((string)($a['alias'] ?? ''), (string)($b['alias'] ?? ''));
                }
                return ($sa > $sb) ? -1 : 1;
            });
        } else {
            shuffle($entries);
        }

        $entryById = array();
        $entryId = 1;
        foreach ($entries as $e) {
            $e['entry_id'] = $entryId;
            $entryById[$entryId] = $e;
            $entryId++;
        }

        $positions = sim_tournament_seed_positions($size);
        $slots = array_fill(1, $size, 0);
        $maxFill = min($size, count($entryById));
        for ($i = 0; $i < $maxFill; $i++) {
            $slot = (int)$positions[$i];
            $slots[$slot] = $i + 1;
        }

        $roundCount = (int)round(log($size, 2));
        $matches = array();
        for ($r = 1; $r <= $roundCount; $r++) {
            $matchCount = (int)($size / pow(2, $r));
            $matches[$r] = array();
            for ($m = 1; $m <= $matchCount; $m++) {
                $p1 = 0;
                $p2 = 0;
                if ($r === 1) {
                    $p1 = (int)($slots[($m * 2) - 1] ?? 0);
                    $p2 = (int)($slots[$m * 2] ?? 0);
                }
                $matches[$r][$m] = array(
                    'p1' => $p1,
                    'p2' => $p2,
                    'winner' => 0,
                    'status' => 'pending',
                    'summary' => '',
                    'battle_log_id' => 0,
                    'resolved_at' => '',
                );
            }
        }

        $baseKey = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $tournamentName));
        $baseKey = trim((string)$baseKey, '-');
        if ($baseKey === '') {
            $baseKey = 'torneo';
        }

        return array(
            'id' => $baseKey . '_' . date('Ymd_His') . '_' . mt_rand(1000, 9999),
            'name' => ($tournamentName !== '') ? $tournamentName : 'Torneo',
            'created_at' => date('Y-m-d H:i:s'),
            'size' => $size,
            'seed_mode' => $seedMode,
            'season_id' => $seasonId,
            'round_count' => $roundCount,
            'entries' => $entryById,
            'matches' => $matches,
            'champion' => 0,
            'history' => array(),
        );
    }
}

if (!function_exists('sim_tournament_reset_match')) {
    function sim_tournament_reset_match(&$match)
    {
        $match['winner'] = 0;
        $match['status'] = 'pending';
        $match['summary'] = '';
        $match['battle_log_id'] = 0;
        $match['resolved_at'] = '';
    }
}

if (!function_exists('sim_tournament_match_locked')) {
    function sim_tournament_match_locked($match)
    {
        if ((int)($match['winner'] ?? 0) > 0) {
            return true;
        }
        $status = (string)($match['status'] ?? 'pending');
        return in_array($status, array('bye', 'void'), true);
    }
}

if (!function_exists('sim_tournament_sync')) {
    function sim_tournament_sync(&$tournament)
    {
        if (!is_array($tournament) || !isset($tournament['matches'], $tournament['round_count'])) {
            return;
        }

        $roundCount = (int)$tournament['round_count'];
        if ($roundCount < 1) {
            return;
        }

        $changed = true;
        $guard = 0;
        while ($changed && $guard < 64) {
            $changed = false;
            $guard++;

            for ($r = 2; $r <= $roundCount; $r++) {
                $matchCount = isset($tournament['matches'][$r]) ? count($tournament['matches'][$r]) : 0;
                for ($m = 1; $m <= $matchCount; $m++) {
                    $prevA = (int)($tournament['matches'][$r - 1][($m * 2) - 1]['winner'] ?? 0);
                    $prevB = (int)($tournament['matches'][$r - 1][$m * 2]['winner'] ?? 0);

                    if ((int)$tournament['matches'][$r][$m]['p1'] !== $prevA) {
                        $tournament['matches'][$r][$m]['p1'] = $prevA;
                        $changed = true;
                    }
                    if ((int)$tournament['matches'][$r][$m]['p2'] !== $prevB) {
                        $tournament['matches'][$r][$m]['p2'] = $prevB;
                        $changed = true;
                    }

                    $p1 = (int)($tournament['matches'][$r][$m]['p1'] ?? 0);
                    $p2 = (int)($tournament['matches'][$r][$m]['p2'] ?? 0);
                    $winner = (int)($tournament['matches'][$r][$m]['winner'] ?? 0);
                    $status = (string)($tournament['matches'][$r][$m]['status'] ?? 'pending');
                    if ($winner > 0 && $winner !== $p1 && $winner !== $p2) {
                        sim_tournament_reset_match($tournament['matches'][$r][$m]);
                        $changed = true;
                    } elseif (in_array($status, array('bye', 'void'), true) && $p1 > 0 && $p2 > 0) {
                        sim_tournament_reset_match($tournament['matches'][$r][$m]);
                        $changed = true;
                    }
                }
            }

            for ($r = 1; $r <= $roundCount; $r++) {
                if (!isset($tournament['matches'][$r]) || !is_array($tournament['matches'][$r])) {
                    continue;
                }
                foreach ($tournament['matches'][$r] as $m => $match) {
                    $winner = (int)($match['winner'] ?? 0);
                    $p1 = (int)($match['p1'] ?? 0);
                    $p2 = (int)($match['p2'] ?? 0);
                    if ($winner > 0) {
                        continue;
                    }

                    $canResolveBye = false;
                    if ($r === 1) {
                        $canResolveBye = true;
                    } else {
                        $srcA = $tournament['matches'][$r - 1][((int)$m * 2) - 1] ?? array();
                        $srcB = $tournament['matches'][$r - 1][(int)$m * 2] ?? array();
                        $canResolveBye = sim_tournament_match_locked($srcA) && sim_tournament_match_locked($srcB);
                    }
                    if (!$canResolveBye) {
                        continue;
                    }

                    if ($p1 > 0 && $p2 <= 0) {
                        $tournament['matches'][$r][$m]['winner'] = $p1;
                        $tournament['matches'][$r][$m]['status'] = 'bye';
                        $tournament['matches'][$r][$m]['summary'] = 'Pase automatico (bye)';
                        $tournament['matches'][$r][$m]['battle_log_id'] = 0;
                        $tournament['matches'][$r][$m]['resolved_at'] = date('Y-m-d H:i:s');
                        $changed = true;
                    } elseif ($p2 > 0 && $p1 <= 0) {
                        $tournament['matches'][$r][$m]['winner'] = $p2;
                        $tournament['matches'][$r][$m]['status'] = 'bye';
                        $tournament['matches'][$r][$m]['summary'] = 'Pase automatico (bye)';
                        $tournament['matches'][$r][$m]['battle_log_id'] = 0;
                        $tournament['matches'][$r][$m]['resolved_at'] = date('Y-m-d H:i:s');
                        $changed = true;
                    } elseif ($r === 1 && $p1 <= 0 && $p2 <= 0) {
                        $tournament['matches'][$r][$m]['status'] = 'void';
                        $tournament['matches'][$r][$m]['summary'] = 'Sin participantes';
                        $tournament['matches'][$r][$m]['battle_log_id'] = 0;
                        $tournament['matches'][$r][$m]['resolved_at'] = date('Y-m-d H:i:s');
                        $changed = true;
                    }
                }
            }
        }

        $tournament['champion'] = (int)($tournament['matches'][$roundCount][1]['winner'] ?? 0);
    }
}

if (!function_exists('sim_tournament_find_pending')) {
    function sim_tournament_find_pending($tournament, $onlyRound = 0)
    {
        if (!is_array($tournament) || !isset($tournament['matches'], $tournament['round_count'])) {
            return null;
        }

        $roundCount = (int)$tournament['round_count'];
        for ($r = 1; $r <= $roundCount; $r++) {
            if ($onlyRound > 0 && $r !== (int)$onlyRound) {
                continue;
            }
            if (!isset($tournament['matches'][$r])) {
                continue;
            }
            foreach ($tournament['matches'][$r] as $m => $match) {
                $winner = (int)($match['winner'] ?? 0);
                $p1 = (int)($match['p1'] ?? 0);
                $p2 = (int)($match['p2'] ?? 0);
                if ($winner <= 0 && $p1 > 0 && $p2 > 0) {
                    return array('round' => $r, 'match' => (int)$m);
                }
            }
        }
        return null;
    }
}

if (!function_exists('sim_tournament_entry_name')) {
    function sim_tournament_entry_name($entry)
    {
        if (!is_array($entry)) {
            return '';
        }
        $alias = trim((string)($entry['alias'] ?? ''));
        if ($alias !== '') {
            return $alias;
        }
        return trim((string)($entry['name'] ?? ''));
    }
}

if (!function_exists('sim_tournament_slot_avatar_html')) {
    function sim_tournament_slot_avatar_html($imageUrl, $name, $isEmpty = false)
    {
        $imageUrl = trim((string)$imageUrl);
        $name = trim((string)$name);
        if ($imageUrl !== '') {
            return '<img class="sim-tournament-avatar" src="' . sim_tournament_h($imageUrl) . '" alt="' . sim_tournament_h($name) . '">';
        }
        $initial = '-';
        if (!$isEmpty && $name !== '') {
            $initial = strtoupper(substr($name, 0, 1));
        }
        return '<span class="sim-tournament-avatar sim-tournament-avatar-fallback">' . sim_tournament_h($initial) . '</span>';
    }
}

if (!function_exists('sim_tournament_render_match_card')) {
    function sim_tournament_render_match_card($tournament, $matchNumber, $match)
    {
        if (!is_array($tournament) || !is_array($match)) {
            return '';
        }

        $matchNumber = (int)$matchNumber;
        $p1 = (int)($match['p1'] ?? 0);
        $p2 = (int)($match['p2'] ?? 0);
        $w = (int)($match['winner'] ?? 0);
        $status = (string)($match['status'] ?? 'pending');
        $summary = (string)($match['summary'] ?? '');
        $battleLogId = (int)($match['battle_log_id'] ?? 0);

        $e1 = ($p1 > 0) ? ($tournament['entries'][$p1] ?? null) : null;
        $e2 = ($p2 > 0) ? ($tournament['entries'][$p2] ?? null) : null;
        $name1 = $e1 ? sim_tournament_entry_name($e1) : 'BYE';
        $name2 = $e2 ? sim_tournament_entry_name($e2) : 'BYE';
        $img1 = $e1 ? trim((string)($e1['img'] ?? '')) : '';
        $img2 = $e2 ? trim((string)($e2['img'] ?? '')) : '';

        $p1Classes = 'sim-tournament-slot';
        $p2Classes = 'sim-tournament-slot';
        if ($w > 0 && $w === $p1) {
            $p1Classes .= ' is-winner';
        } elseif ($w > 0 && $p1 > 0) {
            $p1Classes .= ' is-loser';
        }
        if ($w > 0 && $w === $p2) {
            $p2Classes .= ' is-winner';
        } elseif ($w > 0 && $p2 > 0) {
            $p2Classes .= ' is-loser';
        }
        if ($p1 <= 0) {
            $p1Classes .= ' is-empty';
        }
        if ($p2 <= 0) {
            $p2Classes .= ' is-empty';
        }

        $title = trim(($summary !== '' ? ($summary . ' | ') : '') . $name1 . ' vs ' . $name2);
        $html = '';
        if ($battleLogId > 0) {
            $html .= '<a class="sim-tournament-match-link" href="/tools/combat-simulator/log/' . (int)$battleLogId . '" title="' . sim_tournament_h($title) . '">';
        } else {
            $html .= '<div class="sim-tournament-match-link is-disabled" title="' . sim_tournament_h($title) . '">';
        }

        $html .= '<div class="sim-tournament-match sim-status-' . sim_tournament_h($status) . '">';
        $html .= '<div class="sim-tournament-match-head">M' . $matchNumber;
        if ($battleLogId > 0) {
            $html .= ' #' . $battleLogId;
        }
        $html .= '</div>';
        $html .= '<div class="sim-tournament-duel">';
        $html .= '<div class="' . sim_tournament_h($p1Classes) . '" title="' . sim_tournament_h($name1) . '">';
        $html .= sim_tournament_slot_avatar_html($img1, $name1, ($p1 <= 0));
        $html .= '</div>';
        $html .= '<div class="sim-tournament-vs">VS</div>';
        $html .= '<div class="' . sim_tournament_h($p2Classes) . '" title="' . sim_tournament_h($name2) . '">';
        $html .= sim_tournament_slot_avatar_html($img2, $name2, ($p2 <= 0));
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';

        if ($battleLogId > 0) {
            $html .= '</a>';
        } else {
            $html .= '</div>';
        }
        return $html;
    }
}

if (!function_exists('sim_tournament_resolve_engine')) {
    function sim_tournament_resolve_engine($link, $entryA, $entryB, $seasonId, $tournamentId, $round, $match)
    {
        $seasonId = (int)$seasonId;
        $round = (int)$round;
        $match = (int)$match;
        $characterA = (int)($entryA['character_id'] ?? 0);
        $characterB = (int)($entryB['character_id'] ?? 0);
        if ($characterA <= 0 || $characterB <= 0) {
            return null;
        }

        $backupPost = $_POST;
        $maxAttempts = 6;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $_POST = array(
                'pj1' => (string)$characterA,
                'pj2' => (string)$characterB,
                'season_id' => (string)$seasonId,
                'turnos' => '5',
                'vit' => '7',
                'usarheridas' => 'yes',
                'regeneracion' => 'no',
                'combate' => 'normal',
                'narrative_tone' => 'random',
                'ambient_msgs' => 'yes',
                'rubberbanding' => 'yes',
                'armasrandom' => 'yes',
                'protrandom' => 'yes',
                'formarandom' => 'yes',
                'tournament_background' => '1',
                'tournament_id' => (string)$tournamentId,
                'tournament_round' => (string)$round,
                'tournament_match' => (string)$match
            );

            $GLOBALS['sim_last_battle_id'] = 0;
            $GLOBALS['sim_last_battle_outcome'] = '';
            $GLOBALS['sim_last_battle_winner_character_id'] = 0;

            ob_start();
            include(__DIR__ . '/battle_result_page.php');
            ob_end_clean();

            $outcome = (string)($GLOBALS['sim_last_battle_outcome'] ?? '');
            $battleLogId = (int)($GLOBALS['sim_last_battle_id'] ?? 0);
            $winnerCharacterId = (int)($GLOBALS['sim_last_battle_winner_character_id'] ?? 0);

            if ($outcome === 'draw') {
                continue;
            }

            $winnerEntryId = 0;
            if ($winnerCharacterId > 0) {
                if ($winnerCharacterId === $characterA) {
                    $winnerEntryId = (int)($entryA['entry_id'] ?? 0);
                } elseif ($winnerCharacterId === $characterB) {
                    $winnerEntryId = (int)($entryB['entry_id'] ?? 0);
                }
            }

            if ($winnerEntryId <= 0 || $battleLogId <= 0) {
                continue;
            }

            $winnerName = ($winnerEntryId === (int)($entryA['entry_id'] ?? 0))
                ? sim_tournament_entry_name($entryA)
                : sim_tournament_entry_name($entryB);
            $summary = 'Ganador: ' . $winnerName . ' (log #' . $battleLogId . ')';

            $_POST = $backupPost;
            return array(
                'winner' => $winnerEntryId,
                'summary' => $summary,
                'battle_log_id' => $battleLogId,
                'outcome' => 'win',
            );
        }

        $_POST = $backupPost;
        return null;
    }
}

if (!function_exists('sim_tournament_simulate_next')) {
    function sim_tournament_simulate_next($link, &$tournament)
    {
        sim_tournament_sync($tournament);
        $pending = sim_tournament_find_pending($tournament, 0);
        if (!$pending) {
            return false;
        }

        $r = (int)$pending['round'];
        $m = (int)$pending['match'];
        $match = $tournament['matches'][$r][$m];
        $entryA = $tournament['entries'][(int)$match['p1']] ?? null;
        $entryB = $tournament['entries'][(int)$match['p2']] ?? null;
        if (!$entryA || !$entryB) {
            return false;
        }

        $seasonId = (int)($tournament['season_id'] ?? 0);
        $tournamentId = (string)($tournament['id'] ?? '');
        $result = sim_tournament_resolve_engine($link, $entryA, $entryB, $seasonId, $tournamentId, $r, $m);
        if (!is_array($result)) {
            return false;
        }
        $winner = (int)($result['winner'] ?? 0);
        if ($winner <= 0) {
            return false;
        }

        $winnerEntry = $tournament['entries'][$winner] ?? null;
        $winnerName = sim_tournament_entry_name($winnerEntry);

        $tournament['matches'][$r][$m]['winner'] = $winner;
        $tournament['matches'][$r][$m]['status'] = 'done';
        $tournament['matches'][$r][$m]['summary'] = (string)($result['summary'] ?? '');
        $tournament['matches'][$r][$m]['battle_log_id'] = (int)($result['battle_log_id'] ?? 0);
        $tournament['matches'][$r][$m]['resolved_at'] = date('Y-m-d H:i:s');
        $tournament['history'][] = 'R' . $r . ' M' . $m . ': ' . $winnerName;

        sim_tournament_sync($tournament);
        return true;
    }
}

if (!function_exists('sim_tournament_simulate_round')) {
    function sim_tournament_simulate_round($link, &$tournament)
    {
        sim_tournament_sync($tournament);
        $firstPending = sim_tournament_find_pending($tournament, 0);
        if (!$firstPending) {
            return 0;
        }
        $round = (int)$firstPending['round'];
        $count = 0;
        while (true) {
            $pending = sim_tournament_find_pending($tournament, $round);
            if (!$pending) {
                break;
            }
            if (!sim_tournament_simulate_next($link, $tournament)) {
                break;
            }
            $count++;
        }
        return $count;
    }
}

if (!function_exists('sim_tournament_simulate_all')) {
    function sim_tournament_simulate_all($link, &$tournament)
    {
        $count = 0;
        $guard = 0;
        while ($guard < 512) {
            $guard++;
            if (!sim_tournament_simulate_next($link, $tournament)) {
                break;
            }
            $count++;
        }
        return $count;
    }
}

if (!function_exists('sim_tournament_round_label')) {
    function sim_tournament_round_label($round, $roundCount)
    {
        $round = (int)$round;
        $roundCount = (int)$roundCount;
        if ($round <= 0 || $roundCount <= 0) {
            return 'Ronda';
        }
        if ($round === $roundCount) {
            return 'Final';
        }
        if ($round === ($roundCount - 1)) {
            return 'Semifinal';
        }
        if ($round === ($roundCount - 2)) {
            return 'Cuartos';
        }
        return 'Ronda ' . $round;
    }
}

if (!function_exists('sim_tournament_state_encode')) {
    function sim_tournament_state_encode($state)
    {
        return serialize($state);
    }
}

if (!function_exists('sim_tournament_state_decode')) {
    function sim_tournament_state_decode($payload)
    {
        if (!is_string($payload) || trim($payload) === '') {
            return null;
        }
        $state = @unserialize($payload);
        return is_array($state) ? $state : null;
    }
}

if (!function_exists('sim_tournament_table_ready')) {
    function sim_tournament_table_ready($link)
    {
        return sim_tournament_table_exists($link, 'fact_sim_tournaments');
    }
}

if (!function_exists('sim_tournament_fetch_one')) {
    function sim_tournament_fetch_one($link, $id)
    {
        $id = (int)$id;
        if ($id <= 0 || !sim_tournament_table_ready($link)) {
            return null;
        }
        $query = "SELECT * FROM fact_sim_tournaments WHERE id = $id LIMIT 1";
        $rs = mysql_query($query, $link);
        if (!$rs || mysql_num_rows($rs) === 0) {
            return null;
        }
        return mysql_fetch_array($rs);
    }
}

if (!function_exists('sim_tournament_fetch_recent')) {
    function sim_tournament_fetch_recent($link, $limit = 20)
    {
        $limit = (int)$limit;
        if ($limit < 1) {
            $limit = 1;
        }
        if ($limit > 100) {
            $limit = 100;
        }
        if (!sim_tournament_table_ready($link)) {
            return array();
        }
        $query = "SELECT id, tournament_key, name, status, bracket_size, season_id, created_at, updated_at, finished_at, champion_character_id"
            . " FROM fact_sim_tournaments"
            . " ORDER BY updated_at DESC, id DESC"
            . " LIMIT $limit";
        $rs = mysql_query($query, $link);
        if (!$rs) {
            return array();
        }
        $rows = array();
        while ($row = mysql_fetch_array($rs)) {
            $rows[] = $row;
        }
        return $rows;
    }
}

if (!function_exists('sim_tournament_pick_row_for_view')) {
    function sim_tournament_pick_row_for_view($rows, $preferredId = 0)
    {
        $preferredId = (int)$preferredId;
        if (!is_array($rows) || empty($rows)) {
            return null;
        }
        if ($preferredId > 0) {
            foreach ($rows as $row) {
                if ((int)($row['id'] ?? 0) === $preferredId) {
                    return $row;
                }
            }
        }
        foreach ($rows as $row) {
            if ((string)($row['status'] ?? '') === 'active') {
                return $row;
            }
        }
        return $rows[0];
    }
}

if (!function_exists('sim_tournament_detect_champion_character')) {
    function sim_tournament_detect_champion_character($state)
    {
        if (!is_array($state)) {
            return 0;
        }
        $championEntryId = (int)($state['champion'] ?? 0);
        if ($championEntryId <= 0) {
            return 0;
        }
        $entry = $state['entries'][$championEntryId] ?? null;
        if (!is_array($entry)) {
            return 0;
        }
        return (int)($entry['character_id'] ?? 0);
    }
}

if (!function_exists('sim_tournament_character_name_by_id')) {
    function sim_tournament_character_name_by_id($link, $characterId)
    {
        static $cache = array();
        $characterId = (int)$characterId;
        if ($characterId <= 0) {
            return '';
        }
        if (isset($cache[$characterId])) {
            return (string)$cache[$characterId];
        }

        $name = '';
        $query = "SELECT COALESCE(alias, '') AS alias, COALESCE(nombre, '') AS nombre FROM vw_sim_characters WHERE id = $characterId LIMIT 1";
        $rs = mysql_query($query, $link);
        if ($rs && mysql_num_rows($rs) > 0) {
            $row = mysql_fetch_array($rs);
            $alias = trim((string)($row['alias'] ?? ''));
            $nombre = trim((string)($row['nombre'] ?? ''));
            $name = ($alias !== '') ? $alias : $nombre;
        }

        if ($name === '' && sim_tournament_table_exists($link, 'fact_characters')) {
            $rsFallback = mysql_query("SELECT COALESCE(name, '') AS name FROM fact_characters WHERE id = $characterId LIMIT 1", $link);
            if ($rsFallback && mysql_num_rows($rsFallback) > 0) {
                $rowFallback = mysql_fetch_array($rsFallback);
                $name = trim((string)($rowFallback['name'] ?? ''));
            }
        }

        $cache[$characterId] = $name;
        return $name;
    }
}

if (!function_exists('sim_tournament_character_slug_by_id')) {
    function sim_tournament_character_slug_by_id($link, $characterId)
    {
        static $cache = array();
        static $hasPrettyId = null;

        $characterId = (int)$characterId;
        if ($characterId <= 0) {
            return '';
        }
        if (isset($cache[$characterId])) {
            return (string)$cache[$characterId];
        }

        if ($hasPrettyId === null) {
            $hasPrettyId = false;
            if (sim_tournament_table_exists($link, 'fact_characters')) {
                $colRs = mysql_query("SHOW COLUMNS FROM `fact_characters` LIKE 'pretty_id'", $link);
                if ($colRs && mysql_num_rows($colRs) > 0) {
                    $hasPrettyId = true;
                }
                if ($colRs) {
                    mysql_free_result($colRs);
                }
            }
        }

        $slug = (string)$characterId;
        if ($hasPrettyId) {
            $query = "SELECT COALESCE(NULLIF(pretty_id, ''), CAST(id AS CHAR)) AS slug FROM fact_characters WHERE id = $characterId LIMIT 1";
            $rs = mysql_query($query, $link);
            if ($rs && mysql_num_rows($rs) > 0) {
                $row = mysql_fetch_array($rs);
                $value = trim((string)($row['slug'] ?? ''));
                if ($value !== '') {
                    $slug = $value;
                }
            }
            if ($rs) {
                mysql_free_result($rs);
            }
        }

        $cache[$characterId] = $slug;
        return $slug;
    }
}

if (!function_exists('sim_tournament_character_profile_by_id')) {
    function sim_tournament_character_profile_by_id($link, $characterId)
    {
        static $cache = array();
        $characterId = (int)$characterId;
        if ($characterId <= 0) {
            return array('gender' => '', 'concept' => '');
        }
        if (isset($cache[$characterId])) {
            return $cache[$characterId];
        }

        $profile = array('gender' => '', 'concept' => '');
        if (!sim_tournament_table_exists($link, 'fact_characters')) {
            $cache[$characterId] = $profile;
            return $profile;
        }

        $query = "SELECT COALESCE(gender, '') AS gender, COALESCE(concept, '') AS concept FROM fact_characters WHERE id = $characterId LIMIT 1";
        $rs = mysql_query($query, $link);
        if ($rs && mysql_num_rows($rs) > 0) {
            $row = mysql_fetch_array($rs);
            $profile['gender'] = trim((string)($row['gender'] ?? ''));
            $profile['concept'] = trim((string)($row['concept'] ?? ''));
        }
        if ($rs) {
            mysql_free_result($rs);
        }

        $cache[$characterId] = $profile;
        return $profile;
    }
}

if (!function_exists('sim_tournament_insert_row')) {
    function sim_tournament_insert_row($link, $name, $state)
    {
        if (!sim_tournament_table_ready($link) || !is_array($state)) {
            return 0;
        }

        $name = trim((string)$name);
        if ($name === '') {
            $name = 'Torneo';
        }
        if (strlen($name) > 120) {
            $name = substr($name, 0, 120);
        }

        $tournamentKey = trim((string)($state['id'] ?? ''));
        if ($tournamentKey === '') {
            $tournamentKey = 'torneo_' . date('Ymd_His') . '_' . mt_rand(1000, 9999);
            $state['id'] = $tournamentKey;
        }
        if (strlen($tournamentKey) > 64) {
            $tournamentKey = substr($tournamentKey, 0, 64);
            $state['id'] = $tournamentKey;
        }

        $seasonId = (int)($state['season_id'] ?? 0);
        $size = (int)($state['size'] ?? 8);
        $seedMode = ((string)($state['seed_mode'] ?? 'rank') === 'random') ? 'random' : 'rank';
        $status = ((int)($state['champion'] ?? 0) > 0) ? 'finished' : 'active';
        $championCharacterId = sim_tournament_detect_champion_character($state);
        $payload = sim_tournament_state_encode($state);

        $safeKey = mysql_real_escape_string($tournamentKey, $link);
        $safeName = mysql_real_escape_string($name, $link);
        $safeSeed = mysql_real_escape_string($seedMode, $link);
        $safeStatus = mysql_real_escape_string($status, $link);
        $safePayload = mysql_real_escape_string($payload, $link);
        $safeIp = mysql_real_escape_string((string)($_SERVER['REMOTE_ADDR'] ?? ''), $link);
        $seasonSql = ($seasonId > 0) ? (string)$seasonId : 'NULL';
        $championSql = ($championCharacterId > 0) ? (string)$championCharacterId : 'NULL';
        $finishedSql = ($status === 'finished') ? 'CURRENT_TIMESTAMP' : 'NULL';

        $query = "INSERT INTO fact_sim_tournaments"
            . " (tournament_key, name, season_id, bracket_size, seed_mode, status, state_payload, champion_character_id, created_by_ip, finished_at)"
            . " VALUES ('$safeKey', '$safeName', $seasonSql, $size, '$safeSeed', '$safeStatus', '$safePayload', $championSql, '$safeIp', $finishedSql)";

        if (!mysql_query($query, $link)) {
            return 0;
        }
        return (int)mysql_insert_id($link);
    }
}

if (!function_exists('sim_tournament_update_row')) {
    function sim_tournament_update_row($link, $rowId, $state)
    {
        $rowId = (int)$rowId;
        if ($rowId <= 0 || !sim_tournament_table_ready($link) || !is_array($state)) {
            return false;
        }

        $status = ((int)($state['champion'] ?? 0) > 0) ? 'finished' : 'active';
        $championCharacterId = sim_tournament_detect_champion_character($state);
        $payload = sim_tournament_state_encode($state);
        $safeStatus = mysql_real_escape_string($status, $link);
        $safePayload = mysql_real_escape_string($payload, $link);
        $championSql = ($championCharacterId > 0) ? (string)$championCharacterId : 'NULL';
        $finishedSql = ($status === 'finished') ? 'CURRENT_TIMESTAMP' : 'NULL';

        $query = "UPDATE fact_sim_tournaments"
            . " SET state_payload = '$safePayload',"
            . " status = '$safeStatus',"
            . " champion_character_id = $championSql,"
            . " finished_at = $finishedSql"
            . " WHERE id = $rowId LIMIT 1";
        return (bool)mysql_query($query, $link);
    }
}

if (!function_exists('sim_tournament_cancel_row')) {
    function sim_tournament_cancel_row($link, $rowId)
    {
        $rowId = (int)$rowId;
        if ($rowId <= 0 || !sim_tournament_table_ready($link)) {
            return false;
        }
        return (bool)mysql_query("UPDATE fact_sim_tournaments SET status = 'cancelled' WHERE id = $rowId LIMIT 1", $link);
    }
}

$isAdmin = sim_tournament_is_admin();
$seasonCfg = sim_tournament_active_season($link);
$activeSeasonId = (int)($seasonCfg['id'] ?? 0);
$rosterLimit = (int)($seasonCfg['character_limit'] ?? 35);
if ($rosterLimit < 1) { $rosterLimit = 35; }
$roster = sim_tournament_roster($link, $activeSeasonId, $rosterLimit);

$flash = '';
$tableReady = sim_tournament_table_ready($link);
$selectedTournamentId = isset($_GET['tid']) ? (int)$_GET['tid'] : 0;
if ($selectedTournamentId <= 0) {
    $selectedTournamentId = isset($_POST['tournament_db_id']) ? (int)$_POST['tournament_db_id'] : 0;
}

$recentTournaments = $tableReady ? sim_tournament_fetch_recent($link, 20) : array();
if ($selectedTournamentId <= 0) {
    $picked = sim_tournament_pick_row_for_view($recentTournaments, 0);
    $selectedTournamentId = (int)($picked['id'] ?? 0);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    if (!$isAdmin) {
        $flash = 'Acceso restringido: esta funcionalidad es exclusiva de administracion.';
    } elseif (!$tableReady) {
        $flash = "Falta la tabla de torneos. Ejecuta app/tools/simulator_tournaments_setup_20260314.php";
    } elseif ($action === 'create_tournament') {
        $name = trim((string)($_POST['tournament_name'] ?? ''));
        $size = (int)($_POST['bracket_size'] ?? 8);
        $seedMode = (string)($_POST['seed_mode'] ?? 'rank');
        $allowedSizes = array(4, 8, 16, 32);
        if (!in_array($size, $allowedSizes, true)) {
            $size = 8;
        }
        if (count($roster) < 2) {
            $flash = 'No hay suficientes personajes para crear torneo.';
        } elseif ($name === '') {
            $flash = 'Debes indicar un nombre para el torneo.';
        } else {
            $newTournament = sim_tournament_create($roster, $size, $seedMode, $activeSeasonId, $name);
            sim_tournament_sync($newTournament);
            $newId = sim_tournament_insert_row($link, $name, $newTournament);
            if ($newId > 0) {
                header('Location: /tools/combat-simulator/tournament?tid=' . (int)$newId);
                exit;
            }
            $flash = 'No se pudo guardar el torneo en base de datos.';
        }
    } else {
        $rowId = (int)($_POST['tournament_db_id'] ?? 0);
        $row = sim_tournament_fetch_one($link, $rowId);
        if (!$row) {
            $flash = 'No se encontro el torneo seleccionado.';
        } elseif ($action === 'reset_tournament') {
            sim_tournament_cancel_row($link, $rowId);
            header('Location: /tools/combat-simulator/tournament');
            exit;
        } elseif ((string)($row['status'] ?? '') !== 'active') {
            $flash = 'El torneo seleccionado no esta activo.';
        } else {
            $state = sim_tournament_state_decode((string)($row['state_payload'] ?? ''));
            if (!is_array($state)) {
                $flash = 'El estado del torneo esta danado.';
            } else {
                sim_tournament_sync($state);
                if ($action === 'simulate_next') {
                    if (!sim_tournament_simulate_next($link, $state)) {
                        $flash = 'No se pudo simular el siguiente combate.';
                    }
                } elseif ($action === 'simulate_round') {
                    $done = sim_tournament_simulate_round($link, $state);
                    if ($done <= 0) {
                        $flash = 'No habia combates pendientes en esa ronda.';
                    }
                } elseif ($action === 'simulate_all') {
                    $done = sim_tournament_simulate_all($link, $state);
                    if ($done <= 0) {
                        $flash = 'No habia combates pendientes para simular.';
                    }
                }
                sim_tournament_sync($state);
                sim_tournament_update_row($link, $rowId, $state);
                header('Location: /tools/combat-simulator/tournament?tid=' . (int)$rowId);
                exit;
            }
        }
    }
}

$currentTournamentRow = ($selectedTournamentId > 0) ? sim_tournament_fetch_one($link, $selectedTournamentId) : null;
$tournament = null;
if (is_array($currentTournamentRow)) {
    $tournament = sim_tournament_state_decode((string)($currentTournamentRow['state_payload'] ?? ''));
    if (is_array($tournament)) {
        sim_tournament_sync($tournament);
        if ((string)($currentTournamentRow['status'] ?? '') !== 'cancelled') {
            sim_tournament_update_row($link, (int)$currentTournamentRow['id'], $tournament);
        }
    }
}

include('app/partials/main_nav_bar.php');
?>
<div class="sim-ui">
    <h2>Torneo del Simulador</h2>
    <div class="sim-actions-row">
        <a class="sim-classic-btn" href="/tools/combat-simulator">Simulador</a>
        <a class="sim-classic-btn" href="/tools/combat-simulator/log">Registro</a>
    </div>

    <?php if (!$tableReady): ?>
        <fieldset class="sim-fieldset-inline">
            <legend>Configuracion pendiente</legend>
            <p class="sim-tournament-note">Falta la tabla de torneos. Ejecuta: <code>app/tools/simulator_tournaments_setup_20260314.php</code></p>
        </fieldset>
    <?php else: ?>
        <?php if ($activeSeasonId > 0): ?>
            <br />
            <p class="sim-tournament-note">
                <?php /* Temporada activa: <b> echo sim_tournament_h(($seasonCfg['name'] ?? '') !== '' ? $seasonCfg['name'] : ('#' . $activeSeasonId)); </b> */?>
            </p>
        <?php endif; ?>

        <?php if ($flash !== ''): ?>
            <p class="sim-tournament-note"><?php echo sim_tournament_h($flash); ?></p>
        <?php endif; ?>

        <?php if ($isAdmin): ?>
            <fieldset class="sim-fieldset-inline">
                <legend>Crear torneo</legend>
                <form method="post" action="/tools/combat-simulator/tournament" class="sim-tournament-create">
                    <input type="hidden" name="action" value="create_tournament">
                    <label>Nombre
                        <input type="text" name="tournament_name" maxlength="120" placeholder="Ej: Torneo Tenkaichi #1" required="required">
                    </label>
                    <label>Cuadro
                        <select name="bracket_size">
                            <option value="4">4</option>
                            <option value="8" selected="selected">8</option>
                            <option value="16">16</option>
                            <option value="32">32</option>
                        </select>
                    </label>
                    <label>Seeding
                        <select name="seed_mode">
                            <option value="rank" selected="selected">Por ranking</option>
                            <option value="random">Aleatorio</option>
                        </select>
                    </label>
                    <button type="submit">Crear torneo</button>
                </form>
                <p class="sim-tournament-note">
                    El torneo se guarda en BDD y cada combate real queda registrado en el log con flag de torneo.
                </p>
            </fieldset>
        <?php else: ?>
            <?php /* <p class="sim-tournament-note">Vista publica: solo administracion puede crear y simular torneos.</p> */ ?>
        <?php endif; ?>

        <?php if (!empty($recentTournaments)): ?>
            <fieldset class="sim-fieldset-inline">
                <legend>Torneos guardados</legend>
                <form method="get" action="/tools/combat-simulator/tournament" class="sim-tournament-picker">
                    <label for="simTournamentPickerSelect">Seleccionar torneo</label>
                    <select id="simTournamentPickerSelect" name="tid" onchange="this.form.submit()">
                        <?php foreach ($recentTournaments as $row): ?>
                            <?php
                            $rowId = (int)($row['id'] ?? 0);
                            $rowStatus = (string)($row['status'] ?? 'active');
                            $winnerId = (int)($row['champion_character_id'] ?? 0);
                            $winnerName = ($winnerId > 0) ? sim_tournament_character_name_by_id($link, $winnerId) : '';
                            if ($winnerName === '') {
                                $winnerName = ($winnerId > 0) ? ('ID ' . $winnerId) : '-';
                            }
                            $label = '#' . $rowId . ' | ' . (string)($row['name'] ?? 'Torneo') . ' | Ganador: ' . $winnerName . ' | ' . strtoupper($rowStatus);
                            ?>
                            <option value="<?php echo $rowId; ?>"<?php echo ($rowId === (int)$selectedTournamentId ? ' selected="selected"' : ''); ?>>
                                <?php echo sim_tournament_h($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <noscript><button type="submit">Abrir</button></noscript>
                </form>
            </fieldset>
        <?php endif; ?>

        <?php if (is_array($tournament)): ?>
            <?php
            $championId = (int)($tournament['champion'] ?? 0);
            $champion = ($championId > 0) ? ($tournament['entries'][$championId] ?? null) : null;
            $dbId = (int)($currentTournamentRow['id'] ?? 0);
            $dbStatus = (string)($currentTournamentRow['status'] ?? 'active');
            $tournamentSize = (int)($tournament['size'] ?? 0);
            $allowFullscreen = ($tournamentSize === 32);
            $canRun = ($isAdmin && $championId <= 0 && $dbStatus === 'active');
            ?>

            <?php if ($isAdmin): ?>
            <fieldset class="sim-fieldset-inline">
                <legend>Control del torneo</legend>
                <div class="sim-tournament-toolbar">
                    <?php if ($canRun): ?>
                        <form method="post" action="/tools/combat-simulator/tournament">
                            <input type="hidden" name="tournament_db_id" value="<?php echo $dbId; ?>">
                            <button type="submit" name="action" value="simulate_next">Simular siguiente combate</button>
                        </form>
                        <form method="post" action="/tools/combat-simulator/tournament">
                            <input type="hidden" name="tournament_db_id" value="<?php echo $dbId; ?>">
                            <button type="submit" name="action" value="simulate_round">Simular ronda</button>
                        </form>
                        <form method="post" action="/tools/combat-simulator/tournament">
                            <input type="hidden" name="tournament_db_id" value="<?php echo $dbId; ?>">
                            <button type="submit" name="action" value="simulate_all">Simular todo</button>
                        </form>
                        <form method="post" action="/tools/combat-simulator/tournament">
                            <input type="hidden" name="tournament_db_id" value="<?php echo $dbId; ?>">
                            <button type="submit" name="action" value="reset_tournament">Cancelar torneo</button>
                        </form>
                    <?php endif; ?>
                    <?php if ($allowFullscreen): ?>
                        <button type="button" id="simTournamentToggle" class="sim-tournament-view-toggle" aria-pressed="false">Pantalla completa</button>
                    <?php endif; ?>
                </div>
                <?php if (!$canRun): ?>
                    <p class="sim-tournament-note">Este torneo ya no admite simulaciones (estado: <?php echo sim_tournament_h($dbStatus); ?>).</p>
                <?php endif; ?>
                <p class="sim-tournament-note">
                    Torneo: <b><?php echo sim_tournament_h((string)($currentTournamentRow['name'] ?? ($tournament['name'] ?? 'Torneo'))); ?></b> |
                    Cuadro: <?php echo $tournamentSize; ?> |
                    Seeding: <?php echo sim_tournament_h((string)($tournament['seed_mode'] ?? 'rank')); ?> |
                    Estado: <?php echo sim_tournament_h($dbStatus); ?>
                </p>
            </fieldset>
            <?php endif; ?>

            <?php
            $roundCount = (int)($tournament['round_count'] ?? 0);
            $finalMatch = $tournament['matches'][$roundCount][1] ?? array();
            $championName = $champion ? sim_tournament_entry_name($champion) : '';
            $championImg = $champion ? trim((string)($champion['img'] ?? '')) : '';
            $championCharacterId = $champion ? (int)($champion['character_id'] ?? 0) : 0;
            $championSlug = ($championCharacterId > 0) ? sim_tournament_character_slug_by_id($link, $championCharacterId) : '';
            $championBioUrl = ($championSlug !== '') ? ('/characters/' . rawurlencode($championSlug)) : '';
            $championProfile = ($championCharacterId > 0) ? sim_tournament_character_profile_by_id($link, $championCharacterId) : array('gender' => '', 'concept' => '');
            $championGender = strtolower(trim((string)($championProfile['gender'] ?? '')));
            $championConcept = trim((string)($championProfile['concept'] ?? ''));
            $championGenderKey = ($championGender !== '') ? substr($championGender, 0, 1) : '';
            if ($championGenderKey === 'm') {
                $championLabel = 'Campe&oacute;n del torneo';
            } elseif ($championGenderKey === 'f') {
                $championLabel = 'Campeona del torneo';
            } else {
                $championLabel = 'Campeone del torneo';
            }
            ?>
            <div id="simTournamentStage" class="sim-tournament-stage">
                <?php if ($champion): ?>
                    <div class="sim-tournament-winner-hero">
                        <div class="sim-tournament-winner-crown" aria-hidden="true">&#128081;</div>
                        <div class="sim-tournament-winner-avatar-wrap">
                            <?php if ($championImg !== ''): ?>
                                <img class="sim-tournament-winner-avatar" src="<?php echo sim_tournament_h($championImg); ?>" alt="<?php echo sim_tournament_h($championName); ?>">
                            <?php else: ?>
                                <span class="sim-tournament-winner-avatar sim-tournament-winner-avatar-fallback"><?php echo sim_tournament_h(substr($championName, 0, 1)); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="sim-tournament-winner-text">
                            <div class="sim-tournament-winner-label"><?php echo $championLabel; ?></div>
                            <div class="sim-tournament-winner-name"><?php echo sim_tournament_h($championName); ?></div>
                            <?php if ($championConcept !== ''): ?>
                                <?php if ($championBioUrl !== ''): ?>
                                    <a class="sim-tournament-winner-concept sim-tournament-winner-concept-link" href="<?php echo sim_tournament_h($championBioUrl); ?>"><?php echo sim_tournament_h($championConcept); ?></a>
                                <?php else: ?>
                                    <div class="sim-tournament-winner-concept"><?php echo sim_tournament_h($championConcept); ?></div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="sim-tournament-board">
                    <div class="sim-tournament-side sim-tournament-side-left">
                        <?php for ($r = 1; $r < $roundCount; $r++): ?>
                            <?php
                            $roundMatches = $tournament['matches'][$r] ?? array();
                            $matchCount = is_array($roundMatches) ? count($roundMatches) : 0;
                            $half = (int)floor($matchCount / 2);
                            if ($half <= 0) {
                                continue;
                            }
                            ?>
                            <div class="sim-tournament-round">
                                <div class="sim-tournament-round-title"><?php echo sim_tournament_h(sim_tournament_round_label($r, $roundCount)); ?></div>
                                <?php foreach ($roundMatches as $m => $match): ?>
                                    <?php if ((int)$m > $half) { continue; } ?>
                                    <?php echo sim_tournament_render_match_card($tournament, (int)$m, $match); ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endfor; ?>
                    </div>

                    <div class="sim-tournament-center">
                        <div class="sim-tournament-round sim-tournament-final-round">
                            <div class="sim-tournament-round-title">Final</div>
                            <?php echo sim_tournament_render_match_card($tournament, 1, $finalMatch); ?>
                        </div>
                    </div>

                    <div class="sim-tournament-side sim-tournament-side-right">
                        <?php for ($r = $roundCount - 1; $r >= 1; $r--): ?>
                            <?php
                            $roundMatches = $tournament['matches'][$r] ?? array();
                            $matchCount = is_array($roundMatches) ? count($roundMatches) : 0;
                            $half = (int)floor($matchCount / 2);
                            if ($half <= 0) {
                                continue;
                            }
                            ?>
                            <div class="sim-tournament-round">
                                <div class="sim-tournament-round-title"><?php echo sim_tournament_h(sim_tournament_round_label($r, $roundCount)); ?></div>
                                <?php foreach ($roundMatches as $m => $match): ?>
                                    <?php if ((int)$m <= $half) { continue; } ?>
                                    <?php echo sim_tournament_render_match_card($tournament, (int)$m, $match); ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
            <?php if ($allowFullscreen): ?>
                <script>
                    (function () {
                        var stage = document.getElementById('simTournamentStage');
                        var toggle = document.getElementById('simTournamentToggle');
                        if (!stage || !toggle) {
                            return;
                        }

                        var setExpanded = function (expanded) {
                            stage.classList.toggle('is-expanded', expanded);
                            document.body.classList.toggle('sim-tournament-fullscreen-open', expanded);
                            toggle.textContent = expanded ? 'Salir de pantalla completa' : 'Pantalla completa';
                            toggle.setAttribute('aria-pressed', expanded ? 'true' : 'false');
                        };

                        toggle.addEventListener('click', function () {
                            var expanded = stage.classList.contains('is-expanded');
                            if (!expanded) {
                                if (stage.requestFullscreen) {
                                    stage.requestFullscreen().catch(function () {});
                                }
                                setExpanded(true);
                                return;
                            }
                            if (document.fullscreenElement && document.exitFullscreen) {
                                document.exitFullscreen().catch(function () {});
                            }
                            setExpanded(false);
                        });

                        document.addEventListener('fullscreenchange', function () {
                            if (document.fullscreenElement === stage) {
                                setExpanded(true);
                            } else if (stage.classList.contains('is-expanded')) {
                                setExpanded(false);
                            }
                        });

                        document.addEventListener('keydown', function (ev) {
                            if (ev.key === 'Escape' && stage.classList.contains('is-expanded') && !document.fullscreenElement) {
                                setExpanded(false);
                            }
                        });
                    })();
                </script>
            <?php endif; ?>
        <?php else: ?>
            <fieldset class="sim-fieldset-inline">
                <legend>Pool disponible</legend>
                <div class="sim-tournament-pool">
                    <?php if (empty($roster)): ?>
                        <p>No hay personajes disponibles en el roster del simulador.</p>
                    <?php else: ?>
                        <?php foreach ($roster as $row): ?>
                            <?php $label = sim_tournament_entry_name($row); ?>
                            <span class="sim-tournament-chip" title="<?php echo sim_tournament_h((string)($row['name'] ?? '')); ?>">
                                <?php echo sim_tournament_h($label); ?> (<?php echo str_repeat('&#9733;', (int)($row['rank_stars'] ?? 1)); ?>)
                            </span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </fieldset>
        <?php endif; ?>
    <?php endif; ?>
</div>
