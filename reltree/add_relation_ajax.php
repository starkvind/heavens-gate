<?php
include 'config.php';

$source = $_POST['source_id'];
$target = $_POST['target_id'];
$type = $_POST['relation_type'];
$arrows = $_POST['arrows'] ?? null;
$tag = $_POST['tag'] ?? null;
$importance = $_POST['importance'] ?? 0;
$desc = $_POST['description'] ?? null;

$sql = "INSERT INTO character_relations 
        (source_id, target_id, relation_type, tag, importance, description, arrows)
        VALUES (?, ?, ?, ?, ?, ?, ?)";

$stmt = $pdo->prepare($sql);
$stmt->execute([$source, $target, $type, $tag, $importance, $desc, $arrows]);

echo "Relación añadida correctamente.";
