<?php

require __DIR__ . '/page_context.php';

$routes = require __DIR__ . '/../routing/routes.php';

normalize_pretty_request($link, $routeKey);
$hgRequest = hg_request_context_from_query($_GET);
require __DIR__ . '/../http/dispatcher.php';

// Fallback de metatags desde títulos de página si no se han definido
if (empty($metaTitle)) {
    $metaTitle = trim(($pageTitle2 ?? '') . ' | ' . ($pageSect ?? '') . ' | ' . $pageTitle, ' |');
}
