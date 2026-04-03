<?php
include_once(__DIR__ . '/../../helpers/character_avatar.php');
include_once(__DIR__ . '/../../helpers/public_response.php');

if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

if (!function_exists('docs_page_table_exists')) {
  function docs_page_table_exists(mysqli $db, string $table): bool {
    $safe = mysqli_real_escape_string($db, preg_replace('/[^a-zA-Z0-9_]/', '', $table));
    if ($safe === '') return false;
    $sql = "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$safe}' LIMIT 1";
    $rs = mysqli_query($db, $sql);
    return ($rs && mysqli_num_rows($rs) > 0);
  }
}

if (!function_exists('docs_page_column_exists')) {
  function docs_page_column_exists(mysqli $db, string $table, string $column): bool {
    $safeTable = mysqli_real_escape_string($db, preg_replace('/[^a-zA-Z0-9_]/', '', $table));
    $safeColumn = mysqli_real_escape_string($db, preg_replace('/[^a-zA-Z0-9_]/', '', $column));
    if ($safeTable === '' || $safeColumn === '') return false;
    $sql = "SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = '{$safeTable}'
              AND COLUMN_NAME = '{$safeColumn}'
            LIMIT 1";
    $rs = mysqli_query($db, $sql);
    return ($rs && mysqli_num_rows($rs) > 0);
  }
}

// Asegurarse de que la conexion a la base de datos ($link) este definida y sea valida
if (!$link) {
    hg_public_log_error('docs_page', 'missing DB connection');
    hg_public_render_error('Documento no disponible', 'No se pudo cargar el documento en este momento.');
    return;
}

// Obtener id o pretty-id
$docRaw = $_GET['b'] ?? '';
$docId = resolve_pretty_id($link, 'fact_docs', (string)$docRaw) ?? 0;
if ($docId <= 0) {
  hg_public_render_not_found('Documento no encontrado', 'El documento solicitado no esta disponible.', true);
  return;
}

// Consulta preparada (id = ?, no LIKE)
$Query = "SELECT dz.title, d.kind AS section_id, dz.content, dz.source
          FROM fact_docs dz
          LEFT JOIN dim_doc_categories d ON d.id = dz.section_id
          WHERE dz.id = ? LIMIT 1";
$stmt = mysqli_prepare($link, $Query);
if (!$stmt) {
  hg_public_log_error('docs_page', 'prepare failed: ' . mysqli_error($link));
  hg_public_render_error('Documento no disponible', 'No se pudo cargar el documento en este momento.');
  return;
}

