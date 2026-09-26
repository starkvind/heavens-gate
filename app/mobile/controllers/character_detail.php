<?php

$mobileCharacterDetailReady = false;
include __DIR__ . '/character_detail_data.php';
if (!$mobileCharacterDetailReady) {
    return;
}
include __DIR__ . '/../views/character_detail.php';
