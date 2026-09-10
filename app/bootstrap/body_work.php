<?php

require __DIR__ . '/page_context.php';
require_once __DIR__ . '/../http/pretty_request.php';

$routeKey = hg_request_route($hgRequest);
$routeParam = hg_request_query_param($hgRequest, 't');
$routes = require __DIR__ . '/../routing/routes.php';

$hgQuery = hg_pretty_request_normalize(
    $link,
    $routeKey,
    $hgQuery,
    (string)($_SERVER['REQUEST_URI'] ?? '/')
);
$hgRequest = hg_request_context_from_query($hgQuery);
$routeKey = hg_request_route($hgRequest);
$routeParam = hg_request_query_param($hgRequest, 't');

require __DIR__ . '/../http/dispatcher.php';

// Fallback de metatags desde títulos de página si no se han definido
if (empty($metaTitle)) {
    $metaTitle = trim(($pageTitle2 ?? '') . ' | ' . ($pageSect ?? '') . ' | ' . $pageTitle, ' |');
}
