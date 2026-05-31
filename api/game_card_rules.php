<?php
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, max-age=0');

function hg_gcr_json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    require_once __DIR__ . '/../app/helpers/db_connection.php';
} catch (Throwable $e) {
    hg_gcr_json_response(['success' => false, 'error' => 'database_unavailable'], 500);
}

require_once __DIR__ . '/../app/modules/game_cards/game_card_rules_catalog.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'HEAD'], true)) {
    hg_gcr_json_response(['success' => false, 'error' => 'method_not_allowed'], 405);
}

if (!isset($link) || !($link instanceof mysqli)) {
    hg_gcr_json_response(['success' => false, 'error' => 'database_unavailable'], 500);
}

try {
    hg_gcr_json_response(hg_gcr_build_payload($link));
} catch (Throwable $e) {
    hg_gcr_json_response([
        'success' => false,
        'error' => 'rules_catalog_unavailable',
        'detail' => $e->getMessage(),
    ], 500);
}
