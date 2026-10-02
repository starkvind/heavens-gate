<?php

require_once __DIR__ . '/../../domains/characters/form_presentation_queries.php';

$mobileCharacterDetailReady = false;
include __DIR__ . '/character_detail_data.php';
if (!$mobileCharacterDetailReady) {
    return;
}

if (!empty($mobileForms)) {
    $mobileFormIds = array_column($mobileForms, 'id');
    $mobileOverridesById = hg_characters_fetch_form_trait_overrides($link, $mobileFormIds);
    foreach ($mobileForms as &$mobileForm) {
        $formId = (int)($mobileForm['id'] ?? 0);
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