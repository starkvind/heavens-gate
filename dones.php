<?php
declare(strict_types=1);

/**
 * Galeria publica de iconos de dones.
 *
 * Muestra todos los dones para revisar rapidamente el progreso visual:
 * - Imagen asignada o placeholder.
 * - Nombre y enlace a la ficha publica.
 * - Rango, tipo y sistema.
 */

if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}

require_once(__DIR__ . '/app/helpers/db_connection.php');

if (!isset($link) || !($link instanceof mysqli)) {
    http_response_code(500);
    echo 'Error: no se ha encontrado una conexion mysqli valida.';
    exit;
}

$link->set_charset('utf8mb4');

function hg_gifts_gallery_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function hg_gifts_gallery_image_url(?string $imageUrl): string
{
    $imageUrl = trim((string)$imageUrl);
    if ($imageUrl === '') {
        return '/img/inv/no-photo.gif';
    }

    if (preg_match('~^https?://~i', $imageUrl)) {
        return $imageUrl;
    }

    return '/' . ltrim($imageUrl, '/');
}

function hg_gifts_gallery_detail_url(int $id, string $prettyId): string
{
    $segment = trim($prettyId) !== '' ? $prettyId : (string)$id;
    return '/powers/gift/' . rawurlencode($segment);
}

$gifts = [];
$withImage = 0;
$sql = "
    SELECT
        g.id,
        g.pretty_id,
        g.name,
        g.image_url,
        g.rank,
        COALESCE(t.name, g.kind, '-') AS gift_type,
        COALESCE(NULLIF(s.name, ''), NULLIF(g.system_name, ''), '-') AS system_name
    FROM fact_gifts g
    LEFT JOIN dim_gift_types t ON t.id = CAST(g.kind AS UNSIGNED)
    LEFT JOIN dim_systems s ON s.id = g.system_id
    ORDER BY g.id ASC
";

// g.name ASC, 

$result = $link->query($sql);
if (!$result) {
    http_response_code(500);
    echo 'Error SQL: ' . hg_gifts_gallery_h($link->error);
    exit;
}

while ($row = $result->fetch_assoc()) {
    if (trim((string)($row['image_url'] ?? '')) !== '') {
        $withImage++;
    }
    $gifts[] = $row;
}
$result->close();

$totalGifts = count($gifts);
$pendingGifts = max(0, $totalGifts - $withImage);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Galer&iacute;a de dones | Heaven's Gate</title>
    <style>
        :root {
            color-scheme: dark;
            --hg-bg: #03082c;
            --hg-panel: #07104b;
            --hg-panel-hover: #0b1767;
            --hg-border: #142b91;
            --hg-text: #f0f5ff;
            --hg-muted: #9fb7e4;
            --hg-accent: #33ffff;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            color: var(--hg-text);
            background: radial-gradient(circle at top, #0a1761 0, var(--hg-bg) 48%);
            font-family: Verdana, Arial, sans-serif;
        }

        .hg-gifts-gallery {
            max-width: 1440px;
            margin: 0 auto;
            padding: clamp(18px, 3vw, 36px);
        }

        .hg-gifts-gallery__head {
            margin-bottom: 24px;
        }

        .hg-gifts-gallery__head h1 {
            margin: 0 0 8px;
            color: var(--hg-accent);
            font-size: clamp(1.5rem, 3vw, 2.2rem);
            font-weight: 600;
        }

        .hg-gifts-gallery__head p {
            margin: 0;
            color: var(--hg-muted);
            font-size: .92rem;
            line-height: 1.6;
        }

        .hg-gifts-gallery__grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(176px, 1fr));
            gap: 16px;
        }

        .hg-gift-card {
            overflow: hidden;
            border: 1px solid var(--hg-border);
            border-radius: 12px;
            background: rgba(7, 16, 75, .92);
            transition: transform .15s ease, background .15s ease, border-color .15s ease;
        }

        .hg-gift-card:hover {
            transform: translateY(-2px);
            border-color: #2877c7;
            background: var(--hg-panel-hover);
        }

        .hg-gift-card__image-link {
            display: block;
            aspect-ratio: 1 / 1;
            background: rgba(0, 0, 0, .32);
        }

        .hg-gift-card__image {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .hg-gift-card__body {
            padding: 11px 12px 13px;
        }

        .hg-gift-card__title {
            margin: 0 0 6px;
            color: #fff;
            font-size: .93rem;
            line-height: 1.35;
            font-weight: 600;
        }

        .hg-gift-card__title a {
            color: inherit;
            text-decoration: none;
        }

        .hg-gift-card__title a:hover {
            color: var(--hg-accent);
        }

        .hg-gift-card__meta {
            display: block;
            color: var(--hg-muted);
            font-size: .72rem;
            line-height: 1.45;
        }

        .hg-gifts-gallery__empty {
            padding: 20px;
            border: 1px solid var(--hg-border);
            border-radius: 12px;
            background: var(--hg-panel);
            color: var(--hg-muted);
        }
    </style>
</head>
<body>
    <main class="hg-gifts-gallery">
        <header class="hg-gifts-gallery__head">
            <h1>Galer&iacute;a de dones</h1>
            <p>
                <?= $withImage ?> iconos asignados de <?= $totalGifts ?> dones.
                <?php if ($pendingGifts > 0): ?>Quedan <?= $pendingGifts ?> pendientes.<?php endif; ?>
            </p>
        </header>

        <?php if ($totalGifts === 0): ?>
            <p class="hg-gifts-gallery__empty">No hay dones disponibles.</p>
        <?php else: ?>
            <section class="hg-gifts-gallery__grid" aria-label="Dones e iconos">
                <?php foreach ($gifts as $gift):
                    $id = (int)($gift['id'] ?? 0);
                    $prettyId = (string)($gift['pretty_id'] ?? '');
                    $name = trim((string)($gift['name'] ?? 'Don sin nombre'));
                    $rank = trim((string)($gift['rank'] ?? ''));
                    $type = trim((string)($gift['gift_type'] ?? '-'));
                    $system = trim((string)($gift['system_name'] ?? '-'));
                    $href = hg_gifts_gallery_detail_url($id, $prettyId);
                    $image = hg_gifts_gallery_image_url((string)($gift['image_url'] ?? ''));
                    $meta = [
                        $rank !== '' ? 'Rango ' . $rank : 'Sin rango',
                        $type !== '' ? $type : '-',
                        $system !== '' ? $system : '-',
                    ];
                ?>
                    <article class="hg-gift-card">
                        <a class="hg-gift-card__image-link" href="<?= hg_gifts_gallery_h($href) ?>" aria-label="Ver don <?= hg_gifts_gallery_h($name) ?>">
                            <img
                                class="hg-gift-card__image"
                                src="<?= hg_gifts_gallery_h($image) ?>"
                                alt="Icono de <?= hg_gifts_gallery_h($name) ?>"
                                loading="lazy"
                            >
                        </a>
                        <div class="hg-gift-card__body">
                            <h2 class="hg-gift-card__title">
                                <a href="<?= hg_gifts_gallery_h($href) ?>"><?= hg_gifts_gallery_h($name) ?></a>
                            </h2>
                            <small class="hg-gift-card__meta"><?= hg_gifts_gallery_h(implode(' - ', $meta)) ?></small>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>
