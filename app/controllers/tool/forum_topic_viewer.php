<?php
/* include("app/partials/main_nav_bar.php"); */

$metaTitle = "Foro | Heaven's Gate";
$metaDescription = "Visualizador de temas de foro.";

$topicId = filter_input(INPUT_GET, 'id_topic', FILTER_VALIDATE_INT);
$topicId = $topicId ? (int)$topicId : 0;

if (isset($link) && $link && $topicId > 0) {
    if (method_exists($link, 'set_charset')) {
        $link->set_charset('utf8mb4');
    } else {
        mysqli_set_charset($link, 'utf8mb4');
    }

    $sqlFull = "SELECT
                    ftv.topic_name,
                    ftv.topic_description,
                    dc.name AS chapter_name,
                    dc.chapter_number,
                    ds.name AS season_name
                FROM fact_tools_topic_viewer ftv
                LEFT JOIN dim_chapters dc ON dc.id = ftv.chapter_id
                LEFT JOIN dim_seasons ds ON ds.id = dc.season_id
                WHERE ftv.topic_id = ?
                  AND ftv.is_active = 1
                LIMIT 1";

    $topicRow = null;
    $st = mysqli_prepare($link, $sqlFull);
    if ($st) {
        mysqli_stmt_bind_param($st, 'i', $topicId);
        mysqli_stmt_execute($st);
        $rs = mysqli_stmt_get_result($st);
        if ($rs) {
            $topicRow = mysqli_fetch_assoc($rs) ?: null;
            mysqli_free_result($rs);
        }
        mysqli_stmt_close($st);
    } else {
        // Fallback por si la tabla aun no tiene chapter_id
        $sqlFallback = "SELECT topic_name, topic_description
                        FROM fact_tools_topic_viewer
                        WHERE topic_id = ?
                          AND is_active = 1
                        LIMIT 1";
        $st2 = mysqli_prepare($link, $sqlFallback);
        if ($st2) {
            mysqli_stmt_bind_param($st2, 'i', $topicId);
            mysqli_stmt_execute($st2);
            $rs2 = mysqli_stmt_get_result($st2);
            if ($rs2) {
                $topicRow = mysqli_fetch_assoc($rs2) ?: null;
                mysqli_free_result($rs2);
            }
            mysqli_stmt_close($st2);
        }
    }

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

include(__DIR__ . '/../../tools/forum_topic_viewer_tool.php');


