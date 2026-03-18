<?php
setMetaFromPage(
    "Visor de temas de foro | Heaven's Gate",
    "Visualiza temas del foro con render BBCode incluyendo hg_avatar y hg_tirada.",
    null,
    'website'
);
/* include("app/partials/main_nav_bar.php"); */

if (!defined('HG_FORUM_TOPIC_VIEWER_EMBED')) {
    define('HG_FORUM_TOPIC_VIEWER_EMBED', true);
}

include(__DIR__ . '/../../tools/forum_topic_viewer_tool.php');


