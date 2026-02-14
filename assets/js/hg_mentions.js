/* global window, document, fetch */
(function(){
  'use strict';

  function createDropdown() {
    var box = document.createElement('div');
    box.className = 'hg-mention-box';
    box.style.display = 'none';
    box.innerHTML = '<div class="hg-mention-list"></div>';
    document.body.appendChild(box);
    return box;
  }

  function positionDropdown(box, anchorEl) {
    if (!box || !anchorEl) return;
    var rect = anchorEl.getBoundingClientRect();
    box.style.left = (rect.left + window.scrollX) + 'px';
    box.style.top = (rect.bottom + window.scrollY + 6) + 'px';
    box.style.minWidth = Math.max(240, rect.width) + 'px';
  }

  function fetchMentions(type, q) {
    var url = '/ajax/mentions?type=' + encodeURIComponent(type) + '&q=' + encodeURIComponent(q || '');
    return fetch(url, { credentials: 'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (data && data.ok && Array.isArray(data.items)) return data.items;
        return [];
      })
      .catch(function(){ return []; });
  }

  function renderList(box, items, onPick) {
    var list = box.querySelector('.hg-mention-list');
    list.innerHTML = '';
    if (!items.length) {
      list.innerHTML = '<div class="hg-mention-empty">No results</div>';
      return;
    }
    items.forEach(function(it, idx){
      var row = document.createElement('div');
      row.className = 'hg-mention-item';
      row.dataset.index = String(idx);
      var meta = it.type;
      if (it.chronicle_name) meta += ' · ' + it.chronicle_name;
      row.textContent = it.label + ' (' + meta + ')';
      row.addEventListener('click', function(){ onPick(it); });
      list.appendChild(row);
    });
  }

  function setupKeyboard(box, items, onPick) {
    var active = 0;
    function highlight() {
      var rows = box.querySelectorAll('.hg-mention-item');
      rows.forEach(function(r){ r.classList.remove('active'); });
      if (rows[active]) rows[active].classList.add('active');
    }
    highlight();
    return function onKey(e) {
      if (!items.length) return;
      if (e.key === 'ArrowDown') {
        active = (active + 1) % items.length;
        highlight();
        e.preventDefault();
      } else if (e.key === 'ArrowUp') {
        active = (active - 1 + items.length) % items.length;
        highlight();
        e.preventDefault();
      } else if (e.key === 'Enter') {
        onPick(items[active]);
        e.preventDefault();
      } else if (e.key === 'Escape') {
        box.style.display = 'none';
      }
    };
  }

  function attachQuill(quill, opts) {
    if (!quill) return;
    var types = (opts && opts.types) ? opts.types : [];
    var dropdown = createDropdown();
    var lastQuery = '';
    var lastType = '';
    var keyHandler = null;
    var debounce = null;

    function closeBox() {
      dropdown.style.display = 'none';
      if (keyHandler) {
        quill.root.removeEventListener('keydown', keyHandler);
        keyHandler = null;
      }
    }

    function openBox(items, onPick) {
      renderList(dropdown, items, onPick);
      dropdown.style.display = 'block';
      positionDropdown(dropdown, quill.root);
      if (keyHandler) quill.root.removeEventListener('keydown', keyHandler);
      keyHandler = setupKeyboard(dropdown, items, onPick);
      quill.root.addEventListener('keydown', keyHandler);
    }

    function insertMention(it, range, startIndex, tokenLen) {
      var href = it.href || '';
      var label = it.label || it.pretty_id || ('#' + it.id);
      quill.deleteText(startIndex, tokenLen, 'user');
      quill.insertText(startIndex, label, 'link', href, 'user');
      quill.insertText(startIndex + label.length, ' ', 'user');
      quill.setSelection(startIndex + label.length + 1, 0, 'user');
      closeBox();
    }

    quill.on('text-change', function(){
      var range = quill.getSelection();
      if (!range) return closeBox();
      var text = quill.getText(0, range.index);
      var match = text.match(/@([a-z_]+)\s([^@\n]*)$/i);
      if (!match) return closeBox();
      var type = (match[1] || '').toLowerCase();
      if (types.indexOf(type) === -1) return closeBox();
      var query = (match[2] || '').trim();
      if (type !== lastType || query !== lastQuery) {
        lastType = type;
        lastQuery = query;
        if (debounce) clearTimeout(debounce);
        debounce = setTimeout(function(){
          fetchMentions(type, query).then(function(items){
            if (!items.length) return openBox([], function(){});
            openBox(items, function(it){
              var startIndex = range.index - match[0].length;
              insertMention(it, range, startIndex, match[0].length);
            });
          });
        }, 120);
      }
    });

    quill.on('selection-change', function(range){
      if (!range) closeBox();
    });
  }

  function attachTextarea(textarea, opts) {
    if (!textarea) return;
    var types = (opts && opts.types) ? opts.types : [];
    var dropdown = createDropdown();
    var debounce = null;
    var keyHandler = null;
    var lastQuery = '';
    var lastType = '';

    function closeBox() {
      dropdown.style.display = 'none';
      if (keyHandler) {
        textarea.removeEventListener('keydown', keyHandler);
        keyHandler = null;
      }
    }

    function openBox(items, onPick) {
      renderList(dropdown, items, onPick);
      dropdown.style.display = 'block';
      positionDropdown(dropdown, textarea);
      if (keyHandler) textarea.removeEventListener('keydown', keyHandler);
      keyHandler = setupKeyboard(dropdown, items, onPick);
      textarea.addEventListener('keydown', keyHandler);
    }

    function insertToken(it, startIndex, tokenLen) {
      var token = '[@' + it.type + ':' + it.id + ']';
      var val = textarea.value;
      textarea.value = val.slice(0, startIndex) + token + ' ' + val.slice(startIndex + tokenLen);
      var caret = startIndex + token.length + 1;
      textarea.setSelectionRange(caret, caret);
      closeBox();
    }

    textarea.addEventListener('keyup', function(){
      var pos = textarea.selectionStart || 0;
      var text = textarea.value.slice(0, pos);
      var match = text.match(/@([a-z_]+)\s([^@\n]*)$/i);
      if (!match) return closeBox();
      var type = (match[1] || '').toLowerCase();
      if (types.indexOf(type) === -1) return closeBox();
      var query = (match[2] || '').trim();
      if (type !== lastType || query !== lastQuery) {
        lastType = type;
        lastQuery = query;
        if (debounce) clearTimeout(debounce);
        debounce = setTimeout(function(){
          fetchMentions(type, query).then(function(items){
            if (!items.length) return openBox([], function(){});
            openBox(items, function(it){
              var startIndex = pos - match[0].length;
              insertToken(it, startIndex, match[0].length);
            });
          });
        }, 120);
      }
    });

    textarea.addEventListener('blur', function(){ setTimeout(closeBox, 200); });
  }

  function attachAuto() {
    if (!document.querySelectorAll) return;
    document.querySelectorAll('[data-hg-mentions-quill]').forEach(function(el){
      var id = el.getAttribute('data-hg-mentions-quill');
      var q = window.__hgQuillMap && window.__hgQuillMap[id];
      if (q) attachQuill(q, { types: (el.getAttribute('data-mentions') || '').split(',').filter(Boolean) });
    });
    document.querySelectorAll('textarea.hg-mention-input').forEach(function(el){
      var types = (el.getAttribute('data-mentions') || '').split(',').filter(Boolean);
      attachTextarea(el, { types: types });
    });
  }

  window.hgMentions = {
    attachQuill: attachQuill,
    attachTextarea: attachTextarea,
    attachAuto: attachAuto
  };
})();
