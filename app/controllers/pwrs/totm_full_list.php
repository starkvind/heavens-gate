<?php
setMetaFromPage("Tótems | Heaven's Gate", "Listado completo de tótems en formato extendido.", null, 'website');

$pageSect = "Tótems";
$_SESSION['punk2'] = $pageSect;
$printMode = isset($_GET['print']) && $_GET['print'] == '1';

if (!$printMode) {
    include("app/partials/main_nav_bar.php");
}

function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function anchor_id_totem($id) {
    return "totem_" . (int)$id;
}

function current_page_href(array $replaceQuery = []) {
    $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '/');
    $parts = parse_url($requestUri);

    $path = (string)($parts['path'] ?? '/');
    $query = [];

    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
    }

    foreach ($replaceQuery as $key => $value) {
        if ($value === null || $value === '') {
            unset($query[$key]);
            continue;
        }
        $query[$key] = $value;
    }

    $queryString = http_build_query($query);
    return $path . ($queryString !== '' ? '?' . $queryString : '');
}

function totem_image_src($value) {
    $img = trim((string)$value);
    if ($img === '') {
        return '/img/ui/icons/icon_totem.png';
    }
    if (preg_match('#^https?://#i', $img) || strncmp($img, '/', 1) === 0) {
        return $img;
    }
    if (strpos($img, '/') !== false) {
        return '/' . ltrim($img, '/');
    }
    return '/img/totems/' . ltrim($img, '/');
}

$pageHref = h(current_page_href());
$printHref = h(current_page_href(['print' => '1']));

$consulta = "
select
    t.id as totem_id,
    t.name as totem_name,
    CONCAT(
        'Tótem',
        CASE
            WHEN tt.determinant <> '' THEN CONCAT(' ', tt.determinant)
            ELSE ''
        END,
        ' ',
        tt.name
    ) as totem_type,
    t.cost as totem_cost,
    t.description as totem_description,
    t.traits as totem_traits,
    t.prohibited as totem_prohibited,
    t.image_url as totem_image_url,
    b.name as totem_origin
from dim_totems t
    left join dim_totem_types tt on t.totem_type_id = tt.id
    left join dim_bibliographies b on t.bibliography_id = b.id
order by
    t.bibliography_id,
    t.cost,
    t.name
";

$stmt = $link->prepare($consulta);
$stmt->execute();
$result = $stmt->get_result();

$totems = [];
while ($row = $result->fetch_assoc()) {
    $totems[] = $row;
}
$total = count($totems);

$totemsByOrigin = [];
foreach ($totems as $t) {
    $origin = trim((string)($t['totem_origin'] ?? ''));
    if ($origin === '') {
        $origin = 'Sin origen';
    }
    $totemsByOrigin[$origin][] = $t;
}
?>
<style>
.hg-totems {
    --bg: #f6f7fb;
    --card: #ffffff;
    --text: #1b1f2a;
    --muted: #667085;
    --line: #e6e8f0;
    --shadow2: 0 6px 14px rgba(16, 24, 40, .08);
    --radius: 14px;
    --radius2: 10px;
    --accent: #2b6cb0;
    --accent2: #0f172a;
    --chip: #f2f4f7;

    background: var(--bg);
    color: var(--text);
    font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
    line-height: 1.45;
    padding: 20px 0 40px;
}

.hg-totems h2, .hg-totems h3 {
    color: var(--accent2);
    margin: 0;
    padding: 0;
    border: 0;
    background: none;
}

.hg-totems a { color: var(--accent); text-decoration: none; }
.hg-totems a:hover { text-decoration: underline; }

.hg-totems .wrap {
    max-width: 1100px;
    margin: 0 auto;
    padding: 0 16px;
}

.hg-totems .hero,
.hg-totems .index,
.hg-totems .card {
    background: var(--card);
    border: 1px solid var(--line);
    border-radius: var(--radius);
    box-shadow: var(--shadow2);
}

.hg-totems .hero {
    background: linear-gradient(180deg, #ffffff, #fbfbfe);
    padding: 18px;
}

.hg-totems .hero .title,
.hg-totems .index .head,
.hg-totems .card .topline {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 12px;
}

.hg-totems .hero h2 { font-size: 22px; letter-spacing: .2px; }
.hg-totems .hero .count,
.hg-totems .index .count,
.hg-totems .footer { color: var(--muted); font-size: 13px; }
.hg-totems .hero p { margin: 10px 0 0; color: var(--muted); }

.hg-totems .index {
    margin-top: 16px;
    padding: 16px;
}

.hg-totems .index .head h3 {
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: var(--muted);
}

.hg-totems .index-origin { margin-top: 14px; }
.hg-totems .index-origin-title {
    font-size: 14px;
    font-weight: 700;
    color: var(--accent2);
    margin: 8px 0;
    padding-bottom: 4px;
    border-bottom: 1px solid var(--line);
    display: flex;
    align-items: baseline;
    gap: 6px;
}
.hg-totems .index-origin-count { font-size: 12px; font-weight: 500; color: var(--muted); }

.hg-totems .grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px 14px;
}

