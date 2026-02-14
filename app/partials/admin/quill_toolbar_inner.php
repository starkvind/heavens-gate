<?php
if (!function_exists('admin_quill_toolbar_inner')) {
    function admin_quill_toolbar_inner(): string {
        return <<<HTML
<span class="ql-formats">
  <select class="ql-header">
    <option value="1"></option>
    <option value="2"></option>
    <option value="3"></option>
    <option value="4"></option>
    <option selected></option>
  </select>
  <select class="ql-size"></select>
</span>
<span class="ql-formats">
  <select class="ql-color"></select>
  <select class="ql-background"></select>
</span>
<span class="ql-formats">
  <button class="ql-bold"></button>
  <button class="ql-italic"></button>
  <button class="ql-underline"></button>
  <button class="ql-strike"></button>
</span>
<span class="ql-formats">
  <button class="ql-blockquote"></button>
  <button class="ql-code-block"></button>
</span>
<span class="ql-formats">
  <button class="ql-list" value="ordered"></button>
  <button class="ql-list" value="bullet"></button>
  <button class="ql-indent" value="-1"></button>
  <button class="ql-indent" value="+1"></button>
</span>
<span class="ql-formats">
  <select class="ql-align"></select>
</span>
<span class="ql-formats">
  <button class="ql-link"></button>
  <button class="ql-clean"></button>
</span>
HTML;
    }
}
