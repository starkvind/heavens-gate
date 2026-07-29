<?php

include_once(__DIR__ . '/../../helpers/public_response.php');
include_once(__DIR__ . '/../../helpers/character_avatar.php');

$metaTitle = "Documentos | Heaven's Gate";
$metaDescription = 'Archivo móvil de documentos.';
$pageSect = 'Documentos';

if (!function_exists('hg_mobile_doc_h')) {
    function hg_mobile_doc_h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}
if (!function_exists('hg_mobile_doc_col_exists')) {
    function hg_mobile_doc_col_exists(mysqli $link, string $table, string $column): bool {
        static $cache = [];
        $key = $table . ':' . $column;
        if (isset($cache[$key])) return $cache[$key];
        $ok = false;
        if ($st = $link->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?")) {
            $st->bind_param('ss', $table, $column);
            $st->execute();
            $st->bind_result($count);
            $st->fetch();
            $st->close();
            $ok = ((int)$count > 0);
        }
        return $cache[$key] = $ok;
    }
}
if (!function_exists('hg_mobile_doc_table_exists')) {
    function hg_mobile_doc_table_exists(mysqli $link, string $table): bool {
        static $cache = [];
        if (isset($cache[$table])) return $cache[$table];
        $rs = $link->query("SHOW TABLES LIKE '" . $link->real_escape_string($table) . "'");
        if (!$rs) return $cache[$table] = false;
        $ok = $rs->num_rows > 0;
        $rs->free();
        return $cache[$table] = $ok;
    }
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
if (!function_exists('hg_mobile_doc_resolve_id')) {
    function hg_mobile_doc_resolve_id(mysqli $link, string $raw): int {
        $raw = trim(rawurldecode($raw));
        if ($raw === '') return 0;
        $resolved = resolve_pretty_id($link, 'fact_docs', $raw);
        if ($resolved !== null && (int)$resolved > 0) return (int)$resolved;
        if (preg_match('/^\d+$/', $raw)) return (int)$raw;
        if (!function_exists('slugify_pretty_id')) return 0;
        $prettyExpr = hg_mobile_doc_col_exists($link, 'fact_docs', 'pretty_id') ? 'pretty_id' : "'' AS pretty_id";
        if ($res = $link->query("SELECT id, title, {$prettyExpr} FROM fact_docs")) {
            while ($row = $res->fetch_assoc()) {
                $id = (int)($row['id'] ?? 0);
                if ($id <= 0) continue;
                if (trim((string)($row['pretty_id'] ?? '')) === $raw || slugify_pretty_id((string)($row['title'] ?? '')) === $raw) {
                    $res->free();
                    return $id;
                }
            }
            $res->free();
        }
        return 0;
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

$raw = trim((string)($_GET['b'] ?? ''));
$docId = $raw !== '' ? hg_mobile_doc_resolve_id($link, $raw) : 0;

$hasPretty = hg_mobile_doc_col_exists($link, 'fact_docs', 'pretty_id');
$hasSource = hg_mobile_doc_col_exists($link, 'fact_docs', 'source');
$hasBibliography = hg_mobile_doc_col_exists($link, 'fact_docs', 'bibliography_id') && hg_mobile_doc_table_exists($link, 'dim_bibliographies');
$hasCategories = hg_mobile_doc_table_exists($link, 'dim_doc_categories') && hg_mobile_doc_col_exists($link, 'fact_docs', 'section_id');
$prettyExpr = $hasPretty ? "COALESCE(d.pretty_id, '')" : "''";
$sourceExpr = $hasSource ? "COALESCE(d.source, '')" : "''";
$categoryJoin = $hasCategories ? 'LEFT JOIN dim_doc_categories cat ON cat.id = d.section_id' : '';
$categoryExpr = $hasCategories ? "COALESCE(cat.kind, '')" : "''";
$categoryOrder = $hasCategories && hg_mobile_doc_col_exists($link, 'dim_doc_categories', 'sort_order') ? 'COALESCE(cat.sort_order, 999999),' : '';
$biblioJoin = $hasBibliography ? 'LEFT JOIN dim_bibliographies bib ON bib.id = d.bibliography_id' : '';
$biblioExpr = $hasBibliography ? "COALESCE(bib.name, '')" : "''";

if ($docId <= 0) {
    $docs = [];
    $sql = "
        SELECT d.id, {$prettyExpr} AS pretty_id, d.title, {$categoryExpr} AS category,
               {$biblioExpr} AS origin, d.content
        FROM fact_docs d
        {$categoryJoin}
        {$biblioJoin}
        ORDER BY {$categoryOrder} d.title ASC, d.id ASC
    ";
    if ($res = $link->query($sql)) {
        while ($row = $res->fetch_assoc()) $docs[] = $row;
        $res->free();
    } else {
        hg_public_log_error('mobile_documents', 'list query failed: ' . mysqli_error($link));
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

$doc = null;
$sql = "
    SELECT d.id, {$prettyExpr} AS pretty_id, d.title, d.content, {$sourceExpr} AS source,
           {$categoryExpr} AS category, {$biblioExpr} AS origin
    FROM fact_docs d
    {$categoryJoin}
    {$biblioJoin}
    WHERE d.id = ?
    LIMIT 1
";
if ($st = $link->prepare($sql)) {
    $st->bind_param('i', $docId);
    $st->execute();
    $res = $st->get_result();
    $doc = $res ? $res->fetch_assoc() : null;
    $st->close();
}
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

$charChronicleAnd = function_exists('hg_mobile_chronicle_exclusion_and') ? hg_mobile_chronicle_exclusion_and('c') : ' AND c.chronicle_id NOT IN (2,7) ';

$characters = [];
if (hg_mobile_doc_table_exists($link, 'bridge_characters_docs') && hg_mobile_doc_table_exists($link, 'fact_characters')) {
    $hasCharAlias = hg_mobile_doc_col_exists($link, 'fact_characters', 'alias');
    $hasCharImage = hg_mobile_doc_col_exists($link, 'fact_characters', 'image_url');
    $hasCharGender = hg_mobile_doc_col_exists($link, 'fact_characters', 'gender');
    $hasCharStatus = hg_mobile_doc_col_exists($link, 'fact_characters', 'status_id') && hg_mobile_doc_table_exists($link, 'dim_character_status');
    $hasBridgeSort = hg_mobile_doc_col_exists($link, 'bridge_characters_docs', 'sort_order');
    $aliasExpr = $hasCharAlias ? "COALESCE(c.alias, '')" : "''";
    $imageExpr = $hasCharImage ? "COALESCE(c.image_url, '')" : "''";
    $genderExpr = $hasCharGender ? "COALESCE(c.gender, '')" : "''";
    $statusJoin = $hasCharStatus ? 'LEFT JOIN dim_character_status dcs ON dcs.id = c.status_id' : '';
    $statusExpr = $hasCharStatus ? "COALESCE(dcs.label, '')" : "''";
    $order = $hasBridgeSort ? 'b.sort_order ASC, c.name ASC' : 'c.name ASC';
    $charSql = "
        SELECT c.id, c.name, {$aliasExpr} AS alias, {$imageExpr} AS image_url,
               {$genderExpr} AS gender, {$statusExpr} AS status
        FROM bridge_characters_docs b
        INNER JOIN fact_characters c ON c.id = b.character_id
        {$statusJoin}
        WHERE b.doc_id = ? {$charChronicleAnd}
        ORDER BY {$order}
    ";
    if ($st = $link->prepare($charSql)) {
        $st->bind_param('i', $docId);
        $st->execute();
        $res = $st->get_result();
        while ($res && ($row = $res->fetch_assoc())) $characters[] = $row;
        $st->close();
    }
}
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