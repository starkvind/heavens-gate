<?php

require_once __DIR__ . '/path_normalization.php';
require_once __DIR__ . '/legacy_query.php';
require_once __DIR__ . '/path_matcher.php';

/**
 * Runtime request orchestration.
 *
 * Canonical pretty-path routing is database-free. Legacy query-string
 * canonicalization remains a separate compatibility seam in legacy_query.php.
 */
function hg_request_method_is_safe(string $method): bool
{
    return in_array(strtoupper(trim($method)), ['GET', 'HEAD'], true);
}

function hg_request_routing_resolve(
    mysqli $link,
    string $requestUri,
    array $query,
    string $method = 'GET'
): array {
    $path = hg_request_path_normalize((string)(parse_url($requestUri, PHP_URL_PATH) ?? '/'));
    $safeMethod = hg_request_method_is_safe($method);

    if ($path === '/sep/snippet_forum_hg.php' && $safeMethod) {
        return hg_request_router_redirect(
            '/forum/message' . hg_request_router_forum_embed_query('forum_message', $query),
            301
        );
    }

    if (!empty($query['p']) && $safeMethod) {
        return hg_request_router_legacy_query_result($link, $path, $query);
    }

    if (!empty($query['p'])) {
        return hg_request_router_noop();
    }

    if ($path === '/' || $path === '') {
        return hg_request_router_noop();
    }

    return hg_request_path_matcher_match($path);
}

function hg_request_routing_bootstrap(
    mysqli $link,
    string $requestUri,
    array $query,
    string $method = 'GET'
): array {
    $result = hg_request_routing_resolve($link, $requestUri, $query, $method);

    if (($result['action'] ?? '') === 'redirect') {
        header('Location: ' . (string)$result['location'], true, (int)($result['status'] ?? 301));
        exit;
    }

    if (($result['action'] ?? '') !== 'route' || empty($result['params']) || !is_array($result['params'])) {
        return $query;
    }

    foreach ($result['params'] as $key => $value) {
        $query[(string)$key] = (string)$value;
    }

    return $query;
}
