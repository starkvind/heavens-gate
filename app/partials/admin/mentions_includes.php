<?php
// Mentions assets for admin editors
$mentionsCss = '/assets/css/hg-mentions.css';
$mentionsJs = '/assets/js/hg_mentions.js';
$mentionsCssVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . $mentionsCss) ?: time();
$mentionsJsVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . $mentionsJs) ?: time();
?>
<link rel="stylesheet" href="<?= htmlspecialchars($mentionsCss, ENT_QUOTES, 'UTF-8') ?>?v=<?= (int)$mentionsCssVer ?>">
<script src="<?= htmlspecialchars($mentionsJs, ENT_QUOTES, 'UTF-8') ?>?v=<?= (int)$mentionsJsVer ?>"></script>
