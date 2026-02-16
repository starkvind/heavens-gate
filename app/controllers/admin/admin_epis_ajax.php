<?php
// admin_epis_ajax.php
require_once("../heroes.php");

header('Content-Type: application/json');
if (!$link || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Conexi?n o m?todo inv?lido']);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'get_relations':
        $capId = intval($_POST['chapter_id'] ?? ($_POST['capitulo_id'] ?? 0));
        $stmt = $link->prepare("SELECT acp.id, acp.character_id, p.name FROM bridge_chapters_characters acp JOIN fact_characters p ON acp.character_id = p.id WHERE acp.chapter_id = ?");
        $stmt->bind_param("i", $capId);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        echo json_encode(['ok' => true, 'data' => $rows]);
        exit;

    case 'add_relation':
        $capId = intval($_POST['chapter_id'] ?? ($_POST['capitulo_id'] ?? 0));
        $pjId = intval($_POST['character_id'] ?? 0);
        $stmt = $link->prepare("INSERT IGNORE INTO bridge_chapters_characters (chapter_id, character_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $capId, $pjId);
        $stmt->execute();
        echo json_encode(['ok' => true]);
        exit;

    case 'del_relation':
        $relId = intval($_POST['rel_id'] ?? 0);
        $stmt = $link->prepare("DELETE FROM bridge_chapters_characters WHERE id = ?");
        $stmt->bind_param("i", $relId);
        $stmt->execute();
        echo json_encode(['ok' => true]);
        exit;

    default:
        echo json_encode(['ok' => false, 'error' => 'Acci?n no v?lida']);
        exit;
}
