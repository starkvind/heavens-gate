<?php

$mobileCharacterDetailReady = false;
include __DIR__ . '/character_detail_data.php';
if (!$mobileCharacterDetailReady) {
    return;
}
include __DIR__ . '/../views/character_detail.php';

$mobileFormPositionHref = '/assets/js/hg-mobile-form-position.js';
$mobileFormPositionFile = dirname(__DIR__, 3) . $mobileFormPositionHref;
$mobileFormPositionMtime = @filemtime($mobileFormPositionFile);
if ($mobileFormPositionMtime !== false) {
    $mobileFormPositionHref .= '?v=' . (int)$mobileFormPositionMtime;
}
echo '<script src="' . htmlspecialchars($mobileFormPositionHref, ENT_QUOTES, 'UTF-8') . '"></script>';