mysqli_stmt_bind_param($stmt, 'i', $docId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
if (!$result) {
  hg_public_log_error('docs_page', 'query failed: ' . mysqli_error($link));
  mysqli_stmt_close($stmt);
  hg_public_render_error('Documento no disponible', 'No se pudo cargar el documento en este momento.');
  return;
}

$ResultQuery = mysqli_fetch_assoc($result);
mysqli_free_result($result);
mysqli_stmt_close($stmt);

if (!$ResultQuery) { 
  hg_public_render_not_found('Documento no encontrado', 'El documento solicitado no esta disponible.', true);
  return;
} else {

$titleDoc = (string)$ResultQuery["title"];
$texto    = (string)$ResultQuery["content"];   // HTML (Quill) -> se imprime tal cual
$source   = (string)($ResultQuery["source"] ?? '');
$secciDoc = (string)($ResultQuery["section_id"] ?? 'Documento');
$docCharacters = [];
$hasDocCharacters = false;

if (docs_page_table_exists($link, 'bridge_characters_docs') && docs_page_table_exists($link, 'fact_characters')) {
  $characterKindSql = function_exists('hg_character_kind_select') ? hg_character_kind_select($link, 'c') : "''";
  $docSortOrder = docs_page_column_exists($link, 'bridge_characters_docs', 'sort_order')
    ? 'b.sort_order ASC, c.name ASC'
    : 'c.name ASC';
  $sqlDocCharacters = "SELECT c.id, c.name, c.alias, c.image_url, c.gender, COALESCE(dcs.label, '') AS status, {$characterKindSql} AS character_kind
                      FROM bridge_characters_docs b
                      INNER JOIN fact_characters c ON c.id = b.character_id
                      LEFT JOIN dim_character_status dcs ON dcs.id = c.status_id
                      WHERE b.doc_id = ?
                      ORDER BY {$docSortOrder}";
  if ($stChars = $link->prepare($sqlDocCharacters)) {
    $stChars->bind_param('i', $docId);
    $stChars->execute();
    $rsChars = $stChars->get_result();
    while ($rsChars && ($rowChar = $rsChars->fetch_assoc())) {
      $docCharacters[] = $rowChar;
    }
    $stChars->close();
  }
  $hasDocCharacters = !empty($docCharacters);
}

// Para tu sistema de titulos
$pageSect   = "Documento";
$pageTitle2 = $titleDoc;
setMetaFromPage($titleDoc . " | Documentos | Heaven's Gate", meta_excerpt($texto), null, 'article');

// Barra navegacion (la tuya)
include("app/partials/main_nav_bar.php");
echo '<link rel="stylesheet" href="/assets/css/hg-docs.css">';
?>



<div class="doc-page" id="docRoot">
  <div class="doc-wrap">
    <div class="theme-switch" aria-label="Cambiar tema">
      <button type="button" class="theme-btn" data-theme="og" title="Tema OG"><span class="ico">◇</span>OG</button>
      <button type="button" class="theme-btn" data-theme="light" title="Tema claro"><span class="ico">☀</span>Claro</button>
      <button type="button" class="theme-btn" data-theme="terminal" title="Tema terminal"><span class="ico">⌘</span>Terminal</button>
    </div>

    <div class="doc-card">
      <h1 class="doc-title"><?= htmlspecialchars($titleDoc, ENT_QUOTES, 'UTF-8') ?></h1>

      <div class="doc-meta">
        <span class="doc-chip"><?= htmlspecialchars($secciDoc, ENT_QUOTES, 'UTF-8') ?></span>
      </div>

      <div class="doc-body">
        <?= $texto /* HTML guardado */ ?>
      </div>

      <?php if (trim(strip_tags($source)) !== ''): ?>
        <div class="doc-source">
          <strong>Fuente:</strong>
          <div class="doc-source-top"><?= $source ?></div>
        </div>
      <?php endif; ?>

      <?php if ($hasDocCharacters): ?>
        <div class="doc-source">
          <strong>Personajes relacionados:</strong>
          <div class="grupoBioClan">
            <div class="contenidoAfiliacion">
              <?php foreach ($docCharacters as $char): ?>
                <?php
                  $charId = (int)($char['id'] ?? 0);
                  $charName = (string)($char['name'] ?? '');
                  $charAlias = (string)($char['alias'] ?? '');
                  $charHref = pretty_url($link, 'fact_characters', '/characters', $charId);
                  hg_render_character_avatar_tile([
                    'href' => $charHref,
                    'title' => $charName,
                    'name' => $charName,
                    'alias' => $charAlias,
                    'character_id' => $charId,
                    'image_url' => (string)($char['image_url'] ?? ''),
                    'gender' => (string)($char['gender'] ?? ''),
                    'status' => (string)($char['status'] ?? ''),
                    'character_kind' => hg_character_kind_from_row($char),
                    'target_blank' => true,
                  ]);
                ?>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
(function(){
  var root = document.getElementById('docRoot');
  var btns = document.querySelectorAll('.theme-btn');
  var KEY = 'hg_doc_theme';

  function apply(theme){
    // og = sin atributo
    if (theme === 'og') root.removeAttribute('data-doc-theme');
    else root.setAttribute('data-doc-theme', theme);

    btns.forEach(function(b){
      b.classList.toggle('active', b.getAttribute('data-theme') === theme);
    });

    try { localStorage.setItem(KEY, theme); } catch(e){}
  }

  btns.forEach(function(b){
    b.addEventListener('click', function(){
      apply(b.getAttribute('data-theme') || 'og');
    });
  });

  var saved = 'og';
  try { saved = localStorage.getItem(KEY) || 'og'; } catch(e){}
  if (!saved) saved = 'og';
  apply(saved);
})();
</script>


<?php } ?>
