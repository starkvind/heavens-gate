<?php

include_once(__DIR__ . '/../../helpers/public_response.php');

$metaTitle = "Tablón CSP | Heaven's Gate";
$metaDescription = 'Tablón móvil de mensajes CSP.';
$pageSect = 'Herramientas';

if (!function_exists('hg_mobile_csp_h')) {
    function hg_mobile_csp_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('hg_mobile_csp_body')) {
    function hg_mobile_csp_body(string $value): string
    {
        return nl2br(hg_mobile_csp_h($value), false);
    }
}

if (!isset($link) || !($link instanceof mysqli)) {
    hg_public_log_error('mobile_csp', 'missing DB connection');
    hg_public_render_error('Tablón no disponible', 'No se pudo cargar el tablon de mensajes.');
    return;
}

$posts = [];
$sql = "SELECT author, title, message, posted_at FROM fact_csp_posts ORDER BY id DESC";
if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $posts[] = $row;
    }
    mysqli_stmt_close($stmt);
} else {
    hg_public_log_error('mobile_csp', 'query prepare failed: ' . mysqli_error($link));
    hg_public_render_error('Tablón no disponible', 'No se pudo cargar el tablon de mensajes.');
    return;
}
?>

<section class="hg-mobile-section">
    <h1>Tablón de mensajes</h1>
    <p class="hg-mobile-muted"><?= number_format(count($posts), 0, ',', '.') ?> mensajes</p>
</section>

<section class="hg-mobile-section">
    <div class="hg-mobile-card-list hg-mobile-csp-list" data-mobile-paginated data-mobile-search="1" data-page-size="12" data-search-placeholder="Buscar mensaje, autor o título" data-empty-text="No hay mensajes con ese filtro.">
        <?php if (empty($posts)): ?>
            <p class="hg-mobile-muted">No hay mensajes publicados.</p>
        <?php endif; ?>
        <?php foreach ($posts as $post): ?>
            <?php
                $author = trim((string)($post['author'] ?? ''));
                $title = trim((string)($post['title'] ?? ''));
                $message = (string)($post['message'] ?? '');
                $date = trim((string)($post['posted_at'] ?? ''));
                $search = trim($author . ' ' . $title . ' ' . strip_tags($message) . ' ' . $date);
            ?>
            <article class="hg-mobile-card hg-mobile-csp-card" data-mobile-item data-mobile-search="<?= hg_mobile_csp_h($search) ?>">
                <header>
                    <strong><?= hg_mobile_csp_h($title !== '' ? $title : '(Sin título)') ?></strong>
                    <span><?= hg_mobile_csp_h(trim(($author !== '' ? $author : 'Anonimo') . ($date !== '' ? ' | ' . $date : ''))) ?></span>
                </header>
                <div class="hg-mobile-csp-message"><?= hg_mobile_csp_body($message) ?></div>
            </article>
        <?php endforeach; ?>
    </div>
</section>