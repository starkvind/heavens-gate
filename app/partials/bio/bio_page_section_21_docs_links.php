<?php
$docsRows = isset($characterDocs) && is_array($characterDocs) ? $characterDocs : [];
$externalRows = isset($characterExternalLinks) && is_array($characterExternalLinks) ? $characterExternalLinks : [];

if (empty($docsRows) && empty($externalRows)) {
    echo "<p>No hay documentacion vinculada para este personaje.</p>";
    return;
}
?>

<div class="bioSheetPowers bio-doc-links-wrap">
  <?php if (!empty($docsRows)): ?>
    <div class="bio-doc-links-group">
    <div class="bio-doc-links-title">Documentos internos</div>
      <div class="bio-doc-links-content">
      <?php foreach ($docsRows as $row): ?>
        <?php
          $docId = (int)($row['doc_id'] ?? 0);
          $docTitle = trim((string)($row['title'] ?? ''));
          $docSection = trim((string)($row['section_name'] ?? ''));
          $docRel = trim((string)($row['relation_label'] ?? ''));
          $docHref = $docId > 0 ? pretty_url($link, 'fact_docs', '/documents', $docId) : '#';
          $meta = $docSection !== '' ? $docSection : 'Documento';
          if ($docRel !== '') {
            $meta .= ' | ' . $docRel;
          }
        ?>
        <a href="<?= h($docHref) ?>" target="_blank" rel="noopener noreferrer">
          <div class="bioSheetPower bio-doc-link-card" title="<?= h($meta) ?>">
            <img class="valign bio-inline-icon" src="img/ui/icons/icon_document.png" alt="" />
            <?= h($docTitle !== '' ? $docTitle : ('Documento #'.$docId)) ?>
            <div class="bio-inline-type"><?= h($docSection !== '' ? $docSection : 'doc') ?></div>
          </div>
        </a>
      <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if (!empty($externalRows)): ?>
    <div class="bio-doc-links-group">
    <div class="bio-doc-links-title">Enlaces externos</div>
    <div class="bio-doc-links-content">
      <?php foreach ($externalRows as $row): ?>
        <?php
          $title = trim((string)($row['title'] ?? ''));
          $url = trim((string)($row['url'] ?? ''));
          $kind = trim((string)($row['kind'] ?? ''));
          $source = trim((string)($row['source_label'] ?? ''));
          $rel = trim((string)($row['relation_label'] ?? ''));
          $active = (int)($row['is_active'] ?? 1) === 1;
          $desc = trim((string)($row['description'] ?? ''));
          $meta = $kind !== '' ? $kind : 'Enlace';
          if ($source !== '') {
            $meta .= ' | ' . $source;
          }
          if ($rel !== '') {
            $meta .= ' | ' . $rel;
          }
          if (!$active) {
            $meta .= ' | inactivo';
          }
          if ($desc !== '') {
            $meta .= ' | ' . $desc;
          }
        ?>
        <a href="<?= h($url !== '' ? $url : '#') ?>" target="_blank" rel="noopener noreferrer">
          <div class="bioSheetPower bio-doc-link-card" title="<?= h($meta) ?>">
            <img class="valign bio-inline-icon" src="img/ui/icons/icon_document.png" alt="" />
            <?= h($title !== '' ? $title : $url) ?>
            <div class="bio-inline-type"><?= h($kind !== '' ? $kind : 'ext') ?></div>
          </div>
        </a>
      <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</div>
