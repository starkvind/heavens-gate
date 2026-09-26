<?php

include_once(__DIR__ . '/../../helpers/public_response.php');
require_once(__DIR__ . '/../../domains/soundtracks/queries.php');

$metaTitle = "Banda sonora | Heaven's Gate";
$metaDescription = 'Banda sonora móvil de Heaven\'s Gate.';
$pageSect = 'Banda sonora';

if (!function_exists('hg_mobile_ost_h')) {
    function hg_mobile_ost_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('hg_mobile_ost_youtube_id')) {
    function hg_mobile_ost_youtube_id(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $url)) {
            return $url;
        }
        if (preg_match('#(?:youtube\.com/watch\?v=|youtube\.com/embed/|youtube\.com/shorts/|youtu\.be/)([A-Za-z0-9_-]{11})#i', $url, $m)) {
            return (string)$m[1];
        }
        if (preg_match('/[?&]v=([A-Za-z0-9_-]{11})/i', $url, $m)) {
            return (string)$m[1];
        }
        return '';
    }
}

if (!function_exists('hg_mobile_ost_watch_url')) {
    function hg_mobile_ost_watch_url(string $id): string
    {
        return $id !== '' ? ('https://www.youtube.com/watch?v=' . rawurlencode($id) . '&referrer=heavensgate') : '';
    }
}

if (!isset($link) || !($link instanceof mysqli)) {
    hg_public_log_error('mobile_soundtrack', 'missing DB connection');
    hg_public_render_error('Banda sonora no disponible', 'No se pudo cargar la banda sonora.');
    return;
}

$songs = hg_soundtracks_fetch_catalog($link, true);
if ($songs === null) {
    hg_public_log_error('mobile_soundtrack', 'list query failed: ' . mysqli_error($link));
    hg_public_render_error('Banda sonora no disponible', 'No se pudo cargar el listado musical.');
    return;
}
foreach ($songs as &$row) {
    $youtubeId = hg_mobile_ost_youtube_id((string)($row['youtube_url'] ?? ''));
    $row['youtube_id'] = $youtubeId;
    $row['youtube_watch_url'] = hg_mobile_ost_watch_url($youtubeId);
}
unset($row);

include __DIR__ . '/../views/soundtrack.php';
