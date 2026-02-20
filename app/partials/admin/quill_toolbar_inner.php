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
  <select class="ql-color">
    <option selected></option>
    <option value="#000000"></option>
    <option value="#e60000"></option>
    <option value="#ff9900"></option>
    <option value="#ffff00"></option>
    <option value="#008a00"></option>
    <option value="#0066cc"></option>
    <option value="#9933ff"></option>
    <option value="#ffffff"></option>
    <option value="#facccc"></option>
    <option value="#ffebcc"></option>
    <option value="#ffffcc"></option>
    <option value="#cce8cc"></option>
    <option value="#cce0f5"></option>
    <option value="#ebd6ff"></option>
    <option value="#bbbbbb"></option>
    <option value="#f06666"></option>
    <option value="#ffc266"></option>
    <option value="#ffff66"></option>
    <option value="#66b966"></option>
    <option value="#66a3e0"></option>
    <option value="#c285ff"></option>
    <option value="#888888"></option>
    <option value="#a10000"></option>
    <option value="#b26b00"></option>
    <option value="#b2b200"></option>
    <option value="#006100"></option>
    <option value="#0047b2"></option>
    <option value="#6b24b2"></option>
    <option value="#444444"></option>
    <option value="#5c0000"></option>
    <option value="#663d00"></option>
    <option value="#666600"></option>
    <option value="#003700"></option>
    <option value="#002966"></option>
    <option value="#3d1466"></option>
  </select>
  <select class="ql-background">
    <option selected></option>
    <option value="#000000"></option>
    <option value="#e60000"></option>
    <option value="#ff9900"></option>
    <option value="#ffff00"></option>
    <option value="#008a00"></option>
    <option value="#0066cc"></option>
    <option value="#9933ff"></option>
    <option value="#ffffff"></option>
    <option value="#facccc"></option>
    <option value="#ffebcc"></option>
    <option value="#ffffcc"></option>
    <option value="#cce8cc"></option>
    <option value="#cce0f5"></option>
    <option value="#ebd6ff"></option>
    <option value="#bbbbbb"></option>
    <option value="#f06666"></option>
    <option value="#ffc266"></option>
    <option value="#ffff66"></option>
    <option value="#66b966"></option>
    <option value="#66a3e0"></option>
    <option value="#c285ff"></option>
    <option value="#888888"></option>
    <option value="#a10000"></option>
    <option value="#b26b00"></option>
    <option value="#b2b200"></option>
    <option value="#006100"></option>
    <option value="#0047b2"></option>
    <option value="#6b24b2"></option>
    <option value="#444444"></option>
    <option value="#5c0000"></option>
    <option value="#663d00"></option>
    <option value="#666600"></option>
    <option value="#003700"></option>
    <option value="#002966"></option>
    <option value="#3d1466"></option>
  </select>
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