@media (max-width: 820px) {
    .hg-totems .grid { grid-template-columns: 1fr; }
}

.hg-totems .item {
    display: block;
    padding: 10px 12px;
    border: 1px solid var(--line);
    border-radius: var(--radius2);
    background: #fff;
    transition: transform .08s ease, box-shadow .08s ease;
}

.hg-totems .item:hover {
    transform: translateY(-1px);
    box-shadow: var(--shadow2);
    text-decoration: none;
}

.hg-totems .label { display: flex; gap: 10px; align-items: flex-start; }
.hg-totems .badge {
    flex: 0 0 auto;
    width: 22px;
    height: 22px;
    border-radius: 6px;
    background: var(--chip);
    display: grid;
    place-items: center;
    border: 1px solid var(--line);
    margin-top: 1px;
}

.hg-totems .txt { min-width: 0; }
.hg-totems .meta,
.hg-totems .thumb-fallback {
    font-size: 12px;
    color: var(--muted);
}

.hg-totems .meta,
.hg-totems .name {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.hg-totems .name {
    font-size: 14px;
    color: var(--accent2);
    font-weight: 600;
}

.hg-totems .cards { margin-top: 18px; display: grid; gap: 14px; }
.hg-totems .card { padding: 16px; }

.hg-totems .topline {
    align-items: flex-start;
}

.hg-totems .topline-left {
    display: flex;
    gap: 12px;
    align-items: center;
    min-width: 0;
}

.hg-totems .thumb {
    width: 64px;
    height: 64px;
    border-radius: 12px;
    border: 1px solid var(--line);
    object-fit: cover;
    background: #fff;
    box-shadow: 0 1px 4px rgba(16, 24, 40, .08);
}

.hg-totems .topline .name {
    font-size: 18px;
    font-weight: 700;
    color: var(--accent2);
    margin: 0;
}

.hg-totems .back {
    font-size: 12px;
    color: var(--muted);
    padding: 6px 10px;
    border-radius: 999px;
    border: 1px solid var(--line);
    background: #fff;
    white-space: nowrap;
}

.hg-totems .chips {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 12px;
}

.hg-totems .chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    border-radius: 999px;
    font-size: 12px;
    background: var(--chip);
    border: 1px solid var(--line);
    color: #344054;
}

.hg-totems .sections {
    margin-top: 14px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.hg-totems .box {
    border: 1px solid var(--line);
    border-radius: var(--radius2);
    background: #fff;
    padding: 12px;
}

.hg-totems .box h3 {
    font-size: 13px;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: var(--muted);
    margin-bottom: 8px;
}

.hg-totems .box .content { color: var(--text); font-size: 14px; }
.hg-totems .box .content p { margin: 0 0 10px; }
.hg-totems .box .content p:last-child { margin-bottom: 0; }
.hg-totems .box .content ul,
.hg-totems .box .content ol { margin: 8px 0 8px 22px; }
.hg-totems .box .content hr { border: 0; border-top: 1px solid var(--line); margin: 10px 0; }
.hg-totems .box .content img { max-width: 100%; height: auto; }

.hg-totems .footer { margin-top: 16px; text-align: right; }

.hg-totems .hg-print-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 12px;
    border-radius: 999px;
    border: 1px solid var(--line);
    background: #fff;
    color: var(--accent2);
    font-weight: 600;
    font-size: 13px;
    box-shadow: var(--shadow2);
}

.hg-totems .hg-print-btn:hover { text-decoration: none; transform: translateY(-1px); }

.hg-totems.hg-print {
    --bg: #ffffff;
    --card: #ffffff;
    --text: #000000;
    --muted: #333333;
    --line: #d0d0d0;
    --shadow2: none;
    --chip: #f3f3f3;
    padding: 0;
}

