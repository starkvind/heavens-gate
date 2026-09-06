<?php
setMetaFromPage("Dones | Heaven's Gate", "Listado completo de dones en formato extendido.", null, 'website');
// =======================
// Página: Todos los fact_gifts (corporativo)
// Estilo mysqli / $link
// =======================

$pageSect = "Dones";
$_SESSION['punk2'] = $pageSect;
$printMode = isset($_GET['print']) && $_GET['print'] == '1';
$markdownMode = isset($_GET['export']) && $_GET['export'] === 'md';

if (!$printMode) {
    include("app/partials/main_nav_bar.php");
    include_once("app/partials/power_catalog_tabs.php");
}

// =======================
// Helpers
// =======================
function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function anchor_id_gift($id) {
    return "gift_" . (int)$id;
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

function hg_gifts_markdown_text($value): string {
    $text = html_entity_decode((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = preg_replace('~<\s*br\s*/?\s*>~i', "\n", $text);
    $text = preg_replace('~<\s*/p\s*>~i', "\n\n", $text);
    $text = preg_replace('~<\s*p[^>]*>~i', '', $text);
    $text = preg_replace('~<\s*/h[1-6]\s*>~i', "\n\n", $text);
    $text = preg_replace('~<\s*h([1-6])[^>]*>(.*?)<\s*/h[1-6]\s*>~is', "\n\n## $2\n\n", $text);
    $text = preg_replace('~<\s*li[^>]*>~i', "- ", $text);
    $text = preg_replace('~<\s*/li\s*>~i', "\n", $text);
    $text = preg_replace('~<\s*/?(ul|ol)[^>]*>~i', "\n", $text);
    $text = preg_replace('~<\s*hr\s*/?\s*>~i', "\n\n---\n\n", $text);
    $text = preg_replace('~<\s*(strong|b)[^>]*>(.*?)<\s*/\1\s*>~is', '**$2**', $text);
    $text = preg_replace('~<\s*(em|i)[^>]*>(.*?)<\s*/\1\s*>~is', '*$2*', $text);
    $text = preg_replace('~<\s*a[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\s*/a\s*>~is', '[$2]($1)', $text);
    $text = strip_tags($text);
    $text = preg_replace("/\n{3,}/", "\n\n", $text);
    return trim($text);
}

function hg_gifts_markdown_field($value, string $fallback): string {
    $text = hg_gifts_markdown_text((string)$value);
    return $text !== '' ? $text : $fallback;
}

function hg_gifts_markdown_download(array $gifts): void {
    $lines = [
        '# Dones',
        '',
        'Total: ' . count($gifts),
        '',
    ];

    foreach ($gifts as $g) {
        $name = trim((string)($g['gift_name'] ?? 'Sin nombre'));
        $type = trim((string)($g['gift_type'] ?? ''));
        $category = trim((string)($g['gift_category'] ?? ''));
        $level = trim((string)($g['gift_level'] ?? ''));
        $attribute = trim((string)($g['gift_roll_attribute'] ?? ''));
        $skill = trim((string)($g['gift_roll_skill'] ?? ''));
        $system = trim((string)($g['gift_fera_system'] ?? ''));
        $origin = trim((string)($g['gift_origin'] ?? ''));
        $rollShort = trim($attribute . ($attribute !== '' && $skill !== '' ? ' + ' : '') . $skill);

        $lines[] = '## ' . $name;
        $lines[] = '';
        if ($type !== '') { $lines[] = '- Tipo: ' . $type; }
        if ($category !== '') { $lines[] = '- Categoria: ' . $category; }
        if ($level !== '') { $lines[] = '- Rango: ' . $level; }
        if ($rollShort !== '') { $lines[] = '- Tirada: ' . $rollShort; }
        if ($system !== '') { $lines[] = '- Sistema/Fera: ' . $system; }
        if ($origin !== '') { $lines[] = '- Origen: ' . $origin; }
        $lines[] = '';
        $lines[] = '### Descripcion';
        $lines[] = '';
        $lines[] = hg_gifts_markdown_field($g['gift_description'] ?? '', 'Descripcion no disponible.');
        $rollText = hg_gifts_markdown_text($g['gift_roll_description'] ?? '');
        if ($rollText !== '') {
            $lines[] = '';
            $lines[] = '### Sistema';
            $lines[] = '';
            $lines[] = $rollText;
        }
        $lines[] = '';
    }

    $body = implode("\n", $lines) . "\n";
    header('Content-Type: text/markdown; charset=UTF-8');
    header('Content-Disposition: attachment; filename="dones.md"');
    echo $body;
    exit;
}

function gift_mechanics_col(mysqli $link): string {
    $rs = mysqli_query($link, "SHOW COLUMNS FROM `fact_gifts` LIKE 'mechanics_text'");
    if ($rs && mysqli_num_rows($rs) > 0) {
        mysqli_free_result($rs);
        return 'mechanics_text';
    }
    if ($rs) mysqli_free_result($rs);
    return 'system_name';
}
$giftRulesCol = gift_mechanics_col($link);
$pageHref = h(current_page_href());
$printHref = h(current_page_href(['print' => '1']));
$markdownHref = h(current_page_href(['export' => 'md', 'print' => null]));

// =======================
// 1) Query principal (LA TUYA)
// =======================
$consulta = "
select
    d.id as gift_id,
    d.name as gift_name,
    ntd.name as gift_type,
    d.gift_group as gift_category,
    d.rank as gift_level,
    d.attribute_name as gift_roll_attribute,
    d.ability_name as gift_roll_skill,
    d.description as gift_description,
    d.`$giftRulesCol` as gift_roll_description,
    s.name as gift_fera_system,
    d.system_id as gift_system_id,
    nb.name as gift_origin
from fact_gifts d
    left join dim_gift_types ntd on d.kind = ntd.id
    left join dim_bibliographies nb on d.bibliography_id = nb.id
    left join dim_systems s on d.system_id = s.id
order by d.bibliography_id, d.rank
";

$stmt = $link->prepare($consulta);
$stmt->execute();
$result = $stmt->get_result();

$gifts = [];
while ($row = $result->fetch_assoc()) {
    $gifts[] = $row;
}
$total = count($gifts);

if ($markdownMode) {
    hg_gifts_markdown_download($gifts);
}

// =======================
// Agrupar dones por Origen (para el índice)
// =======================
$giftsByOrigin = [];

foreach ($gifts as $g) {
    $origin = trim($g['gift_origin'] ?? '');
    if ($origin === '') {
        $origin = 'Sin origen';
    }
    $giftsByOrigin[$origin][] = $g;
}

// =======================
// 2) Render (CSS + HTML)
// =======================
?>
<?php
if (function_exists('hg_page_register_stylesheet')) {
    hg_page_register_stylesheet('/assets/css/hg-powers.css');
    hg_page_register_stylesheet('/assets/css/hg-powers-print.css');
} else {
    echo '<link rel="stylesheet" href="/assets/css/hg-powers.css">';
    echo '<link rel="stylesheet" href="/assets/css/hg-powers-print.css">';
}
?>

<div class="hg-dones<?php echo $printMode ? ' hg-print' : ''; ?>">
  <div class="wrap">
    <?php if (!$printMode) { hg_render_power_catalog_tabs('gifts', 'full'); } ?>

    <a id="top"></a>

    <div class="hero">
      <div class="title">
        <h2>Dones</h2>
        <div class="count">Total: <b><?php echo (int)$total; ?></b></div>
      </div>
      <p>Listado completo de dones, con acceso rápido y ficha completa (descripción + sistema).</p>
      <?php if (!$printMode): ?>
        <p style="margin-top:12px;">
          <a href="<?php echo $printHref; ?>" class="hg-print-btn">&#128424; Versi&oacute;n imprimible</a>
          <a href="<?php echo $markdownHref; ?>" class="hg-print-btn">.md Exportar Markdown</a>
        </p>
      <?php endif; ?>
    </div>

    <?php if ($total > 0): ?>
      <div class="index">
        <div class="head">
          <h3>Índice por Origen</h3>
          <div class="count"><?php echo (int)$total; ?> entradas</div>
        </div>

        <?php foreach ($giftsByOrigin as $origin => $list): ?>
          <div class="index-origin">
            <h4 class="index-origin-title">
              <?php echo h($origin); ?>
              <span class="index-origin-count">(<?php echo count($list); ?>)</span>
            </h4>

            <div class="grid">
              <?php foreach ($list as $g):
                  $id = (int)$g['gift_id'];
                  $anchor = anchor_id_gift($id);

                  $name = h($g['gift_name']);
                  $type = h($g['gift_type'] ?? '');
                  $lvl  = h($g['gift_level'] ?? '');
              ?>
                <a class="item" href="<?php echo $pageHref; ?>#<?php echo h($anchor); ?>">
                  <div class="label">
                    <div class="badge">&diams;</div>
                    <div class="txt">
                      <div class="meta"><?php echo ($type !== '' || $lvl !== '') ? "[{$type} · Nv. {$lvl}]" : ""; ?></div>
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
      <?php foreach ($gifts as $g):
          $id = (int)$g['gift_id'];
          $anchor = anchor_id_gift($id);

          $name = h($g['gift_name']);
          $type = h($g['gift_type'] ?? '');
          $cat  = h($g['gift_category'] ?? '');
          $lvl  = h($g['gift_level'] ?? '');
          $attr = h($g['gift_roll_attribute'] ?? '');
          $skill= h($g['gift_roll_skill'] ?? '');
          $fera = h($g['gift_fera_system'] ?? '');
          $orig = h($g['gift_origin'] ?? '');

          // CAMPOS LARGOS: no usar htmlspecialchars()
          $desc = $g['gift_description'] ?: "<p>Descripción no disponible</p>";
          $roll = $g['gift_roll_description'] ?: "<p><i>Sistema no disponible</i></p>";

          $rollShort = trim(($g['gift_roll_attribute'] ?? '') . ($attr !== '' && $skill !== '' ? ' + ' : '') . ($g['gift_roll_skill'] ?? ''));
          $rollShortSafe = h($rollShort);
      ?>
        <article class="card" id="<?php echo h($anchor); ?>">
          <div class="topline">
            <h3 class="name"><?php echo $name; ?></h3>
            <a class="back" href="<?php echo $pageHref; ?>#top">&uarr; Arriba</a>
          </div>

          <div class="chips">
            <?php if ($type !== ''): ?><span class="chip"><?php echo $type; ?></span><?php endif; ?>
            <?php if ($cat  !== ''): ?><span class="chip"><?php echo $cat; ?></span><?php endif; ?>
            <?php if ($lvl  !== ''): ?><span class="chip">Rango <?php echo $lvl; ?></span><?php endif; ?>
            <?php if ($rollShort !== ''): ?><span class="chip"><?php echo $rollShortSafe; ?></span><?php endif; ?>
            <?php if ($fera !== ''): ?><span class="chip"><?php echo $fera; ?></span><?php endif; ?>
            <?php if ($orig !== ''): ?><span class="chip"><?php echo $orig; ?></span><?php endif; ?>
          </div>

          <div class="sections">
            <div class="box">
              <h3>Descripción</h3>
              <div class="content"><?php echo $desc; ?></div>
            </div>

            <?php if ($roll != "<p><i>Sistema no disponible</i></p>"): ?>
              <div class="box">
                <h3>Sistema</h3>
                <div class="content"><?php echo $roll; ?></div>
              </div>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <div class="footer">
      Dones hallados: <b><?php echo (int)$total; ?></b>
    </div>

  </div>
</div>
