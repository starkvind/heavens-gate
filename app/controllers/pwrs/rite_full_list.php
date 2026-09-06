<?php
setMetaFromPage("Rituales | Heaven's Gate", "Listado completo de rituales en formato extendido.", null, 'website');
// =======================
// Página: Todos los rituales (corporativo)
// Estilo mysqli / $link
// =======================

$pageSect = "Rituales";
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

function anchor_id($id) {
    return "ritual_" . (int)$id;
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

function hg_rites_markdown_text($value): string {
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

function hg_rites_markdown_field($value, string $fallback): string {
    $text = hg_rites_markdown_text((string)$value);
    return $text !== '' ? $text : $fallback;
}

function hg_rites_markdown_download(array $rituales): void {
    $lines = [
        '# Rituales',
        '',
        'Total: ' . count($rituales),
        '',
    ];

    foreach ($rituales as $r) {
        $name = trim((string)($r['ritual_name'] ?? 'Sin nombre'));
        $type = trim((string)($r['ritual_type'] ?? ''));
        $level = trim((string)($r['ritual_level'] ?? ''));
        $race = trim((string)($r['ritual_species'] ?? ''));
        $system = trim((string)($r['ritual_fera_system'] ?? ''));
        $origin = trim((string)($r['ritual_origin'] ?? ''));

        $lines[] = '## ' . $name;
        $lines[] = '';
        if ($type !== '') { $lines[] = '- Tipo: ' . $type; }
        if ($level !== '') { $lines[] = '- Nivel: ' . $level; }
        if ($race !== '' && $race !== $system) { $lines[] = '- Raza: ' . $race; }
        if ($system !== '') { $lines[] = '- Sistema: ' . $system; }
        if ($origin !== '') { $lines[] = '- Origen: ' . $origin; }
        $lines[] = '';
        $lines[] = '### Descripcion';
        $lines[] = '';
        $lines[] = hg_rites_markdown_field($r['ritual_description'] ?? '', 'Descripcion no disponible.');
        $rollText = hg_rites_markdown_text($r['ritual_roll_description'] ?? '');
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
    header('Content-Disposition: attachment; filename="rituales.md"');
    echo $body;
    exit;
}

$pageHref = h(current_page_href());
$printHref = h(current_page_href(['print' => '1']));
$markdownHref = h(current_page_href(['export' => 'md', 'print' => null]));

// =======================
// 1) Query principal (LA TUYA)
// =======================
$consulta = "
select
    nr.id as ritual_id,
    nr.name as ritual_name,
    CONCAT(
        'Rito',
        CASE
            WHEN ntr.determinant <> '' THEN CONCAT(' ', ntr.determinant)
            ELSE ''
        END,
        ' ',
        ntr.name
    ) as ritual_type,
    nr.level as ritual_level,
    nr.race as ritual_species,
    nr.description as ritual_description,
    nr.system_text as ritual_roll_description,
    s.name as ritual_fera_system,
    nr.system_id as ritual_system_id,
    nb.name as ritual_origin
from fact_rites nr
    left join dim_rite_types ntr on nr.kind = ntr.id
    left join dim_bibliographies nb on nr.bibliography_id = nb.id
    left join dim_systems s on nr.system_id = s.id
order by
    nr.bibliography_id,
    nr.level
";

$stmt = $link->prepare($consulta);
$stmt->execute();
$result = $stmt->get_result();

$rituales = [];
while ($row = $result->fetch_assoc()) {
    $rituales[] = $row;
}
$total = count($rituales);

if ($markdownMode) {
    hg_rites_markdown_download($rituales);
}

// =======================
// Agrupar rituales por Origen (para el índice)
// =======================
$ritualesPorOrigen = [];

foreach ($rituales as $r) {
    $origen = trim($r['ritual_origin'] ?? '');
    if ($origen === '') {
        $origen = 'Sin origen';
    }
    $ritualesPorOrigen[$origen][] = $r;
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

<div class="hg-rituales<?php echo $printMode ? ' hg-print' : ''; ?>">
  <div class="wrap">
    <?php if (!$printMode) { hg_render_power_catalog_tabs('rites', 'full'); } ?>

    <a id="top"></a>

    <div class="hero">
      <div class="title">
        <h2>Rituales</h2>
        <div class="count">Total: <b><?php echo (int)$total; ?></b></div>
      </div>
      <p>Listado completo de rituales, con acceso rápido y ficha completa (descripción + sistema).</p>
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

    <?php foreach ($ritualesPorOrigen as $origen => $lista): ?>
      <div class="index-origin">
        <h4 class="index-origin-title">
          <?php echo h($origen); ?>
          <span class="index-origin-count">(<?php echo count($lista); ?>)</span>
        </h4>

        <div class="grid">
          <?php foreach ($lista as $r):
              $id = (int)$r['ritual_id'];
              $anchor = anchor_id($id);

              $name = h($r['ritual_name']);
              $type = h($r['ritual_type']);
              $lvl  = h($r['ritual_level']);
          ?>
            <a class="item" href="<?php echo $pageHref; ?>#<?php echo h($anchor); ?>">
              <div class="label">
                <div class="badge">&diams;</div>
                <div class="txt">
                  <div class="meta"><?php echo "[{$type} · Nv. {$lvl}]"; ?></div>
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
      <?php foreach ($rituales as $r):
          $id   = (int)$r['ritual_id'];
          $anchor = anchor_id($id);

          $name = h($r['ritual_name']);
          $type = h($r['ritual_type']);
          $lvl  = h($r['ritual_level']);
          $race = h($r['ritual_species']);
          $sys  = h($r['ritual_fera_system']);
          $orig = h($r['ritual_origin'] ?? '');

          // CAMPOS LARGOS: no usar htmlspecialchars() (permitimos HTML guardado)
          $desc = $r['ritual_description'] ?: "<p>Descripción no disponible</p>";
          $roll = $r['ritual_roll_description'] ?: "<p><i>Sistema no disponible</i></p>";
      ?>
        <article class="card" id="<?php echo h($anchor); ?>">
          <div class="topline">
            <h3 class="name"><?php echo $name; ?></h3>
            <a class="back" href="<?php echo $pageHref; ?>#top">&uarr; Arriba</a>
          </div>

          <div class="chips">
            <span class="chip"><?php echo $type; ?></span>
            <span class="chip">Nivel <?php echo $lvl; ?></span>

            <?php if ($race !== '' && $sys != $race): ?>
              <span class="chip"><?php echo $race; ?></span>
            <?php endif; ?>

            <span class="chip"><?php echo $sys; ?></span>

            <?php if ($orig !== ''): ?>
              <span class="chip"><?php echo $orig; ?></span>
            <?php endif; ?>

            <?php if (1 == 0): ?>
              <span class="chip">ID <?php echo (int)$id; ?></span>
            <?php endif; ?>
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
      Rituales hallados: <b><?php echo (int)$total; ?></b>
    </div>

  </div>
</div>