.hg-totems.hg-print .hero { box-shadow: none; background: #fff; }
.hg-totems.hg-print .index { display: none; }
.hg-totems.hg-print .back { display: none; }

@media print {
    body * { visibility: hidden !important; }

    .hg-totems,
    .hg-totems * { visibility: visible !important; }

    .hg-totems {
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        width: 100% !important;
        background: #fff !important;
        color: #000 !important;
        padding: 0 !important;
    }

    .hg-totems .wrap {
        max-width: none !important;
        padding: 0 !important;
        margin: 0 !important;
    }

    .hg-totems .hg-print-btn,
    .hg-totems .back,
    .hg-totems .index { display: none !important; }

    .hg-totems .card {
        border: 1px solid #bbb !important;
        box-shadow: none !important;
        break-inside: avoid;
        page-break-inside: avoid;
    }

    .hg-totems .chip {
        background: #f3f3f3 !important;
        border: 1px solid #cfcfcf !important;
        color: #111 !important;
    }

    .hg-totems .box { border: 1px solid #ccc !important; }
    .hg-totems h3 { break-after: avoid-page; page-break-after: avoid; }
    .hg-totems a { color: #000 !important; text-decoration: none !important; }
}
</style>

<div class="hg-totems<?php echo $printMode ? ' hg-print' : ''; ?>">
  <div class="wrap">

    <a id="top"></a>

    <div class="hero">
      <div class="title">
        <h2>T&oacute;tems</h2>
        <div class="count">Total: <b><?php echo (int)$total; ?></b></div>
      </div>
      <p>Listado completo de t&oacute;tems, con acceso r&aacute;pido y ficha completa.</p>
      <?php if (!$printMode): ?>
        <p style="margin-top:12px;">
          <a href="<?php echo $printHref; ?>" class="hg-print-btn">&#128424; Versi&oacute;n imprimible</a>
        </p>
      <?php endif; ?>
    </div>

<?php if ($total > 0): ?>
  <div class="index">
    <div class="head">
      <h3>Indice por Origen</h3>
      <div class="count"><?php echo (int)$total; ?> entradas</div>
    </div>

    <?php foreach ($totemsByOrigin as $origin => $list): ?>
      <div class="index-origin">
        <h4 class="index-origin-title">
          <?php echo h($origin); ?>
          <span class="index-origin-count">(<?php echo count($list); ?>)</span>
        </h4>

        <div class="grid">
          <?php foreach ($list as $t):
              $id = (int)$t['totem_id'];
              $anchor = anchor_id_totem($id);
              $name = h($t['totem_name']);
              $type = h($t['totem_type']);
              $cost = h($t['totem_cost']);
          ?>
            <a class="item" href="<?php echo $pageHref; ?>#<?php echo h($anchor); ?>">
              <div class="label">
                <div class="badge">&diams;</div>
                <div class="txt">
                  <div class="meta"><?php echo "[{$type} · Coste {$cost}]"; ?></div>
                  <div class="name"><?php echo $name; ?></div>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

    <div class="cards">
      <?php foreach ($totems as $t):
          $id = (int)$t['totem_id'];
          $anchor = anchor_id_totem($id);

          $name = h($t['totem_name']);
          $type = h($t['totem_type']);
          $cost = h($t['totem_cost']);
          $origin = h($t['totem_origin'] ?? '');
          $imageSrc = h(totem_image_src($t['totem_image_url'] ?? ''));

          $desc = $t['totem_description'] ?: "<p>Descripción no disponible.</p>";
          $traits = $t['totem_traits'] ?: '';
          $prohibited = $t['totem_prohibited'] ?: '';
      ?>
        <article class="card" id="<?php echo h($anchor); ?>">
          <div class="topline">
            <div class="topline-left">
              <img class="thumb" src="<?php echo $imageSrc; ?>" alt="<?php echo $name; ?>">
              <h3 class="name"><?php echo $name; ?></h3>
            </div>
            <a class="back" href="<?php echo $pageHref; ?>#top">&uarr; Arriba</a>
          </div>

          <div class="chips">
            <span class="chip"><?php echo $type; ?></span>
            <span class="chip">Coste <?php echo $cost; ?></span>
            <?php if ($origin !== ''): ?>
              <span class="chip"><?php echo $origin; ?></span>
            <?php endif; ?>
          </div>

          <div class="sections">
            <div class="box">
              <h3>Descripción</h3>
              <div class="content"><?php echo $desc; ?></div>
            </div>

            <?php if ($traits !== ''): ?>
              <div class="box">
                <h3>Rasgos</h3>
                <div class="content"><?php echo $traits; ?></div>
              </div>
            <?php endif; ?>

            <?php if ($prohibited !== ''): ?>
              <div class="box">
                <h3>Prohibicion</h3>
                <div class="content"><?php echo $prohibited; ?></div>
              </div>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <div class="footer">
      Tótems hallados: <b><?php echo (int)$total; ?></b>
    </div>

  </div>
</div>
