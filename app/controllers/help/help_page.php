<?php
require_once __DIR__ . '/../../domains/help/queries.php';

$helpSlug = trim((string)hg_request_query_param($hgRequest, 'slug'));
$helpPage = hg_help_fetch_page_by_slug($link, $helpSlug, true);

if (!$helpPage) {
    include __DIR__ . '/../main/error404.php';
    return;
}

$helpTitle = (string)($helpPage['title'] ?? 'Ayuda');
$helpSummary = trim((string)($helpPage['summary'] ?? ''));
$helpLead = trim((string)($helpPage['lead'] ?? ''));
$helpMetaDescription = trim((string)($helpPage['meta_description'] ?? ''));
if ($helpMetaDescription === '') {
    $helpMetaDescription = $helpSummary !== '' ? $helpSummary : $helpLead;
}

setMetaFromPage(
    $helpTitle . " | Ayuda | Heaven's Gate",
    $helpMetaDescription,
    null,
    'article'
);

$helpBreadcrumbTitle = $helpTitle;
include("app/partials/main_nav_bar.php");
if (function_exists('hg_page_register_stylesheet')) {
    hg_page_register_stylesheet('/assets/css/hg-help.css');
}
?>

<main class="hg-help-shell help-article">
    <header class="hg-help-hero">
        <p class="hg-help-kicker"><a href="/help">Ayuda</a></p>
        <h1><?= htmlspecialchars($helpTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h1>
        <?php if ($helpLead !== ''): ?>
            <p class="hg-help-lead"><?= htmlspecialchars($helpLead, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
        <?php endif; ?>
    </header>

    <?= (string)($helpPage['content_html'] ?? '') ?>
</main>

<script src="/assets/js/hg-help.js" defer></script>
