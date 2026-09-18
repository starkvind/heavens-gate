<?php
require_once(__DIR__ . '/../helpers/runtime_response.php');
require_once(__DIR__ . '/../domains/inventory/queries.php');

if (!isset($link) || !($link instanceof mysqli)) {
    require_once(__DIR__ . '/../helpers/db_connection.php');
}

$itemId = filter_var(hg_request_param($hgRequest, 'id'), FILTER_VALIDATE_INT);

if (!$itemId) {
    hg_runtime_embed_error('Objeto no disponible', 'No se ha indicado ningun objeto.', 400);
    return;
}

$item = hg_inventory_fetch_item($link, (int)$itemId);
if (!$item) {
    hg_runtime_embed_error('Objeto no encontrado', 'No existe ningun objeto con ese identificador.', 404);
    return;
}

$name = htmlspecialchars((string)$item['name'], ENT_QUOTES, 'UTF-8');
$img = trim((string)($item['image_url'] ?? ''));
$img = ($img !== '') ? htmlspecialchars($img, ENT_QUOTES, 'UTF-8') : "img/inv/no-photo.webp";
$description = (string)($item['description'] ?? '');
$description = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $description);
$rating = trim((string)($item['rating'] ?? ''));
$ratingInt = (preg_match('/^\d+$/', $rating) === 1) ? (int)$rating : 0;

$typePretty = '';
$typeName = trim((string)($item['item_type_name'] ?? ''));
$typeId = (int)($item['item_type_id'] ?? 0);
if ($typeId > 0) {
    $typeRow = hg_inventory_fetch_type($link, $typeId);
    if ($typeRow) {
        $typePretty = trim((string)($typeRow['pretty_id'] ?? ''));
        if ($typeName === '') {
            $typeName = trim((string)($typeRow['name'] ?? ''));
        }
    }
}
if ($typePretty === '') {
    $typePretty = ($typeId > 0) ? (string)$typeId : 'tipo';
}
if ($typeName === '') {
    switch ($typeId) {
        case 1:
            $typeName = 'Arma';
            break;
        case 2:
            $typeName = 'Protector';
            break;
        case 3:
            $typeName = 'Objeto magico';
            break;
        case 5:
            $typeName = 'Amuleto';
            break;
        default:
            $typeName = 'Objeto';
            break;
    }
}
$typeName = htmlspecialchars($typeName, ENT_QUOTES, 'UTF-8');

$itemPretty = trim((string)($item['pretty_id'] ?? ''));
if ($itemPretty === '') {
    $itemPretty = (string)$itemId;
}

$itemHref = "/inventory/" . rawurlencode($typePretty) . "/" . rawurlencode($itemPretty);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= $name ?></title>
    <link href="/assets/vendor/fonts/quicksand/quicksand.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/embeds/item.css">
</head>
<body class="hg-embed-item">
    <article class="embed-item-card">
        <a class="embed-item-card__banner" href="<?= htmlspecialchars($itemHref, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">
            <span class="embed-item-card__title"><?= $name ?></span>
        </a>

        <div class="embed-item-card__body">
            <a class="embed-item-card__media" href="<?= htmlspecialchars($itemHref, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">
                <span class="embed-item-card__img-wrap">
                    <img class="embed-item-card__img" src="../<?= $img ?>" alt="<?= $name ?>">
                </span>
            </a>

            <div class="embed-item-card__content">
                <div class="embed-item-card__stats">
                    <div class="embed-item-card__stat">
                        <div class="embed-item-card__stat-label">Tipo</div>
                        <div class="embed-item-card__stat-value"><?= $typeName ?></div>
                    </div>
                    <?php if ($typeId === 3 && $rating !== ''): ?>
                        <div class="embed-item-card__stat">
                            <div class="embed-item-card__stat-label">Nivel</div>
                            <div class="embed-item-card__stat-value">
                                <?php if ($ratingInt >= 1 && $ratingInt <= 9): ?>
                                    <img class="embed-item-card__gem" src="../img/ui/gems/pwr/gem-pwr-0<?= $ratingInt ?>.webp" alt="Nivel <?= $ratingInt ?>">
                                <?php else: ?>
                                    <?= htmlspecialchars($rating, ENT_QUOTES, 'UTF-8') ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="embed-item-card__desc">
                    <div class="embed-item-card__desc-body"><?= $description ?></div>
                </div>
            </div>
        </div>
    </article>

    <script>
        function sendHeight() {
            const height = document.body.scrollHeight + 24;
            window.parent.postMessage({ type: 'setHeight', height }, '*');
        }

        window.addEventListener('load', sendHeight);
        window.addEventListener('resize', sendHeight);
    </script>
</body>
</html>
