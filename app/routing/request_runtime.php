<?php

require_once __DIR__ . '/../bootstrap/request_router.php';
require_once __DIR__ . '/path_matcher.php';

/**
 * Runtime request orchestration.
 *
 * Canonical pretty-path routing is intentionally database-free here. Legacy
 * query-string canonicalization still delegates to request_router.php until
 * that compatibility layer is extracted in a later cut.
 */
function hg_request_routing_resolve(mysqli $link, string $requestUri, array $query): array
{
    $path = hg_request_router_normalize_path((string)(parse_url($requestUri, PHP_URL_PATH) ?? '/'));

    if ($path === '/sep/snippet_forum_hg.php' && hg_request_router_is_safe_method()) {
        return hg_request_router_redirect(
            '/forum/message' . hg_request_router_forum_embed_query('forum_message', $query),
            301
        );
    }

    if (!empty($query['p']) && hg_request_router_is_safe_method()) {
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

function hg_request_routing_bootstrap(mysqli $link): void
{
    $result = hg_request_routing_resolve(
        $link,
        (string)($_SERVER['REQUEST_URI'] ?? '/'),
        $_GET
    );

    if (($result['action'] ?? '') === 'redirect') {
        header('Location: ' . (string)$result['location'], true, (int)($result['status'] ?? 301));
        exit;
    }

    if (($result['action'] ?? '') !== 'route' || empty($result['params']) || !is_array($result['params'])) {
        return;
    }

    foreach ($result['params'] as $key => $value) {
        $_GET[$key] = (string)$value;
    }
}
