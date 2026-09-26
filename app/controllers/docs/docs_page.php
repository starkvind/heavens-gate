<?php
include_once(__DIR__ . '/../../helpers/character_avatar.php');
include_once(__DIR__ . '/../../helpers/public_response.php');
require_once(__DIR__ . '/../../domains/documents/queries.php');

if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

// Asegurarse de que la conexion a la base de datos ($link) este definida y sea valida
if (!$link) {
    hg_public_log_error('docs_page', 'missing DB connection');
    hg_public_render_error('Documento no disponible', 'No se pudo cargar el documento en este momento.');
    return;
}

// Obtener id o pretty-id
$docRaw = hg_request_param($hgRequest, 'document');
$docId = resolve_pretty_id($link, 'fact_docs', (string)$docRaw) ?? 0;
if ($docId <= 0) {
  hg_public_render_not_found('Documento no encontrado', 'El documento solicitado no esta disponible.', true);
  return;
}

$ResultQuery = hg_documents_fetch_detail($link, $docId);
if ($ResultQuery === false) {
  hg_public_log_error('docs_page', 'query failed: ' . mysqli_error($link));
  hg_public_render_error('Documento no disponible', 'No se pudo cargar el documento en este momento.');
  return;
}
if (!$ResultQuery) {
  hg_public_render_not_found('Documento no encontrado', 'El documento solicitado no esta disponible.', true);
  return;
}

$titleDoc = (string)$ResultQuery["title"];
$texto    = (string)$ResultQuery["content"];
$source   = (string)($ResultQuery["source"] ?? '');
$secciDoc = (string)($ResultQuery["section_id"] ?? 'Documento');
$docCharacters = hg_documents_fetch_characters($link, $docId);
$hasDocCharacters = !empty($docCharacters);

// Para tu sistema de titulos
$pageSect   = "Documento";
$pageTitle2 = $titleDoc;
setMetaFromPage($titleDoc . " | Documentos | Heaven's Gate", meta_excerpt($texto), null, 'article');

// Barra navegacion (la tuya)
include("app/partials/main_nav_bar.php");
if (function_exists('hg_page_register_stylesheet')) {
    hg_page_register_stylesheet('/assets/css/hg-docs.css');
} else {
    if (function_exists('hg_page_register_stylesheet')) {
        hg_page_register_stylesheet('/assets/css/hg-docs.css');
    } else {
        echo '<link rel="stylesheet" href="/assets/css/hg-docs.css">';
    }
}
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
