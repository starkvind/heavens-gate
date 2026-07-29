<?php

$metaTitle = "Noticias | Heaven's Gate";
$metaDescription = "Ultimas novedades de Heaven's Gate.";
$pageSect = 'Noticias';

if (!function_exists('hg_mobile_news_h')) {
    function hg_mobile_news_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$page = filter_input(INPUT_GET, 'pag', FILTER_VALIDATE_INT);
if (!$page || $page < 1) {
    $page = 1;
}

$pageSize = 5;
$totalRows = 0;
$totalPages = 1;
$posts = [];

if (isset($link) && ($link instanceof mysqli)) {
    if ($result = mysqli_query($link, "SELECT COUNT(*) AS total FROM fact_admin_posts")) {
        $row = mysqli_fetch_assoc($result);
        $totalRows = (int)($row['total'] ?? 0);
        mysqli_free_result($result);
    }

    $totalPages = max(1, (int)ceil($totalRows / $pageSize));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $pageSize;

    if ($stmt = mysqli_prepare($link, "SELECT author, title, message, posted_at FROM fact_admin_posts ORDER BY id DESC LIMIT ?, ?")) {
        mysqli_stmt_bind_param($stmt, 'ii', $offset, $pageSize);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $posts[] = $row;
        }
        mysqli_stmt_close($stmt);
    }
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

