<?php
// Mentions endpoint (JSON)
header('Content-Type: application/json; charset=UTF-8');

if (!isset($link) || !$link) {
    echo json_encode(['ok' => false, 'error' => 'No DB']);
    exit;
}

include_once(__DIR__ . '/../../helpers/mentions.php');

$type = strtolower(hg_request_param($hgRequest, 'mention_type'));
$q = hg_request_query_param($hgRequest, 'q');
$limitRaw = hg_request_query_param($hgRequest, 'limit');
$limit = $limitRaw !== '' ? max(1, min(30, (int)$limitRaw)) : 12;

if ($type === '' || !isset(hg_mentions_config()[$type])) {
    echo json_encode(['ok' => false, 'error' => 'Invalid type']);
    exit;
}

$items = hg_mentions_search($link, $type, $q, $limit);
echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
