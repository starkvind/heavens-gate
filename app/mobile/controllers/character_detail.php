<?php

require_once __DIR__ . '/../../domains/characters/form_presentation_queries.php';

$mobileCharacterDetailReady = false;
include __DIR__ . '/character_detail_data.php';
if (!$mobileCharacterDetailReady) {
    return;
}

if (!empty($mobileForms)) {
    $mobileFormIds = array_column($mobileForms, 'id');
    $mobilePresentationById = hg_characters_fetch_form_presentation_by_ids($link, $mobileFormIds, (int)$breedId);
    $mobileOverridesById = hg_characters_fetch_form_trait_overrides($link, $mobileFormIds);
    foreach ($mobileForms as &$mobileForm) {
        $formId = (int)($mobileForm['id'] ?? 0);
        $presentation = $mobilePresentationById[$formId] ?? [];
        $mobileForm['description'] = trim((string)($presentation['description'] ?? ''));
        $mobileForm['silhouette_image_url'] = trim((string)($presentation['silhouette_image_url'] ?? ''));
        $mobileForm['weapons'] = (int)($presentation['weapons'] ?? 0);
        $mobileForm['firearms'] = (int)($presentation['firearms'] ?? 0);
        $mobileForm['regeneration'] = (int)($presentation['regeneration'] ?? 0);
        $mobileForm['hpregen'] = (int)($presentation['hpregen'] ?? 0);
        $mobileForm['regeneration_label'] = (string)($presentation['regeneration_label'] ?? ($mobileForm['hpregen'] > 0 ? $mobileForm['hpregen'] . ' / turno' : 'No'));
        $mobileForm['regen_stress_difficulty'] = (int)($presentation['regen_stress_difficulty'] ?? 0);
        $mobileForm['regen_aggravated_auto'] = (int)($presentation['regen_aggravated_auto'] ?? 0);
        $mobileForm['overrides'] = $mobileOverridesById[$formId] ?? [];
    }
    unset($mobileForm);
}

include __DIR__ . '/../views/character_detail.php';

$mobileFormPositionHref = '/assets/js/hg-mobile-form-position.js';
$mobileFormPositionFile = dirname(__DIR__, 3) . $mobileFormPositionHref;
$mobileFormPositionMtime = @filemtime($mobileFormPositionFile);
if ($mobileFormPositionMtime !== false) {
    $mobileFormPositionHref .= '?v=' . (int)$mobileFormPositionMtime;
}
echo '<script src="' . htmlspecialchars($mobileFormPositionHref, ENT_QUOTES, 'UTF-8') . '"></script>';
