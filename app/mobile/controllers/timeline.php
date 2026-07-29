<?php

include_once(__DIR__ . '/../../helpers/pretty.php');
include_once(__DIR__ . '/../helpers/chronicle_scope.php');

$metaTitle = "Línea temporal | Heaven's Gate";
$metaDescription = 'Línea temporal móvil de eventos y sucesos.';
$pageSect = 'Línea temporal';

if (!defined('HG_MOBILE_TIMELINE_EMBED')) {
    define('HG_MOBILE_TIMELINE_EMBED', true);
}

if (!function_exists('hg_mobile_timeline_table_exists')) {
    function hg_mobile_timeline_table_exists(mysqli $link, string $table): bool
    {
        static $cache = [];
        if (isset($cache[$table])) return $cache[$table];
        $ok = false;
        if ($st = $link->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?")) {
            $st->bind_param('s', $table);
            $st->execute();
            $st->bind_result($count);
            $st->fetch();
            $st->close();
            $ok = ((int)$count > 0);
        }
        return $cache[$table] = $ok;
    }
}

if (!function_exists('hg_mobile_timeline_event_is_excluded')) {
    function hg_mobile_timeline_event_is_excluded(mysqli $link, string $rawEvent): bool
    {
        $csv = hg_mobile_excluded_chronicles_csv();
        if ($csv === '' || $rawEvent === '' || !hg_mobile_timeline_table_exists($link, 'bridge_timeline_events_chronicles')) {
            return false;
        }
        $eventId = 0;
        if (preg_match('/^\d+$/', $rawEvent)) {
            $eventId = (int)$rawEvent;
        } elseif (function_exists('resolve_pretty_id')) {
            $eventId = (int)(resolve_pretty_id($link, 'fact_timeline_events', $rawEvent) ?? 0);
        }
        if ($eventId <= 0) {
            return false;
        }
        $sql = "SELECT 1 FROM bridge_timeline_events_chronicles WHERE event_id = ? AND chronicle_id IN ({$csv}) LIMIT 1";
        if (!$st = $link->prepare($sql)) {
            return false;
        }
        $st->bind_param('i', $eventId);
        $st->execute();
        $rs = $st->get_result();
        $excluded = $rs && $rs->num_rows > 0;
        $st->close();
        return $excluded;
    }
}

$routeKey = trim((string)($_GET['p'] ?? ''));

if ($routeKey === 'timeline_event') {
    $rawEvent = trim((string)($_GET['t'] ?? ''));
    if (isset($link) && ($link instanceof mysqli) && hg_mobile_timeline_event_is_excluded($link, $rawEvent)) {
        echo '<section class="hg-mobile-section"><h1>Evento no encontrado</h1><p>No se puede mostrar este evento.</p></section>';
        return;
    }
    include(__DIR__ . '/../../controllers/main/events_page.php');
    return;
}

include(__DIR__ . '/../../controllers/main/events_main.php');