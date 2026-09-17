<?php

include_once(__DIR__ . '/../../helpers/public_response.php');
include_once(__DIR__ . '/../../helpers/character_avatar.php');
include_once(__DIR__ . '/../helpers/chronicle_scope.php');
require_once(__DIR__ . '/../../domains/documents/queries.php');

$metaTitle = "Documentos | Heaven's Gate";
$metaDescription = 'Archivo móvil de documentos.';
$pageSect = 'Documentos';

if (!function_exists('hg_mobile_doc_h')) {
    function hg_mobile_doc_h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}
if (!function_exists('hg_mobile_doc_url')) {
    function hg_mobile_doc_url(mysqli $link, string $table, string $base, int $id): string {
        return $id > 0 && function_exists('pretty_url') ? pretty_url($link, $table, $base, $id) : rtrim($base, '/') . '/' . $id;
    }
}
if (!function_exists('hg_mobile_doc_excerpt')) {
    function hg_mobile_doc_excerpt(string $text, int $max = 150): string {
        $text = trim(strip_tags($text));
        if ($text === '') return '';
        if (function_exists('mb_strlen') && function_exists('mb_substr')) return mb_strlen($text, 'UTF-8') > $max ? mb_substr($text, 0, $max, 'UTF-8') . '...' : $text;
        return strlen($text) > $max ? substr($text, 0, $max) . '...' : $text;
    }
}
if (!function_exists('hg_mobile_doc_character_card')) {
    function hg_mobile_doc_character_card(mysqli $link, array $character): void {
        $id = (int)($character['id'] ?? 0);
        $href = hg_mobile_doc_url($link, 'fact_characters', '/characters', $id);
        $avatar = hg_character_avatar_url((string)($character['image_url'] ?? ''), (string)($character['gender'] ?? ''));
        ?>
        <a class="hg-mobile-character-card" href="<?= hg_mobile_doc_h($href) ?>" data-mobile-item data-mobile-search="<?= hg_mobile_doc_h((string)($character['name'] ?? '') . ' ' . (string)($character['alias'] ?? '') . ' ' . (string)($character['status'] ?? '')) ?>">
            <?php if ($avatar !== ''): ?><img src="<?= hg_mobile_doc_h($avatar) ?>" alt=""><?php else: ?><span class="hg-mobile-character-avatar" aria-hidden="true"></span><?php endif; ?>
            <span class="hg-mobile-character-main">
                <strong><?= hg_mobile_doc_h($character['name'] ?? '') ?></strong>
                <span><?= hg_mobile_doc_h(trim((string)($character['alias'] ?? '') . ' ' . (string)($character['status'] ?? ''))) ?></span>
            </span>
        </a>
        <?php
    }
}

if (!isset($link) || !($link instanceof mysqli)) {
    hg_public_log_error('mobile_documents', 'missing DB connection');
    hg_public_render_error('Documentos no disponibles', 'No se pudo cargar el archivo.');
    return;
}

$raw = hg_request_param($hgRequest, 'document');
$docId = $raw !== '' ? hg_documents_resolve_id($link, $raw) : 0;

if ($docId <= 0) {
    $docs = hg_documents_fetch_mobile_catalog($link);
    if ($docs === null) {
        hg_public_log_error('mobile_documents', 'list query failed: ' . mysqli_error($link));
        $docs = [];
    }
    ?>
    <section class="hg-mobile-section">
        <h1>Documentos</h1>
        <p class="hg-mobile-muted"><?= number_format(count($docs), 0, ',', '.') ?> documentos</p>
    </section>
    <section class="hg-mobile-section">
        <div class="hg-mobile-card-list" data-mobile-paginated data-mobile-search="1" data-page-size="20" data-search-placeholder="Buscar documentos">
            <?php if (empty($docs)): ?><p class="hg-mobile-muted">No hay documentos disponibles.</p><?php endif; ?>
            <?php foreach ($docs as $doc): ?>
                <?php
                    $id = (int)($doc['id'] ?? 0);
                    $href = hg_mobile_doc_url($link, 'fact_docs', '/documents', $id);
                    $category = trim((string)($doc['category'] ?? '')) ?: 'Documento';
                    $origin = trim((string)($doc['origin'] ?? ''));
                    $excerpt = hg_mobile_doc_excerpt((string)($doc['content'] ?? ''), 120);
                    $search = (string)($doc['title'] ?? '') . ' ' . $category . ' ' . $origin . ' ' . strip_tags((string)($doc['content'] ?? ''));
                ?>
                <a class="hg-mobile-card" href="<?= hg_mobile_doc_h($href) ?>" data-mobile-item data-mobile-search="<?= hg_mobile_doc_h($search) ?>">
                    <strong><?= hg_mobile_doc_h($doc['title'] ?? '') ?></strong>
                    <span><?= hg_mobile_doc_h($category) ?><?= $origin !== '' ? ' - ' . hg_mobile_doc_h($origin) : '' ?></span>
                    <?php if ($excerpt !== ''): ?><span><?= hg_mobile_doc_h($excerpt) ?></span><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
    return;
}

$doc = hg_documents_fetch_mobile_detail($link, $docId);
if (!$doc) {
    hg_public_render_not_found('Documento no encontrado', 'No se pudo localizar el documento solicitado.');
    return;
}

$title = (string)($doc['title'] ?? 'Documento');
$content = (string)($doc['content'] ?? '');
$source = (string)($doc['source'] ?? '');
$category = trim((string)($doc['category'] ?? '')) ?: 'Documento';
$origin = trim((string)($doc['origin'] ?? ''));
$metaTitle = $title . " | Documentos | Heaven's Gate";
$metaDescription = hg_mobile_doc_excerpt($content, 160);

$characters = hg_documents_fetch_mobile_characters(
    $link,
    $docId,
    function_exists('hg_mobile_excluded_chronicles_csv') ? hg_mobile_excluded_chronicles_csv() : '2,7'
);
?>
<article class="hg-mobile-bio">
    <nav class="hg-mobile-local-nav"><a href="/documents?view=mobile">Volver a documentos</a></nav>

    <section class="hg-mobile-section">
        <h1><?= hg_mobile_doc_h($title) ?></h1>
        <div class="hg-mobile-fact-grid">
            <div><span>Categoría</span><strong><?= hg_mobile_doc_h($category) ?></strong></div>
            <?php if ($origin !== ''): ?><div><span>Origen</span><strong><?= hg_mobile_doc_h($origin) ?></strong></div><?php endif; ?>
        </div>
    </section>

    <section class="hg-mobile-section hg-mobile-prose hg-mobile-doc-body">
        <?= $content !== '' ? $content : '<p>Sin contenido.</p>' ?>
    </section>

    <?php if (trim(strip_tags($source)) !== ''): ?>
        <section class="hg-mobile-section hg-mobile-prose hg-mobile-doc-source">
            <h2>Fuente</h2>
            <?= $source ?>
        </section>
    <?php endif; ?>

    <?php if (!empty($characters)): ?>
        <section class="hg-mobile-section">
            <h2>Personajes relacionados</h2>
            <div class="hg-mobile-character-list" data-mobile-paginated data-mobile-search="1" data-page-size="20" data-search-placeholder="Buscar personajes">
                <?php foreach ($characters as $character): ?>
                    <?php hg_mobile_doc_character_card($link, $character); ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</article>