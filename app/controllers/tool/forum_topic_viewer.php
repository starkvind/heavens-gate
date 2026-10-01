<?php
/* include("app/partials/main_nav_bar.php"); */

$metaTitle = "Foro | Heaven's Gate";
$metaDescription = "Visualizador de temas de foro.";

require_once __DIR__ . '/../../domains/forum/queries.php';
require_once __DIR__ . '/../../helpers/forum_item_embed.php';

$topicId = filter_var(hg_request_query_param($hgRequest, 'id_topic'), FILTER_VALIDATE_INT);
$topicId = $topicId ? (int)$topicId : 0;

if (isset($link) && $link && $topicId > 0) {
    if (method_exists($link, 'set_charset')) {
        $link->set_charset('utf8mb4');
    } else {
        mysqli_set_charset($link, 'utf8mb4');
    }

    $topicRow = hg_forum_topic_fetch_metadata($link, $topicId);
    if (is_array($topicRow)) {
        $topicName = trim((string)($topicRow['topic_name'] ?? ''));
        $topicDesc = trim((string)($topicRow['topic_description'] ?? ''));
        $chapterName = trim((string)($topicRow['chapter_name'] ?? ''));
        $chapterNumber = (int)($topicRow['chapter_number'] ?? 0);
        $seasonName = trim((string)($topicRow['season_name'] ?? ''));

        if ($chapterName !== '') {
            $parts = [$chapterName];
            if ($seasonName !== '') { $parts[] = $seasonName; }
            if ($chapterNumber > 0) { $parts[] = 'Ep. ' . $chapterNumber; }
            $parts[] = 'Foro';
            $parts[] = "Heaven's Gate";
            $metaTitle = implode(' | ', $parts);
        } elseif ($topicName !== '') {
            $metaTitle = $topicName . " | Foro | Heaven's Gate";
        }

        if ($topicDesc !== '') {
            $metaDescription = $topicDesc;
        } elseif ($topicName !== '') {
            $metaDescription = "Tema del foro: " . $topicName;
        }
    }
}

if (function_exists('setMetaFromPage')) {
    setMetaFromPage($metaTitle, $metaDescription, null, 'article');
}

if (!defined('HG_FORUM_TOPIC_VIEWER_EMBED')) {
    define('HG_FORUM_TOPIC_VIEWER_EMBED', true);
}

$hgForumViewerRoot = dirname(__DIR__, 3);
$hgForumViewerCss = '/assets/css/hg-forum-item-embed.css';
$hgForumViewerJs = '/assets/js/forum-item-viewer-embed.js';
$hgForumViewerCssVersion = @filemtime($hgForumViewerRoot . $hgForumViewerCss) ?: 1;
$hgForumViewerJsVersion = @filemtime($hgForumViewerRoot . $hgForumViewerJs) ?: 1;

echo '<link rel="stylesheet" href="' . $hgForumViewerCss . '?v=' . rawurlencode((string)$hgForumViewerCssVersion) . '">';
echo '<script src="' . $hgForumViewerJs . '?v=' . rawurlencode((string)$hgForumViewerJsVersion) . '" defer></script>';
ob_start();
include(__DIR__ . '/../../tools/forum_topic_viewer_tool.php');
$forumViewerHtml = (string)ob_get_clean();
echo hg_forum_expand_item_bbcode($forumViewerHtml);


