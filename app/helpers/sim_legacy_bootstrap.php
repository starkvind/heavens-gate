<?php

if (defined('HG_SIM_LEGACY_BOOTSTRAP')) {
    return;
}
define('HG_SIM_LEGACY_BOOTSTRAP', true);

if (!isset($bdd) || $bdd === '') {
    $bdd = defined('MYSQL_BDD') ? MYSQL_BDD : '';
}

if (!defined('MYSQL_ASSOC')) {
    define('MYSQL_ASSOC', MYSQLI_ASSOC);
}
if (!defined('MYSQL_NUM')) {
    define('MYSQL_NUM', MYSQLI_NUM);
}
if (!defined('MYSQL_BOTH')) {
    define('MYSQL_BOTH', MYSQLI_BOTH);
}

if (!function_exists('hg_mysql_link')) {
    function hg_mysql_link($link_identifier = null)
    {
        if ($link_identifier instanceof mysqli) {
            return $link_identifier;
        }
        return $GLOBALS['link'] ?? null;
    }
}

if (!function_exists('mysql_query')) {
    function mysql_query($query, $link_identifier = null)
    {
        $conn = hg_mysql_link($link_identifier);
        if (!$conn) {
            return false;
        }
        return mysqli_query($conn, (string)$query);
    }
}

if (!function_exists('mysql_fetch_array')) {
    function mysql_fetch_array($result, $result_type = MYSQL_BOTH)
    {
        if (!$result instanceof mysqli_result) {
            return false;
        }
        return mysqli_fetch_array($result, $result_type);
    }
}

if (!function_exists('mysql_fetch_assoc')) {
    function mysql_fetch_assoc($result)
    {
        if (!$result instanceof mysqli_result) {
            return false;
        }
        return mysqli_fetch_assoc($result);
    }
}

if (!function_exists('mysql_fetch_row')) {
    function mysql_fetch_row($result)
    {
        if (!$result instanceof mysqli_result) {
            return false;
        }
        return mysqli_fetch_row($result);
    }
}

if (!function_exists('mysql_num_rows')) {
    function mysql_num_rows($result)
    {
        if (!$result instanceof mysqli_result) {
            return 0;
        }
        return mysqli_num_rows($result);
    }
}

if (!function_exists('mysql_free_result')) {
    function mysql_free_result($result)
    {
        if ($result instanceof mysqli_result) {
            mysqli_free_result($result);
            return true;
        }
        return false;
    }
}

if (!function_exists('mysql_select_db')) {
    function mysql_select_db($database_name, $link_identifier = null)
    {
        $conn = hg_mysql_link($link_identifier);
        if (!$conn) {
            return false;
        }
        return mysqli_select_db($conn, (string)$database_name);
    }
}

if (!function_exists('mysql_real_escape_string')) {
    function mysql_real_escape_string($unescaped_string, $link_identifier = null)
    {
        $conn = hg_mysql_link($link_identifier);
        if (!$conn) {
            return addslashes((string)$unescaped_string);
        }
        return mysqli_real_escape_string($conn, (string)$unescaped_string);
    }
}

if (!function_exists('mysql_error')) {
    function mysql_error($link_identifier = null)
    {
        $conn = hg_mysql_link($link_identifier);
        if (!$conn) {
            return 'No active MySQL link';
        }
        return mysqli_error($conn);
    }
}

if (!function_exists('mysql_errno')) {
    function mysql_errno($link_identifier = null)
    {
        $conn = hg_mysql_link($link_identifier);
        if (!$conn) {
            return 0;
        }
        return mysqli_errno($conn);
    }
}

if (!function_exists('mysql_insert_id')) {
    function mysql_insert_id($link_identifier = null)
    {
        $conn = hg_mysql_link($link_identifier);
        if (!$conn) {
            return 0;
        }
        return mysqli_insert_id($conn);
    }
}

if (!function_exists('mysql_affected_rows')) {
    function mysql_affected_rows($link_identifier = null)
    {
        $conn = hg_mysql_link($link_identifier);
        if (!$conn) {
            return 0;
        }
        return mysqli_affected_rows($conn);
    }
}

if (!function_exists('ereg')) {
    function ereg($pattern, $string, &$regs = null)
    {
        $regex = '/' . str_replace('/', '\\/', (string)$pattern) . '/';
        $result = preg_match($regex, (string)$string, $matches);
        if (func_num_args() >= 3) {
            $regs = $matches;
        }
        return ($result === 1) ? 1 : 0;
    }
}

$moduleDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'combat_simulator';
if (is_dir($moduleDir)) {
    set_include_path($moduleDir . PATH_SEPARATOR . get_include_path());
}
