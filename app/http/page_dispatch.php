<?php

require __DIR__ . '/../bootstrap/page_context.php';
require_once __DIR__ . '/pretty_request.php';

$routeKey = hg_request_route($hgRequest);
$routeParam = hg_request_query_param($hgRequest, 't');
$routes = require __DIR__ . '/../routing/routes.php';

$hgQuery = hg_pretty_request_normalize(
    $link,
    $routeKey,
    $hgQuery,
    (string)($_SERVER['REQUEST_URI'] ?? '/')
);
$hgRequest = hg_request_context_from_query($hgQuery, $hgBody);
$routeKey = hg_request_route($hgRequest);
$routeParam = hg_request_query_param($hgRequest, 't');

require __DIR__ . '/dispatcher.php';

if (empty($metaTitle)) {
    $metaTitle = trim(($pageTitle2 ?? '') . ' | ' . ($pageSect ?? '') . ' | ' . $pageTitle, ' |');
}
