<?php

if (!function_exists('hg_cbe_birthtext_expr')) {
    function hg_cbe_birthtext_expr(mysqli $db, string $characterAlias = 'fc'): string
    {
        $alias = preg_replace('/[^a-zA-Z0-9_]/', '', $characterAlias);
        return ($alias !== '' ? $alias : 'fc') . '.birthdate_text';
    }
}
