<?php

require_once __DIR__ . '/../../helpers/character_avatar.php';
require_once __DIR__ . '/../../domains/characters/detail_queries.php';
require_once __DIR__ . '/../../domains/characters/sheet_queries.php';
require_once __DIR__ . '/../../presentation/characters/biography_helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

$mensajeDeError = 'No se pudo cargar el personaje solicitado.';
$characterId = (int)hg_request_param($hgRequest, 'character');
if ($characterId <= 0) {
    echo "<p class='bio-error-msg'>{$mensajeDeError}</p>";
    return;
}

$detail = hg_characters_fetch_detail_row($link, $characterId);
$dataResult = $detail['row'] ?? null;
$deathTable = $detail['death_table'] ?? null;
if (!is_array($dataResult)) {
    echo "<p class='bio-error-msg'>{$mensajeDeError}</p>";
    return;
}

$bioIsAdminFlag = bio_page_is_admin_flag_enabled();

include __DIR__ . '/bio_page_prepare.php';
include __DIR__ . '/../../presentation/characters/biography_export.php';
include __DIR__ . '/../../views/characters/biography.php';
