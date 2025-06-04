<?php
include 'config.php';

$source_id = $_POST['source_id'];
$target_id = $_POST['target_id'];
$relation_type = $_POST['relation_type'];
$tag = $_POST['tag'] ?? null;
$importance = $_POST['importance'] ?? 0;
$description = $_POST['description'] ?? null;

$sql = "INSERT INTO character_relations 
        (source_id, target_id, relation_type, tag, importance, description)
        VALUES (?, ?, ?, ?, ?, ?)";

$stmt = $pdo->prepare($sql);
$stmt->execute([$source_id, $target_id, $relation_type, $tag, $importance, $description]);

header("Location: index.php");
exit;
