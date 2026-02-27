<?php
// Obtener id o pretty-id
$docRaw = $_GET['b'] ?? '';
$docId = resolve_pretty_id($link, 'fact_docs', (string)$docRaw) ?? 0;
if ($docId <= 0) { print("Documento invalido."); }

// Asegurarse de que la conexion a la base de datos ($link) este definida y sea valida
if (!$link) {
    print("Error de conexion a la base de datos: " . mysqli_connect_error());
}

// Consulta preparada (id = ?, no LIKE)
$Query = "SELECT dz.title, d.kind AS section_id, dz.content, dz.source
          FROM fact_docs dz
          LEFT JOIN dim_doc_categories d ON d.id = dz.section_id
          WHERE dz.id = ? LIMIT 1";
$stmt = mysqli_prepare($link, $Query);
if (!$stmt) { print("Error al preparar la consulta: " . mysqli_error($link)); }

mysqli_stmt_bind_param($stmt, 'i', $docId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
if (!$result) { print("Error en la consulta: " . mysqli_error($link)); }

$ResultQuery = mysqli_fetch_assoc($result);
mysqli_free_result($result);
mysqli_stmt_close($stmt);

if (!$ResultQuery) { 
  print("Documento no encontrado."); 
} else {

$titleDoc = (string)$ResultQuery["title"];
$texto    = (string)$ResultQuery["content"];   // HTML (Quill) -> se imprime tal cual
$source   = (string)($ResultQuery["source"] ?? '');
$secciDoc = (string)($ResultQuery["section_id"] ?? 'Documento');

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
