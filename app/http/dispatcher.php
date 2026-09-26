<?php

require_once __DIR__ . '/dispatch_policy.php';

$dispatch = hg_dispatch_resolve($routes, $routeKey);
$file = $dispatch['file'];
$sect = $dispatch['section'];

if ($sect) {
    $pageSect = $sect;
}

if (!empty($dispatch['bare'])) {
    $isBarePage = true;
}

include $file;
