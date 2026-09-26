<?php

require_once(__DIR__ . '/../../domains/news/queries.php');

$metaTitle = "Noticias | Heaven's Gate";
$metaDescription = "Ultimas novedades de Heaven's Gate.";
$pageSect = 'Noticias';

if (!function_exists('hg_mobile_news_h')) {
    function hg_mobile_news_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$page = filter_var(hg_request_query_param($hgRequest, 'pag'), FILTER_VALIDATE_INT);
if (!$page || $page < 1) {
    $page = 1;
}

$pageSize = 5;
$totalRows = 0;
$totalPages = 1;
$posts = [];

if (isset($link) && ($link instanceof mysqli)) {
    $totalRows = hg_news_count_posts($link) ?? 0;
    $totalPages = max(1, (int)ceil($totalRows / $pageSize));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $pageSize;
    $posts = hg_news_fetch_posts($link, $offset, $pageSize) ?? [];
}
?>

<section class="hg-mobile-section">
    <h1>Noticias</h1>

    <?php if (empty($posts)): ?>
        <p class="hg-mobile-muted">No hay noticias disponibles.</p>
    <?php else: ?>
        <div class="hg-mobile-news-list">
            <?php foreach ($posts as $post): ?>
                <article class="hg-mobile-news-card">
                    <h2><?= hg_mobile_news_h($post['title'] ?? '') ?></h2>
                    <div class="hg-mobile-news-body">
                        <?= (string)($post['message'] ?? '') ?>
                    </div>
                    <footer>
                        por <strong><?= hg_mobile_news_h($post['author'] ?? '') ?></strong>
                        <span><?= hg_mobile_news_h($post['posted_at'] ?? '') ?></span>
                    </footer>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($totalPages > 1): ?>
        <nav class="hg-mobile-pagination" aria-label="Paginacion de noticias">
            <?php if ($page > 1): ?>
                <a href="/news?pag=<?= $page - 1 ?>">Anterior</a>
            <?php endif; ?>
            <span><?= $page ?> / <?= $totalPages ?></span>
            <?php if ($page < $totalPages): ?>
                <a href="/news?pag=<?= $page + 1 ?>">Siguiente</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
</section>

