<?php

require_once __DIR__ . '/../../helpers/character_avatar.php';
require_once __DIR__ . '/../../domains/characters/detail_queries.php';
require_once __DIR__ . '/../../domains/characters/sheet_queries.php';
require_once __DIR__ . '/../../domains/characters/form_presentation_queries.php';
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

if (!empty($bioForms)) {
    $bioFormIds = array_column($bioForms, 'id');
    $formPresentationById = hg_characters_fetch_form_presentation_by_ids($link, $bioFormIds);
    $formOverridesById = hg_characters_fetch_form_trait_overrides($link, $bioFormIds);

    foreach ($bioForms as &$bioForm) {
        $formId = (int)($bioForm['id'] ?? 0);
        $presentation = $formPresentationById[$formId] ?? [];
        $bioForm['description'] = trim((string)($presentation['description'] ?? ''));
        $bioForm['silhouette_image_url'] = trim((string)($presentation['silhouette_image_url'] ?? ''));
        $bioForm['weapons'] = (int)($presentation['weapons'] ?? 0);
        $bioForm['firearms'] = (int)($presentation['firearms'] ?? 0);
        $bioForm['hpregen'] = (int)($presentation['hpregen'] ?? 0);
        $bioForm['regeneration'] = $bioForm['hpregen'] > 0 ? 1 : 0;
        $bioForm['overrides'] = $formOverridesById[$formId] ?? [];
    }
    unset($bioForm);

    if (function_exists('hg_page_register_stylesheet')) {
        hg_page_register_stylesheet('/assets/css/components/bio-forms-detail.css');
    }
}

include __DIR__ . '/../../presentation/characters/biography_export.php';
include __DIR__ . '/../../views/characters/biography.php';