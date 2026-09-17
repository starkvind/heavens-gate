<?php

function hg_output_bootstrap_html(): void
{
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: no-cache, max-age=0, must-revalidate');
    }

    ini_set('default_charset', 'UTF-8');
    if (function_exists('mb_internal_encoding')) {
        mb_internal_encoding('UTF-8');
    }
    if (function_exists('mb_http_output')) {
        mb_http_output('UTF-8');
    }
}

function hg_strip_utf8_bom(string $content): string
{
    return (substr($content, 0, 3) === "\xEF\xBB\xBF") ? substr($content, 3) : $content;
}

function hg_normalize_utf8_output(string $content): string
{
    $content = hg_strip_utf8_bom($content);

    if (function_exists('mb_check_encoding') && !mb_check_encoding($content, 'UTF-8')) {
        $converted = @mb_convert_encoding($content, 'UTF-8', ['UTF-8', 'Windows-1252', 'ISO-8859-1']);
        if (is_string($converted) && $converted !== '') {
            $content = $converted;
        }
    }

    return $content;
}
