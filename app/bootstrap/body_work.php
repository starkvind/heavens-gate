<?php

require __DIR__ . '/page_context.php';

$routes = require __DIR__ . '/../routing/routes.php';

normalize_pretty_request($link, $routeKey);
require __DIR__ . '/../http/dispatcher.php';

// Fallback de metatags desde títulos de página si no se han definido
if (empty($metaTitle)) {
    $metaTitle = trim(($pageTitle2 ?? '') . ' | ' . ($pageSect ?? '') . ' | ' . $pageTitle, ' |');
}
