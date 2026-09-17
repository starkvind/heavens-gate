<?php

include_once(__DIR__ . '/../../helpers/pretty.php');
include_once(__DIR__ . '/../helpers/chronicle_scope.php');
include_once(__DIR__ . '/../../domains/timeline/queries.php');

$metaTitle = "Línea temporal | Heaven's Gate";
$metaDescription = 'Línea temporal móvil de eventos y sucesos.';
$pageSect = 'Línea temporal';

if (!defined('HG_MOBILE_TIMELINE_EMBED')) {
    define('HG_MOBILE_TIMELINE_EMBED', true);
}

$routeKey = hg_request_route($hgRequest);

if ($routeKey === 'timeline_event') {
    $rawEvent = hg_request_param($hgRequest, 'event');
    $eventId = isset($link) && ($link instanceof mysqli)
        ? hg_timeline_resolve_event_id($link, $rawEvent)
        : 0;
    $excludedIds = function_exists('hg_mobile_excluded_chronicles_csv')
        ? hg_timeline_normalize_ids(hg_mobile_excluded_chronicles_csv())
        : [];

    if (
        isset($link)
        && ($link instanceof mysqli)
        && $eventId > 0
        && hg_timeline_event_is_excluded($link, $eventId, $excludedIds)
    ) {
        echo '<section class="hg-mobile-section"><h1>Evento no encontrado</h1><p>No se puede mostrar este evento.</p></section>';
        return;
    }

    include(__DIR__ . '/../../controllers/main/events_page.php');
    return;
}

include(__DIR__ . '/../../controllers/main/events_main.php');
