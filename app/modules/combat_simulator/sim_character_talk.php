<?php

if (!function_exists('sim_talk_table_exists')) {
    function sim_talk_table_exists($link, $tableName)
    {
        $safe = mysql_real_escape_string((string)$tableName, $link);
        $rs = mysql_query("SHOW TABLES LIKE '$safe'", $link);
        return ($rs && mysql_num_rows($rs) > 0);
    }
}

if (!function_exists('sim_talk_table_columns')) {
    function sim_talk_table_columns($link, $tableName)
    {
        static $cache = array();
        $key = (string)$tableName;
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $cols = array();
        $safe = mysql_real_escape_string((string)$tableName, $link);
        $rs = mysql_query("SHOW COLUMNS FROM `$safe`", $link);
        if ($rs) {
            while ($row = mysql_fetch_array($rs)) {
                $field = (string)($row['Field'] ?? '');
                if ($field !== '') {
                    $cols[$field] = true;
                }
            }
        }
        $cache[$key] = $cols;
        return $cols;
    }
}

if (!function_exists('sim_talk_fallback_pool')) {
    function sim_talk_fallback_pool($talkType)
    {
        $type = strtolower((string)$talkType);
        if ($type === 'victory') {
            return array(
                'Para poder vencerme, debes dominar a Shen-Long.',
                'Eso fue sólo el calentamiento.',
                'No lucho para ganar, lucho por mi destino.',
                'Aprende del dolor. Te servirá en la próxima.',
                'Hoy no era tu día. Ni tu noche.',
                '¿Ya está? Pensaba que eras una amenaza.'
            );
        }
        return array();
    }
}

if (!function_exists('sim_talk_apply_tokens')) {
    function sim_talk_apply_tokens($phrase, $tokens, $link = null)
    {
        $text = (string)$phrase;
        if (!is_array($tokens) || empty($tokens)) {
            return sim_talk_apply_bd_char_tokens($text, $link);
        }
        foreach ($tokens as $key => $value) {
            $text = str_replace((string)$key, (string)$value, $text);
        }
        return sim_talk_apply_bd_char_tokens($text, $link);
    }
}

if (!function_exists('sim_talk_fetch_character_label')) {
    function sim_talk_fetch_character_label($link, $characterId)
    {
        static $cache = array();
        $id = (int)$characterId;
        if ($id <= 0 || !$link) {
            return '';
        }

        if (isset($cache[$id])) {
            return (string)$cache[$id];
        }

        $query = "SELECT name, alias FROM fact_characters WHERE id = $id LIMIT 1";
        $result = mysql_query($query, $link);
        if (!$result || mysql_num_rows($result) === 0) {
            $cache[$id] = '';
            return '';
        }

        $row = mysql_fetch_array($result);
        $name = trim((string)($row['name'] ?? ''));
        $alias = trim((string)($row['alias'] ?? ''));
        $label = ($alias !== '') ? $alias : $name;
        $cache[$id] = $label;
        return $label;
    }
}

if (!function_exists('sim_talk_apply_bd_char_tokens')) {
    function sim_talk_apply_bd_char_tokens($text, $link)
    {
        $phrase = (string)$text;
        if ($phrase === '' || !$link) {
            return $phrase;
        }

        return preg_replace_callback('/\{bd_char:(\d+)\}/i', function ($matches) use ($link) {
            $id = isset($matches[1]) ? (int)$matches[1] : 0;
            if ($id <= 0) {
                return '';
            }
            $label = sim_talk_fetch_character_label($link, $id);
            return ($label !== '') ? $label : ('#' . $id);
        }, $phrase);
    }
}

if (!function_exists('sim_talk_fetch_pool')) {
    function sim_talk_fetch_pool($link, $talkType, $characterId)
    {
        $table = 'fact_sim_characters_talk';
        if (!sim_talk_table_exists($link, $table)) {
            return array();
        }

        $cols = sim_talk_table_columns($link, $table);
        if (!isset($cols['phrase'])) {
            return array();
        }

        $where = array();
        $where[] = "phrase IS NOT NULL";
        $where[] = "TRIM(phrase) <> ''";

        if (isset($cols['talk_type'])) {
            $safeType = mysql_real_escape_string((string)$talkType, $link);
            $where[] = "talk_type = '$safeType'";
        }
        if (isset($cols['is_active'])) {
            $where[] = "is_active = 1";
        }
        if (isset($cols['character_id'])) {
            $cid = (int)$characterId;
            $where[] = "character_id = $cid";
        }

        $order = array();
        if (isset($cols['weight'])) {
            $order[] = "weight DESC";
        }
        if (isset($cols['id'])) {
            $order[] = "id DESC";
        }

        $query = "SELECT phrase FROM $table WHERE " . implode(' AND ', $where);
        if (!empty($order)) {
            $query .= " ORDER BY " . implode(', ', $order);
        }
        $query .= " LIMIT 200";

        $pool = array();
        $rs = mysql_query($query, $link);
        if ($rs) {
            while ($row = mysql_fetch_array($rs)) {
                $phrase = trim((string)($row['phrase'] ?? ''));
                if ($phrase !== '') {
                    $pool[] = $phrase;
                }
            }
        }
        return $pool;
    }
}

if (!function_exists('sim_talk_fetch_generic_pool')) {
    function sim_talk_fetch_generic_pool($link, $talkType)
    {
        $table = 'fact_sim_characters_talk';
        if (!sim_talk_table_exists($link, $table)) {
            return array();
        }

        $cols = sim_talk_table_columns($link, $table);
        if (!isset($cols['phrase'])) {
            return array();
        }

        $where = array();
        $where[] = "phrase IS NOT NULL";
        $where[] = "TRIM(phrase) <> ''";

        if (isset($cols['talk_type'])) {
            $safeType = mysql_real_escape_string((string)$talkType, $link);
            $where[] = "talk_type = '$safeType'";
        }
        if (isset($cols['is_active'])) {
            $where[] = "is_active = 1";
        }
        if (isset($cols['character_id'])) {
            $where[] = "(character_id = 0 OR character_id IS NULL)";
        }

        $order = array();
        if (isset($cols['weight'])) {
            $order[] = "weight DESC";
        }
        if (isset($cols['id'])) {
            $order[] = "id DESC";
        }

        $query = "SELECT phrase FROM $table WHERE " . implode(' AND ', $where);
        if (!empty($order)) {
            $query .= " ORDER BY " . implode(', ', $order);
        }
        $query .= " LIMIT 200";

        $pool = array();
        $rs = mysql_query($query, $link);
        if ($rs) {
            while ($row = mysql_fetch_array($rs)) {
                $phrase = trim((string)($row['phrase'] ?? ''));
                if ($phrase !== '') {
                    $pool[] = $phrase;
                }
            }
        }
        return $pool;
    }
}

if (!function_exists('sim_talk_pick_phrase')) {
    function sim_talk_pick_phrase($link, $talkType, $characterId, $tokens = array())
    {
        $pool = sim_talk_fetch_pool($link, $talkType, (int)$characterId);
        if (empty($pool)) {
            $pool = sim_talk_fetch_generic_pool($link, $talkType);
        }
        if (empty($pool)) {
            $pool = sim_talk_fallback_pool($talkType);
        }
        if (empty($pool)) {
            return '';
        }

        $picked = (string)$pool[array_rand($pool)];
        return sim_talk_apply_tokens($picked, $tokens, $link);
    }
}
