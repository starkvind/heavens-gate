<?php
include 'config.php';

$source = $_POST['source_id'];
$target = $_POST['target_id'];

$stmt = $pdo->prepare("DELETE FROM character_relations WHERE source_id = ? AND target_id = ?");
$stmt->execute([$source, $target]);

echo "Relación eliminada correctamente.";
