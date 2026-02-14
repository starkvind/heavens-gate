<?php
// Mentions endpoint (JSON)
header('Content-Type: application/json; charset=UTF-8');

if (!isset($link) || !$link) {
    echo json_encode(['ok' => false, 'error' => 'No DB']);
    exit;
}

include_once(__DIR__ . '/../../helpers/mentions.php');

$type = isset($_GET['type']) ? strtolower(trim((string)$_GET['type'])) : '';
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$limit = isset($_GET['limit']) ? max(1, min(30, (int)$_GET['limit'])) : 12;

if ($type === '' || !isset(hg_mentions_config()[$type])) {
    echo json_encode(['ok' => false, 'error' => 'Invalid type']);
    exit;
}

$items = hg_mentions_search($link, $type, $q, $limit);
echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
