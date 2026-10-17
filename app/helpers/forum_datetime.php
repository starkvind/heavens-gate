<?php

/**
 * SMF poster_time / BBCode date values are Unix timestamps (UTC instants).
 * Format them in Spain's civil time without relying on PHP's server timezone.
 * Europe/Madrid handles both CET (UTC+1) and CEST (UTC+2).
 */
if (!function_exists('hg_forum_format_post_time')) {
    function hg_forum_format_post_time(int $timestamp, string $format = 'd/m/Y · H:i T'): string
    {
        if ($timestamp <= 0) {
            return '';
        }

        static $timezone = null;
        if ($timezone === null) {
            $timezone = new DateTimeZone('Europe/Madrid');
        }

        return (new DateTimeImmutable('@' . $timestamp))
            ->setTimezone($timezone)
            ->format($format);
    }
}
