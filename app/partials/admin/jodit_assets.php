<?php
/** Shared pilot assets; requires controller-provided $joditConfig. */
?>
<script>window.HGJoditConfig = <?= json_encode($joditConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<?php if ($joditConfig['ready']): ?>
<link href="/assets/vendor/jodit/4.21.0/jodit.min.css" rel="stylesheet">
<script src="/assets/vendor/jodit/4.21.0/jodit.min.js"></script>
<script src="/assets/js/admin/hg-jodit-editor.js"></script>
<?php elseif (!$joditConfig['enabled']): ?>
<link href="/assets/vendor/quill/1.3.7/quill.snow.css" rel="stylesheet">
<script src="/assets/vendor/quill/1.3.7/quill.min.js"></script>
<?php endif; ?>
